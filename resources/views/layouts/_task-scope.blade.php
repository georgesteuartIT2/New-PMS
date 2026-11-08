<label class="task-scope-control">
    <span class="task-monitor-sr-only">Task view</span>
    <select aria-label="Task view" onchange="window.location.assign(this.value)">
        <option value="{{ route('pmo.task-monitor') }}" @selected(request()->routeIs('pmo.task-monitor*'))>All company tasks</option>
        <option value="{{ route('tasks.mine') }}" @selected(request()->routeIs('tasks.mine'))>Assigned to me</option>
    </select>
</label>
<noscript><a href="{{ route('pmo.task-monitor') }}">All company tasks</a> &middot; <a href="{{ route('tasks.mine') }}">Assigned to me</a></noscript>