<?php
session_start();

include "koneksi.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $pass = md5($password); 

    // Cek username dan password di database
    $stmt = $konek->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if ($pass === $user['password']) {
            $_SESSION['username'] = $user['username'];
            echo '<div class="alert alert-success">Login berhasil!</div>';
            header("Location: ../dashboard.php?pesan=sukses_login");
        } else {
            echo '<div class="alert alert-danger">Login gagal!</div>';
            header("Location: ../index.php?pesan=gagal_login");
        }
    } else {
        echo '<div class="alert alert-danger">Username atau password salah!</div>';
        header("Location: ../index.php?pesan=gagal_login");
    }

    // Tutup statement dan konek
    $stmt->close();
    $konek->close();
}
