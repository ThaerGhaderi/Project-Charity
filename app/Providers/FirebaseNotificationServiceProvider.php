<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging;

class FirebaseNotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Messaging::class, function ($app) {
            $credentials = config('firebase.projects.app.credentials');
            $projectId   = config('firebase.projects.app.project_id');

            if (!$credentials || !file_exists($credentials)) {
                throw new \RuntimeException(
                    'Firebase credentials file not found at: ' . $credentials
                );
            }

            return (new Factory)
                ->withServiceAccount($credentials)
                ->withProjectId($projectId)
                ->createMessaging();
        });
    }

    public function boot(): void
    {
        //
    }
}
