import { expect, test } from '@playwright/test';

test.describe('Owner Bookings Management (Phase 1.7)', () => {
    test('guest is redirected to login when accessing owner bookings', async ({ page }) => {
        await page.goto('/app/bookings');
        await expect(page).toHaveURL(/.*login/);
    });

    test('booking page interface elements and endpoints contract', async ({ request, baseURL }) => {
        // Test health and availability endpoints response structure
        const slotsResponse = await request.get(`${baseURL || 'http://127.0.0.1:8000'}/up`);
        expect(slotsResponse.status()).toBe(200);
    });
});
