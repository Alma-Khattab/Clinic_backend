<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\MedicalRecordController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\ShiftController;
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
        Route::post('/doctor/shift-days/{id}' ,[AdminController::class ,'assignShiftAndDays']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('/profile/doctor')->group(function () {
        Route::get('/random', [DoctorController::class, 'getRandomDoctors']);
        Route::post('/store', [DoctorController::class, 'storeProfile']);
        Route::put('/update/{id}',[DoctorController::class,'updateProfile']);
        Route::delete('/delete/{id}',[DoctorController::class,'destroyProfile']);
        Route::get('/get/{id}', [DoctorController::class, 'getDoctorProfile']);
    });
    Route::get('/doctor/my-appointments/{date}', [DoctorController::class, 'getDoctorUpcomingAppointments']);
    Route::get('/doctors/specialization/{specialization}', [DoctorController::class, 'getDoctorsBySpecialization']);
    Route::get('/doctors/profile/{id}', [DoctorController::class, 'getDoctorProfileForBooking']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/appointments' ,[AppointmentController::class , 'book']);
    Route::get('/appointments/available-slots/{id}/{date}' ,[AppointmentController::class , 'getAvailableSlots']);
    Route::put('/appointments/no-show/{appointment_id}', [AppointmentController::class, 'markAsNoShow']);
    Route::get('/doctor/appointments/history', [AppointmentController::class, 'getDoctorAppointmentHistory']);
    Route::get('/doctor/appointments/day/{date}', [AppointmentController::class, 'getDoctorAppointmentsByDate']);
    Route::put('/appointments/complete/{id}', [AppointmentController::class, 'completeAppointment']);
    Route::put('/appointments/cancel/{id}', [AppointmentController::class, 'cancelAppointment']);
});




Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('/profile/patient')->group(function () {
        Route::post('/store', [PatientController::class, 'storeProfile']);
        Route::get('/get/{id}' ,[PatientController::class , 'getProfile']);
        Route::put('/update/{id}' , [PatientController::class , 'updateProfile']);
        Route::delete('/delete/{id}',[PatientController::class,'destroyProfile']);
    });

    Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/medical-record', [MedicalRecordController::class, 'store']);
    Route::put('/medical-record/{appointment_id}', [MedicalRecordController::class, 'update']);
    Route::get('/medical-record/{appointment_id}', [MedicalRecordController::class, 'show']);
    Route::get('/medical-records/patient/{patient_id}', [MedicalRecordController::class, 'getPatientHistory']);
    Route::get('/medical-records/doctor/{doctor_id}', [MedicalRecordController::class, 'getDoctorMedicalRecords']);


});
});






