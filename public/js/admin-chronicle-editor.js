(() => {
    const cfg = window.__CHRONICLE_EDITOR__;
    if (!cfg) return;

    let state = null;
    let saveTimer = null;
    let saving = false;
    let dirty = false;
    let sortable = null;
    let bootstrapped = false;

    const els = {
        blocksList: document.querySelector('[data-blocks-list]'),
        saveStatus: document.querySelector('[data-save-status]'),
        readingTime: document.querySelector('[data-reading-time]'),
        coverPreview: document.querySelector('[data-cover-preview]'),
        coverDrop: document.querySelector('[data-upload-kind="cover"]'),
    };

    const blockLabels = {
        paragraph: 'Абзац',
        heading: 'Заголовок',
        image: 'Картинка',
        gallery: 'Галерея',
        quote: 'Цитата',
        audio: 'OmPlayer',
        video: 'Видео',
        divider: 'Разделитель',
        callout: 'Врезка',
    };

    function uid() {
        return `c-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
    }

    function ensureClientId(block) {
        if (!block._clientId) {
            block._clientId = uid();
        }
        if (block.type === 'gallery' && Array.isArray(block.images)) {
            block.images.forEach((img) => {
                if (!img._clientId) {
                    img._clientId = uid();
                }
            });
        }

        return block._clientId;
    }

    function initState(data) {
        if (!data || typeof data !== 'object') {
            return { blocks: [], status: 'draft', coverImagePath: null };
        }

        const next = structuredClone(data);
        if (!Array.isArray(next.blocks)) {
            next.blocks = [];
        }
        next.blocks.forEach(ensureClientId);

        return next;
    }

    function findBlock(clientId) {
        if (!state) return null;
        return state.blocks.find((b) => b._clientId === clientId) ?? null;
    }

    function markDirty() {
        if (!state) return;
        dirty = true;
        scheduleSave();
    }

    function scheduleSave() {
        clearTimeout(saveTimer);
        setStatus('Сохраняю…', 'pending');
        saveTimer = setTimeout(save, 900);
    }

    function setStatus(text, kind = 'ok') {
        if (!els.saveStatus) return;
        els.saveStatus.textContent = text;
        els.saveStatus.dataset.kind = kind;
    }

    function collectMeta() {
        if (!state) return {};
        readMetaFieldsFromDom();
        const tagIds = [...document.querySelectorAll('[data-field="tagIds"]:checked')].map((el) => Number(el.value));

        return {
            title: document.querySelector('[data-field="title"]')?.value ?? '',
            slug: document.querySelector('[data-field="slug"]')?.value ?? '',
            lede: document.querySelector('[data-field="lede"]')?.value ?? '',
            excerpt: document.querySelector('[data-field="excerpt"]')?.value ?? '',
            coverImagePath: state.coverImagePath ?? null,
            eraId: document.querySelector('[data-field="eraId"]')?.value || null,
            seriesId: document.querySelector('[data-field="seriesId"]')?.value || null,
            tagIds,
            publishedAt: document.querySelector('[data-field="publishedAt"]')?.value || null,
            isFeatured: document.querySelector('[data-field="isFeatured"]')?.checked ?? false,
            isUnlisted: document.querySelector('[data-field="isUnlisted"]')?.checked ?? false,
            seoTitle: document.querySelector('[data-field="seoTitle"]')?.value ?? '',
            seoDescription: document.querySelector('[data-field="seoDescription"]')?.value ?? '',
            status: state.status,
            blocks: state.blocks.map((block, index) => ({ ...block, sortOrder: index * 10 })),
        };
    }

    function readMetaFieldsFromDom() {
        // Block field values are synced on input; gallery captions read before save.
        els.blocksList?.querySelectorAll('[data-block-card]').forEach((card) => {
            const block = findBlock(card.dataset.clientId);
            if (!block) return;

            card.querySelectorAll('[data-block-field]').forEach((field) => {
                const key = field.dataset.blockField;
                if (key === 'headingLevel') {
                    block[key] = Number(field.value);
                } else {
                    block[key] = field.value;
                }
            });

            if (block.type === 'gallery' && Array.isArray(block.images)) {
                card.querySelectorAll('[data-gallery-item]').forEach((item) => {
                    const img = block.images.find((i) => i._clientId === item.dataset.clientId);
                    if (!img) return;
                    const caption = item.querySelector('[data-gallery-caption]');
                    if (caption instanceof HTMLInputElement) {
                        img.caption = caption.value;
                    }
                });
            }
        });
    }

    function captureFocus() {
        const el = document.activeElement;
        if (!(el instanceof HTMLElement)) return null;

        const card = el.closest('[data-block-card]');
        const fieldKey = el.dataset.blockField || el.dataset.field || null;
        const isGalleryCaption = el.matches('[data-gallery-caption]');
        const galleryItem = el.closest('[data-gallery-item]');

        let selection = null;
        if (el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement) {
            selection = { start: el.selectionStart ?? 0, end: el.selectionEnd ?? 0 };
        }

        return {
            clientId: card instanceof HTMLElement ? card.dataset.clientId : null,
            fieldKey: isGalleryCaption ? 'gallery-caption' : fieldKey,
            galleryClientId: galleryItem instanceof HTMLElement ? galleryItem.dataset.clientId : null,
            metaField: el.dataset.field || null,
            selection,
        };
    }

    function restoreFocus(snapshot) {
        if (!snapshot) return;

        let el = null;
        if (snapshot.clientId && snapshot.fieldKey === 'gallery-caption' && snapshot.galleryClientId) {
            const card = document.querySelector(`[data-block-card][data-client-id="${snapshot.clientId}"]`);
            const item = card?.querySelector(`[data-gallery-item][data-client-id="${snapshot.galleryClientId}"]`);
            el = item?.querySelector('[data-gallery-caption]') ?? null;
        } else if (snapshot.clientId && snapshot.fieldKey) {
            const card = document.querySelector(`[data-block-card][data-client-id="${snapshot.clientId}"]`);
            el = card?.querySelector(`[data-block-field="${snapshot.fieldKey}"]`) ?? null;
        } else if (snapshot.metaField) {
            el = document.querySelector(`[data-field="${snapshot.metaField}"]`);
        }

        if (!(el instanceof HTMLElement)) return;

        el.focus({ preventScroll: true });
        if (
            snapshot.selection
            && (el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement)
            && typeof el.setSelectionRange === 'function'
        ) {
            const { start, end } = snapshot.selection;
            try {
                el.setSelectionRange(start, end);
            } catch {
                // Some input types do not support selection ranges.
            }
        }
    }

    function mergeStateFromServer(serverData, clientIds, imageClientIds) {
        const merged = { ...serverData };
        merged.blocks = (serverData.blocks || []).map((block, index) => {
            const next = { ...block, _clientId: clientIds[index] ?? uid() };
            if (next.type === 'gallery' && Array.isArray(next.images)) {
                const oldImageIds = imageClientIds[index] || [];
                next.images = next.images.map((img, imgIndex) => ({
                    ...img,
                    _clientId: oldImageIds[imgIndex] ?? uid(),
                }));
            }

            return next;
        });

        return merged;
    }

    async function save() {
        if (!state || saving) return;
        saving = true;
        readMetaFieldsFromDom();
        const clientIds = state.blocks.map((b) => b._clientId);
        const imageClientIds = state.blocks.map((b) =>
            b.type === 'gallery' && Array.isArray(b.images) ? b.images.map((i) => i._clientId) : [],
        );
        const payload = collectMeta();

        try {
            const res = await fetch(cfg.autosaveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                credentials: 'same-origin',
            });
            const json = await res.json();
            if (!res.ok || !json.ok) throw new Error(json.error || 'Save failed');

            state = mergeStateFromServer(json.data, clientIds, imageClientIds);
            dirty = false;
            if (els.readingTime) els.readingTime.textContent = `${json.readingTimeMin} мин`;
            const updated = new Date(json.updatedAt);
            setStatus(`Сохранено ${updated.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })}`);
        } catch (e) {
            setStatus('Ошибка сохранения', 'error');
            console.error(e);
        } finally {
            saving = false;
        }
    }

    async function publish(action = 'publish') {
        await save();
        const payload = { ...collectMeta(), action };
        const res = await fetch(cfg.publishUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            credentials: 'same-origin',
        });
        const json = await res.json();
        if (!res.ok || !json.ok) {
            alert('Не удалось опубликовать');
            return;
        }
        if (json.data && state) {
            const clientIds = state.blocks.map((b) => b._clientId);
            const imageClientIds = state.blocks.map((b) =>
                b.type === 'gallery' && Array.isArray(b.images) ? b.images.map((i) => i._clientId) : [],
            );
            state = mergeStateFromServer(json.data, clientIds, imageClientIds);
            renderBlocks();
        }
        setStatus(action === 'schedule' ? 'Запланировано' : 'Опубликовано', 'published');
        if (json.publicUrl) cfg.publicUrl = json.publicUrl;
    }

    async function uploadFile(file, kind = 'inline') {
        const fd = new FormData();
        fd.append('file', file);
        fd.append('kind', kind);
        const res = await fetch(cfg.uploadUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
        const json = await res.json();
        if (!res.ok || !json.ok) throw new Error(json.error || 'Upload failed');

        return json.path;
    }

    function renderBlocks(options = {}) {
        if (!els.blocksList || !state) return;

        const focus = options.restoreFocus ? captureFocus() : null;
        readMetaFieldsFromDom();
        els.blocksList.innerHTML = '';

        state.blocks.forEach((block) => {
            ensureClientId(block);
            els.blocksList.appendChild(renderBlockCard(block));
        });

        if (sortable) sortable.destroy();
        sortable = Sortable.create(els.blocksList, {
            handle: '.chronicle-block-card__handle',
            animation: 150,
            onEnd(evt) {
                if (evt.oldIndex === evt.newIndex) return;
                const [moved] = state.blocks.splice(evt.oldIndex, 1);
                state.blocks.splice(evt.newIndex, 0, moved);
                markDirty();
            },
        });

        if (focus) restoreFocus(focus);
    }

    function renderBlockCard(block) {
        const card = document.createElement('article');
        card.className = 'chronicle-block-card';
        card.dataset.blockCard = '';
        card.dataset.clientId = block._clientId;

        const type = block.type || 'paragraph';
        let body = '';

        if (type === 'paragraph' || type === 'callout') {
            body = `<textarea class="form-control chronicle-autosize" rows="1" data-block-field="body" placeholder="Markdown: **жирный**, *курсив*, [ссылка](url)">${escapeHtml(block.body || '')}</textarea>`;
        } else if (type === 'heading') {
            body = `
                <select class="form-select form-select-sm mb-2" data-block-field="headingLevel">
                    <option value="2" ${block.headingLevel == 2 ? 'selected' : ''}>H2</option>
                    <option value="3" ${block.headingLevel == 3 ? 'selected' : ''}>H3</option>
                </select>
                <input type="text" class="form-control" data-block-field="body" value="${escapeAttr(block.body || '')}" placeholder="Заголовок">`;
        } else if (type === 'quote') {
            body = `
                <textarea class="form-control chronicle-autosize mb-2" rows="1" data-block-field="body">${escapeHtml(block.body || '')}</textarea>
                <input type="text" class="form-control" data-block-field="author" value="${escapeAttr(block.author || '')}" placeholder="Автор">`;
        } else if (type === 'image') {
            body = renderImageBlock(block);
        } else if (type === 'gallery') {
            body = renderGalleryBlock(block);
        } else if (type === 'audio') {
            body = `
                <input type="text" class="form-control mb-2" data-block-field="omTrackSlug" value="${escapeAttr(block.omTrackSlug || '')}" placeholder="slug трека OmPlayer">
                <input type="text" class="form-control" data-block-field="caption" value="${escapeAttr(block.caption || '')}" placeholder="Подпись">`;
        } else if (type === 'divider') {
            body = '<hr class="my-2">';
        }

        card.innerHTML = `
            <div class="chronicle-block-card__head">
                <button type="button" class="chronicle-block-card__handle" aria-label="Перетащить">≡</button>
                <span class="chronicle-block-card__type">${blockLabels[type] || type}</span>
                <button type="button" class="chronicle-block-card__remove" data-remove-block aria-label="Удалить">×</button>
            </div>
            <div class="chronicle-block-card__body">${body}</div>`;

        queueMicrotask(() => {
            card.querySelectorAll('textarea.chronicle-autosize').forEach((el) => {
                autosizeTextarea(el);
                requestAnimationFrame(() => autosizeTextarea(el));
            });
        });

        return card;
    }

    function autosizeTextarea(el) {
        if (!(el instanceof HTMLTextAreaElement)) return;
        // Reset so scrollHeight reflects content, not the previous fixed height.
        el.style.height = '0px';
        const next = Math.max(el.scrollHeight, 40);
        el.style.height = `${next}px`;
    }

    function imgWithFallback(filename, dirs, className = '') {
        if (!filename) return '';
        const primary = `/uploads/${dirs[0]}/${filename}`;
        const rest = dirs.slice(1).map((d) => `/uploads/${d}/${filename}`);
        const fallbackAttr = rest.length
            ? ` data-fallback-srcs="${escapeAttr(rest.join('|'))}" onerror="window.__chronicleImgFallback && window.__chronicleImgFallback(this)"`
            : '';
        const cls = className ? ` class="${className}"` : '';
        return `<img src="${primary}" alt=""${cls}${fallbackAttr}>`;
    }

    if (!window.__chronicleImgFallback) {
        window.__chronicleImgFallback = function (img) {
            const raw = img.getAttribute('data-fallback-srcs') || '';
            const next = raw.split('|').filter(Boolean);
            if (!next.length) {
                img.onerror = null;
                return;
            }
            img.src = next.shift();
            img.setAttribute('data-fallback-srcs', next.join('|'));
        };
    }

    function renderImageBlock(block) {
        const preview = block.imagePath
            ? imgWithFallback(block.imagePath, ['chronicle/inline', 'chronicle/covers', 'chronicle/gallery'], 'chronicle-editor-drop__preview')
            : '';

        return `
            <div class="chronicle-editor-drop mb-2" data-upload-zone data-upload-kind="inline">
                ${preview}
                <p class="chronicle-editor-drop__hint">Картинка — перетащите или нажмите</p>
                <input type="file" accept="image/*" hidden data-upload-input>
            </div>
            <input type="text" class="form-control mb-2" data-block-field="caption" value="${escapeAttr(block.caption || '')}" placeholder="Подпись">
            <input type="text" class="form-control" data-block-field="alt" value="${escapeAttr(block.alt || '')}" placeholder="Alt">`;
    }

    function renderGalleryBlock(block) {
        const images = (block.images || []).map((img) => {
            ensureClientId(block);
            if (!img._clientId) img._clientId = uid();

            return `
            <div class="chronicle-gallery-item" data-gallery-item data-client-id="${img._clientId}">
                ${img.imagePath ? imgWithFallback(img.imagePath, ['chronicle/gallery', 'chronicle/inline', 'chronicle/covers']) : ''}
                <input type="text" class="form-control form-control-sm mt-1" data-gallery-caption placeholder="Подпись" value="${escapeAttr(img.caption || '')}">
                <button type="button" class="btn btn-sm btn-link text-danger" data-gallery-remove>удалить</button>
            </div>`;
        }).join('');

        return `
            <div class="chronicle-gallery-grid mb-2">${images}</div>
            <div class="chronicle-editor-drop" data-upload-zone data-upload-kind="gallery">
                <p class="chronicle-editor-drop__hint">+ фото в галерею</p>
                <input type="file" accept="image/*" multiple hidden data-upload-input>
            </div>`;
    }

    function removeBlock(clientId) {
        const idx = state.blocks.findIndex((b) => b._clientId === clientId);
        if (idx < 0) return;
        state.blocks.splice(idx, 1);
        renderBlocks({ restoreFocus: true });
        markDirty();
    }

    async function handleUpload(file, kind, clientId) {
        const path = await uploadFile(file, kind);

        if (kind === 'cover') {
            state.coverImagePath = path;
            if (els.coverPreview) {
                els.coverPreview.hidden = false;
                els.coverPreview.src = `/uploads/chronicle/covers/${path}`;
            }
            markDirty();
            return;
        }

        const block = findBlock(clientId);
        if (!block) return;

        if (kind === 'gallery') {
            block.images = block.images || [];
            block.images.push({
                _clientId: uid(),
                imagePath: path,
                caption: '',
                alt: '',
                sortOrder: block.images.length * 10,
            });
        } else {
            block.imagePath = path;
        }

        renderBlocks({ restoreFocus: true });
        markDirty();
    }

    function addBlock(type) {
        const block = { _clientId: uid(), type, sortOrder: state.blocks.length * 10 };
        if (type === 'gallery') block.images = [];
        if (type === 'heading') block.headingLevel = 2;
        state.blocks.push(block);
        renderBlocks({ restoreFocus: true });
        markDirty();
    }

    function escapeHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function escapeAttr(str) {
        return escapeHtml(str).replace(/"/g, '&quot;');
    }

    // Event delegation — stable after re-render
    els.blocksList?.addEventListener('click', (e) => {
        const target = e.target;
        if (!(target instanceof Element)) return;

        const removeBtn = target.closest('[data-remove-block]');
        if (removeBtn) {
            e.preventDefault();
            e.stopPropagation();
            const card = removeBtn.closest('[data-block-card]');
            if (card instanceof HTMLElement && card.dataset.clientId) {
                removeBlock(card.dataset.clientId);
            }
            return;
        }

        const galleryRemove = target.closest('[data-gallery-remove]');
        if (galleryRemove) {
            e.preventDefault();
            e.stopPropagation();
            const item = galleryRemove.closest('[data-gallery-item]');
            const card = galleryRemove.closest('[data-block-card]');
            if (!(item instanceof HTMLElement) || !(card instanceof HTMLElement)) return;
            const block = findBlock(card.dataset.clientId);
            if (!block?.images) return;
            block.images = block.images.filter((img) => img._clientId !== item.dataset.clientId);
            renderBlocks({ restoreFocus: true });
            markDirty();
            return;
        }

        const zone = target.closest('[data-upload-zone]');
        if (zone && !target.closest('[data-gallery-remove]')) {
            const input = zone.querySelector('[data-upload-input]');
            if (input instanceof HTMLInputElement && target !== input) {
                input.click();
            }
        }
    });

    els.blocksList?.addEventListener('input', (e) => {
        const target = e.target;
        if (!(target instanceof HTMLElement) || !target.matches('[data-block-field], [data-gallery-caption]')) return;
        if (target instanceof HTMLTextAreaElement) {
            autosizeTextarea(target);
        }
        markDirty();
    });

    els.blocksList?.addEventListener('change', async (e) => {
        const target = e.target;
        if (!(target instanceof HTMLInputElement) || !target.matches('[data-upload-input]')) return;

        const zone = target.closest('[data-upload-zone]');
        const card = target.closest('[data-block-card]');
        const kind = zone instanceof HTMLElement ? zone.dataset.uploadKind || 'inline' : 'inline';
        const clientId = card instanceof HTMLElement ? card.dataset.clientId : null;
        const files = [...(target.files || [])];

        target.value = '';

        for (const file of files) {
            try {
                await handleUpload(file, kind, clientId);
            } catch (err) {
                alert('Ошибка загрузки');
                console.error(err);
            }
        }
    });

    els.blocksList?.addEventListener('dragover', (e) => {
        const zone = e.target instanceof Element ? e.target.closest('[data-upload-zone]') : null;
        if (!zone) return;
        e.preventDefault();
        zone.classList.add('is-dragover');
    });

    els.blocksList?.addEventListener('dragleave', (e) => {
        const zone = e.target instanceof Element ? e.target.closest('[data-upload-zone]') : null;
        if (zone) zone.classList.remove('is-dragover');
    });

    els.blocksList?.addEventListener('drop', async (e) => {
        const zone = e.target instanceof Element ? e.target.closest('[data-upload-zone]') : null;
        if (!zone) return;
        e.preventDefault();
        zone.classList.remove('is-dragover');
        const card = zone.closest('[data-block-card]');
        const kind = zone.dataset.uploadKind || 'inline';
        const clientId = card instanceof HTMLElement ? card.dataset.clientId : null;
        const files = [...(e.dataTransfer?.files || [])];

        for (const file of files) {
            if (!file.type.startsWith('image/')) continue;
            try {
                await handleUpload(file, kind, clientId);
            } catch (err) {
                alert('Ошибка загрузки');
                console.error(err);
            }
        }
    });

    document.querySelectorAll('[data-field]').forEach((field) => {
        field.addEventListener(field.dataset.field === 'tagIds' ? 'change' : 'input', markDirty);
    });

    document.querySelectorAll('[data-add-block]').forEach((btn) => {
        btn.addEventListener('click', () => addBlock(btn.dataset.addBlock || 'paragraph'));
    });

    document.querySelector('[data-action="publish"]')?.addEventListener('click', () => publish('publish'));
    document.querySelector('[data-action="preview"]')?.addEventListener('click', async () => {
        await save();
        window.open(cfg.previewUrl, '_blank');
    });
    document.querySelector('[data-action="copy-short"]')?.addEventListener('click', async () => {
        const url = `${window.location.origin}${cfg.shortUrl}`;
        try {
            await navigator.clipboard.writeText(url);
            setStatus('Ссылка скопирована');
        } catch {
            prompt('Короткая ссылка:', url);
        }
    });

    if (els.coverDrop) {
        els.coverDrop.addEventListener('click', (e) => {
            if (e.target instanceof Element && e.target.closest('[data-upload-input]')) return;
            els.coverDrop.querySelector('[data-upload-input]')?.click();
        });
        els.coverDrop.addEventListener('dragover', (e) => {
            e.preventDefault();
            els.coverDrop.classList.add('is-dragover');
        });
        els.coverDrop.addEventListener('dragleave', () => els.coverDrop.classList.remove('is-dragover'));
        els.coverDrop.addEventListener('drop', async (e) => {
            e.preventDefault();
            els.coverDrop.classList.remove('is-dragover');
            const file = e.dataTransfer?.files?.[0];
            if (file) {
                try {
                    await handleUpload(file, 'cover', null);
                } catch (err) {
                    alert('Ошибка загрузки');
                    console.error(err);
                }
            }
        });
        els.coverDrop.querySelector('[data-upload-input]')?.addEventListener('change', async (e) => {
            const input = e.target;
            if (!(input instanceof HTMLInputElement)) return;
            const file = input.files?.[0];
            input.value = '';
            if (file) {
                try {
                    await handleUpload(file, 'cover', null);
                } catch (err) {
                    alert('Ошибка загрузки');
                    console.error(err);
                }
            }
        });
    }

    function appendTagCheckbox(tag, checked = true) {
        const list = document.querySelector('[data-tags-list]');
        if (!(list instanceof HTMLElement)) return;

        const existing = list.querySelector(`[data-field="tagIds"][value="${tag.id}"]`);
        if (existing instanceof HTMLInputElement) {
            existing.checked = checked;
            return;
        }

        const label = document.createElement('label');
        label.className = 'chronicle-editor-tag';
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.value = String(tag.id);
        checkbox.dataset.field = 'tagIds';
        checkbox.checked = checked;
        label.appendChild(checkbox);
        label.append(` ${tag.name}`);
        list.appendChild(label);
    }

    function renderTagList(tags, selectedTagIds) {
        const list = document.querySelector('[data-tags-list]');
        if (!(list instanceof HTMLElement)) return;

        const selected = new Set((selectedTagIds || []).map((id) => Number(id)));
        list.querySelectorAll('.chronicle-editor-tag').forEach((el) => el.remove());
        list.querySelector('[data-tags-loading]')?.remove();

        if (!tags.length) {
            const empty = document.createElement('p');
            empty.className = 'text-muted mb-0';
            empty.textContent = 'Пока нет ключевых слов — добавьте первое ниже.';
            list.appendChild(empty);
            return;
        }

        tags.forEach((tag) => appendTagCheckbox(tag, selected.has(Number(tag.id))));
    }

    async function loadTags() {
        if (!cfg.tagsUrl) return;

        try {
            const res = await fetch(cfg.tagsUrl, { credentials: 'same-origin' });
            const json = await res.json();
            if (!res.ok || !json.ok) {
                throw new Error(json.error || 'Не удалось загрузить ключевые слова');
            }

            renderTagList(json.tags || [], json.selectedTagIds || []);
        } catch (err) {
            const list = document.querySelector('[data-tags-list]');
            list?.querySelector('[data-tags-loading]')?.remove();
            if (list instanceof HTMLElement) {
                const error = document.createElement('p');
                error.className = 'text-danger mb-0';
                error.textContent = err instanceof Error ? err.message : 'Не удалось загрузить ключевые слова';
                list.appendChild(error);
            }
            console.error(err);
        }
    }

    async function bootstrapEditor() {
        if (bootstrapped) return;
        bootstrapped = true;
        setStatus('Загрузка…', 'pending');

        const tagsPromise = loadTags();
        let data = cfg.data;

        if (!data && cfg.dataUrl) {
            try {
                const res = await fetch(cfg.dataUrl, { credentials: 'same-origin' });
                const json = await res.json();
                if (!res.ok || !json.ok || !json.data) {
                    throw new Error(json.error || 'Не удалось загрузить запись');
                }
                data = json.data;
            } catch (err) {
                setStatus('Ошибка загрузки', 'error');
                console.error(err);
                alert(err instanceof Error ? err.message : 'Не удалось загрузить запись');
                return;
            }
        }

        if (!data) {
            setStatus('Ошибка загрузки', 'error');
            return;
        }

        state = initState(data);
        await tagsPromise;
        renderBlocks();
        setStatus('Готово');
    }

    async function createKeyword() {
        const input = document.querySelector('[data-tag-create-input]');
        if (!(input instanceof HTMLInputElement)) return;

        const name = input.value.trim();
        if (!name) return;

        const button = document.querySelector('[data-tag-create]');
        if (button instanceof HTMLButtonElement) {
            button.disabled = true;
        }

        try {
            const res = await fetch(cfg.tagCreateUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name }),
            });
            const data = await res.json();
            if (!res.ok || !data.tag) {
                throw new Error(data.error || 'Не удалось создать ключевое слово');
            }

            appendTagCheckbox(data.tag, true);
            input.value = '';
            markDirty();
        } catch (err) {
            alert(err instanceof Error ? err.message : 'Не удалось создать ключевое слово');
            console.error(err);
        } finally {
            if (button instanceof HTMLButtonElement) {
                button.disabled = false;
            }
        }
    }

    document.querySelector('[data-tag-create]')?.addEventListener('click', () => {
        createKeyword();
    });

    document.querySelector('[data-tag-create-input]')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            createKeyword();
        }
    });

    window.addEventListener('beforeunload', (e) => {
        if (dirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    bootstrapEditor();
})();
