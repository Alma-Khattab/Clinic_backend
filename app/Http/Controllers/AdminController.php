<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

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

        $user->delete();
        return response()->json('User deleted successfully', 200);
    }
}
