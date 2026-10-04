import { AlertOctagon, AlertTriangle, HelpCircle } from 'lucide-react';
import React, { ReactNode } from 'react';
import { Button } from './Button';
import { Modal } from './Modal';

export interface ConfirmationDialogProps {
    isOpen: boolean;
    onClose: () => void;
    onConfirm: () => void;
    title: string;
    message: ReactNode;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: 'danger' | 'warning' | 'primary';
    isLoading?: boolean;
    icon?: ReactNode;
}

export const ConfirmationDialog: React.FC<ConfirmationDialogProps> = ({
    isOpen,
    onClose,
    onConfirm,
    title,
    message,
    confirmLabel = 'Konfirmasi',
    cancelLabel = 'Batal',
    variant = 'danger',
    isLoading = false,
    icon,
}) => {
    const iconWrapperClasses = {
        danger: 'bg-rose-100 text-rose-600 border border-rose-200',
        warning: 'bg-amber-100 text-amber-700 border border-amber-200',
        primary: 'bg-blue-100 text-blue-600 border border-blue-200',
    }[variant];

    const defaultIcon = {
        danger: <AlertOctagon className="h-5 w-5" />,
        warning: <AlertTriangle className="h-5 w-5" />,
        primary: <HelpCircle className="h-5 w-5" />,
    }[variant];

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            size="sm"
            showCloseButton={false}
            footer={
                <>
                    <Button
                        variant="secondary"
                        size="sm"
                        onClick={onClose}
                        disabled={isLoading}
                    >
                        {cancelLabel}
                    </Button>
                    <Button
                        variant={variant === 'danger' ? 'danger' : 'primary'}
                        size="sm"
                        onClick={onConfirm}
                        isLoading={isLoading}
                    >
                        {confirmLabel}
                    </Button>
                </>
            }
        >
            <div className="flex items-start gap-3.5 py-1">
                <div
                    className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full ${iconWrapperClasses}`}
                >
                    {icon || defaultIcon}
                </div>
                <div className="space-y-1">
                    <h4 className="text-xs font-semibold text-slate-900">
                        {title}
                    </h4>
                    <div className="text-xs leading-relaxed text-slate-600">
                        {message}
                    </div>
                </div>
            </div>
        </Modal>
    );
};
