import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

import './stores/ui';
import './components/lembar-nilai';

Alpine.plugin(collapse);

/*
 * Confirmation prompts are declared as data, never as inline handlers.
 *
 * An inline `onsubmit="return confirm('… {{ $nama }} …')"` puts a record name
 * inside a JavaScript string, where Blade's HTML escaping is undone by the
 * attribute parser before the script runs: an applicant named `x');…('` ran
 * code in the admin's session. A data attribute is only ever text, and with no
 * inline handlers left the CSP can refuse 'unsafe-inline' for scripts.
 *
 *   data-confirm="Hapus berkas ini?"        on the form or its submit button
 *   data-confirm-if="hasil=lulus"           ask only when that field has that value
 */
document.addEventListener(
    'submit',
    (event) => {
        const form = event.target;
        const sumber = event.submitter?.dataset.confirm ? event.submitter : form;
        const pesan = sumber.dataset?.confirm;

        if (!pesan) {
            return;
        }

        const syarat = sumber.dataset.confirmIf;
        if (syarat) {
            const [nama, nilai] = syarat.split('=');
            if (form.elements.namedItem(nama)?.value !== nilai) {
                return;
            }
        }

        if (!window.confirm(pesan)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    },
    true,
);

window.Alpine = Alpine;

Alpine.start();
