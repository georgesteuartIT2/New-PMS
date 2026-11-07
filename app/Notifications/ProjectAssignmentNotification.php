<?php
namespace App\Notifications;
use App\Models\Project;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
class ProjectAssignmentNotification extends Notification implements ShouldQueue {
 use Queueable;
 public function __construct(public Project $project){}
 public function via(object $notifiable):array{return ['database','mail'];}
 public function toArray(object $notifiable):array{return ['title'=>'New project assignment','message'=>'You have been assigned as Project Manager for '.$this->project->name.'.','url'=>route('projects.show',$this->project->id,false)];}
 public function toMail(object $notifiable):MailMessage{return (new MailMessage)->subject('GS ProjectFlow: Project assignment')->line('You have been assigned as Project Manager for '.$this->project->name.'.')->action('Review project',route('projects.show',$this->project->id))->line('Please accept or decline the assignment.');}
}
