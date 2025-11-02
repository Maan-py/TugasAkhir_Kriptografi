<?php
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['username'])) {
    header("Location: index.php?pesan=belum_login");
    exit();
}

include "php/koneksi.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard - Kriptografi</title>
    <link rel="stylesheet" href="css/dashboard.css">
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-content">
                <h1>Dashboard Kriptografi</h1>
                <div class="user-info">
                    <span class="welcome-text">Selamat datang, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
                    <a href="php/logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </header>

        <main class="dashboard-main">
            <?php
            // Tampilkan pesan notifikasi jika ada
            if (isset($_GET['pesan'])) {
                echo "<div class='alert alert-success'>";
                if ($_GET['pesan'] == "sukses_login") {
                    echo "Login berhasil! Selamat datang di dashboard.";
                }
                echo "</div>";
            }
            ?>

            <div class="dashboard-content">
                <h2>Pilih Layanan</h2>
                <p class="subtitle">Pilih jenis enkripsi/dekripsi yang ingin Anda gunakan</p>

                <div class="menu-grid">
                    <!-- Text Encryption/Decryption -->
                    <div class="menu-card">
                        <div class="card-icon">📝</div>
                        <h3>Text Encryption</h3>
                        <p>Enkripsi teks dengan algoritma kriptografi</p>
                        <a href="text-encryption.php" class="card-btn">Buka</a>
                    </div>

                    <div class="menu-card">
                        <div class="card-icon">📄</div>
                        <h3>Text Decryption</h3>
                        <p>Dekripsi teks terenkripsi</p>
                        <a href="text-decryption.php" class="card-btn">Buka</a>
                    </div>

                    <!-- Steganography Encryption/Decryption -->
                    <div class="menu-card">
                        <div class="card-icon">🖼️</div>
                        <h3>Stegano Encryption</h3>
                        <p>Sembunyikan pesan dalam gambar</p>
                        <a href="stegano-encryption.php" class="card-btn">Buka</a>
                    </div>

                    <div class="menu-card">
                        <div class="card-icon">🔍</div>
                        <h3>Stegano Decryption</h3>
                        <p>Ekstrak pesan dari gambar</p>
                        <a href="stegano-decryption.php" class="card-btn">Buka</a>
                    </div>

                    <!-- File Encryption/Decryption -->
                    <div class="menu-card">
                        <div class="card-icon">🔒</div>
                        <h3>File Encryption</h3>
                        <p>Enkripsi file dengan keamanan tinggi</p>
                        <a href="file-encryption.php" class="card-btn">Buka</a>
                    </div>

                    <div class="menu-card">
                        <div class="card-icon">🔓</div>
                        <h3>File Decryption</h3>
                        <p>Dekripsi file terenkripsi</p>
                        <a href="file-decryption.php" class="card-btn">Buka</a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>

</html>