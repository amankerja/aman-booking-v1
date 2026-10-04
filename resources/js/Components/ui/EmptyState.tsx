import { Inbox } from 'lucide-react';
import React, { ReactNode } from 'react';

export interface EmptyStateProps {
    icon?: ReactNode;
    title: string;
    description?: string;
    action?: ReactNode;
    className?: string;
}

export const EmptyState: React.FC<EmptyStateProps> = ({
    icon,
    title,
    description,
    action,
    className = '',
}) => {
    return (
        <div
            className={`flex flex-col items-center justify-center rounded-[12px] border border-dashed border-slate-200 bg-white p-8 text-center ${className}`}
        >
            <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-slate-400">
                {icon || <Inbox className="h-6 w-6 stroke-[1.5]" />}
            </div>
            <h3 className="text-xs font-semibold text-slate-900">{title}</h3>
            {description && (
                <p className="mt-1 max-w-sm text-xs leading-relaxed text-slate-500">
                    {description}
                </p>
            )}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
};
