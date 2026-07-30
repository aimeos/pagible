import { expect, test } from '@playwright/test';
import path from 'node:path';


test('switches several prices by their unit', async ({ page }) => {
    await page.setContent(`
        <section class="pricing">
            <div class="pricing-list">
                <div class="pricing-item">
                    <span class="pricing-price" data-priceid="basic-month" data-unit="month"></span>
                    <span class="pricing-price" data-priceid="basic-year" data-unit="year" hidden></span>
                    <span class="pricing-price" data-priceid="basic-once" data-unit="once" hidden></span>
                    <input name="price" value="basic-month">
                </div>
                <div class="pricing-item">
                    <span class="pricing-price" data-priceid="pro-month" data-unit="month"></span>
                    <span class="pricing-price" data-priceid="pro-year" data-unit="year" hidden></span>
                    <input name="price" value="pro-month">
                </div>
            </div>
        </section>
    `);
    await page.addScriptTag({ path: path.resolve(import.meta.dirname, '../public/pricing.js') });

    const captions = page.locator('.pricing-toggle > span');
    await expect(captions).toHaveText(['month', 'year', 'once']);
    await expect(captions.first()).toHaveClass(/active/);

    await captions.nth(1).click();
    await expect(page.locator('input[name="price"]').nth(0)).toHaveValue('basic-year');
    await expect(page.locator('input[name="price"]').nth(1)).toHaveValue('pro-year');

    await captions.nth(2).click();
    await expect(page.locator('input[name="price"]').nth(0)).toHaveValue('basic-once');
    await expect(page.locator('input[name="price"]').nth(1)).toHaveValue('pro-month');
});
