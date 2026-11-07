@props(['title' => 'Supporting Evidence', 'description' => 'Attach proof or evidence for this contribution.', 'inputName' => 'evidence', 'errorName' => 'evidence', 'showOptional' => true])

<section class="space-y-3 rounded-xl border border-slate-200 bg-white p-3.5 sm:p-4" data-evidence-uploader>
    <div class="flex items-center gap-2">
        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-700"><x-icon name="document" class="h-4 w-4" /></span>
        <div>
            <h3 class="text-sm font-semibold text-slate-950">{{ $title }} @if($showOptional)<span class="font-normal text-slate-400">(optional)</span>@endif</h3>
            <p class="text-xs text-slate-500">{{ $description }}</p>
        </div>
    </div>

    <input type="file" name="{{ $inputName }}[]" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" multiple class="sr-only" data-evidence-input>

    <button type="button" class="flex min-h-24 w-full flex-col items-center justify-center rounded-lg border-2 border-dashed border-blue-300 bg-blue-50/30 px-4 py-4 text-center transition hover:border-blue-500 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500" data-evidence-dropzone>
        <x-icon name="upload" class="h-7 w-7 text-blue-600" />
        <span class="mt-1 text-sm font-semibold text-slate-900">Upload Attachment</span>
        <span class="text-xs text-slate-500">Drag and drop a file here, or click to browse</span>
        <span class="mt-1 text-[11px] text-slate-400">PDF, JPG, JPEG, or PNG up to 10MB per file</span>
    </button>

    <p class="hidden rounded-lg bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700" data-evidence-error role="alert"></p>
    <div class="hidden space-y-2" data-evidence-list></div>
    <x-form.error :name="$errorName" />
    @foreach ($errors->get($errorName.'.*') as $messages)
        @foreach ((array) $messages as $message)
            <p class="text-sm text-red-600">{{ $message }}</p>
        @endforeach
    @endforeach
</section>
