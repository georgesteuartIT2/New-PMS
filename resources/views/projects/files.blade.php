@extends('layouts.app')
@section('title','Project files')
@section('content')
@include('projects._tabs')
<div class="page-head"><div><a class="back-link" href="{{ route('projects.show',$project) }}">← {{ $project->name }}</a><div class="eyebrow">PROJECT WORKSPACE</div><h1>Files</h1><p class="muted">All task attachments in one place. Upload new files inside a task.</p></div><a class="btn btn-outline" href="{{ route('projects.show',$project) }}#tasks">Open tasks ↗</a></div>

<section class="card"><div class="card-head"><h2>Task attachments</h2><span class="muted text-sm">{{ $files->total() }} files</span></div>
<div class="table-wrap"><table><thead><tr><th>File</th><th>Task</th><th>Size</th><th>Uploaded</th><th></th></tr></thead><tbody>
@forelse($files as $file)<tr><td><b>▣ {{ $file->original_name }}</b></td><td><a class="text-link" href="{{ route('projects.show',$project) }}">{{ $file->task->title }}</a></td><td>{{ number_format($file->size_bytes/1024) }} KB</td><td>{{ $file->created_at->format('M d, Y') }}</td><td><a class="btn btn-outline btn-sm" href="{{ route('attachments.download',$file) }}">Download</a></td></tr>
@empty<tr><td colspan="5"><div class="empty"><b>No files yet</b><p class="muted">Files added to tasks will appear here automatically.</p></div></td></tr>@endforelse
</tbody></table></div><div class="pagination-wrap">{{ $files->links() }}</div></section>
@endsection
