document.addEventListener('click', (event) => {
    const button = event.target instanceof HTMLElement
        ? event.target.closest('[data-copy-btn]')
        : null;

    if (!(button instanceof HTMLButtonElement)) {
        return;
    }

    const row = button.closest('.payment-admin-links__row');
    const field = row?.querySelector('[data-copy-field]');

    if (!(field instanceof HTMLInputElement)) {
        return;
    }

    const text = field.value;
    const copiedLabel = 'Скопировано';
    const defaultLabel = button.dataset.defaultLabel || button.textContent || 'Копировать';

    const markCopied = () => {
        button.textContent = copiedLabel;
        button.classList.add('is-copied');
        window.setTimeout(() => {
            button.textContent = defaultLabel;
            button.classList.remove('is-copied');
        }, 1800);
    };

    if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(text).then(markCopied).catch(() => {
            field.select();
            document.execCommand('copy');
            markCopied();
        });

        return;
    }

    field.select();
    document.execCommand('copy');
    markCopied();
});

document.querySelectorAll('[data-copy-btn]').forEach((button) => {
    if (button instanceof HTMLButtonElement && !button.dataset.defaultLabel) {
        button.dataset.defaultLabel = button.textContent?.trim() || 'Копировать';
    }
});
