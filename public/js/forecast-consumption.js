(() => {
    'use strict';

    const initialize = () => {
        const select = document.getElementById('forecast-consumption-series');
        const canvas = document.getElementById('forecastConsumptionChart');
        const data = document.getElementById('forecast-consumption-data');
        const note = document.getElementById('forecast-consumption-note');
        const values = document.getElementById('forecast-consumption-values');
        if (!select || !canvas || !data || !note || !values) return;

        const series = JSON.parse(data.textContent);
        let chart;
        const render = () => {
            const item = series[Number(select.value)];
            if (!item) return;
            const usual = item.baseline === null
                ? 'More completed consumption history is needed to calculate the usual weekly amount.'
                : 'Usual weekly amount: ' + Number(item.baseline).toLocaleString(undefined, {maximumFractionDigits: 1}) + ' ' + item.unit + '.';
            note.textContent = usual;
            canvas.setAttribute('aria-label', 'Weekly consumption of ' + item.label + ', in ' + item.unit);
            values.replaceChildren(...item.history.map(week => {
                const row = document.createElement('tr');
                const period = document.createElement('td');
                const quantity = document.createElement('td');
                period.style.padding = quantity.style.padding = '8px';
                period.textContent = week.start + ' to ' + week.end;
                quantity.textContent = week.quantity === null ? 'No earlier consumption history'
                    : Number(week.quantity).toLocaleString() + ' ' + item.unit;
                row.append(period, quantity);
                return row;
            }));

            if (typeof Chart === 'undefined') {
                note.textContent = usual + ' Graph unavailable; view the graph values below.';
                return;
            }
            if (chart) chart.destroy();
            const datasets = [{
                label: 'Consumed',
                data: item.history.map(week => week.quantity),
                borderColor: '#0f766e', backgroundColor: 'rgba(15, 118, 110, 0.08)',
                borderWidth: 3, pointRadius: 4, pointHoverRadius: 6,
                fill: true, tension: 0, spanGaps: false,
            }];
            if (item.baseline !== null) {
                datasets.push({
                    label: 'Usual weekly amount',
                    data: item.history.map(() => item.baseline),
                    borderColor: '#64748b', borderWidth: 2, borderDash: [6, 5],
                    pointRadius: 0, fill: false,
                });
            }
            chart = new Chart(canvas, {
                type: 'line',
                data: {labels: item.history.map(week => week.start), datasets},
                options: {
                    responsive: true, maintainAspectRatio: false,
                    interaction: {mode: 'index', intersect: false},
                    plugins: {
                        legend: {position: 'bottom', labels: {usePointStyle: true, boxWidth: 8}},
                        tooltip: {callbacks: {label: context => context.dataset.label + ': '
                            + Number(context.parsed.y).toLocaleString(undefined, {maximumFractionDigits: 1}) + ' ' + item.unit}},
                    },
                    scales: {
                        x: {title: {display: true, text: 'Completed week starting'}, grid: {display: false}},
                        y: {beginAtZero: true, title: {display: true, text: 'Quantity consumed (' + item.unit + ')'}, ticks: {precision: 0}},
                    },
                },
            });
        };
        select.addEventListener('change', render);
        window.RhuConsumptionSearch?.enhance(select);
        render();
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, {once: true});
    else initialize();
})();
