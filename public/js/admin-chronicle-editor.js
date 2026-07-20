(() => {
    const cfg = window.__CHRONICLE_EDITOR__;
    if (!cfg) return;

    let state = initState(cfg.data);
    let saveTimer = null;
    let saving = false;
    let dirty = false;
    let sortable = null;

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
        const next = structuredClone(data);
        (next.blocks || []).forEach(ensureClientId);

        return next;
    }

    function findBlock(clientId) {
        return state.blocks.find((b) => b._clientId === clientId) ?? null;
    }

    function markDirty() {
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
        if (saving) return;
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
        state = initState(json.data);
        renderBlocks();
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
        if (!els.blocksList) return;

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
            body = `<textarea class="form-control" rows="5" data-block-field="body" placeholder="Markdown: **жирный**, *курсив*, [ссылка](url)">${escapeHtml(block.body || '')}</textarea>`;
        } else if (type === 'heading') {
            body = `
                <select class="form-select form-select-sm mb-2" data-block-field="headingLevel">
                    <option value="2" ${block.headingLevel == 2 ? 'selected' : ''}>H2</option>
                    <option value="3" ${block.headingLevel == 3 ? 'selected' : ''}>H3</option>
                </select>
                <input type="text" class="form-control" data-block-field="body" value="${escapeAttr(block.body || '')}" placeholder="Заголовок">`;
        } else if (type === 'quote') {
            body = `
                <textarea class="form-control mb-2" rows="3" data-block-field="body">${escapeHtml(block.body || '')}</textarea>
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

        return card;
    }

    function renderImageBlock(block) {
        const preview = block.imagePath
            ? `<img src="/uploads/chronicle/inline/${block.imagePath}" alt="" class="chronicle-editor-drop__preview">`
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
                ${img.imagePath ? `<img src="/uploads/chronicle/gallery/${img.imagePath}" alt="">` : ''}
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

    window.addEventListener('beforeunload', (e) => {
        if (dirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    renderBlocks();
    setStatus('Готово');
})();
