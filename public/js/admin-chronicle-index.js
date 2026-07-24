document.addEventListener('DOMContentLoaded', () => {
    initChronicleFeaturedToggle();
    initChronicleCfSelects();
    whenSortableReady(initChronicleIndexSortable);
});

function whenSortableReady(callback) {
    if (typeof Sortable !== 'undefined') {
        callback();
        return;
    }
    let tries = 0;
    const timer = window.setInterval(() => {
        tries += 1;
        if (typeof Sortable !== 'undefined') {
            window.clearInterval(timer);
            callback();
        } else if (tries > 40) {
            window.clearInterval(timer);
        }
    }, 50);
}

function initChronicleFeaturedToggle() {
    const csrf = document.querySelector('meta[name="chronicle-featured-csrf"]')?.getAttribute('content') || '';

    document.querySelectorAll('[data-toggle-featured]').forEach((btn) => {
        btn.addEventListener('click', async (event) => {
            event.preventDefault();
            event.stopPropagation();
            const url = btn.getAttribute('data-url');
            if (!url) return;

            btn.disabled = true;
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new URLSearchParams({ _token: csrf }),
                });
                const data = await response.json();
                if (!response.ok || !data.ok) {
                    throw new Error(data.error || 'toggle failed');
                }
                btn.classList.toggle('is-active', Boolean(data.isFeatured));
                btn.setAttribute('aria-pressed', data.isFeatured ? 'true' : 'false');
                btn.title = data.isFeatured ? 'Убрать из избранного' : 'В избранное';
            } catch (error) {
                console.error(error);
                alert('Не удалось переключить избранное');
            } finally {
                btn.disabled = false;
            }
        });
    });
}

function initChronicleCfSelects() {
    document.querySelectorAll('[data-chronicle-cf-select]').forEach((select) => {
        if (!(select instanceof HTMLSelectElement)) {
            return;
        }
        select.addEventListener('change', () => {
            const key = select.getAttribute('data-cf-key');
            if (!key) {
                return;
            }
            const url = new URL(window.location.href);
            const value = select.value.trim();
            if (value) {
                url.searchParams.set(`cf[${key}]`, value);
            } else {
                url.searchParams.delete(`cf[${key}]`);
            }
            url.searchParams.set('page', '1');
            window.location.assign(url.toString());
        });
    });
}

function initChronicleIndexSortable() {
    const tbody = document.querySelector('.ea-index table.datagrid tbody, .ea-index table.table tbody');
    if (!tbody || typeof Sortable === 'undefined' || !tbody.querySelector('[data-chronicle-id]')) {
        return;
    }

    Sortable.create(tbody, {
        handle: '.chronicle-admin-drag',
        animation: 160,
        ghostClass: 'chronicle-admin-row-ghost',
        chosenClass: 'chronicle-admin-row-chosen',
        onEnd: async () => {
            const ids = [...tbody.querySelectorAll('[data-chronicle-id]')]
                .map((el) => el.getAttribute('data-chronicle-id'))
                .filter(Boolean);
            const token = document.querySelector('meta[name="chronicle-reorder-csrf"]')?.getAttribute('content') || '';
            const url = document.querySelector('meta[name="chronicle-reorder-url"]')?.getAttribute('content') || '';
            if (!url || ids.length === 0) {
                return;
            }
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ ids, _token: token }),
                });
                if (!response.ok) {
                    console.error('chronicle reorder failed', await response.text());
                }
            } catch (error) {
                console.error(error);
            }
        },
    });
}
