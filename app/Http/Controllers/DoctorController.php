<?php

namespace App\Http\Controllers;

use App\Http\Requests\DoctorRequest;
use App\Http\Requests\UpdateDoctorProfileRequest;
use App\Models\Doctor;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DoctorController extends Controller
{
    public function storeProfile(DoctorRequest $request)
{
    $user = Auth::user();

    if (!$user) {
        return response()->json(['error' => 'User not authenticated'], 401);
    }

    $user_id = $user->id;
    $validateData = $request->validated();

    if ($request->hasFile('personal_image') && $request->hasFile('document_image')) {
        $validateData['personal_image'] = $request->file('personal_image')->store('personal_image', 'public');
        $validateData['document_image'] = $request->file('document_image')->store('document_image', 'public');
    }

    $doctor = Doctor::where('user_id', $user_id)->first();

    if ($doctor) {
        $doctor->update($validateData);
    } else {
        $validateData['user_id'] = $user_id;
        $doctor = Doctor::create($validateData);
    }

    return response()->json([
        'message' => 'Successfully updated profile',
        'profile' => $doctor
    ], 200);
}

    public function updateProfile(UpdateDoctorProfileRequest $request, $id)
    {
        $user_id = Auth::user()->id;
        $profile = Doctor::find($id);
        $validateData = $request->validated();
        if (!$profile) {
            return response()->json(['message' => 'Profile Not Found'], 404);
        }
        if ($profile->user_id != $user_id) {
            return response()->json(["massege" => 'unauthaurize'], 403);
        }
        if ($request->hasFile('personal_image') ) {
            $path1 = $request->file('personal_image')->store('personal_image', 'public');
            $validateData['personal_image'] = $path1;
        }
        $profile->update($validateData);
        $profile->save();
        return response()->json([
            'massege' => "profile updated successfully.",
            'profile' => $profile
        ], 200);
    }

    public function destroyProfile($id)
    {
        try {
            $user_id = Auth::user()->id;
            $profile = Doctor::findorfail($id);

            if ($profile->user_id != $user_id) {
                return response()->json(["massege" => 'unauthaurize'], 403);
            }
            $profile->delete();
            return response()->json('profile deleted successfully', 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(
                [
                    'error' => 'profile not found',
                    'detailes' => $e->getMessage()
                ],
                404
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'error' => 'something went wrong while deleting the profile',
                    'detailes' => $e->getMessage()
                ],
                404
            );
        }
    }


    public function getDoctorsBySpecialization($specialization)
{
    $doctors = Doctor::where('doctor_specialization', $specialization)->get();

    if ($doctors->isEmpty()) {
        return response()->json(['message' => 'There are currently no doctors in this specialty.'], 404);
    }

    return response()->json([
        'success' => true,
        'doctors' => $doctors
    ], 200);
}

public function getDoctorProfileForBooking($id)
    {
        $doctor = Doctor::find($id);
        if (!$doctor) {
            return response()->json([
                'success' => false,
                'message' => 'The doctor is not found'
            ], 404);
        }
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }
        if ($user->role !== 'patient' && $doctor->user_id != $user->id) {
        return response()->json([
            'success' => false,
            'message' => "You do not have the authorization to view this profile."
        ], 403);
    }
        return response()->json([
            'success' => true,
            'doctor_profile' => $doctor
        ], 200);
    }
}
