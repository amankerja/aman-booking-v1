import React from 'react';

export interface SkeletonProps {
    variant?: 'text' | 'rect' | 'circle' | 'table-row';
    width?: string | number;
    height?: string | number;
    lines?: number;
    className?: string;
}

export const Skeleton: React.FC<SkeletonProps> = ({
    variant = 'rect',
    width,
    height,
    lines = 1,
    className = '',
}) => {
    const baseClasses = 'animate-pulse bg-slate-200/80';

    const parseDimension = (val?: string | number) =>
        typeof val === 'number' ? `${val}px` : val;

    const style = {
        width: parseDimension(width),
        height: parseDimension(height),
    };

    if (variant === 'circle') {
        return (
            <div
                style={style}
                className={`${baseClasses} shrink-0 rounded-full ${
                    !width && !height ? 'h-8 w-8' : ''
                } ${className}`}
            />
        );
    }

    if (variant === 'text') {
        return (
            <div className={`w-full space-y-2 ${className}`}>
                {Array.from({ length: lines }).map((_, i) => (
                    <div
                        key={i}
                        style={{
                            width:
                                i === lines - 1 && lines > 1
                                    ? '70%'
                                    : parseDimension(width) || '100%',
                            height: parseDimension(height) || '12px',
                        }}
                        className={`${baseClasses} rounded`}
                    />
                ))}
            </div>
        );
    }

    if (variant === 'table-row') {
        return (
            <div className={`flex items-center gap-4 px-4 py-3 ${className}`}>
                <div className={`${baseClasses} h-4 w-12 rounded`} />
                <div className={`${baseClasses} h-4 w-32 rounded`} />
                <div className={`${baseClasses} h-4 w-24 rounded`} />
                <div className={`${baseClasses} h-4 flex-1 rounded`} />
                <div className={`${baseClasses} h-4 w-16 rounded`} />
            </div>
        );
    }

    // Default 'rect'
    return (
        <div
            style={style}
            className={`${baseClasses} rounded-lg ${
                !height ? 'h-24' : ''
            } w-full ${className}`}
        />
    );
};
