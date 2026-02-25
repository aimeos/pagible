/**
 * @license MIT, https://opensource.org/license/MIT
 */


/**
 * Page search
 */
function PagibleSearch() {
    let modal = null;

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
                .filter(v => v.length > 2)
                .map(v => v.replace(/[.*+?^${}()|[\]\\]/g, ''));

            if (!words.length) {
                return text;
            }

            const regex = new RegExp(`(${words.join("|")})`, "i");
            const match = text.match(regex);

            const start = match?.index > 30 ? text.indexOf(' ', match.index - 30) : 0;
            const end = text.indexOf(' ', Math.min(start + 110, text.length));

            text = text.substring(Math.max(start, 0), end > 0 ? end : text.length);

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
                if (results) this.update(results, result, value);
            }).catch(error => {
                console.error('Error searching pages', error);
            });

            ev.preventDefault();
        },


        select(ev) {
            const result = ev.target?.closest('article')?.querySelector('.result-item a');

            if (result?.href) {
                modal?.close();
                window.location.href = result.href;
            }

            ev.preventDefault();
        },


        update(results, data, value) {
            if (!results) return;

            results.innerHTML = '';

            const grouped = data.reduce((acc, item) => {
                acc[item.title] = acc[item.title] || [];
                acc[item.title].push(item);
                return acc;
            }, {});

            for (const name in grouped) {
                const container = document.createElement('div');
                const title = document.createElement('a');
                const first = grouped[name][0];

                title.role = 'heading';
                title.textContent = name;
                title.href = window.location.protocol + '//' + (first?.domain || window.location.host) + '/' + first?.path;
                title.addEventListener('click', () => {
                    try {
                        if (new URL(title.href).pathname === window.location.pathname) modal?.close();
                    } catch(e) {}
                });

                container.classList.add('result-item');
                container.appendChild(title);

                for(const item of grouped[name]) {
                    const content = document.createElement('a');

                    content.href = window.location.protocol + '//' + (item.domain || window.location.host) + '/' + item.path;
                    content.innerHTML = this.format(item.content, value);
                    content.role = 'button';
                    content.addEventListener('click', () => {
                        try {
                            if (new URL(content.href).pathname === window.location.pathname) modal?.close();
                        } catch(e) {}
                    });
                    container.appendChild(content);
                }

                results.appendChild(container);
            }
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
