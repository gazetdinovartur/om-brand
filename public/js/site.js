document.addEventListener('DOMContentLoaded', () => {
    const header = document.querySelector('.site-header');
    const fab = document.querySelector('.site-fab');
    const heroCta = document.querySelector('[data-hero-cta]');
    const contact = document.querySelector('#contact');

    initMobileNav();
    initFormFeedbackReveal();
    initInquiryForm();
    initFileUploads();
    initPricingAccordion();
    initCapabilitiesMap();
    initProcessPath();
    initCaseLightbox();
    initCopyLink();
    initContentEngage();
    initChronicleFeed();
    initPageBack();
    initChronicleFiltersPersist();

    const updateFabVisibility = () => {
        if (!(fab instanceof HTMLElement) || document.body.classList.contains('site-body--nav-open')) {
            return;
        }

        let showFab = true;

        if (heroCta instanceof HTMLElement) {
            const heroRect = heroCta.getBoundingClientRect();
            const heroCtaVisible = heroRect.bottom > 0 && heroRect.top < window.innerHeight;

            if (heroCtaVisible) {
                showFab = false;
            }
        }

        if (showFab && contact instanceof HTMLElement) {
            const contactRect = contact.getBoundingClientRect();

            if (contactRect.top < window.innerHeight * 0.6) {
                showFab = false;
            }
        }

        fab.classList.toggle('is-visible', showFab);
    };

    const onScroll = () => {
        const y = window.scrollY;
        document.documentElement.style.setProperty('--scroll', String(Math.min(y / 600, 1)));

        updateFabVisibility();

        if (header) {
            header.style.boxShadow = y > 12 ? '0 10px 30px rgba(31, 26, 20, 0.05)' : 'none';
        }
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', updateFabVisibility, { passive: true });
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
            root.querySelector('[data-nav-close]')?.focus({ preventScroll: true });
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

    root.querySelectorAll('[data-nav-close]').forEach((button) => {
        button.addEventListener('click', close);
    });

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

function initProcessPath() {
    const block = document.querySelector('[data-process-path]');
    if (!(block instanceof HTMLElement)) {
        return;
    }

    const orb = block.querySelector('[data-process-path-orb]');
    if (!(orb instanceof HTMLElement)) {
        return;
    }

    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const mobileQuery = window.matchMedia('(max-width: 768px)');

    let animationToken = 0;
    let points = [];
    let steps = [];

    const clearChargedStates = () => {
        block.querySelectorAll('.process-path__step.is-charged').forEach((step) => {
            step.classList.remove('is-charged');
        });
    };

    const setOrb = (x, y, scale, opacity) => {
        orb.style.left = `${x}px`;
        orb.style.top = `${y}px`;
        orb.style.transform = `translate(-50%, -50%) scale(${scale})`;
        orb.style.opacity = String(opacity);
    };

    const syncTracks = () => {
        const isMobile = mobileQuery.matches;
        const horizontalSvg = block.querySelector('.process-path__track--horizontal');
        const verticalSvg = block.querySelector('.process-path__track--vertical');
        const activeSvg = isMobile ? verticalSvg : horizontalSvg;

        if (!(activeSvg instanceof SVGSVGElement)) {
            return;
        }

        const nodes = [...block.querySelectorAll('.process-path__node-shell')];
        steps = nodes.map((node) => node.closest('.process-path__step')).filter((step) => step instanceof HTMLElement);

        if (nodes.length < 2) {
            return;
        }

        const blockRect = block.getBoundingClientRect();
        const width = Math.max(1, Math.round(blockRect.width));
        const height = Math.max(1, Math.round(blockRect.height));

        points = nodes.map((node) => {
            const rect = node.getBoundingClientRect();

            return {
                x: rect.left + rect.width / 2 - blockRect.left,
                y: rect.top + rect.height / 2 - blockRect.top,
            };
        });

        const pathData = buildStraightPath(points);

        activeSvg.setAttribute('viewBox', `0 0 ${width} ${height}`);
        activeSvg.setAttribute('preserveAspectRatio', 'none');

        activeSvg.querySelectorAll('[data-process-path-base], [data-process-path-draw]').forEach((path) => {
            if (path instanceof SVGPathElement) {
                path.setAttribute('d', pathData);
            }
        });

        if (block.classList.contains('is-animating')) {
            restartAnimation();
        }
    };

    const restartAnimation = () => {
        animationToken += 1;
        clearChargedStates();
        setOrb(points[0]?.x ?? 0, points[0]?.y ?? 0, 0, 0);

        if (!prefersReducedMotion && block.classList.contains('is-visible') && points.length > 1) {
            block.classList.add('is-animating');
            runAnimationLoop(animationToken);
        }
    };

    const wait = (ms, token) => new Promise((resolve) => {
        window.setTimeout(() => {
            resolve(token === animationToken);
        }, ms);
    });

    const tweenOrb = (from, to, duration, token) => new Promise((resolve) => {
        const start = performance.now();

        const frame = (now) => {
            if (token !== animationToken) {
                resolve(false);
                return;
            }

            const progress = Math.min(1, (now - start) / duration);
            const eased = 1 - (1 - progress) ** 3;

            setOrb(
                from.x + (to.x - from.x) * eased,
                from.y + (to.y - from.y) * eased,
                from.scale + (to.scale - from.scale) * eased,
                from.opacity + (to.opacity - from.opacity) * eased,
            );

            if (progress < 1) {
                requestAnimationFrame(frame);
                return;
            }

            resolve(true);
        };

        requestAnimationFrame(frame);
    });

    const chargeNode = async (index, token, isLast = false, fromHidden = false) => {
        const step = steps[index];
        const point = points[index];

        if (!(step instanceof HTMLElement) || !point) {
            return false;
        }

        if (fromHidden) {
            setOrb(point.x, point.y, 0.2, 0);

            const appeared = await tweenOrb(
                { x: point.x, y: point.y, scale: 0.2, opacity: 0 },
                { x: point.x, y: point.y, scale: 1, opacity: 1 },
                280,
                token,
            );

            if (!appeared) {
                return false;
            }
        }

        const ignited = await tweenOrb(
            { x: point.x, y: point.y, scale: 1, opacity: 1 },
            { x: point.x, y: point.y, scale: 2.4, opacity: 1 },
            200,
            token,
        );

        if (!ignited) {
            return false;
        }

        const vanished = await tweenOrb(
            { x: point.x, y: point.y, scale: 2.4, opacity: 1 },
            { x: point.x, y: point.y, scale: 0.2, opacity: 0 },
            220,
            token,
        );

        if (!vanished) {
            return false;
        }

        step.classList.add('is-charged');
        const dwellMs = isLast ? 1050 : 760;
        const stillActive = await wait(dwellMs, token);

        if (!stillActive) {
            return false;
        }

        step.classList.remove('is-charged');
        const settled = await wait(480, token);

        if (!settled) {
            return false;
        }

        const emerged = await tweenOrb(
            { x: point.x, y: point.y, scale: 0.25, opacity: 0 },
            { x: point.x, y: point.y, scale: 1, opacity: 1 },
            300,
            token,
        );

        return emerged;
    };

    const runAnimationLoop = async (token) => {
        if (points.length < 2) {
            return;
        }

        while (token === animationToken && block.classList.contains('is-visible')) {
            const booted = await chargeNode(0, token, false, true);
            if (!booted) {
                return;
            }

            for (let index = 0; index < points.length - 1; index += 1) {
                const from = points[index];
                const to = points[index + 1];
                const distance = Math.hypot(to.x - from.x, to.y - from.y);
                const duration = Math.max(520, Math.min(920, distance * 1.35));

                const traveled = await tweenOrb(
                    { x: from.x, y: from.y, scale: 1, opacity: 1 },
                    { x: to.x, y: to.y, scale: 1, opacity: 1 },
                    duration,
                    token,
                );

                if (!traveled) {
                    return;
                }

                const charged = await chargeNode(index + 1, token, index + 1 === points.length - 1);
                if (!charged) {
                    return;
                }
            }

            setOrb(points[0].x, points[0].y, 0.2, 0);
            const cycled = await wait(360, token);

            if (!cycled) {
                return;
            }
        }
    };

    const revealIfNeeded = () => {
        if (prefersReducedMotion) {
            block.classList.add('is-visible');
            return;
        }

        block.classList.add('is-awaiting');

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (!entry.isIntersecting) {
                    return;
                }

                block.classList.add('is-visible');
                observer.disconnect();

                window.setTimeout(() => {
                    restartAnimation();
                }, 1400);
            },
            { threshold: 0.2, rootMargin: '0px 0px -8% 0px' },
        );

        observer.observe(block);
    };

    syncTracks();
    revealIfNeeded();

    requestAnimationFrame(() => {
        syncTracks();
        requestAnimationFrame(syncTracks);
    });

    if (document.fonts?.ready) {
        document.fonts.ready.then(syncTracks).catch(() => {});
    }

    if (typeof ResizeObserver !== 'undefined') {
        const resizeObserver = new ResizeObserver(() => {
            syncTracks();
        });
        resizeObserver.observe(block);
    } else {
        window.addEventListener('resize', syncTracks, { passive: true });
    }

    mobileQuery.addEventListener('change', syncTracks);
}

function buildStraightPath(points) {
    if (points.length === 0) {
        return '';
    }

    let path = `M ${round(points[0].x)} ${round(points[0].y)}`;

    for (let index = 1; index < points.length; index += 1) {
        path += ` L ${round(points[index].x)} ${round(points[index].y)}`;
    }

    return path;
}

function round(value) {
    return Math.round(value * 10) / 10;
}

function initPricingAccordion() {
    const root = document.querySelector('[data-pricing-accordion]');

    initExclusiveDetails(root, '.pricing-accordion__item');
}

function initExclusiveDetails(root, itemSelector) {
    if (!(root instanceof HTMLElement)) {
        return;
    }

    const items = root.querySelectorAll(itemSelector);
    items.forEach((item) => {
        item.addEventListener('toggle', () => {
            if (!item.open) {
                return;
            }

            items.forEach((other) => {
                if (other !== item) {
                    other.open = false;
                }
            });
        });
    });
}

function initCapabilitiesMap() {
    const root = document.querySelector('[data-capabilities-map]');
    if (!(root instanceof HTMLElement)) {
        return;
    }

    initExclusiveDetails(root, '.capabilities-map__details');

    const tabs = Array.from(root.querySelectorAll('[data-capability-tab]'));
    const panels = Array.from(root.querySelectorAll('[data-capability-panel]'));
    if (tabs.length === 0 || panels.length === 0) {
        return;
    }

    root.classList.add('is-enhanced');

    const activate = (index, shouldFocus = false) => {
        tabs.forEach((tab, tabIndex) => {
            const isActive = tabIndex === index;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            tab.setAttribute('tabindex', isActive ? '0' : '-1');
        });

        panels.forEach((panel, panelIndex) => {
            const isActive = panelIndex === index;
            panel.classList.toggle('is-active', isActive);
            panel.hidden = !isActive;
        });

        if (shouldFocus) {
            tabs[index]?.focus({ preventScroll: true });
        }
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activate(index));

        tab.addEventListener('keydown', (event) => {
            const currentIndex = tabs.indexOf(tab);
            let nextIndex = currentIndex;

            if (event.key === 'ArrowDown' || event.key === 'ArrowRight') {
                nextIndex = (currentIndex + 1) % tabs.length;
            } else if (event.key === 'ArrowUp' || event.key === 'ArrowLeft') {
                nextIndex = (currentIndex - 1 + tabs.length) % tabs.length;
            } else if (event.key === 'Home') {
                nextIndex = 0;
            } else if (event.key === 'End') {
                nextIndex = tabs.length - 1;
            } else {
                return;
            }

            event.preventDefault();
            activate(nextIndex, true);
        });
    });

    activate(0);
}

function initCaseLightbox() {
    const dialog = document.querySelector('[data-lightbox]');
    if (!(dialog instanceof HTMLDialogElement)) {
        return;
    }

    const img = dialog.querySelector('[data-lightbox-img]');
    const captionEl = dialog.querySelector('[data-lightbox-caption]');
    const closeBtn = dialog.querySelector('[data-lightbox-close]');
    const prevBtn = dialog.querySelector('[data-lightbox-prev]');
    const nextBtn = dialog.querySelector('[data-lightbox-next]');
    const triggers = [...document.querySelectorAll('[data-lightbox-open]')];

    if (!(img instanceof HTMLImageElement) || triggers.length === 0) {
        return;
    }

    dialog.hidden = false;
    let items = [];
    let index = 0;

    const collectGroup = (trigger) => {
        const gallery = trigger.closest('[data-lightbox-gallery]');
        const scope = gallery ? [...gallery.querySelectorAll('[data-lightbox-open]')] : [trigger];
        return scope.map((btn) => ({
            src: btn.getAttribute('data-lightbox-src') || '',
            alt: btn.getAttribute('data-lightbox-alt') || '',
            caption: btn.getAttribute('data-lightbox-caption') || '',
        })).filter((item) => item.src !== '');
    };

    const render = () => {
        const item = items[index];
        if (!item) {
            return;
        }
        img.src = item.src;
        img.alt = item.alt;
        if (captionEl instanceof HTMLElement) {
            captionEl.textContent = item.caption;
            captionEl.hidden = item.caption === '';
        }
        const multi = items.length > 1;
        if (prevBtn instanceof HTMLButtonElement) {
            prevBtn.hidden = !multi;
        }
        if (nextBtn instanceof HTMLButtonElement) {
            nextBtn.hidden = !multi;
        }
    };

    const openAt = (i) => {
        if (items.length === 0) {
            return;
        }
        index = (i + items.length) % items.length;
        render();
        if (!dialog.open) {
            dialog.showModal();
        }
    };

    const close = () => {
        if (dialog.open) {
            dialog.close();
        }
    };

    triggers.forEach((btn) => {
        btn.addEventListener('click', () => {
            items = collectGroup(btn);
            const src = btn.getAttribute('data-lightbox-src') || '';
            const start = Math.max(0, items.findIndex((item) => item.src === src));
            openAt(start === -1 ? 0 : start);
        });
    });

    closeBtn?.addEventListener('click', close);
    prevBtn?.addEventListener('click', () => openAt(index - 1));
    nextBtn?.addEventListener('click', () => openAt(index + 1));

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            close();
        }
    });

    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            openAt(index - 1);
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            openAt(index + 1);
        }
    });
}

function initCopyLink() {
    document.querySelectorAll('[data-copy-link]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const path = btn.getAttribute('data-copy-link');
            if (!path) return;
            const url = path.startsWith('http') ? path : `${window.location.origin}${path}`;
            try {
                await navigator.clipboard.writeText(url);
                const label = btn.textContent;
                btn.textContent = 'Скопировано';
                setTimeout(() => { btn.textContent = label; }, 1600);
            } catch {
                prompt('Ссылка:', url);
            }
        });
    });
}

function preferNativeShare() {
    if (typeof navigator.share !== 'function') {
        return false;
    }
    const coarse = window.matchMedia('(pointer: coarse)').matches;
    const narrow = window.matchMedia('(max-width: 900px)').matches;
    const touch = navigator.maxTouchPoints > 0;
    return coarse || (narrow && touch);
}

function ensureToastHost() {
    let host = document.querySelector('[data-toast-host]');
    if (host instanceof HTMLElement) {
        return host;
    }
    host = document.createElement('div');
    host.className = 'site-toast-host';
    host.setAttribute('data-toast-host', '');
    host.setAttribute('aria-live', 'polite');
    document.body.appendChild(host);
    return host;
}

function showToast(message) {
    const host = ensureToastHost();
    const toast = document.createElement('div');
    toast.className = 'site-toast';
    toast.textContent = message;
    host.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('is-visible'));
    window.setTimeout(() => {
        toast.classList.remove('is-visible');
        window.setTimeout(() => toast.remove(), 280);
    }, 2200);
}

async function copyText(text) {
    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(text);
        return;
    }
    const input = document.createElement('textarea');
    input.value = text;
    input.setAttribute('readonly', '');
    input.style.position = 'fixed';
    input.style.opacity = '0';
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    input.remove();
}

function formatLikeCount(count) {
    const n = Number(count) || 0;
    return n > 0 ? String(n) : '';
}

function initContentEngage() {
    document.querySelectorAll('[data-engage]').forEach((root) => {
        if (!(root instanceof HTMLElement)) {
            return;
        }

        const likeBtn = root.querySelector('[data-engage-like]');
        const shareBtn = root.querySelector('[data-engage-share]');
        const countEl = root.querySelector('[data-engage-count]');
        const shareLabel = root.querySelector('[data-engage-share-label]');

        const applyLikeState = (liked, count) => {
            if (likeBtn instanceof HTMLButtonElement) {
                likeBtn.classList.toggle('is-liked', liked);
                likeBtn.setAttribute('aria-pressed', liked ? 'true' : 'false');
            }
            root.dataset.liked = liked ? '1' : '0';
            if (countEl instanceof HTMLElement) {
                countEl.textContent = formatLikeCount(count);
                countEl.hidden = !(Number(count) > 0);
            }
        };

        if (countEl instanceof HTMLElement) {
            const initial = Number(countEl.textContent || '0');
            countEl.hidden = !(initial > 0);
            if (initial > 0) {
                countEl.textContent = String(initial);
            }
        }

        likeBtn?.addEventListener('click', async () => {
            if (!(likeBtn instanceof HTMLButtonElement) || likeBtn.disabled) {
                return;
            }
            const url = root.dataset.likeUrl;
            const csrf = root.dataset.csrf;
            if (!url || !csrf) {
                return;
            }

            likeBtn.disabled = true;
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                if (!response.ok) {
                    throw new Error('like failed');
                }
                const data = await response.json();
                applyLikeState(Boolean(data.liked), Number(data.count) || 0);
            } catch {
                showToast('Не удалось сохранить лайк');
            } finally {
                likeBtn.disabled = false;
            }
        });

        shareBtn?.addEventListener('click', async () => {
            const url = root.dataset.shareUrl || window.location.href;
            const title = root.dataset.shareTitle || document.title;
            const label = shareLabel instanceof HTMLElement ? shareLabel.textContent : null;

            if (preferNativeShare()) {
                try {
                    await navigator.share({ title, url, text: title });
                    return;
                } catch (error) {
                    if (error && typeof error === 'object' && 'name' in error && error.name === 'AbortError') {
                        return;
                    }
                    // fall through to clipboard
                }
            }

            try {
                await copyText(url);
                if (shareLabel instanceof HTMLElement) {
                    shareLabel.textContent = 'Скопировано';
                    window.setTimeout(() => {
                        shareLabel.textContent = label || 'Поделиться';
                    }, 1600);
                }
                showToast('Ссылка скопирована');
            } catch {
                prompt('Ссылка:', url);
            }
        });
    });
}

function initChronicleFeed() {
    const feed = document.querySelector('[data-chronicle-feed]');
    const moreBtn = document.querySelector('[data-chronicle-more]');
    const moreWrap = document.querySelector('.chronicle-hub__more');
    const filters = document.querySelector('[data-chronicle-filters]');
    const heartBtn = document.querySelector('[data-chronicle-heart]');
    const featuredInput = document.querySelector('[data-featured-input]');
    const filtersToggle = document.querySelector('[data-chronicle-filters-toggle]');
    const filtersPanel = document.querySelector('[data-chronicle-filters-panel]');

    if (filtersToggle instanceof HTMLButtonElement && filtersPanel instanceof HTMLElement) {
        const syncToggle = () => {
            const open = filtersPanel.classList.contains('is-open');
            filtersToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        };
        syncToggle();
        filtersToggle.addEventListener('click', () => {
            filtersPanel.classList.toggle('is-open');
            syncToggle();
        });
    }

    document.querySelector('[data-chronicle-filters-reset]')?.addEventListener('click', () => {
        try {
            sessionStorage.removeItem('chronicle.filters');
        } catch {
            // ignore
        }
    });

    if (heartBtn instanceof HTMLButtonElement && filters instanceof HTMLFormElement) {
        heartBtn.addEventListener('click', () => {
            const next = heartBtn.getAttribute('aria-pressed') !== 'true';
            heartBtn.classList.toggle('is-active', next);
            heartBtn.setAttribute('aria-pressed', next ? 'true' : 'false');
            if (featuredInput instanceof HTMLInputElement) {
                featuredInput.disabled = !next;
            }
            const year = filters.querySelector('select[name="year"]');
            if (year instanceof HTMLSelectElement && !year.value) {
                year.disabled = true;
            }
            filters.submit();
        });
    }

    if (filters instanceof HTMLFormElement) {
        filters.querySelectorAll('[data-chronicle-auto]').forEach((el) => {
            el.addEventListener('change', () => {
                const year = filters.querySelector('select[name="year"]');
                if (year instanceof HTMLSelectElement && !year.value) {
                    year.disabled = true;
                }
                if (featuredInput instanceof HTMLInputElement && featuredInput.disabled) {
                    // keep disabled
                }
                filters.submit();
            });
        });
    }

    if (!(feed instanceof HTMLElement) || !(moreBtn instanceof HTMLButtonElement)) {
        return;
    }

    let nextOffset = Number(feed.dataset.nextOffset || '0');
    let hasMore = feed.dataset.hasMore === '1';
    let loading = false;

    const setHasMore = (value) => {
        hasMore = value;
        feed.dataset.hasMore = value ? '1' : '0';
        if (!value) {
            moreWrap?.remove();
            return;
        }
        if (moreWrap instanceof HTMLElement) {
            moreWrap.hidden = false;
        }
    };

    if (!hasMore) {
        moreWrap?.remove();
        return;
    }

    moreBtn.addEventListener('click', async () => {
        if (loading || !hasMore) {
            return;
        }
        loading = true;
        moreBtn.disabled = true;
        moreBtn.textContent = 'Загрузка…';

        try {
            let query = {};
            try {
                query = JSON.parse(feed.dataset.query || '{}') || {};
            } catch {
                query = {};
            }
            const params = new URLSearchParams();
            Object.entries(query).forEach(([key, value]) => {
                if (value !== null && value !== undefined && value !== '') {
                    params.set(key, String(value));
                }
            });
            params.set('offset', String(nextOffset));

            const url = `${feed.dataset.moreUrl}?${params.toString()}`;
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) {
                throw new Error('more failed');
            }
            const data = await response.json();
            if (typeof data.html === 'string' && data.html.trim() !== '') {
                feed.insertAdjacentHTML('beforeend', data.html);
            }
            nextOffset = Number(data.nextOffset || nextOffset);
            feed.dataset.nextOffset = String(nextOffset);
            setHasMore(Boolean(data.hasMore));
        } catch {
            moreBtn.textContent = 'Не удалось загрузить';
            setTimeout(() => {
                moreBtn.textContent = 'Смотреть ещё';
            }, 1800);
        } finally {
            loading = false;
            moreBtn.disabled = false;
            if (hasMore) {
                moreBtn.textContent = 'Смотреть ещё';
            }
        }
    });
}

const CHRONICLE_FILTERS_KEY = 'chronicle.filters';

function readChronicleFilters() {
    try {
        const raw = sessionStorage.getItem(CHRONICLE_FILTERS_KEY);
        if (!raw) {
            return {};
        }
        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' ? parsed : {};
    } catch {
        return {};
    }
}

function writeChronicleFilters(query) {
    try {
        sessionStorage.setItem(CHRONICLE_FILTERS_KEY, JSON.stringify(query || {}));
    } catch {
        // ignore quota / private mode
    }
}

function chronicleHubUrl(basePath) {
    const query = readChronicleFilters();
    const params = new URLSearchParams();
    Object.entries(query).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            params.set(key, String(value));
        }
    });
    const suffix = params.toString();
    return suffix ? `${basePath}?${suffix}` : basePath;
}

function initChronicleFiltersPersist() {
    const feed = document.querySelector('[data-chronicle-feed]');
    if (feed instanceof HTMLElement) {
        try {
            writeChronicleFilters(JSON.parse(feed.dataset.query || '{}') || {});
        } catch {
            writeChronicleFilters({});
        }
    }

    document.querySelectorAll('[data-chronicle-hub-link]').forEach((link) => {
        if (!(link instanceof HTMLAnchorElement)) {
            return;
        }
        const base = link.dataset.chronicleHubBase || '/chronicle';
        const url = chronicleHubUrl(base);
        link.href = url;
        if (link.hasAttribute('data-back-fallback')) {
            link.dataset.backFallback = url;
        }
    });
}

function initPageBack() {
    document.querySelectorAll('[data-page-back]').forEach((link) => {
        if (!(link instanceof HTMLAnchorElement)) {
            return;
        }

        link.addEventListener('click', (event) => {
            if (window.history.length > 1) {
                event.preventDefault();
                window.history.back();
            }
        });
    });
}
