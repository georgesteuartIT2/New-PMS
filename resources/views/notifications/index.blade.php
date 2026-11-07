@extends('layouts.app')
@section('title', 'Notifications')
@section('content')

<div class="page-head" style="margin-bottom: 20px;">
    <div>
        <h1>Notifications</h1>
        <p class="muted">Updates, assignments, and schedule alerts relevant to you.</p>
    </div>
    @if($unreadCount > 0)
        <form method="post" action="{{ route('notifications.read-all') }}">
            @csrf
            <button class="btn btn-outline btn-sm">Mark All as Read</button>
        </form>
    @endif
</div>

<!-- Filter Tabs: All vs Unread -->
<div style="display: flex; gap: 8px; border-bottom: 1px solid var(--border); margin-bottom: 20px;">
    <a href="{{ route('notifications.index', ['filter' => 'all']) }}" 
       style="padding: 8px 16px; font-size: 13.5px; font-weight: 600; text-decoration: none; border-bottom: 2px solid {{ $filter === 'all' ? 'var(--primary)' : 'transparent' }}; color: {{ $filter === 'all' ? 'var(--primary)' : 'var(--muted)' }};">
        All Notifications ({{ $totalCount }})
    </a>
    <a href="{{ route('notifications.index', ['filter' => 'unread']) }}" 
       style="padding: 8px 16px; font-size: 13.5px; font-weight: 600; text-decoration: none; border-bottom: 2px solid {{ $filter === 'unread' ? 'var(--primary)' : 'transparent' }}; color: {{ $filter === 'unread' ? 'var(--primary)' : 'var(--muted)' }};">
        Unread Only ({{ $unreadCount }})
    </a>
</div>

<div class="card" style="padding: 0; overflow: hidden; background: #FFFFFF; border: 1px solid var(--border);">
    <div style="display: flex; flex-direction: column;">
        @forelse($notifications as $item)
            @php
                $isUnread = empty($item->read_at);
                $title = $item->data['title'] ?? 'Notification';
                $message = $item->data['message'] ?? '';
                $project = $item->data['project'] ?? ($item->data['project_name'] ?? null);
                $task = $item->data['task'] ?? ($item->data['task_title'] ?? null);
            @endphp
            <div style="padding: 16px 20px; border-bottom: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; background: {{ $isUnread ? '#F8FAFB' : '#FFFFFF' }}; border-left: {{ $isUnread ? '3px solid var(--primary)' : '3px solid transparent' }};">
                <div style="flex: 1;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                        <b style="font-size: 14px; color: #111827;">{{ $title }}</b>
                        @if($isUnread)
                            <span class="status-pill pill-warning" style="font-size: 10.5px; padding: 1px 6px;">New</span>
                        @endif
                    </div>

                    @if(!empty($message))
                        <p style="margin: 0 0 6px; font-size: 13px; color: #4B5563; line-height: 1.45;">{{ $message }}</p>
                    @endif

                    <div style="display: flex; align-items: center; gap: 12px; font-size: 11.5px; color: var(--muted);">
                        @if(!empty($project))
                            <span>Project: <b>{{ $project }}</b></span>
                        @endif
                        @if(!empty($task))
                            <span>Task: <b>{{ $task }}</b></span>
                        @endif
                        <span>{{ $item->created_at->diffForHumans() }}</span>
                    </div>
                </div>

                <div class="inline-actions" style="flex-shrink: 0;">
                    <form method="post" action="{{ route('notifications.read', $item->id) }}">
                        @csrf
                        <button class="btn btn-outline btn-xs" type="submit" style="font-size: 12px; padding: 4px 12px;">Open →</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="empty" style="padding: 48px 24px; text-align: center;">
                <b>You're all caught up</b>
                <p class="muted" style="margin-top: 4px; font-size: 13px;">No {{ $filter === 'unread' ? 'unread' : '' }} notifications at this time.</p>
            </div>
        @endforelse
    </div>

    @if($notifications->hasPages())
        <div class="projects-pagination-bar" style="padding: 12px 20px; border-top: 1px solid var(--border);">
            <span>Showing {{ $notifications->firstItem() ?? 0 }} to {{ $notifications->lastItem() ?? 0 }} of {{ $notifications->total() }}</span>
            <div class="pagination-pages">
                @if($notifications->onFirstPage())
                    <span class="page-nav-btn disabled">‹</span>
                @else
                    <a href="{{ $notifications->previousPageUrl() }}" class="page-nav-btn">‹</a>
                @endif
                <span class="page-num-btn active">{{ $notifications->currentPage() }}</span>
                @if($notifications->hasMorePages())
                    <a href="{{ $notifications->nextPageUrl() }}" class="page-nav-btn">›</a>
                @else
                    <span class="page-nav-btn disabled">›</span>
                @endif
            </div>
        </div>
    @endif
</div>

@endsection
