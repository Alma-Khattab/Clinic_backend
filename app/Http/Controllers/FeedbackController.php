<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFeedbackRequest;
use App\Models\Appointment;
use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FeedbackController extends Controller
{
    public function store(StoreFeedbackRequest $request)
    {
        $validated = $request->validated();

        $user = Auth::user();
        $userId = $user->id;

        $appointment = Appointment::with('doctor.user')->findOrFail($validated['appointment_id']);

        // شرط 1: المريض صاحب الموعد فقط
        if ($appointment->user_id !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You can only leave feedback for your own appointments.'
            ], 403);
        }

        // شرط 2: الموعد حالته completed حصراً
        if ($appointment->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'You can only leave feedback for completed appointments.'
            ], 422);
        }

        // إنشاء الفيدباك مباشرة
        $feedback = Feedback::create([
            'appointment_id' => $appointment->id,
            'user_id'        => $userId,
            'doctor_id'      => $appointment->doctor_id,
            'comment'        => $validated['comment'],
            'is_anonymous'   => $validated['is_anonymous'],
        ]);

        // 📍 تسجيل إنشاء التقييم
        Log::info("New Feedback Submitted", [
            'feedback_id'    => $feedback->id,
            'patient_user_id' => $userId,
            'doctor_id'      => $appointment->doctor_id,
            'is_anonymous'   => $feedback->is_anonymous
        ]);

        // =================  نظام الإشعارات للفيربيز  =================

        $doctorUser = $appointment->doctor ? $appointment->doctor->user : null;

        if ($doctorUser && $doctorUser->fcm_token) {

            // فحص رغبة المريض: إذا anonymous نكتب مريض مجهول، وإلا نكتب اسمه الحقيقي
            $senderName = $validated['is_anonymous'] ? 'An anonymous patient' : $user->full_name;

            $firebase = app(\App\Services\FirebaseNotificationService::class);
            $firebase->sendNotification(
                $doctorUser->fcm_token,
                'New Feedback Received',
                "{$senderName} left a new feedback for you."
            );
        }

        // ==============================================================

        return response()->json([
            'success' => true,
            'message' => 'Feedback submitted successfully.',
            'data'    => $feedback
        ], 201);
    }
    public function update(Request $request, $id)
    {
        $request->validate([
            'comment'      => 'sometimes|string|min:5|max:1000',
            'is_anonymous' => 'sometimes|boolean',
        ]);

        $feedback = Feedback::findOrFail($id);

        // التأكد أن صاحب الفيدباك هو من يقوم بالتعديل
        if ($feedback->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $feedback->update([
            'comment'      => $request->comment,
            'is_anonymous' => $request->is_anonymous,
        ]);

        // 📍 تسجيل تعديل التقييم
        Log::info("Feedback Updated", [
            'feedback_id' => $feedback->id,
            'user_id'     => Auth::id()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Feedback updated successfully.',
            'data'    => $feedback
        ]);
    }
    public function destroy($id)
    {
        $feedback = Feedback::findOrFail($id);

        if ($feedback->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $feedback->delete();
        // 📍 تسجيل حذف التقييم
        Log::info("Feedback Deleted", [
            'user_id'     => Auth::id()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Feedback deleted successfully.'
        ]);
    }
    public function getDoctorFeedbacks()
    {
        $user = Auth::user();

        // التأكد أن المستخدم الحالي هو طبيب ولديه سجل طبيب مرتبط
        if ($user->role !== 'doctor' || !$user->doctor) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only doctors can view this.'
            ], 403);
        }

        // جلب الفيدباكس الخاصة بالطبيب مع اسم المريض فقط (لأجل الأمان وتوفير الأداء)
        $feedbacks = Feedback::where('doctor_id', $user->doctor->id)
            ->with('user:id,full_name')
            ->latest()
            ->get();

        // تحويل البيانات لترجع بس الاسم والكومنت
        $transformedFeedbacks = $feedbacks->map(function ($item) {
            return [
                'patient_name' => $item->is_anonymous ? 'Anonymous Patient' : $item->user->full_name,
                'comment'      => $item->comment,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $transformedFeedbacks
        ]);
    }

    public function getPatientFeedbacks()
    {
        $user = Auth::user();

        // ❌ منع أي مستخدم ليس مريضاً (طبيب، أدمن...) من دخول هذا التابع نهائياً
        if ($user->role !== 'patient') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only patients can view their feedback history.'
            ], 403);
        }

        // جلب الفيدباكس الخاصة بهذا المريض المحدد فقط بناءً على الـ ID تبعه
        $feedbacks = Feedback::where('user_id', $user->id)
            ->with('doctor.user:id,full_name')
            ->latest()
            ->get();

        // تحويل وتنسيق البيانات
        $transformedFeedbacks = $feedbacks->map(function ($item) {
            return [
                'feedback_id' => $item->id,
                'doctor_name' => $item->doctor && $item->doctor->user ? $item->doctor->user->full_name : 'Unknown Doctor',
                'comment'     => $item->comment,
                'is_anonymous' => (bool)$item->is_anonymous,
                'created_at'  => $item->created_at->toDateTimeString(),
            ];
        });

        return response()->json([
            'success' => true,
            'count'   => $transformedFeedbacks->count(),
            'data'    => $transformedFeedbacks
        ]);
    }
    public function showFeedback($id)
    {
        $user = Auth::user();
        if ($user->role !== 'patient') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only patients can view this.'
            ], 403);
        }

        $feedback = Feedback::with('doctor.user:id,full_name')->findOrFail($id);

        if ($feedback->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You can only view your own feedback.'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'feedback_id'  => $feedback->id,
                'doctor_name'  => $feedback->doctor && $feedback->doctor->user ? $feedback->doctor->user->full_name : 'Unknown Doctor',
                'comment'      => $feedback->comment,
                'is_anonymous' => (bool)$feedback->is_anonymous,
                'created_at'   => $feedback->created_at->toDateTimeString(),
            ]
        ]);
    }
}
