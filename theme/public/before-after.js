/**
 * @license MIT, https://opensource.org/license/MIT
 */


/**
 * Move the divider of before/after image comparisons
 */
document.addEventListener('input', ev => {
    if(ev.target.matches?.('.before-after .compare input[type="range"]')) {
        ev.target.parentElement.style.setProperty('--pos', ev.target.value + '%')
    }
});
