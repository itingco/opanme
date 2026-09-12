(() => {
    'use strict';

    const config = window.itemLookupConfig ?? {};
    const codeInput = document.getElementById('item_code');
    const nameInput = document.getElementById('item_name');
    const qtyInput = document.getElementById('qty');
    const resultBox = document.getElementById('item-results');
    const status = document.getElementById('lookup-status');
    const qrPreview = document.getElementById('qr-preview');

    if (!codeInput || !nameInput || !qtyInput || !resultBox || !config.endpoint) {
        return;
    }

    let debounceTimer = null;
    let requestController = null;
    let selectedCode = '';
    let currentItems = [];
    let activeIndex = -1;

    const updateQrPreview = () => {
        const code = codeInput.value.trim();
        const qty = qtyInput.value.trim();
        qrPreview.textContent = code && qty ? `${code}-${qty}` : '-';
    };

    const closeResults = () => {
        resultBox.hidden = true;
        resultBox.innerHTML = '';
        currentItems = [];
        activeIndex = -1;
        codeInput.setAttribute('aria-expanded', 'false');
    };

    const selectItem = (item) => {
        codeInput.value = item.code;
        nameInput.value = item.name;
        selectedCode = item.code;
        status.textContent = 'Dipilih';
        closeResults();
        updateQrPreview();
        qtyInput.focus();
        qtyInput.select();
    };

    const renderResults = (items) => {
        resultBox.innerHTML = '';
        currentItems = items;
        activeIndex = -1;

        if (items.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'lookup-empty';
            empty.textContent = 'Barang tidak ditemukan.';
            resultBox.appendChild(empty);
        } else {
            items.forEach((item, index) => {
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'lookup-option';
                option.id = `item-option-${index}`;
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', 'false');

                const code = document.createElement('strong');
                code.textContent = item.code;
                const name = document.createElement('span');
                name.textContent = item.name;

                option.append(code, name);
                option.addEventListener('click', () => selectItem(item));
                resultBox.appendChild(option);
            });
        }

        resultBox.hidden = false;
        codeInput.setAttribute('aria-expanded', 'true');
    };

    const setActiveOption = (newIndex) => {
        const options = Array.from(resultBox.querySelectorAll('.lookup-option'));

        if (options.length === 0) {
            return;
        }

        activeIndex = (newIndex + options.length) % options.length;
        options.forEach((option, index) => {
            const active = index === activeIndex;
            option.setAttribute('aria-selected', active ? 'true' : 'false');
            if (active) {
                codeInput.setAttribute('aria-activedescendant', option.id);
                option.scrollIntoView({ block: 'nearest' });
            }
        });
    };

    const fetchItems = async (term, selectExactOnly = false) => {
        requestController?.abort();
        requestController = new AbortController();
        status.textContent = 'Mencari…';

        try {
            const url = new URL(config.endpoint, window.location.origin);
            url.searchParams.set('q', term);
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                signal: requestController.signal,
            });

            if (!response.ok) {
                throw new Error('Lookup request failed');
            }

            const payload = await response.json();
            const items = Array.isArray(payload.data) ? payload.data : [];
            const exact = items.find((item) => item.code.toLowerCase() === term.toLowerCase());

            if (selectExactOnly && exact) {
                codeInput.value = exact.code;
                nameInput.value = exact.name;
                selectedCode = exact.code;
                status.textContent = 'Ditemukan';
                closeResults();
                updateQrPreview();
                return;
            }

            status.textContent = items.length ? `${items.length} hasil` : 'Tidak ada hasil';
            renderResults(items);
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            status.textContent = 'Koneksi gagal';
            nameInput.value = '';
            closeResults();
        }
    };

    codeInput.addEventListener('input', () => {
        const term = codeInput.value.trim();
        nameInput.value = '';
        selectedCode = '';
        updateQrPreview();
        window.clearTimeout(debounceTimer);

        if (term.length === 0) {
            status.textContent = '';
            closeResults();
            return;
        }

        debounceTimer = window.setTimeout(() => fetchItems(term), 250);
    });

    codeInput.addEventListener('blur', () => {
        const term = codeInput.value.trim();

        window.setTimeout(() => {
            closeResults();
        }, 180);

        if (term && term !== selectedCode) {
            fetchItems(term, true);
        }
    });

    codeInput.addEventListener('keydown', (event) => {
        if (resultBox.hidden) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActiveOption(activeIndex + 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActiveOption(activeIndex - 1);
        } else if (event.key === 'Enter' && activeIndex >= 0) {
            event.preventDefault();
            selectItem(currentItems[activeIndex]);
        } else if (event.key === 'Escape') {
            closeResults();
        }
    });

    qtyInput.addEventListener('input', updateQrPreview);
    document.addEventListener('click', (event) => {
        if (!resultBox.contains(event.target) && event.target !== codeInput) {
            closeResults();
        }
    });

    updateQrPreview();

    if (config.previousCode) {
        fetchItems(config.previousCode, true);
    }
})();
