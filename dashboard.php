<?php
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['username'])) {
    header("Location: index.php?pesan=belum_login");
    exit();
}

include "php/koneksi.php";

// 1. LOGIKA SAPAAN WAKTU
// Atur zona waktu ke Asia/Jakarta
date_default_timezone_set('Asia/Jakarta');
$jam = (int)date('H');
$sapaan = "Selamat Datang"; // Default

if ($jam >= 4 && $jam < 11) {
    $sapaan = "Selamat Pagi ☀️";
} elseif ($jam >= 11 && $jam < 15) {
    $sapaan = "Selamat Siang ☀️";
} elseif ($jam >= 15 && $jam < 19) {
    $sapaan = "Selamat Sore 🌇";
} elseif ($jam >= 19 || $jam < 4) {
    $sapaan = "Selamat Malam 🌙";
}

// 2. LOGIKA DATA RINGKASAN & AKTIVITAS TERBARU
$username = $_SESSION['username'];
$total_catatan = 0;
$aktivitas_terbaru = "Belum ada aktivitas. Coba tulis catatan pertamamu!";

// Query untuk menghitung total catatan
$stmt_total = $konek->prepare("SELECT COUNT(*) FROM usersData WHERE username = ?");
$stmt_total->bind_param("s", $username);
$stmt_total->execute();
$stmt_total->bind_result($total_catatan);
$stmt_total->fetch();
$stmt_total->close();

// Query untuk mengambil aktivitas terbaru (1 data terakhir)
$stmt_recent = $konek->prepare("SELECT data_label, created_at FROM usersData WHERE username = ? ORDER BY created_at DESC LIMIT 1");
$stmt_recent->bind_param("s", $username);
$stmt_recent->execute();
$result_recent = $stmt_recent->get_result();

if ($result_recent->num_rows > 0) {
    $row_recent = $result_recent->fetch_assoc();
    // Panggil fungsi time_ago (didefinisikan di bawah)
    $aktivitas_terbaru = "Kamu menyimpan '" . htmlspecialchars($row_recent['data_label']) . "' (" . time_ago($row_recent['created_at']) . ")";
}
$stmt_recent->close();
$konek->close();

/**
 * Fungsi helper untuk mengubah timestamp menjadi format "time ago"
 * (Misal: "5 menit lalu", "2 jam lalu")
 */
function time_ago($timestamp)
{
    $waktu_lalu = new DateTime($timestamp);
    $waktu_sekarang = new DateTime("now");
    $selisih = $waktu_sekarang->diff($waktu_lalu);

    if ($selisih->y > 0) return $selisih->y . " tahun lalu";
    if ($selisih->m > 0) return $selisih->m . " bulan lalu";
    if ($selisih->d > 0) return $selisih->d . " hari lalu";
    if ($selisih->h > 0) return $selisih->h . " jam lalu";
    if ($selisih->i > 0) return $selisih->i . " menit lalu";
    return "Baru saja";
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard - Brankas Pribadi</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-content">
                <div class="header-left">
                    <h1>Brankas Catatan Pribadimu</h1>
                    <span class="welcome-text">
                        <?php echo $sapaan; ?>, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                    </span>
                </div>


                <div class="header-right">
                    <a href="php/logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </header>

        <main class="dashboard-main">

            <div class="summary-row">
                <div class="summary-card">
                    <i class="fa-solid fa-lock"></i>
                    <div class="summary-text">
                        <h3>Catatan Terkunci</h3>
                        <p><?php echo $total_catatan; ?> data tersimpan aman</p>
                    </div>
                </div>
                <div class="activity-card">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <div class="summary-text">
                        <h3>Aktivitas Terakhir</h3>
                        <p><?php echo $aktivitas_terbaru; ?></p>
                    </div>
                </div>
            </div>

            <div class="module-group">
                <h2>Brankas Catatan Pribadi</h2>
                <div class="menu-grid">
                    <div class="menu-card">
                        <div class="card-icon"><i class="fa-solid fa-pencil"></i></div>
                        <h3>Tulis & Kunci Catatan Baru</h3>
                        <p>Simpan data diri atau pesan rahasia baru ke brankas</p>
                        <a href="menu/text-encryption.php" class="card-btn">Tulis Catatan</a>
                    </div>
                    <div class="menu-card">
                        <div class="card-icon"><i class="fa-solid fa-book-open"></i></div>
                        <h3>Buka & Baca Catatan Lama</h3>
                        <p>Lihat daftar catatan terkunci dan buka data rahasiamu</p>
                        <a href="menu/text-decryption.php" class="card-btn">Buka Brankas</a>
                    </div>
                </div>
            </div>

            <div class="module-group">
                <h2>Perkakas Keamanan File</h2>
                <div class="menu-grid">
                    <div class="menu-card">
                        <div class="card-icon"><i class="fa-solid fa-file-shield"></i></div>
                        <h3>Amankan File Penting</h3>
                        <p>Enkripsi file penting (.pdf, .docx, .zip) dengan password</p>
                        <a href="menu/file-encryption.php" class="card-btn">Kunci File</a>
                    </div>
                    <div class="menu-card">
                        <div class="card-icon"><i class="fa-solid fa-file-export"></i></div>
                        <h3>Buka File Terkunci</h3>
                        <p>Dekripsi file .enc yang sudah kamu amankan sebelumnya</p>
                        <a href="menu/file-decryption.php" class="card-btn">Buka File</a>
                    </div>
                </div>
            </div>

            <div class="module-group">
                <h2>Pesan Rahasia Gambar</h2>
                <div class="menu-grid">
                    <div class="menu-card">
                        <div class="card-icon"><i class="fa-solid fa-image"></i></div>
                        <h3>Sembunyikan Pesan di Gambar</h3>
                        <p>Sisipkan pesan rahasia di dalam file gambar (Steganografi)</p>
                        <a href="menu/stegano-encryption.php" class="card-btn">Sembunyikan</a>
                    </div>
                    <div class="menu-card">
                        <div class="card-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
                        <h3>Periksa Pesan di Gambar</h3>
                        <p>Ekstrak dan baca pesan rahasia yang ada di dalam gambar</p>
                        <a href="menu/stegano-decryption.php" class="card-btn">Periksa Gambar</a>
                    </div>
                </div>
            </div>

        </main>
    </div>
    <script>
        document.getElementById("toggle-theme").addEventListener("click", () => {
            document.body.classList.toggle("dark-mode");
            const icon = document.querySelector("#toggle-theme i");
            icon.classList.toggle("fa-moon");
            icon.classList.toggle("fa-sun");
        });
    </script>

</body>

</html>