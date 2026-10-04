import React, { TextareaHTMLAttributes } from 'react';

export interface TextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
    label?: string;
    helperText?: string;
    error?: string;
    showCount?: boolean;
}

export const Textarea = React.forwardRef<HTMLTextAreaElement, TextareaProps>(
    (
        {
            id,
            label,
            helperText,
            error,
            showCount = false,
            maxLength,
            value,
            defaultValue,
            rows = 3,
            className = '',
            disabled,
            ...props
        },
        ref
    ) => {
        const textareaId =
            id ||
            (label ? label.toLowerCase().replace(/\s+/g, '-') : undefined);

        const currentLength =
            typeof value === 'string'
                ? value.length
                : typeof defaultValue === 'string'
                  ? defaultValue.length
                  : 0;

        return (
            <div className="w-full space-y-1">
                <div className="flex items-center justify-between">
                    {label && (
                        <label
                            htmlFor={textareaId}
                            className="block text-xs font-medium text-slate-700"
                        >
                            {label}
                            {props.required && (
                                <span className="ml-0.5 text-rose-500">*</span>
                            )}
                        </label>
                    )}
                    {showCount && maxLength && (
                        <span className="text-[11px] text-slate-400">
                            {currentLength}/{maxLength}
                        </span>
                    )}
                </div>

                <textarea
                    ref={ref}
                    id={textareaId}
                    rows={rows}
                    maxLength={maxLength}
                    value={value}
                    defaultValue={defaultValue}
                    disabled={disabled}
                    aria-invalid={Boolean(error)}
                    className={`w-full rounded-lg border bg-white p-2.5 text-xs text-slate-900 transition-colors placeholder:text-slate-400 focus:ring-1 focus:outline-none disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400 ${
                        error
                            ? 'border-rose-400 focus:border-rose-600 focus:ring-rose-600'
                            : 'border-slate-200 focus:border-blue-600 focus:ring-blue-600'
                    } ${className}`}
                    {...props}
                />

                {error ? (
                    <p className="text-[11px] font-medium text-rose-600">
                        {error}
                    </p>
                ) : helperText ? (
                    <p className="text-[11px] text-slate-500">{helperText}</p>
                ) : null}
            </div>
        );
    }
);

Textarea.displayName = 'Textarea';
