<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Mail\SendOtpMail;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller
{
    public function register(UserRequest $request)
    {
        $validateData = $request->validated();
        $validateData['password'] = Hash::make($request->password);

        if ($validateData['role'] == 'doctor') {
            $validateData['status'] = 'pending';
        } else {
            $validateData['status'] = 'approved';
        }

        $user = User::create($validateData);

        $otp = rand(1000, 9999);

        $user->update([
            'otp' => $otp,
            'otp_expires_at' => now()->addMinutes(5),
            'otp_type' => 'register'
        ]);

        // تسجيل حدث تسجيل مستخدم جديد
        Log::info("New User Registered", [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role
        ]);


        // حماية النظام في حال توقف سيرفر الإيميل (SMTP)
        try {
            Mail::to($user->email)->send(new SendOtpMail($otp));
        } catch (\Exception $e) {
            Log::error("Failed to send OTP Email", [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);
        }

        $user->makeHidden([
            'otp',
            'otp_expires_at',
            'otp_type',
            'remember_token'
        ]);

        return response()->json([
            'message' => 'A verification code has been sent to your email.',
            'user' => $user
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {

            // تسجيل محاولة دخول بكلمة مرور خاطئة
            Log::warning("Failed Login Attempt - Invalid Credentials", [
                'email' => $request->email,
                'ip' => request()->ip()
            ]);
            return response()->json([
                'message' => 'Invalid password or email'
            ], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        if ($user->role !== 'admin' && !$user->is_verified) {
            // تسجيل محاولة دخول لحساب غير مفعل
            Log::warning("Failed Login Attempt - Unverified Account", [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            return response()->json([
                'message' => 'Account not verified. Please verify OTP first.'
            ], 403);
        }

        if ($user->status == 'approved') {
            $token = $user->createToken('auth_token')->plainTextToken;
            // تسجيل عملية دخول ناجحة
            Log::info("User Logged In", [
                'user_id' => $user->id,
                'role' => $user->role,
                'ip' => request()->ip()
            ]);
            return response()->json([
                'message' => 'Login successfully',
                'user' => $user,
                'token' => $token
            ], 200);
        } else if ($user->status == 'rejected') {
            return response()->json([
                'message' => 'The request was rejected by the admin'
            ], 200);
        } else {
            return response()->json([
                'message' => 'The request was pending by the admin'
            ], 200);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        // تسجيل عملية الخروج
        Log::info("User Logged Out", [
            'user_id' => Auth::user()
        ]);
        return response()->json([
            'message' => 'Logout successfully'
        ], 200);
    }

    public function saveFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required'
        ]);

        $user = $request->user();
        $user->update([
            'fcm_token' => $request->fcm_token
        ]);

        return response()->json([
            'message' => 'Token saved successfully'
        ]);
    }
    public function updateUserBasicInfo(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'date_of_birth' => 'sometimes|date|nullable',
            'gender' => 'sometimes|string|in:male,female,other|nullable',
        ]);

        try {
            $user->update($request->only('full_name', 'date_of_birth', 'gender'));

            // 🟢 Log: تسجيل تحديث البيانات الشخصية
            Log::info("User profile updated", ['user_id' => $user->id]);

            return response()->json([
                'message' => 'User profile updated successfully',
                'user' => $user
            ], 200);
        } catch (\Exception $e) {
            Log::error("Failed to update user profile", ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to update profile.'], 500);
        }
    }

    public function changeEmailRequest(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);

        try {
            $otp = rand(1000, 9999);

            $user->update([
                'otp' => $otp,
                'otp_expires_at' => now()->addMinutes(5),
                'otp_type' => 'change_email'
            ]);

            Mail::to($request->email)->send(new SendOtpMail($otp));

            // 🟢 Log: تسجيل طلب تغيير الإيميل (يفضل عدم طباعة الـ OTP نفسه في الـ Log لأسباب أمنية)
            Log::info("Email change OTP requested", [
                'user_id' => $user->id,
                'target_email' => $request->email
            ]);

            return response()->json([
                'message' => 'OTP sent successfully to your new email.'
            ], 200);
        } catch (\Exception $e) {
            Log::error("Failed to send OTP for email change", ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Could not send OTP. Please try again.'], 500);
        }
    }

    public function verifyChangeEmail(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'new_email' => 'required|email|unique:users,email,' . $user->id,
            'otp' => 'required'
        ]);

        if ($user->otp != $request->otp || $user->otp_type !== 'change_email') {
            return response()->json(['message' => 'Invalid OTP'], 400);
        }

        if (now()->gt($user->otp_expires_at)) {
            return response()->json(['message' => 'OTP expired'], 400);
        }

        try {
            $oldEmail = $user->email;

            $user->update([
                'email' => $request->new_email,
                'otp' => null,
                'otp_expires_at' => null,
                'otp_type' => null
            ]);

            // 🟢 Log: تسجيل نجاح تغيير الإيميل
            Log::info("Email successfully changed", [
                'user_id' => $user->id,
                'old_email' => $oldEmail,
                'new_email' => $user->email
            ]);

            return response()->json([
                'message' => 'Email updated successfully.',
                'user' => $user
            ], 200);
        } catch (\Exception $e) {
            Log::error("Failed to verify and change email", ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to update email.'], 500);
        }
    }
}
