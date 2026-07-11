/**
 * @license MIT, https://opensource.org/license/MIT
 */


/**
 * Dynamically load list pages via AJAX when clicking on the pagination links.
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.cms-content .list').forEach(item => {
        item.addEventListener('click', ev => {
            const items = ev.target.closest('.list-items')
            const a = ev.target.closest('.list-items .pagination a.page-link');

            if(a && document.body.contains(a)) {
                ev.preventDefault();

                fetch(a.href).then(response => {
                    if(!response.ok) {
                        console.error('Fetching list page failed', response);
                        return;
                    }
                    return response.text();
                }).then(text => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(text, 'text/html');
                    const newitems = doc.querySelector(`.list-items[data-list="${items.dataset.list}"]`);

                    if(newitems) {
                        items.replaceWith(newitems);
                        item.scrollIntoView({ behavior: 'smooth' });
                    }
                })
            }
        });
    });
});
