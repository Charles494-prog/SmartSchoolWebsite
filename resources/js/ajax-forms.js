/**
 * Stay-on-page actions. Inside <main data-ajax-forms>, POST forms (approve, decline, notify, release…)
 * are sent in the background. The server still answers with its normal redirect; we fetch the page it
 * lands on and morph only the changed parts in place (main content, sidebar badges, toast), so open
 * panels, tabs, scroll position and filters stay as they were. If the action leads to another page,
 * we simply go there. Opt a form out with data-no-ajax.
 */
import { enhanceAll } from './combobox';

const REGIONS = ['app-main', 'app-nav'];

function eligible(form, submitter) {
    if (!(form instanceof HTMLFormElement) || !form.closest('[data-ajax-forms]')) return false;
    if (form.hasAttribute('data-no-ajax') || form.target || (submitter && submitter.formTarget)) return false;
    if ((submitter?.getAttribute('formmethod') || form.getAttribute('method') || 'get').toLowerCase() !== 'post') return false;
    return true;
}

async function send(form, submitter) {
    const action = submitter?.getAttribute('formaction') || form.action;
    const body = new FormData(form, submitter || undefined);
    const buttons = [...form.querySelectorAll('button, input[type=submit]')];
    buttons.forEach((b) => { b.dataset.wasDisabled = b.disabled ? '1' : ''; b.disabled = true; });
    form.setAttribute('aria-busy', 'true');
    try {
        const res = await fetch(action, { method: 'POST', body, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' } });
        const type = res.headers.get('content-type') || '';
        const landed = new URL(res.url, location.href);
        if (!type.includes('text/html')) { location.assign(res.url); return; }
        if (landed.pathname !== location.pathname) { location.assign(landed.href); return; }
        const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
        for (const id of REGIONS) {
            const now = document.getElementById(id);
            const next = doc.getElementById(id);
            if (now && next) window.Alpine.morph(now, next, { key: (el) => el.id || el.dataset?.key });
        }
        // Toasts are replaced, not morphed: a morph would keep the old toast's hidden state.
        const toasts = document.getElementById('app-toasts');
        const newToasts = doc.getElementById('app-toasts');
        if (toasts && newToasts) toasts.innerHTML = newToasts.innerHTML;
        enhanceAll();
        // Forms that showed a "Saving…" state get their buttons back.
        document.querySelectorAll('[x-data]').forEach((el) => {
            const d = el._x_dataStack?.[0];
            if (d && Object.prototype.hasOwnProperty.call(d, 'busy')) d.busy = false;
        });
        // Parts drawn from JSON (e.g. the bookstore orders panel) refresh themselves on this event.
        window.dispatchEvent(new CustomEvent('ajax-done', { detail: { doc } }));
        if (landed.search !== location.search) history.replaceState(history.state, '', landed.href);
    } catch {
        form.submit(); // network hiccup: fall back to a normal submit
        return;
    } finally {
        form.removeAttribute('aria-busy');
        buttons.forEach((b) => { if (b.isConnected) b.disabled = b.dataset.wasDisabled === '1'; });
    }
}

export function initAjaxForms() {
    // Bubble phase, so x-on:submit confirm() dialogs that call preventDefault() win.
    document.addEventListener('submit', (e) => {
        if (e.defaultPrevented || !eligible(e.target, e.submitter)) return;
        e.preventDefault();
        send(e.target, e.submitter);
    });
}
