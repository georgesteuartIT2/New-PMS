@extends('layouts.app')
@section('title','Project calendar')
@section('content')
<div class="page-head"><div><a class="back-link" href="{{ route('projects.show',$project) }}">← {{ $project->name }}</a><div class="eyebrow">PROJECT WORKSPACE</div><h1>Calendar</h1><p class="muted">See work on the days it is scheduled.</p></div><a class="btn btn-outline" href="{{ route('projects.board',$project) }}">Task board ↗</a></div>

<section class="card"><div class="calendar-controls"><a class="btn btn-outline btn-sm" href="{{ route('projects.calendar',['project'=>$project,'month'=>$month->copy()->subMonth()->format('Y-m')]) }}" aria-label="Previous month">←</a><h2>{{ $month->format('F Y') }}</h2><a class="btn btn-outline btn-sm" href="{{ route('projects.calendar',['project'=>$project,'month'=>$month->copy()->addMonth()->format('Y-m')]) }}" aria-label="Next month">→</a></div>
<div class="calendar-grid calendar-weekdays">@foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day)<span>{{ $day }}</span>@endforeach</div><div class="calendar-grid calendar-days">@foreach($cells as $cell)<div class="calendar-cell {{ $cell['date']->month===$month->month?'':'off-month' }} {{ $cell['date']->isToday()?'today':'' }}"><div class="calendar-date">{{ $cell['date']->day }}</div>@foreach($cell['tasks'] as $task)<a href="{{ route('projects.show',$project) }}" class="calendar-event {{ $task->status }}" title="{{ $task->title }}">{{ $task->title }}</a>@endforeach@if($cell['more'])<span class="calendar-more">+{{ $cell['more'] }} more active tasks</span>@endif</div>@endforeach</div>
</section>
@endsection
