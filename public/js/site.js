document.addEventListener('DOMContentLoaded', () => {
    const header = document.querySelector('.site-header');
    const fab = document.querySelector('.site-fab');
    const contact = document.querySelector('#contact');

    initMobileNav();
    initFormFeedbackReveal();
    initInquiryForm();
    initFileUploads();

    const onScroll = () => {
        const y = window.scrollY;
        document.documentElement.style.setProperty('--scroll', String(Math.min(y / 600, 1)));

        if (fab && contact && !document.body.classList.contains('site-body--nav-open')) {
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
});

function initMobileNav() {
    const toggle = document.querySelector('[data-nav-toggle]');
    const root = document.querySelector('[data-mobile-nav]');
    const backdrop = document.querySelector('[data-nav-backdrop]');
    const links = document.querySelectorAll('[data-nav-link]');

    if (!(toggle instanceof HTMLButtonElement) || !(root instanceof HTMLElement) || !(backdrop instanceof HTMLButtonElement)) {
        return;
    }

    let scrollY = 0;

    const lockScroll = () => {
        scrollY = window.scrollY;
        document.body.classList.add('site-body--nav-open');
        document.body.style.top = `-${scrollY}px`;
    };

    const unlockScroll = () => {
        document.body.classList.remove('site-body--nav-open');
        document.body.style.top = '';
        window.scrollTo(0, scrollY);
    };

    const close = () => {
        toggle.setAttribute('aria-expanded', 'false');
        root.classList.remove('is-open');
        root.hidden = true;
        root.setAttribute('aria-hidden', 'true');
        unlockScroll();
        toggle.focus({ preventScroll: true });
    };

    const open = () => {
        toggle.setAttribute('aria-expanded', 'true');
        root.hidden = false;
        root.setAttribute('aria-hidden', 'false');
        lockScroll();
        requestAnimationFrame(() => {
            root.classList.add('is-open');
            root.querySelector('[data-nav-link]')?.focus({ preventScroll: true });
        });
    };

    toggle.addEventListener('click', (event) => {
        event.stopPropagation();
        if (toggle.getAttribute('aria-expanded') === 'true') {
            close();
        } else {
            open();
        }
    });

    backdrop.addEventListener('click', close);

    links.forEach((link) => {
        link.addEventListener('click', close);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || toggle.getAttribute('aria-expanded') !== 'true') {
            return;
        }

        event.preventDefault();
        close();
    });
}

function initFormFeedbackReveal() {
    const feedback = document.querySelector('[data-form-feedback]');
    const alert = feedback?.querySelector('.site-alert');

    if (!(feedback instanceof HTMLElement) || !(alert instanceof HTMLElement)) {
        return;
    }

    revealFeedback(feedback);
}

function initInquiryForm() {
    const form = document.querySelector('[data-inquiry-form]');
    const card = document.querySelector('[data-inquiry-card]');
    const loader = document.querySelector('[data-inquiry-loader]');
    const submitButton = form?.querySelector('[data-inquiry-submit]');
    const feedback = form?.querySelector('[data-form-feedback]');

    if (!(form instanceof HTMLFormElement) || !(feedback instanceof HTMLElement)) {
        return;
    }

    initContactField(form);

    const formName = form.dataset.formName || 'inquiry_form';

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (form.dataset.submitting === '1') {
            return;
        }

        setLoading(true);
        clearClientErrors(form);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const contentType = response.headers.get('content-type') || '';
            const data = contentType.includes('application/json')
                ? await response.json()
                : null;

            if (!data || typeof data.ok !== 'boolean') {
                const statusHint = response.status >= 500
                    ? 'Сервер вернул ошибку. Попробуйте чуть позже.'
                    : 'Не удалось отправить заявку. Попробуйте ещё раз.';
                showFeedback(feedback, 'error', statusHint);
                revealFeedback(feedback);
                return;
            }

            if (data.ok) {
                form.reset();
                resetFileUploads(form);
                form.querySelector('[data-contact-type]')?.dispatchEvent(new Event('change'));

                if (data.message) {
                    showFeedback(feedback, 'success', data.message);
                    revealFeedback(feedback);
                } else {
                    clearFeedback(feedback);
                }

                return;
            }

            if (data.message) {
                showFeedback(feedback, 'error', data.message);
                revealFeedback(feedback);
            } else {
                clearFeedback(feedback);
            }

            applyFieldErrors(form, formName, data.errors || {});
            focusFirstInvalid(form);
        } catch {
            showFeedback(feedback, 'error', 'Не удалось отправить заявку. Проверьте соединение и попробуйте ещё раз.');
            revealFeedback(feedback);
        } finally {
            setLoading(false);
        }
    });

    function setLoading(isLoading) {
        form.dataset.submitting = isLoading ? '1' : '0';
        card?.classList.toggle('is-loading', isLoading);

        if (loader instanceof HTMLElement) {
            loader.hidden = !isLoading;
        }

        if (submitButton instanceof HTMLButtonElement) {
            submitButton.disabled = isLoading;
        }
    }
}

function initContactField(form) {
    const typeSelect = form.querySelector('[data-contact-type]');
    const input = form.querySelector('[data-contact-input]');
    const hint = form.querySelector('[data-contact-hint]');

    if (!(typeSelect instanceof HTMLSelectElement) || !(input instanceof HTMLInputElement)) {
        return;
    }

    const modes = {
        phone: {
            placeholder: '+7 (___) ___-__-__',
            inputMode: 'tel',
            autocomplete: 'tel',
            type: 'tel',
            hint: 'Российский номер: мобильный или городской',
        },
        email: {
            placeholder: 'name@example.com',
            inputMode: 'email',
            autocomplete: 'email',
            type: 'text',
            hint: 'Проверьте адрес — ответ придёт на эту почту',
        },
        telegram: {
            placeholder: '@username',
            inputMode: 'text',
            autocomplete: 'off',
            type: 'text',
            hint: 'Имя пользователя, ссылка t.me/... или номер телефона',
        },
        vk: {
            placeholder: 'username или id123456',
            inputMode: 'text',
            autocomplete: 'off',
            type: 'text',
            hint: 'Короткое имя, id или ссылка vk.com/...',
        },
    };

    const applyMode = () => {
        const mode = modes[typeSelect.value] || modes.telegram;

        input.placeholder = mode.placeholder;
        input.inputMode = mode.inputMode;
        input.autocomplete = mode.autocomplete;
        input.type = mode.type;
        input.classList.toggle('form-control--phone', typeSelect.value === 'phone');

        if (hint instanceof HTMLElement) {
            hint.textContent = mode.hint;
            hint.hidden = false;
        }
    };

    const formatRuPhone = (raw) => {
        let digits = raw.replace(/\D/g, '');

        if (digits.startsWith('8')) {
            digits = `7${digits.slice(1)}`;
        }

        if (digits.length === 10 && digits.startsWith('9')) {
            digits = `7${digits}`;
        }

        if (digits.length > 0 && !digits.startsWith('7')) {
            digits = `7${digits.replace(/^7*/, '')}`;
        }

        digits = digits.slice(0, 11);

        if (digits.length === 0) {
            return '';
        }

        if (digits === '7') {
            return '+7 (';
        }

        const rest = digits.slice(1);
        let result = '+7 (';

        if (rest.length > 0) {
            result += rest.slice(0, 3);
        }

        if (rest.length >= 3) {
            result += `) ${rest.slice(3, 6)}`;
        }

        if (rest.length >= 6) {
            result += `-${rest.slice(6, 8)}`;
        }

        if (rest.length >= 8) {
            result += `-${rest.slice(8, 10)}`;
        }

        return result;
    };

    const handlePhoneInput = () => {
        if (typeSelect.value !== 'phone') {
            return;
        }

        const formatted = formatRuPhone(input.value);
        input.value = formatted;
    };

    typeSelect.addEventListener('change', () => {
        applyMode();

        if (typeSelect.value === 'phone' && !input.value.trim()) {
            input.value = '+7 (';
        }
    });

    input.addEventListener('input', handlePhoneInput);

    input.addEventListener('focus', () => {
        if (typeSelect.value === 'phone' && !input.value.trim()) {
            input.value = '+7 (';
        }
    });

    input.addEventListener('blur', () => {
        if (typeSelect.value === 'phone' && (input.value === '+7 (' || input.value === '+7')) {
            input.value = '';
        }

        if (typeSelect.value === 'email' && input.value) {
            input.value = input.value.trim().toLowerCase();
        }
    });

    applyMode();
}

function clearFeedback(feedback) {
    feedback.innerHTML = '';
}

function showFeedback(feedback, type, message) {
    feedback.innerHTML = '';

    const alert = document.createElement('div');
    alert.className = `site-alert site-alert--${type} site-alert--prominent`;
    alert.setAttribute('role', 'alert');
    alert.textContent = message;
    feedback.appendChild(alert);
}

function revealFeedback(feedback) {
    const alert = feedback.querySelector('.site-alert');
    if (!(alert instanceof HTMLElement)) {
        return;
    }

    requestAnimationFrame(() => {
        const header = document.querySelector('.site-header');
        const headerHeight = header instanceof HTMLElement ? header.offsetHeight : 0;
        const bottomGap = 40;
        const rect = alert.getBoundingClientRect();
        const fitsInView = rect.top >= headerHeight + 12 && rect.bottom <= window.innerHeight - bottomGap;

        if (!fitsInView) {
            alert.scrollIntoView({ behavior: 'smooth', block: 'end' });
        }
    });
}

function clearClientErrors(form) {
    form.querySelectorAll('.form-control.is-invalid').forEach((control) => {
        control.classList.remove('is-invalid');
        control.removeAttribute('aria-invalid');
    });

    form.querySelectorAll('.form-consent__checkbox.is-invalid').forEach((control) => {
        control.classList.remove('is-invalid');
        control.removeAttribute('aria-invalid');
    });

    form.querySelectorAll('.form-field__client-errors').forEach((node) => {
        node.remove();
    });
}

function applyFieldErrors(form, formName, errors) {
    Object.entries(errors).forEach(([field, messages]) => {
        if (field === '_form' || !Array.isArray(messages) || messages.length === 0) {
            return;
        }

        const control = form.querySelector(`#${formName}_${field}, [name="${formName}[${field}]"]`);
        if (!(control instanceof HTMLElement)) {
            return;
        }

        control.classList.add('is-invalid');
        control.setAttribute('aria-invalid', 'true');

        const fieldWrap = control.closest('.form-field, .form-consent');
        if (!(fieldWrap instanceof HTMLElement)) {
            return;
        }

        const errorEl = document.createElement('div');
        errorEl.className = 'form-field__client-errors';
        errorEl.dataset.fieldErrors = field;
        errorEl.textContent = messages.join(' ');
        fieldWrap.appendChild(errorEl);
    });
}

function focusFirstInvalid(form) {
    const invalid = form.querySelector('.is-invalid');
    if (invalid instanceof HTMLElement) {
        invalid.focus({ preventScroll: true });
    }
}

function resetFileUploads(form) {
    form.querySelectorAll('[data-file-upload]').forEach((zone) => {
        const input = zone.querySelector('[data-file-input]');
        if (input instanceof HTMLInputElement) {
            input.value = '';
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });
}

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
