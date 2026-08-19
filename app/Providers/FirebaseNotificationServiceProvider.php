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

            if (!$credentials) {
                throw new \RuntimeException('Firebase credentials are not configured.');
            }

            // ✅ حزمة kreait ذكية، هي تقبل المسار (String) أو المصفوفة (Array) أو النص (JSON String)
            // لذلك أزلنا file_exists لكي لا تنفجر الدالة إذا كانت المصفوفة قادمة من Base64
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
