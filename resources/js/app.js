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

const donorWizardSteps = (wizard) => Array.from(wizard.querySelectorAll('[data-wizard-step]'));
const donorWizardForm = (target) => {
    if (! target) {
        return null;
    }

    const form = target.closest('[data-donor-wizard-form]');

    if (form) {
        return form.querySelector('[data-donor-form]');
    }

    return target.closest('[data-donor-form]');
};

const donorWizardControls = (wizard) => {
    const form = wizard.closest('[data-donor-wizard-form]');

    return {
        back: form?.querySelector('[data-wizard-back]') ?? null,
        next: form?.querySelector('[data-wizard-next]') ?? null,
        submit: form?.querySelector('[data-wizard-submit]') ?? null,
        error: form?.querySelector('[data-wizard-error]') ?? wizard.querySelector('[data-wizard-error]') ?? null,
    };
};

const setDonorWizardStep = (wizard, stepIndex) => {
    const steps = donorWizardSteps(wizard);
    const currentIndex = Math.max(0, Math.min(stepIndex, steps.length - 1));
    const isLastStep = currentIndex === steps.length - 1;
    const controls = donorWizardControls(wizard);

    steps.forEach((step, index) => {
        step.hidden = index !== currentIndex;
    });

    wizard.querySelectorAll('[data-wizard-indicator]').forEach((indicator, index) => {
        const number = indicator.querySelector('[data-wizard-number]');
        const isCurrent = index === currentIndex;
        const isComplete = index < currentIndex;

        indicator.classList.toggle('text-blue-700', isCurrent || isComplete);
        indicator.classList.toggle('text-slate-500', ! isCurrent && ! isComplete);
        indicator.setAttribute('aria-current', isCurrent ? 'step' : 'false');
        number.classList.toggle('bg-blue-700', isCurrent || isComplete);
        number.classList.toggle('text-white', isCurrent || isComplete);
        number.classList.toggle('bg-slate-100', ! isCurrent && ! isComplete);
        number.classList.toggle('text-slate-500', ! isCurrent && ! isComplete);
    });

    wizard.querySelector('[data-wizard-current]').textContent = String(currentIndex + 1);
    wizard.querySelector('[data-wizard-title]').textContent = steps[currentIndex].dataset.wizardStepTitle;
    if (controls.back) controls.back.disabled = currentIndex === 0;
    if (controls.next) controls.next.hidden = isLastStep;
    if (controls.submit) controls.submit.hidden = ! isLastStep;
    wizard.dataset.currentWizardStep = String(currentIndex);
};

const invalidDonorWizardField = (step) => Array.from(step.querySelectorAll('input, select, textarea'))
    .find((field) => ! field.disabled
        && ! field.closest('[data-wizard-step]')?.hidden
        && ! field.checkValidity());

const validateDonorWizardStep = (wizard, step) => {
    const invalidField = invalidDonorWizardField(step);
    const errorMessage = donorWizardControls(wizard).error ?? wizard.querySelector('[data-wizard-error]');

    if (errorMessage) {
        errorMessage.hidden = ! invalidField;
        if (invalidField) {
            const invalidCount = Array.from(step.querySelectorAll('input, select, textarea')).filter((field) => ! field.disabled && ! field.checkValidity()).length;
            errorMessage.textContent = invalidCount > 1
                ? 'Please fill out the remaining fields.'
                : 'Please fill out the remaining field.';
        }
    }

    if (! invalidField) {
        return true;
    }

    invalidField.focus();
    invalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });

    return false;
};

document.querySelectorAll('[data-donor-form]').forEach((wizard) => {
    setDonorWizardStep(wizard, Number(wizard.dataset.wizardStartStep) || 0);
});

document.addEventListener('click', (event) => {
    const nextButton = event.target.closest('[data-wizard-next]');
    const backButton = event.target.closest('[data-wizard-back]');
    const button = nextButton ?? backButton;

    if (! button) {
        return;
    }

    const wizard = donorWizardForm(button);

    if (! wizard) {
        return;
    }

    const steps = donorWizardSteps(wizard);
    const visibleIndex = steps.findIndex((step) => ! step.hidden);
    const currentIndex = Number.isInteger(Number(wizard.dataset.currentWizardStep))
        ? Number(wizard.dataset.currentWizardStep)
        : Math.max(0, visibleIndex);

    if (nextButton && ! validateDonorWizardStep(wizard, steps[currentIndex])) {
        return;
    }

    setDonorWizardStep(wizard, currentIndex + (nextButton ? 1 : -1));
});

document.addEventListener('submit', (event) => {
    const form = event.target.closest('[data-donor-wizard-form]');

    if (! form) {
        return;
    }

    const wizard = form.querySelector('[data-donor-form]');
    const steps = donorWizardSteps(wizard);
    const invalidIndex = steps.findIndex((step) => invalidDonorWizardField(step));

    if (invalidIndex !== -1) {
        event.preventDefault();
        setDonorWizardStep(wizard, invalidIndex);
        validateDonorWizardStep(wizard, steps[invalidIndex]);
    }
});

document.addEventListener('input', (event) => {
    const wizard = donorWizardForm(event.target);

    if (wizard) {
        const error = donorWizardControls(wizard).error ?? wizard.querySelector('[data-wizard-error]');

        if (error) {
            error.hidden = true;
        }
    }
});

document.addEventListener('change', (event) => {
    const wizard = donorWizardForm(event.target);

    if (wizard) {
        const error = donorWizardControls(wizard).error ?? wizard.querySelector('[data-wizard-error]');

        if (error) {
            error.hidden = true;
        }
    }
});

const updateContributionSections = (form) => {
    const type = form.querySelector('[data-contribution-type]')?.value;

    form.querySelectorAll('[data-contribution-section]').forEach((section) => {
        const active = section.dataset.contributionSection === type;

        section.hidden = ! active;
        section.querySelectorAll('input, select, textarea, button').forEach((field) => {
            field.disabled = ! active;
        });
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
