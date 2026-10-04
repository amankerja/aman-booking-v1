import '@testing-library/jest-dom';
import { vi } from 'vitest';

vi.mock('@inertiajs/react', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@inertiajs/react')>();
    return {
        ...actual,
        Head: () => null,
    };
});
