# Tugas Akhir Kriptografi - Brankas Pribadi

Aplikasi web berbasis PHP untuk enkripsi dan dekripsi data pribadi dengan berbagai metode kriptografi. Aplikasi ini menyediakan fitur untuk mengamankan catatan teks, file, dan pesan rahasia menggunakan steganografi.

## 📋 Deskripsi

Aplikasi **Brankas Pribadi** adalah sistem keamanan data yang memungkinkan pengguna untuk:

- Menyimpan dan mengamankan catatan pribadi dengan enkripsi
- Mengenkripsi dan mendekripsi file penting
- Menyembunyikan pesan rahasia di dalam gambar menggunakan steganografi
- Mengelola data terenkripsi dengan aman melalui dashboard

## ✨ Fitur

### 🔐 Brankas Catatan Pribadi

- **Tulis & Kunci Catatan Baru**: Simpan data diri atau pesan rahasia dengan enkripsi
- **Buka & Baca Catatan Lama**: Akses catatan yang sudah tersimpan dan terenkripsi

### 📁 Brankas Keamanan File

- **Amankan File Penting**: Enkripsi file (.pdf, .docx, .zip, dll) dengan password
- **Buka File Terkunci**: Dekripsi file yang sudah dienkripsi sebelumnya

### 🖼️ Brankas Pesan Rahasia Gambar

- **Sembunyikan Pesan di Gambar**: Sisipkan pesan rahasia di dalam file gambar (Steganografi)
- **Periksa Pesan di Gambar**: Ekstrak dan baca pesan rahasia yang ada di dalam gambar

### 👤 Sistem Autentikasi

- Registrasi pengguna baru
- Login dengan username dan password
- Session management
- Logout

## 🛠️ Teknologi yang Digunakan

- **Backend**: PHP 8.2+
- **Database**: MySQL/MariaDB
- **Library Kriptografi**: phpseclib3 (RC4, RSA)
- **Algoritma Kriptografi**:
  - Vigenere Cipher
  - RC4 Encryption
  - RSA Encryption
  - Steganografi (EXIF)

## 📦 Persyaratan Sistem

- PHP 8.2 atau lebih tinggi
- MySQL/MariaDB 10.4 atau lebih tinggi
- Web Server (Apache/Nginx) atau Laragon/XAMPP
- Composer (untuk dependency management)
- Extension PHP:
  - `mysqli`
  - `gd` (untuk steganografi)
  - `openssl`

## 🚀 Instalasi

### 1. Clone Repository

```bash
git clone <repository-url>
cd TugasAkhir_Kriptografi
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Setup Database

1. Buat database baru di MySQL:

```sql
CREATE DATABASE TA_Kripto;
```

2. Import file SQL:

```bash
# Menggunakan phpMyAdmin atau command line
mysql -u root -p TA_Kripto < database/ta_kripto.sql
```

Atau import file `database/ta_kripto.sql` melalui phpMyAdmin.

### 4. Konfigurasi Database

Edit file `php/koneksi.php` dan sesuaikan dengan konfigurasi database Anda:

```php
$host = "localhost";
$user = "root";
$pass = "";  // Sesuaikan dengan password MySQL Anda
$db = "TA_Kripto";
```

### 5. Setup Folder Uploads

Pastikan folder `uploads/files/` memiliki permission write:

- `uploads/files/file_encrypted/`
- `uploads/files/file_decrypted/`

### 6. Jalankan Aplikasi

Jika menggunakan Laragon/XAMPP:

- Pastikan Apache dan MySQL sudah berjalan
- Akses aplikasi melalui: `http://localhost/TugasAkhir_Kriptografi/`

## 📖 Cara Penggunaan

### 1. Registrasi & Login

- Buka halaman utama aplikasi
- Klik "Register" untuk membuat akun baru
- Login dengan username dan password yang sudah didaftarkan

### 2. Enkripsi Teks

- Pilih menu "Tulis & Kunci Catatan Baru"
- Masukkan label dan teks yang ingin dienkripsi
- Pilih metode enkripsi (Vigenere, RC4, atau RSA)
- Masukkan kunci enkripsi
- Klik "Enkripsi & Simpan"

### 3. Dekripsi Teks

- Pilih menu "Buka & Baca Catatan Lama"
- Pilih catatan yang ingin dibuka
- Masukkan kunci dekripsi
- Klik "Dekripsi"

### 4. Enkripsi File

- Pilih menu "Amankan File Penting"
- Upload file yang ingin dienkripsi
- Masukkan password untuk enkripsi
- File terenkripsi akan disimpan dengan ekstensi `.enc`

### 5. Dekripsi File

- Pilih menu "Buka File Terkunci"
- Upload file dengan ekstensi `.enc`
- Masukkan password yang benar
- File akan didekripsi dan dapat diunduh

### 6. Steganografi

- **Sembunyikan Pesan**: Upload gambar, masukkan pesan rahasia, dan simpan gambar yang sudah berisi pesan
- **Ekstrak Pesan**: Upload gambar yang berisi pesan rahasia untuk mengekstrak pesan

## 📁 Struktur Folder

```
TugasAkhir_Kriptografi/
├── css/                    # File stylesheet
│   ├── dashboard.css
│   └── textED.css
├── database/              # File database SQL
│   └── ta_kripto.sql
├── menu/                  # Halaman menu aplikasi
│   ├── text-encryption.php
│   ├── text-decryption.php
│   ├── file-encryption.php
│   ├── file-decryption.php
│   ├── stegano-encryption.php
│   └── stegano-decryption.php
├── php/                   # File PHP backend
│   ├── koneksi.php
│   ├── cek_login.php
│   ├── proses_regist.php
│   ├── simpan_textEnkrip.php
│   └── logout.php
├── uploads/              # Folder untuk file upload
│   └── files/
│       ├── file_encrypted/
│       └── file_decrypted/
├── vendor/               # Dependencies Composer
├── index.php             # Halaman login
├── register.php          # Halaman registrasi
├── dashboard.php         # Halaman dashboard
├── composer.json         # Dependencies PHP
└── README.md            # Dokumentasi proyek
```

## 🔒 Keamanan

- Password disimpan menggunakan hash MD5 (disarankan untuk upgrade ke bcrypt/argon2)
- Session management untuk autentikasi
- Prepared statements untuk mencegah SQL injection
- Input validation dan sanitization

## ⚠️ Catatan Penting

- **Backup Kunci**: Pastikan untuk menyimpan kunci enkripsi dengan aman. Jika kunci hilang, data tidak dapat didekripsi.
- **Password File**: Simpan password enkripsi file dengan baik. File yang terenkripsi tidak dapat dibuka tanpa password yang benar.
- **Database**: Lakukan backup database secara berkala untuk mencegah kehilangan data.

## 🐛 Troubleshooting

### Error: "Gagal terhubung ke database"

- Pastikan MySQL/MariaDB sudah berjalan
- Periksa konfigurasi di `php/koneksi.php`
- Pastikan database `TA_Kripto` sudah dibuat

### Error: "Class not found" atau "Composer autoload"

- Jalankan `composer install` untuk menginstall dependencies
- Pastikan folder `vendor/` sudah ada

### File tidak bisa diupload

- Periksa permission folder `uploads/files/`
- Periksa `upload_max_filesize` dan `post_max_size` di `php.ini`

## 📝 Lisensi

Proyek ini dibuat untuk keperluan Tugas Akhir Kriptografi.

## 👨‍💻 Developer

Dikembangkan sebagai bagian dari Tugas Akhir mata kuliah Kriptografi.

---

**Selamat menggunakan Brankas Pribadi! 🔐**
