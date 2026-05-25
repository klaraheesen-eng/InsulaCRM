<?php

namespace App\Http\Controllers;

use App\Events\LeadStatusChanged;
use App\Facades\Hooks;
use App\Http\Requests\LeadRequest;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\LeadClaim;
use App\Models\LeadPhoto;
use App\Models\OfficeLookupRequest;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\BusinessModeService;
use App\Services\CustomFieldService;
use App\Services\AssignmentHistoryService;
use App\Services\MotivationScoreService;
use App\Services\LeadTransactionStatusService;
use Illuminate\Support\Facades\DB;
use App\Notifications\LeadAssigned;
use Illuminate\Support\Facades\Storage;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Lead::class);

        $query = Lead::with(['agent', 'property'])->withCount('lists');

        if (auth()->user()->isAgent()) {
            $query->where('agent_id', auth()->id());
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('secondary_phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhereHas('property', function ($propertyQuery) use ($search) {
                      $propertyQuery->where('address', 'like', "%{$search}%")
                          ->orWhere('city', 'like', "%{$search}%")
                          ->orWhere('state', 'like', "%{$search}%")
                          ->orWhere('zip_code', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('source')) {
            $query->where('lead_source', $request->source);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('temperature')) {
            $query->where('temperature', $request->temperature);
        }

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->agent_id);
        }

        // Stacked leads filter
        if ($request->filled('stacked') && $request->stacked) {
            $query->has('lists', '>=', 2)->orderByDesc('motivation_score');
        }

        // DNC filter
        if ($request->filled('dnc')) {
            $query->where('do_not_contact', true);
        }

        if ($request->filled('contact_type')) {
            $query->where('contact_type', $request->contact_type);
        }

        // Sorting
        if ($request->filled('sort')) {
            $allowedSorts = ['id', 'first_name', 'lead_source', 'status', 'temperature', 'motivation_score', 'created_at'];
            $col = $request->input('sort');
            $dir = strtolower($request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
            if (in_array($col, $allowedSorts)) {
                $query->reorder($col, $dir);
            }
        }

        $totalLeads = (clone $query)->count();
        $leads = $query->latest()->paginate(max($totalLeads, 1));

        $agents = !auth()->user()->isAgent() ? $this->getAgents() : collect();

        return view('leads.index', compact('leads', 'agents'));
    }

    public function bulkAction(Request $request)
    {
        $this->authorize('bulkUpdate', Lead::class);

        $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
            'action' => 'required|in:assign,status,delete',
            'agent_id' => 'required_if:action,assign|nullable|integer|exists:users,id',
            'status' => 'required_if:action,status|nullable|string',
        ]);

        $query = Lead::whereIn('id', $request->ids);

        // Agent scoping - agents can only bulk-act on their own leads
        if (auth()->user()->isAgent()) {
            $query->where('agent_id', auth()->id());
        }

        $leads = $query->get();
        $count = $leads->count();

        switch ($request->action) {
            case 'assign':
                // Verify target agent belongs to same tenant
                $targetAgent = User::where('id', $request->agent_id)
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->firstOrFail();
                foreach ($leads as $lead) {
                    $lead->update(['agent_id' => $targetAgent->id]);
                }
                $message = "{$count} lead(s) assigned to {$targetAgent->name}.";
                break;

            case 'status':
                $validStatuses = CustomFieldService::getValidSlugs('lead_status');
                if (!in_array($request->status, $validStatuses)) {
                    return redirect()->back()->with('error', 'Invalid status.');
                }
                foreach ($leads as $lead) {
                    $oldStatus = $lead->status;
                    $lead->update(['status' => $request->status]);
                    if ($oldStatus !== $request->status) {
                        event(new LeadStatusChanged($lead, $oldStatus));
                        Hooks::doAction('lead.status_changed', $lead, $oldStatus);
                    }
                }
                $message = "{$count} lead(s) status updated.";
                break;

            case 'delete':
                foreach ($leads as $lead) {
                    $lead->delete();
                    AuditLog::log('lead.deleted', $lead);
                }
                $message = "{$count} lead(s) deleted.";
                break;
        }

        return redirect()->route('leads.index')->with('success', $message);
    }

    public function create()
    {
        $this->authorize('create', Lead::class);

        $agents = $this->getAgents();
        return view('leads.create', compact('agents'));
    }

    public function store(LeadRequest $request)
    {
        $this->authorize('create', Lead::class);

        $data = $request->validated();
        $propertyData = [
            'address' => $data['property_address'] ?? null,
            'city' => $data['property_city'] ?? null,
            'state' => $data['property_state'] ?? null,
            'zip_code' => $data['property_zip_code'] ?? null,
            'existing_listing_link' => $data['existing_listing_link'] ?? null,
            'listing_price' => $data['listing_price'] ?? null,
        ];
        unset($data['property_address'], $data['property_city'], $data['property_state'], $data['property_zip_code'], $data['existing_listing_link'], $data['listing_price']);

        $data['first_name'] = filled($data['first_name'] ?? null) ? $data['first_name'] : 'Unknown';
        $data['last_name'] = filled($data['last_name'] ?? null) ? $data['last_name'] : 'Owner';
        $data['tenant_id'] = auth()->user()->tenant_id;

        // Handle custom fields — store as JSON, remove empty values
        if (isset($data['custom_fields'])) {
            $data['custom_fields'] = array_filter($data['custom_fields'], fn($v) => $v !== null && $v !== '');
        }
        if (filled($propertyData['existing_listing_link'] ?? null) || filled($propertyData['listing_price'] ?? null)) {
            $data['custom_fields'] = $data['custom_fields'] ?? [];
            if (filled($propertyData['existing_listing_link'] ?? null)) {
                $data['custom_fields']['existing_listing_link'] = $propertyData['existing_listing_link'];
            }
            if (filled($propertyData['listing_price'] ?? null)) {
                $data['custom_fields']['listing_price'] = $propertyData['listing_price'];
            }
        }

        if (auth()->user()->isAgent()) {
            $data['agent_id'] = auth()->id();
        }

        $lead = Lead::create($data);

        if (filled($propertyData['address'] ?? null)) {
            Property::create([
                'tenant_id' => auth()->user()->tenant_id,
                'lead_id' => $lead->id,
                'address' => $propertyData['address'],
                'city' => $propertyData['city'] ?: 'Pretoria',
                'state' => $propertyData['state'] ?: 'Gauteng',
                'zip_code' => $propertyData['zip_code'] ?: '',
                'property_type' => 'house',
                'list_price' => $propertyData['listing_price'] ?? null,
                'listing_status' => filled($propertyData['existing_listing_link'] ?? null) ? 'active' : null,
                'notes' => $propertyData['existing_listing_link'] ?? null,
            ]);
        }

        app(MotivationScoreService::class)->recalculate($lead);

        // AI auto-qualify temperature
        if (auth()->user()->tenant->ai_enabled) {
            \App\Jobs\AutoQualifyLead::dispatch($lead, auth()->user()->tenant);
        }

        AuditLog::log('lead.created', $lead);
        Hooks::doAction('lead.created', $lead);

        \App\Services\WebhookService::dispatch('lead.created', [
            'lead_id' => $lead->id,
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'secondary_phone' => $lead->secondary_phone,
            'source' => $lead->lead_source,
            'status' => $lead->status,
        ], auth()->user()->tenant_id);

        // Notify assigned agent
        if ($lead->agent_id) {
            $tenant = auth()->user()->tenant;
            if ($tenant->wantsNotification('lead_assigned')) {
                $lead->agent->notify(new LeadAssigned($lead, $tenant));
            }
        }

        return redirect()->route('leads.show', $lead)->with('success', __('Lead created successfully.'));
    }

    public function show(Lead $lead)
    {
        $this->authorize('view', $lead);
        $lead->load(['agent', 'property', 'activities', 'tasks', 'deals', 'lists', 'photos.uploader', 'sequenceEnrollments.sequence.steps']);
        $sequences = \App\Models\Sequence::where('is_active', true)->get();
        $assignmentHistory = app(AssignmentHistoryService::class)->getHistory($lead);
        return view('leads.show', compact('lead', 'sequences', 'assignmentHistory'));
    }

    public function officeLookupWhatsApp(Lead $lead)
    {
        $this->authorize('update', $lead);

        if (! $this->isUnknownOwnerLookupCandidate($lead)) {
            return redirect()->route('leads.show', $lead)
                ->with('error', __('Office lookup requests are only available for unknown-owner leads without contact details.'));
        }

        $tenant = auth()->user()->tenant;
        $officePhone = $this->formatOfficeLookupWhatsAppPhone((string) $tenant->office_address_lookup_phone);

        if (! $officePhone) {
            return redirect()->route('leads.show', $lead)
                ->with('error', __('Add the office address lookup WhatsApp number in Settings first.'));
        }

        $lead->loadMissing('property');
        $lookup = OfficeLookupRequest::createForLead($lead, auth()->id());
        $property = $lead->property;
        $address = $property?->full_address ?: $property?->address ?: __('Address not available');
        $coordinates = null;

        if ($property && preg_match('/Scout coordinates:\s*([-0-9.]+),\s*([-0-9.]+)/', (string) $property->notes, $matches)) {
            $coordinates = $matches[1] . ',' . $matches[2];
        }

        $mapsUrl = $coordinates
            ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($coordinates)
            : 'https://www.google.com/maps/search/?api=1&query=' . urlencode($address);

        $message = implode("\n\n", array_filter([
            "Hi, please look up the owner/client details and contact number for this property.",
            "Lead: #{$lead->id} ({$lead->full_name})",
            "Address: {$address}",
            "Google Maps: {$mapsUrl}",
            "Update form (expires in 72 hours): " . route('office-lookup.show', $lookup->token),
        ]));

        Activity::create([
            'tenant_id' => auth()->user()->tenant_id,
            'lead_id' => $lead->id,
            'agent_id' => auth()->id(),
            'type' => 'note',
            'subject' => __('Office lookup requested'),
            'body' => __('Office lookup WhatsApp prepared for :address. Link expires in 72 hours.', ['address' => $address]),
            'logged_at' => now(),
        ]);

        return redirect()->away('https://wa.me/' . $officePhone . '?text=' . rawurlencode($message));
    }

    private function isUnknownOwnerLookupCandidate(Lead $lead): bool
    {
        $name = strtolower(trim($lead->full_name));

        return ($name === 'unknown owner' || str_contains($name, 'unknown'))
            && blank($lead->phone)
            && blank($lead->secondary_phone)
            && blank($lead->email);
    }

    private function formatOfficeLookupWhatsAppPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            return '27' . substr($digits, 1);
        }

        return $digits;
    }

    public function createTransaction(Lead $lead)
    {
        $this->authorize('update', $lead);

        if (! BusinessModeService::isRealEstate(auth()->user()->tenant)) {
            return redirect()->route('leads.show', $lead)
                ->with('error', __('Listing transactions are only available in real estate mode.'));
        }

        $existingDeal = $lead->deals()
            ->whereNotIn('stage', ['closed_won', 'closed_lost'])
            ->latest('updated_at')
            ->first();

        if ($existingDeal) {
            app(LeadTransactionStatusService::class)->syncForTransaction($existingDeal);

            return redirect()->route('deals.show', $existingDeal)
                ->with('info', __('This lead already has an active transaction.'));
        }

        $propertyAddress = $lead->property?->address;
        $dealTitle = $propertyAddress
            ? $propertyAddress . ' - Listing Agreement'
            : $lead->full_name . ' - Listing Agreement';

        $deal = Deal::create([
            'tenant_id' => auth()->user()->tenant_id,
            'lead_id' => $lead->id,
            'agent_id' => $lead->agent_id ?: (auth()->user()->isAgent() ? auth()->id() : null),
            'title' => $dealTitle,
            'stage' => 'listing_agreement',
            'stage_changed_at' => now(),
            'listing_date' => now()->toDateString(),
        ]);

        app(LeadTransactionStatusService::class)->syncForTransaction($deal);

        Activity::create([
            'tenant_id' => auth()->user()->tenant_id,
            'lead_id' => $lead->id,
            'deal_id' => $deal->id,
            'agent_id' => auth()->id(),
            'type' => 'stage_change',
            'subject' => __('Transaction created'),
            'body' => __('Lead converted to a transaction at Listing Agreement stage.'),
            'logged_at' => now(),
        ]);

        AuditLog::log('deal.created_from_lead', $deal, null, [
            'lead_id' => $lead->id,
            'stage' => 'listing_agreement',
        ]);

        return redirect()->route('deals.show', $deal)
            ->with('success', __('Transaction created at Listing Agreement stage.'));
    }

    public function edit(Lead $lead)
    {
        $this->authorize('update', $lead);
        $agents = $this->getAgents();
        return view('leads.edit', compact('lead', 'agents'));
    }

    public function update(LeadRequest $request, Lead $lead)
    {
        $this->authorize('update', $lead);
        $oldStatus = $lead->status;

        $data = $request->validated();
        if (isset($data['custom_fields'])) {
            $data['custom_fields'] = array_filter($data['custom_fields'], fn($v) => $v !== null && $v !== '');
        }

        $lead->update($data);
        app(MotivationScoreService::class)->recalculate($lead);

        if ($oldStatus !== $lead->status) {
            event(new LeadStatusChanged($lead, $oldStatus));
            Hooks::doAction('lead.status_changed', $lead, $oldStatus);
        }

        AuditLog::log('lead.updated', $lead);
        Hooks::doAction('lead.updated', $lead);

        \App\Services\WebhookService::dispatch('lead.updated', [
            'lead_id' => $lead->id,
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'status' => $lead->status,
            'temperature' => $lead->temperature,
        ], auth()->user()->tenant_id);

        return redirect()->route('leads.show', $lead)->with('success', __('Lead updated successfully.'));
    }

    public function destroy(Lead $lead)
    {
        $this->authorize('delete', $lead);
        $lead->delete();
        AuditLog::log('lead.deleted', $lead);

        return redirect()->route('leads.index')->with('success', __('Lead deleted successfully.'));
    }

    public function updateStatus(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);
        $validStatuses = implode(',', \App\Services\CustomFieldService::getValidSlugs('lead_status'));
        $request->validate(['status' => "required|in:{$validStatuses}"]);

        $oldStatus = $lead->status;
        $lead->update(['status' => $request->status]);

        if ($oldStatus !== $lead->status) {
            event(new LeadStatusChanged($lead, $oldStatus));
            Hooks::doAction('lead.status_changed', $lead, $oldStatus);
        }

        return response()->json(['success' => true]);
    }

    public function updateTemperature(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);

        $request->validate([
            'temperature' => 'required|in:hot,warm,cold',
        ]);

        $lead->update(['temperature' => $request->temperature]);

        \App\Services\WebhookService::dispatch('lead.updated', [
            'lead_id' => $lead->id,
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'status' => $lead->status,
            'temperature' => $lead->temperature,
        ], auth()->user()->tenant_id);

        return response()->json(['success' => true]);
    }

    public function updateCustomField(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);

        $fieldRules = [
            'phone_contact_1' => 'boolean',
            'phone_contact_notes' => 'nullable|string|max:2000',
            'whatsapp_intro_sent' => 'boolean',
            'follow_up_date' => 'nullable|date',
            'listing_appointment_date' => 'nullable|date',
            'listing_notes' => 'nullable|string|max:2000',
            'existing_listing_link' => 'nullable|string|max:2048',
            'listing_price' => 'nullable|numeric|min:0|max:999999999999.99',
        ];

        $request->validate([
            'field' => 'required|string|in:' . implode(',', array_keys($fieldRules)),
            'value' => $fieldRules[$request->input('field')] ?? 'nullable',
        ]);

        $field = $request->input('field');
        $value = $request->input('value');

        if (in_array($field, ['phone_contact_1', 'whatsapp_intro_sent'], true)) {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        } elseif (in_array($field, ['follow_up_date', 'listing_appointment_date'], true) && blank($value)) {
            $value = null;
        } elseif ($field === 'listing_price' && blank($value)) {
            $value = null;
        }

        $customFields = $lead->custom_fields ?? [];
        $customFields[$field] = $value;
        $lead->update(['custom_fields' => $customFields]);

        return response()->json(['success' => true, 'value' => $value]);
    }

    public function claim(Lead $lead)
    {
        $this->authorize('claim', $lead);

        // Only works for shark_tank or hybrid distribution
        $tenant = auth()->user()->tenant;
        if (!in_array($tenant->distribution_method, ['shark_tank', 'hybrid'])) {
            return response()->json(['error' => __('Claiming not enabled')], 422);
        }

        // Database-level locking to prevent race conditions
        $claimed = DB::transaction(function () use ($lead) {
            $locked = Lead::where('id', $lead->id)
                ->whereNull('agent_id')
                ->lockForUpdate()
                ->first();

            if (!$locked) {
                return false;
            }

            $locked->update(['agent_id' => auth()->id()]);

            LeadClaim::create([
                'tenant_id' => auth()->user()->tenant_id,
                'lead_id' => $lead->id,
                'agent_id' => auth()->id(),
                'claimed' => true,
            ]);

            return true;
        });

        if ($claimed) {
            return response()->json(['success' => true, 'message' => __('Lead claimed successfully.')]);
        }

        return response()->json(['error' => __('Lead already claimed.')], 409);
    }

    public function uploadPhoto(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);

        $request->validate([
            'photos' => 'required|array|max:10',
            'photos.*' => 'image|mimes:jpg,jpeg,png,gif,webp|max:10240',
            'captions' => 'nullable|array',
            'captions.*' => 'nullable|string|max:255',
        ]);

        $uploaded = 0;
        foreach ($request->file('photos') as $i => $file) {
            $filename = uniqid('photo_') . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs("lead-photos/{$lead->id}", $filename, 'public');

            LeadPhoto::create([
                'tenant_id' => auth()->user()->tenant_id,
                'lead_id' => $lead->id,
                'uploaded_by' => auth()->id(),
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'caption' => $request->input("captions.{$i}"),
            ]);
            $uploaded++;
        }

        return redirect()->route('leads.show', $lead)
            ->with('success', "{$uploaded} photo(s) uploaded.");
    }

    public function deletePhoto(Lead $lead, LeadPhoto $photo)
    {
        $this->authorize('update', $lead);

        if ($photo->lead_id !== $lead->id || $photo->tenant_id !== auth()->user()->tenant_id) {
            abort(404);
        }

        Storage::disk('public')->delete($photo->path);
        if ($photo->thumbnail_path) {
            Storage::disk('public')->delete($photo->thumbnail_path);
        }
        $photo->delete();

        return redirect()->route('leads.show', $lead)
            ->with('success', 'Photo deleted.');
    }

    public function export(Request $request)
    {
        $this->authorize('export', Lead::class);

        $query = Lead::with('agent');

        if (auth()->user()->isAgent()) {
            $query->where('agent_id', auth()->id());
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('source')) {
            $query->where('lead_source', $request->source);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('temperature')) {
            $query->where('temperature', $request->temperature);
        }

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->agent_id);
        }

        if ($request->filled('stacked') && $request->stacked) {
            $query->has('lists', '>=', 2)->orderByDesc('motivation_score');
        }

        if ($request->filled('dnc')) {
            $query->where('do_not_contact', true);
        }

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                __('First Name'), __('Last Name'), __('Phone'), __('Email'),
                __('Source'), __('Status'), __('Temperature'), __('Score'),
                __('Agent'), __('Created Date'),
            ]);
            foreach ($query->with('agent')->latest()->cursor() as $lead) {
                fputcsv($handle, [
                    $lead->first_name,
                    $lead->last_name,
                    $lead->phone,
                    $lead->email,
                    ucwords(str_replace('_', ' ', $lead->lead_source)),
                    ucwords(str_replace('_', ' ', $lead->status)),
                    ucfirst($lead->temperature),
                    $lead->motivation_score,
                    $lead->agent->name ?? '',
                    $lead->created_at?->format('Y-m-d'),
                ]);
            }
            fclose($handle);
        }, 'leads-export-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function getAgents()
    {
        if (auth()->user()->isAgent()) {
            return collect([auth()->user()]);
        }

        $agentRoleNames = \App\Services\BusinessModeService::getAgentRoleNames();
        $agentRoleIds = Role::whereIn('name', $agentRoleNames)->pluck('id');
        return User::where('tenant_id', auth()->user()->tenant_id)
            ->whereIn('role_id', $agentRoleIds)
            ->get();
    }
}
