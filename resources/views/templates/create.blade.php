@extends('layouts.app')
@section('title', 'Create Template')

@section('content')
<div class="form-width" style="max-width: 880px; margin: 0 auto; padding-bottom: 60px;">
    <!-- Breadcrumb Trail -->
    <div class="breadcrumb-trail" style="margin-bottom: 14px;">
        <a href="{{ route('templates.index') }}">Templates</a>
        <span class="crumb-sep">›</span>
        <span>Create Template</span>
    </div>

    <!-- Page Header -->
    <div class="page-head" style="margin-bottom: 22px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 700; color: #22272B; margin: 0 0 4px;">Create Template</h1>
            <p class="muted" style="margin: 0; font-size: 13.5px;">Create a reusable schedule for your projects.</p>
        </div>
        <div>
            <a href="{{ route('templates.index') }}" class="btn btn-outline btn-sm" style="height: 36px; line-height: 34px; font-size: 13px;">
                ← Back to Templates
            </a>
        </div>
    </div>

    <!-- Main Card -->
    <div class="card form-card" style="background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); padding: 28px;">
        <form id="create-template-form" onsubmit="event.preventDefault(); submitCreateTemplateForm();">
            
            <!-- SECTION 1: TEMPLATE IDENTITY -->
            <div style="margin-bottom: 24px;">
                <div class="form-section-title" style="font-size: 14px; font-weight: 700; color: #22272B; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between;">
                    <span>Template Information</span>
                    <span style="font-size: 11.5px; font-weight: 500; color: #64748B;">* Required fields</span>
                </div>

                <!-- Template Name -->
                <div style="margin-bottom: 16px;">
                    <label for="create_template_name" style="display: block; font-size: 13px; font-weight: 650; margin-bottom: 6px; color: #22272B;">
                        Template Name <span style="color: #DC2626;">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="create_template_name" 
                        name="name" 
                        value="{{ old('name', '') }}" 
                        placeholder="e.g. Network Infrastructure Upgrade" 
                        required 
                        autofocus
                        style="width: 100%; height: 40px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 12px; font-size: 13.5px; background: #FFFFFF; color: #22272B;"
                    >
                    <small style="display: block; font-size: 12px; color: #64748B; margin-top: 4px;">
                        Give this template a descriptive title that clearly identifies its purpose across projects.
                    </small>
                </div>

                <!-- Category & Description -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div>
                        <label for="create_category" style="display: block; font-size: 13px; font-weight: 650; margin-bottom: 6px; color: #22272B;">
                            Category <span style="color: #DC2626;">*</span>
                        </label>
                        <select id="create_category" name="category" required style="width: 100%; height: 40px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 12px; font-size: 13px; background: #FFFFFF; color: #22272B;">
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}" @selected(old('category', 'General') === $cat)>{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="create_description" style="display: block; font-size: 13px; font-weight: 650; margin-bottom: 6px; color: #475569;">
                            Description <span style="font-weight: 400; font-size: 12px; color: #94A3B8;">(Optional)</span>
                        </label>
                        <input 
                            type="text"
                            id="create_description" 
                            name="description" 
                            value="{{ old('description', '') }}" 
                            placeholder="Brief summary of template purpose..." 
                            style="width: 100%; height: 40px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 12px; font-size: 13px; background: #FFFFFF; color: #22272B;"
                        >
                    </div>
                </div>
            </div>

            <hr style="border: 0; border-top: 1px solid #E5E7EB; margin: 24px 0;">

            <!-- SECTION 2: REFERENCE SCHEDULE & WORKING CALENDAR -->
            <div style="margin-bottom: 24px;">
                <div class="form-section-title" style="font-size: 14px; font-weight: 700; color: #22272B; margin-bottom: 16px;">
                    Reference Schedule & Working Calendar
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <!-- Estimated Duration -->
                    <div>
                        <label for="create_duration" style="display: block; font-size: 13px; font-weight: 650; margin-bottom: 6px; color: #22272B;">
                            Estimated Duration <span style="color: #DC2626;">*</span>
                        </label>
                        <div style="display: flex; gap: 8px;">
                            <input 
                                type="number" 
                                id="create_duration" 
                                name="estimated_duration" 
                                value="{{ old('estimated_duration', 14) }}" 
                                min="1" 
                                max="3650" 
                                required 
                                style="width: 85px; height: 40px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 10px; font-size: 13.5px; text-align: center; background: #FFFFFF; color: #22272B;"
                            >
                            <select id="create_duration_unit" name="duration_unit" style="flex: 1; height: 40px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 12px; font-size: 13px; background: #FFFFFF; color: #22272B;">
                                <option value="working_days" @selected(old('duration_unit', 'working_days') === 'working_days')>Working Days</option>
                                <option value="days" @selected(old('duration_unit') === 'days')>Calendar Days</option>
                                <option value="weeks" @selected(old('duration_unit') === 'weeks')>Weeks</option>
                                <option value="months" @selected(old('duration_unit') === 'months')>Months</option>
                            </select>
                        </div>
                    </div>

                    <!-- Reference Start Date -->
                    <div>
                        <label for="create_reference_start_date" style="display: block; font-size: 13px; font-weight: 650; margin-bottom: 6px; color: #22272B;">
                            Reference Start Date <span style="color: #DC2626;">*</span>
                        </label>
                        <input 
                            type="date" 
                            id="create_reference_start_date" 
                            name="reference_start_date" 
                            value="{{ old('reference_start_date', date('Y-m-d')) }}" 
                            required 
                            style="width: 100%; height: 40px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 12px; font-size: 13px; background: #FFFFFF; color: #22272B;"
                        >
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px;">
                    <!-- Working Hours -->
                    <div>
                        <label for="create_working_hours" style="display: block; font-size: 13px; font-weight: 650; margin-bottom: 6px; color: #22272B;">
                            Working Hours
                        </label>
                        <select id="create_working_hours" name="working_hours" style="width: 100%; height: 40px; border: 1px solid #CBD5E1; border-radius: 6px; padding: 0 12px; font-size: 13px; background: #FFFFFF; color: #22272B;">
                            <option value="09:00 - 17:00" @selected(old('working_hours', '09:00 - 17:00') === '09:00 - 17:00')>09:00 AM – 05:00 PM (Standard 8h)</option>
                            <option value="08:30 - 17:00" @selected(old('working_hours') === '08:30 - 17:00')>08:30 AM – 05:00 PM</option>
                            <option value="08:00 - 16:30" @selected(old('working_hours') === '08:00 - 16:30')>08:00 AM – 04:30 PM</option>
                            <option value="09:00 - 18:00" @selected(old('working_hours') === '09:00 - 18:00')>09:00 AM – 06:00 PM</option>
                        </select>
                    </div>

                    <!-- Working Days Policy -->
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 650; margin-bottom: 6px; color: #22272B;">
                            Working Days Policy
                        </label>
                        <div style="height: 40px; border: 1px solid #E2E8F0; border-radius: 6px; padding: 0 12px; font-size: 13px; background: #F8FAFC; color: #334155; display: flex; align-items: center; justify-content: space-between;">
                            <span>Monday – Friday (5 Days / Week)</span>
                            <span style="font-size: 11px; font-weight: 600; background: #E2E8F0; color: #475569; padding: 2px 8px; border-radius: 4px;">Excludes Weekends</span>
                        </div>
                    </div>
                </div>

                <!-- Live Calculated Reference Schedule Box -->
                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-left: 4px solid #315F58; border-radius: 6px; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <div style="font-size: 11px; font-weight: 650; text-transform: uppercase; letter-spacing: 0.5px; color: #64748B; margin-bottom: 3px;">
                            Calculated Reference Schedule
                        </div>
                        <div id="create-schedule-text" style="font-size: 14px; font-weight: 700; color: #22272B;">
                            <!-- Auto-calculated live via JS -->
                        </div>
                    </div>
                    <div style="font-size: 12px; color: #64748B;">
                        Dates shift automatically to actual project start dates upon project creation.
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #E5E7EB; padding-top: 20px; margin-top: 24px;">
                <a href="{{ route('templates.index') }}" class="btn btn-outline" style="height: 40px; font-size: 13px; padding: 0 16px;">
                    Cancel
                </a>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <a href="{{ route('templates.downloadBlank') }}" class="btn btn-outline" style="height: 40px; font-size: 13px; padding: 0 14px;">
                        Download Excel Sample
                    </a>
                    <button type="submit" id="btn-create-task-plan" class="btn btn-primary" style="background: #315F58; color: #FFFFFF; font-weight: 650; height: 40px; font-size: 13.5px; padding: 0 22px; border: 1px solid #315F58;">
                        Create Task Plan →
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Shared Smart Task Plan Popup Modal -->
@include('templates.partials.task-plan-modal')

<script>
    function updateCreateSchedulePreview() {
        const durInput = document.getElementById('create_duration');
        const unitInput = document.getElementById('create_duration_unit');
        const startInput = document.getElementById('create_reference_start_date');
        const previewEl = document.getElementById('create-schedule-text');

        if (!durInput || !unitInput || !startInput || !previewEl) return;

        const dur = parseInt(durInput.value, 10) || 1;
        const unit = unitInput.value;
        const start = startInput.value;

        if (window.SmartTaskPlan) {
            const end = window.SmartTaskPlan.calculateTemplateEndDate(start, dur, unit);
            const unitLabel = unit.replace('_', ' ');
            const startDisplay = window.SmartTaskPlan.formatDateDisplay(start);
            const endDisplay = window.SmartTaskPlan.formatDateDisplay(end);

            previewEl.textContent = `${dur} ${unitLabel} · ${startDisplay} – ${endDisplay} (Working Calendar)`;
        }
    }

    document.getElementById('create_duration').addEventListener('input', updateCreateSchedulePreview);
    document.getElementById('create_duration_unit').addEventListener('change', updateCreateSchedulePreview);
    document.getElementById('create_reference_start_date').addEventListener('change', updateCreateSchedulePreview);

    function submitCreateTemplateForm() {
        const name = document.getElementById('create_template_name').value.trim();
        const duration = parseInt(document.getElementById('create_duration').value, 10) || 14;
        const unit = document.getElementById('create_duration_unit').value;
        const startDate = document.getElementById('create_reference_start_date').value;
        const workingHours = document.getElementById('create_working_hours').value;
        const category = document.getElementById('create_category').value;
        const description = document.getElementById('create_description').value.trim();

        if (!name) {
            alert('Please enter a Template Name before creating the task plan.');
            document.getElementById('create_template_name').focus();
            return;
        }

        if (window.SmartTaskPlan) {
            window.SmartTaskPlan.openCreate({
                name: name,
                estimated_duration: duration,
                duration_unit: unit,
                reference_start_date: startDate,
                working_hours: workingHours,
                category: category,
                description: description
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        updateCreateSchedulePreview();
    });
</script>
@endsection
