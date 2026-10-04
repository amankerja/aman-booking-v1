import React, { InputHTMLAttributes, ReactNode } from 'react';

export interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
    label?: string;
    helperText?: string;
    error?: string;
    leftIcon?: ReactNode;
    rightIcon?: ReactNode;
}

export const Input = React.forwardRef<HTMLInputElement, InputProps>(
    (
        {
            id,
            label,
            helperText,
            error,
            leftIcon,
            rightIcon,
            className = '',
            disabled,
            ...props
        },
        ref
    ) => {
        const inputId =
            id ||
            (label ? label.toLowerCase().replace(/\s+/g, '-') : undefined);

        return (
            <div className="w-full space-y-1">
                {label && (
                    <label
                        htmlFor={inputId}
                        className="block text-xs font-medium text-slate-700"
                    >
                        {label}
                        {props.required && (
                            <span className="ml-0.5 text-rose-500">*</span>
                        )}
                    </label>
                )}

                <div className="relative rounded-lg">
                    {leftIcon && (
                        <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            {leftIcon}
                        </div>
                    )}

                    <input
                        ref={ref}
                        id={inputId}
                        disabled={disabled}
                        aria-invalid={Boolean(error)}
                        className={`w-full rounded-lg border bg-white py-2 text-xs text-slate-900 transition-colors placeholder:text-slate-400 focus:ring-1 focus:outline-none disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400 ${
                            leftIcon ? 'pl-9' : 'pl-3'
                        } ${rightIcon ? 'pr-9' : 'pr-3'} ${
                            error
                                ? 'border-rose-400 focus:border-rose-600 focus:ring-rose-600'
                                : 'border-slate-200 focus:border-blue-600 focus:ring-blue-600'
                        } ${className}`}
                        {...props}
                    />

                    {rightIcon && (
                        <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
                            {rightIcon}
                        </div>
                    )}
                </div>

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

Input.displayName = 'Input';
