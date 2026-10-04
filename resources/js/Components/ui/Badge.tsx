import {
    AlertOctagon,
    AlertTriangle,
    CheckCircle2,
    Circle,
    Info,
} from 'lucide-react';
import React, { HTMLAttributes, ReactNode } from 'react';

export type BadgeVariant =
    | 'active'
    | 'loading'
    | 'success'
    | 'breakdown'
    | 'danger'
    | 'queuing'
    | 'dumping'
    | 'warning'
    | 'hauling'
    | 'info'
    | 'neutral';

export interface BadgeProps extends HTMLAttributes<HTMLSpanElement> {
    variant?: BadgeVariant;
    size?: 'sm' | 'md';
    icon?: ReactNode;
    showIcon?: boolean;
    dot?: boolean;
}

export const Badge: React.FC<BadgeProps> = ({
    variant = 'neutral',
    size = 'md',
    icon,
    showIcon = true,
    dot = false,
    children,
    className = '',
    ...props
}) => {
    // Semantic colors strictly complying with OFALabs / Fleet Management guidelines
    const variantStyles: Record<
        BadgeVariant,
        { bg: string; text: string; border: string; defaultIcon: ReactNode }
    > = {
        active: {
            bg: 'bg-[#dcfce7]',
            text: 'text-[#15803d]',
            border: 'border-[#bbf7d0]',
            defaultIcon: <CheckCircle2 className="h-3 w-3 shrink-0" />,
        },
        loading: {
            bg: 'bg-[#dcfce7]',
            text: 'text-[#15803d]',
            border: 'border-[#bbf7d0]',
            defaultIcon: <CheckCircle2 className="h-3 w-3 shrink-0" />,
        },
        success: {
            bg: 'bg-[#dcfce7]',
            text: 'text-[#15803d]',
            border: 'border-[#bbf7d0]',
            defaultIcon: <CheckCircle2 className="h-3 w-3 shrink-0" />,
        },
        breakdown: {
            bg: 'bg-[#fee2e2]',
            text: 'text-[#b91c1c]',
            border: 'border-[#fecaca]',
            defaultIcon: <AlertOctagon className="h-3 w-3 shrink-0" />,
        },
        danger: {
            bg: 'bg-[#fee2e2]',
            text: 'text-[#b91c1c]',
            border: 'border-[#fecaca]',
            defaultIcon: <AlertOctagon className="h-3 w-3 shrink-0" />,
        },
        queuing: {
            bg: 'bg-[#fef9c3]',
            text: 'text-[#854d0e]',
            border: 'border-[#fef08a]',
            defaultIcon: <AlertTriangle className="h-3 w-3 shrink-0" />,
        },
        dumping: {
            bg: 'bg-[#fef9c3]',
            text: 'text-[#854d0e]',
            border: 'border-[#fef08a]',
            defaultIcon: <AlertTriangle className="h-3 w-3 shrink-0" />,
        },
        warning: {
            bg: 'bg-[#fef9c3]',
            text: 'text-[#854d0e]',
            border: 'border-[#fef08a]',
            defaultIcon: <AlertTriangle className="h-3 w-3 shrink-0" />,
        },
        hauling: {
            bg: 'bg-[#e0f2fe]',
            text: 'text-[#0369a1]',
            border: 'border-[#bae6fd]',
            defaultIcon: <Info className="h-3 w-3 shrink-0" />,
        },
        info: {
            bg: 'bg-[#e0f2fe]',
            text: 'text-[#0369a1]',
            border: 'border-[#bae6fd]',
            defaultIcon: <Info className="h-3 w-3 shrink-0" />,
        },
        neutral: {
            bg: 'bg-[#f1f5f9]',
            text: 'text-[#475569]',
            border: 'border-[#e2e8f0]',
            defaultIcon: <Circle className="h-2.5 w-2.5 shrink-0" />,
        },
    };

    const current = variantStyles[variant];

    const sizeStyles = {
        sm: 'text-[10px] px-2 py-0.5 gap-1',
        md: 'text-xs px-2.5 py-0.5 gap-1.5',
    }[size];

    const displayIcon =
        icon !== undefined ? icon : showIcon ? current.defaultIcon : null;

    return (
        <span
            className={`inline-flex items-center rounded-full border font-medium ${current.bg} ${current.text} ${current.border} ${sizeStyles} ${className}`}
            {...props}
        >
            {dot && (
                <span className="h-1.5 w-1.5 shrink-0 rounded-full bg-current" />
            )}
            {displayIcon}
            <span>{children}</span>
        </span>
    );
};
