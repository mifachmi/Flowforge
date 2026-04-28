import { useQuery } from "@tanstack/react-query";
import { workflowApi } from "../api/workflows";
import { Activity, CheckCircle, XCircle, Clock } from "lucide-react";

export function HealthPanel() {
    // Perbaikan: Ambil 'data' lalu beri alias 'health'
    const {
        data: health,
        isLoading,
        isError,
    } = useQuery({
        queryKey: ["health"],
        queryFn: workflowApi.getHealth,
        refetchInterval: 30_000, // refresh tiap 30 detik
    });

    // (Opsional) Penanganan state loading dan error yang baik
    if (isLoading)
        return (
            <div className="text-gray-500 text-sm">Memuat data metrik...</div>
        );
    if (isError)
        return <div className="text-red-500 text-sm">Gagal memuat metrik.</div>;

    const stats = [
        {
            label: "Active Runs",
            value: health?.active_runs ?? 0,
            icon: Activity,
            color: "text-blue-600",
        },
        {
            label: "Success Rate (24h)",
            value: health?.success_rate ? `${health.success_rate}%` : "—",
            icon: CheckCircle,
            color: "text-green-600",
        },
        {
            label: "Failed (24h)",
            value: health?.failed_count ?? 0,
            icon: XCircle,
            color: "text-red-600",
        },
        {
            label: "Avg Duration",
            value: health?.avg_duration_ms
                ? `${health.avg_duration_ms}ms`
                : "—",
            icon: Clock,
            color: "text-gray-600",
        },
    ];

    return (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            {stats.map(({ label, value, icon: Icon, color }) => (
                <div key={label} className="bg-white rounded-xl border p-4">
                    <div className="flex items-center gap-2 mb-1">
                        <Icon size={16} className={color} />
                        <span className="text-xs text-gray-500">{label}</span>
                    </div>
                    <div className="text-2xl font-bold text-gray-900">
                        {value}
                    </div>
                </div>
            ))}
        </div>
    );
}
