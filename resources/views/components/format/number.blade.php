@props(['value', 'decimals' => 0, 'empty' => '—'])

{{ $value === null || $value === '' ? $empty : number_format((float) $value, $decimals) }}
