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
            const a = ev.target.closest('.pagination a.page-link');
            const items = item.querySelector('.list-items');
            const status = announce(items);

            if(a && items && item.contains(a)) {
                ev.preventDefault();
                item.setAttribute('aria-busy', 'true');

                fetch(a.href).then(response => {
                    if(!response.ok) {
                        throw new Error('Fetching list page failed');
                    }
                    return response.text();
                }).then(text => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(text, 'text/html');
                    const element = item.closest('.cms-content [id]');
                    const newitem = element?.id ? doc.getElementById(element.id)?.querySelector('.list') : null;
                    const newitems = newitem?.querySelector('.list-items') || Array.from(doc.querySelectorAll('.list-items'))
                        .find(candidate => candidate.dataset.list === items.dataset.list);
                    const replacement = newitems?.closest('.list');

                    if(replacement) {
                        const newStatus = announce(newitems);
                        if(status && newStatus) {
                            status.textContent = newStatus.textContent;
                            newStatus.replaceWith(status);
                        }
                        item.replaceChildren(...replacement.childNodes);
                        item.scrollIntoView({ behavior: 'smooth' });
                        return;
                    }

                    throw new Error('No list payload found in response');
                }).catch(error => {
                    console.error(error);
                }).finally(() => {
                    item.removeAttribute('aria-busy');
                });
            }
        });
    });
});
