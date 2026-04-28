/* eslint-disable @typescript-eslint/no-explicit-any */
import { useQuery } from "@tanstack/react-query";
import { useNavigate } from "react-router-dom";
import { workflowApi } from "../api/workflows";
import { HealthPanel } from "../components/HealthPanel";
import { formatDistanceToNow } from "date-fns";
import { Play, Plus } from "lucide-react";

export default function DashboardPage() {
    const navigate = useNavigate();
    const { data, isLoading } = useQuery({
        queryKey: ["workflows"],
        queryFn: () => workflowApi.list(),
    });

    const handleTrigger = async (e: React.MouseEvent, id: string) => {
        // e.stopPropagation() masih perlu agar klik tombol Run tidak memicu navigasi card
        e.stopPropagation();
        await workflowApi.trigger(id);
        alert("Workflow triggered!");
    };

    // Fungsi handleCardKeyDown sudah bisa dihapus karena <button> bawaannya sudah menangani tombol Enter/Spasi!

    return (
        <div className="min-h-screen bg-gray-50">
            <header className="bg-white border-b px-6 py-4 flex items-center justify-between">
                <h1 className="text-lg font-bold text-gray-900">FlowForge</h1>
                <button className="flex items-center gap-2 bg-teal-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-teal-700 transition">
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
                    ) : (
                        <div className="space-y-3">
                            {data?.data?.map((wf: any) => (
                                <div
                                    key={wf.id}
                                    // 1. Hapus role, tabIndex, onClick, dan onKeyDown.
                                    // Tambahkan "relative" dan "focus-within:ring-2" (agar ring muncul saat elemen di dalamnya difokuskan)
                                    className="relative bg-white border rounded-xl px-5 py-4 flex items-center justify-between hover:shadow-sm transition focus-within:ring-2 focus-within:ring-teal-500 focus-within:ring-offset-1"
                                >
                                    <div>
                                        {/* 2. Judul menggunakan <button> asli */}
                                        <button
                                            onClick={() =>
                                                navigate(`/workflows/${wf.id}`)
                                            }
                                            // Triknya ada di "before:absolute before:inset-0" -> ini membuat area klik melebar seluas card
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

                                    {/* 3. Tombol aksi sekunder ("Run") */}
                                    <button
                                        onClick={(e) => handleTrigger(e, wf.id)}
                                        // Beri "relative z-10" agar tombol ini posisinya berada di atas overlay "before:inset-0" milik judul
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
        </div>
    );
}
