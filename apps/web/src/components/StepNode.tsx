import { Handle, Position } from "reactflow";

const statusColors: Record<string, string> = {
    pending: "bg-gray-100 border-gray-300 text-gray-600",
    running: "bg-blue-100 border-blue-400 text-blue-700 animate-pulse",
    success: "bg-green-100 border-green-400 text-green-700",
    failed: "bg-red-100 border-red-400 text-red-700",
    retrying: "bg-yellow-100 border-yellow-400 text-yellow-700",
};

// 1. Definisikan bentuk data dari node kamu
interface StepNodeData {
    label: string;
    type: string;
    status?: string;
}

// 2. Gunakan NodeProps dari reactflow (opsional tapi best practice)
// atau destructure langsung dengan tipe yang benar
export function StepNode({ data }: { data: StepNodeData }) {
    const status = data.status ?? "pending";
    const colorClass = statusColors[status] ?? statusColors.pending;

    return (
        <div
            className={`px-4 py-2 rounded-lg border-2 text-sm font-medium min-w-30 text-center ${colorClass}`}
        >
            <Handle type="target" position={Position.Left} />
            <div className="font-semibold">{data.label}</div>
            <div className="text-xs opacity-70 mt-0.5">{data.type}</div>
            <div className="text-xs mt-1 capitalize">{status}</div>
            <Handle type="source" position={Position.Right} />
        </div>
    );
}
