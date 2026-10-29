<?php

namespace App\Http\Requests\Donations;

use App\Models\Contribution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Support\Workspaces\WorkspaceValidationRules;

class StoreContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Contribution::class) ?? false;
    }

    public function rules(): array
    {
        $isInKind = $this->input('donation_type') === 'in_kind';

        return [
            'donation_type' => ['required', Rule::in(array_keys(config('donations.types')))],
            'donor_id' => [
                'required',
                WorkspaceValidationRules::exists('donors')
                    ->where('status', config('donors.defaults.status')),
            ],
            'campaign_id' => ['nullable', WorkspaceValidationRules::exists('campaigns')],
            'received_at' => ['nullable', 'date_format:Y-m-d'],
            'amount' => ['required_if:donation_type,cash', 'nullable', 'numeric', 'min:0.01', 'max:99999999.99'],
            'purpose_type' => ['nullable', Rule::in(['general', 'specific'])],
            'purpose_name' => ['required_if:purpose_type,specific', 'nullable', 'string', 'max:255'],
            'payment_method' => ['required_if:donation_type,cash', 'nullable', Rule::in(array_merge(
                array_keys(config('donations.payment_methods')),
                config('donations.legacy_input_payment_methods', []),
            ))],
            'payment_method_name' => ['required_if:payment_method,other', 'nullable', 'string', 'max:100'],
            'official_receipt_number' => [Rule::requiredIf(fn () => $this->input('donation_type') === 'cash'), 'nullable', 'string', 'max:100'],
            'check_number' => ['required_if:payment_method,check', 'nullable', 'string', 'max:100'],
            'bank_name' => ['required_if:payment_method,check', 'nullable', 'string', 'max:150'],
            'registered_bank_account' => ['required_if:payment_method,bank_transfer', 'nullable', 'string', 'max:150'],
            'reference_number' => [
                Rule::requiredIf(fn () => $this->requiresExternalReference()),
                'nullable',
                'string',
                'max:100',
                WorkspaceValidationRules::unique('contributions', 'reference_number')->where(fn ($query) => $query->where('payment_method', $this->input('payment_method'))),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'evidence' => ['nullable', 'array', 'max:5'],
            'evidence.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'bank_transfer_proof' => ['nullable', 'array', 'max:5'],
            'bank_transfer_proof.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'official_receipt_document' => [Rule::requiredIf(fn () => $this->input('donation_type') === 'cash'), 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'deed_of_donation' => [Rule::requiredIf(fn () => $this->requiresAdditionalDocuments()), 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'certificate_of_acceptance' => [Rule::requiredIf(fn () => $this->input('donation_type') === 'cash'), 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'deed_declined' => ['boolean'],
            'deed_decline_reason' => [Rule::requiredIf(fn () => $this->cashDeedException()), 'nullable', 'string', 'max:2000'],
            'donation_item_list' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'deed_of_donation_acceptance' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'par_ics_acknowledgement' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'certificate_requested' => ['boolean'],
            'donor_tin' => ['nullable', 'string', 'max:30'],
            'items' => $isInKind ? ['required', 'array', 'min:1'] : ['array'],
            'items.*.name' => ['required_if:donation_type,in_kind', 'string', 'max:255'],
            'items.*.category' => ['required_if:donation_type,in_kind', Rule::in(array_keys(config('donations.item_main_categories')))],
            'items.*.category_description' => ['required_if:items.*.category,others', 'nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required_if:donation_type,in_kind', 'numeric', 'min:0.01', 'max:999999.99'],
            'items.*.unit' => ['required_if:donation_type,in_kind', Rule::in(array_keys(config('donations.units')))],
            'items.*.condition' => ['required_if:donation_type,in_kind', Rule::in(array_keys(config('donations.item_conditions')))],
            'items.*.expiration_date' => ['nullable', 'date_format:Y-m-d'],
            'items.*.brand' => ['nullable', 'string', 'max:150'],
            'items.*.model' => ['nullable', 'string', 'max:150'],
            'items.*.serial_number' => ['nullable', 'string', 'max:150'],
            'items.*.estimated_value' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'official_receipt_number.required' => 'Enter the Official Receipt Number before saving this cash donation.',
            'official_receipt_document.required' => 'Upload the Official Receipt before saving this cash donation.',
            'certificate_of_acceptance.required' => 'Upload the Certificate of Acceptance before saving this cash donation.',
            'deed_of_donation.required' => 'Upload the Deed of Donation for cash donations of PHP 5,000 or more, or check the exception and explain why the donor declined.',
            'deed_decline_reason.required' => 'Explain why the donor declined to sign the Deed of Donation.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $donationType = $this->nullableLower($this->input('donation_type'));

        $items = $donationType === 'in_kind'
            ? collect($this->input('items', []))
                ->map(function ($item) {
                    $item = is_array($item) ? $item : [];

                    return [
                        'name' => $this->nullableValue(Arr::get($item, 'name')),
                        'category' => $this->nullableLower(Arr::get($item, 'category')),
                        'category_description' => $this->nullableValue(Arr::get($item, 'category_description')),
                        'quantity' => $this->nullableValue(Arr::get($item, 'quantity')),
                        'unit' => $this->nullableLower(Arr::get($item, 'unit')),
                        'condition' => $this->nullableLower(Arr::get($item, 'condition')),
                        'expiration_date' => $this->nullableValue(Arr::get($item, 'expiration_date')),
                        'brand' => $this->nullableValue(Arr::get($item, 'brand')),
                        'model' => $this->nullableValue(Arr::get($item, 'model')),
                        'serial_number' => $this->nullableValue(Arr::get($item, 'serial_number')),
                        'estimated_value' => $this->nullableValue(Arr::get($item, 'estimated_value')),
                        'notes' => $this->nullableValue(Arr::get($item, 'notes')),
                    ];
                })
                ->filter(fn ($item) => collect($item)->filter(fn ($value) => $value !== null)->isNotEmpty())
                ->values()
                ->all()
            : [];

        $this->merge([
            'donation_type' => $donationType,
            'campaign_id' => $this->nullableValue($this->input('campaign_id')),
            'amount' => $this->nullableValue($this->input('amount')),
            'purpose_type' => $this->input('donation_type') === 'cash'
                ? ($this->nullableLower($this->input('purpose_type')) ?? 'general')
                : null,
            'purpose_name' => $this->nullableValue($this->input('purpose_name')),
            'payment_method' => $this->nullableLower($this->input('payment_method')),
            'payment_method_name' => $this->nullableValue($this->input('payment_method_name')),
            'official_receipt_number' => $this->nullableValue($this->input('official_receipt_number')),
            'check_number' => $this->nullableValue($this->input('check_number')),
            'bank_name' => $this->nullableValue($this->input('bank_name')),
            'registered_bank_account' => $this->nullableValue($this->input('registered_bank_account')),
            'reference_number' => $this->nullableValue($this->input('reference_number')),
            'notes' => $this->nullableValue($this->input('notes')),
            'items' => $items,
            'certificate_requested' => $this->boolean('certificate_requested'),
            'donor_tin' => $this->nullableValue($this->input('donor_tin')),
            'deed_declined' => $this->boolean('deed_declined'),
            'deed_decline_reason' => $this->nullableValue($this->input('deed_decline_reason')),
        ]);
    }

    private function requiresExternalReference(): bool
    {
        if ($this->input('donation_type') !== 'cash') {
            return false;
        }

        if (in_array($this->input('payment_method'), ['bank_transfer', 'gcash', 'maya'], true)) {
            return true;
        }

        // Preserve validation compatibility for older direct check submissions,
        // while new check donations use check_number and bank_name instead.
        return $this->input('payment_method') === 'check'
            && blank($this->input('check_number'))
            && blank($this->input('bank_name'));
    }

    private function requiresAdditionalDocuments(): bool
    {
        return $this->input('donation_type') === 'cash'
            && (float) $this->input('amount', 0) >= 5000
            && ! $this->boolean('deed_declined');
    }

    private function cashDeedException(): bool
    {
        return $this->input('donation_type') === 'cash'
            && (float) $this->input('amount', 0) >= 5000
            && $this->boolean('deed_declined');
    }

    private function nullableValue(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableLower(mixed $value): ?string
    {
        $value = $this->nullableValue($value);

        return $value ? Str::lower($value) : null;
    }
}
