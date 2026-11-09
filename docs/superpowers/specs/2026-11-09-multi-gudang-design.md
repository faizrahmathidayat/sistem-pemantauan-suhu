# Rancangan: Multi-Gudang (Gudang → Zona → Produk)

Tanggal: 9 November 2026
Status: menunggu persetujuan
Bagian dari: pengembangan multi-gudang, **proyek 2 dari 2**. Proyek 1 (modul hak akses: role, izin, user) sudah selesai dan menjadi dasar dokumen ini.

Penanda: **[Anda]** = diputuskan pengguna, **[Saya]** = keputusan saya, boleh dikoreksi.

## 1. Maksud dan latar

Sistem sekarang hanya mengenal satu kumpulan zona yang dilihat semua orang. Perusahaan punya beberapa gudang, dan tiap gudang punya beberapa zona dengan sensor sendiri, misalnya Gudang Cikokol (Telur) dengan Zona Inbound, Zona FG, dan Zona Loading. Orang yang bertugas di satu gudang tidak perlu (dan tidak boleh) melihat atau menerima alert gudang lain.

## 2. Tujuan

- **[Anda]** Struktur **Gudang → Zona → Produk**.
  - **Gudang:** punya nama (mis. "Gudang Cikokol (Telur)").
  - **Zona:** milik satu gudang, dengan **satu sensor ESP32** dan **batas suhu/kelembapan sendiri** (satu sensor mengawasi semua produk di zona itu).
  - **Produk:** daftar **nama saja** di dalam zona, tanpa jumlah atau stok.
- **[Anda]** User di-assign ke **satu atau lebih gudang** dan hanya melihat serta menerima alert gudang itu.
- **[Anda]** Dashboard: chip gudang di atas (menampilkan status terburuk gudang itu), lalu kartu zona yang langsung memuat daftar produknya (pilihan "B: satu layar").
- **[Anda]** Hak akses per role tetap menentukan *apa* yang boleh dilakukan; assignment menentukan *di gudang mana*. Satu role per user, berlaku di semua gudangnya.
- Pengelola dapat menambah, mengubah, dan menghapus gudang, zona, dan produk dari aplikasi, termasuk membuat token sensor untuk zona baru (menggantikan kebiasaan memakai `php artisan zone:token` untuk setup awal).

## 3. Bukan tujuan

- Role berbeda per gudang; stok, jumlah, atau tanggal masuk produk; denah gudang.
- Memindahkan zona antar gudang; menonaktifkan (soft delete) gudang atau zona.
- Pembatasan Log Perubahan per gudang (lihat bagian 6).
- Anti-tujuan: user melihat, menandai, atau menerima alert gudang yang bukan miliknya, lewat halaman maupun endpoint JSON.

## 4. Aturan akses data

- **[Saya]** Role **Administrator** (sistem) melihat dan mengelola **semua gudang** tanpa perlu assignment. User lain hanya melihat gudang yang di-assign. User non-Administrator tanpa assignment melihat dashboard kosong dengan keterangan "Anda belum ditugaskan ke gudang mana pun".
- Pembatasan berlaku di semua tempat data gudang muncul: dashboard dan datanya (`/dashboard/data`), grafik zona (`/zones/{zone}/readings`), riwayat insiden, tombol "Tandai Telah Ditangani", halaman Batas Zona, dan notifikasi. Mengakses zona/insiden gudang lain lewat URL langsung menghasilkan 403 (JSON untuk endpoint polling).
- **Alert**: insiden baru dikirim ke user yang punya izin `notification.view` **dan** (Administrator atau di-assign ke gudang zona itu). Teks notifikasi dan banner menyebut lokasi lengkap, mis. "Gudang Cikokol › Zona FG".
- **[Saya]** Assignment user dikelola di form User (kotak centang gudang) oleh pemegang `user.manage`. User non-Administrator hanya boleh meng-assign gudang yang ia sendiri punya, supaya tidak bisa memberi dirinya akses ke gudang lain.

## 5. Izin baru **[Saya]**

| Kunci | Melindungi |
|---|---|
| `warehouse.manage` | Tambah, ubah nama, dan hapus gudang |
| `zone.manage` | Tambah, ubah nama/device ID/produk, dan hapus zona; membuat atau memutar token sensor |

Izin yang sudah ada tetap: `zone.view` dan `zone.update` (lihat dan ubah **batas** zona). Role awal tidak berubah (Administrator otomatis memiliki izin baru; Supervisor dan Operator tidak).

## 6. Aturan data dan perilaku

- Nama gudang unik. Nama zona unik di dalam satu gudang. `device_id` tetap unik di seluruh sistem (sensor mengirim ke satu zona).
- **Produk:** diinput sebagai teks, satu nama per baris; maksimal 20 produk per zona dan 80 karakter per nama; baris kosong dan duplikat diabaikan.
- **Menghapus gudang** hanya bila tidak punya zona (pesan menyebut jumlah zona). **[Saya]** **Menghapus zona** ikut menghapus insiden, pembacaan sensor, dan produk zona itu, dengan dialog konfirmasi yang menyebut hal itu. Penghapusan tidak bisa dibatalkan.
- **Token sensor:** ditampilkan **sekali** saat dibuat atau diputar (token lama langsung tidak berlaku); hanya hash yang disimpan, seperti sekarang. Perilaku `zone:token` lewat CLI tetap berjalan.
- **Dashboard:** chip "Semua gudang" plus satu chip per gudang yang boleh dilihat user; chip menampilkan status terburuk (🔴/🟡/🟢) dan jumlah insiden terbuka. Pilihan chip tersimpan di alamat halaman (`?gudang=`) agar bisa dibagikan. **[Saya]** Bawaan: "Semua gudang" bila user punya lebih dari satu gudang; gudangnya langsung bila hanya satu. Kartu zona mengurut zona bahaya paling atas, seperti sekarang, dan memuat daftar produk.
- **Insiden dan Batas Zona** dikelompokkan atau difilter per gudang. Riwayat Insiden mendapat kolom/label gudang.
- **Pencatatan:** pembuatan, perubahan, dan penghapusan gudang, zona, produk, dan assignment masuk ke Log Perubahan yang ada (token tidak pernah dicatat nilainya). **[Saya]** Log Perubahan tetap menampilkan semua catatan bagi pemegang `auditlog.view` (tidak difilter per gudang); izin itu sebaiknya hanya untuk orang yang dipercaya melihat seluruh perubahan.

## 7. Data yang sudah ada **[Saya]**

Migrasi membuat satu gudang bawaan bernama **"Gudang Utama"**, memasukkan semua zona yang ada ke dalamnya (sensor, token, insiden, dan pembacaan tetap utuh), menjadikan jenis produk lama tiap zona (Telur/Buah/Sayur) sebagai satu produk di zona itu, lalu menghapus kolom jenis produk. Semua user non-Administrator yang ada di-assign ke gudang itu agar tidak kehilangan tampilan. Seeder contoh untuk instalasi baru memakai Gudang Cikokol (Telur) dengan Zona Inbound, FG, dan Loading.

## 8. Cara membuatnya

Tabel baru `warehouses`, `warehouse_user` (assignment), `zone_products`; kolom `zones.warehouse_id` (wajib). Pembatasan lewat satu tempat pusat (scope "zona/gudang yang terlihat oleh user") yang dipakai semua query dan pemeriksaan, bukan di tiap controller. Izin baru lewat enum `Permission` yang sudah ada. Antarmuka mengikuti pola halaman Role dan User (kartu ramah HP).

## 9. Diserahkan ke pembangun

Nama tabel/kolom, struktur kelas, teks pesan galat, tata letak persis halaman pengelolaan gudang/zona, cara menampilkan token sekali-tampil.

## 10. Risiko yang perlu diuji

Kebocoran data antar gudang lewat endpoint JSON, grafik, insiden, dan notifikasi; user dengan gudang ganda; migrasi pada database yang sudah berisi data; penghapusan zona yang punya riwayat sensor; token hanya tampil sekali.
