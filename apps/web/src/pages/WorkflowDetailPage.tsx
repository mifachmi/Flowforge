/* eslint-disable @typescript-eslint/no-explicit-any */
import { useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { workflowApi } from "../api/workflows";
import { DagViewer } from "../components/DagViewer";
import { useWorkflowSocket } from "../hooks/useWorkflowSocket";
import { formatDistanceToNow } from "date-fns";
import { Play } from "lucide-react";

export default function WorkflowDetailPage() {
    const { id } = useParams<{ id: string }>();
    const [activeRunId, setActiveRunId] = useState<string | null>(null);
    const { stepStatuses } = useWorkflowSocket(activeRunId);

    // Perbaikan pada useQuery workflow
    const { data: workflow } = useQuery({
        queryKey: ["workflow", id],
        // Berikan fallback string kosong agar TypeScript yakin ini selalu string
        queryFn: () => workflowApi.get(id ?? ""),
        enabled: !!id,
    });

    // Perbaikan pada useQuery runs
    const { data: runs } = useQuery({
        queryKey: ["runs", id],
        // Berikan fallback string kosong
        queryFn: () => workflowApi.getRuns(id ?? ""),
        refetchInterval: activeRunId ? 3000 : false,
        enabled: !!id,
    });

    const handleTrigger = async () => {
        if (!id) return;

        // Karena di atas sudah ada if (!id) return, TypeScript seharusnya
        // sudah cerdas (Type Narrowing) dan tahu bahwa 'id' di baris ini PASTI string.
        // Tapi jika masih rewel, kamu bisa tambahkan fallback juga:
        const run = await workflowApi.trigger(id ?? "");
        setActiveRunId(run.id);
    };

    const dagDefinition = workflow?.active_version?.dag_definition;

    return (
        <div className="min-h-screen bg-gray-50">
            <header className="bg-white border-b px-6 py-4 flex items-center justify-between">
                <div>
                    <h1 className="text-lg font-bold text-gray-900">
                        {workflow?.name}
                    </h1>
                    <p className="text-xs text-gray-400">
                        v{workflow?.current_version} · {workflow?.trigger_type}
                    </p>
                </div>
                <button
                    onClick={handleTrigger}
                    className="flex items-center gap-2 bg-teal-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-teal-700 transition"
                >
                    <Play size={14} /> Trigger Run
                </button>
            </header>

            <main className="max-w-5xl mx-auto px-6 py-8 space-y-6">
                {/* DAG Viewer — real-time step status */}
                <section>
                    <h2 className="text-sm font-semibold text-gray-500 uppercase mb-3">
                        Workflow DAG
                    </h2>
                    {dagDefinition && (
                        <DagViewer
                            dagDefinition={dagDefinition}
                            stepStatuses={stepStatuses}
                        />
                    )}
                </section>

                {/* Run History */}
                <section>
                    <h2 className="text-sm font-semibold text-gray-500 uppercase mb-3">
                        Run History
                    </h2>
                    <div className="bg-white border rounded-xl overflow-hidden">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50 border-b">
                                <tr>
                                    <th className="text-left px-4 py-3 text-gray-500 font-medium">
                                        Run ID
                                    </th>
                                    <th className="text-left px-4 py-3 text-gray-500 font-medium">
                                        Status
                                    </th>
                                    <th className="text-left px-4 py-3 text-gray-500 font-medium">
                                        Duration
                                    </th>
                                    <th className="text-left px-4 py-3 text-gray-500 font-medium">
                                        Started
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {runs?.data?.map((run: any) => (
                                    <tr
                                        key={run.id}
                                        onClick={() => setActiveRunId(run.id)}
                                        className="hover:bg-gray-50 cursor-pointer"
                                    >
                                        <td className="px-4 py-3 font-mono text-xs text-gray-500">
                                            {run.id.slice(0, 8)}...
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusBadge status={run.status} />
                                        </td>
                                        <td className="px-4 py-3 text-gray-600">
                                            {run.finished_at && run.started_at
                                                ? `${Math.round((new Date(run.finished_at).getTime() - new Date(run.started_at).getTime()) / 1000)}s`
                                                : "—"}
                                        </td>
                                        <td className="px-4 py-3 text-gray-400 text-xs">
                                            {run.started_at
                                                ? formatDistanceToNow(
                                                      new Date(run.started_at),
                                                  ) + " ago"
                                                : "—"}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </div>
    );
}

function StatusBadge({ status }: { status: string }) {
    const styles: Record<string, string> = {
        success: "bg-green-100 text-green-700",
        failed: "bg-red-100 text-red-700",
        running: "bg-blue-100 text-blue-700 animate-pulse",
        pending: "bg-gray-100 text-gray-600",
        timeout: "bg-orange-100 text-orange-700",
    };
    return (
        <span
            className={`text-xs font-medium px-2 py-1 rounded-full ${styles[status] ?? styles.pending}`}
        >
            {status}
        </span>
    );
}
