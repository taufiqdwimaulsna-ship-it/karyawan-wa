# PresensiWA - Sistem Presensi Karyawan & WhatsApp Gateway Fonnte

Aplikasi presensi dan manajemen karyawan modern berbasis PHP Native dengan antarmuka (UI/UX) premium, responsif, dan terintegrasi langsung dengan Web API WhatsApp Gateway Fonnte.

---

## ✨ Fitur-Fitur Unggulan

1. **Klasifikasi 4 Status Kehadiran**:
   - **Hadir (Tepat Waktu)**: Karyawan melakukan absen masuk sebelum atau tepat pada batas waktu.
   - **Terlambat**: Otomatis terdeteksi jika absen masuk melebihi batas jam masuk (lengkap dengan kalkulasi menit keterlambatan).
   - **Izin**: Formulir pengajuan izin dengan alasan/keterangan yang dicatat rapi.
   - **Sakit**: Formulir pengajuan sakit untuk karyawan yang berhalangan hadir.

2. **Batas Waktu Fleksibel (Toleransi Keterlambatan)**:
   - Batas jam masuk default: **08:00 WIB**.
   - Administrator dapat mengubah batas jam masuk sewaktu-waktu langsung dari menu **Atur Batas Waktu** di Dashboard Admin tanpa perlu mengubah kode sumber.

3. **Notifikasi WhatsApp Real-Time untuk Admin & Karyawan**:
   - **Notifikasi ke Karyawan**:
     - Notifikasi saat registrasi/pembuatan akun baru.
     - Konfirmasi saat absen masuk (tepat waktu / terlambat).
     - Konfirmasi saat absen pulang beserta total jam kerja.
     - Konfirmasi pencatatan izin / sakit.
   - **Notifikasi ke Admin**:
     - Notifikasi saat karyawan absen masuk tepat waktu.
     - Notifikasi saat karyawan terlambat (lengkap dengan jumlah menit telat).
     - Notifikasi saat karyawan absen pulang (lengkap dengan durasi kerja).
     - Notifikasi saat karyawan mengajukan izin atau sakit.

4. **Rekapitulasi Kehadiran & Filter Lengkap**:
   - Filter berdasarkan rentang tanggal (tanggal mulai & akhir).
   - Filter berdasarkan karyawan tertentu atau semua karyawan.
   - Filter berdasarkan status kehadiran (Hadir, Terlambat, Izin, Sakit).
   - Matriks rekapitulasi kehadiran per karyawan.

5. **Export Laporan (Excel & PDF)**:
   - **Export Excel (`.xls`)**: Mengunduh spreadsheet berformat rapi yang dapat langsung dibuka di Microsoft Excel atau Google Sheets.
   - **Cetak / Export PDF**: Halaman cetak resmi lengkap dengan kop laporan, ringkasan metrik, tabel rincian presensi, dan kolom tanda tangan pengesahan (Pimpinan/HRD).

---

## 🚀 Panduan Penggunaan & Akses Cepat

1. Pastikan folder berada di `C:/xampp/htdocs/karyawan_wa/`.
2. Nyalakan modul **Apache** dan **MySQL** di XAMPP Control Panel.
3. Buka browser pada alamat:
   `http://localhost/karyawan_wa/`

### 🔑 Akun Demo Tersedia

| Role | Nama | Email | Password |
|---|---|---|---|
| **Administrator** | Administrator Demo | `admin@demo.com` | `admin123` |
| **Karyawan** | Budi Santoso | `budi@demo.com` | `budi123` |
| **Karyawan** | Agus Setiawan | `agus@demo.com` | `agus123` |
| **Karyawan** | Siti Rahmawati | `siti@demo.com` | `siti123` |

---

## ⚙️ Konfigurasi WhatsApp Gateway (Fonnte)

Buka file [config/database.php](file:///c:/xampp/htdocs/karyawan_wa/config/database.php):
- Ganti `FONNTE_TOKEN` dengan token aktif dari dashboard [Fonnte](https://fonnte.com).
- Ganti `ADMIN_PHONE` atau atur melalui menu pengaturan di Dashboard Admin.
