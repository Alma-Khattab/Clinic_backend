<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMedicalRecordRequest;
use App\Http\Requests\UpdateMedicalRecordRequest;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use Auth;
use Illuminate\Http\Request;

class MedicalRecordController extends Controller
{
    public function store(StoreMedicalRecordRequest $request)
{
    $user = Auth::user();

    $validated = $request->validated();
    if (!$user || $user->role !== 'doctor' || !$user->doctor) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. Only doctors can fill medical records.'
        ], 403);
    }

    $appointment = Appointment::where('id', $validated['appointment_id'])
        ->where('doctor_id', $user->doctor->id)
        ->first();

    if (!$appointment) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. This appointment does not exist or does not belong to you.'
        ], 403);
    }
     /// ربط
    $validated['doctor_id'] = $user->doctor->id;
    $validated['patient_id'] = $appointment->user_id;

    $record = MedicalRecord::create($validated);

    return response()->json([
        'success' => true,
        'message' => 'Medical record created and linked to appointment successfully.',
        'record' => $record
    ], 201);
}

    public function update(UpdateMedicalRecordRequest $request, $appointment_id)
{
    $user = Auth::user();

    if (!$user || $user->role !== 'doctor' || !$user->doctor) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. Only doctors can update medical records.'
        ], 403);
    }
    $record = MedicalRecord::where('appointment_id', $appointment_id)->first();

    if (!$record) {
        return response()->json([
            'success' => false,
            'message' => 'No medical record found for this appointment to update.'
        ], 404);
    }

    if ($record->doctor_id !== $user->doctor->id) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. You can only update records created by yourself.'
        ], 403);
    }

    $validated = $request->validated();
    $record->update($validated);

    return response()->json([
        'success' => true,
        'message' => 'Medical record updated successfully.',
        'record' => $record
    ], 200);
}

    public function show($appointment_id)
{
    $user = Auth::user();

    $record = MedicalRecord::where('appointment_id', $appointment_id)->first();

    if (!$record) {
        return response()->json([
            'success' => false,
            'message' => 'No medical record found for this appointment.'
        ], 404);
    }

    if ($user->role === 'patient') {
        if (!$user->patient || $record->patient_id !== $user->patient->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You can only view your own medical records.'
            ], 403);
        }
    }

    if ($user->role === 'doctor') {
        if (!$user->doctor || $record->doctor_id !== $user->doctor->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You can only view medical records that you have created.'
            ], 403);
        }
    }

    return response()->json([
        'success' => true,
        'record' => $record
    ], 200);
}


    public function getPatientHistory($patient_id)
    {
        $user = Auth::user();

        if ($user->role !== 'patient') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only patients can access this history.'
            ], 403);
        }

        if ($user->id != $patient_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You can only view your own medical history.'
            ], 403);
        }

        $records = MedicalRecord::where('patient_id', $patient_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success'       => true,
            'total_records' => $records->count(),
            'history'       => $records
        ], 200);
    }


    public function getDoctorMedicalRecords($doctor_id)
{
    $user = Auth::user();

    if (!$user || $user->role !== 'doctor' ||  !$user->doctor) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. Only doctors can view medical records.'
        ], 403);
    }

    if ($user->id != $doctor_id) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. You can only view your own medical history.'
        ], 403);
    }

    $doctorId = $user->doctor->id;

    $medicalRecords = MedicalRecord::with(['patient.user'])
        ->where('doctor_id', $doctorId)
        ->orderBy('created_at', 'desc')
        ->get()
            ->map(function ($record) {
            $data = $record->toArray();

            $patient = $record->patient;
            $patientUser = optional($patient)->user;

            $data['patient_name'] = optional($patientUser)->full_name;
            $data['patient_image'] = optional($patient)->personal_image;

            unset($data['patient']);

            return $data;
        });


    return response()->json([
        'success'         => true,
        'total_records'   => $medicalRecords->count(),
        'medical_records' => $medicalRecords
    ], 200);
}
}
