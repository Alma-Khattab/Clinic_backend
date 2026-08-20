<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Http\Requests\StoreAppointmentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
////////////new
use Illuminate\Support\Facades\Log;

class AppointmentController extends Controller
{
    public function getAvailableSlots($id, $date)
    {
        // ✅ التعديل هنا: أخذ التاريخ مباشرة من بارامتر الدالة القادم من الرابط (وبدون سيشن)
        $chosenDate = $date;

        $doctor = Doctor::with('shift')->findOrFail($id);

        if (!$doctor->shift) {
            return response()->json(['success' => false, 'message' => "No work shift has been assigned for this doctor yet."], 422);
        }

        $dayName = Carbon::parse($chosenDate)->format('l');

        if (!in_array($dayName, $doctor->working_days)) {
            return response()->json(['success' => false, 'message' => "The doctor is not working on this day."], 422);
        }

        $startTime = Carbon::parse($doctor->shift->start_time);
        $endTime = Carbon::parse($doctor->shift->end_time);
        $allSlots = [];

        while ($startTime->lt($endTime)) {
            $requestedDateTime = Carbon::parse($chosenDate . ' ' . $startTime->format('H:i'), 'Asia/Damascus');
            $now = Carbon::now('Asia/Damascus');

            if ($requestedDateTime->getTimestamp() > $now->getTimestamp()) {
                // ✅ تعديل إضافي: توليد الوقت بالثواني ليطابق تماماً تخزين الداتابيز ويحذف المحجوز بنجاح
                $allSlots[] = $startTime->format('H:i:s');
            }

            $startTime->addMinutes(30);
        }

        $bookedTimes = Appointment::where('doctor_id', $id)
            ->where('appointment_date', $chosenDate)
            ->where('status', 'booked')
            ->pluck('appointment_time')
            ->toArray();

        $availableSlots = array_diff($allSlots, $bookedTimes);

        return response()->json([
            'success'         => true,
            'date_checked'    => $chosenDate,
            'shift_name'      => $doctor->shift->name,
            'available_slots' => array_values($availableSlots)
        ]);
    }
    public function book(StoreAppointmentRequest $request)
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'patient' || !$user->patient) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only patients can book appointments.'
            ], 403);
        }

        $missedCount = Appointment::where('user_id', $user->id)
            ->where('status', 'missed')
            ->count();

        if ($missedCount >= 3 || ($user->patient && $user->patient->is_blocked)) {
            // 📍 تسجيل محاولة حجز من مريض محظور
            Log::warning("Blocked patient attempted to book an appointment", [
                'user_id'      => $user->id,
                'missed_count' => $missedCount
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Sorry, you have been blocked from booking because you missed 3 appointments.'
            ], 403);
        }

        $validatedData = $request->validated();

        $doctor = Doctor::with('shift')->findOrFail($validatedData['doctor_id']);
        $dayName = Carbon::parse($validatedData['appointment_date'])->format('l');

        if ($doctor->shift) {
            $bookingTime = Carbon::parse($validatedData['appointment_time']);
            $shiftStart  = Carbon::parse($doctor->shift->start_time);
            $shiftEnd    = Carbon::parse($doctor->shift->end_time);

            $minutesDifference = $shiftStart->diffInMinutes($bookingTime);
            if ($minutesDifference % 30 !== 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Invalid appointment time. Appointments must be booked in 30-minute intervals."
                ], 422);
            }

            if ($bookingTime->lt($shiftStart) || $bookingTime->gte($shiftEnd)) {
                return response()->json([
                    'success' => false,
                    'message' => "Sorry, this time is outside the doctor's working hours for this shift ({$doctor->shift->name})."
                ], 422);
            }
        } else {
            return response()->json(['success' => false, 'message' => "The doctor's shift details are not defined."], 422);
        }

        if (!in_array($dayName, $doctor->working_days)) {
            return response()->json(['success' => false, 'message' => "The doctor does not have a shift on this day."], 422);
        }

        $requestedDateTime = Carbon::parse($validatedData['appointment_date'] . ' ' . $validatedData['appointment_time'], 'Asia/Damascus');
        $now = Carbon::now('Asia/Damascus');

        if ($requestedDateTime->getTimestamp() <= $now->getTimestamp()) {
            return response()->json(['success' => false, 'message' => "Sorry, this time has passed, please choose a future time."], 422);
        }

        $isAlreadyBooked = Appointment::where('doctor_id', $validatedData['doctor_id'])
            ->where('appointment_date', $validatedData['appointment_date'])
            ->where('appointment_time', $validatedData['appointment_time'])
            ->where('status', 'booked')
            ->exists();

        if ($isAlreadyBooked) {
            return response()->json(['success' => false, 'message' => 'The appointment is already booked.'], 422);
        }

        $isPatientBusy = Appointment::where('user_id', Auth::id())
            ->where('appointment_date', $validatedData['appointment_date'])
            ->where('appointment_time', $validatedData['appointment_time'])
            ->where('status', 'booked')
            ->exists();

        if ($isPatientBusy) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an appointment at this time.'
            ], 422);
        }

        $validatedData['user_id'] = Auth::id();
        $validatedData['status']  = 'booked';
        // 🟢 سحب المكافأة فور الحجز وتخصيص الموعد كمجاني    بداية
        $patient = $user->patient;
        if ($patient && $patient->has_free_visit) {
            $validatedData['is_free'] = true;
            $patient->has_free_visit = false;
            $patient->save();
        } else {
            $validatedData['is_free'] = false;
        }//نهاية



        $appointment = Appointment::create($validatedData);
        // 📍 تسجيل حجز موعد جديد
        Log::info("New appointment booked", [
            'appointment_id'   => $appointment->id,
            'patient_user_id'  => $user->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => $appointment->appointment_date,
            'appointment_time' => $appointment->appointment_time
        ]);

        // إرسال الإشعارات
        $doctorUser = $doctor->user;
        $patientUser = Auth::user();
        $firebase = app(\App\Services\FirebaseNotificationService::class);
        //
        if ($patientUser->fcm_token) {
            $firebase->sendNotification(
                $patientUser->fcm_token,
                'Appointment Confirmed',
                "Your appointment with {$doctorUser->full_name} is confirmed for {$appointment->appointment_date} at {$appointment->appointment_time}.\n\n⚠️ Important Note: Please attend on time. Missing 3 scheduled appointments will result in an automatic block from booking future appointments.", //تم حجز موعدك بنجاح! ⚠️ يرجى الانتباه: عدم الحضور لـ 3 مواعيد يتسبب في حظر الحساب تلقائياً.
                [
                    'type' => 'appointment',
                    'id' => (string)$appointment->id,
                    'user_id' => $patientUser->id  // 👈 إضافة هذا السطر ضرورية جداً
                ]
            );
        }

        if ($doctorUser && $doctorUser->fcm_token) {
            $firebase->sendNotification(
                $doctorUser->fcm_token,
                'New Appointment',
                "You have a new appointment with {$patientUser->full_name} on {$appointment->appointment_date} at {$appointment->appointment_time}",
                [
                        'type'=>'appointment',
                        'id'=>(string)$appointment->id,
                        'user_id' => $doctorUser->id
                    ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'The appointment has been successfully booked and confirmed!',
            'booking' => $appointment
        ], 201);
    }

    public function markAsNoShow($id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'doctor' || !$user->doctor) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only doctors can perform this action.'
            ], 403);
        }

        $appointment = Appointment::where('id', $id)
            ->where('doctor_id', $user->doctor->id)
            ->firstOrFail();

        if ($appointment->status === 'completed' || $appointment->status === 'missed') {
            return response()->json([
                'success' => false,
                'message' => 'This appointment status cannot be changed.'
            ], 400);
        }

        $appointment->status = 'missed';
        $appointment->save();

        $patient = $appointment->patient;

        if ($patient) {
            $patient->increment('missed_appointments_count');
            // 🟢 التعديل الجديد: تصفير عداد المكافآت لأن المريض غاب ولم يلتزم
           $patient->rewards_count = 0;
           $patient->has_free_visit = false; // 👈 ضف هذا السطر فقط
           $patient->save(); // نهاية التعديل

            // 📍 تسجيل تغيير الحالة إلى missed
            Log::notice("Appointment marked as missed", [
                'appointment_id' => $appointment->id,
                'patient_id'     => $patient->id,
                'doctor_id'      => $user->doctor->id
            ]);

            if ($patient->missed_appointments_count >= 3) {
                // Auto-cancel all upcoming booked appointments for this patient
                Appointment::where('user_id', $appointment->user_id)
                    ->where('status', 'booked')
                    ->update(['status' => 'cancelled']);
                //اشعارات
                $patientUser = $appointment->user; // أو $patient->user حسب علاقات Models لديك
                if ($patientUser && $patientUser->fcm_token) {
                    $firebase = app(\App\Services\FirebaseNotificationService::class);
                    $firebase->sendNotification(
                        $patientUser->fcm_token,
                        'Account Blocked',
                        'You have been blocked from booking new appointments due to missing 3 appointments, and all your upcoming appointments have been cancelled.',
                        [
                            'type' => 'appointment',
                            'id' => (string)$appointment->id,
                            'user_id' => $patientUser->id // ✅ تمت إضافة المصفوفة كاملة هنا
                        ]
                    );
                }
                // تسجيل تحذير في الـ Log عند حظر المريض
                Log::warning("Patient Blocked due to missed appointments", [
                    'patient_id' => $patient->id,
                    'missed_count' => $patient->missed_appointments_count
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'Appointment marked as missed. The patient has reached the limit (3) and is now blocked from future bookings. All upcoming appointments have been cancelled.'
                ], 200);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Appointment marked as missed. Patient warning counter increased by 1.'
        ], 200);
    }

    public function getDoctorAppointmentHistory(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'doctor' || !$user->doctor) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only doctors can view this history.'
            ], 403);
        }
        $perPage = $request->input('per_page', 10);

        $history = Appointment::where('doctor_id', $user->doctor->id)
            ->select('id', 'appointment_date', 'appointment_time', 'user_id', 'doctor_id', 'status')
            ->with([
                'user' => function ($query) {
                    $query->select('id', 'full_name');
                },
                'user.patient' => function ($query) {
                    $query->select('id', 'user_id', 'personal_image');
                }
            ])
            ->orderBy('appointment_date', 'desc')
            ->orderBy('appointment_time', 'desc')
            ->paginate($perPage);

        $customHistory = $history->through(function ($appointment) {
            return [
                'id' => $appointment->id,
                'appointment_date' => $appointment->appointment_date,
                'appointment_time' => $appointment->appointment_time,
                'status' => $appointment->status,
                'patient_name' => $appointment->user ? $appointment->user->full_name : 'Unknown Patient',
                'patient_image' => ($appointment->user && $appointment->user->patient)
                    ? $appointment->user->patient->personal_image
                    : null
            ];
        });

        return response()->json([
            'success' => true,
            'total_appointments' => $customHistory->count(),
            'history' => $customHistory
        ], 200);
    }

    public function getPatientAppointmentHistory()
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'patient'  || !$user->patient) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only patients can view this history.'
            ], 403);
        }

        $history = Appointment::where('user_id', $user->id)
            ->select('id', 'appointment_date', 'appointment_time', 'user_id', 'doctor_id', 'status')
            ->with([
                'doctor.user' => function ($query) {
                    $query->select('id', 'full_name');
                }
            ])
            ->orderBy('appointment_date', 'desc')
            ->orderBy('appointment_time', 'desc')
            ->get();

        $customHistory = $history->map(function ($appointment) {
            return [
                'id' => $appointment->id,
                'appointment_date' => $appointment->appointment_date,
                'appointment_time' => $appointment->appointment_time,
                'status' => $appointment->status,
                'doctor_name' => ($appointment->doctor && $appointment->doctor->user)
                    ? $appointment->doctor->user->full_name
                    : 'Unknown Doctor',
                'doctor_specialization' => $appointment->doctor
                    ? $appointment->doctor->doctor_specialization
                    : 'General',
                'doctor_image' => $appointment->doctor
                    ? $appointment->doctor->personal_image
                    : null
            ];
        });

        return response()->json([
            'success' => true,
            'total_appointments' => $customHistory->count(),
            'history' => $customHistory
        ], 200);
    }

    public function getDoctorAppointmentsByDate($date)
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'doctor' || !$user->doctor) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only doctors can view these appointments.'
            ], 403);
        }

        $appointments = Appointment::where('doctor_id', $user->doctor->id)
            ->where('appointment_date', $date)
            ->select('id', 'appointment_date', 'appointment_time', 'user_id', 'doctor_id', 'status','is_free')
            ->with([
                'user' => function ($query) {
                    $query->select('id', 'full_name');
                },
                'user.patient' => function ($query) {
                    $query->select('id', 'user_id', 'personal_image');
                }
            ])
            ->orderBy('appointment_time', 'asc')
            ->get();

        $formattedAppointments = $appointments->map(function ($appointment) {
            return [
                'id' => $appointment->id,
                'appointment_date' => $appointment->appointment_date,
                'appointment_time' => $appointment->appointment_time,
                'status' => $appointment->status,
                'is_free' => (bool) $appointment->is_free, //معدل
                'patient_name' => $appointment->user ? $appointment->user->full_name : 'Unknown Patient',
                'patient_image' => ($appointment->user && $appointment->user->patient)
                    ? $appointment->user->patient->personal_image
                    : null,
                'patient_user_id' => $appointment->user_id,
                'patient_profile_id' => ($appointment->user && $appointment->user->patient)
                    ? $appointment->user->patient->id
                    : null
            ];
        });

        return response()->json([
            'success' => true,
            'selected_date' => $date,
            'total_appointments' => $formattedAppointments->count(),
            'appointments' => $formattedAppointments
        ], 200);
    }

    public function completeAppointment($id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'doctor' || !$user->doctor) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only doctors can complete appointments.'
            ], 403);
        }

        $appointment = Appointment::where('id', $id)
            ->where('doctor_id', $user->doctor->id)
            ->firstOrFail();

        if ($appointment->status !== 'booked') {
            return response()->json([
                'success' => false,
                'message' => "This appointment cannot be completed because its current status is: {$appointment->status}."
            ], 400);
        }
        // تحديث نظام المكافآت للمريض بداية التعديل
              // 🟢 نظام المكافآت الجديد
        $patient = \App\Models\Patient::where('user_id', $appointment->user_id)->first();
        if ($patient) {
            // نزيد العداد فقط إذا الموعد ليس مجانياً
            if (!$appointment->is_free) {
                $patient->rewards_count += 1;
                if ($patient->rewards_count >= 3) {
                    $patient->has_free_visit = true;
                    $patient->rewards_count = 0;
                }
                $patient->save();
            }
        }//نهاية التعديل

        $appointment->status = 'completed';
        $appointment->save();
        if($appointment->patiaent && $appointment->patiant->fcm_token){
            $firebase = app(\app\Services\FirebaseNotificationService::class);
            $firebase->sendNotification(
                $appointment->patient->fcm_token,
                'اكتمل الموعد',
                'تم اكمال الموعد  بنجاح نتمنى لكم دوام الصحة',
                [
                    'type'=>'appointment',
                    'id'=>(string)$appointment->id,
                    'user_id' => $appointment->user_id // ✅ تمت إضافة هذا السطر
                ]
            );
        }
        // 📍 تسجيل إكمال الموعد
        Log::info("Appointment marked as completed", [
            'appointment_id' => $appointment->id,
            'doctor_id'      => $user->doctor->id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Appointment has been successfully marked as completed.'
        ], 200);
    }

    public function cancelAppointment($id)
    {
        $user = Auth::user();
        $appointment = Appointment::with('user', 'doctor.user')->findOrFail($id);

        if ($appointment->status !== 'booked') {
            return response()->json([
                'message' => 'Appointment cannot be cancelled'
            ], 422);
        }

        $firebase = app(\App\Services\FirebaseNotificationService::class);

        if ($user->role === 'patient') {
            if ($appointment->user_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            $appointment->status = 'cancelled';
            $appointment->save();
            // 🟢 عقاب الإلغاء: تصفير المكافآت وسحبها
            $patient = $user->patient;
            if ($patient) {
                $patient->rewards_count = 0;
                $patient->has_free_visit = false;
                $patient->save();
            }//نهاية التعديل
            ////////////new
            // 📍 تسجيل عملية إلغاء الموعد بواسطة المريض
            Log::info("Appointment Cancelled", [
                'appointment_id' => $appointment->id,
                'cancelled_by_role' => $user->role,
                'user_id' => $user->id
            ]);
            ////////////
            $doctorUser = $appointment->doctor->user;
            if ($user->fcm_token) {
                $firebase->sendNotification(
                    $user->fcm_token,
                    'Cancelled Successfully',
                    'Your appointment has been cancelled successfully.',
                    [
                        'type'=>'appointment',
                        'id'=>(string)$appointment->id,
                        'user_id' => $user->id // ✅ تمت الإضافة
                    ]
                );
            }
            if ($doctorUser && $doctorUser->fcm_token) {
                $firebase->sendNotification(
                    $doctorUser->fcm_token,
                    'Appointment Cancelled',
                    'The patient has cancelled the appointment.',
                    [
                        'type'=>'appointment',
                        'id'=>(string)$appointment->id,
                        'user_id' => $doctorUser->id // ✅ تمت الإضافة
                    ]
                );
            }
        } else if ($user->role === 'doctor') {
            if ($appointment->doctor->user_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            $appointment->status = 'cancelled';
            $appointment->save();

            $patientUser = $appointment->user;
            if ($user->fcm_token) {
                $firebase->sendNotification(
                    $user->fcm_token,
                    'Cancelled Successfully',
                    'Appointment has been cancelled successfully.',
                    [
                        'type'=>'appointment',
                        'id'=>(string)$appointment->id,
                        'user_id' => $user->id // ✅ تمت الإضافة
                    ]
                );
            }
            if ($patientUser && $patientUser->fcm_token) {
                $firebase->sendNotification(
                    $patientUser->fcm_token,
                    'Appointment Cancelled',
                    'The doctor has cancelled your appointment.',
                    [
                        'type'=>'appointment',
                        'id'=>(string)$appointment->id,
                        'user_id' => $patientUser->id // ✅ تمت الإضافة
                    ]
                );
            }
        } else {
            return response()->json([
                'message' => 'Unauthorized role'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Appointment cancelled successfully'
        ]);
    }

    public function updateAppointment(Request $request, $id)
    {
        $request->validate([
            'appointment_date' => 'required|date|date_format:Y-m-d|after_or_equal:today',
            'appointment_time' => 'required|date_format:H:i',
        ]);

        $appointment = Appointment::with('doctor.shift', 'doctor.user')->findOrFail($id);

        if ($appointment->status !== 'booked') {
            return response()->json([
                'success' => false,
                'message' => 'Only active appointments can be rescheduled.'
            ], 422);
        }

        if ($appointment->user_id != Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $doctor = $appointment->doctor;

        if (!$doctor->shift) {
            return response()->json([
                'success' => false,
                'message' => "The doctor's shift details are not defined."
            ], 422);
        }

        $dayName = Carbon::parse($request->appointment_date)->format('l');

        if (!in_array($dayName, $doctor->working_days)) {
            return response()->json([
                'success' => false,
                'message' => "The doctor does not have a shift on this day."
            ], 422);
        }

        $bookingTime = Carbon::parse($request->appointment_time);
        $shiftStart = Carbon::parse($doctor->shift->start_time);
        $shiftEnd = Carbon::parse($doctor->shift->end_time);

        $minutesDifference = $shiftStart->diffInMinutes($bookingTime);

        if ($minutesDifference % 30 !== 0) {
            return response()->json([
                'success' => false,
                'message' => 'Appointments must be booked in 30-minute intervals.'
            ], 422);
        }

        if ($bookingTime->lt($shiftStart) || $bookingTime->gte($shiftEnd)) {
            return response()->json([
                'success' => false,
                'message' => "Sorry, this time is outside the doctor's working hours."
            ], 422);
        }

        $requestedDateTime = Carbon::parse(
            $request->appointment_date . ' ' . $request->appointment_time,
            'Asia/Damascus'
        );

        if ($requestedDateTime->lte(Carbon::now('Asia/Damascus'))) {
            return response()->json([
                'success' => false,
                'message' => 'Please choose a future time.'
            ], 422);
        }

        $isAlreadyBooked = Appointment::where('doctor_id', $doctor->id)
            ->where('appointment_date', $request->appointment_date)
            ->where('appointment_time', $request->appointment_time)
            ->where('status', 'booked')
            ->where('id', '!=', $appointment->id)
            ->exists();

        if ($isAlreadyBooked) {
            return response()->json([
                'success' => false,
                'message' => 'The appointment is already booked.'
            ], 422);
        }

        $isPatientBusy = Appointment::where('user_id', Auth::id())
            ->where('appointment_date', $request->appointment_date)
            ->where('appointment_time', $request->appointment_time)
            ->where('status', 'booked')
            ->where('id', '!=', $appointment->id)
            ->exists();

        if ($isPatientBusy) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an appointment at this time.'
            ], 422);
        }

        $oldDate = $appointment->appointment_date;
        $oldTime = $appointment->appointment_time;

        $appointment->update([
            'appointment_date' => $request->appointment_date,
            'appointment_time' => $request->appointment_time,
        ]);
        // 📍 تسجيل إعادة جدولة الموعد (تعديل الوقت/التاريخ)
        Log::info("Appointment rescheduled", [
            'appointment_id' => $appointment->id,
            'user_id'        => Auth::id(),
            'old_date'       => $oldDate,
            'old_time'       => $oldTime,
            'new_date'       => $appointment->appointment_date,
            'new_time'       => $appointment->appointment_time
        ]);

        $firebase = app(\App\Services\FirebaseNotificationService::class);
        $patientUser = Auth::user();
        $doctorUser = $doctor->user;

        if ($patientUser->fcm_token) {
            $firebase->sendNotification(
                $patientUser->fcm_token,
                'Appointment Updated',
                'Your appointment has been changed successfully.',
                [
                        'type'=>'appointment',
                        'id'=>(string)$appointment->id,
                        'user_id' => $patientUser->id // ✅ تمت الإضافة
                    ]
            );
        }

        if ($doctorUser && $doctorUser->fcm_token) {
            $firebase->sendNotification(
                $doctorUser->fcm_token,
                'Appointment Updated',
                'A patient has changed an appointment time.',
                [
                        'type'=>'appointment',
                        'id'=>(string)$appointment->id,
                        'user_id' => $doctorUser->id // ✅ تمت الإضافة
                    ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Appointment updated successfully.',
            'old_date' => $oldDate,
            'old_time' => $oldTime,
            'new_date' => $appointment->appointment_date,
            'new_time' => $appointment->appointment_time,
        ]);
    }
}
