import Chart from 'chart.js/auto';

const dataElement = document.getElementById('report-data');
const canvas = document.getElementById('report-chart');

if (dataElement && canvas) {
    const data = JSON.parse(dataElement.textContent);
    const buttons = document.querySelectorAll('[data-metric]');
    let metric = 'temperature';
    let chart = null;

    const dayMs = 24 * 60 * 60 * 1000;
    const dates = data.labels.map((label) => new Date(label));
    const span = dates.length > 1 ? dates[dates.length - 1] - dates[0] : 0;

    // Rentang pendek cukup jam; rentang panjang butuh tanggal (dan jam bila bucket lebih kecil dari sehari).
    // Zona waktu aplikasi (bukan zona browser) supaya jam di grafik sama dengan tabel, daftar insiden, dan CSV.
    const timeZone = data.timezone;
    const formatLabel = (date) => {
        if (span <= 1.5 * dayMs) {
            return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', timeZone });
        }
        if (data.bucket_seconds >= 86400) {
            return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', timeZone });
        }
        return date.toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit', timeZone });
    };

    const flat = (value) => (value === null ? null : data.labels.map(() => value));

    function draw() {
        const isTemp = metric === 'temperature';
        const series = isTemp ? data.temperature : data.humidity;
        const min = isTemp ? data.temp_min : data.humidity_min;
        const max = isTemp ? data.temp_max : data.humidity_max;
        const unit = isTemp ? '°C' : '%';
        const line = isTemp ? '#4f7a5d' : '#0ea5e9';
        const faint = isTemp ? '#bcd3c3' : '#7dd3fc';

        const datasets = [
            { label: `Rata-rata (${unit})`, data: series.avg, borderColor: line, borderWidth: 2, pointRadius: 0, tension: 0.2 },
            { label: `Terendah (${unit})`, data: series.min, borderColor: faint, borderWidth: 1, pointRadius: 0, tension: 0.2 },
            { label: `Tertinggi (${unit})`, data: series.max, borderColor: faint, borderWidth: 1, pointRadius: 0, tension: 0.2 },
            { label: `Batas maks (${unit})`, data: flat(max), borderColor: '#d92d20', borderDash: [6, 4], borderWidth: 1, pointRadius: 0 },
            { label: `Batas min (${unit})`, data: flat(min), borderColor: '#dc6803', borderDash: [6, 4], borderWidth: 1, pointRadius: 0 },
        ].filter((dataset) => dataset.data !== null);

        chart?.destroy();
        chart = new Chart(canvas, {
            type: 'line',
            data: { labels: dates.map(formatLabel), datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false, // agar hasil cetak tidak setengah tergambar
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { ticks: { maxTicksLimit: 10, color: '#667085' }, grid: { display: false }, border: { color: '#e4e7ec' } },
                    y: { ticks: { color: '#667085' }, grid: { color: '#f2f4f7' }, border: { display: false } },
                },
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, color: '#475467' } } },
            },
        });
    }

    if (data.labels.length > 0) {
        draw();
    } else {
        canvas.parentElement.hidden = true;
    }

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            metric = button.dataset.metric;
            buttons.forEach((other) => other.classList.toggle('chip-active', other === button));
            if (data.labels.length > 0) draw();
        });
    });
}
