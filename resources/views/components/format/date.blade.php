@props(['value', 'format' => 'M d, Y', 'empty' => '—'])

{{ filled($value) ? IlluminateSupportCarbon::parse($value)->format($format) : $empty }}
