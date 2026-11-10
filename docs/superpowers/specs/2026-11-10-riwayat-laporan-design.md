# Riwayat & Laporan Sensor — Rancangan

Tanggal: 2026-11-10. Status: menunggu persetujuan.

## Tujuan

Data sensor sudah tersimpan 90 hari, tetapi dashboard hanya menampilkan grafik 24 jam. Fitur ini membuat data itu bisa dilihat dan dibawa keluar: grafik rentang bebas, ringkasan per zona, dan ekspor CSV untuk audit atau pelaporan mutu.

**Bukan tujuan (ditunda):** laporan harian otomatis, notifikasi Telegram, pembuatan file PDF di server, penyimpanan data lebih dari 90 hari, grafik beberapa zona sekaligus, ekspor XLSX, pencatatan siapa mengunduh data.

## Halaman Laporan (`/reports`)

Menu baru **Laporan**, dilindungi izin baru `report.view` ("Melihat dan mengunduh laporan sensor", kelompok "Laporan").

**Filter** (semua tersimpan di alamat halaman agar bisa dibagikan):
- **Gudang**: hanya gudang yang boleh dilihat user (Administrator: semua).
- **Zona**: zona di gudang itu. Ringkasan menampilkan semua zona gudang; grafik dan ekspor mentah untuk satu zona terpilih.
- **Periode**: 24 jam, 7 hari (bawaan), 30 hari, 90 hari, atau **tanggal sendiri** (dari–sampai). Tanggal sendiri tidak boleh lebih dari 90 hari ke belakang, rentangnya maksimal 90 hari, "dari" tidak boleh setelah "sampai", dan tanggal masa depan dipotong ke sekarang. Isian tidak valid menampilkan pesan, bukan halaman kosong.

**Bagian halaman:**
1. **Ringkasan per zona** (tabel, semua zona di gudang terpilih): suhu min / maks / rata-rata, kelembapan min / maks / rata-rata, jumlah pembacaan, jumlah insiden, total lama insiden, total lama sensor offline, dan **% pembacaan dalam batas** (terhadap batas zona saat ini; diberi catatan bila batas pernah diubah tidak dihitung ulang).
2. **Grafik** zona terpilih, tombol Suhu / Kelembapan seperti di dashboard. Tiap titik adalah satu *bucket* waktu dan digambar tiga garis: rata-rata, minimum, maksimum (supaya lonjakan singkat tidak tersembunyi oleh rata-rata), ditambah garis batas zona. Ukuran bucket otomatis sehingga grafik berisi sekitar 300 titik atau kurang (5 menit sampai 1 hari).
3. **Daftar insiden** zona terpilih pada periode itu (mulai, selesai, durasi, alasan, nilai puncak), maksimal 100 baris terbaru.

**Cetak:** tombol "Cetak / Simpan PDF" memakai fitur cetak browser dengan tampilan khusus cetak (judul gudang, zona, periode, nama pencetak, waktu cetak; tanpa menu). Tidak ada pustaka PDF di server.

## Ekspor CSV

Dua tombol, keduanya mengikuti filter yang sedang aktif:
- **Ringkasan** (CSV): isi tabel ringkasan semua zona di gudang itu.
- **Data mentah** (CSV): pembacaan zona terpilih (waktu, gudang, zona, device_id, suhu, kelembapan), urut waktu, dialirkan bertahap agar tidak menghabiskan memori untuk 90 hari data.

Format: UTF-8 dengan BOM, pemisah koma, titik desimal, waktu `YYYY-MM-DD HH:MM:SS` zona waktu aplikasi. Sel yang diawali `=`, `+`, `-`, `@` diberi awalan `'` (nama zona/gudang diisi user, cegah formula injection di Excel).

## Akses

- Halaman dan ekspor hanya untuk user dengan `report.view`.
- Zona dan gudang di luar jangkauan user ditolak (403) di halaman, grafik, dan kedua ekspor, sama seperti dashboard.
- Role yang sudah ada: migrasi memberi `report.view` ke role yang punya `dashboard.view` (mereka sudah bisa melihat datanya; Administrator mencabutnya di menu Role bila perlu). Role awal baru Supervisor dan Operator mendapat izin ini.
- Dashboard: di bawah grafik 24 jam ada tautan "Riwayat lengkap" ke halaman Laporan (hanya bila punya izin).

## Perhitungan

- Agregasi (min/maks/rata-rata, bucket, jumlah) dilakukan di database, bukan dengan memuat semua baris ke PHP. Ekspresi bucket tidak bergantung zona waktu sesi database (selisih detik dari titik tetap, bukan `UNIX_TIMESTAMP`) dan didukung MySQL (produksi) dan SQLite (tes).
- Lama insiden dan offline dipotong ke periode yang dipilih; insiden yang masih terbuka dihitung sampai sekarang.
- Retensi tetap `SensorLog::RETENTION_DAYS` (90). Halaman menyebut "data disimpan 90 hari" dan tidak menjanjikan data lebih lama.

## Keputusan saya (mohon diperiksa)

1. Izin baru `report.view` terpisah dari `dashboard.view`, agar tiap role bisa diatur apakah boleh mengunduh data.
2. Satu izin untuk melihat sekaligus mengunduh.
3. CSV standar (koma, titik desimal). Excel dengan pengaturan regional Indonesia kadang menaruh semuanya dalam satu kolom; solusinya Data → From Text/CSV. Alternatif `;` dengan koma desimal ditolak karena merusak pemakaian di alat lain.
4. PDF lewat cetak browser, bukan pustaka PDF (menghindari dependensi baru).
5. "% pembacaan dalam batas" memakai batas zona saat ini.
