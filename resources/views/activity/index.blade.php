@extends('layouts.app')
@section('title', 'Activity Log')

@push('styles')
<style>
    .activity-log-card {
        background: #FFFFFF;
        border: 1px solid var(--border, #E2E8F0);
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    }
    .activity-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }
    .activity-table th {
        background: #F8FAFC;
        padding: 12px 16px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: #475569;
        border-bottom: 1px solid #E2E8F0;
    }
    .activity-table td {
        padding: 14px 16px;
        border-bottom: 1px solid #F1F5F9;
        font-size: 13px;
        vertical-align: middle;
    }
    .activity-table tr:hover {
        background: #F8FAFC;
    }
    .activity-pagination-bar {
        padding: 14px 20px;
        border-top: 1px solid #E2E8F0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        background: #FAFBFB;
    }
    .activity-pagination-bar svg,
    .activity-log-card svg,
    .pagination-wrap svg,
    nav[role="navigation"] svg {
        width: 16px !important;
        height: 16px !important;
        max-width: 16px !important;
        max-height: 16px !important;
        display: inline-block !important;
        vertical-align: middle !important;
    }
</style>
@endpush

@section('content')
<div class="page-head" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 12px; width: 100%;">
        <div>
            <div class="eyebrow" style="font-size: 11px; font-weight: 700; color: #315F58; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">AUDIT HISTORY</div>
            <h1 style="font-size: 24px; font-weight: 750; color: #0F172A; margin: 0 0 4px;">Activity Log</h1>
            <p class="muted" style="margin: 0; font-size: 13px;">Important system changes and user actions, in chronological order.</p>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <span class="pill pill-neutral" style="font-size: 12px; padding: 4px 10px; font-weight: 600;">
                {{ $entries->total() }} {{ Str::plural('Event', $entries->total()) }}
            </span>
        </div>
    </div>
</div>

<div class="activity-log-card">
    <div class="table-wrap" style="overflow-x: auto;">
        <table class="activity-table">
            <thead>
                <tr>
                    <th style="width: 18%;">Date &amp; Time</th>
                    <th style="width: 20%;">Action</th>
                    <th style="width: 20%;">User</th>
                    <th style="width: 18%;">Record</th>
                    <th style="width: 24%;">Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse($entries as $entry)
                    @php
                        $actLower = strtolower($entry->action);
                        $isCreate = str_contains($actLower, 'created') || str_contains($actLower, 'create') || str_contains($actLower, 'add');
                        $isDelete = str_contains($actLower, 'delete') || str_contains($actLower, 'remove');
                        $isAssign = str_contains($actLower, 'assign');
                        $isClose  = str_contains($actLower, 'close') || str_contains($actLower, 'complete');
                        $isUpdate = str_contains($actLower, 'update') || str_contains($actLower, 'edit') || str_contains($actLower, 'change');

                        $badgeStyle = match(true) {
                            $isCreate => 'background: #DCFCE7; color: #15803D; border: 1px solid #BBF7D0;',
                            $isDelete => 'background: #FEE2E2; color: #B91C1C; border: 1px solid #FECACA;',
                            $isAssign => 'background: #F3E8FF; color: #7E22CE; border: 1px solid #E9D5FF;',
                            $isClose  => 'background: #E0E7FF; color: #3730A3; border: 1px solid #C7D2FE;',
                            $isUpdate => 'background: #E0F2FE; color: #0369A1; border: 1px solid #BAE6FD;',
                            default   => 'background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0;',
                        };

                        $actorName = $entry->actor?->name ?? 'System';
                        $initial = strtoupper(substr($actorName, 0, 1));
                        $subjectType = class_basename($entry->subject_type);
                    @endphp
                    <tr>
                        <!-- Date & Time -->
                        <td>
                            <div style="font-weight: 600; color: #1E293B; font-size: 13px;">{{ $entry->created_at->format('M d, Y') }}</div>
                            <div class="muted" style="font-size: 11px;">{{ $entry->created_at->format('H:i') }} &middot; {{ $entry->created_at->diffForHumans() }}</div>
                        </td>

                        <!-- Action -->
                        <td>
                            <span style="font-size: 11.5px; font-weight: 600; padding: 2.5px 8px; border-radius: 5px; display: inline-flex; align-items: center; gap: 4px; {{ $badgeStyle }}">
                                {{ ucwords(str_replace(['.', '_'], ' ', $entry->action)) }}
                            </span>
                        </td>

                        <!-- User -->
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="width: 26px; height: 26px; border-radius: 50%; background: #E6F4EA; color: #315F58; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex-shrink: 0;">
                                    {{ $initial }}
                                </span>
                                <div>
                                    <div style="font-weight: 600; color: #1E293B; font-size: 13px;">{{ $actorName }}</div>
                                    @if($entry->actor?->role)
                                        <div class="muted" style="font-size: 10.5px;">{{ strtoupper($entry->actor->role) }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>

                        <!-- Record -->
                        <td>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <span style="font-size: 11px; font-weight: 600; padding: 2px 7px; border-radius: 4px; background: #F1F5F9; border: 1px solid #E2E8F0; color: #334155;">
                                    {{ $subjectType }}
                                </span>
                                <span style="font-size: 12px; font-weight: 600; color: #64748B;">
                                    #{{ $entry->subject_id }}
                                </span>
                            </div>
                        </td>

                        <!-- Details -->
                        <td>
                            @if(!empty($entry->details) && is_array($entry->details))
                                <div style="font-size: 11.5px; color: #475569; max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ json_encode($entry->details) }}">
                                    @php $count = 0; @endphp
                                    @foreach($entry->details as $k => $v)
                                        @if(!is_array($v) && $count < 3)
                                            <span style="font-weight: 600; color: #64748B;">{{ $k }}:</span> {{ (string)$v }}@if(!$loop->last && $count < 2), @endif
                                            @php $count++; @endphp
                                        @endif
                                    @endforeach
                                    @if(count($entry->details) > 3)
                                        <span class="muted">+{{ count($entry->details) - 3 }} more</span>
                                    @endif
                                </div>
                            @else
                                <span class="muted" style="font-size: 12px;">&mdash;</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="empty" style="text-align: center; padding: 36px 20px; color: #64748B;">
                            No activity recorded yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($entries->hasPages())
        <div class="activity-pagination-bar">
            <div style="font-size: 12.5px; color: #64748B;">
                Showing <strong style="color: #1E293B;">{{ $entries->firstItem() ?? 0 }}</strong> to <strong style="color: #1E293B;">{{ $entries->lastItem() ?? 0 }}</strong> of <strong style="color: #1E293B;">{{ $entries->total() }}</strong> entries
            </div>
            <div class="activity-pagination-controls pagination-wrap" style="margin-top: 0;">
                {{ $entries->links() }}
            </div>
        </div>
    @endif
</div>
@endsection
