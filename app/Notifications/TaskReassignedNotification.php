<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskReassignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Task $task,
        public User $changedBy,
        public string $type = 'assigned', // 'assigned' or 'unassigned'
        public ?string $reason = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        if ($this->type === 'assigned') {
            $msg = "You have been assigned to task '{$this->task->title}' in project {$this->task->project->name} by {$this->changedBy->name}.";
        } else {
            $msg = "You have been unassigned from task '{$this->task->title}' in project {$this->task->project->name} by {$this->changedBy->name}.";
        }

        if ($this->reason) {
            $msg .= " Reason: {$this->reason}";
        }

        return [
            'title' => $this->type === 'assigned' ? 'New Task Assignment' : 'Task Reassigned',
            'message' => $msg,
            'url' => route('projects.show', $this->task->project_id, false),
            'task_id' => $this->task->id,
            'changed_by' => $this->changedBy->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->type === 'assigned'
            ? 'GS ProjectFlow: New Task Assignment - ' . $this->task->title
            : 'GS ProjectFlow: Task Assignment Changed - ' . $this->task->title;

        $mail = (new MailMessage)->subject($subject);

        if ($this->type === 'assigned') {
            $mail->line("You have been assigned to task '{$this->task->title}' in project {$this->task->project->name} by {$this->changedBy->name}.");
        } else {
            $mail->line("You are no longer assigned to task '{$this->task->title}' in project {$this->task->project->name}.");
        }

        if ($this->reason) {
            $mail->line("Reason provided: {$this->reason}");
        }

        return $mail->action('Open Project', route('projects.show', $this->task->project_id));
    }
}

