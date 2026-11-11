<?php
namespace App\Notifications;
use App\Models\Project;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
class ProjectAssignmentNotification extends Notification implements ShouldQueue {
 use Queueable;
 public function __construct(public Project $project){$this->onQueue('project-notifications');}
 public function viaConnections(): array { return ['database'=>'sync']; }
 public function via(object $notifiable):array{return ['database','mail'];}
 public function toArray(object $notifiable):array{return ['type'=>'project_approval','project_id'=>$this->project->id,'project_name'=>$this->project->name,'action_label'=>'Review project','title'=>'Project approval requested','message'=>'You have been assigned as Project Manager for '.$this->project->name.'. Please review and accept or decline the assignment.','url'=>route('projects.show',$this->project->id,false)];}
 public function shouldSend(object $notifiable,string $channel):bool {
  if($channel!=='mail')return true;
  $project=Project::find($this->project->id);if(!$project || !$notifiable->isActive())return false;
  return $project->status==='pending' && (int)$project->pm_user_id===$notifiable->id;
 }
 public function toMail(object $notifiable):MailMessage {
  $this->project->loadMissing('manager','subsidiary');
  return (new MailMessage)->subject('Action required: review project assignment | '.$this->project->name.' | GS NexusPM')->view(['html'=>'emails.project-update','text'=>'emails.project-update-text'],[
   'project'=>$this->project,'recipient'=>$notifiable,'eyebrow'=>'Your decision is needed','heading'=>'Project manager assignment',
   'intro'=>'You have been nominated as Project Manager. Please review the project plan, schedule and team before accepting or declining.',
   'actionLabel'=>'Review project assignment','actionUrl'=>route('projects.show',$this->project->id),
   'guidance'=>'Reviewing the request does not accept it. You can accept or decline after reviewing the project.',
  ]);
 }
}
