@extends('layouts.app')
@section('title', $project->name)
@section('content')

<div class="project-workspace-v3" id="project-workspace">

    <nav class="workspace-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('projects.index') }}">&larr; Projects</a>
    </nav>
    <section class="workspace-overview-card" aria-label="Project overview">
    <!-- Title & Top Action Bar -->
    <div class="pw-head-row">
        <div class="pw-head-left">
            <span class="project-identity-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a1 1 0 0 1 1-1h6l2 3h8a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7z"/></svg></span>
            <div class="project-identity-copy">
                <h1>{{ $project->name }}</h1>
                <div class="pw-head-meta"><span>{{ $project->code }}</span><span class="meta-sep" aria-hidden="true">&middot;</span><span>{{ $project->subsidiary?->name ?? 'General Group' }}</span></div>
            </div>
        </div>
        <div class="pw-head-actions">
            <!-- Project Status Badge -->
            <span class="project-status-pill status-{{ $project->status }}">
                <span class="status-dot"></span>
                <span>
                    @if($project->status === 'declined')
                        PM Declined
                    @elseif($project->status === 'pending')
                        Pending PM Approval
                    @else
                        {{ ucwords(str_replace('_', ' ', $project->status)) }}
                    @endif
                </span>
            </span>

            @if(app(\App\Services\Access::class)->project(auth()->user(), $project, 'edit_project') && $project->status !== 'archived')
                <button type="button" class="btn btn-outline workspace-edit-project" data-show-dialog="project-edit-dialog">Edit project</button>
            @endif
            <!-- More Actions (...) Dropdown -->
            <div class="pw-dropdown-wrap">
                <button type="button" class="pw-more-btn pw-dropdown-toggle" title="More project actions" aria-label="More actions">
                    ···
                </button>
                <div class="pw-dropdown-menu align-right">
@if($health && !$health['baseline_available'] && $canTeam && in_array($project->status, ['active', 'on_hold'], true))
                        <form method="post" action="{{ route('projects.baseline', $project) }}">
                            @csrf
                            <button type="submit" class="pw-dropdown-item">Capture Schedule Baseline</button>
                        </form>
                    @endif

                    @if($canManage)
                        <a href="{{ route('tasks.export', $project) }}" class="pw-dropdown-item">Export Tasks to Excel</a>
                        <a href="{{ route('tasks.export', ['project' => $project, 'blank' => 1]) }}" class="pw-dropdown-item">Download Blank Excel Template</a>
                    @endif

                    @if($canTeam)
                        <button type="button" class="pw-dropdown-item" data-show-dialog="project-closure-dialog" style="text-align: left; width: 100%; background: none; border: none; font-size: inherit; cursor: pointer;">Project Closure & Sign-off</button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- PM Acceptance & Comprehensive Scope Review Banner (When project is pending and current user is PM) -->
    @if($project->status === 'pending' && $project->pm_user_id === auth()->id())
        @php
            $workingDays = max(1, (int) $project->starts_on->diffInWeekdays($project->ends_on) + 1);
            $calendarDays = $project->starts_on->diffInDays($project->ends_on) + 1;
        @endphp
        <section class="pm-assignment-review-banner" style="margin-bottom: 24px; background: #FFFFFF; border: 1px solid #D8E7E3; border-radius: 10px; box-shadow: 0 4px 16px -2px rgba(49, 95, 88, 0.08), 0 2px 6px -1px rgba(0, 0, 0, 0.03); overflow: hidden;">
            <!-- Header Strip (Sleek GS ProjectFlow Corporate Bar) -->
            <div style="background: linear-gradient(135deg, #244943 0%, #315F58 100%); color: #FFFFFF; padding: 16px 22px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
                <div style="display: flex; align-items: center; gap: 12px; flex: 1; min-width: 280px; max-width: 680px;">
                    <div style="display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; background: rgba(255, 255, 255, 0.15); border-radius: 8px; flex-shrink: 0;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <polyline points="16 11 18 13 22 9"></polyline>
                        </svg>
                    </div>
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 2px;">
                            <h2 style="font-size: 16.5px; font-weight: 700; margin: 0; color: #FFFFFF; letter-spacing: -0.2px;">Your approval is needed</h2>
                            <span style="display: inline-flex; align-items: center; gap: 4px; background: rgba(255, 255, 255, 0.16); color: #E8F4EC; font-size: 10.5px; font-weight: 650; text-transform: uppercase; letter-spacing: 0.5px; padding: 1px 7px; border-radius: 4px;">
                                <span style="width: 5px; height: 5px; border-radius: 50%; background: #4ADE80;"></span> Action Required
                            </span>
                        </div>
                        <p style="font-size: 12.5px; margin: 0; color: #E0EBE8; line-height: 1.4;">You have been designated as Project Manager. Please review the project details, timeline, and task schedule below before responding.</p>
                    </div>
                </div>

                <!-- Action Buttons in Banner Header (Aligned cleanly to the right) -->
                <div style="display: flex; align-items: center; gap: 10px; flex-shrink: 0; margin-left: auto;">
                    <button type="button" class="btn" data-show-dialog="decline-project-dialog" style="background: rgba(255, 255, 255, 0.12); color: #FFFFFF; border: 1px solid rgba(255, 255, 255, 0.28); font-weight: 600; font-size: 13px; padding: 7px 15px; border-radius: 6px; cursor: pointer; transition: all 0.15s ease; display: inline-flex; align-items: center; gap: 6px;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        Decline Assignment
                    </button>
                    <form action="{{ route('projects.respond', $project) }}" method="post" style="display: inline;">
                        @csrf
                        <input type="hidden" name="decision" value="accept">
                        <button type="submit" class="btn" style="background: #FFFFFF; color: #234842; border: 1px solid #FFFFFF; font-weight: 700; font-size: 13px; padding: 7px 18px; border-radius: 6px; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.14); cursor: pointer; transition: all 0.15s ease; display: inline-flex; align-items: center; gap: 6px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            ✓ Accept &amp; Activate Project
                        </button>
                    </form>
                </div>
            </div>

            <!-- Project Details Review Grid (Cohesive Modern Surface) -->
            <div style="padding: 18px 22px; background: #F8FAFB;">
                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: #315F58; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    Project Scope &amp; Creation Details
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px;">
                    <!-- 1. Subsidiary -->
                    <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 12px 14px; display: flex; gap: 10px; align-items: flex-start;">
                        <div style="width: 30px; height: 30px; border-radius: 6px; background: #EBF3F1; color: #315F58; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M3 7v14M21 7v14M6 11h.01M6 15h.01M10 11h.01M10 15h.01M14 11h.01M14 15h.01M18 11h.01M18 15h.01M9 3h6v4H9z"></path></svg>
                        </div>
                        <div style="min-width: 0; flex: 1;">
                            <div style="font-size: 10.5px; text-transform: uppercase; font-weight: 650; color: #64748B; letter-spacing: 0.3px;">Subsidiary / Division</div>
                            <strong style="font-size: 13.5px; color: #1E293B; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px;">{{ $project->subsidiary?->name ?? 'General Corporate' }}</strong>
                            <div style="margin-top: 3px;"><code style="background: #EBF3F1; color: #315F58; font-size: 11px; padding: 1px 5px; border-radius: 4px; font-weight: 600;">{{ $project->code }}</code></div>
                        </div>
                    </div>

                    <!-- 2. Timeline -->
                    <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 12px 14px; display: flex; gap: 10px; align-items: flex-start;">
                        <div style="width: 30px; height: 30px; border-radius: 6px; background: #EBF3F1; color: #315F58; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        </div>
                        <div style="min-width: 0; flex: 1;">
                            <div style="font-size: 10.5px; text-transform: uppercase; font-weight: 650; color: #64748B; letter-spacing: 0.3px;">Planned Timeline</div>
                            <strong style="font-size: 13.5px; color: #1E293B; display: block; margin-top: 2px;">{{ $project->starts_on->format('d M Y') }} – {{ $project->ends_on->format('d M Y') }}</strong>
                            <div style="font-size: 11.5px; color: #059669; font-weight: 600; margin-top: 3px;">
                                {{ $workingDays }} Working Days <span style="color: #94A3B8; font-weight: 500;">({{ $calendarDays }}d total)</span>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Template / Task Plan -->
                    <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 12px 14px; display: flex; gap: 10px; align-items: flex-start;">
                        <div style="width: 30px; height: 30px; border-radius: 6px; background: #EBF3F1; color: #315F58; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                        </div>
                        <div style="min-width: 0; flex: 1;">
                            <div style="font-size: 10.5px; text-transform: uppercase; font-weight: 650; color: #64748B; letter-spacing: 0.3px;">Schedule Structure</div>
                            <strong style="font-size: 13.5px; color: #1E293B; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px;">{{ $project->template?->name ?? 'Custom Blank Schedule' }}</strong>
                            <div style="font-size: 11.5px; color: #315F58; font-weight: 600; margin-top: 3px;">
                                {{ $totalCount }} {{ Str::plural('Task', $totalCount) }} Prepared
                            </div>
                        </div>
                    </div>

                    <!-- 4. Initiated By -->
                    <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 12px 14px; display: flex; gap: 10px; align-items: flex-start;">
                        <div style="width: 30px; height: 30px; border-radius: 6px; background: #EBF3F1; color: #315F58; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        </div>
                        <div style="min-width: 0; flex: 1;">
                            <div style="font-size: 10.5px; text-transform: uppercase; font-weight: 650; color: #64748B; letter-spacing: 0.3px;">Initiated By</div>
                            <strong style="font-size: 13.5px; color: #1E293B; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px;">{{ $project->creator?->name ?? 'PMO Administration' }}</strong>
                            <div style="font-size: 11px; color: #64748B; margin-top: 3px;">
                                {{ $project->created_at->format('d M Y, H:i') }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Description / Scope (if any) -->
                @if($project->description)
                    <div style="margin-top: 12px; background: #FFFFFF; border: 1px solid #E2E8F0; border-left: 3px solid #315F58; border-radius: 0 8px 8px 0; padding: 10px 14px;">
                        <span style="font-size: 10.5px; text-transform: uppercase; font-weight: 700; color: #315F58; letter-spacing: 0.4px; display: block; margin-bottom: 2px;">Project Description &amp; Scope</span>
                        <div style="font-size: 13px; color: #334155; line-height: 1.5; white-space: pre-line;">{{ $project->description }}</div>
                    </div>
                @endif

                <!-- Assigned Team Members -->
                @if($project->memberships->where('user_id', '!=', $project->pm_user_id)->isNotEmpty())
                    <div style="margin-top: 12px; background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 10px 14px;">
                        <span style="font-size: 10.5px; text-transform: uppercase; font-weight: 700; color: #315F58; letter-spacing: 0.4px; display: block; margin-bottom: 6px;">
                            Pre-assigned Team Members ({{ $project->memberships->where('user_id', '!=', $project->pm_user_id)->count() }})
                        </span>
                        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                            @foreach($project->memberships->where('user_id', '!=', $project->pm_user_id) as $member)
                                <div style="display: inline-flex; align-items: center; gap: 6px; background: #F8FAFB; border: 1px solid #E2E8F0; border-radius: 6px; padding: 3px 8px; font-size: 12px;">
                                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; border-radius: 50%; background: #EBF3F1; color: #315F58; font-size: 10px; font-weight: 700;">{{ strtoupper(substr($member->user?->name ?? 'U', 0, 1)) }}</span>
                                    <span style="font-weight: 600; color: #1E293B;">{{ $member->user?->name }}</span>
                                    <span style="background: #E2E8F0; color: #475569; font-size: 10px; font-weight: 600; padding: 1px 5px; border-radius: 4px;">{{ $member->role?->name }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Bottom Guidance note -->
                <div style="margin-top: 12px; background: #EBF3F1; border: 1px solid #D8E7E3; border-radius: 6px; padding: 8px 12px; display: flex; align-items: center; gap: 8px; font-size: 12px; color: #234842;">
                    <svg width="15" height="15" viewBox="0 0 20 20" fill="#315F58" style="flex-shrink: 0;">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                    <span>Review the <strong>Tasks</strong>, <strong>Gantt Timeline</strong>, and <strong>Team</strong> tabs below to inspect the full schedule and milestones before confirming your acceptance.</span>
                </div>
            </div>
        </section>
    @elseif($project->status === 'declined' && $project->pm_user_id === auth()->id())
        <section class="action-banner" style="margin-bottom: 20px; background: #FEF2F2; border: 1px solid #FECACA; border-left: 5px solid #DC2626; border-radius: 8px; padding: 16px 20px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; background: #FEE2E2; color: #DC2626; border-radius: 50%; font-size: 14px; font-weight: bold;">✕</span>
                <div>
                    <strong style="color: #991B1B; font-size: 15px;">You Declined This Project Assignment</strong>
                    <p style="color: #7F1D1D; font-size: 13px; margin: 4px 0 0 0;">
                        You declined this assignment on {{ $declinedInvitation?->responded_at ? $declinedInvitation->responded_at->format('d M Y \a\t H:i') : 'recently' }}. PMO Administration will review your decline reason and reassign the project.
                    </p>
                    @if($declinedInvitation?->decline_reason)
                        <div style="margin-top: 8px; background: #FFFFFF; border: 1px solid #FCA5A5; border-radius: 6px; padding: 8px 12px; font-size: 13px; color: #374151;">
                            <strong>Your Reason:</strong> <em>"{{ $declinedInvitation->decline_reason }}"</em>
                        </div>
                    @endif
                </div>
            </div>
        </section>
    @elseif($project->status === 'declined' && auth()->user()->isPmo())
        <section class="action-banner action-banner-declined" style="margin-bottom: 20px; background: #FEF2F2; border: 1px solid #FECACA; border-left: 5px solid #DC2626; border-radius: 8px; padding: 16px 20px;">
            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 260px;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                        <span style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; background: #DC2626; color: white; border-radius: 50%; font-size: 14px; font-weight: bold;">✕</span>
                        <strong style="color: #991B1B; font-size: 15px;">Project Manager Assignment Declined</strong>
                    </div>
                    <p style="color: #7F1D1D; font-size: 13px; margin: 6px 0 10px 0;">
                        <strong>{{ $declinedInvitation?->user?->name ?? $project->manager?->name ?? 'Assigned PM' }}</strong> declined this assignment on <strong>{{ $declinedInvitation?->responded_at ? $declinedInvitation->responded_at->format('d M Y \a\t H:i') : 'recently' }}</strong>.
                    </p>
                    @if($declinedInvitation?->decline_reason)
                        <div style="background: white; border: 1px solid #FCA5A5; border-radius: 6px; padding: 10px 14px; color: #1F2937; font-size: 13px;">
                            <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #991B1B; letter-spacing: 0.5px; margin-bottom: 2px;">Decline Reason:</div>
                            <div style="font-style: italic; color: #374151;">"{{ $declinedInvitation->decline_reason }}"</div>
                        </div>
                    @endif
                </div>
                <div style="display: flex; align-items: center; gap: 10px; margin-top: 4px;">
                    <button type="button" class="btn btn-primary" data-show-dialog="assign-pm-dialog" style="background: #315F58; border-color: #315F58; font-weight: 600; white-space: nowrap;">
                        <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" style="display: inline-block; vertical-align: -2px; margin-right: 4px;">
                            <path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z"/>
                        </svg>
                        Reassign Project Manager
                    </button>
                </div>
            </div>
        </section>
    @elseif($project->status === 'pending' && auth()->user()->isPmo())
        <section class="action-banner action-banner-pending" style="margin-bottom: 20px; background: #FFFBEB; border: 1px solid #FDE68A; border-left: 5px solid #F59E0B; border-radius: 8px; padding: 14px 20px;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
                <div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; background: #F59E0B; color: white; border-radius: 50%; font-size: 13px; font-weight: bold;">⏳</span>
                        <strong style="color: #92400E; font-size: 14px;">Awaiting Project Manager Acceptance</strong>
                    </div>
                    <p style="color: #B45309; font-size: 13px; margin: 4px 0 0 0;">
                        Assignment invitation sent to <strong>{{ $project->manager?->name }}</strong> on {{ $pendingInvitation?->created_at ? $pendingInvitation->created_at->format('d M Y') : $project->created_at->format('d M Y') }}. You can reassign to another Project Manager if needed.
                    </p>
                </div>
                <button type="button" class="btn btn-outline btn-sm" data-show-dialog="assign-pm-dialog" style="border-color: #315F58; color: #315F58; font-weight: 600;">
                    Reassign PM
                </button>
            </div>
        </section>
    @elseif($project->status === 'draft' && auth()->user()->isPmo() && !$project->pm_user_id)
        <section class="action-banner" style="margin-bottom: 20px;">
            <div>
                <strong>Project Manager not assigned</strong>
                <p class="muted">Assign an active Project Manager to submit this project for acceptance.</p>
            </div>
            <button class="btn btn-primary btn-sm" type="button" data-show-dialog="assign-pm-dialog">Assign PM</button>
        </section>
    @endif

    <dl class="workspace-summary workspace-overview-details" aria-label="Project summary">
        <div class="workspace-manager">
            <dt>Project manager</dt>
            <dd>
                @if($project->manager)
                    <span class="workspace-manager-avatar" aria-hidden="true">{{ Str::upper(Str::substr($project->manager->name, 0, 1)) }}</span>
                    <span class="workspace-manager-name">{{ $project->manager->name }}</span>
                @else
                    <span class="workspace-manager-unassigned">Not assigned</span>
                @endif
            </dd>
        </div>
        <div class="workspace-schedule">
            <dt>Schedule</dt>
            <dd>
                <time datetime="{{ $project->starts_on->format('Y-m-d') }}" aria-label="Start date">{{ $project->starts_on->format('d M Y') }}</time>
                <span class="workspace-date-separator" aria-hidden="true">&rarr;</span>
                <time datetime="{{ $project->ends_on->format('Y-m-d') }}" aria-label="Due date">{{ $project->ends_on->format('d M Y') }}</time>
            </dd>
        </div>
        <div class="workspace-summary-progress">
            <dt>Progress</dt>
            <dd>
                <strong>{{ $project->progress }}%</strong>
                <span class="workspace-progress-track" role="progressbar" aria-label="Project progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $project->progress }}">
                    <span style="width:{{ $project->progress }}%;"></span>
                </span>
                <span class="workspace-completed-tasks">{{ $completedCount }} of {{ $totalCount }} tasks complete</span>
            </dd>
        </div>
    </dl>
    </section>
@php
        $overdueCount = $tasks->filter(fn($t) => $t->isOverdue())->count();
    @endphp

    <!-- Main Tabs Navigation (Role-Scoped) -->
    <nav class="project-tabs-nav" aria-label="Project workspace sections">
        <button type="button" class="project-tab-btn active" data-tab-target="tasks">
            {{ ($isScopedTeamMember ?? false) ? 'My Assigned Tasks' : 'Tasks' }}
            @if($overdueCount > 0)
                <span style="background: #DC2626; color: #FFFFFF; font-size: 10.5px; font-weight: 700; padding: 1px 7px; border-radius: 10px; margin-left: 6px;" title="{{ $overdueCount }} tasks overdue">{{ $overdueCount }} Overdue</span>
            @endif
        </button>
        @if($canViewAllTasks ?? true)
            <button type="button" class="project-tab-btn" data-tab-target="gantt">Timeline</button>
<button type="button" class="project-tab-btn" data-tab-target="team">Team ({{ $project->memberships->count() }})</button>
        @endif
        @if($canViewAllTasks ?? true)
            <button type="button" class="project-tab-btn" data-tab-target="milestones">Milestones</button>
            <button type="button" class="project-tab-btn" data-tab-target="risks">Risks &amp; delays @if($delays->where('status', 'open')->count())<span class="workspace-tab-count">{{ $delays->where('status', 'open')->count() }}</span>@endif</button>
            <button type="button" class="project-tab-btn" data-tab-target="changes">Changes</button>
        @endif
        <button type="button" class="project-tab-btn" data-tab-target="info">Details</button>
    </nav>

    <!-- =========================================================================
         TAB 1: TASKS (Unified WBS Tree + Integrated Timeline Gantt)
         ========================================================================= -->
    <div class="pw-tab-panel active" id="tab-tasks">

        @if($isScopedTeamMember ?? false)
            <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 6px; padding: 10px 16px; margin-bottom: 14px; font-size: 13px; color: var(--muted); display: flex; align-items: center; justify-content: space-between;">
                <span>Showing tasks explicitly assigned to you in this project.</span>
                <span class="status-pill pill-neutral">{{ $tasks->count() }} Assigned</span>
            </div>
        @endif

        <div class="workspace-task-toolbar">
            <h2 class="workspace-sr-only">Project tasks</h2>
            @if($canViewTasks && $tasks->isNotEmpty())
                <div class="task-quick-filters" role="search" aria-label="Filter project tasks">
                    <label class="quick-search-label"><span class="workspace-sr-only">Find a task</span>
                        <input type="search" data-task-search placeholder="Find a task or assignee..." autocomplete="off">
                    </label>
                    <label><span class="workspace-sr-only">Task status</span>
                        <select data-task-filter>
                            <option value="all">All tasks</option>
                            <option value="overdue">Overdue</option>
                            <option value="open">Open</option>
                            <option value="completed">Completed</option>
                        </select>
                    </label>
                    <button type="button" class="btn btn-ghost btn-sm" data-clear-task-filters hidden>Clear</button>
                    <span class="workspace-result-count" data-task-result-count role="status" aria-live="polite"></span>
                </div>
            @endif
            @if($canManage)
                <div class="workspace-task-actions">
                    <button type="button" class="btn btn-primary" data-show-dialog="add-task-dialog" data-add-mode="main">+ Add task</button>
                    @if($tasks->isNotEmpty())
                        <button type="button" class="btn btn-ghost" data-show-dialog="add-task-dialog" data-add-mode="sub">+ Subtask</button>
                    @endif
                    <button type="button" class="btn btn-outline" data-show-dialog="bulk-edit-tasks-dialog" id="open-bulk-edit-btn">Bulk edit</button>
                    <div class="pw-dropdown-wrap">
                        <button type="button" class="btn btn-ghost pw-dropdown-toggle" aria-label="Import and export tasks">
                            Import / export <span aria-hidden="true">&#8964;</span>
                        </button>
                        <div class="pw-dropdown-menu align-right">
                            <button type="button" class="pw-dropdown-item" data-show-dialog="import-task-dialog">Import from Excel</button>
                            <a href="{{ route('tasks.export', ['project' => $project, 'blank' => 1]) }}" class="pw-dropdown-item">Download blank Excel template</a>
                            <a href="{{ route('tasks.export', $project) }}" class="pw-dropdown-item">Export tasks to Excel</a>
                        </div>
                    </div>
                </div>
            @endif
        </div>
        @if(!$canViewTasks)
            <div class="empty-state-inline" style="background: #FFFFFF; border: 1px solid var(--border); border-radius: 8px;">
                @if($project->status === 'pending')
                    Tasks will become available to the team as soon as the Project Manager accepts this project.
                @else
                    You have permission to view the project summary. Detailed task access is restricted.
                @endif
            </div>
        @elseif($tasks->isEmpty())
            <div class="empty-state-inline" style="background: #FFFFFF; border: 1px solid var(--border); border-radius: 8px; padding: 48px 24px;">
                <b>No tasks created yet</b>
                <p class="muted">Add your first task or import your plan using an Excel template.</p>
                @if($canManage)
                    <div class="inline-actions mt-16" style="justify-content: center; gap: 10px;">
                        <button class="btn btn-primary" type="button" data-show-dialog="add-task-dialog">+ Add Task</button>
                        <button class="btn btn-outline" type="button" data-show-dialog="import-task-dialog">Import Excel</button>
                    </div>
                @endif
            </div>
        @else
            <p class="empty-state" data-task-no-results hidden>No matching tasks. Change your search or clear the filters.</p>
            <!-- Project task list -->
            <div class="card" style="padding: 0; background: #FFFFFF; border: 1px solid var(--border, #D0D7DE); border-radius: 8px; overflow: hidden; margin-bottom: 24px;">
                <div class="table-wrap" style="overflow-x: auto;">
                    <table class="clean-table project-tasks-full-table" style="width: 100%; border-collapse: collapse; text-align: left;">
                        <thead>
                            <tr style="background: #F8FAFC; border-bottom: 1px solid #E2E8F0; font-size: 12px; font-weight: 700; color: #475569;">
                                <th style="padding: 12px 16px; width: 34%;">Task / Subtask</th>
                                <th style="padding: 12px 14px; width: 18%;">Assignee</th>
                                <th style="padding: 12px 14px; width: 14%;">Starts</th>
                                <th style="padding: 12px 14px; width: 14%;">Due</th>
                                <th style="padding: 12px 14px; width: 10%;">Progress</th>
                                <th style="padding: 12px 16px; width: 10%;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tasks as $task)
                                @php
                                    $wbsNum = $numbers[$task->id] ?? $task->code;
                                    $isSubtask = !empty($task->parent_task_id);
                                    $hasChildren = $tasks->where('parent_task_id', $task->id)->count() > 0;
                                    $assignees = $task->assignees;
                                    $primaryAssignee = $assignees->first();
                                    $extraCount = max(0, $assignees->count() - 1);
                                    $isOverdue = $task->isOverdue();
                                @endphp
                                <tr class="project-task-row {{ $isSubtask ? 'is-subtask' : 'is-main-task' }} {{ $isOverdue ? 'is-overdue-task' : '' }}" 
                                    data-task-id="{{ $task->id }}" 
                                    data-parent="{{ $task->parent_task_id ?? '' }}"
                                    data-is-overdue="{{ $isOverdue ? '1' : '0' }}" data-task-status="{{ $task->status }}" data-task-search-text="{{ $task->title . ' ' . $assignees->pluck('name')->join(' ') }}"
                                    style="border-bottom: 1px solid #F1F5F9; transition: background 0.12s; {{ $isOverdue ? 'background: #FFF8F8;' : '' }}">
                                    
                                    <!-- Column 1: Task / Subtask -->
                                    <td style="padding: 12px 16px; {{ $isSubtask ? 'padding-left: 36px;' : '' }}">
                                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                            <span class="muted" style="font-size: 12px; font-weight: 600; min-width: 24px;">{{ $wbsNum }}</span>
                                            @if($hasChildren)
                                                <button type="button" class="uwt-toggle-btn" data-parent-task="{{ $task->id }}" aria-expanded="true" title="Collapse / Expand subtasks" style="cursor: pointer; border: none; background: none; color: var(--muted); font-size: 11px; padding: 2px 4px;">
                                                    ⌵
                                                </button>
                                            @endif
                                            <button type="button" 
                                                    class="uwt-task-title-link" 
                                                    data-task-panel="{{ route('tasks.panel', $task) }}" 
                                                    title="Click to view details"
                                                    style="font-size: 13.5px; {{ $isSubtask ? 'font-weight: 500; color: #334155;' : 'font-weight: 700; color: #0F172A;' }} text-decoration: none; border: none; background: none; cursor: pointer; text-align: left; padding: 0;">
                                                {{ $task->title }}
                                            </button>
                                            @if($isOverdue)
                                                <span class="badge-overdue-tag" style="background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; font-size: 10px; font-weight: 700; padding: 1.5px 6px; border-radius: 4px; display: inline-flex; align-items: center; gap: 3px; vertical-align: middle; flex-shrink: 0;" title="Task deadline has passed">
                                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                                    Overdue
                                                </span>
                                            @endif
                                        </div>
                                    </td>

                                    <!-- Column 2: Assignee -->
                                    <td style="padding: 12px 14px; font-size: 13px;">
                                        @if($primaryAssignee)
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <span style="color: #22272B; font-weight: 500;">{{ $primaryAssignee->name }}</span>
                                                @if($extraCount > 0)
                                                    <span class="pill pill-neutral" title="{{ $assignees->pluck('name')->join(', ') }}" style="font-size: 10px; padding: 1px 5px; cursor: help;">+{{ $extraCount }}</span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="muted" style="font-size: 12.5px;">Unassigned</span>
                                        @endif
                                    </td>

                                    <!-- Column 3: Start Date & Time -->
                                    <td style="padding: 12px 14px; font-size: 12.5px;">
                                        <div style="color: #22272B; font-weight: 500;">{{ $task->starts_at->format('d M Y') }}</div>
                                        <div class="muted" style="font-size: 11px;">{{ $task->starts_at->format('h:i A') }}</div>
                                    </td>

                                    <!-- Column 4: End Date & Time -->
                                    <td style="padding: 12px 14px; font-size: 12.5px;" class="task-end-date-cell">
                                        @if($isOverdue)
                                            <div class="task-date-val" style="color: #DC2626; font-weight: 700;">{{ $task->ends_at->format('d M Y') }}</div>
                                            <div style="display: flex; align-items: center; gap: 6px; margin-top: 3px;">
                                                <span class="task-time-val" style="font-size: 11px; color: #DC2626; font-weight: 500;">{{ $task->ends_at->format('h:i A') }}</span>
                                                <span class="badge-overdue" style="background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 4px; display: inline-flex; align-items: center; gap: 2px;">
                                                    Overdue
                                                </span>
                                            </div>
                                        @else
                                            <div class="task-date-val" style="color: #22272B; font-weight: 500;">{{ $task->ends_at->format('d M Y') }}</div>
                                            <div class="muted task-time-val" style="font-size: 11px;">{{ $task->ends_at->format('h:i A') }}</div>
                                        @endif
                                    </td>

                                    <!-- Column 5: Progress -->
                                    <td style="padding: 12px 14px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <div style="width: 50px; height: 5px; background: #E5E7EB; border-radius: 3px; overflow: hidden; flex-shrink: 0;">
                                                <div class="task-progress-fill" style="width: {{ $task->progress }}%; height: 100%; background: {{ $isOverdue ? '#DC2626' : '#315F58' }}; border-radius: 3px; transition: width 0.2s;"></div>
                                            </div>
                                            <span class="task-progress-text" style="font-size: 12px; font-weight: 600; color: {{ $isOverdue ? '#DC2626' : '#374151' }};">{{ $task->progress }}%</span>
                                        </div>
                                    </td>

                                    <!-- Column 6: Status -->
                                    <td style="padding: 12px 16px;">
                                        @if($canManage || (app(\App\Services\Access::class)->task(auth()->user(), $task, 'update') && $project->status === 'active'))
                                            <select class="quick-status-select" 
                                                    data-task-id="{{ $task->id }}"
                                                    data-update-url="{{ route('tasks.update', $task) }}"
                                                    data-current-status="{{ $task->status }}"
                                                    style="{{ $isOverdue ? 'border-color: #FCA5A5; color: #DC2626; font-weight: 600;' : '' }}">
                                                @foreach(['todo' => 'To Do', 'in_progress' => 'In Progress', 'on_hold' => 'On Hold', 'completed' => 'Completed'] as $val => $lbl)
                                                    <option value="{{ $val }}" @selected($task->status === $val)>{{ $lbl }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <div style="display: flex; align-items: center; gap: 4px;">
                                                <span class="uwt-status-pill status-{{ $task->status }}" style="font-size: 11px;">
                                                    {{ ucwords(str_replace('_', ' ', $task->status)) }}
                                                </span>
                                                @if($isOverdue)
                                                    <span class="badge-overdue" style="font-size: 9.5px; padding: 1px 5px;">Overdue</span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>

                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    <!-- =========================================================================
         TAB 2: GANTT (Dedicated Schedule Gantt with Critical Path Analysis)
         ========================================================================= -->
    <div class="pw-tab-panel" id="tab-gantt">
        <div class="card" style="padding: 20px; background: #FFFFFF; border: 1px solid var(--border); border-radius: 8px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h2 style="font-size: 16px; font-weight: 700; margin: 0 0 4px;">Full Schedule Gantt</h2>
                    <p class="muted" style="font-size: 13px; margin: 0;">Comprehensive schedule with dependencies and Critical Path Analysis.</p>
                </div>
                <div class="inline-actions" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    @if($health && $health['cpm_reliable'])
                        <label class="quiet-check" style="font-size: 12.5px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="checkbox" id="highlight-critical" @checked(in_array($health['health'], ['at_risk', 'overdue'], true))>
                            Highlight Critical Path
                        </label>
                    @endif

                    <!-- Scale Switcher Segmented Control -->
                    <div class="view-scale-segmented" role="group" aria-label="Timeline scale">
                        <button type="button" class="view-scale-btn" data-scale="day">Day</button>
                        <button type="button" class="view-scale-btn active" data-scale="week">Week</button>
                        <button type="button" class="view-scale-btn" data-scale="month">Month</button>
                    </div>
                </div>
            </div>

            @if($health && $health['cpm_warning'])
                <div class="schedule-hint" style="margin-bottom: 16px;">
                    <b>Critical Path:</b> {{ $health['cpm_warning'] }}
                </div>
            @endif

            @if($tasks->isEmpty())
                <p class="muted text-sm">No tasks created yet to display on the Gantt chart.</p>
            @else
                <!-- Interactive Gantt Timeline Grid in Tab 2 -->
                <div class="unified-workplan-card" style="border: 1px solid var(--border); border-radius: 6px; margin-top: 14px;">
                    <div class="unified-workplan-scroll" id="unified-workplan-scroll">
                        <div class="unified-workplan-table" id="unified-workplan-grid">
                            <!-- Header Row -->
                            <div class="uwt-header-row">
                                <div class="uwt-head-table">
                                    <div style="text-align: center;">#</div>
                                    <div>Task</div>
                                    <div>Status</div>
                                    <div>Progress</div>
                                    <div>Start</div>
                                    <div>End</div>
                                </div>
                                <div class="uwt-head-timeline">
                                    <div class="uwt-timeline-month-band" id="uwt-month-band"></div>
                                    <div class="uwt-timeline-sub-band" id="uwt-sub-band">
                                        <div class="uwt-today-badge" style="display: none;">Today</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Body Rows for Gantt -->
                            @foreach($tasks as $task)
                                @php
                                    $wbsNum = $numbers[$task->id] ?? $task->code;
                                    $isSubtask = !empty($task->parent_task_id);
                                    $isOverdue = $task->isOverdue();
                                @endphp
                                <div class="uwt-body-row {{ $isSubtask ? 'is-subtask' : '' }} {{ $isOverdue ? 'is-overdue-row' : '' }}" data-task-id="{{ $task->id }}">
                                    <div class="uwt-cell-table">
                                        <div class="uwt-wbs-num" style="text-align: center;">{{ $wbsNum }}</div>
                                        <div class="uwt-task-title-cell" style="display: flex; align-items: center; gap: 4px; overflow: hidden;">
                                            <button type="button" class="uwt-task-title-link" data-task-panel="{{ route('tasks.panel', $task) }}" data-uwt-task-link="{{ $task->id }}" title="{{ $task->title }}" style="text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">
                                                {{ $task->title }}
                                            </button>
                                            @if($isOverdue)
                                                <span style="background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; font-size: 9px; font-weight: 700; padding: 1px 4px; border-radius: 3px; flex-shrink: 0;">Overdue</span>
                                            @endif
                                        </div>
                                        <div>
                                            <span class="uwt-status-pill status-{{ $task->status }}">{{ ucwords(str_replace('_', ' ', $task->status)) }}</span>
                                        </div>
                                        <div style="font-size: 12px; font-weight: 600; color: {{ $isOverdue ? '#DC2626' : '#374151' }};">{{ $task->progress }}%</div>
                                        <div style="font-size: 11.5px; color: var(--muted);">{{ $task->starts_at->format('d M') }}</div>
                                        <div style="font-size: 11.5px; {{ $isOverdue ? 'color: #DC2626; font-weight: 700;' : 'color: var(--muted);' }}">
                                            {{ $task->ends_at->format('d M') }}
                                            @if($isOverdue)
                                                <span style="color: #DC2626; font-size: 9px; font-weight: 700; display: block;">(Overdue)</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="uwt-cell-timeline" data-uwt-track="{{ $task->id }}">
                                        <div class="uwt-today-line" style="display: none;"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- =========================================================================
         TAB 3: TEAM & ROLES
         ========================================================================= -->
    <div class="pw-tab-panel" id="tab-team">
        <div class="card" style="padding: 20px; background: #FFFFFF;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h2 style="font-size: 16px; font-weight: 700; margin: 0 0 4px;">Project Team & Stakeholders</h2>
                    <p class="muted" style="font-size: 13px; margin: 0;">Assigned employees, roles, and project leadership.</p>
                </div>
                @if($canTeam && $project->status !== 'archived')
                    <button class="btn btn-primary btn-sm" type="button" onclick="document.getElementById('add-member-form-box').style.display = document.getElementById('add-member-form-box').style.display === 'none' ? 'block' : 'none'">
                        + Add Member
                    </button>
                @endif
            </div>

            <!-- Inline Add Member Form (Hidden by default, 0 clicks to expand) -->
            <div id="add-member-form-box" style="display: none; background: var(--surface); border: 1px solid var(--border); border-radius: 6px; padding: 16px; margin-bottom: 20px;">
                <form method="post" action="{{ route('projects.members.store', $project) }}" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
                    @csrf
                    <label style="flex: 1; min-width: 220px; margin: 0; font-size: 12px; font-weight: 600;">Person
                        <select name="user_id" required style="height: 38px; font-size: 13px; background: #FFFFFF; margin-top: 4px;">
                            <option value="">Select team member...</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                    </label>
                    <label style="min-width: 180px; margin: 0; font-size: 12px; font-weight: 600;">Role
                        <select name="project_role_id" required style="height: 38px; font-size: 13px; background: #FFFFFF; margin-top: 4px;">
                            <option value="">Select role...</option>
                            @foreach($roles->where('slug', '!=', 'pm') as $r)
                                <option value="{{ $r->id }}">{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="btn btn-primary" style="height: 38px; font-size: 13px;">Add to Team</button>
                    <button type="button" class="btn btn-ghost" style="height: 38px;" onclick="document.getElementById('add-member-form-box').style.display='none'">Cancel</button>
                </form>
            </div>

            <!-- Team Members Table -->
            <div class="table-wrap">
                <table class="clean-table">
                    <thead>
                        <tr>
                            <th>Member Name</th>
                            <th>Work Email</th>
                            <th>Project Role</th>
                            <th>Assigned Tasks</th>
                            @if($canTeam) <th class="right">Action</th> @endif
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Project Manager Row -->
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span class="avatar avatar-sm">{{ strtoupper(substr($project->manager?->name ?? 'P', 0, 1)) }}</span>
                                    <b>{{ $project->manager?->name ?? 'Not assigned' }}</b>
                                </div>
                            </td>
                            <td><span class="muted text-sm">{{ $project->manager?->email ?? '—' }}</span></td>
                            <td><span class="pill" style="background: #E8F0EE; color: var(--primary); font-weight: 700;">Project Manager</span></td>
                            <td>{{ $tasks->filter(fn($t) => $t->assignees->contains('id', $project->pm_user_id))->count() }}</td>
                            @if($canTeam)
                                <td class="right">
                                    @if(auth()->user()->isPmo() && in_array($project->status, ['draft', 'pending', 'declined']))
                                        <button type="button" class="btn btn-outline btn-sm" data-show-dialog="assign-pm-dialog">Change PM</button>
                                    @else
                                        <span class="muted text-sm">Lead</span>
                                    @endif
                                </td>
                            @endif
                        </tr>

                        <!-- Other Members -->
                        @forelse($project->memberships->where('user_id', '!=', $project->pm_user_id) as $m)
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span class="avatar avatar-sm">{{ strtoupper(substr($m->user?->name ?? 'U', 0, 1)) }}</span>
                                        <b>{{ $m->user?->name ?? 'Unknown' }}</b>
                                    </div>
                                </td>
                                <td><span class="muted text-sm">{{ $m->user?->email ?? '—' }}</span></td>
                                <td><span class="pill todo">{{ $m->role?->name ?? 'Team Member' }}</span></td>
                                <td>{{ $tasks->filter(fn($t) => $t->assignees->contains('id', $m->user_id))->count() }}</td>
                                @if($canTeam)
                                    <td class="right">
                                        <form method="post" action="{{ route('projects.members.delete', ['project' => $project, 'user' => $m->user_id]) }}" onsubmit="return confirm('Remove this member from project?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline btn-sm" style="color: var(--danger);">Remove</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            @if(!$project->pm_user_id)
                                <tr>
                                    <td colspan="5" class="empty">No team members assigned yet.</td>
                                </tr>
                            @endif
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- =========================================================================
         TAB 4: FILES REPOSITORY
         ========================================================================= -->
    <div class="pw-tab-panel" id="tab-files">
        <div class="card" style="padding: 20px; background: #FFFFFF;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h2 style="font-size: 16px; font-weight: 700; margin: 0 0 4px;">Project Files & Attachments</h2>
                    <p class="muted" style="font-size: 13px; margin: 0;">Documents, deliverables, specifications, and task uploads.</p>
                </div>
            </div>

            @if($files->isEmpty())
                <div class="empty-state-inline">
                    <b>No files attached yet</b>
                    <p class="muted">Upload deliverables or documents directly from any task detail panel.</p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="clean-table">
                        <thead>
                            <tr>
                                <th>File Name</th>
                                <th>Related Task</th>
                                <th>Uploaded Date</th>
                                <th class="right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($files as $file)
                                <tr>
                                    <td><b>{{ $file->original_name }}</b></td>
                                    <td>
                                        <button type="button" class="text-action" data-task-panel="{{ route('tasks.panel', $file->task) }}">
                                            {{ $file->task?->title ?? 'Task' }}
                                        </button>
                                    </td>
                                    <td><span class="muted text-sm">{{ $file->created_at->format('d M Y, H:i') }}</span></td>
                                    <td class="right">
                                        <a href="{{ route('attachments.download', $file) }}" class="btn btn-outline btn-sm">Download</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- =========================================================================
         TAB 5: MILESTONES
         ========================================================================= -->
    <div class="pw-tab-panel" id="tab-milestones">
        <div class="card" style="padding: 20px; background: #FFFFFF;">
            <div style="margin-bottom: 16px;">
                <h2 style="font-size: 16px; font-weight: 700; margin: 0 0 4px;">Project Milestones & Deliverables</h2>
                <p class="muted" style="font-size: 13px; margin: 0;">Major schedule checkpoints and completion deadlines.</p>
            </div>

            @php
                $milestoneTasks = $tasks->filter(fn($t) => $t->children()->count() > 0 || str_contains(strtolower($t->title), 'milestone') || str_contains(strtolower($t->title), 'approval') || str_contains(strtolower($t->title), 'sign-off') || str_contains(strtolower($t->title), 'go-live'));
            @endphp

            @if($milestoneTasks->isEmpty())
                <p class="muted">All scheduled tasks are tracked in the <b>Tasks</b> view.</p>
            @else
                <div class="table-wrap">
                    <table class="clean-table">
                        <thead>
                            <tr>
                                <th>Phase / Milestone</th>
                                <th>Target Date</th>
                                <th>Progress</th>
                                <th>Status</th>
                                <th class="right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($milestoneTasks as $mt)
                                @php $mtOverdue = $mt->isOverdue(); @endphp
                                <tr style="{{ $mtOverdue ? 'background: #FFF8F8;' : '' }}">
                                    <td>
                                        <b>{{ $mt->title }}</b>
                                        @if($mtOverdue)
                                            <span class="badge-overdue" style="font-size: 10px; margin-left: 6px;">Overdue</span>
                                        @endif
                                    </td>
                                    <td style="{{ $mtOverdue ? 'color: #DC2626; font-weight: 700;' : '' }}">
                                        {{ $mt->ends_at->format('d M Y') }}
                                        @if($mtOverdue)
                                            <span style="color: #DC2626; font-size: 11px; font-weight: 700; display: block;">Overdue</span>
                                        @endif
                                    </td>
                                    <td style="{{ $mtOverdue ? 'color: #DC2626; font-weight: 600;' : '' }}">{{ $mt->progress }}%</td>
                                    <td><span class="uwt-status-pill status-{{ $mt->status }}">{{ ucwords(str_replace('_',' ',$mt->status)) }}</span></td>
                                    <td class="right">
                                        <button type="button" class="btn btn-outline btn-sm" data-task-panel="{{ route('tasks.panel', $mt) }}">View</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- =========================================================================
         TAB 6: RISKS & REPORTED DELAYS
         ========================================================================= -->
    <div class="pw-tab-panel" id="tab-risks">
        <div class="card" style="padding: 20px; background: #FFFFFF;">
            <div style="margin-bottom: 16px;">
                <h2 style="font-size: 16px; font-weight: 700; margin: 0 0 4px;">Risks, Blockers & Delay Reports</h2>
                <p class="muted" style="font-size: 13px; margin: 0;">Investigate reported bottlenecks, responsible parties, and action owners.</p>
            </div>

            @if($delays->isEmpty())
                <div class="empty-state-inline">
                    <b>No reported blockers</b>
                    <p class="muted">Team members can flag delay reasons and follow-up owners directly on any task.</p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="clean-table">
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Cause / Blocker</th>
                                <th>Involved Party</th>
                                <th>Follow-up Owner</th>
                                <th>Status</th>
                                <th class="right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($delays as $delay)
                                <tr>
                                    <td>
                                        <button type="button" class="text-action" data-task-panel="{{ route('tasks.panel', $delay->task) }}">
                                            <b>{{ $delay->task?->title ?? 'Task' }}</b>
                                        </button>
                                    </td>
                                    <td>
                                        <b>{{ \App\Http\Controllers\TaskDelayController::REASONS[$delay->reason_code] ?? $delay->reason_code }}</b>
                                        @if($delay->details)
                                            <small class="block muted">{{ $delay->details }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="pill todo">
                                            {{ $delay->responsible_party_name ?: ucwords($delay->responsible_party_type) }}
                                        </span>
                                    </td>
                                    <td>{{ $delay->actionOwner?->name ?? 'Not assigned' }}</td>
                                    <td>
                                        <span class="pill {{ $delay->status === 'open' ? 'on_hold' : 'active' }}">
                                            {{ ucfirst($delay->status) }}
                                        </span>
                                    </td>
                                    <td class="right">
                                        @if($delay->status === 'open' && $canManage)
                                            <form method="post" action="{{ route('tasks.delays.resolve', $delay) }}" style="display: inline-block;">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-outline btn-sm">Resolve</button>
                                            </form>
                                        @else
                                            <button type="button" class="btn btn-outline btn-sm" data-task-panel="{{ route('tasks.panel', $delay->task) }}">Details</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- =========================================================================
         TAB 7: CHANGES & BASELINE AUDIT
         ========================================================================= -->
    <div class="pw-tab-panel" id="tab-changes">
        <div class="card" style="padding: 20px; background: #FFFFFF;">
            <div style="margin-bottom: 16px;">
                <h2 style="font-size: 16px; font-weight: 700; margin: 0 0 4px;">Baseline Variance & Audit Trail</h2>
                <p class="muted" style="font-size: 13px; margin: 0;">Historical baseline comparison and recorded project changes.</p>
            </div>

            @if($health && $health['baseline_available'])
                <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 6px; padding: 14px 18px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <b>Schedule Baseline Active</b>
                            <p class="muted text-sm" style="margin: 2px 0 0;">
                                Saved on {{ $project->baseline_captured_at?->format('d M Y') }}. Original planned deadline: <b>{{ $project->baseline_ends_on?->format('d M Y') }}</b>.
                            </p>
                        </div>
                        <div>
                            @php
                                $diff = $project->ends_on->diffInDays($project->baseline_ends_on, false);
                            @endphp
                            @if($diff < 0)
                                <span class="pill on_hold">{{ abs($diff) }} Days Delayed</span>
                            @else
                                <span class="pill active">On Baseline</span>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 6px; padding: 14px 18px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <b>No Baseline Captured</b>
                        <p class="muted text-sm" style="margin: 2px 0 0;">Save the approved schedule baseline to compare future variance and delays.</p>
                    </div>
                    @if($canTeam && in_array($project->status, ['active', 'on_hold'], true))
                        <form method="post" action="{{ route('projects.baseline', $project) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline btn-sm">Capture Baseline</button>
                        </form>
                    @endif
                </div>
            @endif

            <h3 style="font-size: 14px; font-weight: 700; margin: 20px 0 10px;">Recent Activity History</h3>
            @if($activities->isEmpty())
                <p class="muted text-sm">No activity records logged yet.</p>
            @else
                <div class="table-wrap">
                    <table class="clean-table">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>User</th>
                                <th>Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($activities as $act)
                                <tr>
                                    <td><b>{{ ucwords(str_replace('.', ' ', $act->action)) }}</b></td>
                                    <td>{{ $act->actor?->name ?? 'System' }}</td>
                                    <td><span class="muted text-sm">{{ $act->created_at->format('d M Y, H:i') }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- =========================================================================
         TAB 8: PROJECT INFO & CLOSURE
         ========================================================================= -->
    <div class="pw-tab-panel" id="tab-info">
        <div class="card" style="padding: 20px; background: #FFFFFF;">
            <div style="margin-bottom: 18px;">
                <h2 style="font-size: 16px; font-weight: 700; margin: 0 0 4px;">Project Overview & Configuration</h2>
                <p class="muted" style="font-size: 13px; margin: 0;">General specifications, subsidiary entity, and closure details.</p>
            </div>

            <div class="info-grid" style="margin-bottom: 20px;">
                <div>
                    <small>Project Code</small>
                    <b>{{ $project->code }}</b>
                </div>
                <div>
                    <small>Subsidiary</small>
                    <b>{{ $project->subsidiary?->name ?? 'None' }} [{{ $project->subsidiary?->code ?? '—' }}]</b>
                </div>
                <div>
                    <small>Project Manager</small>
                    <b>{{ $project->manager?->name ?? 'Not assigned' }}</b>
                </div>
                <div>
                    <small>Timeline</small>
                    <b>{{ $project->starts_on->format('d M Y') }} – {{ $project->ends_on->format('d M Y') }}</b>
                </div>
            </div>

            @if($project->description)
                <div style="background: var(--surface); padding: 14px 18px; border-radius: 6px; margin-bottom: 20px; border: 1px solid var(--border);">
                    <b style="font-size: 12px; color: var(--muted); display: block; margin-bottom: 4px;">Description</b>
                    <p style="margin: 0; font-size: 13.5px; line-height: 1.5; color: var(--text);">{{ $project->description }}</p>
                </div>
            @endif

            <div style="border-top: 1px solid var(--border); padding-top: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    <b>Project Governance & Closure</b>
                    <p class="muted text-sm" style="margin: 2px 0 0;">Complete all deliverables, review sign-offs, and submit for formal closure.</p>
                </div>
                <div class="inline-actions">
                    @if(app(\App\Services\Access::class)->project(auth()->user(), $project, 'edit_project') && $project->status !== 'archived')
                        <button type="button" class="btn btn-outline btn-sm" data-show-dialog="project-edit-dialog">Edit Details</button>
                    @endif
                    @if($canTeam)
                        <button type="button" class="btn btn-outline btn-sm" data-show-dialog="project-closure-dialog">Closure Sign-off →</button>
                    @endif
                </div>
            </div>
        </div>
    </div>

</div>

<!-- =========================================================================
     SLIDE-OUT TASK DETAILS SIDE PANEL (Accessible Drawer, 0 Page Reload)
     ========================================================================= -->
<aside class="task-side-panel" id="task-side-panel" aria-labelledby="task-panel-title" aria-hidden="true" hidden>
    <div class="task-panel-top">
        <h2 id="task-panel-title">Task Details</h2>
        <button type="button" class="panel-close" id="task-panel-close" aria-label="Close task details">×</button>
    </div>
    <div id="task-panel-content" class="task-panel-content">
        <p class="muted">Select a task to see its details.</p>
    </div>
</aside>
<div id="task-panel-shade" class="task-panel-shade" hidden></div>

<!-- =========================================================================
     COMPACT INLINE DIALOGS (Add Task, Import Tasks, Edit Project, Assign PM)
     ========================================================================= -->
@if($canManage)
    <!-- Add Task Dialog -->
    <dialog id="add-task-dialog" class="modal" style="max-width: 560px;">
        <div class="modal-head">
            <h2>Add Task</h2>
            <button type="button" class="icon-btn" data-close-dialog aria-label="Close">×</button>
        </div>
        <form method="post" action="{{ route('tasks.store', $project) }}" enctype="multipart/form-data" class="stack mt-16">
            @csrf
            <label>Task Name <span class="required">*</span>
                <input name="title" required maxlength="200" placeholder="e.g. Requirement Gathering">
            </label>

            <label>Main Task or Subtask
                <select name="parent_task_id" id="add-task-parent-select">
                    <option value="">Main Task (Top Level)</option>
                    @foreach($tasks as $t)
                        <option value="{{ $t->id }}">Under {{ $numbers[$t->id] ?? $t->code }} {{ $t->title }}</option>
                    @endforeach
                </select>
            </label>

            <div class="two-fields">
                <label>Start Date & Time <span class="required">*</span>
                    <input type="datetime-local" name="starts_at" value="{{ $project->starts_on->format('Y-m-d') }}T09:00" required>
                </label>
                <label>End Date & Time <span class="required">*</span>
                    <input type="datetime-local" name="ends_at" value="{{ $project->ends_on->format('Y-m-d') }}T17:00" required>
                </label>
            </div>

            <div class="two-fields">
                <label>Assign To
                    <select name="assigned_user_id" id="add-task-assignee-select">
                        <option value="">Unassigned (Assign later...)</option>
                        @foreach($project->memberships->unique('user_id') as $m)
                            <option value="{{ $m->user_id }}">{{ $m->user?->name }} ({{ $m->role?->name ?? 'Member' }})</option>
                        @endforeach
                    </select>
                    @if($canTeam)
                        <button type="button" class="text-action" id="add-task-open-member-btn" style="font-size: 11px; margin-top: 4px; display: inline-block; padding: 0; background: none; border: none; cursor: pointer; color: #315F58; text-decoration: underline;">
                            + Add Project Member
                        </button>
                    @endif
                </label>
                <label>Priority
                    <select name="priority">
                        <option value="medium">Normal</option>
                        <option value="low">Low</option>
                        <option value="high">High</option>
                    </select>
                </label>
            </div>

            <label>Description (Optional)
                <textarea name="description" rows="2" placeholder="Task deliverables or scope..."></textarea>
            </label>

            <div class="form-actions mt-16">
                <button class="btn btn-ghost" type="button" data-close-dialog>Cancel</button>
                <button class="btn btn-primary" type="submit">Create Task</button>
            </div>
        </form>
    </dialog>

    <!-- =========================================================================
         BULK TASK EDITING MODAL (Desktop Wide ~95vw, Max 1400px, Sticky Header/Footer)
         ========================================================================= -->
    <dialog id="bulk-edit-tasks-dialog" class="modal" style="width: 95vw; max-width: 1400px; max-height: 90vh; padding: 0; border: none; border-radius: 8px; box-shadow: 0 20px 50px rgba(0,0,0,0.25);">
        <form method="post" action="{{ route('tasks.bulk-update', $project) }}" id="bulk-edit-form" style="display: flex; flex-direction: column; height: 100%; max-height: 90vh;">
            @csrf
            
            <!-- Sticky Modal Header -->
            <div class="modal-head" style="padding: 16px 24px; border-bottom: 1px solid #E1E4E8; display: flex; justify-content: space-between; align-items: center; background: #FFFFFF; flex-shrink: 0;">
                <div>
                    <h2 style="font-size: 18px; font-weight: 700; color: #22272B; margin: 0 0 2px;">Edit Project Tasks</h2>
                    <p class="muted" style="font-size: 13px; margin: 0;">Update tasks directly in the table.</p>
                </div>
                <button type="button" class="icon-btn" data-close-dialog id="bulk-edit-x-btn" aria-label="Close dialog" style="font-size: 20px; background: none; border: none; cursor: pointer; color: #6B7280; padding: 4px 8px;">×</button>
            </div>

            <!-- Scrollable Bulk Edit Table -->
            <div class="bulk-edit-scroll" style="flex: 1; overflow-y: auto; overflow-x: auto; background: #FAFAFA; padding: 0;">
                <table class="clean-table bulk-edit-table" style="width: 100%; border-collapse: separate; border-spacing: 0; text-align: left; background: #FFFFFF; font-size: 12.5px;">
                    <thead style="position: sticky; top: 0; z-index: 5; background: #F8FAFC;">
                        <tr style="border-bottom: 1px solid #CBD5E1; color: #475569; font-size: 11.5px; font-weight: 700;">
                            <th style="padding: 10px 12px; width: 45px; border-bottom: 1px solid #CBD5E1;">#</th>
                            <th style="padding: 10px 12px; min-width: 220px; border-bottom: 1px solid #CBD5E1;">Task / Subtask</th>
                            <th style="padding: 10px 12px; min-width: 180px; border-bottom: 1px solid #CBD5E1;">
                                Assignee
                                @if($canTeam)
                                    <button type="button" class="text-action" id="bulk-open-add-member" style="font-size: 10.5px; color: #315F58; margin-left: 6px; text-decoration: underline; background: none; border: none; cursor: pointer;">+ Add Member</button>
                                @endif
                            </th>
                            <th style="padding: 10px 8px; width: 130px; border-bottom: 1px solid #CBD5E1;">Start Date</th>
                            <th style="padding: 10px 8px; width: 95px; border-bottom: 1px solid #CBD5E1;">Start Time</th>
                            <th style="padding: 10px 8px; width: 130px; border-bottom: 1px solid #CBD5E1;">End Date</th>
                            <th style="padding: 10px 8px; width: 95px; border-bottom: 1px solid #CBD5E1;">End Time</th>
                            <th style="padding: 10px 8px; width: 75px; border-bottom: 1px solid #CBD5E1;">Progress</th>
                            <th style="padding: 10px 10px; width: 120px; border-bottom: 1px solid #CBD5E1;">Status</th>
                            <th style="padding: 10px 12px; min-width: 200px; border-bottom: 1px solid #CBD5E1;">Dependencies</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tasks as $task)
                            @php
                                $wbsNum = $numbers[$task->id] ?? $task->code;
                                $isSub = !empty($task->parent_task_id);
                                $assignedId = $task->assignees->first()?->id;
                                $depIds = $task->dependencies->pluck('id')->all();
                            @endphp
                            <tr class="bulk-task-row {{ $isSub ? 'is-subtask-row' : '' }}" 
                                data-task-id="{{ $task->id }}"
                                style="border-bottom: 1px solid #E2E8F0; transition: background 0.15s;">
                                
                                <!-- 1. Number / Code -->
                                <td style="padding: 8px 12px; color: #64748B; font-weight: 600; font-size: 11.5px; border-bottom: 1px solid #E2E8F0;">
                                    {{ $wbsNum }}
                                    @if($task->isOverdue())
                                        <div style="color: #DC2626; font-size: 9.5px; font-weight: 700; margin-top: 2px;">Overdue</div>
                                    @endif
                                </td>

                                <!-- 2. Task Name -->
                                <td style="padding: 8px 12px; border-bottom: 1px solid #E2E8F0; {{ $isSub ? 'padding-left: 28px;' : '' }}">
                                    <input type="text" 
                                           name="tasks[{{ $task->id }}][title]" 
                                           value="{{ $task->title }}" 
                                           required 
                                           maxlength="200"
                                           class="bulk-input bulk-field-track"
                                           data-field="title"
                                           style="width: 100%; font-size: 12.5px; padding: 5px 8px; border: 1px solid #CBD5E1; border-radius: 4px; {{ $isSub ? 'font-weight: 500;' : 'font-weight: 650; color: #0F172A;' }}">
                                </td>

                                <!-- 3. Assignee -->
                                <td style="padding: 8px 12px; border-bottom: 1px solid #E2E8F0;">
                                    <select name="tasks[{{ $task->id }}][assigned_user_id]" 
                                            class="bulk-select bulk-assignee-select bulk-field-track"
                                            data-field="assigned_user_id"
                                            style="width: 100%; font-size: 12px; padding: 5px 6px; border: 1px solid #CBD5E1; border-radius: 4px; background: #FFFFFF;">
                                        <option value="">Unassigned</option>
                                        @foreach($project->memberships->unique('user_id') as $m)
                                            <option value="{{ $m->user_id }}" @selected($assignedId == $m->user_id)>
                                                {{ $m->user?->name }} ({{ $m->role?->name ?? 'Member' }})
                                            </option>
                                        @endforeach
                                    </select>
                                </td>

                                <!-- 4. Start Date -->
                                <td style="padding: 8px 8px; border-bottom: 1px solid #E2E8F0;">
                                    <input type="date" 
                                           name="tasks[{{ $task->id }}][start_date]" 
                                           value="{{ $task->starts_at->format('Y-m-d') }}" 
                                           required 
                                           class="bulk-input bulk-field-track"
                                           data-field="start_date"
                                           style="width: 100%; font-size: 11.5px; padding: 5px 4px; border: 1px solid #CBD5E1; border-radius: 4px;">
                                </td>

                                <!-- 5. Start Time -->
                                <td style="padding: 8px 8px; border-bottom: 1px solid #E2E8F0;">
                                    <input type="time" 
                                           name="tasks[{{ $task->id }}][start_time]" 
                                           value="{{ $task->starts_at->format('H:i') }}" 
                                           required 
                                           class="bulk-input bulk-field-track"
                                           data-field="start_time"
                                           style="width: 100%; font-size: 11.5px; padding: 5px 4px; border: 1px solid #CBD5E1; border-radius: 4px;">
                                </td>

                                <!-- 6. End Date -->
                                <td style="padding: 8px 8px; border-bottom: 1px solid #E2E8F0;">
                                    <input type="date" 
                                           name="tasks[{{ $task->id }}][end_date]" 
                                           value="{{ $task->ends_at->format('Y-m-d') }}" 
                                           required 
                                           class="bulk-input bulk-field-track"
                                           data-field="end_date"
                                           style="width: 100%; font-size: 11.5px; padding: 5px 4px; border: 1px solid #CBD5E1; border-radius: 4px;">
                                </td>

                                <!-- 7. End Time -->
                                <td style="padding: 8px 8px; border-bottom: 1px solid #E2E8F0;">
                                    <input type="time" 
                                           name="tasks[{{ $task->id }}][end_time]" 
                                           value="{{ $task->ends_at->format('H:i') }}" 
                                           required 
                                           class="bulk-input bulk-field-track"
                                           data-field="end_time"
                                           style="width: 100%; font-size: 11.5px; padding: 5px 4px; border: 1px solid #CBD5E1; border-radius: 4px;">
                                </td>

                                <!-- 8. Progress -->
                                <td style="padding: 8px 8px; border-bottom: 1px solid #E2E8F0;">
                                    <div style="display: flex; align-items: center; gap: 4px;">
                                        <input type="number" 
                                               name="tasks[{{ $task->id }}][progress]" 
                                               value="{{ $task->progress }}" 
                                               min="0" 
                                               max="100" 
                                               required 
                                               class="bulk-input bulk-progress-input bulk-field-track"
                                               data-task-id="{{ $task->id }}"
                                               data-field="progress"
                                               style="width: 52px; font-size: 12px; padding: 5px 4px; border: 1px solid #CBD5E1; border-radius: 4px; text-align: center;">
                                        <span class="muted" style="font-size: 11px;">%</span>
                                    </div>
                                </td>

                                <!-- 9. Status -->
                                <td style="padding: 8px 10px; border-bottom: 1px solid #E2E8F0;">
                                    <select name="tasks[{{ $task->id }}][status]" 
                                            class="bulk-select bulk-status-select bulk-field-track"
                                            data-task-id="{{ $task->id }}"
                                            data-field="status"
                                            style="width: 100%; font-size: 12px; padding: 5px 6px; border: 1px solid #CBD5E1; border-radius: 4px; background: #FFFFFF; font-weight: 500;">
                                        <option value="todo" @selected($task->status === 'todo')>To Do</option>
                                        <option value="in_progress" @selected($task->status === 'in_progress')>In Progress</option>
                                        <option value="on_hold" @selected($task->status === 'on_hold')>On Hold</option>
                                        <option value="completed" @selected($task->status === 'completed')>Completed</option>
                                    </select>
                                </td>

                                <!-- 10. Dependencies (Predecessors) -->
                                <td style="padding: 8px 12px; border-bottom: 1px solid #E2E8F0;">
                                    <div class="bulk-dep-cell" data-task-id="{{ $task->id }}">
                                        <div class="bulk-dep-chips" id="bulk-chips-{{ $task->id }}" style="display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 4px;">
                                            @foreach($task->dependencies as $dep)
                                                <span class="bulk-dep-chip" data-dep-id="{{ $dep->id }}" style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 6px; background: #F1F5F9; border: 1px solid #CBD5E1; border-radius: 3px; font-size: 11px; color: #334155;">
                                                    <span>{{ $numbers[$dep->id] ?? $dep->code }}</span>
                                                    <button type="button" class="bulk-remove-dep-btn" title="Remove dependency" style="border: none; background: none; cursor: pointer; color: #EF4444; font-weight: bold; font-size: 12px; line-height: 1; padding: 0 2px;">×</button>
                                                    <input type="hidden" name="tasks[{{ $task->id }}][dependencies][]" value="{{ $dep->id }}">
                                                </span>
                                            @endforeach
                                        </div>
                                        <select class="bulk-add-dep-select" 
                                                data-task-id="{{ $task->id }}"
                                                style="width: 100%; font-size: 11px; padding: 3px 6px; border: 1px dashed #94A3B8; border-radius: 3px; background: #FFFFFF; color: #475569;">
                                            <option value="">+ Add Predecessor...</option>
                                            @foreach($tasks->where('id', '!=', $task->id) as $cand)
                                                @if(!in_array($cand->id, $depIds, true))
                                                    <option value="{{ $cand->id }}" data-label="{{ $numbers[$cand->id] ?? $cand->code }}">
                                                        {{ $numbers[$cand->id] ?? $cand->code }} – {{ \Illuminate\Support\Str::limit($cand->title, 24) }}
                                                    </option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                </td>

                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Sticky Modal Footer -->
            <div class="modal-foot" style="padding: 12px 24px; border-top: 1px solid #E1E4E8; display: flex; justify-content: space-between; align-items: center; background: #FFFFFF; flex-shrink: 0;">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <button type="button" class="btn btn-ghost" data-close-dialog id="bulk-edit-cancel-btn">Cancel</button>
                    <span id="bulk-modified-counter" class="muted" style="font-size: 13px; font-weight: 500;">0 tasks modified</span>
                </div>
                <button type="submit" class="btn btn-primary" id="bulk-edit-save-btn" style="min-width: 150px; font-weight: 650;">
                    Save All Changes
                </button>
            </div>
        </form>
    </dialog>

    @if($canTeam)
        <!-- Quick Add Project Member Dialog (non-disruptive inline modal) -->
        <dialog id="quick-add-member-dialog" class="modal" style="max-width: 440px;">
            <div class="modal-head">
                <h2>Add Project Member</h2>
                <button type="button" class="icon-btn" data-close-dialog aria-label="Close">×</button>
            </div>
            <form id="quick-add-member-form" method="post" action="{{ route('projects.members.store', $project) }}" class="stack mt-16">
                @csrf
                <label>Person <span class="required">*</span>
                    <select name="user_id" id="quick-member-user-id" required>
                        <option value="">Select team member...</option>
                        @foreach($users as $u)
                            @if(!$project->memberships->pluck('user_id')->contains($u->id) && $project->pm_user_id !== $u->id)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endif
                        @endforeach
                    </select>
                </label>
                <label>Project Role <span class="required">*</span>
                    <select name="project_role_id" id="quick-member-role-id" required>
                        @foreach($roles->where('slug', '!=', 'pm') as $r)
                            <option value="{{ $r->id }}" @selected($r->slug === 'team')>{{ $r->name }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="form-actions mt-16">
                    <button type="button" class="btn btn-ghost" data-close-dialog>Cancel</button>
                    <button type="submit" class="btn btn-primary" id="quick-add-member-submit-btn">Add to Project</button>
                </div>
            </form>
        </dialog>
    @endif

    <!-- Import Task Dialog -->
    <dialog id="import-task-dialog" class="modal" style="max-width: 520px;">
        <div class="modal-head">
            <h2>Import Tasks from Excel</h2>
            <button type="button" class="icon-btn" data-close-dialog>×</button>
        </div>
        <p class="muted text-sm mt-8">Download an Excel template, fill in your tasks, and upload below for a review before saving.</p>
        
        <div class="inline-actions mt-12 mb-16">
            <a class="btn btn-outline btn-sm" href="{{ route('tasks.export', ['project' => $project, 'blank' => 1]) }}">Download Blank Template</a>
            <a class="btn btn-outline btn-sm" href="{{ route('tasks.export', $project) }}">Export Current Tasks</a>
        </div>

        <form method="post" action="{{ route('tasks.import.preview', $project) }}" enctype="multipart/form-data" class="stack">
            @csrf
            <label>Excel File (.xlsx, .csv) <span class="required">*</span>
                <input type="file" name="file" accept=".xlsx,.csv" required>
            </label>
            <div class="form-actions mt-16">
                <button type="button" class="btn btn-ghost" data-close-dialog>Cancel</button>
                <button class="btn btn-primary" type="submit">Preview Import</button>
            </div>
        </form>
    </dialog>
@endif

@if(app(\App\Services\Access::class)->project(auth()->user(), $project, 'edit_project') && $project->status !== 'archived')
    @include('projects._edit-dialog')
@endif

@if($project->status === 'pending' && $project->pm_user_id === auth()->id())
    <!-- PM Decline Assignment Dialog -->
    <dialog id="decline-project-dialog" class="modal" style="max-width: 520px;">
        <div class="modal-head">
            <h2 style="display: flex; align-items: center; gap: 8px; color: #B91C1C; margin: 0; font-size: 17px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
                Decline Project Assignment
            </h2>
            <button type="button" class="icon-btn" data-close-dialog aria-label="Close">×</button>
        </div>
        <form action="{{ route('projects.respond', $project) }}" method="post" class="stack mt-16">
            @csrf
            <input type="hidden" name="decision" value="decline">
            
            <p class="muted text-sm" style="line-height: 1.5; margin: 0 0 12px 0;">
                You are declining the Project Manager role for <strong>{{ $project->name }} ({{ $project->code }})</strong>.
                Please provide a specific reason so the PMO Admin can understand your constraints and reassign the project appropriately.
            </p>

            <label style="display: block; font-weight: 600; font-size: 13px;">
                Reason for Declining <span class="required" style="color: #DC2626;">*</span>
                <textarea name="decline_reason" id="decline-reason-input" rows="4" required minlength="5" maxlength="1000"
                    placeholder="e.g. Schedule conflicts with existing commitments, lack of technical capacity in this domain, currently at full project capacity..."
                    class="form-textarea" style="width: 100%; margin-top: 6px; padding: 10px 12px; border: 1px solid #D1D5DB; border-radius: 6px; font-family: inherit; font-size: 13px;"></textarea>
                <span class="text-xs muted" style="margin-top: 4px; display: block;">Minimum 5 characters. This reason will be recorded and shared directly with PMO administration.</span>
            </label>

            <div class="form-actions mt-16" style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-ghost" data-close-dialog>Cancel</button>
                <button type="submit" class="btn" style="background: #DC2626; color: #FFFFFF; font-weight: 600; border: none; padding: 8px 18px; border-radius: 6px; cursor: pointer;">
                    Confirm & Decline Assignment
                </button>
            </div>
        </form>
    </dialog>
@endif

@if(auth()->user()->isPmo() && in_array($project->status, ['draft', 'pending', 'declined']))
    <!-- Assign / Reassign PM Dialog -->
    <dialog id="assign-pm-dialog" class="modal" style="max-width: 520px;">
        <div class="modal-head">
            <h2 style="margin: 0; font-size: 17px;">
                @if($project->status === 'declined')
                    Reassign Project Manager
                @elseif($project->status === 'pending')
                    Reassign Project Manager
                @else
                    Assign Project Manager
                @endif
            </h2>
            <button type="button" class="icon-btn" data-close-dialog aria-label="Close">×</button>
        </div>
        <form method="post" action="{{ route('projects.manager', $project) }}" class="stack mt-16">
            @csrf
            
            @if($project->status === 'declined' && $declinedInvitation)
                <div style="background: #FEF2F2; border: 1px solid #FECACA; border-radius: 6px; padding: 10px 14px; font-size: 13px; color: #991B1B; margin-bottom: 12px;">
                    <div style="font-weight: 700;">Previous PM: {{ $declinedInvitation->user?->name ?? $project->manager?->name ?? 'Assigned PM' }} (Declined)</div>
                    @if($declinedInvitation->decline_reason)
                        <div style="margin-top: 4px; font-style: italic; color: #4B5563;">"{{ $declinedInvitation->decline_reason }}"</div>
                    @endif
                </div>
            @elseif($project->status === 'pending' && $project->manager)
                <div style="background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 6px; padding: 10px 14px; font-size: 13px; color: #92400E; margin-bottom: 12px;">
                    Currently assigned to <strong>{{ $project->manager->name }}</strong> (Awaiting response). Reassigning will cancel the existing pending invitation and send a request to the newly selected manager.
                </div>
            @else
                <p class="muted text-sm" style="margin-top: 0; margin-bottom: 12px;">The selected manager will receive an invitation to review, accept, and activate this project.</p>
            @endif

            <label style="display: block; font-weight: 600; font-size: 13px;">Project Manager <span class="required">*</span>
                <select name="pm_user_id" required style="width: 100%; margin-top: 6px; padding: 8px 10px; border: 1px solid #D1D5DB; border-radius: 6px;">
                    <option value="">Choose an active Project Manager...</option>
                    @foreach($pmCandidates ?? $users as $u)
                        <option value="{{ $u->id }}" @selected($u->id === $project->pm_user_id)>
                            {{ $u->name }} ({{ $u->email }}){{ $u->id === $project->pm_user_id && $project->status === 'declined' ? ' — Previously Declined' : '' }}
                        </option>
                    @endforeach
                </select>
            </label>
            <div class="form-actions mt-16" style="display: flex; justify-content: flex-end; gap: 10px;">
                <button class="btn btn-ghost" type="button" data-close-dialog>Cancel</button>
                <button class="btn btn-primary" type="submit" style="font-weight: 650;">
                    {{ in_array($project->status, ['declined', 'pending']) ? 'Reassign & Send Request' : 'Send Invitation' }}
                </button>
            </div>
        </form>
    </dialog>
@endif

@if($canTeam)
    <!-- Project Closure & Sign-off Dialog -->
    <dialog id="project-closure-dialog" class="modal" style="max-width: 540px;">
        <div class="modal-head">
            <h2>Project Closure & Sign-off</h2>
            <button type="button" class="icon-btn" data-close-dialog>×</button>
        </div>
        <p class="muted text-sm mt-8">Review the deliverables checklist and submit this project for formal sign-off and closure.</p>
        <form method="post" action="{{ route('projects.closure.submit', $project) }}" class="stack mt-16">
            @csrf
            <div class="closure-checklist stack" style="gap: 10px; background: var(--surface); padding: 14px; border-radius: 6px; border: 1px solid var(--border);">
                <div style="font-weight: 600; font-size: 13px;">Completion checklist</div>
                <label class="custom-checkbox-row" style="display: flex; align-items: center; gap: 8px; font-size: 13px;">
                    <input type="checkbox" name="tasks_completed" value="1" {{ $project->progress >= 100 ? 'checked' : '' }}>
                    <span>All project tasks are completed.</span>
                </label>
                <label class="custom-checkbox-row" style="display: flex; align-items: center; gap: 8px; font-size: 13px;">
                    <input type="checkbox" name="deliverables_submitted" value="1" checked>
                    <span>Deliverables are submitted.</span>
                </label>
                <label class="custom-checkbox-row" style="display: flex; align-items: center; gap: 8px; font-size: 13px;">
                    <input type="checkbox" name="approval_obtained" value="1">
                    <span>Client / Sponsor approval is obtained.</span>
                </label>
            </div>

            <label class="mt-16" style="display: block; font-weight: 600; font-size: 13px;">Closure Notes
                <textarea name="notes" rows="4" placeholder="Enter closure notes..." class="form-textarea" style="width: 100%; margin-top: 6px;"></textarea>
            </label>

            <div class="form-actions mt-16" style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-ghost" data-close-dialog>Cancel</button>
                <button type="submit" name="action" value="draft" class="btn btn-outline">Save Draft</button>
                <button type="submit" name="action" value="request" class="btn btn-primary">Request Closure</button>
            </div>
        </form>
    </dialog>
@endif

@push('scripts')
@if($canViewTasks && $tasks->isNotEmpty())
<script>
window.gsProjectTimeline = {{ \Illuminate\Support\Js::from($tasks->map(fn($t) => [
    'id' => $t->id,
    'title' => $t->title,
    'start' => $t->starts_at->toIso8601String(),
    'end' => $t->ends_at->toIso8601String(),
    'progress' => $t->progress,
    'critical' => $health && in_array($t->id, $health['critical_ids'] ?? [], true)
])->values()->all()) }};
</script>
@endif
<script defer src="{{ asset('js/project-workspace.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('reassign') === '1') {
        const dialog = document.getElementById('assign-pm-dialog');
        if (dialog && typeof dialog.showModal === 'function') {
            dialog.showModal();
        }
    }
});
</script>
@endpush

@endsection
