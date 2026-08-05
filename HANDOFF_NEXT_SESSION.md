# Handoff — migrasi form laporan Laravel

Tanggal: 2026-08-05
Repo: `/var/www/regionalb-online-laravel`

## Status

Pass form laporan sudah diimplementasikan dan akan disimpan sebagai checkpoint commit. Masih perlu satu pass review/tes authenticated sebelum dianggap selesai penuh.

Staging sudah dicutover ke Laravel pada 2026-08-05. Backup vhost tersimpan di `/etc/nginx/sites-available/staging.regionalb.online.conf.before-laravel-20260805`. Production `regionalb.online` tidak diubah.

Yang sudah ditambahkan:

- `ReportFormService`: konfigurasi form untuk `marketing`, `other`, dan `ads`; create/update/delete; scope; identity staff; validasi plafon ads; upload Storage public; activity log.
- `ReportFormController`: create/store/show/edit/update/destroy/attachment.
- Route create/store/detail/edit/update/delete/download di `routes/web.php`.
- View generik `resources/views/reports/form.blade.php` dan `show.blade.php`.
- Tombol Tambah pada Kegiatan, Aktivitas, Anggaran dan link Detail pada tabel laporan.
- Konfigurasi fallback lampiran legacy melalui `filesystems.legacy_public_root`.

## Verifikasi yang sudah dilakukan

- PHP lint berhasil.
- `php artisan view:cache` berhasil.
- `php artisan test`: 7 test / 13 assertion lulus.
- HTTP smoke test login koordinator:
  - `/kegiatan/create` 200
  - `/aktivitas/create` 200
  - `/anggaran/create` 200
  - `/laporan/83` 200
  - `/laporan/83/edit` 403 sesuai aturan karena akun koordinator bukan pemilik/editable report.
- Satu create marketing melalui HTTP berhasil menghasilkan row `rsm_reports` status `Dikirim`; row, log, dan akun smoke test sudah dihapus kembali.

## Catatan penting

- Form awal sempat terkena RouteNotFound dan undefined `$user`; keduanya sudah diperbaiki.
- Route form store saat ini bernama `kegiatan.store`, `aktivitas.store`, dan `anggaran.store`; view sudah memakai nama dinamis.
- Belum ada feature test authenticated untuk create/update/upload/delete.
- Checkpoint pass ini akan menjadi commit terpisah dari implementasi sebelumnya.
- `https://staging.regionalb.online/login` sekarang merespons Laravel (HTTP 200); guest `/` redirect ke `/login` (302).
- Permission `public/build` sempat menyebabkan 500 karena `manifest.json` mode 600; sudah diperbaiki ke permission baca publik.
- Login staging sempat gagal dengan `SQLSTATE[42S22]`/kode UI `42522` karena Laravel mencoba menulis kolom `password`; `RsmUser::getAuthPasswordName()` sekarang mengembalikan `password_hash` dan login sudah terverifikasi 302 → dashboard 200.
- Permission `bootstrap/cache` dan `storage/framework` juga dinormalkan agar PHP-FPM dapat membaca cache setelah Artisan dijalankan.
- Production `https://regionalb.online` tetap memakai `/var/www/regionalb.online/public_html` native PHP.

## Langkah berikutnya

1. Review `ReportFormService` dan controller untuk authorization/status edge case.
2. Tambahkan feature tests authenticated untuk tiga report type, upload invalid, scope 403, dan delete.
3. Jalankan `php artisan test`, Blade/Vite build, dan HTTP smoke test dengan snapshot DB.
4. Commit pass form.
5. Jangan mengganti production legacy tanpa approval/cutover terpisah. Untuk rollback staging, pulihkan backup vhost lalu `nginx -t && systemctl reload nginx`.

## Progress Rekap (2026-08-05)

- Ditambahkan `ReportRecapService` dan `ReportRecapController`.
- Route `/rekap` dan `/rekap/export` aktif; placeholder Rekap dihapus.
- View Rekap menyediakan filter periode/wilayah/unit/jenis, ringkasan laporan/leads/closing/anggaran/realisasi, tabel, Detail, dan export CSV.
- Smoke test staging berhasil: `/rekap` 200 dan `/rekap/export` 200 dengan 7 baris CSV.
- Permission source (`app`, `resources`, `routes`, `config`) perlu dijaga readable oleh PHP-FPM setelah perubahan file.

## Progress Target Bulanan (2026-08-05)

- Placeholder Target Bulanan sudah diganti `TargetController` + view `resources/views/targets/index.blade.php`.
- Route `GET /targets` dan `POST /targets` aktif; akses dibatasi role yang sama dengan legacy (`super_user`, executive/director, senior, mentor).
- Form mendukung scope regional/wilayah/unit/staff, opsi terapkan ke semua, target leads/follow-up/registrasi/herregistrasi/anggaran, catatan, dan upsert memakai `scope_key` legacy.
- Daftar target staff terbaru ditampilkan dan data mengikuti area user.
- Smoke test staging authenticated berhasil: `/targets` 200, POST target staff 200 dengan pesan berhasil dan row tampil. Akun/data smoke test sudah dihapus.
- `php artisan test` tetap lulus 7 test / 13 assertion; Blade cache berhasil setelah permission `storage/framework` dinormalkan.

## Langkah berikutnya

1. Tambahkan feature test authenticated untuk Target Bulanan dan validasi scope/bulk.
2. Migrasikan halaman administrasi berikutnya: Jadwal Personalia atau Kelola User.
3. Pertahankan staging-only; production tetap native sampai ada approval cutover.

## Progress Jadwal Personalia (2026-08-05)

- Placeholder Jadwal Personalia diganti halaman Laravel dengan snapshot cache legacy sebagai fallback.
- Ditambahkan `PersonnelScheduleService`, `PersonnelScheduleController`, route GET `/jadwal-personalia`, dan POST `/jadwal-personalia/sync`.
- Sinkronisasi mengambil Zona 2 dari `cb.web.id`; jika sumber gagal, snapshot lama tetap ditampilkan dan error dicatat.
- Smoke test staging authenticated berhasil: halaman 200 dan POST sinkronisasi 200. Sumber eksternal saat tes tidak mengembalikan tabel, tetapi cache lama tetap tampil tanpa kehilangan data.
- Akun smoke test sudah dihapus. Production tetap native.

## Progress Kelola User (2026-08-05)

- Placeholder Kelola User diganti `UserManagementController` dan view `resources/views/users/index.blade.php`.
- Ditambahkan daftar user per area, tambah user baru dengan role/area/regional, aktivasi/nonaktifkan, dan reset password dengan `must_change_password`.
- Akses dibatasi sama dengan legacy: hanya `super_user` dan `senior`.
- Smoke test staging berhasil: `/users` 200 dan create user 200. Akun/data smoke test sudah dibersihkan.
- `php artisan test` tetap lulus 7 test / 13 assertion.
