<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    public function addToFavorite($doctorId)
    {
        Doctor::findOrFail($doctorId);
        $user = Auth::user();
        if ($user->role !== 'patient') {
            return response()->json([
                'message' => 'Only patients can add TO favorites'
            ], 403);
        }
        $user->favoriteDoctors()->syncWithoutDetaching([$doctorId]);
        return response()->json([
            'message' => 'Doctor added to favorite'
        ], 200);
    }

    public function removeFromFavorite($doctorId)
    {
        $user = Auth::user();
        $user->favoriteDoctors()->detach($doctorId);
        return response()->json([
            'message' => 'Doctor removed from favorite'
        ], 200);
    }
    public function getAllFavorites()
    {
        $user = Auth::user();
        return response()->json([
            'favorite Doctors' => $user->favoriteDoctors
        ], 200);
    }
}
