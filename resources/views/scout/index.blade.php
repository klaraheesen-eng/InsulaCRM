@extends('layouts.app')

@section('title', __('Scout Utility'))
@section('page-title', __('Scout Utility'))

@push('styles')
<style>
    body.scout-page {
        overflow: hidden;
        overscroll-behavior: none;
    }
    body.scout-page .page-wrapper {
        display: flex;
        flex-direction: column;
        height: 100vh;
        height: 100dvh;
        overflow: hidden;
    }
    body.scout-page .page-body {
        flex: 1 1 auto;
        min-height: 0;
        margin: 0;
        padding: 0;
        overflow: hidden;
    }
    body.scout-page .page-body > .container-xl {
        height: 100%;
        max-width: none;
        padding: 0;
    }
    body.scout-page #quick-add-fab,
    body.scout-page #pwa-install-banner,
    body.scout-page #pwa-update-banner { display: none !important; }
    .scout-shell {
        position: relative;
        height: var(--scout-available-height, calc(100dvh - 96px));
        min-height: 0;
        margin: 0;
        overflow: hidden;
        background: #111827;
        touch-action: none;
    }
    #scout-map { width: 100%; height: 100%; }
    .capture-center-pin {
        position: absolute;
        left: 50%;
        top: calc((100% - var(--capture-sheet-height, 0px)) / 2);
        z-index: 7;
        width: 36px;
        height: 36px;
        transform: translate(-50%, -100%);
        pointer-events: none;
        display: none;
        filter: drop-shadow(0 3px 6px rgba(0,0,0,.35));
    }
    .capture-center-pin.show { display: block; }
    .capture-center-pin::before {
        content: '';
        position: absolute;
        left: 50%;
        top: 2px;
        width: 24px;
        height: 24px;
        background: #dc2626;
        border: 3px solid #fff;
        border-radius: 50% 50% 50% 0;
        transform: translateX(-50%) rotate(-45deg);
    }
    .capture-center-pin::after {
        content: '';
        position: absolute;
        left: 50%;
        top: 10px;
        width: 8px;
        height: 8px;
        background: #fff;
        border-radius: 50%;
        transform: translateX(-50%);
    }
    .scout-toolbar {
        position: absolute;
        left: 12px;
        right: 12px;
        bottom: calc(12px + env(safe-area-inset-bottom));
        z-index: 5;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .scout-toolbar-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }
    .scout-toolbar-row .btn {
        min-height: 46px;
        font-size: clamp(0.8rem, 3.2vw, 1rem);
        padding-inline: 0.5rem;
        white-space: nowrap;
    }
    .scout-status {
        position: absolute;
        top: 12px;
        left: 12px;
        right: 12px;
        z-index: 5;
        pointer-events: none;
    }
    .scout-status .alert {
        pointer-events: auto;
        box-shadow: 0 8px 24px rgba(0,0,0,.35);
        border: 1px solid rgba(255,255,255,.16);
        background: rgba(15, 23, 42, .94);
        color: #fff;
        font-weight: 700;
        text-shadow: 0 1px 1px rgba(0,0,0,.45);
        backdrop-filter: blur(8px);
    }
    .scout-status .alert.alert-success { background: rgba(22, 101, 52, .94); }
    .scout-status .alert.alert-danger { background: rgba(153, 27, 27, .96); }
    .scout-status .alert.alert-warning { background: rgba(146, 64, 14, .96); }
    #center-me {
        background: rgba(255,255,255,.96);
        color: #0f172a;
        border-color: #fff;
        font-weight: 800;
        box-shadow: 0 6px 16px rgba(0,0,0,.25);
    }
    #center-me.is-following {
        background: #2563eb;
        border-color: #2563eb;
        color: #fff;
    }
    .capture-sheet {
        position: absolute;
        left: 0;
        right: 0;
        bottom: var(--capture-keyboard-inset, 0px);
        z-index: 6;
        background: var(--tblr-bg-surface, #fff);
        border-radius: 18px 18px 0 0;
        box-shadow: 0 -12px 30px rgba(0,0,0,.25);
        padding: 12px;
        padding-bottom: calc(12px + env(safe-area-inset-bottom));
        display: none;
        max-height: min(72dvh, calc(var(--scout-available-height, 100dvh) - 48px));
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
        transition: bottom .15s ease-out;
    }
    .capture-sheet.show { display: block; }
    .capture-sheet h3 { font-size: 1.05rem; margin-bottom: .25rem !important; }
    .capture-sheet .form-label { margin-bottom: .25rem; }
    .capture-sheet .mb-2 { margin-bottom: .45rem !important; }
    .capture-sheet .mb-3 { margin-bottom: .55rem !important; }
    .scout-shell.is-capturing .scout-toolbar,
    .scout-shell.is-capturing .scout-status { display: none; }
    body.scout-capture-open #quick-add-fab,
    body.scout-capture-open #pwa-install-banner,
    body.scout-capture-open #pwa-update-banner { display: none !important; }
    .capture-address-bar {
        min-height: 42px;
        font-size: 1rem;
    }
    @media (max-width: 768px) {
        body.scout-page .navbar {
            min-height: 52px;
            padding-top: max(4px, env(safe-area-inset-top));
            padding-bottom: 4px;
        }
        body.scout-page .navbar .container-fluid { min-height: 44px; }
        body.scout-page .navbar-brand img { max-height: 34px !important; max-width: 120px !important; }
        body.scout-page .navbar-toggler { padding: .25rem .45rem; }
        body.scout-page .page-header { display: none !important; }
        body.scout-page .page-body { margin-top: 0; }
        .scout-toolbar .btn { min-height: 46px; font-size: 1rem; }
        .capture-sheet { padding: 10px; padding-bottom: calc(10px + env(safe-area-inset-bottom)); }
        .capture-sheet .capture-help { display: none; }
    }
</style>
@endpush

@section('content')
<div class="scout-shell">
    <div id="scout-map"></div>
    <video id="scout-keep-awake" playsinline webkit-playsinline muted loop aria-hidden="true" style="position:fixed;width:1px;height:1px;opacity:0;pointer-events:none;z-index:-1;"></video>
    <div id="capture-center-pin" class="capture-center-pin" aria-hidden="true"></div>

    <div class="scout-status">
        <div id="scout-alert" class="alert alert-info py-2 px-3 mb-0">
            {{ $googleMapsKey ? __('Loading map…') : __('Google Maps key is not configured.') }}
        </div>
    </div>

    <div class="scout-toolbar">
        <button type="button" id="capture-house" class="btn btn-primary btn-lg w-100" disabled>
            {{ __('House For Sale') }}
        </button>
        <div class="scout-toolbar-row">
            <button type="button" id="center-me" class="btn" aria-pressed="false">
                {{ __('Center') }}
            </button>
            <button type="button" id="start-scouting" class="btn btn-success">
                {{ __('Start') }}
            </button>
        </div>
    </div>

    <form id="capture-sheet" class="capture-sheet" enctype="multipart/form-data">
        <h3 class="mb-2">{{ __('Capture House For Sale') }}</h3>
        <p class="text-secondary small mb-2 capture-help">{{ __('Move the red pin onto the house and confirm the address. A photo is optional.') }}</p>
        <div class="mb-2">
            <label class="form-label">{{ __('Address') }}</label>
            <input id="capture-address" name="address" class="form-control capture-address-bar" placeholder="{{ __('Address from pin') }}">
        </div>
        <input type="hidden" id="capture-latitude" name="latitude">
        <input type="hidden" id="capture-longitude" name="longitude">
        <input type="hidden" id="capture-city" name="city">
        <input type="hidden" id="capture-state" name="state">
        <input type="hidden" id="capture-zip" name="zip_code">
        <div class="mb-3">
            <label class="form-label">{{ __('House Photo') }} <span class="text-secondary">({{ __('optional') }})</span></label>
            <input id="capture-photo" type="file" name="photo" class="form-control" accept="image/*" capture="environment">
        </div>
        <div class="d-flex gap-2">
            <button type="submit" id="submit-capture" class="btn btn-primary flex-fill">{{ __('Create Lead') }}</button>
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
        reverseGeocode: @json(route('scout.reverseGeocode')),
    },
    csrf: @json(csrf_token()),
    existingPoints: @json($existingPoints),
    existingCaptures: @json($existingCaptures),
};

(function () {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistrations()
            .then(registrations => registrations.forEach(registration => registration.unregister()))
            .catch(() => {});
    }
    if (window.caches) {
        caches.keys()
            .then(keys => keys.filter(key => key.startsWith('insulacrm-')).forEach(key => caches.delete(key)))
            .catch(() => {});
    }

    let map, geocoder, pinProjectionOverlay, currentMarker, captureMarker, livePolyline;
    let currentPosition = null;
    let captureMode = false;
    let hasCenteredOnLocation = false;
    let geocodeTimer = null;
    let geocodeRequestId = 0;
    let watchId = null;
    let savingPoint = false;
    let wakeLock = null;
    let keepAwakeWanted = false;
    let keepAwakeCanvas = null;
    let keepAwakeStream = null;
    let keepAwakeTick = null;
    let sessionId = null;
    let lastPointId = null;
    let tracking = false;
    let followingLocation = false;
    let pendingAddressParts = {};
    const ROUTE_SAVE_MIN_METERS = 15;
    const pointById = new Map();
    const path = [];

    const shellEl = document.querySelector('.scout-shell');
    document.body.classList.add('scout-page');
    const alertEl = document.getElementById('scout-alert');
    const startBtn = document.getElementById('start-scouting');
    const captureBtn = document.getElementById('capture-house');
    const centerBtn = document.getElementById('center-me');
    const keepAwakeVideo = document.getElementById('scout-keep-awake');
    const sheet = document.getElementById('capture-sheet');
    const submitBtn = document.getElementById('submit-capture');
    const cancelBtn = document.getElementById('cancel-capture');
    const submitBtnDefaultHtml = submitBtn.innerHTML;
    let captureSubmitting = false;
    const centerPinEl = document.getElementById('capture-center-pin');
    const addressEl = document.getElementById('capture-address');
    const latEl = document.getElementById('capture-latitude');
    const lngEl = document.getElementById('capture-longitude');
    const cityEl = document.getElementById('capture-city');
    const stateEl = document.getElementById('capture-state');
    const zipEl = document.getElementById('capture-zip');

    function syncScoutViewport() {
        if (!shellEl) return;
        const viewport = window.visualViewport;
        const top = shellEl.getBoundingClientRect().top;
        const height = viewport ? viewport.height + viewport.offsetTop : window.innerHeight;
        const available = Math.max(260, Math.round(height - top));
        shellEl.style.setProperty('--scout-available-height', available + 'px');
    }

    function syncCaptureKeyboard() {
        if (!shellEl || !sheet.classList.contains('show')) {
            shellEl?.style.removeProperty('--capture-keyboard-inset');
            return;
        }

        const viewport = window.visualViewport;
        const inset = viewport ? Math.max(0, Math.round(window.innerHeight - viewport.height - viewport.offsetTop)) : 0;
        shellEl.style.setProperty('--capture-keyboard-inset', inset + 'px');
        updateCaptureLayout();

        if (document.activeElement === addressEl) {
            requestAnimationFrame(() => addressEl.scrollIntoView({ block: 'center', behavior: 'smooth' }));
        }
    }

    function syncScoutLayout() {
        syncScoutViewport();
        syncCaptureKeyboard();
    }

    syncScoutLayout();
    window.addEventListener('resize', syncScoutLayout);
    window.addEventListener('orientationchange', () => setTimeout(syncScoutLayout, 250));
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', syncScoutLayout);
        window.visualViewport.addEventListener('scroll', syncScoutLayout);
    }
    addressEl.addEventListener('focus', () => setTimeout(syncCaptureKeyboard, 100));
    addressEl.addEventListener('blur', () => setTimeout(syncCaptureKeyboard, 250));

    function setStatus(message, type = 'info') {
        alertEl.className = 'alert alert-' + type + ' py-2 px-3 mb-0';
        alertEl.textContent = message;
    }

    function setFollowLocation(on) {
        followingLocation = !!on;
        centerBtn.classList.toggle('is-following', followingLocation);
        centerBtn.setAttribute('aria-pressed', followingLocation ? 'true' : 'false');
        centerBtn.textContent = followingLocation ? '{{ __('Following') }}' : '{{ __('Center') }}';
    }

    function updateScoutToggleUi() {
        if (tracking) {
            startBtn.textContent = '{{ __('Stop') }}';
            startBtn.classList.remove('btn-success');
            startBtn.classList.add('btn-danger');
        } else {
            startBtn.textContent = '{{ __('Start') }}';
            startBtn.classList.remove('btn-danger');
            startBtn.classList.add('btn-success');
        }
        startBtn.disabled = false;
    }

    function setCaptureSubmitting(busy) {
        captureSubmitting = busy;
        submitBtn.disabled = busy;
        cancelBtn.disabled = busy;
        submitBtn.innerHTML = busy
            ? '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>{{ __('Creating…') }}'
            : submitBtnDefaultHtml;
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

    function distanceMeters(lat1, lng1, lat2, lng2) {
        const earthRadius = 6371000;
        const toRad = degrees => (degrees * Math.PI) / 180;
        const dLat = toRad(lat2 - lat1);
        const dLng = toRad(lng2 - lng1);
        const a = Math.sin(dLat / 2) ** 2
            + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
        return earthRadius * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    async function requestScreenWakeLock() {
        if (!keepAwakeWanted || !('wakeLock' in navigator)) return;
        try {
            wakeLock = await navigator.wakeLock.request('screen');
            wakeLock.addEventListener('release', () => { wakeLock = null; });
        } catch (error) {
            wakeLock = null;
        }
    }

    function startVideoKeepAwake() {
        if (!keepAwakeVideo || keepAwakeStream || !HTMLCanvasElement.prototype.captureStream) return;
        try {
            keepAwakeCanvas = document.createElement('canvas');
            keepAwakeCanvas.width = 2;
            keepAwakeCanvas.height = 2;
            const ctx = keepAwakeCanvas.getContext('2d');
            let frame = 0;
            const drawFrame = () => {
                ctx.fillStyle = frame % 2 ? '#000001' : '#000000';
                ctx.fillRect(0, 0, 2, 2);
                frame += 1;
            };
            drawFrame();
            keepAwakeTick = setInterval(drawFrame, 15000);
            keepAwakeStream = keepAwakeCanvas.captureStream(1);
            keepAwakeVideo.srcObject = keepAwakeStream;
            keepAwakeVideo.muted = true;
            keepAwakeVideo.playsInline = true;
            keepAwakeVideo.play().catch(() => {});
        } catch (error) {
            keepAwakeStream = null;
        }
    }

    function acquireKeepAwake() {
        keepAwakeWanted = true;
        requestScreenWakeLock();
        startVideoKeepAwake();
    }

    function releaseKeepAwake() {
        keepAwakeWanted = false;
        if (wakeLock) {
            wakeLock.release().catch(() => {});
            wakeLock = null;
        }
        if (keepAwakeTick) clearInterval(keepAwakeTick);
        keepAwakeTick = null;
        if (keepAwakeStream) keepAwakeStream.getTracks().forEach(track => track.stop());
        keepAwakeStream = null;
        keepAwakeCanvas = null;
        if (keepAwakeVideo) {
            keepAwakeVideo.pause();
            keepAwakeVideo.srcObject = null;
        }
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
                strokeColor: '#16a34a',
                strokeOpacity: 0.9,
                strokeWeight: 5,
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

    function currentLocationIcon() {
        return {
            path: google.maps.SymbolPath.CIRCLE,
            scale: 11,
            fillColor: '#4285f4',
            fillOpacity: 1,
            strokeColor: '#ffffff',
            strokeWeight: 3,
        };
    }

    function updateCurrentMarker(position) {
        currentPosition = position;
        const latLng = { lat: position.coords.latitude, lng: position.coords.longitude };
        if (!currentMarker) {
            currentMarker = new google.maps.Marker({
                map,
                position: latLng,
                title: 'You are here',
                icon: currentLocationIcon(),
                optimized: false,
                zIndex: 999,
            });
        } else {
            currentMarker.setPosition(latLng);
        }
        currentMarker.setVisible(!captureMode);
        captureBtn.disabled = false;
    }

    const IPHONE_LOCATION_HINT = 'On iPhone: Settings → Privacy & Security → Location Services → Safari Websites → While Using, and turn Precise Location ON. In Safari: tap aA → Website Settings → Location → Allow, then reload.';

    function centerMapOnPosition(position) {
        if (!map || !position) return;
        const latLng = { lat: position.coords.latitude, lng: position.coords.longitude };
        map.setCenter(latLng);
        map.setZoom(17);
        hasCenteredOnLocation = true;
    }

    function getPositionOnce(options) {
        return new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(resolve, reject, options);
        });
    }

    function locationErrorMessage(err) {
        if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost') {
            return 'Location needs HTTPS. Open the secure CRM URL, then tap Center again.';
        }

        if (err && err.code === 1) {
            return 'Location is blocked for this site. ' + IPHONE_LOCATION_HINT;
        }

        if (err && err.code === 2) {
            return 'Location unavailable. Move to an open area, check Location Services are on, then tap Center. ' + IPHONE_LOCATION_HINT;
        }

        if (err && err.code === 3) {
            return 'Location timed out. Try Center again outdoors or near a window. ' + IPHONE_LOCATION_HINT;
        }

        return 'Location error: ' + (err && err.message ? err.message : 'Could not get your position.');
    }

    function startLocationWatch() {
        if (watchId !== null) return;
        watchId = navigator.geolocation.watchPosition(
            pos => {
                updateCurrentMarker(pos);
                if (followingLocation && !captureMode && map) {
                    map.panTo({ lat: pos.coords.latitude, lng: pos.coords.longitude });
                }
                if (tracking) {
                    savePoint().catch(err => setStatus('Could not save route point: ' + err.message, 'danger'));
                }
                if (!captureMode) {
                    setStatus(tracking ? 'Scouting is running. Route saves every 15 m.' : 'Location ready. Tap Start.', tracking ? 'success' : 'info');
                }
            },
            err => {
                setFollowLocation(false);
                setStatus(locationErrorMessage(err), 'danger');
            },
            { enableHighAccuracy: false, maximumAge: 10000, timeout: 20000 }
        );
    }

    async function requestLocation(options = {}) {
        const centerOnSuccess = !!options.centerOnSuccess;

        if (!navigator.geolocation) {
            setStatus('Location is not available on this device.', 'danger');
            return;
        }

        setStatus('Getting your location… keep Safari open and tap Allow if asked.');
        const preciseOptions = { enableHighAccuracy: true, maximumAge: 5000, timeout: 12000 };
        const approximateOptions = { enableHighAccuracy: false, maximumAge: 30000, timeout: 20000 };
        let position;

        try {
            position = await getPositionOnce(preciseOptions);
        } catch (err) {
            if (err && (err.code === 2 || err.code === 3)) {
                setStatus('Precise location failed. Trying approximate location…', 'warning');
                try {
                    position = await getPositionOnce(approximateOptions);
                } catch (fallbackErr) {
                    setStatus(locationErrorMessage(fallbackErr), 'danger');
                    return;
                }
            } else {
                setStatus(locationErrorMessage(err), 'danger');
                return;
            }
        }

        updateCurrentMarker(position);
        startLocationWatch();
        if (centerOnSuccess || (!captureMode && !hasCenteredOnLocation && !path.length)) {
            centerMapOnPosition(position);
        }
        setStatus(tracking ? 'Scouting is running. Route saves every 15 m.' : 'Location ready. Tap Start.', tracking ? 'success' : 'info');
    }

    async function savePoint({ force = false } = {}) {
        if (!currentPosition || !tracking || savingPoint) return;

        const lat = currentPosition.coords.latitude;
        const lng = currentPosition.coords.longitude;
        const last = path.length ? path[path.length - 1] : null;

        if (!force && last && distanceMeters(last.lat, last.lng, lat, lng) < ROUTE_SAVE_MIN_METERS) {
            return;
        }

        savingPoint = true;
        try {
            const body = {
                session_id: sessionId,
                previous_point_id: lastPointId,
                latitude: lat,
                longitude: lng,
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
        } finally {
            savingPoint = false;
        }
    }

    async function startTracking() {
        acquireKeepAwake();
        if (!currentPosition) {
            setStatus('Waiting for your location before starting…');
            await requestLocation({ centerOnSuccess: true });
            if (!currentPosition) {
                releaseKeepAwake();
                updateScoutToggleUi();
                return;
            }
        }
        let res;
        try {
            res = await authFetch(window.scoutConfig.routes.session, { method: 'POST' });
        } catch (error) {
            releaseKeepAwake();
            throw error;
        }
        sessionId = res.session_id;
        tracking = true;
        captureBtn.disabled = false;
        updateScoutToggleUi();
        livePolyline = new google.maps.Polyline({
            map,
            path: [],
            strokeColor: '#2563eb',
            strokeOpacity: 0.95,
            strokeWeight: 5,
        });
        await savePoint({ force: true });
        setStatus('Scouting started. Route saves when you move 15 m or more.', 'success');
    }

    function stopTracking() {
        tracking = false;
        releaseKeepAwake();
        updateScoutToggleUi();
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

    function applyAddressResult(address, parts = {}) {
        addressEl.value = address || `${Number(latEl.value).toFixed(7)}, ${Number(lngEl.value).toFixed(7)}`;
        pendingAddressParts = parts || {};
        cityEl.value = pendingAddressParts.city || 'Pretoria';
        stateEl.value = pendingAddressParts.state || 'Gauteng';
        zipEl.value = pendingAddressParts.zip || '';
    }

    async function fallbackReverseGeocode(latLng, requestId) {
        const params = new URLSearchParams({
            latitude: latLng.lat(),
            longitude: latLng.lng(),
        });
        const result = await authFetch(`${window.scoutConfig.routes.reverseGeocode}?${params.toString()}`);
        if (requestId !== geocodeRequestId) return;
        applyAddressResult(result.address, {
            city: result.city || 'Pretoria',
            state: result.state || 'Gauteng',
            zip: result.zip_code || '',
        });
    }

    function getPinTipContainerPixel() {
        const mapRect = map.getDiv().getBoundingClientRect();
        const pinRect = centerPinEl.getBoundingClientRect();

        return new google.maps.Point(
            pinRect.left + (pinRect.width / 2) - mapRect.left,
            pinRect.bottom - mapRect.top
        );
    }

    function getPinnedLatLng() {
        const projection = pinProjectionOverlay && pinProjectionOverlay.getProjection();
        if (!projection || !centerPinEl.classList.contains('show')) return map.getCenter();

        return projection.fromContainerPixelToLatLng(getPinTipContainerPixel()) || map.getCenter();
    }

    function panMapSoPinTipIsAt(latLng) {
        const projection = pinProjectionOverlay && pinProjectionOverlay.getProjection();
        const target = latLng instanceof google.maps.LatLng ? latLng : new google.maps.LatLng(latLng.lat, latLng.lng);

        if (!projection || !centerPinEl.classList.contains('show')) {
            map.setCenter(target);
            return;
        }

        const pinTip = getPinTipContainerPixel();
        const targetPixel = projection.fromLatLngToContainerPixel(target);
        const centerPixel = projection.fromLatLngToContainerPixel(map.getCenter());
        const newCenter = projection.fromContainerPixelToLatLng(new google.maps.Point(
            centerPixel.x + (targetPixel.x - pinTip.x),
            centerPixel.y + (targetPixel.y - pinTip.y)
        ));

        map.setCenter(newCenter || target);
    }

    function setCurrentMarkerVisible(visible) {
        if (currentMarker) currentMarker.setVisible(!!visible);
    }

    function geocodePin(latLng) {
        const requestId = ++geocodeRequestId;
        latEl.value = latLng.lat();
        lngEl.value = latLng.lng();
        addressEl.value = 'Looking up address…';
        geocoder.geocode({ location: latLng }, (results, status) => {
            if (requestId !== geocodeRequestId) return;
            if (status === 'OK' && results && results[0]) {
                applyAddressResult(results[0].formatted_address, extractAddressParts(results[0]));
            } else {
                fallbackReverseGeocode(latLng, requestId).catch(() => {
                    if (requestId !== geocodeRequestId) return;
                    applyAddressResult(`${latLng.lat().toFixed(7)}, ${latLng.lng().toFixed(7)}`, {});
                });
            }
        });
    }

    function updateCaptureFromPin(delay = 350) {
        if (!captureMode || !map) return;
        if (geocodeTimer) clearTimeout(geocodeTimer);
        geocodeTimer = setTimeout(() => geocodePin(getPinnedLatLng()), delay);
    }

    function updateCaptureLayout() {
        const sheetHeight = sheet.classList.contains('show') ? sheet.offsetHeight : 0;
        shellEl.style.setProperty('--capture-sheet-height', sheetHeight + 'px');
    }

    function closeCapture() {
        captureMode = false;
        centerPinEl.classList.remove('show');
        sheet.classList.remove('show');
        shellEl.classList.remove('is-capturing');
        document.body.classList.remove('scout-capture-open');
        shellEl.style.removeProperty('--capture-keyboard-inset');
        updateCaptureLayout();
        if (geocodeTimer) clearTimeout(geocodeTimer);
        geocodeTimer = null;
        setCaptureSubmitting(false);
        if (currentPosition) setCurrentMarkerVisible(true);
        if (!tracking) setStatus('Location ready. Tap House For Sale to capture another property.', 'info');
    }

    function beginCapture() {
        const position = currentPosition
            ? new google.maps.LatLng(currentPosition.coords.latitude, currentPosition.coords.longitude)
            : map.getCenter();
        if (captureMarker) {
            captureMarker.setMap(null);
            captureMarker = null;
        }
        captureMode = true;
        centerPinEl.classList.add('show');
        sheet.classList.add('show');
        shellEl.classList.add('is-capturing');
        document.body.classList.add('scout-capture-open');
        shellEl.style.removeProperty('--capture-keyboard-inset');
        updateCaptureLayout();
        setCurrentMarkerVisible(false);
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                updateCaptureLayout();
                panMapSoPinTipIsAt(position);
                updateCaptureFromPin(0);
            });
        });
        setStatus('Move the red pin onto the house. The address updates below.', 'info');
    }

    async function submitCapture(event) {
        event.preventDefault();
        if (captureSubmitting) return;

        setCaptureSubmitting(true);
        try {
            const form = new FormData(sheet);
            form.append('session_id', sessionId || '');
            form.append('previous_point_id', lastPointId || '');
            const res = await authFetch(window.scoutConfig.routes.capture, { method: 'POST', body: form });

            if (livePolyline && res.lat && res.lng) {
                livePolyline.getPath().push(new google.maps.LatLng(res.lat, res.lng));
                path.push({ lat: res.lat, lng: res.lng });
            }
            lastPointId = res.point_id;
            const marker = new google.maps.Marker({
                map,
                position: { lat: res.lat, lng: res.lng },
                title: res.address || 'Scouted lead',
                icon: 'https://maps.google.com/mapfiles/ms/icons/green-dot.png',
            });
            if (res.lead_url) {
                const info = new google.maps.InfoWindow({
                    content: `<strong>${res.address || 'Scouted lead'}</strong><br><a href="${res.lead_url}">Open lead</a>`,
                });
                marker.addListener('click', () => info.open({ map, anchor: marker }));
            }

            closeCapture();
            sheet.reset();
            setStatus(tracking ? 'Lead created. Tracking is still running — tap House For Sale for the next property.' : 'Lead created. Tap House For Sale to capture another property.', 'success');
        } catch (err) {
            setStatus('Could not create lead: ' + err.message, 'danger');
        } finally {
            setCaptureSubmitting(false);
        }
    }

    window.initScoutMap = function () {
        try {
        const fallback = { lat: -25.785, lng: 28.282 };
        map = new google.maps.Map(document.getElementById('scout-map'), {
            center: fallback,
            zoom: 15,
            mapTypeId: 'hybrid',
            fullscreenControl: false,
            streetViewControl: false,
            mapTypeControl: !window.matchMedia('(max-width: 768px)').matches,
            gestureHandling: 'greedy',
        });
        geocoder = new google.maps.Geocoder();
        pinProjectionOverlay = new google.maps.OverlayView();
        pinProjectionOverlay.onAdd = function () {};
        pinProjectionOverlay.draw = function () {};
        pinProjectionOverlay.onRemove = function () {};
        pinProjectionOverlay.setMap(map);
        map.addListener('idle', () => updateCaptureFromPin());
        map.addListener('tilesloaded', syncScoutViewport);
        captureBtn.disabled = false;
        drawExisting();
        syncScoutViewport();
        setStatus('Tap Center to follow your location, or Start to begin scouting.', 'info');
        } catch (error) {
            setStatus('Map failed to initialise: ' + error.message, 'danger');
        }
    };

    startBtn.addEventListener('click', () => {
        if (tracking) {
            stopTracking();
            return;
        }
        startBtn.disabled = true;
        startTracking().catch(err => {
            tracking = false;
            releaseKeepAwake();
            updateScoutToggleUi();
            setStatus('Could not start scouting: ' + err.message, 'danger');
        });
    });
    centerBtn.addEventListener('click', () => {
        if (followingLocation) {
            setFollowLocation(false);
            return;
        }
        setFollowLocation(true);
        requestLocation({ centerOnSuccess: true }).catch(err => {
            setFollowLocation(false);
            setStatus('Could not get location: ' + err.message, 'danger');
        });
    });
    captureBtn.addEventListener('click', beginCapture);
    document.getElementById('cancel-capture').addEventListener('click', closeCapture);
    window.addEventListener('resize', updateCaptureLayout);
    window.addEventListener('pagehide', releaseKeepAwake);
    window.addEventListener('beforeunload', releaseKeepAwake);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && tracking) {
            acquireKeepAwake();
        }
    });
    sheet.addEventListener('submit', event => submitCapture(event).catch(err => setStatus('Could not create lead: ' + err.message, 'danger')));
})();
</script>
@if($googleMapsKey)
<script async defer src="https://maps.googleapis.com/maps/api/js?key={{ urlencode($googleMapsKey) }}&callback=initScoutMap&region=za" onerror="window.gm_authFailure && window.gm_authFailure()"></script>
@endif
@endpush
