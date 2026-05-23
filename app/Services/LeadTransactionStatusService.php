<?php

namespace App\Services;

use App\Events\LeadStatusChanged;
use App\Facades\Hooks;
use App\Models\Deal;
use App\Models\Lead;

class LeadTransactionStatusService
{
    /**
     * Keep the source lead status aligned with the active transaction stage.
     */
    public function syncForTransaction(Deal $deal): void
    {
        if (! BusinessModeService::isRealEstate($deal->tenant)) {
            return;
        }

        $deal->loadMissing(['lead', 'tenant']);
        $lead = $deal->lead;

        if (! $lead || in_array($lead->status, ['closed_won', 'closed_lost', 'dead'], true)) {
            return;
        }

        $targetStatus = $this->statusForStage($deal->stage, $lead);

        if (! $targetStatus || $lead->status === $targetStatus) {
            return;
        }

        $oldStatus = $lead->status;
        $lead->update(['status' => $targetStatus]);
        event(new LeadStatusChanged($lead->fresh(), $oldStatus));
        Hooks::doAction('lead.status_changed', $lead->fresh(), $oldStatus);
    }

    protected function statusForStage(string $stage, Lead $lead): ?string
    {
        return match ($stage) {
            'listing_agreement', 'active_listing', 'showing' => $this->firstValidStatus($lead, ['listed', 'active_client']),
            'offer_received', 'under_contract', 'inspection', 'appraisal', 'closing' => $this->firstValidStatus($lead, ['under_offer', 'active_client']),
            'closed_won' => 'closed_won',
            'closed_lost' => 'closed_lost',
            default => $this->firstValidStatus($lead, ['active_client']),
        };
    }

    protected function firstValidStatus(Lead $lead, array $statuses): ?string
    {
        $validStatuses = CustomFieldService::getValidSlugs('lead_status', $lead->tenant);

        foreach ($statuses as $status) {
            if (in_array($status, $validStatuses, true)) {
                return $status;
            }
        }

        return null;
    }
}
