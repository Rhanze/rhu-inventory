(() => {
    const source = document.getElementById('disease-consumption-chart-data');
    if (!source) return;
    const analysis = JSON.parse(source.textContent);
    const medicines = analysis.medicines;
    const selector = document.getElementById('consumption-chart-medicine');
    const modeSelector = document.getElementById('consumption-chart-mode');
    if (modeSelector) modeSelector.value = analysis.default_mode || 'periods';
    const format = value => new Intl.NumberFormat(undefined, {maximumFractionDigits: 1}).format(value);
    const name = medicine => [medicine.name, medicine.dosage, medicine.dosage_form].filter(Boolean).join(' ');
    const period = analysis.period_type === 'monthly' ? 'month' : 'week';
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let historyChart;

    function showMedicine() {
        const medicine = medicines.find(item => String(item.id) === selector.value);
        if (!medicine) return;
        const transactions = modeSelector?.value === 'transactions' && Array.isArray(medicine.transactions_data);
        const points = transactions ? medicine.transactions_data : medicine.history;
        document.getElementById('consumption-chart-associations').textContent = medicine.diseases.length
            ? 'Associated diseases: ' + medicine.diseases.join(', ')
            : 'No configured disease association. This graph shows recorded medicine consumption only.';
        document.getElementById('consumption-graph-unit').textContent = 'Consumed (' + medicine.unit + ')';
        const dateHeading = document.getElementById('consumption-graph-dates');
        if (dateHeading) dateHeading.textContent = transactions ? 'Dispensing date / record' : 'Dates included';
        const rows = document.getElementById('consumption-graph-rows');
        rows.replaceChildren();
        points.forEach((point, index) => {
            const row = document.createElement('tr');
            const values = [transactions ? point.date + ' / record ' + (index + 1) : point.included_start + ' to ' + point.included_end,
                point.quantity === null ? 'Before first recorded consumption' : format(point.quantity),
                transactions ? '1' : (point.quantity === null ? '—' : format(point.transactions))];
            values.forEach(value => {
                const cell = document.createElement('td');
                cell.textContent = value;
                row.appendChild(cell);
            });
            rows.appendChild(row);
        });
        let summary = analysis.historical
            ? (analysis.saved_history
                ? 'Saved history from ' + analysis.window.from + ' to ' + analysis.window.to + ' contains ' + format(medicine.total_quantity) + ' ' + medicine.unit + ' consumed.'
                : 'Selected dates only: ' + format(medicine.total_quantity) + ' ' + medicine.unit + ' consumed. Boundary periods include only the dates you selected.')
            : 'Latest completed ' + period + ': ' + format(medicine.current_quantity) + ' ' + medicine.unit + '. ' +
                (medicine.baseline_average === null ? 'Four prior completed periods are needed to establish usual consumption.'
                    : 'Usual consumption: ' + format(medicine.baseline_average) + ' ' + medicine.unit + ' per ' + period + '.') +
                ' Gaps before recording began remain unknown; zero means no recorded consumption.';
        if (transactions) summary = format(medicine.transactions_data.length) + ' saved dispensing transactions, totaling ' +
            format(medicine.total_quantity) + ' ' + medicine.unit + '. Each bar is one recorded transaction on its actual date; same-date records remain separate. This is not a time-trend estimate.';
        document.getElementById('consumption-chart-summary').textContent = summary;
        if (typeof Chart === 'undefined') {
            document.getElementById('consumption-chart-unavailable').hidden = false;
            return;
        }
        const datasets = [{label: transactions ? 'Dispensed per transaction' : 'Recorded consumption',
            data: points.map(point => point.quantity), borderColor: '#0f766e',
            backgroundColor: '#0f766e', pointRadius: 4, tension: 0, spanGaps: false}];
        if (!transactions && !analysis.historical && medicine.baseline_average !== null) {
            datasets.push({label: 'Usual consumption',
                data: medicine.history.map(() => medicine.baseline_average), borderColor: '#64748b',
                backgroundColor: '#64748b', borderDash: [6, 4], pointRadius: 0, tension: 0});
        }
        historyChart?.destroy();
        historyChart = new Chart(document.getElementById('diseaseConsumptionHistoryChart'), {
            type: transactions ? 'bar' : 'line',
            data: {labels: points.map((point, index) => transactions ? [point.date, 'Record ' + (index + 1)] : point.start), datasets},
            options: {
                responsive: true, maintainAspectRatio: false, animation: reduceMotion ? false : undefined,
                interaction: {mode: 'index', intersect: false},
                plugins: {legend: {position: 'bottom', labels: {boxWidth: 18}},
                    tooltip: {callbacks: {
                        title: items => {const index = items[0].dataIndex; const point = points[index]; return transactions
                            ? point.date + ' / record ' + (index + 1) : point.included_start + ' to ' + point.included_end;},
                        label: item => item.dataset.label + ': ' + format(item.raw) + ' ' + medicine.unit
                    }}},
                scales: {y: {beginAtZero: true, title: {display: true, text: 'Consumed (' + medicine.unit + ')'}},
                    x: {title: {display: true, text: transactions ? 'Recorded dispensing date / record' : (period === 'month' ? 'Month starting' : 'Week starting')}, ticks: {maxTicksLimit: 6}}}
            }
        });
    }

    const hasRecordedConsumption = medicine => medicine.history.some(point => point.transactions > 0);
    const initialMedicine = medicines.find(item => String(item.id) === String(analysis.preferred_medicine_id) && hasRecordedConsumption(item))
        || medicines.find(item => item.status === 'elevated' && hasRecordedConsumption(item))
        || medicines.find(hasRecordedConsumption) || medicines[0];
    selector.value = String(initialMedicine.id);
    selector.addEventListener('change', showMedicine);
    modeSelector?.addEventListener('change', showMedicine);
    showMedicine();
    window.RhuConsumptionSearch?.enhance(selector);

    const comparisonCanvas = document.getElementById('diseaseConsumptionChangeChart');
    if (!comparisonCanvas) return;
    const comparable = medicines.filter(item => item.change_percent !== null)
        .sort((left, right) => right.change_percent - left.change_percent || name(left).localeCompare(name(right)));
    const displayed = comparable.slice(0, 10);
    const note = document.getElementById('consumption-comparison-note');
    if (!displayed.length) {
        document.getElementById('consumption-comparison-wrap').hidden = true;
        note.textContent = 'No percentage comparisons yet. A positive usual-consumption baseline and four prior completed periods are needed. New consumption and short histories remain in the evidence table.';
        return;
    }
    note.textContent = (comparable.length > 10 ? 'Showing the 10 highest consumption changes out of ' + comparable.length + ' comparable medicines. ' : '') +
        'Each bar represents one medicine. Hover or tap to see its linked diseases. Percentages describe consumption changes; they are not disease probabilities.';
    if (typeof Chart === 'undefined') return;
    function wrapLabel(value) {
        const limit = document.getElementById('consumption-comparison-wrap').clientWidth < 400 ? 15 : 22;
        const lines = [];
        let line = '';
        value.split(/\s+/).forEach(word => {
            if (line && (line + ' ' + word).length > limit) {lines.push(line); line = word;}
            else line = line ? line + ' ' + word : word;
        });
        if (line) lines.push(line);
        return lines;
    }
    const comparisonLabels = displayed.map(item => wrapLabel(name(item)));
    document.getElementById('consumption-comparison-wrap').style.height = Math.max(280,
        100 + displayed.length * Math.max(...comparisonLabels.map(lines => Math.max(64, lines.length * 16 + 16)))) + 'px';
    new Chart(comparisonCanvas, {
        type: 'bar',
        data: {labels: comparisonLabels, datasets: [{label: 'Consumption change',
            data: displayed.map(item => item.change_percent),
            backgroundColor: displayed.map(item => item.status === 'elevated' ? '#b45309' : (item.change_percent < 0 ? '#64748b' : '#0f766e')),
            borderRadius: 4}]},
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false, animation: reduceMotion ? false : undefined,
            plugins: {legend: {display: false}, tooltip: {callbacks: {
                title: items => name(displayed[items[0].dataIndex]),
                label: item => (item.raw > 0 ? '+' : '') + format(item.raw) + '% consumption change',
                afterLabel: item => {const medicine = displayed[item.dataIndex]; return ['Latest: ' + format(medicine.current_quantity) + ' ' + medicine.unit,
                    'Usual: ' + format(medicine.baseline_average) + ' ' + medicine.unit, ...medicine.diseases.map(disease => 'Associated: ' + disease)];}
            }}},
            scales: {x: {beginAtZero: true, suggestedMax: analysis.threshold_percent + 10, title: {display: true, text: 'Consumption change (%)'}, ticks: {callback: value => value + '%'}},
                y: {grid: {display: false}, ticks: {autoSkip: false, font: {size: 13}}}}
        },
        plugins: [{id: 'consumptionReviewThreshold', afterDraw(chart) {
            const {ctx, chartArea, scales} = chart;
            const x = scales.x.getPixelForValue(analysis.threshold_percent);
            ctx.save();
            ctx.strokeStyle = '#b45309'; ctx.lineWidth = 2; ctx.setLineDash([6, 4]);
            ctx.beginPath(); ctx.moveTo(x, chartArea.top); ctx.lineTo(x, chartArea.bottom); ctx.stroke();
            ctx.restore();
        }}]
    });
})();
