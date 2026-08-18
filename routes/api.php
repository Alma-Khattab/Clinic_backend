<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Api\FirebaseAuthController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\MedicalRecordController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\CheckAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes (المسارات العامة)
|--------------------------------------------------------------------------
*/

// Auth / User Routes
Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);

// OTP & Password Reset
Route::post('/verifyotp', [AuthController::class, 'verifyOtp']);
Route::post('/forgotpassword', [AuthController::class, 'forgotPassword']);
Route::post('/resetpassword', [AuthController::class, 'resetPassword']);
Route::post('/resendotp', [AuthController::class, 'resendOtp']);


/*
|--------------------------------------------------------------------------
| Protected Routes (المسارات التي تتطلب تسجيل دخول auth:sanctum)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // User Session & FCM
    Route::get('/user', function (Request $request) {
        return $request->user() ? $request->user()->load('patient') : null;
    });
    Route::post('/logout', [UserController::class, 'logout']);
    Route::post('/save-fcm-token', [UserController::class, 'saveFcmToken']);
    //newميزة تعديل الايميل
    Route::put('/user/update-profile', [UserController::class, 'updateUserBasicInfo']);
    Route::post('/user/change-email-request', [UserController::class, 'changeEmailRequest']);
    Route::post('/user/verify-change-email', [UserController::class, 'verifyChangeEmail']);
    /*
    |--------------------------------------------------------------------------
    | Admin Routes (مسارات الأدمن)
    |--------------------------------------------------------------------------
    */
    Route::middleware(CheckAdmin::class)->prefix('/admin')->group(function () {
        Route::get('/doctor/approve/{id}', [AdminController::class, 'approveDoctor']);
        Route::get('/doctor/reject/{id}', [AdminController::class, 'rejectDoctor']);
        Route::get('/allusers', [AdminController::class, 'getAllUsers']);
        Route::get('/alldoctors', [AdminController::class, 'getAllDoctors']);
        Route::get('/allpatients', [AdminController::class, 'getAllPatients']);
        Route::get('/getuser/{id}', [AdminController::class, 'getUser']);
        Route::delete('/deleteuser/{id}', [AdminController::class, 'deleteUser']);
        Route::post('/doctor/shift-days/{id}', [AdminController::class, 'assignShiftAndDays']);
    });

    /*
    |--------------------------------------------------------------------------
    | Appointment Routes (مسارات المواعيد)
    |--------------------------------------------------------------------------
    */
    Route::prefix('/appointments')->group(function () {
        Route::post('', [AppointmentController::class, 'book']);
        Route::get('/available-slots/{id}/{date}', [AppointmentController::class, 'getAvailableSlots']);
        Route::post('/{id}/cancel', [AppointmentController::class, 'cancelAppointment']);
        Route::put('/cancel/{id}', [AppointmentController::class, 'cancelAppointment']);
        Route::put('/{id}/update', [AppointmentController::class, 'updateAppointment']);
        Route::put('/no-show/{appointment_id}', [AppointmentController::class, 'markAsNoShow']);
        Route::put('/complete/{id}', [AppointmentController::class, 'completeAppointment']);
    });

    /*
    |--------------------------------------------------------------------------
    | Doctor Specific Appointment Routes (مواعيد الطبيب)
    |--------------------------------------------------------------------------
    */
    Route::prefix('/doctor/appointments')->group(function () {
        Route::get('/history', [AppointmentController::class, 'getDoctorAppointmentHistory']);
        Route::get('/day/{date}', [AppointmentController::class, 'getDoctorAppointmentsByDate']);
    });
    Route::get('/patient/appointments/history', [AppointmentController::class, 'getPatientAppointmentHistory']);

    /*
    |--------------------------------------------------------------------------
    | Profiles Routes (الملفات الشخصية)
    |--------------------------------------------------------------------------
    */
    // Patient Profile
    Route::prefix('/profile/patient')->group(function () {
        Route::post('/store', [PatientController::class, 'storeProfile']);
        Route::get('/missed', [PatientController::class, 'getMissedAppointments']);
        Route::get('/get/{id}', [PatientController::class, 'getProfile']);
        Route::put('/update/{id}', [PatientController::class, 'updateProfile']);
        Route::delete('/delete/{id}', [PatientController::class, 'destroyProfile']);
    });

    // Doctor Profile
    Route::prefix('/profile/doctor')->group(function () {
        Route::get('/random', [DoctorController::class, 'getRandomDoctors']);
        Route::post('/store', [DoctorController::class, 'storeProfile']);
        Route::get('/get/{id}', [DoctorController::class, 'getDoctorProfile']);
        Route::put('/update/{id}', [DoctorController::class, 'updateProfile']);
        Route::delete('/delete/{id}', [DoctorController::class, 'destroyProfile']);
    });

    // Doctor General Endpoints
    Route::get('/doctor/my-appointments/{date}', [DoctorController::class, 'getDoctorUpcomingAppointments']);
    Route::get('/doctors/specialization/{specialization}', [DoctorController::class, 'getDoctorsBySpecialization']);
    Route::get('/doctors/search', [DoctorController::class, 'searchDoctors']);
    Route::get('/doctors/profile/{id}', [DoctorController::class, 'getDoctorProfileForBooking']);

    /*
    |--------------------------------------------------------------------------
    | Favorites Routes (المفضلة)
    |--------------------------------------------------------------------------
    */
    Route::prefix('/favorites')->group(function () {
        Route::post('/add/{doctorId}', [FavoriteController::class, 'addToFavorite']);
        Route::delete('/remove/{doctorId}', [FavoriteController::class, 'removeFromFavorite']);
        Route::get('/getdoctors', [FavoriteController::class, 'getAllFavorites']);
    });

    /*
    |--------------------------------------------------------------------------
    | Feedbacks Routes (التقييمات والملاحظات)
    |--------------------------------------------------------------------------
    */
    Route::prefix('/feedbacks')->group(function () {
        Route::post('/store', [FeedbackController::class, 'store']);
        Route::put('/update/{id}', [FeedbackController::class, 'update']);
        Route::delete('/destroy/{id}', [FeedbackController::class, 'destroy']);
        Route::get('/doctor', [FeedbackController::class, 'getDoctorFeedbacks']);
        Route::get('/patient', [FeedbackController::class, 'getPatientFeedbacks']);
        Route::get('/{id}', [FeedbackController::class, 'showFeedback']);
    });

    /*
    |--------------------------------------------------------------------------
    | Medical Records Routes (السجلات الطبية)
    |--------------------------------------------------------------------------
    */
    Route::post('/medical-record', [MedicalRecordController::class, 'store']);
    Route::put('/medical-record/{appointment_id}', [MedicalRecordController::class, 'update']);
    Route::get('/medical-record/{appointment_id}', [MedicalRecordController::class, 'show']);
    Route::get('/medical-records/patient/{patient_id}', [MedicalRecordController::class, 'getPatientHistory']);
    Route::get('/medical-records/doctor/{doctor_id}', [MedicalRecordController::class, 'getDoctorMedicalRecords']);

    Route::post('/auth/firebase', [FirebaseAuthController::class, 'handleFirebaseToken']);

    Route::middleware('auth:sanctum')->group(function () {
           // 1. رابط جلب كل الإشعارات
            Route::get('/notifications', [NotificationController::class, 'index']);
            // // 2. رابط تحديث الإشعار كمقروء
            Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        });
});
