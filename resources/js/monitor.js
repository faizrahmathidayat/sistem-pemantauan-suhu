// Monitor publik untuk TV gudang. Hanya membaca: mengambil data tiap 10 detik dan menggambar ulang.
// Aturan utama: layar yang tidak segar tidak boleh terlihat seperti kondisi aman.

const POLL_MS = 10_000;
const FETCH_TIMEOUT_MS = 8_000;
const STALE_AFTER_MS = 45_000; // tanpa respons sukses selama ini = tampilkan "tidak terhubung"
const RELOAD_AFTER_MS = 6 * 60 * 60 * 1000; // muat ulang berkala agar TV ikut versi aplikasi terbaru
const COMPACT_ABOVE = 6; // lebih dari ini, kartu dikecilkan supaya muat satu layar

const root = document.getElementById('monitor');
const dataUrl = root.dataset.url;
const startedAt = Date.now();

let state = JSON.parse(document.getElementById('monitor-initial').textContent);
let receivedAt = Date.now(); // waktu lokal saat data terakhir diterima
let lastSuccess = Date.now();
let inFlight = false;
let poller = null;

const LEVEL = {
    aman: 'bg-success-600 text-white',
    waspada: 'bg-warning-500 text-gray-900',
    offline: 'bg-gray-600 text-white',
    bahaya: 'bg-error-600 text-white animate-pulse',
    tanpa_data: 'bg-gray-700 text-white',
};

const CARD = {
    aman: 'border-t-success-500 bg-[#14231b]',
    waspada: 'border-t-warning-500 bg-[#2a2210]',
    bahaya: 'border-t-error-500 bg-[#3a1410]',
    offline: 'border-t-gray-500 bg-[#1c1f24] opacity-70',
    tanpa_data: 'border-t-gray-600 bg-[#1c1f24] opacity-70',
};

const BADGE = {
    aman: 'bg-success-600 text-white',
    waspada: 'bg-warning-500 text-gray-900',
    bahaya: 'bg-error-600 text-white',
    offline: 'bg-gray-600 text-white',
    tanpa_data: 'bg-gray-600 text-white',
};

const esc = (value) =>
    String(value ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch]);

const number = (value) => (value === null || value === undefined ? '–' : Number(value).toFixed(1));

const trim = (value) => String(Number(value));

function ago(seconds) {
    if (seconds < 60) return `${Math.floor(seconds)} dtk lalu`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)} mnt lalu`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)} jam lalu`;
    return `${Math.floor(seconds / 86400)} hari lalu`;
}

function limits(zone) {
    const parts = [`Suhu ${trim(zone.temp_min)}–${trim(zone.temp_max)}°C`];
    if (zone.humidity_min !== null && zone.humidity_max !== null) {
        parts.push(`RH ${trim(zone.humidity_min)}–${trim(zone.humidity_max)}%`);
    }
    return parts.join(' · ');
}

function seenText(zone, seconds) {
    if (seconds === null) return 'Belum pernah mengirim data';
    return zone.status === 'offline' ? `Terakhir mengirim data ${ago(seconds)}` : `Diperbarui ${ago(seconds)}`;
}

// Jam dari waktu server (bukan jam TV, yang bisa salah) ditambah waktu yang berlalu sejak data diterima.
const serverNow = () => Date.parse(state.server_time) + (Date.now() - receivedAt);

function format(ms, options) {
    try {
        return new Intl.DateTimeFormat('id-ID', { timeZone: state.timezone, ...options }).format(new Date(ms));
    } catch {
        return new Intl.DateTimeFormat('id-ID', options).format(new Date(ms));
    }
}

const clock = (ms) => format(ms, { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }).replace(/\./g, ':');

function renderBanner() {
    const { level, headline, detail } = state.overall;
    const banner = document.getElementById('monitor-banner');
    banner.className = `rounded-3xl px-[2vw] py-[1.2vw] ${LEVEL[level] ?? LEVEL.tanpa_data}`;
    banner.innerHTML = `
        <p class="text-[clamp(1.5rem,3.4vw,4rem)] leading-tight font-extrabold">${esc(headline)}</p>
        ${detail ? `<p class="mt-1 text-[clamp(1rem,1.9vw,2.2rem)] font-semibold opacity-95">${esc(detail)}</p>` : ''}`;
}

function renderZones() {
    const compact = state.zones.length > COMPACT_ABOVE;
    const temp = compact ? 'text-[clamp(2rem,3.4vw,4.5rem)]' : 'text-[clamp(2.5rem,5.2vw,7rem)]';
    const humid = compact ? 'text-[clamp(1.1rem,1.8vw,2.4rem)]' : 'text-[clamp(1.4rem,2.6vw,3.2rem)]';
    const name = compact ? 'text-[clamp(1rem,1.6vw,2rem)]' : 'text-[clamp(1.2rem,2vw,2.6rem)]';

    document.getElementById('monitor-zones').innerHTML = state.zones
        .map((zone) => {
            const dim = zone.status === 'offline' ? 'opacity-80' : '';
            return `
        <section class="flex flex-col justify-center gap-2 rounded-3xl border border-t-[0.6rem] border-white/10 p-[1.6vw] ${CARD[zone.status] ?? CARD.tanpa_data}">
            <div class="flex items-start justify-between gap-3">
                <h2 class="min-w-0 truncate ${name} font-semibold text-gray-100">${esc(zone.name)}</h2>
                <span class="shrink-0 rounded-full px-3 py-1 text-[clamp(0.75rem,1.1vw,1.3rem)] font-bold tracking-wide ${BADGE[zone.status] ?? BADGE.tanpa_data}">${esc(zone.status_label)}</span>
            </div>
            <p class="${temp} leading-none font-extrabold tabular-nums ${dim}">${number(zone.temperature)}<span class="font-bold">°C</span></p>
            <p class="${humid} font-semibold text-gray-200 tabular-nums ${dim}">Kelembapan ${number(zone.humidity)}%</p>
            <p class="mt-1 text-[clamp(0.9rem,1.4vw,1.7rem)] text-gray-300">${esc(limits(zone))}</p>
            <p class="text-[clamp(0.9rem,1.4vw,1.7rem)] text-gray-400" data-seen data-seconds="${zone.last_seen_seconds ?? ''}" data-status="${esc(zone.status)}"></p>
        </section>`;
        })
        .join('');
    tickSeen();
}

// "Diperbarui n dtk lalu" dihitung ulang tiap detik dari data terakhir, tanpa menunggu polling.
function tickSeen() {
    const elapsed = (Date.now() - receivedAt) / 1000;
    document.querySelectorAll('[data-seen]').forEach((el) => {
        const base = el.dataset.seconds === '' ? null : Number(el.dataset.seconds) + elapsed;
        el.textContent = seenText({ status: el.dataset.status }, base);
    });
}

function tick() {
    const now = serverNow();
    document.getElementById('monitor-clock').textContent = clock(now);
    document.getElementById('monitor-date').textContent = format(now, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    tickSeen();

    const stale = Date.now() - lastSuccess > STALE_AFTER_MS;
    const offline = document.getElementById('monitor-offline');
    offline.hidden = !stale;
    if (stale) offline.textContent = `TIDAK TERHUBUNG KE SERVER · data terakhir pukul ${clock(Date.parse(state.server_time))}`;
    document.getElementById('monitor-zones').classList.toggle('opacity-30', stale);
    document.getElementById('monitor-zones').classList.toggle('grayscale', stale);
    document.getElementById('monitor-banner').classList.toggle('opacity-30', stale);

    if (Date.now() - startedAt >= RELOAD_AFTER_MS && !stale) location.reload();
}

function render() {
    document.getElementById('monitor-title').textContent = state.warehouse;
    document.title = `${state.warehouse} · Monitor`;
    renderBanner();
    renderZones();
    tick();
}

function showRevoked() {
    clearInterval(poller);
    root.innerHTML = `
        <div class="flex flex-1 flex-col items-center justify-center gap-4 text-center">
            <p class="text-[clamp(1.8rem,4vw,5rem)] font-extrabold">Alamat monitor ini tidak berlaku lagi</p>
            <p class="text-[clamp(1.1rem,2vw,2.4rem)] text-gray-400">Minta alamat baru kepada admin.</p>
        </div>`;
}

async function refresh() {
    if (inFlight) return;
    inFlight = true;
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), FETCH_TIMEOUT_MS);
    try {
        const response = await fetch(dataUrl, { cache: 'no-store', headers: { Accept: 'application/json' }, signal: controller.signal });
        if (response.status === 404) {
            showRevoked();
            return;
        }
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        state = await response.json();
        receivedAt = lastSuccess = Date.now();
        render();
    } catch {
        // Tidak ada yang diubah: tick() menampilkan pita "tidak terhubung" bila gagal terlalu lama.
    } finally {
        clearTimeout(timer);
        inFlight = false;
    }
}

render();
poller = setInterval(refresh, POLL_MS);
setInterval(tick, 1000);
