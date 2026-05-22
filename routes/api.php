<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\CheckAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);
Route::post('/logout', [UserController::class, 'logout'])->middleware('auth:sanctum');


Route::post('/verifyotp', [AuthController::class, 'verifyOtp']);
Route::post('/forgotpassword', [AuthController::class, 'forgotPassword']);
Route::post('/resetpassword', [AuthController::class, 'resetPassword']);


Route::middleware(['auth:sanctum', CheckAdmin::class])->group(function () {
    Route::prefix('/admin')->group(function () {
        Route::get('/doctor/approve/{id}', [AdminController::class, 'approveDoctor']);
        Route::get('/doctor/reject/{id}', [AdminController::class, 'rejectDoctor']);
        Route::get('/allusers', [AdminController::class, 'getAllUsers']);
        Route::get('/alldoctors',[AdminController::class, 'getAllDoctors']);
        Route::get('/allpatients',[AdminController::class, 'getAllPatients']);
        Route::get('/getuser/{id}', [AdminController::class, 'getUser']);
        Route::delete('/deleteuser/{id}',[AdminController::class,'deleteUser']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('/profile/doctor')->group(function () {
        Route::post('/store', [DoctorController::class, 'storeProfile']);
        Route::get('/get/{id}',[DoctorController::class,'getProfile']);
        Route::put('/update/{id}',[DoctorController::class,'updateProfile']);
        Route::delete('/delete/{id}',[DoctorController::class,'destroyProfile']);
    });
});



Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('/patient')->group(function () {
        Route::post('/profile', [PatientController::class, 'storeProfile']);
    });
});




