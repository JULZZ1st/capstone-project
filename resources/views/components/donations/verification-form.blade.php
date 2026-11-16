@props(['contribution'])

@php($verificationErrors = $contribution->verificationErrors())

<form method="POST" action="{{ route('donations.verify', $contribution) }}" {{ $attributes->class(['space-y-3']) }} data-donation-verification>
    @csrf
    @method('PATCH')
    @if ($verificationErrors)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950" role="status">
            <p class="font-semibold">Complete the required documents before verification.</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($verificationErrors as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
            <a href="{{ route('donations.show', $contribution) }}" class="mt-2 inline-block font-semibold text-blue-800 underline">Review donation documents</a>
        </div>
    @endif
    <x-button :disabled="count($verificationErrors) > 0" class="bg-emerald-700 hover:bg-emerald-800">Verify Donation</x-button>
</form>
