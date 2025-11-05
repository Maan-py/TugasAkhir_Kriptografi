<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../index.php?pesan=belum_login");
    exit();
}

include "../php/koneksi.php";

$error = "";
$success = "";
$encrypted_file = "";
$original_filename = "";

$lastCryptoError = ""; 

// Fungsi untuk mengenkripsi file menggunakan RC2-CBC
function encryptFile($filePath, $outputPath, $password)
{
    global $lastCryptoError;

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }

    $key = md5($password, true);
    $iv = random_bytes(8);

    $fileData = file_get_contents($filePath);
    if ($fileData === false) {
        $lastCryptoError = "Gagal membaca file sumber.";
        return false;
    }

    try {
        $magic = "RC2FILEv1"; 
        $hmac16 = substr(hash_hmac('sha256', $fileData, $key, true), 0, 16);
        $payload = $magic . $hmac16 . $fileData;

        if (class_exists('phpseclib3\\Crypt\\RC2')) {
            $rc2Class = '\\phpseclib3\\Crypt\\RC2';
            $rc2 = new $rc2Class('cbc');
            $rc2->setKey($key);
            $rc2->setIV($iv);
            $ciphertext = $rc2->encrypt($payload);
        } else {
            throw new RuntimeException('Library RC2 tidak tersedia. Instal phpseclib: composer require phpseclib/phpseclib:^3.0');
        }
    } catch (Throwable $e) {
        $lastCryptoError = $e->getMessage();
        return false;
    }

    $encryptedData = $iv . $ciphertext;
    $result = file_put_contents($outputPath, base64_encode($encryptedData));
    return $result !== false;
}

// Proses upload dan enkripsi
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['encrypt'])) {
    $password = $_POST['password'];

    if (empty($password)) {
        $error = "Silakan masukkan password untuk enkripsi!";
    } elseif (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error = "Silakan pilih file yang valid!";
    } else {
        $uploadDir = "../uploads/files/file_encrypted/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $original_filename = basename($_FILES['file']['name']);
        $fileExtension = pathinfo($original_filename, PATHINFO_EXTENSION);
        $fileBaseName = pathinfo($original_filename, PATHINFO_FILENAME);

        $uploadFile = $uploadDir . uniqid() . '_' . $original_filename;
        $encryptedFileName = $fileBaseName . '_encrypted.' . $fileExtension . '.enc';
        $outputFile = $uploadDir . $encryptedFileName;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadFile)) {
            if (encryptFile($uploadFile, $outputFile, $password)) {
                $encrypted_file = $encryptedFileName;
                $success = "File berhasil dienkripsi!";
                // Hapus file upload original
                unlink($uploadFile);
            } else {
                $error = "Gagal mengenkripsi file! " . ($lastCryptoError ? $lastCryptoError : "Pastikan file valid dan tidak korup.");
                unlink($uploadFile);
            }
        } else {
            $error = "Gagal mengunggah file!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>File Encryption - Kriptografi</title>
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
        .form-group input[type="text"] {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #f3b7c0;
            border-radius: 5px;
            font-size: 1rem;
            font-family: inherit;
            outline: none;
        }

        .form-group input[type="file"]:focus,
        .form-group input[type="text"]:focus {
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

        .file-info {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            border: 1px solid #e9ecef;
            margin-top: 1rem;
        }

        .file-info strong {
            color: #b56576;
        }

        .password-warning {
            background-color: #fff3cd;
            color: #856404;
            padding: 1rem;
            border-radius: 5px;
            border-left: 4px solid #ffc107;
            margin-top: 1rem;
            font-size: 0.9rem;
        }

        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.85rem;
        }

        .strength-weak {
            color: #dc3545;
        }

        .strength-medium {
            color: #ffc107;
        }

        .strength-strong {
            color: #28a745;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-content">
                <h1>File Encryption</h1>
                <div class="user-info">
                    <span class="welcome-text">User: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
                    <a href="../php/logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </header>

        <main class="dashboard-main">
            <div class="encryption-container">
                <a href="../dashboard.php" class="back-link">← Kembali ke Dashboard</a>

                <h2 style="color: #e29fa6; margin-bottom: 1rem;">Enkripsi File</h2>
                <p style="color: #666; margin-bottom: 2rem;">Upload file dan masukkan password untuk mengenkripsi file menggunakan RC2-CBC</p>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="file">Pilih File untuk Dienkripsi:</label>
                        <input type="file" name="file" id="file" required>
                        <p class="info-text">File akan dienkripsi menggunakan RC2-CBC. Maksimal ukuran file sesuai konfigurasi server.</p>
                    </div>

                    <div class="form-group">
                        <label for="password">Password Enkripsi:</label>
                        <input type="text" name="password" id="password" placeholder="Masukkan password untuk enkripsi" required onkeyup="checkPasswordStrength(this.value)">
                        <div id="password-strength" class="password-strength"></div>
                        <p class="info-text">Gunakan password yang kuat dan simpan dengan aman. Anda akan membutuhkan password ini untuk dekripsi!</p>
                    </div>

                    <div class="btn-group">
                        <button type="submit" name="encrypt" class="btn btn-primary">Enkripsi File</button>
                        <a href="../dashboard.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>

</html>