<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{

    // جلب قائمة إشعارات المستخدم المسجل حالياً

    public function index(Request $request)
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'تم جلب الإشعارات بنجاح',
            'data' => $notifications,
        ], 200);
    }


     /* تغيير حالة الإشعار إلى "مقروء"
     */
    public function markAsRead($id)
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', Auth::user())
            ->first();

        if ($notification) {
            $notification->update(['is_read' => true]);
        }

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث حالة الإشعار',
        ], 200);
    }
}
