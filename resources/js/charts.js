import { Chart } from 'chart.js/auto';

const formatValue = (raw, prefix, suffix) => {
    const value = typeof raw === 'number'
        ? raw.toLocaleString('en-US', { maximumFractionDigits: 2 })
        : raw;

    return `${prefix}${value}${suffix}`;
};

const buildTooltipOptions = (canvas) => {
    const prefix = canvas.dataset.valuePrefix ?? '';
    const suffix = canvas.dataset.valueSuffix ?? '';

    return {
        backgroundColor: '#0f172a',
        titleColor: '#f8fafc',
        bodyColor: '#e2e8f0',
        padding: 10,
        cornerRadius: 8,
        displayColors: true,
        boxPadding: 4,
        callbacks: {
            label(context) {
                const raw = context.parsed?.y ?? context.parsed?.x ?? context.parsed ?? context.raw;
                const label = context.label ? `${context.label}: ` : '';

                return `${label}${formatValue(raw, prefix, suffix)}`;
            },
        },
    };
};

const parseConfig = (canvas) => {
    try {
        return JSON.parse(canvas.dataset.chartConfig ?? '{}');
    } catch (error) {
        console.error('Unable to parse chart configuration for', canvas.id, error);

        return null;
    }
};

/**
 * Scans the given root for [data-chart] canvases and boots a Chart.js
 * instance for each one, using the JSON config the server already rendered
 * into data-chart-config. Safe to call repeatedly - already-rendered charts
 * are skipped.
 */
export function initCharts(root = document) {
    root.querySelectorAll('[data-chart]').forEach((canvas) => {
        if (canvas.dataset.chartRendered === 'true') {
            return;
        }

        const config = parseConfig(canvas);

        if (!config || !config.data) {
            return;
        }

        config.options = config.options ?? {};
        config.options.plugins = {
            ...(config.options.plugins ?? {}),
            tooltip: {
                ...(config.options.plugins?.tooltip ?? {}),
                ...buildTooltipOptions(canvas),
            },
        };

        // eslint-disable-next-line no-new
        new Chart(canvas, config);
        canvas.dataset.chartRendered = 'true';
    });
}

document.addEventListener('DOMContentLoaded', () => initCharts());
