<?php
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['username'])) {
    header("HTTP/1.0 403 Forbidden");
    exit("Access denied");
}

if (!isset($_GET['token']) || !isset($_GET['type'])) {
    header("HTTP/1.0 400 Bad Request");
    exit("Invalid request");
}

$token = $_GET['token'];
$type = $_GET['type']; // 'encrypted', 'decrypted', 'stegano'

// Validasi token (alphanumeric, dash, underscore, dan titik untuk ekstensi)
if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $token)) {
    header("HTTP/1.0 400 Bad Request");
    exit("Invalid token");
}

// Tentukan path file berdasarkan type
$baseDir = __DIR__ . '/../uploads/';
$filePath = '';

switch ($type) {
    case 'encrypted':
        $filePath = $baseDir . 'files/file_encrypted/' . $token;
        break;
    case 'decrypted':
        $filePath = $baseDir . 'files/file_decrypted/' . $token;
        break;
    case 'stegano':
        $filePath = $baseDir . 'stegano/img_encrypted/' . $token;
        break;
    default:
        header("HTTP/1.0 400 Bad Request");
        exit("Invalid type");
}

// Cek apakah file ada
if (!file_exists($filePath)) {
    header("HTTP/1.0 404 Not Found");
    exit("File not found");
}

// Tentukan nama file untuk download
// Gunakan parameter filename jika ada, jika tidak gunakan fallback
if (isset($_GET['filename']) && !empty($_GET['filename'])) {
    $downloadName = basename($_GET['filename']);
    // Validasi nama file untuk keamanan (hanya alphanumeric, titik, dash, underscore)
    $downloadName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $downloadName);
} else {
    // Fallback: gunakan nama default berdasarkan type
    if ($type === 'encrypted') {
        $downloadName = 'encrypted_file.enc';
    } elseif ($type === 'decrypted') {
        // Deteksi tipe file dari konten
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        $extension = '';
        if ($mimeType === 'application/pdf') {
            $extension = '.pdf';
        } elseif (strpos($mimeType, 'image/') === 0) {
            $extension = '.' . substr($mimeType, 6);
        } elseif (strpos($mimeType, 'text/') === 0) {
            $extension = '.txt';
        } elseif ($mimeType === 'application/zip') {
            $extension = '.zip';
        } elseif ($mimeType === 'application/msword') {
            $extension = '.doc';
        } elseif ($mimeType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            $extension = '.docx';
        }
        $downloadName = 'decrypted_file' . $extension;
    } elseif ($type === 'stegano') {
        $downloadName = 'stego_image.jpg';
    } else {
        $downloadName = basename($filePath);
    }
}

// Set header untuk download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Baca dan kirim file
$handle = fopen($filePath, 'rb');
if ($handle === false) {
    header("HTTP/1.0 500 Internal Server Error");
    exit("Error reading file");
}

// Stream file ke output
while (!feof($handle)) {
    echo fread($handle, 8192);
    flush();
}
fclose($handle);

// Hapus file setelah download selesai
@unlink($filePath);

exit;
