<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    public function approveDoctor($id)
    {
        $doctor = User::findOrFail($id);

        if (!$doctor){
            return response()->json(['message' => 'User Not Found'], 404);
        }
        if ($doctor->role !== 'doctor') {
            return response()->json([
                'message' => 'This user is not a doctor.'
            ], 400);
        }

        $doctor->status = 'approved';
        $doctor->save();
        // 📍 تسجيل عملية القبول
        Log::info("Doctor approved by admin", [
            'admin_id' => Auth::user(),
            'doctor_id' => $doctor->id
        ]);

        return response()->json([
            'message' => 'Doctor approved successfully.'
        ]);
    }
    public function rejectDoctor($id)
    {
        $doctor = User::findOrFail($id);

        if ($doctor->role !== 'doctor') {
            return response()->json([
                'message' => 'This user is not a doctor.'
            ], 400);
        }

        $doctor->status = 'rejected';
        $doctor->save();
        // 📍 تسجيل عملية الرفض
        Log::info("Doctor rejected by admin", [
            'admin_id' => Auth::user(),
            'doctor_id' => $doctor->id
        ]);

        return response()->json([
            'message' => 'Doctor rejected.'
        ]);
    }

    public function getAllUsers()
    {
        $users = User::where('role', '!=', 'admin')->get();
        return response()->json($users, 200);
    }

    public function getAllPatients()
    {
        $patients = User::with('patient')
            ->where('role', 'patient')
            ->get();
        return response()->json([
            'patients' => $patients
        ], 200);
    }

    public function getAllDoctors()
    {
        $doctors = User::with('doctor')
            ->where('role', 'doctor')
            ->get();
        return response()->json([
            'doctors' => $doctors
        ], 200);
    }

    public function getUser($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User Not Found'], 404);
        }
        return response()->json([
            'message' => 'Operation completed successfully',
            'user' => $user
        ], 200);
    }

    public function deleteUser($id ,Request $request)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($request->user()->id == $user->id) {
            return response()->json([
                'message' => 'Admin cannot delete himself'
            ], 403);
        }
        // 📍 تسجيل عملية الحذف (خطيرة جداً ويجب توثيقها)
        Log::warning("User deleted by admin", [
            'admin_id' => $request->user()->id,
            'deleted_user_id' => $user->id,
            'deleted_user_email' => $user->email
        ]);

        $user->delete();
        return response()->json('User deleted successfully', 200);
    }


    public function assignShiftAndDays(Request $request, $id)
{
    $validated = $request->validate([
        'shift_id'       => 'required|exists:shifts,id',
        'working_days'   => 'required|array|min:1',
        'working_days.*' => 'string|in:Sunday,Monday,Tuesday,Wednesday,Thursday,Saturday'
    ]);

    $user = User::findOrFail($id);
    if ($user->role !== 'doctor') {
                return response()->json([
                    'message' => 'Assign shifts only to doctors.'
                ], 403);
            }

    $doctor = Doctor::updateOrCreate(
        ['user_id' => $user->id],
        [
            'shift_id'     => $validated['shift_id'],
            'working_days' => $validated['working_days'],
        ]
    );
    // 📍 تسجيل تغيير وردية الدكتور
        Log::info("Doctor shift assigned by admin", [
            'admin_id' => Auth::user(),
            'doctor_id' => $user->id,
            'shift_id' => $validated['shift_id']
        ]);
    return response()->json([
        'success' => true,
        'message' => 'The shift and working days have been successfully added.'
    ], 200);
}
}
