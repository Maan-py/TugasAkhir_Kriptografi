<?php 
session_start(); 
include "koneksi.php";

mysqli_report(MYSQLI_REPORT_OFF);

function setErrorAndRedirect($pesan_error) {
    $_SESSION['error_register'] = $pesan_error;
    header("Location: ../register.php");
    exit(); 
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username_raw = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password_raw = isset($_POST['password']) ? trim($_POST['password']) : '';
    $konfirmasi_password_raw = isset($_POST['konfirmasi_password']) ? trim($_POST['konfirmasi_password']) : '';

    if (empty($username_raw)) {
        setErrorAndRedirect("Username tidak boleh kosong.");
    }
    if (empty($password_raw)) {
        setErrorAndRedirect("Password tidak boleh kosong.");
    }
    if (empty($konfirmasi_password_raw)) {
        setErrorAndRedirect("Konfirmasi Password tidak boleh kosong.");
    }

    if (strlen($username_raw) < 4) {
        setErrorAndRedirect("Username minimal harus 4 karakter.");
    }
    if (strlen($username_raw) > 256) {
        setErrorAndRedirect("Username maksimal 256 karakter.");
    }

    if (strlen($password_raw) < 8) {
        setErrorAndRedirect("Password minimal harus 8 karakter.");
    }
    if (strlen($password_raw) > 256) {
        setErrorAndRedirect("Password maksimal 256 karakter.");
    }

    if ($password_raw !== $konfirmasi_password_raw) {
        setErrorAndRedirect("Password dan Konfirmasi Password tidak cocok!");
    }

    $username = htmlspecialchars($username_raw);
    $password = htmlspecialchars($password_raw);
    $hashedPassword = md5($password);

    $sqlUser = "INSERT INTO users (username, password) VALUES (?, ?)";
    $stmtUser = $konek->prepare($sqlUser);
    
    if (!$stmtUser) {
        setErrorAndRedirect("Terjadi kesalahan saat menyiapkan query: " . $konek->error);
    }

    $stmtUser->bind_param("ss", $username, $hashedPassword);

    if ($stmtUser->execute()) {
        header("Location: ../index.php?pesan=sukses_regist"); 
        exit(); 
    } else {
        if ($stmtUser->errno == 1062) {
            setErrorAndRedirect("Username '" . htmlspecialchars($username) . "' sudah digunakan, silakan pilih username lain.");
        } else {
            setErrorAndRedirect("Registrasi gagal. Error database: " . $stmtUser->error);
        }
    }

    $stmtUser->close();
    $konek->close();
} else {
    setErrorAndRedirect("Akses tidak valid.");
}
?>