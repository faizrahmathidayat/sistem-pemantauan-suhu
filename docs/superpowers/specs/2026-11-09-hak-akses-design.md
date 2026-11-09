# Rancangan: Modul Hak Akses (Role dan Izin)

Tanggal: 9 November 2026
Status: menunggu persetujuan
Bagian dari: pengembangan multi-gudang. Dokumen ini adalah **proyek 1 dari 2**; proyek 2 (Gudang → Zona → Produk, lihat bagian "Proyek berikutnya") dikerjakan setelah ini selesai.

Penanda: **[Anda]** = diputuskan pengguna, **[Saya]** = keputusan saya, boleh dikoreksi.

## 1. Maksud dan latar

Sistem sekarang menganggap semua user setara: siapa pun yang masuk boleh mengubah batas zona dan menerima semua alert. Itu tidak cukup begitu ada banyak gudang dan banyak orang dengan tugas berbeda. Perlu aturan "siapa boleh melakukan apa", yang bisa diatur sendiri oleh pengelola tanpa mengubah kode.

## 2. Tujuan

- **[Anda]** Hak akses melekat pada **role**. Izin dicentang per role di sebuah halaman pengaturan.
- **[Anda]** Satu user punya **satu role**, berlaku di semua gudang yang di-assign ke dia. Role per gudang bukan bagian proyek ini.
- **[Saya]** Role dan pilihan izinnya bisa dibuat, diubah, dan dihapus dari aplikasi. Daftar **izin** itu sendiri tetap di kode (karena tiap izin melindungi fitur nyata).
- Pengelolaan user lewat aplikasi: tambah, ubah role, ubah kata sandi, hapus. Menggantikan kebiasaan memakai `php artisan user:create`.
- Semua halaman dan endpoint dijaga izin. Menu yang tidak boleh dipakai tidak ditampilkan.

## 3. Bukan tujuan

- Role berbeda per gudang; pembuatan izin baru dari UI; satu user dengan banyak role.
- Login sosial, 2FA, atur ulang kata sandi lewat email, undangan lewat email.
- Pembatasan data per gudang. Itu **proyek 2**; proyek ini hanya menyiapkan izin dan role.
- Anti-tujuan: user terkunci dari sistem (lihat aturan Administrator di bagian 5).

## 4. Daftar izin versi ini **[Saya]**

| Kunci | Melindungi |
|---|---|
| `dashboard.view` | Dashboard dan datanya (`/dashboard`, `/dashboard/data`, grafik zona) |
| `incident.view` | Riwayat insiden |
| `incident.acknowledge` | Tombol "Tandai Telah Ditangani" |
| `notification.view` | Halaman notifikasi dan lonceng; juga syarat **menerima** alert |
| `zone.view` | Daftar Batas Zona |
| `zone.update` | Mengubah batas zona |
| `auditlog.view` | Halaman Log Perubahan |
| `user.manage` | Halaman user (lihat, tambah, ubah, hapus) |
| `role.manage` | Halaman role (lihat, tambah, ubah, hapus, atur izin) |

Proyek 2 menambah izin untuk mengelola gudang, zona, dan produk. Menambah izin baru cukup satu entri di daftar kode; role Administrator otomatis memilikinya.

## 5. Aturan

**Role.** Punya nama unik dan kumpulan izin.

**Administrator** adalah role sistem **[Saya]**: selalu punya semua izin (termasuk izin yang ditambahkan kelak), tidak bisa diubah izinnya, diganti namanya, atau dihapus.

**Role awal** (bisa diubah, kecuali Administrator) **[Saya]**:

| Role | Izin |
|---|---|
| Administrator | semua |
| Supervisor | `dashboard.view`, `incident.view`, `incident.acknowledge`, `notification.view`, `zone.view`, `zone.update`, `auditlog.view` |
| Operator | `dashboard.view`, `incident.view`, `incident.acknowledge`, `notification.view` |

**User.** Wajib punya satu role. User tanpa role (kondisi tidak seharusnya terjadi) bisa login tetapi hanya melihat halaman "Akun Anda belum punya hak akses, hubungi administrator".

**Penghapusan dan pengubahan.**
- Role yang masih dipakai user tidak bisa dihapus (pesan menyebut jumlah user).
- Selalu harus tersisa minimal satu user Administrator: user Administrator terakhir tidak bisa dihapus atau dipindah role.
- User tidak bisa menghapus akunnya sendiri.

**Pencegahan naik hak** **[Saya, ditambahkan setelah review akhir]**: hanya Administrator yang boleh memberi role Administrator, serta mengubah atau menghapus akun Administrator; user non-Administrator tidak bisa mengubah role-nya sendiri. Pemegang `role.manage` tetap bisa memberi role-nya sendiri izin apa pun selain menjadi Administrator, jadi kedua izin pengelolaan ini diberikan hanya kepada orang tepercaya.

**Alert.** Insiden baru dikirim ke user yang rolenya punya `notification.view` **[Saya]** (menggantikan "semua user").

**Halaman awal setelah login** **[Saya]**: dashboard bila punya `dashboard.view`; bila tidak, halaman pertama yang diizinkan menurut urutan menu; bila tidak ada satu pun, halaman "belum punya hak akses" (tanpa pengalihan berulang).

**Akses ditolak.** Halaman web: halaman 403 berbahasa Indonesia. Endpoint JSON: respons 403.

**Pencatatan.** Perubahan user dan role masuk ke Log Perubahan yang sudah ada **[Saya]**: pembuatan, perubahan role/izin, penghapusan. Kata sandi tidak pernah dicatat nilainya, hanya keterangan "kata sandi diubah".

## 6. Layar

- **Role** (`role.manage`): daftar role beserta jumlah user; form tambah/ubah berisi nama dan kotak centang izin, dikelompokkan per fitur. Ramah HP.
- **User** (`user.manage`): daftar user dengan role-nya; form tambah (nama, email, kata sandi, role) dan ubah (termasuk ganti kata sandi opsional).
- Menu atas hanya menampilkan item yang diizinkan.

## 7. Data yang sudah ada **[Saya]**

Migrasi membuat tiga role awal dan menjadikan **semua user yang ada Administrator**, sehingga tidak ada yang kehilangan akses. Perintah `user:create` mendapat opsi `--role=` (bawaan: Administrator).

## 8. Cara membuatnya **[Saya]**

Implementasi sendiri, tanpa paket pihak ketiga: izin didefinisikan sebagai enum di kode, role disimpan di tabel `roles`, pemetaan role-izin di tabel pivot, `users.role_id` menunjuk role. Pemeriksaan lewat Gate Laravel (`can:` pada route dan `@can` pada tampilan). Alasannya: izin sudah pasti terikat pada fitur di kode, sehingga yang dinamis hanya pemetaan role ke izin. Paket seperti spatie/laravel-permission akan menambah ketergantungan dan fitur (banyak role, guard, tim) yang tidak dipakai.

## 9. Diserahkan ke pembangun

Nama tabel dan kolom, struktur kelas, teks pesan galat, tata letak persis form, urutan kelompok izin.

## 10. Proyek berikutnya (proyek 2), ringkasan kesepakatan

- Struktur **Gudang → Zona → Produk**. Satu zona punya satu sensor ESP32 dan batas suhu/kelembapan sendiri; produk adalah daftar nama di dalam zona (tanpa stok) **[Anda]**.
- Dashboard: chip gudang (menampilkan status terburuk) lalu kartu zona yang langsung memuat daftar produknya (pilihan B) **[Anda]**.
- User di-assign ke satu atau lebih gudang dan hanya melihat serta menerima alert gudangnya **[Anda]**.
- Zona dan sensor yang ada dipindahkan ke satu gudang bawaan **[Saya]**.
- Dirancang detail setelah proyek 1 selesai.
