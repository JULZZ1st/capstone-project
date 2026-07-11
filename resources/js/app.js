import './bootstrap';

document.addEventListener('click', (event) => {
    const openButton = event.target.closest('[data-dialog-open]');
    const closeButton = event.target.closest('[data-dialog-close]');

    if (openButton) {
        const dialog = document.getElementById(openButton.dataset.dialogOpen);

        if (dialog instanceof HTMLDialogElement) {
            dialog.showModal();
        }
    }

    if (closeButton) {
        const dialog = document.getElementById(closeButton.dataset.dialogClose);

        if (dialog instanceof HTMLDialogElement) {
            dialog.close();
        }
    }
});

document.addEventListener('cancel', (event) => {
    if (event.target instanceof HTMLDialogElement) {
        event.target.close();
    }
}, true);

const updateContributionSections = (form) => {
    const type = form.querySelector('[data-contribution-type]')?.value;

    form.querySelectorAll('[data-contribution-section]').forEach((section) => {
        section.hidden = section.dataset.contributionSection !== type;
    });
};

const reindexItemRows = (container) => {
    container.querySelectorAll('[data-item-row]').forEach((row, index) => {
        row.querySelectorAll('[name]').forEach((field) => {
            field.name = field.name.replace(/items\[\d+\]/, `items[${index}]`);
        });

        row.querySelectorAll('[id]').forEach((field) => {
            field.id = field.id.replace(/items_\d+_/, `items_${index}_`);
        });

        row.querySelectorAll('label[for]').forEach((label) => {
            label.htmlFor = label.htmlFor.replace(/items_\d+_/, `items_${index}_`);
        });
    });
};

document.querySelectorAll('[data-contribution-form]').forEach(updateContributionSections);

document.addEventListener('change', (event) => {
    if (event.target.matches('[data-contribution-type]')) {
        updateContributionSections(event.target.closest('[data-contribution-form]'));
    }
});

document.addEventListener('click', (event) => {
    const addButton = event.target.closest('[data-add-item-row]');
    const removeButton = event.target.closest('[data-remove-item-row]');

    if (addButton) {
        const container = addButton.closest('[data-contribution-section]').querySelector('[data-item-rows]');
        const template = container.querySelector('[data-item-row]');
        const row = template.cloneNode(true);

        row.querySelectorAll('input, textarea').forEach((field) => {
            field.value = '';
        });

        row.querySelectorAll('select').forEach((field) => {
            field.selectedIndex = 0;
        });

        container.append(row);
        reindexItemRows(container);
    }

    if (removeButton) {
        const container = removeButton.closest('[data-item-rows]');

        if (container.querySelectorAll('[data-item-row]').length > 1) {
            removeButton.closest('[data-item-row]').remove();
            reindexItemRows(container);
        }
    }
});
