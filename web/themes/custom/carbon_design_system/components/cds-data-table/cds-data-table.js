(() => {
    const query = (selector, el = document) => el.querySelector(selector);
    const queryAll = (selector, el = document) => Array.from(el.querySelectorAll(selector));
    const getTableRows = wrapper => queryAll('cds-table-body > cds-table-row', wrapper);
    const clampNumber = (n, min, max) => Math.max(min, Math.min(n, max));

    // ---------- PAGINATION  ----------
    const applyPaginationToWrapper = wrapper => {
        if (wrapper.dataset.paginated === '0') return;
        const pager = query('cds-pagination', wrapper);
        if (!pager) return;

        const allRows = getTableRows(wrapper);
        const total = allRows.length;

        const pageSize =
            Number(pager.pageSize ?? pager.getAttribute('page-size') ??
                   wrapper.getAttribute('data-page-size') ?? 10);

        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        let page = Number(pager.page ?? pager.getAttribute('page') ?? 1);
        page = clampNumber(page, 1, totalPages);

        // Keep the pagination component in sync
        if (String(pager.getAttribute('total-items')) !== String(total)) {
            pager.setAttribute('total-items', String(total));
        }
        if ((pager.page ?? null) !== page) {
            pager.setAttribute('page', String(page));
        }

        // Hide all rows, then show only the current page slice
        allRows.forEach(r => { r.hidden = true; });
        const start = (page - 1) * pageSize;
        const end = start + pageSize;
        allRows.slice(start, end).forEach(r => { r.hidden = false; });

        // Optional "empty" state (if you render one)
        const empty = query('.cds-table-empty', wrapper);
        if (empty) empty.hidden = total !== 0;
    };

    const initDataTable = wrapper => {
        // Initial paint
        requestAnimationFrame(() => applyPaginationToWrapper(wrapper));

        // Hook up pager events (covers both cds- and older bx- events)
        const pager = query('cds-pagination', wrapper);
        if (!pager) return;

        const refreshPagination = () => applyPaginationToWrapper(wrapper);

        [
            'cds-pagination-changed-current',
            'cds-pagination-changed-page-size',
            'bx-pagination-changed-current',
            'bx-pagination-changed-page-size',
            'change',
            'input',
        ].forEach(evt => pager.addEventListener(evt, refreshPagination, { passive: true }));

        // React to attribute changes (e.g., if page/page-size set programmatically)
        new MutationObserver(muts => {
            if (muts.some(m => m.attributeName === 'page' || m.attributeName === 'page-size')) {
                refreshPagination();
            }
        }).observe(pager, { attributes: true, attributeFilter: ['page', 'page-size'] });

        // Safety: clicks that mutate internals
        pager.addEventListener('click', () => requestAnimationFrame(refreshPagination), { passive: true });
    };

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-cds-datatable]').forEach(initDataTable);
    });
})();