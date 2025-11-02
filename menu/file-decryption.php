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
$decrypted_file = "";
$original_filename = "";

// Fungsi untuk mendekripsi file menggunakan RC2-CBC
function decryptFile($filePath, $outputPath, $password)
{
    $cipher = "RC2-CBC";
    $key = hash('sha256', $password, true);
    $ivlen = openssl_cipher_iv_length($cipher);

    // Baca file terenkripsi
    $encryptedDataBase64 = file_get_contents($filePath);
    if ($encryptedDataBase64 === false) {
        return false;
    }

    // Decode dari base64
    $encryptedData = base64_decode($encryptedDataBase64, true);
    if ($encryptedData === false) {
        return false;
    }

    // Ekstrak IV (8 byte pertama untuk RC2)
    $iv = substr($encryptedData, 0, $ivlen);
    $encrypted = substr($encryptedData, $ivlen);

    // Dekripsi
    $decrypted = openssl_decrypt($encrypted, $cipher, $key, OPENSSL_RAW_DATA, $iv);
    if ($decrypted === false) {
        return false;
    }

    // Simpan file terdekripsi
    $result = file_put_contents($outputPath, $decrypted);

    return $result !== false;
}

// Proses upload dan dekripsi
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['decrypt'])) {
    $password = $_POST['password'];

    if (empty($password)) {
        $error = "Silakan masukkan password untuk dekripsi!";
    } elseif (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error = "Silakan pilih file terenkripsi yang valid!";
    } else {
        $uploadDir = "../uploads/files/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $original_filename = basename($_FILES['file']['name']);

        // Cek apakah file adalah .enc file
        if (!preg_match('/\.enc$/', $original_filename)) {
            $error = "File harus berformat .enc (file terenkripsi)!";
        } else {
            // Ekstrak nama file original dari nama file terenkripsi
            $fileParts = explode('_encrypted.', $original_filename);
            if (count($fileParts) > 1) {
                $extPart = explode('.enc', $fileParts[1])[0];
                $decryptedFileName = $fileParts[0] . '_decrypted.' . $extPart;
            } else {
                // Jika format tidak sesuai, gunakan nama default
                $decryptedFileName = 'decrypted_' . time() . '.file';
            }

            $uploadFile = $uploadDir . uniqid() . '_' . $original_filename;
            $outputFile = $uploadDir . $decryptedFileName;

            if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadFile)) {
                if (decryptFile($uploadFile, $outputFile, $password)) {
                    $decrypted_file = $decryptedFileName;
                    $success = "File berhasil didekripsi! Silakan download file terdekripsi.";
                    // Hapus file upload terenkripsi
                    unlink($uploadFile);
                } else {
                    $error = "Gagal mendekripsi file! Password salah atau file korup/tidak valid.";
                    unlink($uploadFile);
                }
            } else {
                $error = "Gagal mengunggah file!";
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
    <title>File Decryption - Kriptografi</title>
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
        .form-group input[type="password"] {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #f3b7c0;
            border-radius: 5px;
            font-size: 1rem;
            font-family: inherit;
            outline: none;
        }

        .form-group input[type="file"]:focus,
        .form-group input[type="password"]:focus {
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
            background-color: #d1ecf1;
            color: #0c5460;
            padding: 1rem;
            border-radius: 5px;
            border-left: 4px solid #17a2b8;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-content">
                <h1>File Decryption</h1>
                <div class="user-info">
                    <span class="welcome-text">User: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
                    <a href="../php/logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </header>

        <main class="dashboard-main">
            <div class="encryption-container">
                <a href="../dashboard.php" class="back-link">← Kembali ke Dashboard</a>

                <h2 style="color: #e29fa6; margin-bottom: 1rem;">Dekripsi File</h2>
                <p style="color: #666; margin-bottom: 2rem;">Upload file terenkripsi (.enc) dan masukkan password yang sama saat enkripsi untuk mendekripsi file</p>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="file">Pilih File Terenkripsi (.enc):</label>
                        <input type="file" name="file" id="file" accept=".enc" required>
                        <p class="info-text">File harus berformat .enc yang dihasilkan dari proses enkripsi. Gunakan password yang sama dengan saat enkripsi.</p>
                    </div>

                    <div class="form-group">
                        <label for="password">Password Dekripsi:</label>
                        <input type="password" name="password" id="password" placeholder="Masukkan password yang digunakan saat enkripsi" required>
                        <p class="info-text">Password harus sama persis dengan password yang digunakan saat mengenkripsi file.</p>
                    </div>

                    <div class="password-warning">
                        <strong>ℹ️ Informasi:</strong> Jika password salah, file tidak dapat didekripsi. Pastikan password yang Anda masukkan benar.
                    </div>

                    <div class="btn-group">
                        <button type="submit" name="decrypt" class="btn btn-primary">Dekripsi File</button>
                        <a href="../dashboard.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>

                <?php if (!empty($decrypted_file)): ?>
                    <div class="result-group">
                        <label>File Terdekripsi:</label>
                        <div class="file-info">
                            <strong>Nama File:</strong> <?php echo htmlspecialchars($decrypted_file); ?><br>
                            <strong>File Terenkripsi:</strong> <?php echo htmlspecialchars($original_filename); ?>
                        </div>
                        <a href="../uploads/files/<?php echo htmlspecialchars($decrypted_file); ?>" download class="btn btn-download" style="margin-top: 1rem;">Download File Terdekripsi</a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>

</html>