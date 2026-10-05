import {
    addEdge,
    Background,
    BackgroundVariant,
    Connection,
    Controls,
    Edge,
    MiniMap,
    ReactFlow,
    ReactFlowInstance,
    useEdgesState,
    useNodesState,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';
import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Check,
    Maximize2,
    Play,
    Redo,
    Send,
    Undo,
    ZoomIn,
    ZoomOut,
} from 'lucide-react';
import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '../../../Components/ui/Button';
import { useToast } from '../../../Components/ui/Toast';
import { NodeLibrary, NodeTemplateItem } from './NodeLibrary';
import { NodePropertyEditor } from './NodePropertyEditor';
import { TestSimulationModal } from './TestSimulationModal';
import {
    CustomWorkflowNode,
    DryRunResult,
    WorkflowItem,
} from './types';
import { workflowNodeTypes } from './WorkflowNodes';

interface BuilderPageProps {
    workflow: WorkflowItem;
    services: Array<{ id: number; name: string }>;
    statuses: Array<{ id: number | string; code: string; name: string }>;
    presets: Array<{ key: string; name: string; description: string }>;
}

export default function WorkflowBuilderPage({
    workflow,
    services: _services,
    statuses,
    presets: _presets,
}: BuilderPageProps) {
    const toast = useToast();

    // Determine initial graph: prefer draft_version, fallback to current_version, fallback to empty
    const initialGraph =
        workflow.draft_version?.graph ||
        workflow.current_version?.graph || {
            nodes: [
                {
                    id: 'trigger_1',
                    type: 'trigger',
                    position: { x: 250, y: 50 },
                    data: {
                        label: 'Booking Dibuat',
                        category: 'trigger',
                        type: 'booking_created',
                        config: {},
                    },
                },
            ],
            edges: [],
        };

    const [nodes, setNodes, onNodesChange] = useNodesState<CustomWorkflowNode>(
        (initialGraph.nodes || []) as CustomWorkflowNode[]
    );
    const [edges, setEdges, onEdgesChange] = useEdgesState<Edge>(
        (initialGraph.edges || []) as Edge[]
    );

    const [reactFlowInstance, setReactFlowInstance] =
        useState<ReactFlowInstance<CustomWorkflowNode, Edge> | null>(null);

    // Selected node state for property editor
    const [selectedNodeId, setSelectedNodeId] = useState<string | null>(null);
    const selectedNode =
        nodes.find((n) => n.id === selectedNodeId) || null;

    // Undo / Redo history stacks
    const [history, setHistory] = useState<
        Array<{ nodes: CustomWorkflowNode[]; edges: Edge[] }>
    >([]);
    const [future, setFuture] = useState<
        Array<{ nodes: CustomWorkflowNode[]; edges: Edge[] }>
    >([]);

    // Autosave & publishing status
    const [saveStatus, setSaveStatus] = useState<
        'saved' | 'saving' | 'unsaved'
    >('saved');
    const [isPublishing, setIsPublishing] = useState(false);
    const [isTestModalOpen, setIsTestModalOpen] = useState(false);

    const autosaveTimerRef = useRef<NodeJS.Timeout | null>(null);

    // Record history snapshot before meaningful mutation
    const pushHistory = useCallback(() => {
        setHistory((prev) => [
            ...prev.slice(-20),
            { nodes: [...nodes], edges: [...edges] },
        ]);
        setFuture([]);
        setSaveStatus('unsaved');
    }, [nodes, edges]);

    // Handle Undo
    const handleUndo = () => {
        if (history.length === 0) return;
        const previous = history[history.length - 1];
        setHistory((prev) => prev.slice(0, prev.length - 1));
        setFuture((prev) => [{ nodes: [...nodes], edges: [...edges] }, ...prev]);
        setNodes(previous.nodes);
        setEdges(previous.edges);
        setSaveStatus('unsaved');
    };

    // Handle Redo
    const handleRedo = () => {
        if (future.length === 0) return;
        const next = future[0];
        setFuture((prev) => prev.slice(1));
        setHistory((prev) => [
            ...prev,
            { nodes: [...nodes], edges: [...edges] },
        ]);
        setNodes(next.nodes);
        setEdges(next.edges);
        setSaveStatus('unsaved');
    };

    // Connect nodes with edge
    const onConnect = useCallback(
        (connection: Connection) => {
            pushHistory();
            setEdges((eds) =>
                addEdge(
                    {
                        ...connection,
                        type: 'smoothstep',
                        animated: true,
                        style: { stroke: '#64748b', strokeWidth: 2 },
                    },
                    eds
                )
            );
        },
        [pushHistory, setEdges]
    );

    // Node selection
    const onNodeClick = useCallback(
        (_: React.MouseEvent, node: CustomWorkflowNode) => {
            setSelectedNodeId(node.id);
        },
        []
    );

    const onPaneClick = useCallback(() => {
        setSelectedNodeId(null);
    }, []);

    // Add node from library (Single-Pointer Tap or Drag Drop)
    const handleAddNode = useCallback(
        (template: NodeTemplateItem, position?: { x: number; y: number }) => {
            pushHistory();
            const newId = `${template.category}_${Date.now()}`;
            const defaultPos = position || {
                x: 200 + Math.random() * 80,
                y: 150 + Math.random() * 80,
            };

            const newNode: CustomWorkflowNode = {
                id: newId,
                type: template.category,
                position: defaultPos,
                data: {
                    label: template.label,
                    category: template.category,
                    type: template.type,
                    description: template.description,
                    config: { ...template.defaultConfig },
                },
            };

            setNodes((nds) => [...nds, newNode]);
            setSelectedNodeId(newId);
        },
        [pushHistory, setNodes]
    );

    // Drag-and-drop on canvas
    const onDragOver = useCallback((event: React.DragEvent) => {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
    }, []);

    const onDrop = useCallback(
        (event: React.DragEvent) => {
            event.preventDefault();
            const dataStr = event.dataTransfer.getData('application/reactflow-node');
            if (!dataStr || !reactFlowInstance) return;

            try {
                const template = JSON.parse(dataStr) as NodeTemplateItem;
                const position = reactFlowInstance.screenToFlowPosition({
                    x: event.clientX,
                    y: event.clientY,
                });
                handleAddNode(template, position);
            } catch {
                // Ignore parse errors
            }
        },
        [reactFlowInstance, handleAddNode]
    );

    // Update node data from property editor
    const handleUpdateNodeData = (
        nodeId: string,
        updatedData: Partial<CustomWorkflowNode['data']>
    ) => {
        pushHistory();
        setNodes((nds) =>
            nds.map((n) => {
                if (n.id === nodeId) {
                    return {
                        ...n,
                        data: {
                            ...n.data,
                            ...updatedData,
                        },
                    };
                }
                return n;
            })
        );
    };

    // Duplicate selected node
    const handleDuplicateNode = (nodeId: string) => {
        const target = nodes.find((n) => n.id === nodeId);
        if (!target) return;

        pushHistory();
        const newId = `${target.data.category}_${Date.now()}`;
        const newNode: CustomWorkflowNode = {
            ...target,
            id: newId,
            position: {
                x: target.position.x + 40,
                y: target.position.y + 40,
            },
            data: {
                ...target.data,
                label: `${target.data.label} (Salinan)`,
            },
            selected: false,
        };

        setNodes((nds) => [...nds, newNode]);
        setSelectedNodeId(newId);
        toast.success(`Node '${target.data.label}' berhasil diduplikasi.`);
    };

    // Delete selected node
    const handleDeleteNode = (nodeId: string) => {
        pushHistory();
        setNodes((nds) => nds.filter((n) => n.id !== nodeId));
        setEdges((eds) =>
            eds.filter((e) => e.source !== nodeId && e.target !== nodeId)
        );
        setSelectedNodeId(null);
        toast.success('Node berhasil dihapus dari kanvas.');
    };

    // Save Draft API Call
    const executeSaveDraft = useCallback(async () => {
        setSaveStatus('saving');
        try {
            const csrfToken =
                (
                    document.querySelector(
                        'meta[name="csrf-token"]'
                    ) as HTMLMetaElement
                )?.content || '';

            const res = await fetch(
                `/app/workflows/${workflow.id}/save-draft`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        graph: {
                            nodes,
                            edges,
                        },
                    }),
                }
            );

            if (res.ok) {
                setSaveStatus('saved');
            } else {
                setSaveStatus('unsaved');
            }
        } catch {
            setSaveStatus('unsaved');
        }
    }, [workflow.id, nodes, edges]);

    // Debounced autosave (after 2 seconds of inactivity)
    useEffect(() => {
        if (saveStatus !== 'unsaved') return;

        if (autosaveTimerRef.current) {
            clearTimeout(autosaveTimerRef.current);
        }

        autosaveTimerRef.current = setTimeout(() => {
            executeSaveDraft();
        }, 2000);

        return () => {
            if (autosaveTimerRef.current) {
                clearTimeout(autosaveTimerRef.current);
            }
        };
    }, [saveStatus, executeSaveDraft]);

    // Publish Version API Call
    const handlePublish = async () => {
        setIsPublishing(true);
        try {
            const csrfToken =
                (
                    document.querySelector(
                        'meta[name="csrf-token"]'
                    ) as HTMLMetaElement
                )?.content || '';

            // First ensure current draft is saved
            await fetch(`/app/workflows/${workflow.id}/save-draft`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ graph: { nodes, edges } }),
            });

            // Publish
            const res = await fetch(`/app/workflows/${workflow.id}/publish`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            });

            const data = await res.json();
            if (res.ok) {
                toast.success(data.message || 'Alur kerja berhasil dipublikasikan!');
                setSaveStatus('saved');
                router.reload();
            } else {
                toast.error(
                    data.message || 'Gagal mempublikasikan: Cek kembali aturan graf alur kerja.'
                );
            }
        } catch {
            toast.error('Terjadi kesalahan jaringan.');
        } finally {
            setIsPublishing(false);
        }
    };

    // Dry Run Simulation API Call
    const handleRunSimulation = async (mockPayload: Record<string, unknown>): Promise<DryRunResult> => {
        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]'
                ) as HTMLMetaElement
            )?.content || '';

        const res = await fetch(`/app/workflows/${workflow.id}/test-run`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                graph: { nodes, edges },
                mock_payload: mockPayload,
            }),
        });

        return await res.json();
    };

    return (
        <div className="flex h-screen w-screen flex-col overflow-hidden bg-slate-50 text-slate-900">
            <Head title={`Workflow Builder - ${workflow.name}`} />

            {/* Mobile Alert Notice (< 768px) */}
            <div className="md:hidden flex items-center justify-between border-b border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                <span className="flex items-center gap-1.5 font-medium">
                    <AlertTriangle className="h-4 w-4 shrink-0 text-amber-600" />
                    Editor visual dioptimalkan untuk desktop/tablet.
                </span>
                <Link href="/app/workflows" className="font-semibold text-blue-600 underline">
                    Kembali
                </Link>
            </div>

            {/* Top Toolbar (PRD 66) */}
            <header className="flex h-14 shrink-0 items-center justify-between border-b border-slate-200 bg-white px-4 shadow-2xs">
                {/* Left: Back & Title */}
                <div className="flex items-center gap-3">
                    <Link
                        href="/app/workflows"
                        className="flex h-8 w-8 items-center justify-center rounded-[8px] border border-slate-200 text-slate-500 transition-colors hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900"
                    >
                        <ArrowLeft className="h-4 w-4" />
                    </Link>

                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-sm font-bold text-slate-900">{workflow.name}</h1>
                            {workflow.current_version ? (
                                <span className="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                                    V{workflow.current_version.version_number} AKTIF
                                </span>
                            ) : (
                                <span className="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700">
                                    DRAFT
                                </span>
                            )}
                        </div>
                        <span className="text-[11px] text-slate-500">
                            {workflow.service?.name
                                ? `Layanan: ${workflow.service.name}`
                                : 'Alur Kerja Utama (Default)'}
                        </span>
                    </div>
                </div>

                {/* Center: Canvas Controls (Undo/Redo, Zoom) */}
                <div className="hidden sm:flex items-center gap-1 rounded-[8px] border border-slate-200 bg-slate-50/80 p-1 text-xs">
                    <button
                        type="button"
                        onClick={handleUndo}
                        disabled={history.length === 0}
                        title="Undo (Ctrl+Z)"
                        className="rounded-[6px] p-1.5 text-slate-600 hover:bg-white hover:text-slate-900 disabled:opacity-40"
                    >
                        <Undo className="h-3.5 w-3.5" />
                    </button>
                    <button
                        type="button"
                        onClick={handleRedo}
                        disabled={future.length === 0}
                        title="Redo (Ctrl+Y)"
                        className="rounded-[6px] p-1.5 text-slate-600 hover:bg-white hover:text-slate-900 disabled:opacity-40"
                    >
                        <Redo className="h-3.5 w-3.5" />
                    </button>
                    <div className="h-4 w-px bg-slate-200 mx-1" />
                    <button
                        type="button"
                        onClick={() => reactFlowInstance?.zoomIn()}
                        title="Perbesar"
                        className="rounded-[6px] p-1.5 text-slate-600 hover:bg-white hover:text-slate-900"
                    >
                        <ZoomIn className="h-3.5 w-3.5" />
                    </button>
                    <button
                        type="button"
                        onClick={() => reactFlowInstance?.zoomOut()}
                        title="Perkecil"
                        className="rounded-[6px] p-1.5 text-slate-600 hover:bg-white hover:text-slate-900"
                    >
                        <ZoomOut className="h-3.5 w-3.5" />
                    </button>
                    <button
                        type="button"
                        onClick={() => reactFlowInstance?.fitView({ padding: 0.2 })}
                        title="Sesuaikan Kanvas (Fit)"
                        className="rounded-[6px] p-1.5 text-slate-600 hover:bg-white hover:text-slate-900"
                    >
                        <Maximize2 className="h-3.5 w-3.5" />
                    </button>
                </div>

                {/* Right: Autosave status & Main CTA */}
                <div className="flex items-center gap-3">
                    <div className="hidden sm:flex items-center gap-1.5 text-[11px] text-slate-500">
                        {saveStatus === 'saving' && (
                            <span className="flex items-center gap-1 text-blue-600">
                                <span className="h-2 w-2 animate-ping rounded-full bg-blue-600" />
                                Menyimpan draf...
                            </span>
                        )}
                        {saveStatus === 'saved' && (
                            <span className="flex items-center gap-1 text-emerald-600">
                                <Check className="h-3.5 w-3.5" /> Draf tersimpan
                            </span>
                        )}
                        {saveStatus === 'unsaved' && (
                            <span className="text-amber-600 font-medium">
                                Ada perubahan belum tersimpan
                            </span>
                        )}
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => setIsTestModalOpen(true)}
                        className="gap-1.5 text-xs text-slate-700"
                    >
                        <Play className="h-3.5 w-3.5 text-blue-600" /> Uji Alur (Test)
                    </Button>

                    <Button
                        type="button"
                        variant="primary"
                        size="sm"
                        onClick={handlePublish}
                        isLoading={isPublishing}
                        className="gap-1.5 text-xs font-semibold"
                    >
                        <Send className="h-3.5 w-3.5" /> Publikasikan Versi
                    </Button>
                </div>
            </header>

            {/* Main Workspace (Left Library, Center Canvas, Right Property Editor) */}
            <div className="flex flex-1 overflow-hidden">
                {/* 1. Left Sidebar: Node Library (260px) */}
                <aside className="hidden md:block w-64 shrink-0">
                    <NodeLibrary onAddNode={handleAddNode} />
                </aside>

                {/* 2. Center: XYFlow Canvas */}
                <main className="relative flex-1 bg-slate-50" onDragOver={onDragOver} onDrop={onDrop}>
                    <ReactFlow
                        nodes={nodes}
                        edges={edges}
                        nodeTypes={workflowNodeTypes}
                        onNodesChange={onNodesChange}
                        onEdgesChange={onEdgesChange}
                        onConnect={onConnect}
                        onNodeClick={onNodeClick}
                        onPaneClick={onPaneClick}
                        onInit={setReactFlowInstance}
                        fitView
                        fitViewOptions={{ padding: 0.2 }}
                        defaultEdgeOptions={{
                            type: 'smoothstep',
                            animated: true,
                            style: { stroke: '#64748b', strokeWidth: 2 },
                        }}
                        className="h-full w-full"
                    >
                        <Background variant={BackgroundVariant.Dots} gap={16} size={1} color="#cbd5e1" />
                        <Controls showInteractive={false} className="!bg-white !border-slate-200 !shadow-xs" />
                        <MiniMap
                            nodeColor={(n) => {
                                switch (n.type) {
                                    case 'trigger':
                                        return '#2563eb';
                                    case 'condition':
                                        return '#d97706';
                                    case 'action':
                                        return '#059669';
                                    case 'delay':
                                        return '#7c3aed';
                                    default:
                                        return '#94a3b8';
                                }
                            }}
                            className="!border !border-slate-200 !bg-white/80"
                        />
                    </ReactFlow>
                </main>

                {/* 3. Right Sidebar: Property Editor (300px) */}
                <aside className="w-80 shrink-0">
                    <NodePropertyEditor
                        node={selectedNode}
                        statuses={statuses}
                        onUpdateNodeData={handleUpdateNodeData}
                        onDuplicateNode={handleDuplicateNode}
                        onDeleteNode={handleDeleteNode}
                        onClose={() => setSelectedNodeId(null)}
                    />
                </aside>
            </div>

            {/* Test Simulation Modal */}
            <TestSimulationModal
                isOpen={isTestModalOpen}
                onClose={() => setIsTestModalOpen(false)}
                onRunSimulation={handleRunSimulation}
            />
        </div>
    );
}
