<?php
session_start();
include "koneksi.php";

// Nonaktifkan mode exception untuk mysqli (hanya alert saja muncul)
mysqli_report(MYSQLI_REPORT_OFF); // Menonaktifkan laporan error dalam bentuk exception

// Mengecek apakah form di-submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Mengambil data dari formulir
    $username = htmlspecialchars($_POST['username']);
    $password = htmlspecialchars($_POST['password']);
    
    // --- MODIFIKASI 1: Ambil data konfirmasi password ---
    $konfirmasi_password = htmlspecialchars($_POST['konfirmasi_password']);

    // --- Validasi panjang username DIHAPUS ---
    // if (strlen($username) < 6 || strlen($username) > 12) {
    //     echo '<div class="alert alert-danger">Username harus antara 6 dan 12 karakter.</div>';
    //     exit; // Hentikan eksekusi script jika ada error
    // }

    // --- MODIFIKASI 2: Tambahkan validasi kesamaan password (Tetap ada) ---
    if ($password !== $konfirmasi_password) {
        echo '<div class="alert alert-danger">Password dan Konfirmasi Password tidak cocok!</div>';
        exit; // Hentikan eksekusi script jika password tidak sama
    }
    // --- Akhir Modifikasi ---


    // Hash password (Hanya dijalankan SETELAH password terkonfirmasi cocok)
    $hashedPassword = md5($password);

    // Menyimpan data pengguna ke database
    $sqlUser = "INSERT INTO users (username, password) VALUES (?, ?)";
    $stmtUser = $konek->prepare($sqlUser); // Tetap pakai $konek
    $stmtUser->bind_param("ss", $username, $hashedPassword);

    // Eksekusi statement dan periksa kesalahan
    if ($stmtUser->execute()) {
        echo '<div class="alert alert-success">Registrasi berhasil!</div>';
        header("Location: ../index.php?pesan=sukses_regist");
        // Tambahkan kategori default untuk pengguna baru
        // $sqlCategory = "
        //     INSERT INTO categories (name, is_default, username)
        //     SELECT name, TRUE, ? FROM categories WHERE username IS NULL AND is_default = TRUE
        // ";
        // $stmtCategory = $konek->prepare($sqlCategory); // Tetap pakai $konek
        // $stmtCategory->bind_param("s", $username);
        // $stmtCategory->execute();
        // $stmtCategory->close(); // Tutup $stmtCategory setelah digunakan
    } else {
        // Memeriksa apakah kesalahan disebabkan oleh duplikasi username
        if ($stmtUser->errno == 1062) { // Error code untuk duplikasi
            echo '<div class="alert alert-danger">Username sudah digunakan, silakan pilih username lain.</div>';
        } else {
            echo '<div class="alert alert-danger">Error: ' . $stmtUser->error . '</div>';
        }
    }

    // Tutup statement dan koneksi
    $stmtUser->close(); // Tutup $stmtUser setelah digunakan
    $konek->close(); // Tetap pakai $konek
} else {
    echo '<div class="alert alert-danger">Tidak ada data yang dikirim.</div>';
}
?>