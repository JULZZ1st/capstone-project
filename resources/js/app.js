import './bootstrap';
import './charts';

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

const updateReferenceRequirement = (form) => {
    const method = form.querySelector('[data-payment-method]')?.value;
    const reference = form.querySelector('[data-reference-number]');
    const referenceField = form.querySelector('[data-reference-field]');
    const referenceLabel = form.querySelector('[data-reference-label]');

    if (! reference || ! referenceField) {
        return;
    }

    const isElectronic = method !== '' && method !== 'cash';
    const methodLabel = form.querySelector('[data-payment-method] option:checked')?.textContent?.trim();

    referenceField.hidden = ! isElectronic;
    reference.disabled = ! isElectronic;
    reference.required = isElectronic;
    referenceLabel.textContent = methodLabel && method !== 'other'
        ? `${methodLabel} ${method === 'check' || method === 'money_order' ? 'No.' : 'Reference No.'}`
        : 'External Payment Reference No.';
    reference.placeholder = isElectronic
        ? 'Enter the reference from the payment provider'
        : '';

    if (! isElectronic) {
        reference.value = '';
    }
};

const subcategoryOptionsFor = (row) => {
    const section = row.closest('[data-contribution-section]');

    if (! section?.dataset.subcategories) {
        return {};
    }

    try {
        return JSON.parse(section.dataset.subcategories);
    } catch {
        return {};
    }
};

const updateSubcategorySelect = (row, preserveSelection = true) => {
    const category = row.querySelector('[data-item-category]')?.value;
    const subcategory = row.querySelector('[data-item-subcategory]');
    const customCategory = row.querySelector('[data-item-custom-category]');
    const customCategoryInput = customCategory?.querySelector('input');
    const customSubcategory = row.querySelector('[data-item-custom-subcategory]');
    const customSubcategoryInput = customSubcategory?.querySelector('input');

    if (! subcategory) {
        return;
    }

    const selected = preserveSelection ? (subcategory.value || subcategory.dataset.selectedSubcategory || '') : '';
    const options = category ? (subcategoryOptionsFor(row)[category] || []) : [];

    if (customCategory && customCategoryInput) {
        const isOtherCategory = category === '__other__';
        customCategory.hidden = ! isOtherCategory;
        customCategoryInput.disabled = ! isOtherCategory;
        if (! isOtherCategory && ! preserveSelection) customCategoryInput.value = '';
    }

    subcategory.replaceChildren();

    if (! category) {
        subcategory.append(new Option('Select category first', ''));
        subcategory.disabled = true;
        subcategory.dataset.selectedSubcategory = '';
        if (customSubcategory && customSubcategoryInput) {
            customSubcategory.hidden = true;
            customSubcategoryInput.disabled = true;
            if (! preserveSelection) customSubcategoryInput.value = '';
        }
        return;
    }

    subcategory.append(new Option('Select', ''));

    options.forEach((option) => {
        subcategory.append(new Option(option.label, option.key, false, option.key === selected));
    });

    subcategory.append(new Option('Other', '__other__', false, selected === '__other__'));

    if (! options.some((option) => option.key === selected) && selected !== '__other__') {
        subcategory.value = '';
    }

    subcategory.disabled = false;
    subcategory.dataset.selectedSubcategory = subcategory.value;

    if (customSubcategory && customSubcategoryInput) {
        const isOtherSubcategory = subcategory.value === '__other__';
        customSubcategory.hidden = ! isOtherSubcategory;
        customSubcategoryInput.disabled = ! isOtherSubcategory;
        if (! isOtherSubcategory && ! preserveSelection) customSubcategoryInput.value = '';
    }
};

const initializeSubcategorySelects = (root = document) => {
    root.querySelectorAll('[data-item-row]').forEach((row) => updateSubcategorySelect(row));
};

const inventorySubcategoriesFor = (form) => {
    try {
        return JSON.parse(form.dataset.subcategories || '{}');
    } catch {
        return {};
    }
};

const updateInventorySubcategorySelect = (form, preserveSelection = true) => {
    const category = form.querySelector('[data-inventory-category]')?.value;
    const subcategory = form.querySelector('[data-inventory-subcategory]');
    const customCategory = form.querySelector('[data-inventory-custom-category]');
    const customCategoryInput = customCategory?.querySelector('input');
    const customSubcategory = form.querySelector('[data-inventory-custom-subcategory]');
    const customSubcategoryInput = customSubcategory?.querySelector('input');

    if (! subcategory) {
        return;
    }

    const selected = preserveSelection ? (subcategory.value || subcategory.dataset.selectedSubcategory || '') : '';
    const options = category ? (inventorySubcategoriesFor(form)[category] || []) : [];

    if (customCategory && customCategoryInput) {
        const isOtherCategory = category === '__other__';
        customCategory.hidden = ! isOtherCategory;
        customCategoryInput.disabled = ! isOtherCategory;
        if (! isOtherCategory && ! preserveSelection) customCategoryInput.value = '';
    }

    subcategory.replaceChildren();

    if (! category) {
        subcategory.append(new Option('Select category first', ''));
        subcategory.disabled = true;
        subcategory.value = '';
        if (customSubcategory && customSubcategoryInput) {
            customSubcategory.hidden = true;
            customSubcategoryInput.disabled = true;
            if (! preserveSelection) customSubcategoryInput.value = '';
        }
        return;
    }

    subcategory.append(new Option('Select subcategory', ''));
    options.forEach((option) => {
        subcategory.append(new Option(option.label, option.id, false, String(option.id) === String(selected)));
    });

    subcategory.append(new Option('Other', '__other__', false, selected === '__other__'));

    subcategory.disabled = false;
    subcategory.dataset.selectedSubcategory = subcategory.value;

    if (customSubcategory && customSubcategoryInput) {
        const isOtherSubcategory = subcategory.value === '__other__';
        customSubcategory.hidden = ! isOtherSubcategory;
        customSubcategoryInput.disabled = ! isOtherSubcategory;
        if (! isOtherSubcategory && ! preserveSelection) customSubcategoryInput.value = '';
    }
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

document.querySelectorAll('[data-contribution-form]').forEach((form) => {
    updateContributionSections(form);
    updateReferenceRequirement(form);
});
document.querySelectorAll('[data-inventory-item-form]').forEach((form) => updateInventorySubcategorySelect(form));
initializeSubcategorySelects();

document.addEventListener('change', (event) => {
    if (event.target.matches('[data-contribution-type]')) {
        updateContributionSections(event.target.closest('[data-contribution-form]'));
    }

    if (event.target.matches('[data-payment-method]')) {
        updateReferenceRequirement(event.target.closest('[data-contribution-form]'));
    }

    if (event.target.matches('[data-item-category]')) {
        updateSubcategorySelect(event.target.closest('[data-item-row]'), false);
    }

    if (event.target.matches('[data-item-subcategory]')) {
        event.target.dataset.selectedSubcategory = event.target.value;
        updateSubcategorySelect(event.target.closest('[data-item-row]'));
    }

    if (event.target.matches('[data-inventory-category]')) {
        updateInventorySubcategorySelect(event.target.closest('[data-inventory-item-form]'), false);
    }

    if (event.target.matches('[data-inventory-subcategory]')) {
        event.target.dataset.selectedSubcategory = event.target.value;
        updateInventorySubcategorySelect(event.target.closest('[data-inventory-item-form]'));
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
            field.dataset.selectedSubcategory = '';
        });

        container.append(row);
        reindexItemRows(container);
        updateSubcategorySelect(row, false);
    }

    if (removeButton) {
        const container = removeButton.closest('[data-item-rows]');

        if (container.querySelectorAll('[data-item-row]').length > 1) {
            removeButton.closest('[data-item-row]').remove();
            reindexItemRows(container);
        }
    }
});

const updateDonorCreateForm = (form) => {
    const donorType = form.querySelector('[data-donor-type]')?.value;
    const organizationNameWrapper = form.querySelector('[data-organization-name-field]');
    const organizationNameInput = organizationNameWrapper?.querySelector('[name="organization_name"]');
    const individualNameWrappers = form.querySelectorAll('[data-individual-name-field]');
    const contactPersonWrapper = form.querySelector('[data-contact-person-field]');
    const contactPersonInput = contactPersonWrapper?.querySelector('[name="contact_person"]');
    const isOrganization = donorType === 'organization';

    if (organizationNameWrapper && organizationNameInput) {
        organizationNameWrapper.hidden = ! isOrganization;
        organizationNameInput.disabled = ! isOrganization;
        organizationNameInput.required = isOrganization;

        if (! isOrganization) {
            organizationNameInput.value = '';
        }
    }

    individualNameWrappers.forEach((wrapper) => {
        const input = wrapper.querySelector('input');

        wrapper.hidden = isOrganization;

        if (input) {
            input.disabled = isOrganization;
            input.required = ! isOrganization;

            if (isOrganization) {
                input.value = '';
            }
        }
    });

    if (contactPersonWrapper && contactPersonInput) {
        contactPersonWrapper.hidden = ! isOrganization;
        contactPersonInput.disabled = ! isOrganization;

        if (! isOrganization) {
            contactPersonInput.value = '';
        }
    }
};

document.querySelectorAll('[data-donor-form]').forEach(updateDonorCreateForm);

document.addEventListener('change', (event) => {
    if (event.target.matches('[data-donor-type]')) {
        updateDonorCreateForm(event.target.closest('[data-donor-form]'));
    }
});
