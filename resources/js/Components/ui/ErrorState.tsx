import { AlertOctagon, RotateCw } from 'lucide-react';
import React, { ReactNode } from 'react';
import { Button } from './Button';

export interface ErrorStateProps {
    title?: string;
    message: string;
    code?: string;
    onRetry?: () => void;
    retryLabel?: string;
    action?: ReactNode;
    className?: string;
}

export const ErrorState: React.FC<ErrorStateProps> = ({
    title = 'Terjadi Kesalahan',
    message,
    code,
    onRetry,
    retryLabel = 'Coba Lagi',
    action,
    className = '',
}) => {
    return (
        <div
            role="alert"
            className={`flex flex-col items-center justify-center rounded-[12px] border border-rose-200 bg-rose-50/30 p-8 text-center ${className}`}
        >
            <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-full border border-rose-200 bg-rose-100 text-rose-600">
                <AlertOctagon className="h-6 w-6 stroke-[1.5]" />
            </div>

            <div className="flex items-center gap-2">
                <h3 className="text-xs font-semibold text-rose-900">{title}</h3>
                {code && (
                    <span className="rounded border border-rose-200 bg-rose-100 px-1.5 py-0.5 font-mono text-[10px] text-rose-700">
                        {code}
                    </span>
                )}
            </div>

            <p className="mt-1 max-w-sm text-xs leading-relaxed text-rose-700">
                {message}
            </p>

            {(onRetry || action) && (
                <div className="mt-4 flex items-center gap-2">
                    {onRetry && (
                        <Button
                            variant="secondary"
                            size="sm"
                            leftIcon={<RotateCw className="h-3 w-3" />}
                            onClick={onRetry}
                        >
                            {retryLabel}
                        </Button>
                    )}
                    {action}
                </div>
            )}
        </div>
    );
};
