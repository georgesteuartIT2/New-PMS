<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\ProjectRole;
use App\Models\ProjectTemplate;
use App\Models\Subsidiary;
use App\Models\User;
use App\Notifications\ProjectActivatedNotification;
use App\Notifications\ProjectWorkflowNotification;
use App\Services\Audit;
use App\Services\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectWorkflowController
{
    private function person(int $id): User
    {
        return User::whereKey($id)->where('account_status', 'active')->where('system_role', 'user')->firstOrFail();
    }

    private function membership(Project $p, User $u, string $slug, string $name): void
    {
        $role = ProjectRole::firstOrCreate(['slug' => $slug], ['name' => $name]);
        ProjectMembership::firstOrCreate(['project_id' => $p->id, 'user_id' => $u->id, 'project_role_id' => $role->id]);
    }

    private function leaders(Project $p, User $actor): void
    {
        abort_unless($actor->isActive() && ($actor->isPmo() || ($actor->system_role === 'user' && (int) $p->owner_user_id === $actor->id)), 403);
    }

    private function inform(Project $p, string $message): void
    {
        $ids = User::where('system_role', 'pmo')->where('account_status', 'active')->pluck('id')->push($p->owner_user_id)->filter()->unique();
        foreach (User::whereIn('id', $ids)->where('account_status', 'active')->get() as $u) {
            DB::afterCommit(fn () => $u->notify(new ProjectWorkflowNotification($p, 'decision', $message, (string) $p->updated_at)));
        }
    }

    public function create(Request $r)
    {
        abort_unless($r->user()->isPmo(), 403);

        return view('projects.initiate', ['subsidiaries' => Subsidiary::where('status', 'active')->orderBy('name')->get(), 'users' => User::where('system_role', 'user')->where('account_status', 'active')->orderBy('name')->get()]);
    }

    public function store(Request $r, ProjectService $service)
    {
        abort_unless($r->user()->isPmo(), 403);
        $d = $r->validate(['name' => 'required|string|max:160', 'subsidiary_id' => 'required|exists:subsidiaries,id', 'description' => 'nullable|string|max:3000', 'assignment_type' => 'required|in:owner,pm', 'assignee_id' => 'required|integer|exists:users,id']);
        $person = $this->person($d['assignee_id']);
        $p = DB::transaction(function () use ($d, $person, $r, $service) {
            $sub = Subsidiary::whereKey($d['subsidiary_id'])->where('status', 'active')->lockForUpdate()->firstOrFail();
            $p = Project::create(['name' => $d['name'], 'description' => $d['description'] ?? null, 'subsidiary_id' => $sub->id, 'code' => $sub->nextProjectCode(), 'created_by' => $r->user()->id, 'starts_on' => today(), 'ends_on' => today(), 'status' => 'pending', 'approval_stage' => $d['assignment_type'] === 'owner' ? 'owner' : 'pm', 'owner_user_id' => $d['assignment_type'] === 'owner' ? $person->id : null]);
            if ($d['assignment_type'] === 'owner') {
                $this->membership($p, $person, 'po', 'Project Owner');
                DB::afterCommit(fn () => $person->notify(new ProjectWorkflowNotification($p, 'owner_request', 'PMO created '.$p->name.'. Review the project, choose a Project Manager and approve, or reject with a reason.', (string) $p->updated_at)));
            } else {
                $service->assignManager($p, $person);
            }Audit::record('project.initiated', $p, ['assignment_type' => $d['assignment_type']]);

            return $p;
        });

        return redirect()->route('projects.show', $p)->with('success', 'Project created with code '.$p->code.'. Approval request sent.');
    }

    public function ownerRespond(Request $r, Project $project, ProjectService $service)
    {
        $d = $r->validate(['decision' => 'required|in:accept,decline', 'pm_user_id' => 'required_if:decision,accept|nullable|integer|exists:users,id', 'decline_reason' => 'required_if:decision,decline|nullable|string|min:5|max:1000']);
        DB::transaction(function () use ($r, $project, $d, $service) {
            $p = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            abort_unless($r->user()->isActive() && $r->user()->system_role === 'user' && (int) $p->owner_user_id === $r->user()->id, 403);
            abort_unless($p->approval_stage === 'owner' && $p->status === 'pending', 409, 'This request has already changed.');
            if ($d['decision'] === 'accept') {
                $pm = $this->person($d['pm_user_id']);
                $p->update(['approval_stage' => 'pm', 'approval_reason' => null]);
                $service->assignManager($p, $pm);
                $this->inform($p, $r->user()->name.' approved the project and requested PM acceptance from '.$pm->name.'.');
            } else {
                $p->update(['status' => 'declined', 'approval_stage' => 'owner_declined', 'approval_reason' => $d['decline_reason']]);
                $this->inform($p, 'Project Owner rejected '.$p->name.': '.$d['decline_reason']);
            }Audit::record('project.owner_'.$d['decision'], $p, ['reason' => $d['decline_reason'] ?? null]);
        });

        return redirect()->route('projects.show', $project)->with('success', $d['decision'] === 'accept' ? 'Approved. The Project Manager has been invited.' : 'Rejection sent to PMO.');
    }

    public function reassign(Request $r, Project $project, ProjectService $service)
    {
        $d = $r->validate(['assignment_type' => 'required|in:owner,pm', 'assignee_id' => 'required|integer|exists:users,id']);
        DB::transaction(function () use ($r, $project, $service, $d) {
            $p = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            $this->leaders($p, $r->user());
            abort_unless(($p->status==='declined' && in_array($p->approval_stage, ['owner_declined', 'pm_declined'], true)), 409, 'Reassignment is available only after the current reviewer rejects the request.');
            if (!$r->user()->isPmo())abort_unless($p->approval_stage==='pm_declined',403);
            if ($d['assignment_type'] === 'owner') {
                abort_unless($r->user()->isPmo(), 403);
            }$u = $this->person($d['assignee_id']);
            $p->invitations()->where('status', 'pending')->update(['status' => 'superseded']);
            if ($d['assignment_type'] === 'owner') {
                if ($p->pm_user_id) {
                    $p->memberships()->where('user_id', $p->pm_user_id)->whereHas('role', fn ($q) => $q->where('slug', 'pm'))->delete();
                }$old = $p->owner_user_id;
                if ($old && $old !== $u->id) {
                    $p->memberships()->where('user_id', $old)->whereHas('role', fn ($q) => $q->whereIn('slug', ['po', 'owner']))->delete();
                }$p->update(['owner_user_id' => $u->id, 'pm_user_id' => null, 'status' => 'pending', 'approval_stage' => 'owner', 'approval_reason' => null]);
                $this->membership($p, $u, 'po', 'Project Owner');
                DB::afterCommit(fn () => $u->notify(new ProjectWorkflowNotification($p, 'owner_request', 'Please review '.$p->name.', select a Project Manager and approve or reject.', (string) $p->updated_at)));
            } else {
                $p->update(['approval_stage' => 'pm', 'approval_reason' => null]);
                $service->assignManager($p, $u);
            }$this->inform($p, 'Assignment request sent to '.$u->name.' by '.$r->user()->name.'.');
            Audit::record('project.workflow_reassigned', $p, ['user_id' => $u->id]);
        });

        return redirect()->route('projects.show', $project)->with('success', 'Assignment request sent.');
    }

    public function pmRespond(Request $r, Project $project, ProjectService $service)
    {
        $d = $r->validate(['decision' => 'required|in:accept,decline', 'decline_reason' => 'required_if:decision,decline|nullable|string|min:5|max:1000']);
        DB::transaction(function () use ($r, $project, $d) {
            $p = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            abort_unless($r->user()->isActive() && $r->user()->system_role === 'user' && (int) $p->pm_user_id === $r->user()->id, 403);
            abort_unless($p->approval_stage === 'pm' && $p->status === 'pending', 409, 'This assignment has already changed.');
            $invite = $p->invitations()->where('user_id', $r->user()->id)->where('status', 'pending')->lockForUpdate()->latest('id')->firstOrFail();
            $invite->update(['status' => $d['decision'] === 'accept' ? 'accepted' : 'declined', 'decline_reason' => $d['decline_reason'] ?? null, 'responded_at' => now()]);
            $p->update(['status' => $d['decision'] === 'accept' ? 'active' : 'declined', 'approval_stage' => $d['decision'] === 'accept' ? 'planning' : 'pm_declined', 'approval_reason' => $d['decline_reason'] ?? null]);
            $this->inform($p, $r->user()->name.($d['decision'] === 'accept' ? ' accepted '.$p->name.' and is preparing the plan.' : ' rejected '.$p->name.': '.$d['decline_reason']));
            Audit::record('project.pm_'.$d['decision'], $p, ['reason' => $d['decline_reason'] ?? null]);
        });
        $url = $d['decision'] === 'accept' ? route('projects.plan.setup', $project) : route('dashboard');
        $message = $d['decision'] === 'accept' ? 'Thank you for accepting this project. Choose a plan to get started.' : 'Rejection sent to the Project Owner and PMO.';
        if ($r->expectsJson()) {
            $r->session()->flash('success', $message);

            return response()->json(['message' => $message, 'decision' => $d['decision'], 'redirect' => $url]);
        }

        return redirect($url)->with('success', $message);
    }

    public function setup(Request $r, Project $project)
    {
        $this->planner($r, $project);

        return view('projects.plan-setup', ['project' => $project, 'selectedTemplate' => ProjectTemplate::where('status', 'active')->find((int) ($r->old('project_template_id') ?: $r->query('template_id')))]);
    }

    public function templates(Request $r, Project $project)
    {
        $this->planner($r, $project);
        $d = $r->validate(['q' => 'nullable|string|max:160', 'page' => 'nullable|integer|min:1']);
        $items = ProjectTemplate::where('status', 'active')->when($d['q'] ?? null, fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))->withCount('tasks')->orderBy('name')->orderBy('id')->paginate(12);

        return response()->json(['items' => $items->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'task_count' => $t->tasks_count, 'duration' => $t->estimated_duration.' '.str_replace('_', ' ', $t->duration_unit ?: 'working_days')]), 'page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'total' => $items->total()])->header('Cache-Control', 'private, no-store');
    }

    public function templatePreview(Request $r, Project $project, ProjectTemplate $template)
    {
        $this->planner($r, $project);
        abort_unless($template->status === 'active', 404);
        $template->load(['tasks' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')]);

        return response()->json(['id' => $template->id, 'name' => $template->name, 'description' => $template->description, 'duration' => $template->estimated_duration.' '.str_replace('_', ' ', $template->duration_unit ?: 'working_days'), 'working_hours' => $template->working_hours, 'tasks' => $template->tasks->map(fn ($t) => ['code' => $t->code, 'parent_code' => $t->parent_code, 'title' => $t->title, 'duration_days' => $t->duration_days, 'dependency_code' => $t->dependency_code])])->header('Cache-Control', 'private, no-store');
    }

    private function planner(Request $r, Project $p): void
    {
        abort_unless($r->user()->isActive() && ($r->user()->isPmo() || ($r->user()->system_role === 'user' && in_array($r->user()->id, [$p->pm_user_id, $p->owner_user_id], true))), 403);
        abort_unless($p->approval_stage === 'planning' && $p->status === 'active', 409, 'This project is not awaiting plan setup.');
    }

    public function initialize(Request $r, Project $project, ProjectService $service)
    {
        $d = $r->validate(['mode' => 'required|in:blank,template', 'project_template_id' => 'required_if:mode,template|nullable|integer|exists:project_templates,id', 'starts_on' => 'required|date', 'ends_on' => 'required|date|after_or_equal:starts_on', 'planned_budget' => 'nullable|numeric|min:0|max:9999999999999']);
        DB::transaction(function () use ($r, $project, $d, $service) {
            $p = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            $this->planner($r, $p);
            abort_if($p->plan_initialized || $p->tasks()->exists(), 409, 'The plan has already been started. Continue editing its tasks.');
            $template = $d['mode'] === 'template' ? ProjectTemplate::whereKey($d['project_template_id'])->where('status', 'active')->with('tasks')->firstOrFail() : null;
            $p->update(['starts_on' => $d['starts_on'], 'ends_on' => $d['ends_on'], 'planned_budget' => $d['planned_budget'] ?? null, 'project_template_id' => $template?->id, 'plan_initialized' => true]);
            if ($template) {
                $service->applyTemplate($p, $template, $r->user());
            }Audit::record('project.plan_started', $p);
        });

        return redirect(route('projects.show', $project).'#tasks')->with('success', 'Plan started. Add or import tasks, assign your team, then save and notify them.');
    }

    public function publish(Request $r, Project $project, ProjectService $service)
    {
        DB::transaction(function () use ($r, $project, $service) {
            $p = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            $this->planner($r, $p);
            abort_unless($p->plan_initialized && $p->tasks()->exists(), 422, 'Add or import at least one task before saving the plan.');
            $p->update(['approval_stage' => 'ready', 'plan_published_at' => now()]);
            $service->captureBaseline($p);
            $ids = $p->tasks()->with('assignees')->get()->flatMap(fn ($t) => $t->assignees->pluck('id'))->merge($p->memberships()->pluck('user_id'))->unique()->reject(fn ($id) => (int) $id === (int) $p->pm_user_id);
            foreach (User::whereIn('id', $ids)->where('account_status', 'active')->where('system_role', 'user')->get() as $u) {
                DB::afterCommit(fn () => $u->notify(new ProjectActivatedNotification($p)));
            }Audit::record('project.plan_published', $p);
        });

        return redirect(route('projects.show', $project).'#tasks')->with('success','Plan saved. Your team has been notified.');
    }
}
