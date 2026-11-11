<?php
namespace App\Http\Controllers;

use App\Models\{User, SocialAccount, Subsidiary, Project, Task, ProjectTemplate, TaskAttachment, ProjectMembership, ProjectInvitation};
use App\Services\{AzureGraphService, Audit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class UserController {
    public function index(Request $request) {
        $q = trim((string)$request->input('q', ''));
        $subId = $request->input('subsidiary_id');

        $query = User::with('socialAccounts', 'subsidiary');

        if ($q !== '') {
            $query->where(function ($sq) use ($q) {
                $sq->where('name', 'like', '%' . $q . '%')
                    ->orWhere('email', 'like', '%' . $q . '%');
            });
        }

        if ($subId) {
            $query->where('subsidiary_id', $subId);
        }

        $users = $query->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        $subsidiaries = Subsidiary::withCount('users')->where('status', 'active')->orderBy('name')->get();
        $totalUsersCount = User::count();
        $activeUsersCount = User::where('account_status', 'active')->count();
        $pmoAdminCount = User::where('system_role', 'pmo')->count();

        return view('users.index', compact('users', 'subsidiaries', 'totalUsersCount', 'activeUsersCount', 'pmoAdminCount', 'q', 'subId'));
    }

    public function store(Request $request) {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:255|unique:users,email',
            'system_role' => 'nullable|in:user,pmo,admin_viewer',
            'subsidiary_id' => 'nullable|exists:subsidiaries,id',
        ]);

        $password = Str::random(22);
        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower(trim($data['email'])),
            'password' => $password,
            'system_role' => $data['system_role'] ?? 'user',
            'account_status' => 'active',
            'must_change_password' => true,
            'subsidiary_id' => $data['subsidiary_id'] ?? null,
        ]);

        Audit::record('user.created', $user);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'User created successfully.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'system_role' => $user->system_role,
                    'account_status' => $user->account_status,
                    'subsidiary' => $user->subsidiary?->name,
                ],
                'temp_password' => $password,
            ]);
        }

        return redirect()->route('users.index')
            ->with('success', 'User created. Copy the one-time temporary password securely.')
            ->with('temp_user', $user->email)
            ->with('temp_password', $password);
    }

    public function quick(Request $request) {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:255|unique:users,email',
            'system_role' => 'nullable|in:user,pmo,admin_viewer',
            'subsidiary_id' => 'nullable|exists:subsidiaries,id',
        ]);

        $password = Str::random(22);
        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower(trim($data['email'])),
            'password' => $password,
            'system_role' => $data['system_role'] ?? 'user',
            'account_status' => 'active',
            'must_change_password' => true,
            'subsidiary_id' => $data['subsidiary_id'] ?? null,
        ]);

        Audit::record('user.created', $user);

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'temp_password' => $password,
        ]);
    }

    public function update(Request $request, User $user) {
        abort_if($user->id === 1 && $request->input('account_status') === 'inactive', 403);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'system_role' => 'nullable|in:user,pmo,admin_viewer',
            'account_status' => 'sometimes|required|in:active,inactive',
            'subsidiary_id' => 'nullable|exists:subsidiaries,id',
        ]);

        if ($user->isPmo() && isset($data['system_role']) && $data['system_role'] !== 'pmo') {
            $pmoCount = User::where('system_role', 'pmo')->count();
            abort_if($pmoCount <= 1, 403, 'Cannot change role: at least one PMO Administrator must remain in the system.');
        }

        if ($user->isPmo() && isset($data['account_status']) && $data['account_status'] === 'inactive') {
            $activePmoCount = User::where('system_role', 'pmo')->where('account_status', 'active')->count();
            abort_if($activePmoCount <= 1, 403, 'Cannot deactivate the last active PMO Administrator.');
        }

        $user->update($data);
        Audit::record('user.updated', $user);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Updated {$user->name}.",
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'account_status' => $user->account_status,
                    'system_role' => $user->system_role,
                ]
            ]);
        }

        return back()->with('success', 'User updated.');
    }

    public function resetPassword(Request $request, User $user) {
        abort_if(!$user->password, 403);
        $password = Str::random(22);
        $user->update(['password' => $password, 'must_change_password' => true]);
        Audit::record('user.temporary_password_reset', $user);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "New temporary password generated for {$user->name}.",
                'email' => $user->email,
                'temp_password' => $password,
            ]);
        }

        return back()->with('success', 'Temporary password created.')
            ->with('temp_user', $user->email)
            ->with('temp_password', $password);
    }

    public function destroy(Request $request, User $user) {
        abort_if($user->id === 1 || $user->id === $request->user()->id, 403, 'Cannot delete the primary administrator or your own account.');

        if ($user->isPmo()) {
            $pmoCount = User::where('system_role', 'pmo')->count();
            abort_if($pmoCount <= 1, 403, 'Cannot delete the last remaining PMO Administrator account. The system must always have at least one PMO Administrator.');
        }

        DB::transaction(function () use ($user, $request) {
            $actorId = $request->user()->id;

            // Reassign records created by this user to avoid FK constraint issues
            Project::where('created_by', $user->id)->update(['created_by' => $actorId]);
            Task::where('created_by', $user->id)->update(['created_by' => $actorId]);
            ProjectTemplate::where('created_by', $user->id)->update(['created_by' => $actorId]);
            TaskAttachment::where('uploaded_by', $user->id)->update(['uploaded_by' => $actorId]);

            // Clear PM assignment on projects if assigned
            Project::where('pm_user_id', $user->id)->update(['pm_user_id' => null, 'status' => 'draft']);

            // Delete memberships, assignments, invitations and social links
            ProjectMembership::where('user_id', $user->id)->delete();
            ProjectInvitation::where('user_id', $user->id)->delete();
            DB::table('task_assignments')->where('user_id', $user->id)->delete();
            SocialAccount::where('user_id', $user->id)->delete();

            Audit::record('user.deleted', $user);
            $user->delete();
        });

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "User {$user->name} has been deleted."
            ]);
        }

        return redirect()->route('users.index')->with('success', "User {$user->name} has been deleted.");
    }

    public function azure(Request $request, AzureGraphService $graph) {
        $actor = $request->user();
        abort_unless($this->canUseDirectory($request), 403, 'Permission denied.');

        $results = [];
        $error = null;
        $q = trim((string)$request->input('q', ''));

        if ($graph->configured()) {
            try {
                $results = $graph->search($q);
            } catch (Throwable $e) {
                report($e);
                $error = 'Microsoft Azure Graph connection error: ' . $e->getMessage();
            }
        } else {
            $error = 'Microsoft Azure Entra configuration is incomplete. Please check .env credentials.';
        }

        $tenant = config('services.azure.tenant');
        $existing = SocialAccount::where('provider', 'azure')
            ->where('tenant_id', $tenant)
            ->pluck('provider_user_id')
            ->all();

        $emails = array_map(fn($r) => strtolower(trim($r['mail'] ?: ($r['userPrincipalName'] ?? ''))), $results);
        $emailMatches = User::whereIn('email', $emails)->get()->keyBy('email');
        $subsidiaries = Subsidiary::where('status', 'active')->get();

        if ($request->expectsJson() || $request->has('ajax')) {
            return response()->json([
                'q' => $q,
                'error' => $error,
                'results' => array_values(array_map(function($r) use ($existing, $emailMatches) {
                    $email = strtolower(trim($r['mail'] ?: ($r['userPrincipalName'] ?? '')));
                    $isAdded = in_array($r['id'], $existing, true);
                    $hasLocalMatch = $emailMatches->has($email);
                    return [
                        'id' => $r['id'],
                        'displayName' => $r['displayName'] ?? 'Unknown',
                        'jobTitle' => $r['jobTitle'] ?? '',
                        'email' => $email,
                        'company' => $r['companyName'] ?? ($r['department'] ?? ''),
                        'isAdded' => $isAdded,
                        'hasLocalMatch' => $hasLocalMatch,
                    ];
                }, $results)),
            ]);
        }

        return view('users.azure', compact('results', 'error', 'q', 'existing', 'emailMatches', 'subsidiaries'));
    }

    public function addAzure(Request $request, AzureGraphService $graph) {
        $actor = $request->user();
        abort_unless($this->canUseDirectory($request), 403, 'Permission denied.');

        $data = $request->validate(['azure_id' => 'required|uuid']);

        try {
            $remote = $graph->get($data['azure_id']);
        } catch (Throwable $e) {
            report($e);
            if ($request->expectsJson()) return response()->json(['message'=>'Could not verify this Microsoft user. Please try again.'],422);
            return back()->withErrors(['azure_id' => 'Could not verify this Azure user: ' . $e->getMessage()]);
        }

        abort_if(($remote['accountEnabled'] ?? true) === false, 422, 'This Microsoft account is disabled.');

        $tenant = config('services.azure.tenant');
        $email = strtolower(trim($remote['mail'] ?: ($remote['userPrincipalName'] ?? '')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if ($request->expectsJson()) return response()->json(['message'=>'Microsoft did not return a usable email address for this person.'],422);
            return back()->withErrors(['azure_id' => 'No usable email address was returned by Microsoft.']);
        }

        $linkedAccount = SocialAccount::with('user')->where('provider', 'azure')->where('tenant_id', $tenant)->where('provider_user_id', $remote['id'])->first();
        if ($linkedAccount && $linkedAccount->user) {
            if ($request->expectsJson()) return response()->json(['success'=>true,'message'=>'Person already saved in the system.','user'=>$linkedAccount->user,'action'=>'existing']);
            return back()->with('success', 'User already added and linked to Microsoft.');
        }

        $result = DB::transaction(function () use ($remote, $email, $tenant) {
            $company = trim($remote['companyName'] ?? ($remote['department'] ?? ''));
            $sub = null;
            if ($company) {
                $sub = Subsidiary::where('name', $company)
                    ->orWhere('azure_company_name', $company)
                    ->first();
            }

            $user = User::where('email', $email)->first();
            if ($user) {
                SocialAccount::updateOrCreate(
                    ['user_id' => $user->id, 'provider' => 'azure'],
                    ['tenant_id' => $tenant, 'provider_user_id' => $remote['id']]
                );
                if ($sub && !$user->subsidiary_id) {
                    $user->update(['subsidiary_id' => $sub->id, 'company_name' => $company]);
                }
                Audit::record('user.azure_linked', $user);
                return ['user' => $user, 'action' => 'linked'];
            }

            $user = User::create([
                'name' => $remote['displayName'] ?: $email,
                'email' => $email,
                'password' => null,
                'system_role' => 'user',
                'account_status' => 'active',
                'must_change_password' => false,
                'subsidiary_id' => $sub?->id,
                'company_name' => $company ?: null,
            ]);

            SocialAccount::create([
                'user_id' => $user->id,
                'provider' => 'azure',
                'tenant_id' => $tenant,
                'provider_user_id' => $remote['id']
            ]);

            Audit::record('user.azure_imported', $user);
            return ['user' => $user, 'action' => 'created'];
        });

        $msg = $result['action'] === 'linked'
            ? "Linked existing account for {$result['user']->name} to Microsoft Azure."
            : "Successfully added {$result['user']->name} from Microsoft Azure.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'user' => $result['user'],
                'action' => $result['action']
            ]);
        }

        return back()->with('success', $msg);
    }
    private function canUseDirectory(Request $request): bool {
        $actor=$request->user();
        if(!$actor)return false;
        $access=app(\App\Services\Access::class);
        if($actor->isPmo() || $access->hasCompanyPermission($actor,'projects.create') || $access->hasCompanyPermission($actor,'users.manage'))return true;
        $project=\App\Models\Project::find((int)$request->input('directory_project_id'));
        return $project && $project->status!=='archived' && (($project->approval_stage && (int)$project->owner_user_id===$actor->id && $actor->isActive()) || $access->project($actor,$project,'manage_team'));
    }
}
