<?php

namespace App\Http\Controllers;

use App\Http\Requests\DoctorRequest;
use App\Http\Requests\UpdateDoctorProfileRequest;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\User;
use Carbon\Carbon;
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
            return response()->json(["message" => 'Unauthorized'], 403);
        }

        if ($request->hasFile('personal_image')) {
            $validateData['personal_image'] = $request->file('personal_image')->store('personal_image', 'public');
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
            $profile = Doctor::findOrFail($id);

            if ($profile->user_id != $user_id) {
                return response()->json(["message" => 'Unauthorized'], 403);
            }

            $profile->delete();
            return response()->json('Profile deleted successfully', 200);
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

    public function getDoctorProfile($id)
    {
        if (auth()->id() != $id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You can only view your own profile.'
            ], 403);
        }

        $user = User::where('id', $id)
            ->where('role', 'doctor')
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Doctor user account not found.'
            ], 404);
        }

        $doctorProfile = Doctor::where('user_id', $user->id)->first();

        if (!$doctorProfile) {
            return response()->json([
                'success' => false,
                'message' => 'This doctor has an account but has not completed their profile details yet.',
                'user_info' => [
                    'user_id'   => $user->id,
                    'full_name' => $user->full_name,
                    'email'     => $user->email,
                ]
            ], 200);
        }

        return response()->json([
            'success' => true,
            'profile' => [
                'user_id'               => $user->id,
                'full_name'             => $user->full_name,
                'email'                 => $user->email,
                'phone_number'          => $doctorProfile->phone_number,
                'doctor_specialization' => $doctorProfile->doctor_specialization,
                'working_days'          => $doctorProfile->working_days,
                'personal_image'        => $doctorProfile->personal_image,
                'document_image'        => $doctorProfile->document_image,
                'bio'                   => $doctorProfile->bio,
                'years_of_experience'   => $doctorProfile->years_of_experience,
            ]
        ], 200);
    }

    public function getDoctorsBySpecialization($specialization)
    {
        $doctors = Doctor::with(['user', 'shift'])->where('doctor_specialization', $specialization)->get();

        if ($doctors->isEmpty()) {
            return response()->json(['message' => 'There are currently no doctors in this specialty.'], 404);
        }

        return response()->json([
            'success' => true,
            'doctors' => $doctors
        ], 200);
    }

    public function searchDoctors(Request $request)
    {
        $request->validate([
            'search' => 'required|string'
        ]);

        $doctors = Doctor::with(['user', 'shift'])->whereHas('user', function ($query) use ($request) {
            $query->where('full_name', 'like', '%' . $request->search . '%');
        })->get();

        return response()->json([
            'success' => true,
            'doctors' => $doctors
        ], 200);
    }

    public function getDoctorProfileForBooking($id)
    {
        $doctor = Doctor::with(['user', 'shift'])->find($id);

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

        $shiftName = $doctor->shift ? ($doctor->shift->name === 'Morning' ? 'Morning' : 'Evening') : 'Unlimited';

        return response()->json([
            'success' => true,
            'doctor_profile' => [
                'doctor_id'             => $doctor->id,
                'full_name'             => $doctor->user ? $doctor->user->full_name : 'Unknown Doctor',
                'doctor_specialization' => $doctor->doctor_specialization,
                'working_days'          => $doctor->working_days,
                'shift'                 => $shiftName,
                'personal_image'        => $doctor->personal_image,
                'bio'                   => $doctor->bio,
                'years_of_experience'   => $doctor->years_of_experience,
            ]
        ], 200);
    }

    public function getRandomDoctors()
    {
        $randomDoctors = Doctor::with('user')
            ->inRandomOrder()
            ->limit(5)
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Doctors retrieved successfully',
            'data' => $randomDoctors
        ], 200);
    }

    public function getDoctorUpcomingAppointments(Request $request, $date = null)
    {
        $doctor = auth()->user()->doctor;

        if (!$doctor) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized or Doctor profile not found.'
            ], 403);
        }

        if (!$date) {
            $date = Carbon::today()->toDateString();
        }

        $baseQuery = Appointment::where('doctor_id', $doctor->id)
            ->where('appointment_date', $date);

        $totalPatientsToday = (clone $baseQuery)->count();
        $remainingCount = (clone $baseQuery)->where('status', 'booked')->count();
        $completedCount = (clone $baseQuery)->where('status', 'completed')->count();

        return response()->json([
            'success' => true,
            'date' => $date,
            'statistics' => [
                'patients_today' => $totalPatientsToday,
                'remaining' => $remainingCount,
                'completed' => $completedCount
            ]
        ], 200);
    }
}
