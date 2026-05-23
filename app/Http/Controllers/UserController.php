<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Models\User;
use App\Services\OtpService;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Http\Requests\RoleRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Str;
class UserController extends Controller
{
    protected $otpService;
    protected $rateLimiter;
    public function __construct(OtpService $otpService, RateLimiter $rateLimiter)
    {
        $this->otpService = $otpService;
        $this->rateLimiter = $rateLimiter;
    }
    public function register(RegisterRequest $request)
{
    $data = $request->validated();

    $user = User::create([
        'name' => trim($data['name']),
        'email' => $data['email'],
        'password' => Hash::make($data['password']),
        'role' => null,
        'profile_completed' => false,
    ]);

    $this->otpService->sendOtp($user->email, 'verification');

    $token = $user->createToken('profile_token')->plainTextToken;
    return response()->json([
        'code' => 201,
        'status' => 'success',
        'message' => 'Account created. Please verify your email.',
        'token' => $token,
        'user' => $user
    ], 201);
}
/*public function register(RegisterRequest $request)
{
    $key = 'register.' . $request->ip();
    if ($this->rateLimiter->tooManyAttempts($key, 3)) {
        $seconds = $this->rateLimiter->availableIn($key);
        return response()->json([
            'code' => 429,
            'status' => 'error',
            'message' => 'Too many registration attempts. Please try again in ' . ceil($seconds / 60) . ' minutes.',
            'retry_after' => $seconds
        ], 429);
    }
    //$this->rateLimiter->hit($key, 3600);
    $data = $request->validated();
    if (!isset($data['name']) || empty(trim($data['name']))) {
        return response()->json([
            'code' => 422,
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => [
                'name' => ['The name field is required and cannot be empty']
            ]
        ], 422);
    }

    // ✅ التأكد من وجود باقي الحقول
    if (!isset($data['email']) || empty($data['email'])) {
        return response()->json([
            'code' => 422,
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => [
                'email' => ['The email field is required']
            ]
        ], 422);
    }
    if (!isset($data['password']) || empty($data['password'])) {
        return response()->json([
            'code' => 422,
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => [
                'password' => ['The password field is required']
            ]
        ], 422);
    }

    // ✅ التحقق من تطابق كلمة المرور
    if (!isset($data['password_confirmation']) || $data['password'] !== $data['password_confirmation']) {
        return response()->json([
            'code' => 422,
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => [
                'password_confirmation' => ['The password confirmation does not match']
            ]
        ], 422);
    }
    // ✅ التحقق من أن البريد الإلكتروني فريد
    $existingUser = User::where('email', $data['email'])->first();
    if ($existingUser) {
        return response()->json([
            'code' => 422,
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => [
                'email' => ['This email is already registered']
            ]
        ], 422);
    }
    $user = User::create([
        'name' => trim($data['name']),
        'email' => $data['email'],
        'password' => Hash::make($data['password']),
        'role' => null,
        'profile_completed' => false,
    ]);
    $this->otpService->sendOtp($user->email, 'verification');
    $token = $user->createToken('profile_token')->plainTextToken;
    return response()->json([
        'code' => 201,
        'status' => 'success',
        'message' => 'Account created. Please verify your email and complete profile.',
        'token' => $token,
        'user' => $user
    ], 201);
}*/
    // Select role after registration
    public function selectRole(RoleRequest $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'code' => 401,
                'status' => 'error',
                'message' => 'Unauthenticated'
            ], 401);
        }

        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'code' => 403,
                'status' => 'error',
                'message' => 'Please verify your email first using OTP.'
            ], 403);
        }

        if ($user->role !== null) {
            return response()->json([
                'code' => 400,
                'status' => 'error',
                'message' => 'You have already selected a role'
            ], 400);
        }

        $user->role = $request->role;
        $user->save();
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'Role selected successfully. Please complete your profile.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'profile_completed' => $user->profile_completed
            ]
        ], 200);
    }

   public function verifyOtp(VerifyOtpRequest $request)
{
    // الحصول على المستخدم من التوكن الحالي
    $user = $request->user();
    if (!$user) {
        return response()->json([
            'code' => 401,
            'status' => 'error',
            'message' => 'Unauthenticated'
        ], 401);
    }
    $key = 'verify_otp.' . $user->email . '.' . $request->ip();

    /*if ($this->rateLimiter->tooManyAttempts($key, 5)) {
        $seconds = $this->rateLimiter->availableIn($key);
        return response()->json([
            'code' => 429,
            'status' => 'error',
            'message' => 'Too many verification attempts. Please try again after ' . ceil($seconds / 60) . ' minutes.',
            'retry_after' => $seconds
        ], 429);
    }*/
    $valid = $this->otpService->verifyOtp(
        $user->email,
        $request->otp,
        'verification'
    );
    if (!$valid) {
        //$this->rateLimiter->hit($key, 900);
        return response()->json([
            'code' => 422,
            'status' => 'error',
            'message' => 'Invalid or expired OTP'
        ], 422);
    }
    if (!$user->hasVerifiedEmail()) {
        $user->markEmailAsVerified();
    }
    //$this->rateLimiter->clear($key);

    return response()->json([
        'code' => 200,
        'status' => 'success',
        'message' => 'Email verified successfully'
    ], 200);
}
    public function login(LoginRequest $request)
    {
        $key = 'login.' . $request->input('email') . '.' . $request->ip();

        if ($this->rateLimiter->tooManyAttempts($key, 5)) {
            $seconds = $this->rateLimiter->availableIn($key);
            return response()->json([
                'code' => 429,
                'status' => 'error',
                'message' => 'Too many login attempts. Please try again after ' . ceil($seconds / 60) . ' minutes.',
                'retry_after' => $seconds
            ], 429);
        }

        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            $this->rateLimiter->hit($key, 900);

            return response()->json([
                'code' => 401,
                'status' => 'error',
                'message' => 'Invalid credentials'
            ], 401);
        }

        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'code' => 403,
                'status' => 'error',
                'message' => 'Please verify your email first. Check your inbox for the OTP code.'
            ], 403);
        }

        if ($user->role === null) {
            return response()->json([
                'code' => 403,
                'status' => 'error',
                'message' => 'Please select your role first'
            ], 403);
        }

        if (!$user->profile_completed) {
            return response()->json([
                'code' => 403,
                'status' => 'error',
                'message' => 'Please complete your profile first'
            ], 403);
        }

        $this->rateLimiter->clear($key);

        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        $userData = $this->getUserWithProfile($user);

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'Login successful',
            'token' => $token,
            'user' => $userData
        ], 200);
    }

    private function getUserWithProfile($user)
    {
        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'profile_completed' => $user->profile_completed,
            'email_verified_at' => $user->email_verified_at,
            'created_at' => $user->created_at,
        ];

        switch ($user->role) {
            case 'Donor':
                if ($user->donor) {
                    $userData['profile'] = [
                        'type' => 'donor',
                        'skills' => $user->donor->skills,
                        'availability' => $user->donor->availability,
                        'region' => $user->donor->region,
                        'total_hours' => $user->donor->total_hours,
                        'status' => $user->donor->status,
                        'total_donated' => $user->donor->total_donated,
                        'created_at' => $user->donor->created_at,
                        'updated_at' => $user->donor->updated_at,
                    ];
                } else {
                    $userData['profile'] = null;
                }
                break;

            case 'volunteer':
                if ($user->volunteer) {
                    $userData['profile'] = [
                        'type' => 'volunteer',
                        'skills' => $user->volunteer->skills,
                        'availability' => $user->volunteer->availability,
                        'total_hours' => $user->volunteer->total_hours,
                        'status' => $user->volunteer->status,
                        'region' => $user->volunteer->region,
                        'created_at' => $user->volunteer->created_at,
                        'updated_at' => $user->volunteer->updated_at,
                    ];
                } else {
                    $userData['profile'] = null;
                }
                break;

            case 'Beneficiary':
                if ($user->beneficiary) {
                    $userData['profile'] = [
                        'type' => 'beneficiary',
                        'address' => $user->beneficiary->address,
                        'region' => $user->beneficiary->region,
                        'category' => $user->beneficiary->category,
                        'priority_score' => $user->beneficiary->priority_score,
                        'birth_date' => $user->beneficiary->birth_date,
                        'gender' => $user->beneficiary->gender,
                        'marital_status' => $user->beneficiary->marital_status,
                        'is_anonymized' => $user->beneficiary->is_anonymized,
                        'created_at' => $user->beneficiary->created_at,
                        'updated_at' => $user->beneficiary->updated_at,
                    ];
                } else {
                    $userData['profile'] = null;
                }
                break;

            default:
                $userData['profile'] = null;
                break;
        }

        return $userData;
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'code' => 401,
                'status' => 'error',
                'message' => 'Unauthenticated'
            ], 401);
        }

        $user->currentAccessToken()->delete();

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'Logged out successfully'
        ], 200);
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $key = 'change_password.' . $request->user()->id . '.' . $request->ip();

        if ($this->rateLimiter->tooManyAttempts($key, 3)) {
            $seconds = $this->rateLimiter->availableIn($key);
            return response()->json([
                'code' => 429,
                'status' => 'error',
                'message' => 'Too many password change attempts. Please try again after ' . ceil($seconds / 60) . ' minutes.',
                'retry_after' => $seconds
            ], 429);
        }

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'code' => 401,
                'status' => 'error',
                'message' => 'Unauthenticated'
            ], 401);
        }

        $data = $request->validated();

        if (!Hash::check($data['current_password'], $user->password)) {
            $this->rateLimiter->hit($key, 3600);

            return response()->json([
                'code' => 422,
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => ['current_password' => ['The current password is incorrect']]
            ], 422);
        }

        $user->password = Hash::make($data['new_password']);
        $user->save();

        $this->rateLimiter->clear($key);

        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'Password changed successfully. Please login again on other devices.'
        ], 200);
    }

    public function forgotPassword(Request $request)
    {
        $key = 'forgot_password.' . $request->ip() . '.' . $request->input('email');

        if ($this->rateLimiter->tooManyAttempts($key, 3)) {
            $seconds = $this->rateLimiter->availableIn($key);
            return response()->json([
                'code' => 429,
                'status' => 'error',
                'message' => 'Too many password reset requests. Please try again after ' . ceil($seconds / 60) . ' minutes.',
                'retry_after' => $seconds
            ], 429);
        }



        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'code' => 404,
                'status' => 'error',
                'message' => 'Email not found'
            ], 404);
        }

        $this->rateLimiter->hit($key, 3600);
        $this->otpService->sendOtp($user->email, 'reset_password');

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'OTP sent to your email for password reset'
        ], 200);
    }

    public function resetPassword(Request $request)
    {
        $key = 'reset_password.' . $request->ip() . '.' . $request->input('email');

        if ($this->rateLimiter->tooManyAttempts($key, 3)) {
            $seconds = $this->rateLimiter->availableIn($key);
            return response()->json([
                'code' => 429,
                'status' => 'error',
                'message' => 'Too many password reset attempts. Please try again after ' . ceil($seconds / 60) . ' minutes.',
                'retry_after' => $seconds
            ], 429);
        }

        $request->validate([
          //  'email' => 'required|email|exists:users,email',
            'otp' => 'required|string|size:5',
            'new_password' => 'required|string|min:8|confirmed'
        ]);

        $valid = $this->otpService->verifyOtp(
            $request->email,
            $request->otp,
            'reset_password'
        );

        if (!$valid) {
            $this->rateLimiter->hit($key, 3600);

            return response()->json([
                'code' => 422,
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => ['otp' => ['Invalid or expired OTP']]
            ], 422);
        }

        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->new_password);
        $user->save();

        $this->rateLimiter->clear($key);

        $user->tokens()->delete();

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'Password reset successfully. Please login with your new password.'
        ], 200);
    }

    public function resendOtp(Request $request)
    {
        $key = 'resend_otp.' . $request->input('email') . '.' . $request->ip();

        if ($this->rateLimiter->tooManyAttempts($key, 2)) {
            $seconds = $this->rateLimiter->availableIn($key);
            return response()->json([
                'code' => 429,
                'status' => 'error',
                'message' => 'Too many resend attempts. Please try again after ' . ceil($seconds / 60) . ' minutes.',
                'retry_after' => $seconds
            ], 429);
        }

        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'code' => 400,
                'status' => 'error',
                'message' => 'Email already verified'
            ], 400);
        }

        $this->rateLimiter->hit($key, 3600);
        $this->otpService->sendOtp($user->email, 'verification');

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'OTP resent successfully. Please check your email.'
        ], 200);
    }

}
