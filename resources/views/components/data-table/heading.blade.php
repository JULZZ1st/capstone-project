@props(['align' => 'left'])
<th scope="col" {{ $attributes->class(['sticky top-0', 'data-table__number' => $align === 'right']) }}>{{ $slot }}</th>
