@props(['columns', 'title', 'description' => null])
<tr>
    <td colspan="{{ $columns }}">
        <div class="data-table__empty">
            <span class="mb-3 inline-flex rounded-full bg-slate-100 p-3 text-slate-400"><x-icon name="search" class="h-5 w-5" /></span>
            <p class="font-semibold text-slate-800">{{ $title }}</p>
            @if ($description)<p class="mt-1 text-sm leading-6 text-slate-500">{{ $description }}</p>
    @endif
        </div>
    </td>
</tr>
