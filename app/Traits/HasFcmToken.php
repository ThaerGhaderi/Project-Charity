<?php

namespace App\Traits;

/**
 * @property string|null $fcm_token
 * @property string|null $device_type
 */
trait HasFcmToken
{
    public function updateFcmToken(string $token, ?string $deviceType = null): static
    {
        $this->fcm_token = $token;
        if ($deviceType) {
            $this->device_type = $deviceType;
        }
        $this->save();
        
        return $this;
    }

    public function removeFcmToken(): static
    {
        $this->fcm_token = null;
        $this->save();
        
        return $this;
    }

    public function hasFcmToken(): bool
    {
        return !is_null($this->fcm_token);
    }
}