class BillSplitter {
    constructor() {
        this.personCount = 0;
        this.itemCount = 1;
        this.currentBillId = null;
        this.isOwner = true;
        this._calcTimer = null;
        this._init();
    }

    _init() {
        this._updateFooterColspan();
        this._bindEvents();
        this._loadFromUrl();
    }

    _bindEvents() {
        document.getElementById('serviceRate').addEventListener('input', () => this._debounceCalc());
        document.getElementById('gstRate').addEventListener('input', () => this._debounceCalc());

        document.addEventListener('blur', (e) => {
            if (e.target.contentEditable === 'true') this._debounceCalc();
        }, true);

        document.addEventListener('keydown', (e) => {
            if (e.target.classList.contains('amount-input') && e.key === 'Enter') {
                e.preventDefault();
                this._moveNext(e.target);
            }
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                e.preventDefault();
                this.saveBill();
            }
            if (e.key === 'Escape') this.closeModal();
        });

        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('name-input') || e.target.classList.contains('amount-input') || e.target.tagName === 'INPUT') {
                setTimeout(() => e.target.select(), 0);
            }
            if (e.target.contentEditable === 'true') {
                setTimeout(() => {
                    const range = document.createRange();
                    range.selectNodeContents(e.target);
                    const sel = window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(range);
                }, 0);
            }
        });

        document.addEventListener('focus', (e) => {
            if (e.target.classList.contains('amount-input')) {
                setTimeout(() => e.target.select(), 0);
            }
        }, true);

        document.getElementById('modal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) this.closeModal();
        });
    }

    _debounceCalc() {
        clearTimeout(this._calcTimer);
        this._calcTimer = setTimeout(() => this.calculate(), 80);
    }

    _moveNext(currentInput) {
        const row = currentInput.closest('tr');
        const inputs = Array.from(row.querySelectorAll('.amount-input'));
        const idx = inputs.indexOf(currentInput);
        const nextRow = row.nextElementSibling;
        if (nextRow) {
            const next = nextRow.querySelectorAll('.amount-input')[idx];
            if (next) next.focus();
        }
    }

    _generateId() {
        const c = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let r = '';
        for (let i = 0; i < 7; i++) r += c[Math.floor(Math.random() * c.length)];
        return r;
    }

    _toast(message, type = 'info') {
        const el = document.getElementById('toast');
        if (!el) return;
        el.innerHTML = `<div class="toast toast-${type}">${message}</div>`;
        setTimeout(() => {
            const t = el.querySelector('.toast');
            if (t) {
                t.style.transition = 'opacity 0.2s';
                t.style.opacity = '0';
                setTimeout(() => el.innerHTML = '', 200);
            }
        }, 3000);
    }

    _isOwnerOf(billId) {
        return JSON.parse(localStorage.getItem('billOwners') || '{}')[billId] === true;
    }

    _markOwner(billId) {
        const o = JSON.parse(localStorage.getItem('billOwners') || '{}');
        o[billId] = true;
        localStorage.setItem('billOwners', JSON.stringify(o));
    }

    _getApiUrl(query = '') {
        const path = window.location.pathname;
        let base;
        if (path.includes('index.html')) {
            base = path.replace('index.html', 'api.php');
        } else {
            base = path.endsWith('/') ? path + 'api.php' : path + '/api.php';
        }
        return query ? `${base}?${query}` : base;
    }

    _copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).catch(() => this._fallbackCopy(text));
        } else {
            this._fallbackCopy(text);
        }
    }

    _fallbackCopy(text) {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
    }

    _headerRowHTML() {
        return `
            <th class="col-name">Person</th>
            <th class="col-item" contenteditable="true">Item 1</th>
            <th class="col-add no-print"><button onclick="BillSplitter.addItem()" aria-label="Add item">+</button></th>
            <th class="col-total">Total</th>
            <th class="col-action no-print"></th>`;
    }

    addPerson() {
        if (!this.isOwner) return;
        this.personCount++;
        const tbody = document.getElementById('tableBody');
        const row = document.createElement('tr');

        let cells = '';
        for (let i = 0; i < this.itemCount; i++) {
            cells += `<td><input type="text" inputmode="decimal" class="bill-table input amount-input" value="0" oninput="BillSplitter.instance._debounceCalc()"></td>`;
        }

        row.innerHTML = `
            <td class="col-name"><input type="text" class="bill-table input name-input" value="Person ${this.personCount}" oninput="BillSplitter.instance._debounceCalc()"></td>
            ${cells}
            <td class="col-add no-print"><button onclick="BillSplitter.instance.addItem()" aria-label="Add item">+</button></td>
            <td class="col-total total-cell">0.00</td>
            <td class="col-action no-print"><button class="remove-btn" onclick="BillSplitter.instance.removePerson(this)" aria-label="Remove person">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button></td>
        `;

        tbody.appendChild(row);
        this.calculate();
        if (this._initialized) {
            const nameInput = row.querySelector('.name-input');
            nameInput.focus();
            nameInput.select();
            row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    removePerson(btn) {
        if (!this.isOwner) return;
        const tbody = document.getElementById('tableBody');
        if (tbody.children.length <= 1) {
            this._toast('At least one person is required', 'error');
            return;
        }
        const row = btn.closest('tr');
        row.classList.add('removing');
        setTimeout(() => {
            row.remove();
            this.calculate();
        }, 200);
    }

    addItem() {
        if (!this.isOwner) return;
        this.itemCount++;

        const headerRow = document.getElementById('headerRow');
        const addCol = headerRow.querySelector('.col-add');

        const th = document.createElement('th');
        th.className = 'col-item';
        th.contentEditable = 'true';
        th.textContent = `Item ${this.itemCount}`;
        headerRow.insertBefore(th, addCol);

        const rows = document.getElementById('tableBody').children;
        for (const row of rows) {
            const td = document.createElement('td');
            td.className = 'col-item';
            td.innerHTML = `<input type="text" inputmode="decimal" class="bill-table input amount-input" value="0" oninput="BillSplitter.instance._debounceCalc()">`;
            row.insertBefore(td, row.querySelector('.col-add'));
        }

        this._updateFooterColspan();
        this.calculate();
        th.focus();
        const range = document.createRange();
        range.selectNodeContents(th);
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
    }

    _updateFooterColspan() {
        const footerBreakdown = document.getElementById('footerBreakdown');
        if (footerBreakdown) {
            footerBreakdown.setAttribute('colspan', this.itemCount + 1);
        }
    }

    _calcVal(expr) {
        if (!expr || expr === '') return 0;
        try {
            const clean = expr.toString().replace(/[^0-9+\-*/.() ]/g, '');
            if (/^[\d+\-*/.() ]+$/.test(clean)) {
                const r = Function('"use strict"; return (' + clean + ')')();
                return (typeof r === 'number' && isFinite(r)) ? r : 0;
            }
            return parseFloat(clean) || 0;
        } catch {
            return parseFloat(expr) || 0;
        }
    }

    calculate() {
        const svcRate = parseFloat(document.getElementById('serviceRate').value) || 0;
        const gstRate = parseFloat(document.getElementById('gstRate').value) || 0;

        let tSub = 0, tSvc = 0, tGst = 0;

        const rows = document.getElementById('tableBody').children;
        for (const row of rows) {
            let sub = 0;
            for (const inp of row.querySelectorAll('.amount-input')) {
                sub += this._calcVal(inp.value);
            }

            const svc = sub * (svcRate / 100);
            const gst = (sub + svc) * (gstRate / 100);
            const total = sub + svc + gst;

            row.querySelector('.total-cell').textContent = total.toFixed(2);

            tSub += sub;
            tSvc += svc;
            tGst += gst;
        }

        document.getElementById('totalSubtotal').textContent = tSub.toFixed(2);
        document.getElementById('totalService').textContent = tSvc.toFixed(2);
        document.getElementById('totalGST').textContent = tGst.toFixed(2);
        document.getElementById('grandTotal').textContent = (tSub + tSvc + tGst).toFixed(2);
    }

    _collectData() {
        const items = [];
        const headerRow = document.getElementById('headerRow');
        headerRow.querySelectorAll('.col-item').forEach(th => items.push(th.textContent.trim()));

        const people = [];
        const rows = document.getElementById('tableBody').children;
        for (const row of rows) {
            const name = row.querySelector('.name-input').value;
            const amounts = [];
            row.querySelectorAll('.amount-input').forEach(inp => amounts.push(inp.value));
            people.push({ name, amounts });
        }

        return {
            billId: this.currentBillId,
            people,
            items,
            serviceRate: document.getElementById('serviceRate').value,
            gstRate: document.getElementById('gstRate').value,
            createdAt: new Date().toISOString(),
            lastModified: new Date().toISOString()
        };
    }

    async saveBill() {
        if (!this.isOwner) return;

        const btn = document.getElementById('saveBtn');
        const origHTML = btn.innerHTML;
        btn.innerHTML = 'Saving...';
        btn.disabled = true;

        const billId = this.currentBillId || this._generateId();
        const data = this._collectData();

        if (!this.currentBillId) {
            this.currentBillId = billId;
            this._markOwner(billId);
        }

        const local = JSON.parse(localStorage.getItem('billSplitterData') || '{}');
        local[billId] = data;
        localStorage.setItem('billSplitterData', JSON.stringify(local));

        let serverOk = false;
        try {
            const res = await fetch(this._getApiUrl(), {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ...data, billId })
            });

            if (res.ok) {
                const j = await res.json();
                if (j.success) serverOk = true;
            }
        } catch (e) {
            console.log('Server save failed:', e);
        }

        const url = `${window.location.origin}${window.location.pathname}?${billId}`;
        this._copyToClipboard(url);

        this._toast(serverOk ? 'Link copied to clipboard' : 'Bill saved locally (server unavailable)', serverOk ? 'success' : 'info');

        setTimeout(() => {
            btn.innerHTML = origHTML;
            btn.disabled = false;
        }, 400);
    }

    _loadFromUrl() {
        const params = new URLSearchParams(window.location.search);
        const billId = [...params.keys()][0];

        if (billId && /^[a-zA-Z0-9]{7}$/.test(billId)) {
            this._loadBill(billId);
        } else {
            this.addPerson();
            this.addPerson();
            this.calculate();
        }
        this._initialized = true;
    }

    async _loadBill(billId) {
        let data = null;

        try {
            const res = await fetch(this._getApiUrl(`id=${billId}`));
            if (res.ok) data = await res.json();
        } catch (e) {
            console.log('Server load failed:', e);
        }

        if (!data) {
            const local = JSON.parse(localStorage.getItem('billSplitterData') || '{}');
            data = local[billId];
        }

        if (!data) {
            this._toast('Bill not found', 'error');
            this.addPerson();
            this.addPerson();
            return;
        }

        this.isOwner = this._isOwnerOf(billId);
        this.currentBillId = billId;

        document.getElementById('tableBody').innerHTML = '';
        this.personCount = 0;
        this.itemCount = 1;

        document.getElementById('serviceRate').value = data.serviceRate || 10;
        document.getElementById('gstRate').value = data.gstRate || 8;

        const headerRow = document.getElementById('headerRow');
        headerRow.innerHTML = this._headerRowHTML();

        this.itemCount = data.items.length;
        for (let i = 1; i < data.items.length; i++) {
            const addCol = headerRow.querySelector('.col-add');
            const th = document.createElement('th');
            th.className = 'col-item';
            th.contentEditable = 'true';
            th.textContent = data.items[i];
            headerRow.insertBefore(th, addCol);
        }

        if (data.items[0]) {
            headerRow.querySelector('.col-item').textContent = data.items[0];
        }

        data.people.forEach(p => this._addPersonWithData(p.name, p.amounts));

        this._updateFooterColspan();
        this._applyPermissions();
        setTimeout(() => this.calculate(), 50);
    }

    _addPersonWithData(name, amounts) {
        this.personCount++;
        const tbody = document.getElementById('tableBody');
        const row = document.createElement('tr');

        let cells = '';
        for (let i = 0; i < this.itemCount; i++) {
            const v = amounts[i] || '0';
            cells += `<td class="col-item"><input type="text" inputmode="decimal" class="bill-table input amount-input" value="${v}" oninput="BillSplitter.instance._debounceCalc()"></td>`;
        }

        row.innerHTML = `
            <td class="col-name"><input type="text" class="bill-table input name-input" value="${name}" oninput="BillSplitter.instance._debounceCalc()"></td>
            ${cells}
            <td class="col-add no-print"><button onclick="BillSplitter.instance.addItem()" aria-label="Add item">+</button></td>
            <td class="col-total total-cell">0.00</td>
            <td class="col-action no-print"><button class="remove-btn" onclick="BillSplitter.instance.removePerson(this)" aria-label="Remove person">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button></td>
        `;

        tbody.appendChild(row);
    }

    _applyPermissions() {
        if (!this.isOwner) {
            const ctrl = document.querySelector('.settings');
            if (ctrl) ctrl.style.display = 'none';

            const printBtn = document.querySelector('.btn-print');
            if (printBtn) printBtn.style.display = 'none';

            const table = document.querySelector('.bill-table');

            table.querySelectorAll('th.col-add, th.col-action').forEach(el => el.remove());

            table.querySelectorAll('tbody tr').forEach(row => {
                row.querySelector('.col-add')?.remove();
                row.querySelector('.col-action')?.remove();
            });

            const footerRow = table.querySelector('.footer-row');
            if (footerRow) {
                footerRow.querySelector('.no-print')?.remove();
                const footerBreakdown = document.getElementById('footerBreakdown');
                if (footerBreakdown) {
                    footerBreakdown.setAttribute('colspan', this.itemCount);
                }
            }

            document.querySelectorAll('.app input, .app button').forEach(el => {
                if (el.closest('.btn-new')) return;
                el.disabled = true;
                el.style.cursor = 'default';
            });

            document.querySelectorAll('[contenteditable]').forEach(el => {
                el.contentEditable = 'false';
                el.style.cursor = 'default';
            });

            const bottomBar = document.querySelector('.bottom-bar');
            if (bottomBar) bottomBar.style.display = 'none';
        }
    }

    copyLink() {
        const inp = document.getElementById('shareLink');
        inp.select();
        this._copyToClipboard(inp.value);

        const btn = document.getElementById('copyBtn');
        const orig = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(() => { btn.textContent = orig; }, 1500);
    }

    closeModal() {
        const modal = document.getElementById('modal');
        modal.classList.remove('open');
        setTimeout(() => { modal.style.display = 'none'; }, 200);
    }

    clearAll() {
        if (this.isOwner && !confirm('Clear all data? This cannot be undone.')) return;
        window.location.href = window.location.pathname;
    }

    static addPerson() { BillSplitter.instance?.addPerson(); }
    static addItem() { BillSplitter.instance?.addItem(); }
    static saveBill() { BillSplitter.instance?.saveBill(); }
    static clearAll() { BillSplitter.instance?.clearAll(); }
    static copyLink() { BillSplitter.instance?.copyLink(); }
    static closeModal() { BillSplitter.instance?.closeModal(); }
}

document.addEventListener('DOMContentLoaded', () => {
    BillSplitter.instance = new BillSplitter();
});
