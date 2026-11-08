@extends('layouts.app')
@section('title', 'Notifications')
@section('content')
<div class="notifications-workspace"><div class="page-head"><div><h1>Notifications</h1><p class="muted">{{ auth()->user()->isPmo() ? 'Project assignments, team updates and reminders in one place.' : 'Your project assignments, task updates and reminders in one place.' }}</p></div>@if($unreadCount>0)<form method="post" action="{{ route('notifications.read-all') }}">@csrf<button type="submit" class="btn btn-outline">Mark all as read</button></form>@endif</div>
<nav class="filter-tabs" aria-label="Notification filters"><a href="{{ route('notifications.index',['filter'=>'all']) }}" class="{{ $filter==='all' ? 'active' : '' }}" @if($filter==='all') aria-current="page" @endif>All updates <span>{{ $totalCount }}</span></a><a href="{{ route('notifications.index',['filter'=>'unread']) }}" class="{{ $filter==='unread' ? 'active' : '' }}" @if($filter==='unread') aria-current="page" @endif>Unread <span>{{ $unreadCount }}</span></a></nav>
<section class="notification-feed" aria-label="Notification updates">
    @forelse($notifications as $item)
        @php
            $isUnread=empty($item->read_at);
            $title=$item->data['title']??'Notification';
            $message=$item->data['message']??'';
            $project=$item->data['project']??($item->data['project_name']??null);
            $task=$item->data['task']??($item->data['task_title']??null);
        @endphp
        <article class="notification-row {{ $isUnread ? 'is-unread' : '' }}"><span class="notification-event-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3h8v4H8z"/><path d="M8 5H5v16h14V5h-3M8 12h8M8 16h5"/></svg></span><div class="notification-copy"><div class="notification-title"><b>{{ $title }}</b>@if($isUnread)<span class="notification-new" title="Unread"><span class="workspace-sr-only">Unread</span></span>@endif</div>@if($message)<p>{{ $message }}</p>@endif<div class="notification-meta">@if($project)<span>{{ $project }}</span>@endif @if($task)<span>{{ $task }}</span>@endif<time datetime="{{ $item->created_at->toIso8601String() }}" title="{{ $item->created_at->format('d M Y, H:i') }}">{{ $item->created_at->diffForHumans() }}</time></div></div><form method="post" action="{{ route('notifications.read',$item->id) }}">@csrf
@if(!empty($item->data['url']))<button type="submit" class="btn btn-outline btn-sm" aria-label="Open notification: {{ $title }}">View update &rarr;</button>@elseif($isUnread)<button type="submit" class="btn btn-outline btn-sm">Mark as read</button>@else<span class="notification-read-label">Read</span>@endif</form></article>
    @empty<div class="home-empty notification-empty"><span class="home-empty-mark" aria-hidden="true">&#10003;</span><b>{{ $filter==='unread' || $totalCount>0 ? "You're all caught up" : 'No notifications yet' }}</b><p>{{ $filter==='unread' ? 'No unread updates. You can still browse your previous notifications.' : 'Your assignments and project updates will appear here.' }}</p>@if($filter==='unread')<a class="text-action" href="{{ route('notifications.index',['filter'=>'all']) }}">View all updates &rarr;</a>@else<a class="text-action" href="{{ route('dashboard') }}">Back to workspace &rarr;</a>@endif</div>@endforelse
</section>
@if($notifications->hasPages())<div class="pagination-wrap">{{ $notifications->withQueryString()->links() }}</div>@endif
</div>
@endsection
