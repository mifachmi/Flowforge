/* eslint-disable @typescript-eslint/no-explicit-any */
import { useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useNavigate } from "react-router-dom";
import { workflowApi } from "../api/workflows";
import { HealthPanel } from "../components/HealthPanel";
import { formatDistanceToNow } from "date-fns";
import { Play, Plus, X, Trash2, ArrowRight } from "lucide-react";

// ─── Types ───────────────────────────────────────────────────────────────────

type StepType = "http" | "delay" | "script" | "condition";

interface StepForm {
    id: string;
    name: string;
    type: StepType;
    // HTTP
    method: string;
    url: string;
    maxRetries: number;
    // Delay
    seconds: number;
    // Script
    script: string;
}

interface EdgeForm {
    from: string;
    to: string;
}

const defaultStep = (): StepForm => ({
    id: `step${Date.now()}`,
    name: "",
    type: "http",
    method: "GET",
    url: "",
    maxRetries: 3,
    seconds: 2,
    script: "",
});

// ─── Build DAG payload dari form ────────────────────────────────────────────

function buildDagDefinition(steps: StepForm[], edges: EdgeForm[]) {
    return {
        steps: steps.map((s) => {
            const base = { id: s.id, name: s.name, type: s.type };
            if (s.type === "http") {
                return {
                    ...base,
                    config: {
                        method: s.method,
                        url: s.url.trim().replace(/^["']|["']$/g, ""), // ← hapus kutip di awal/akhir
                        max_retries: s.maxRetries,
                    },
                };
            }
            if (s.type === "delay") {
                return { ...base, config: { seconds: s.seconds } };
            }
            if (s.type === "script") {
                return { ...base, config: { script: s.script } };
            }
            return base;
        }),
        edges: edges.filter((e) => e.from && e.to),
    };
}

// ─── Komponen Step Config ────────────────────────────────────────────────────

function StepConfigFields({
    step,
    onChange,
}: {
    step: StepForm;
    onChange: (updated: Partial<StepForm>) => void;
}) {
    if (step.type === "http") {
        return (
            <div className="grid grid-cols-2 gap-2 mt-2">
                <div>
                    <label className="text-xs text-gray-500">Method</label>
                    <select
                        value={step.method}
                        onChange={(e) => onChange({ method: e.target.value })}
                        className="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-teal-500 bg-white"
                    >
                        {["GET", "POST", "PUT", "PATCH", "DELETE"].map((m) => (
                            <option key={m}>{m}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="text-xs text-gray-500">Max Retries</label>
                    <input
                        type="number"
                        min={0}
                        max={10}
                        value={step.maxRetries}
                        onChange={(e) =>
                            onChange({ maxRetries: Number(e.target.value) })
                        }
                        className="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-teal-500"
                    />
                </div>
                <div className="col-span-2">
                    <label className="text-xs text-gray-500">URL</label>
                    <input
                        type="url"
                        value={step.url}
                        onChange={(e) => onChange({ url: e.target.value })}
                        placeholder="https://api.example.com/endpoint"
                        className="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-teal-500"
                    />
                </div>
            </div>
        );
    }

    if (step.type === "delay") {
        return (
            <div className="mt-2">
                <label className="text-xs text-gray-500">Delay (seconds)</label>
                <input
                    type="number"
                    min={1}
                    value={step.seconds}
                    onChange={(e) =>
                        onChange({ seconds: Number(e.target.value) })
                    }
                    className="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-teal-500"
                />
            </div>
        );
    }

    if (step.type === "script") {
        return (
            <div className="mt-2">
                <label className="text-xs text-gray-500">Script</label>
                <textarea
                    value={step.script}
                    onChange={(e) => onChange({ script: e.target.value })}
                    placeholder="echo Hello World"
                    rows={2}
                    className="w-full border border-gray-300 rounded px-2 py-1 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-teal-500"
                />
            </div>
        );
    }

    return null;
}

// ─── Main Page ───────────────────────────────────────────────────────────────

export default function DashboardPage() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    // Modal state
    const [showModal, setShowModal] = useState(false);
    const [newName, setNewName] = useState("");
    const [newTriggerType, setNewTriggerType] = useState<
        "manual" | "cron" | "webhook"
    >("manual");
    const [steps, setSteps] = useState<StepForm[]>([defaultStep()]);
    const [edges, setEdges] = useState<EdgeForm[]>([]);
    const [creating, setCreating] = useState(false);
    const [createError, setCreateError] = useState("");
    const [activeTab, setActiveTab] = useState<"steps" | "edges">("steps");

    const { data, isLoading } = useQuery({
        queryKey: ["workflows"],
        queryFn: () => workflowApi.list(),
        staleTime: 30_000,
    });

    // ─── Handlers ──────────────────────────────────────────────────────────────

    const handleTrigger = async (e: React.MouseEvent, id: string) => {
        e.stopPropagation();
        await workflowApi.trigger(id);
        await queryClient.invalidateQueries({ queryKey: ["workflows"] });
        alert("Workflow triggered!");
    };

    const handleAddStep = () => {
        const newStep = defaultStep();
        setSteps((prev) => [...prev, newStep]);
        // Auto-tambah edge dari step terakhir ke step baru
        if (steps.length > 0) {
            const lastStep = steps[steps.length - 1];
            setEdges((prev) => [
                ...prev,
                { from: lastStep.id, to: newStep.id },
            ]);
        }
    };

    const handleUpdateStep = (index: number, updated: Partial<StepForm>) => {
        setSteps((prev) => {
            const next = [...prev];
            const oldId = next[index].id;
            next[index] = { ...next[index], ...updated };
            // Update edges kalau id berubah
            if (updated.id && updated.id !== oldId) {
                setEdges((prevEdges) =>
                    prevEdges.map((e) => ({
                        from: e.from === oldId ? updated.id! : e.from,
                        to: e.to === oldId ? updated.id! : e.to,
                    })),
                );
            }
            return next;
        });
    };

    const handleRemoveStep = (index: number) => {
        const removedId = steps[index].id;
        setSteps((prev) => prev.filter((_, i) => i !== index));
        setEdges((prev) =>
            prev.filter((e) => e.from !== removedId && e.to !== removedId),
        );
    };

    const handleCloseModal = () => {
        setShowModal(false);
        setNewName("");
        setNewTriggerType("manual");
        setSteps([defaultStep()]);
        setEdges([]);
        setCreateError("");
        setActiveTab("steps");
    };

    const handleCreate = async () => {
        if (!newName.trim()) return;
        setCreating(true);
        setCreateError("");

        try {
            const workflow = await workflowApi.create({
                name: newName.trim(),
                trigger_type: newTriggerType,
                dag_definition: buildDagDefinition(steps, edges),
            });
            await queryClient.invalidateQueries({ queryKey: ["workflows"] });
            handleCloseModal();
            navigate(`/workflows/${workflow.id}`);
        } catch {
            setCreateError("Failed to create workflow. Please try again.");
        } finally {
            setCreating(false);
        }
    };

    // ─── Render ─────────────────────────────────────────────────────────────────

    return (
        <div className="min-h-screen bg-gray-50">
            <header className="bg-white border-b px-6 py-4 flex items-center justify-between">
                <h1 className="text-lg font-bold text-gray-900">FlowForge</h1>
                <button
                    onClick={() => setShowModal(true)}
                    className="flex items-center gap-2 bg-teal-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-teal-700 transition"
                >
                    <Plus size={16} /> New Workflow
                </button>
            </header>

            <main className="max-w-5xl mx-auto px-6 py-8 space-y-6">
                <section>
                    <h2 className="text-sm font-semibold text-gray-500 uppercase mb-3">
                        System Health
                    </h2>
                    <HealthPanel />
                </section>

                <section>
                    <h2 className="text-sm font-semibold text-gray-500 uppercase mb-3">
                        Workflows
                    </h2>

                    {isLoading ? (
                        <div className="space-y-3">
                            {[1, 2, 3].map((i) => (
                                <div
                                    key={i}
                                    className="h-16 bg-gray-200 rounded-xl animate-pulse"
                                />
                            ))}
                        </div>
                    ) : data?.data?.length === 0 ? (
                        <div className="text-center py-16 text-gray-400">
                            <div className="text-4xl mb-3">⚡</div>
                            <p className="font-medium text-gray-600">
                                No workflows yet
                            </p>
                            <p className="text-sm mt-1">
                                Create your first workflow to get started.
                            </p>
                            <button
                                onClick={() => setShowModal(true)}
                                className="mt-4 inline-flex items-center gap-2 bg-teal-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-teal-700 transition"
                            >
                                <Plus size={16} /> New Workflow
                            </button>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {data?.data?.map((wf: any) => (
                                <div
                                    key={wf.id}
                                    className="relative bg-white border rounded-xl px-5 py-4 flex items-center justify-between hover:shadow-sm transition focus-within:ring-2 focus-within:ring-teal-500 focus-within:ring-offset-1"
                                >
                                    <div>
                                        <button
                                            onClick={() =>
                                                navigate(`/workflows/${wf.id}`)
                                            }
                                            className="font-medium text-gray-900 text-left focus:outline-none before:absolute before:inset-0 before:z-0"
                                        >
                                            {wf.name}
                                        </button>
                                        <div className="text-xs text-gray-400 mt-0.5">
                                            v{wf.current_version} ·{" "}
                                            {wf.trigger_type} · updated{" "}
                                            {formatDistanceToNow(
                                                new Date(wf.updated_at),
                                            )}{" "}
                                            ago
                                        </div>
                                    </div>
                                    <button
                                        onClick={(e) => handleTrigger(e, wf.id)}
                                        className="relative z-10 flex items-center gap-1 text-sm text-teal-600 hover:text-teal-700 font-medium focus:outline-none focus:ring-2 focus:ring-teal-500 rounded p-1"
                                    >
                                        <Play size={14} /> Run
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}
                </section>
            </main>

            {/* ─── Modal New Workflow ─────────────────────────────────────────────── */}
            {showModal && (
                <div
                    className="fixed inset-0 bg-black/40 flex items-start justify-center z-50 overflow-y-auto py-8"
                    onClick={(e) =>
                        e.target === e.currentTarget && handleCloseModal()
                    }
                >
                    <div className="bg-white rounded-xl shadow-lg w-full max-w-2xl mx-4">
                        {/* Modal Header */}
                        <div className="flex items-center justify-between px-6 py-4 border-b">
                            <h2 className="text-lg font-semibold text-gray-900">
                                New Workflow
                            </h2>
                            <button
                                onClick={handleCloseModal}
                                className="text-gray-400 hover:text-gray-600"
                            >
                                <X size={20} />
                            </button>
                        </div>

                        <div className="px-6 py-4 space-y-4">
                            {createError && (
                                <div className="p-3 bg-red-50 text-red-700 rounded-lg text-sm">
                                    {createError}
                                </div>
                            )}

                            {/* Nama & Trigger Type */}
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Workflow Name{" "}
                                        <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={newName}
                                        onChange={(e) =>
                                            setNewName(e.target.value)
                                        }
                                        placeholder="e.g. Daily Report Pipeline"
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500"
                                        autoFocus
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Trigger Type
                                    </label>
                                    <select
                                        value={newTriggerType}
                                        onChange={(e) =>
                                            setNewTriggerType(
                                                e.target.value as any,
                                            )
                                        }
                                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white"
                                    >
                                        <option value="manual">Manual</option>
                                        <option value="cron">
                                            Scheduled (Cron)
                                        </option>
                                        <option value="webhook">Webhook</option>
                                    </select>
                                </div>
                            </div>

                            {/* Tabs */}
                            <div className="border-b flex gap-4">
                                {(["steps", "edges"] as const).map((tab) => (
                                    <button
                                        key={tab}
                                        onClick={() => setActiveTab(tab)}
                                        className={`pb-2 text-sm font-medium capitalize border-b-2 transition ${
                                            activeTab === tab
                                                ? "border-teal-600 text-teal-600"
                                                : "border-transparent text-gray-500 hover:text-gray-700"
                                        }`}
                                    >
                                        {tab}
                                        {tab === "steps" && (
                                            <span className="ml-1 text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded-full">
                                                {steps.length}
                                            </span>
                                        )}
                                        {tab === "edges" && (
                                            <span className="ml-1 text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded-full">
                                                {edges.length}
                                            </span>
                                        )}
                                    </button>
                                ))}
                            </div>

                            {/* Tab: Steps */}
                            {activeTab === "steps" && (
                                <div className="space-y-3 max-h-80 overflow-y-auto pr-1">
                                    {steps.map((step, index) => (
                                        <div
                                            key={step.id}
                                            className="border border-gray-200 rounded-lg p-3 bg-gray-50"
                                        >
                                            <div className="flex items-center gap-2">
                                                <span className="text-xs font-mono bg-teal-100 text-teal-700 px-2 py-0.5 rounded">
                                                    #{index + 1}
                                                </span>

                                                {/* Step ID */}
                                                <input
                                                    type="text"
                                                    value={step.id}
                                                    onChange={(e) =>
                                                        handleUpdateStep(
                                                            index,
                                                            {
                                                                id: e.target
                                                                    .value,
                                                            },
                                                        )
                                                    }
                                                    placeholder="step-id"
                                                    className="w-24 border border-gray-300 rounded px-2 py-1 text-xs font-mono focus:outline-none focus:ring-1 focus:ring-teal-500"
                                                />

                                                {/* Step Name */}
                                                <input
                                                    type="text"
                                                    value={step.name}
                                                    onChange={(e) =>
                                                        handleUpdateStep(
                                                            index,
                                                            {
                                                                name: e.target
                                                                    .value,
                                                            },
                                                        )
                                                    }
                                                    placeholder="Step name"
                                                    className="flex-1 border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-teal-500"
                                                />

                                                {/* Step Type */}
                                                <select
                                                    value={step.type}
                                                    onChange={(e) =>
                                                        handleUpdateStep(
                                                            index,
                                                            {
                                                                type: e.target
                                                                    .value as StepType,
                                                            },
                                                        )
                                                    }
                                                    className="border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-teal-500 bg-white"
                                                >
                                                    <option value="http">
                                                        HTTP
                                                    </option>
                                                    <option value="delay">
                                                        Delay
                                                    </option>
                                                    <option value="script">
                                                        Script
                                                    </option>
                                                    <option value="condition">
                                                        Condition
                                                    </option>
                                                </select>

                                                <button
                                                    onClick={() =>
                                                        handleRemoveStep(index)
                                                    }
                                                    disabled={
                                                        steps.length === 1
                                                    }
                                                    className="text-gray-400 hover:text-red-500 disabled:opacity-30 transition"
                                                >
                                                    <Trash2 size={14} />
                                                </button>
                                            </div>

                                            {/* Config fields per type */}
                                            <StepConfigFields
                                                step={step}
                                                onChange={(updated) =>
                                                    handleUpdateStep(
                                                        index,
                                                        updated,
                                                    )
                                                }
                                            />
                                        </div>
                                    ))}

                                    <button
                                        onClick={handleAddStep}
                                        className="w-full border border-dashed border-gray-300 rounded-lg py-2 text-sm text-gray-500 hover:border-teal-400 hover:text-teal-600 transition"
                                    >
                                        + Add Step
                                    </button>
                                </div>
                            )}

                            {/* Tab: Edges */}
                            {activeTab === "edges" && (
                                <div className="space-y-2 max-h-80 overflow-y-auto pr-1">
                                    {edges.length === 0 && (
                                        <p className="text-sm text-gray-400 text-center py-4">
                                            No edges yet. Add steps first, then
                                            define connections here.
                                        </p>
                                    )}

                                    {edges.map((edge, index) => (
                                        <div
                                            key={index}
                                            className="flex items-center gap-2"
                                        >
                                            <select
                                                value={edge.from}
                                                onChange={(e) =>
                                                    setEdges((prev) => {
                                                        const next = [...prev];
                                                        next[index] = {
                                                            ...next[index],
                                                            from: e.target
                                                                .value,
                                                        };
                                                        return next;
                                                    })
                                                }
                                                className="flex-1 border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-teal-500 bg-white"
                                            >
                                                <option value="">
                                                    From step...
                                                </option>
                                                {steps.map((s) => (
                                                    <option
                                                        key={s.id}
                                                        value={s.id}
                                                    >
                                                        {s.id}{" "}
                                                        {s.name
                                                            ? `(${s.name})`
                                                            : ""}
                                                    </option>
                                                ))}
                                            </select>

                                            <ArrowRight
                                                size={16}
                                                className="text-gray-400 shrink-0"
                                            />

                                            <select
                                                value={edge.to}
                                                onChange={(e) =>
                                                    setEdges((prev) => {
                                                        const next = [...prev];
                                                        next[index] = {
                                                            ...next[index],
                                                            to: e.target.value,
                                                        };
                                                        return next;
                                                    })
                                                }
                                                className="flex-1 border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-teal-500 bg-white"
                                            >
                                                <option value="">
                                                    To step...
                                                </option>
                                                {steps.map((s) => (
                                                    <option
                                                        key={s.id}
                                                        value={s.id}
                                                    >
                                                        {s.id}{" "}
                                                        {s.name
                                                            ? `(${s.name})`
                                                            : ""}
                                                    </option>
                                                ))}
                                            </select>

                                            <button
                                                onClick={() =>
                                                    setEdges((prev) =>
                                                        prev.filter(
                                                            (_, i) =>
                                                                i !== index,
                                                        ),
                                                    )
                                                }
                                                className="text-gray-400 hover:text-red-500 transition"
                                            >
                                                <Trash2 size={14} />
                                            </button>
                                        </div>
                                    ))}

                                    <button
                                        onClick={() =>
                                            setEdges((prev) => [
                                                ...prev,
                                                { from: "", to: "" },
                                            ])
                                        }
                                        className="w-full border border-dashed border-gray-300 rounded-lg py-2 text-sm text-gray-500 hover:border-teal-400 hover:text-teal-600 transition"
                                    >
                                        + Add Edge
                                    </button>
                                </div>
                            )}
                        </div>

                        {/* Modal Footer */}
                        <div className="px-6 py-4 border-t flex items-center justify-between">
                            <p className="text-xs text-gray-400">
                                {steps.length} step
                                {steps.length !== 1 ? "s" : ""} · {edges.length}{" "}
                                edge
                                {edges.length !== 1 ? "s" : ""}
                            </p>
                            <div className="flex gap-2">
                                <button
                                    onClick={handleCloseModal}
                                    className="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    onClick={handleCreate}
                                    disabled={!newName.trim() || creating}
                                    className="px-4 py-2 bg-teal-600 text-white text-sm rounded-lg hover:bg-teal-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
                                >
                                    {creating
                                        ? "Creating..."
                                        : "Create Workflow"}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
