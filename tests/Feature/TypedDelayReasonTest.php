<?php
namespace Tests\Feature;
use Tests\TestCase;use Illuminate\Foundation\Testing\RefreshDatabase;use App\Models\{User,Project,Task};
class TypedDelayReasonTest extends TestCase {
 use RefreshDatabase;
 public function test_typed_reason_is_required_saved_and_visible_to_pmo():void {
 $pm=User::create(['name'=>'Manager','email'=>'manager@example.test','password'=>'secret123','system_role'=>'user','account_status'=>'active','must_change_password'=>false]);
 $p=Project::create(['name'=>'Delivery','code'=>'D1','created_by'=>$pm->id,'pm_user_id'=>$pm->id,'starts_on'=>today(),'ends_on'=>today()->addWeek(),'status'=>'active']);
 $t=Task::create(['project_id'=>$p->id,'title'=>'Procurement','code'=>'T1','created_by'=>$pm->id,'starts_at'=>now(),'ends_at'=>now()->addDay()]);
 $this->actingAs($pm)->postJson(route('tasks.delays.store',$t),['reason_text'=>'Vendor has not delivered the required equipment','responsible_party_type'=>'supplier'])->assertOk();
 $this->assertSame('Vendor has not delivered the required equipment',$t->delayReports()->first()->reason_label);
 $this->postJson(route('tasks.delays.store',$t),['reason_text'=>'','responsible_party_type'=>'internal'])->assertUnprocessable()->assertJsonValidationErrors('reason_text');
 $pmo=User::create(['name'=>'PMO','email'=>'pmo@example.test','password'=>'secret123','system_role'=>'pmo','account_status'=>'active','must_change_password'=>false]);
 $this->actingAs($pmo)->get(route('projects.show',$p))->assertOk()->assertSee('Vendor has not delivered the required equipment')->assertSee('name="reason_text"',false);
 }
 public function test_dashboard_unread_count_is_per_person_and_clears_only_on_read():void {
 $a=User::create(['name'=>'PMO A','email'=>'a@example.test','password'=>'secret123','system_role'=>'pmo','account_status'=>'active','must_change_password'=>false]);
 $b=User::create(['name'=>'PMO B','email'=>'b@example.test','password'=>'secret123','system_role'=>'pmo','account_status'=>'active','must_change_password'=>false]);
 $p=Project::create(['name'=>'Delivery','code'=>'R1','created_by'=>$a->id,'starts_on'=>today(),'ends_on'=>today()->addWeek(),'status'=>'active']);
 $t=Task::create(['project_id'=>$p->id,'title'=>'Blocked work','code'=>'T1','created_by'=>$a->id,'starts_at'=>now(),'ends_at'=>now()->addDay()]);
 $d=$t->delayReports()->create(['reported_by'=>$a->id,'reason_code'=>'other','reason_text'=>'Awaiting equipment','responsible_party_type'=>'supplier','status'=>'open']);
 $this->actingAs($a)->get(route('dashboard',['task_attention'=>'blocked']))->assertOk()->assertViewHas('unreadBlockerCount',1)->assertSee('Awaiting equipment');
 $this->post(route('tasks.delays.read',$d))->assertRedirect(route('projects.show',[$p->id,'focus_issue'=>$d->id]).'#risks');
 $this->get(route('dashboard'))->assertViewHas('unreadBlockerCount',0);
 $this->actingAs($b)->get(route('dashboard'))->assertViewHas('unreadBlockerCount',1);
 $this->assertSame('open',$d->fresh()->status);
 }
}
