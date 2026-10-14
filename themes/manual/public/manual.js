/**
 * Manual Theme - Search shortcut, code copy, heading anchors and page outline
 * @license MIT, https://opensource.org/license/MIT
 */


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
 * Copy code to the clipboard
 */
document.querySelectorAll('.code-box .code-copy').forEach(button => {
    const label = button.textContent;

    button.addEventListener('click', () => {
        const text = button.closest('.code-box')?.querySelector('pre code')?.innerText || '';

        navigator.clipboard?.writeText(text).then(() => {
            button.textContent = button.dataset.copied || label;
            button.classList.add('copied');

            setTimeout(() => {
                button.textContent = label;
                button.classList.remove('copied');
            }, 1500);
        });
    });
});


/**
 * Add anchor links to documentation headings
 */
document.querySelectorAll('main .content .heading[id] :is(h2, h3)').forEach(heading => {
    const link = document.createElement('a');

    link.href = '#' + heading.closest('.heading').id;
    link.className = 'anchor';
    link.textContent = '#';
    link.setAttribute('aria-hidden', 'true');
    link.tabIndex = -1;
    heading.append(link);
});


/**
 * Highlight the current section in the page outline
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


/**
 * Scroll the active sidebar link into view
 */
const sidebar = document.querySelector('main nav.sidebar');
const active = sidebar?.querySelector('a.active:not(.section)');

if(sidebar && active && active.offsetTop > sidebar.clientHeight - 64) {
    sidebar.scrollTop = active.offsetTop - sidebar.clientHeight / 3;
}
