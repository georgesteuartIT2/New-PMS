/**
 * GS ProjectFlow - Calendar & Microsoft Outlook Meetings Workspace
 * Interactive Free/Busy Availability Checker & Meeting Management
 */
document.addEventListener('DOMContentLoaded', function () {
  const scheduleDialog = document.getElementById('schedule-meeting-dialog');
  const viewDialog = document.getElementById('view-meeting-dialog');
  const projectSelector = document.getElementById('modal-project-selector');
  const scheduleForm = document.getElementById('schedule-meeting-form');
  const attendeesContainer = document.getElementById('attendees-checkbox-container');
  const dateInput = document.getElementById('meeting-date-input');
  const startTimeInput = document.getElementById('meeting-start-time');
  const endTimeInput = document.getElementById('meeting-end-time');
  const checkAvailBtn = document.getElementById('btn-check-availability');
  const availLoading = document.getElementById('availability-loading-state');
  const suggestedSlotsContainer = document.getElementById('suggested-slots-container');
  const busyConflictsBox = document.getElementById('busy-conflicts-details');
  const selectAllBtn = document.getElementById('btn-select-all-attendees');
  const deselectBtn = document.getElementById('btn-deselect-attendees');

  // 1. Dynamic Project Selection (when on Global Calendar)
  if (projectSelector && window.PROJECT_MEMBERS_MAP) {
    projectSelector.addEventListener('change', function () {
      const projId = this.value;
      const selectedOpt = this.options[this.selectedIndex];
      if (selectedOpt && selectedOpt.dataset.storeUrl) {
        scheduleForm.action = selectedOpt.dataset.storeUrl;
      }

      if (!projId || !window.PROJECT_MEMBERS_MAP[projId]) {
        attendeesContainer.innerHTML = '<div class="muted" style="font-size: 12px; padding: 6px 0;">Select a project above to load team members.</div>';
        suggestedSlotsContainer.innerHTML = '<span class="muted" style="font-size: 11.5px;">Select attendees to check availability.</span>';
        return;
      }

      const members = window.PROJECT_MEMBERS_MAP[projId];
      if (!members || members.length === 0) {
        attendeesContainer.innerHTML = '<div class="muted" style="font-size: 12px; padding: 6px 0;">No members assigned to this project yet.</div>';
        return;
      }

      let html = '';
      members.forEach(mem => {
        html += `
          <label class="attendee-check-row" style="display: flex; align-items: center; justify-content: space-between; padding: 6px 10px; background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 5px; cursor: pointer;">
            <div style="display: flex; align-items: center; gap: 8px;">
              <input type="checkbox" name="attendee_ids[]" value="${mem.id}" data-email="${escapeHtml(mem.email.toLowerCase())}" data-name="${escapeHtml(mem.name)}" class="attendee-checkbox" checked>
              <span style="font-size: 12.5px; font-weight: 600; color: #1E293B;">${escapeHtml(mem.name)}</span>
              <small class="muted" style="font-size: 11px;">(${escapeHtml(mem.job_title || 'Member')})</small>
            </div>
            <span class="muted" style="font-size: 11.5px;">${escapeHtml(mem.email)}</span>
          </label>
        `;
      });
      attendeesContainer.innerHTML = html;

      // Automatically trigger availability check
      triggerAvailabilityCheck();
    });
  }

  // 2. Select All / Deselect All Attendees
  if (selectAllBtn) {
    selectAllBtn.addEventListener('click', function () {
      const boxes = document.querySelectorAll('.attendee-checkbox');
      boxes.forEach(b => { b.checked = true; });
      triggerAvailabilityCheck();
    });
  }

  if (deselectBtn) {
    deselectBtn.addEventListener('click', function () {
      const boxes = document.querySelectorAll('.attendee-checkbox');
      boxes.forEach(b => { b.checked = false; });
      suggestedSlotsContainer.innerHTML = '<span class="muted" style="font-size: 11.5px;">Please select at least one attendee.</span>';
      busyConflictsBox.style.display = 'none';
    });
  }

  // 3. Attendee checkbox change & Date change triggers availability update
  if (attendeesContainer) {
    attendeesContainer.addEventListener('change', function (e) {
      if (e.target.classList.contains('attendee-checkbox')) {
        triggerAvailabilityCheck();
      }
    });
  }

  if (dateInput) {
    dateInput.addEventListener('change', function () {
      triggerAvailabilityCheck();
    });
  }

  if (checkAvailBtn) {
    checkAvailBtn.addEventListener('click', function () {
      triggerAvailabilityCheck();
    });
  }

  // Function to query availability from Microsoft Graph / local schedule
  async function triggerAvailabilityCheck() {
    const selectedBoxes = Array.from(document.querySelectorAll('.attendee-checkbox:checked'));
    const emails = selectedBoxes.map(b => b.dataset.email).filter(Boolean);
    const date = dateInput ? dateInput.value : '';

    if (!date || emails.length === 0) {
      if (suggestedSlotsContainer) {
        suggestedSlotsContainer.innerHTML = '<span class="muted" style="font-size: 11.5px;">Select attendees and a date to check availability.</span>';
      }
      if (busyConflictsBox) busyConflictsBox.style.display = 'none';
      return;
    }

    if (availLoading) availLoading.style.display = 'block';
    if (suggestedSlotsContainer) suggestedSlotsContainer.innerHTML = '';
    if (busyConflictsBox) busyConflictsBox.style.display = 'none';

    try {
      const csrf = document.querySelector('input[name="_token"]')?.value || '';
      const res = await fetch('/meetings/check-availability', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
          date: date,
          attendee_emails: emails
        })
      });

      if (!res.ok) throw new Error('Availability query returned status ' + res.status);
      const data = await res.json();

      // Render Suggested Free Slots
      if (data.suggested_slots && data.suggested_slots.length > 0) {
        let slotsHtml = '';
        data.suggested_slots.forEach(slot => {
          slotsHtml += `
            <button type="button" class="avail-slot-pill" data-start="${slot.start_time}" data-end="${slot.end_time}" title="Click to schedule this free slot">
              <span style="display:inline-block; width:7px; height:7px; border-radius:50%; background:#059669;"></span>
              ${escapeHtml(slot.label)}
            </button>
          `;
        });
        suggestedSlotsContainer.innerHTML = slotsHtml;

        // Slot click handler: 1 single click to fill times!
        suggestedSlotsContainer.querySelectorAll('.avail-slot-pill').forEach(btn => {
          btn.addEventListener('click', function () {
            if (startTimeInput) startTimeInput.value = this.dataset.start;
            if (endTimeInput) endTimeInput.value = this.dataset.end;
            suggestedSlotsContainer.querySelectorAll('.avail-slot-pill').forEach(b => {
              b.style.background = '#ECFDF5';
              b.style.color = '#065F46';
            });
            this.style.background = '#315F58';
            this.style.color = '#FFFFFF';
          });
        });
      } else {
        suggestedSlotsContainer.innerHTML = '<span class="muted" style="font-size: 12px; color: #DC2626;">No conflict-free 1-hour slots found in working hours. You can still set custom times below.</span>';
      }

      // Render busy details / conflicts if any
      let conflictsHtml = '';
      let hasBusy = false;
      for (const [em, sched] of Object.entries(data.schedules || {})) {
        if (sched.busy_slots && sched.busy_slots.length > 0) {
          hasBusy = true;
          const slotsText = sched.busy_slots.map(s => {
            const st = s.start.substring(11, 16);
            const et = s.end.substring(11, 16);
            return `${st}-${et} (${s.subject || 'Busy in Outlook'})`;
          }).join(', ');
          conflictsHtml += `<div><strong>${escapeHtml(em)}:</strong> Busy during ${escapeHtml(slotsText)}</div>`;
        }
      }

      if (hasBusy && busyConflictsBox) {
        busyConflictsBox.innerHTML = `<strong>Existing Calendar Bookings:</strong><div style="margin-top:4px;">${conflictsHtml}</div>`;
        busyConflictsBox.style.display = 'block';
      }
    } catch (err) {
      console.warn('Could not inspect Outlook availability:', err);
      if (suggestedSlotsContainer) {
        suggestedSlotsContainer.innerHTML = '<span class="muted" style="font-size: 11.5px;">Schedule times can be set directly below.</span>';
      }
    } finally {
      if (availLoading) availLoading.style.display = 'none';
    }
  }

  // 4. View Meeting Dialog Trigger
  document.addEventListener('click', function (e) {
    const meetingBtn = e.target.closest('[data-meeting-info]');
    if (!meetingBtn) return;

    try {
      const m = JSON.parse(meetingBtn.dataset.meetingInfo);
      const titleEl = document.getElementById('view-meeting-title');
      const projectEl = document.getElementById('view-meeting-project');
      const datetimeEl = document.getElementById('view-meeting-datetime');
      const locationEl = document.getElementById('view-meeting-location');
      const organizerEl = document.getElementById('view-meeting-organizer');
      const descEl = document.getElementById('view-meeting-description');
      const descWrap = document.getElementById('view-meeting-description-wrap');
      const attendeesList = document.getElementById('view-meeting-attendees-list');
      const syncBox = document.getElementById('view-meeting-sync-box');
      const syncText = document.getElementById('view-meeting-sync-text');
      const outlookLink = document.getElementById('view-meeting-outlook-link');
      const icsLink = document.getElementById('view-meeting-ics-link');
      const deleteForm = document.getElementById('delete-meeting-form');

      if (titleEl) titleEl.textContent = m.title;
      if (projectEl) projectEl.textContent = m.project_name || 'Project';
      if (datetimeEl) datetimeEl.textContent = m.formatted_datetime || `${m.starts_at} - ${m.ends_at}`;
      if (locationEl) locationEl.textContent = m.location || 'Microsoft Teams Meeting';
      if (organizerEl) organizerEl.textContent = m.organizer_name || 'Project Manager';

      if (descEl && descWrap) {
        if (m.description) {
          descEl.textContent = m.description;
          descWrap.style.display = 'block';
        } else {
          descWrap.style.display = 'none';
        }
      }

      if (attendeesList) {
        let attHtml = '';
        (m.attendees || []).forEach(att => {
          attHtml += `<span class="pill pill-neutral" style="font-size: 11.5px;">${escapeHtml(att.name)} (${escapeHtml(att.email)})</span>`;
        });
        attendeesList.innerHTML = attHtml || '<span class="muted">No attendees listed</span>';
      }

      if (syncText) {
        if (m.ms_sync_status === 'synced') {
          syncText.textContent = 'Synced with Microsoft Outlook Calendar';
          syncBox.style.background = '#F0FDF4';
          syncBox.style.borderColor = '#BBF7D0';
        } else {
          syncText.textContent = 'Scheduled in ProjectFlow (Ready for Outlook)';
          syncBox.style.background = '#F8FAFC';
          syncBox.style.borderColor = '#E2E8F0';
        }
      }

      if (outlookLink) {
        outlookLink.href = m.outlook_web_link || '#';
      }

      if (icsLink) {
        icsLink.href = `/meetings/${m.id}/ics`;
      }

      if (deleteForm) {
        if (m.can_manage) {
          deleteForm.action = `/meetings/${m.id}`;
          deleteForm.style.display = 'block';
        } else {
          deleteForm.style.display = 'none';
        }
      }

      if (viewDialog && typeof viewDialog.showModal === 'function') {
        viewDialog.showModal();
      }
    } catch (err) {
      console.error('Error opening meeting modal:', err);
    }
  });

  // 5. Open Schedule Meeting Dialog Trigger with prefilled date
  document.addEventListener('click', function (e) {
    const trigger = e.target.closest('[data-schedule-meeting]');
    if (!trigger) return;

    const prefillDate = trigger.dataset.prefillDate;
    if (prefillDate && dateInput) {
      dateInput.value = prefillDate;
    }

    if (scheduleDialog && typeof scheduleDialog.showModal === 'function') {
      scheduleDialog.showModal();
      triggerAvailabilityCheck();
    }
  });

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
});

