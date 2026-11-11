<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\ProjectRole;
use App\Models\ProjectTemplate;
use App\Models\Subsidiary;
use App\Models\Task;
use App\Models\TemplateTask;
use App\Models\User;
use App\Notifications\ProjectActivatedNotification;
use App\Notifications\ProjectAssignmentNotification;
use App\Notifications\ProjectWorkflowNotification;
use App\Services\Access;
use App\Services\AzureGraphService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OwnerApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function person($role = 'user')
    {
        return User::create(['name' => 'Workflow person', 'email' => uniqid().'@example.test', 'password' => 'secret123', 'system_role' => $role, 'account_status' => 'active', 'must_change_password' => false]);
    }

    private function snapshot($name, $html)
    {
        $html = preg_replace('#(href|src)="http://(?:localhost|127\.0\.0\.1:8000)/(css|js)/#', '$1="file:///'.str_replace('\\', '/', base_path()).'/public/$2/', $html);
        file_put_contents(storage_path('app/ui-review/'.$name.'.html'), $html);
    }

    private function initiate($type = 'owner')
    {
        Notification::fake();
        ProjectRole::firstOrCreate(['slug' => 'pm'], ['name' => 'Project Manager']);
        $pmo = $this->person('pmo');
        $lead = $this->person();
        $sub = Subsidiary::create(['name' => 'Workflow company', 'code' => uniqid('GS'), 'status' => 'active', 'created_by' => $pmo->id]);
        $this->actingAs($pmo)->post(route('projects.initiate'), ['name' => 'New delivery', 'description' => 'Deliver the new workflow', 'subsidiary_id' => $sub->id, 'assignment_type' => $type, 'assignee_id' => $lead->id])->assertRedirect();

        return [$pmo, $lead, Project::latest('id')->first(), $sub];
    }

    public function test_owner_approves_then_pm_accepts_prepares_and_publishes_once(): void
    {
        [$pmo,$owner,$p] = $this->initiate();
        $pm = $this->person();
        $member = $this->person();
        $outsider = $this->person();
        $team = ProjectRole::create(['name' => 'Team', 'slug' => 'team']);
        $this->assertSame('owner', $p->approval_stage);
        $this->assertNull($p->pm_user_id);
        Notification::assertSentTo($owner, ProjectWorkflowNotification::class);
        $ownerView = $this->actingAs($owner)->get(route('projects.show', $p));
        $ownerView->assertOk()->assertSee('Approve &amp; invite PM', false);
        $this->snapshot('workflow-owner-review', $ownerView->getContent());
        $this->get(route('dashboard'))->assertOk()->assertSee('Review &amp; choose PM', false);
        $this->actingAs($outsider)->post(route('projects.owner.respond', $p), ['decision' => 'accept', 'pm_user_id' => $pm->id])->assertForbidden();
        $this->actingAs($owner)->post(route('projects.owner.respond', $p), ['decision' => 'accept', 'pm_user_id' => $pm->id])->assertRedirect();
        $this->assertSame('pm', $p->fresh()->approval_stage);
        Notification::assertSentTo($pm, ProjectAssignmentNotification::class);
        $this->post(route('projects.owner.respond', $p), ['decision' => 'accept', 'pm_user_id' => $pm->id])->assertStatus(409);
        $this->actingAs($pm)->postJson(route('projects.respond', $p), ['decision' => 'accept'])->assertOk()->assertJsonPath('redirect', route('projects.plan.setup', $p));
        $p->refresh();
        $this->assertSame('planning', $p->approval_stage);
        $this->assertNull($p->baseline_captured_at);
        Notification::assertNotSentTo($member, ProjectActivatedNotification::class);
        $setup = $this->get(route('projects.plan.setup', $p));
        $setup->assertOk()->assertSee('Blank plan')->assertSee('Use a template');
        $this->snapshot('workflow-plan-setup', $setup->getContent());
        $this->post(route('projects.plan.initialize', $p), ['mode' => 'blank', 'starts_on' => '2026-11-12', 'ends_on' => '2026-11-30'])->assertRedirect(route('projects.show', $p).'#tasks');
        $this->post(route('projects.plan.initialize', $p), ['mode' => 'blank', 'starts_on' => '2026-11-12', 'ends_on' => '2026-11-30'])->assertStatus(409);
        ProjectMembership::create(['project_id' => $p->id, 'user_id' => $member->id, 'project_role_id' => $team->id]);
        $task = Task::create(['project_id' => $p->id, 'code' => '1', 'title' => 'First work', 'starts_at' => '2026-11-12 09:00', 'ends_at' => '2026-11-13 17:00', 'created_by' => $pm->id]);
        $task->assignees()->attach($member->id);
        foreach ([$pm, $owner] as $lead) {
            foreach (['tasks.create', 'tasks.assign', 'project.members_manage', 'project.budget_manage', 'project.closure_approve'] as $permission) {
                $this->assertTrue(app(Access::class)->project($lead, $p->fresh(), $permission));
            }
        }
        $this->actingAs($outsider)->post(route('projects.plan.publish', $p))->assertForbidden();
        $this->actingAs($pm)->post(route('projects.plan.publish', $p))->assertRedirect();
        $this->assertSame('ready', $p->fresh()->approval_stage);
        $this->assertNotNull($p->fresh()->baseline_captured_at);
        Notification::assertSentToTimes($member, ProjectActivatedNotification::class, 1);
        Notification::assertNotSentTo($outsider, ProjectActivatedNotification::class);
        $this->post(route('projects.plan.publish', $p))->assertStatus(409);
        Notification::assertSentToTimes($member, ProjectActivatedNotification::class, 1);
    }

    public function test_rejections_and_reassignments_are_visible_to_both_leaders(): void
    {
        [$pmo,$owner,$p] = $this->initiate();
        $pm = $this->person();
        $next = $this->person();
        $this->actingAs($owner)->post(route('projects.owner.respond', $p), ['decision' => 'decline', 'decline_reason' => 'The scope needs revision.'])->assertRedirect();
        $this->assertSame('owner_declined', $p->fresh()->approval_stage);
        $this->actingAs($pmo)->get(route('dashboard'))->assertOk()->assertSee('The scope needs revision.');
        Notification::assertSentTo($pmo, ProjectWorkflowNotification::class);
        $this->actingAs($pmo)->post(route('projects.workflow.reassign', $p), ['assignment_type' => 'owner', 'assignee_id' => $next->id])->assertRedirect();
        $this->assertSame($next->id, $p->fresh()->owner_user_id);
        $this->assertFalse(app(Access::class)->project($owner, $p->fresh()));
        $this->actingAs($next)->post(route('projects.owner.respond', $p), ['decision' => 'accept', 'pm_user_id' => $pm->id])->assertRedirect();
        $this->actingAs($pm)->post(route('projects.respond', $p), ['decision' => 'decline', 'decline_reason' => 'I cannot take this schedule.'])->assertRedirect();
        $this->assertSame('pm_declined', $p->fresh()->approval_stage);
        $this->assertSame('I cannot take this schedule.', $p->fresh()->approval_reason);
        Notification::assertSentTo($next, ProjectWorkflowNotification::class);
        $this->actingAs($next)->get(route('projects.show', $p))->assertOk()->assertSee('I cannot take this schedule.');
        $this->post(route('projects.workflow.reassign', $p), ['assignment_type' => 'pm', 'assignee_id' => $owner->id])->assertRedirect();
        $this->assertSame($owner->id, $p->fresh()->pm_user_id);
        $this->actingAs($pm)->post(route('projects.respond', $p), ['decision' => 'accept'])->assertForbidden();
    }

    public function test_direct_pm_route_unique_codes_and_viewer_restrictions(): void
    {
        [$pmo,$pm,$p,$sub] = $this->initiate('pm');
        $this->assertSame('pm', $p->approval_stage);
        $this->assertNull($p->owner_user_id);
        Notification::assertSentTo($pm, ProjectAssignmentNotification::class);
        $create = $this->actingAs($pmo)->get(route('projects.create'));
        $create->assertOk()->assertSee('Who should review first?');
        $this->snapshot('workflow-create', $create->getContent());
        $this->post(route('projects.initiate'), ['name' => 'Second project', 'subsidiary_id' => $sub->id, 'assignment_type' => 'pm', 'assignee_id' => $pm->id])->assertRedirect();
        $this->assertNotSame($p->code, Project::latest('id')->first()->code);
        $viewer = $this->person('admin_viewer');
        $this->actingAs($viewer)->post(route('projects.workflow.reassign', $p), ['assignment_type' => 'pm', 'assignee_id' => $pm->id])->assertForbidden();
        $this->actingAs($pm)->postJson(route('projects.respond', $p), ['decision' => 'accept'])->assertOk();
        $this->assertFalse(app(Access::class)->project($pm, $p->fresh(), 'users.manage'));
    }

    public function test_template_tasks_are_copied_and_directory_person_can_be_assigned_without_early_mail(): void
    {
        [$pmo,$pm,$p] = $this->initiate('pm');
        $this->actingAs($pm)->postJson(route('projects.respond', $p), ['decision' => 'accept'])->assertOk();
        $template = ProjectTemplate::create(['name' => 'Reusable delivery', 'status' => 'active', 'created_by' => $pmo->id, 'estimated_duration' => 5, 'duration_unit' => 'working_days', 'reference_start_date' => '2026-11-09', 'working_hours' => '09:00 - 17:00']);
        TemplateTask::create(['project_template_id' => $template->id, 'code' => 'T1', 'title' => 'Template first task', 'start_date' => '2026-11-09', 'end_date' => '2026-11-09', 'start_time' => '09:00', 'end_time' => '17:00', 'sort_order' => 1]);
        $this->post(route('projects.plan.initialize', $p), ['mode' => 'template', 'project_template_id' => $template->id, 'starts_on' => '2026-11-16', 'ends_on' => '2026-11-30'])->assertRedirect();
        $p->refresh();
        $this->assertSame($template->id, $p->project_template_id);
        $this->assertSame('Template first task', $p->tasks()->first()->title);
        $remoteId = '8bb7acb5-9772-465c-971a-03c38cc34f42';
        $graph = \Mockery::mock(AzureGraphService::class);
        $graph->shouldReceive('get')->once()->with($remoteId)->andReturn(['id' => $remoteId, 'displayName' => 'New plan colleague', 'mail' => 'plan-colleague@example.test', 'userPrincipalName' => 'plan-colleague@example.test', 'accountEnabled' => true, 'companyName' => null, 'jobTitle' => null]);
        $this->app->instance(AzureGraphService::class, $graph);
        $this->postJson(route('users.azure.add'), ['azure_id' => $remoteId, 'directory_project_id' => $p->id])->assertOk();
        $new = User::where('email', 'plan-colleague@example.test')->firstOrFail();
        $task = $p->tasks()->first();
        $this->postJson(route('tasks.assign', $task), ['user_id' => $new->id])->assertOk();
        $this->assertTrue($p->memberships()->where('user_id', $new->id)->exists());
        $this->assertTrue($task->assignees()->where('users.id', $new->id)->exists());
        Notification::assertNotSentTo($new, ProjectActivatedNotification::class);
        $this->actingAs($new)->get(route('projects.show', $p))->assertForbidden();
        $this->get(route('tasks.mine'))->assertOk()->assertDontSee('Template first task');
        $this->actingAs($pm)->post(route('projects.plan.publish', $p))->assertRedirect();
        Notification::assertSentToTimes($new, ProjectActivatedNotification::class, 1);
        $this->actingAs($new)->get(route('projects.show', $p))->assertOk();
        $this->assertSame('Template first task', $template->tasks()->first()->title);
    }

    public function test_owner_request_is_in_inbox_without_a_mail_worker(): void
    {
        config(['queue.default' => 'database']);
        $pmo = $this->person('pmo');
        $owner = $this->person();
        $sub = Subsidiary::create(['name' => 'Inbox company', 'code' => 'INB', 'status' => 'active', 'created_by' => $pmo->id]);
        $this->actingAs($pmo)->post(route('projects.initiate'), ['name' => 'Owner inbox request', 'subsidiary_id' => $sub->id, 'assignment_type' => 'owner', 'assignee_id' => $owner->id])->assertRedirect();
        $this->assertSame(1, $owner->notifications()->count());
        $this->assertSame(1, DB::table('jobs')->count());
        $this->actingAs($owner)->get(route('notifications.index'))->assertOk()->assertSee('Project owner approval requested')->assertSee('Owner inbox request');
    }

    public function test_template_browser_is_project_scoped_read_only_searchable_and_paginated(): void
    {
        [$pmo,$owner,$project] = $this->initiate();
        $pm = $this->person();
        $outsider = $this->person();
        $this->actingAs($owner)->post(route('projects.owner.respond', $project), ['decision' => 'accept', 'pm_user_id' => $pm->id])->assertRedirect();
        $this->actingAs($pm)->postJson(route('projects.respond', $project), ['decision' => 'accept'])->assertOk();
        $template = ProjectTemplate::create(['name' => 'Finance rollout', 'status' => 'active', 'created_by' => $pmo->id, 'estimated_duration' => 10, 'duration_unit' => 'working_days', 'working_hours' => '09:00 - 17:00']);
        TemplateTask::create(['project_template_id' => $template->id, 'code' => 'F1', 'title' => 'Review finance scope', 'duration_days' => 2, 'sort_order' => 1]);
        TemplateTask::create(['project_template_id' => $template->id, 'code' => 'F1.1', 'parent_code' => 'F1', 'title' => 'Review accounts', 'duration_days' => 1, 'sort_order' => 2]);
        $inactive = ProjectTemplate::create(['name' => 'Retired plan', 'status' => 'archived', 'created_by' => $pmo->id]);
        foreach (range(1, 13) as $n) {
            ProjectTemplate::create(['name' => 'Other plan '.$n, 'status' => 'active', 'created_by' => $pmo->id]);
        }
        foreach ([$pm, $owner, $pmo] as $lead) {
            $this->actingAs($lead)->getJson(route('projects.plan.templates', [$project, 'q' => 'Finance']))->assertOk()->assertJsonPath('total', 1)->assertJsonPath('items.0.name', 'Finance rollout')->assertJsonPath('items.0.task_count', 2);
            $this->getJson(route('projects.plan.template-preview', [$project, $template]))->assertOk()->assertJsonPath('tasks.0.title', 'Review finance scope')->assertJsonPath('tasks.1.parent_code', 'F1');
        }
        $this->actingAs($pm)->getJson(route('projects.plan.templates', [$project, 'page' => 2]))->assertOk()->assertJsonPath('last_page', 2)->assertJsonPath('total', 14);
        $this->getJson(route('projects.plan.template-preview', [$project, $inactive]))->assertNotFound();
        $this->putJson(route('templates.update', $template), ['name' => 'Changed shared plan'])->assertForbidden();
        $this->actingAs($owner)->putJson(route('templates.update', $template), ['name' => 'Changed shared plan'])->assertForbidden();
        $this->actingAs($outsider)->getJson(route('projects.plan.templates', $project))->assertForbidden();
        $this->getJson(route('projects.plan.template-preview', [$project, $template]))->assertForbidden();
        $this->assertSame(0, $project->tasks()->count());
        $this->assertSame('Finance rollout', $template->fresh()->name);
        $this->assertSame('Review finance scope', $template->tasks()->first()->title);
        $setup = $this->actingAs($pm)->get(route('projects.plan.setup', $project));
        $setup->assertOk()->assertSee('Use a template')->assertSee('plan-setup.js');
        $this->snapshot('workflow-template-browser',$setup->getContent());
    }
    public function test_reassignment_is_hidden_and_blocked_until_rejection():void
    {
        [$pmo,$owner,$project]=$this->initiate();$next=$this->person();
        $this->actingAs($pmo)->get(route('projects.show',$project))->assertOk()->assertDontSee('class="flow-reassign"',false)->assertDontSee('data-show-dialog="assign-pm-dialog"',false);
        $this->postJson(route('projects.workflow.reassign',$project),['assignment_type'=>'owner','assignee_id'=>$next->id])->assertStatus(409);
        $this->actingAs($owner)->post(route('projects.owner.respond',$project),['decision'=>'accept','pm_user_id'=>$next->id])->assertRedirect();
        foreach([$pmo,$owner] as $actor){
            $this->actingAs($actor)->get(route('projects.show',$project))->assertOk()->assertDontSee('class="flow-reassign"',false)->assertDontSee('data-show-dialog="assign-pm-dialog"',false);
            $this->postJson(route('projects.workflow.reassign',$project),['assignment_type'=>'pm','assignee_id'=>$owner->id])->assertStatus(409);
            $this->postJson(route('projects.manager',$project),['pm_user_id'=>$owner->id])->assertStatus($actor->isPmo()?409:403);
        }
        $this->assertSame($next->id,$project->fresh()->pm_user_id);$this->assertSame(1,$project->invitations()->where('status','pending')->count());
        $this->actingAs($next)->post(route('projects.respond',$project),['decision'=>'decline','decline_reason'=>'Cannot take this assignment.'])->assertRedirect();
        $this->actingAs($owner)->get(route('projects.show',$project))->assertOk()->assertSee('class="flow-reassign"',false);
        $this->post(route('projects.workflow.reassign',$project),['assignment_type'=>'pm','assignee_id'=>$owner->id])->assertRedirect();
        $this->actingAs($pmo)->get(route('projects.show',$project))->assertOk()->assertDontSee('class="flow-reassign"',false);
    }


    public function test_leaders_can_create_a_template_and_set_budget_during_plan_setup(): void
    {
        [$pmo,$pm,$p] = $this->initiate('pm');
        $outsider = $this->person();
        $this->actingAs($pm)->postJson(route('projects.respond', $p), ['decision' => 'accept'])->assertOk();
        $payload = [
            'name' => 'Quick launch',
            'category' => 'General',
            'description' => 'From plan setup',
            'estimated_duration' => 7,
            'duration_unit' => 'working_days',
            'select_for_project' => 1,
            'plan_project' => $p->id,
            'starts_on' => '2026-11-12',
            'ends_on' => '2026-11-30',
            'planned_budget' => '250000',
            'tasks' => [
                ['title' => 'Kick-off', 'duration_days' => 2],
                ['title' => 'Build', 'duration_days' => 5],
            ],
        ];

        $this->actingAs($outsider)->get(route('templates.index', ['select_for_project' => 1, 'plan_project' => $p->id]))->assertForbidden();
        $this->actingAs($outsider)->get(route('templates.create', ['select_for_project' => 1, 'plan_project' => $p->id]))->assertForbidden();
        $this->actingAs($outsider)->postJson(route('templates.store'), $payload)->assertForbidden();

        $this->actingAs($pm)->get(route('templates.index', ['select_for_project' => 1, 'plan_project' => $p->id]))->assertOk()->assertSee('+ Create Template');
        $this->actingAs($pm)->get(route('templates.create', ['select_for_project' => 1, 'plan_project' => $p->id]))->assertOk();

        $res = $this->actingAs($pm)->postJson(route('templates.store'), $payload)->assertOk()->assertJsonPath('success', true);
        $tplId = $res->json('template_id');
        $expectedRedirect = route('projects.plan.setup', ['project' => $p->id, 'starts_on' => '2026-11-12', 'ends_on' => '2026-11-30', 'planned_budget' => '250000', 'template_id' => $tplId]);
        $this->assertSame($expectedRedirect, $res->json('redirect'));

        $tpl = ProjectTemplate::findOrFail($tplId);
        $this->assertSame('active', $tpl->status);
        $this->assertSame(2, $tpl->tasks()->count());

        $setup = $this->actingAs($pm)->get($expectedRedirect);
        $setup->assertOk()->assertSee('Quick launch')->assertSee('Project budget');

        $this->actingAs($pm)->post(route('projects.plan.initialize', $p), ['mode' => 'template', 'project_template_id' => $tpl->id, 'starts_on' => '2026-11-12', 'ends_on' => '2026-11-30', 'planned_budget' => '250000'])->assertRedirect();
        $this->assertSame('250000.00', (string) $p->fresh()->planned_budget);
        $this->assertSame(2, $p->tasks()->count());
        $this->assertSame(2, $tpl->fresh()->tasks()->count());

        $this->actingAs($pm)->post(route('projects.plan.publish', $p))->assertRedirect();
        $this->actingAs($pm)->get(route('templates.create', ['select_for_project' => 1, 'plan_project' => $p->id]))->assertForbidden();
        $this->actingAs($pm)->postJson(route('templates.store'), $payload)->assertForbidden();
    }
}
