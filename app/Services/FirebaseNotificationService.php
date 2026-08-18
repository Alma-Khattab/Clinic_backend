<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use App\Models\Notification as DatabaseNotification;
use Illuminate\Support\Facades\Log;

class FirebaseNotificationService
{
    public function sendNotification(
        $token,
        $title,
        $body,
        array $data = []
    ) {
        // 1. التحقق من وجود التوكن وتسجيل التحذير في حال عدم وجوده
        if (empty($token)) {
            Log::warning("Firebase Notification Skipped: No FCM Token provided.", [
                'user_id' => $data['user_id'] ?? null,
                'title'   => $title
            ]);
            return;
        }

        try {
            // 2. إعداد الاتصال بـ Firebase
            $factory = (new Factory)
                ->withServiceAccount(
                    storage_path(
                        'app/firebase/clinic-management-system-c2d71-firebase-adminsdk-fbsvc-b8b06e231c.json'
                    )
                );

            $messaging = $factory->createMessaging();

            // 3. بناء الرسالة
            $messageBuilder = CloudMessage::withTarget('token', $token)
                ->withNotification(Notification::create($title, $body));

            if (!empty($data)) {
                $messageBuilder = $messageBuilder->withData($data);
            }

            // 4. إرسال الإشعار
            $messaging->send($messageBuilder);

            // 5. حفظ الإشعار في قاعدة البيانات
            DatabaseNotification::create([
                'user_id'   => $data['user_id'] ?? null,
                'title'     => $title,
                'body'      => $body,
                'type'      => $data['type'] ?? null,
                'target_id' => $data['id'] ?? null,
                'is_read'   => false,
            ]);

            // 6. تسجيل نجاح الإرسال في ملف الـ Log
            Log::info("Firebase Notification Sent Successfully", [
                'user_id' => $data['user_id'] ?? null,
                'token'   => $token,
                'title'   => $title,
            ]);

        } catch (\Exception $e) {
            // 7. تسجيل الخطأ في حال وجود أي مشكلة دون إيقاف باقي النظام
            Log::error("Firebase Notification Failed To Send", [
                'user_id' => $data['user_id'] ?? null,
                'token'   => $token,
                'title'   => $title,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
