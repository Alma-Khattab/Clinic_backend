<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatientRequest;
use App\Http\Requests\UpdatePatientProfileRequest;
use App\Models\Patient;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        $exists = Patient::where('user_id',  $user_id)->exists();
        if ($exists) {
            return response()->json([
                'message' => 'You already have a profile'
            ], 400);
        }

        $validateData = $request->validated();
        $validateData['user_id'] = $user_id;
        if ($request->hasFile('personal_image')) {
            $path = $request->file('personal_image')->store('personal_image', 'public');
            $validateData['personal_image'] = $path;
        }
        $profile = Patient::create($validateData);
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

        $profile->update($validateData);

        return response()->json([
            'message' => "Profile updated successfully.",
            'profile' => $profile
        ], 200);
    }

    public function destroyProfile($id)
    {
        try {
            $user_id = Auth::user()->id;
            $profile = Patient::findOrFail($id); // تغيير الموديل وتصحيح الحروف الكابيتال لـ findOrFail

            if ($profile->user_id != $user_id) {
                return response()->json(["message" => 'Unauthorized'], 403);
            }

            $profile->delete();
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
            ], 500); // تغيير الـ Status code لـ 500 لأنه خطأ سيرفر داخلي وليس 404
        }
    }
}
