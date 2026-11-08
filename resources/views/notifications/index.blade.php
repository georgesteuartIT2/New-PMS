@extends('layouts.app')
@section('title', 'Notifications')
@section('content')
<div class="page-head"><div><h1>Notifications</h1><p class="muted">Assignments, updates and reminders that matter to you.</p></div>@if($unreadCount>0)<form method="post" action="{{ route('notifications.read-all') }}">@csrf<button type="submit" class="btn btn-outline">Mark all as read</button></form>@endif</div>
<nav class="filter-tabs" aria-label="Notification filters"><a href="{{ route('notifications.index',['filter'=>'all']) }}" class="{{ $filter==='all' ? 'active' : '' }}" @if($filter==='all') aria-current="page" @endif>All updates <span>{{ $totalCount }}</span></a><a href="{{ route('notifications.index',['filter'=>'unread']) }}" class="{{ $filter==='unread' ? 'active' : '' }}" @if($filter==='unread') aria-current="page" @endif>Unread <span>{{ $unreadCount }}</span></a></nav>
<div class="notification-feed">
    @forelse($notifications as $item)
        @php
            $isUnread=empty($item->read_at);
            $title=$item->data['title']??'Notification';
            $message=$item->data['message']??'';
            $project=$item->data['project']??($item->data['project_name']??null);
            $task=$item->data['task']??($item->data['task_title']??null);
        @endphp
        <article class="notification-row {{ $isUnread ? 'is-unread' : '' }}"><div class="notification-copy"><div class="notification-title"><b>{{ $title }}</b>@if($isUnread)<span class="notification-new" title="Unread"><span class="workspace-sr-only">Unread</span></span>@endif</div>@if($message)<p>{{ $message }}</p>@endif<div class="notification-meta">@if($project)<span>{{ $project }}</span>@endif @if($task)<span>{{ $task }}</span>@endif<time datetime="{{ $item->created_at->toIso8601String() }}" title="{{ $item->created_at->format('d M Y, H:i') }}">{{ $item->created_at->diffForHumans() }}</time></div></div><form method="post" action="{{ route('notifications.read',$item->id) }}">@csrf<button type="submit" class="btn btn-outline btn-sm" aria-label="Open notification: {{ $title }}">Open &rarr;</button></form></article>
    @empty<div class="home-empty"><span class="home-empty-mark" aria-hidden="true">&#10003;</span><b>You're all caught up</b><p>{{ $filter==='unread' ? 'No unread updates. You can still browse your previous notifications.' : 'Your assignments and project updates will appear here.' }}</p>@if($filter==='unread')<a class="text-action" href="{{ route('notifications.index',['filter'=>'all']) }}">View all updates &rarr;</a>@else<a class="text-action" href="{{ route('dashboard') }}">Back to workspace &rarr;</a>@endif</div>@endforelse
</div>
@if($notifications->hasPages())<div class="pagination-wrap">{{ $notifications->withQueryString()->links() }}</div>@endif
@endsection
