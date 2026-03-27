/**
 * @license MIT, https://opensource.org/license/MIT
 */


/**
 * Copy code
 */
document.querySelectorAll('.code-box .code-copy').forEach(button => {
    button.addEventListener('click', () => {
        const text = button.nextElementSibling?.querySelector('code')?.innerText;

        navigator.clipboard.writeText(text).then(() => {
            button.querySelector('.copy-code').classList.toggle('hidden')
            button.querySelector('.copy-check').classList.toggle('hidden')

            setTimeout(() => {
                button.querySelector('.copy-code').classList.toggle('hidden')
                button.querySelector('.copy-check').classList.toggle('hidden')
            }, 1500);
        });
    });
});
