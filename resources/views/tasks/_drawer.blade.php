<aside class="task-side-panel" id="task-side-panel" role="dialog" aria-modal="true" aria-labelledby="task-panel-title" aria-hidden="true" hidden>
    <div class="task-panel-top">
        <h2 id="task-panel-title">Task details</h2>
        <button type="button" class="panel-close" id="task-panel-close" aria-label="Close task details">&times;</button>
    </div>
    <div id="task-panel-content" class="task-panel-content"><p class="muted">Select a task to see its details.</p></div>
</aside>
<div id="task-panel-shade" class="task-panel-shade" hidden></div>
@push('scripts')
<script defer src="{{ asset('js/project-workspace.js') }}"></script>
@endpush