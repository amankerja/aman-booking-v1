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
