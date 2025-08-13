/**
 * Contact form
 */
document.querySelectorAll('.contact form').forEach(form => {

    /*
     * HCaptcha dark/light mode
     */
    form.querySelectorAll('.hcaptcha').forEach(el => {
        el.dataset.theme = window.matchMedia("(prefers-color-scheme: dark)").matches ? 'dark' : 'light';
    });


    /*
     * Submit contact form
     */
    form.addEventListener('submit', e => {
        e.preventDefault();

        form.querySelector('.btn').disabled = true;
        form.querySelector('.btn .send')?.classList?.add('hidden');
        form.querySelector('.btn .sending')?.classList?.remove('hidden');

        const errors = form.querySelector('.errors');
        if(errors) errors.textContent = '';

        fetch(form.getAttribute('action'), {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
            },
            body: new FormData(e.target)
        }).then(response => {
            return response.json();
        }).then(result => {
            if(!result.status) {
                throw result.errors || {};
            }

            form.querySelector('.btn .success')?.classList?.remove('hidden');
        }).catch(errors => {
            form.querySelector('.btn .failure')?.classList?.remove('hidden');
            const container = form.querySelector('.errors');

            Object.keys(errors || {}).forEach(key => {
                const field = form.querySelector('[name="' + key + '"');

                field?.classList?.add('error');
                field?.addEventListener('change', () => {
                    field?.classList?.remove('error');
                })

                container.innerHTML = [container.innerHTML, ...errors[key]].filter(v => !!v).join('<br/>')
            })

            setTimeout(() => {
                form.querySelectorAll('.btn span').forEach(el => {
                    el.classList?.add('hidden');
                });
                form.querySelector('.btn .send')?.classList?.remove('hidden');
                form.querySelector('.btn').disabled = false;
            }, 5000);
        }).finally(() => {
            form.querySelector('.btn .sending')?.classList?.add('hidden');
        });
    })
});