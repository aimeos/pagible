/**
 * Allow editing content by admins in preview
 */

if (window.self !== window.top) {
    let trustedOrigin

    // Handle messages from parent window
    window.addEventListener('message', msg => {
        if(trustedOrigin && trustedOrigin !== msg.origin) return

        switch(msg.data) {
            case 'init': trustedOrigin = msg.origin; break
            case 'reload': location.reload(); break
        }
    });


    /**
     * Messages:
     * 0: unselect element
     * -1: not allowed
     * -2: not cms content
     */
    document.addEventListener('DOMContentLoaded', () => {

        // Show actions menu
        document.querySelectorAll('.cms-content').forEach(el => {
            const section = el.dataset.section || 'main'

            if(!el.children.length) {
                const match = getComputedStyle(el)?.backgroundColor?.match(/\d+/g)
                const a = parseFloat(match[3] ?? 1)

                const r = (1 - a) * 255 + a * parseInt(match[0] ?? 0, 10)
                const g = (1 - a) * 255 + a * parseInt(match[1] ?? 0, 10)
                const b = (1 - a) * 255 + a * parseInt(match[2] ?? 0, 10)
                const lum = (0.299 * r + 0.587 * g + 0.114 * b) / 255

                el.classList.add('admin', 'placeholder', lum > 0.5 ? 'dark' : 'light')
            }

            el.addEventListener('dblclick', ev => {
                ev.stopPropagation();
                const id = ev.target?.closest('[id]')?.id || -1; // -1: add element
                window.parent.postMessage({id: id, section: section}, trustedOrigin || '*');
            });
        });


        // Not CMS content
        document.body.addEventListener('dblclick', () => {
            window.parent.postMessage(-2, trustedOrigin || '*');
        });


        // Prevent off-page link actions in preview mode or hide actions menu
        document.body.addEventListener('click', (e) => {
            const link = e.target.closest('a')?.href;

            if(link) {
                const current = window.location;
                const target = new URL(link, current);

                if(target.origin !== current.origin || target.pathname !== current.pathname) {
                    window.parent.postMessage(-1, trustedOrigin || '*');
                    e.stopPropagation();
                    e.preventDefault();
                }
                return;
            }

            window.parent.postMessage(0, trustedOrigin || '*');
        });

        // Prevent native form submissions in preview mode
        document.body.addEventListener('submit', (e) => {
            window.parent.postMessage(-1, trustedOrigin || '*');
            e.stopPropagation();
            e.preventDefault();
        });
    });
}
