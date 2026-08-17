<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
////////////////new
use Illuminate\Support\Facades\Log; // 1. استيراد Log Facade

class FirebaseNotificationService
{
    public function sendNotification(
        $token,
        $title,
        $body
    ) {
        try {/////new
            $factory = (new Factory)
                ->withServiceAccount(
                    storage_path(
                        'app/firebase/clinic-management-system-c2d71-firebase-adminsdk-fbsvc-b8b06e231c.json'
                    )
                );

            $messaging = $factory->createMessaging();

            $message = CloudMessage::withTarget(
                'token',
                $token
            )
                ->withNotification(
                    Notification::create(
                        $title,
                        $body
                    )
                );

            $messaging->send($message);

////////////////new
            // 2. تسجيل نجاح الإرسال (Info Log)
            Log::info("Firebase Notification Sent Successfully", [
                'token' => $token,
                'title' => $title
            ]);
        } catch (\Exception $e) {
            // 3. تسجيل الخطأ مع السبب التفصيلي دون إيقاف باقي السيرفر (Error Log)
            Log::error("Firebase Notification Failed", [
                'token' => $token,
                'title' => $title,
                'error' => $e->getMessage()
            ]);
        }
    }
}
