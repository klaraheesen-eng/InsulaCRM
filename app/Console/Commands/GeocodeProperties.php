<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Models\ScoutLeadCapture;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GeocodeProperties extends Command
{
    protected $signature = 'properties:geocode {--limit=100 : Maximum missing-coordinate properties to geocode}';

    protected $description = 'Fill property latitude/longitude for Scout map display.';

    public function handle(): int
    {
        $backfilled = $this->backfillFromScoutCaptures();
        $this->info("Backfilled {$backfilled} properties from Scout captures.");

        $limit = max(0, (int) $this->option('limit'));
        if ($limit === 0) {
            return self::SUCCESS;
        }

        $geocoded = 0;
        $properties = Property::query()
            ->with('lead:id,tenant_id,agent_id,first_name,last_name')
            ->whereNull('latitude')
            ->whereNull('longitude')
            ->whereNotNull('address')
            ->oldest('id')
            ->limit($limit)
            ->get();

        foreach ($properties as $property) {
            $coords = $this->geocode($property->full_address);
            if (! $coords) {
                $this->warn("No coordinates for property {$property->id}: {$property->full_address}");
                continue;
            }

            $property->forceFill([
                'latitude' => $coords['lat'],
                'longitude' => $coords['lng'],
                'geocoded_at' => now(),
            ])->save();

            $geocoded++;
            $this->line("Geocoded property {$property->id}: {$property->full_address}");
            usleep(150000);
        }

        $this->info("Geocoded {$geocoded} properties from addresses.");

        return self::SUCCESS;
    }

    private function backfillFromScoutCaptures(): int
    {
        $count = 0;
        $captures = ScoutLeadCapture::query()
            ->whereNotNull('lead_id')
            ->latest('id')
            ->get()
            ->unique('lead_id');

        foreach ($captures as $capture) {
            $property = Property::query()
                ->where('lead_id', $capture->lead_id)
                ->whereNull('latitude')
                ->whereNull('longitude')
                ->first();

            if (! $property) {
                continue;
            }

            $property->forceFill([
                'latitude' => $capture->latitude,
                'longitude' => $capture->longitude,
                'geocoded_at' => $capture->created_at ?? now(),
            ])->save();
            $count++;
        }

        return $count;
    }

    private function geocode(string $address): ?array
    {
        $address = trim($address);
        if ($address === '') {
            return null;
        }

        $googleKey = config('services.google_maps.server_key') ?: config('services.google_maps.browser_key');
        if ($googleKey) {
            $response = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $address,
                'region' => 'za',
                'key' => $googleKey,
            ]);

            if ($response->ok() && ($response->json('status') === 'OK')) {
                $location = $response->json('results.0.geometry.location');
                if (isset($location['lat'], $location['lng'])) {
                    return ['lat' => $location['lat'], 'lng' => $location['lng']];
                }
            }
        }

        $response = Http::timeout(10)
            ->withHeaders(['User-Agent' => 'InsulaCRM Scout Utility (estatecrm.barberrylabs.dpdns.org)'])
            ->get('https://nominatim.openstreetmap.org/search', [
                'format' => 'jsonv2',
                'q' => $address,
                'countrycodes' => 'za',
                'limit' => 1,
            ]);

        if (! $response->ok() || empty($response->json())) {
            return null;
        }

        $first = $response->json()[0] ?? null;
        if (! isset($first['lat'], $first['lon'])) {
            return null;
        }

        return ['lat' => (float) $first['lat'], 'lng' => (float) $first['lon']];
    }
}
