/**
 * Type-to-filter for long dropdowns. Any <select> with 12+ options (or data-search) gets a search box
 * in front of it. The real <select> stays in the form (hidden) and keeps its value, so validation,
 * Alpine x-model and form posts work unchanged. Opt out with data-no-search.
 */
const MIN_OPTIONS = 12;

function enhance(select) {
    if (select.dataset.comboReady || select.multiple || select.hasAttribute('data-no-search')) return;
    if (!select.hasAttribute('data-search') && select.options.length < MIN_OPTIONS) return;
    select.dataset.comboReady = '1';

    const wrap = document.createElement('div');
    wrap.className = 'combo relative';
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);
    select.classList.add('sr-only');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');

    const id = 'cb' + Math.random().toString(36).slice(2, 8);
    const input = document.createElement('input');
    input.type = 'text';
    input.className = (select.className.replace('sr-only', '') + ' input combo-input').trim();
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', id);
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('aria-label', select.getAttribute('aria-label') || (select.labels?.[0]?.textContent || '').trim() || 'Choose');
    if (select.id) { input.id = select.id + '_search'; select.labels?.forEach((l) => l.setAttribute('for', input.id)); }
    input.disabled = select.disabled;

    const list = document.createElement('ul');
    list.id = id;
    list.setAttribute('role', 'listbox');
    list.className = 'combo-list';
    list.hidden = true;
    wrap.append(input, list);

    let active = -1;
    let shown = [];

    const selectedText = () => select.selectedOptions[0]?.textContent.trim() ?? '';
    const placeholder = () => (select.options[0] && select.options[0].value === '' ? select.options[0].textContent.trim() : 'Type to search…');
    const sync = () => { input.value = select.value === '' ? '' : selectedText(); input.placeholder = placeholder(); input.disabled = select.disabled; };

    const render = (q) => {
        q = q.toLowerCase().trim();
        shown = [...select.options].filter((o) => !o.disabled && (!q || o.textContent.toLowerCase().includes(q)));
        list.innerHTML = '';
        shown.slice(0, 200).forEach((o, i) => {
            const li = document.createElement('li');
            li.setAttribute('role', 'option');
            li.id = id + '-' + i;
            li.textContent = o.textContent.trim() || '—';
            li.className = 'combo-opt' + (o.value === select.value ? ' is-selected' : '');
            li.setAttribute('aria-selected', String(o.value === select.value));
            li.addEventListener('mousedown', (e) => { e.preventDefault(); pick(o); });
            list.appendChild(li);
        });
        if (!shown.length) {
            const li = document.createElement('li');
            li.className = 'combo-empty';
            li.textContent = 'No matches';
            list.appendChild(li);
        }
        active = shown.length ? Math.max(0, shown.findIndex((o) => o.value === select.value)) : -1;
        highlight();
    };
    const highlight = () => {
        [...list.querySelectorAll('.combo-opt')].forEach((li, i) => li.classList.toggle('is-active', i === active));
        const cur = list.querySelector('.is-active');
        input.setAttribute('aria-activedescendant', cur ? cur.id : '');
        cur?.scrollIntoView({ block: 'nearest' });
    };
    const open = () => { if (input.disabled) return; render(''); list.hidden = false; input.setAttribute('aria-expanded', 'true'); input.select(); };
    const close = () => { list.hidden = true; input.setAttribute('aria-expanded', 'false'); sync(); };
    const pick = (o) => {
        select.value = o.value;
        select.dispatchEvent(new Event('input', { bubbles: true }));
        select.dispatchEvent(new Event('change', { bubbles: true }));
        close();
    };

    input.addEventListener('focus', open);
    input.addEventListener('click', () => { if (list.hidden) open(); });
    input.addEventListener('input', () => { list.hidden = false; render(input.value); });
    input.addEventListener('blur', () => setTimeout(close, 100));
    input.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); if (list.hidden) open(); else { active = Math.min(active + 1, shown.length - 1); highlight(); } }
        else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(active - 1, 0); highlight(); }
        else if (e.key === 'Enter') { if (!list.hidden && shown[active]) { e.preventDefault(); pick(shown[active]); } }
        else if (e.key === 'Escape') { if (!list.hidden) { e.preventDefault(); close(); } }
    });
    select.addEventListener('change', sync);
    // Alpine (x-model / :disabled) changes the select without events; keep the box in step.
    new MutationObserver(sync).observe(select, { attributes: true, childList: true, subtree: true });
    setTimeout(sync, 0);
}

export function enhanceAll(root = document) {
    root.querySelectorAll('select').forEach(enhance);
}

export function initCombobox(root = document) {
    root.querySelectorAll('select').forEach(enhance);
    new MutationObserver((muts) => muts.forEach((m) => m.addedNodes.forEach((n) => {
        if (n.nodeType !== 1) return;
        if (n.tagName === 'SELECT') enhance(n); else n.querySelectorAll?.('select').forEach(enhance);
    }))).observe(document.body, { childList: true, subtree: true });
}
