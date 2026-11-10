# Monitor Publik (layar TV gudang) — Rancangan

## Tujuan

Halaman layar penuh untuk TV di dinding gudang, supaya semua karyawan bisa melihat kondisi suhu dan kelembapan tanpa login. Terbaca dari jarak beberapa meter, memperbarui diri sendiri, dan tidak boleh tampak "aman" ketika datanya sebenarnya tidak segar.

## Keputusan user

- Tampilan **C**: pita status keseluruhan di atas ("SEMUA ZONA AMAN" / "PERHATIAN: n ZONA DI LUAR BATAS") dan kartu zona gelap dengan angka besar di bawahnya.
- Tanpa login; dilindungi **alamat rahasia per gudang** (`/monitor/{kode-acak}`) yang bisa diganti atau dimatikan admin.

## Keputusan saya (belum dikonfirmasi user)

- Satu alamat = satu gudang. Satu gudang boleh punya banyak TV memakai alamat yang sama.
- Kode disimpan sebagai hash sha256 (seperti token sensor) dan hanya tampil sekali saat dibuat. Bila hilang, buat ulang; alamat lama langsung mati.
- Halaman hanya menampilkan: nama gudang, jam, status keseluruhan, nama zona, suhu, kelembapan, batas, status, usia data. Tidak ada nama produk, id insiden, nama pengguna, tombol, atau tautan.
- Zona yang berhenti mengirim data tampil **OFFLINE** dan ikut menaikkan pita status; zona yang belum pernah mengirim data tampil **BELUM ADA DATA**.
- Bila browser TV gagal menghubungi server lebih dari 45 detik, halaman menampilkan pita "TIDAK TERHUBUNG KE SERVER" dan meredupkan kartu. Data lama tidak boleh terlihat seperti kondisi terkini.
- Bila alamat sudah dimatikan atau diganti, halaman menampilkan "Alamat monitor ini tidak berlaku lagi" (bukan layar kosong).
- Halaman memuat ulang dirinya tiap 6 jam supaya TV yang menyala terus ikut mendapat versi aplikasi terbaru.
- Pembaruan memakai polling 10 detik. Jam diambil dari waktu server, bukan jam TV.

## Bukan tujuan

- Tidak ada grafik, riwayat, atau insiden detail; itu tugas dashboard.
- Tidak ada tombol "Tandai Telah Ditangani" atau interaksi apa pun.
- Tidak ada login atau akun khusus TV. Tidak memakai Pusher/WebSocket (ditunda).
- Tidak ada laporan harian otomatis.

## Anti-tujuan

- Halaman yang tetap hijau padahal sensor mati atau koneksi putus.
- Alamat yang bisa ditebak, diindeks mesin pencari, atau bocor lewat header Referer.
- Data gudang lain muncul di monitor suatu gudang.

## Perilaku

### Status keseluruhan (pita)

Ditentukan dari status zona terburuk, urutan: Bahaya > Offline > Waspada > Aman > Tanpa data.

| Kondisi | Tingkat | Judul |
|---|---|---|
| Ada zona Bahaya | `bahaya` | `PERHATIAN: {n} ZONA DI LUAR BATAS` |
| Tidak ada Bahaya, ada Offline | `offline` | `SENSOR OFFLINE: {n} ZONA` |
| Hanya Waspada (selain Aman) | `waspada` | `WASPADA: {n} ZONA MENDEKATI BATAS` |
| Semua zona Aman | `aman` | `SEMUA ZONA AMAN` |
| Tidak ada zona, atau semua belum ada data | `tanpa_data` | `BELUM ADA DATA` |

Zona "belum ada data" tidak menurunkan status; bila sebagian zona Aman dan sebagian belum ada data, pita tetap `aman` tetapi kartu yang bersangkutan jelas bertanda BELUM ADA DATA.

Rincian pita: untuk tingkat `bahaya`, tiap zona Bahaya satu fragmen `"{zona}: {alasan} {nilai}{satuan} (batas {batas}{satuan})"` (alasan dan satuan dari `IncidentReason`), dipisah ` · `; untuk `offline`, nama zona offline; untuk `waspada`, nama zona waspada.

### Kartu zona

Urut: Bahaya, Offline, Waspada, Aman, Tanpa data; lalu nama. Tiap kartu: nama zona, suhu, kelembapan, "Batas ≤ {max}°C · RH {min}–{max}%" (RH dihilangkan bila batas kelembapan kosong), status, usia data ("diperbarui n dtk lalu", dihitung ulang tiap detik di browser). Zona offline menampilkan nilai terakhir diredupkan dengan keterangan sejak kapan.

### Alamat dan keamanan

- `GET /monitor/{token}` (halaman) dan `GET /monitor/{token}/data` (JSON), tanpa middleware `auth`.
- Token tidak dikenal atau sudah dicabut: 404 pada halaman dan data. Halaman 404 untuk token yang pernah ada tidak bisa dibedakan dari token acak, jadi pesan "tidak berlaku lagi" hanya muncul di halaman yang sudah terbuka (data mengembalikan 404 → JS menampilkan pesan).
- Throttle per IP: halaman 60/menit, data 300/menit (beberapa TV di satu kantor memakai satu IP).
- Header: `Cache-Control: no-store`, `X-Robots-Tag: noindex, nofollow`, `Referrer-Policy: no-referrer`.
- Token: 40 karakter acak (`Str::random(40)`), disimpan `monitor_token_hash` (sha256, unik, nullable) di tabel `warehouses`, disembunyikan dari serialisasi dan dari catatan audit.

### Pengelolaan (admin)

Di halaman ubah gudang (izin `warehouse.manage`, harus punya akses ke gudang tersebut), kartu "Layar monitor": status (aktif / belum dibuat), tombol **Buat alamat monitor** / **Buat ulang alamat** (alamat lengkap tampil sekali dengan tombol salin), dan **Matikan alamat**. Tiap aksi dicatat di Log Perubahan (`warehouse.monitor_issued`, `warehouse.monitor_revoked`).

## Data JSON (`/monitor/{token}/data`)

```
{
  "warehouse": "Gudang Demo",
  "server_time": "2026-11-10T14:32:05+07:00",
  "timezone": "Asia/Jakarta",
  "overall": {"level": "bahaya", "headline": "...", "detail": "..."},
  "zones": [
    {"name": "Zona Loading", "status": "bahaya", "status_label": "BAHAYA",
     "temperature": 21.4, "humidity": 73.8,
     "temp_min": 15.0, "temp_max": 20.0, "humidity_min": 60.0, "humidity_max": 85.0,
     "last_seen_seconds": 7}
  ]
}
```

## Yang diserahkan ke pembangun

Detail CSS (ukuran huruf memakai satuan relatif layar agar pas di berbagai resolusi TV), animasi (tidak berkedip berlebihan; pita merah boleh berdenyut pelan), dan teks kesalahan.
