import { Handle, Position, NodeProps } from '@xyflow/react';
import {
    AlertCircle,
    Bell,
    Clock,
    GitBranch,
    Play,
    RefreshCw,
    UserCheck,
    Zap,
} from 'lucide-react';
import React from 'react';
import { CustomWorkflowNode } from './types';

// Helper to get category icon
const getNodeIcon = (category: string, subType: string) => {
    switch (category) {
        case 'trigger':
            return <Zap className="h-4 w-4 text-blue-600" />;
        case 'condition':
            return <GitBranch className="h-4 w-4 text-amber-600" />;
        case 'action':
            if (subType === 'send_notification') {
                return <Bell className="h-4 w-4 text-emerald-600" />;
            }
            if (subType === 'assign_resource') {
                return <UserCheck className="h-4 w-4 text-emerald-600" />;
            }
            return <RefreshCw className="h-4 w-4 text-emerald-600" />;
        case 'delay':
            return <Clock className="h-4 w-4 text-purple-600" />;
        default:
            return <Play className="h-4 w-4 text-slate-500" />;
    }
};

interface BaseNodeLayoutProps {
    id: string;
    selected?: boolean;
    category: 'trigger' | 'condition' | 'action' | 'delay';
    subType: string;
    title: string;
    subtitle?: string;
    disabled?: boolean;
    children?: React.ReactNode;
}

const BaseNodeLayout: React.FC<BaseNodeLayoutProps> = ({
    selected,
    category,
    subType,
    title,
    subtitle,
    disabled,
    children,
}) => {
    const borderColor = selected
        ? 'border-blue-600 ring-2 ring-blue-500/20'
        : 'border-slate-200 hover:border-slate-300';

    const categoryBadge = {
        trigger: { label: 'PEMICU', bg: 'bg-blue-50 text-blue-700 border-blue-200' },
        condition: { label: 'KONDISI', bg: 'bg-amber-50 text-amber-700 border-amber-200' },
        action: { label: 'AKSI', bg: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
        delay: { label: 'JEDA', bg: 'bg-purple-50 text-purple-700 border-purple-200' },
    }[category];

    return (
        <div
            className={`min-w-[240px] max-w-[280px] rounded-[12px] border bg-white p-3 shadow-xs transition-all ${borderColor} ${
                disabled ? 'opacity-50 grayscale' : ''
            }`}
        >
            <div className="mb-2 flex items-center justify-between gap-2 border-b border-slate-100 pb-2">
                <div className="flex items-center gap-1.5">
                    <div className="flex h-7 w-7 items-center justify-center rounded-[8px] bg-slate-50">
                        {getNodeIcon(category, subType)}
                    </div>
                    <span
                        className={`rounded-full border px-2 py-0.5 text-[10px] font-semibold tracking-wider ${categoryBadge.bg}`}
                    >
                        {categoryBadge.label}
                    </span>
                </div>
                {disabled && (
                    <span className="flex items-center gap-0.5 text-[10px] text-slate-400">
                        <AlertCircle className="h-3 w-3" /> Nonaktif
                    </span>
                )}
            </div>

            <div className="space-y-1">
                <h4 className="text-xs font-bold text-slate-900 leading-snug">{title}</h4>
                {subtitle && (
                    <p className="text-[11px] text-slate-500 line-clamp-2 leading-relaxed">
                        {subtitle}
                    </p>
                )}
            </div>

            {children}
        </div>
    );
};

export const TriggerNode: React.FC<NodeProps<CustomWorkflowNode>> = ({ id, data, selected }) => {
    return (
        <div className="relative">
            <BaseNodeLayout
                id={id}
                selected={selected}
                category="trigger"
                subType={data.type}
                title={data.label}
                subtitle="Memicu alur otomatis saat peristiwa ini terjadi."
                disabled={data.disabled}
            />
            {/* Trigger has bottom output handle */}
            <Handle
                type="source"
                position={Position.Bottom}
                id="default"
                className="!h-3 !w-3 !rounded-full !border-2 !border-white !bg-blue-600 transition-transform hover:scale-125"
            />
        </div>
    );
};

export const ConditionNode: React.FC<NodeProps<CustomWorkflowNode>> = ({ id, data, selected }) => {
    return (
        <div className="relative">
            {/* Top input handle */}
            <Handle
                type="target"
                position={Position.Top}
                id="default"
                className="!h-3 !w-3 !rounded-full !border-2 !border-white !bg-slate-500 transition-transform hover:scale-125"
            />

            <BaseNodeLayout
                id={id}
                selected={selected}
                category="condition"
                subType={data.type}
                title={data.label}
                subtitle="Mengevaluasi kondisi data booking."
                disabled={data.disabled}
            >
                <div className="mt-2.5 flex items-center justify-between border-t border-slate-100 pt-2 text-[10px] font-semibold">
                    <span className="text-emerald-600">✓ YA (Terpenuhi)</span>
                    <span className="text-rose-600">✕ TIDAK (Lainnya)</span>
                </div>
            </BaseNodeLayout>

            {/* Bottom branches: Left = YES, Right = NO */}
            <Handle
                type="source"
                position={Position.Bottom}
                id="yes"
                style={{ left: '25%' }}
                className="!h-3 !w-3 !rounded-full !border-2 !border-white !bg-emerald-600 transition-transform hover:scale-125"
            />
            <Handle
                type="source"
                position={Position.Bottom}
                id="no"
                style={{ left: '75%' }}
                className="!h-3 !w-3 !rounded-full !border-2 !border-white !bg-rose-600 transition-transform hover:scale-125"
            />
        </div>
    );
};

export const ActionNode: React.FC<NodeProps<CustomWorkflowNode>> = ({ id, data, selected }) => {
    let actionDetail = 'Mengeksekusi tindakan pada booking.';
    if (data.type === 'change_status' && data.config?.target_status) {
        actionDetail = `Ubah status -> ${data.config.target_status}`;
    } else if (data.type === 'send_notification') {
        actionDetail = `Kirim ${data.config?.channel || 'WhatsApp'}`;
    }

    return (
        <div className="relative">
            {/* Top input handle */}
            <Handle
                type="target"
                position={Position.Top}
                id="default"
                className="!h-3 !w-3 !rounded-full !border-2 !border-white !bg-slate-500 transition-transform hover:scale-125"
            />

            <BaseNodeLayout
                id={id}
                selected={selected}
                category="action"
                subType={data.type}
                title={data.label}
                subtitle={actionDetail}
                disabled={data.disabled}
            />

            {/* Bottom output handle */}
            <Handle
                type="source"
                position={Position.Bottom}
                id="default"
                className="!h-3 !w-3 !rounded-full !border-2 !border-white !bg-emerald-600 transition-transform hover:scale-125"
            />
        </div>
    );
};

export const DelayNode: React.FC<NodeProps<CustomWorkflowNode>> = ({ id, data, selected }) => {
    const duration = data.config?.duration || 10;
    const unit = data.config?.unit || 'minutes';
    const unitLabel = unit === 'hours' ? 'Jam' : unit === 'days' ? 'Hari' : 'Menit';

    return (
        <div className="relative">
            {/* Top input handle */}
            <Handle
                type="target"
                position={Position.Top}
                id="default"
                className="!h-3 !w-3 !rounded-full !border-2 !border-white !bg-slate-500 transition-transform hover:scale-125"
            />

            <BaseNodeLayout
                id={id}
                selected={selected}
                category="delay"
                subType={data.type}
                title={data.label}
                subtitle={`Tunggu selama ${duration} ${unitLabel}`}
                disabled={data.disabled}
            />

            {/* Bottom output handle */}
            <Handle
                type="source"
                position={Position.Bottom}
                id="default"
                className="!h-3 !w-3 !rounded-full !border-2 !border-white !bg-purple-600 transition-transform hover:scale-125"
            />
        </div>
    );
};

export const workflowNodeTypes = {
    trigger: TriggerNode,
    condition: ConditionNode,
    action: ActionNode,
    delay: DelayNode,
};
