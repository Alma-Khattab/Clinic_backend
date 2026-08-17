<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatientRequest;
use App\Http\Requests\UpdatePatientProfileRequest;
use App\Models\Patient;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
// use Storage;
use Illuminate\Support\Facades\Storage;

class PatientController extends Controller
{
    public function storeProfile(PatientRequest $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        if ($user->role !== 'patient') {
            return response()->json([
                'message' => 'Only patients can create a patient profile.'
            ], 403);
        }

        $user_id = $user->id;

        $exists = Patient::where('user_id', $user_id)->exists();
        if ($exists) {
            return response()->json([
                'message' => 'You already have a profile.'
            ], 400);
        }

        $validateData = $request->validated();
        $validateData['user_id'] = $user_id;
        if ($request->hasFile('personal_image')) {
            $path = $request->file('personal_image')->store('personal_image', 'public');
            $validateData['personal_image'] = $path;
        }

        $profile = Patient::create($validateData);

        $profile->setAttribute('missed_appointments_count', 0);
        $profile->setAttribute('is_blocked', false);

        // 📍 تسجيل إنشاء ملف مريض جديد
        Log::info("Patient Profile Created", [
            'patient_profile_id' => $profile->id,
            'user_id'            => $user_id,
            'has_image'          => $request->hasFile('personal_image')
        ]);
        return response()->json([
            'message' => "successfully created profile",
            'profile' => $profile
        ], 201);
    }

    public function getProfile($id)
    {
        $user = Auth::user();
        $profile = Patient::find($id);

        if (!$profile) {
            return response()->json(['message' => 'Profile Not Found'], 404);
        }

        if ($user->role !== 'doctor' && $profile->user_id != $user->id) {
            return response()->json(["message" => 'Unauthorized'], 403);
        }
        $missedCount = \App\Models\Appointment::where('user_id', $profile->user_id)
            ->where('status', 'missed')
            ->count();
        $profile->setAttribute('missed_appointments_count', $missedCount);
        $profile->setAttribute('is_blocked', $missedCount >= 3);
        return response()->json([
            'profile' => $profile
        ], 200);
    }

    public function updateProfile(UpdatePatientProfileRequest $request, $id)
    {
        $user_id = Auth::user()->id;
        $profile = Patient::find($id);

        $validateData = $request->validated();

        if (!$profile) {
            return response()->json(['message' => 'Profile Not Found'], 404);
        }

        if ($profile->user_id != $user_id) {
            return response()->json(["message" => 'Unauthorized'], 403);
        }
        if ($request->hasFile('personal_image')) {
            if ($profile->personal_image) {
                Storage::disk('public')->delete($profile->personal_image);
            }
            $path = $request->file('personal_image')->store('personal_image', 'public');
            $validateData['personal_image'] = $path;
        }
        $profile->update($validateData);

        $missedCount = \App\Models\Appointment::where('user_id', $profile->user_id)
            ->where('status', 'missed')
            ->count();
        $profile->setAttribute('missed_appointments_count', $missedCount);
        $profile->setAttribute('is_blocked', $missedCount >= 3);

        // 📍 تسجيل تحديث بيانات الملف الشخصي
        Log::info("Patient Profile Updated", [
            'patient_profile_id' => $profile->id,
            'user_id'            => $user_id,
            'updated_fields'     => array_keys($validateData)
        ]);

        return response()->json([
            'message' => "Profile updated successfully.",
            'profile' => $profile
        ], 200);
    }

    public function destroyProfile($id)
    {
        try {
            $user_id = Auth::user()->id;
            $profile = Patient::findOrFail($id);

            if ($profile->user_id != $user_id) {
                return response()->json(["message" => 'Unauthorized'], 403);
            }

            $profile->delete();
            // 📍 تسجيل حذف الملف الشخصي بنجاح
            Log::warning("Patient Profile Deleted", [
                'user_id'            => $user_id
            ]);
            return response()->json(['message' => 'Profile deleted successfully'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Profile not found',
                'details' => $e->getMessage()
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Something went wrong while deleting the profile',
                'details' => $e->getMessage()
            ], 500);
        }
    }
    public function getMissedAppointments()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $appointments = \App\Models\Appointment::with(['doctor.user'])
            ->where('user_id', $user->id)
            ->where('status', 'missed')
            ->orderBy('appointment_date', 'desc')
            ->get()
            ->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'appointment_date' => $appointment->appointment_date,
                    'appointment_time' => $appointment->appointment_time,
                    'doctor_name' => optional(optional($appointment->doctor)->user)->full_name ?? 'Unknown Doctor',
                    'specialization' => optional($appointment->doctor)->doctor_specialization,
                ];
            });

        return response()->json([
            'success' => true,
            'appointments' => $appointments
        ], 200);
    }
}
