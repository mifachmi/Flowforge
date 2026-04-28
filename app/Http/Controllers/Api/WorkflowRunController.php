<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ExecuteWorkflowJob;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowRun;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkflowRunController extends Controller
{
    public function trigger(Request $request, string $id)
    {
        $workflow = WorkflowDefinition::forTenant($request->_tenant_id)
            ->with('activeVersion')  // ← pastikan ini ada
            ->findOrFail($id);

        $run = WorkflowRun::create([
            'id'              => Str::uuid(),
            'workflow_id'     => $workflow->id,
            'tenant_id'       => $request->_tenant_id,
            'version'         => $workflow->current_version,
            'status'          => 'pending',
            'trigger_context' => ['source' => 'manual'],
        ]);

        // Load relasi sebelum dispatch ke queue
        $run->load('workflow.activeVersion');

        ExecuteWorkflowJob::dispatch($run);

        return response()->json($run, 201);
    }


    public function index(Request $request, string $id)
    {
        $workflow = WorkflowDefinition::forTenant($request->_tenant_id)->findOrFail($id);

        $runs = WorkflowRun::where('workflow_id', $workflow->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($runs);
    }

    public function logs(Request $request, string $runId)
    {
        $run = WorkflowRun::where('id', $runId)
            ->where('tenant_id', $request->_tenant_id)
            ->firstOrFail();

        return response()->json($run->load('stepLogs'));
    }

    public function health(Request $request)
    {
        $tenantId = $request->_tenant_id;
        $since    = now()->subHours(24);

        $runs = WorkflowRun::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $since)
            ->get();

        $total      = $runs->count();
        $success    = $runs->where('status', 'success')->count();
        $failed     = $runs->where('status', 'failed')->count();
        $active     = $runs->where('status', 'running')->count();
        $avgMs = $runs
            ->filter(fn($r) => $r->finished_at && $r->started_at)
            ->avg(fn($r) => (int) abs($r->started_at->diffInMilliseconds($r->finished_at)));

        return response()->json([
            'active_runs'    => $active,
            'success_rate'   => $total > 0 ? round($success / $total * 100, 1) : 0,
            'failed_count'   => $failed,
            'avg_duration_ms' => (int) max(0, round($avgMs ?? 0)),  // ← tidak pernah negatif
            'total_runs_24h' => $total,
        ]);
    }

    public function stream(Request $request, string $runId)
    {
        $tenantId = $request->_tenant_id;

        // Validasi dulu sebelum masuk streaming
        $run = WorkflowRun::where('id', $runId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        return response()->stream(function () use ($run) {
            // Bersihkan semua output buffer yang ada
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            // Heartbeat awal — pastikan koneksi tidak langsung drop
            echo " " . json_encode(['type' => 'connected']) . "\n\n";
            flush();

            $maxTime   = 120;
            $startTime = time();
            $lastSent  = null;

            while (true) {
                if (time() - $startTime > $maxTime) break;
                if (connection_aborted()) break;

                $run->refresh();
                $logs = $run->stepLogs()->orderBy('started_at')->get();

                $payload = [
                    'run_status'    => $run->status,
                    'step_statuses' => $logs->mapWithKeys(
                        fn($log) => [$log->step_id => $log->status]
                    )->toArray(),
                ];

                // Kirim hanya kalau ada perubahan
                $encoded = json_encode($payload);
                if ($encoded !== $lastSent) {
                    echo " {$encoded}\n\n"; // ← " " bukan " "
                    flush();
                    $lastSent = $encoded;
                }

                if ($run->isFinished()) break;

                sleep(1);
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }
}
