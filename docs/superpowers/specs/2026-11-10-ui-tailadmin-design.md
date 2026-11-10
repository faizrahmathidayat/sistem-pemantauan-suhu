# Perombakan Tampilan Gaya TailAdmin — Rancangan

Tanggal: 2026-11-10. Status: menunggu persetujuan.

## Tujuan

Membuat seluruh antarmuka tampak profesional dan rapi di desktop maupun HP dengan pola visual **TailAdmin**: sidebar tetap di kiri, header atas, kartu berlatar putih bersudut membulat, badge status, tabel bersih, dan formulir seragam. Hanya tampilan yang berubah; backend, rute, izin, dan data tidak disentuh.

**Bukan tujuan:** mengganti Chart.js dengan ApexCharts; mode gelap; kolom pencarian di header; font khusus (tetap font sistem agar build tidak butuh internet); mengunduh atau menyalin berkas template TailAdmin; fitur baru; mengubah nama aplikasi (nama tampil dari `config('app.name')`, ubah lewat `APP_NAME` di `.env`, mis. "FreshGuard IoT").

## Yang sudah ada dan dipertahankan

Zona berstatus Bahaya sudah diurutkan paling atas oleh server (`DashboardSnapshot`, ada tesnya), banner kritis sudah menempel, tombol aksi sudah selebar layar di HP, dan chip gudang dengan `?gudang=` sudah ada. Semua perilaku itu tetap, hanya digambar ulang.

## Pendekatan

Meniru pola TailAdmin dengan Tailwind 4 yang sudah ada (pilihan Anda), dengan palet warnanya sebagai token di `resources/css/app.css` (`@theme`): `brand` hijau tua `#0a1f12` (utama), `gray`, `success` hijau, `warning` kuning, `error` merah. Warna utama bisa diganti dengan mengubah token, tanpa menyentuh Blade.

**Dependensi baru:** hanya `alpinejs` (versi dipasang persis) untuk buka-tutup sidebar di HP dan menu dropdown. Chart.js tetap per halaman seperti sekarang.

## Layout (`layouts/app.blade.php`)

- **Sidebar** kiri (lebar 18 rem) di layar ≥ lg; di HP menjadi panel geser dengan latar gelap, dibuka tombol hamburger di header dan ditutup dengan klik di luar atau tombol Esc (Alpine).
- Menu dikelompokkan dan hanya menampilkan yang diizinkan (`@can`), tidak berubah logikanya: **Menu** (Dashboard, Riwayat Insiden, Laporan, Notifikasi), **Pengaturan** (Batas Zona, Gudang), **Administrasi** (User, Role, Log Perubahan). Item aktif disorot.
- **Header** menempel di atas: hamburger (HP), lonceng notifikasi dengan lencana jumlah belum dibaca (atribut `data-unread-badge` tetap, dipakai `dashboard.js`), dan menu pengguna (inisial, nama, nama role, tombol Keluar).
- **Banner kritis** berada dalam wadah yang menempel bersama header, jadi selalu terlihat tepat di bawah header di semua ukuran layar. Section `banner`, `title`, `content`, dan `scripts` di layout tetap sama sehingga halaman lain tidak perlu mengubah kontrak.
- Tampilan cetak: sidebar dan header disembunyikan, isi memakai lebar penuh (fitur cetak Laporan tetap bekerja).

## Komponen bersama

Komponen Blade (`resources/views/components/`): `x-icon` (SVG sederhana buatan sendiri), `x-card`, `x-badge` (varian success/warning/error/gray/brand), `x-page-header` (judul, deskripsi, jalur remah roti, slot aksi), `x-alert`, `x-empty` (keadaan kosong), `x-table` (pembungkus tabel bergaya TailAdmin, bergulir mendatar bila sempit). Kelas formulir dan tombol dibuat sebagai kelas komponen CSS (`.form-input`, `.form-label`, `.btn`, `.btn-primary`, `.btn-secondary`, `.btn-danger`, `.chip`) agar halaman yang sudah ada tinggal mengganti nama kelas.

## Halaman

- **Login, 403, belum punya hak akses:** login berlayout dua kolom di layar lebar (formulir dan panel merek), satu kolom di HP.
- **Dashboard:** judul dan pemilih gudang (chip yang bisa digeser, status terburuk dan jumlah insiden terbuka) di atas; **kartu metrik** per zona (nama zona dan gudang, badge status hijau/kuning/merah berdenyut/abu-abu, suhu besar, batas, ikon panah tren, kelembapan, waktu pembaruan, tag produk; kartu Bahaya diberi cincin merah); grid 1 kolom di HP, 2 di tablet, 3 di desktop. **Kartu grafik** dengan chip zona dan pilihan Suhu/Kelembapan; Chart.js dengan warna dan grid yang cocok. **Kartu insiden terbaru**.
- **Laporan:** formulir filter dalam kartu; **tabel ringkasan** gaya TailAdmin di layar ≥ md dan **daftar kartu** per zona di HP; kartu grafik dan kartu insiden. Tombol unduh dan cetak tetap.
- **Riwayat Insiden, Notifikasi, Log Perubahan:** tabel di desktop dan daftar kartu di HP (pola yang sudah dipakai Riwayat Insiden).
- **Batas Zona, Gudang (daftar, buat, ubah), Zona (buat, ubah, kelola):** kartu dan formulir seragam; aksi berbahaya (hapus) berwarna merah dan dipisahkan.
- **User dan Role:** tabel (desktop) dan daftar kartu (HP), formulir seragam.

Lebar tombol: penuh di HP untuk aksi utama (Simpan, Tandai Telah Ditangani, Terapkan), otomatis di desktop.

## Pengujian dan verifikasi

- Seluruh 345 tes tetap hijau; tes yang bergantung pada markup lama disesuaikan, bukan dilonggarkan (ada beberapa pemeriksaan atribut: `data-data-url`, `data-readings-url`, `data-ack-url`, `data-unread-badge`, `>Administrator</option>`).
- Tes baru untuk layout: sidebar hanya menampilkan menu yang diizinkan per role dan menandai halaman aktif; header menampilkan nama dan role user, lonceng dengan `data-unread-badge`, dan formulir Keluar; wadah banner dan tombol hamburger ada; halaman login, 403, dan "belum punya hak akses" tampil.
- Verifikasi nyata di browser (data demo di MySQL dev, akun uji sementara yang dihapus sesudahnya): tiap halaman di lebar desktop dan 375 px, termasuk banner menempel saat insiden aktif, tombol Tandai Telah Ditangani, grafik, sidebar HP, dan tampilan cetak Laporan. Tidak ada galat konsol.
- Build Vite harus bersih; `npm run build` dijalankan setelah perubahan kelas.

## Keputusan saya (mohon diperiksa)

1. Warna utama **`#0a1f12`** (hijau tua, permintaan Anda setelah rancangan disetujui) menggantikan biru TailAdmin; skala tint turunannya saya tentukan (lihat rencana) dan status tetap hijau/kuning/merah. Mudah diganti lewat token.
2. Ikon SVG sederhana buatan sendiri (bukan paket ikon), supaya tidak menambah dependensi.
3. Tanpa mode gelap dan tanpa pencarian di header (tidak diminta, dan dua-duanya menggandakan pekerjaan di setiap halaman).
4. Hasilnya meniru tampilan TailAdmin, tidak identik piksel per piksel karena berkas template tidak diunduh.
5. Pemilih gudang di dashboard tetap berupa chip yang bisa digeser (bukan dropdown), karena sudah menunjukkan status dan jumlah insiden tiap gudang.
