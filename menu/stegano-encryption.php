<?php
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php?pesan=belum_login");
    exit();
}

include "../php/koneksi.php";

$error = "";
$success = "";
$output_image = "";
$message = "";

// Fungsi untuk menyembunyikan pesan dalam gambar menggunakan LSB
function hideMessageInImage($imagePath, $message, $outputPath)
{
    // Baca gambar
    $imageInfo = getimagesize($imagePath);
    if ($imageInfo === false) {
        return false;
    }

    $imageType = $imageInfo[2];

    // Buat image resource berdasarkan tipe
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($imagePath);
            break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($imagePath);
            break;
        case IMAGETYPE_GIF:
            $image = imagecreatefromgif($imagePath);
            break;
        default:
            return false;
    }

    if ($image === false) {
        return false;
    }

    $width = imagesx($image);
    $height = imagesy($image);

    // Tambahkan delimiter di akhir pesan
    $message .= "|||END|||";
    $messageLength = strlen($message);

    // Cek apakah gambar cukup besar untuk menyimpan pesan
    $maxMessageLength = ($width * $height * 3) / 8; // 3 bits per pixel (RGB)
    if ($messageLength > $maxMessageLength) {
        imagedestroy($image);
        return false;
    }

    $messageIndex = 0;
    $bitIndex = 0;
    $char = ord($message[0]);

    // Loop melalui setiap pixel
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $rgb = imagecolorat($image, $x, $y);

            // Ekstrak RGB
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;

            // Embed bit ke LSB dari setiap channel
            if ($messageIndex < $messageLength) {
                // R channel
                if ($bitIndex < 8) {
                    $bit = ($char >> $bitIndex) & 1;
                    $r = ($r & 0xFE) | $bit;
                    $bitIndex++;

                    if ($bitIndex >= 8) {
                        $bitIndex = 0;
                        $messageIndex++;
                        if ($messageIndex < $messageLength) {
                            $char = ord($message[$messageIndex]);
                        }
                    }
                }

                // G channel
                if ($bitIndex < 8 && $messageIndex < $messageLength) {
                    $bit = ($char >> $bitIndex) & 1;
                    $g = ($g & 0xFE) | $bit;
                    $bitIndex++;

                    if ($bitIndex >= 8) {
                        $bitIndex = 0;
                        $messageIndex++;
                        if ($messageIndex < $messageLength) {
                            $char = ord($message[$messageIndex]);
                        }
                    }
                }

                // B channel
                if ($bitIndex < 8 && $messageIndex < $messageLength) {
                    $bit = ($char >> $bitIndex) & 1;
                    $b = ($b & 0xFE) | $bit;
                    $bitIndex++;

                    if ($bitIndex >= 8) {
                        $bitIndex = 0;
                        $messageIndex++;
                        if ($messageIndex < $messageLength) {
                            $char = ord($message[$messageIndex]);
                        }
                    }
                }
            }

            // Set warna baru
            $newColor = imagecolorallocate($image, $r, $g, $b);
            imagesetpixel($image, $x, $y, $newColor);
        }
    }

    // Simpan gambar
    $result = false;
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($image, $outputPath, 90);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($image, $outputPath);
            break;
        case IMAGETYPE_GIF:
            $result = imagegif($image, $outputPath);
            break;
    }

    imagedestroy($image);
    return $result;
}

// Proses upload dan steganografi
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['encrypt'])) {
    $message = $_POST['message'];

    if (empty($message)) {
        $error = "Silakan masukkan pesan yang akan disembunyikan!";
    } elseif (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $error = "Silakan pilih gambar yang valid!";
    } else {
        $uploadDir = "../uploads/stegano/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $fileType = $_FILES['image']['type'];

        if (!in_array($fileType, $allowedTypes)) {
            $error = "Format gambar tidak didukung! Hanya JPEG, PNG, dan GIF yang diperbolehkan.";
        } else {
            $uploadFile = $uploadDir . uniqid() . '_' . basename($_FILES['image']['name']);
            $outputFile = $uploadDir . 'stego_' . uniqid() . '.png';

            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
                if (hideMessageInImage($uploadFile, $message, $outputFile)) {
                    $output_image = basename($outputFile);
                    $success = "Pesan berhasil disembunyikan dalam gambar!";
                    // Hapus file upload original
                    unlink($uploadFile);
                } else {
                    $error = "Gagal menyembunyikan pesan! Pastikan gambar cukup besar untuk menyimpan pesan.";
                    unlink($uploadFile);
                }
            } else {
                $error = "Gagal mengunggah gambar!";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Stegano Encryption - Kriptografi</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
        .encryption-container {
            background-color: #fff;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            max-width: 900px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #b56576;
            font-weight: 600;
        }

        .form-group input[type="file"],
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #f3b7c0;
            border-radius: 5px;
            font-size: 1rem;
            font-family: inherit;
            outline: none;
        }

        .form-group input[type="file"]:focus,
        .form-group textarea:focus {
            border-color: #e29fa6;
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .btn {
            padding: 0.8rem 2rem;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background-color: #f28c98;
            color: #fff;
        }

        .btn-primary:hover {
            background-color: #e0717d;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: #fff;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        .btn-download {
            background-color: #28a745;
            color: #fff;
            padding: 0.6rem 1.5rem;
            font-size: 0.9rem;
        }

        .btn-download:hover {
            background-color: #218838;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #f5c6cb;
        }

        .back-link {
            margin-bottom: 1.5rem;
            display: inline-block;
            color: #e29fa6;
            text-decoration: none;
            font-weight: 500;
        }

        .back-link:hover {
            color: #b56576;
            text-decoration: underline;
        }

        .info-text {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.5rem;
            font-style: italic;
        }

        .result-group {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #f3b7c0;
        }

        .image-preview {
            max-width: 100%;
            max-height: 400px;
            border-radius: 5px;
            margin-top: 1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .file-info {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.5rem;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-content">
                <h1>Stegano Encryption</h1>
                <div class="user-info">
                    <span class="welcome-text">User: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
                    <a href="../php/logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </header>

        <main class="dashboard-main">
            <div class="encryption-container">
                <a href="../dashboard.php" class="back-link">← Kembali ke Dashboard</a>

                <h2 style="color: #e29fa6; margin-bottom: 1rem;">Steganografi - Menyembunyikan Pesan dalam Gambar</h2>
                <p style="color: #666; margin-bottom: 2rem;">Pilih gambar dan masukkan pesan rahasia yang akan disembunyikan menggunakan teknik LSB (Least Significant Bit)</p>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="image">Pilih Gambar (JPEG, PNG, atau GIF):</label>
                        <input type="file" name="image" id="image" accept="image/jpeg,image/jpg,image/png,image/gif" required>
                        <p class="info-text">Format yang didukung: JPEG, PNG, GIF. Gambar harus cukup besar untuk menyimpan pesan.</p>
                    </div>

                    <div class="form-group">
                        <label for="message">Masukkan Pesan yang Akan Disembunyikan:</label>
                        <textarea name="message" id="message" placeholder="Masukkan pesan rahasia..." required><?php echo htmlspecialchars($message); ?></textarea>
                        <p class="info-text">Pesan akan disembunyikan dalam gambar menggunakan teknik LSB. Semakin panjang pesan, semakin besar gambar yang diperlukan.</p>
                    </div>

                    <div class="btn-group">
                        <button type="submit" name="encrypt" class="btn btn-primary">Sembunyikan Pesan</button>
                        <a href="../dashboard.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>

                <?php if (!empty($output_image)): ?>
                    <div class="result-group">
                        <label>Gambar dengan Pesan Tersembunyi:</label>
                        <img src="../uploads/stegano/<?php echo htmlspecialchars($output_image); ?>" alt="Stego Image" class="image-preview">
                        <div class="file-info">File: <?php echo htmlspecialchars($output_image); ?></div>
                        <a href="../uploads/stegano/<?php echo htmlspecialchars($output_image); ?>" download class="btn btn-download" style="margin-top: 1rem;">Download Gambar</a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>

</html>