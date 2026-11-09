import Chart from 'chart.js/auto';

const root = document.getElementById('dashboard');
const urls = {
    data: root.dataset.dataUrl,
    readings: root.dataset.readingsUrl,
    ack: root.dataset.ackUrl,
};
const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

let state = JSON.parse(document.getElementById('dashboard-data').textContent);
let selectedZoneId = null;
let metric = 'temperature';
let chart = null;
let chartTick = 0;

const STATUS = {
    aman: { label: 'AMAN', border: 'border-emerald-500', badge: 'bg-emerald-100 text-emerald-800' },
    waspada: { label: 'WASPADA', border: 'border-yellow-400', badge: 'bg-yellow-100 text-yellow-800' },
    bahaya: { label: 'BAHAYA', border: 'border-red-600', badge: 'bg-red-100 text-red-800', pulse: true },
    offline: { label: 'OFFLINE', border: 'border-slate-400', badge: 'bg-slate-200 text-slate-700' },
    tanpa_data: { label: 'BELUM ADA DATA', border: 'border-slate-300', badge: 'bg-slate-100 text-slate-600' },
};

const esc = (value) =>
    String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

const num = (value) => (value === null || value === undefined ? '–' : Number(value).toFixed(1).replace(/\.0$/, ''));

function ago(seconds) {
    if (seconds === null) return 'belum pernah';
    if (seconds < 60) return `${seconds} dtk lalu`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)} mnt lalu`;
    return `${Math.floor(seconds / 3600)} jam lalu`;
}

function renderBanners() {
    const html = state.banners
        .map((b) => {
            const acknowledged = b.acknowledged_by !== null;
            const palette = {
                'kritis-false': 'bg-red-600 text-white',
                'kritis-true': 'bg-amber-400 text-slate-900',
                'offline-false': 'bg-stone-700 text-orange-100',
                'offline-true': 'bg-stone-500 text-white',
            }[`${b.kind}-${acknowledged}`];
            const icon = b.kind === 'kritis' ? '🚨' : '📡';
            const text = acknowledged
                ? `🛠️ Sedang ditangani oleh <strong>${esc(b.acknowledged_by)}</strong>. ${esc(b.message)}`
                : `${icon} <strong>${b.kind === 'kritis' ? 'KRITIS:' : 'SENSOR OFFLINE:'}</strong> ${esc(b.message)}`;
            const button = acknowledged
                ? ''
                : `<button type="button" data-ack="${b.incident_id}" class="w-full rounded-lg bg-white px-4 py-3 text-sm font-semibold text-slate-900 shadow sm:w-auto">Tandai Telah Ditangani</button>`;

            return `<div class="${palette}"><div class="mx-auto flex max-w-6xl flex-col gap-2 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between"><p>${text}</p>${button}</div></div>`;
        })
        .join('');
    document.getElementById('banners').innerHTML = html;
}

function renderZones() {
    const container = document.getElementById('zones');
    if (state.zones.length === 0) {
        container.innerHTML = '<p class="rounded-xl bg-white p-4 text-slate-500">Belum ada zona terdaftar.</p>';
        return;
    }

    container.innerHTML = state.zones
        .map((z) => {
            const s = STATUS[z.status] ?? STATUS.tanpa_data;
            const danger = z.status === 'bahaya';
            const dot = s.pulse
                ? '<span class="relative mr-1 inline-flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-500 opacity-75"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-red-600"></span></span>'
                : '';
            const arrow = z.trend === 'naik'
                ? '<span class="text-2xl font-bold text-red-600" title="Suhu di atas batas">↑</span>'
                : z.trend === 'turun'
                    ? '<span class="text-2xl font-bold text-blue-600" title="Suhu di bawah batas">↓</span>'
                    : '';
            const limit = z.trend === 'turun' ? `(Min: ${num(z.temp_min)}°C)` : `(Max: ${num(z.temp_max)}°C)`;
            const limitColor = z.trend ? 'text-red-600' : 'text-slate-500';
            const humidityLimit = z.humidity_min !== null && z.humidity_max !== null
                ? ` · RH ${num(z.humidity_min)}–${num(z.humidity_max)}%`
                : '';
            // Bila penyebab bahaya adalah kelembapan, sorot barisnya seperti suhu.
            const humidityArrow = z.humidity_trend === 'naik' ? ' ↑' : z.humidity_trend === 'turun' ? ' ↓' : '';
            const humidityClass = z.humidity_trend ? 'font-bold text-red-600' : '';
            const humidityBound = z.humidity_trend === 'naik'
                ? ` (Max: ${num(z.humidity_max)}%)`
                : z.humidity_trend === 'turun' ? ` (Min: ${num(z.humidity_min)}%)` : '';

            return `<article class="rounded-xl border-l-8 ${s.border} bg-white p-4 shadow-sm ${danger ? 'ring-2 ring-red-200' : ''}">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="font-semibold">${esc(z.name)}</h3>
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ${s.badge}">${dot}${s.label}</span>
                </div>
                <div class="mt-2 flex flex-wrap items-baseline gap-2">
                    <span class="text-4xl font-bold ${danger ? 'text-red-600' : ''}">${num(z.temperature)}°C</span>${arrow}
                    <span class="text-sm font-medium ${limitColor}">${limit}</span>
                </div>
                <p class="mt-1 text-sm text-slate-500">💧 <span class="${humidityClass}">${num(z.humidity)}%${humidityArrow}${humidityBound}</span>${humidityLimit}</p>
                <p class="text-xs text-slate-400">update ${ago(z.last_seen_seconds)}</p>
            </article>`;
        })
        .join('');
}

function renderChrome() {
    document.getElementById('scheduler-warning').hidden = !state.scheduler_stale;
    document.querySelectorAll('[data-unread-badge]').forEach((el) => {
        el.textContent = state.unread_notifications;
        el.hidden = state.unread_notifications === 0;
    });
}

function renderZoneChips() {
    const holder = document.getElementById('chart-zone-chips');
    if (selectedZoneId === null || !state.zones.some((z) => z.id === selectedZoneId)) {
        selectedZoneId = state.zones[0]?.id ?? null;
    }
    holder.innerHTML = state.zones
        .map((z) => `<button type="button" data-zone="${z.id}" class="chip ${z.id === selectedZoneId ? 'chip-active' : ''}">${esc(z.name)}</button>`)
        .join('');
}

async function loadChart() {
    const empty = document.getElementById('chart-empty');
    const canvas = document.getElementById('trend-chart');
    if (selectedZoneId === null) {
        chart?.destroy();
        chart = null;
        empty.hidden = false;
        return;
    }

    const response = await fetch(urls.readings.replace('__ID__', selectedZoneId) + '?hours=24', {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });
    if (!response.ok) return;
    const data = await response.json();

    const isTemp = metric === 'temperature';
    const values = isTemp ? data.temperature : data.humidity;
    const min = isTemp ? data.temp_min : data.humidity_min;
    const max = isTemp ? data.temp_max : data.humidity_max;
    const unit = isTemp ? '°C' : '%';
    const labels = data.labels.map((l) => new Date(l).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }));
    const flat = (v) => (v === null ? null : labels.map(() => v));

    const datasets = [
        { label: isTemp ? 'Suhu (°C)' : 'Kelembapan (%)', data: values, borderColor: isTemp ? '#2563eb' : '#0ea5e9', borderWidth: 2, pointRadius: 0, tension: 0.3 },
        { label: `Batas maks (${unit})`, data: flat(max), borderColor: '#dc2626', borderDash: [6, 4], borderWidth: 1, pointRadius: 0 },
        { label: `Batas min (${unit})`, data: flat(min), borderColor: '#f59e0b', borderDash: [6, 4], borderWidth: 1, pointRadius: 0 },
    ].filter((d) => d.data !== null);

    empty.hidden = labels.length > 0;
    chart?.destroy();
    chart = new Chart(canvas, {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            interaction: { mode: 'index', intersect: false },
            scales: { x: { ticks: { maxTicksLimit: 8 } } },
            plugins: { legend: { position: 'bottom' } },
        },
    });
}

function render() {
    renderBanners();
    renderZones();
    renderChrome();
}

async function refresh() {
    const warning = document.getElementById('connection-warning');
    try {
        const response = await fetch(urls.data, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (response.status === 401 || response.status === 419) {
            window.location.reload();
            return;
        }
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        state = await response.json();
        warning.hidden = true;
        render();
        renderZoneChips();
        if (++chartTick % 4 === 0) await loadChart();
    } catch {
        warning.hidden = false;
    }
}

document.getElementById('banners').addEventListener('click', async (event) => {
    const button = event.target.closest('button[data-ack]');
    if (!button) return;
    button.disabled = true;
    await fetch(urls.ack.replace('__ID__', button.dataset.ack), {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        credentials: 'same-origin',
    });
    await refresh();
});

document.getElementById('chart-zone-chips').addEventListener('click', (event) => {
    const button = event.target.closest('button[data-zone]');
    if (!button) return;
    selectedZoneId = Number(button.dataset.zone);
    renderZoneChips();
    loadChart();
});

document.getElementById('chart-metric-chips').addEventListener('click', (event) => {
    const button = event.target.closest('button[data-metric]');
    if (!button) return;
    metric = button.dataset.metric;
    document.querySelectorAll('#chart-metric-chips .chip').forEach((el) => el.classList.toggle('chip-active', el === button));
    loadChart();
});

render();
renderZoneChips();
loadChart();
setInterval(refresh, 15000);
