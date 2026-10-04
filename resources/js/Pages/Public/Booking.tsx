import { Head } from '@inertiajs/react';

export default function Booking() {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-slate-50 p-6 text-slate-900">
            <Head title="Reservasi Layanan" />
            <div className="w-full max-w-lg rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                <div className="mb-4 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                    <span className="h-2 w-2 rounded-full bg-emerald-600" />
                    Portal Reservasi Publik
                </div>
                <h1 className="text-2xl font-bold tracking-tight text-slate-900">
                    Formulir Pemesanan
                </h1>
                <p className="mt-2 text-sm text-slate-600">
                    Pilih layanan dan waktu reservasi Anda dengan mudah dan
                    cepat.
                </p>
                <div className="mt-6 rounded-lg bg-slate-50 p-4 text-xs text-slate-500">
                    Layanan booking instan tanpa perlu registrasi akun.
                </div>
            </div>
        </div>
    );
}
