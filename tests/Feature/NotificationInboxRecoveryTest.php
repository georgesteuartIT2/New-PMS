<?php
namespace Tests\Feature;
use Tests\TestCase;use Illuminate\Foundation\Testing\RefreshDatabase;use App\Models\{User,Project,Task};
class NotificationInboxRecoveryTest extends TestCase {
 use RefreshDatabase;
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
