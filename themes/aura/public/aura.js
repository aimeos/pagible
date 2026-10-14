/**
 * Aura Theme - Light/dark switch, scroll reveals, card spotlights, marquee, counters, copy buttons and post outline
 * @license MIT, https://opensource.org/license/MIT
 */


/**
 * Apply the stored color mode and enable scripted styles before the page is rendered
 */
document.documentElement.classList.replace('no-js', 'js');

try {
    const mode = localStorage.getItem('aura-mode');
    mode && document.documentElement.setAttribute('data-theme', mode);
} catch(e) {}


document.addEventListener('DOMContentLoaded', () => {

    const motion = matchMedia('(prefers-reduced-motion: no-preference)').matches;


    /**
     * Switch between light and dark mode
     */
    document.querySelector('header button.mode')?.addEventListener('click', () => {
        const root = document.documentElement;
        const dark = root.dataset.theme ? root.dataset.theme === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches;
        const mode = dark ? 'light' : 'dark';
        const apply = () => root.setAttribute('data-theme', mode);

        motion && document.startViewTransition ? document.startViewTransition(apply) : apply();
        try { localStorage.setItem('aura-mode', mode); } catch(e) {}
    });


    /**
     * Condense the floating header after scrolling
     */
    const header = document.querySelector('body > header');
    const condense = () => header?.classList.toggle('scrolled', scrollY > 8);

    addEventListener('scroll', condense, {passive: true});
    condense();


    /**
     * Open search with Ctrl+K, Cmd+K or "/"
     */
    document.addEventListener('keydown', ev => {
        const typing = ev.target.closest?.('input, textarea, select, [contenteditable]');

        if(((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 'k') || (ev.key === '/' && !typing)) {
            if(!document.querySelector('dialog[open]')) {
                ev.preventDefault();
                document.querySelector('header a.search')?.click();
            }
        }
    });


    /**
     * Reveal page sections when they scroll into view
     */
    const sections = document.querySelectorAll('main > .cms-content > div:not(:first-child)');

    if('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if(entry.isIntersecting) {
                    entry.target.classList.add('in');
                    observer.unobserve(entry.target);
                }
            });
        }, {rootMargin: '0px 0px -10% 0px'});

        sections.forEach(el => observer.observe(el));
    } else {
        sections.forEach(el => el.classList.add('in'));
    }


    /**
     * Endless marquee for badge cards, items are duplicated for a seamless loop
     */
    if(motion) {
        document.querySelectorAll('main .card-list.layout-badges').forEach(list => {
            [...list.children].forEach(item => {
                const clone = item.cloneNode(true);

                clone.setAttribute('aria-hidden', 'true');
                clone.querySelectorAll('a').forEach(link => link.tabIndex = -1);
                list.append(clone);
            });
            list.classList.add('marquee');
        });
    }


    /**
     * Spotlight following the pointer on cards
     */
    document.querySelectorAll('main .card-list:is(.layout-cards, .layout-figures) .card-item, main .list-cards .list-item').forEach(card => {
        const spot = document.createElement('span');

        spot.className = 'spot';
        spot.setAttribute('aria-hidden', 'true');
        card.prepend(spot);

        card.addEventListener('pointermove', ev => {
            const rect = card.getBoundingClientRect();
            spot.style.transform = `translate(${ev.clientX - rect.left}px, ${ev.clientY - rect.top}px)`;
        });
    });


    /**
     * Count up the numbers of figure cards when they become visible
     */
    if(motion && 'IntersectionObserver' in window) {
        const counter = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if(!entry.isIntersecting) {
                    return;
                }

                const el = entry.target;
                const [, prefix, number, suffix] = el.dataset.count.match(/^(\D*)([\d.,]+)(.*)$/s);
                const decimals = (number.split(/[.,]/)[1] || '').length;
                const sep = number.match(/[.,]/)?.[0] || '.';
                const target = parseFloat(number.replace(',', '.'));
                const start = performance.now();

                const step = now => {
                    const ratio = Math.min(1, (now - start) / 1400);
                    const value = (target * (1 - Math.pow(1 - ratio, 4))).toFixed(decimals).replace('.', sep);

                    el.textContent = prefix + value + suffix;
                    ratio < 1 && requestAnimationFrame(step);
                };

                counter.unobserve(el);
                requestAnimationFrame(step);
            });
        }, {threshold: 0.6});

        document.querySelectorAll('main .card-list.layout-figures .card-item .title').forEach(el => {
            const text = el.textContent.trim();

            if(/^\D*\d{1,6}([.,]\d{1,2})?\D*$/s.test(text) && !el.querySelector('a')) {
                el.dataset.count = text;
                counter.observe(el);
            }
        });
    }


    /**
     * Copy code and e-mail addresses to the clipboard
     */
    const copied = button => {
        button.classList.add('copied');
        setTimeout(() => button.classList.remove('copied'), 1500);
    };

    document.querySelectorAll('.code-box .code-copy').forEach(button => {
        const label = button.textContent;

        button.addEventListener('click', () => {
            const text = button.closest('.code-box')?.querySelector('pre code')?.innerText || '';

            navigator.clipboard?.writeText(text).then(() => {
                button.textContent = button.dataset.copied || label;
                copied(button);
                setTimeout(() => button.textContent = label, 1500);
            });
        });
    });

    document.querySelectorAll('footer button.copy[data-copy]').forEach(button => {
        button.addEventListener('click', () => {
            navigator.clipboard?.writeText(button.dataset.copy).then(() => {
                copied(button);
            });
        });
    });


    /**
     * Add anchor links to post headings
     */
    document.querySelectorAll('main .post .heading[id] :is(h2, h3)').forEach(heading => {
        const link = document.createElement('a');

        link.href = '#' + heading.closest('.heading').id;
        link.className = 'anchor';
        link.textContent = '#';
        link.setAttribute('aria-hidden', 'true');
        link.tabIndex = -1;
        heading.append(link);
    });


    /**
     * Highlight the current section in the post outline
     */
    const outline = document.querySelectorAll('main .outline a');

    if(outline.length && 'IntersectionObserver' in window) {
        const links = new Map([...outline].map(link => [decodeURIComponent(link.hash.slice(1)), link]));
        const visible = new Set();

        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => entry.isIntersecting ? visible.add(entry.target.id) : visible.delete(entry.target.id));

            const current = [...links.keys()].find(id => visible.has(id));

            if(current) {
                outline.forEach(link => link.classList.toggle('active', link === links.get(current)));
            }
        }, {rootMargin: '-96px 0px -60% 0px'});

        links.forEach((link, id) => {
            const el = document.getElementById(id);
            el && observer.observe(el);
        });
    }
});
