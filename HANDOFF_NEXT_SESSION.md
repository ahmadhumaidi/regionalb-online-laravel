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
- Production `https://regionalb.online` tetap memakai `/var/www/regionalb.online/public_html` native PHP.

## Langkah berikutnya

1. Review `ReportFormService` dan controller untuk authorization/status edge case.
2. Tambahkan feature tests authenticated untuk tiga report type, upload invalid, scope 403, dan delete.
3. Jalankan `php artisan test`, Blade/Vite build, dan HTTP smoke test dengan snapshot DB.
4. Commit pass form.
5. Jangan mengganti production legacy tanpa approval/cutover terpisah. Untuk rollback staging, pulihkan backup vhost lalu `nginx -t && systemctl reload nginx`.
