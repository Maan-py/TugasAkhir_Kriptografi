<?php
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php?pesan=belum_login");
    exit();
}

// Koneksi dan Library
include "../php/koneksi.php";
require_once __DIR__ . '/../vendor/autoload.php'; // Panggil phpseclib
$username = $_SESSION['username'];

// Gunakan Class Kriptografi
use phpseclib3\Crypt\RC4;
use phpseclib3\Crypt\DES;

// --- FUNGSI VIGENERE DECRYPT ---
function vigenere_decrypt($ciphertext, $key) {
    $key = strtoupper($key);
    $key_len = strlen($key);
    $key_idx = 0;
    $plaintext = "";

    if ($key_len == 0) return $ciphertext; // Kunci kosong

    for ($i = 0; $i < strlen($ciphertext); $i++) {
        $char = $ciphertext[$i];
        
        if (ctype_alpha($char)) {
            $is_upper = ctype_upper($char);
            $char_ord = ord($char);
            $key_char = $key[$key_idx % $key_len];
            $key_ord = ord($key_char);
            
            $base = $is_upper ? 65 : 97;
            
            // Rumus Dekripsi Vigenere: (C - K + 26) mod 26
            $decrypted_ord = ($char_ord - $base - ($key_ord - 65) + 26) % 26 + $base;
            $plaintext .= chr($decrypted_ord);
            
            $key_idx++;
        } else {
            $plaintext .= $char;
        }
    }
    return $plaintext;
}
// --- AKHIR FUNGSI VIGENERE ---


// Inisialisasi variabel
$errors = [];
$post_data = []; // Untuk menyimpan kunci yang di-submit
$decrypted_results = []; // Untuk menyimpan hasil dekripsi
$data_list = []; // Untuk daftar data
$current_data = null; // Untuk data yang sedang dilihat detailnya
$data_id = null;

// Tentukan Tampilan (View)
// 1. 'list' (default): Menampilkan daftar semua data
// 2. 'detail': Menampilkan rincian 1 data + form kunci
$view = $_GET['view'] ?? 'list';


if ($view == 'list') {
    // TAMPILAN 1: Ambil DAFTAR data milik user
    $stmt_list = $konek->prepare("SELECT data_id, data_label, created_at FROM usersData WHERE username = ? ORDER BY created_at DESC");
    $stmt_list->bind_param("s", $username);
    $stmt_list->execute();
    $data_list = $stmt_list->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_list->close();

} elseif ($view == 'detail') {
    // TAMPILAN 2: Ambil DETAIL 1 data
    if (!isset($_GET['id'])) {
        $errors[] = "ID Data tidak ditemukan.";
        $view = 'list'; // Kembalikan ke list jika ID tidak ada
    } else {
        $data_id = (int)$_GET['id'];
        
        // Query keamanan: AMBIL HANYA data_id JIKA ITU MILIK user
        $stmt_detail = $konek->prepare("SELECT * FROM usersData WHERE data_id = ? AND username = ?");
        $stmt_detail->bind_param("is", $data_id, $username);
        $stmt_detail->execute();
        $current_data = $stmt_detail->get_result()->fetch_assoc();
        $stmt_detail->close();

        if (!$current_data) {
            $errors[] = "Data tidak ditemukan atau Anda tidak punya akses.";
            $view = 'list'; // Kembalikan ke list jika data tidak valid
        }
    }
}


// --- PROSES DEKRIPSI (SAAT FORM DI-SUBMIT) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['decrypt_data']) && $current_data) {
    
    $post_data = $_POST; // Simpan kunci yang dimasukkan

    // Cek jika tidak ada kunci yang dimasukkan
    if (empty($_POST['kunci_rc4_datadiri']) && (empty($_POST['kunci_vigenere']) || empty($_POST['kunci_des_pesan']))) {
        $errors[] = "Anda harus memasukkan setidaknya satu set kunci (RC4 atau Vigenere+DES) untuk memulai dekripsi.";
    }

    // --- PROSES BAGIAN A: DATA DIRI (RC4) ---
    if (!empty($_POST['kunci_rc4_datadiri'])) {
        if (!empty($current_data['enc_nama'])) {
            try {
                $rc4 = new RC4();
                $kunci_rc4 = $_POST['kunci_rc4_datadiri'];
                $rc4->setKey($kunci_rc4);
                
                // Urutan dekripsi HARUS SAMA PERSIS dengan enkripsi
                $decrypted_results['Data Diri'] = [
                    'Nama' => $rc4->decrypt(hex2bin($current_data['enc_nama'])),
                    'Telepon' => $rc4->decrypt(hex2bin($current_data['enc_telepon'])),
                    'Tempat Lahir' => $rc4->decrypt(hex2bin($current_data['enc_tempat_lahir'])),
                    'Tanggal Lahir' => $rc4->decrypt(hex2bin($current_data['enc_tanggal_lahir'])),
                    'Alamat' => $rc4->decrypt(hex2bin($current_data['enc_alamat'])),
                ];

            } catch (Exception $e) {
                $errors[] = "Gagal dekripsi Data Diri (RC4): Kunci salah atau data korup.";
            }
        } else {
            $errors[] = "Anda memasukkan kunci RC4, tapi data diri tidak ditemukan di set data ini.";
        }
    }

    // --- PROSES BAGIAN B: PESAN BEBAS (DES -> VIGENERE) ---
    if (!empty($_POST['kunci_vigenere']) && !empty($_POST['kunci_des_pesan'])) {
         if (!empty($current_data['enc_pesan_bebas'])) {
            try {
                // 1. Decode Hex
                $encrypted_raw_pesan = hex2bin($current_data['enc_pesan_bebas']);
                
                // 2. Dekripsi Lapis 1 (DES)
                $des = new DES('cbc');
                $kunci_des = $_POST['kunci_des_pesan'];
                
                // Turunkan Kunci 8-byte dan IV 8-byte (HARUS SAMA DENGAN ENKRIPSI)
                $key_hash = hash('md5', $kunci_des, true); // 16 bytes
                $des_key_8byte = substr($key_hash, 0, 8);
                $des_iv_8byte = substr($key_hash, 8, 8);

                $des->setKey($des_key_8byte);
                $des->setIV($des_iv_8byte);
                
                $vigenere_result = $des->decrypt($encrypted_raw_pesan);

                if ($vigenere_result === false) {
                    $errors[] = "Gagal mendekripsi Pesan Bebas (Lapis DES): Kunci DES salah atau data korup.";
                } else {
                    // 3. Dekripsi Lapis 2 (Vigenere)
                    $decrypted_plaintext = vigenere_decrypt($vigenere_result, $_POST['kunci_vigenere']);
                    $decrypted_results['Pesan Bebas'] = $decrypted_plaintext;
                }

            } catch (Exception $e) {
                $errors[] = "Gagal dekripsi Pesan Bebas: " . $e->getMessage();
            }
         } else {
             $errors[] = "Anda memasukkan kunci Vigenere+DES, tapi pesan bebas tidak ditemukan di set data ini.";
         }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Text Decryption (Ambil dari DB) - Kriptografi</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <!-- Panggil CSS Eksternal BARU -->
    <link rel="stylesheet" href="../css/textED.css">
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-content">
                <h1>Dekripsi Data Tersimpan</h1>
                <div class="user-info">
                    <span class="welcome-text">User: <strong><?php echo htmlspecialchars($username); ?></strong></span>
                    <a href="../php/logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </header>

        <main class="dashboard-main">
            <div class="encryption-container">
                
                <!-- Tampilkan link "Kembali" yang berbeda tergantung view -->
                <?php if ($view == 'list'): ?>
                    <a href="../dashboard.php" class="back-link">← Kembali ke Dashboard</a>
                <?php else: ?>
                    <a href="text-decryption.php" class="back-link">← Kembali ke Daftar Data</a>
                <?php endif; ?>


                <h2 style="color: #e29fa6; margin-bottom: 1rem;">Dekripsi Data dari Database</h2>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert-error">
                        <strong>Terjadi Kesalahan:</strong>
                        <ul>
                            <?php foreach ($errors as $err): ?>
                                <li><?php echo htmlspecialchars($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <!-- ======================================================= -->
                <!-- ============ TAMPILAN 1: DAFTAR DATA (LIST) ============ -->
                <!-- ======================================================= -->
                <?php if ($view == 'list'): ?>
                
                    <p style="color: #666; margin-bottom: 2rem;">Berikut adalah daftar data terenkripsi yang Anda simpan di database. Pilih data untuk dilihat rinciannya dan didekripsi.</p>

                    <?php if (empty($data_list)): ?>
                        <div class="alert-info">
                            Anda belum memiliki data terenkripsi yang tersimpan di database. Silakan gunakan menu "Enkripsi & Simpan Data" terlebih dahulu.
                        </div>
                    <?php else: ?>
                        <table class="data-list-table">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Label Data</th>
                                    <th>Tanggal Disimpan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data_list as $index => $item): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($item['data_label']); ?></td>
                                    <td><?php echo htmlspecialchars(date('d F Y H:i', strtotime($item['created_at']))); ?></td>
                                    <td>
                                        <a href="text-decryption.php?view=detail&id=<?php echo $item['data_id']; ?>" class="btn-detail">Lihat Detail & Dekripsi</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                
                
                <!-- ======================================================= -->
                <!-- ============ TAMPILAN 2: DETAIL & DEKRIPSI ============ -->
                <!-- ======================================================= -->
                <?php elseif ($view == 'detail' && $current_data): ?>
                
                    <p style="color: #666; margin-bottom: 2rem;">Anda sedang melihat data: <strong>"<?php echo htmlspecialchars($current_data['data_label']); ?>"</strong> (disimpan pada <?php echo htmlspecialchars(date('d F Y', strtotime($current_data['created_at']))); ?>).</p>

                    <!-- RINCIAN CIPHERTEXT (YANG KAMU MINTA) -->
                    <div class="cipher-detail-box">
                        <h3>Rincian Ciphertext</h3>
                        
                        <?php if(!empty($current_data['enc_nama'])): ?>
                            <div class="cipher-field">
                                <strong>Cipher - Nama:</strong>
                                <span><?php echo htmlspecialchars($current_data['enc_nama']); ?></span>
                            </div>
                            <div class="cipher-field">
                                <strong>Cipher - Telepon:</strong>
                                <span><?php echo htmlspecialchars($current_data['enc_telepon']); ?></span>
                            </div>
                            <div class="cipher-field">
                                <strong>Cipher - Tempat Lahir:</strong>
                                <span><?php echo htmlspecialchars($current_data['enc_tempat_lahir']); ?></span>
                            </div>
                            <div class="cipher-field">
                                <strong>Cipher - Tanggal Lahir:</strong>
                                <span><?php echo htmlspecialchars($current_data['enc_tanggal_lahir']); ?></span>
                            </div>
                            <div class="cipher-field">
                                <strong>Cipher - Alamat:</strong>
                                <span><?php echo htmlspecialchars($current_data['enc_alamat']); ?></span>
                            </div>
                        <?php else: ?>
                             <div class="alert-info" style="margin-bottom: 1rem;">Tidak ada Data Diri tersimpan di set data ini.</div>
                        <?php endif; ?>

                        <hr style="border:0; border-top: 1px solid #f3b7c0; margin: 1.5rem 0;">

                        <?php if(!empty($current_data['enc_pesan_bebas'])): ?>
                             <div class="cipher-field">
                                <strong>Cipher - Pesan Bebas (Vigenere+DES):</strong>
                                <span><?php echo htmlspecialchars($current_data['enc_pesan_bebas']); ?></span>
                            </div>
                        <?php else: ?>
                             <div class="alert-info" style="margin-bottom: 1rem;">Tidak ada Pesan Bebas tersimpan di set data ini.</div>
                        <?php endif; ?>
                    </div>

                    <!-- FORM KUNCI DEKRIPSI -->
                    <form method="POST" action="text-decryption.php?view=detail&id=<?php echo $data_id; ?>">
                        
                        <!-- GRUP KUNCI DATA DIRI -->
                        <fieldset>
                            <legend>Bagian A: Dekripsi Data Diri (RC4)</legend>
                            <p class="info-text" style="margin-bottom: 1rem;">Masukkan kunci RC4 untuk mendekripsi Data Diri di atas.</p>
                            
                            <div class="form-group">
                                <label for="kunci_rc4_datadiri">Kunci RC4 Data Diri:</label>
                                <input type="text" name="kunci_rc4_datadiri" id="kunci_rc4_datadiri" placeholder="Kunci rahasia untuk semua data diri" value="<?php echo htmlspecialchars($post_data['kunci_rc4_datadiri'] ?? ''); ?>">
                            </div>
                        </fieldset>

                        <!-- GRUP KUNCI PESAN BEBAS -->
                        <fieldset>
                            <legend>Bagian B: Dekripsi Pesan Bebas (Vigenere + DES)</legend>
                            <p class="info-text" style="margin-bottom: 1rem;">Masukkan 2 kunci untuk mendekripsi Pesan Bebas di atas.</p>
                            
                            <div class="form-group">
                                <label for="kunci_vigenere">Kunci Pesan - Vigenere:</label>
                                <input type="text" name="kunci_vigenere" id="kunci_vigenere" placeholder="Kunci untuk lapis 1 (Vigenere) Pesan Bebas" value="<?php echo htmlspecialchars($post_data['kunci_vigenere'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="kunci_des_pesan">Kunci Pesan - DES:</label>
                                <input type="text" name="kunci_des_pesan" id="kunci_des_pesan" placeholder="Kunci untuk lapis 2 (DES) Pesan Bebas" value="<?php echo htmlspecialchars($post_data['kunci_des_pesan'] ?? ''); ?>">
                            </div>
                        </fieldset>

                        <div class="btn-group">
                            <button type="submit" name="decrypt_data" class="btn btn-primary">Dekripsi Data Ini</button>
                        </div>
                    </form>

                    <!-- HASIL DEKRIPSI (JIKA SUKSES) -->
                    <?php if (!empty($decrypted_results)): ?>
                        <div class="result-group">
                            <h2 style="color: #e29fa6; margin-bottom: 1rem;">Hasil Dekripsi</h2>
                            
                            <!-- Hasil Bagian A: Data Diri -->
                            <?php if (isset($decrypted_results['Data Diri'])): 
                                $res_dd = $decrypted_results['Data Diri'];
                            ?>
                                <fieldset>
                                    <legend>Data Diri (RC4)</legend>
                                    <div class="result-box">
                                        <span class="result-box-field">Nama:</span> <?php echo htmlspecialchars($res_dd['Nama']); ?><br>
                                        <span class="result-box-field">Telepon:</span> <?php echo htmlspecialchars($res_dd['Telepon']); ?><br>
                                        <span class="result-box-field">Tempat Lahir:</span> <?php echo htmlspecialchars($res_dd['Tempat Lahir']); ?><br>
                                        <span class="result-box-field">Tanggal Lahir:</span> <?php echo htmlspecialchars($res_dd['Tanggal Lahir']); ?><br>
                                        <span class="result-box-field">Alamat:</span> <?php echo htmlspecialchars($res_dd['Alamat']); ?>
                                    </div>
                                </fieldset>
                            <?php endif; ?>

                            <!-- Hasil Bagian B: Pesan Bebas -->
                            <?php if (isset($decrypted_results['Pesan Bebas'])): 
                                $res_pb = $decrypted_results['Pesan Bebas'];
                            ?>
                                 <fieldset style="margin-top: 2rem;">
                                    <legend>Pesan Bebas (Vigenere + DES)</legend>
                                    <div class="result-box">
                                        <?php echo nl2br(htmlspecialchars($res_pb)); // nl2br agar baris baru di plaintext tampil ?>
                                    </div>
                                </fieldset>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>
                    
            </div>
        </main>
    </div>
</body>
</html>

