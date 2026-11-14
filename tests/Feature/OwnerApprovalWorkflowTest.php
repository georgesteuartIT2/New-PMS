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

    public function test_edit_project_dialog_matches_governance_workflow(): void
    {
        [$pmo, $owner, $project] = $this->initiate();
        $pm = $this->person();
        $role = \App\Models\ProjectRole::where('slug', '!=', 'pm')->first();

        // 1. Stage: Owner Review
        $editOwnerStage = $this->actingAs($pmo)->get(route('projects.edit', [$project, 'popup' => 1]))->assertOk();
        $editOwnerStage->assertSee($owner->name)
            ->assertSee('Project Owner')
            ->assertSee('Owner review')
            ->assertSee('Awaiting Owner approval');

        // Owner approves and assigns PM
        $this->actingAs($owner)->post(route('projects.owner.respond', $project), ['decision' => 'accept', 'pm_user_id' => $pm->id])->assertRedirect();

        // 2. Stage: PM Review
        $editPmStage = $this->actingAs($pmo)->get(route('projects.edit', [$project, 'popup' => 1]))->assertOk();
        $editPmStage->assertSee($owner->name)
            ->assertSee($pm->name)
            ->assertSee('PM review')
            ->assertSee('Awaiting PM acceptance');

        // PM accepts assignment
        $this->actingAs($pm)->post(route('projects.respond', $project), ['decision' => 'accept'])->assertRedirect();
        $this->actingAs($pm)->post(route('projects.plan.initialize', $project), [
            'mode' => 'blank',
            'starts_on' => '2026-11-15',
            'ends_on' => '2026-11-30',
            'planned_budget' => '180000',
        ])->assertRedirect();

        // 3. Stage: Planning Stage
        $editPlanning = $this->actingAs($pm)->get(route('projects.edit', [$project, 'popup' => 1]))->assertOk();
        $editPlanning->assertSee($owner->name)
            ->assertSee('Project Owner')
            ->assertSee($pm->name)
            ->assertSee('Project Manager')
            ->assertSee('Planning stage')
            ->assertSee('Planning (Unpublished)')
            ->assertSee('Review & publish', false);

        // PM edits project details during planning - status stays 'active'
        $this->actingAs($pm)->putJson(route('projects.update', $project), [
            'name' => 'Renamed Planning Project',
            'description' => 'Updated scope during planning',
            'starts_on' => '2026-11-15',
            'ends_on' => '2026-11-30',
            'status' => 'active',
            'planned_budget' => '200000.00',
        ])->assertOk();
        $this->assertSame('Renamed Planning Project', $project->fresh()->name);
        $this->assertSame('200000.00', (string) $project->fresh()->planned_budget);

        // Disallow adding Project Owner as a generic team member
        if ($role) {
            $this->actingAs($pm)->putJson(route('projects.update', $project), [
                'name' => 'Renamed Planning Project',
                'starts_on' => '2026-11-15',
                'ends_on' => '2026-11-30',
                'status' => 'active',
                'edit_team' => 1,
                'members' => [
                    ['user_id' => $owner->id, 'project_role_id' => $role->id],
                ],
            ])->assertStatus(422);
        }
    }

    public function test_optional_pmo_plan_and_roles_survive_acceptance_without_duplicate_tasks_or_early_mail(): void
    {
        [$pmo,$pm,$unused,$sub]=$this->initiate('pm');
        $member=$this->person();$other=$this->person();
        $role=ProjectRole::create(['name'=>'Delivery contributor','slug'=>'delivery-contributor']);
        $template=ProjectTemplate::create(['name'=>'Optional starting plan','status'=>'active','created_by'=>$pmo->id,'estimated_duration'=>2,'duration_unit'=>'working_days','working_hours'=>'09:00 - 17:00']);
        TemplateTask::create(['project_template_id'=>$template->id,'code'=>'A','title'=>'Prepared task','duration_days'=>2,'sort_order'=>1]);
        $this->actingAs($pmo)->post(route('projects.initiate'),['name'=>'Prepared delivery','subsidiary_id'=>$sub->id,'assignment_type'=>'pm','assignee_id'=>$pm->id,'plan_mode'=>'template','project_template_id'=>$template->id,'starts_on'=>'2026-11-12','ends_on'=>'2026-11-30','members'=>[['user_id'=>$member->id,'project_role_id'=>$role->id]]])->assertRedirect();
        $p=Project::latest('id')->first();$this->assertTrue((bool)$p->plan_initialized);$this->assertSame(1,$p->tasks()->count());
        $this->assertDatabaseHas('project_memberships',['project_id'=>$p->id,'user_id'=>$member->id,'project_role_id'=>$role->id]);
        Notification::assertNotSentTo($member,ProjectActivatedNotification::class);
        $this->actingAs($pm)->postJson(route('projects.respond',$p),['decision'=>'accept'])->assertOk();
        $page=$this->get(route('projects.plan.setup',$p))->assertOk()->assertSee('Resume plan setup')->assertSee('Project roles');
        $this->snapshot('workflow-optional-plan',$page->getContent());
        $payload=['planned_budget'=>10000,'members'=>[['user_id'=>$other->id,'project_role_id'=>$role->id]]];
        $this->actingAs($member)->post(route('projects.plan.options',$p),$payload)->assertForbidden();
        $this->actingAs($pm)->post(route('projects.plan.options',$p),$payload)->assertRedirect();
        $this->post(route('projects.plan.options',$p),$payload)->assertRedirect();
        $this->assertSame(1,$p->tasks()->count());$this->assertSame(1,ProjectMembership::where('project_id',$p->id)->where('user_id',$other->id)->count());
        $this->assertEquals(10000,$p->fresh()->planned_budget);Notification::assertNotSentTo($other,ProjectActivatedNotification::class);
    }

    public function test_optional_roles_are_validated_and_blank_plan_remains_optional(): void
    {
        [$pmo,$pm,$p]=$this->initiate('pm');$this->assertFalse((bool)$p->plan_initialized);
        $this->actingAs($pm)->postJson(route('projects.respond',$p),['decision'=>'accept'])->assertOk();
        $member=$this->person();$reserved=ProjectRole::where('slug','pm')->first();
        $this->postJson(route('projects.plan.initialize',$p),['mode'=>'blank','starts_on'=>'2026-11-12','ends_on'=>'2026-11-30','members'=>[['user_id'=>$member->id,'project_role_id'=>$reserved->id]]])->assertUnprocessable();
        $this->assertFalse((bool)$p->fresh()->plan_initialized);
        $this->post(route('projects.plan.initialize',$p),['mode'=>'blank','starts_on'=>'2026-11-12','ends_on'=>'2026-11-30'])->assertRedirect();
        $this->assertTrue((bool)$p->fresh()->plan_initialized);$this->assertSame(0,$p->tasks()->count());
    }

    public function test_optional_blank_editor_tasks_are_saved_only_in_project_and_cycles_are_rejected(): void
    {
        [$pmo,$pm,$unused,$sub]=$this->initiate('pm');
        $task=['code'=>'1','title'=>'Manual kickoff','duration_days'=>1,'start_date'=>'2026-11-12','end_date'=>'2026-11-12','start_time'=>'09:00','end_time'=>'17:00'];
        $data=['name'=>'Blank edited project','subsidiary_id'=>$sub->id,'assignment_type'=>'pm','assignee_id'=>$pm->id,'plan_mode'=>'blank','starts_on'=>'2026-11-12','ends_on'=>'2026-11-30','blank_plan'=>json_encode(['reference_start_date'=>'2026-11-12','tasks'=>[$task]])];
        $this->actingAs($pmo)->post(route('projects.initiate'),$data)->assertRedirect();
        $p=Project::latest('id')->first();$this->assertNull($p->project_template_id);$this->assertSame('Manual kickoff',$p->tasks()->first()->title);$this->assertSame(0,ProjectTemplate::count());
        $task['dependency_code']='1';$data['blank_plan']=json_encode(['tasks'=>[$task]]);$before=Project::count();
        $this->postJson(route('projects.initiate'),$data)->assertUnprocessable();$this->assertSame($before,Project::count());
    }

    public function test_pm_can_customize_a_template_copy_without_changing_shared_plan(): void
    {
        [$pmo,$pm,$p]=$this->initiate('pm');
        $this->actingAs($pm)->postJson(route('projects.respond',$p),['decision'=>'accept'])->assertOk();
        $original=ProjectTemplate::create(['name'=>'Shared baseline','status'=>'active','created_by'=>$pmo->id,'estimated_duration'=>2,'duration_unit'=>'working_days']);
        TemplateTask::create(['project_template_id'=>$original->id,'code'=>'1','title'=>'Shared original task','duration_days'=>2,'sort_order'=>1]);
        $page=$this->get(route('templates.index',['select_for_project'=>1,'plan_project'=>$p->id]))->assertOk()->assertSee('canEditSelection: true',false);
        $this->snapshot('workflow-editable-template',$page->getContent());
        $payload=['name'=>'My project plan','category'=>'General','select_for_project'=>1,'plan_project'=>$p->id,'estimated_duration'=>2,'duration_unit'=>'working_days','tasks'=>[['code'=>'1','title'=>'Customized task','duration_days'=>2]]];
        $response=$this->postJson(route('templates.store'),$payload)->assertOk();
        $id=$response->json('template_id');$this->assertNotEquals($original->id,$id);
        $this->assertSame('Shared original task',$original->tasks()->first()->title);
        $this->assertDatabaseHas('template_tasks',['project_template_id'=>$id,'title'=>'Customized task']);
    }

    public function test_project_leads_show_owner_before_pm_and_both_after_owner_approval(): void
    {
        [$pmo,$owner,$p]=$this->initiate();$owner->update(['name'=>'Owner lead example']);
        $this->actingAs($pmo)->get(route('projects.index'))->assertOk()->assertSee('Project leads')->assertSee('Owner lead example');
        $pm=$this->person();$pm->update(['name'=>'Manager lead example']);
        $this->actingAs($owner)->post(route('projects.owner.respond',$p),['decision'=>'accept','pm_user_id'=>$pm->id])->assertRedirect();
        $this->actingAs($pmo)->get(route('projects.index'))->assertOk()->assertSee('Owner lead example')->assertSee('Manager lead example');
        $this->get(route('projects.index',['search'=>'Owner lead example']))->assertOk()->assertSee($p->name);
    }

    public function test_pm_excel_assignee_roundtrip_assigns_tasks_and_members_only_to_the_project(): void
    {
        [$pmo,$pm,$p]=$this->initiate('pm');$member=$this->person();
        $this->actingAs($pm)->postJson(route('projects.respond',$p),['decision'=>'accept'])->assertOk();
        $details=['name'=>'Assigned plan','plan_project'=>$p->id,'estimated_duration'=>2,'duration_unit'=>'working_days','reference_start_date'=>'2026-11-12','working_hours'=>'09:00 - 17:00'];
        $task=['code'=>'1','title'=>'Assigned task','duration_days'=>1,'start_date'=>'2026-11-12','end_date'=>'2026-11-12','start_time'=>'09:00','end_time'=>'17:00','assignee_email'=>$member->email];
        $service=app(\App\Services\TemplateSpreadsheetService::class);$book=$service->blankWorkbook($details,[$task]);
        $this->assertSame('Assignee (Email)',$book->getSheetByName('TASKS')->getCell('J1')->getValue());
        $this->assertSame($member->email,$book->getSheetByName('TASKS')->getCell('J2')->getValue());
        $path=tempnam(sys_get_temp_dir(),'plan_xlsx_');(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);
        try{$upload=new \Illuminate\Http\UploadedFile($path,'assigned-plan.xlsx','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',null,true);$this->post(url('/templates/import-preview'),array_merge($details,['file'=>$upload]),['Accept'=>'application/json'])->assertOk()->assertJsonPath('success',true)->assertJsonPath('tasks.0.assignee_id',$member->id);}finally{@unlink($path);}

        $text="Task Code\tTask Name\tStart Date\tEnd Date\tDuration\tAssignee (Email)\n1\tAssigned task\t2026-11-12\t2026-11-12\t1\t".$member->email;
        $preview=$this->postJson(url('/templates/import-preview'),array_merge($details,['raw_text'=>$text]))->assertOk()->assertJsonPath('success',true)->assertJsonPath('tasks.0.assignee_id',$member->id);
        $tasks=$preview->json('tasks');
        $saved=$this->postJson(route('templates.store'),array_merge($details,['category'=>'General','select_for_project'=>1,'tasks'=>$tasks]))->assertOk();$id=$saved->json('template_id');
        $this->assertEquals($member->id,$p->fresh()->plan_assignment_drafts[$id]['1']);
        $this->flushSession();
        $this->post(route('projects.plan.initialize',$p),['mode'=>'template','project_template_id'=>$id,'starts_on'=>'2026-11-12','ends_on'=>'2026-11-30'])->assertRedirect();
        $task=$p->tasks()->first();$this->assertTrue($task->assignees()->where('users.id',$member->id)->exists());
        $this->assertTrue($p->memberships()->where('user_id',$member->id)->exists());Notification::assertNotSentTo($member,ProjectActivatedNotification::class);
        $this->postJson(url('/templates/import-preview'),array_merge($details,['raw_text'=>str_replace($member->email,'unknown@example.test',$text)]))->assertJsonPath('success',false);
        $outsider=$this->person();$this->actingAs($outsider)->postJson(route('templates.store'),array_merge($details,['category'=>'General','tasks'=>$tasks]))->assertForbidden();
    }

    public function test_publish_review_lists_recipients_and_unassigned_tasks():void {
        [$pmo,$pm,$p]=$this->initiate('pm');$this->actingAs($pm)->postJson(route('projects.respond',$p),['decision'=>'accept'])->assertOk();
        $this->post(route('projects.plan.initialize',$p),['mode'=>'blank','starts_on'=>'2026-11-12','ends_on'=>'2026-11-30'])->assertRedirect();
        Task::create(['project_id'=>$p->id,'code'=>'1','title'=>'Needs owner','starts_at'=>'2026-11-12 09:00','ends_at'=>'2026-11-12 17:00','created_by'=>$pm->id]);
        $reviewPage=$this->get(route('projects.show',$p));$this->snapshot('publish-review-page',$reviewPage->getContent());$reviewPage->assertOk()->assertSee('Review &amp; publish',false)->assertSee('tasks still need an assignee')->assertSee('Needs owner')->assertSee('emails are queued');
    }

    public function test_review_popup_opens_after_continue_to_tasks_when_plan_has_tasks():void {
        [$pmo,$pm,$p]=$this->initiate('pm');$this->actingAs($pm)->postJson(route('projects.respond',$p),['decision'=>'accept'])->assertOk();
        $this->post(route('projects.plan.initialize',$p),['mode'=>'blank','starts_on'=>'2026-11-12','ends_on'=>'2026-11-30'])->assertRedirect()->assertSessionHas('open_publish_review');
        $this->get(route('projects.show',$p))->assertOk()->assertSee('Ready to publish your plan?')->assertDontSee("d.showModal()",false)->assertSee('Your plan has no tasks yet')->assertSee('Review &amp; publish',false);
        Task::create(['project_id'=>$p->id,'code'=>'1','title'=>'First task','starts_at'=>'2026-11-12 09:00','ends_at'=>'2026-11-12 17:00','created_by'=>$pm->id]);
        $this->withSession(['open_publish_review'=>true])->get(route('projects.show',$p))->assertOk()->assertSee("d.showModal()",false)->assertSee('Assignment coverage')->assertSee('Publish &amp; notify team',false);
    }
}