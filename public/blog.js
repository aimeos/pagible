/**
 * @license MIT, https://opensource.org/license/MIT
 */


/**
 * Dynamically load blog
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.blog').forEach(blog => {
        blog.addEventListener('click', ev => {
            const items = ev.target.closest('.blog-items')
            const a = ev.target.closest('.blog-items .pagination a.page-link');

            if(a && document.body.contains(a)) {
                ev.preventDefault();

                fetch(a.href).then(response => {
                    if(!response.ok) {
                        console.error('Fetching blog page failed', response);
                        return;
                    }
                    return response.text();
                }).then(text => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(text, 'text/html');
                    const newitems = doc.querySelector(`.blog-items[data-blog="${items.dataset.blog}"]`);

                    if(newitems) {
                        items.replaceWith(newitems);
                        blog.scrollIntoView({ behavior: 'smooth' });
                    }
                })
            }
        });
    });
});
