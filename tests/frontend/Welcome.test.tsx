import { render, screen } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import Welcome from '@/Pages/Welcome';

describe('Welcome Component', () => {
    it('renders platform heading properly', () => {
        render(<Welcome />);
        expect(screen.getByText('Sistem Booking Multi-Tenant')).toBeInTheDocument();
        expect(screen.getByText('AMAN BOOKING Platform')).toBeInTheDocument();
    });
});
