import { Paperclip, UploadCloud } from 'lucide-react';
import React from 'react';
import { Input } from '../../Components/ui/Input';
import { Textarea } from '../../Components/ui/Textarea';
import { FormFieldItem } from '../Owner/Bookings/types';

interface CustomFormFieldInputProps {
    field: FormFieldItem;
    value: unknown;
    fileValue?: File | null;
    error?: string;
    onChange: (fieldKey: string, value: unknown) => void;
    onFileChange: (fieldKey: string, file: File | null) => void;
}

export const CustomFormFieldInput: React.FC<CustomFormFieldInputProps> = ({
    field,
    value,
    fileValue,
    error,
    onChange,
    onFileChange,
}) => {
    const fieldId = `field_${field.field_key}`;

    return (
        <div className="space-y-1">
            <label
                htmlFor={fieldId}
                className="block text-xs font-semibold text-slate-700"
            >
                {field.label}{' '}
                {field.is_required && (
                    <span className="text-red-500">*</span>
                )}
            </label>

            {field.help_text && (
                <p className="text-[11px] text-slate-400">{field.help_text}</p>
            )}

            {/* Render input based on field type */}
            {field.type === 'textarea' && (
                <Textarea
                    id={fieldId}
                    rows={3}
                    value={value || ''}
                    placeholder={field.placeholder || ''}
                    error={error}
                    onChange={(e) => onChange(field.field_key, e.target.value)}
                    className="text-sm"
                />
            )}

            {(field.type === 'text' ||
                field.type === 'email' ||
                field.type === 'phone' ||
                field.type === 'number' ||
                field.type === 'currency' ||
                field.type === 'date' ||
                field.type === 'time') && (
                <Input
                    id={fieldId}
                    type={
                        field.type === 'number' || field.type === 'currency'
                            ? 'number'
                            : field.type === 'email'
                              ? 'email'
                              : field.type === 'phone'
                                ? 'tel'
                                : field.type === 'date'
                                  ? 'date'
                                  : field.type === 'time'
                                    ? 'time'
                                    : 'text'
                    }
                    value={value !== undefined && value !== null ? value : ''}
                    placeholder={field.placeholder || ''}
                    error={error}
                    onChange={(e) => onChange(field.field_key, e.target.value)}
                    className="text-sm"
                />
            )}

            {field.type === 'select' && (
                <select
                    id={fieldId}
                    value={value || ''}
                    onChange={(e) => onChange(field.field_key, e.target.value)}
                    className={`w-full rounded-[8px] border bg-white px-3 py-2 text-sm text-slate-900 transition-colors focus:border-blue-600 focus:outline-hidden ${
                        error ? 'border-red-500' : 'border-slate-200'
                    }`}
                >
                    <option value="">
                        {field.placeholder || '-- Pilih Salah Satu --'}
                    </option>
                    {field.options?.map((opt, i) => (
                        <option key={i} value={opt.value}>
                            {opt.label}
                        </option>
                    ))}
                </select>
            )}

            {field.type === 'radio' && (
                <div className="space-y-2 pt-1">
                    {field.options?.map((opt, i) => (
                        <label
                            key={i}
                            className={`flex cursor-pointer items-center gap-2.5 rounded-[8px] border p-2.5 transition-all ${
                                String(value) === String(opt.value)
                                    ? 'border-blue-600 bg-blue-50/50 font-medium text-blue-900'
                                    : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'
                            }`}
                        >
                            <input
                                type="radio"
                                name={field.field_key}
                                value={opt.value}
                                checked={String(value) === String(opt.value)}
                                onChange={() => onChange(field.field_key, opt.value)}
                                className="h-4 w-4 text-blue-600 border-slate-300 focus:ring-blue-500"
                            />
                            <span className="text-xs">{opt.label}</span>
                        </label>
                    ))}
                </div>
            )}

            {field.type === 'checkbox' && (
                <label className="flex cursor-pointer items-start gap-2.5 rounded-[8px] border border-slate-200 bg-white p-2.5 text-xs text-slate-700 hover:bg-slate-50">
                    <input
                        type="checkbox"
                        checked={Boolean(value)}
                        onChange={(e) => onChange(field.field_key, e.target.checked)}
                        className="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                    />
                    <span>{field.placeholder || field.label}</span>
                </label>
            )}

            {field.type === 'multiselect' && (
                <div className="space-y-1.5 pt-1">
                    {field.options?.map((opt, i) => {
                        const arrValue = Array.isArray(value) ? value : [];
                        const isChecked = arrValue.includes(opt.value);
                        return (
                            <label
                                key={i}
                                className={`flex cursor-pointer items-center gap-2.5 rounded-[8px] border p-2 text-xs transition-all ${
                                    isChecked
                                        ? 'border-blue-600 bg-blue-50/50 font-medium text-blue-900'
                                        : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'
                                }`}
                            >
                                <input
                                    type="checkbox"
                                    checked={isChecked}
                                    onChange={(e) => {
                                        if (e.target.checked) {
                                            onChange(field.field_key, [...arrValue, opt.value]);
                                        } else {
                                            onChange(
                                                field.field_key,
                                                arrValue.filter((v: unknown) => v !== opt.value)
                                            );
                                        }
                                    }}
                                    className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                />
                                <span>{opt.label}</span>
                            </label>
                        );
                    })}
                </div>
            )}

            {field.type === 'file' && (
                <div className="pt-1">
                    <label
                        className={`flex cursor-pointer flex-col items-center justify-center rounded-[8px] border border-dashed p-3 text-center transition-all ${
                            fileValue
                                ? 'border-blue-600 bg-blue-50/40 text-blue-900'
                                : error
                                  ? 'border-red-400 bg-red-50/30'
                                  : 'border-slate-300 bg-slate-50/60 hover:bg-slate-100'
                        }`}
                    >
                        <input
                            type="file"
                            className="hidden"
                            onChange={(e) => {
                                const file = e.target.files?.[0] || null;
                                onFileChange(field.field_key, file);
                            }}
                        />
                        {fileValue ? (
                            <div className="flex items-center gap-2 text-xs font-semibold text-blue-700">
                                <Paperclip className="h-4 w-4" />
                                <span>{fileValue.name}</span>
                                <span className="text-[10px] text-slate-500">
                                    ({Math.round(fileValue.size / 1024)} KB)
                                </span>
                            </div>
                        ) : (
                            <>
                                <UploadCloud className="h-6 w-6 text-slate-400" />
                                <span className="mt-1 text-xs font-semibold text-slate-700">
                                    Klik untuk unggah berkas
                                </span>
                                <span className="mt-0.5 text-[10px] text-slate-400">
                                    PNG, JPG, PDF (Maks. 5 MB)
                                </span>
                            </>
                        )}
                    </label>
                </div>
            )}

            {field.type === 'address' && (
                <Textarea
                    id={fieldId}
                    rows={2}
                    value={value || ''}
                    placeholder={
                        field.placeholder ||
                        'Alamat lengkap (nama jalan, nomor rumah, RT/RW, kota)'
                    }
                    error={error}
                    onChange={(e) => onChange(field.field_key, e.target.value)}
                    className="text-sm"
                />
            )}

            {error && (
                <span className="block text-[11px] text-red-600">{error}</span>
            )}
        </div>
    );
};
