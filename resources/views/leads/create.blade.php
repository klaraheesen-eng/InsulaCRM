@extends('layouts.app')

@section('title', __('Add Lead'))
@section('page-title', __('Add New Lead'))

@section('breadcrumbs')
<li class="breadcrumb-item"><a href="{{ route('leads.index') }}">{{ __('Leads') }}</a></li>
<li class="breadcrumb-item active" aria-current="page">{{ __('Add New Lead') }}</li>
@endsection

@section('content')
<form action="{{ route('leads.store') }}" method="POST">
    @csrf

    <!-- Property Information -->
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">{{ __('Property Information') }}</h3>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">{{ __('Address') }}</label>
                    <input type="text" name="property_address" class="form-control @error('property_address') is-invalid @enderror" value="{{ old('property_address') }}" placeholder="{{ __('Street address') }}" autofocus>
                    @error('property_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <small class="text-secondary">{{ __('You can create the lead from the address first and add owner details later.') }}</small>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('City') }}</label>
                    <input type="text" name="property_city" class="form-control @error('property_city') is-invalid @enderror" value="{{ old('property_city', 'Pretoria') }}">
                    @error('property_city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ Fmt::stateLabel() }}</label>
                    <input type="text" name="property_state" class="form-control @error('property_state') is-invalid @enderror" value="{{ old('property_state', 'Gauteng') }}">
                    @error('property_state') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ Fmt::postalCodeLabel() }}</label>
                    <input type="text" name="property_zip_code" class="form-control @error('property_zip_code') is-invalid @enderror" value="{{ old('property_zip_code') }}">
                    @error('property_zip_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-8">
                    <label class="form-label">{{ __('Existing Listing Link') }}</label>
                    <input type="url" name="existing_listing_link" class="form-control @error('existing_listing_link') is-invalid @enderror" value="{{ old('existing_listing_link') }}" placeholder="https://www.property24.com/...">
                    @error('existing_listing_link') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>

    <!-- Contact Information -->
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">{{ __('Owner / Contact Information') }}</h3>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">{{ __('First Name') }}</label>
                    <input type="text" name="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name') }}" placeholder="{{ __('Unknown') }}">
                    @error('first_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Last Name') }}</label>
                    <input type="text" name="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name') }}" placeholder="{{ __('Owner') }}">
                    @error('last_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label">{{ __('Primary Phone') }}</label>
                    <input type="tel" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}" placeholder="{{ __('072 123 4567') }}">
                    @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('Secondary Phone') }}</label>
                    <input type="tel" name="secondary_phone" class="form-control @error('secondary_phone') is-invalid @enderror" value="{{ old('secondary_phone') }}" placeholder="{{ __('Optional second number') }}">
                    @error('secondary_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('Email') }}</label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('Timezone') }}</label>
                    <select name="timezone" class="form-select">
                        <option value="">{{ __('-- Auto-detect later --') }}</option>
                        @foreach(Fmt::timezones() as $region => $zones)
                            <optgroup label="{{ $region }}">
                                @foreach($zones as $tz)
                                    <option value="{{ $tz }}" {{ old('timezone') == $tz ? 'selected' : '' }}>{{ $tz }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <small class="text-secondary">{{ __('Required for :law calling-hours compliance', ['law' => Fmt::complianceLawName()]) }}</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Lead Classification -->
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">{{ ($businessMode ?? 'wholesale') === 'realestate' ? __('Lead Details') : __('Lead Classification') }}</h3>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label required">{{ __('Lead Source') }}</label>
                    <select name="lead_source" class="form-select @error('lead_source') is-invalid @enderror" required>
                        @foreach(\App\Services\CustomFieldService::getOptions('lead_source') as $val => $label)
                            <option value="{{ $val }}" {{ old('lead_source') == $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('lead_source') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label required">{{ __('Status') }}</label>
                    <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                        @foreach(\App\Services\CustomFieldService::getOptions('lead_status') as $val => $label)
                            <option value="{{ $val }}" {{ old('status', 'new') == $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label required">{{ __('Temperature') }}</label>
                    <select name="temperature" class="form-select @error('temperature') is-invalid @enderror" required>
                        @foreach(['hot' => __('Hot'), 'warm' => __('Warm'), 'cold' => __('Cold')] as $val => $label)
                            <option value="{{ $val }}" {{ old('temperature', 'cold') == $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('temperature') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label required">{{ __('Assigned Agent') }}</label>
                    <select name="agent_id" class="form-select @error('agent_id') is-invalid @enderror" required>
                        @foreach($agents as $agent)
                            <option value="{{ $agent->id }}" {{ old('agent_id') == $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                        @endforeach
                    </select>
                    @error('agent_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <label class="form-check mb-0">
                        <input type="hidden" name="do_not_contact" value="0">
                        <input type="checkbox" name="do_not_contact" value="1" class="form-check-input" {{ old('do_not_contact') ? 'checked' : '' }}>
                        <span class="form-check-label">{{ __('Do Not Contact') }}</span>
                    </label>
                </div>
                @if(($businessMode ?? 'wholesale') === 'realestate')
                <div class="col-md-4">
                    <label class="form-label">{{ __('Contact Type') }}</label>
                    <select name="contact_type" class="form-select">
                        <option value="">{{ __('— Select —') }}</option>
                        @foreach(['seller_lead' => __('Seller Lead'), 'buyer_lead' => __('Buyer Lead'), 'active_client' => __('Active Client'), 'past_client' => __('Past Client')] as $val => $label)
                            <option value="{{ $val }}" {{ old('contact_type') == $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Custom Fields -->
    @php $customFieldDefs = \App\Models\CustomFieldDefinition::forEntity('lead'); @endphp
    @if($customFieldDefs->count())
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">{{ __('Additional Information') }}</h3>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                @foreach($customFieldDefs as $cfd)
                <div class="col-md-4 mb-3">
                    <label class="form-label {{ $cfd->required ? 'required' : '' }}">{{ __($cfd->name) }}</label>
                    @if($cfd->field_type === 'text')
                        <input type="text" name="custom_fields[{{ $cfd->slug }}]" class="form-control" value="{{ old('custom_fields.' . $cfd->slug) }}" {{ $cfd->required ? 'required' : '' }}>
                    @elseif($cfd->field_type === 'textarea')
                        <textarea name="custom_fields[{{ $cfd->slug }}]" class="form-control" rows="2" {{ $cfd->required ? 'required' : '' }}>{{ old('custom_fields.' . $cfd->slug) }}</textarea>
                    @elseif($cfd->field_type === 'number')
                        <input type="number" step="any" name="custom_fields[{{ $cfd->slug }}]" class="form-control" value="{{ old('custom_fields.' . $cfd->slug) }}" {{ $cfd->required ? 'required' : '' }}>
                    @elseif($cfd->field_type === 'date')
                        <input type="date" name="custom_fields[{{ $cfd->slug }}]" class="form-control" value="{{ old('custom_fields.' . $cfd->slug) }}" {{ $cfd->required ? 'required' : '' }}>
                    @elseif($cfd->field_type === 'select')
                        <select name="custom_fields[{{ $cfd->slug }}]" class="form-select" {{ $cfd->required ? 'required' : '' }}>
                            <option value="">{{ __('-- Select --') }}</option>
                            @foreach($cfd->options ?? [] as $opt)
                                <option value="{{ $opt }}" {{ old('custom_fields.' . $cfd->slug) == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                            @endforeach
                        </select>
                    @elseif($cfd->field_type === 'checkbox')
                        <div>
                            <input type="hidden" name="custom_fields[{{ $cfd->slug }}]" value="0">
                            <label class="form-check">
                                <input type="checkbox" name="custom_fields[{{ $cfd->slug }}]" value="1" class="form-check-input" {{ old('custom_fields.' . $cfd->slug) ? 'checked' : '' }}>
                                <span class="form-check-label">{{ __('Yes') }}</span>
                            </label>
                        </div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- Notes -->
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">{{ __('Notes') }}</h3>
        </div>
        <div class="card-body">
            <label for="lead-notes" class="visually-hidden">{{ __('Notes') }}</label>
            <textarea name="notes" id="lead-notes" class="form-control" rows="4" placeholder="{{ ($businessMode ?? 'wholesale') === 'realestate' ? __('Notes about the client, property interests, timeline, etc.') : __('Initial notes about the lead, seller situation, etc.') }}">{{ old('notes') }}</textarea>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('leads.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary">{{ __('Create Lead') }}</button>
    </div>
</form>
@endsection
