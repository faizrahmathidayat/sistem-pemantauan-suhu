// Monitor publik untuk TV gudang. Hanya membaca: mengambil data tiap 10 detik dan menggambar ulang.
// Aturan utama: layar yang tidak segar tidak boleh terlihat seperti kondisi aman.

const POLL_MS = 10_000;
const FETCH_TIMEOUT_MS = 8_000;
const STALE_AFTER_MS = 45_000; // tanpa respons sukses selama ini = tampilkan "tidak terhubung"
const RELOAD_AFTER_MS = 6 * 60 * 60 * 1000; // muat ulang berkala agar TV ikut versi aplikasi terbaru
const PER_PAGE = 6; // kartu per layar; lebih dari ini berganti halaman sendiri
const PAGE_MS = 10_000; // lama tiap halaman tampil
const MAX_COLS = 3; // lebih banyak kolom membuat kartu sempit dan nama zona terpotong

// Satu kartu butuh kira-kira selebar 13u dan setinggi 11u (u = satuan ukuran huruf yang dihitung fitGrid);
// angka ini dari jumlah ukuran huruf, jarak, dan tinggi baris isi kartu di renderZones.
const CELL_W_U = 13;
const CELL_H_U = 11;

const root = document.getElementById('monitor');
const dataUrl = root.dataset.url;
const startedAt = Date.now();

let state = JSON.parse(document.getElementById('monitor-initial').textContent);
let receivedAt = Date.now(); // waktu lokal saat data terakhir diterima
let lastSuccess = Date.now();
let inFlight = false;
let reloadChecking = false;
let page = 0; // halaman zona yang sedang tampil (mulai dari 0)
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
        ${detail ? `<p class="mt-1 line-clamp-2 text-[clamp(1rem,1.9vw,2.2rem)] font-semibold opacity-95">${esc(detail)}</p>` : ''}`;
}

// Ukuran di dalam kartu dikali --u, yang dipasang fitGrid sesuai jumlah zona dan ukuran layar.
const u = (k) => `calc(var(--u, 20px) * ${k})`;

// Zona sudah diurutkan server (Bahaya, Offline, Waspada, Aman), jadi yang bermasalah selalu di halaman pertama.
// Dibagi rata antarhalaman (7 zona = 4 + 3) supaya kartu tidak ada yang sendirian di halaman terakhir.
function pageZones() {
    const total = state.zones.length;
    const pages = Math.max(1, Math.ceil(total / PER_PAGE));
    const size = Math.ceil(total / pages);
    page = Math.min(page, pages - 1);
    return { zones: state.zones.slice(page * size, page * size + size), pages };
}

function renderPager(pages) {
    const pager = document.getElementById('monitor-pager');
    pager.hidden = pages <= 1;
    if (pages <= 1) return;
    const dots = Array.from({ length: pages }, (_, i) => `<span class="inline-block size-[0.9em] rounded-full ${i === page ? 'bg-white' : 'bg-white/25'}"></span>`).join('');
    pager.innerHTML = `${dots}<span>Halaman ${page + 1} dari ${pages}</span>`;
}

function nextPage() {
    const { pages } = pageZones();
    if (pages <= 1) return;
    page = (page + 1) % pages;
    renderZones();
}

function renderZones() {
    const view = pageZones();
    renderPager(view.pages);
    document.getElementById('monitor-zones').innerHTML = view.zones
        .map((zone) => {
            const dim = zone.status === 'offline' ? 'opacity-80' : '';
            return `
        <section class="flex min-h-0 flex-col justify-center overflow-hidden rounded-3xl border border-white/10 ${CARD[zone.status] ?? CARD.tanpa_data}" style="gap:${u(0.25)};padding:${u(0.8)};border-top-width:${u(0.35)}">
            <div class="flex items-start justify-between" style="gap:${u(0.4)}">
                <h2 class="min-w-0 truncate font-semibold text-gray-100" style="font-size:${u(0.95)}">${esc(zone.name)}</h2>
                <span class="shrink-0 rounded-full font-bold tracking-wide ${BADGE[zone.status] ?? BADGE.tanpa_data}" style="font-size:${u(0.6)};padding:${u(0.1)} ${u(0.45)}">${esc(zone.status_label)}</span>
            </div>
            <p class="leading-none font-extrabold whitespace-nowrap tabular-nums ${dim}" style="font-size:${u(2.7)}">${number(zone.temperature)}<span class="font-bold">°C</span></p>
            <p class="font-semibold whitespace-nowrap text-gray-200 tabular-nums ${dim}" style="font-size:${u(1.2)}">Kelembapan ${number(zone.humidity)}%</p>
            <p class="truncate text-gray-300" style="font-size:${u(0.75)}">${esc(limits(zone))}</p>
            <p class="truncate text-gray-400" style="font-size:${u(0.75)}" data-seen data-seconds="${zone.last_seen_seconds ?? ''}" data-status="${esc(zone.status)}"></p>
        </section>`;
        })
        .join('');
    fitGrid();
    tickSeen();
}

// TV tidak bisa digulir, jadi semua zona harus muat satu layar. Dicoba setiap jumlah kolom; yang memberi
// ukuran huruf terbesar menang. Jumlah zona sedikit = kartu besar, banyak = kartu kecil tetapi tetap terbaca.
function fitGrid() {
    const zones = document.getElementById('monitor-zones');
    const count = zones.children.length; // hanya kartu di halaman yang sedang tampil
    const { width, height } = zones.getBoundingClientRect();
    if (count === 0 || width === 0 || height === 0) return;

    const gap = Math.max(8, width * 0.012);
    let best = { unit: 0, cols: 1, rows: count };
    for (let cols = 1; cols <= Math.min(count, MAX_COLS); cols++) {
        const rows = Math.ceil(count / cols);
        const cellW = (width - gap * (cols - 1)) / cols;
        const cellH = (height - gap * (rows - 1)) / rows;
        const unit = Math.min(cellW / CELL_W_U, cellH / CELL_H_U);
        if (unit > best.unit) best = { unit, cols, rows };
    }

    zones.style.gap = `${gap}px`;
    zones.style.gridTemplateColumns = `repeat(${best.cols}, minmax(0, 1fr))`;
    zones.style.gridTemplateRows = `repeat(${best.rows}, minmax(0, 1fr))`;
    zones.style.setProperty('--u', `${Math.min(best.unit, width * 0.06)}px`);
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

    if (Date.now() - startedAt >= RELOAD_AFTER_MS && !stale && !reloadChecking) {
        reloadChecking = true;
        reloadIfServerHealthy().finally(() => setTimeout(() => (reloadChecking = false), 60_000));
    }
}

// Muat ulang penuh hanya bila server baru saja menjawab 200. Kalau server sedang 503/429/galat,
// memuat ulang membuat TV terjebak di halaman galat peramban tanpa JavaScript; lebih baik tetap
// di halaman ini (pita "tidak terhubung" tetap bekerja) dan mencoba lagi semenit kemudian.
async function reloadIfServerHealthy() {
    try {
        const response = await fetch(location.href, { cache: 'no-store' });
        if (response.ok) location.reload();
    } catch {
        // coba lagi nanti
    }
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
// Tinggi sisa layar berubah bila pita status berganti (mis. rincian bahaya lebih panjang) atau jendela diubah ukurannya.
if (typeof ResizeObserver === 'function') new ResizeObserver(fitGrid).observe(document.getElementById('monitor-zones'));
else window.addEventListener('resize', fitGrid);
poller = setInterval(refresh, POLL_MS);
setInterval(nextPage, PAGE_MS);
setInterval(tick, 1000);
