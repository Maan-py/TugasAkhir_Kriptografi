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
$all_metadata_html = "";

// Ekstrak pesan dari metadata JPEG (IPTC Caption 2:120 atau EXIF UserComment)
function extractMessageFromJpegMetadata($jpegPath, $keyOrEmpty)
{
    $info = [];
    $size = @getimagesize($jpegPath, $info);
    if ($size === false || $size[2] !== IMAGETYPE_JPEG) {
        return [false, 'File bukan JPEG atau rusak.'];
    }

    $rawMessage = '';
    if (isset($info['APP13'])) {
        $iptc = @iptcparse($info['APP13']);
        if ($iptc && isset($iptc['2#120'])) {
            $rawMessage = is_array($iptc['2#120']) ? implode('', $iptc['2#120']) : $iptc['2#120'];
        }
    }

    if ($rawMessage === '') {
        // fallback EXIF UserComment
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($jpegPath, 'COMMENT,EXIF,IFD0', true);
            if ($exif) {
                // UserComment bisa di EXIF['EXIF']['UserComment']
                if (isset($exif['EXIF']['UserComment'])) {
                    $val = $exif['EXIF']['UserComment'];
                    if (is_array($val)) $val = implode('', $val);
                    $rawMessage = $val;
                }
            }
        }
    }

    if ($rawMessage === '') {
        return [false, 'Tidak menemukan pesan pada metadata JPEG.'];
    }

    // Jika ada key, asumsikan data disimpan sebagai base64(ciphertext_AES-128-ECB)
    if (!empty($keyOrEmpty)) {
        $cipherB64 = $rawMessage;
        $cipher = base64_decode($cipherB64, true);
        if ($cipher === false) {
            return [false, 'Data metadata tidak valid (bukan base64 terenkripsi).'];
        }
        $plain = openssl_decrypt($cipher, 'AES-128-ECB', $keyOrEmpty);
        if ($plain === false) {
            return [false, 'Kunci salah atau data terenkripsi rusak.'];
        }
        return [true, $plain];
    }

    // Tanpa key: tampilkan apa adanya
    return [true, $rawMessage];
}

// Kumpulkan seluruh metadata JPEG (IPTC + EXIF) dan kembalikan sebagai string JSON terformat
function esc($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function formatMetadataHtmlRecursive($data)
{
    if (!is_array($data)) {
        return '<span>' . esc($data) . '</span>';
    }
    $html = '<ul style="margin:0; padding-left:1rem; list-style:disc;">';
    foreach ($data as $k => $v) {
        $key = esc($k);
        if (is_array($v)) {
            // Flatten single-element arrays with numeric keys (common in IPTC)
            if (count($v) === 1 && array_key_exists(0, $v) && !is_array($v[0])) {
                $html .= '<li><strong>' . $key . ':</strong> ' . esc($v[0]) . '</li>';
            } else {
                $html .= '<li><strong>' . $key . ':</strong> ' . formatMetadataHtmlRecursive($v) . '</li>';
            }
        } else {
            $html .= '<li><strong>' . $key . ':</strong> ' . esc($v) . '</li>';
        }
    }
    $html .= '</ul>';
    return $html;
}

function formatIptcHtmlRecursive($data)
{
    if (!is_array($data)) {
        return '<span>' . esc($data) . '</span>';
    }
    $html = '<ul style="margin:0; padding-left:1rem; list-style:disc;">';
    foreach ($data as $k => $v) {
        $key = esc($k);
        if ($k === '2#120') {
            $key = 'Pesan-Rahasia';
        }
        if (is_array($v)) {
            if (count($v) === 1 && array_key_exists(0, $v) && !is_array($v[0])) {
                $html .= '<li><strong>' . $key . ':</strong> ' . esc($v[0]) . '</li>';
            } else {
                $html .= '<li><strong>' . $key . ':</strong> ' . formatIptcHtmlRecursive($v) . '</li>';
            }
        } else {
            $html .= '<li><strong>' . $key . ':</strong> ' . esc($v) . '</li>';
        }
    }
    $html .= '</ul>';
    return $html;
}

function collectAllJpegMetadataHtml($jpegPath)
{
    $meta = [
        'basic' => null,
        'iptc' => null,
        'exif' => null,
    ];

    $basic = @getimagesize($jpegPath, $info);
    if ($basic !== false) {
        $meta['basic'] = [
            'width' => $basic[0],
            'height' => $basic[1],
            'mime' => isset($basic['mime']) ? $basic['mime'] : null,
        ];
        if (isset($info['APP13'])) {
            $iptc = @iptcparse($info['APP13']);
            if ($iptc !== false) {
                $meta['iptc'] = $iptc;
            }
        }
    }

    if (function_exists('exif_read_data')) {
        $exif = @exif_read_data($jpegPath, null, true);
        if ($exif !== false) {
            $meta['exif'] = $exif;
        }
    }

    $sections = '';
    foreach (['basic' => 'Informasi Dasar', 'iptc' => 'IPTC', 'exif' => 'EXIF'] as $key => $label) {
        if (!empty($meta[$key])) {
            $sections .= '<div style="margin-bottom:1rem;"><div style="font-weight:600;color:#b56576;margin-bottom:0.5rem;">' . esc($label) . '</div>' . ($key === 'iptc' ? formatIptcHtmlRecursive($meta[$key]) : formatMetadataHtmlRecursive($meta[$key])) . '</div>';
        }
    }
    if ($sections === '') {
        $sections = '<em>Tidak ada metadata yang ditemukan.</em>';
    }
    return $sections;
}

// Proses upload dan ekstraksi
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['decrypt'])) {
    $keyInput = isset($_POST['key']) ? $_POST['key'] : '';
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $error = "Silakan pilih gambar yang valid!";
    } else {
        $uploadDir = "../uploads/stegano/img_decrypted/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $allowedTypes = ['image/jpeg', 'image/jpg'];
        $fileType = $_FILES['image']['type'];

        if (!in_array($fileType, $allowedTypes)) {
            $error = "Hanya JPG yang didukung untuk ekstraksi metadata.";
        } else {
            $uploadFile = $uploadDir . uniqid() . '_' . basename($_FILES['image']['name']);

            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
                list($ok, $out) = extractMessageFromJpegMetadata($uploadFile, $keyInput);
                if ($ok) {
                    $extracted_message = $out;
                    $uploaded_image = basename($uploadFile);
                    $success = "Pesan berhasil diekstrak dari metadata JPEG!";
                    // Tampilkan metadata hanya jika dekripsi/ekstraksi berhasil
                    $all_metadata_html = collectAllJpegMetadataHtml($uploadFile);
                } else {
                    $error = $out ?: "Tidak ada pesan yang ditemukan atau format tidak valid.";
                    // Jangan tampilkan metadata ketika kunci salah/gagal
                    $all_metadata_html = "";
                }

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
            display: flex;
            flex-direction: column;
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

                <h2 style="color: #e29fa6; margin-bottom: 1rem;">Steganografi - Ekstraksi Pesan (EXIF/IPTC)</h2>
                <p style="color: #666; margin-bottom: 2rem;">Unggah JPG yang berisi pesan pada metadata (IPTC/EXIF). Jika disimpan dengan kunci, masukkan kunci yang sama untuk mendekripsi.</p>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="image">Pilih Gambar (JPG):</label>
                        <input type="file" name="image" id="image" accept="image/jpeg,image/jpg" required>
                        <p class="info-text">Hanya JPEG. Metadata dari platform tertentu bisa dihapus (mis. media sosial).</p>
                    </div>

                    <div class="form-group">
                        <label for="key">Kunci Dekripsi (opsional):</label>
                        <input type="text" name="key" id="key" placeholder="Isi jika pesan dienkripsi (AES-128-ECB)">
                    </div>

                    <div class="btn-group">
                        <button type="submit" name="decrypt" class="btn btn-primary">Ekstrak Pesan</button>
                        <a href="../dashboard.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>

                <?php if (!empty($extracted_message)): ?>
                    <div class="result-group">
                        <label>Pesan yang Diekstrak:</label>
                        <textarea style="resize: none;" class="result-box" id="extracted-result" readonly><?php echo $extracted_message; ?></textarea>
                        <button type="button" class="btn btn-copy" onclick="copyToClipboard()" style="margin-top: 1rem;">Salin Pesan</button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($all_metadata_html)): ?>
                    <div class="result-group">
                        <label>Metadata Gambar (IPTC/EXIF):</label>
                        <div class="result-box" style="max-height: 400px; overflow: auto;">
                            <?php echo $all_metadata_html; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function copyToClipboard() {
            const resultBox = document.getElementById('extracted-result');
            const text = resultBox.tagName === 'TEXTAREA' ? resultBox.value : resultBox.textContent;

            navigator.clipboard.writeText(text).then(function() {
                alert('Pesan berhasil disalin ke clipboard!');
            }, function(err) {
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