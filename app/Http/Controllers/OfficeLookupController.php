<?php

namespace App\Http\Controllers;

use App\Models\OfficeLookupRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OfficeLookupController extends Controller
{
    public function show(string $token)
    {
        $lookup = OfficeLookupRequest::with(['lead.property', 'tenant'])
            ->where('token', $token)
            ->firstOrFail();

        return view('office-lookup.show', [
            'lookup' => $lookup,
            'lead' => $lookup->lead,
            'property' => $lookup->lead?->property,
        ]);
    }

    public function update(Request $request, string $token)
    {
        $lookup = OfficeLookupRequest::where('token', $token)->firstOrFail();

        if (! $lookup->isUsable()) {
            return redirect()->route('office-lookup.show', $token)
                ->with('error', __('This lookup link has expired or has already been completed.'));
        }

        $data = $request->validate([
            'owner_name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'secondary_phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string|max:2000',
        ]);

        $completed = DB::transaction(function () use ($lookup, $data) {
            $lookup = OfficeLookupRequest::whereKey($lookup->id)->lockForUpdate()->firstOrFail();

            if (! $lookup->isUsable()) {
                return false;
            }

            $nameParts = preg_split('/\s+/', trim($data['owner_name']), 2);
            $lead = $lookup->lead;
            $existingNotes = trim((string) $lead->notes);
            $lookupNote = trim((string) ($data['notes'] ?? ''));

            $lead->update([
                'first_name' => $nameParts[0] ?: 'Unknown',
                'last_name' => $nameParts[1] ?? '',
                'phone' => $data['phone'],
                'secondary_phone' => $data['secondary_phone'] ?? null,
                'email' => $data['email'] ?? null,
                'notes' => trim($existingNotes . ($lookupNote ? "\n\nOffice lookup notes: {$lookupNote}" : '')),
            ]);

            $lookup->update([
                'completed_at' => now(),
                'completed_payload' => $data,
            ]);

            OfficeLookupRequest::where('lead_id', $lead->id)
                ->whereKeyNot($lookup->id)
                ->whereNull('completed_at')
                ->update([
                    'completed_at' => now(),
                    'completed_payload' => ['invalidated_reason' => 'lead_updated_by_lookup'],
                ]);

            return true;
        });

        if (! $completed) {
            return redirect()->route('office-lookup.show', $token)
                ->with('error', __('This lookup link has expired or has already been completed.'));
        }

        return redirect()->route('office-lookup.show', $token)
            ->with('success', __('Thank you. The lead has been updated.'));
    }
}
