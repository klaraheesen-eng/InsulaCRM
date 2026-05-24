<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Property Owner Lookup') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler.min.css">
</head>
<body class="d-flex flex-column bg-light">
<div class="page page-center">
    <div class="container-tight py-4">
        <div class="card card-md">
            <div class="card-body">
                <h1 class="h2 mb-2">{{ __('Property Owner Lookup') }}</h1>
                <p class="text-secondary mb-3">{{ __('Please look up the owner/client details for this scouted property and submit the contact number.') }}</p>

                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                <div class="mb-3 p-3 bg-azure-lt rounded">
                    <div class="text-secondary small text-uppercase">{{ __('Property address') }}</div>
                    <div class="fw-bold">{{ $property?->full_address ?: ($property?->address ?: __('Address not available')) }}</div>
                    @php
                        preg_match('/Scout coordinates:\s*([-0-9.]+),\s*([-0-9.]+)/', (string) ($property?->notes ?? ''), $coords);
                        $mapsUrl = !empty($coords) ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($coords[1] . ',' . $coords[2]) : 'https://www.google.com/maps/search/?api=1&query=' . urlencode($property?->full_address ?? $property?->address ?? '');
                    @endphp
                    <a class="btn btn-outline-primary btn-sm mt-2" href="{{ $mapsUrl }}" target="_blank" rel="noopener">{{ __('Open in Google Maps') }}</a>
                </div>

                @if(! $lookup->isUsable())
                    <div class="alert alert-warning">
                        {{ $lookup->completed_at ? __('This lookup has already been completed.') : __('This lookup link has expired.') }}
                    </div>
                @else
                    <form method="POST" action="{{ route('office-lookup.update', $lookup->token) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label required">{{ __('Owner / Client Name') }}</label>
                            <input name="owner_name" class="form-control @error('owner_name') is-invalid @enderror" value="{{ old('owner_name') }}" required>
                            @error('owner_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">{{ __('Contact Number') }}</label>
                            <input name="phone" type="tel" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}" required>
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Second Contact Number') }}</label>
                            <input name="secondary_phone" type="tel" class="form-control @error('secondary_phone') is-invalid @enderror" value="{{ old('secondary_phone') }}">
                            @error('secondary_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Email') }}</label>
                            <input name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Notes') }}</label>
                            <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
                        </div>
                        <button class="btn btn-primary w-100" type="submit">{{ __('Update Lead Details') }}</button>
                    </form>
                @endif
            </div>
        </div>
        <div class="text-center text-secondary mt-3 small">{{ __('This secure link expires automatically.') }}</div>
    </div>
</div>
</body>
</html>
