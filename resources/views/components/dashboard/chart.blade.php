@props([
    'id',
    'type' => 'bar',
    'labels' => [],
    'values' => [],
    'colors' => null,
    'horizontal' => false,
    'height' => 220,
    'valuePrefix' => '',
    'valueSuffix' => '',
    'cutout' => '68%',
    'showLegend' => false,
])

{{--
    Reusable Chart.js canvas. Every number rendered comes from the $labels/$values
    arrays the caller passes in from live query results - this component holds no
    data of its own. Chart.js (chart.js/auto, installed via npm) is booted client-side
    by resources/js/charts.js, which reads the JSON config below off data-chart-config.
--}}

@php
    $defaultPalette = ['#2563eb', '#f59e0b', '#f97316', '#ef4444', '#10b981', '#8b5cf6', '#0ea5e9', '#64748b'];
    $palette = $colors ?? $defaultPalette;
    $labelValues = array_values($labels);
    $dataValues = array_values(array_map(static fn ($value) => (float) $value, $values));

    $segmentColors = [];
    foreach ($labelValues as $index => $label) {
        $segmentColors[] = $palette[$index % count($palette)];
    }

    $isDoughnut = $type === 'doughnut';
    $isLine = $type === 'line';

    $dataset = [
        'data' => $dataValues,
        'backgroundColor' => $isLine ? ($palette[0] ?? '#2563eb').'26' : $segmentColors,
        'borderColor' => $isLine ? ($palette[0] ?? '#2563eb') : ($isDoughnut ? '#ffffff' : 'transparent'),
        'borderWidth' => $isLine ? 2 : ($isDoughnut ? 3 : 0),
        'borderRadius' => $type === 'bar' ? 6 : 0,
        'borderSkipped' => false,
        'hoverOffset' => $isDoughnut ? 6 : 0,
        'fill' => $isLine,
        'tension' => $isLine ? 0.35 : 0,
        'pointRadius' => $isLine ? 3 : 0,
        'maxBarThickness' => 28,
    ];

    $config = [
        'type' => $horizontal ? 'bar' : $type,
        'data' => [
            'labels' => $labelValues,
            'datasets' => [$dataset],
        ],
        'options' => [
            'indexAxis' => $horizontal ? 'y' : 'x',
            'responsive' => true,
            'maintainAspectRatio' => false,
            'cutout' => $isDoughnut ? $cutout : null,
            'plugins' => [
                'legend' => ['display' => $showLegend, 'position' => 'bottom'],
            ],
            'scales' => in_array($type, ['bar', 'line'], true) ? [
                'x' => [
                    'display' => true,
                    'grid' => ['display' => false],
                    'ticks' => ['color' => '#64748b'],
                ],
                'y' => [
                    'display' => true,
                    'beginAtZero' => true,
                    'grid' => ['color' => '#eef2f7'],
                    'ticks' => ['color' => '#64748b'],
                ],
            ] : [],
        ],
    ];
@endphp

<div {{ $attributes->except('aria-label')->merge(['class' => 'relative w-full']) }} style="height: {{ $height }}px">
    <canvas
        id="{{ $id }}"
        data-chart
        data-chart-config="{{ json_encode($config) }}"
        data-value-prefix="{{ $valuePrefix }}"
        data-value-suffix="{{ $valueSuffix }}"
        role="img"
        aria-label="{{ $attributes->get('aria-label', 'Chart') }}"
    ></canvas>
</div>
