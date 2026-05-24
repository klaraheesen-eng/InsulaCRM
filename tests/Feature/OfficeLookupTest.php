<?php

namespace Tests\Feature;

use App\Models\OfficeLookupRequest;
use Tests\TestCase;

class OfficeLookupTest extends TestCase
{
    public function test_unknown_owner_office_lookup_redirects_to_whatsapp_with_magic_link(): void
    {
        $this->actingAsAdmin(['office_address_lookup_phone' => '082 000 0000']);
        $lead = $this->createLead([
            'first_name' => 'Unknown',
            'last_name' => 'Owner',
            'phone' => null,
        ]);
        $this->createProperty([
            'lead_id' => $lead->id,
            'address' => '245 Indus Street',
            'city' => 'Pretoria',
            'state' => 'Gauteng',
            'notes' => 'Scout coordinates: -25.781000, 28.253000',
        ]);

        $response = $this->post(route('leads.officeLookupWhatsApp', $lead));

        $lookup = OfficeLookupRequest::first();
        $this->assertNotNull($lookup);
        $response->assertRedirect();
        $this->assertStringStartsWith('https://wa.me/27820000000?text=', $response->headers->get('Location'));
        $this->assertStringContainsString(rawurlencode(route('office-lookup.show', $lookup->token)), $response->headers->get('Location'));
        $this->assertDatabaseHas('activities', [
            'tenant_id' => $this->tenant->id,
            'lead_id' => $lead->id,
            'agent_id' => $this->adminUser->id,
            'type' => 'note',
            'subject' => 'Office lookup requested',
        ]);
    }

    public function test_office_lookup_whatsapp_rejects_leads_that_already_have_contact_details(): void
    {
        $this->actingAsAdmin(['office_address_lookup_phone' => '27820000000']);
        $lead = $this->createLead([
            'first_name' => 'Jane',
            'last_name' => 'Seller',
            'phone' => '0821234567',
        ]);

        $response = $this->post(route('leads.officeLookupWhatsApp', $lead));

        $response->assertRedirect(route('leads.show', $lead));
        $this->assertSame(0, OfficeLookupRequest::count());
    }

    public function test_new_office_lookup_request_invalidates_prior_active_tokens_for_same_lead(): void
    {
        $this->actingAsAdmin(['office_address_lookup_phone' => '27820000000']);
        $lead = $this->createLead([
            'first_name' => 'Unknown',
            'last_name' => 'Owner',
            'phone' => null,
        ]);

        $this->post(route('leads.officeLookupWhatsApp', $lead));
        $firstLookup = OfficeLookupRequest::first();

        $this->post(route('leads.officeLookupWhatsApp', $lead));

        $this->assertNotNull($firstLookup->fresh()->completed_at);
        $this->assertCount(1, OfficeLookupRequest::where('lead_id', $lead->id)->whereNull('completed_at')->get());
    }

    public function test_public_office_lookup_form_updates_lead_and_marks_completed(): void
    {
        $this->actingAsAdmin();
        $lead = $this->createLead([
            'first_name' => 'Unknown',
            'last_name' => 'Owner',
            'phone' => null,
            'email' => null,
            'notes' => 'Original note',
        ]);
        $lookup = OfficeLookupRequest::createForLead($lead, $this->adminUser->id);

        auth()->logout();

        $response = $this->post(route('office-lookup.update', $lookup->token), [
            'owner_name' => 'Jane Seller',
            'phone' => '0821234567',
            'secondary_phone' => '0831234567',
            'email' => 'jane@example.com',
            'notes' => 'Found via deeds office',
        ]);

        $response->assertRedirect(route('office-lookup.show', $lookup->token));
        $lead->refresh();
        $this->assertSame('Jane', $lead->first_name);
        $this->assertSame('Seller', $lead->last_name);
        $this->assertSame('0821234567', $lead->phone);
        $this->assertSame('0831234567', $lead->secondary_phone);
        $this->assertSame('jane@example.com', $lead->email);
        $this->assertStringContainsString('Office lookup notes: Found via deeds office', $lead->notes);
        $this->assertNotNull($lookup->fresh()->completed_at);
    }

    public function test_expired_office_lookup_link_does_not_update_lead(): void
    {
        $this->actingAsAdmin();
        $lead = $this->createLead([
            'first_name' => 'Unknown',
            'last_name' => 'Owner',
            'phone' => null,
        ]);
        $lookup = OfficeLookupRequest::createForLead($lead, $this->adminUser->id);
        $lookup->update(['expires_at' => now()->subMinute()]);

        auth()->logout();

        $response = $this->post(route('office-lookup.update', $lookup->token), [
            'owner_name' => 'Jane Seller',
            'phone' => '0821234567',
        ]);

        $response->assertRedirect(route('office-lookup.show', $lookup->token));
        $this->assertNull($lead->fresh()->phone);
        $this->assertNull($lookup->fresh()->completed_at);
    }
}
