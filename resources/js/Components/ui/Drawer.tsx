import { X } from 'lucide-react';
import React, { ReactNode, useEffect, useRef } from 'react';

export interface DrawerProps {
    isOpen: boolean;
    onClose: () => void;
    title?: ReactNode;
    children: ReactNode;
    footer?: ReactNode;
    position?: 'right' | 'left' | 'bottom';
    size?: 'sm' | 'md' | 'lg' | 'xl';
    closeOnOverlayClick?: boolean;
    className?: string;
}

export const Drawer: React.FC<DrawerProps> = ({
    isOpen,
    onClose,
    title,
    children,
    footer,
    position = 'right',
    size = 'md',
    closeOnOverlayClick = true,
    className = '',
}) => {
    const drawerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && isOpen) {
                onClose();
            }
        };

        if (isOpen) {
            document.body.style.overflow = 'hidden';
            window.addEventListener('keydown', handleKeyDown);
        }

        return () => {
            document.body.style.overflow = '';
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [isOpen, onClose]);

    if (!isOpen) return null;

    const widthClasses = {
        sm: 'max-w-xs',
        md: 'max-w-md',
        lg: 'max-w-lg',
        xl: 'max-w-2xl',
    }[size];

    const positionClasses = {
        right: 'inset-y-0 right-0 h-full border-l border-slate-200',
        left: 'inset-y-0 left-0 h-full border-r border-slate-200',
        bottom: 'inset-x-0 bottom-0 max-h-[85vh] rounded-t-2xl border-t border-slate-200',
    }[position];

    const sizeClass =
        position === 'bottom' ? 'w-full' : `w-full ${widthClasses}`;

    return (
        <div
            className="fixed inset-0 z-50 overflow-hidden"
            role="dialog"
            aria-modal="true"
        >
            {/* Backdrop */}
            <div
                className="fixed inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity"
                onClick={closeOnOverlayClick ? onClose : undefined}
                aria-hidden="true"
            />

            <div className="pointer-events-none fixed inset-0">
                <div
                    ref={drawerRef}
                    className={`fixed ${positionClasses} ${sizeClass} pointer-events-auto z-10 flex flex-col bg-white shadow-2xl transition-transform ${className}`}
                >
                    {/* Header */}
                    <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <h3 className="text-sm font-semibold text-slate-900">
                            {title}
                        </h3>
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-lg p-1 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 focus-visible:outline-2 focus-visible:outline-blue-600"
                            aria-label="Tutup panel"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    </div>

                    {/* Content */}
                    <div className="flex-1 overflow-y-auto px-5 py-4 text-xs text-slate-700">
                        {children}
                    </div>

                    {/* Footer */}
                    {footer && (
                        <div className="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/50 px-5 py-3">
                            {footer}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};
