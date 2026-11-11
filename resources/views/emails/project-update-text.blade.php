GS NexusPM
{{ $heading }}

Hello {{ $recipient->name }},
{{ $intro }}

Project: {{ $project->name }}
Code: {{ $project->code }}
Project manager: {{ $project->manager?->name }}
Start: {{ $project->starts_on->format('d M Y') }}
Target end: {{ $project->ends_on->format('d M Y') }}

{{ $actionLabel }}: {{ $actionUrl }}

{{ $guidance }}
GS NexusPM Engine
