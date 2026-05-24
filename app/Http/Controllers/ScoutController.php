<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadPhoto;
use App\Models\Property;
use App\Models\ScoutLeadCapture;
use App\Models\ScoutPoint;
use App\Models\ScoutSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ScoutController extends Controller
{
    public function index()
    {
        $points = ScoutPoint::query()
            ->where('user_id', auth()->id())
            ->where('captured_at', '>=', now()->subDays(30))
            ->whereNotIn('id', ScoutLeadCapture::query()
                ->where('user_id', auth()->id())
                ->whereNotNull('scout_point_id')
                ->select('scout_point_id'))
            ->orderBy('captured_at')
            ->limit(3000)
            ->get()
            ->map(fn (ScoutPoint $point) => [
                'id' => $point->id,
                'previous_point_id' => $point->previous_point_id,
                'lat' => (float) $point->latitude,
                'lng' => (float) $point->longitude,
                'accuracy' => $point->accuracy ? (float) $point->accuracy : null,
                'captured_at' => optional($point->captured_at)->toIso8601String(),
            ]);

        $captures = ScoutLeadCapture::query()
            ->with('lead')
            ->where('user_id', auth()->id())
            ->latest()
            ->limit(300)
            ->get()
            ->map(fn (ScoutLeadCapture $capture) => [
                'id' => $capture->id,
                'lead_id' => $capture->lead_id,
                'lead_url' => route('leads.show', $capture->lead_id),
                'address' => $capture->address,
                'lat' => (float) $capture->latitude,
                'lng' => (float) $capture->longitude,
                'created_at' => $capture->created_at->toIso8601String(),
            ]);

        return response()
            ->view('scout.index', [
                'googleMapsKey' => config('services.google_maps.browser_key'),
                'existingPoints' => $points,
                'existingCaptures' => $captures,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function reverseGeocode(Request $request)
    {
        $data = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $response = Http::timeout(8)
            ->withHeaders([
                'User-Agent' => 'InsulaCRM Scout Utility (estatecrm.barberrylabs.dpdns.org)',
            ])
            ->get('https://nominatim.openstreetmap.org/reverse', [
                'format' => 'jsonv2',
                'lat' => $data['latitude'],
                'lon' => $data['longitude'],
                'addressdetails' => 1,
                'zoom' => 18,
            ]);

        if (! $response->ok()) {
            return response()->json(['message' => 'Address lookup failed'], 502);
        }

        $payload = $response->json();
        $address = $payload['address'] ?? [];

        return response()->json([
            'address' => $payload['display_name'] ?? null,
            'city' => $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['suburb'] ?? 'Pretoria',
            'state' => $address['state'] ?? 'Gauteng',
            'zip_code' => $address['postcode'] ?? '',
        ]);
    }

    public function startSession(Request $request)
    {
        $session = ScoutSession::create([
            'tenant_id' => auth()->user()->tenant_id,
            'user_id' => auth()->id(),
            'started_at' => now(),
        ]);

        return response()->json(['session_id' => $session->id]);
    }

    public function storePoint(Request $request)
    {
        $data = $request->validate([
            'session_id' => 'nullable|integer|exists:scout_sessions,id',
            'previous_point_id' => 'nullable|integer|exists:scout_points,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0|max:99999',
            'captured_at' => 'nullable|date',
        ]);

        $point = ScoutPoint::create([
            'tenant_id' => auth()->user()->tenant_id,
            'scout_session_id' => $data['session_id'] ?? null,
            'user_id' => auth()->id(),
            'previous_point_id' => $data['previous_point_id'] ?? null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'accuracy' => $data['accuracy'] ?? null,
            'captured_at' => isset($data['captured_at']) ? \Carbon\Carbon::parse($data['captured_at']) : now(),
        ]);

        return response()->json([
            'id' => $point->id,
            'previous_point_id' => $point->previous_point_id,
            'lat' => (float) $point->latitude,
            'lng' => (float) $point->longitude,
        ]);
    }

    public function captureLead(Request $request)
    {
        $data = $request->validate([
            'session_id' => 'nullable|integer|exists:scout_sessions,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:15360',
        ]);

        $result = DB::transaction(function () use ($request, $data) {
            $address = trim($data['address'] ?? '') ?: 'Scouted property ' . now()->format('Y-m-d H:i');

            $point = ScoutPoint::create([
                'tenant_id' => auth()->user()->tenant_id,
                'scout_session_id' => $data['session_id'] ?? null,
                'user_id' => auth()->id(),
                'previous_point_id' => null,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'captured_at' => now(),
            ]);

            $lead = Lead::create([
                'tenant_id' => auth()->user()->tenant_id,
                'agent_id' => auth()->id(),
                'first_name' => 'Unknown',
                'last_name' => 'Owner',
                'lead_source' => 'other',
                'status' => 'new',
                'contact_type' => 'seller_lead',
                'temperature' => 'warm',
                'notes' => "Created from Scout Utility at {$data['latitude']}, {$data['longitude']}.",
                'custom_fields' => [
                    'phone_contact_1' => false,
                    'whatsapp_intro_sent' => false,
                ],
            ]);

            Property::create([
                'tenant_id' => auth()->user()->tenant_id,
                'lead_id' => $lead->id,
                'address' => $address,
                'city' => ($data['city'] ?? null) ?: 'Pretoria',
                'state' => ($data['state'] ?? null) ?: 'Gauteng',
                'zip_code' => ($data['zip_code'] ?? null) ?: '',
                'property_type' => 'house',
                'listing_status' => 'active',
                'notes' => "Scout coordinates: {$data['latitude']}, {$data['longitude']}",
            ]);

            $path = null;

            if ($request->hasFile('photo')) {
                $file = $request->file('photo');
                $extension = $file->getClientOriginalExtension() ?: 'jpg';
                $filename = 'scout_' . Str::uuid() . '.' . $extension;
                $path = $file->storeAs("lead-photos/{$lead->id}", $filename, 'public');

                LeadPhoto::create([
                    'tenant_id' => auth()->user()->tenant_id,
                    'lead_id' => $lead->id,
                    'uploaded_by' => auth()->id(),
                    'filename' => $filename,
                    'original_name' => $file->getClientOriginalName() ?: $filename,
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'caption' => 'Scout house photo',
                ]);
            }

            ScoutLeadCapture::create([
                'tenant_id' => auth()->user()->tenant_id,
                'scout_point_id' => $point->id,
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'address' => $address,
                'photo_path' => $path,
            ]);

            return [$lead, $point, $address];
        });

        [$lead, $point, $address] = $result;

        return response()->json([
            'success' => true,
            'lead_id' => $lead->id,
            'lead_url' => route('leads.show', $lead),
            'point_id' => $point->id,
            'lat' => (float) $point->latitude,
            'lng' => (float) $point->longitude,
            'address' => $address,
        ]);
    }
}
