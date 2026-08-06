<?php
namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Http\Requests\StoreAppointmentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AppointmentController extends Controller
{
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

        if ($missedCount >= 2) {
            return response()->json([
                'success' => false,
                'message' => 'You are blocked from booking appointments because you missed your previous appointments twice.'
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
        $appointment = Appointment::create($validatedData);

        // إرسال الإشعارات
        $doctorUser = $doctor->user;
        $patientUser = Auth::user();
        $firebase = app(\App\Services\FirebaseNotificationService::class);

        if ($patientUser->fcm_token) {
            $firebase->sendNotification(
                $patientUser->fcm_token,
                'Appointment Confirmed',
                'Your appointment has been booked successfully.'
            );
        }

        if ($doctorUser && $doctorUser->fcm_token) {
            $firebase->sendNotification(
                $doctorUser->fcm_token,
                'New Appointment',
                'You have a new appointment booking.'
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

            if ($patient->missed_appointments_count >= 2) {
                $patient->update(['is_blocked' => true]);
                return response()->json([
                    'success' => true,
                    'message' => 'Appointment marked as missed. The patient has reached the limit (2) and is now blocked from future bookings.'
                ], 200);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Appointment marked as missed. Patient warning counter increased by 1.'
        ], 200);
    }

    public function getDoctorAppointmentHistory()
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'doctor' || !$user->doctor) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only doctors can view this history.'
            ], 403);
        }

        $history = Appointment::where('doctor_id', $user->doctor->id)
            ->select('id', 'appointment_date', 'appointment_time', 'user_id', 'doctor_id', 'status')
            ->with([
                'user' => function($query) {
                    $query->select('id', 'full_name');
                },
                'user.patient' => function($query) {
                    $query->select('id', 'user_id', 'personal_image');
                }
            ])
            ->orderBy('appointment_date', 'desc')
            ->orderBy('appointment_time', 'desc')
            ->get();

        $customHistory = $history->map(function($appointment) {
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
            ->select('id', 'appointment_date', 'appointment_time', 'user_id', 'doctor_id', 'status')
            ->with([
                'user' => function($query) {
                    $query->select('id', 'full_name');
                },
                'user.patient' => function($query) {
                    $query->select('id', 'user_id', 'personal_image');
                }
            ])
            ->orderBy('appointment_time', 'asc')
            ->get();

        $formattedAppointments = $appointments->map(function($appointment) {
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

        $appointment->status = 'completed';
        $appointment->save();

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

            $doctorUser = $appointment->doctor->user;
            if ($user->fcm_token) {
                $firebase->sendNotification(
                    $user->fcm_token,
                    'Cancelled Successfully',
                    'Your appointment has been cancelled successfully.'
                );
            }
            if ($doctorUser && $doctorUser->fcm_token) {
                $firebase->sendNotification(
                    $doctorUser->fcm_token,
                    'Appointment Cancelled',
                    'The patient has cancelled the appointment.'
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
                    'Appointment has been cancelled successfully.'
                );
            }
            if ($patientUser && $patientUser->fcm_token) {
                $firebase->sendNotification(
                    $patientUser->fcm_token,
                    'Appointment Cancelled',
                    'The doctor has cancelled your appointment.'
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

        $firebase = app(\App\Services\FirebaseNotificationService::class);
        $patientUser = Auth::user();
        $doctorUser = $doctor->user;

        if ($patientUser->fcm_token) {
            $firebase->sendNotification(
                $patientUser->fcm_token,
                'Appointment Updated',
                'Your appointment has been changed successfully.'
            );
        }

        if ($doctorUser && $doctorUser->fcm_token) {
            $firebase->sendNotification(
                $doctorUser->fcm_token,
                'Appointment Updated',
                'A patient has changed an appointment time.'
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
