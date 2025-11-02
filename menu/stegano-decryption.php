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
$extracted_message = "";
$uploaded_image = "";

// Fungsi untuk mengekstrak pesan dari gambar menggunakan LSB
function extractMessageFromImage($imagePath)
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

    $message = "";
    $char = 0;
    $bitIndex = 0;

    // Loop melalui setiap pixel
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $rgb = imagecolorat($image, $x, $y);

            // Ekstrak RGB
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;

            // Ekstrak bit dari LSB setiap channel
            // R channel
            $bit = $r & 1;
            $char |= ($bit << $bitIndex);
            $bitIndex++;

            if ($bitIndex >= 8) {
                // Cek delimiter
                if ($char == 0) {
                    imagedestroy($image);
                    return $message;
                }

                // Cek delimiter "|||END|||"
                $message .= chr($char);
                if (substr($message, -9) === "|||END|||") {
                    $message = substr($message, 0, -9);
                    imagedestroy($image);
                    return $message;
                }

                $char = 0;
                $bitIndex = 0;
            }

            // G channel
            $bit = $g & 1;
            $char |= ($bit << $bitIndex);
            $bitIndex++;

            if ($bitIndex >= 8) {
                if ($char == 0) {
                    imagedestroy($image);
                    return $message;
                }

                $message .= chr($char);
                if (substr($message, -9) === "|||END|||") {
                    $message = substr($message, 0, -9);
                    imagedestroy($image);
                    return $message;
                }

                $char = 0;
                $bitIndex = 0;
            }

            // B channel
            $bit = $b & 1;
            $char |= ($bit << $bitIndex);
            $bitIndex++;

            if ($bitIndex >= 8) {
                if ($char == 0) {
                    imagedestroy($image);
                    return $message;
                }

                $message .= chr($char);
                if (substr($message, -9) === "|||END|||") {
                    $message = substr($message, 0, -9);
                    imagedestroy($image);
                    return $message;
                }

                $char = 0;
                $bitIndex = 0;
            }
        }
    }

    imagedestroy($image);
    return $message;
}

// Proses upload dan ekstraksi
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['decrypt'])) {
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
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

            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
                $extracted = extractMessageFromImage($uploadFile);

                if ($extracted !== false && !empty($extracted)) {
                    $extracted_message = $extracted;
                    $uploaded_image = basename($uploadFile);
                    $success = "Pesan berhasil diekstrak dari gambar!";
                } else {
                    $error = "Tidak ada pesan yang ditemukan dalam gambar ini atau format tidak valid!";
                }

                // Hapus file setelah diekstrak
                unlink($uploadFile);
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
    <title>Stegano Decryption - Kriptografi</title>
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

        .form-group input[type="file"] {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #f3b7c0;
            border-radius: 5px;
            font-size: 1rem;
            font-family: inherit;
            outline: none;
        }

        .form-group input[type="file"]:focus {
            border-color: #e29fa6;
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

        .btn-copy {
            background-color: #17a2b8;
            color: #fff;
            padding: 0.6rem 1.5rem;
            font-size: 0.9rem;
        }

        .btn-copy:hover {
            background-color: #138496;
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

        .result-box {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            border: 1px solid #e9ecef;
            word-wrap: break-word;
            min-height: 100px;
            font-family: 'Courier New', monospace;
            white-space: pre-wrap;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-content">
                <h1>Stegano Decryption</h1>
                <div class="user-info">
                    <span class="welcome-text">User: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
                    <a href="../php/logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </header>

        <main class="dashboard-main">
            <div class="encryption-container">
                <a href="../dashboard.php" class="back-link">← Kembali ke Dashboard</a>

                <h2 style="color: #e29fa6; margin-bottom: 1rem;">Steganografi - Mengekstrak Pesan dari Gambar</h2>
                <p style="color: #666; margin-bottom: 2rem;">Unggah gambar yang mengandung pesan tersembunyi untuk mengekstrak pesan menggunakan teknik LSB (Least Significant Bit)</p>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="image">Pilih Gambar yang Mengandung Pesan Tersembunyi:</label>
                        <input type="file" name="image" id="image" accept="image/jpeg,image/jpg,image/png,image/gif" required>
                        <p class="info-text">Format yang didukung: JPEG, PNG, GIF. Pastikan gambar dibuat menggunakan steganografi encryption.</p>
                    </div>

                    <div class="btn-group">
                        <button type="submit" name="decrypt" class="btn btn-primary">Ekstrak Pesan</button>
                        <a href="../dashboard.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>

                <?php if (!empty($extracted_message)): ?>
                    <div class="result-group">
                        <label>Pesan yang Diekstrak:</label>
                        <div class="result-box" id="extracted-result"><?php echo htmlspecialchars($extracted_message); ?></div>
                        <button type="button" class="btn btn-copy" onclick="copyToClipboard()" style="margin-top: 1rem;">Salin Pesan</button>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function copyToClipboard() {
            const resultBox = document.getElementById('extracted-result');
            const text = resultBox.textContent;

            navigator.clipboard.writeText(text).then(function() {
                alert('Pesan berhasil disalin ke clipboard!');
            }, function(err) {
                // Fallback untuk browser yang tidak support clipboard API
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                textArea.remove();
                alert('Pesan berhasil disalin ke clipboard!');
            });
        }
    </script>
</body>

</html>