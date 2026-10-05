import { Node, Edge } from '@xyflow/react';

export type NodeCategory = 'trigger' | 'condition' | 'action' | 'delay';

export interface WorkflowNodeConfig {
    target_status?: string;
    payment_status?: string;
    status?: string;
    service_id?: number | null;
    amount?: number;
    channel?: 'whatsapp' | 'email';
    template?: string;
    duration?: number;
    unit?: 'minutes' | 'hours' | 'days';
    note?: string;
    [key: string]: unknown;
}

export interface WorkflowNodeData extends Record<string, unknown> {
    label: string;
    category: NodeCategory;
    type: string;
    description?: string;
    disabled?: boolean;
    config: WorkflowNodeConfig;
}

export type CustomWorkflowNode = Node<WorkflowNodeData, string>;

export interface WorkflowVersionItem {
    id: number;
    tenant_id: number;
    workflow_id: number;
    version_number: number;
    status: 'DRAFT' | 'PUBLISHED' | 'ARCHIVED';
    graph: {
        nodes: CustomWorkflowNode[];
        edges: Edge[];
    };
    published_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface WorkflowItem {
    id: number;
    tenant_id: number;
    service_id: number | null;
    name: string;
    description: string | null;
    is_active: boolean;
    is_default: boolean;
    current_version_id: number | null;
    created_at: string;
    updated_at: string;
    service?: { id: number; name: string } | null;
    current_version?: WorkflowVersionItem | null;
    draft_version?: WorkflowVersionItem | null;
    versions?: WorkflowVersionItem[];
}

export interface BusinessPresetWorkflow {
    key: string;
    name: string;
    description: string;
    graph: {
        nodes: CustomWorkflowNode[];
        edges: Edge[];
    };
}

export interface DryRunStep {
    node_id: string;
    title: string;
    type: string;
    status: string;
    detail: string;
}

export interface DryRunResult {
    success: boolean;
    steps: DryRunStep[];
    final_status: string | null;
    notifications: string[];
}

export interface WorkflowLogItem {
    id: number;
    tenant_id: number;
    workflow_run_id: number;
    node_id: string;
    node_type: string;
    node_label: string | null;
    status: 'PENDING' | 'RUNNING' | 'SUCCESS' | 'FAILED' | 'SKIPPED' | 'WAITING_DELAY';
    attempt: number;
    input_data: Record<string, unknown> | null;
    output_data: Record<string, unknown> | null;
    error_message: string | null;
    run_at: string | null;
    executed_at: string | null;
    created_at: string;
}

export interface WorkflowRunItem {
    id: number;
    tenant_id: number;
    workflow_id: number;
    version_id: number;
    booking_id: number | null;
    execution_id: string;
    trigger_event: string;
    trigger_payload: Record<string, unknown> | null;
    status: 'PENDING' | 'RUNNING' | 'COMPLETED' | 'FAILED' | 'CANCELLED';
    current_node_id: string | null;
    depth: number;
    error_message: string | null;
    started_at: string | null;
    completed_at: string | null;
    created_at: string;
    booking?: {
        id: number;
        code: string;
        customer?: { name: string; phone: string } | null;
    } | null;
    version?: WorkflowVersionItem | null;
    logs?: WorkflowLogItem[];
}
