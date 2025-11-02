<?php
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php?pesan=belum_login");
    exit();
}

include "../php/koneksi.php";

$decrypted_text = "";
$input_text = "";
$algorithm = "base64";
$error = "";
$success = "";

// Proses dekripsi
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['decrypt'])) {
    $input_text = $_POST['text'];
    $algorithm = $_POST['algorithm'];

    if (empty($input_text)) {
        $error = "Silakan masukkan teks yang akan didekripsi!";
    } else {
        // Proses dekripsi berdasarkan algoritma
        switch ($algorithm) {
            case "base64":
                $decoded = base64_decode($input_text, true);
                if ($decoded !== false) {
                    $decrypted_text = $decoded;
                } else {
                    $error = "Format Base64 tidak valid!";
                }
                break;
            case "caesar":
                $shift = 3; // Caesar cipher dengan shift 3 (kebalikan dari enkripsi)
                $decrypted_text = "";
                for ($i = 0; $i < strlen($input_text); $i++) {
                    $char = $input_text[$i];
                    if (ctype_alpha($char)) {
                        $ascii = ord($char);
                        $shifted = $ascii - $shift; // Shift ke kiri untuk dekripsi
                        if (ctype_upper($char)) {
                            if ($shifted < 65) {
                                $shifted = $shifted + 26;
                            }
                        } else {
                            if ($shifted < 97) {
                                $shifted = $shifted + 26;
                            }
                        }
                        $decrypted_text .= chr($shifted);
                    } else {
                        $decrypted_text .= $char;
                    }
                }
                break;
            case "md5":
                $error = "MD5 adalah one-way hash dan tidak dapat didekripsi. Hash digunakan untuk verifikasi, bukan untuk dekripsi.";
                break;
            case "sha256":
                $error = "SHA-256 adalah one-way hash dan tidak dapat didekripsi. Hash digunakan untuk verifikasi, bukan untuk dekripsi.";
                break;
            case "aes":
                // AES decryption menggunakan OpenSSL
                $key = "TaKripto2024Key!"; // Key untuk AES (harus sama dengan saat enkripsi)
                $cipher = "AES-128-CBC";
                try {
                    $data = base64_decode($input_text, true);
                    if ($data === false) {
                        $error = "Format enkripsi AES tidak valid!";
                        break;
                    }
                    $parts = explode('::', $data, 2);
                    if (count($parts) != 2) {
                        $error = "Format enkripsi AES tidak valid! Pastikan teks dienkripsi dengan AES-128-CBC.";
                        break;
                    }
                    $encrypted = $parts[0];
                    $iv = $parts[1];
                    $decrypted_text = openssl_decrypt($encrypted, $cipher, $key, 0, $iv);
                    if ($decrypted_text === false) {
                        $error = "Gagal mendekripsi! Pastikan teks dienkripsi dengan key yang sama.";
                    }
                } catch (Exception $e) {
                    $error = "Error saat dekripsi: " . $e->getMessage();
                }
                break;
            default:
                $decoded = base64_decode($input_text, true);
                if ($decoded !== false) {
                    $decrypted_text = $decoded;
                } else {
                    $error = "Format tidak valid!";
                }
        }

        if (!empty($decrypted_text) && empty($error)) {
            $success = "Teks berhasil didekripsi!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Text Decryption - Kriptografi</title>
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

        .warning-text {
            font-size: 0.85rem;
            color: #856404;
            margin-top: 0.5rem;
            padding: 0.75rem;
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-content">
                <h1>Text Decryption</h1>
                <div class="user-info">
                    <span class="welcome-text">User: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
                    <a href="../php/logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </header>

        <main class="dashboard-main">
            <div class="encryption-container">
                <a href="../dashboard.php" class="back-link">← Kembali ke Dashboard</a>

                <h2 style="color: #e29fa6; margin-bottom: 1rem;">Dekripsi Teks</h2>
                <p style="color: #666; margin-bottom: 2rem;">Masukkan teks terenkripsi yang ingin Anda dekripsi dan pilih algoritma yang sama dengan saat enkripsi</p>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="algorithm">Pilih Algoritma Dekripsi:</label>
                        <select name="algorithm" id="algorithm" required onchange="showWarning(this.value)">
                            <option value="base64" <?php echo ($algorithm == "base64") ? "selected" : ""; ?>>Base64</option>
                            <option value="caesar" <?php echo ($algorithm == "caesar") ? "selected" : ""; ?>>Caesar Cipher</option>
                            <option value="md5" <?php echo ($algorithm == "md5") ? "selected" : ""; ?>>MD5 Hash</option>
                            <option value="sha256" <?php echo ($algorithm == "sha256") ? "selected" : ""; ?>>SHA-256 Hash</option>
                            <option value="aes" <?php echo ($algorithm == "aes") ? "selected" : ""; ?>>AES-128-CBC</option>
                        </select>
                        <div id="hash-warning" style="display: none;" class="warning-text">
                            <strong>Perhatian:</strong> MD5 dan SHA-256 adalah one-way hash function dan tidak dapat didekripsi. Hash digunakan untuk verifikasi integritas data, bukan untuk dekripsi.
                        </div>
                        <p class="info-text">
                            <strong>Base64:</strong> Decode dari Base64 |
                            <strong>Caesar:</strong> Shift cipher kembali |
                            <strong>MD5/SHA-256:</strong> Tidak dapat didekripsi (one-way) |
                            <strong>AES:</strong> Dekripsi simetris
                        </p>
                    </div>

                    <div class="form-group">
                        <label for="text">Masukkan Teks Terenkripsi:</label>
                        <textarea name="text" id="text" placeholder="Masukkan teks terenkripsi yang akan didekripsi..." required><?php echo htmlspecialchars($input_text); ?></textarea>
                    </div>

                    <div class="btn-group">
                        <button type="submit" name="decrypt" class="btn btn-primary">Dekripsi</button>
                        <a href="../dashboard.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>

                <?php if (!empty($decrypted_text)): ?>
                    <div class="result-group">
                        <label>Teks Terdekripsi:</label>
                        <div class="result-box" id="decrypted-result"><?php echo htmlspecialchars($decrypted_text); ?></div>
                        <button type="button" class="btn btn-copy" onclick="copyToClipboard()" style="margin-top: 1rem;">Salin Hasil</button>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function showWarning(value) {
            const warning = document.getElementById('hash-warning');
            if (value === 'md5' || value === 'sha256') {
                warning.style.display = 'block';
            } else {
                warning.style.display = 'none';
            }
        }

        // Tampilkan warning saat halaman dimuat jika algoritma hash dipilih
        window.onload = function() {
            const algorithm = document.getElementById('algorithm');
            showWarning(algorithm.value);
        };

        function copyToClipboard() {
            const resultBox = document.getElementById('decrypted-result');
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