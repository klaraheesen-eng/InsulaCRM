@extends('layouts.app')

@section('title', __('Scout Utility'))
@section('page-title', __('Scout Utility'))

@push('styles')
<style>
    .scout-shell {
        position: relative;
        height: calc(100vh - 96px);
        min-height: 620px;
        margin: -1rem;
        overflow: hidden;
        background: #111827;
    }
    #scout-map { width: 100%; height: 100%; }
    .scout-toolbar {
        position: absolute;
        left: 12px;
        right: 12px;
        bottom: calc(12px + env(safe-area-inset-bottom));
        z-index: 5;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }
    .scout-status {
        position: absolute;
        top: 12px;
        left: 12px;
        right: 12px;
        z-index: 5;
        pointer-events: none;
    }
    .scout-status .alert { pointer-events: auto; box-shadow: 0 6px 20px rgba(0,0,0,.18); }
    .capture-sheet {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 6;
        background: var(--tblr-bg-surface, #fff);
        border-radius: 18px 18px 0 0;
        box-shadow: 0 -12px 30px rgba(0,0,0,.25);
        padding: 16px;
        padding-bottom: calc(16px + env(safe-area-inset-bottom));
        display: none;
    }
    .capture-sheet.show { display: block; }
    .capture-sheet textarea { min-height: 72px; }
    @media (max-width: 768px) {
        .page-body { margin-top: 0; }
        .scout-shell { height: calc(100vh - 64px); min-height: 520px; margin: -0.75rem; }
        .scout-toolbar { grid-template-columns: 1fr; }
        .scout-toolbar .btn { min-height: 48px; font-size: 1rem; }
    }
</style>
@endpush

@section('content')
<div class="scout-shell">
    <div id="scout-map"></div>

    <div class="scout-status">
        <div id="scout-alert" class="alert alert-info py-2 px-3 mb-0">
            {{ $googleMapsKey ? __('Loading map…') : __('Google Maps key is not configured.') }}
        </div>
    </div>

    <div class="scout-toolbar">
        <button type="button" id="start-scouting" class="btn btn-success btn-lg">
            {{ __('Start Scouting') }}
        </button>
        <button type="button" id="capture-house" class="btn btn-primary btn-lg" disabled>
            {{ __('House For Sale') }}
        </button>
        <button type="button" id="center-me" class="btn btn-outline-light">
            {{ __('Center On Me') }}
        </button>
        <button type="button" id="stop-scouting" class="btn btn-outline-light" disabled>
            {{ __('Stop') }}
        </button>
    </div>

    <form id="capture-sheet" class="capture-sheet" enctype="multipart/form-data">
        <h3 class="mb-2">{{ __('Capture House For Sale') }}</h3>
        <p class="text-secondary small mb-2">{{ __('Move the pin onto the house, confirm the address, then take a photo.') }}</p>
        <div class="mb-2">
            <label class="form-label">{{ __('Address') }}</label>
            <textarea id="capture-address" name="address" class="form-control" placeholder="{{ __('Address from pin') }}"></textarea>
        </div>
        <input type="hidden" id="capture-latitude" name="latitude">
        <input type="hidden" id="capture-longitude" name="longitude">
        <input type="hidden" id="capture-city" name="city">
        <input type="hidden" id="capture-state" name="state">
        <input type="hidden" id="capture-zip" name="zip_code">
        <div class="mb-3">
            <label class="form-label required">{{ __('House Photo') }}</label>
            <input id="capture-photo" type="file" name="photo" class="form-control" accept="image/*" capture="environment" required>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-fill">{{ __('Create Lead') }}</button>
            <button type="button" id="cancel-capture" class="btn btn-outline-secondary">{{ __('Cancel') }}</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
window.gm_authFailure = function () {
    var el = document.getElementById('scout-alert');
    if (el) {
        el.className = 'alert alert-danger py-2 px-3 mb-0';
        el.textContent = 'Google Maps could not load. The API key may need this CRM domain added to allowed referrers.';
    }
};
window.scoutConfig = {
    routes: {
        session: @json(route('scout.session')),
        point: @json(route('scout.points.store')),
        capture: @json(route('scout.captureLead')),
    },
    csrf: @json(csrf_token()),
    existingPoints: @json($existingPoints),
    existingCaptures: @json($existingCaptures),
};

(function () {
    let map, geocoder, currentMarker, captureMarker, livePolyline;
    let currentPosition = null;
    let watchId = null;
    let trackingTimer = null;
    let sessionId = null;
    let lastPointId = null;
    let tracking = false;
    let pendingAddressParts = {};
    const pointById = new Map();
    const path = [];

    const alertEl = document.getElementById('scout-alert');
    const startBtn = document.getElementById('start-scouting');
    const stopBtn = document.getElementById('stop-scouting');
    const captureBtn = document.getElementById('capture-house');
    const centerBtn = document.getElementById('center-me');
    const sheet = document.getElementById('capture-sheet');
    const addressEl = document.getElementById('capture-address');
    const latEl = document.getElementById('capture-latitude');
    const lngEl = document.getElementById('capture-longitude');
    const cityEl = document.getElementById('capture-city');
    const stateEl = document.getElementById('capture-state');
    const zipEl = document.getElementById('capture-zip');

    function setStatus(message, type = 'info') {
        alertEl.className = 'alert alert-' + type + ' py-2 px-3 mb-0';
        alertEl.textContent = message;
    }

    function authFetch(url, options = {}) {
        options.headers = Object.assign({
            'X-CSRF-TOKEN': window.scoutConfig.csrf,
            'Accept': 'application/json',
        }, options.headers || {});
        return fetch(url, options).then(async response => {
            if (!response.ok) {
                const text = await response.text();
                throw new Error(text || 'Request failed');
            }
            return response.json();
        });
    }

    function drawExisting() {
        const points = window.scoutConfig.existingPoints || [];
        points.forEach(p => pointById.set(Number(p.id), p));

        points.forEach(p => {
            if (!p.previous_point_id || !pointById.has(Number(p.previous_point_id))) return;
            const prev = pointById.get(Number(p.previous_point_id));
            new google.maps.Polyline({
                map,
                path: [{ lat: prev.lat, lng: prev.lng }, { lat: p.lat, lng: p.lng }],
                strokeColor: '#64748b',
                strokeOpacity: 0.65,
                strokeWeight: 4,
            });
        });

        (window.scoutConfig.existingCaptures || []).forEach(capture => {
            const marker = new google.maps.Marker({
                map,
                position: { lat: capture.lat, lng: capture.lng },
                title: capture.address || 'Scouted lead',
                icon: 'https://maps.google.com/mapfiles/ms/icons/green-dot.png',
            });
            const info = new google.maps.InfoWindow({
                content: `<strong>${capture.address || 'Scouted lead'}</strong><br><a href="${capture.lead_url}">Open lead</a>`,
            });
            marker.addListener('click', () => info.open({ map, anchor: marker }));
        });
    }

    function updateCurrentMarker(position) {
        currentPosition = position;
        const latLng = { lat: position.coords.latitude, lng: position.coords.longitude };
        if (!currentMarker) {
            currentMarker = new google.maps.Marker({
                map,
                position: latLng,
                title: 'You are here',
                icon: 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png',
            });
        } else {
            currentMarker.setPosition(latLng);
        }
        captureBtn.disabled = false;
        if (!path.length) {
            map.setCenter(latLng);
            map.setZoom(17);
        }
    }

    function requestLocation() {
        if (!navigator.geolocation) {
            setStatus('Location is not available on this device.', 'danger');
            return;
        }
        setStatus('Asking for location permission…');
        watchId = navigator.geolocation.watchPosition(
            pos => {
                updateCurrentMarker(pos);
                setStatus(tracking ? 'Scouting is running. Saving your route every 10 seconds.' : 'Location ready. Tap Start Scouting.', tracking ? 'success' : 'info');
            },
            err => setStatus('Location permission/error: ' + err.message, 'danger'),
            { enableHighAccuracy: true, maximumAge: 5000, timeout: 15000 }
        );
    }

    async function savePoint() {
        if (!currentPosition || !tracking) return;
        const body = {
            session_id: sessionId,
            previous_point_id: lastPointId,
            latitude: currentPosition.coords.latitude,
            longitude: currentPosition.coords.longitude,
            accuracy: currentPosition.coords.accuracy,
            captured_at: new Date().toISOString(),
        };
        const saved = await authFetch(window.scoutConfig.routes.point, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        const latLng = { lat: saved.lat, lng: saved.lng };
        path.push(latLng);
        if (path.length === 1) {
            livePolyline.setPath(path);
        } else {
            livePolyline.getPath().push(new google.maps.LatLng(saved.lat, saved.lng));
        }
        lastPointId = saved.id;
    }

    async function startTracking() {
        if (!currentPosition) {
            requestLocation();
            setStatus('Waiting for your location before starting…');
            return;
        }
        const res = await authFetch(window.scoutConfig.routes.session, { method: 'POST' });
        sessionId = res.session_id;
        tracking = true;
        startBtn.disabled = true;
        stopBtn.disabled = false;
        captureBtn.disabled = false;
        livePolyline = new google.maps.Polyline({
            map,
            path: [],
            strokeColor: '#2563eb',
            strokeOpacity: 0.95,
            strokeWeight: 5,
        });
        await savePoint();
        trackingTimer = setInterval(() => savePoint().catch(err => setStatus('Could not save route point: ' + err.message, 'danger')), 10000);
        setStatus('Scouting started. Route points save every 10 seconds.', 'success');
    }

    function stopTracking() {
        tracking = false;
        if (trackingTimer) clearInterval(trackingTimer);
        trackingTimer = null;
        startBtn.disabled = false;
        stopBtn.disabled = true;
        setStatus('Scouting stopped. You can start again when ready.', 'info');
    }

    function extractAddressParts(result) {
        const parts = { city: '', state: '', zip: '' };
        (result.address_components || []).forEach(component => {
            const types = component.types || [];
            if ((types.indexOf('locality') !== -1 || types.indexOf('sublocality') !== -1) && !parts.city) parts.city = component.long_name;
            if (types.indexOf('administrative_area_level_1') !== -1) parts.state = component.long_name;
            if (types.indexOf('postal_code') !== -1) parts.zip = component.long_name;
        });
        return parts;
    }

    function geocodePin(latLng) {
        latEl.value = latLng.lat();
        lngEl.value = latLng.lng();
        geocoder.geocode({ location: latLng }, (results, status) => {
            if (status === 'OK' && results && results[0]) {
                addressEl.value = results[0].formatted_address;
                pendingAddressParts = extractAddressParts(results[0]);
                cityEl.value = pendingAddressParts.city || 'Pretoria';
                stateEl.value = pendingAddressParts.state || 'Gauteng';
                zipEl.value = pendingAddressParts.zip || '';
            } else {
                addressEl.value = `${latLng.lat().toFixed(7)}, ${latLng.lng().toFixed(7)}`;
            }
        });
    }

    function beginCapture() {
        const position = currentPosition
            ? { lat: currentPosition.coords.latitude, lng: currentPosition.coords.longitude }
            : map.getCenter();
        if (captureMarker) captureMarker.setMap(null);
        captureMarker = new google.maps.Marker({
            map,
            position,
            draggable: true,
            title: 'Move me onto the house',
            icon: 'https://maps.google.com/mapfiles/ms/icons/red-dot.png',
        });
        map.panTo(position);
        sheet.classList.add('show');
        geocodePin(captureMarker.getPosition());
        captureMarker.addListener('dragend', () => geocodePin(captureMarker.getPosition()));
    }

    async function submitCapture(event) {
        event.preventDefault();
        const form = new FormData(sheet);
        form.append('session_id', sessionId || '');
        form.append('previous_point_id', lastPointId || '');
        const res = await authFetch(window.scoutConfig.routes.capture, { method: 'POST', body: form });
        if (livePolyline && res.lat && res.lng) {
            livePolyline.getPath().push(new google.maps.LatLng(res.lat, res.lng));
            path.push({ lat: res.lat, lng: res.lng });
        }
        lastPointId = res.point_id;
        if (captureMarker) {
            captureMarker.setIcon('https://maps.google.com/mapfiles/ms/icons/green-dot.png');
            captureMarker.setDraggable(false);
        }
        sheet.classList.remove('show');
        sheet.reset();
        setStatus('Lead created from scout capture. Opening lead…', 'success');
        setTimeout(() => { window.location.href = res.lead_url; }, 700);
    }

    window.initScoutMap = function () {
        try {
        const fallback = { lat: -25.785, lng: 28.282 };
        map = new google.maps.Map(document.getElementById('scout-map'), {
            center: fallback,
            zoom: 15,
            mapTypeId: 'roadmap',
            fullscreenControl: false,
            streetViewControl: false,
            mapTypeControl: true,
        });
        geocoder = new google.maps.Geocoder();
        drawExisting();
        requestLocation();
        } catch (error) {
            setStatus('Map failed to initialise: ' + error.message, 'danger');
        }
    };

    startBtn.addEventListener('click', () => startTracking().catch(err => setStatus('Could not start scouting: ' + err.message, 'danger')));
    stopBtn.addEventListener('click', stopTracking);
    centerBtn.addEventListener('click', () => {
        if (currentPosition) map.panTo({ lat: currentPosition.coords.latitude, lng: currentPosition.coords.longitude });
        else requestLocation();
    });
    captureBtn.addEventListener('click', beginCapture);
    document.getElementById('cancel-capture').addEventListener('click', () => sheet.classList.remove('show'));
    sheet.addEventListener('submit', event => submitCapture(event).catch(err => setStatus('Could not create lead: ' + err.message, 'danger')));
})();
</script>
@if($googleMapsKey)
<script async defer src="https://maps.googleapis.com/maps/api/js?key={{ urlencode($googleMapsKey) }}&callback=initScoutMap&region=za" onerror="window.gm_authFailure && window.gm_authFailure()"></script>
@endif
@endpush
