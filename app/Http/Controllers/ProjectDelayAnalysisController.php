<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\{Access, ProjectDelayDiagnosisService};
use Illuminate\Http\Request;

class ProjectDelayAnalysisController
{
    public function show(Request $request, Project $project, ProjectDelayDiagnosisService $diagnosisService, Access $access)
    {
        $user = $request->user();
        abort_unless($user && $user->isActive(), 403);

        // Strict Role-Based Authorization:
        // PMO Admin can review all company projects;
        // Project Manager can review assigned projects;
        // Authorized users with effective project.view or projects.view_all permission can review.
        $canReview = $user->isPmo() ||
            ($project->pm_user_id === $user->id) ||
            $access->project($user, $project, 'project.view') ||
            $access->hasCompanyPermission($user, 'projects.view_all');

        abort_unless($canReview, 403, 'You do not have permission to view delay investigation for this project.');

        $responsePayload = $diagnosisService->diagnose($project, $user);

        return response()->json($responsePayload);
    }
}
