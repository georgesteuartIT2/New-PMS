<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use App\Models\{User,Project,Task,ProjectInvitation,ProjectRole,ProjectMembership};
class DashboardApprovalReviewTest extends TestCase {
 use RefreshDatabase;
 private function user($role='user'){return User::create(['name'=>'Approval reviewer','email'=>uniqid().'@example.test','password'=>'secret123','system_role'=>$role,'account_status'=>'active','must_change_password'=>false]);}
 private function requestProject($pm,$creator){$p=Project::create(['name'=>'Finance review','description'=>'Deliver a finance workflow.','code'=>uniqid('PR-'),'pm_user_id'=>$pm->id,'created_by'=>$creator->id,'starts_on'=>today(),'ends_on'=>today()->addWeek(),'status'=>'pending']);ProjectInvitation::create(['project_id'=>$p->id,'user_id'=>$pm->id,'status'=>'pending']);return $p;}
 private function snapshot($name,$html){$html=preg_replace('#(href|src)="http://(?:localhost|127\.0\.0\.1:8000)/(css|js)/#','$1="file:///'.str_replace('\\','/',base_path()).'/public/$2/',$html);file_put_contents(storage_path('app/ui-review/'.$name.'.html'),$html);}
 public function test_dashboard_review_has_complete_plan_team_and_is_read_only():void {
  $creator=$this->user('pmo');$pm=$this->user();$p=$this->requestProject($pm,$creator);$member=$this->user();$member->update(['name'=>'Assigned colleague']);$role=ProjectRole::create(['slug'=>'team','name'=>'Team member']);ProjectMembership::create(['project_id'=>$p->id,'user_id'=>$member->id,'project_role_id'=>$role->id]);
  $parent=Task::create(['project_id'=>$p->id,'code'=>'T1','title'=>'Review main task','created_by'=>$creator->id,'starts_at'=>now(),'ends_at'=>now()->addDay()]);$child=Task::create(['project_id'=>$p->id,'parent_task_id'=>$parent->id,'code'=>'T1.1','title'=>'Review child task','created_by'=>$creator->id,'starts_at'=>now(),'ends_at'=>now()->addDay()]);$child->assignees()->attach($member->id);
  $dashboard=$this->actingAs($pm)->get(route('dashboard'));$dashboard->assertOk()->assertSee('data-approval-review-url',false)->assertSee('approval-queue-priority');$this->assertLessThan(strpos($dashboard->getContent(),'class="home-summary"'),strpos($dashboard->getContent(),'id="project-approvals"'));
  $review=$this->get(route('projects.approval-review',$p));$review->assertOk()->assertSee('Deliver a finance workflow.')->assertSee('Review main task')->assertSee('Review child task')->assertSee('Assigned colleague')->assertSee('Accept project')->assertSee('Confirm rejection')->assertSee('Blank project');$this->assertSame('pending',$p->fresh()->status);$this->assertNull($p->fresh()->baseline_captured_at);
  $this->snapshot('approval-popup-dashboard',$dashboard->getContent());file_put_contents(storage_path('app/ui-review/approval-popup-fragment.html'),$review->getContent());
 }
 public function test_only_current_invited_manager_can_review_and_answer_request():void {
  $pm=$this->user();$pmo=$this->user('pmo');$p=$this->requestProject($pm,$pmo);
  foreach([$pmo,$this->user(),$this->user('admin_viewer')] as $outsider){$this->actingAs($outsider)->get(route('projects.approval-review',$p))->assertForbidden();$this->postJson(route('projects.respond',$p),['decision'=>'accept'])->assertForbidden();}
  Notification::fake();$this->actingAs($pm)->postJson(route('projects.respond',$p),['decision'=>'accept'])->assertOk()->assertJsonPath('decision','accept')->assertJsonPath('redirect',route('dashboard').'#project-approvals');$this->assertSame('active',$p->fresh()->status);$this->assertNotNull($p->fresh()->baseline_captured_at);$this->get(route('projects.approval-review',$p))->assertStatus(409);$this->postJson(route('projects.respond',$p),['decision'=>'accept'])->assertNotFound();
 }
 public function test_rejection_stays_pending_until_reason_is_valid():void {
  Notification::fake();$pm=$this->user();$p=$this->requestProject($pm,$this->user('pmo'));
  $this->actingAs($pm)->postJson(route('projects.respond',$p),['decision'=>'decline'])->assertUnprocessable()->assertJsonValidationErrors('decline_reason');$this->assertSame('pending',$p->fresh()->status);
  $this->postJson(route('projects.respond',$p),['decision'=>'decline','decline_reason'=>'Please revise the delivery schedule.'])->assertOk()->assertJsonPath('decision','decline');$this->assertSame('declined',$p->fresh()->status);$this->assertNull($p->fresh()->baseline_captured_at);$this->assertDatabaseHas('project_invitations',['project_id'=>$p->id,'status'=>'declined','decline_reason'=>'Please revise the delivery schedule.']);Notification::assertNothingSent();
 }
 public function test_reassignment_returns_to_project_without_reopening_popup():void {
  Notification::fake();$pmo=$this->user('pmo');$old=$this->user();$next=$this->user();$p=$this->requestProject($old,$pmo);$p->update(['status'=>'declined']);$p->invitations()->update(['status'=>'declined','decline_reason'=>'Cannot take this assignment.']);ProjectRole::create(['name'=>'Project Manager','slug'=>'pm']);
  $this->actingAs($pmo)->from(route('projects.show',[$p,'reassign'=>1]))->post(route('projects.manager',$p),['pm_user_id'=>$next->id])->assertRedirect(route('projects.show',$p))->assertSessionHas('success');
  $this->assertSame($next->id,$p->fresh()->pm_user_id);$this->assertSame(1,$p->invitations()->where('status','pending')->count());
  Notification::assertSentToTimes($next,\App\Notifications\ProjectAssignmentNotification::class,1);
 }
}
