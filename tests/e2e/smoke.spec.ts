import { test, expect } from '@playwright/test';

test.describe('Smoke Test', () => {
    test('homepage responds', async ({ request, baseURL }) => {
        const response = await request.get(baseURL || 'http://127.0.0.1:8000');
        expect(response.status()).toBeLessThan(500);
    });
});
