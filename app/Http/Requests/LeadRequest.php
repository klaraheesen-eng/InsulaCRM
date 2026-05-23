<?php

namespace App\Http\Requests;

use App\Models\CustomFieldDefinition;
use App\Services\CustomFieldService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $leadSources = implode(',', CustomFieldService::getValidSlugs('lead_source'));
        $leadStatuses = implode(',', CustomFieldService::getValidSlugs('lead_status'));

        $rules = [
            'agent_id' => ['required', Rule::exists('users', 'id')->where('tenant_id', auth()->user()->tenant_id)],
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'property_address' => 'nullable|string|max:255',
            'property_city' => 'nullable|string|max:100',
            'property_state' => 'nullable|string|max:100',
            'property_zip_code' => 'nullable|string|max:20',
            'existing_listing_link' => 'nullable|string|max:2048',
            'listing_price' => 'nullable|numeric|min:0|max:999999999999.99',
            'phone' => 'nullable|string|max:50',
            'secondary_phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'lead_source' => "required|in:{$leadSources}",
            'status' => "required|in:{$leadStatuses}",
            'temperature' => 'required|in:hot,warm,cold',
            'timezone' => 'nullable|string|max:50',
            'do_not_contact' => 'nullable|boolean',
            'contact_type' => 'nullable|string|in:seller_lead,buyer_lead,active_client,past_client',
            'notes' => 'nullable|string',
            'custom_fields' => 'nullable|array',
            'custom_fields.*' => 'nullable',
        ];

        // Add validation for required custom fields
        $definitions = CustomFieldDefinition::forEntity('lead');
        foreach ($definitions as $field) {
            if ($field->required) {
                $rules["custom_fields.{$field->slug}"] = 'required';
            }
            if ($field->field_type === 'number') {
                $rules["custom_fields.{$field->slug}"] = ($field->required ? 'required' : 'nullable') . '|numeric';
            }
            if ($field->field_type === 'date') {
                $rules["custom_fields.{$field->slug}"] = ($field->required ? 'required' : 'nullable') . '|date';
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'agent_id.required' => 'Please assign an agent.',
            'agent_id.exists' => 'Selected agent is invalid.',
        ];
    }
}
