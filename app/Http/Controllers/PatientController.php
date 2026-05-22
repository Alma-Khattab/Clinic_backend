<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatientRequest;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PatientController extends Controller
{
    public function storeProfile(PatientRequest $request){
        $user_id = Auth::user()->id;
        $validateData = $request->validated();
        $validateData['user_id'] = $user_id;
        if ($request->hasFile('personal_image')) {
            $path = $request->file('personal_image')->store('personal_image', 'public');
            $validateData['personal_image'] = $path;
        }
        $profile = Patient::create($validateData);
        return response()->json([
            'massege' => "successfully created profile",
            'profile' => $profile
        ], 201);
    }
}
