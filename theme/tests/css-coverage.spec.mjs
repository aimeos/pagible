import { test, chromium } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const VIEWPORTS = [
    { name: 'mobile',  width: 375,  height: 812  },
    { name: 'tablet',  width: 768,  height: 1024 },
    { name: 'desktop', width: 1280, height: 800  },
    { name: 'wide',    width: 1920, height: 1080 },
];

const HOVER_SELECTORS = 'a, button, [role="button"], [role="menuitem"], details > summary, .card-item, .btn, .swiffy-slider, .swiffy-slider .slider-nav, nav.breadcrumb a, dialog button[type=reset], .load-more, footer.cms-content .cards .text a';
const FOCUS_SELECTORS = 'input, textarea, select, button, a[href]';

test('CSS coverage across pages and viewports', async ({}, testInfo) => {
    const baseURL = testInfo.project.use?.baseURL || 'http://localhost:8000';
    const browser = await chromium.launch();
    const context = await browser.newContext();
    const page = await context.newPage();

    // Discover pages from sitemap (before starting coverage)
    const urls = await discoverUrls(page, baseURL);

    if (!urls.length) {
        console.warn('No URLs found in sitemap, falling back to homepage');
        urls.push('/');
    }

    console.log(`Found ${urls.length} page(s) to test`);

    // Collect coverage per page visit, merge afterwards
    const allCoverage = [];
    const allMatchedSelectors = new Set();

    // Visit each page at each viewport, hover and focus elements
    for (const vp of VIEWPORTS) {
        await page.setViewportSize(vp);
        console.log(`\n  Viewport: ${vp.name} (${vp.width}x${vp.height})`);

        for (const url of urls) {
            await page.coverage.startCSSCoverage({ resetOnNavigation: false });
            await page.goto(baseURL + url, { waitUntil: 'domcontentloaded', timeout: 15_000 }).catch(() => null);
            await page.waitForTimeout(500);

            // Trigger all CSS states via JavaScript (fast, no Playwright auto-waiting)
            await page.evaluate(({ hoverSel, focusSel, isMobile }) => {
                // Open mobile menu
                if (isMobile) {
                    const menuBtn = document.querySelector('.menu-open button');
                    if (menuBtn) menuBtn.click();
                }

                // Open dropdowns, FAQ details, and sidebar navigation
                document.querySelectorAll('details.dropdown, .questions details, nav.sidebar details').forEach(dd => {
                    dd.open = true;
                });

                // Show docs sidebar on mobile
                if (isMobile) {
                    document.querySelectorAll('nav.sidebar').forEach(el => el.classList.add('show'));
                }

                // Hover elements
                document.querySelectorAll(hoverSel).forEach(el => {
                    el.dispatchEvent(new MouseEvent('mouseenter', { bubbles: true }));
                    el.dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));
                });

                // Focus elements
                const focusable = document.querySelectorAll(focusSel);
                for (let i = 0; i < Math.min(focusable.length, 10); i++) {
                    try { focusable[i].focus(); } catch {}
                }

                // Focus contact form inputs explicitly
                document.querySelectorAll('.contact input, .contact textarea').forEach(el => {
                    el.focus(); el.blur();
                });

                // Toggle pricing to trigger .alt state
                document.querySelectorAll('.pricing-toggle').forEach(el => {
                    el.click();
                    el.classList.add('alt');
                });

                // Simulate caption.js filling video captions
                document.querySelectorAll('video[title]').forEach(v => {
                    const cap = v.nextElementSibling;
                    if (cap?.classList.contains('caption') && v.title) cap.textContent = v.title;
                });

                // Simulate contact form error and success/failure states
                document.querySelectorAll('.contact input, .contact textarea').forEach(el => {
                    el.classList.add('error');
                });
                document.querySelectorAll('.contact button .success, .contact button .failure').forEach(el => {
                    el.classList.remove('hidden');
                });
                document.querySelectorAll('.contact button .send').forEach(el => {
                    el.classList.add('hidden');
                });

                // Open search modal and trigger search
                const searchLink = document.querySelector('a.search[data-modal]');
                if (searchLink) searchLink.click();
                const searchInput = document.querySelector('#modal-search-input');
                if (searchInput) {
                    searchInput.value = 'PagibleAI';
                    searchInput.dispatchEvent(new Event('input', { bubbles: true }));
                }

                // Inject mock search results to trigger result CSS
                const resultsDiv = document.querySelector('dialog.search .results');
                if (resultsDiv) {
                    resultsDiv.innerHTML = '<a class="result-item" href="/"><div class="result-title">Test Result</div><div class="result-content">This is a <b>PagibleAI</b> search result</div></a><button class="load-more" disabled>Load more</button>';
                }

                // Hover injected search results elements
                document.querySelectorAll('dialog.search .result-item, dialog.search .load-more').forEach(el => {
                    el.dispatchEvent(new MouseEvent('mouseenter', { bubbles: true }));
                    el.dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));
                });

                // Hover dialog reset button
                document.querySelectorAll('dialog button[type=reset]').forEach(el => {
                    el.dispatchEvent(new MouseEvent('mouseenter', { bubbles: true }));
                    el.dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));
                });

                // Scroll through the page
                const height = document.body.scrollHeight;
                const step = Math.max(height / 5, 500);
                for (let y = 0; y <= height; y += step) {
                    window.scrollTo(0, y);
                }
                window.scrollTo(0, 0);

                return [];
            }, { hoverSel: HOVER_SELECTORS, focusSel: FOCUS_SELECTORS, isMobile: vp.width < 992 });

            // Wait for search results and async scripts
            await page.waitForTimeout(1000);

            // Collect matched selectors after interactions (search results now visible)
            const matched = await page.evaluate(() => {
                const pseudoRe = /:(?:hover|focus|focus-visible|focus-within|active|checked|disabled|visited)(?:\([^)]*\))?/g;
                const selectors = [];
                for (const sheet of document.styleSheets) {
                    try {
                        for (const rule of sheet.cssRules) {
                            if (rule.selectorText && pseudoRe.test(rule.selectorText)) {
                                pseudoRe.lastIndex = 0;
                                const base = rule.selectorText.replace(pseudoRe, '').trim();
                                const unwrapped = base.replace(/:(?:where|is)\(([^()]*(?:\([^()]*\)[^()]*)*)\)/g, '$1');
                                for (const part of unwrapped.split(',')) {
                                    const s = part.trim();
                                    if (s) {
                                        try {
                                            if (document.querySelector(s)) {
                                                selectors.push(s);
                                                // Also add unquoted version for raw CSS matching
                                                const unquoted = s.replace(/=["']([^"']*)["']\]/g, '=$1]');
                                                if (unquoted !== s) selectors.push(unquoted);
                                            }
                                        } catch {}
                                    }
                                }
                            }
                            pseudoRe.lastIndex = 0;
                        }
                    } catch {}
                }
                return selectors;
            });

            for (const s of matched) allMatchedSelectors.add(s);

            const pageCoverage = await page.coverage.stopCSSCoverage();
            allCoverage.push(...pageCoverage);
        }
    }

    // RTL pass: all pages at desktop viewport
    console.log('\n  RTL pass (all pages)');
    await page.setViewportSize({ width: 1280, height: 800 });
    for (const url of urls) {
        await page.coverage.startCSSCoverage({ resetOnNavigation: false });
        await page.goto(baseURL + url, { waitUntil: 'domcontentloaded', timeout: 15_000 }).catch(() => null);
        await page.evaluate(({ hoverSel, focusSel }) => {
            document.documentElement.dir = 'rtl';

            // Open dropdowns, FAQ details, and sidebar navigation
            document.querySelectorAll('details.dropdown, .questions details, nav.sidebar details').forEach(dd => {
                dd.open = true;
            });

            // Hover elements
            document.querySelectorAll(hoverSel).forEach(el => {
                el.dispatchEvent(new MouseEvent('mouseenter', { bubbles: true }));
                el.dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));
            });

            // Focus elements
            const focusable = document.querySelectorAll(focusSel);
            for (let i = 0; i < Math.min(focusable.length, 10); i++) {
                try { focusable[i].focus(); } catch {}
            }
        }, { hoverSel: HOVER_SELECTORS, focusSel: FOCUS_SELECTORS });
        await page.waitForTimeout(300);
        const pageCoverage = await page.coverage.stopCSSCoverage();
        allCoverage.push(...pageCoverage);
    }

    await browser.close();

    // Merge coverage entries for the same URL, excluding non-CSS inline styles
    const merged = mergeCoverage(allCoverage).filter(e => e.url.includes('.css'));

    // Adjust coverage with pseudo-state static analysis
    const coverage = adjustPseudoCoverage(merged, allMatchedSelectors);

    // Build report
    const report = buildReport(coverage, baseURL);
    printReport(report);

    // Write JSON report
    const theme = process.env.THEME || 'default';
    const outDir = path.join(path.dirname(testInfo.config.configFile), 'coverage', theme);
    fs.mkdirSync(outDir, { recursive: true });
    fs.writeFileSync(path.join(outDir, 'css-coverage.json'), JSON.stringify(report, null, 2));

    // Write unused rules detail
    const unused = buildUnusedDetail(coverage);
    fs.writeFileSync(path.join(outDir, 'css-unused.json'), JSON.stringify(unused, null, 2));

    console.log(`\nReports written to ${outDir}/`);
});


async function discoverUrls(page, baseURL) {
    const urls = new Set();

    // Try sitemap first
    try {
        const resp = await page.goto(baseURL + '/sitemap.xml', { timeout: 10_000 });
        if (resp?.ok()) {
            const xml = await resp.text();

            // Check for sitemap index (references to sub-sitemaps)
            const sitemapRefs = [...xml.matchAll(/<loc>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?<\/loc>/g)]
                .map(m => m[1])
                .filter(u => u.includes('sitemap'));

            if (sitemapRefs.length) {
                for (const ref of sitemapRefs) {
                    try {
                        const sub = await page.goto(ref, { timeout: 10_000 });
                        if (sub?.ok()) {
                            const subXml = await sub.text();
                            for (const m of subXml.matchAll(/<loc>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?<\/loc>/g)) {
                                urls.add(new URL(m[1]).pathname);
                            }
                        }
                    } catch {}
                }
            } else {
                for (const m of xml.matchAll(/<loc>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?<\/loc>/g)) {
                    urls.add(new URL(m[1]).pathname);
                }
            }
        }
    } catch {}

    // Fallback: crawl homepage links
    if (!urls.size) {
        try {
            await page.goto(baseURL + '/', { waitUntil: 'domcontentloaded', timeout: 10_000 });
            const links = await page.locator('a[href]').evaluateAll(els =>
                els.map(el => el.getAttribute('href')).filter(h => h?.startsWith('/') && !h.startsWith('//'))
            );
            for (const l of links) {
                urls.add(l);
            }
            urls.add('/');
        } catch {}
    }

    return [...urls];
}


function mergeCoverage(entries) {
    const map = new Map();

    for (const entry of entries) {
        const key = entry.url.replace(/\?.*$/, '');

        if (!map.has(key)) {
            map.set(key, { url: key, text: entry.text, ranges: [...entry.ranges] });
        } else {
            const existing = map.get(key);
            // Use longest text (same file, but maybe different cache-busted versions)
            if (entry.text.length > existing.text.length) {
                existing.text = entry.text;
            }
            existing.ranges.push(...entry.ranges);
        }
    }

    // Merge overlapping ranges for each file
    for (const entry of map.values()) {
        entry.ranges.sort((a, b) => a.start - b.start || a.end - b.end);
        const merged = [];

        for (const range of entry.ranges) {
            const last = merged[merged.length - 1];
            if (last && range.start <= last.end) {
                last.end = Math.max(last.end, range.end);
            } else {
                merged.push({ start: range.start, end: range.end });
            }
        }

        entry.ranges = merged;
    }

    return [...map.values()];
}


function adjustPseudoCoverage(coverage, matchedSelectors) {
    const pseudoRe = /:(?:hover|focus|focus-visible|focus-within|active|checked|disabled|visited)(?:\([^)]*\))?/g;

    for (const entry of coverage) {
        // Find uncovered ranges
        const uncovered = [];
        let pos = 0;
        for (const range of entry.ranges) {
            if (range.start > pos) uncovered.push({ start: pos, end: range.start });
            pos = range.end;
        }
        if (pos < entry.text.length) uncovered.push({ start: pos, end: entry.text.length });

        const extra = [];
        for (const range of uncovered) {
            const css = entry.text.slice(range.start, range.end);
            const ruleRe = /([^{}]+)\{[^}]*\}/g;
            let m;
            while ((m = ruleRe.exec(css)) !== null) {
                const selector = m[1].trim();
                if (pseudoRe.test(selector)) {
                    pseudoRe.lastIndex = 0;
                    const base = selector.replace(pseudoRe, '').trim();
                    const unwrapped = base.replace(/:(?:where|is)\(([^()]*(?:\([^()]*\)[^()]*)*)\)/g, '$1');
                    const parts = unwrapped.split(',').map(s => s.trim()).filter(Boolean);
                    if (parts.some(p => matchedSelectors.has(p))) {
                        extra.push({
                            start: range.start + m.index,
                            end: range.start + m.index + m[0].length,
                        });
                    }
                }
                pseudoRe.lastIndex = 0;
            }
        }

        if (extra.length) {
            entry.ranges = [...entry.ranges, ...extra].sort((a, b) => a.start - b.start);
            const merged = [];
            for (const r of entry.ranges) {
                const last = merged[merged.length - 1];
                if (last && r.start <= last.end) last.end = Math.max(last.end, r.end);
                else merged.push({ start: r.start, end: r.end });
            }
            entry.ranges = merged;
        }
    }

    return coverage;
}


function buildReport(coverage, baseURL) {
    let totalBytes = 0;
    let usedBytes = 0;
    const files = [];

    for (const entry of coverage) {
        const name = entry.url.replace(baseURL, '').replace(/\?.*$/, '');
        const total = entry.text.length;
        let used = 0;

        for (const range of entry.ranges) {
            used += range.end - range.start;
        }

        totalBytes += total;
        usedBytes += used;

        files.push({
            file: name,
            total,
            used,
            unused: total - used,
            coverage: total ? +((used / total) * 100).toFixed(1) : 100,
        });
    }

    files.sort((a, b) => a.coverage - b.coverage);

    return {
        summary: {
            totalBytes,
            usedBytes,
            unusedBytes: totalBytes - usedBytes,
            coverage: totalBytes ? +((usedBytes / totalBytes) * 100).toFixed(1) : 100,
        },
        files,
    };
}


function buildUnusedDetail(coverage) {
    const result = [];

    for (const entry of coverage) {
        if (!entry.ranges.length) {
            result.push({ file: entry.url, unusedRanges: [{ start: 0, end: entry.text.length, sample: entry.text.slice(0, 200) }] });
            continue;
        }

        const unused = [];
        let pos = 0;

        for (const range of entry.ranges) {
            if (range.start > pos) {
                unused.push({
                    start: pos,
                    end: range.start,
                    sample: entry.text.slice(pos, Math.min(pos + 200, range.start)),
                });
            }
            pos = range.end;
        }

        if (pos < entry.text.length) {
            unused.push({
                start: pos,
                end: entry.text.length,
                sample: entry.text.slice(pos, pos + 200),
            });
        }

        if (unused.length) {
            result.push({ file: entry.url, unusedRanges: unused });
        }
    }

    return result;
}


function printReport(report) {
    console.log('\n  CSS Coverage Report (includes pseudo-state analysis)');
    console.log('  ' + '─'.repeat(76));
    console.log(`  ${'File'.padEnd(45)} ${'Total'.padStart(8)} ${'Used'.padStart(8)} ${'Coverage'.padStart(10)}`);
    console.log('  ' + '─'.repeat(76));

    for (const f of report.files) {
        const bar = f.coverage >= 80 ? '✓' : f.coverage >= 50 ? '●' : '✗';
        console.log(`  ${bar} ${f.file.padEnd(43)} ${fmtBytes(f.total).padStart(8)} ${fmtBytes(f.used).padStart(8)} ${(f.coverage + '%').padStart(9)}`);
    }

    console.log('  ' + '─'.repeat(76));
    console.log(`  ${'TOTAL'.padEnd(45)} ${fmtBytes(report.summary.totalBytes).padStart(8)} ${fmtBytes(report.summary.usedBytes).padStart(8)} ${(report.summary.coverage + '%').padStart(9)}`);
    console.log(`  Unused: ${fmtBytes(report.summary.unusedBytes)}`);
}


function fmtBytes(bytes) {
    if (bytes < 1024) return bytes + 'B';
    return (bytes / 1024).toFixed(1) + 'KB';
}
