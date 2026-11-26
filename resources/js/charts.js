import { ArcElement, Chart, PieController, Tooltip } from 'chart.js';

Chart.register(PieController, ArcElement, Tooltip);

/**
 * Draws every <canvas data-pie-chart='[{label, value, percent, color}]'>.
 * Slices keep a 2px white gap; hovering a slice shows its name, count and share.
 * The legend is the HTML list beside the chart (resources/views/partials/pie-chart.blade.php).
 */
export function drawPieCharts(root = document) {
    root.querySelectorAll('canvas[data-pie-chart]').forEach((canvas) => {
        const slices = JSON.parse(canvas.dataset.pieChart);

        new Chart(canvas, {
            type: 'pie',
            data: {
                labels: slices.map((s) => s.label),
                datasets: [{
                    data: slices.map((s) => s.value),
                    backgroundColor: slices.map((s) => s.color),
                    borderColor: '#ffffff',
                    // 2px gap between slices; none for a single slice (it would draw a seam).
                    borderWidth: slices.length > 1 ? 2 : 0,
                    hoverOffset: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 1,
                layout: { padding: 6 },
                animation: { duration: 400 },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#ffffff',
                        titleColor: '#0b0b0b',
                        bodyColor: '#52514e',
                        borderColor: 'rgba(11, 11, 11, 0.10)',
                        borderWidth: 1,
                        padding: 10,
                        boxPadding: 4,
                        usePointStyle: true,
                        callbacks: {
                            label: (context) => {
                                const slice = slices[context.dataIndex];
                                return ` ${slice.value} (${slice.percent}%)`;
                            },
                        },
                    },
                },
            },
        });
    });
}
