/**
 * @license MIT, https://opensource.org/license/MIT
 */


/**
 * Lazy CSRF token for statically cached / CDN-served pages.
 *
 * Cacheable pages are byte-identical for every visitor and carry no session
 * token. The first time a visitor submits a form, fetch a fresh token from an
 * uncached endpoint (which also starts their session), populate the token field
 * and the <meta name="csrf-token"> tag, then let the original submit proceed.
 *
 * Works for both native form submits (e.g. checkout) and AJAX handlers that read
 * the meta tag (e.g. the contact form) — no per-form code required.
 */
(function() {
    let pending = null;

    function token() {
        if(pending) {
            return pending;
        }

        pending = fetch('/cmsapi/csrf', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(response => response.json())
            .then(data => {
                let meta = document.querySelector('meta[name="csrf-token"]');

                if(!meta) {
                    meta = document.createElement('meta');
                    meta.setAttribute('name', 'csrf-token');
                    document.head.appendChild(meta);
                }

                meta.setAttribute('content', data.token);
                document.querySelectorAll('input[name="_token"]').forEach(el => { el.value = data.token; });

                return data.token;
            })
            .catch(() => { pending = null; });

        return pending;
    }

    // Ensure the form's _token field is populated before it submits. When a token
    // is already available (e.g. an authenticated editor with the meta tag) it is
    // filled synchronously and the submit proceeds. Otherwise the submit is held,
    // a token is fetched (starting the session), and the form is re-submitted. The
    // capture phase + stopImmediatePropagation keep other submit handlers (e.g. the
    // contact form's AJAX) from running until the token is in place.
    document.addEventListener('submit', e => {
        const form = e.target;
        const field = form.querySelector ? form.querySelector('input[name="_token"]') : null;

        if(!field || field.value) {
            return; // no token field, or already filled (e.g. our own re-submit)
        }

        const available = document.querySelector('meta[name="csrf-token"]')?.content;

        if(available) {
            field.value = available; // token on hand — fill the field, let the submit proceed
            return;
        }

        e.preventDefault();
        e.stopImmediatePropagation();

        token().then(() => form.requestSubmit(e.submitter));
    }, true);

    // Expose for explicit use by AJAX handlers if ever needed.
    window.cmsCsrfToken = token;
})();
