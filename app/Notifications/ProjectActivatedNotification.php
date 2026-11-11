<?php
namespace App\Notifications;
use App\Models\Project;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
class ProjectActivatedNotification extends Notification implements ShouldQueue {
 use Queueable;
 public function __construct(public Project $project){$this->onQueue('project-notifications');}
 public function via(object $notifiable):array{return ['database','mail'];}
 public function viaConnections():array{return ['database'=>'sync'];}
 public function toArray(object $notifiable):array{return ['title'=>'Project ready to start','message'=>$this->project->manager?->name.' accepted '.$this->project->name.'. You are part of the project team.','project_id'=>$this->project->id,'project_name'=>$this->project->name,'action_label'=>'Open project','url'=>route('projects.show',$this->project,false)];}
 public function shouldSend(object $notifiable,string $channel):bool {
  if($channel!=='mail')return true;
  $project=Project::find($this->project->id);if(!$project || !$notifiable->isActive())return false;
  return (!$project->approval_stage || $project->approval_stage==='ready') && in_array($project->status,['active','on_hold'],true) && $project->memberships()->where('user_id',$notifiable->id)->exists() && $notifiable->system_role==='user' && (int)$project->pm_user_id!==$notifiable->id;
 }
 public function toMail(object $notifiable):MailMessage {
  $this->project->loadMissing('manager','subsidiary');
  return (new MailMessage)->subject('Project activated | '.$this->project->name.' | GS NexusPM')->view(['html'=>'emails.project-update','text'=>'emails.project-update-text'],[
   'project'=>$this->project,'recipient'=>$notifiable,'eyebrow'=>'Ready to begin','heading'=>'Your project is ready to start',
   'intro'=>$this->project->approval_stage ? 'The Project Manager has saved the project plan. You are part of the team. Review the schedule and your assigned tasks.' : 'The Project Manager has accepted this project. You are part of the project team and can now review the plan and your assigned work.',
   'actionLabel'=>'Open project','actionUrl'=>route('projects.show',$this->project->id),
   'guidance'=>'Sign in with your work account to view the project. Contact your Project Manager if you need help with your tasks.',
  ]);
 }
}
