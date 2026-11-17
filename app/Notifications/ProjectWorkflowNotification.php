<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjectWorkflowNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Project $project, public string $kind, public string $message, public string $version, public string $eventKey = 'project_request_received')
    {
        $this->onQueue('project-notifications');
    }

    public function viaConnections(): array
    {
        return ['database' => 'sync', 'mail' => 'sync'];
    }

    public function via(object $person): array
    {
        return ['database', 'mail'];
    }

    public function shouldSend(object $person, string $channel): bool
    {
        if (! $person->isActive()) {
            return false;
        }
        $p = Project::find($this->project->id);
        if (! $p) {
            return false;
        }
        if ($this->kind === 'owner_request') {
            return $p->approval_stage === 'owner' && (int) $p->owner_user_id === $person->id;
        }

        return true;
    }

    public function toArray(object $person): array
    {
        $title = match ($this->eventKey) {
            'project_request_accepted' => 'Project request accepted',
            'project_request_rejected' => 'Project request rejected',
            'project_reassigned' => 'Project assignment updated',
            default => $this->kind === 'owner_request' ? 'Project owner approval requested' : 'Project assignment update',
        };
        return ['type' => 'project_workflow', 'event_key' => $this->eventKey, 'project_id' => $this->project->id, 'project_name' => $this->project->name, 'title' => $title, 'message' => $this->message, 'action_label' => 'Review project', 'url' => route('projects.show', $this->project->id, false)];
    }

    public function toMail(object $person): MailMessage
    {
        return (new MailMessage)->subject(($this->kind === 'owner_request' ? 'Action required: project owner review' : 'Project assignment update').' | '.$this->project->name.' | GS ProjectFlow')->view(['html' => 'emails.project-update', 'text' => 'emails.project-update-text'], ['project' => $this->project->loadMissing('manager', 'subsidiary', 'owner'), 'recipient' => $person, 'eyebrow' => 'Project update', 'heading' => $this->kind === 'owner_request' ? 'Review your project owner assignment' : 'Project assignment update', 'intro' => $this->message, 'actionLabel' => 'Review project', 'actionUrl' => route('projects.show', $this->project->id), 'guidance' => 'Sign in to review the details and the next action.']);
    }
}
