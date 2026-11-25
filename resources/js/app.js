import Alpine from 'alpinejs';
import { Html5Qrcode } from 'html5-qrcode';
import bookstore from './bookstore';

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
Alpine.data('bookstore', bookstore);
Alpine.start();
