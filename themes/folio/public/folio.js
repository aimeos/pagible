/**
 * Folio Theme - Light/dark switch, search shortcut, copy buttons, heading anchors and post outline
 * @license MIT, https://opensource.org/license/MIT
 */


/**
 * Apply the stored color mode before the page is rendered
 */
try {
    const mode = localStorage.getItem('folio-mode');
    mode && document.documentElement.setAttribute('data-theme', mode);
} catch(e) {}


document.addEventListener('DOMContentLoaded', () => {

    /**
     * Switch between light and dark mode
     */
    document.querySelector('header button.mode')?.addEventListener('click', () => {
        const root = document.documentElement;
        const dark = root.dataset.theme ? root.dataset.theme === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches;
        const mode = dark ? 'light' : 'dark';

        root.setAttribute('data-theme', mode);
        try { localStorage.setItem('folio-mode', mode); } catch(e) {}
    });


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
        }, {rootMargin: '-64px 0px -60% 0px'});

        links.forEach((link, id) => {
            const el = document.getElementById(id);
            el && observer.observe(el);
        });
    }
});
