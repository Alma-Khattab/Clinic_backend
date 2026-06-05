<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Appointment;
use App\Models\Doctor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Request;

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
    $validatedData = $request->validated();

    $doctor = Doctor::with('shift')->findOrFail($validatedData['doctor_id']);
    $dayName = Carbon::parse($validatedData['appointment_date'])->format('l');
    if ($doctor->shift) {
        $bookingTime = Carbon::parse($validatedData['appointment_time']);
        $shiftStart  = Carbon::parse($doctor->shift->start_time);
        $shiftEnd    = Carbon::parse($doctor->shift->end_time);

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

    $validatedData['user_id'] = Auth::id();
    $validatedData['status']  = 'booked';
    $appointment = Appointment::create($validatedData);

    return response()->json([
        'success' => true,
        'message' => 'The appointment has been successfully booked and confirmed!',
        'booking' => $appointment
    ], 201);
}


}
