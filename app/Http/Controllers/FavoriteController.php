<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FavoriteController extends Controller
{
    public function addToFavorite($doctorId)
    {
        $doctor = Doctor::where('id', $doctorId)
            ->whereHas('user', function ($query) {
                $query->where('status', 'approved');
            })->first();

        if (!$doctor) {
            return response()->json([
                'message' => 'Doctor not found or not approved by admin.'
            ], 404);
        }
        $user = Auth::user();
        if ($user->role !== 'patient') {
            return response()->json([
                'message' => 'Only patients can add TO favorites'
            ], 403);
        }
        $user->favoriteDoctors()->syncWithoutDetaching([$doctorId]);
        // 📍 تسجيل إضافة الطبيب إلى المفضلة
        Log::info("Doctor Added to Favorites", [
            'patient_user_id' => $user->id,
            'doctor_id'       => $doctorId
        ]);
        return response()->json([
            'message' => 'Doctor added to favorite'
        ], 200);
    }

    public function removeFromFavorite($doctorId)
    {
        $user = Auth::user();
        $user->favoriteDoctors()->detach($doctorId);
        // 📍 تسجيل إزالة الطبيب من المفضلة
        Log::info("Doctor Removed from Favorites", [
            'patient_user_id' => $user->id,
            'doctor_id'       => $doctorId
        ]);
        return response()->json([
            'message' => 'Doctor removed from favorite'
        ], 200);
    }
   public function getAllFavorites()
    {
        $user = Auth::user();
        $doctors = $user->favoriteDoctors()
            ->completedAndScheduled()
            ->with(['user', 'shift'])
            ->get();
        return response()->json([
            'favorite Doctors' => $doctors
        ], 200);
    }
}
