import Alpine from 'alpinejs';

window.Alpine = Alpine;

const KEY = 'sidebarExpanded';

// Pilihan disimpan di browser; gagal baca/tulis (mode privat, penyimpanan diblokir) tidak boleh merusak halaman.
const readExpanded = () => {
    try {
        return localStorage.getItem(KEY) !== 'false';
    } catch {
        return true;
    }
};

// Penanda di <html> dibaca CSS (group-data-[collapsed=true]/shell). Skrip kecil di <head> sudah memasangnya
// sebelum halaman digambar, jadi tidak ada kedipan; di sini hanya dijaga tetap sinkron.
const applyCollapsed = (collapsed) => {
    if (collapsed) document.documentElement.dataset.collapsed = 'true';
    else delete document.documentElement.dataset.collapsed;
};

Alpine.data('shell', () => ({
    sidebarOpen: false, // panel geser di HP
    sidebarExpanded: readExpanded(), // layar lebar: true = lebar penuh, false = hanya ikon

    init() {
        applyCollapsed(!this.sidebarExpanded);
        // Transisi baru diaktifkan setelah keadaan awal terpasang, supaya halaman tidak beranimasi saat dimuat.
        setTimeout(() => {
            document.documentElement.dataset.ready = 'true';
        }, 50);
    },

    toggleSidebar() {
        this.sidebarExpanded = !this.sidebarExpanded;
        applyCollapsed(!this.sidebarExpanded);
        try {
            localStorage.setItem(KEY, String(this.sidebarExpanded));
        } catch {
            // diabaikan: pilihan hanya berlaku untuk halaman ini
        }
    },
}));

Alpine.start();
