<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\FeedbackController;
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
Route::post('/save-fcm-token', [UserController::class, 'saveFcmToken'])->middleware('auth:sanctum');


Route::post('/verifyotp', [AuthController::class, 'verifyOtp']);
Route::post('/forgotpassword', [AuthController::class, 'forgotPassword']);
Route::post('/resetpassword', [AuthController::class, 'resetPassword']);
Route::post('/resendotp', [AuthController::class, 'resendOtp']);


Route::middleware(['auth:sanctum', CheckAdmin::class])->group(function () {
    Route::prefix('/admin')->group(function () {
        Route::get('/doctor/approve/{id}', [AdminController::class, 'approveDoctor']);
        Route::get('/doctor/reject/{id}', [AdminController::class, 'rejectDoctor']);
        Route::get('/allusers', [AdminController::class, 'getAllUsers']);
        Route::get('/alldoctors', [AdminController::class, 'getAllDoctors']);
        Route::get('/allpatients', [AdminController::class, 'getAllPatients']);
        Route::get('/getuser/{id}', [AdminController::class, 'getUser']);
        Route::delete('/deleteuser/{id}', [AdminController::class, 'deleteUser']);
        Route::post('/doctor/shift-days/{id}', [AdminController::class, 'assignShiftAndDays']);
    });
});



Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('/appointments')->group(function () {
        Route::post('', [AppointmentController::class, 'book']);
        Route::get('/available-slots/{id}/{date}', [AppointmentController::class, 'getAvailableSlots']);
        Route::post('/{id}/cancel', [AppointmentController::class, 'cancelAppointment']);
        Route::put('/{id}/update', [AppointmentController::class, 'updateAppointment']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('/favorites')->group(function () {
        Route::post('/add/{doctorId}', [FavoriteController::class, 'addToFavorite']);
        Route::delete('/remove/{doctorId}', [FavoriteController::class, 'removeFromFavorite']);
        Route::get('getdoctors', [FavoriteController::class, 'getAllFavorites']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('/feedbacks')->group(function () {
        Route::post('/store', [FeedbackController::class, 'store']);
        Route::put('/update/{id}', [FeedbackController::class, 'update']);
        Route::delete('/destroy/{id}', [FeedbackController::class, 'destroy']);
        Route::get('/doctor', [FeedbackController::class, 'getDoctorFeedbacks']);
        Route::get('/patient', [FeedbackController::class, 'getPatientFeedbacks']);
        Route::get('/{id}', [FeedbackController::class, 'showFeedback']);
    });
});


Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('/profile/patient')->group(function () {
        Route::post('/store', [PatientController::class, 'storeProfile']);
        Route::get('/get/{id}', [PatientController::class, 'getProfile']);
        Route::put('/update/{id}', [PatientController::class, 'updateProfile']);
        Route::delete('/delete/{id}', [PatientController::class, 'destroyProfile']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('/profile/doctor')->group(function () {
        Route::post('/store', [DoctorController::class, 'storeProfile']);
        Route::put('/update/{id}', [DoctorController::class, 'updateProfile']);
        Route::delete('/delete/{id}', [DoctorController::class, 'destroyProfile']);
    });
    Route::get('/doctors/specialization/{specialization}', [DoctorController::class, 'getDoctorsBySpecialization']);
    Route::get('/doctors/search', [DoctorController::class, 'searchDoctors']);
    Route::get('/doctors/profile/{id}', [DoctorController::class, 'getDoctorProfileForBooking']);
});


