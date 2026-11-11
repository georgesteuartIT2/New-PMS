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

    public function __construct(public Project $project, public string $kind, public string $message, public string $version)
    {
        $this->onQueue('project-notifications');
    }

    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    public function via(object $person): array
    {
        return ['database', 'mail'];
    }

    public function shouldSend(object $person, string $channel): bool
    {
        if (! $person->isActive()) {
            return false;
        }$p = Project::find($this->project->id);
        if (! $p) {
            return false;
        }
        if ($this->kind === 'owner_request') {
            return $p->approval_stage === 'owner' && (int) $p->owner_user_id === $person->id && (string) $p->updated_at === $this->version;
        }

        return true;
    }

    public function toArray(object $person): array
    {
        return ['type' => 'project_workflow', 'project_id' => $this->project->id, 'project_name' => $this->project->name, 'title' => $this->kind === 'owner_request' ? 'Project owner approval requested' : 'Project assignment update', 'message' => $this->message, 'action_label' => 'Review project', 'url' => route('projects.show', $this->project->id, false)];
    }

    public function toMail(object $person): MailMessage
    {
        return (new MailMessage)->subject(($this->kind === 'owner_request' ? 'Action required: project owner review' : 'Project assignment update').' | '.$this->project->name)->view(['html' => 'emails.project-update', 'text' => 'emails.project-update-text'], ['project' => $this->project->loadMissing('manager', 'subsidiary'), 'recipient' => $person, 'eyebrow' => 'Project update', 'heading' => $this->kind === 'owner_request' ? 'Review your project owner assignment' : 'Project assignment update', 'intro' => $this->message, 'actionLabel' => 'Review project', 'actionUrl' => route('projects.show', $this->project->id), 'guidance' => 'Sign in to review the details and the next action.']);
    }
}
