import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight, Building2, Lock, Mail, User } from 'lucide-react';
import React, { FormEventHandler } from 'react';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        business_name: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/register', {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-slate-50 p-4 sm:p-6">
            <Head title="Pendaftaran Pemilik Usaha" />

            <div className="w-full max-w-lg rounded-xl border border-slate-200 bg-white p-6 sm:p-8">
                <div className="mb-6 text-center">
                    <span className="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                        Trial 14 Hari Tanpa Kartu Kredit
                    </span>
                    <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-900">
                        Mulai dengan AMAN BOOKING
                    </h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Buat portal reservasi dan alur kerja bisnis modern Anda
                        dalam 2 menit
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label
                            className="mb-1 block text-xs font-medium text-slate-700"
                            htmlFor="business_name"
                        >
                            Nama Usaha / Outlet
                        </label>
                        <div className="relative">
                            <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <Building2 className="h-4 w-4" />
                            </span>
                            <input
                                id="business_name"
                                type="text"
                                value={data.business_name}
                                onChange={(e) =>
                                    setData('business_name', e.target.value)
                                }
                                className="w-full rounded-lg border border-slate-200 bg-white py-2 pr-3 pl-9 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-600 focus:ring-1 focus:ring-blue-600 focus:outline-none"
                                placeholder="Contoh: Barbershop Sentosa, Studio Foto Cantik"
                                required
                                autoFocus
                            />
                        </div>
                        {errors.business_name && (
                            <p className="mt-1 text-xs text-rose-600">
                                {errors.business_name}
                            </p>
                        )}
                    </div>

                    <div>
                        <label
                            className="mb-1 block text-xs font-medium text-slate-700"
                            htmlFor="name"
                        >
                            Nama Lengkap Pemilik
                        </label>
                        <div className="relative">
                            <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <User className="h-4 w-4" />
                            </span>
                            <input
                                id="name"
                                type="text"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                className="w-full rounded-lg border border-slate-200 bg-white py-2 pr-3 pl-9 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-600 focus:ring-1 focus:ring-blue-600 focus:outline-none"
                                placeholder="Nama Anda"
                                required
                            />
                        </div>
                        {errors.name && (
                            <p className="mt-1 text-xs text-rose-600">
                                {errors.name}
                            </p>
                        )}
                    </div>

                    <div>
                        <label
                            className="mb-1 block text-xs font-medium text-slate-700"
                            htmlFor="email"
                        >
                            Alamat Email (Untuk Login)
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
                                placeholder="pemilik@usaha.com"
                                required
                            />
                        </div>
                        {errors.email && (
                            <p className="mt-1 text-xs text-rose-600">
                                {errors.email}
                            </p>
                        )}
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label
                                className="mb-1 block text-xs font-medium text-slate-700"
                                htmlFor="password"
                            >
                                Kata Sandi
                            </label>
                            <div className="relative">
                                <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                    <Lock className="h-4 w-4" />
                                </span>
                                <input
                                    id="password"
                                    type="password"
                                    value={data.password}
                                    onChange={(e) =>
                                        setData('password', e.target.value)
                                    }
                                    className="w-full rounded-lg border border-slate-200 bg-white py-2 pr-3 pl-9 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-600 focus:ring-1 focus:ring-blue-600 focus:outline-none"
                                    placeholder="Min. 8 karakter"
                                    required
                                />
                            </div>
                            {errors.password && (
                                <p className="mt-1 text-xs text-rose-600">
                                    {errors.password}
                                </p>
                            )}
                        </div>

                        <div>
                            <label
                                className="mb-1 block text-xs font-medium text-slate-700"
                                htmlFor="password_confirmation"
                            >
                                Ulangi Kata Sandi
                            </label>
                            <div className="relative">
                                <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                    <Lock className="h-4 w-4" />
                                </span>
                                <input
                                    id="password_confirmation"
                                    type="password"
                                    value={data.password_confirmation}
                                    onChange={(e) =>
                                        setData(
                                            'password_confirmation',
                                            e.target.value
                                        )
                                    }
                                    className="w-full rounded-lg border border-slate-200 bg-white py-2 pr-3 pl-9 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-600 focus:ring-1 focus:ring-blue-600 focus:outline-none"
                                    placeholder="Ulangi kata sandi"
                                    required
                                />
                            </div>
                            {errors.password_confirmation && (
                                <p className="mt-1 text-xs text-rose-600">
                                    {errors.password_confirmation}
                                </p>
                            )}
                        </div>
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-blue-700 disabled:opacity-50"
                    >
                        <span>
                            {processing
                                ? 'Mendaftarkan...'
                                : 'Buat Akun Usaha Sekarang'}
                        </span>
                        <ArrowRight className="h-4 w-4" />
                    </button>
                </form>

                <div className="mt-6 border-t border-slate-100 pt-4 text-center">
                    <p className="text-xs text-slate-600">
                        Sudah punya akun?{' '}
                        <Link
                            href="/login"
                            className="font-semibold text-blue-600 hover:text-blue-700"
                        >
                            Masuk ke Portal
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    );
}
