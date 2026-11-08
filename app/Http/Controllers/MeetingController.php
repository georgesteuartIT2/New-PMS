<?php

namespace App\Http\Controllers;

use App\Models\{Meeting, MeetingAttendee, Project, User};
use App\Services\{Access, Audit, AzureGraphService};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MeetingController
{
    /**
     * Check availability of selected attendees on a specific date.
     * Queries Microsoft Graph Outlook calendar free/busy API with local fallback.
     */
    public function checkAvailability(Request $request, AzureGraphService $graphService)
    {
        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'attendee_emails' => 'required|array|min:1',
            'attendee_emails.*' => 'required|email',
            'project_id' => 'nullable|exists:projects,id',
        ]);

        $date = $validated['date'];
        $emails = array_map('strtolower', array_unique($validated['attendee_emails']));

        // Get schedules from Microsoft Graph and local DB
        $schedules = $graphService->getSchedule($emails, $date);

        // Compute suggested free slots (e.g. 08:30 - 17:00, 30 & 60 min intervals)
        $startWork = Carbon::parse("{$date} 08:30:00");
        $endWork = Carbon::parse("{$date} 17:00:00");

        // Collect all busy intervals across all selected attendees
        $allBusyRanges = [];
        foreach ($schedules as $email => $data) {
            foreach ($data['busy_slots'] ?? [] as $slot) {
                if (!empty($slot['start']) && !empty($slot['end'])) {
                    $slotStart = Carbon::parse($slot['start']);
                    $slotEnd = Carbon::parse($slot['end']);
                    $allBusyRanges[] = [
                        'start' => $slotStart,
                        'end' => $slotEnd,
                        'email' => $email,
                        'subject' => $slot['subject'] ?? 'Busy',
                    ];
                }
            }
        }

        // Generate 60-minute candidate slots throughout the work day
        $suggestedSlots = [];
        $slotCursor = $startWork->copy();
        while ($slotCursor->copy()->addMinutes(60)->lte($endWork)) {
            $candidateStart = $slotCursor->copy();
            $candidateEnd = $slotCursor->copy()->addMinutes(60);

            // Check if ANY attendee is busy during this candidate slot
            $conflict = false;
            $conflictingUsers = [];
            foreach ($allBusyRanges as $range) {
                if ($candidateStart->lt($range['end']) && $candidateEnd->gt($range['start'])) {
                    $conflict = true;
                    $conflictingUsers[] = $range['email'];
                }
            }

            if (!$conflict) {
                $suggestedSlots[] = [
                    'start_time' => $candidateStart->format('H:i'),
                    'end_time' => $candidateEnd->format('H:i'),
                    'label' => $candidateStart->format('h:i A') . ' - ' . $candidateEnd->format('h:i A'),
                    'available' => true,
                ];
            }

            $slotCursor->addMinutes(30);
        }

        return response()->json([
            'success' => true,
            'date' => $date,
            'attendee_count' => count($emails),
            'schedules' => $schedules,
            'suggested_slots' => array_slice($suggestedSlots, 0, 8),
            'has_conflicts' => !empty($allBusyRanges),
        ]);
    }

    /**
     * Store a newly scheduled meeting for a project and sync to Microsoft Outlook.
     */
    public function store(Request $request, Project $project, AzureGraphService $graphService, Access $access)
    {
        $user = $request->user();
        abort_unless(
            $user->isPmo() || $project->pm_user_id === $user->id || $access->canManageTask($user, $project),
            403,
            'Only PMO Administrators or the Project Manager can schedule project meetings.'
        );

        $data = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'location' => 'nullable|string|max:200',
            'meeting_date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'attendee_ids' => 'required|array|min:1',
            'attendee_ids.*' => 'exists:users,id',
        ]);

        $startsAt = Carbon::parse("{$data['meeting_date']} {$data['start_time']}:00");
        $endsAt = Carbon::parse("{$data['meeting_date']} {$data['end_time']}:00");

        $meeting = Meeting::create([
            'project_id' => $project->id,
            'created_by' => $user->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'location' => $data['location'] ?: 'Microsoft Teams Meeting',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'scheduled',
            'ms_sync_status' => 'pending',
        ]);

        // Attach attendees
        $attendeeUsers = User::whereIn('id', $data['attendee_ids'])->get();
        foreach ($attendeeUsers as $att) {
            MeetingAttendee::create([
                'meeting_id' => $meeting->id,
                'user_id' => $att->id,
                'email' => strtolower($att->email),
                'name' => $att->name,
                'response_status' => $att->id === $user->id ? 'accepted' : 'needsAction',
            ]);
        }

        // Always ensure organizer is included in attendee list if not already
        if (!$attendeeUsers->contains('id', $user->id)) {
            MeetingAttendee::firstOrCreate(
                ['meeting_id' => $meeting->id, 'email' => strtolower($user->email)],
                ['user_id' => $user->id, 'name' => $user->name, 'response_status' => 'accepted']
            );
        }

        // Reload relationships for sync
        $meeting->load(['attendeeRecords', 'creator', 'project']);

        // Sync with Microsoft Outlook Calendar via Microsoft Graph
        $syncResult = $graphService->createEvent($meeting);

        if ($syncResult['success'] ?? false) {
            $meeting->update([
                'ms_event_id' => $syncResult['ms_event_id'] ?? null,
                'ms_synced_at' => now(),
                'ms_sync_status' => 'synced',
                'meeting_link' => $syncResult['meeting_link'] ?? $meeting->meeting_link,
            ]);
        } else {
            $meeting->update([
                'ms_sync_status' => $syncResult['status'] ?? 'local_only',
                'ms_sync_error' => $syncResult['error'] ?? null,
            ]);
        }

        Audit::record('meeting.created', $project, [
            'meeting_id' => $meeting->id,
            'title' => $meeting->title,
            'starts_at' => $startsAt->toIso8601String(),
            'ms_synced' => $meeting->ms_sync_status === 'synced',
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'meeting' => $meeting->fresh(),
                'message' => 'Meeting scheduled successfully' . ($meeting->ms_sync_status === 'synced' ? ' and synced to Outlook Calendar.' : '.'),
            ]);
        }

        return redirect()->back()->with('success', 'Meeting scheduled successfully' . ($meeting->ms_sync_status === 'synced' ? ' and synced to Microsoft Outlook Calendar.' : '.'));
    }

    /**
     * Update an existing meeting.
     */
    public function update(Request $request, Meeting $meeting, AzureGraphService $graphService, Access $access)
    {
        $user = $request->user();
        abort_unless(
            $user->isPmo() || $meeting->project->pm_user_id === $user->id || $meeting->created_by === $user->id || $access->canManageTask($user, $meeting->project),
            403,
            'Unauthorized to edit this meeting.'
        );

        $data = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'location' => 'nullable|string|max:200',
            'meeting_date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'status' => 'required|in:scheduled,completed,cancelled',
            'attendee_ids' => 'nullable|array',
            'attendee_ids.*' => 'exists:users,id',
        ]);

        $startsAt = Carbon::parse("{$data['meeting_date']} {$data['start_time']}:00");
        $endsAt = Carbon::parse("{$data['meeting_date']} {$data['end_time']}:00");

        $meeting->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'location' => $data['location'] ?? $meeting->location,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => $data['status'],
        ]);

        // If attendees passed, synchronize attendee records
        if (isset($data['attendee_ids'])) {
            $newAttendees = User::whereIn('id', $data['attendee_ids'])->get();
            $meeting->attendees()->sync($newAttendees->mapWithKeys(fn ($u) => [
                $u->id => ['email' => strtolower($u->email), 'name' => $u->name, 'response_status' => 'needsAction']
            ]));
        }

        $meeting->load(['attendeeRecords', 'creator', 'project']);

        // Update in Microsoft Outlook if already synced
        if ($meeting->ms_event_id) {
            $graphService->updateEvent($meeting);
        }

        Audit::record('meeting.updated', $meeting->project, ['meeting_id' => $meeting->id]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'meeting' => $meeting->fresh(),
                'message' => 'Meeting updated successfully.',
            ]);
        }

        return redirect()->back()->with('success', 'Meeting updated successfully.');
    }

    /**
     * Delete / Cancel a scheduled meeting and remove from Microsoft Outlook.
     */
    public function destroy(Request $request, Meeting $meeting, AzureGraphService $graphService, Access $access)
    {
        $user = $request->user();
        abort_unless(
            $user->isPmo() || $meeting->project->pm_user_id === $user->id || $meeting->created_by === $user->id || $access->canManageTask($user, $meeting->project),
            403,
            'Unauthorized to delete this meeting.'
        );

        $project = $meeting->project;

        // Delete from Microsoft Outlook if event id exists
        if ($meeting->ms_event_id) {
            $graphService->deleteEvent($meeting);
        }

        Audit::record('meeting.deleted', $project, ['title' => $meeting->title]);
        $meeting->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Meeting deleted successfully.']);
        }

        return redirect()->back()->with('success', 'Meeting deleted successfully.');
    }

    /**
     * Download meeting as standard iCalendar (.ics) format.
     */
    public function ics(Meeting $meeting)
    {
        $start = $meeting->starts_at->utc()->format('Ymd\THis\Z');
        $end = $meeting->ends_at->utc()->format('Ymd\THis\Z');
        $now = now()->utc()->format('Ymd\THis\Z');
        $uid = 'projectflow-meeting-' . $meeting->id . '@georgesteuart.lk';
        $summary = str_replace(["\r", "\n"], ' ', $meeting->title);
        $desc = str_replace(["\r\n", "\n"], "\\n", ($meeting->description ?: '') . "\\nProject: " . ($meeting->project?->name ?? ''));
        $loc = str_replace(["\r", "\n"], ' ', $meeting->location ?: 'Online Meeting');

        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//George Steuart//GS ProjectFlow//EN\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:REQUEST\r\n";
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:{$uid}\r\n";
        $ics .= "DTSTAMP:{$now}\r\n";
        $ics .= "DTSTART:{$start}\r\n";
        $ics .= "DTEND:{$end}\r\n";
        $ics .= "SUMMARY:{$summary}\r\n";
        $ics .= "DESCRIPTION:{$desc}\r\n";
        $ics .= "LOCATION:{$loc}\r\n";
        $ics .= "STATUS:CONFIRMED\r\n";
        $ics .= "ORGANIZER;CN=\"" . addslashes($meeting->creator?->name ?? 'Organizer') . "\":mailto:" . ($meeting->creator?->email ?? 'noreply@georgesteuart.lk') . "\r\n";

        foreach ($meeting->attendeeRecords as $att) {
            $ics .= "ATTENDEE;ROLE=REQ-PARTICIPANT;CN=\"" . addslashes($att->name) . "\":mailto:{$att->email}\r\n";
        }

        $ics .= "END:VEVENT\r\n";
        $ics .= "END:VCALENDAR\r\n";

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="meeting-' . $meeting->id . '.ics"',
        ]);
    }
}
