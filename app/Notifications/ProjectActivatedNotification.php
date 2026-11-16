<?php
namespace App\Notifications;
use App\Models\Project;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
class ProjectActivatedNotification extends Notification implements ShouldQueue {
 use Queueable;
 public function __construct(public Project $project,public string $eventKey='project_activated'){$this->onQueue('project-notifications');}
 public function via(object $notifiable):array{return ['database','mail'];}
 public function viaConnections():array{return ['database'=>'sync'];}
 public function toArray(object $notifiable):array{return ['event_key'=>$this->eventKey,'title'=>$this->eventKey==='plan_published'?'Project plan published':'Project ready to start','message'=>$this->eventKey==='plan_published'?'The Project Manager published the plan for '.$this->project->name.'. Review the schedule and your assigned tasks.':$this->project->manager?->name.' accepted '.$this->project->name.'. You are part of the project team.','project_id'=>$this->project->id,'project_name'=>$this->project->name,'action_label'=>'Open project','url'=>route('projects.show',$this->project,false)];}
 public function shouldSend(object $notifiable,string $channel):bool {
  if($channel!=='mail')return true;
  $project=Project::find($this->project->id);if(!$project || !$notifiable->isActive())return false;
  $isMemberOrAssignee = $project->memberships()->where('user_id',$notifiable->id)->exists() || $project->tasks()->whereHas('assignees', fn($q) => $q->where('users.id', $notifiable->id))->exists();
  return in_array($project->status,['active','on_hold'],true) && $isMemberOrAssignee && (int)$project->pm_user_id!==$notifiable->id;
 }
 public function toMail(object $notifiable):MailMessage {
  $this->project->loadMissing('manager','subsidiary','owner');
  $published=$this->eventKey==='plan_published';
  return (new MailMessage)->subject(($published?'Project plan published':'Project activated').' | '.$this->project->name.' | GS ProjectFlow')->view(['html'=>'emails.project-update','text'=>'emails.project-update-text'],[
   'project'=>$this->project,'recipient'=>$notifiable,'eyebrow'=>$published?'Plan published':'Ready to begin','heading'=>$published?'The project plan is published':'Your project is ready to start',
   'intro'=>$published ? 'The Project Manager published the plan for '.$this->project->name.'. Review the schedule and your assigned tasks.' : ($this->project->approval_stage ? 'The Project Manager has saved the project plan. You are part of the team. Review the schedule and your assigned tasks.' : 'The Project Manager has accepted this project. You are part of the project team and can now review the plan and your assigned work.'),
   'actionLabel'=>'Open project','actionUrl'=>route('projects.show',$this->project->id),
   'guidance'=>'Sign in with your work account to view the project. Contact your Project Manager if you need help with your tasks.',
  ]);
 }
}
