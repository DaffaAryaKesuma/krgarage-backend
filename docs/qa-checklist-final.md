# Checklist QA Final KRGarage Backend

Tanggal update: 2026-04-16

## Ringkasan Status

- Total item: 21
- Selesai: 17
- Belum dicek manual: 4

## A. Checklist Yang Sudah Dilakukan

### 1) Auth dan Akses

- [x] Request API tanpa token ditolak dengan 401.
- [x] Request API dengan role yang salah ditolak dengan 403.
- [x] Endpoint logout menghapus token aktif dan token lama tidak bisa dipakai lagi.
- [x] Unauthenticated API tidak melempar error Route login, tetapi kembali JSON 401.

### 2) Otorisasi Mekanik per Booking

- [x] Mekanik yang tidak ditugaskan tidak bisa update status booking milik mekanik lain (403).
- [x] Mekanik yang tidak ditugaskan tidak bisa tambah suku cadang booking milik mekanik lain (403).
- [x] Mekanik yang tidak ditugaskan tidak bisa hapus item booking milik mekanik lain (403).

### 3) Alur Status Booking

- [x] Admin bisa ubah status ke In Progress (200).
- [x] Mekanik hanya menerima status Completed, nilai lain ditolak validasi (422).
- [x] Mekanik tidak bisa Completed jika status belum In Progress (400).
- [x] Booking yang sudah Completed atau Cancelled tidak bisa diubah lagi (400).

### 4) Integritas Stok

- [x] Stok suku cadang berkurang saat transisi pertama ke Completed.
- [x] Request Completed berulang tidak mengurangi stok lagi.

### 5) Skenario Cancel

- [x] Admin bisa Cancelled booking sesuai flow.
- [x] Setelah Cancelled, mekanik tidak bisa ubah status/tambah item/hapus item pada booking tersebut.

### 6) Verifikasi Otomatis

- [x] Feature test BookingStatusFlowTest lulus.
- [x] Full test suite lulus.

## B. Checklist Manual Lanjutan (Disarankan)

- [ ] Validasi notifikasi ke user yang tepat untuk setiap transisi status (tanpa duplikasi).
- [ ] Validasi frontend route guard saat akses URL lintas role secara langsung.
- [ ] Validasi perilaku token expired di frontend (redirect dan clear session).
- [ ] Validasi tampilan riwayat mekanik hanya memuat booking yang memang ditugaskan ke mekanik login.

## C. Catatan Bukti yang Sudah Ada

- Bukti manual utama: pengujian Postman untuk status 401/403/422/400/200 serta alur status admin dan mekanik.
- Bukti otomatis: seluruh test di backend sudah lulus saat dijalankan pada environment test.

## D. Lokasi File Test Utama

- tests/Feature/BookingStatusFlowTest.php

## E. Versi Tabel Untuk Laporan Skripsi

| No  | Modul             | Skenario Pengujian                          | Langkah Uji Singkat                                        | Hasil Diharapkan                              | Hasil Aktual                     | Status      |
| --- | ----------------- | ------------------------------------------- | ---------------------------------------------------------- | --------------------------------------------- | -------------------------------- | ----------- |
| 1   | Auth dan Akses    | API tanpa token                             | Request endpoint protected tanpa Authorization             | Response 401                                  | Response 401                     | Lulus       |
| 2   | Auth dan Akses    | Akses lintas role                           | Login role salah, akses endpoint role lain                 | Response 403                                  | Response 403                     | Lulus       |
| 3   | Auth dan Akses    | Logout revoke token                         | Login, panggil endpoint keluar, pakai token lama           | Token lama tidak valid                        | Token lama tidak valid           | Lulus       |
| 4   | Auth dan Akses    | Unauthenticated API handling                | Akses endpoint protected tanpa login                       | JSON 401, tidak redirect ke login route       | JSON 401 tanpa error Route login | Lulus       |
| 5   | Otorisasi Mekanik | Mekanik lain update status                  | Mekanik B update booking milik mekanik A                   | Ditolak 403                                   | Ditolak 403                      | Lulus       |
| 6   | Otorisasi Mekanik | Mekanik lain tambah item                    | Mekanik B tambah suku cadang booking mekanik A             | Ditolak 403                                   | Ditolak 403                      | Lulus       |
| 7   | Otorisasi Mekanik | Mekanik lain hapus item                     | Mekanik B hapus item booking mekanik A                     | Ditolak 403                                   | Ditolak 403                      | Lulus       |
| 8   | Alur Status       | Admin set In Progress                       | Admin patch status ke In Progress                          | Response 200, status berubah                  | Response 200, status berubah     | Lulus       |
| 9   | Alur Status       | Mekanik kirim status selain Completed       | Mekanik kirim Confirmed/In Progress                        | Ditolak validasi 422                          | Ditolak 422                      | Lulus       |
| 10  | Alur Status       | Mekanik selesai sebelum In Progress         | Mekanik kirim Completed saat status belum In Progress      | Ditolak 400                                   | Ditolak 400                      | Lulus       |
| 11  | Alur Status       | Lock status final                           | Ubah status setelah Completed/Cancelled                    | Ditolak 400                                   | Ditolak 400                      | Lulus       |
| 12  | Integritas Stok   | Potong stok saat selesai pertama            | Transisi pertama ke Completed                              | Stok berkurang sesuai item                    | Stok berkurang sekali            | Lulus       |
| 13  | Integritas Stok   | Anti double deduction                       | Kirim Completed berulang                                   | Stok tidak berkurang lagi                     | Stok tetap                       | Lulus       |
| 14  | Skenario Cancel   | Admin membatalkan booking                   | Admin patch status ke Cancelled                            | Response 200, status Cancelled                | Response 200, status Cancelled   | Lulus       |
| 15  | Skenario Cancel   | Mekanik tidak bisa sentuh booking cancelled | Mekanik coba update/tambah/hapus pada booking cancelled    | Ditolak 400                                   | Ditolak 400                      | Lulus       |
| 16  | Otomasi Test      | Feature test alur status                    | Jalankan php artisan test --filter=BookingStatusFlowTest   | Semua test terkait lulus                      | 4 test lulus                     | Lulus       |
| 17  | Otomasi Test      | Full test suite backend                     | Jalankan php artisan test                                  | Seluruh test lulus                            | 6 test lulus                     | Lulus       |
| 18  | Notifikasi        | Ketepatan penerima dan duplikasi            | Uji transisi status dan cek notifikasi tiap role           | Notifikasi tepat sasaran, tidak duplikat      | Belum diuji manual final         | Belum Diuji |
| 19  | Frontend Guard    | Akses URL lintas role                       | Login per role lalu akses route role lain via URL langsung | Redirect sesuai role                          | Belum diuji manual final         | Belum Diuji |
| 20  | Frontend Session  | Penanganan token expired                    | Pakai token expired saat akses API dari frontend           | Session dibersihkan, diarahkan ke login       | Belum diuji manual final         | Belum Diuji |
| 21  | Riwayat Mekanik   | Filter riwayat berdasarkan penugasan        | Login mekanik dan buka riwayat pekerjaan                   | Hanya booking milik mekanik login yang tampil | Belum diuji manual final         | Belum Diuji |
