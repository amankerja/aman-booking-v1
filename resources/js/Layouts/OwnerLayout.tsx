import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Building2,
    Calendar,
    CalendarDays,
    Clock,
    History,
    Layers,
    LayoutDashboard,
    LogOut,
    Menu,
    Palette,
    UserCheck,
    Users,
} from 'lucide-react';
import React, { ReactNode, useEffect, useState } from 'react';
import { Badge } from '../Components/ui/Badge';
import { Drawer } from '../Components/ui/Drawer';
import { ToastProvider, useToast } from '../Components/ui/Toast';
import { Tooltip } from '../Components/ui/Tooltip';

export interface OwnerLayoutProps {
    title?: string;
    breadcrumbs?: Array<{ label: string; href?: string }>;
    actions?: ReactNode;
    children: ReactNode;
}

interface SharedAuthProps {
    auth?: {
        user?: {
            id: number;
            name: string;
            email: string;
        } | null;
        tenant?: {
            id: number;
            name: string;
        } | null;
    };
    flash?: {
        success?: string | null;
        error?: string | null;
    };
    business?: {
        id: number;
        name: string;
        slug: string;
    } | null;
    subscription?: {
        status: string;
        plan_name: string;
    } | null;
}

const FlashMessageHandler: React.FC = () => {
    const { flash } = usePage().props as unknown as SharedAuthProps;
    const toast = useToast();

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash, toast]);

    return null;
};

const OwnerLayoutInner: React.FC<OwnerLayoutProps> = ({
    title,
    breadcrumbs,
    actions,
    children,
}) => {
    const page = usePage();
    const { auth, business, subscription } =
        page.props as unknown as SharedAuthProps;
    const currentUrl = page.url;
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

    const handleLogout = () => {
        router.post('/logout');
    };

    const navItems = [
        {
            label: 'Dashboard',
            href: '/app/dashboard',
            icon: <LayoutDashboard className="h-4 w-4" />,
            exact: true,
        },
        {
            label: 'Booking',
            href: '/app/bookings',
            icon: <Calendar className="h-4 w-4" />,
            exact: false,
        },
        {
            label: 'Layanan & Paket',
            href: '/app/services',
            icon: <Layers className="h-4 w-4" />,
            exact: false,
        },
        {
            label: 'Resource & Tim',
            href: '/app/resources',
            icon: <Users className="h-4 w-4" />,
            exact: false,
        },
        {
            label: 'Pelanggan',
            href: '/app/customers',
            icon: <UserCheck className="h-4 w-4" />,
            exact: false,
        },
        {
            label: 'Profil Bisnis',
            href: '/app/settings/business',
            icon: <Building2 className="h-4 w-4" />,
            exact: false,
        },
        {
            label: 'Jam Operasional',
            href: '/app/settings/hours',
            icon: <Clock className="h-4 w-4" />,
            exact: false,
        },
        {
            label: 'Kalender & Libur',
            href: '/app/settings/calendar',
            icon: <CalendarDays className="h-4 w-4" />,
            exact: false,
        },
        {
            label: 'Audit Logs',
            href: '/app/audit-logs',
            icon: <History className="h-4 w-4" />,
            exact: false,
        },
        {
            label: 'Styleguide',
            href: '/app/_styleguide',
            icon: <Palette className="h-4 w-4" />,
            exact: false,
        },
    ];

    const isLinkActive = (href: string, exact = false) => {
        if (exact) {
            return currentUrl === href;
        }
        return currentUrl.startsWith(href);
    };

    return (
        <div className="flex min-h-screen flex-col bg-[#f8fafc] text-slate-900">
            <FlashMessageHandler />
            {title && <Head title={`${title} - AMAN BOOKING`} />}

            {/* Skip to Content for Accessibility */}
            <a
                href="#main-content"
                className="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-md focus:bg-blue-600 focus:px-3 focus:py-2 focus:text-xs focus:font-semibold focus:text-white focus:outline-none"
            >
                Lewati ke konten utama
            </a>

            {/* Topbar for Mobile (< 768px) */}
            <header className="sticky top-0 z-30 flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 md:hidden">
                <div className="flex items-center gap-2.5">
                    <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-600 text-xs font-bold text-white">
                        AB
                    </span>
                    <div className="leading-tight">
                        <span className="block max-w-[160px] truncate text-xs font-semibold text-slate-900">
                            {business?.name || 'AMAN BOOKING'}
                        </span>
                        <span className="block font-mono text-[10px] text-slate-500">
                            {business?.slug || 'workspace'}
                        </span>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    {subscription && (
                        <Badge
                            variant={
                                subscription.status === 'ACTIVE'
                                    ? 'active'
                                    : 'queuing'
                            }
                            size="sm"
                        >
                            {subscription.plan_name}
                        </Badge>
                    )}
                    <button
                        type="button"
                        onClick={() => setMobileMenuOpen(true)}
                        className="rounded-lg p-1.5 text-slate-600 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-blue-600"
                        aria-label="Buka menu navigasi"
                    >
                        <Menu className="h-5 w-5" />
                    </button>
                </div>
            </header>

            <div className="flex flex-1">
                {/* Desktop Sidebar (lg: >= 1024px) */}
                <aside className="sticky top-0 hidden h-screen w-64 shrink-0 flex-col border-r border-slate-200 bg-white lg:flex">
                    {/* Brand / Business Header */}
                    <div className="flex items-center gap-3 border-b border-slate-100 p-4">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-600 text-sm font-bold text-white shadow-xs">
                            AB
                        </span>
                        <div className="min-w-0 flex-1">
                            <h2 className="truncate text-xs font-bold text-slate-900">
                                {business?.name || 'AMAN BOOKING'}
                            </h2>
                            <div className="mt-0.5 flex items-center gap-1.5">
                                <span className="truncate font-mono text-[10px] text-slate-500">
                                    {business?.slug || 'portal'}
                                </span>
                                {subscription && (
                                    <span className="py-0.2 inline-flex rounded border border-blue-200 bg-blue-50 px-1.5 text-[9px] font-semibold text-blue-700 uppercase">
                                        {subscription.plan_name}
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Navigation Menu */}
                    <nav
                        className="flex-1 space-y-1 overflow-y-auto p-3"
                        aria-label="Menu Utama"
                    >
                        <div className="px-3 py-1.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                            Navigasi
                        </div>
                        {navItems.map((item) => {
                            const active = isLinkActive(item.href, item.exact);
                            return (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className={`flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-medium transition-colors ${
                                        active
                                            ? 'bg-blue-50 font-semibold text-blue-700'
                                            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                                    }`}
                                >
                                    <span
                                        className={
                                            active
                                                ? 'text-blue-600'
                                                : 'text-slate-400'
                                        }
                                    >
                                        {item.icon}
                                    </span>
                                    <span>{item.label}</span>
                                </Link>
                            );
                        })}
                    </nav>

                    {/* User Profile / Logout */}
                    <div className="border-t border-slate-100 p-3">
                        <div className="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 p-2">
                            <div className="flex min-w-0 items-center gap-2.5">
                                <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-semibold text-blue-700">
                                    {auth?.user?.name?.charAt(0) || 'U'}
                                </div>
                                <div className="min-w-0 flex-1 leading-tight">
                                    <p className="truncate text-xs font-semibold text-slate-800">
                                        {auth?.user?.name}
                                    </p>
                                    <p className="truncate text-[10px] text-slate-400">
                                        {auth?.user?.email}
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={handleLogout}
                                className="rounded p-1 text-slate-400 transition-colors hover:bg-slate-200 hover:text-rose-600"
                                title="Keluar akun"
                                aria-label="Keluar akun"
                            >
                                <LogOut className="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>
                </aside>

                {/* Tablet Sidebar (md: 768px - 1023px) */}
                <aside className="sticky top-0 hidden h-screen w-16 shrink-0 flex-col items-center justify-between border-r border-slate-200 bg-white py-4 md:flex lg:hidden">
                    <div className="flex w-full flex-col items-center gap-4 px-2">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-600 text-sm font-bold text-white">
                            AB
                        </span>

                        <nav className="flex w-full flex-col items-center gap-1.5 border-t border-slate-100 pt-4">
                            {navItems.map((item) => {
                                const active = isLinkActive(
                                    item.href,
                                    item.exact
                                );
                                return (
                                    <Tooltip
                                        key={item.href}
                                        content={item.label}
                                        position="right"
                                    >
                                        <Link
                                            href={item.href}
                                            aria-label={item.label}
                                            className={`flex h-10 w-10 items-center justify-center rounded-lg transition-colors ${
                                                active
                                                    ? 'bg-blue-50 text-blue-600'
                                                    : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900'
                                            }`}
                                        >
                                            {item.icon}
                                        </Link>
                                    </Tooltip>
                                );
                            })}
                        </nav>
                    </div>

                    <div className="flex w-full flex-col items-center gap-2 border-t border-slate-100 px-2 pt-3">
                        <Tooltip content="Keluar" position="right">
                            <button
                                type="button"
                                onClick={handleLogout}
                                className="flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600"
                                aria-label="Keluar akun"
                            >
                                <LogOut className="h-4 w-4" />
                            </button>
                        </Tooltip>
                    </div>
                </aside>

                {/* Mobile Drawer Navigation */}
                <Drawer
                    isOpen={mobileMenuOpen}
                    onClose={() => setMobileMenuOpen(false)}
                    title={business?.name || 'AMAN BOOKING'}
                    position="left"
                    size="sm"
                >
                    <div className="space-y-4">
                        <div className="rounded-lg border border-slate-100 bg-slate-50 p-3">
                            <p className="text-xs font-semibold text-slate-900">
                                {auth?.user?.name}
                            </p>
                            <p className="text-[11px] text-slate-500">
                                {auth?.user?.email}
                            </p>
                            {subscription && (
                                <div className="mt-2">
                                    <Badge variant="hauling" size="sm">
                                        Paket {subscription.plan_name}
                                    </Badge>
                                </div>
                            )}
                        </div>

                        <nav className="space-y-1">
                            {navItems.map((item) => {
                                const active = isLinkActive(
                                    item.href,
                                    item.exact
                                );
                                return (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className={`flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-medium transition-colors ${
                                            active
                                                ? 'bg-blue-50 font-semibold text-blue-700'
                                                : 'text-slate-600 hover:bg-slate-50'
                                        }`}
                                    >
                                        <span
                                            className={
                                                active
                                                    ? 'text-blue-600'
                                                    : 'text-slate-400'
                                            }
                                        >
                                            {item.icon}
                                        </span>
                                        <span>{item.label}</span>
                                    </Link>
                                );
                            })}
                        </nav>

                        <div className="border-t border-slate-100 pt-4">
                            <button
                                type="button"
                                onClick={handleLogout}
                                className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium text-rose-600 transition-colors hover:bg-rose-50"
                            >
                                <LogOut className="h-4 w-4" />
                                <span>Keluar Akun</span>
                            </button>
                        </div>
                    </div>
                </Drawer>

                {/* Main Content Viewport */}
                <div className="flex min-w-0 flex-1 flex-col pb-16 md:pb-0">
                    {/* Desktop / Tablet Header */}
                    <header className="sticky top-0 z-20 hidden h-14 items-center justify-between border-b border-slate-200 bg-white px-6 md:flex">
                        <div className="flex items-center gap-2">
                            {breadcrumbs && breadcrumbs.length > 0 ? (
                                <nav
                                    className="flex items-center gap-1.5 text-xs text-slate-500"
                                    aria-label="Breadcrumb"
                                >
                                    {breadcrumbs.map((crumb, i) => (
                                        <React.Fragment key={crumb.label}>
                                            {i > 0 && <span>/</span>}
                                            {crumb.href ? (
                                                <Link
                                                    href={crumb.href}
                                                    className="transition-colors hover:text-slate-900"
                                                >
                                                    {crumb.label}
                                                </Link>
                                            ) : (
                                                <span className="font-semibold text-slate-900">
                                                    {crumb.label}
                                                </span>
                                            )}
                                        </React.Fragment>
                                    ))}
                                </nav>
                            ) : (
                                <h1 className="text-xs font-bold text-slate-900">
                                    {title}
                                </h1>
                            )}
                        </div>

                        <div className="flex items-center gap-3">
                            {actions}
                            {subscription?.status === 'TRIAL' && (
                                <Badge variant="hauling" size="sm">
                                    Trial Aktif ({subscription.plan_name})
                                </Badge>
                            )}
                        </div>
                    </header>

                    {/* Main Content Body */}
                    <main
                        id="main-content"
                        className="mx-auto w-full max-w-7xl flex-1 p-4 sm:p-6 lg:p-8"
                    >
                        {children}
                    </main>
                </div>
            </div>

            {/* Sticky Bottom Navigation (< 768px) */}
            <nav
                className="fixed inset-x-0 bottom-0 z-40 flex items-center justify-around border-t border-slate-200 bg-white px-2 py-1.5 md:hidden"
                aria-label="Navigasi Bawah"
            >
                {navItems.map((item) => {
                    const active = isLinkActive(item.href, item.exact);
                    return (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={`flex flex-col items-center gap-0.5 rounded-md px-3 py-1 text-[10px] font-medium transition-colors ${
                                active
                                    ? 'font-semibold text-blue-600'
                                    : 'text-slate-500 hover:text-slate-900'
                            }`}
                        >
                            {item.icon}
                            <span>{item.label}</span>
                        </Link>
                    );
                })}
                <button
                    type="button"
                    onClick={() => setMobileMenuOpen(true)}
                    className="flex flex-col items-center gap-0.5 rounded-md px-3 py-1 text-[10px] font-medium text-slate-500 hover:text-slate-900"
                >
                    <Menu className="h-4 w-4" />
                    <span>Menu</span>
                </button>
            </nav>
        </div>
    );
};

export const OwnerLayout: React.FC<OwnerLayoutProps> = (props) => {
    return (
        <ToastProvider>
            <OwnerLayoutInner {...props} />
        </ToastProvider>
    );
};
