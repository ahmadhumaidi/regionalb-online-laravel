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

## Progress Closing Kampus (2026-08-05)

- Native page `closing-kampus` kini memiliki route Laravel `/closing-kampus` dan view khusus.
- Filter tanggal, wilayah, unit, grouping per regional, total closing, dan progress bar sudah dipindahkan memakai `CollabMetricsService` yang sama dengan dashboard.
- Guest smoke test staging berhasil redirect 302 ke login setelah permission compiled-view dinormalkan.

## Progress Regression Tests (2026-08-05)

- Guest redirect coverage diperluas dari 6 menjadi seluruh halaman migrasi: 16 protected routes termasuk Closing Kampus, Rekap export, Target, User, seluruh jadwal, Collab/BDC, dan Role.
- Test suite sekarang lulus 17 test / 33 assertion.

## Progress Authorization Tests (2026-08-05)

- Ditambahkan `tests/Feature/AuthorizationTest.php` untuk memastikan staff mendapat 403 pada Kelola User, Target, Jadwal Personalia, Sumber Collab, dan Jadwal Koordinator.
- Test suite sekarang lulus 22 test / 38 assertion.
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

## Progress Parity Jadwal Koordinator (2026-08-05)

- Daftar Jadwal Koordinator kini memiliki form Edit inline yang terhubung ke route PATCH, mencakup unit, tipe, status, agenda, hasil, dan follow-up.
- Cache/view berhasil dikompilasi dan test suite tetap lulus 7 test / 13 assertion.
- Smoke update staging sempat terkena permission compiled-view (gejala yang sama setelah `view:cache` dijalankan root); permission `storage/framework` sudah dinormalkan dan data uji dibersihkan.

## Progress Role/Permission (2026-08-05)

- Placeholder Role/Permission diganti `RoleController` dan view `resources/views/role/index.blade.php`.
- Menampilkan matriks akses seluruh role dan 40 log aktivitas terbaru dari `rsm_activity_logs`.
- Smoke test staging authenticated berhasil: halaman 200 dan matriks/log tampil. Akun smoke test sudah dihapus.
- `php artisan test` tetap lulus 7 test / 13 assertion.

## Progress BDC Marketing (2026-08-05)

- Placeholder BDC Marketing diganti `BdcUsersController`, `BdcReportUsersService`, dan view `resources/views/bdc-users/index.blade.php`.
- Menampilkan ringkasan staff/data/closing/FU dan tabel detail BDC Regional B dari cache API P2K.
- Tombol segarkan tersedia untuk role manajemen; cache legacy tetap menjadi fallback.
- Smoke test staging authenticated berhasil: halaman 200 dan data live API tampil. Akun smoke test sudah dihapus.
- `php artisan test` tetap lulus 7 test / 13 assertion.

## Progress Sumber Data Collab (2026-08-05)

- Placeholder Sumber Data Collab diganti `CollabSourceService`, `CollabSourceController`, dan view `resources/views/collab-source/index.blade.php`.
- Menampilkan tab report Collab, metadata cache, sumber asli, dan tabel snapshot legacy.
- Aksi segarkan menyalin cache legacy terbaru ke storage Laravel; jika sumber eksternal gagal, snapshot tetap tersedia.
- Smoke test staging authenticated berhasil: halaman 200 dan refresh 200. Akun smoke test sudah dihapus.
- `php artisan test` tetap lulus 7 test / 13 assertion.

## Progress Jadwal Koordinator (2026-08-05)

- Placeholder Jadwal Koordinator diganti `CoordinatorScheduleController` dan view `resources/views/coordinator-schedule/index.blade.php`.
- Ditambahkan filter bulan/wilayah/koordinator/status, tambah agenda, update, dan hapus.
- Scope role mengikuti native: staff tidak melihat halaman; koordinator terbatas regionalnya; role manajemen dapat mengelola.
- Smoke test staging authenticated berhasil: halaman 200 dan tambah agenda 200. Data/account smoke test sudah dibersihkan.
- `php artisan test` tetap lulus 7 test / 13 assertion.

## Progress Profil Pengguna (2026-08-05)

- Placeholder navigasi avatar kini menuju halaman Laravel `/profile`.
- Ditambahkan ringkasan profil, total kegiatan/leads/closing/hari aktif, aktivitas terbaru, edit biodata, dan upload foto profil.
- Scope laporan mengikuti role staff/koordinator dan area user.
- Ganti password tetap tersedia di `/profile/password`.
- Checkpoint commit: `e4d92e7`.
- Test regresi tetap lulus 22 test / 38 assertion.

## Gap Berikutnya

- Kelola User belum memiliki edit/hapus dan pengelolaan seluruh field profil.
- Jadwal Koordinator belum memiliki checklist, dokumentasi, generate bulanan, dan laporan WhatsApp.
- Sinkronisasi Collab/BDC/Personalia belum seluruhnya menjadi job API Laravel otomatis.
- Integrasi Instagram/social post native belum memiliki halaman Laravel.

## Progress Kelola User Lanjutan (2026-08-05)

- Ditambahkan edit user (nama, role, area, regional, unit, kontak, bio).
- Ditambahkan hapus user dengan proteksi agar akun sendiri tidak dapat dihapus.
- Perubahan dan penghapusan dicatat ke `rsm_activity_logs`.
- Route baru: `PATCH /users/{managedUser}` dan `DELETE /users/{managedUser}`.
- Test regresi tetap lulus 22 test / 38 assertion.

## Progress Jadwal Koordinator Parity (2026-08-05)

- Edit agenda sekarang mendukung checklist jobdesk (`checklist_json`).
- Upload dokumentasi JPG/PNG/WEBP/PDF maksimal 5 MB ke Laravel public storage.
- Ditambahkan route unduh dokumentasi dengan scope area/regional.
- Test regresi tetap lulus 22 test / 38 assertion.

## Progress Sinkronisasi Otomatis (2026-08-05)

- Ditambahkan command `php artisan rsm:sync-sources` untuk refresh Personalia, Collab, dan BDC.
- Mendukung subset `--only=personalia`, `--only=collab`, atau `--only=bdc`.
- Scheduler Laravel menjalankan sinkronisasi semua sumber setiap hari pukul 02:15 dengan `withoutOverlapping()`.
- Server perlu menjalankan cron Laravel: `* * * * * cd /var/www/regionalb-online-laravel && php artisan schedule:run >> /dev/null 2>&1`.

## Progress Generate Jadwal Koordinator (2026-08-05)

- Ditambahkan tombol dan route `POST /jadwal-koordinator/generate`.
- Generate membuat agenda default untuk setiap hari dan koordinator pada bulan terpilih.
- Agenda yang sudah ada tidak ditimpa; koordinator hanya dapat generate regionalnya sendiri.

## Progress Laporan WhatsApp Koordinator (2026-08-05)

- Super User dapat membuat rekap teks WhatsApp berdasarkan bulan terpilih.
- Rekap memuat tanggal, regional, koordinator, unit, status, hasil, dan follow-up.
- Hasil ditampilkan siap salin; tidak mengirim pesan otomatis.

## Progress Konten Instagram Manual (2026-08-05)

- Ditambahkan pendaftaran akun Instagram kampus di halaman Konten.
- Ditambahkan pencatatan post manual (feed/reels/story/tidak posting), keyword PMB, URL, dan skor otomatis.
- Scope post dibatasi ke area user; OAuth Meta tetap menunggu credential/config staging.

## Progress Authorization Regression (2026-08-05)

- Ditambahkan coverage authorization untuk generate jadwal bulanan dan laporan WhatsApp.
- Guest redirect coverage mencakup halaman Profil.

## Progress Collab Live Sync (2026-08-05)

- `CollabSourceService::sync()` sekarang mencoba mengambil enam endpoint cb.web.id secara langsung.
- HTML tabel diparsing menjadi snapshot Laravel; jika seluruh endpoint gagal, cache legacy tetap digunakan.
- Mode sumber pada snapshot membedakan data live dan fallback.
- Uji live sync berjalan; sebagian besar report berhasil, satu endpoint `Herreg Kampus Regional` masih mengembalikan error dan fallback digunakan.

## Deployment Scheduler (2026-08-05)

- Cron server staging sudah ditambahkan: `* * * * * cd /var/www/regionalb-online-laravel && php artisan schedule:run >> /dev/null 2>&1`.
- Sinkronisasi harian Laravel kini akan dieksekusi otomatis oleh cron.

## Deployment Hardening (2026-08-05)

- Ditambahkan `scripts/prepare-staging.sh` untuk normalisasi permission source/runtime dan rebuild cache Blade.
- Script mengembalikan ownership compiled view ke `www-data` setelah `view:cache`.

## Progress Profil Gamification (2026-08-05)

- Profil Laravel kini menghitung XP dari laporan/leads/closing.
- Ditambahkan level, progress level, league, skor performa, dan badge dasar.
- Test suite tetap lulus 24 test / 42 assertion.

## Setup Environment Lokal (2026-08-08)

- Environment dev lokal (Windows) berhasil dijalankan: PHP 8.3.30 (Laragon,
  bukan XAMPP yang 8.2 — composer.lock butuh `^8.3` karena zipstream-php),
  php.ini dibuat manual (belum ada sebelumnya) dengan ekstensi curl,
  fileinfo, gd, intl, mbstring, openssl, pdo_mysql, pdo_sqlite, sqlite3,
  zip, dan `curl.cainfo`/`openssl.cafile` diarahkan ke cacert bundle XAMPP
  (perlu untuk HTTPS request PHP ke `cb.web.id`, awalnya gagal SSL).
- SQLite tidak dipakai — migration `add_wilayah_to_partner_campuses_table`
  pakai raw MySQL `UPDATE ... JOIN`. Database dev lokal: MySQL
  (`regionalb_online_dev`), data ditarik dari dump `staging_regionalb_db`
  di VPS (bukan dari `regionalb_db` production yang berisi PII asli 86
  staff — sengaja dihindari untuk dev lokal).
- Workflow yang disepakati: edit langsung di VPS (staging) via SSH, commit
  & push dari sana ke GitHub, verifikasi jalan (biasanya lewat
  `php artisan tinker` dengan skenario nyata) SEBELUM push, baru
  `git pull` ke lokal untuk sinkron. Jangan buru-buru push sebelum
  terverifikasi.

## Bug Fix: Dropdown Unit/Kampus Menimpa Data Report (2026-08-07/08)

- Ditemukan laporan iklan UNIGRES (`id=60` production, staff Alif
  Triwisno) `unit_name`/`partner_campus_id`-nya ketuker jadi "IKIP Widya
  Darma" setelah diedit koordinator Moh Nor Abidin.
- Akar masalah: select "Unit/Kampus" di form edit (production
  `render_edit_input()` dan Laravel `reports/form.blade.php`) mencocokkan
  opsi dengan strict string match ke `report->unit_name` (nama panjang,
  `partner_campuses.name`); daftar referensi kampus kadang cuma
  menyediakan nama pendek (`display_name`/`rsm_users.campus_name`), jadi
  tidak ada opsi ter-`selected` dan browser default ke opsi pertama —
  submit form menimpa campus dengan nilai yang salah.
- Data laporan id=60 sudah dikembalikan ke Universitas Gresik (UNIGRES).
  Fix kode sudah di kedua tempat: production `dashboard.php` (commit lokal
  `6e183ee`, **belum di-push** — lihat "Divergensi Repo Production" di
  bawah) dan Laravel `reports/form.blade.php` (commit `a94d7cf`, sudah
  di-push).

## Divergensi Repo Production `regionalb.git` — BELUM Direconcile (2026-08-08)

Repo git production di VPS (`/var/www/regionalb.online`, branch
`feature/rsm-dashboard-phase-1`) sudah lama diverged dari `origin`
(GitHub `ahmadhumaidi/regionalb`): **17 commit lokal-only vs 15
commit origin-only** (per 2026-08-08). Sengaja **ditunda**, jangan
disentuh tanpa waktu khusus — ringkasan investigasi:

- HEAD lokal = kode yang **live sekarang** di production. Origin punya
  kerjaan yang sudah di-push ke GitHub tapi **belum pernah di-deploy**
  ke server ini (rentang tanggal 2026-07-16 s/d 2026-08-01): perbaikan
  bug `"Fix duplicate KORWIL cards and wrong WhatsApp achievement text"`,
  `"Enforce ad report approval workflow"`, beberapa polish UX form
  pengajuan iklan (pre-fill anggaran, popup sukses, dll).
- Ada jejak commit lokal `d1a51b7` "Snapshot VPS working tree before
  realigning with origin" (2026-08-01) — sepertinya ada usaha reconcile
  sebelumnya yang **mangkrak di tengah jalan**: bukannya menyelesaikan
  penggabungan, malah lanjut nambah commit baru (Sumber Data Collab,
  Jadwal Personalia, dst.) di atas snapshot itu tanpa pernah pull origin.
- File `instagram-connect.php`/`instagram-callback.php` **identik** di
  kedua sisi (bukan sumber konflik).
- Percobaan trial-merge (di branch sementara, sudah di-abort &
  dibersihkan, tidak menyentuh branch asli) menghasilkan **76 titik
  konflik** di 4 file: `dashboard.php` (35), `rsm_db.php` (33),
  `assets/app.js` (2), `assets/style.css` (6). Perlu waktu khusus untuk
  resolve manual per konflik, jangan buru-buru/otomatis di production.
- ~289 file "berubah" versi lokal mayoritas cuma noise (`runtime/.../
  sessions/sess_*` — file session PHP yang ke-commit tidak sengaja,
  sudah ditangani parsial oleh commit `d7bf7e6` "Stop tracking runtime
  uploads and source_activity in git" tapi historinya tetap ada).
- Scan laporan lain dengan pola sama: cuma id=60 yang benar-benar korup
  (5 kandidat lain cuma beda gaya penamaan teks, `partner_campus_id`
  tetap konsisten benar).

## Audit Parity Staging vs Production (2026-08-08)

Dibandingkan langsung lewat kode (bukan cuma cek per-halaman ada/tidak):
gap yang ditemukan dan sudah dikerjakan:

- **Plafon Anggaran Iklan**: Senior Manager sekarang bisa simpan plafon
  per regional/periode (`AdBudgetLimitService::save()`, route
  `POST /anggaran/limit`). Infrastruktur permission
  (`RsmRole::canManageAdBudget`, Gate `manage-ad-budget`) sudah ada
  sebelumnya tapi tidak pernah dipakai.
- **Bahan WhatsApp Otomatis** di halaman Rekap (super_user only):
  `AchievementWhatsappService`, route `POST /rekap/whatsapp`. Versi teks
  saja — gambar PNG legacy (headless browser screenshot + fallback GD)
  sengaja tidak diporting, ikut pola "Laporan WhatsApp Koordinator" yang
  sudah ada.
- **Bug lama ikut kefix**: panel "Laporan WhatsApp Koordinator" generate
  teksnya benar tapi blade-nya cek `session('whatsapp_report')` yang
  tidak pernah di-set controller — hasilnya tidak pernah tampil. Sekarang
  pakai `$whatsappArtifact` yang memang sudah dikirim controller.
- **Export Rekap**: tambah PDF (`barryvdh/laravel-dompdf` sudah
  ter-install tapi belum pernah dipakai) dan Excel untuk semua jenis
  rekap (sebelumnya Excel cuma untuk tipe ads). Controller terima
  `?format=csv|excel|pdf`.
- **Panel sync-health** (Collab + BDC) ditambahkan ke halaman BDC
  Marketing, menyusul yang sudah ada di Sumber Data Collab.
- **Chore**: `public/build` di-stop-track dari git (sudah gitignored
  tapi kepentok ke-commit lama, bikin gesekan tiap `npm run build`).

Belum dikerjakan (perlu keputusan/koordinasi eksternal lebih lanjut):

- **Instagram/Meta OAuth auto-sync**: dikonfirmasi ini fitur yang
  BENERAN jalan di production — `source_activity/meta.php` berisi
  app_id/app_secret Meta App asli, ada `instagram-connect.php` +
  `instagram-callback.php`. Bukan cuma menunggu kredensial (asumsi
  sebelumnya salah). Untuk porting ke staging perlu: redirect URI baru
  terdaftar di Meta App Dashboard (staging.regionalb.online), pemindahan
  kredensial sensitif ke `.env`, dan port OAuth flow + Graph API sync job.
  User memilih untuk laporan dulu, belum eksekusi.
- Test suite tetap lulus 24 test / 42 assertion setelah semua perubahan
  di atas.
