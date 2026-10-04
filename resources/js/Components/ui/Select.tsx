import { ChevronDown } from 'lucide-react';
import React, { ReactNode, SelectHTMLAttributes } from 'react';

export interface SelectOption {
    value: string | number;
    label: string;
    disabled?: boolean;
}

export interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
    label?: string;
    helperText?: string;
    error?: string;
    options?: SelectOption[];
    placeholder?: string;
    leftIcon?: ReactNode;
}

export const Select = React.forwardRef<HTMLSelectElement, SelectProps>(
    (
        {
            id,
            label,
            helperText,
            error,
            options,
            placeholder,
            leftIcon,
            children,
            className = '',
            disabled,
            ...props
        },
        ref
    ) => {
        const selectId =
            id ||
            (label ? label.toLowerCase().replace(/\s+/g, '-') : undefined);

        return (
            <div className="w-full space-y-1">
                {label && (
                    <label
                        htmlFor={selectId}
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

                    <select
                        ref={ref}
                        id={selectId}
                        disabled={disabled}
                        aria-invalid={Boolean(error)}
                        className={`w-full appearance-none rounded-lg border bg-white py-2 text-xs text-slate-900 transition-colors focus:ring-1 focus:outline-none disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400 ${
                            leftIcon ? 'pl-9' : 'pl-3'
                        } pr-9 ${
                            error
                                ? 'border-rose-400 focus:border-rose-600 focus:ring-rose-600'
                                : 'border-slate-200 focus:border-blue-600 focus:ring-blue-600'
                        } ${className}`}
                        {...props}
                    >
                        {placeholder && (
                            <option value="" disabled>
                                {placeholder}
                            </option>
                        )}
                        {options
                            ? options.map((opt) => (
                                  <option
                                      key={opt.value}
                                      value={opt.value}
                                      disabled={opt.disabled}
                                  >
                                      {opt.label}
                                  </option>
                              ))
                            : children}
                    </select>

                    <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
                        <ChevronDown className="h-4 w-4" />
                    </div>
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

Select.displayName = 'Select';
