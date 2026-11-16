@props(['value', 'decimals' => 2, 'empty' => '—', 'prefix' => '₱'])

{{ $value === null || $value === '' ? $empty : $prefix.number_format((float) $value, $decimals) }}
