<nav class="project-return-nav" aria-label="Project navigation">
    <a href="{{ route('projects.index') }}">Projects</a>
    <span aria-hidden="true">/</span>
    <a href="{{ route('projects.show', $project) }}">{{ $project->name }} workspace</a>
</nav>