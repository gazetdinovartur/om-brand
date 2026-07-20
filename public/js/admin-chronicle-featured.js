document.addEventListener('DOMContentLoaded', () => {
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
});
