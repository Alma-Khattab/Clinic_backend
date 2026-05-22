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

        Mail::to($user->email)->send(new SendOtpMail($otp));

        $user->makeHidden([
            'otp',
            'otp_expires_at',
            'otp_type',
            'remember_token'
        ]);
        return response()->json([
            'massege' => 'A verification code has been sent to your email.',
            'User' => $user
        ], 201);
    }

    public function login(Request $request)
    {

        $request->validate([
            'email' => 'required|email',
            'password' => "required|string"
        ]);
        if (!Auth::attempt($request->only('email', 'password')))
            return response()->json([
                'massege' => 'invalid password or email'
            ], 401);

        $user = User::where('email', $request->email)->firstOrFail();

        if ($user->role !== 'admin' && !$user->is_verified) {
            return response()->json([
                'message' => 'Account not verified. Please verify OTP first.'
            ], 403);
        }
        if ($user->status == 'approved') {
            $token = $user->createToken('auth_token')->plainTextToken;
            return response()->json([
                'message' => 'login successfully',
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
        return response()->json([
            'massege' => 'logout successfully'
        ], 200);
    }
}
