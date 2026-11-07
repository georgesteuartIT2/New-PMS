<?php
namespace App\Http\Controllers;

use App\Models\{Subsidiary, Project};
use App\Services\{AzureGraphService, Audit};
use Illuminate\Http\Request;
use Throwable;

class SubsidiaryController {
    public function index(Request $request, AzureGraphService $graph) {
        $subsidiaries = Subsidiary::withCount(['projects', 'users'])->orderBy('name')->get();
        $azureConfigured = $graph->configured();
        $azureCompanies = [];
        $syncError = null;

        if ($request->has('check_azure') && $azureConfigured) {
            try {
                $azureCompanies = $graph->getSubsidiariesFromDirectory();
            } catch (Throwable $e) {
                report($e);
                $syncError = 'Could not read directory entities: ' . $e->getMessage();
            }
        }

        return view('subsidiaries.index', compact('subsidiaries', 'azureConfigured', 'azureCompanies', 'syncError'));
    }

    public function store(Request $request) {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'code' => 'required|string|min:2|max:8|regex:/^[A-Za-z0-9]+$/|unique:subsidiaries,code',
            'azure_company_name' => 'nullable|string|max:160',
            'description' => 'nullable|string|max:1000',
        ]);

        $data['code'] = strtoupper(trim($data['code']));
        $data['created_by'] = $request->user()->id;
        $subsidiary = Subsidiary::create($data);

        Audit::record('subsidiary.created', $subsidiary);
        return redirect()->route('subsidiaries.index')->with('success', "Subsidiary '{$subsidiary->name}' created with prefix [{$subsidiary->code}]. Projects created under this subsidiary will have IDs like {$subsidiary->code}-PRJ-001.");
    }

    public function update(Request $request, Subsidiary $subsidiary) {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'code' => 'required|string|min:2|max:8|regex:/^[A-Za-z0-9]+$/|unique:subsidiaries,code,' . $subsidiary->id,
            'azure_company_name' => 'nullable|string|max:160',
            'description' => 'nullable|string|max:1000',
            'status' => 'required|in:active,inactive',
        ]);

        $data['code'] = strtoupper(trim($data['code']));
        $subsidiary->update($data);

        Audit::record('subsidiary.updated', $subsidiary);
        return back()->with('success', "Subsidiary '{$subsidiary->name}' updated.");
    }

    public function destroy(Subsidiary $subsidiary) {
        if ($subsidiary->projects()->count() > 0) {
            return back()->withErrors(['error' => 'Cannot delete subsidiary with active project history.']);
        }

        Audit::record('subsidiary.deleted', $subsidiary);
        $subsidiary->delete();
        return back()->with('success', 'Subsidiary deleted.');
    }

    public function importAzure(Request $request) {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'code' => 'required|string|min:2|max:8',
            'azure_company_name' => 'required|string|max:160',
        ]);

        $code = strtoupper(trim($data['code']));
        // If code already taken, append a number
        $baseCode = $code;
        $counter = 1;
        while (Subsidiary::where('code', $code)->exists()) {
            $code = substr($baseCode, 0, 6) . $counter++;
        }

        $sub = Subsidiary::updateOrCreate(
            ['azure_company_name' => $data['azure_company_name']],
            [
                'name' => $data['name'],
                'code' => $code,
                'created_by' => $request->user()->id,
                'status' => 'active'
            ]
        );

        Audit::record('subsidiary.imported_azure', $sub);
        return redirect()->route('subsidiaries.index')->with('success', "Imported subsidiary '{$sub->name}' from Azure with prefix [{$sub->code}].");
    }
}

