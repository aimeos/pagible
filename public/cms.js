/**
 * @license MIT, https://opensource.org/license/MIT
 */


document.querySelectorAll('link[rel="preload"][as="style"]').forEach(el => {
    el.rel = 'stylesheet';
});


/**
 * Page search
 */
function PagibleSearch() {
    let modal = null;
    let nextPageUrl = null;

    return {
        debounce(fn, delay = 300) {
            let timer;
            return function (...args) {
                clearTimeout(timer);
                timer = setTimeout(() => fn.apply(this, args), delay);
            };
        },


        init(dialog, m) {
            modal = m;

            const form = dialog.querySelector('form');
            const input = dialog.querySelector('input');
            const onSubmit = (ev) => this.select(ev);
            const onInput = this.debounce((ev) => this.search(ev));

            input?.focus();

            form?.addEventListener('submit', onSubmit);
            input?.addEventListener('input', onInput);

            dialog.addEventListener('close', () => {
                form?.removeEventListener('submit', onSubmit);
                input?.removeEventListener('input', onInput);
                modal = null;
            }, { once: true });
        },


        format(text, term) {
            const words = term
                .split(" ")
                .filter(v => v.length > 2);

            if (!words.length) {
                return text;
            }

            return text.replace(new RegExp(`(${words.join("|")})`, "gi"), '<b>$1</b>');
        },


        search(ev) {
            const value = ev.target?.value;
            const form = ev.target?.closest('form');

            if (!form || !value || value.length < 3) {
                return;
            }

            fetch(form.getAttribute('action')?.replace(/_term_/, encodeURIComponent(value)), {
                method: 'GET',
                headers: { 'Accept': 'application/json' },
            }).then(response => {
                if (!response.ok) throw response;
                return response.json();
            }).then(result => {
                const results = ev.target?.closest('article')?.querySelector('.results');
                if (results) {
                    nextPageUrl = result.next_page_url;
                    this.update(results, result.data, value);
                    this.loadmore(results, value);
                }
            }).catch(error => {
                console.error('Error searching pages', error);
            });

            ev.preventDefault();
        },


        select(ev) {
            const result = ev.target?.closest('article')?.querySelector('a.result-item');

            if (result?.href) {
                modal?.close();
                window.location.href = result.href;
            }

            ev.preventDefault();
        },


        update(results, data, value, append = false) {
            if (!results) return;

            if (!append) {
                results.innerHTML = '';
            } else {
                results.querySelector('.load-more')?.remove();
            }

            for (const item of data) {
                const container = document.createElement('a');
                const title = document.createElement('span');
                const content = document.createElement('span');

                container.classList.add('result-item');
                container.href = window.location.protocol + '//' + (item.domain || window.location.host) + '/' + item.path;
                container.addEventListener('click', () => {
                    try {
                        if (new URL(container.href).pathname === window.location.pathname) modal?.close();
                    } catch(e) {}
                });

                title.classList.add('result-title');
                title.textContent = item.title;
                container.appendChild(title);

                content.classList.add('result-content');
                content.innerHTML = this.format(item.content, value);
                container.appendChild(content);

                results.appendChild(container);
            }
        },


        loadmore(results, value) {
            if (!results || !nextPageUrl) return;

            const btn = document.createElement('button');
            btn.classList.add('load-more');
            btn.textContent = 'Load more';
            btn.addEventListener('click', () => {
                btn.disabled = true;

                fetch(nextPageUrl, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                }).then(response => {
                    if (!response.ok) throw response;
                    return response.json();
                }).then(result => {
                    nextPageUrl = result.next_page_url;
                    this.update(results, result.data, value, true);
                    this.loadmore(results, value);
                }).catch(error => {
                    btn.disabled = false;
                    console.error('Error loading more results', error);
                });
            });

            results.appendChild(btn);
        }
    };
};


/**
 * Modals
 */
function PagibleModals(modal) {
    const instance = {
        closingClass: "modal-is-closing",
        openingClass: "modal-is-opening",
        isOpenClass: "modal-is-open",
        visible: false,
        modal: null,


        init(modal) {
            this.modal = modal;
            this.scrollbar();

            // Close with button
            this.modal.querySelector('button[type="reset"]')?.addEventListener("click", () => {
                this.close();
            });

            // Close with a click outside
            document.addEventListener("click", (event) => {
                if (this.visible && this.modal === event.target) {
                this.close();
                }
            });

            // Close with Esc key
            document.addEventListener("keydown", (event) => {
                if (this.visible && event.key === "Escape") {
                this.close();
                }
            });
        },


        open(modal) {
            if (!modal) return;

            document.documentElement.classList.add(this.isOpenClass, this.openingClass);

            setTimeout(() => {
                document.documentElement.classList.remove(this.openingClass);
                this.visible = true;
            }, 300);

            this.init(modal);
            modal.showModal();
        },


        close() {
            if (!this.modal) return;

            this.visible = false;
            document.documentElement.classList.add(this.closingClass);

            setTimeout(() => {
                document.documentElement.classList.remove(this.closingClass, this.isOpenClass);
                this.modal.close();
                this.modal = null;
            }, 300);
        },


        scrollbar() {
            const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;

            if (scrollbarWidth) {
                document.documentElement.style.setProperty('--pico-scrollbar-width', `${scrollbarWidth}px`);
            }
        },
    };

    if(modal) {
        instance.init(modal);
    }

    return instance;
}


document.addEventListener('click', (ev) => {
    const id = ev.target?.closest('[data-modal]')?.dataset?.modal;
    const node = document.getElementById(id);
    const finder = PagibleSearch();

    if(node) {
        const modal = PagibleModals();
        modal.open(node);
        finder.init(node, modal);
        ev.preventDefault();
    }
});



/**
 * Navigation menu
 */
document.addEventListener('DOMContentLoaded', () => {

    const nav = document.querySelector("header nav");
    const open = document.querySelector("header nav .menu-open");
    const close = document.querySelector("header nav .menu-close");

    const sidebar = document.querySelector("main nav.sidebar");
    const sideopen = document.querySelector("header nav .sidebar-open");
    const sideclose = document.querySelector("header nav .sidebar-close");

    open?.addEventListener("click", () => {
        nav?.querySelectorAll(".menu .is-menu")?.forEach(el => el.classList.toggle('dropdown'));
        nav?.classList?.toggle("small");
        open?.classList?.toggle("show");
        close?.classList?.toggle("show");
    });

    close?.addEventListener("click", () => {
        nav?.querySelectorAll(".menu .is-menu")?.forEach(el => el.classList.toggle('dropdown'));
        nav?.classList?.toggle("small");
        open?.classList?.toggle("show");
        close?.classList?.toggle("show");
    });

    sideopen?.addEventListener("click", () => {
        sideopen?.classList?.toggle("show");
        sideclose?.classList?.toggle("show");
        sidebar?.classList.toggle("show");
    });

    sideclose?.addEventListener("click", () => {
        sideopen?.classList?.toggle("show");
        sideclose?.classList?.toggle("show");
        sidebar?.classList.toggle("show");
    });
});
