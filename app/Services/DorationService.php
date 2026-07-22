<?php

namespace App\Services;

use App\Models\Doration;
use App\Repositories\DonorProfileRepository;
use Illuminate\Support\Collection;

class DorationService
{
    public function __construct(
        private readonly DonorProfileRepository $donorProfileRepository)
     {

     }

    public function createDoration(array $data): Doration
    {
        $donorProfile = $this->donorProfileRepository->findOrCreateByEmail(
            email: $data['donor_email'],
            name:  $data['donor_name'],
        );
        $this->donorProfileRepository->updateDonorType(
            profile:     $donorProfile,
            donorType:   $data['donor_type'] ?? null,
            isAnonymous: $data['is_anonymous'] ?? null,
        );
        return $donorProfile->dorations()->create($this->dorationFields($data));
    }
    public function getAllDorations(): Collection
    {
        return Doration::with('donorProfile.user')->latest()->get();
    }
    public function getDorationById(int $id): Doration
    {
        return Doration::with('donorProfile.user')->findOrFail($id);
    }
    public function deleteDoration(int $id): void
    {
        Doration::findOrFail($id)->delete();
    }
    private function dorationFields(array $data): array
    {
        return [
            'name'   => $data['name'],
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'],
            'cat'    => $data['cat'],
            'date'   => $data['date'],
            'status' => $data['status'],
            'notes'  => $data['notes'] ?? '',
        ];
    }
}