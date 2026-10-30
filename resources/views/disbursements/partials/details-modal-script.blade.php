<script>
document.addEventListener('DOMContentLoaded', () => {
    const closeMenus = () => document.querySelectorAll('[data-fund-request-menu-panel]').forEach((panel) => {
        panel.hidden = true;
        panel.closest('[data-fund-request-action]')?.querySelector('[data-fund-request-menu-toggle]')?.setAttribute('aria-expanded', 'false');
    });
    const showDetails = (id) => {
        const dialog = document.getElementById(id);
        if (dialog && !dialog.open) dialog.showModal();
        closeMenus();
    };
    document.querySelectorAll('[data-open-fund-request-details]').forEach((button) => button.addEventListener('click', (event) => {
        event.stopPropagation();
        showDetails(button.dataset.openFundRequestDetails);
    }));
    document.querySelectorAll('[data-fund-request-scroll] tr[data-fund-request-id]').forEach((row) => row.addEventListener('click', (event) => {
        if (event.target.closest('[data-fund-request-action]')) return;
        showDetails(`fund-request-details-modal-${row.dataset.fundRequestId}`);
    }));
    document.querySelectorAll('[data-close-fund-request-details]').forEach((button) => button.addEventListener('click', () => button.closest('dialog')?.close()));
    document.querySelectorAll('[data-open-fund-request-edit]').forEach((button) => button.addEventListener('click', (event) => {
        event.stopPropagation();
        const nested = document.getElementById(button.dataset.openFundRequestEdit);
        const parent = nested?.closest('[data-fund-request-details-dialog]');
        if (parent && !parent.open) parent.showModal();
        if (nested && !nested.open) nested.showModal();
        closeMenus();
    }));
    document.querySelectorAll('[data-open-voucher-preview]').forEach((button) => button.addEventListener('click', () => document.getElementById(button.dataset.openVoucherPreview)?.showModal()));
    document.querySelectorAll('[data-open-reject-review]').forEach((button) => button.addEventListener('click', () => document.getElementById(`${button.dataset.openRejectReview}-reject`)?.showModal()));
    document.querySelectorAll('[data-open-missing-review]').forEach((button) => button.addEventListener('click', () => document.getElementById(`${button.dataset.openMissingReview}-missing`)?.showModal()));
    document.querySelectorAll('[data-open-cancel-review]').forEach((button) => button.addEventListener('click', () => document.getElementById(`${button.dataset.openCancelReview}-cancel`)?.showModal()));
    document.querySelectorAll('[data-close-nested-dialog]').forEach((button) => button.addEventListener('click', () => button.closest('dialog')?.close()));
});
</script>
