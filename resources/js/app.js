import './bootstrap';
import './charts';

const donorSearchTimers = new WeakMap();
const donorSearchRequests = new WeakMap();
const donationFilterTimers = new WeakMap();

const customSelectMenu = (wrapper) => wrapper?.__customSelectMenu || wrapper?.querySelector('[data-custom-select-menu]');
const customSelectPortalTarget = (wrapper) => wrapper?.closest('dialog') || document.body;

const customSelectWrapperForTarget = (target) => {
    const wrapper = target.closest('[data-custom-select]');
    if (wrapper) return wrapper;

    const menu = target.closest('[data-custom-select-menu]');
    const owner = menu?.dataset.customSelectOwner;
    return owner ? document.getElementById(owner)?.closest('[data-custom-select]') : null;
};

const closeCustomSelects = (except = null) => {
    document.querySelectorAll('[data-custom-select][data-custom-select-open]').forEach((wrapper) => {
        if (wrapper !== except) {
            closeCustomSelect(wrapper);
        }
    });
};

const positionCustomSelectMenu = (wrapper) => {
    const trigger = wrapper.querySelector('[data-custom-select-trigger]');
    const menu = customSelectMenu(wrapper);

    if (! trigger || ! menu) return;

    const rect = trigger.getBoundingClientRect();
    const scaleX = trigger.offsetWidth ? rect.width / trigger.offsetWidth : 1;
    const scaleY = trigger.offsetHeight ? rect.height / trigger.offsetHeight : 1;
    menu.style.left = `${Math.round(rect.left / scaleX)}px`;
    menu.style.top = `${Math.round((rect.bottom + 4) / scaleY)}px`;
    menu.style.width = `${Math.round(rect.width / scaleX)}px`;
    menu.style.maxHeight = `${Math.max(120, (window.innerHeight - rect.bottom - 16) / scaleY)}px`;
};

const refreshCustomSelect = (select) => {
    const wrapper = select.closest('[data-custom-select]');
    const menu = customSelectMenu(wrapper);
    const optionsContainer = menu?.querySelector('[data-custom-select-options]');
    const value = wrapper?.querySelector('[data-custom-select-value]');
    const trigger = wrapper?.querySelector('[data-custom-select-trigger]');
    const empty = menu?.querySelector('[data-custom-select-empty]');

    if (! wrapper || ! menu || ! optionsContainer || ! value || ! trigger || ! empty) return;

    const selected = Array.from(select.options).find((option) => option.value === select.value);
    const placeholder = wrapper.dataset.customSelectPlaceholder || 'Select an option';
    value.textContent = selected?.value ? selected.textContent.trim() : placeholder;
    trigger.disabled = select.disabled;
    trigger.setAttribute('aria-disabled', select.disabled ? 'true' : 'false');
    trigger.setAttribute('aria-invalid', select.getAttribute('aria-invalid') === 'true' ? 'true' : 'false');
    trigger.id = `${select.id}-trigger`;
    menu.id = `${select.id}-menu`;
    menu.dataset.customSelectOwner = select.id;
    trigger.setAttribute('aria-controls', menu.id);
    menu.setAttribute('aria-labelledby', trigger.id);

    optionsContainer.replaceChildren();
    Array.from(select.options).forEach((option) => {
        if (option.disabled) return;

        const button = document.createElement('button');
        button.type = 'button';
        button.role = 'option';
        button.className = 'block w-full rounded-md px-3 py-2 text-left text-sm transition hover:bg-slate-50 focus:bg-blue-50 focus:outline-none';
        button.dataset.customSelectOption = option.value;
        button.setAttribute('aria-selected', option.value === select.value ? 'true' : 'false');
        button.textContent = option.textContent.trim();
        if (option.value === select.value) {
            button.classList.add('bg-blue-50', 'font-semibold', 'text-blue-700');
        } else {
            button.classList.add('text-slate-700');
        }
        optionsContainer.append(button);
    });

    empty.classList.toggle('hidden', optionsContainer.children.length > 0);
};

const openCustomSelect = (wrapper) => {
    const trigger = wrapper.querySelector('[data-custom-select-trigger]');
    const menu = customSelectMenu(wrapper);
    const search = wrapper.querySelector('[data-custom-select-search]');

    if (! trigger || trigger.disabled || ! menu) return;

    closeCustomSelects(wrapper);
    wrapper.__customSelectMenu = menu;
    customSelectPortalTarget(wrapper).append(menu);
    positionCustomSelectMenu(wrapper);
    wrapper.setAttribute('data-custom-select-open', 'true');
    menu.classList.remove('hidden');
    trigger.setAttribute('aria-expanded', 'true');
    trigger.querySelector('[data-custom-select-chevron]')?.classList.add('rotate-180');

    if (search) {
        search.value = '';
        search.focus();
    } else {
        menu.querySelector('[data-custom-select-option][aria-selected="true"]')?.focus();
    }
};

const closeCustomSelect = (wrapper, restoreFocus = false) => {
    const menu = customSelectMenu(wrapper);
    const trigger = wrapper?.querySelector('[data-custom-select-trigger]');

    if (! wrapper || ! menu || ! trigger) return;

    wrapper.removeAttribute('data-custom-select-open');
    menu.classList.add('hidden');
    if (menu.parentElement !== wrapper) wrapper.append(menu);
    menu.style.left = '';
    menu.style.top = '';
    menu.style.width = '';
    menu.style.maxHeight = '';
    trigger.setAttribute('aria-expanded', 'false');
    trigger.querySelector('[data-custom-select-chevron]')?.classList.remove('rotate-180');
    if (restoreFocus) trigger.focus();
};

const filterCustomSelectOptions = (wrapper, query) => {
    const normalizedQuery = query.trim().toLowerCase();
    const menu = customSelectMenu(wrapper);
    const options = menu?.querySelectorAll('[data-custom-select-option]') || [];
    let visible = 0;

    options.forEach((option) => {
        const matches = option.textContent.toLowerCase().includes(normalizedQuery);
        option.classList.toggle('hidden', ! matches);
        if (matches) visible += 1;
    });

    menu?.querySelector('[data-custom-select-empty]')?.classList.toggle('hidden', visible > 0);
};

document.querySelectorAll('[data-custom-select-native]').forEach(refreshCustomSelect);

document.addEventListener('change', (event) => {
    if (event.target.matches('[data-custom-select-native]')) {
        refreshCustomSelect(event.target);
    }
});

document.addEventListener('custom-select-refresh', (event) => {
    if (event.target.matches('[data-custom-select-native]')) {
        refreshCustomSelect(event.target);
    }
});

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-custom-select-trigger]');
    const option = event.target.closest('[data-custom-select-option]');
    const wrapper = customSelectWrapperForTarget(event.target);

    if (trigger) {
        const parent = trigger.closest('[data-custom-select]');
        if (parent?.hasAttribute('data-custom-select-open')) {
            closeCustomSelect(parent);
        } else if (parent) {
            openCustomSelect(parent);
        }
        return;
    }

    if (option && wrapper) {
        const select = wrapper.querySelector('[data-custom-select-native]');
        if (select) {
            select.value = option.dataset.customSelectOption;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            refreshCustomSelect(select);
        }
        closeCustomSelect(wrapper, true);
        return;
    }

    if (! wrapper) closeCustomSelects();
});

document.addEventListener('input', (event) => {
    const search = event.target.closest('[data-custom-select-search]');
    if (search) filterCustomSelectOptions(customSelectWrapperForTarget(search), search.value);
});

document.addEventListener('keydown', (event) => {
    const trigger = event.target.closest('[data-custom-select-trigger]');
    const option = event.target.closest('[data-custom-select-option]');
    const search = event.target.closest('[data-custom-select-search]');
    const wrapper = customSelectWrapperForTarget(event.target);

    if (trigger && ['Enter', ' ', 'ArrowDown', 'ArrowUp'].includes(event.key)) {
        event.preventDefault();
        if (wrapper.hasAttribute('data-custom-select-open')) closeCustomSelect(wrapper);
        else openCustomSelect(wrapper);
        return;
    }

    if (search && event.key === 'ArrowDown') {
        event.preventDefault();
        customSelectMenu(wrapper)?.querySelector('[data-custom-select-option]:not(.hidden)')?.focus();
        return;
    }

    if ((search || option) && event.key === 'Escape' && wrapper) {
        event.preventDefault();
        closeCustomSelect(wrapper, true);
        return;
    }

    if (! option || ! wrapper) return;

    const options = Array.from(customSelectMenu(wrapper)?.querySelectorAll('[data-custom-select-option]:not(.hidden)') || []);
    const index = options.indexOf(option);
    let nextIndex = index;
    if (event.key === 'ArrowDown') nextIndex = Math.min(index + 1, options.length - 1);
    if (event.key === 'ArrowUp') nextIndex = Math.max(index - 1, 0);
    if (event.key === 'Home') nextIndex = 0;
    if (event.key === 'End') nextIndex = options.length - 1;
    if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
        event.preventDefault();
        options[nextIndex]?.focus();
    }
});

document.addEventListener('scroll', () => {
    document.querySelectorAll('[data-custom-select][data-custom-select-open]').forEach(positionCustomSelectMenu);
}, true);

window.addEventListener('resize', () => {
    document.querySelectorAll('[data-custom-select][data-custom-select-open]').forEach(positionCustomSelectMenu);
});

const submitDonationFilters = (form) => {
    if (form) form.requestSubmit();
};

document.addEventListener('input', (event) => {
    const input = event.target.closest('[data-donation-live-search]');
    const form = input?.closest('[data-donation-filter-form]');

    if (! form) return;

    window.clearTimeout(donationFilterTimers.get(form));
    donationFilterTimers.set(form, window.setTimeout(() => submitDonationFilters(form), 300));
});

document.addEventListener('change', (event) => {
    const filter = event.target.closest('[data-donation-live-filter]');
    const form = filter?.closest('[data-donation-filter-form]');

    if (form) submitDonationFilters(form);
});

const refreshDonorResults = async (form, targetUrl) => {
    const results = document.querySelector('[data-donor-results]');

    if (! results) {
        form.requestSubmit();
        return;
    }

    donorSearchRequests.get(form)?.abort();
    const controller = new AbortController();
    donorSearchRequests.set(form, controller);
    results.setAttribute('aria-busy', 'true');

    try {
        const response = await fetch(targetUrl, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: controller.signal,
        });

        if (! response.ok) {
            throw new Error(`Donor search failed with status ${response.status}`);
        }

        const payload = await response.json();
        const donorTbody = results.querySelector('[data-donor-tbody]');
        if (donorTbody) {
            donorTbody.innerHTML = payload.rows;
        }
        results.querySelector('[data-donor-pagination]').innerHTML = payload.pagination;
        results.querySelector('[data-donor-modals]').innerHTML = payload.modals;
        window.history.replaceState({}, '', targetUrl);
    } catch (error) {
        if (error.name !== 'AbortError') {
            window.location.assign(targetUrl);
        }
    } finally {
        if (donorSearchRequests.get(form) === controller) {
            donorSearchRequests.delete(form);
            results.removeAttribute('aria-busy');
        }
    }
};

const donorUrl = (form, page = 1) => {
    const url = new URL(form.action, window.location.origin);
    const formData = new FormData(form);

    for (const [key, value] of formData.entries()) {
        if (value !== '') url.searchParams.set(key, value);
    }

    if (page > 1) url.searchParams.set('page', page);

    return url.toString();
};

document.addEventListener('input', (event) => {
    const input = event.target.closest('[data-donor-live-search]');

    if (! input) {
        return;
    }

    const form = input.closest('[data-donor-search-form]');
    if (! form) {
        return;
    }

    window.clearTimeout(donorSearchTimers.get(form));
    donorSearchTimers.set(form, window.setTimeout(() => refreshDonorResults(form, donorUrl(form)), 250));
});

document.addEventListener('change', (event) => {
    const filter = event.target.closest('[data-donor-live-filter]');

    if (filter) {
        refreshDonorResults(filter.closest('[data-donor-search-form]'), donorUrl(filter.closest('[data-donor-search-form]')));
    }
});

document.addEventListener('click', (event) => {
    const link = event.target.closest('[data-donor-pagination] a');

    if (! link) return;

    const form = document.querySelector('[data-donor-search-form]');
    if (! form) return;

    event.preventDefault();
    const url = new URL(link.href, window.location.origin);
    refreshDonorResults(form, donorUrl(form, Number(url.searchParams.get('page') || 1)));
});

const successToast = document.querySelector('[data-toast]');
if (successToast) {
    window.setTimeout(() => {
        successToast.classList.add('translate-y-[-0.5rem]', 'opacity-0');
        window.setTimeout(() => successToast.remove(), 300);
    }, 4000);
}

document.addEventListener('click', (event) => {
    const previewButton = event.target.closest('[data-document-preview-open]');
    const openButton = event.target.closest('[data-dialog-open]');
    const closeButton = event.target.closest('[data-dialog-close]');

    if (previewButton) {
        const dialog = document.getElementById(previewButton.dataset.previewDialog);
        if (dialog instanceof HTMLDialogElement) {
            dialog.__returnFocus = previewButton;
            dialog.__returnScrollPositions = [...document.querySelectorAll('dialog[open]')].flatMap((openDialog) => [...openDialog.querySelectorAll('.overflow-y-auto')].map((scrollable) => ({ scrollable, top: scrollable.scrollTop })));
            dialog.__previewUrl = previewButton.dataset.previewUrl;
            dialog.showModal();
            loadDocumentPreview(dialog, previewButton);
        }
    }

    if (openButton) {
        const dialog = document.getElementById(openButton.dataset.dialogOpen);

        if (dialog instanceof HTMLDialogElement) {
            dialog.__returnFocus = openButton;
            if (! dialog.open) dialog.showModal();
        }
    }

    if (closeButton) {
        const dialog = document.getElementById(closeButton.dataset.dialogClose);

        if (dialog instanceof HTMLDialogElement) {
            dialog.close();
        }
    }
});

const loadDocumentPreview = async (dialog, trigger) => {
    const loading = dialog.querySelector('[data-preview-loading]');
    const pdf = dialog.querySelector('[data-preview-pdf]');
    const image = dialog.querySelector('[data-preview-image]');
    const error = dialog.querySelector('[data-preview-error]');
    const title = dialog.querySelector('[data-preview-document-title]');
    const filename = dialog.querySelector('[data-preview-filename-label]');
    const mime = trigger.dataset.previewMime || '';

    title.textContent = trigger.dataset.previewDocument || 'Document Preview';
    filename.textContent = trigger.dataset.previewFilename || '';
    loading.classList.remove('hidden');
    pdf.classList.add('hidden');
    image.classList.add('hidden');
    error.classList.add('hidden');
    error.textContent = '';

    try {
        if (! ['application/pdf', 'image/jpeg', 'image/png'].includes(mime)) {
            throw new Error('This document type is not supported for preview.');
        }

        const response = await fetch(trigger.dataset.previewUrl, { headers: { Accept: mime } });
        if (! response.ok) throw new Error('The document could not be opened. It may be missing or no longer available.');
        const blob = await response.blob();
        if (! blob.type.startsWith('application/pdf') && ! blob.type.startsWith('image/')) {
            throw new Error('The uploaded file is not a supported PDF or image.');
        }

        if (dialog.__previewObjectUrl) URL.revokeObjectURL(dialog.__previewObjectUrl);
        dialog.__previewObjectUrl = URL.createObjectURL(blob);
        loading.classList.add('hidden');

        if (mime === 'application/pdf') {
            pdf.src = dialog.__previewObjectUrl;
            pdf.classList.remove('hidden');
        } else {
            image.src = dialog.__previewObjectUrl;
            image.alt = trigger.dataset.previewFilename || 'Uploaded image';
            image.classList.remove('hidden');
        }
    } catch (previewError) {
        loading.classList.add('hidden');
        error.textContent = previewError.message || 'Unable to preview this document.';
        error.classList.remove('hidden');
        error.classList.add('flex');
    }
};

document.querySelectorAll('[data-document-preview-dialog]').forEach((dialog) => {
    dialog.addEventListener('close', () => {
        const returnScrollPositions = dialog.__returnScrollPositions;
        const returnFocus = dialog.__returnFocus;
        dialog.__returnFocus = null;
        window.setTimeout(() => {
            if (returnFocus instanceof HTMLElement && document.contains(returnFocus)) returnFocus.focus();
            window.setTimeout(() => returnScrollPositions?.forEach(({ scrollable, top }) => {
                scrollable.scrollTop = top;
            }), 0);
        }, 0);
        dialog.__returnScrollPositions = null;
        if (dialog.__previewObjectUrl) {
            URL.revokeObjectURL(dialog.__previewObjectUrl);
            dialog.__previewObjectUrl = null;
        }
        const pdf = dialog.querySelector('[data-preview-pdf]');
        const image = dialog.querySelector('[data-preview-image]');
        if (pdf) pdf.removeAttribute('src');
        if (image) image.removeAttribute('src');
    });
});

document.addEventListener('close', (event) => {
    const dialog = event.target;
    if (!(dialog instanceof HTMLDialogElement)) return;
    const returnFocus = dialog.__returnFocus;
    if (returnFocus instanceof HTMLElement && document.contains(returnFocus)) {
        window.setTimeout(() => returnFocus.focus(), 0);
    }
});

document.addEventListener('cancel', (event) => {
    if (event.target instanceof HTMLDialogElement) {
        event.target.close();
    }
}, true);

const addressSection = (el) => el.closest('[data-wizard-step][data-wizard-step-title="Address Information"]');

const fetchLocationOptions = (url) => fetch(url, {
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
}).then((response) => (response.ok ? response.json() : [])).catch(() => []);

const selectedLocationCode = (select) => select.selectedOptions[0]?.dataset.code ?? '';

const populateLocationSelect = (select, items, placeholderText, extraOption = null) => {
    select.innerHTML = '';

    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = placeholderText;
    placeholder.selected = true;
    select.appendChild(placeholder);

    if (extraOption) {
        const none = document.createElement('option');
        none.value = '';
        none.dataset.noneProvince = 'true';
        none.textContent = extraOption;
        select.appendChild(none);
    }

    items.forEach((item) => {
        const option = document.createElement('option');
        option.value = item.name;
        option.dataset.code = item.code;
        option.textContent = item.name;
        select.appendChild(option);
    });

    select.disabled = false;
};

document.addEventListener('change', (event) => {
    const regionSelect = event.target.closest('[data-address-region]');
    const provinceSelect = event.target.closest('[data-address-province]');
    const citySelect = event.target.closest('[data-address-city]');

    if (regionSelect) {
        const section = addressSection(regionSelect);
        const province = section?.querySelector('[data-address-province]');
        const city = section?.querySelector('[data-address-city]');
        const barangay = section?.querySelector('[data-address-barangay]');
        if (! province || ! city || ! barangay) return;

        populateLocationSelect(city, [], 'Select City / Municipality');
        city.disabled = true;
        populateLocationSelect(barangay, [], 'Select Barangay');
        barangay.disabled = true;

        const code = selectedLocationCode(regionSelect);
        if (! code) {
            populateLocationSelect(province, [], 'Select Province');
            province.disabled = true;
            return;
        }

        province.disabled = true;
        fetchLocationOptions(`/locations/provinces?region_code=${encodeURIComponent(code)}`)
            .then((items) => populateLocationSelect(province, items, 'Select Province', 'Not Applicable (Independent City)'));
        return;
    }

    if (provinceSelect) {
        const section = addressSection(provinceSelect);
        const city = section?.querySelector('[data-address-city]');
        const barangay = section?.querySelector('[data-address-barangay]');
        if (! city || ! barangay) return;

        populateLocationSelect(barangay, [], 'Select Barangay');
        barangay.disabled = true;

        const provinceCode = selectedLocationCode(provinceSelect);
        const independentCity = provinceSelect.selectedOptions[0]?.hasAttribute('data-none-province');
        const regionCode = section.querySelector('input[name="region"]')?.dataset.code || '';

        if (independentCity && regionCode) {
            city.disabled = true;
            fetchLocationOptions('/locations/cities?region_code=' + encodeURIComponent(regionCode))
                .then((items) => populateLocationSelect(city, items, 'Select City / Municipality'));
            return;
        }

        if (! provinceCode) {
            populateLocationSelect(city, [], 'Select City / Municipality');
            city.disabled = true;
            return;
        }

        city.disabled = true;
        fetchLocationOptions(`/locations/cities?province_code=${encodeURIComponent(provinceCode)}`)
            .then((items) => populateLocationSelect(city, items, 'Select City / Municipality'));
        return;
    }

    if (citySelect) {
        const section = addressSection(citySelect);
        const barangay = section?.querySelector('[data-address-barangay]');
        if (! barangay) return;

        const cityCode = selectedLocationCode(citySelect);
        if (! cityCode) {
            populateLocationSelect(barangay, [], 'Select Barangay');
            barangay.disabled = true;
            return;
        }

        barangay.disabled = true;
        fetchLocationOptions(`/locations/barangays?city_code=${encodeURIComponent(cityCode)}`)
            .then((items) => populateLocationSelect(barangay, items, 'Select Barangay'));
    }
});

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

const donorWizardFieldLabel = (field) => {
    const label = field.id ? document.querySelector(`label[for="${CSS.escape(field.id)}"]`) : null;

    return label?.textContent.trim() || field.name || 'this field';
};

const INVALID_FIELD_CLASSES = ['border-red-400', 'ring-2', 'ring-red-100'];

const markDonorWizardFieldInvalid = (field) => field.classList.add(...INVALID_FIELD_CLASSES);
const clearDonorWizardFieldInvalid = (field) => field.classList.remove(...INVALID_FIELD_CLASSES);

const validateDonorWizardStep = (wizard, step) => {
    const invalidField = invalidDonorWizardField(step);
    const errorMessage = donorWizardControls(wizard).error ?? wizard.querySelector('[data-wizard-error]');

    step.querySelectorAll('input, select, textarea').forEach(clearDonorWizardFieldInvalid);

    if (errorMessage) {
        errorMessage.hidden = ! invalidField;
        if (invalidField) {
            const invalidFields = Array.from(step.querySelectorAll('input, select, textarea')).filter((field) => ! field.disabled && ! field.checkValidity());
            invalidFields.forEach(markDonorWizardFieldInvalid);

            errorMessage.textContent = invalidFields.length > 1
                ? `Please complete the ${invalidFields.length} highlighted fields before continuing.`
                : `Please complete "${donorWizardFieldLabel(invalidField)}" before continuing.`;
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
        clearDonorWizardFieldInvalid(event.target);
        const error = donorWizardControls(wizard).error ?? wizard.querySelector('[data-wizard-error]');

        if (error) {
            error.hidden = true;
        }
    }
});

document.addEventListener('change', (event) => {
    const wizard = donorWizardForm(event.target);

    if (wizard) {
        clearDonorWizardFieldInvalid(event.target);
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
            if (field.matches('select[data-custom-select-native]')) refreshCustomSelect(field);
        });
    });
};

const updateContributionTabs = (form) => {
    const type = form.querySelector('[data-contribution-type]')?.value;

    form.querySelectorAll('[data-contribution-tab]').forEach((tab) => {
        const active = tab.dataset.contributionTab === type;
        const icon = tab.querySelector('span:first-child');
        const description = tab.querySelector('span:last-child span:last-child');

        tab.setAttribute('aria-selected', active ? 'true' : 'false');
        tab.classList.toggle('border-blue-500', active);
        tab.classList.toggle('bg-gradient-to-r', active);
        tab.classList.toggle('from-blue-600', active);
        tab.classList.toggle('to-indigo-600', active);
        tab.classList.toggle('text-white', active);
        tab.classList.toggle('shadow-sm', active);
        tab.classList.toggle('border-slate-200', ! active);
        tab.classList.toggle('bg-slate-50', ! active);
        tab.classList.toggle('text-slate-700', ! active);

        if (icon) {
            icon.classList.toggle('bg-white/15', active);
            icon.classList.toggle('text-white', active);
            icon.classList.toggle('bg-white', ! active);
            icon.classList.toggle('text-slate-600', ! active);
            icon.classList.toggle('shadow-sm', ! active);
        }

        if (description) {
            description.classList.toggle('text-blue-100', active);
            description.classList.toggle('text-slate-500', ! active);
        }
    });
};

const evidenceExtensions = new Set(['pdf', 'jpg', 'jpeg', 'png']);
const evidenceMimeTypes = new Set(['application/pdf', 'image/jpeg', 'image/png']);
const evidenceMaxSize = 10 * 1024 * 1024;
const evidenceMaxFiles = 5;

const formatFileSize = (bytes) => {
    if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const evidenceFileExtension = (file) => file.name.split('.').pop()?.toLowerCase() || '';

const setEvidenceFiles = (input, files) => {
    const transfer = new DataTransfer();
    files.forEach((file) => transfer.items.add(file));
    input.files = transfer.files;
    return Array.from(input.files);
};

const renderEvidenceFiles = (uploader, files) => {
    const list = uploader.querySelector('[data-evidence-list]');
    const error = uploader.querySelector('[data-evidence-error]');

    list.replaceChildren();
    list.classList.toggle('hidden', files.length === 0);
    error.classList.toggle('hidden', ! error.textContent);

    files.forEach((file, index) => {
        const row = document.createElement('div');
        row.className = 'flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2';

        if (file.type.startsWith('image/')) {
            const preview = document.createElement('img');
            preview.src = URL.createObjectURL(file);
            preview.alt = '';
            preview.className = 'h-10 w-10 shrink-0 rounded-md object-cover ring-1 ring-slate-200';
            row.append(preview);
        } else {
            const icon = document.createElement('span');
            icon.className = 'flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-white text-xs font-bold uppercase text-blue-700 ring-1 ring-slate-200';
            icon.textContent = evidenceFileExtension(file);
            row.append(icon);
        }

        const details = document.createElement('span');
        details.className = 'min-w-0 flex-1';
        details.innerHTML = `<span class="block truncate text-sm font-medium text-slate-800"></span><span class="block text-xs text-slate-500"></span>`;
        details.firstElementChild.textContent = file.name;
        details.lastElementChild.textContent = formatFileSize(file.size);
        row.append(details);

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'shrink-0 rounded-full p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-700 focus:outline-none focus:ring-2 focus:ring-blue-500';
        remove.textContent = '×';
        remove.setAttribute('aria-label', `Remove ${file.name}`);
        remove.title = 'Remove file';
        remove.dataset.evidenceRemove = String(index);
        row.append(remove);
        list.append(row);
    });
};

const validateEvidenceFiles = (files) => {
    const valid = [];
    const errors = [];

    if (files.length > evidenceMaxFiles) {
        errors.push(`You can attach up to ${evidenceMaxFiles} files.`);
    }

    files.slice(0, evidenceMaxFiles).forEach((file) => {
        const extension = evidenceFileExtension(file);
        if (! evidenceMimeTypes.has(file.type) && ! evidenceExtensions.has(extension)) {
            errors.push(`${file.name}: only PDF, JPG, JPEG, and PNG files are allowed.`);
            return;
        }
        if (file.size > evidenceMaxSize) {
            errors.push(`${file.name}: the maximum file size is 10 MB.`);
            return;
        }
        valid.push(file);
    });

    return { valid, errors };
};

const initializeEvidenceUploader = (uploader) => {
    const input = uploader.querySelector('[data-evidence-input]');
    const dropzone = uploader.querySelector('[data-evidence-dropzone]');
    const error = uploader.querySelector('[data-evidence-error]');

    if (! input || ! dropzone || ! error) return;

    const processFiles = (incomingFiles) => {
        const { valid, errors } = validateEvidenceFiles(Array.from(incomingFiles));
        setEvidenceFiles(input, valid);
        error.textContent = errors.join(' ');
        error.classList.toggle('hidden', errors.length === 0);
        renderEvidenceFiles(uploader, valid);
    };

    dropzone.addEventListener('click', () => input.click());
    input.addEventListener('change', () => processFiles(input.files));
    dropzone.addEventListener('dragover', (event) => {
        event.preventDefault();
        dropzone.classList.add('border-blue-600', 'bg-blue-50');
    });
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('border-blue-600', 'bg-blue-50'));
    dropzone.addEventListener('drop', (event) => {
        event.preventDefault();
        dropzone.classList.remove('border-blue-600', 'bg-blue-50');
        processFiles(event.dataTransfer.files);
    });
    uploader.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-evidence-remove]');
        if (! remove) return;

        const files = Array.from(input.files);
        files.splice(Number(remove.dataset.evidenceRemove), 1);
        processFiles(files);
    });
};

const updateReferenceRequirement = (form) => {
    const method = form.querySelector('[data-payment-method]')?.value;
    const reference = form.querySelector('[data-reference-number]');
    const referenceField = form.querySelector('[data-reference-field]');
    const referenceLabel = form.querySelector('[data-reference-label]');
    const methodName = form.querySelector('[data-payment-method-name]');
    const methodNameField = form.querySelector('[data-payment-method-name-field]');

    form.querySelectorAll('[data-payment-method-field]').forEach((field) => {
        const methods = (field.dataset.paymentMethodField || '').split(',').map((value) => value.trim());
        const active = methods.includes(method);
        const input = field.querySelector('input, select, textarea');

        field.hidden = ! active;
        if (input) {
            input.disabled = ! active;
            input.required = active && ['check_number', 'bank_name', 'registered_bank_account'].includes(input.name);

            if (! active) input.value = '';
        }
    });

    if (methodName && methodNameField) {
        const isOther = method === 'other';

        methodNameField.hidden = ! isOther;
        methodName.disabled = ! isOther;
        methodName.required = isOther;

        if (! isOther) {
            methodName.value = '';
        }
    }

    if (! reference || ! referenceField) {
        const bankProofSection = form.querySelector('[data-bank-proof-section]');
        const bankProofInput = bankProofSection?.querySelector('[name="bank_transfer_proof[]"]');

        if (bankProofSection) bankProofSection.hidden = method !== 'bank_transfer';
        if (bankProofInput) bankProofInput.disabled = method !== 'bank_transfer';
        return;
    }

    const isBankTransfer = method === 'bank_transfer';
    referenceField.hidden = ! isBankTransfer;
    reference.disabled = ! isBankTransfer;
    reference.required = isBankTransfer;
    referenceLabel.textContent = 'Bank Reference Number';
    reference.placeholder = 'Enter bank reference number';

    if (! isBankTransfer) reference.value = '';

    const bankProofSection = form.querySelector('[data-bank-proof-section]');
    const bankProofInput = bankProofSection?.querySelector('[name="bank_transfer_proof[]"]');
    if (bankProofSection) bankProofSection.hidden = ! isBankTransfer;
    if (bankProofInput) bankProofInput.disabled = ! isBankTransfer;
};

const updateCashPurposeFields = (form) => {
    const purpose = form.querySelector('[data-donation-purpose]')?.value;
    const purposeField = form.querySelector('[data-purpose-name-field]');
    const purposeName = purposeField?.querySelector('[name="purpose_name"]');
    const requested = form.querySelector('[name="certificate_requested"]:checked')?.value === '1';
    const tinField = form.querySelector('[data-certificate-tin-field]');
    const tin = tinField?.querySelector('[name="donor_tin"]');

    if (purposeField && purposeName) {
        purposeField.hidden = purpose !== 'specific';
        purposeName.disabled = purpose !== 'specific';
        purposeName.required = purpose === 'specific';
        if (purpose !== 'specific') purposeName.value = '';
    }

    if (tinField && tin) {
        tinField.hidden = ! requested;
        tin.disabled = ! requested;
        if (! requested) tin.value = '';
    }
};

const updateCashDocumentRequirements = (form) => {
    const cashSection = form?.querySelector('[data-cash-fields]');
    if (! cashSection) return;

    const amount = Number.parseFloat(cashSection.querySelector('[name="amount"]')?.value || '0');
    const deedExceptionSection = cashSection.querySelector('[data-deed-exception-section]');
    const deedDeclined = cashSection.querySelector('[name="deed_declined"]')?.checked === true;
    const deedExceptionReasonField = cashSection.querySelector('[data-deed-exception-reason]');
    const deedExceptionReason = cashSection.querySelector('[name="deed_decline_reason"]');
    const deedRequired = amount >= 5000 && ! deedDeclined;

    if (deedExceptionSection) deedExceptionSection.hidden = amount < 5000;
    if (deedExceptionReasonField) deedExceptionReasonField.hidden = ! deedDeclined || amount < 5000;
    if (deedExceptionReason) {
        deedExceptionReason.disabled = amount < 5000 || ! deedDeclined;
        deedExceptionReason.required = amount >= 5000 && deedDeclined;
        if (amount < 5000 || ! deedDeclined) deedExceptionReason.value = '';
    }

    cashSection.querySelectorAll('[data-conditional-document]').forEach((input) => {
        input.required = input.name === 'certificate_of_acceptance' || deedRequired;
        const field = input.closest('div');
        field?.querySelector('[data-document-required-indicator]')?.classList.toggle('hidden', input.name === 'deed_of_donation' && ! deedRequired);
        field?.querySelector('[data-document-optional-indicator]')?.classList.toggle('hidden', input.name === 'deed_of_donation' && deedRequired);
        const help = field?.querySelector('[data-document-help]');
        if (help && ! input.files?.length) {
            help.textContent = input.name === 'certificate_of_acceptance'
                ? 'Required. PDF, JPG, JPEG, or PNG up to 10MB.'
                : deedRequired
                    ? 'Required at PHP 5,000 or above unless the donor exception is recorded.'
                    : 'Optional below PHP 5,000. PDF, JPG, JPEG, or PNG up to 10MB.';
        }
    });
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

const ICON_PENCIL_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><path d="M15.2 4.3 19.7 8.8" /><path d="M5 19l.9-3.8L15.5 5.6a1.8 1.8 0 0 1 2.5 0l1.4 1.4a1.8 1.8 0 0 1 0 2.5L9.8 19.1 5 19Z" /></svg>';
const ICON_TRASH_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><path d="M4.5 7h15" /><path d="M9 7V4.8c0-.4.3-.8.8-.8h4.4c.5 0 .8.4.8.8V7" /><path d="M6.5 7l.7 12c0 .8.7 1.5 1.5 1.5h6.6c.8 0 1.5-.7 1.5-1.5L18 7" /><path d="M10 11v6" /><path d="M14 11v6" /></svg>';
const ITEM_FIELDS = ['name', 'category', 'category_description', 'quantity', 'unit', 'condition', 'expiration_date', 'brand', 'model', 'serial_number', 'estimated_value'];

const inKindItemsState = new WeakMap();

const getItemsState = (container) => {
    if (! inKindItemsState.has(container)) {
        let items = [];
        try {
            items = JSON.parse(container.dataset.oldItems || '[]');
        } catch {
            items = [];
        }
        inKindItemsState.set(container, { items: Array.isArray(items) ? items : [], editingIndex: null });
    }
    return inKindItemsState.get(container);
};

const itemModalRow = () => document.querySelector('#add-item-dialog [data-item-row]');

const itemLabelFor = (container, kind, key, customValue) => {
    if (key === 'others') return customValue ? `Others — ${customValue}` : 'Others';
    if (! key) return '—';
    try {
        const labels = JSON.parse(container.dataset[kind] || '{}');
        return labels[key] || key;
    } catch {
        return key;
    }
};

const formatEstimatedValue = (value) => {
    if (value === null || value === undefined || value === '') return '—';
    const amount = Number(value);
    return Number.isNaN(amount) ? '—' : `₱${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

const renderItemsTable = (container) => {
    const state = getItemsState(container);
    const body = container.querySelector('[data-items-table-body]');
    const emptyState = container.querySelector('[data-items-empty-state]');
    const tableWrapper = container.querySelector('[data-items-table-wrapper]');
    const itemsError = container.querySelector('[data-items-error]');

    if (! body || ! emptyState || ! tableWrapper) return;

    body.replaceChildren();
    const hasItems = state.items.length > 0;
    emptyState.hidden = hasItems;
    tableWrapper.hidden = ! hasItems;
    if (hasItems && itemsError) itemsError.classList.add('hidden');

    state.items.forEach((item, index) => {
        const row = document.createElement('tr');

        [
            item.name || '—',
            itemLabelFor(container, 'categories', item.category, item.category_description),
            item.quantity || '—',
            itemLabelFor(container, 'units', item.unit),
            itemLabelFor(container, 'conditions', item.condition),
            formatEstimatedValue(item.estimated_value),
        ].forEach((text, cellIndex) => {
            const td = document.createElement('td');
            td.className = cellIndex === 0 ? 'px-3 py-2 font-medium text-slate-800' : 'px-3 py-2 text-slate-600';
            td.textContent = text;
            row.append(td);
        });

        const actions = document.createElement('td');
        actions.className = 'space-x-1 px-3 py-2 text-right';
        actions.innerHTML = `
            <button type="button" class="rounded-md p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700" data-edit-item="${index}" aria-label="Edit item">${ICON_PENCIL_SVG}</button>
            <button type="button" class="rounded-md p-1.5 text-red-600 hover:bg-red-50" data-delete-item="${index}" aria-label="Remove item">${ICON_TRASH_SVG}</button>
        `;
        row.append(actions);
        body.append(row);
    });
};

const renderItemHiddenInputs = (container) => {
    const state = getItemsState(container);
    const holder = container.querySelector('[data-item-hidden-inputs]');
    if (! holder) return;

    holder.replaceChildren();

    state.items.forEach((item, index) => {
        ITEM_FIELDS.forEach((field) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `items[${index}][${field}]`;
            input.value = item[field] ?? '';
            holder.append(input);
        });
    });
};

const itemCategoryGroup = (row, attr) => {
    try {
        return JSON.parse(row.dataset[attr] || '[]');
    } catch {
        return [];
    }
};

const updateItemConditionalFields = (row) => {
    const category = row.querySelector('[data-item-category]')?.value || '';
    const quantity = Number(row.querySelector('[data-item-field="quantity"]')?.value);

    const descriptionWrapper = row.querySelector('[data-item-category-description]');
    const descriptionInput = descriptionWrapper?.querySelector('[data-item-field="category_description"]');
    const isOther = category === 'others';

    if (descriptionWrapper && descriptionInput) {
        descriptionWrapper.hidden = ! isOther;
        descriptionInput.disabled = ! isOther;
        if (! isOther) descriptionInput.value = '';
    }

    const expirationWrapper = row.querySelector('[data-item-expiration]');
    const expirationInput = expirationWrapper?.querySelector('[data-item-field="expiration_date"]');
    const showsExpiration = itemCategoryGroup(row, 'expirationCategories').includes(category);

    if (expirationWrapper && expirationInput) {
        expirationWrapper.hidden = ! showsExpiration;
        expirationInput.disabled = ! showsExpiration;
        if (! showsExpiration) expirationInput.value = '';
    }

    const equipmentWrapper = row.querySelector('[data-item-equipment-fields]');
    const showsEquipment = itemCategoryGroup(row, 'equipmentCategories').includes(category);

    if (equipmentWrapper) {
        equipmentWrapper.hidden = ! showsEquipment;

        const brand = equipmentWrapper.querySelector('[data-item-field="brand"]');
        const model = equipmentWrapper.querySelector('[data-item-field="model"]');
        const serial = equipmentWrapper.querySelector('[data-item-field="serial_number"]');
        const serialHint = equipmentWrapper.querySelector('[data-item-serial-hint]');

        [brand, model].forEach((field) => {
            if (! field) return;
            field.disabled = ! showsEquipment;
            if (! showsEquipment) field.value = '';
        });

        if (serial) {
            const allowsSerial = showsEquipment && quantity === 1;
            serial.disabled = ! allowsSerial;
            if (! allowsSerial) serial.value = '';
            if (serialHint) serialHint.hidden = ! (showsEquipment && quantity !== 1);
        }
    }
};

const resetItemModalFields = (row) => {
    row.querySelectorAll('input[data-item-field]').forEach((field) => { field.value = ''; });

    const category = row.querySelector('[data-item-category]');
    const unit = row.querySelector('[data-item-field="unit"]');
    const condition = row.querySelector('[data-item-field="condition"]');

    [category, unit, condition].forEach((select) => {
        if (! select) return;
        select.selectedIndex = 0;
        refreshCustomSelect(select);
    });

    updateItemConditionalFields(row);
};

const populateItemModalFields = (row, item) => {
    const set = (selector, value) => {
        const field = row.querySelector(selector);
        if (field) field.value = value ?? '';
    };

    set('[data-item-field="name"]', item.name);
    set('[data-item-field="category_description"]', item.category_description);
    set('[data-item-field="quantity"]', item.quantity);
    set('[data-item-field="expiration_date"]', item.expiration_date);
    set('[data-item-field="brand"]', item.brand);
    set('[data-item-field="model"]', item.model);
    set('[data-item-field="serial_number"]', item.serial_number);
    set('[data-item-field="estimated_value"]', item.estimated_value);

    const category = row.querySelector('[data-item-category]');
    const unit = row.querySelector('[data-item-field="unit"]');
    const condition = row.querySelector('[data-item-field="condition"]');

    if (category) {
        category.value = item.category || '';
        refreshCustomSelect(category);
    }

    if (unit) {
        unit.value = item.unit || '';
        refreshCustomSelect(unit);
    }

    if (condition) {
        condition.value = item.condition || '';
        refreshCustomSelect(condition);
    }

    updateItemConditionalFields(row);
};

const openAddItemModal = (container, index = null) => {
    const dialog = document.getElementById('add-item-dialog');
    const row = itemModalRow();
    if (! dialog || ! row) return;

    const state = getItemsState(container);
    state.editingIndex = index;
    dialog.__itemsContainer = container;

    const title = dialog.querySelector('[data-item-modal-title]');
    const submit = dialog.querySelector('[data-item-modal-submit]');
    const error = dialog.querySelector('[data-item-modal-error]');
    if (error) { error.textContent = ''; error.classList.add('hidden'); }

    if (index !== null && state.items[index]) {
        if (title) title.textContent = 'Edit Item';
        if (submit) submit.textContent = 'Save Changes';
        populateItemModalFields(row, state.items[index]);
    } else {
        if (title) title.textContent = 'Add Item';
        if (submit) submit.textContent = 'Add Item';
        resetItemModalFields(row);
    }

    dialog.showModal();
};

const collectItemModalValues = (row) => {
    const value = (selector) => row.querySelector(selector)?.value?.trim() ?? '';

    return {
        name: value('[data-item-field="name"]'),
        category: value('[data-item-category]'),
        category_description: value('[data-item-field="category_description"]'),
        quantity: value('[data-item-field="quantity"]'),
        unit: value('[data-item-field="unit"]'),
        condition: value('[data-item-field="condition"]'),
        expiration_date: value('[data-item-field="expiration_date"]'),
        brand: value('[data-item-field="brand"]'),
        model: value('[data-item-field="model"]'),
        serial_number: value('[data-item-field="serial_number"]'),
        estimated_value: value('[data-item-field="estimated_value"]'),
    };
};

const validateItemModalValues = (container, row, values, excludeIndex) => {
    if (! values.name) return 'Item name is required.';
    if (! values.category) return 'Category is required.';
    if (values.category === 'others' && ! values.category_description) return 'Specify a category.';
    if (! values.quantity || Number(values.quantity) <= 0) return 'Enter a valid quantity greater than 0.';
    if (! values.unit) return 'Unit is required.';
    if (! values.condition) return 'Condition is required.';
    if (values.estimated_value && Number(values.estimated_value) < 0) return 'Estimated value cannot be negative.';

    const state = getItemsState(container);
    const normalizedName = values.name.toLowerCase();
    const duplicate = state.items.some((item, index) => index !== excludeIndex
        && (item.name || '').toLowerCase() === normalizedName
        && item.category === values.category
        && (item.category_description || '') === (values.category_description || ''));

    return duplicate ? 'An item with this name and category has already been added.' : null;
};

const commitItemModal = () => {
    const dialog = document.getElementById('add-item-dialog');
    const row = itemModalRow();
    const container = dialog?.__itemsContainer;
    if (! dialog || ! row || ! container) return;

    const state = getItemsState(container);
    const values = collectItemModalValues(row);
    const errorEl = dialog.querySelector('[data-item-modal-error]');
    const message = validateItemModalValues(container, row, values, state.editingIndex);

    if (message) {
        if (errorEl) {
            errorEl.textContent = message;
            errorEl.classList.remove('hidden');
        }
        return;
    }

    if (state.editingIndex !== null) {
        state.items[state.editingIndex] = values;
    } else {
        state.items.push(values);
    }

    renderItemsTable(container);
    renderItemHiddenInputs(container);
    dialog.close();
};

document.querySelectorAll('[data-items-table]').forEach((container) => {
    renderItemsTable(container);
    renderItemHiddenInputs(container);
});

document.getElementById('add-item-dialog')?.addEventListener('close', () => {
    const dialog = document.getElementById('add-item-dialog');
    const row = itemModalRow();
    const container = dialog.__itemsContainer;

    if (container) getItemsState(container).editingIndex = null;
    if (row) resetItemModalFields(row);

    const error = dialog.querySelector('[data-item-modal-error]');
    if (error) { error.textContent = ''; error.classList.add('hidden'); }
});

document.querySelectorAll('[data-contribution-form]').forEach((form) => {
    updateContributionTabs(form);
    updateContributionSections(form);
    updateReferenceRequirement(form);
    updateCashPurposeFields(form);
    updateCashDocumentRequirements(form);
});

document.querySelectorAll('[data-evidence-uploader]').forEach(initializeEvidenceUploader);

const initializeSingleDocument = (input) => {
    const wrapper = input.parentElement;
    const name = wrapper?.querySelector('[data-single-document-name]');
    const remove = wrapper?.querySelector('[data-single-document-remove]');

    if (! wrapper || ! name || ! remove) return;

    name.dataset.emptyText = name.textContent.trim();

    const render = () => {
        const file = input.files?.[0];
        name.textContent = file ? `${file.name} · ${formatFileSize(file.size)}` : name.dataset.emptyText;
        name.title = file?.name || '';
        remove.classList.toggle('hidden', ! file);
    };

    input.addEventListener('change', render);
    remove.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        input.value = '';
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });
    render();
};

document.querySelectorAll('[data-single-document]').forEach(initializeSingleDocument);

document.addEventListener('submit', (event) => {
    const form = event.target.closest('[data-contribution-form]');
    const submit = form?.querySelector('[data-contribution-submit]');

    if (! form || ! submit) {
        return;
    }

    const donationType = form.querySelector('[data-contribution-type]')?.value;
    const itemsContainer = form.querySelector('[data-items-table]');

    if (donationType === 'in_kind' && itemsContainer && getItemsState(itemsContainer).items.length === 0) {
        event.preventDefault();
        itemsContainer.querySelector('[data-items-error]')?.classList.remove('hidden');
        itemsContainer.querySelector('[data-add-item]')?.focus();
        return;
    }

    submit.disabled = true;
    submit.setAttribute('aria-busy', 'true');
    const label = submit.querySelector('[data-contribution-submit-label]');
    if (label) label.textContent = 'Saving…';
});

document.querySelectorAll('[data-inventory-item-form]').forEach((form) => updateInventorySubcategorySelect(form));
document.querySelectorAll('#add-item-dialog [data-item-row]').forEach(updateItemConditionalFields);

document.addEventListener('change', (event) => {
    if (event.target.matches('[data-contribution-type]')) {
        updateContributionSections(event.target.closest('[data-contribution-form]'));
    }

    if (event.target.matches('[data-payment-method]')) {
        updateReferenceRequirement(event.target.closest('[data-contribution-form]'));
    }

    if (event.target.matches('[data-donation-purpose], [name="certificate_requested"]')) {
        const form = event.target.closest('[data-contribution-form]');
        updateCashPurposeFields(form);
        updateCashDocumentRequirements(form);
    }

    if (event.target.matches('[name="amount"]')) {
        updateCashDocumentRequirements(event.target.closest('[data-contribution-form]'));
    }

    if (event.target.matches('[name="deed_declined"]')) {
        updateCashDocumentRequirements(event.target.closest('[data-contribution-form]'));
    }

    if (event.target.matches('[data-item-category]') || event.target.matches('[data-item-field="quantity"]')) {
        const row = event.target.closest('[data-item-row]');
        if (row) updateItemConditionalFields(row);
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
    const tab = event.target.closest('[data-contribution-tab]');

    if (! tab) return;

    const form = tab.closest('[data-contribution-form]');
    const type = form?.querySelector('[data-contribution-type]');

    if (! form || ! type) return;

    type.value = tab.dataset.contributionTab;
    updateContributionTabs(form);
    updateContributionSections(form);
    updateReferenceRequirement(form);
});

document.addEventListener('click', (event) => {
    const addItemButton = event.target.closest('[data-add-item]');
    const editItemButton = event.target.closest('[data-edit-item]');
    const deleteItemButton = event.target.closest('[data-delete-item]');
    const submitItemButton = event.target.closest('[data-item-modal-submit]');

    if (addItemButton) {
        const container = addItemButton.closest('[data-items-table]');
        if (container) openAddItemModal(container);
    }

    if (editItemButton) {
        const container = editItemButton.closest('[data-items-table]');
        if (container) openAddItemModal(container, Number(editItemButton.dataset.editItem));
    }

    if (deleteItemButton) {
        const container = deleteItemButton.closest('[data-items-table]');
        if (container && window.confirm('Remove this item?')) {
            const state = getItemsState(container);
            state.items.splice(Number(deleteItemButton.dataset.deleteItem), 1);
            renderItemsTable(container);
            renderItemHiddenInputs(container);
        }
    }

    if (submitItemButton) {
        commitItemModal();
    }
});

document.addEventListener('input', (event) => {
    if (event.target.matches('#add-item-dialog [data-item-field="quantity"]')) {
        const row = event.target.closest('[data-item-row]');
        if (row) updateItemConditionalFields(row);
    }
});

document.addEventListener('change', (event) => {
    if (event.target.matches('[data-certificate-requested-radio]')) {
        const hidden = event.target.closest('[data-contribution-section]')?.querySelector('[data-certificate-requested-hidden]');
        if (hidden) hidden.value = event.target.value;
    }
});

const updateDonorCreateForm = (form) => {
    const donorType = form.querySelector('[data-donor-type]')?.value;
    const organizationNameWrappers = form.querySelectorAll('[data-organization-name-field], [data-organization-optional-field]');
    const individualNameWrappers = form.querySelectorAll('[data-individual-name-field]');
    const isOrganization = donorType === 'organization';

    organizationNameWrappers.forEach((wrapper) => {
        const input = wrapper.querySelector('input');
        wrapper.hidden = ! isOrganization;
        if (! input) return;
        input.disabled = ! isOrganization;
        input.required = isOrganization && ! wrapper.hasAttribute('data-organization-optional-field');
        if (! isOrganization && ! wrapper.hasAttribute('data-organization-optional-field')) input.value = '';
    });

    individualNameWrappers.forEach((wrapper) => {
        const input = wrapper.querySelector('input');

        wrapper.hidden = isOrganization;

        if (input) {
            input.disabled = isOrganization;
            input.required = ! isOrganization && input.dataset.requiredForIndividual === 'true';

            if (isOrganization) {
                input.value = '';
            }
        }
    });

};

document.querySelectorAll('[data-donor-form]').forEach(updateDonorCreateForm);

document.addEventListener('change', (event) => {
    if (event.target.matches('[data-donor-type]')) {
        updateDonorCreateForm(event.target.closest('[data-donor-form]'));
    }
});

const donorDirectory = document.getElementById('donor-directory-modal');

if (donorDirectory) {
    const rows = Array.from(donorDirectory.querySelectorAll('[data-donor-directory-row]'));
    const search = donorDirectory.querySelector('[data-donor-directory-search]');
    const type = donorDirectory.querySelector('[data-donor-directory-type]');
    const status = donorDirectory.querySelector('[data-donor-directory-status]');
    const count = donorDirectory.querySelector('[data-donor-directory-count]');
    const pageLabel = donorDirectory.querySelector('[data-donor-directory-page]');
    const empty = donorDirectory.querySelector('[data-donor-directory-empty]');
    const previous = donorDirectory.querySelector('[data-donor-directory-prev]');
    const next = donorDirectory.querySelector('[data-donor-directory-next]');
    const pageSize = 8;
    let page = 1;

    const renderDonorDirectory = () => {
        const query = (search?.value || '').trim().toLowerCase();
        const matches = rows.filter((row) => (row.dataset.search || '').includes(query)
            && (! type?.value || row.dataset.donorType === type.value)
            && (! status?.value || row.dataset.donorStatus === status.value));
        const pageCount = Math.max(1, Math.ceil(matches.length / pageSize));

        page = Math.min(page, pageCount);
        rows.forEach((row) => { row.hidden = true; });
        matches.slice((page - 1) * pageSize, page * pageSize).forEach((row) => { row.hidden = false; });

        if (count) count.textContent = `${matches.length} ${matches.length === 1 ? 'donor' : 'donors'}`;
        if (pageLabel) pageLabel.textContent = matches.length ? `Page ${page} of ${pageCount}` : 'No results';
        if (empty) empty.hidden = matches.length > 0;
        if (previous) previous.disabled = page <= 1 || matches.length === 0;
        if (next) next.disabled = page >= pageCount || matches.length === 0;
    };

    search?.addEventListener('input', () => {
        page = 1;
        renderDonorDirectory();
    });
    type?.addEventListener('change', () => { page = 1; renderDonorDirectory(); });
    status?.addEventListener('change', () => { page = 1; renderDonorDirectory(); });
    previous?.addEventListener('click', () => { page -= 1; renderDonorDirectory(); });
    next?.addEventListener('click', () => { page += 1; renderDonorDirectory(); });
    donorDirectory.addEventListener('close', () => {
        if (search) search.value = '';
        if (type) type.value = '';
        if (status) status.value = '';
        page = 1;
        renderDonorDirectory();
    });
    renderDonorDirectory();
}

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('#donor-directory-modal form[action*="/donors/"] , #donor-directory-modal form[action$="/donors"]');
    if (! form || form.matches('[data-quick-donor-form]')) return;
    event.preventDefault();

    const submit = form.querySelector('button[type="submit"], [data-wizard-submit]');
    const originalLabel = submit?.textContent;
    if (submit) { submit.disabled = true; submit.textContent = 'Saving...'; }

    try {
        let response;
        let payload;
        let duplicateConfirmed = Boolean(form.querySelector('input[name="_confirm_duplicate"]'));

        do {
            response = await fetch(form.action, {
                method: form.querySelector('input[name="_method"]')?.value || 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            });
            payload = await response.json().catch(() => ({}));

            if (response.status !== 409 || duplicateConfirmed || ! window.confirm(payload.message || 'A similar donor already exists. Create this profile anyway?')) {
                break;
            }

            const confirmation = document.createElement('input');
            confirmation.type = 'hidden';
            confirmation.name = '_confirm_duplicate';
            confirmation.value = '1';
            form.appendChild(confirmation);
            duplicateConfirmed = true;
        } while (true);

        if (! response.ok) {
            const message = payload.errors ? Object.values(payload.errors).flat().join(' ') : payload.message;
            throw new Error(message || 'Unable to save the donor profile.');
        }
        form.closest('dialog')?.close();
        document.getElementById('donor-directory-modal')?.close();
        window.location.reload();
    } catch (requestError) {
        let error = form.querySelector('[data-directory-donor-error]');
        if (! error) {
            error = document.createElement('p');
            error.dataset.directoryDonorError = 'true';
            error.className = 'mx-4 mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 sm:mx-6';
            form.querySelector('[data-donor-form]')?.before(error);
        }
        error.textContent = requestError.message;
    } finally {
        if (submit) { submit.disabled = false; submit.textContent = originalLabel || 'Save'; }
    }
});

const refreshContributionDonorOptions = (donor) => {
    document.querySelectorAll('[data-donor-select-native]').forEach((select) => {
        if (! select.querySelector(`option[value="${donor.id}"]`)) {
            select.add(new Option(donor.label, donor.id));
        }

        select.value = String(donor.id);
        select.dispatchEvent(new Event('change', { bubbles: true }));
        refreshCustomSelect(select);
    });
};

document.addEventListener('click', (event) => {
    const openButton = event.target.closest('[data-quick-donor-open]');

    if (! openButton) return;

    const dialog = document.getElementById('quick-create-donor-dialog');
    if (dialog instanceof HTMLDialogElement) dialog.showModal();
});

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-quick-donor-form]');

    if (! form || event.defaultPrevented) return;

    event.preventDefault();

    const submit = form.querySelector('[data-wizard-submit]');
    const error = form.querySelector('[data-quick-donor-error]');
    const originalLabel = submit?.textContent;
    if (submit) {
        submit.disabled = true;
        submit.textContent = 'Saving...';
    }
    if (error) {
        error.hidden = true;
        error.textContent = '';
    }

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form),
        });
        const payload = await response.json().catch(() => ({}));

        if (! response.ok) {
            const validation = payload.errors ? Object.values(payload.errors).flat().join(' ') : payload.message;
            throw new Error(validation || 'Unable to create the donor profile.');
        }

        refreshContributionDonorOptions(payload.donor);
        document.getElementById('quick-create-donor-dialog')?.close();
    } catch (requestError) {
        if (error) {
            error.textContent = requestError.message;
            error.hidden = false;
        }
    } finally {
        if (submit) {
            submit.disabled = false;
            submit.textContent = originalLabel || 'Save Donor';
        }
    }
});


// Keep wide records in one scroll surface with a matching sticky header.
document.querySelectorAll('[data-table]').forEach((table) => {
    const scroll = table.querySelector('[data-table-scroll]');
    const hint = table.querySelector('[data-table-hint]');
    if (!scroll || !hint) return;

    const updateOverflow = () => {
        table.style.setProperty('--table-viewport-width', scroll.clientWidth + 'px');
        const horizontal = scroll.scrollWidth > scroll.clientWidth + 1;
        hint.hidden = !horizontal;
        scroll.tabIndex = horizontal || scroll.scrollHeight > scroll.clientHeight + 1 ? 0 : -1;
    };
    const observer = new ResizeObserver(updateOverflow);
    observer.observe(scroll);
    observer.observe(scroll.querySelector('table'));
    updateOverflow();
});

// Mobile navigation stays compact; desktop keeps its independent collapse preference.
const sidebar = document.getElementById('app-sidebar');
const sidebarToggle = document.getElementById('sidebar-toggle');
if (sidebar && sidebarToggle) {
    const desktop = window.matchMedia('(min-width: 1024px)');
    const storageKey = 'sidebarCollapsed';
    let collapsed = false;
    let mobileOpen = false;
    try { collapsed = ['1', 'true'].includes(localStorage.getItem(storageKey)); } catch {}
    const applySidebar = () => {
        sidebar.classList.toggle('collapsed', desktop.matches && collapsed);
        sidebar.classList.toggle('mobile-open', !desktop.matches && mobileOpen);
        document.documentElement.classList.toggle('sidebar-collapsed', desktop.matches && collapsed);
        const expanded = desktop.matches ? !collapsed : mobileOpen;
        sidebarToggle.setAttribute('aria-expanded', String(expanded));
        sidebarToggle.setAttribute('aria-label', desktop.matches ? 'Toggle sidebar' : (expanded ? 'Close navigation' : 'Open navigation'));
    };
    sidebarToggle.addEventListener('click', () => {
        if (desktop.matches) {
            collapsed = !collapsed;
            try { localStorage.setItem(storageKey, collapsed ? '1' : '0'); } catch {}
        } else {
            mobileOpen = !mobileOpen;
        }
        applySidebar();
    });
    desktop.addEventListener('change', () => { mobileOpen = false; applySidebar(); });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !desktop.matches && mobileOpen) {
            mobileOpen = false;
            applySidebar();
            sidebarToggle.focus();
        }
    });
    applySidebar();
}
