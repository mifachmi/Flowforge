/* eslint-disable @typescript-eslint/no-explicit-any */
import ReactFlow, {
    Background,
    Controls,
    MiniMap,
    useNodesState,
    useEdgesState,
} from "reactflow";
import "reactflow/dist/style.css";
import { StepNode } from "./StepNode";
import { useEffect } from "react";

const nodeTypes = { step: StepNode };

interface DagViewerProps {
    dagDefinition: { steps: any[]; edges: any[] };
    stepStatuses?: Record<string, string>;
}

export function DagViewer({
    dagDefinition,
    stepStatuses = {},
}: DagViewerProps) {
    const [nodes, setNodes, onNodesChange] = useNodesState([]);
    const [edges, setEdges, onEdgesChange] = useEdgesState([]);

    useEffect(() => {
        if (!dagDefinition?.steps) return;

        const builtNodes = dagDefinition.steps.map((step, i) => ({
            id: step.id,
            type: "step",
            position: { x: i * 200, y: 100 },
            // Tambahkan key "data:" di sini
            data: {
                label: step.name,
                type: step.type,
                status: stepStatuses[step.id] ?? "pending",
            },
        }));

        const builtEdges = (dagDefinition.edges ?? []).map((edge: any) => ({
            id: `${edge.from}-${edge.to}`,
            source: edge.from,
            target: edge.to,
            animated: stepStatuses[edge.from] === "running",
        }));

        setNodes(builtNodes);
        setEdges(builtEdges);
    }, [dagDefinition, stepStatuses, setNodes, setEdges]); // Tambahkan setNodes & setEdges ke dependency array (best practice)

    return (
        <div className="h-64 w-full border rounded-xl overflow-hidden bg-gray-50">
            <ReactFlow
                nodes={nodes}
                edges={edges}
                onNodesChange={onNodesChange}
                onEdgesChange={onEdgesChange}
                nodeTypes={nodeTypes}
                fitView
            >
                <Background />
                <Controls />
                <MiniMap />
            </ReactFlow>
        </div>
    );
}
