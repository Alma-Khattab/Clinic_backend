<?php

namespace App\Http\Controllers;

use App\Mail\ForgotPasswordOtpMail;
use App\Mail\SendOtpMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || $user->otp != $request->otp || $user->otp_type !== 'register') {
            // 📍 تسجيل محاولة توثيق خاطئة
            Log::warning("Failed OTP verification attempt", [
                'email' => $request->email,
                'ip'    => $request->ip()
            ]);
            return response()->json(['message' => 'Invalid OTP'], 400);
        }

        if (now()->gt($user->otp_expires_at)) {
            // 📍 تسجيل محاولة أدخال رمز منتهي الصلاحية
            Log::warning("Expired OTP verification attempt", [
                'email'   => $request->email,
                'user_id' => $user->id
            ]);
            return response()->json(['message' => 'OTP expired'], 400);
        }

        $user->update([
            'is_verified' => true,
            'otp' => null,
            'otp_expires_at' => null,
            'otp_type' => null
        ]);

        // 📍 تسجيل نجاح التوثيق
        Log::info("User account verified successfully", [
            'user_id' => $user->id,
            'email'   => $user->email,
            'role'    => $user->role
        ]);

        if ($user->role == 'doctor') {
            return response()->json([
                'massege' => 'Doctor account verified successfully and is pending admin approval.',
                'User' => $user
            ], 201);
        } else {
            return response()->json([
                'message' => 'Patient account verified successfully.',
                'user' => $user
            ], 201);
        }
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            Log::warning("Password reset requested for non-existing email", [
                'email' => $request->email,
                'ip'    => $request->ip()
            ]);
            return response()->json(['message' => 'User not found'], 404);
        }

        $otp = rand(1000, 9999);

        $user->update([
            'otp' => $otp,
            'otp_expires_at' => now()->addMinutes(5),
            'otp_type' => 'reset_password'
        ]);

        Mail::to($user->email)->send(new ForgotPasswordOtpMail($otp));
        // 📍 تسجيل طلب إعادة تعيين كلمة المرور
        Log::info("Password reset OTP sent", [
            'user_id' => $user->id,
            'email'   => $user->email
        ]);

        return response()->json(['message' => 'OTP sent successfully']);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required',
            'password' => 'required|min:8'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || $user->otp != $request->otp || $user->otp_type !== 'reset_password') {
            Log::warning("Failed password reset attempt - Invalid OTP", [
                'email' => $request->email,
                'ip'    => $request->ip()
            ]);
            return response()->json(['message' => 'Invalid OTP'], 400);
        }
        if (now()->gt($user->otp_expires_at)) {
            Log::warning("Failed password reset attempt - Expired OTP", [
                'email'   => $request->email,
                'user_id' => $user->id
            ]);
            return response()->json(['message' => 'OTP expired'], 400);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'otp' => null,
            'otp_expires_at' => null,
            'otp_type' => null
        ]);
        // 📍 تسجيل نجاح تغيير كلمة المرور
        Log::info("Password reset successfully", [
            'user_id' => $user->id,
            'email'   => $user->email
        ]);

        return response()->json(['message' => 'Password updated']);
    }

    public function resendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp_type' => 'required|in:register,reset_password'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        if ($user->otp_expires_at && now()->lt($user->otp_expires_at)) {
            Log::notice("OTP resend rate limit triggered", [
                'user_id' => $user->id,
                'email'   => $user->email
            ]);
            return response()->json([
                'message' => 'Current OTP is still valid. Please wait until it expires.'
            ], 429);
        }
        $otp = rand(1000, 9999);

        $user->update([
            'otp' => $otp,
            'otp_expires_at' => now()->addMinutes(5),
            'otp_type' => $request->otp_type
        ]);

        if ($request->otp_type == 'register') {
            Mail::to($user->email)->send(new SendOtpMail($otp));
        } else {
            Mail::to($user->email)->send(new ForgotPasswordOtpMail($otp));
        }
        // 📍 تسجيل إعادة إرسال الرمز
        Log::info("OTP resent successfully", [
            'user_id'  => $user->id,
            'email'    => $user->email,
            'otp_type' => $request->otp_type
        ]);

        return response()->json([
            'message' => 'OTP resent successfully'
        ]);
    }
}
