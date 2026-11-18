# Setup Laravel, Filament, dan PostgreSQL

Project ini menggunakan PostgreSQL sebagai database utama dan Filament pada `/admin`.

## Menjalankan project dengan DDEV di Ubuntu WSL

Buka terminal Ubuntu. Gunakan salinan kerja di filesystem Ubuntu dan jalankan perintah dari root project.

```bash
cd ~/projects/app-first
ddev start
ddev describe
ddev launch /admin
```

URL admin default adalah <https://app-first.ddev.site/admin>. Jika port router berubah, gunakan URL dari `ddev describe`. DDEV mengelola Nginx, PHP 8.4, dan PostgreSQL 16 melalui Docker Engine di Ubuntu. `ddev stop` menghentikan layanan project dan mempertahankan volume database.

Untuk checkout baru, buat `.env` dari `.env.example` jika belum tersedia. Jalankan `ddev composer install` setelah `ddev start`. Jalankan `ddev artisan key:generate` hanya jika `APP_KEY` masih kosong. Gunakan `ddev artisan db:show --database=pgsql` untuk memeriksa target sebelum menjalankan `ddev artisan migrate`. Jika membawa data lama, ikuti langkah backup dan impor untuk database kosong di [rencana implementasi](IMPLEMENTATION_DDEV.md).

Jika database kosong dan belum punya akun admin, jalankan `ddev artisan make:filament-user --panel=admin` lalu `ddev artisan app:grant-admin <email>` dengan mengganti placeholder email. Jika database berasal dari impor, gunakan akun admin yang sudah ada. Composer menerbitkan aset bawaan Filament saat instalasi.

### DBeaver Windows

Buat koneksi PostgreSQL baru dengan host dan port **eksternal** dari `ddev describe`. Untuk setup ini, nama database, username, dan password development masing-masing `db`. Setelah **Test Connection**, buka `Schemas > public > Tables`. Laravel menggunakan `db:5432` di jaringan container. Jika port host dipilih otomatis, perbarui koneksi DBeaver dengan nilai terbaru setelah restart.

Saat verifikasi dari Windows, PostgreSQL DDEV dapat diakses pada `127.0.0.1:22519` dan menampilkan sembilan tabel. Ambil port terbaru dari `ddev describe` sebelum menyimpan koneksi DBeaver.

```sql
SELECT current_database(), current_user;
SELECT migration, batch FROM public.migrations ORDER BY id;
```

## Referensi setup PHP/PostgreSQL native di Windows

Bagian ini mendokumentasikan environment Windows yang menjadi sumber data awal.

### Environment yang diverifikasi

- Windows, PHP 8.4.4, Composer 2.10.2, PostgreSQL 16.
- Laravel 13.42.0, Filament 5.11.4, Livewire 4.4.8; versi pasti tersimpan di `composer.lock`.
- Ekstensi PHP: `pdo_pgsql`, `intl`, `mbstring`, `dom`, `fileinfo`, dan `zip`, beserta ekstensi standar Laravel. `composer check-platform-reqs` memeriksa dependency aktual.
- Pada setup native, PHP dan PostgreSQL berjalan langsung di Windows.
- Pada komputer ini, `intl`, `pdo_pgsql`, `pgsql`, dan `zip` diaktifkan di `C:\php-8.4.4\php.ini`. Restart proses PHP yang sudah berjalan setelah mengubah ekstensi.

Rujukan: [instalasi Filament 5](https://filamentphp.com/docs/5.x/introduction/installation) dan [otorisasi panel](https://filamentphp.com/docs/5.x/users/overview).

### Konfigurasi lokal native

Isi `.env` menggunakan koneksi berikut. Password lokal tidak disimpan dalam dokumentasi atau source control.

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=filament
DB_USERNAME=admin
DB_PASSWORD="<password PostgreSQL>"
APP_URL=http://127.0.0.1:8000
SESSION_SECURE_COOKIE=false
```

Role aplikasi `admin` memiliki database `filament`, tanpa izin superuser, membuat database, atau membuat role. Kredensial superuser PostgreSQL tidak digunakan oleh aplikasi.

Session, cache, dan queue memakai driver `database`. Migration bawaan Laravel menyediakan tabelnya. `.env.example` sekarang berisi pengaturan development DDEV.

### Menjalankan checkout baru dengan PHP native

1. Pastikan database dan role PostgreSQL sudah tersedia. Jika sudah berisi data, gunakan kredensial database tersebut dan periksa migration yang tertunda sebelum menjalankannya.
2. Jalankan `composer install`.
3. Salin `.env.example` menjadi `.env` hanya jika `.env` belum ada, lalu ganti pengaturan koneksi, URL, dan cookie dengan nilai native di atas.
4. Jalankan `php artisan key:generate` hanya untuk instalasi baru yang belum memiliki `APP_KEY`.
5. Jalankan:

```powershell
php artisan config:clear
php artisan db:show --database=pgsql
php artisan migrate:status
php artisan migrate --pretend
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

Buka <http://127.0.0.1:8000/admin>. Aset bawaan Filament diterbitkan melalui Composer hook `filament:upgrade` saat pemasangan dependency. Halaman welcome menggunakan CSS statis yang disertakan langsung dalam view.

Untuk pengembangan lokal, `composer dev` menjalankan `php artisan serve`. Jika membutuhkan pemrosesan queue, jalankan `php artisan queue:work --tries=1` pada terminal terpisah.

Gunakan `migrate` untuk menerapkan migration yang belum dijalankan. Hindari `migrate:fresh`, `migrate:refresh`, atau `db:wipe` pada database berisi data. Database SQLite lama tidak dihapus maupun dipindahkan secara otomatis.

## Membuat dan mengizinkan admin

```powershell
php artisan make:filament-user --panel=admin
php artisan app:grant-admin admin@gmail.com
```

Command pertama meminta nama, email unik, dan password secara interaktif, lalu menyimpan password sebagai hash. Ganti email pada command kedua sesuai user yang dibuat. Jika user sudah ada, cukup jalankan command kedua; command ini tidak mengganti password atau membuat user baru.

Admin awal instalasi ini adalah `admin@gmail.com` dengan nama `Administrator`. Password mengikuti permintaan pemilik project dan tidak dicantumkan dalam file yang dilacak Git.

`User::canAccessPanel()` mengizinkan panel `admin` hanya ketika `is_admin=true`, termasuk pada environment lokal. User baru memiliki `is_admin=false`; atribut ini tidak dapat diisi lewat mass assignment. Pendaftaran publik tidak diaktifkan. Filament menggunakan guard `web`, middleware autentikasi/session/CSRF bawaan, dan pembatasan percobaan login bawaan.

## DBeaver

Buat koneksi **PostgreSQL** dengan host `127.0.0.1`, port `5432`, database `filament`, username `admin`, dan password database dari `.env`. Jalankan **Test Connection**, lalu buka **Databases → filament → Schemas → public → Tables** dan refresh navigator.

Tabel yang sudah diverifikasi melalui koneksi SQL:

`users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, dan `migrations`.

Kolom `users.is_admin` bertipe boolean dengan default `false`. Tabel `users` berisi admin pertama; password disimpan menggunakan bcrypt. Antarmuka DBeaver belum diperiksa langsung.

## Pengujian

```powershell
composer test
composer test:postgres
composer check-platform-reqs
composer validate --strict
composer audit
php vendor/laravel/pint/builds/pint --test
```

`composer test` menggunakan SQLite `:memory:` yang dipaksa oleh `phpunit.xml`, sehingga tidak memakai database aplikasi.

`composer test:postgres` memakai PostgreSQL dari `.env` melalui `phpunit.postgres.xml`. Jalankan setelah migration. Setiap pengujian membungkus perubahan user dalam transaksi yang di-rollback; suite ini tidak menjalankan reset, drop tabel, atau migration pada PostgreSQL. Session dan cache pengujian memakai array. Jangan menjalankan suite integrasi ini secara paralel menggunakan opsi yang membuat ulang database.

Cakupan pengujian: halaman login, redirect tamu, login admin dan dashboard, password salah, penolakan user biasa, logout, hash password hasil command Filament, pemberian izin berulang tanpa perubahan password, user tidak ditemukan, pencegahan peningkatan izin lewat mass assignment, dan pencabutan akses admin.

Hasil verifikasi setup tanggal 17 November 2026:

- `composer test`: 13 test lulus, 69 assertion.
- `composer test:postgres`: 11 test lulus, 45 assertion; transaksi pengujian di-rollback dan hanya admin awal tersisa.
- Empat migration berstatus `Ran`; sembilan tabel ditemukan pada schema `public`.
- Login HTTP menggunakan akun admin awal: halaman login 200, POST Livewire autentikasi 200, redirect ke `/admin`, dashboard 200 dengan session cookie.
- Password admin cocok melalui `Hash::check()` dan terdeteksi sebagai bcrypt.
- Validasi Composer, persyaratan platform, Laravel Pint, dan `git diff --check` lulus. Audit Composer tidak menemukan advisori keamanan.
- Ekstensi PHP lokal diaktifkan pada `C:\php-8.4.4\php.ini`; perubahan environment ini berada di luar repository.

## Perubahan setup

- `composer.json`, `composer.lock`: Filament, syarat ekstensi PostgreSQL, pembaruan dependency kompatibel, penerbitan aset, serta command pengujian PostgreSQL.
- `app/Providers/Filament/AdminPanelProvider.php`, `bootstrap/providers.php`: panel `/admin`, login, dashboard, guard dan middleware.
- `app/Models/User.php`: kontrak `FilamentUser`, pembatasan akses, cast boolean admin; cast password `hashed` tetap digunakan.
- `database/migrations/2026_11_17_000000_add_is_admin_to_users_table.php`: penambahan izin admin tanpa mengganti migration lama.
- `app/Console/Commands/GrantAdmin.php`: memberikan akses admin pada user yang sudah ada.
- `.env`, `.env.example`, `config/database.php`: koneksi utama PostgreSQL; `.env` tetap diabaikan Git.
- `.gitignore`: aset terbitan Filament.
- `tests/Feature/AdminPanelTest.php`, `phpunit.xml`, `phpunit.postgres.xml`: pengujian autentikasi dan otorisasi, dengan database pengujian terpisah menurut konfigurasi.
- `README.md`, `docs/SETUP.md`: petunjuk menjalankan project dan koneksi DBeaver.
