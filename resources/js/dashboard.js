import Chart from 'chart.js/auto';

const root = document.getElementById('dashboard');
const urls = {
    data: root.dataset.dataUrl,
    readings: root.dataset.readingsUrl,
    ack: root.dataset.ackUrl,
};
const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

let state = JSON.parse(document.getElementById('dashboard-data').textContent);
let selectedWarehouse = 'all'; // 'all' atau id gudang
let selectedZoneId = null;
let metric = 'temperature';
let chart = null;
let chartTick = 0;

const STATUS = {
    aman: { label: 'AMAN', border: 'border-l-success-500', badge: 'bg-success-50 text-success-700', dot: 'bg-success-500' },
    waspada: { label: 'WASPADA', border: 'border-l-warning-500', badge: 'bg-warning-50 text-warning-700', dot: 'bg-warning-500' },
    bahaya: { label: 'BAHAYA', border: 'border-l-error-600', badge: 'bg-error-50 text-error-700', pulse: true, dot: 'bg-error-500' },
    offline: { label: 'OFFLINE', border: 'border-l-gray-400', badge: 'bg-gray-100 text-gray-700', dot: 'bg-gray-400' },
    tanpa_data: { label: 'BELUM ADA DATA', border: 'border-l-gray-300', badge: 'bg-gray-100 text-gray-600', dot: 'bg-gray-300' },
};

// Ikon garis kecil (24x24) yang dipakai di string HTML; isinya statis.
const icon = (paths, cls = 'size-5') =>
    `<svg class="${cls}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths}</svg>`;
const ICON = {
    thermometer: icon('<path d="M14 14.8V5a2 2 0 0 0-4 0v9.8a4 4 0 1 0 4 0Z"/>', 'size-6'),
    droplet: icon('<path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11Z"/>', 'size-4'),
    clock: icon('<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>', 'size-3.5'),
    up: icon('<path d="M12 19V5M6 11l6-6 6 6"/>', 'size-3.5'),
    down: icon('<path d="M12 5v14M6 13l6 6 6-6"/>', 'size-3.5'),
    alert: icon('<path d="M12 3 2.5 20h19L12 3Z"/><path d="M12 10v4.5M12 17.5v.01"/>', 'size-5 shrink-0'),
    offline: icon('<path d="M2 8.8a15 15 0 0 1 20 0M5 12.5a10 10 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0M12 19.5v.01M3 3l18 18"/>', 'size-5 shrink-0'),
    tool: icon('<path d="M14.5 6.5a4 4 0 0 0-5 5L3 18l3 3 6.5-6.5a4 4 0 0 0 5-5l-2.5 2.5-2.5-.5-.5-2.5 2.5-2.5Z"/>', 'size-5 shrink-0'),
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

// Gudang yang dipilih: dari ?gudang=ID bila valid; selain itu "semua" (atau gudang tunggal).
function initialWarehouse() {
    const param = new URLSearchParams(window.location.search).get('gudang');
    if (param !== null && state.warehouses.some((w) => String(w.id) === param)) return Number(param);
    return state.warehouses.length === 1 ? state.warehouses[0].id : 'all';
}

function ensureValidWarehouse() {
    if (selectedWarehouse !== 'all' && !state.warehouses.some((w) => w.id === selectedWarehouse)) {
        selectedWarehouse = state.warehouses.length === 1 ? state.warehouses[0].id : 'all';
    }
}

function visibleZones() {
    return selectedWarehouse === 'all' ? state.zones : state.zones.filter((z) => z.warehouse_id === selectedWarehouse);
}

function worstStatus() {
    const order = ['bahaya', 'waspada', 'offline', 'tanpa_data', 'aman'];
    const found = order.find((s) => state.warehouses.some((w) => w.status === s));
    return found ?? 'tanpa_data';
}

function renderAccessNotice() {
    let notice = document.getElementById('no-access-notice');
    if (state.has_access) {
        notice?.remove();
        return;
    }
    if (!notice) {
        notice = document.createElement('p');
        notice.id = 'no-access-notice';
        notice.className = 'mb-4 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700';
        notice.textContent = 'Anda belum ditugaskan ke gudang mana pun. Hubungi administrator.';
        document.getElementById('warehouse-chips').before(notice);
    }
}

function renderWarehouseChips() {
    const holder = document.getElementById('warehouse-chips');
    if (state.warehouses.length === 0) {
        holder.innerHTML = '';
        return;
    }

    const chip = (key, label, status, open) => {
        const active = String(selectedWarehouse) === String(key) ? 'chip-active' : '';
        const badge = open > 0 ? `<span class="ml-1 rounded-full bg-error-600 px-1.5 text-[10px] font-semibold text-white">${open}</span>` : '';
        const dot = `<span class="size-2 shrink-0 rounded-full ${(STATUS[status] ?? STATUS.tanpa_data).dot}"></span>`;
        return `<button type="button" data-warehouse="${key}" class="chip ${active}">${dot}${esc(label)}${badge}</button>`;
    };

    const all = state.warehouses.length > 1
        ? chip('all', 'Semua gudang', worstStatus(), state.warehouses.reduce((sum, w) => sum + w.open_incidents, 0))
        : '';
    holder.innerHTML = all + state.warehouses.map((w) => chip(w.id, w.name, w.status, w.open_incidents)).join('');
}

function renderBanners() {
    const html = state.banners
        .map((b) => {
            const acknowledged = b.acknowledged_by !== null;
            const palette = {
                'kritis-false': 'bg-error-600 text-white',
                'kritis-true': 'bg-warning-500 text-gray-900',
                'offline-false': 'bg-gray-800 text-white',
                'offline-true': 'bg-gray-600 text-white',
            }[`${b.kind}-${acknowledged}`];
            const glyph = acknowledged ? ICON.tool : b.kind === 'kritis' ? ICON.alert : ICON.offline;
            const where = `<strong>${esc(b.location)}</strong> — ${esc(b.message)}`;
            const text = acknowledged
                ? `Sedang ditangani oleh <strong>${esc(b.acknowledged_by)}</strong>. ${where}`
                : `<strong>${b.kind === 'kritis' ? 'KRITIS:' : 'SENSOR OFFLINE:'}</strong> ${where}`;
            const button = acknowledged || !state.can_acknowledge
                ? ''
                : `<button type="button" data-ack="${b.incident_id}" class="w-full rounded-lg bg-white px-4 py-3 text-sm font-semibold text-gray-900 shadow-sm hover:bg-gray-100 sm:w-auto">Tandai Telah Ditangani</button>`;

            return `<div class="${palette}"><div class="flex flex-col gap-3 px-4 py-3 text-sm md:px-6 sm:flex-row sm:items-center sm:justify-between"><p class="flex items-start gap-2">${glyph}<span>${text}</span></p>${button}</div></div>`;
        })
        .join('');
    document.getElementById('banners').innerHTML = html;
}

function renderZones() {
    const container = document.getElementById('zones');
    const zones = visibleZones();
    if (zones.length === 0) {
        container.innerHTML = state.has_access
            ? '<p class="rounded-2xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500 sm:col-span-2 xl:col-span-3">Belum ada zona terdaftar di gudang ini.</p>'
            : '';
        return;
    }

    const showWarehouse = selectedWarehouse === 'all' && state.warehouses.length > 1;

    container.innerHTML = zones
        .map((z) => {
            const s = STATUS[z.status] ?? STATUS.tanpa_data;
            const danger = z.status === 'bahaya';
            const dot = s.pulse
                ? '<span class="relative inline-flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-error-500 opacity-75"></span><span class="relative inline-flex size-2 rounded-full bg-error-600"></span></span>'
                : '';
            const trend = z.trend === 'naik'
                ? `<span class="inline-flex items-center gap-0.5 rounded-full bg-error-50 px-2 py-0.5 text-xs font-medium text-error-700" title="Suhu di atas batas">${ICON.up} Di atas batas</span>`
                : z.trend === 'turun'
                    ? `<span class="inline-flex items-center gap-0.5 rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700" title="Suhu di bawah batas">${ICON.down} Di bawah batas</span>`
                    : '';
            const limit = z.trend === 'turun' ? `(Min: ${num(z.temp_min)}°C)` : `(Max: ${num(z.temp_max)}°C)`;
            const limitColor = z.trend ? 'font-medium text-error-600' : 'text-gray-500';
            const humidityLimit = z.humidity_min !== null && z.humidity_max !== null
                ? `<span class="text-xs text-gray-500">RH ${num(z.humidity_min)}–${num(z.humidity_max)}%</span>`
                : '';
            // Bila penyebab bahaya adalah kelembapan, sorot barisnya seperti suhu.
            const humidityArrow = z.humidity_trend === 'naik' ? ' ↑' : z.humidity_trend === 'turun' ? ' ↓' : '';
            const humidityClass = z.humidity_trend ? 'font-semibold text-error-600' : 'text-gray-700';
            const humidityBound = z.humidity_trend === 'naik'
                ? ` (Max: ${num(z.humidity_max)}%)`
                : z.humidity_trend === 'turun' ? ` (Min: ${num(z.humidity_min)}%)` : '';
            const products = z.products.length > 0
                ? `<div class="mt-3 flex flex-wrap gap-1.5">${z.products.map((p) => `<span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-600">${esc(p)}</span>`).join('')}</div>`
                : '';
            const warehouseLabel = showWarehouse ? `<p class="truncate text-xs text-gray-500">${esc(z.warehouse_name)}</p>` : '';

            return `<article class="rounded-2xl border border-l-4 border-gray-200 ${s.border} bg-white p-5 ${danger ? 'ring-4 ring-error-100' : ''}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0"><h3 class="truncate text-base font-semibold text-gray-800">${esc(z.name)}</h3>${warehouseLabel}</div>
                    <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ${s.badge}">${dot}${s.label}</span>
                </div>
                <div class="mt-4 flex items-center gap-3">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl ${danger ? 'bg-error-50 text-error-600' : 'bg-gray-100 text-gray-600'}">${ICON.thermometer}</span>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <span class="text-3xl font-semibold ${danger ? 'text-error-600' : 'text-gray-800'}">${num(z.temperature)}°C</span>${trend}
                        </div>
                        <p class="text-xs ${limitColor}">${limit}</p>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between gap-2 rounded-xl bg-gray-50 px-3 py-2 text-sm">
                    <span class="flex items-center gap-2 ${humidityClass}">${ICON.droplet}<span>${num(z.humidity)}%${humidityArrow}${humidityBound}</span></span>
                    ${humidityLimit}
                </div>
                <p class="mt-3 flex items-center gap-1.5 text-xs text-gray-500">${ICON.clock} update ${ago(z.last_seen_seconds)}</p>
                ${products}
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
    const zones = visibleZones();
    if (selectedZoneId === null || !zones.some((z) => z.id === selectedZoneId)) {
        selectedZoneId = zones[0]?.id ?? null;
    }
    holder.innerHTML = zones
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
        { label: isTemp ? 'Suhu (°C)' : 'Kelembapan (%)', data: values, borderColor: isTemp ? '#4f7a5d' : '#0ea5e9', borderWidth: 2, pointRadius: 0, tension: 0.3 },
        { label: `Batas maks (${unit})`, data: flat(max), borderColor: '#d92d20', borderDash: [6, 4], borderWidth: 1, pointRadius: 0 },
        { label: `Batas min (${unit})`, data: flat(min), borderColor: '#dc6803', borderDash: [6, 4], borderWidth: 1, pointRadius: 0 },
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
            scales: {
                x: { ticks: { maxTicksLimit: 8, color: '#667085' }, grid: { display: false }, border: { color: '#e4e7ec' } },
                y: { ticks: { color: '#667085' }, grid: { color: '#f2f4f7' }, border: { display: false } },
            },
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, color: '#475467' } } },
        },
    });
}

function render() {
    renderAccessNotice();
    renderWarehouseChips();
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
        // 403: izin user dicabut saat halaman terbuka; muat ulang agar halaman menjelaskannya.
        if (response.status === 401 || response.status === 403 || response.status === 419) {
            window.location.reload();
            return;
        }
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        state = await response.json();
        warning.hidden = true;
        ensureValidWarehouse();
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

document.getElementById('warehouse-chips').addEventListener('click', (event) => {
    const button = event.target.closest('button[data-warehouse]');
    if (!button) return;
    selectedWarehouse = button.dataset.warehouse === 'all' ? 'all' : Number(button.dataset.warehouse);

    // Simpan pilihan di alamat halaman agar bisa dibagikan.
    const url = new URL(window.location.href);
    if (selectedWarehouse === 'all') url.searchParams.delete('gudang');
    else url.searchParams.set('gudang', selectedWarehouse);
    window.history.replaceState(null, '', url);

    renderWarehouseChips();
    renderZones();
    renderZoneChips();
    loadChart();
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

selectedWarehouse = initialWarehouse();
render();
renderZoneChips();
loadChart();
setInterval(refresh, 15000);
