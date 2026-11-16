<?php
namespace App\Notifications;
use App\Models\Project;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
class ProjectAssignmentNotification extends Notification implements ShouldQueue {
 use Queueable;
 public function __construct(public Project $project,public string $eventKey='project_request_received'){$this->onQueue('project-notifications');}
 public function viaConnections(): array { return ['database'=>'sync', 'mail'=>'sync']; }
 public function via(object $notifiable):array{return ['database','mail'];}
 public function toArray(object $notifiable):array{return ['type'=>'project_approval','event_key'=>$this->eventKey,'project_id'=>$this->project->id,'project_name'=>$this->project->name,'action_label'=>'Review project','title'=>$this->eventKey==='project_reassigned'?'Project assignment updated':'Project approval requested','message'=>'You have been assigned as Project Manager for '.$this->project->name.'. Please review and accept the assignment to set up the project plan.','url'=>route('projects.show',$this->project->id,false)];}
 public function shouldSend(object $notifiable,string $channel):bool {
  if($channel!=='mail')return true;
  $project=Project::find($this->project->id);if(!$project || !$notifiable->isActive())return false;
  return $project->status==='pending' && (int)$project->pm_user_id===$notifiable->id;
 }
 public function toMail(object $notifiable):MailMessage {
  $this->project->loadMissing('manager','subsidiary','owner');
  return (new MailMessage)->subject('Action required: review project assignment | '.$this->project->name.' | GS ProjectFlow')->view(['html'=>'emails.project-update','text'=>'emails.project-update-text'],[
   'project'=>$this->project,'recipient'=>$notifiable,'eyebrow'=>'Your decision is needed','heading'=>'Project manager assignment',
   'intro'=>'You have been assigned as Project Manager for '.$this->project->name.'. Please review and accept the assignment, then set up the project plan.',
   'actionLabel'=>'Review & accept project','actionUrl'=>route('projects.show',$this->project->id),
   'guidance'=>'Reviewing the request allows you to accept the assignment and prepare the project plan.',
  ]);
 }
}
