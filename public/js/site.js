document.addEventListener('DOMContentLoaded', () => {
    const header = document.querySelector('.site-header');
    const fab = document.querySelector('.site-fab');
    const contact = document.querySelector('#contact');

    const onScroll = () => {
        const y = window.scrollY;
        document.documentElement.style.setProperty('--scroll', String(Math.min(y / 600, 1)));

        if (fab && contact) {
            const rect = contact.getBoundingClientRect();
            fab.style.opacity = rect.top < window.innerHeight * 0.6 ? '0' : '1';
            fab.style.pointerEvents = rect.top < window.innerHeight * 0.6 ? 'none' : 'auto';
        }

        if (header) {
            header.style.boxShadow = y > 12 ? '0 10px 30px rgba(31, 26, 20, 0.05)' : 'none';
        }
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    initFileUploads();
});

function formatFileSize(bytes) {
    if (bytes < 1024) {
        return `${bytes} Б`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1).replace('.0', '')} КБ`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1).replace('.0', '')} МБ`;
}

function initFileUploads() {
    document.querySelectorAll('[data-file-upload]').forEach((zone) => {
        const input = zone.querySelector('[data-file-input]');
        const dropzone = zone.querySelector('[data-file-dropzone]');
        const preview = zone.querySelector('[data-file-preview]');
        const nameEl = zone.querySelector('[data-file-name]');
        const sizeEl = zone.querySelector('[data-file-size]');
        const removeBtn = zone.querySelector('[data-file-remove]');

        if (!(input instanceof HTMLInputElement) || !dropzone || !preview || !nameEl || !sizeEl || !removeBtn) {
            return;
        }

        const sync = () => {
            const file = input.files?.[0];

            if (file) {
                zone.classList.add('is-filled');
                preview.hidden = false;
                nameEl.textContent = file.name;
                sizeEl.textContent = formatFileSize(file.size);
            } else {
                zone.classList.remove('is-filled');
                preview.hidden = true;
                nameEl.textContent = '';
                sizeEl.textContent = '';
            }
        };

        input.addEventListener('change', sync);

        removeBtn.addEventListener('click', () => {
            input.value = '';
            sync();
        });

        dropzone.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                input.click();
            }
        });

        ['dragenter', 'dragover'].forEach((eventName) => {
            dropzone.addEventListener(eventName, (event) => {
                event.preventDefault();
                dropzone.classList.add('is-dragover');
            });
        });

        ['dragleave', 'drop'].forEach((eventName) => {
            dropzone.addEventListener(eventName, () => {
                dropzone.classList.remove('is-dragover');
            });
        });

        dropzone.addEventListener('drop', (event) => {
            event.preventDefault();

            const file = event.dataTransfer?.files?.[0];
            if (!file) {
                return;
            }

            const transfer = new DataTransfer();
            transfer.items.add(file);
            input.files = transfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
}
