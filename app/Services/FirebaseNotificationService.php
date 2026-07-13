<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseNotificationService
{
    public function sendNotification(
        $token,
        $title,
        $body
    )
    {
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
    }
}
