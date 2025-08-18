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
