/**
 * @license MIT, https://opensource.org/license/MIT
 */


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


/**
 * Modals
 */
document.addEventListener('click', (ev) => {

    const el = ev.target?.closest('[data-modal]')
    const modal = document.getElementById(el?.dataset?.modal);
    const closingClass = "modal-is-closing";
    const openingClass = "modal-is-opening";
    const isOpenClass = "modal-is-open";
    let visible = false;


    const scrollbar = () => {
        const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;

        if (scrollbarWidth) {
            document.documentElement.style.setProperty('--pico-scrollbar-width', `${scrollbarWidth}px`);
        }
    }

    // Close modal
    const closeModal = () => {
        visible = false;
        document.documentElement?.classList?.add(closingClass);

        setTimeout(() => {
            document.documentElement?.classList?.remove(closingClass, isOpenClass);
            modal.close();
        }, 300);
    };

    // Close with button
    modal?.querySelector('button[type="reset"]')?.addEventListener("click", () => {
        closeModal();
    });

    // Close with a click outside
    document.addEventListener("click", (event) => {
        if (visible && modal === event.target) {
            closeModal();
        }
    });

    // Close with Esc key
    document.addEventListener("keydown", (event) => {
        if (visible && event.key === "Escape") {
            closeModal();
        }
    });


    if(modal) {
        scrollbar();
        ev.preventDefault();

        document.documentElement?.classList?.add(isOpenClass, openingClass);

        setTimeout(() => {
            document.documentElement?.classList?.remove(openingClass);
            visible = true
        }, 300);
        modal.showModal();
    }
});


/**
 * Page search
 */
const search_debounce = (fn, delay = 300) => {
    let timer;
    return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), delay);
    };
};

const search_format = (text, term) => {
    const words = term.split(" ").filter(v => v.length > 2).map(v => v.replace(/[.*+?^${}()|[\]\\]/g, ''));

    if(!words.length) {
        return text;
    }

    const regex = new RegExp(`(${words.join("|")})`, "i");
    const match = text.match(regex);

    const start = match?.index > 30 ? text.indexOf(' ', match.index - 30) : 0;
    const end = text.indexOf(' ', Math.min(start + 110, text.length));

    text = text.substring(Math.max(start, 0), end > 0 ? end : text.length);

    return text.replace(new RegExp(`(${words.join("|")})`, "gi"), '<b>$1</b>');
};

const search_page = search_debounce((ev) => {
    ev.preventDefault();

    const value = ev.target?.value;
    const form = ev.target?.closest('form');

    if(!form || !value || value.length < 3) {
        return;
    }

    fetch(form?.getAttribute('action')?.replace(/_term_/, encodeURIComponent(value)), {
        method: 'GET',
        headers: {
            'Accept': 'application/json'
        },
    }).then(response => {
        if(!response.ok) {
            throw response;
        }
        return response.json();
    }).then(result => {
        const results = ev.target?.closest('article')?.querySelector('.results');
        results.innerHTML = '';

        if(!results) {
            return;
        }

        const grouped = result.reduce((acc, item) => {
            acc[item.title] = acc[item.title] || []
            acc[item.title].push(item);
            return acc;
        }, {});

        for(let name in grouped) {
            const container = document.createElement('div');
            const title = document.createElement('a');
            const first = grouped[name][0]

            title.role = 'heading';
            title.textContent = name;
            title.href = window.location.protocol + '//' + (first?.domain || window.location.host) + '/' + first?.path;

            container.classList.add('result-item');
            container.appendChild(title);

            for(let item of grouped[name]) {
                const content = document.createElement('a');

                content.href = window.location.protocol + '//' + (item.domain || window.location.host) + '/' + item.path;
                content.innerHTML = search_format(item.content, value);
                content.role = 'button';

                container.appendChild(content);
            }

            results.appendChild(container);
        }
    }).catch(errors => {
        console.error('Error searching pages', errors);
    });
});

document.getElementById('modal-search-input')?.addEventListener('input', search_page);
