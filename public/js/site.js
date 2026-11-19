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
    initNotifySubscribe();
    initChronicleFeed();
    initPageBack();
    initChronicleFiltersPersist();
    initLibraryReader();

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

// Restore smooth scroll after the browser finishes the initial #hash jump.
window.addEventListener('load', () => {
    requestAnimationFrame(() => {
        document.documentElement.classList.remove('js-hash-landing');
    });
});

// bfcache already restored DOM + scroll; drop resume intent so hub links don't re-jump.
window.addEventListener('pageshow', (event) => {
    if (!event.persisted) {
        return;
    }
    const state = readChronicleListState();
    if (state?.focusSlug) {
        writeChronicleListState({ focusSlug: null, scrollY: window.scrollY });
    }
});

function initMobileNav() {
    const toggles = Array.from(document.querySelectorAll('[data-nav-toggle]')).filter(
        (el) => el instanceof HTMLButtonElement,
    );
    const root = document.querySelector('[data-mobile-nav]');
    const backdrop = document.querySelector('[data-nav-backdrop]');
    const links = document.querySelectorAll('[data-nav-link]');

    if (toggles.length === 0 || !(root instanceof HTMLElement) || !(backdrop instanceof HTMLButtonElement)) {
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

    const setExpanded = (expanded) => {
        toggles.forEach((toggle) => {
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        });
    };

    const close = () => {
        setExpanded(false);
        root.classList.remove('is-open');
        root.hidden = true;
        root.setAttribute('aria-hidden', 'true');
        unlockScroll();
        toggles[0]?.focus({ preventScroll: true });
    };

    const open = () => {
        setExpanded(true);
        root.hidden = false;
        root.setAttribute('aria-hidden', 'false');
        lockScroll();
        requestAnimationFrame(() => {
            root.classList.add('is-open');
            root.querySelector('[data-nav-close]')?.focus({ preventScroll: true });
        });
    };

    toggles.forEach((toggle) => {
        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            if (root.classList.contains('is-open')) {
                close();
            } else {
                open();
            }
        });
    });

    backdrop.addEventListener('click', close);

    root.querySelectorAll('[data-nav-close]').forEach((button) => {
        button.addEventListener('click', close);
    });

    links.forEach((link) => {
        link.addEventListener('click', close);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !root.classList.contains('is-open')) {
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
    const inner = dialog.querySelector('.case-lightbox__inner');
    const stage = dialog.querySelector('[data-lightbox-stage]');
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
    let scrollY = 0;
    let touchStartX = 0;
    let touchStartY = 0;
    let touchActive = false;
    let suppressClick = false;
    let scrollGuardTimer = 0;
    let focusSink = document.getElementById('lightbox-focus-sink');

    const getFocusSink = () => {
        if (focusSink instanceof HTMLElement) {
            return focusSink;
        }
        focusSink = document.createElement('div');
        focusSink.id = 'lightbox-focus-sink';
        focusSink.className = 'visually-hidden';
        focusSink.tabIndex = -1;
        focusSink.setAttribute('aria-hidden', 'true');
        document.body.appendChild(focusSink);
        return focusSink;
    };

    const focusWithoutScroll = (element) => {
        if (element instanceof HTMLElement) {
            element.focus({ preventScroll: true });
        }
    };

    const restoreScroll = (y) => {
        document.documentElement.scrollTop = y;
        document.body.scrollTop = y;
        window.scrollTo({ top: y, left: 0, behavior: 'auto' });
    };

    const clearScrollGuard = () => {
        window.clearTimeout(scrollGuardTimer);
        scrollGuardTimer = 0;
    };

    const armScrollGuard = (y) => {
        clearScrollGuard();
        const startedAt = Date.now();
        const guard = () => {
            if (Math.abs(window.scrollY - y) > 1) {
                restoreScroll(y);
            }
            if (Date.now() - startedAt < 600) {
                window.requestAnimationFrame(guard);
            }
        };
        window.requestAnimationFrame(guard);
        scrollGuardTimer = window.setTimeout(clearScrollGuard, 650);
    };

    const lockScroll = () => {
        scrollY = window.scrollY || document.documentElement.scrollTop || 0;
        document.documentElement.classList.add('is-lightbox-open');
        document.body.classList.add('is-lightbox-open');
    };

    const unlockScroll = () => {
        document.documentElement.classList.remove('is-lightbox-open');
        document.body.classList.remove('is-lightbox-open');
    };

    const finalizeClose = () => {
        const y = scrollY;
        unlockScroll();
        restoreScroll(y);
        focusWithoutScroll(getFocusSink());
        armScrollGuard(y);
    };

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
            prevBtn.disabled = !multi;
        }
        if (nextBtn instanceof HTMLButtonElement) {
            nextBtn.hidden = !multi;
            nextBtn.disabled = !multi;
        }
    };

    const openAt = (i) => {
        if (items.length === 0) {
            return;
        }
        index = (i + items.length) % items.length;
        render();
        if (!dialog.open) {
            lockScroll();
            focusWithoutScroll(getFocusSink());
            dialog.showModal();
        }
    };

    const close = () => {
        if (!dialog.open) {
            return;
        }
        focusWithoutScroll(getFocusSink());
        dialog.close();
    };

    triggers.forEach((btn) => {
        // Keep focus off the trigger so dialog close won't scroll the page to the photo.
        btn.addEventListener('mousedown', (event) => {
            event.preventDefault();
        });
        btn.addEventListener('click', () => {
            items = collectGroup(btn);
            const src = btn.getAttribute('data-lightbox-src') || '';
            const start = Math.max(0, items.findIndex((item) => item.src === src));
            openAt(start === -1 ? 0 : start);
        });
    });

    closeBtn?.addEventListener('mousedown', (event) => event.preventDefault());
    closeBtn?.addEventListener('click', close);
    prevBtn?.addEventListener('click', () => openAt(index - 1));
    nextBtn?.addEventListener('click', () => openAt(index + 1));

    dialog.addEventListener('cancel', () => {
        focusWithoutScroll(getFocusSink());
    });
    dialog.addEventListener('close', finalizeClose);

    dialog.addEventListener('click', (event) => {
        if (suppressClick) {
            suppressClick = false;
            return;
        }
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        if (target.closest('[data-lightbox-close], [data-lightbox-prev], [data-lightbox-next]')) {
            return;
        }
        // Full-bleed dialog: dimmed chrome is __inner / empty stage, not ::backdrop.
        if (
            target === dialog
            || target === inner
            || target === stage
            || target === captionEl
        ) {
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

    const onTouchStart = (event) => {
        if (items.length < 2 || event.touches.length !== 1) {
            touchActive = false;
            return;
        }
        touchActive = true;
        touchStartX = event.touches[0].clientX;
        touchStartY = event.touches[0].clientY;
    };

    const onTouchMove = (event) => {
        if (!touchActive || event.touches.length !== 1) {
            return;
        }
        const dx = event.touches[0].clientX - touchStartX;
        const dy = event.touches[0].clientY - touchStartY;
        if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 8) {
            event.preventDefault();
        }
    };

    const onTouchEnd = (event) => {
        if (!touchActive) {
            return;
        }
        touchActive = false;
        const touch = event.changedTouches[0];
        if (!touch) {
            return;
        }
        const dx = touch.clientX - touchStartX;
        const dy = touch.clientY - touchStartY;
        // Any intentional drag should not also close via the synthesized click.
        if (Math.abs(dx) > 12 || Math.abs(dy) > 12) {
            suppressClick = true;
        }
        if (items.length < 2 || Math.abs(dx) < 48 || Math.abs(dx) < Math.abs(dy) * 1.2) {
            return;
        }
        if (dx < 0) {
            openAt(index + 1);
        } else {
            openAt(index - 1);
        }
    };

    const swipeTarget = stage instanceof HTMLElement ? stage : dialog;
    swipeTarget.addEventListener('touchstart', onTouchStart, { passive: true });
    swipeTarget.addEventListener('touchmove', onTouchMove, { passive: false });
    swipeTarget.addEventListener('touchend', onTouchEnd, { passive: true });
    swipeTarget.addEventListener('touchcancel', () => { touchActive = false; }, { passive: true });
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

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    const output = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i += 1) {
        output[i] = raw.charCodeAt(i);
    }
    return output;
}

function isIosSafari() {
    const ua = window.navigator.userAgent || '';
    const iOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const webkit = /WebKit/.test(ua);
    const chrome = /CriOS|FxiOS|EdgiOS/.test(ua);
    return iOS && webkit && !chrome;
}

function isStandaloneDisplay() {
    return window.matchMedia('(display-mode: standalone)').matches
        || ('standalone' in navigator && Boolean(navigator.standalone));
}

async function registerNotifyServiceWorker(swUrl) {
    if (!('serviceWorker' in navigator)) {
        return null;
    }
    try {
        return await navigator.serviceWorker.register(swUrl, { scope: '/' });
    } catch {
        return null;
    }
}

async function getPushSubscription() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        return null;
    }
    const registration = await navigator.serviceWorker.ready;
    return registration.pushManager.getSubscription();
}

function initNotifySubscribe() {
    const configEl = document.querySelector('[data-notify-config]');
    const dialog = document.querySelector('[data-notify-dialog]');
    if (!(configEl instanceof HTMLElement) || !(dialog instanceof HTMLDialogElement)) {
        return;
    }

    const csrf = configEl.dataset.csrf || '';
    const vapidPublic = configEl.dataset.vapidPublic || '';
    const pushSubscribeUrl = configEl.dataset.pushSubscribe || '';
    const pushUnsubscribeUrl = configEl.dataset.pushUnsubscribe || '';
    const emailSubscribeUrl = configEl.dataset.emailSubscribe || '';
    const swUrl = configEl.dataset.swUrl || '/sw.js';

    const pushToggle = dialog.querySelector('[data-notify-push-toggle]');
    const pushHint = dialog.querySelector('[data-notify-push-hint]');
    const pushBlocked = dialog.querySelector('[data-notify-push-blocked]');
    const iosHint = dialog.querySelector('[data-notify-ios-hint]');
    const pushSection = dialog.querySelector('[data-notify-push-section]');
    const emailForm = dialog.querySelector('[data-notify-email-form]');
    const emailInput = dialog.querySelector('[data-notify-email-input]');
    const emailStatus = dialog.querySelector('[data-notify-email-status]');
    const closeBtn = dialog.querySelector('[data-notify-close]');

    const pushSupported = 'Notification' in window
        && 'serviceWorker' in navigator
        && 'PushManager' in window
        && Boolean(vapidPublic);

    const syncBellState = (active) => {
        document.querySelectorAll('[data-notify-bell]').forEach((btn) => {
            if (btn instanceof HTMLElement) {
                btn.classList.toggle('is-active', active);
                btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            }
        });
    };

    const setPushUi = ({ state, label }) => {
        if (pushToggle instanceof HTMLButtonElement) {
            pushToggle.textContent = label;
            pushToggle.disabled = state === 'unsupported' || state === 'blocked' || state === 'busy';
            pushToggle.setAttribute('aria-pressed', state === 'on' ? 'true' : 'false');
            pushToggle.classList.toggle('is-on', state === 'on');
        }
        if (pushBlocked instanceof HTMLElement) {
            pushBlocked.hidden = state !== 'blocked';
        }
        if (pushHint instanceof HTMLElement) {
            if (state === 'unsupported') {
                pushHint.textContent = 'В этом браузере пуш недоступен — используйте email или RSS';
            } else if (state === 'on') {
                pushHint.textContent = 'Включено на этом устройстве';
            } else {
                pushHint.textContent = 'Короткий пуш, когда выходит новая запись';
            }
        }
        syncBellState(state === 'on');
    };

    const refreshPushState = async () => {
        if (iosHint instanceof HTMLElement) {
            iosHint.hidden = !(isIosSafari() && !isStandaloneDisplay());
        }

        if (!pushSupported) {
            setPushUi({ state: 'unsupported', label: 'Недоступно' });
            return;
        }

        if (Notification.permission === 'denied') {
            setPushUi({ state: 'blocked', label: 'Заблокировано' });
            return;
        }

        await registerNotifyServiceWorker(swUrl);
        const sub = await getPushSubscription();
        if (sub) {
            setPushUi({ state: 'on', label: 'Выключить' });
        } else {
            setPushUi({ state: 'off', label: 'Включить' });
        }
    };

    const postJson = async (url, body) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok) {
            throw new Error((data && data.message) || 'request failed');
        }
        return data;
    };

    const enablePush = async () => {
        setPushUi({ state: 'busy', label: '…' });
        try {
            const registration = await registerNotifyServiceWorker(swUrl);
            if (!registration) {
                throw new Error('sw');
            }
            await navigator.serviceWorker.ready;
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                if (permission === 'denied') {
                    setPushUi({ state: 'blocked', label: 'Заблокировано' });
                } else {
                    setPushUi({ state: 'off', label: 'Включить' });
                }
                return;
            }
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidPublic),
            });
            await postJson(pushSubscribeUrl, subscription.toJSON());
            setPushUi({ state: 'on', label: 'Выключить' });
            showToast('Уведомления включены');
        } catch {
            setPushUi({ state: 'off', label: 'Включить' });
            showToast('Не удалось включить уведомления');
        }
    };

    const disablePush = async () => {
        setPushUi({ state: 'busy', label: '…' });
        try {
            const subscription = await getPushSubscription();
            if (subscription) {
                await postJson(pushUnsubscribeUrl, { endpoint: subscription.endpoint });
                await subscription.unsubscribe();
            }
            setPushUi({ state: 'off', label: 'Включить' });
            showToast('Уведомления выключены');
        } catch {
            await refreshPushState();
            showToast('Не удалось выключить уведомления');
        }
    };

    document.querySelectorAll('[data-notify-bell]').forEach((btn) => {
        btn.addEventListener('click', async (event) => {
            event.preventDefault();
            await refreshPushState();
            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
            } else {
                dialog.setAttribute('open', '');
            }
        });
    });

    closeBtn?.addEventListener('click', () => {
        if (typeof dialog.close === 'function') {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }
    });

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });

    pushToggle?.addEventListener('click', async () => {
        if (!(pushToggle instanceof HTMLButtonElement) || pushToggle.disabled) {
            return;
        }
        if (pushToggle.getAttribute('aria-pressed') === 'true') {
            await disablePush();
        } else {
            await enablePush();
        }
    });

    emailForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!(emailInput instanceof HTMLInputElement) || !(emailStatus instanceof HTMLElement)) {
            return;
        }
        const email = emailInput.value.trim();
        if (!email) {
            return;
        }
        emailStatus.hidden = false;
        emailStatus.textContent = 'Отправляем…';
        emailStatus.classList.remove('is-error');
        try {
            const data = await postJson(emailSubscribeUrl, { email });
            emailStatus.textContent = data.message || 'Проверьте почту — подтвердите подписку.';
            emailInput.value = '';
        } catch (error) {
            emailStatus.classList.add('is-error');
            emailStatus.textContent = error instanceof Error && error.message !== 'request failed'
                ? error.message
                : 'Не удалось подписаться. Попробуйте позже.';
        }
    });

    if (pushSupported) {
        registerNotifyServiceWorker(swUrl).then(() => refreshPushState());
    } else {
        setPushUi({ state: 'unsupported', label: 'Недоступно' });
        if (pushSection instanceof HTMLElement && !vapidPublic) {
            /* keep section visible: email/RSS still useful; push button says unavailable */
        }
    }
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
    const filters = document.querySelector('[data-chronicle-filters]');
    const heartBtn = document.querySelector('[data-chronicle-heart]');
    const likedInput = document.querySelector('[data-liked-input]');
    const filtersToggle = document.querySelector('[data-chronicle-filters-toggle]');
    const filtersPanel = document.querySelector('[data-chronicle-filters-panel]');
    const searchToggle = document.querySelector('[data-chronicle-search-toggle]');
    const searchModal = document.querySelector('[data-chronicle-search-modal]');
    const searchInput = document.querySelector('[data-chronicle-search-input]');
    const searchHidden = document.querySelector('[data-chronicle-search-hidden]');
    const searchClose = document.querySelector('[data-chronicle-search-close]');

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

    const prepareFilterSubmit = () => {
        if (!(filters instanceof HTMLFormElement)) {
            return;
        }
        const year = filters.querySelector('select[name="year"]');
        if (year instanceof HTMLSelectElement && !year.value) {
            year.disabled = true;
        }
        if (searchHidden instanceof HTMLInputElement) {
            const q = searchHidden.value.trim();
            if (q) {
                searchHidden.disabled = false;
                searchHidden.value = q;
            } else {
                searchHidden.disabled = true;
            }
        }
    };

    const submitFilters = () => {
        if (!(filters instanceof HTMLFormElement)) {
            return;
        }
        clearChronicleListState();
        prepareFilterSubmit();
        filters.submit();
    };

    if (
        searchToggle instanceof HTMLButtonElement
        && searchModal instanceof HTMLDialogElement
        && searchInput instanceof HTMLInputElement
    ) {
        const searchUrl = searchModal.getAttribute('data-search-url') || '/chronicle/search';
        const resultsEl = searchModal.querySelector('[data-chronicle-search-results]');
        const statusEl = searchModal.querySelector('[data-chronicle-search-status]');
        const applyLink = searchModal.querySelector('[data-chronicle-search-apply]');
        let searchTimer = 0;
        let searchAbort = null;
        let requestSeq = 0;
        let activeHit = -1;

        const setStatus = (text, loading = false) => {
            if (!(statusEl instanceof HTMLElement)) {
                return;
            }
            statusEl.textContent = text;
            statusEl.classList.toggle('is-loading', loading);
        };

        const setApply = (href, total) => {
            if (!(applyLink instanceof HTMLAnchorElement)) {
                return;
            }
            if (!href || !total) {
                applyLink.hidden = true;
                return;
            }
            applyLink.href = href;
            applyLink.textContent =
                total === 1 ? 'Открыть в ленте' : `Показать все ${total} в ленте`;
            applyLink.hidden = false;
        };

        const clearResults = () => {
            if (resultsEl instanceof HTMLElement) {
                resultsEl.innerHTML = '';
            }
            activeHit = -1;
            setApply('', 0);
        };

        const hitItems = () =>
            resultsEl instanceof HTMLElement
                ? [...resultsEl.querySelectorAll('[data-chronicle-search-hit]')]
                : [];

        const highlightHit = (index) => {
            const items = hitItems();
            items.forEach((el, i) => {
                const row = el.closest('.chronicle-search-modal__item');
                row?.classList.toggle('is-active', i === index);
            });
            activeHit = index;
            if (index >= 0 && items[index] instanceof HTMLElement) {
                items[index].scrollIntoView({ block: 'nearest' });
            }
        };

        const currentFilterParams = () => {
            const params = new URLSearchParams();
            if (!(filters instanceof HTMLFormElement)) {
                return params;
            }
            const year = filters.querySelector('select[name="year"]');
            if (year instanceof HTMLSelectElement && year.value) {
                params.set('year', year.value);
            }
            ['series', 'era', 'tag'].forEach((name) => {
                const input = filters.querySelector(`input[name="${name}"]`);
                if (input instanceof HTMLInputElement && input.value) {
                    params.set(name, input.value);
                }
            });
            if (likedInput instanceof HTMLInputElement && !likedInput.disabled) {
                params.set('liked', '1');
            }
            return params;
        };

        const fetchResults = async (rawQuery) => {
            const q = rawQuery.trim();
            if (q.length === 1) {
                setStatus('Ещё одна буква…');
                clearResults();
                return;
            }
            if (q.length < 2) {
                setStatus('По заголовкам, лидам и тексту записей');
                clearResults();
                return;
            }

            if (searchAbort) {
                searchAbort.abort();
            }
            searchAbort = new AbortController();
            const seq = ++requestSeq;
            setStatus('Ищем…', true);

            const params = currentFilterParams();
            params.set('q', q);
            try {
                const response = await fetch(`${searchUrl}?${params.toString()}`, {
                    headers: { Accept: 'application/json' },
                    signal: searchAbort.signal,
                    credentials: 'same-origin',
                });
                if (!response.ok) {
                    throw new Error('search failed');
                }
                const data = await response.json();
                if (seq !== requestSeq) {
                    return;
                }
                if (data.status === 'idle') {
                    setStatus('По заголовкам, лидам и тексту записей');
                    clearResults();
                    return;
                }
                if (resultsEl instanceof HTMLElement) {
                    resultsEl.innerHTML = data.html || '';
                }
                activeHit = -1;
                if (data.status === 'empty') {
                    setStatus(`Ничего по «${data.q}»`);
                    setApply('', 0);
                    return;
                }
                const shown = Number(data.count) || 0;
                const total = Number(data.total) || 0;
                setStatus(total === shown ? `${total} найдено` : `${shown} из ${total}`);
                setApply(data.feedUrl || '', total);
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }
                if (seq !== requestSeq) {
                    return;
                }
                setStatus('Не удалось найти — попробуйте ещё раз');
                clearResults();
            }
        };

        const scheduleSearch = (immediate = false) => {
            window.clearTimeout(searchTimer);
            const run = () => {
                fetchResults(searchInput.value);
            };
            if (immediate) {
                run();
                return;
            }
            searchTimer = window.setTimeout(run, 280);
        };

        const openSearch = () => {
            if (searchHidden instanceof HTMLInputElement && !searchHidden.disabled && searchHidden.value) {
                searchInput.value = searchHidden.value;
            }
            if (!searchModal.open) {
                searchModal.showModal();
            }
            requestAnimationFrame(() => {
                searchInput.focus();
                searchInput.select();
                scheduleSearch(true);
            });
        };

        const closeSearch = () => {
            window.clearTimeout(searchTimer);
            if (searchAbort) {
                searchAbort.abort();
            }
            if (searchModal.open) {
                searchModal.close();
            }
        };

        searchToggle.addEventListener('click', openSearch);
        searchClose?.addEventListener('click', closeSearch);
        searchModal.addEventListener('click', (event) => {
            if (event.target === searchModal) {
                closeSearch();
            }
        });
        applyLink?.addEventListener('click', () => {
            clearChronicleFilters();
            clearChronicleListState();
        });
        searchInput.addEventListener('input', () => scheduleSearch(false));
        searchInput.addEventListener('keydown', (event) => {
            const items = hitItems();
            if (event.key === 'ArrowDown' && items.length) {
                event.preventDefault();
                highlightHit(Math.min(items.length - 1, activeHit + 1));
                return;
            }
            if (event.key === 'ArrowUp' && items.length) {
                event.preventDefault();
                highlightHit(Math.max(0, activeHit <= 0 ? 0 : activeHit - 1));
                return;
            }
            if (event.key === 'Enter') {
                event.preventDefault();
                if (activeHit >= 0 && items[activeHit] instanceof HTMLAnchorElement) {
                    items[activeHit].click();
                    return;
                }
                if (applyLink instanceof HTMLAnchorElement && !applyLink.hidden && applyLink.href) {
                    clearChronicleListState();
                    window.location.href = applyLink.href;
                    return;
                }
                scheduleSearch(true);
            }
        });
    }

    document.querySelector('[data-chronicle-filters-reset]')?.addEventListener('click', () => {
        clearChronicleFilters();
        clearChronicleListState();
    });

    if (filters instanceof HTMLFormElement) {
        filters.addEventListener('submit', () => {
            clearChronicleListState();
            prepareFilterSubmit();
        });
        filters.querySelectorAll('a[href]').forEach((link) => {
            link.addEventListener('click', () => {
                clearChronicleListState();
            });
        });
    }

    if (heartBtn instanceof HTMLButtonElement && filters instanceof HTMLFormElement) {
        heartBtn.addEventListener('click', () => {
            const next = heartBtn.getAttribute('aria-pressed') !== 'true';
            heartBtn.classList.toggle('is-active', next);
            heartBtn.setAttribute('aria-pressed', next ? 'true' : 'false');
            if (likedInput instanceof HTMLInputElement) {
                likedInput.disabled = !next;
            }
            submitFilters();
        });
    }

    if (filters instanceof HTMLFormElement) {
        filters.querySelectorAll('[data-chronicle-auto]').forEach((el) => {
            el.addEventListener('change', () => {
                submitFilters();
            });
        });
    }

    if (!(feed instanceof HTMLElement)) {
        return;
    }

    initChronicleListPosition(feed);
}

const CHRONICLE_FILTERS_KEY = 'chronicle.filters';
const CHRONICLE_LIST_STATE_KEY = 'chronicle.listState';
const CHRONICLE_LIST_STATE_TTL_MS = 45 * 60 * 1000;
const CHRONICLE_RESTORE_MAX_PAGES = 20;

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

function clearChronicleFilters() {
    try {
        sessionStorage.removeItem(CHRONICLE_FILTERS_KEY);
    } catch {
        // ignore
    }
}

function normalizeChronicleFilters(query) {
    const out = {};
    if (!query || typeof query !== 'object') {
        return out;
    }
    Object.keys(query)
        .sort()
        .forEach((key) => {
            const value = query[key];
            if (value !== null && value !== undefined && value !== '') {
                out[key] = String(value);
            }
        });
    return out;
}

function chronicleFiltersEqual(a, b) {
    const left = normalizeChronicleFilters(a);
    const right = normalizeChronicleFilters(b);
    const keys = new Set([...Object.keys(left), ...Object.keys(right)]);
    for (const key of keys) {
        if ((left[key] || '') !== (right[key] || '')) {
            return false;
        }
    }
    return true;
}

function readChronicleListState() {
    try {
        const raw = sessionStorage.getItem(CHRONICLE_LIST_STATE_KEY);
        if (!raw) {
            return null;
        }
        const parsed = JSON.parse(raw);
        if (!parsed || typeof parsed !== 'object') {
            return null;
        }
        if (typeof parsed.ts !== 'number' || Date.now() - parsed.ts > CHRONICLE_LIST_STATE_TTL_MS) {
            clearChronicleListState();
            return null;
        }
        return {
            filters: normalizeChronicleFilters(parsed.filters),
            focusSlug: typeof parsed.focusSlug === 'string' && parsed.focusSlug ? parsed.focusSlug : null,
            nextOffset: Number(parsed.nextOffset) || 0,
            scrollY: Number(parsed.scrollY) || 0,
            ts: parsed.ts,
        };
    } catch {
        return null;
    }
}

function writeChronicleListState(partial) {
    try {
        const current = readChronicleListState() || {
            filters: {},
            focusSlug: null,
            nextOffset: 0,
            scrollY: 0,
            ts: Date.now(),
        };
        const next = {
            filters: normalizeChronicleFilters(
                partial.filters !== undefined ? partial.filters : current.filters,
            ),
            focusSlug:
                partial.focusSlug !== undefined ? partial.focusSlug || null : current.focusSlug,
            nextOffset:
                partial.nextOffset !== undefined
                    ? Number(partial.nextOffset) || 0
                    : current.nextOffset,
            scrollY: partial.scrollY !== undefined ? Number(partial.scrollY) || 0 : current.scrollY,
            ts: Date.now(),
        };
        sessionStorage.setItem(CHRONICLE_LIST_STATE_KEY, JSON.stringify(next));
    } catch {
        // ignore quota / private mode
    }
}

function clearChronicleListState() {
    try {
        sessionStorage.removeItem(CHRONICLE_LIST_STATE_KEY);
    } catch {
        // ignore
    }
}

function chronicleEntryElementId(slug) {
    return `chronicle-${slug}`;
}

function findChronicleEntry(slug) {
    if (!slug) {
        return null;
    }
    const el = document.getElementById(chronicleEntryElementId(slug));
    return el instanceof HTMLElement ? el : null;
}

function parseChronicleFeedQuery(feed) {
    try {
        return normalizeChronicleFilters(JSON.parse(feed.dataset.query || '{}') || {});
    } catch {
        return {};
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
    // No #anchor here: explicit «Хроника» / crumb should open the hub at the top.
    return suffix ? `${basePath}?${suffix}` : basePath;
}

function isBackForwardNavigation() {
    try {
        const entries = performance.getEntriesByType('navigation');
        const nav = entries && entries.length > 0 ? entries[0] : null;
        if (nav && typeof nav.type === 'string') {
            return nav.type === 'back_forward';
        }
    } catch {
        // ignore
    }
    try {
        // Legacy PerformanceNavigation API
        return performance.navigation && performance.navigation.type === 2;
    } catch {
        return false;
    }
}

function isChronicleHubPath(pathname) {
    if (pathname === '/chronicle') {
        return true;
    }
    return /^\/chronicle\/(era|tag|series)\//.test(pathname);
}

function canHistoryBackToChronicleHub() {
    try {
        const ref = document.referrer;
        if (!ref) {
            return false;
        }
        const url = new URL(ref);
        if (url.origin !== window.location.origin) {
            return false;
        }
        return isChronicleHubPath(url.pathname);
    } catch {
        return false;
    }
}

function initChronicleListPosition(feed) {
    const moreBtn = document.querySelector('[data-chronicle-more]');
    const moreWrap = document.querySelector('.chronicle-hub__more');

    let nextOffset = Number(feed.dataset.nextOffset || '0');
    let hasMore = feed.dataset.hasMore === '1';
    let loading = false;
    let restoring = false;

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
    }

    const persistPosition = (extra = {}) => {
        if (restoring) {
            return;
        }
        writeChronicleListState({
            filters: parseChronicleFeedQuery(feed),
            nextOffset,
            scrollY: window.scrollY,
            ...extra,
        });
    };

    const loadMoreOnce = async ({ silent = false } = {}) => {
        if (loading || !hasMore) {
            return false;
        }
        loading = true;
        if (!silent && moreBtn instanceof HTMLButtonElement) {
            moreBtn.disabled = true;
            moreBtn.textContent = 'Загрузка…';
        }

        try {
            const query = parseChronicleFeedQuery(feed);
            const params = new URLSearchParams();
            Object.entries(query).forEach(([key, value]) => {
                params.set(key, String(value));
            });
            params.set('offset', String(nextOffset));

            const moreUrl = feed.dataset.moreUrl || '/chronicle/more';
            const response = await fetch(`${moreUrl}?${params.toString()}`, {
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
            return true;
        } catch {
            if (!silent && moreBtn instanceof HTMLButtonElement) {
                moreBtn.textContent = 'Не удалось загрузить';
                window.setTimeout(() => {
                    if (hasMore && moreBtn instanceof HTMLButtonElement) {
                        moreBtn.textContent = 'Смотреть ещё';
                    }
                }, 1800);
            }
            return false;
        } finally {
            loading = false;
            if (!silent && moreBtn instanceof HTMLButtonElement) {
                moreBtn.disabled = false;
                if (hasMore) {
                    moreBtn.textContent = 'Смотреть ещё';
                }
            }
        }
    };

    feed.addEventListener(
        'click',
        (event) => {
            const target = event.target;
            if (!(target instanceof Element)) {
                return;
            }

            const link = target.closest('a');
            if (!(link instanceof HTMLAnchorElement) || !feed.contains(link)) {
                return;
            }

            const article = link.closest('.chronicle-entry');
            if (!(article instanceof HTMLElement)) {
                return;
            }

            const isPostLink =
                link.classList.contains('chronicle-entry__media') ||
                Boolean(link.closest('.chronicle-entry__title'));

            if (isPostLink) {
                const id = article.id || '';
                const slug = id.startsWith('chronicle-') ? id.slice('chronicle-'.length) : null;
                if (slug) {
                    persistPosition({ focusSlug: slug });
                }
                return;
            }

            // Filter chips on cards open a new hub slice — drop resume state.
            if (
                link.classList.contains('chronicle-series-chip') ||
                link.classList.contains('chronicle-era-chip') ||
                link.classList.contains('chronicle-tag')
            ) {
                clearChronicleListState();
            }
        },
        true,
    );

    let scrollTimer = 0;
    window.addEventListener(
        'scroll',
        () => {
            if (restoring) {
                return;
            }
            window.clearTimeout(scrollTimer);
            scrollTimer = window.setTimeout(() => {
                persistPosition();
            }, 150);
        },
        { passive: true },
    );

    if (moreBtn instanceof HTMLButtonElement) {
        moreBtn.addEventListener('click', async () => {
            const ok = await loadMoreOnce();
            if (ok) {
                persistPosition();
            }
        });
    }

    void restoreChronicleListPosition(feed, {
        getNextOffset: () => nextOffset,
        getHasMore: () => hasMore,
        loadMoreOnce,
        setRestoring: (value) => {
            restoring = value;
        },
        persistPosition,
    });
}

async function restoreChronicleListPosition(feed, ctx) {
    // Only resume after browser / «Назад» history.back — not after clicking «Хроника».
    if (!isBackForwardNavigation()) {
        return;
    }

    const state = readChronicleListState();
    if (!state) {
        return;
    }

    const currentFilters = parseChronicleFeedQuery(feed);
    if (!chronicleFiltersEqual(state.filters, currentFilters)) {
        return;
    }

    if (!state.focusSlug && !(state.nextOffset > Number(feed.dataset.nextOffset || '0')) && !state.scrollY) {
        return;
    }

    ctx.setRestoring(true);
    document.documentElement.classList.add('js-hash-landing');

    try {
        let pages = 0;
        while (pages < CHRONICLE_RESTORE_MAX_PAGES) {
            const found = state.focusSlug ? findChronicleEntry(state.focusSlug) : null;
            if (found) {
                break;
            }
            if (ctx.getNextOffset() >= state.nextOffset && !state.focusSlug) {
                break;
            }
            if (!ctx.getHasMore()) {
                break;
            }
            // Keep loading while focus slug is missing, or until saved offset is reached.
            if (state.focusSlug || ctx.getNextOffset() < state.nextOffset) {
                const ok = await ctx.loadMoreOnce({ silent: true });
                pages += 1;
                if (!ok) {
                    break;
                }
                continue;
            }
            break;
        }

        const entry = state.focusSlug ? findChronicleEntry(state.focusSlug) : null;
        if (entry) {
            entry.scrollIntoView({ block: 'start', behavior: 'auto' });
            entry.classList.add('is-resume');
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            window.setTimeout(
                () => {
                    entry.classList.remove('is-resume');
                },
                reduceMotion ? 0 : 1700,
            );
        } else if (state.scrollY > 0) {
            window.scrollTo({ top: state.scrollY, behavior: 'auto' });
        }

        writeChronicleListState({
            filters: currentFilters,
            focusSlug: null,
            nextOffset: ctx.getNextOffset(),
            scrollY: window.scrollY,
        });
    } finally {
        ctx.setRestoring(false);
        requestAnimationFrame(() => {
            document.documentElement.classList.remove('js-hash-landing');
        });
    }
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

        // Explicit hub navigation (nav / crumb / fallback «Назад»): open at top, drop resume.
        link.addEventListener('click', () => {
            const willHistoryBack =
                link.hasAttribute('data-page-back') &&
                canHistoryBackToChronicleHub() &&
                window.history.length > 1;
            if (willHistoryBack) {
                return;
            }
            clearChronicleListState();
        });
    });
}

function initPageBack() {
    document.querySelectorAll('[data-page-back]').forEach((link) => {
        if (!(link instanceof HTMLAnchorElement)) {
            return;
        }

        link.addEventListener('click', (event) => {
            const isChronicleBack = link.hasAttribute('data-chronicle-hub-link');
            if (isChronicleBack) {
                if (canHistoryBackToChronicleHub() && window.history.length > 1) {
                    event.preventDefault();
                    window.history.back();
                }
                // else follow href (filters only, top of hub — no resume scroll)
                return;
            }

            if (window.history.length > 1) {
                event.preventDefault();
                window.history.back();
            }
        });
    });
}

const READER_PREFERENCES_KEY = 'om.reader.preferences.v1';
const READER_OFFLINE_PREFIX = 'om.reader.offline.v1:';
const READER_PROGRESS_PREFIX = 'om.reader.progress.v1:';

function initLibraryReader() {
    const reader = document.querySelector('[data-reader]');
    if (!(reader instanceof HTMLElement)) {
        return;
    }

    const chrome = document.querySelector('[data-reader-chrome]') || reader;
    const q = (sel) => chrome.querySelector(sel) || reader.querySelector(sel);
    const isPdfFallback = reader.dataset.readerRenderMode === 'pdf_fallback';
    const blocks = Array.from(reader.querySelectorAll('[data-reader-block]')).filter(
        (block) => block instanceof HTMLElement,
    );
    if (!isPdfFallback && blocks.length === 0) {
        return;
    }

    const progressUrl = reader.dataset.readerProgressUrl || '';
    const progressToken = reader.dataset.readerProgressToken || '';
    const bookmarkUrl = reader.dataset.readerBookmarkUrl || '';
    const bookmarkToken = reader.dataset.readerBookmarkToken || '';
    const quoteUrl = reader.dataset.readerQuoteUrl || '';
    const quoteToken = reader.dataset.readerQuoteToken || '';
    const noteUrl = reader.dataset.readerNoteUrl || '';
    const noteToken = reader.dataset.readerNoteToken || '';
    const offlineUrl = reader.dataset.readerOfflineUrl || '';
    const bookSlug = reader.dataset.readerBook || '';
    const chapter = reader.dataset.readerChapter || '';
    const progressLabel = q('[data-reader-progress]');
    const pageLabel = q('[data-reader-page-label]');
    const bookmarkButton = q('[data-reader-bookmark]');
    const quoteButton = q('[data-reader-quote]');
    const noteButton = q('[data-reader-note]');
    const pagePrevButtons = Array.from(document.querySelectorAll('[data-reader-page="prev"]'));
    const pageNextButtons = Array.from(document.querySelectorAll('[data-reader-page="next"]'));
    const offlineButton = q('[data-reader-offline]');
    const saveStatus = reader.querySelector('[data-reader-save-status]');
    const bookmarksList = reader.querySelector('[data-reader-bookmarks-list]');
    const bookmarksEmpty = reader.querySelector('[data-reader-bookmarks-empty]');
    const quotesList = reader.querySelector('[data-reader-quotes-list]');
    const quotesEmpty = reader.querySelector('[data-reader-quotes-empty]');
    const notesList = reader.querySelector('[data-reader-notes-list]');
    const notesEmpty = reader.querySelector('[data-reader-notes-empty]');
    const content = reader.querySelector('[data-reader-content]');
    const progressStorageKey = bookSlug ? `${READER_PROGRESS_PREFIX}${bookSlug}` : '';
    const serverProgress = {
        chapter: reader.dataset.readerServerChapter || '',
        block: reader.dataset.readerServerBlock || '',
        updatedAt: reader.dataset.readerServerUpdatedAt || '',
        progressPercent: Number(reader.dataset.readerServerPercent || 0),
    };
    let activeBlock = blocks[0] || null;
    let saveTimer = 0;
    let statusTimer = 0;
    let heartbeatTimer = 0;
    let lastSavedBlock = '';
    let restoringPosition = false;
    let positionReady = false;
    let pageIndex = 0;
    let pageCount = 1;
    let columnWidth = 0;

    const toast = document.querySelector('[data-reader-toast]');
    const setStatus = (message, keep = false) => {
        window.clearTimeout(statusTimer);
        if (saveStatus instanceof HTMLElement) {
            saveStatus.textContent = message;
        }
        if (toast instanceof HTMLElement) {
            const showToast = Boolean(message);
            toast.hidden = !showToast;
            toast.textContent = message;
            toast.classList.toggle('is-visible', showToast);
        }
        if (!keep && message) {
            statusTimer = window.setTimeout(() => {
                if (saveStatus instanceof HTMLElement) {
                    saveStatus.textContent = '';
                }
                if (toast instanceof HTMLElement) {
                    toast.hidden = true;
                    toast.textContent = '';
                    toast.classList.remove('is-visible');
                }
            }, 2800);
        }
    };

    const selectionInReader = () => {
        const selection = window.getSelection();
        if (!selection || selection.isCollapsed || selection.rangeCount === 0) {
            return null;
        }
        const range = selection.getRangeAt(0);
        const node = range.commonAncestorContainer;
        const host = node instanceof Element ? node : node.parentElement;
        if (!(host instanceof Element)) {
            return null;
        }
        const block = host.closest('[data-reader-block]');
        if (!(block instanceof HTMLElement) || !reader.contains(block)) {
            return null;
        }
        const excerpt = selection.toString().replace(/\s+/g, ' ').trim();
        if (!excerpt) {
            return null;
        }
        const prefix = document.createRange();
        prefix.selectNodeContents(block);
        prefix.setEnd(range.startContainer, range.startOffset);
        const startOffset = prefix.toString().length;
        const endOffset = startOffset + selection.toString().length;

        return { block, excerpt, startOffset, endOffset };
    };

    const updateActionButtons = () => {
        const selected = selectionInReader();
        const targetBlock = selected?.block instanceof HTMLElement
            ? selected.block
            : activeBlock;

        if (bookmarkButton instanceof HTMLButtonElement) {
            if (!(targetBlock instanceof HTMLElement)) {
                bookmarkButton.disabled = true;
            } else {
                const bookmarked = targetBlock.dataset.bookmarked === '1';
                bookmarkButton.disabled = false;
                bookmarkButton.setAttribute('aria-pressed', bookmarked && !selected ? 'true' : 'false');
                bookmarkButton.setAttribute(
                    'aria-label',
                    selected
                        ? 'Закладка на выделенное'
                        : (bookmarked ? 'Убрать закладку' : 'Закладка'),
                );
            }
        }
        if (quoteButton instanceof HTMLButtonElement) {
            // Цитата — только по выделению (на мобиле свайп больше не мешает).
            quoteButton.disabled = isPdfFallback || !selected;
            quoteButton.setAttribute(
                'aria-label',
                selected ? 'Сохранить выделенное как цитату' : 'Выделите текст для цитаты',
            );
        }
        if (noteButton instanceof HTMLButtonElement) {
            noteButton.disabled = !(targetBlock instanceof HTMLElement) || isPdfFallback;
        }
    };

    const readProgressDraft = () => {
        if (!progressStorageKey) {
            return null;
        }
        try {
            const raw = localStorage.getItem(progressStorageKey);
            if (!raw) {
                return null;
            }
            const draft = JSON.parse(raw);
            if (!draft || typeof draft !== 'object' || !draft.chapter || !draft.block || !draft.updatedAt) {
                return null;
            }
            return draft;
        } catch {
            return null;
        }
    };

    const writeProgressDraft = ({
        blockId = '',
        progressPercent = null,
        synced = false,
        updatedAt = null,
    } = {}) => {
        if (!progressStorageKey || !blockId || !chapter) {
            return;
        }
        const previous = readProgressDraft();
        const percent = typeof progressPercent === 'number'
            ? progressPercent
            : (typeof previous?.progressPercent === 'number' ? previous.progressPercent : serverProgress.progressPercent);
        try {
            localStorage.setItem(progressStorageKey, JSON.stringify({
                chapter,
                block: blockId,
                updatedAt: updatedAt || new Date().toISOString(),
                progressPercent: percent,
                synced: Boolean(synced),
            }));
        } catch {
            // localStorage может быть недоступен — серверный слой всё равно работает.
        }
    };

    const draftIsFresher = (draft, server) => {
        if (!draft?.updatedAt) {
            return false;
        }
        if (!server?.updatedAt) {
            return true;
        }
        const draftMs = Date.parse(draft.updatedAt);
        const serverMs = Date.parse(server.updatedAt);
        if (!Number.isFinite(draftMs)) {
            return false;
        }
        if (!Number.isFinite(serverMs) || draftMs > serverMs) {
            return true;
        }
        if (draftMs === serverMs) {
            const draftPercent = Number(draft.progressPercent || 0);
            const serverPercent = Number(server.progressPercent || 0);
            return draftPercent > serverPercent;
        }
        return false;
    };

    const selectActiveBlock = (block, force = false) => {
        if (!(block instanceof HTMLElement) || (!force && block === activeBlock)) {
            return;
        }
        activeBlock = block;
        updateActionButtons();
        if (!restoringPosition && positionReady) {
            writeProgressDraft({ blockId: block.id, synced: false });
            scheduleProgressSave();
        }
    };

    const saveProgress = async ({ keepalive = false, quiet = false, force = false, block = null } = {}) => {
        const target = block instanceof HTMLElement ? block : activeBlock;
        const blockId = target instanceof HTMLElement ? target.id : '';
        if (!blockId || !progressUrl || !progressToken || (!force && blockId === lastSavedBlock)) {
            return;
        }
        // Не затираем сервер «началом главы», пока реальная позиция ещё не готова.
        if (!force && !positionReady) {
            return;
        }

        writeProgressDraft({ blockId, synced: false });

        try {
            const response = await fetch(progressUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                keepalive,
                body: JSON.stringify({
                    _token: progressToken,
                    chapter,
                    block: blockId,
                }),
            });
            const data = await response.json();
            if (!response.ok || !data?.ok) {
                throw new Error(data?.message || 'save failed');
            }
            lastSavedBlock = blockId;
            if (progressLabel instanceof HTMLElement && typeof data.progressPercent === 'number') {
                progressLabel.textContent = `${Math.round(data.progressPercent)}%`;
            }
            if (data.savedAt) {
                serverProgress.updatedAt = data.savedAt;
                serverProgress.chapter = chapter;
                serverProgress.block = blockId;
                if (typeof data.progressPercent === 'number') {
                    serverProgress.progressPercent = data.progressPercent;
                }
            }
            writeProgressDraft({
                blockId,
                progressPercent: typeof data.progressPercent === 'number' ? data.progressPercent : null,
                synced: true,
                updatedAt: data.savedAt || serverProgress.updatedAt || new Date().toISOString(),
            });
            if (!quiet) {
                setStatus('Место сохранено');
            }
        } catch {
            if (!quiet) {
                setStatus('Не удалось сохранить место чтения', true);
            }
        }
    };

    const scheduleProgressSave = () => {
        if (!positionReady || restoringPosition) {
            return;
        }
        window.clearTimeout(saveTimer);
        saveTimer = window.setTimeout(() => {
            // Автосохранение без тоста — иначе на каждой смене абзаца мелькает «Место сохранено».
            void saveProgress({ quiet: true });
        }, 700);
    };

    const flushProgressNow = (opts = {}) => {
        window.clearTimeout(saveTimer);
        void saveProgress({ keepalive: true, quiet: true, force: true, ...opts });
    };

    const armProgressHeartbeat = () => {
        window.clearInterval(heartbeatTimer);
        heartbeatTimer = window.setInterval(() => {
            if (document.visibilityState !== 'visible' || restoringPosition || !positionReady) {
                return;
            }
            flushProgressNow({ keepalive: false });
        }, 50000);
    };

    /** Горизонтальный старт блока в координатах колонок (не bounding box — он лжёт при column-break). */
    const blockColumnOffset = (el) => {
        if (!(el instanceof Element) || !(content instanceof HTMLElement)) {
            return 0;
        }
        const contentRect = content.getBoundingClientRect();
        const range = document.createRange();
        range.selectNodeContents(el);
        range.collapse(true);
        const rects = range.getClientRects();
        const rect = rects.length > 0 ? rects[0] : el.getBoundingClientRect();
        return rect.left - contentRect.left + content.scrollLeft;
    };

    /**
     * Якорь чтения на текущей «странице» (колонке).
     * Нельзя использовать element.getBoundingClientRect(): у абзаца с column-break
     * box тянется через несколько страниц и всегда «виден» — activeBlock залипает на первом блоке.
     */
    const readingAnchorBlock = () => {
        if (!(content instanceof HTMLElement) || blocks.length === 0) {
            return blocks[0] || null;
        }
        if (reader.dataset.readerFlow !== 'paginated' || columnWidth <= 0) {
            const targetY = window.innerHeight * 0.3;
            let closest = blocks[0];
            let closestDistance = Number.POSITIVE_INFINITY;
            blocks.forEach((block) => {
                const rect = block.getBoundingClientRect();
                if (rect.bottom < 0 || rect.top > window.innerHeight) {
                    return;
                }
                const distance = Math.abs(rect.top - targetY);
                if (distance < closestDistance) {
                    closestDistance = distance;
                    closest = block;
                }
            });
            return closest;
        }

        const pageLeft = pageIndex * columnWidth;
        const pageRight = pageLeft + columnWidth;
        let continuing = blocks[0];
        for (const block of blocks) {
            const start = blockColumnOffset(block);
            if (start < pageLeft - 0.5) {
                continuing = block;
                continue;
            }
            if (start < pageRight - 0.5) {
                return block;
            }
            break;
        }
        return continuing;
    };

    const syncActiveFromViewport = ({ persist = true } = {}) => {
        if (restoringPosition || isPdfFallback) {
            return activeBlock;
        }
        if (reader.dataset.readerFlow === 'paginated') {
            lockPageScroll();
        }
        const block = readingAnchorBlock();
        if (!(block instanceof HTMLElement)) {
            return activeBlock;
        }
        if (!persist) {
            activeBlock = block;
            updateActionButtons();
            return block;
        }
        selectActiveBlock(block);
        return activeBlock;
    };

    const isReaderSelection = () => {
        const selection = window.getSelection();
        if (!selection || selection.isCollapsed || selection.rangeCount === 0) {
            return false;
        }
        const node = selection.anchorNode;
        const host = node instanceof Element ? node : node?.parentElement;
        return host instanceof Element && reader.contains(host);
    };

    let selectionActive = false;
    let selectionLockRaf = 0;
    let touchIsDown = false;
    let touchSawSelection = false;

    const lockPageScroll = () => {
        if (!(content instanceof HTMLElement) || reader.dataset.readerFlow !== 'paginated' || columnWidth <= 0) {
            return;
        }
        const expected = pageIndex * columnWidth;
        if (Math.abs(content.scrollLeft - expected) > 0.5) {
            content.scrollLeft = expected;
        }
    };

    const syncSelectionLock = () => {
        selectionActive = isReaderSelection();
        if (!selectionActive) {
            return;
        }
        lockPageScroll();
        if (selectionLockRaf) {
            return;
        }
        const tick = () => {
            if (!selectionActive) {
                selectionLockRaf = 0;
                return;
            }
            lockPageScroll();
            selectionLockRaf = window.requestAnimationFrame(tick);
        };
        selectionLockRaf = window.requestAnimationFrame(tick);
    };

    const currentFromViewport = () => {
        if (selectionActive) {
            return;
        }
        syncActiveFromViewport();
    };

    const updatePageUi = () => {
        const paginated = reader.dataset.readerFlow === 'paginated' && !isPdfFallback;
        pagePrevButtons.forEach((btn) => {
            if (!(btn instanceof HTMLButtonElement)) {
                return;
            }
            btn.hidden = !paginated;
            btn.disabled = pageIndex <= 0;
        });
        pageNextButtons.forEach((btn) => {
            if (!(btn instanceof HTMLButtonElement)) {
                return;
            }
            btn.hidden = !paginated;
            btn.disabled = pageIndex >= pageCount - 1;
        });
        if (pageLabel instanceof HTMLElement) {
            pageLabel.hidden = !paginated;
            pageLabel.textContent = paginated ? `${pageIndex + 1} / ${pageCount}` : '';
        }
    };

    const syncPaginatedViewport = () => {
        const paginated = reader.dataset.readerFlow === 'paginated' && !isPdfFallback;
        document.body.classList.toggle('is-reader-paginated', paginated);
        const header = document.querySelector('.site-header--reader');
        if (header instanceof HTMLElement && paginated) {
            const height = Math.ceil(header.getBoundingClientRect().height);
            document.documentElement.style.setProperty('--reader-chrome-height', `${height}px`);
        } else {
            document.documentElement.style.removeProperty('--reader-chrome-height');
        }
        if (paginated) {
            window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
        }
    };

    const measurePages = () => {
        syncPaginatedViewport();
        if (!(content instanceof HTMLElement) || reader.dataset.readerFlow !== 'paginated' || isPdfFallback) {
            pageCount = 1;
            pageIndex = 0;
            if (content instanceof HTMLElement) {
                content.style.columnWidth = '';
                content.style.height = '';
                content.scrollLeft = 0;
            }
            updatePageUi();
            return;
        }
        // Сначала сбрасываем явные размеры, чтобы взять фактический flex-бокс.
        content.style.columnWidth = '';
        content.style.height = '';
        void content.offsetHeight;
        const width = Math.max(1, Math.floor(content.getBoundingClientRect().width));
        const height = Math.max(1, Math.floor(content.getBoundingClientRect().height));
        content.style.height = `${height}px`;
        content.style.columnWidth = `${width}px`;
        content.style.columnGap = '0px';
        columnWidth = width;
        void content.offsetHeight;
        const scrollWidth = content.scrollWidth;
        pageCount = Math.max(1, Math.round(scrollWidth / width));
        pageIndex = Math.min(pageIndex, pageCount - 1);
        content.scrollLeft = pageIndex * columnWidth;
        updatePageUi();
        if (!restoringPosition) {
            currentFromViewport();
        }
    };

    const goToPage = (index, { syncActive = true } = {}) => {
        if (!(content instanceof HTMLElement)) {
            return;
        }
        window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
        pageIndex = Math.max(0, Math.min(pageCount - 1, index));
        content.scrollLeft = pageIndex * columnWidth;
        updatePageUi();
        // После scrollLeft client rects колонок стабилизируются на следующем кадре.
        window.requestAnimationFrame(() => {
            lockPageScroll();
            if (syncActive && !restoringPosition) {
                currentFromViewport();
            }
        });
    };

    const pageIndexForElement = (el) => {
        if (!(el instanceof Element) || !(content instanceof HTMLElement) || columnWidth <= 0) {
            return 0;
        }
        const left = blockColumnOffset(el);
        return Math.max(0, Math.min(pageCount - 1, Math.floor((left + 0.5) / columnWidth)));
    };

    const jumpToElement = (el) => {
        if (!(el instanceof Element)) {
            return;
        }
        const block = el.closest('[data-reader-block]');
        if (reader.dataset.readerFlow === 'paginated' && content instanceof HTMLElement) {
            measurePages();
            // Без auto-sync: иначе rAF перебьёт целевой блок якорем «первый на странице».
            goToPage(pageIndexForElement(el), { syncActive: false });
            if (block instanceof HTMLElement) {
                selectActiveBlock(block, true);
            }
        } else if (el instanceof HTMLElement) {
            if (block instanceof HTMLElement) {
                selectActiveBlock(block, true);
            }
            el.scrollIntoView({ block: 'center', inline: 'nearest' });
        }
    };

    const jumpToBlock = (block) => {
        if (!(block instanceof HTMLElement)) {
            return;
        }
        jumpToElement(block);
    };

    // Лента: IntersectionObserver по вертикали. Страницы: якорь только через goToPage + readingAnchorBlock
    // (IO по Y в колонках бесполезен и раньше цеплялся к первому «видимому» bounding box).
    if (!isPdfFallback) {
        window.addEventListener('scroll', currentFromViewport, { passive: true });
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver(
                () => {
                    if (reader.dataset.readerFlow === 'paginated') {
                        return;
                    }
                    currentFromViewport();
                },
                { rootMargin: '-18% 0px -58% 0px', threshold: [0, 0.25, 0.75] },
            );
            blocks.forEach((block) => observer.observe(block));
        }
    }

    updateActionButtons();

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            syncActiveFromViewport({ persist: false });
            flushProgressNow();
        } else if (document.visibilityState === 'visible') {
            armProgressHeartbeat();
        }
    });
    // pagehide надёжнее visibilitychange на iOS при свайпе «закрыть вкладку» / уходе в фон.
    window.addEventListener('pagehide', () => {
        syncActiveFromViewport({ persist: false });
        flushProgressNow();
    });
    document.addEventListener('freeze', () => {
        syncActiveFromViewport({ persist: false });
        flushProgressNow();
    });

    // Перед уходом в другую главу — сразу зафиксировать место.
    reader.querySelectorAll('.reader-toc a, .reader-chapter-nav a').forEach((link) => {
        if (!(link instanceof HTMLAnchorElement)) {
            return;
        }
        link.addEventListener('click', () => {
            syncActiveFromViewport({ persist: false });
            flushProgressNow({ keepalive: true });
        });
    });

    const postJson = async (url, token, payload) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                _token: token,
                ...payload,
            }),
        });
        const data = await response.json();
        if (!response.ok || !data?.ok) {
            throw new Error(data?.message || 'request failed');
        }
        return data;
    };

    const syncEmpty = (emptyEl, listEl, itemSel) => {
        if (!(emptyEl instanceof HTMLElement) || !(listEl instanceof HTMLOListElement)) {
            return;
        }
        emptyEl.hidden = listEl.querySelector(itemSel) !== null;
    };

    const appendListItem = (listEl, emptyEl, itemSel, data, kind, offsets = null) => {
        if (!(listEl instanceof HTMLOListElement) || !data.block) {
            return;
        }
        const item = document.createElement('li');
        item.className = 'reader-nav-item';
        item.dataset.chapter = chapter;
        item.dataset.block = data.block;
        const idValue = kind === 'bookmark'
            ? data.bookmarkId
            : (kind === 'quote' ? data.quoteId : data.noteId);
        if (kind === 'bookmark') {
            item.dataset.readerBookmarkItem = '';
            item.dataset.bookmarkId = String(idValue);
        } else if (kind === 'quote') {
            item.dataset.readerQuoteItem = '';
            item.dataset.quoteId = String(idValue);
        } else {
            item.dataset.readerNoteItem = '';
            item.dataset.noteId = String(idValue);
        }

        const jump = document.createElement('button');
        jump.type = 'button';
        jump.className = 'reader-nav-item__jump';
        jump.dataset.readerJump = '';
        jump.dataset.jumpKind = kind;
        jump.dataset.jumpId = String(idValue || '');
        jump.dataset.jumpBlock = data.block;
        jump.dataset.jumpChapter = chapter;
        jump.dataset.jumpExcerpt = data.excerpt || data.body || '';
        if (offsets) {
            jump.dataset.startOffset = String(offsets.startOffset);
            jump.dataset.endOffset = String(offsets.endOffset);
        }
        jump.dataset.jumpHref = `#${encodeURIComponent(data.block)}`;

        const body = document.createElement('span');
        body.className = 'reader-nav-item__body';
        body.textContent = data.excerpt || data.body || '';
        const place = document.createElement('span');
        place.className = 'reader-nav-item__place';
        place.textContent = reader.querySelector('.reader-head h1')?.textContent?.trim() || 'К месту';
        jump.append(body, place);

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'reader-nav-item__remove';
        removeButton.textContent = 'Удалить';
        if (kind === 'bookmark') {
            removeButton.dataset.readerBookmarkRemove = '';
            removeButton.dataset.bookmarkId = String(idValue);
        } else if (kind === 'quote') {
            removeButton.dataset.readerQuoteRemove = '';
            removeButton.dataset.quoteId = String(idValue);
        } else {
            removeButton.dataset.readerNoteRemove = '';
            removeButton.dataset.noteId = String(idValue);
        }
        item.append(jump, removeButton);
        listEl.prepend(item);
        syncEmpty(emptyEl, listEl, itemSel);
    };

    const marksHostFor = (blockId) => {
        const host = reader.querySelector(`[data-reader-marks][data-block="${CSS.escape(blockId)}"]`);
        return host instanceof HTMLElement ? host : null;
    };

    const refreshMarksVisibility = (host) => {
        if (!(host instanceof HTMLElement)) {
            return;
        }
        host.hidden = host.querySelector('[data-reader-mark]') === null;
    };

    const unwrapQuoteHighlight = (block, quoteId = null) => {
        if (!(block instanceof HTMLElement)) {
            return;
        }
        block.querySelectorAll('mark.reader-inline-quote').forEach((mark) => {
            if (!(mark instanceof HTMLElement)) {
                return;
            }
            if (quoteId !== null && mark.dataset.quoteId !== String(quoteId)) {
                return;
            }
            const parent = mark.parentNode;
            if (!(parent instanceof Node)) {
                return;
            }
            while (mark.firstChild) {
                parent.insertBefore(mark.firstChild, mark);
            }
            parent.removeChild(mark);
            if (parent instanceof Element) {
                parent.normalize();
            }
        });
    };

    const wrapQuoteHighlight = (block, startOffset, endOffset, quoteId) => {
        if (!(block instanceof HTMLElement) || endOffset <= startOffset) {
            return;
        }
        unwrapQuoteHighlight(block, quoteId);
        const walker = document.createTreeWalker(block, NodeFilter.SHOW_TEXT);
        let pos = 0;
        /** @type {{ node: Text, sliceStart: number, sliceEnd: number }[]} */
        const slices = [];
        while (walker.nextNode()) {
            const node = walker.currentNode;
            if (!(node instanceof Text)) {
                continue;
            }
            const len = node.data.length;
            const nodeStart = pos;
            const nodeEnd = pos + len;
            pos = nodeEnd;
            if (nodeEnd <= startOffset || nodeStart >= endOffset) {
                continue;
            }
            slices.push({
                node,
                sliceStart: Math.max(0, startOffset - nodeStart),
                sliceEnd: Math.min(len, endOffset - nodeStart),
            });
        }
        for (let i = slices.length - 1; i >= 0; i -= 1) {
            const { node, sliceStart, sliceEnd } = slices[i];
            if (sliceEnd <= sliceStart || !node.parentNode) {
                continue;
            }
            const text = node.data;
            const mark = document.createElement('mark');
            mark.className = 'reader-inline-quote';
            mark.dataset.quoteId = String(quoteId);
            mark.textContent = text.slice(sliceStart, sliceEnd);
            const frag = document.createDocumentFragment();
            if (sliceStart > 0) {
                frag.append(document.createTextNode(text.slice(0, sliceStart)));
            }
            frag.append(mark);
            if (sliceEnd < text.length) {
                frag.append(document.createTextNode(text.slice(sliceEnd)));
            }
            node.parentNode.replaceChild(frag, node);
        }
    };

    const applyQuoteHighlights = () => {
        blocks.forEach((block) => {
            if (!(block instanceof HTMLElement)) {
                return;
            }
            unwrapQuoteHighlight(block);
            const host = marksHostFor(block.id);
            if (!(host instanceof HTMLElement)) {
                return;
            }
            /** @type {{ id: string, start: number, end: number }[]} */
            const quotes = [];
            host.querySelectorAll('[data-reader-mark="quote"]').forEach((el) => {
                if (!(el instanceof HTMLElement)) {
                    return;
                }
                const id = el.dataset.quoteId || '';
                const start = Number(el.dataset.startOffset || 0);
                const end = Number(el.dataset.endOffset || 0);
                if (!id || !Number.isFinite(start) || !Number.isFinite(end) || end <= start) {
                    // Старые цитаты без оффсетов — подсветим по тексту excerpt, если найдётся.
                    const excerpt = el.querySelector('.reader-mark__text')?.textContent?.trim() || '';
                    if (!id || !excerpt) {
                        return;
                    }
                    const haystack = block.textContent || '';
                    const at = haystack.indexOf(excerpt);
                    if (at >= 0) {
                        quotes.push({ id, start: at, end: at + excerpt.length });
                    }
                    return;
                }
                quotes.push({ id, start, end });
            });
            quotes.sort((a, b) => b.start - a.start);
            quotes.forEach((quote) => {
                wrapQuoteHighlight(block, quote.start, quote.end, quote.id);
            });
            block.dataset.quoted = quotes.length ? '1' : '0';
        });
    };

    const upsertInlineMark = (blockId, kind, text, id = '', offsets = null) => {
        const host = marksHostFor(blockId);
        if (!(host instanceof HTMLElement)) {
            return;
        }
        let mark = null;
        if (kind === 'bookmark') {
            mark = host.querySelector('[data-reader-mark="bookmark"]');
        } else if (kind === 'quote' && id) {
            mark = host.querySelector(`[data-reader-mark="quote"][data-quote-id="${CSS.escape(String(id))}"]`);
        } else if (kind === 'note' && id) {
            mark = host.querySelector(`[data-reader-mark="note"][data-note-id="${CSS.escape(String(id))}"]`);
        }
        if (!(mark instanceof HTMLElement)) {
            mark = document.createElement('div');
            mark.className = `reader-mark reader-mark--${kind}`;
            mark.dataset.readerMark = kind;
            if (kind === 'bookmark' && id) {
                mark.dataset.bookmarkId = String(id);
            }
            if (kind === 'quote' && id) {
                mark.dataset.quoteId = String(id);
            }
            if (kind === 'note' && id) {
                mark.dataset.noteId = String(id);
            }
            const head = document.createElement('div');
            head.className = 'reader-mark__head';
            const label = document.createElement('span');
            label.className = 'reader-mark__label';
            label.textContent = kind === 'bookmark' ? 'Закладка' : (kind === 'quote' ? 'Цитата' : 'Заметка');
            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'reader-mark__remove';
            removeButton.textContent = 'Удалить';
            if (kind === 'bookmark') {
                removeButton.dataset.readerBookmarkRemove = '';
                removeButton.dataset.bookmarkId = String(id);
            } else if (kind === 'quote') {
                removeButton.dataset.readerQuoteRemove = '';
                removeButton.dataset.quoteId = String(id);
            } else {
                removeButton.dataset.readerNoteRemove = '';
                removeButton.dataset.noteId = String(id);
            }
            head.append(label, removeButton);
            mark.append(head);
            if (kind !== 'bookmark') {
                const body = document.createElement('p');
                body.className = 'reader-mark__text';
                body.textContent = text || '';
                mark.append(body);
            }
            if (text) {
                mark.dataset.jumpExcerpt = text;
            }
            host.append(mark);
        } else if (kind !== 'bookmark') {
            const body = mark.querySelector('.reader-mark__text');
            if (body instanceof HTMLElement) {
                body.textContent = text || '';
            }
            if (text) {
                mark.dataset.jumpExcerpt = text;
            }
        }
        if (kind === 'quote' && mark instanceof HTMLElement && offsets) {
            mark.dataset.startOffset = String(offsets.startOffset);
            mark.dataset.endOffset = String(offsets.endOffset);
        }
        refreshMarksVisibility(host);
    };

    const removeInlineMark = (blockId, kind, id = '') => {
        const host = marksHostFor(blockId);
        if (!(host instanceof HTMLElement)) {
            return;
        }
        let mark = null;
        if (kind === 'bookmark') {
            mark = host.querySelector('[data-reader-mark="bookmark"]');
        } else if (kind === 'quote' && id) {
            mark = host.querySelector(`[data-reader-mark="quote"][data-quote-id="${CSS.escape(String(id))}"]`);
        } else if (kind === 'note' && id) {
            mark = host.querySelector(`[data-reader-mark="note"][data-note-id="${CSS.escape(String(id))}"]`);
        }
        mark?.remove();
        refreshMarksVisibility(host);
    };

    if (bookmarkButton instanceof HTMLButtonElement && bookmarkUrl && bookmarkToken) {
        bookmarkButton.addEventListener('click', async () => {
            const selected = selectionInReader();
            // Без выделения — якорь с текущей страницы/viewport, не устаревший activeBlock.
            const target = selected?.block instanceof HTMLElement
                ? selected.block
                : syncActiveFromViewport({ persist: false });
            if (!(target instanceof HTMLElement)) {
                return;
            }
            bookmarkButton.disabled = true;
            try {
                const data = await postJson(bookmarkUrl, bookmarkToken, {
                    chapter,
                    block: target.id,
                });
                target.dataset.bookmarked = data.bookmarked ? '1' : '0';
                target.classList.toggle('is-bookmarked', Boolean(data.bookmarked));
                selectActiveBlock(target, true);
                if (data.bookmarked) {
                    appendListItem(bookmarksList, bookmarksEmpty, '[data-reader-bookmark-item]', data, 'bookmark');
                    upsertInlineMark(target.id, 'bookmark', data.excerpt || '', data.bookmarkId);
                    // Сервер уже сохранил прогресс вместе с закладкой — синхронизируем UI/draft.
                    lastSavedBlock = target.id;
                    if (progressLabel instanceof HTMLElement && typeof data.progressPercent === 'number') {
                        progressLabel.textContent = `${Math.round(data.progressPercent)}%`;
                    }
                    if (data.savedAt) {
                        serverProgress.updatedAt = data.savedAt;
                        serverProgress.chapter = chapter;
                        serverProgress.block = target.id;
                        if (typeof data.progressPercent === 'number') {
                            serverProgress.progressPercent = data.progressPercent;
                        }
                    }
                    writeProgressDraft({
                        blockId: target.id,
                        progressPercent: typeof data.progressPercent === 'number' ? data.progressPercent : null,
                        synced: true,
                        updatedAt: data.savedAt || new Date().toISOString(),
                    });
                    window.clearTimeout(saveTimer);
                    setStatus('Закладка добавлена');
                } else {
                    bookmarksList?.querySelector(`[data-reader-bookmark-item][data-block="${CSS.escape(target.id)}"]`)?.remove();
                    syncEmpty(bookmarksEmpty, bookmarksList, '[data-reader-bookmark-item]');
                    removeInlineMark(target.id, 'bookmark');
                    setStatus('Закладка удалена');
                }
            } catch {
                setStatus('Не удалось изменить закладку', true);
            } finally {
                updateActionButtons();
            }
        });
    }

    if (quoteButton instanceof HTMLButtonElement && quoteUrl && quoteToken) {
        quoteButton.addEventListener('click', async () => {
            const selected = selectionInReader();
            if (!selected?.block) {
                setStatus('Выделите текст для цитаты', true);
                return;
            }
            quoteButton.disabled = true;
            try {
                const data = await postJson(quoteUrl, quoteToken, {
                    chapter,
                    block: selected.block.id,
                    excerpt: selected.excerpt,
                    startOffset: selected.startOffset,
                    endOffset: selected.endOffset,
                });
                selected.block.dataset.quoted = '1';
                selectActiveBlock(selected.block, true);
                appendListItem(
                    quotesList,
                    quotesEmpty,
                    '[data-reader-quote-item]',
                    data,
                    'quote',
                    {
                        startOffset: selected.startOffset,
                        endOffset: selected.endOffset,
                    },
                );
                upsertInlineMark(
                    selected.block.id,
                    'quote',
                    data.excerpt || selected.excerpt,
                    data.quoteId,
                    {
                        startOffset: selected.startOffset,
                        endOffset: selected.endOffset,
                    },
                );
                wrapQuoteHighlight(
                    selected.block,
                    selected.startOffset,
                    selected.endOffset,
                    data.quoteId || selected.excerpt,
                );
                if (reader.dataset.readerFlow === 'paginated') {
                    measurePages();
                }
                flushProgressNow({ block: selected.block, keepalive: false });
                setStatus('Цитата сохранена');
            } catch {
                setStatus('Не удалось сохранить цитату', true);
            } finally {
                updateActionButtons();
            }
        });
    }

    if (noteButton instanceof HTMLButtonElement && noteUrl && noteToken) {
        noteButton.addEventListener('click', async () => {
            const selected = selectionInReader();
            const target = selected?.block instanceof HTMLElement
                ? selected.block
                : syncActiveFromViewport({ persist: false });
            if (!(target instanceof HTMLElement)) {
                return;
            }
            const body = window.prompt('Заметка к этому месту:');
            if (body == null) {
                return;
            }
            const trimmed = body.trim();
            if (!trimmed) {
                setStatus('Пустая заметка не сохранена', true);
                return;
            }
            noteButton.disabled = true;
            try {
                const data = await postJson(noteUrl, noteToken, {
                    chapter,
                    block: target.id,
                    body: trimmed,
                });
                target.dataset.noted = '1';
                target.classList.add('is-noted');
                selectActiveBlock(target, true);
                appendListItem(notesList, notesEmpty, '[data-reader-note-item]', data, 'note');
                upsertInlineMark(target.id, 'note', data.body || trimmed, data.noteId);
                flushProgressNow({ block: target, keepalive: false });
                setStatus('Заметка сохранена');
            } catch {
                setStatus('Не удалось сохранить заметку', true);
            } finally {
                updateActionButtons();
            }
        });
    }

    const clearBookmarkLocal = (block, _id, blockId) => {
        block.dataset.bookmarked = '0';
        block.classList.remove('is-bookmarked');
        removeInlineMark(blockId, 'bookmark');
        bookmarksList?.querySelector(`[data-reader-bookmark-item][data-block="${CSS.escape(blockId)}"]`)?.remove();
        syncEmpty(bookmarksEmpty, bookmarksList, '[data-reader-bookmark-item]');
    };

    const clearQuoteLocal = (block, id, blockId) => {
        removeInlineMark(blockId, 'quote', id);
        unwrapQuoteHighlight(block, id);
        quotesList?.querySelector(`[data-reader-quote-item][data-quote-id="${CSS.escape(String(id))}"]`)?.remove();
        syncEmpty(quotesEmpty, quotesList, '[data-reader-quote-item]');
        const host = marksHostFor(blockId);
        const stillQuoted = Boolean(host?.querySelector('[data-reader-mark="quote"]'));
        block.dataset.quoted = stillQuoted ? '1' : '0';
        if (reader.dataset.readerFlow === 'paginated') {
            measurePages();
        }
    };

    const clearNoteLocal = (block, id, blockId) => {
        removeInlineMark(blockId, 'note', id);
        notesList?.querySelector(`[data-reader-note-item][data-note-id="${CSS.escape(String(id))}"]`)?.remove();
        syncEmpty(notesEmpty, notesList, '[data-reader-note-item]');
        const host = marksHostFor(blockId);
        const stillNoted = Boolean(host?.querySelector('[data-reader-mark="note"]'));
        block.dataset.noted = stillNoted ? '1' : '0';
        block.classList.toggle('is-noted', stillNoted);
    };

    const resolveRemoveTarget = (button) => {
        if (button.hasAttribute('data-reader-bookmark-remove')) {
            return {
                kind: 'bookmark',
                id: button.dataset.bookmarkId || '',
                idKey: 'bookmarkId',
                url: bookmarkUrl,
                token: bookmarkToken,
                clearFlag: clearBookmarkLocal,
                listSel: `[data-reader-bookmark-item][data-bookmark-id="${CSS.escape(button.dataset.bookmarkId || '')}"]`,
            };
        }
        if (button.hasAttribute('data-reader-quote-remove')) {
            return {
                kind: 'quote',
                id: button.dataset.quoteId || '',
                idKey: 'quoteId',
                url: quoteUrl,
                token: quoteToken,
                clearFlag: clearQuoteLocal,
                listSel: `[data-reader-quote-item][data-quote-id="${CSS.escape(button.dataset.quoteId || '')}"]`,
            };
        }
        if (button.hasAttribute('data-reader-note-remove')) {
            return {
                kind: 'note',
                id: button.dataset.noteId || '',
                idKey: 'noteId',
                url: noteUrl,
                token: noteToken,
                clearFlag: clearNoteLocal,
                listSel: `[data-reader-note-item][data-note-id="${CSS.escape(button.dataset.noteId || '')}"]`,
            };
        }
        return null;
    };

    reader.addEventListener('click', async (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const button = target.closest('[data-reader-bookmark-remove], [data-reader-quote-remove], [data-reader-note-remove]');
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        const spec = resolveRemoveTarget(button);
        if (!spec || !spec.id || !spec.url || !spec.token) {
            return;
        }
        button.disabled = true;
        try {
            await postJson(spec.url, spec.token, { action: 'remove', [spec.idKey]: Number(spec.id) });
            const listItem = reader.querySelector(spec.listSel);
            const markCard = button.closest('[data-reader-mark]');
            const blockId = (listItem instanceof HTMLElement && listItem.dataset.block)
                || (markCard instanceof HTMLElement && markCard.closest('[data-reader-marks]')?.getAttribute('data-block'))
                || '';
            if (blockId) {
                const block = reader.querySelector(`#${CSS.escape(blockId)}`);
                if (block instanceof HTMLElement) {
                    spec.clearFlag(block, spec.id, blockId);
                }
            } else if (listItem instanceof HTMLElement) {
                listItem.remove();
            }
            updateActionButtons();
            setStatus('Удалено');
        } catch {
            button.disabled = false;
            setStatus('Не удалось удалить', true);
        }
    });

    let focusTimer = 0;
    const clearFocusFlash = () => {
        reader.querySelectorAll('.is-reader-focus, .reader-focus-temp').forEach((el) => {
            if (!(el instanceof HTMLElement)) {
                return;
            }
            if (el.classList.contains('reader-focus-temp')) {
                const parent = el.parentNode;
                while (el.firstChild) {
                    parent?.insertBefore(el.firstChild, el);
                }
                el.remove();
                if (parent instanceof Element) {
                    parent.normalize();
                }
                return;
            }
            el.classList.remove('is-reader-focus');
        });
    };

    const flashElements = (elements) => {
        window.clearTimeout(focusTimer);
        clearFocusFlash();
        elements.forEach((el) => {
            if (el instanceof HTMLElement) {
                el.classList.add('is-reader-focus');
            }
        });
        focusTimer = window.setTimeout(() => {
            clearFocusFlash();
        }, 1800);
    };

    const flashTextRange = (block, startOffset, endOffset) => {
        if (!(block instanceof HTMLElement) || endOffset <= startOffset) {
            flashElements([block]);
            return;
        }
        const focusId = `focus-${startOffset}-${endOffset}`;
        wrapQuoteHighlight(block, startOffset, endOffset, focusId);
        const temp = block.querySelector(`mark.reader-inline-quote[data-quote-id="${CSS.escape(focusId)}"]`);
        if (temp instanceof HTMLElement) {
            temp.classList.add('reader-focus-temp');
            flashElements([temp]);
            return;
        }
        flashElements([block]);
    };

    const focusMark = ({
        blockId,
        kind = '',
        id = '',
        startOffset = 0,
        endOffset = 0,
        excerpt = '',
    }) => {
        const block = blockId ? reader.querySelector(`#${CSS.escape(blockId)}`) : null;
        if (!(block instanceof HTMLElement)) {
            return false;
        }
        const navigation = reader.querySelector('#reader-navigation');
        if (navigation instanceof HTMLDetailsElement) {
            navigation.open = false;
        }

        const reveal = () => {
            let target = null;
            if (kind === 'quote' && id) {
                target = block.querySelector(`mark.reader-inline-quote[data-quote-id="${CSS.escape(String(id))}"]`);
            }
            if (!(target instanceof HTMLElement) && endOffset > startOffset) {
                flashTextRange(block, startOffset, endOffset);
                target = block.querySelector('.reader-focus-temp, mark.reader-inline-quote.is-reader-focus');
            } else if (!(target instanceof HTMLElement) && excerpt) {
                const haystack = block.textContent || '';
                const at = haystack.indexOf(excerpt);
                if (at >= 0) {
                    flashTextRange(block, at, at + excerpt.length);
                    target = block.querySelector('.reader-focus-temp, mark.reader-inline-quote.is-reader-focus');
                }
            }
            if (target instanceof HTMLElement) {
                jumpToElement(target);
                flashElements([target]);
                return;
            }
            jumpToElement(block);
            flashElements([block]);
        };

        // Дождаться раскладки страниц (особенно после перехода из Своего места).
        window.requestAnimationFrame(() => {
            window.requestAnimationFrame(reveal);
        });
        return true;
    };

    reader.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        if (target.closest('[data-reader-bookmark-remove], [data-reader-quote-remove], [data-reader-note-remove]')) {
            return;
        }
        const jump = target.closest('[data-reader-jump]');
        if (jump instanceof HTMLElement) {
            event.preventDefault();
            const jumpChapter = jump.dataset.jumpChapter || '';
            const href = jump.dataset.jumpHref || '';
            if (jumpChapter && jumpChapter !== chapter && href) {
                window.location.href = href;
                return;
            }
            focusMark({
                blockId: jump.dataset.jumpBlock || '',
                kind: jump.dataset.jumpKind || '',
                id: jump.dataset.jumpId || '',
                startOffset: Number(jump.dataset.startOffset || 0),
                endOffset: Number(jump.dataset.endOffset || 0),
                excerpt: jump.dataset.jumpExcerpt || '',
            });
            return;
        }
        const markCard = target.closest('[data-reader-mark]');
        if (!(markCard instanceof HTMLElement)) {
            return;
        }
        const host = markCard.closest('[data-reader-marks]');
        const blockId = host instanceof HTMLElement ? (host.dataset.block || '') : '';
        focusMark({
            blockId,
            kind: markCard.dataset.readerMark || '',
            id: markCard.dataset.quoteId || markCard.dataset.bookmarkId || markCard.dataset.noteId || '',
            startOffset: Number(markCard.dataset.startOffset || 0),
            endOffset: Number(markCard.dataset.endOffset || 0),
            excerpt: markCard.dataset.jumpExcerpt || markCard.querySelector('.reader-mark__text')?.textContent || '',
        });
    });

    // Click on inline quote mark in text → flash it.
    if (content instanceof HTMLElement) {
        content.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof Element)) {
                return;
            }
            const mark = target.closest('mark.reader-inline-quote');
            if (mark instanceof HTMLElement) {
                flashElements([mark]);
            }
        });
    }

    pagePrevButtons.forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            goToPage(pageIndex - 1);
        });
    });
    pageNextButtons.forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            goToPage(pageIndex + 1);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (reader.dataset.readerFlow !== 'paginated' || isPdfFallback) {
            return;
        }
        const tag = event.target instanceof HTMLElement ? event.target.tagName : '';
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
            return;
        }
        if (event.key === 'ArrowRight' || event.key === 'PageDown') {
            event.preventDefault();
            goToPage(pageIndex + 1);
        } else if (event.key === 'ArrowLeft' || event.key === 'PageUp') {
            event.preventDefault();
            goToPage(pageIndex - 1);
        }
    });

    // Horizontal swipe to change page (не во время выделения / long-press).
    let touchStartX = 0;
    let touchStartY = 0;
    let touchStartAt = 0;
    const swipeTarget = content instanceof HTMLElement ? content : reader;
    swipeTarget.addEventListener('touchstart', (event) => {
        const touch = event.changedTouches[0];
        if (!touch) {
            return;
        }
        touchIsDown = true;
        touchSawSelection = isReaderSelection();
        touchStartX = touch.clientX;
        touchStartY = touch.clientY;
        touchStartAt = Date.now();
    }, { passive: true });
    swipeTarget.addEventListener('touchcancel', () => {
        touchIsDown = false;
        touchSawSelection = false;
    }, { passive: true });
    swipeTarget.addEventListener('touchend', (event) => {
        const touch = event.changedTouches[0];
        const dx = touch ? touch.clientX - touchStartX : 0;
        const dy = touch ? touch.clientY - touchStartY : 0;
        const heldMs = Date.now() - touchStartAt;
        const blockSwipe = touchSawSelection || selectionActive || isReaderSelection() || heldMs > 350;
        touchIsDown = false;
        touchSawSelection = false;
        if (reader.dataset.readerFlow !== 'paginated' || isPdfFallback || blockSwipe || !touch) {
            lockPageScroll();
            return;
        }
        if (Math.abs(dx) >= 36 && Math.abs(dx) > Math.abs(dy) * 1.25) {
            goToPage(pageIndex + (dx < 0 ? 1 : -1));
        } else {
            lockPageScroll();
        }
    }, { passive: true });

    document.addEventListener('selectionchange', () => {
        if (touchIsDown && isReaderSelection()) {
            touchSawSelection = true;
        }
        syncSelectionLock();
        updateActionButtons();
    });

    if (content instanceof HTMLElement) {
        content.addEventListener('scroll', () => {
            // Фиксируем столбец только во время выделения — иначе iOS рвёт страницы при листании.
            if (selectionActive) {
                lockPageScroll();
            }
        }, { passive: true });
    }

    window.addEventListener('resize', () => {
        if (reader.dataset.readerFlow === 'paginated') {
            measurePages();
        }
    });

    const refreshOfflineButton = async ({ announce = false } = {}) => {
        if (!(offlineButton instanceof HTMLButtonElement) || !bookSlug) {
            return false;
        }
        let cached = false;
        try {
            cached = Boolean(localStorage.getItem(READER_OFFLINE_PREFIX + bookSlug));
            if ('caches' in window) {
                const cache = await caches.open('om-library-offline-v1');
                cached = cached && Boolean(await cache.match(offlineUrl));
            }
        } catch {
            cached = false;
        }
        offlineButton.setAttribute('aria-pressed', cached ? 'true' : 'false');
        offlineButton.setAttribute('aria-label', cached ? 'Книга доступна офлайн' : 'Сохранить офлайн');
        if (announce && cached) {
            setStatus('Книга доступна офлайн');
        }
        return cached;
    };

    if (offlineButton instanceof HTMLButtonElement && offlineUrl && bookSlug) {
        offlineButton.addEventListener('click', async () => {
            const alreadyCached = offlineButton.getAttribute('aria-pressed') === 'true';
            if (alreadyCached) {
                setStatus('Книга уже доступна офлайн');
                return;
            }
            offlineButton.disabled = true;
            setStatus('Сохраняю офлайн…', true);
            try {
                const response = await fetch(offlineUrl, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (!response.ok) {
                    throw new Error('offline pack failed');
                }
                const data = await response.json();
                localStorage.setItem(READER_OFFLINE_PREFIX + bookSlug, JSON.stringify({
                    savedAt: data.generatedAt || new Date().toISOString(),
                    title: data.book?.title || bookSlug,
                }));
                if ('caches' in window) {
                    const cache = await caches.open('om-library-offline-v1');
                    await cache.put(offlineUrl, new Response(JSON.stringify(data), {
                        headers: { 'Content-Type': 'application/json' },
                    }));
                    if ('serviceWorker' in navigator) {
                        await navigator.serviceWorker.register('/sw.js', { scope: '/' });
                    }
                }
                await refreshOfflineButton();
                setStatus('Книга доступна офлайн');
            } catch {
                setStatus('Не удалось сохранить офлайн', true);
            } finally {
                offlineButton.disabled = false;
            }
        });
        void refreshOfflineButton({ announce: true });
    }

    const tocLink = chrome.querySelector('a[href="#reader-navigation"]');
    const navigation = reader.querySelector('#reader-navigation');
    if (tocLink instanceof HTMLAnchorElement && navigation instanceof HTMLDetailsElement) {
        tocLink.addEventListener('click', (event) => {
            event.preventDefault();
            navigation.open = !navigation.open;
            if (navigation.open) {
                syncPaginatedViewport();
            }
        });
    }

    applyQuoteHighlights();

    initReaderPreferences(reader, {
        onFlowChange: () => {
            pageIndex = 0;
            measurePages();
            currentFromViewport();
        },
        measurePages,
    });

    // Restore строго после preferences/measurePages: иначе rAF с page=0 перебивает позицию.
    const focusParamsEarly = new URLSearchParams(window.location.search);
    const hasIntentionalFocus = Boolean(focusParamsEarly.get('focus'));
    const progressDraft = readProgressDraft();
    const hashBlockId = (location.hash || '').replace(/^#/, '');
    let restoreBlock = null;
    let restoreFromDraft = false;

    if (hasIntentionalFocus) {
        // focusMark ниже важнее авто-restore.
    } else if (reader.dataset.readerOpenAt === 'end' && blocks.length) {
        restoreBlock = blocks[blocks.length - 1];
    } else if (hashBlockId) {
        // Hash с /continue важнее localStorage — битый draft начала главы больше не откатывает прогресс.
        const fragmentBlock = reader.querySelector(`#${CSS.escape(hashBlockId)}`);
        if (fragmentBlock instanceof HTMLElement && fragmentBlock.hasAttribute('data-reader-block')) {
            restoreBlock = fragmentBlock;
        }
    } else if (
        progressDraft
        && progressDraft.chapter === chapter
        && draftIsFresher(progressDraft, serverProgress)
    ) {
        const draftBlock = reader.querySelector(`#${CSS.escape(progressDraft.block)}`);
        if (draftBlock instanceof HTMLElement && draftBlock.hasAttribute('data-reader-block')) {
            restoreBlock = draftBlock;
            restoreFromDraft = true;
        }
    } else if (serverProgress.chapter === chapter && serverProgress.block) {
        const serverBlock = reader.querySelector(`#${CSS.escape(serverProgress.block)}`);
        if (serverBlock instanceof HTMLElement && serverBlock.hasAttribute('data-reader-block')) {
            restoreBlock = serverBlock;
        }
    }

    const markPositionReady = () => {
        positionReady = true;
        syncActiveFromViewport();
        armProgressHeartbeat();
    };

    const runPositionRestore = () => {
        if (restoreBlock instanceof HTMLElement) {
            restoringPosition = true;
            jumpToBlock(restoreBlock);
            lastSavedBlock = restoreBlock.id;
            writeProgressDraft({
                blockId: restoreBlock.id,
                progressPercent: restoreFromDraft
                    ? Number(progressDraft.progressPercent || serverProgress.progressPercent || 0)
                    : serverProgress.progressPercent,
                synced: !restoreFromDraft,
                updatedAt: restoreFromDraft
                    ? progressDraft.updatedAt
                    : (serverProgress.updatedAt || new Date().toISOString()),
            });
            restoringPosition = false;
            markPositionReady();
            if (restoreFromDraft) {
                flushProgressNow({ block: restoreBlock, keepalive: false });
            }
            return;
        }
        markPositionReady();
    };

    const focusParams = new URLSearchParams(window.location.search);
    const focusKind = focusParams.get('focus') || '';
    const focusId = focusParams.get('qid') || focusParams.get('bid') || focusParams.get('nid') || '';
    const focusBlockId = (window.location.hash || '').replace(/^#/, '');
    if (focusKind && focusBlockId) {
        window.setTimeout(() => {
            let startOffset = Number(focusParams.get('so') || 0);
            let endOffset = Number(focusParams.get('eo') || 0);
            let excerpt = '';
            if (focusKind === 'quote' && focusId) {
                const card = reader.querySelector(`[data-reader-mark="quote"][data-quote-id="${CSS.escape(focusId)}"]`);
                if (card instanceof HTMLElement) {
                    if (!(endOffset > startOffset)) {
                        startOffset = Number(card.dataset.startOffset || 0);
                        endOffset = Number(card.dataset.endOffset || 0);
                    }
                    excerpt = card.dataset.jumpExcerpt || card.querySelector('.reader-mark__text')?.textContent || '';
                }
            } else if (focusKind === 'bookmark' && focusId) {
                const card = reader.querySelector(`[data-reader-mark="bookmark"][data-bookmark-id="${CSS.escape(focusId)}"]`);
                excerpt = card instanceof HTMLElement
                    ? (card.dataset.jumpExcerpt || '')
                    : '';
            } else if (focusKind === 'note' && focusId) {
                const card = reader.querySelector(`[data-reader-mark="note"][data-note-id="${CSS.escape(focusId)}"]`);
                excerpt = card instanceof HTMLElement
                    ? (card.dataset.jumpExcerpt || card.querySelector('.reader-mark__text')?.textContent || '')
                    : '';
            }
            focusMark({
                blockId: focusBlockId,
                kind: focusKind,
                id: focusId,
                startOffset,
                endOffset,
                excerpt,
            });
            focusParams.delete('focus');
            focusParams.delete('qid');
            focusParams.delete('bid');
            focusParams.delete('nid');
            focusParams.delete('so');
            focusParams.delete('eo');
            const next = focusParams.toString();
            const url = `${window.location.pathname}${next ? `?${next}` : ''}${window.location.hash}`;
            window.history.replaceState({}, '', url);
            markPositionReady();
        }, 180);
    } else {
        // Два rAF — дождаться measurePages из preferences.render().
        window.requestAnimationFrame(() => {
            window.requestAnimationFrame(runPositionRestore);
        });
    }
}

function initReaderPreferences(reader, hooks = {}) {
    const chrome = document.querySelector('[data-reader-chrome]') || reader;
    const themeButton = chrome.querySelector('[data-reader-theme]');
    const flowButton = chrome.querySelector('[data-reader-flow]');
    const flowLabel = flowButton?.querySelector('[data-reader-flow-label]');
    const isDesktop = window.matchMedia('(min-width: 900px)').matches;
    let preferences = {
        size: 'default',
        theme: 'light',
        flow: isDesktop ? 'scroll' : 'paginated',
    };
    let stored = {};

    try {
        stored = JSON.parse(localStorage.getItem(READER_PREFERENCES_KEY) || '{}') || {};
        preferences = { ...preferences, ...stored };
    } catch {
        // Keep safe defaults when storage is unavailable or corrupt.
    }
    preferences.size = 'default';
    if (!['light', 'dark'].includes(preferences.theme)) {
        preferences.theme = 'light';
    }
    if (!['paginated', 'scroll'].includes(preferences.flow)) {
        preferences.flow = isDesktop ? 'scroll' : 'paginated';
    }
    // Один раз на десктопе перевести старый дефолт «страницы» на ленту.
    if (isDesktop && stored.flowDesktopScroll !== true) {
        preferences.flow = 'scroll';
        preferences.flowDesktopScroll = true;
    }

    const persist = () => {
        try {
            localStorage.setItem(READER_PREFERENCES_KEY, JSON.stringify(preferences));
        } catch {
            // Reading controls still work for the current page.
        }
    };

    if (isDesktop && stored.flowDesktopScroll !== true) {
        persist();
    }

    const render = () => {
        reader.dataset.readerSize = preferences.size;
        reader.dataset.readerTheme = preferences.theme;
        reader.dataset.readerFlow = preferences.flow;
        if (themeButton instanceof HTMLButtonElement) {
            const dark = preferences.theme === 'dark';
            themeButton.setAttribute('aria-pressed', dark ? 'true' : 'false');
            themeButton.setAttribute('aria-label', dark ? 'Дневной режим' : 'Ночной режим');
            themeButton.querySelector('[data-icon-moon]')?.toggleAttribute('hidden', dark);
            themeButton.querySelector('[data-icon-sun]')?.toggleAttribute('hidden', !dark);
        }
        if (flowButton instanceof HTMLButtonElement) {
            const paginated = preferences.flow === 'paginated';
            flowButton.setAttribute('aria-pressed', paginated ? 'true' : 'false');
            flowButton.setAttribute('aria-label', paginated ? 'Режим: страницы' : 'Режим: лента');
            flowButton.querySelector('[data-icon-pages]')?.toggleAttribute('hidden', !paginated);
            flowButton.querySelector('[data-icon-scroll]')?.toggleAttribute('hidden', paginated);
            if (flowLabel instanceof HTMLElement) {
                flowLabel.textContent = paginated ? 'Страницы' : 'Лента';
            }
        }
        if (typeof hooks.measurePages === 'function') {
            window.requestAnimationFrame(() => hooks.measurePages());
        }
    };

    themeButton?.addEventListener('click', () => {
        preferences.theme = preferences.theme === 'dark' ? 'light' : 'dark';
        persist();
        render();
    });
    flowButton?.addEventListener('click', () => {
        preferences.flow = preferences.flow === 'paginated' ? 'scroll' : 'paginated';
        preferences.flowDesktopScroll = true;
        persist();
        render();
        if (typeof hooks.onFlowChange === 'function') {
            hooks.onFlowChange();
        }
    });

    render();
}
