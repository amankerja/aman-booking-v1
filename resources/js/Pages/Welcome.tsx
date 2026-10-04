import { Head } from '@inertiajs/react';

export default function Welcome() {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-slate-50 p-6 text-slate-900">
            <Head title="Selamat Datang" />
            <div className="w-full max-w-md rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                <div className="mb-4 inline-flex items-center gap-2 rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                    <span className="h-2 w-2 rounded-full bg-blue-600" />
                    AMAN BOOKING Platform
                </div>
                <h1 className="text-2xl font-bold tracking-tight text-slate-900">
                    Sistem Booking Multi-Tenant
                </h1>
                <p className="mt-2 text-sm text-slate-600">
                    Platform booking modern untuk berbagai jenis usaha dan
                    layanan.
                </p>
                <div className="mt-6 border-t border-slate-100 pt-4">
                    <p className="text-xs text-slate-500">
                        Status Sistem:{' '}
                        <span className="font-medium text-emerald-600">
                            Aktif & Siap Digunakan
                        </span>
                    </p>
                </div>
            </div>
        </div>
    );
}
