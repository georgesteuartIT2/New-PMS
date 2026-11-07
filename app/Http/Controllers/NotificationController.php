<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController {
    public function index(Request $request){
        $filter = $request->input('filter', 'all');
        $query = $request->user()->notifications()->latest();
        $unreadCount = $request->user()->unreadNotifications()->count();
        $totalCount = $request->user()->notifications()->count();

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate(20)->withQueryString();
        return view('notifications.index', compact('notifications', 'filter', 'unreadCount', 'totalCount'));
    }

    public function read(Request $request, string $id){
        $n = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $n->markAsRead();
        $url = (string)($n->data['url'] ?? '');
        return redirect()->to(str_starts_with($url, '/') && !str_starts_with($url, '//') ? $url : route('notifications.index'));
    }

    public function readAll(Request $request){
        $request->user()->unreadNotifications()->update(['read_at' => now()]);
        return back()->with('success', 'All notifications marked as read.');
    }
}

