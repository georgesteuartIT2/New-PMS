<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskUpdateRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Task $task,
        public User $requester,
        public ?string $message = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        $note = $this->message ? ": \"{$this->message}\"" : '.';
        return [
            'title' => 'Progress Update Requested',
            'message' => "{$this->requester->name} requested a progress update on task '{$this->task->title}' in {$this->task->project->name}{$note}",
            'url' => route('projects.show', $this->task->project_id, false),
            'task_id' => $this->task->id,
            'requester_id' => $this->requester->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('GS ProjectFlow: Progress Update Requested for ' . $this->task->title)
            ->line("{$this->requester->name} has requested an update on task '{$this->task->title}' in project {$this->task->project->name}.");

        if ($this->message) {
            $mail->line("Message from {$this->requester->name}: \"{$this->message}\"");
        }

        return $mail->action('Open Project', route('projects.show', $this->task->project_id))
            ->line('Please review your task and record your latest progress.');
    }
}

