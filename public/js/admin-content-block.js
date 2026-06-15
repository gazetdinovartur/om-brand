document.addEventListener('DOMContentLoaded', () => {
    const typeField = document.querySelector('select[name*="[type]"]');
    const collectionField = document.querySelector('.field-collection');

    const typesWithItems = ['list', 'cards', 'steps', 'footer'];
    const textOnlyTypes = ['list', 'footer'];

    const refresh = () => {
        if (!typeField) {
            return;
        }

        const type = typeField.value;

        if (collectionField) {
            const group = collectionField.closest('.field-group, .form-group, .field-collection');
            if (group) {
                group.style.display = typesWithItems.includes(type) ? '' : 'none';
            }
        }

        document.querySelectorAll('.content-block-item-title-row').forEach((row) => {
            row.style.display = textOnlyTypes.includes(type) ? 'none' : '';
        });
    };

    typeField?.addEventListener('change', refresh);
    refresh();
});
