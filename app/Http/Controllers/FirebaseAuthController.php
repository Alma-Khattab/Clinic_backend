<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FirebaseAuthController extends Controller
{
    public function handleFirebaseToken(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
            'role'     => 'sometimes|in:patient,doctor',
        ]);

        try {
            // 1. التحقق من صحة التوكن مباشرة من Google
            $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $request->id_token,
            ]);

            if ($response->failed()) {
                return response()->json(['message' => 'Invalid or expired Google token.'], 401);
            }

            $googleData = $response->json();
            $googleId   = $googleData['sub'];
            $email      = $googleData['email'];
            $name       = $googleData['name'] ?? 'Google User';

            // 2. البحث عن المستخدم في القاعدة
            $user = User::where('google_id', $googleId)
                ->orWhere('email', $email)
                ->first();

            if ($user) {
                if (!$user->google_id) {
                    $user->update(['google_id' => $googleId]);
                }
            } else {
                // 3. إنشاء مستخدم جديد بحقول افتراضية آمنة
                $role = $request->input('role', 'patient');

                $user = User::create([
                    'full_name'     => $name,
                    'email'         => $email,
                    'google_id'     => $googleId,
                    'password'      => Hash::make(Str::random(32)),
                    'date_of_birth' => now()->toDateString(),
                    'role'          => $role,
                    'gender'        => 'male',
                    'status'        => $role === 'doctor' ? 'pending' : 'approved',
                    'is_verified'   => true,
                ]);

                if ($role === 'patient') {
                    Patient::create([
                        'user_id'        => $user->id,
                        'address'        => 'Not Specified',
                        'blood_type'     => 'Unknown',
                        'height'         => 170,
                        'weight'         => 70,
                        'job'            => 'Not Specified',
                        'marital_status' => 'Single',
                    ]);
                }
            }

            // فحص حالة الحساب
            if ($user->status === 'rejected') {
                return response()->json(['message' => 'The request was rejected by the admin.'], 403);
            }

            if ($user->status === 'pending') {
                return response()->json(['message' => 'Your account is pending admin approval.'], 403);
            }

            // 4. توليد Sanctum Token للفرونت
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Logged in successfully via Google',
                'user'    => $user,
                'token'   => $token,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Authentication failed.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
