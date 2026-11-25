// Bookstore grid + bag (students, parents and bookstore staff share it).
// Quantities are capped at stock; the server re-checks stock inside a transaction at checkout.
export default (items, opts = {}) => ({
    items,
    q: '', cat: '', sort: 'name',
    bag: opts.walkIn ? [] : JSON.parse(localStorage.getItem(opts.key || 'ss.cart') || '[]'),
    bagOpen: false, sizeFor: null, size: null, busy: false,
    peso(v) { return '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
    get shown() {
        const q = this.q.trim().toLowerCase();
        const list = this.items.filter((i) => (!this.cat || i.category === this.cat) && (!q || i.name.toLowerCase().includes(q) || (i.description || '').toLowerCase().includes(q)));
        const by = { name: (a, b) => a.name.localeCompare(b.name), low: (a, b) => a.price - b.price, high: (a, b) => b.price - a.price }[this.sort];
        return list.sort(by);
    },
    item(id) { return this.items.find((i) => i.id === id) },
    inBag(id) { return this.bag.filter((b) => b.id === id).reduce((t, b) => t + b.qty, 0) },
    left(id) { const it = this.item(id); return it && it.stock !== null ? it.stock - this.inBag(id) : Infinity },
    stockChip(i) {
        if (i.stock === null) return '';
        if (i.stock <= 0) return 'Out of stock';
        return i.stock <= 5 ? 'Only ' + i.stock + ' left' : i.stock + ' in stock';
    },
    add(id, size = null) {
        const it = this.item(id); if (!it || this.left(id) <= 0) return;
        if (it.sizes && it.sizes.length && !size) { this.sizeFor = id; this.size = it.sizes[0]; return }
        const same = this.bag.find((b) => b.id === id && b.size === size);
        same ? same.qty++ : this.bag.push({ id, name: it.name, price: it.price, size, qty: 1 });
        this.sizeFor = null; this.save();
    },
    step(n, d) { const b = this.bag[n]; if (d > 0 && this.left(b.id) <= 0) return; b.qty += d; if (b.qty <= 0) this.bag.splice(n, 1); this.save() },
    remove(n) { this.bag.splice(n, 1); this.save() },
    get count() { return this.bag.reduce((t, b) => t + b.qty, 0) },
    get subtotal() { return this.bag.reduce((t, b) => t + b.qty * b.price, 0) },
    save() { if (!opts.walkIn) localStorage.setItem(opts.key || 'ss.cart', JSON.stringify(this.bag)) },
    clear() { this.bag = []; this.save() },
});
