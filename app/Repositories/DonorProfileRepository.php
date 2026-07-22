<?php

namespace App\Repositories;

use App\Models\DonorProfile;
use App\Models\User;
class DonorProfileRepository
{
    public function findOrCreateByEmail(string $email, string $name): DonorProfile
    {
        $user = $this->findOrCreateUser($email, $name);

        return DonorProfile::firstOrCreate(
            ['user_id' => $user->id],
            ['donor_type' => 'فردي',
            'is_anonymous' => false
            ]
        );
    }
    private function findOrCreateUser(string $email, string $name): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            [
                'name'      => $name,
                'password'  => bcrypt(str()->random(16)),
                'role'      => 'Donor',
                'is_active' => false,
            ]
        );
    }

    public function updateDonorType(DonorProfile $profile, ?string $donorType, ?bool $isAnonymous): void
    {
        $profile->update(array_filter([
            'donor_type'   => $donorType,
            'is_anonymous' => $isAnonymous,
        ], fn($value) => ! is_null($value)));
    }
}