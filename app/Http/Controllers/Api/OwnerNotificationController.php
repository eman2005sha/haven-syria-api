<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OwnerNotificationController extends Controller
{
//تابع يجلب جميع الإشعارات لصاحب العقار
    public function index(Request $request)
    {
        $user = auth()->user();
        
        $query = $user->notifications();
        
        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }
        
        $notifications = $query->orderBy('created_at', 'desc')->paginate(15);
        
        $formatted = $notifications->map(function ($notification) {
            return [
                'id'           => $notification->id,
                'is_read'      => !is_null($notification->read_at),
                'read_at'      => $notification->read_at,
                'data'         => $notification->data,
                'created_at'   => $notification->created_at->toDateTimeString(),
                'created_human'=> $notification->created_at->diffForHumans(),
            ];
        });
        
        return response()->json([
            'success' => true,
            'data'    => $formatted,
            'unread_count' => $user->unreadNotifications->count(),
            'meta'    => [
                'current_page' => $notifications->currentPage(),
                'last_page'    => $notifications->lastPage(),
                'total'        => $notifications->total(),
            ]
        ]);
    }
    //تحديث إشعار واحد كمقروء
    public function markAsRead($id)
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        
        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الإشعار كمقروء'
        ]);
    }
    //تحديث جميغ الإشعارات مقروءة
    public function markAllAsRead()
    {
        auth()->user()->unreadNotifications->markAsRead();
        
        return response()->json([
            'success' => true,
            'message' => 'تم تحديث جميع الإشعارات كمقروءة'
        ]);
    }
}