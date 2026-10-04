import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Mail } from 'lucide-react';
import React, { FormEventHandler } from 'react';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/forgot-password');
    };

    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-slate-50 p-4 sm:p-6">
            <Head title="Lupa Kata Sandi" />

            <div className="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 sm:p-8">
                <div className="mb-6 text-center">
                    <span className="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                        PEMULIHAN AKUN
                    </span>
                    <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900">
                        Atur Ulang Kata Sandi
                    </h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Masukkan alamat email Anda untuk menerima tautan
                        pemulihan kata sandi
                    </p>
                </div>

                {status && (
                    <div className="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-xs font-medium text-emerald-800">
                        {status}
                    </div>
                )}

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label
                            className="mb-1 block text-xs font-medium text-slate-700"
                            htmlFor="email"
                        >
                            Alamat Email
                        </label>
                        <div className="relative">
                            <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <Mail className="h-4 w-4" />
                            </span>
                            <input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                                className="w-full rounded-lg border border-slate-200 bg-white py-2 pr-3 pl-9 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-600 focus:ring-1 focus:ring-blue-600 focus:outline-none"
                                placeholder="nama@usaha.com"
                                required
                                autoFocus
                            />
                        </div>
                        {errors.email && (
                            <p className="mt-1 text-xs text-rose-600">
                                {errors.email}
                            </p>
                        )}
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-blue-700 disabled:opacity-50"
                    >
                        <span>
                            {processing
                                ? 'Mengirim...'
                                : 'Kirim Tautan Pemulihan'}
                        </span>
                        <ArrowRight className="h-4 w-4" />
                    </button>
                </form>

                <div className="mt-6 border-t border-slate-100 pt-4 text-center">
                    <Link
                        href="/login"
                        className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-slate-900"
                    >
                        <ArrowLeft className="h-3.5 w-3.5" />
                        <span>Kembali ke Halaman Masuk</span>
                    </Link>
                </div>
            </div>
        </div>
    );
}
