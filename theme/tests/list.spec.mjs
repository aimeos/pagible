import { expect, test } from '@playwright/test';
import path from 'node:path';


test('loads sibling pagination links and replaces the matching list', async ({ page }) => {
    await page.route('https://example.test/blog?p=2', route => route.fulfill({
        contentType: 'text/html',
        body: `
            <main class="cms-content">
                <section id="first-list">
                    <div class="list">
                        <div class="list-items" data-list="articles">Wrong list</div>
                    </div>
                </section>
                <section id="second-list">
                    <div class="list">
                        <p role="status" aria-live="polite">Page 2</p>
                        <div class="list-items" data-list="articles">Second page</div>
                        <nav><ul class="pagination"><li><a class="page-link" href="https://example.test/blog?p=3">3</a></li></ul></nav>
                    </div>
                </section>
            </main>
        `,
    }));

    await page.setContent(`
        <main class="cms-content">
            <section id="first-list">
                <div class="list">
                    <div class="list-items" data-list="articles">First list</div>
                </div>
            </section>
            <section id="second-list">
                <div class="list">
                    <p role="status" aria-live="polite">Page 1</p>
                    <div class="list-items" data-list="articles">First page</div>
                    <nav><ul class="pagination"><li><a class="page-link" href="https://example.test/blog?p=2">2</a></li></ul></nav>
                </div>
            </section>
        </main>
    `);
    await page.addScriptTag({ path: path.resolve(import.meta.dirname, '../public/list.js') });
    await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));

    await page.locator('#second-list .page-link').click();

    await expect(page.locator('#first-list .list-items')).toHaveText('First list');
    await expect(page.locator('#second-list .list-items')).toHaveText('Second page');
    await expect(page.locator('#second-list [role="status"]')).toHaveText('Page 2');
    await expect(page.locator('#second-list .page-link')).toHaveText('3');
    await expect(page.locator('#second-list .list')).not.toHaveAttribute('aria-busy');
});
