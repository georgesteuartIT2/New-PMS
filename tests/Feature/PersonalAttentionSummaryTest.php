<?php
namespace Tests\Feature;
use Tests\TestCase;use Illuminate\Foundation\Testing\RefreshDatabase;use App\Models\{User,Project,Task};
class PersonalAttentionSummaryTest extends TestCase {
 use RefreshDatabase;
 public function test_many_tasks_stay_grouped_and_shortcut_filters_match():void {
 $u=User::create(['name'=>'PM','email'=>'pm@example.test','password'=>'secret123','system_role'=>'user','account_status'=>'active','must_change_password'=>false]);
 $p=Project::create(['name'=>'Many tasks','code'=>'A1','created_by'=>$u->id,'pm_user_id'=>$u->id,'starts_on'=>today(),'ends_on'=>today()->addMonth(),'status'=>'active']);
 for($i=0;$i<15;$i++){$t=Task::create(['project_id'=>$p->id,'title'=>'Overdue '.$i,'code'=>'T'.$i,'created_by'=>$u->id,'starts_at'=>now()->subDays(4),'ends_at'=>now()->subDay()]);$t->assignees()->attach($u->id);}
 $near=Task::create(['project_id'=>$p->id,'title'=>'Needs owner','code'=>'N','created_by'=>$u->id,'starts_at'=>now()->addDay(),'ends_at'=>now()->addWeek()]);
 Task::create(['project_id'=>$p->id,'title'=>'Future unassigned','code'=>'F','created_by'=>$u->id,'starts_at'=>now()->addMonth(),'ends_at'=>now()->addMonths(2)]);
 $near->delayReports()->create(['reported_by'=>$u->id,'reason_code'=>'other','reason_text'=>'Waiting','responsible_party_type'=>'internal','action_owner_id'=>$u->id,'status'=>'open']);
 $r=$this->actingAs($u)->get(route('dashboard'));$r->assertOk()->assertSee('Needs your attention')->assertDontSee('Show 10 more');$rows=$r->viewData('attentionItems');$this->assertCount(4,$rows);$this->assertSame(15,$rows->firstWhere('type','my_overdue')['count']);$this->assertSame(1,$rows->firstWhere('type','needs_owner')['count']);
 foreach($rows as $row){$this->getJson($row['action_url'])->assertOk()->assertJsonPath('tasks.total',$row['count']);}
 }
}
