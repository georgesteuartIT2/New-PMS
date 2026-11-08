@extends('layouts.app')
@section('title','Activity log')
@section('content')<div class="page-head"><div><div class="eyebrow">AUDIT HISTORY</div><h1>Activity log</h1><p class="muted">Important system changes, in chronological order.</p></div></div>
<div class="card"><div class="table-wrap"><table><thead><tr><th>Date</th><th>Action</th><th>User</th><th>Record</th></tr></thead><tbody>
@forelse($entries as $entry)<tr><td>{{ $entry->created_at->format('M d, Y · H:i') }}</td><td><b>{{ ucwords(str_replace(['.','_'],' ',$entry->action)) }}</b></td><td>{{ $entry->actor?->name??'System' }}</td><td>{{ class_basename($entry->subject_type) }} #{{ $entry->subject_id }}</td></tr>@empty<tr><td colspan="4" class="empty">No activity recorded yet.</td></tr>@endforelse
</tbody></table></div><div class="pagination-wrap">{{ $entries->links() }}</div></div>
@endsection
