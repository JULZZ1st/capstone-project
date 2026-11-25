@props(['dialogId', 'action', 'method', 'title', 'confirmLabel', 'confirmVariant' => 'primary'])

<dialog id="{{ $dialogId }}" class="m-auto w-[min(440px,calc(100vw-2rem))] rounded-lg p-0 shadow-xl backdrop:bg-slate-950/50">
    <form method="POST" action="{{ $action }}" class="bg-white">
        @csrf
        @if (strtoupper($method) !== 'POST')
            @method($method)
        @endif

        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <h2 class="text-base font-semibold text-slate-950">{{ $title }}</h2>
            <button type="button" class="rounded-md px-2 py-1 text-sm text-slate-500 hover:bg-slate-100" data-dialog-close="{{ $dialogId }}">Close</button>
        </div>

        <div class="space-y-2 px-5 py-4 text-sm text-slate-600">
            {{ $slot }}
        </div>

        <div class="flex flex-col-reverse gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end">
            <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-white" data-dialog-close="{{ $dialogId }}">Cancel</button>
            <button type="submit" class="ui-button {{ $confirmVariant === 'danger' ? 'ui-button--danger' : 'ui-button--primary' }} sm:w-auto">{{ $confirmLabel }}</button>
        </div>
    </form>
</dialog>
