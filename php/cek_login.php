<?php
session_start();

include "koneksi.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil data dari form
    $username = $_POST['username'];
    $password = $_POST['password'];

    $pass = md5($password); // password

    // Cek username dan password di database
    $stmt = $konek->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Verifikasi password
        if ($pass) {
            // Sukses login, simpan username ke session
            $_SESSION['username'] = $user['username'];
            echo '<div class="alert alert-success">Login berhasil!</div>';
            header("Location: ../dashboard.php?pesan=sukses_login");
            // echo json_encode(['status' => 'success']);
        } else {
            // Password salah
            echo '<div class="alert alert-danger">Login gagal!</div>';
            header("Location: ../login.php?pesan=gagal_login");
            // echo json_encode(['status' => 'error', 'message' => 'Password salah']);
        }
    } else {
        // Username tidak ditemukan
        echo json_encode(['status' => 'error', 'message' => 'Username tidak ditemukan']);
    }

    // Tutup statement dan konek
    $stmt->close();
    $konek->close();
}
