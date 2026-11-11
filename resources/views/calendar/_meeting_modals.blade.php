<!-- =========================================================================
     MODAL 1: SCHEDULE MEETING DIALOG (Interactive Microsoft Outlook Free/Busy)
     ========================================================================= -->
<dialog id="schedule-meeting-dialog" class="modal" style="width: 95vw; max-width: 680px; padding: 0; border: none; border-radius: 8px; box-shadow: 0 20px 45px rgba(0,0,0,0.22); overflow: hidden;">
    <div style="background: #FFFFFF; display: flex; flex-direction: column; max-height: 90vh;">
        <!-- Header -->
        <div style="padding: 16px 20px; border-bottom: 1px solid #E2E8F0; display: flex; align-items: center; justify-content: space-between; background: #F8FAFC;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 6px; background: #EEF2FF; color: #4F46E5;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                </span>
                <div>
                    <h2 style="font-size: 16px; font-weight: 700; color: #1E293B; margin: 0;">Schedule Project Meeting</h2>
                    <p class="muted" style="font-size: 12px; margin: 2px 0 0;">Inspect Outlook availability and sync directly to Microsoft Calendar.</p>
                </div>
            </div>
            <button type="button" class="icon-btn" data-close-dialog aria-label="Close" style="background: none; border: none; font-size: 22px; cursor: pointer; color: #64748B;">×</button>
        </div>

        <!-- Form Body -->
        <form method="post" id="schedule-meeting-form" action="{{ isset($project) ? route('meetings.store', $project) : '' }}" style="padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 16px;">
            @csrf

            <!-- Project Selector (Shown on Global Calendar, Hidden if already on a Project Calendar) -->
            @if(!isset($project))
                <label style="display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600; color: #334155;">
                    Target Project <span style="color: #DC2626;">*</span>
                    <select name="target_project_id" id="modal-project-selector" required style="height: 38px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 10px; font-size: 13px; background: #FFFFFF;">
                        <option value="">Select project...</option>
                        @foreach($schedulableProjects as $sp)
                            <option value="{{ $sp->id }}" data-store-url="{{ route('meetings.store', $sp) }}" @selected(($selectedProjectId ?? null) == $sp->id)>
                                {{ $sp->name }} ({{ $sp->code }})
                            </option>
                        @endforeach
                    </select>
                </label>
            @endif

            <!-- Meeting Title -->
            <label style="display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600; color: #334155;">
                Meeting Subject / Title <span style="color: #DC2626;">*</span>
                <input type="text" name="title" id="meeting-title-input" required maxlength="200" placeholder="e.g. Scope Alignment & Weekly Progress Review" style="height: 38px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 12px; font-size: 13.5px;">
            </label>

            <!-- Meeting Date -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <label style="display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600; color: #334155;">
                    Meeting Date <span style="color: #DC2626;">*</span>
                    <input type="date" name="meeting_date" id="meeting-date-input" value="{{ now()->format('Y-m-d') }}" required style="height: 38px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 10px; font-size: 13px;">
                </label>
                <label style="display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600; color: #334155;">
                    Location / Platform
                    <input type="text" name="location" value="Microsoft Teams Meeting" placeholder="e.g. Teams, Boardroom 3" style="height: 38px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 10px; font-size: 13px;">
                </label>
            </div>

            <!-- Attendees Selection Box -->
            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; padding: 12px 14px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                    <span style="font-size: 13px; font-weight: 700; color: #334155;">Required Attendees (Project Team) <span style="color: #DC2626;">*</span></span>
                    <div style="display: flex; gap: 6px;">
                        <button type="button" class="btn btn-outline btn-sm" id="btn-select-all-attendees" style="font-size: 11px; padding: 2px 8px; height: 26px;">Select All</button>
                        <button type="button" class="btn btn-outline btn-sm" id="btn-deselect-attendees" style="font-size: 11px; padding: 2px 8px; height: 26px;">Clear</button>
                    </div>
                </div>

                <!-- Attendee Checkbox List -->
                <div id="attendees-checkbox-container" style="max-height: 140px; overflow-y: auto; display: flex; flex-direction: column; gap: 6px;">
                    @php
                        $membersToList = isset($project) ? ($projectMembers ?? collect()) : collect();
                    @endphp
                    @forelse($membersToList as $mem)
                        <label class="attendee-check-row" style="display: flex; align-items: center; justify-content: space-between; padding: 6px 10px; background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 5px; cursor: pointer;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" name="attendee_ids[]" value="{{ $mem->id }}" data-email="{{ strtolower($mem->email) }}" data-name="{{ $mem->name }}" class="attendee-checkbox" checked>
                                <span style="font-size: 12.5px; font-weight: 600; color: #1E293B;">{{ $mem->name }}</span>
                                <small class="muted" style="font-size: 11px;">({{ $mem->job_title ?: 'Member' }})</small>
                            </div>
                            <span class="muted" style="font-size: 11.5px;">{{ $mem->email }}</span>
                        </label>
                    @empty
                        <div id="no-attendees-notice" class="muted" style="font-size: 12px; padding: 6px 0;">
                            Select a project above to load team members.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Microsoft Outlook Live Free/Busy Availability Inspector -->
            <div style="background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 6px; padding: 12px 14px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 14 14"></polyline>
                        </svg>
                        <span style="font-size: 12.5px; font-weight: 700; color: #065F46;">Microsoft Outlook Calendar Availability</span>
                    </div>
                    <button type="button" id="btn-check-availability" class="btn btn-outline btn-sm" style="font-size: 11.5px; padding: 3px 10px; height: 28px; background: #FFFFFF; border-color: #059669; color: #059669; font-weight: 600;">
                        ⟳ Check Availability
                    </button>
                </div>
                <p class="muted" style="font-size: 11.5px; margin: 0 0 10px 0; color: #047857;">
                    Click any suggested conflict-free slot below to automatically set meeting start & end time in one click.
                </p>

                <!-- Status / Loading Indicator -->
                <div id="availability-loading-state" style="display: none; font-size: 12px; color: #047857; margin-bottom: 8px;">
                    <span class="spinner" style="display: inline-block; width: 12px; height: 12px; border: 2px solid #059669; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite; vertical-align: middle; margin-right: 4px;"></span>
                    Querying Microsoft Graph Calendar schedule...
                </div>

                <!-- Suggested Slots Container -->
                <div id="suggested-slots-container" style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 8px;">
                    <span class="muted" style="font-size: 11.5px;">Click "Check Availability" or pick a slot below:</span>
                </div>

                <!-- Busy Details Box (Collapsed by default unless conflicts exist) -->
                <div id="busy-conflicts-details" style="display: none; background: #FFFFFF; border: 1px solid #FECACA; border-radius: 5px; padding: 8px 12px; font-size: 11.5px; color: #991B1B;">
                </div>
            </div>

            <!-- Start Time & End Time -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <label style="display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600; color: #334155;">
                    Start Time <span style="color: #DC2626;">*</span>
                    <input type="time" name="start_time" id="meeting-start-time" value="09:00" required style="height: 38px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 10px; font-size: 13px;">
                </label>
                <label style="display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600; color: #334155;">
                    End Time <span style="color: #DC2626;">*</span>
                    <input type="time" name="end_time" id="meeting-end-time" value="10:00" required style="height: 38px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 10px; font-size: 13px;">
                </label>
            </div>

            <!-- Meeting Agenda / Description -->
            <label style="display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600; color: #334155;">
                Description & Agenda
                <textarea name="description" rows="3" placeholder="Add meeting agenda, discussion topics, or preparation notes..." style="border: 1px solid #CBD5E1; border-radius: 6px; padding: 8px 10px; font-size: 13px; resize: vertical;"></textarea>
            </label>

            <!-- Outlook Sync Banner -->
            <div style="background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: 6px; padding: 10px 12px; display: flex; align-items: center; gap: 8px; font-size: 12px; color: #1E40AF;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="#2563EB" style="flex-shrink: 0;">
                    <path d="M21.5 12a9.5 9.5 0 1 1-19 0 9.5 9.5 0 0 1 19 0z"/>
                    <path fill="#FFFFFF" d="M10 8l6 4-6 4V8z"/>
                </svg>
                <span>This meeting will automatically sync to attendees' <strong>Microsoft Outlook Calendar</strong> and send calendar invitations. (Tasks will remain in GS ProjectFlow only).</span>
            </div>

            <!-- Modal Action Buttons -->
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 6px; padding-top: 14px; border-top: 1px solid #E2E8F0;">
                <button type="button" class="btn btn-outline" data-close-dialog style="font-weight: 600;">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btn-save-meeting" style="background: #315F58; border-color: #315F58; font-weight: 600;">
                    Schedule & Sync to Outlook →
                </button>
            </div>
        </form>
    </div>
</dialog>

<!-- =========================================================================
     MODAL 2: VIEW MEETING DETAILS DIALOG
     ========================================================================= -->
<dialog id="view-meeting-dialog" class="modal" style="width: 95vw; max-width: 580px; padding: 0; border: none; border-radius: 8px; box-shadow: 0 20px 45px rgba(0,0,0,0.22); overflow: hidden;">
    <div style="background: #FFFFFF; display: flex; flex-direction: column;">
        <!-- Header -->
        <div style="padding: 16px 20px; border-bottom: 1px solid #E2E8F0; display: flex; align-items: center; justify-content: space-between; background: #F8FAFC;">
            <div>
                <span class="pill pill-primary" style="background: #EEF2FF; color: #4F46E5; font-size: 10.5px; font-weight: 700; margin-bottom: 4px;">PROJECT MEETING</span>
                <h2 id="view-meeting-title" style="font-size: 17px; font-weight: 700; color: #1E293B; margin: 2px 0 0;">Meeting Details</h2>
            </div>
            <button type="button" class="icon-btn" data-close-dialog aria-label="Close" style="background: none; border: none; font-size: 22px; cursor: pointer; color: #64748B;">×</button>
        </div>

        <div style="padding: 20px; display: flex; flex-direction: column; gap: 14px;">
            <!-- Meta Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; padding: 12px 14px; font-size: 12.5px;">
                <div>
                    <span class="muted" style="font-size: 11px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.3px; display: block; margin-bottom: 2px;">Project</span>
                    <strong id="view-meeting-project" style="color: #1E293B;">—</strong>
                </div>
                <div>
                    <span class="muted" style="font-size: 11px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.3px; display: block; margin-bottom: 2px;">Date & Time</span>
                    <strong id="view-meeting-datetime" style="color: #1E293B;">—</strong>
                </div>
                <div>
                    <span class="muted" style="font-size: 11px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.3px; display: block; margin-bottom: 2px;">Location / Platform</span>
                    <strong id="view-meeting-location" style="color: #1E293B;">Teams Meeting</strong>
                </div>
                <div>
                    <span class="muted" style="font-size: 11px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.3px; display: block; margin-bottom: 2px;">Organized By</span>
                    <strong id="view-meeting-organizer" style="color: #1E293B;">—</strong>
                </div>
            </div>

            <!-- Microsoft Outlook Sync Badge & Link -->
            <div id="view-meeting-sync-box" style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; border-radius: 6px; background: #F0FDF4; border: 1px solid #BBF7D0; font-size: 12px;">
                <div style="display: flex; align-items: center; gap: 6px; color: #065F46; font-weight: 600;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span id="view-meeting-sync-text">Synced with Microsoft Outlook</span>
                </div>
                <div style="display: flex; gap: 8px;">
                    <a id="view-meeting-outlook-link" href="#" target="_blank" class="btn btn-outline btn-sm" style="font-size: 11px; padding: 2px 8px; height: 26px; border-color: #059669; color: #059669;">
                        Open Outlook
                    </a>
                    <a id="view-meeting-ics-link" href="#" class="btn btn-outline btn-sm" style="font-size: 11px; padding: 2px 8px; height: 26px;">
                        .ICS
                    </a>
                </div>
            </div>

            <!-- Attendees List -->
            <div>
                <span class="muted" style="font-size: 11.5px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.3px; display: block; margin-bottom: 6px;">Attendees</span>
                <div id="view-meeting-attendees-list" style="display: flex; flex-wrap: wrap; gap: 6px;">
                </div>
            </div>

            <!-- Description -->
            <div id="view-meeting-description-wrap" style="display: none;">
                <span class="muted" style="font-size: 11.5px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.3px; display: block; margin-bottom: 4px;">Agenda & Notes</span>
                <p id="view-meeting-description" style="margin: 0; font-size: 13px; color: #334155; line-height: 1.5; background: #F8FAFC; padding: 10px 12px; border-radius: 6px; border: 1px solid #E2E8F0;"></p>
            </div>

            <!-- Meeting Action Buttons -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px; padding-top: 14px; border-top: 1px solid #E2E8F0;">
                <div id="view-meeting-manage-actions" style="display: flex; gap: 8px;">
                    <form id="delete-meeting-form" method="post" action="" onsubmit="return AppDialog.confirmAction(event,this,'Cancel and delete this meeting? It will also be removed from Microsoft Outlook Calendar.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger-outline btn-sm" style="font-weight: 600; color: #DC2626; border-color: #FCA5A5;">
                            Delete Meeting
                        </button>
                    </form>
                </div>
                <button type="button" class="btn btn-outline" data-close-dialog style="font-weight: 600;">Close</button>
            </div>
        </div>
    </div>
</dialog>

