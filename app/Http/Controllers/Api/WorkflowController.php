<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use App\Services\DagParser;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException; // tambah di bagian atas

class WorkflowController extends Controller
{
    // GET /api/workflows
    public function index(Request $request)
    {
        $tenantId = $request->_tenant_id;

        $workflows = WorkflowDefinition::forTenant($tenantId)
            ->with('activeVersion')
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->trigger_type, fn($q) => $q->where('trigger_type', $request->trigger_type))
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($workflows);
    }

    // POST /api/workflows
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                 => 'required|string|max:255',
            'description'          => 'nullable|string',
            'trigger_type'         => 'required|in:manual,cron,webhook',
            'cron_expression'      => 'required_if:trigger_type,cron|nullable|string',
            'dag_definition'       => 'required|array',
            'dag_definition.steps' => 'required|array|min:1',
            'dag_definition.edges' => 'nullable|array',
        ]);

        // Validasi DAG — tangkap exception dan ubah jadi 422
        try {
            (new DagParser)->parse($validated['dag_definition'])->validate();
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'dag_definition' => $e->getMessage(),
            ]);
        }

        $workflow = WorkflowDefinition::create([
            'id'              => Str::uuid(),
            'tenant_id'       => $request->_tenant_id,
            'name'            => $validated['name'],
            'description'     => $validated['description'] ?? null,
            'trigger_type'    => $validated['trigger_type'],
            'cron_expression' => $validated['cron_expression'] ?? null,
            'current_version' => 1,
        ]);

        WorkflowVersion::create([
            'id'             => Str::uuid(),
            'workflow_id'    => $workflow->id,
            'version'        => 1,
            'dag_definition' => $validated['dag_definition'],
            'is_active'      => true,
        ]);

        return response()->json($workflow->load('activeVersion'), 201);
    }

    // GET /api/workflows/{id}
    public function show(Request $request, string $id)
    {
        $workflow = WorkflowDefinition::forTenant($request->_tenant_id)
            ->with(['versions', 'activeVersion'])
            ->findOrFail($id);

        return response()->json($workflow);
    }

    // PUT /api/workflows/{id}
    public function update(Request $request, string $id)
    {
        $workflow = WorkflowDefinition::forTenant($request->_tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'name'                 => 'sometimes|string|max:255',
            'description'          => 'nullable|string',
            'dag_definition'       => 'sometimes|array',
            'dag_definition.steps' => 'required_with:dag_definition|array|min:1',
            'dag_definition.edges' => 'nullable|array',
        ]);

        if (isset($validated['dag_definition'])) {
            // Validasi DAG — tangkap exception dan ubah jadi 422
            try {
                (new DagParser)->parse($validated['dag_definition'])->validate();
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages([
                    'dag_definition' => $e->getMessage(),
                ]);
            }

            $workflow->versions()->update(['is_active' => false]);
            $newVersion = $workflow->current_version + 1;

            WorkflowVersion::create([
                'id'             => Str::uuid(),
                'workflow_id'    => $workflow->id,
                'version'        => $newVersion,
                'dag_definition' => $validated['dag_definition'],
                'is_active'      => true,
            ]);

            $workflow->current_version = $newVersion;
        }

        $workflow->fill(collect($validated)->except('dag_definition')->toArray());
        $workflow->save();

        return response()->json($workflow->load('activeVersion'));
    }

    // DELETE /api/workflows/{id}
    public function destroy(Request $request, string $id)
    {
        $workflow = WorkflowDefinition::forTenant($request->_tenant_id)->findOrFail($id);
        $workflow->delete();
        return response()->json(null, 204);
    }

    // POST /api/workflows/{id}/rollback/{version}
    public function rollback(Request $request, string $id, int $version)
    {
        $workflow = WorkflowDefinition::forTenant($request->_tenant_id)->findOrFail($id);

        $targetVersion = $workflow->versions()->where('version', $version)->firstOrFail();

        $workflow->versions()->update(['is_active' => false]);
        $targetVersion->update(['is_active' => true]);
        $workflow->update(['current_version' => $version]);

        return response()->json(['message' => "Rolled back to version {$version}."]);
    }
}
