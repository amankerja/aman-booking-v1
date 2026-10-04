import {
    AlertOctagon,
    AlertTriangle,
    CheckCircle2,
    Info,
    X,
} from 'lucide-react';
import React, {
    createContext,
    ReactNode,
    useCallback,
    useContext,
    useState,
} from 'react';

export type ToastType = 'success' | 'error' | 'warning' | 'info';

export interface ToastItem {
    id: string;
    type: ToastType;
    message: string;
    title?: string;
    duration?: number;
}

interface ToastContextType {
    toasts: ToastItem[];
    addToast: (toast: Omit<ToastItem, 'id'>) => string;
    removeToast: (id: string) => void;
    success: (message: string, title?: string) => string;
    error: (message: string, title?: string) => string;
    warning: (message: string, title?: string) => string;
    info: (message: string, title?: string) => string;
}

const ToastContext = createContext<ToastContextType | undefined>(undefined);

export const ToastProvider: React.FC<{ children: ReactNode }> = ({
    children,
}) => {
    const [toasts, setToasts] = useState<ToastItem[]>([]);

    const removeToast = useCallback((id: string) => {
        setToasts((prev) => prev.filter((t) => t.id !== id));
    }, []);

    const addToast = useCallback(
        ({ type, message, title, duration = 4000 }: Omit<ToastItem, 'id'>) => {
            const id = Math.random().toString(36).substring(2, 9);
            const newToast: ToastItem = { id, type, message, title, duration };

            setToasts((prev) => [...prev, newToast]);

            if (duration > 0) {
                setTimeout(() => {
                    removeToast(id);
                }, duration);
            }

            return id;
        },
        [removeToast]
    );

    const success = useCallback(
        (message: string, title?: string) =>
            addToast({ type: 'success', message, title }),
        [addToast]
    );

    const error = useCallback(
        (message: string, title?: string) =>
            addToast({ type: 'error', message, title, duration: 6000 }),
        [addToast]
    );

    const warning = useCallback(
        (message: string, title?: string) =>
            addToast({ type: 'warning', message, title }),
        [addToast]
    );

    const info = useCallback(
        (message: string, title?: string) =>
            addToast({ type: 'info', message, title }),
        [addToast]
    );

    return (
        <ToastContext.Provider
            value={{
                toasts,
                addToast,
                removeToast,
                success,
                error,
                warning,
                info,
            }}
        >
            {children}
            <ToastContainer toasts={toasts} onDismiss={removeToast} />
        </ToastContext.Provider>
    );
};

export const useToast = (): ToastContextType => {
    const context = useContext(ToastContext);
    if (!context) {
        throw new Error('useToast must be used within a ToastProvider');
    }
    return context;
};

export const ToastContainer: React.FC<{
    toasts: ToastItem[];
    onDismiss: (id: string) => void;
}> = ({ toasts, onDismiss }) => {
    if (toasts.length === 0) return null;

    return (
        <div
            className="pointer-events-none fixed top-4 right-4 z-60 flex w-full max-w-sm flex-col gap-2 p-2 sm:p-0"
            aria-live="polite"
        >
            {toasts.map((toast) => (
                <div
                    key={toast.id}
                    className="pointer-events-auto flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-3.5 shadow-lg transition-all"
                >
                    <div className="mt-0.5 shrink-0">
                        {toast.type === 'success' && (
                            <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                        )}
                        {toast.type === 'error' && (
                            <AlertOctagon className="h-4 w-4 text-rose-600" />
                        )}
                        {toast.type === 'warning' && (
                            <AlertTriangle className="h-4 w-4 text-amber-600" />
                        )}
                        {toast.type === 'info' && (
                            <Info className="h-4 w-4 text-sky-600" />
                        )}
                    </div>

                    <div className="min-w-0 flex-1">
                        {toast.title && (
                            <h4 className="text-xs font-semibold text-slate-900">
                                {toast.title}
                            </h4>
                        )}
                        <p className="text-xs break-words text-slate-600">
                            {toast.message}
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={() => onDismiss(toast.id)}
                        className="shrink-0 rounded p-1 text-slate-400 transition-colors hover:text-slate-600"
                        aria-label="Tutup notifikasi"
                    >
                        <X className="h-3.5 w-3.5" />
                    </button>
                </div>
            ))}
        </div>
    );
};
