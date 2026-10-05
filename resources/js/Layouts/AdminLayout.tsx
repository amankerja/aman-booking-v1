import { Head, Link, router, usePage } from '@inertiajs/react';
import { Building2, CreditCard, LayoutDashboard, Layers, LogOut } from 'lucide-react';
import React, { ReactNode } from 'react';
import { Badge } from '../Components/ui/Badge';
import { ToastProvider } from '../Components/ui/Toast';

export interface AdminLayoutProps {
    title?: string;
    children: ReactNode;
}

interface SharedAdminAuthProps {
    auth?: {
        user?: {
            id: number;
            name: string;
            email: string;
        } | null;
    };
}

const AdminLayoutInner: React.FC<AdminLayoutProps> = ({ title, children }) => {
    const page = usePage();
    const { auth } = page.props as unknown as SharedAdminAuthProps;
    const currentUrl = page.url;

    const handleLogout = () => {
        router.post('/logout');
    };

    const navItems = [
        {
            label: 'Overview',
            href: '/admin/dashboard',
            icon: <LayoutDashboard className="h-4 w-4" />,
            exact: true,
        },
        {
            label: 'Kelola Tenant',
            href: '/admin/tenants',
            icon: <Building2 className="h-4 w-4" />,
            exact: false,
        },
        {
            label: 'Paket Langganan',
            href: '/admin/plans',
            icon: <CreditCard className="h-4 w-4" />,
            exact: false,
        },
        {
            label: 'Katalog Template',
            href: '/admin/templates',
            icon: <Layers className="h-4 w-4" />,
            exact: false,
        },
    ];

    return (
        <div className="flex min-h-screen flex-col bg-[#f8fafc] text-slate-900">
            {title && <Head title={`${title} - Central Super Admin`} />}

            {/* Topbar for Super Admin */}
            <header className="sticky top-0 z-30 border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6">
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-900 text-sm font-bold text-white shadow-xs">
                            SA
                        </span>
                        <div>
                            <div className="flex items-center gap-2">
                                <span className="text-sm font-bold text-slate-900">
                                    AMAN BOOKING CENTRAL
                                </span>
                                <Badge variant="breakdown" size="sm">
                                    Super Admin
                                </Badge>
                            </div>
                            <p className="text-[10px] text-slate-500">
                                Platform Oversight & Multi-Tenant Management
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <div className="hidden flex-col text-right sm:flex">
                            <span className="text-xs font-semibold text-slate-900">
                                {auth?.user?.name}
                            </span>
                            <span className="text-[10px] text-slate-500">
                                {auth?.user?.email}
                            </span>
                        </div>

                        <button
                            type="button"
                            onClick={handleLogout}
                            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50 hover:text-rose-600"
                        >
                            <LogOut className="h-3.5 w-3.5" />
                            <span className="hidden sm:inline">Keluar</span>
                        </button>
                    </div>
                </div>
            </header>

            {/* Sub-header / Quick Nav */}
            <div className="border-b border-slate-200 bg-white px-4 sm:px-6">
                <div className="mx-auto flex max-w-7xl items-center justify-between">
                    <nav className="flex space-x-4">
                        {navItems.map((item) => {
                            const active = item.exact
                                ? currentUrl === item.href
                                : currentUrl.startsWith(item.href);
                            return (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className={`inline-flex items-center gap-2 border-b-2 px-1 py-3 text-xs font-medium transition-colors ${
                                        active
                                            ? 'border-slate-900 font-semibold text-slate-900'
                                            : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700'
                                    }`}
                                >
                                    {item.icon}
                                    <span>{item.label}</span>
                                </Link>
                            );
                        })}
                    </nav>
                </div>
            </div>

            {/* Main Content */}
            <main className="mx-auto w-full max-w-7xl flex-1 p-4 sm:p-6 lg:p-8">
                {children}
            </main>
        </div>
    );
};

export const AdminLayout: React.FC<AdminLayoutProps> = (props) => {
    return (
        <ToastProvider>
            <AdminLayoutInner {...props} />
        </ToastProvider>
    );
};
