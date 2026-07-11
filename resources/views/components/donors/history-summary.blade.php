@props(['donor'])

<div class="grid gap-4 sm:grid-cols-3">
    <x-dashboard.summary-card label="Total Donations" :value="number_format($donor->donations_count)" />
    <x-dashboard.summary-card label="Donor Type" :value="config('donors.types')[$donor->donor_type] ?? ucfirst($donor->donor_type)" />
    <x-dashboard.summary-card label="Status" :value="config('donors.statuses')[$donor->status] ?? ucfirst($donor->status)" />
</div>
