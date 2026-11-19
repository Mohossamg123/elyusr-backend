<?php

namespace App\Support;

use App\Models\Donation;
use App\Models\DonationCampaign;
use App\Models\Program;
use App\Models\Project;


final class DonationTotals
{
    public static function added(Donation $donation): void
    {
        self::shift($donation, (float) $donation->amount);
    }

    public static function removed(Donation $donation): void
    {
        self::shift($donation, -(float) $donation->amount);
    }

    private static function shift(Donation $donation, float $delta): void
    {
            foreach ([[Program::class, $donation->program_id], [Project::class, $donation->project_id], [DonationCampaign::class, $donation->donation_campaign_id]] as [$model, $id]) {
            if (! $id) {
                continue;
            }
            $record = $model::whereKey($id)->lockForUpdate()->first();
            if ($record) {
                $record->collected_amount = max(0, (float) ($record->collected_amount ?? 0) + $delta);
                $record->save();
            }
        }
    }
}
