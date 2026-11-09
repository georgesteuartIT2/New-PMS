(() => {
    const form = document.getElementById('create-template-form');
    const duration = document.getElementById('create_duration');
    const start = document.getElementById('create_reference_start_date');
    const hint = document.getElementById('duration-hint');
    const preview = document.getElementById('create-schedule-text');
    const number = new Intl.NumberFormat('en',{maximumFractionDigits:1});
    window.updateCreateSchedulePreview = () => {
        const days = Number(duration.value);
        if (!duration.value || !duration.validity.valid || !Number.isInteger(days)) {
            hint.textContent = 'Enter a whole number from 1 to 3,650 working days.';
            preview.textContent = 'Enter a valid duration to preview dates.'; return;
        }
        const weeks = days / 5;
        hint.textContent = '\u2248 ' + number.format(weeks) + (weeks === 1 ? ' week' : ' weeks') + (days >= 20 ? ' / about ' + number.format(days / 20) + (days === 20 ? ' month' : ' months') : '');
        hint.title = 'Estimates use 5 working days per week and 20 working days per month.';
        if (start.value && start.validity.valid && window.SmartTaskPlan) {
            const end = SmartTaskPlan.calculateTemplateEndDate(start.value, days, 'working_days');
            preview.textContent = SmartTaskPlan.formatDateDisplay(start.value) + ' - ' + SmartTaskPlan.formatDateDisplay(end);
        } else preview.textContent = 'Choose a reference start date.';
    };
    window.submitCreateTemplateForm = () => {
        if (!form.reportValidity()) return;
        const name = document.getElementById('create_template_name');
        if (!name.value.trim()) { name.setCustomValidity('Enter a template name.'); name.reportValidity(); return; }
        const error = document.getElementById('template-create-error');
        if (!window.SmartTaskPlan) { error.hidden = false; error.textContent = 'The task planner could not load. Refresh the page and try again.'; return; }
        error.hidden = true;
        SmartTaskPlan.openCreate({name:name.value.trim(),estimated_duration:Number(duration.value),duration_unit:'working_days',reference_start_date:start.value,working_hours:document.getElementById('create_working_hours').value,category:'General',description:''});
    };
    document.getElementById('create_template_name').addEventListener('input', event => event.target.setCustomValidity(''));
    form.addEventListener('submit', event => {event.preventDefault();submitCreateTemplateForm();});
    duration.addEventListener('input', updateCreateSchedulePreview);
    start.addEventListener('change', updateCreateSchedulePreview);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',updateCreateSchedulePreview); else updateCreateSchedulePreview();
})();
