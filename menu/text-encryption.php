<?php
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php?pesan=belum_login");
    exit();
}

include "../php/koneksi.php";

$encrypted_text = "";
$input_text = "";
$algorithm = "base64";
$error = "";
$success = "";

// Proses enkripsi
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['encrypt'])) {
    $input_text = $_POST['text'];
    $algorithm = $_POST['algorithm'];

    if (empty($input_text)) {
        $error = "Silakan masukkan teks yang akan dienkripsi!";
    } else {
        // Proses enkripsi berdasarkan algoritma
        switch ($algorithm) {
            case "base64":
                $encrypted_text = base64_encode($input_text);
                break;
            case "caesar":
                $shift = 3; // Caesar cipher dengan shift 3
                $encrypted_text = "";
                for ($i = 0; $i < strlen($input_text); $i++) {
                    $char = $input_text[$i];
                    if (ctype_alpha($char)) {
                        $ascii = ord($char);
                        $shifted = $ascii + $shift;
                        if (ctype_upper($char)) {
                            $shifted = (($shifted - 65) % 26) + 65;
                        } else {
                            $shifted = (($shifted - 97) % 26) + 97;
                        }
                        $encrypted_text .= chr($shifted);
                    } else {
                        $encrypted_text .= $char;
                    }
                }
                break;
            case "md5":
                $encrypted_text = md5($input_text);
                break;
            case "sha256":
                $encrypted_text = hash('sha256', $input_text);
                break;
            case "aes":
                // AES encryption menggunakan OpenSSL
                $key = "TaKripto2024Key!"; // Key untuk AES (harus 16, 24, atau 32 bytes)
                $cipher = "AES-128-CBC";
                $ivlen = openssl_cipher_iv_length($cipher);
                $iv = openssl_random_pseudo_bytes($ivlen);
                $encrypted = openssl_encrypt($input_text, $cipher, $key, 0, $iv);
                $encrypted_text = base64_encode($encrypted . '::' . $iv);
                break;
            default:
                $encrypted_text = base64_encode($input_text);
        }

        if (!empty($encrypted_text)) {
            $success = "Teks berhasil dienkripsi!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Text Encryption - Kriptografi</title>
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

        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #f3b7c0;
            border-radius: 5px;
            font-size: 1rem;
            font-family: inherit;
            outline: none;
        }

        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #e29fa6;
        }

        .form-group textarea {
            min-height: 150px;
            resize: vertical;
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
    </style>
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-content">
                <h1>Text Encryption</h1>
                <div class="user-info">
                    <span class="welcome-text">User: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
                    <a href="../php/logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </header>

        <main class="dashboard-main">
            <div class="encryption-container">
                <a href="../dashboard.php" class="back-link">← Kembali ke Dashboard</a>

                <h2 style="color: #e29fa6; margin-bottom: 1rem;">Enkripsi Teks</h2>
                <p style="color: #666; margin-bottom: 2rem;">Masukkan teks yang ingin Anda enkripsi dan pilih algoritma enkripsi</p>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="algorithm">Pilih Algoritma Enkripsi:</label>
                        <select name="algorithm" id="algorithm" required>
                            <option value="base64" <?php echo ($algorithm == "base64") ? "selected" : ""; ?>>Base64</option>
                            <option value="caesar" <?php echo ($algorithm == "caesar") ? "selected" : ""; ?>>Caesar Cipher</option>
                            <option value="md5" <?php echo ($algorithm == "md5") ? "selected" : ""; ?>>MD5 Hash</option>
                            <option value="sha256" <?php echo ($algorithm == "sha256") ? "selected" : ""; ?>>SHA-256 Hash</option>
                            <option value="aes" <?php echo ($algorithm == "aes") ? "selected" : ""; ?>>AES-128-CBC</option>
                        </select>
                        <p class="info-text">
                            <strong>Base64:</strong> Encoding sederhana |
                            <strong>Caesar:</strong> Shift cipher klasik |
                            <strong>MD5/SHA-256:</strong> One-way hash |
                            <strong>AES:</strong> Enkripsi simetris modern
                        </p>
                    </div>

                    <div class="form-group">
                        <label for="text">Masukkan Teks:</label>
                        <textarea name="text" id="text" placeholder="Masukkan teks yang akan dienkripsi..." required><?php echo htmlspecialchars($input_text); ?></textarea>
                    </div>

                    <div class="btn-group">
                        <button type="submit" name="encrypt" class="btn btn-primary">Enkripsi</button>
                        <a href="../dashboard.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>

                <?php if (!empty($encrypted_text)): ?>
                    <div class="result-group">
                        <label>Teks Terenkripsi:</label>
                        <div class="result-box" id="encrypted-result"><?php echo htmlspecialchars($encrypted_text); ?></div>
                        <button type="button" class="btn btn-copy" onclick="copyToClipboard()" style="margin-top: 1rem;">Salin Hasil</button>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function copyToClipboard() {
            const resultBox = document.getElementById('encrypted-result');
            const text = resultBox.textContent;

            navigator.clipboard.writeText(text).then(function() {
                alert('Teks berhasil disalin ke clipboard!');
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
                alert('Teks berhasil disalin ke clipboard!');
            });
        }
    </script>
</body>

</html>