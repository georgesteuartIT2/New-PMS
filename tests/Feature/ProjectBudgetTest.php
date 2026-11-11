<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,Project};
class ProjectBudgetTest extends TestCase {
 use RefreshDatabase;
 private function person($role='user'){return User::create(['name'=>$role,'email'=>uniqid().'@example.test','password'=>'secret123','system_role'=>$role,'account_status'=>'active','must_change_password'=>false]);}
 private function project($pm){return Project::create(['name'=>'Budget project','code'=>uniqid('B-'),'created_by'=>$pm->id,'pm_user_id'=>$pm->id,'starts_on'=>today(),'ends_on'=>today()->addWeek(),'status'=>'active','planned_budget'=>'500.00']);}
 public function test_expenses_roll_up_and_removal_recalculates_actual_cost():void {
  $pm=$this->person();$p=$this->project($pm);$this->actingAs($pm);
  foreach(['125.25','400.00'] as $amount)$this->post(route('projects.expenses.store',$p),['amount'=>$amount,'description'=>'Licence','spent_on'=>today()->format('Y-m-d')])->assertRedirect(route('projects.show',$p).'#info');
  $this->assertEquals('525.25',$p->fresh()->actual_cost);
  $this->get(route('projects.show',$p))->assertOk()->assertSee('Over budget')->assertSee('525.25');
  $expense=$p->expenses()->first();$this->delete(route('projects.expenses.destroy',[$p,$expense]),['confirm_remove'=>1])->assertRedirect();$this->assertEquals('400.00',$p->fresh()->actual_cost);
  $this->patch(route('projects.budget.update',$p),['planned_budget'=>'600.00'])->assertRedirect();$this->assertEquals('600.00',$p->fresh()->planned_budget);
  $this->post(route('projects.expenses.store',$p),['amount'=>'-1','description'=>'Bad','spent_on'=>today()->format('Y-m-d')])->assertSessionHasErrors('amount');
 }
 public function test_viewer_cannot_write_and_outsider_cannot_access_budget():void {
  $pm=$this->person();$p=$this->project($pm);$viewer=$this->person('admin_viewer');$outsider=$this->person();
  $this->actingAs($viewer)->get(route('projects.show',$p))->assertOk()->assertSee('Project budget')->assertDontSee('+ Add expense');
  $this->post(route('projects.expenses.store',$p),['amount'=>10,'description'=>'No','spent_on'=>today()->format('Y-m-d')])->assertForbidden();
  $this->actingAs($outsider)->patch(route('projects.budget.update',$p),['planned_budget'=>1])->assertForbidden();
  $this->assertFalse($p->canViewBudget($outsider));
 }
 public function test_receipts_are_private_and_bound_to_their_project():void {
  \Illuminate\Support\Facades\Storage::fake('local');$pm=$this->person();$p=$this->project($pm);
  $this->actingAs($pm)->post(route('projects.expenses.store',$p),['amount'=>'12.50','description'=>'Receipt expense','spent_on'=>today()->format('Y-m-d'),'receipt'=>\Illuminate\Http\UploadedFile::fake()->create('receipt.pdf',10,'application/pdf')])->assertRedirect();
  $expense=$p->expenses()->first();\Illuminate\Support\Facades\Storage::disk('local')->assertExists($expense->receipt_path);
  $this->get(route('projects.expenses.receipt',[$p,$expense]))->assertOk()->assertDownload('receipt.pdf');
  $other=$this->project($pm);$this->get(route('projects.expenses.receipt',[$other,$expense]))->assertNotFound();
  $this->actingAs($this->person())->get(route('projects.expenses.receipt',[$p,$expense]))->assertForbidden();
 }
 public function test_project_edit_saves_budget_without_changing_actual_cost():void {
  $pm=$this->person();$p=$this->project($pm);
  $this->actingAs($pm)->putJson(route('projects.update',$p),['name'=>$p->name,'starts_on'=>today()->format('Y-m-d'),'ends_on'=>today()->addWeek()->format('Y-m-d'),'status'=>'active','planned_budget'=>'950.25'])->assertOk();
  $this->assertEquals('950.25',$p->fresh()->planned_budget);$this->assertEquals(0,$p->fresh()->actual_cost);
  $this->get(route('projects.show',$p))->assertOk()->assertSee('name="planned_budget"',false)->assertSee('950.25');
 }
 public function test_blank_budget_is_preserved_and_draft_creation_saves_budget():void {
  $pmo=$this->person('pmo');$p=app(\App\Services\ProjectService::class)->create(['name'=>'Draft','starts_on'=>today(),'ends_on'=>today()->addWeek(),'planned_budget'=>'1234.56'],$pmo);
  $this->assertEquals('1234.56',$p->planned_budget);$this->assertEquals(0,$p->actual_cost);
  $this->actingAs($pmo)->patch(route('projects.budget.update',$p),['planned_budget'=>null])->assertRedirect();$this->assertNull($p->fresh()->planned_budget);
 }
}
