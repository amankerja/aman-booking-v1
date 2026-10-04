import { ArrowDown, ArrowUp, ArrowUpDown, Loader2 } from 'lucide-react';
import { ReactNode } from 'react';

export interface Column<T> {
    key: string;
    header: string;
    render?: (item: T, index: number) => ReactNode;
    sortable?: boolean;
    align?: 'left' | 'center' | 'right';
    width?: string;
    className?: string;
}

export interface DataTableProps<T> {
    columns: Column<T>[];
    data: T[];
    keyExtractor: (item: T, index: number) => string | number;
    sortKey?: string;
    sortDirection?: 'asc' | 'desc';
    onSort?: (key: string) => void;
    isLoading?: boolean;
    emptyText?: string;
    emptyAction?: ReactNode;
    hoverable?: boolean;
    compact?: boolean;
    className?: string;
}

export function DataTable<T>({
    columns,
    data,
    keyExtractor,
    sortKey,
    sortDirection = 'asc',
    onSort,
    isLoading = false,
    emptyText = 'Tidak ada data ditemukan.',
    emptyAction,
    hoverable = true,
    compact = false,
    className = '',
}: DataTableProps<T>) {
    const paddingClass = compact ? 'px-3 py-2' : 'px-4 py-2.5';

    return (
        <div
            className={`w-full overflow-hidden rounded-[12px] border border-slate-200 bg-white ${className}`}
        >
            <div className="overflow-x-auto">
                <table className="w-full border-collapse text-left text-xs">
                    <thead>
                        <tr className="border-b border-slate-200 bg-slate-50 font-medium text-slate-500 select-none">
                            {columns.map((col) => {
                                const isSorted = sortKey === col.key;
                                const alignClass =
                                    col.align === 'center'
                                        ? 'text-center'
                                        : col.align === 'right'
                                          ? 'text-right'
                                          : 'text-left';

                                return (
                                    <th
                                        key={col.key}
                                        style={
                                            col.width
                                                ? { width: col.width }
                                                : undefined
                                        }
                                        className={`${paddingClass} ${alignClass} font-semibold whitespace-nowrap text-slate-700 ${
                                            col.className || ''
                                        }`}
                                    >
                                        {col.sortable && onSort ? (
                                            <button
                                                type="button"
                                                onClick={() => onSort(col.key)}
                                                className="inline-flex items-center gap-1.5 transition-colors hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-blue-600"
                                            >
                                                <span>{col.header}</span>
                                                {isSorted ? (
                                                    sortDirection === 'asc' ? (
                                                        <ArrowUp className="h-3 w-3 text-blue-600" />
                                                    ) : (
                                                        <ArrowDown className="h-3 w-3 text-blue-600" />
                                                    )
                                                ) : (
                                                    <ArrowUpDown className="h-3 w-3 text-slate-300" />
                                                )}
                                            </button>
                                        ) : (
                                            col.header
                                        )}
                                    </th>
                                );
                            })}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {isLoading ? (
                            <tr>
                                <td
                                    colSpan={columns.length}
                                    className="px-4 py-12 text-center text-slate-400"
                                >
                                    <div className="inline-flex flex-col items-center gap-2">
                                        <Loader2 className="h-5 w-5 animate-spin text-blue-600" />
                                        <span className="text-xs">
                                            Memuat data...
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        ) : data.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={columns.length}
                                    className="px-4 py-12 text-center text-slate-500"
                                >
                                    <p className="text-xs font-medium text-slate-600">
                                        {emptyText}
                                    </p>
                                    {emptyAction && (
                                        <div className="mt-3 flex justify-center">
                                            {emptyAction}
                                        </div>
                                    )}
                                </td>
                            </tr>
                        ) : (
                            data.map((item, index) => {
                                const key = keyExtractor(item, index);
                                return (
                                    <tr
                                        key={key}
                                        className={`transition-colors ${
                                            hoverable
                                                ? 'hover:bg-slate-50/80'
                                                : ''
                                        }`}
                                    >
                                        {columns.map((col) => {
                                            const alignClass =
                                                col.align === 'center'
                                                    ? 'text-center'
                                                    : col.align === 'right'
                                                      ? 'text-right'
                                                      : 'text-left';

                                            return (
                                                <td
                                                    key={`${String(key)}-${col.key}`}
                                                    className={`${paddingClass} ${alignClass} text-slate-800 ${
                                                        col.className || ''
                                                    }`}
                                                >
                                                    {col.render
                                                        ? col.render(
                                                              item,
                                                              index
                                                          )
                                                        : ((
                                                              item as Record<
                                                                  string,
                                                                  unknown
                                                              >
                                                          )[
                                                              col.key
                                                          ]?.toString() ?? '-')}
                                                </td>
                                            );
                                        })}
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
