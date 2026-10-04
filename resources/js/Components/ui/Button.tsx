import { Loader2 } from 'lucide-react';
import React, { ButtonHTMLAttributes, ReactNode } from 'react';

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: 'primary' | 'secondary' | 'outline' | 'danger' | 'ghost';
    size?: 'sm' | 'md' | 'lg';
    isLoading?: boolean;
    leftIcon?: ReactNode;
    rightIcon?: ReactNode;
}

export const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
    (
        {
            children,
            variant = 'primary',
            size = 'md',
            isLoading = false,
            leftIcon,
            rightIcon,
            disabled,
            className = '',
            ...props
        },
        ref
    ) => {
        const baseClasses =
            'inline-flex items-center justify-center font-semibold rounded-lg transition-colors focus-visible:outline-2 focus-visible:outline-blue-600 focus-visible:outline-offset-2 disabled:opacity-50 disabled:pointer-events-none select-none';

        const sizeClasses = {
            sm: 'px-2.5 py-1.5 text-xs gap-1.5',
            md: 'px-3.5 py-2 text-xs gap-2',
            lg: 'px-4 py-2.5 text-sm gap-2',
        }[size];

        const variantClasses = {
            primary:
                'bg-blue-600 text-white hover:bg-blue-700 active:bg-blue-800',
            secondary:
                'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 active:bg-slate-100',
            outline:
                'border border-blue-600 text-blue-600 hover:bg-blue-50 active:bg-blue-100',
            danger: 'bg-rose-600 text-white hover:bg-rose-700 active:bg-rose-800',
            ghost: 'text-slate-600 hover:bg-slate-100 active:bg-slate-200',
        }[variant];

        return (
            <button
                ref={ref}
                disabled={disabled || isLoading}
                className={`${baseClasses} ${sizeClasses} ${variantClasses} ${className}`}
                {...props}
            >
                {isLoading ? (
                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                ) : (
                    leftIcon
                )}
                <span>{children}</span>
                {!isLoading && rightIcon}
            </button>
        );
    }
);

Button.displayName = 'Button';
