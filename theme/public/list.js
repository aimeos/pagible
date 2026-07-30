/**
 * @license MIT, https://opensource.org/license/MIT
 */


/**
 * Dynamically load list pages via AJAX when clicking on the pagination links.
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.cms-content .list').forEach(item => {
        const announce = list => list?.closest('.list')?.querySelector('[role="status"][aria-live]');

        item.addEventListener('click', ev => {
            const items = ev.target.closest('.list-items')
            const a = ev.target.closest('.list-items .pagination a.page-link');
            const status = announce(items);

            if(a && document.body.contains(a)) {
                ev.preventDefault();
                items.setAttribute('aria-busy', 'true');

                fetch(a.href).then(response => {
                    if(!response.ok) {
                        throw new Error('Fetching list page failed');
                    }
                    return response.text();
                }).then(text => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(text, 'text/html');
                    const newitems = doc.querySelector(`.list-items[data-list="${items.dataset.list}"]`);

                    if(newitems) {
                        const newStatus = newitems.closest('.list')
                            ?.querySelector('[role="status"][aria-live]');
                        items.replaceWith(newitems);
                        item.scrollIntoView({ behavior: 'smooth' });
                        if(status && newStatus) {
                            status.textContent = newStatus.textContent;
                        }
                        return;
                    }

                    throw new Error('No list payload found in response');
                }).catch(error => {
                    console.error(error);
                }).finally(() => {
                    items.removeAttribute('aria-busy');
                });
            }
        });
    });
});
