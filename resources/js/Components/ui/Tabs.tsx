import React, { KeyboardEvent, ReactNode, useRef } from 'react';

export interface TabItem {
    id: string;
    label: string;
    icon?: ReactNode;
    badge?: string | number;
    disabled?: boolean;
}

export interface TabsProps {
    tabs: TabItem[];
    activeTab: string;
    onChange: (id: string) => void;
    variant?: 'line' | 'pills';
    className?: string;
}

export const Tabs: React.FC<TabsProps> = ({
    tabs,
    activeTab,
    onChange,
    variant = 'line',
    className = '',
}) => {
    const tabRefs = useRef<(HTMLButtonElement | null)[]>([]);

    const handleKeyDown = (
        e: KeyboardEvent<HTMLButtonElement>,
        index: number
    ) => {
        const availableTabs = tabs.filter((t) => !t.disabled);
        const currentIndex = availableTabs.findIndex(
            (t) => t.id === tabs[index].id
        );

        let nextIndex = -1;

        if (e.key === 'ArrowRight') {
            nextIndex = (currentIndex + 1) % availableTabs.length;
        } else if (e.key === 'ArrowLeft') {
            nextIndex =
                (currentIndex - 1 + availableTabs.length) %
                availableTabs.length;
        } else if (e.key === 'Home') {
            nextIndex = 0;
        } else if (e.key === 'End') {
            nextIndex = availableTabs.length - 1;
        }

        if (nextIndex !== -1) {
            e.preventDefault();
            const targetTab = availableTabs[nextIndex];
            onChange(targetTab.id);
            const refIndex = tabs.findIndex((t) => t.id === targetTab.id);
            tabRefs.current[refIndex]?.focus();
        }
    };

    return (
        <div
            role="tablist"
            className={`flex items-center gap-1 overflow-x-auto ${
                variant === 'line'
                    ? 'border-b border-slate-200'
                    : 'rounded-lg bg-slate-100 p-1'
            } ${className}`}
        >
            {tabs.map((tab, idx) => {
                const isActive = tab.id === activeTab;

                const baseStyles =
                    'inline-flex items-center gap-2 whitespace-nowrap text-xs font-medium transition-colors focus-visible:outline-2 focus-visible:outline-blue-600 disabled:opacity-40 disabled:pointer-events-none select-none';

                const variantStyles =
                    variant === 'line'
                        ? `py-2.5 px-3 border-b-2 -mb-px ${
                              isActive
                                  ? 'border-blue-600 text-blue-600 font-semibold'
                                  : 'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300'
                          }`
                        : `px-3 py-1.5 rounded-md ${
                              isActive
                                  ? 'bg-white text-slate-900 shadow-xs font-semibold'
                                  : 'text-slate-600 hover:text-slate-900'
                          }`;

                return (
                    <button
                        key={tab.id}
                        ref={(el) => {
                            tabRefs.current[idx] = el;
                        }}
                        role="tab"
                        aria-selected={isActive}
                        tabIndex={isActive ? 0 : -1}
                        disabled={tab.disabled}
                        onClick={() => onChange(tab.id)}
                        onKeyDown={(e) => handleKeyDown(e, idx)}
                        className={`${baseStyles} ${variantStyles}`}
                    >
                        {tab.icon && (
                            <span className="shrink-0">{tab.icon}</span>
                        )}
                        <span>{tab.label}</span>
                        {tab.badge !== undefined && (
                            <span
                                className={`py-0.2 rounded-full px-1.5 font-mono text-[10px] ${
                                    isActive
                                        ? 'bg-blue-100 text-blue-700'
                                        : 'bg-slate-200 text-slate-700'
                                }`}
                            >
                                {tab.badge}
                            </span>
                        )}
                    </button>
                );
            })}
        </div>
    );
};
