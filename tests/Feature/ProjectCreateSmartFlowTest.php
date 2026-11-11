<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{User, Project, Task, ProjectTemplate, TemplateTask, ProjectRole, Subsidiary, ProjectMembership, SocialAccount};
use App\Services\AzureGraphService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class ProjectCreateSmartFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ProjectRole::firstOrCreate(['slug' => 'pm'], ['name' => 'Project Manager', 'is_custom' => false]);
        ProjectRole::firstOrCreate(['slug' => 'team'], ['name' => 'Team Member', 'is_custom' => false]);
        ProjectRole::firstOrCreate(['slug' => 'ps'], ['name' => 'Project Sponsor', 'is_custom' => false]);
        ProjectRole::firstOrCreate(['slug' => 'po'], ['name' => 'Project Owner', 'is_custom' => false]);
        ProjectRole::firstOrCreate(['slug' => 'steering'], ['name' => 'Steering Committee', 'is_custom' => false]);
    }

    private function createUser(string $name, string $role = 'user'): User
    {
        return User::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '', $name)) . rand(1000, 9999) . '@example.test',
            'password' => 'secret123',
            'system_role' => $role,
            'account_status' => 'active',
            'must_change_password' => false,
        ]);
    }

    public function test_project_create_collects_essentials_and_first_reviewer_before_planning(): void
    {
        $pmo = $this->createUser('PMO Lead', 'pmo');
        $employee = $this->createUser('Kamal Silva', 'user');

        $sub = Subsidiary::create([
            'name' => 'GS Technology',
            'code' => 'GST',
            'status' => 'active',
        ]);

        $tpl = ProjectTemplate::create([
            'name' => 'Agile Sprint Template',
            'category' => 'IT & Software',
            'estimated_duration' => 10,
            'duration_unit' => 'working_days',
            'reference_start_date' => '2026-11-02',
            'status' => 'active',
            'created_by' => $pmo->id,
        ]);

        $response = $this->actingAs($pmo)->get(route('projects.create'));

        $response->assertStatus(200);
        $response->assertSee('New project');
        $response->assertSee('Project name');
        $response->assertSee('Subsidiary');
        $response->assertSee('Description');
        $response->assertSee('Project Owner');
        $response->assertSee('Project Manager');
        $response->assertSee('Kamal Silva');
        $response->assertSee('data-directory-picker', false);
        $response->assertSee('Create &amp; send request', false);
        $response->assertDontSee('window.TEMPLATES_CACHE', false);
        $response->assertSee('Agile Sprint Template')->assertSee('Let the PM prepare the plan later');
    }

    public function test_templates_index_in_project_selection_mode_shows_banner_and_continue_actions(): void
    {
        $pmo = $this->createUser('PMO Lead', 'pmo');

        $tpl = ProjectTemplate::create([
            'name' => 'Warehouse Setup Template',
            'category' => 'Logistics',
            'estimated_duration' => 14,
            'duration_unit' => 'working_days',
            'status' => 'active',
            'created_by' => $pmo->id,
        ]);

        $response = $this->actingAs($pmo)->get(route('templates.index', ['select_for_project' => 1]));

        $response->assertStatus(200);
        $response->assertSee('Select a Template for Your New Project');
        $response->assertSee('Continue with Template →');
        $response->assertSee('← Return to Create Project');
        $response->assertSee(route('projects.create', ['template_id' => $tpl->id, 'mode' => 'template']));
    }

    public function test_project_creation_with_template_shifts_schedule_and_assigns_pm_and_team(): void
    {
        $pmo = $this->createUser('PMO Director', 'pmo');
        $pm = $this->createUser('Nimal PM', 'user');
        $teamMember1 = $this->createUser('Amali Member', 'user');
        $teamMember2 = $this->createUser('Kasun Dev', 'user');

        $sub = Subsidiary::create([
            'name' => 'GS Consumer Goods',
            'code' => 'GSC',
            'status' => 'active',
        ]);

        $teamRole = ProjectRole::where('slug', 'team')->first();

        $tpl = ProjectTemplate::create([
            'name' => 'Office Renovation',
            'category' => 'Construction',
            'estimated_duration' => 10,
            'duration_unit' => 'working_days',
            'reference_start_date' => '2026-11-02',
            'status' => 'active',
            'created_by' => $pmo->id,
        ]);

        $tpl->tasks()->create([
            'code' => '1',
            'parent_code' => null,
            'title' => 'Initial Site Demolition',
            'duration_days' => 4,
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-05',
            'sort_order' => 0,
        ]);

        $payload = [
            'name' => 'Colombo Office Modernization',
            'subsidiary_id' => $sub->id,
            'starts_on' => '2026-12-01',
            'ends_on' => '2026-12-14',
            'mode' => 'template',
            'project_template_id' => $tpl->id,
            'pm_user_id' => $pm->id,
            'action' => 'create',
            'members' => [
                ['user_id' => $teamMember1->id, 'project_role_id' => $teamRole->id],
                ['user_id' => $teamMember2->id, 'project_role_id' => $teamRole->id],
            ]
        ];

        $response = $this->actingAs($pmo)->post(route('projects.store'), $payload);

        $response->assertRedirect();

        $project = Project::where('name', 'Colombo Office Modernization')->first();
        $this->assertNotNull($project);
        $this->assertEquals($pm->id, $project->pm_user_id);
        $this->assertEquals($tpl->id, $project->project_template_id);

        // Check task dates shifted to project start (01 Dec 2026)
        $this->assertEquals(1, $project->tasks()->count());
        $task = $project->tasks()->first();
        $this->assertEquals('2026-12-01', $task->starts_at->format('Y-m-d'));

        // Check project memberships
        $this->assertDatabaseHas('project_memberships', [
            'project_id' => $project->id,
            'user_id' => $pm->id,
        ]);
        $this->assertDatabaseHas('project_memberships', [
            'project_id' => $project->id,
            'user_id' => $teamMember1->id,
            'project_role_id' => $teamRole->id,
        ]);
        $this->assertDatabaseHas('project_memberships', [
            'project_id' => $project->id,
            'user_id' => $teamMember2->id,
            'project_role_id' => $teamRole->id,
        ]);
    }

    public function test_azure_search_returns_json_results_and_is_authorized_for_pmo(): void
    {
        $pmo = $this->createUser('PMO Lead', 'pmo');

        // Mock AzureGraphService
        $mockGraph = Mockery::mock(AzureGraphService::class);
        $mockGraph->shouldReceive('configured')->andReturn(true);
        $mockGraph->shouldReceive('search')->with('chamara')->andReturn([
            [
                'id' => '11111111-2222-3333-4444-555555555555',
                'displayName' => 'Chamara Silva',
                'mail' => 'chamara@georgesteuart.lk',
                'userPrincipalName' => 'chamara@georgesteuart.lk',
                'companyName' => 'GS Technology',
                'jobTitle' => 'Lead Engineer',
                'accountEnabled' => true,
            ]
        ]);
        $this->app->instance(AzureGraphService::class, $mockGraph);

        $response = $this->actingAs($pmo)->getJson(route('users.azure', ['q' => 'chamara', 'ajax' => 1]));

        $response->assertStatus(200);
        $response->assertJson([
            'q' => 'chamara',
            'results' => [
                [
                    'id' => '11111111-2222-3333-4444-555555555555',
                    'displayName' => 'Chamara Silva',
                    'email' => 'chamara@georgesteuart.lk',
                ]
            ]
        ]);
    }

    public function test_azure_add_imports_user_into_system_database(): void
    {
        $pmo = $this->createUser('PMO Lead', 'pmo');

        $azureId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

        $mockGraph = Mockery::mock(AzureGraphService::class);
        $mockGraph->shouldReceive('configured')->andReturn(true);
        $mockGraph->shouldReceive('get')->with($azureId)->andReturn([
            'id' => $azureId,
            'displayName' => 'Sunil De Silva',
            'mail' => 'sunil.desilva@georgesteuart.lk',
            'userPrincipalName' => 'sunil.desilva@georgesteuart.lk',
            'companyName' => 'GS Solutions',
            'department' => 'Engineering',
            'jobTitle' => 'Senior Developer',
            'accountEnabled' => true,
        ]);
        $this->app->instance(AzureGraphService::class, $mockGraph);

        $response = $this->actingAs($pmo)->postJson(route('users.azure.add'), [
            'azure_id' => $azureId
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'action' => 'created',
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Sunil De Silva',
            'email' => 'sunil.desilva@georgesteuart.lk',
            'system_role' => 'user',
            'account_status' => 'active',
        ]);

        $this->assertDatabaseHas('social_accounts', [
            'provider' => 'azure',
            'provider_user_id' => $azureId,
        ]);
    }

    public function test_unauthorized_user_cannot_access_azure_endpoints_or_project_create(): void
    {
        $regularUser = $this->createUser('Regular Employee', 'user');

        $this->actingAs($regularUser)->get(route('projects.create'))
            ->assertStatus(403);

        $this->actingAs($regularUser)->getJson(route('users.azure', ['q' => 'test', 'ajax' => 1]))
            ->assertStatus(403);

        $this->actingAs($regularUser)->postJson(route('users.azure.add'), ['azure_id' => '11111111-2222-3333-4444-555555555555'])
            ->assertStatus(403);
    }

    public function test_quick_custom_role_creation_and_assignment_during_project_create(): void
    {
        $pmo = $this->createUser('PMO Lead', 'pmo');
        $pm = $this->createUser('Nimal PM', 'user');
        $qaLead = $this->createUser('Sunil QA', 'user');

        $sub = Subsidiary::create([
            'name' => 'GS Technology',
            'code' => 'GST',
            'status' => 'active',
        ]);

        // 1. Quick create role endpoint
        $resRole = $this->actingAs($pmo)->postJson(route('roles.quick'), [
            'name' => 'QA Lead',
            'permissions' => ['project.view', 'project.gantt_view', 'tasks.view_all', 'tasks.create', 'tasks.edit_assigned']
        ]);

        $resRole->assertStatus(200);
        $resRole->assertJson([
            'success' => true,
            'role' => [
                'name' => 'QA Lead',
                'slug' => 'qa_lead',
            ]
        ]);

        $roleId = $resRole->json('role.id');
        $this->assertDatabaseHas('project_roles', [
            'id' => $roleId,
            'name' => 'QA Lead',
            'slug' => 'qa_lead',
            'is_system' => false,
        ]);

        // 2. Submit project creation with custom role assigned to $qaLead
        $resProj = $this->actingAs($pmo)->post(route('projects.store'), [
            'name' => 'ERP Upgrade Project',
            'subsidiary_id' => $sub->id,
            'starts_on' => now()->format('Y-m-d'),
            'ends_on' => now()->addDays(30)->format('Y-m-d'),
            'mode' => 'blank',
            'pm_user_id' => $pm->id,
            'members' => [
                [
                    'user_id' => $qaLead->id,
                    'project_role_id' => $roleId,
                ]
            ],
            'action' => 'create',
        ]);

        $resProj->assertRedirect();
        $project = Project::where('name', 'ERP Upgrade Project')->firstOrFail();

        $this->assertDatabaseHas('project_memberships', [
            'project_id' => $project->id,
            'user_id' => $qaLead->id,
            'project_role_id' => $roleId,
        ]);

        // 3. Verify permissions via Access service once project is activated
        $project->update(['status' => 'active']);
        $access = app(\App\Services\Access::class);
        $this->assertTrue($access->project($qaLead, $project, 'project.view'));
        $this->assertTrue($access->project($qaLead, $project, 'project.gantt_view'));
    }
}

