@props(['value', 'decimals' => 2, 'empty' => '—'])

{{ $value === null || $value === '' ? $empty : '₱'.number_format((float) $value, $decimals) }}
