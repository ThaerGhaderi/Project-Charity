<?php
// app/Http/Middleware/RegisterFcmToken.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RegisterFcmToken
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        if ($user && $request->header('X-FCM-Token')) {
            $deviceType = $request->header('X-Device-Type', 'web');
            $user->updateFcmToken($request->header('X-FCM-Token'), $deviceType);
        }
        
        return $next($request);
    }
}