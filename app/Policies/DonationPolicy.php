<?php

namespace App\Policies;

use App\Models\Donation;
use App\Models\User;

class DonationPolicy
{
    public function view(User $user, Donation $donation): bool
    {
        if ($donation->user_id === $user->id) {
            return true;
        }

        return app(GivingCampaignPolicy::class)->isManager($user, $donation->organization);
    }
}
