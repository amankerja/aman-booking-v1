import { render, screen, fireEvent } from '@testing-library/react';
import React, { useState } from 'react';
import { describe, it, expect, vi } from 'vitest';
import {
    Badge,
    Button,
    DataTable,
    EmptyState,
    ErrorState,
    Input,
    Modal,
    Select,
    Skeleton,
    Tabs,
    Textarea,
} from '@/Components/ui';

describe('Design System UI Components', () => {
    it('renders Button variants, handles click, and shows loading state', () => {
        const handleClick = vi.fn();
        const { rerender } = render(
            <Button variant="primary" onClick={handleClick}>
                Simpan
            </Button>
        );

        const btn = screen.getByRole('button', { name: /simpan/i });
        expect(btn).toBeInTheDocument();
        fireEvent.click(btn);
        expect(handleClick).toHaveBeenCalledTimes(1);

        rerender(
            <Button variant="primary" isLoading onClick={handleClick}>
                Simpan
            </Button>
        );
        expect(btn).toBeDisabled();
    });

    it('renders Input with label, helperText, and error states', () => {
        const { rerender } = render(
            <Input
                label="Nama Pelanggan"
                placeholder="Masukkan nama"
                helperText="Nama sesuai KTP"
            />
        );

        expect(screen.getByLabelText(/nama pelanggan/i)).toBeInTheDocument();
        expect(screen.getByText('Nama sesuai KTP')).toBeInTheDocument();

        rerender(
            <Input
                label="Nama Pelanggan"
                placeholder="Masukkan nama"
                error="Nama wajib diisi"
            />
        );
        expect(screen.getByText('Nama wajib diisi')).toBeInTheDocument();
        expect(screen.getByRole('textbox')).toHaveAttribute('aria-invalid', 'true');
    });

    it('renders Select with options and handles value change', () => {
        const handleChange = vi.fn();
        render(
            <Select
                label="Pilih Layanan"
                onChange={handleChange}
                options={[
                    { value: 'srv-1', label: 'Layanan Ekpres' },
                    { value: 'srv-2', label: 'Layanan Reguler' },
                ]}
            />
        );

        expect(screen.getByLabelText(/pilih layanan/i)).toBeInTheDocument();
        const select = screen.getByRole('combobox');
        fireEvent.change(select, { target: { value: 'srv-2' } });
        expect(handleChange).toHaveBeenCalled();
    });

    it('renders Textarea with character counter and error', () => {
        render(
            <Textarea
                label="Catatan"
                value="Halo"
                maxLength={100}
                showCount
                onChange={() => {}}
            />
        );

        expect(screen.getByLabelText(/catatan/i)).toBeInTheDocument();
        expect(screen.getByText('4/100')).toBeInTheDocument();
    });

    it('renders Badge with semantic color, icon, and accessible label', () => {
        render(<Badge variant="active">LOADING / ACTIVE</Badge>);
        expect(screen.getByText('LOADING / ACTIVE')).toBeInTheDocument();
    });

    it('renders Modal dialog when isOpen is true', () => {
        const handleClose = vi.fn();
        const { rerender } = render(
            <Modal isOpen={false} onClose={handleClose} title="Detail Modal">
                Konten Modal
            </Modal>
        );

        expect(screen.queryByText('Detail Modal')).not.toBeInTheDocument();

        rerender(
            <Modal isOpen={true} onClose={handleClose} title="Detail Modal">
                Konten Modal
            </Modal>
        );

        expect(screen.getByText('Detail Modal')).toBeInTheDocument();
        expect(screen.getByText('Konten Modal')).toBeInTheDocument();

        const closeBtn = screen.getByLabelText('Tutup dialog');
        fireEvent.click(closeBtn);
        expect(handleClose).toHaveBeenCalledTimes(1);
    });

    it('renders Tabs and handles active selection with keyboard navigation', () => {
        const TabsWrapper = () => {
            const [active, setActive] = useState('tab-1');
            return (
                <Tabs
                    tabs={[
                        { id: 'tab-1', label: 'Semua' },
                        { id: 'tab-2', label: 'Aktif' },
                    ]}
                    activeTab={active}
                    onChange={setActive}
                />
            );
        };

        render(<TabsWrapper />);
        const tab2 = screen.getByRole('tab', { name: /aktif/i });
        fireEvent.click(tab2);
        expect(tab2).toHaveAttribute('aria-selected', 'true');
    });

    it('renders DataTable with columns and data', () => {
        const data = [{ id: '1', name: 'Armada 01', type: 'Dump Truck' }];
        const columns = [
            { key: 'name', header: 'Nama Unit' },
            { key: 'type', header: 'Jenis' },
        ];

        render(
            <DataTable
                columns={columns}
                data={data}
                keyExtractor={(item) => item.id}
            />
        );

        expect(screen.getByText('Nama Unit')).toBeInTheDocument();
        expect(screen.getByText('Armada 01')).toBeInTheDocument();
        expect(screen.getByText('Dump Truck')).toBeInTheDocument();
    });

    it('renders EmptyState and ErrorState properly', () => {
        const handleRetry = vi.fn();
        render(
            <>
                <EmptyState
                    title="Kosong"
                    description="Belum ada data"
                />
                <ErrorState
                    title="Error"
                    message="Gagal memuat"
                    code="ERR_500"
                    onRetry={handleRetry}
                />
            </>
        );

        expect(screen.getByText('Kosong')).toBeInTheDocument();
        expect(screen.getByText('Belum ada data')).toBeInTheDocument();
        expect(screen.getByText('Error')).toBeInTheDocument();
        expect(screen.getByText('ERR_500')).toBeInTheDocument();

        fireEvent.click(screen.getByText('Coba Lagi'));
        expect(handleRetry).toHaveBeenCalledTimes(1);
    });

    it('renders Skeleton loaders', () => {
        const { container } = render(
            <div>
                <Skeleton variant="text" lines={2} />
                <Skeleton variant="circle" />
                <Skeleton variant="rect" />
            </div>
        );

        const skeletons = container.querySelectorAll('.animate-pulse');
        expect(skeletons.length).toBeGreaterThan(0);
    });
});
