<!-- Smart Task Plan Modal Component -->
<div id="smart-task-plan-backdrop" class="smart-task-plan-backdrop" role="dialog" aria-modal="true" aria-labelledby="stp-modal-title">
    <div id="smart-task-plan-modal" class="smart-task-plan-dialog">
        <!-- Sticky Header -->
        <div class="stp-header">
            <div class="stp-title-group">
                <h2>
                    <span id="stp-modal-title">Task Plan</span>
                    <span id="stp-mode-badge" class="stp-badge stp-badge-view">View Mode</span>
                </h2>
                <div style="display: flex; align-items: center; gap: 10px; margin-top: 3px; flex-wrap: wrap;">
                    <span id="stp-template-title" style="font-weight: 650; font-size: 13.5px; color: #22272B;"></span>
                    <span style="color: #CBD5E1;">•</span>
                    <span id="stp-schedule-badge" class="stp-subtitle" style="font-size: 12px; font-weight: 550; color: #475569;"></span>
                </div>
            </div>

            <div class="stp-header-actions">
                <!-- View Mode Actions -->
                <a id="stp-btn-use" href="#" class="btn btn-primary btn-sm" style="background: #315F58; color: #FFFFFF; font-weight: 650; height: 32px; font-size: 12px; display: none;">
                    Use Template
                </a>
                <button type="button" id="stp-btn-edit-mode" class="btn btn-outline btn-sm" style="height: 32px; font-size: 12px; display: none;">
                    Edit Template
                </button>
                <a id="stp-btn-export" href="#" class="btn btn-outline btn-sm" style="height: 32px; font-size: 12px; display: none;">
                    Export Excel
                </a>

                <!-- Edit Mode Actions -->
                <button type="button" id="stp-btn-toggle-details" class="btn btn-outline btn-sm" style="height: 32px; font-size: 12px; display: none;">
                    Edit Details ▾
                </button>

                <!-- Close Button -->
                <button type="button" id="stp-btn-close" class="stp-close-btn" title="Close" aria-label="Close dialog">✕</button>
            </div>
        </div>

        <!-- Optional Collapsible Details Drawer (in Edit Mode) -->
        <div id="stp-details-drawer" class="stp-details-drawer" style="display: none;"></div>

        <!-- Edit Mode Toolbar -->
        <div id="stp-toolbar" class="stp-toolbar" style="display: none;"></div>

        <!-- Hidden Excel File Input -->
        <input type="file" id="stp-excel-file-input" style="display: none;" accept=".xlsx,.csv">

        <!-- Live Summary & Notification Bar -->
        <div class="stp-summary-bar">
            <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                <span id="stp-summary-counts" style="font-weight: 600; color: #334155;">0 Tasks</span>
                <span id="stp-periods-count-badge" style="font-size: 11.5px; font-weight: 500; color: #64748B;">0 Planning Periods</span>
            </div>
            <div>
                <div id="stp-warning-banner" class="stp-warning-banner" style="display: none;">
                    <span>⚠ Some tasks extend beyond the planned template duration.</span>
                </div>
            </div>
        </div>

        <!-- Excel Import Notification Banner -->
        <div id="stp-import-banner" style="display: none;"></div>

        <!-- Scrollable Modal Body (Pure Table View) -->
        <div class="stp-body">
            <!-- Flat Table View Container -->
            <div id="stp-table-wrap" style="padding: 10px 18px 24px;">
                <table class="stp-table">
                    <thead>
                        <tr>
                            <th style="width: 48px; text-align: center;">#</th>
                            <th style="min-width: 240px;">Task Name</th>
                            <th style="width: 220px;">Start Date & Time</th>
                            <th style="width: 220px;">End Date & Time</th>
                            <th style="width: 75px; text-align: center;">Duration</th>
                            <th style="width: 145px;">Predecessor</th>
                            <th style="width: 160px;">Suggested Role</th>
                            <th style="width: 135px; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="stp-table-body">
                        <!-- Injected dynamically via SmartTaskPlan.renderTable() -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sticky Footer -->
        <div class="stp-footer">
            <div>
                <span id="stp-unsaved-text" style="font-size: 12px; color: #64748B;">All changes saved</span>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <button type="button" id="stp-btn-cancel" class="btn btn-outline btn-sm" style="height: 34px; font-size: 12.5px; padding: 0 14px;">
                    Close
                </button>
                <button type="button" id="stp-btn-save" class="stp-btn-primary" style="height: 34px; font-size: 12.5px; padding: 0 16px; display: none;">
                    Save Template
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Task Editor Popup Dialog -->
<div id="stp-task-editor-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 10005; align-items: center; justify-content: center;">
    <div style="background: #FFFFFF; border-radius: 8px; width: 92%; max-width: 580px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); overflow: hidden; display: flex; flex-direction: column;">
        <div style="padding: 14px 20px; border-bottom: 1px solid #E5E7EB; display: flex; align-items: center; justify-content: space-between; background: #F8FAFC;">
            <h3 id="stp-task-editor-title" style="margin: 0; font-size: 15px; font-weight: 700; color: #22272B;">Add Task</h3>
            <button type="button" onclick="SmartTaskPlan.closeTaskEditor()" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #64748B;">✕</button>
        </div>
        <div style="padding: 20px; max-height: 75vh; overflow-y: auto;">
            <form id="stp-task-form" onsubmit="event.preventDefault(); SmartTaskPlan.saveTaskFromEditor();">
                <input type="hidden" id="task_edit_index" value="">

                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 650; margin-bottom: 5px; color: #22272B;">
                        Task Name <span style="color: #DC2626;">*</span>
                    </label>
                    <input type="text" id="task_edit_title" required placeholder="e.g. Requirements Gathering" style="width: 100%; height: 36px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 10px; font-size: 13px;">
                </div>

                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 650; margin-bottom: 5px; color: #22272B;">
                        Parent Task (Optional)
                    </label>
                    <select id="task_edit_parent" style="width: 100%; height: 36px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 10px; font-size: 13px;">
                        <option value="">None (Top-Level Main Task)</option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 650; margin-bottom: 5px; color: #22272B;">
                            Start Period <span style="color: #DC2626;">*</span>
                        </label>
                        <select id="task_edit_start_period" required style="width: 100%; height: 36px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 10px; font-size: 13px;" onchange="SmartTaskPlan.onEditorPeriodChange()">
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 650; margin-bottom: 5px; color: #22272B;">
                            End Period <span style="color: #DC2626;">*</span>
                        </label>
                        <select id="task_edit_end_period" required style="width: 100%; height: 36px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 10px; font-size: 13px;" onchange="SmartTaskPlan.onEditorPeriodChange()">
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 14px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: #475569;">
                            Start Time
                        </label>
                        <input type="time" id="task_edit_start_time" value="09:00" style="width: 100%; height: 34px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 8px; font-size: 12.5px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: #475569;">
                            End Time
                        </label>
                        <input type="time" id="task_edit_end_time" value="17:00" style="width: 100%; height: 34px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 8px; font-size: 12.5px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: #475569;">
                            Duration (Days)
                        </label>
                        <input type="number" id="task_edit_duration" value="1" min="1" max="365" style="width: 100%; height: 34px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 8px; font-size: 12.5px; text-align: center;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 650; margin-bottom: 5px; color: #22272B;">
                            Dependencies
                        </label>
                        <input type="text" id="task_edit_dependency" placeholder="e.g. 1, 1.1" style="width: 100%; height: 36px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 10px; font-size: 12.5px;">
                        <small style="font-size: 11px; color: #64748B;">Enter task code that must finish first.</small>
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 650; margin-bottom: 5px; color: #22272B;">
                            Suggested Role
                        </label>
                        <select id="task_edit_role" style="width: 100%; height: 36px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 10px; font-size: 12.5px;">
                            <option value="">None</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-size: 12px; font-weight: 650; margin-bottom: 5px; color: #475569;">
                        Description (Optional)
                    </label>
                    <textarea id="task_edit_description" rows="2" placeholder="Task notes and instructions..." style="width: 100%; border: 1px solid #CBD5E1; border-radius: 6px; padding: 6px 10px; font-size: 12.5px; resize: vertical;"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 8px; border-top: 1px solid #E5E7EB; padding-top: 14px; margin-top: 14px;">
                    <button type="button" onclick="SmartTaskPlan.closeTaskEditor()" class="btn btn-outline btn-sm" style="height: 34px;">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" style="background: #315F58; color: #FFFFFF; font-weight: 650; height: 34px; padding: 0 16px;">Save Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import Confirmation / Merge Modal -->
<div id="stp-import-confirm-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 10010; align-items: center; justify-content: center;">
    <div style="background: #FFFFFF; border-radius: 8px; width: 90%; max-width: 500px; padding: 22px; box-shadow: 0 10px 25px rgba(0,0,0,0.18);">
        <h3 style="margin: 0 0 8px; font-size: 16px; font-weight: 700; color: #22272B;">Confirm Excel Import</h3>
        <p id="stp-import-confirm-msg" style="margin: 0 0 16px; font-size: 13px; color: #475569;"></p>
        
        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; padding: 12px 14px; margin-bottom: 18px;">
            <div style="font-size: 12px; font-weight: 650; color: #22272B; margin-bottom: 8px;">Select Import Mode:</div>
            <label style="display: flex; align-items: flex-start; gap: 8px; margin-bottom: 8px; cursor: pointer; font-size: 12.5px; color: #334155;">
                <input type="radio" name="stp_import_mode" value="merge" checked style="margin-top: 2px;">
                <div>
                    <b>Merge / Update Matching Tasks (Recommended)</b>
                    <div style="font-size: 11.5px; color: #64748B;">Updates matching task codes, adds new tasks, and preserves existing tasks that were not in Excel.</div>
                </div>
            </label>
            <label style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer; font-size: 12.5px; color: #334155;">
                <input type="radio" name="stp_import_mode" value="replace" style="margin-top: 2px;">
                <div>
                    <b>Replace Draft Task Plan</b>
                    <div style="font-size: 11.5px; color: #64748B;">Replaces current draft tasks completely with the tasks from Excel.</div>
                </div>
            </label>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 8px;">
            <button type="button" onclick="SmartTaskPlan.cancelImport()" class="btn btn-outline btn-sm">Cancel</button>
            <button type="button" onclick="SmartTaskPlan.confirmImport()" class="btn btn-primary btn-sm" style="background: #315F58; color: #FFFFFF; font-weight: 650;">Apply Import</button>
        </div>
    </div>
</div>

<script src="{{ asset('js/template-builder.js') }}?v={{ filemtime(public_path('js/template-builder.js')) }}"></script>
<script>
    function initSmartTaskPlan() {
        if (window.SmartTaskPlan) {
            window.SmartTaskPlan.init({
                roles: @json($roles ?? []),
                canManage: @json($canManage ?? (auth()->user()?->isPmo() || app(\App\Services\Access::class)->hasCompanyPermission(auth()->user(), 'templates.manage'))),
                canCreateProject: @json($canCreateProject ?? (auth()->user()?->isPmo() || app(\App\Services\Access::class)->hasCompanyPermission(auth()->user(), 'projects.create')))
            });
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSmartTaskPlan);
    } else {
        initSmartTaskPlan();
    }
</script>
