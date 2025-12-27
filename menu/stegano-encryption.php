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
$output_image_display = "";
$message = "";

function embedMessageInJpegMetadata($jpegPath, $message, $keyOrEmpty, $outputPath)
{
    $imageInfo = getimagesize($jpegPath);
    if ($imageInfo === false || $imageInfo[2] !== IMAGETYPE_JPEG) {
        return [false, 'Hanya JPEG yang didukung untuk EXIF/IPTC steganografi.'];
    }

    if (!empty($keyOrEmpty)) {
        $cipherText = openssl_encrypt($message, 'AES-128-ECB', $keyOrEmpty, OPENSSL_RAW_DATA);
        if ($cipherText === false) {
            return [false, 'Gagal mengenkripsi pesan.'];
        }
        // Prefix penanda agar dekripsi tahu bahwa konten terenkripsi
        $messageToStore = 'ENC:' . $cipherText;
    } else {
        $messageToStore = $message;
    }


    $iptcTag = chr(0x1C) . chr(2) . chr(120);
    $len = strlen($messageToStore);
    $iptcData = $iptcTag . chr($len >> 8) . chr($len & 0xFF) . $messageToStore;

    $newImageData = @iptcembed($iptcData, $jpegPath);
    if ($newImageData === false) {
        return [false, 'Gagal menyematkan metadata IPTC ke JPEG.'];
    }
    $ok = file_put_contents($outputPath, $newImageData) !== false;
    return [$ok, $ok ? '' : 'Gagal menulis file keluaran.'];
}

// Proses upload dan steganografi
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['encrypt'])) {
    $message = $_POST['message'];
    $keyInput = isset($_POST['key']) ? $_POST['key'] : '';

    if (empty($message)) {
        $error = "Silakan masukkan pesan yang akan disembunyikan!";
    } elseif (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $error = "Silakan pilih gambar yang valid!";
    } else {
        $uploadDir = "../uploads/stegano/img_encrypted/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $allowedTypes = ['image/jpeg', 'image/jpg'];
        $fileType = $_FILES['image']['type'];

        if (!in_array($fileType, $allowedTypes)) {
            $error = "Hanya file JPG yang didukung untuk EXIF/IPTC steganografi!";
        } else {
            // Gunakan temp file untuk proses
            $tempFile = sys_get_temp_dir() . '/' . uniqid() . '_' . basename($_FILES['image']['name']);
            
            // Generate token unik untuk file hasil
            $token = uniqid('stego_', true) . '_' . time() . '.jpg';
            $outputFile = $uploadDir . $token;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $tempFile)) {
                list($ok, $err) = embedMessageInJpegMetadata($tempFile, $message, $keyInput, $outputFile);
                if ($ok) {
                    $output_image = $token;
                    $output_image_display = 'stego_' . basename($_FILES['image']['name']);
                    $success = "Pesan berhasil disembunyikan ke dalam metadata JPEG!";
                } else {
                    $error = $err ?: "Gagal menyembunyikan pesan.";
                }
                if (file_exists($tempFile)) unlink($tempFile);
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
        .form-group input[type="text"],
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

                <h2 style="color: #e29fa6; margin-bottom: 1rem;">Steganografi - Menyembunyikan Pesan (EXIF/IPTC)</h2>
                <p style="color: #666; margin-bottom: 2rem;">Unggah file JPG dan masukkan pesan rahasia untuk disembunyikan dalam metadata.</p>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <?php if (!empty($output_image)): ?>
                    <div class="file-info" style="margin-bottom: 1rem;">
                        <strong>Gambar dengan Pesan Tersembunyi:</strong> <?php echo htmlspecialchars($output_image_display ?: $output_image); ?>
                    </div>
                    <div class="btn-group" style="margin-bottom: 1.5rem;">
                        <a href="../php/download.php?token=<?php echo urlencode($output_image); ?>&type=stegano&filename=<?php echo urlencode($output_image_display ?: $output_image); ?>" class="btn btn-download">Download Gambar Terenkripsi</a>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="image">Pilih Gambar (JPG):</label>
                        <input type="file" name="image" id="image" accept="image/jpeg,image/jpg" required>
                        <p class="info-text">EXIF/IPTC hanya tersedia pada JPEG. Gambar hasil akan berformat JPG.</p>
                    </div>

                    <div class="form-group">
                        <label for="message">Pesan Rahasia:</label>
                        <textarea name="message" id="message" placeholder="Masukkan pesan rahasia..." required><?php echo htmlspecialchars($message); ?></textarea>
                        <p class="info-text">Opsional: gunakan kunci untuk mengenkripsi pesan (AES-128-ECB). Metadata akan menyimpan ciphertext dengan penanda khusus.</p>
                    </div>

                    <div class="form-group">
                        <label for="key">Kunci Enkripsi (opsional):</label>
                        <input type="text" name="key" id="key" placeholder="Kosongkan jika tidak ingin dienkripsi">
                    </div>

                    <div class="btn-group">
                        <button type="submit" name="encrypt" class="btn btn-primary">Sembunyikan Pesan</button>
                        <a href="../dashboard.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>

</html>