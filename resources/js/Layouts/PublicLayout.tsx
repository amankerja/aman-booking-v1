import { Head } from '@inertiajs/react';
import { Calendar, Phone } from 'lucide-react';
import React, { ReactNode } from 'react';
import { ToastProvider } from '../Components/ui/Toast';

export interface PublicLayoutProps {
    title?: string;
    businessName?: string;
    businessPhone?: string;
    children: ReactNode;
}

const PublicLayoutInner: React.FC<PublicLayoutProps> = ({
    title = 'Reservasi Layanan',
    businessName = 'AMAN BOOKING',
    businessPhone,
    children,
}) => {
    return (
        <div className="flex min-h-screen flex-col justify-between bg-[#f8fafc] text-slate-900">
            <Head title={`${title} - ${businessName}`} />

            {/* Public Header */}
            <header className="sticky top-0 z-30 border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-4xl items-center justify-between px-4 py-3.5 sm:px-6">
                    <div className="flex items-center gap-2.5">
                        <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-600 text-xs font-bold text-white shadow-xs">
                            <Calendar className="h-4 w-4" />
                        </span>
                        <div>
                            <span className="block text-xs font-bold text-slate-900">
                                {businessName}
                            </span>
                            <span className="block text-[10px] text-slate-500">
                                Reservasi Layanan Online
                            </span>
                        </div>
                    </div>

                    {businessPhone && (
                        <a
                            href={`https://wa.me/${businessPhone.replace(/\D/g, '')}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50"
                        >
                            <Phone className="h-3.5 w-3.5 text-emerald-600" />
                            <span className="hidden sm:inline">
                                Hubungi Kami
                            </span>
                        </a>
                    )}
                </div>
            </header>

            {/* Main Content */}
            <main className="mx-auto w-full max-w-4xl flex-1 p-4 sm:p-6 lg:p-8">
                {children}
            </main>

            {/* Footer */}
            <footer className="border-t border-slate-200 bg-white px-4 py-4 text-center">
                <p className="text-[11px] text-slate-400">
                    Didukung oleh{' '}
                    <span className="font-semibold text-slate-600">
                        AMAN BOOKING
                    </span>{' '}
                    — Solusi Manajemen Reservasi & Bisnis Terpercaya.
                </p>
            </footer>
        </div>
    );
};

export const PublicLayout: React.FC<PublicLayoutProps> = (props) => {
    return (
        <ToastProvider>
            <PublicLayoutInner {...props} />
        </ToastProvider>
    );
};
