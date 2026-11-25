import Alpine from 'alpinejs';
import morph from '@alpinejs/morph';
import { Html5Qrcode } from 'html5-qrcode';
import bookstore from './bookstore';
import { initCombobox } from './combobox';
import { initAjaxForms } from './ajax-forms';

window.Alpine = Alpine;
window.Html5Qrcode = Html5Qrcode;

Alpine.store('bell', { count: 0 });
Alpine.store('theme', {
    dark: document.documentElement.classList.contains('dark'),
    toggle() {
        this.dark = !this.dark;
        localStorage.theme = this.dark ? 'dark' : 'light';
        document.documentElement.classList.toggle('dark', this.dark);
    },
});
Alpine.plugin(morph);
Alpine.data('bookstore', bookstore);
Alpine.start();
// After Alpine renders x-for rows, long dropdowns get type-to-filter.
queueMicrotask(() => initCombobox());
initAjaxForms();
