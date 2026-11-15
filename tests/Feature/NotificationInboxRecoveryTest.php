<?php
namespace Tests\Feature;
use Tests\TestCase;use Illuminate\Foundation\Testing\RefreshDatabase;use App\Models\{User,Project,Task};
class NotificationInboxRecoveryTest extends TestCase {
 use RefreshDatabase;
 public function test_overdue_task_notifications_are_recoverable_idempotent_and_resolved_on_completion():void {
 $pmo=User::create(['name'=>'PMO','email'=>'pmo-overdue@example.test','password'=>'secret123','system_role'=>'pmo','account_status'=>'active','must_change_password'=>false]);
 $manager=User::create(['name'=>'Manager','email'=>'manager-overdue@example.test','password'=>'secret123','system_role'=>'user','account_status'=>'active','must_change_password'=>false]);
 $project=Project::create(['name'=>'Overdue delivery','code'=>'OVERDUE-1','created_by'=>$pmo->id,'pm_user_id'=>$manager->id,'starts_on'=>today()->subWeek(),'ends_on'=>today(),'status'=>'active']);
 $task=Task::create(['project_id'=>$project->id,'title'=>'Delayed review','code'=>'T-OVERDUE','created_by'=>$pmo->id,'starts_at'=>now()->subWeeks(2),'ends_at'=>now()->subWeek(),'status'=>'in_progress','progress'=>35]);
 $task->assignees()->attach($manager->id);

 $this->actingAs($manager)->get(route('notifications.index'))->assertOk()->assertSee('Task overdue');
 $notification=$manager->notifications()->where('data->event_key','task_overdue')->firstOrFail();
 $count=$manager->notifications()->count();
 $this->get(route('notifications.index'))->assertOk();
 $this->assertSame($count,$manager->notifications()->count());

 $task->update(['status'=>'completed','progress'=>100]);
 app(\App\Services\InboxService::class)->taskCompleted($task);
 $this->assertNotNull($notification->fresh()->read_at);
 }
 public function test_pmo_and_user_inboxes_recover_real_updates_without_duplicates():void {
 $users=[];foreach(['pmo','user'] as $role)$users[]=User::create(['name'=>$role,'email'=>$role.'@example.test','password'=>'secret123','system_role'=>$role,'account_status'=>'active','must_change_password'=>false]);[$pmo,$user]=$users;
 $p=Project::create(['name'=>'Live delivery','code'=>'N1','created_by'=>$pmo->id,'pm_user_id'=>$user->id,'starts_on'=>today(),'ends_on'=>today()->addWeek(),'status'=>'active']);
 $t=Task::create(['project_id'=>$p->id,'title'=>'Assigned work','code'=>'T1','created_by'=>$pmo->id,'starts_at'=>now(),'ends_at'=>now()->addDay()]);$t->assignees()->attach($user->id);
 $t->delayReports()->create(['reported_by'=>$user->id,'reason_code'=>'other','reason_text'=>'Equipment unavailable','responsible_party_type'=>'internal','status'=>'open']);
 $this->actingAs($pmo)->get(route('notifications.index'))->assertOk()->assertSee('Equipment unavailable');$this->get(route('notifications.index'));$this->assertSame(1,$pmo->notifications()->count());
 $this->actingAs($user)->get(route('notifications.index'))->assertOk()->assertSee('Assigned work');$count=$user->notifications()->count();$this->get(route('notifications.index'));$this->assertSame($count,$user->notifications()->count());
 $n=$user->notifications()->first();$this->post(route('notifications.read',$n->id))->assertRedirect();$this->assertNotNull($n->fresh()->read_at);
 $this->actingAs($pmo)->post(route('notifications.read',$n->id))->assertNotFound();
 }
}
