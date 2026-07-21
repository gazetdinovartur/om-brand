document.addEventListener('DOMContentLoaded', () => {
    whenSortableReady(() => {
        initCaseIndexSortable();
        initCaseGallerySortable();
    });
    // If CDN Sortable never loads, fall back to native HTML5 DnD
    window.setTimeout(() => {
        if (typeof Sortable === 'undefined') {
            initCaseGalleryNativeSortable();
        }
    }, 700);
    initCaseGalleryUploadDrop();
    initCasePresentationToggle();
    initCaseTextareaGrow();
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

function findGalleryLists() {
    const lists = [];

    const addList = (list) => {
        if (list instanceof HTMLElement && !lists.includes(list)) {
            lists.push(list);
        }
    };

    const items = document.querySelectorAll(
        '.case-gallery-collection .field-collection-item, .form-group.case-gallery-collection .field-collection-item',
    );

    if (items.length > 0) {
        items.forEach((item) => addList(item.parentElement));
        return lists;
    }

    // Empty gallery — prepare the insertion container for future items
    document.querySelectorAll('.case-gallery-collection, .form-group.case-gallery-collection').forEach((root) => {
        addList(root.querySelector('.accordion > .form-widget-compound > [data-empty-collection]'));
        addList(root.querySelector('.accordion > .form-widget-compound'));
        addList(root.querySelector('.ea-form-collection-items .form-widget-compound'));
    });

    return lists;
}

function getGalleryItems(list) {
    return [...list.children].filter(
        (node) => node instanceof HTMLElement && node.classList.contains('field-collection-item'),
    );
}

function polishGalleryItem(item) {
    if (!(item instanceof HTMLElement)) {
        return;
    }
    item.classList.add('case-gallery-item');
    item.draggable = false;

    if (!item.querySelector(':scope > .case-gallery-drag')) {
        const handle = document.createElement('span');
        handle.className = 'case-gallery-drag';
        handle.setAttribute('role', 'button');
        handle.setAttribute('aria-label', 'Перетащить');
        handle.title = 'Перетащить';
        handle.textContent = '⋮⋮';
        item.insertBefore(handle, item.firstChild);
    }

    const headerDelete = item.querySelector('.accordion-header .field-collection-delete-button');
    if (headerDelete && !item.querySelector(':scope > .case-gallery-delete')) {
        const del = document.createElement('button');
        del.type = 'button';
        del.className = 'case-gallery-delete';
        del.setAttribute('aria-label', 'Удалить');
        del.title = 'Удалить';
        del.textContent = '×';
        del.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            headerDelete.click();
        });
        item.appendChild(del);
    }
}

function syncGallerySortOrders(list) {
    getGalleryItems(list).forEach((item, index) => {
        const input = item.querySelector('input[name*="[sortOrder]"]');
        if (input instanceof HTMLInputElement) {
            input.value = String(index);
        }
    });
}

function refreshGalleryList(list) {
    getGalleryItems(list).forEach(polishGalleryItem);
    syncGallerySortOrders(list);
    initCaseGalleryUploadDrop();
}

function initCaseIndexSortable() {
    const tbody = document.querySelector('.ea-index table.datagrid tbody, .ea-index table.table tbody');
    if (!tbody || typeof Sortable === 'undefined' || !tbody.querySelector('[data-case-id]')) {
        return;
    }

    Sortable.create(tbody, {
        handle: '.case-admin-drag',
        animation: 160,
        ghostClass: 'case-admin-row-ghost',
        chosenClass: 'case-admin-row-chosen',
        onEnd: async () => {
            const ids = [...tbody.querySelectorAll('[data-case-id]')].map((el) => el.getAttribute('data-case-id'));
            const token = document.querySelector('meta[name="case-reorder-csrf"]')?.getAttribute('content') || '';
            const url = document.querySelector('meta[name="case-reorder-url"]')?.getAttribute('content') || '';
            if (!url || ids.length === 0) {
                return;
            }
            try {
                await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ ids, _token: token }),
                });
            } catch {
                // keep UI order
            }
        },
    });
}

function initCaseGallerySortable() {
    if (typeof Sortable === 'undefined') {
        return;
    }

    const ensureList = (list) => {
        if (!(list instanceof HTMLElement)) {
            return;
        }
        refreshGalleryList(list);
        if (list.dataset.caseGallerySortable === 'sortable') {
            return;
        }
        list.dataset.caseGallerySortable = 'sortable';
        Sortable.create(list, {
            handle: '.case-gallery-drag',
            draggable: '.field-collection-item',
            animation: 150,
            ghostClass: 'case-gallery-ghost',
            chosenClass: 'case-gallery-chosen',
            direction: 'vertical',
            onEnd: () => syncGallerySortOrders(list),
        });
    };

    findGalleryLists().forEach(ensureList);

    document.addEventListener('ea.collection.item-added', (event) => {
        window.setTimeout(() => {
            const newEl = event.detail?.newElement;
            if (newEl instanceof HTMLElement && newEl.closest('.case-gallery-collection, .form-group.case-gallery-collection')) {
                ensureList(newEl.parentElement);
            }
            findGalleryLists().forEach(ensureList);
        }, 0);
    });
}

/** HTML5 DnD — works without Sortable CDN */
function initCaseGalleryNativeSortable() {
    findGalleryLists().forEach((list) => {
        if (list.dataset.caseGallerySortable === 'sortable' || list.dataset.caseGalleryNative === '1') {
            return;
        }
        list.dataset.caseGalleryNative = '1';
        refreshGalleryList(list);
        bindNativeGalleryDnD(list);

        document.addEventListener('ea.collection.item-added', () => {
            window.setTimeout(() => {
                refreshGalleryList(list);
                bindNativeGalleryDnD(list);
            }, 0);
        });
    });
}

function bindNativeGalleryDnD(list) {
    let dragItem = null;

    list.querySelectorAll('.case-gallery-drag').forEach((handle) => {
        if (handle.dataset.nativeBound === '1') {
            return;
        }
        handle.dataset.nativeBound = '1';

        handle.addEventListener('pointerdown', () => {
            const item = handle.closest('.field-collection-item');
            if (item) {
                item.draggable = true;
            }
        });

        handle.addEventListener('pointerup', () => {
            const item = handle.closest('.field-collection-item');
            if (item) {
                item.draggable = false;
            }
        });
    });

    if (list.dataset.nativeListBound === '1') {
        return;
    }
    list.dataset.nativeListBound = '1';

    list.addEventListener('dragstart', (event) => {
        const item = event.target instanceof Element ? event.target.closest('.field-collection-item') : null;
        if (!item || !list.contains(item)) {
            return;
        }
        dragItem = item;
        item.classList.add('case-gallery-chosen');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', 'gallery-item');
    });

    list.addEventListener('dragend', () => {
        if (dragItem) {
            dragItem.classList.remove('case-gallery-chosen');
            dragItem.draggable = false;
        }
        dragItem = null;
        syncGallerySortOrders(list);
    });

    list.addEventListener('dragover', (event) => {
        event.preventDefault();
        const over = event.target instanceof Element ? event.target.closest('.field-collection-item') : null;
        if (!dragItem || !over || over === dragItem || !list.contains(over)) {
            return;
        }
        const rect = over.getBoundingClientRect();
        const before = event.clientY < rect.top + rect.height / 2;
        list.insertBefore(dragItem, before ? over : over.nextSibling);
    });

    list.addEventListener('drop', (event) => {
        event.preventDefault();
        syncGallerySortOrders(list);
    });
}

function initCaseGalleryUploadDrop() {
    document.querySelectorAll('.case-gallery-collection .ea-fileupload').forEach((widget) => {
        if (widget.dataset.caseDropReady === '1') {
            return;
        }
        widget.dataset.caseDropReady = '1';
        widget.classList.add('case-gallery-upload');

        const input = widget.querySelector('input[type="file"]');
        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        const prevent = (event) => {
            event.preventDefault();
            event.stopPropagation();
        };

        ['dragenter', 'dragover'].forEach((type) => {
            widget.addEventListener(type, (event) => {
                prevent(event);
                widget.classList.add('is-dragover');
            });
        });

        ['dragleave', 'drop'].forEach((type) => {
            widget.addEventListener(type, (event) => {
                prevent(event);
                if (type === 'dragleave' && widget.contains(event.relatedTarget)) {
                    return;
                }
                widget.classList.remove('is-dragover');
            });
        });

        widget.addEventListener('drop', (event) => {
            const files = event.dataTransfer?.files;
            if (!files || files.length === 0) {
                return;
            }
            const image = [...files].find((file) => file.type.startsWith('image/'));
            if (!image) {
                return;
            }
            const transfer = new DataTransfer();
            transfer.items.add(image);
            input.files = transfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
}

function initCasePresentationToggle() {
    const mode = findPresentationModeControl();
    if (!mode) {
        return;
    }

    const sync = () => {
        const value = normalizePresentationMode(readPresentationModeValue(mode));
        const showVideo = value === 'video' || value === 'video_audio';
        const showAudio = value === 'audio' || value === 'video_audio';
        setMediaVisibility('video', showVideo);
        setMediaVisibility('audio', showAudio);
    };

    mode.addEventListener('change', sync);
    mode.addEventListener('input', sync);
    sync();
}

function findPresentationModeControl() {
    return (
        document.querySelector('select[data-case-presentation-mode]') ||
        document.querySelector('select.case-admin-presentation-mode') ||
        document.querySelector('select[name*="[presentationMode]"]')
    );
}

function readPresentationModeValue(control) {
    if (control instanceof HTMLSelectElement || control instanceof HTMLInputElement) {
        return control.value;
    }
    return '';
}

function normalizePresentationMode(raw) {
    const value = String(raw || '').trim().toLowerCase();
    if (!value || value === 'none' || value === 'null') {
        return 'none';
    }
    if (value === 'video' || value.endsWith('::video')) {
        return 'video';
    }
    if (value === 'audio' || value.endsWith('::audio')) {
        return 'audio';
    }
    if (value === 'video_audio' || value === 'videoaudio' || value.endsWith('::videoaudio')) {
        return 'video_audio';
    }
    return value;
}

function setMediaVisibility(kind, visible) {
    const nodes = new Set();
    document.querySelectorAll(`[data-case-media="${kind}"]`).forEach((el) => nodes.add(el));
    document.querySelectorAll(`.js-case-media-${kind}`).forEach((el) => {
        nodes.add(el.closest('.form-fieldset') || el.closest('.form-group') || el);
    });
    nodes.forEach((el) => {
        el.hidden = !visible;
        el.classList.toggle('is-hidden', !visible);
        el.style.display = visible ? '' : 'none';
    });
}

function initCaseTextareaGrow() {
    const grow = (ta) => {
        if (!(ta instanceof HTMLTextAreaElement)) {
            return;
        }
        ta.style.height = 'auto';
        ta.style.height = `${Math.max(ta.scrollHeight, 48)}px`;
    };
    document.querySelectorAll('.ea-new textarea.form-control, .ea-edit textarea.form-control').forEach((ta) => {
        grow(ta);
        ta.addEventListener('input', () => grow(ta));
    });
}
