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

        if ($user->role !== 'doctor') {
            return response()->json([
                'message' => 'Only doctors can create a doctor profile.'
            ], 403);
        }
        $user_id = $user->id;
        $existingProfile = Doctor::where('user_id', $user_id)->first();

        if ($existingProfile) {
            return response()->json([
                'message' => 'Doctor profile already exists'
            ], 400);
        }

        $validateData = $request->validated();
        $validateData['user_id'] = $user_id;
        if ($request->hasFile('personal_image') && $request->hasFile('document_image')) {
            $path1 = $request->file('personal_image')->store('personal_image', 'public');
            $path2 = $request->file('document_image')->store('document_image', 'public');
            $validateData['personal_image'] = $path1;
            $validateData['document_image'] = $path2;
        }
        $profile = Doctor::create($validateData);
        return response()->json([
            'massege' => "successfully created profile",
            'profile' => $profile
        ], 201);
    }

    public function getProfile($id)
    {
        $user_id = Auth::user()->id;
        $profile = Doctor::find($id);
        if (!$profile) {
            return response()->json(['message' => 'Profile Not Found'], 404);
        }
        if ($profile->user_id != $user_id) {
            return response()->json(["massege" => 'unauthaurize'], 403);
        }
        return response()->json([
            'profile' => $profile
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
        if ($request->hasFile('personal_image')) {
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
}
