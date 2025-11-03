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
include_once "../php/simpan_textEnkrip.php"; // <-- MEMANGGIL FILE BARUMU

// Gunakan Class Kriptografi
use phpseclib3\Crypt\RC4;
use phpseclib3\Crypt\DES;

// --- FUNGSI VIGENERE ENCRYPT ---
function vigenere_encrypt($plaintext, $key) {
    // (Fungsi Vigenere lengkap ada di sini, sama seperti sebelumnya)
    $key = strtoupper($key);
    $key_len = strlen($key);
    $key_idx = 0;
    $ciphertext = "";
    if ($key_len == 0) return $plaintext;
    for ($i = 0; $i < strlen($plaintext); $i++) {
        $char = $plaintext[$i];
        if (ctype_alpha($char)) {
            $is_upper = ctype_upper($char);
            $char_ord = ord($char);
            $key_char = $key[$key_idx % $key_len];
            $key_ord = ord($key_char);
            $base = $is_upper ? 65 : 97;
            $encrypted_ord = ($char_ord - $base + ($key_ord - 65)) % 26 + $base;
            $ciphertext .= chr($encrypted_ord);
            $key_idx++;
        } else {
            $ciphertext .= $char;
        }
    }
    return $ciphertext;
}
// --- AKHIR FUNGSI VIGENERE ---


// Inisialisasi variabel
$errors = [];
$post_data = []; // Untuk menyimpan data form agar bisa diisi kembali
$results = []; // Untuk menyimpan semua hasil enkripsi & steps
$save_success = false;
$save_error = false;
$username = $_SESSION['username']; // Ambil username dari session

// Proses enkripsi
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['encrypt'])) {
    
    // Ambil semua data POST dan simpan
    $post_data = $_POST;

    // --- 1. VALIDASI DATA ---
    // (Semua logika validasi tetap di sini)
    if (empty($_POST['data_label'])) {
        $errors[] = "Label Data tidak boleh kosong. Ini untuk nama data Anda.";
    }
    if (empty($_POST['nama']) && empty($_POST['pesan_bebas'])) {
        $errors[] = "Data Diri atau Pesan Bebas harus diisi untuk dienkripsi.";
    }
    // (Validasi kunci... validasi data diri... dll... sama seperti sebelumnya)
    // ...
    // ... (Validasi lengkap ada di sini) ...
    // ...
    if (!empty($_POST['nama']) && empty($_POST['kunci_rc4_datadiri'])) $errors[] = "Kunci RC4 Data Diri tidak boleh kosong jika Data Diri diisi.";
    if (!empty($_POST['pesan_bebas'])) {
        if (empty($_POST['kunci_vigenere'])) $errors[] = "Kunci Vigenere Pesan tidak boleh kosong jika Pesan Bebas diisi.";
        if (empty($_POST['kunci_des_pesan'])) $errors[] = "Kunci DES Pesan tidak boleh kosong jika Pesan Bebas diisi.";
    }
    if (!empty($_POST['nama'])) {
        if (!preg_match('/^[a-zA-Z\s\'.\-]+$/', $_POST['nama'])) $errors[] = "Nama hanya boleh berisi huruf, spasi, titik, dan apostrof.";
        if (!preg_match('/^[0-9\+\-\s\(\)]+$/', $_POST['telepon'])) $errors[] = "Nomor Telepon hanya boleh berisi angka, spasi, dan simbol (+, -, (, )).";
        if (empty($_POST['tempat_lahir'])) $errors[] = "Tempat Lahir tidak boleh kosong.";
        if (!preg_match('/[a-zA-Z]/', $_POST['tempat_lahir'])) $errors[] = "Tempat Lahir harus berisi setidaknya satu huruf (tidak boleh hanya angka/simbol).";
        if (empty($_POST['tanggal_lahir'])) $errors[] = "Tanggal Lahir tidak boleh kosong.";
        if (empty($_POST['alamat'])) $errors[] = "Alamat tidak boleh kosong.";
    }


    // --- 2. PROSES ENKRIPSI (Jika tidak ada error) ---
    // (Logika enkripsi lengkap tetap di sini)
    if (empty($errors)) {
        
        // Siapkan "Nampan" data untuk disimpan
        $data_to_save = [
            'username' => $username,
            'data_label' => $_POST['data_label'],
            'enc_nama' => null,
            'enc_telepon' => null,
            'enc_tempat_lahir' => null,
            'enc_tanggal_lahir' => null,
            'enc_alamat' => null,
            'enc_pesan_bebas' => null
        ];

        // --- PROSES BAGIAN A: DATA DIRI (RC4) ---
        if (!empty($_POST['nama'])) {
            try {
                $rc4_datadiri = new RC4();
                $kunci_rc4 = $_POST['kunci_rc4_datadiri'];
                $rc4_datadiri->setKey($kunci_rc4);
                
                $fields_to_encrypt = [
                    'Nama' => $_POST['nama'],
                    'Telepon' => $_POST['telepon'],
                    'Tempat Lahir' => $_POST['tempat_lahir'],
                    'Tanggal Lahir' => $_POST['tanggal_lahir'],
                    'Alamat' => $_POST['alamat']
                ];
                
                $encrypted_fields_hex = [];
                foreach ($fields_to_encrypt as $key => $value) {
                    $encrypted_raw = $rc4_datadiri->encrypt($value);
                    $hex_value = bin2hex($encrypted_raw);
                    $encrypted_fields_hex[$key] = $hex_value;
                    
                    // Masukkan ke "Nampan" data
                    $db_col = 'enc_' . strtolower(str_replace(' ', '_', $key));
                    if (array_key_exists($db_col, $data_to_save)) {
                        $data_to_save[$db_col] = $hex_value;
                    }
                }
                
                $results['data_diri'] = [
                    'title' => 'Data Diri (RC4)',
                    'kunci' => $kunci_rc4,
                    'ciphertexts' => $encrypted_fields_hex,
                    'steps' => [
                        "<b>1. Penyiapan Kunci:</b> Kunci RC4 (yang Anda masukkan) digunakan untuk KSA (Key-Scheduling Algorithm).",
                        "<b>2. Enkripsi Field-by-Field:</b> Objek RC4 yang sama (dengan keystream berkelanjutan) digunakan untuk mengenkripsi 'Nama', lalu 'Telepon', 'Tempat Lahir', 'Tanggal Lahir', dan 'Alamat'.",
                        "<b>3. Finalisasi:</b> Setiap hasil biner di-encode ke Heksadesimal."
                    ]
                ];

            } catch (Exception $e) {
                $errors[] = "Gagal enkripsi Data Diri: " . $e->getMessage();
            }
        }
        
        // --- PROSES BAGIAN B: PESAN BEBAS (VIGENERE + DES) ---
        if (!empty($_POST['pesan_bebas'])) {
            try {
                $vigenere_result = vigenere_encrypt($_POST['pesan_bebas'], $_POST['kunci_vigenere']);
                
                $des = new DES('cbc');
                $kunci_des = $_POST['kunci_des_pesan'];
                $key_hash = hash('md5', $kunci_des, true);
                $des_key_8byte = substr($key_hash, 0, 8);
                $des_iv_8byte = substr($key_hash, 8, 8);
                $des->setKey($des_key_8byte);
                $des->setIV($des_iv_8byte);

                $encrypted_raw_pesan = $des->encrypt($vigenere_result);
                $encrypted_hex_pesan = bin2hex($encrypted_raw_pesan);
                
                // Masukkan ke "Nampan" data
                $data_to_save['enc_pesan_bebas'] = $encrypted_hex_pesan;

                $results['pesan_bebas'] = [
                    'title' => 'Pesan Bebas (Super Enkripsi: Vigenere + DES)',
                    'ciphertext' => $encrypted_hex_pesan,
                    'steps' => [
                        "<b>1. Input Plaintext:</b><br><span class='step-data'>" . htmlspecialchars($_POST['pesan_bebas']) . "</span>",
                        "<b>2. Enkripsi Lapis 1 (Vigenere):</b> Plaintext dienkripsi dengan Vigenere (Kunci: " . htmlspecialchars($_POST['kunci_vigenere']) . ").<br><b>Hasil Vigenere:</b> <span class='step-data'>" . htmlspecialchars($vigenere_result) . "</span>",
                        "<b>3. Penyiapan Kunci DES:</b> Kunci DES diturunkan menjadi Kunci 8-byte dan IV 8-byte (via MD5).",
                        "<b>4. Enkripsi Lapis 2 (DES-CBC):</b> Hasil Vigenere dienkripsi lagi dengan DES (Mode CBC).",
                        "<b>5. Finalisasi:</b> Hasil biner di-encode ke Heksadesimal."
                    ]
                ];

            } catch (Exception $e) {
                $errors[] = "Gagal enkripsi Pesan Bebas: " . $e->getMessage();
            }
        }

        // --- 3. PANGGIL "PELAYAN" UNTUK SIMPAN KE DB ---
        // (Logika SQL sudah dipindah ke file 'simpan_textEnkrip.php')
        if (!empty($results)) {
            
            // Panggil fungsi simpan dari file yang di-include
            $simpan_result = simpanDataEnkripsi($konek, $data_to_save);

            if ($simpan_result === true) {
                // Jika "Pelayan" lapor sukses
                $save_success = "Sukses! Data baru dengan label '" . htmlspecialchars($data_to_save['data_label']) . "' berhasil disimpan di database Anda.";
            } else {
                // Jika "Pelayan" lapor gagal, $simpan_result berisi pesan error
                $save_error = $simpan_result; 
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
    <title>Text Encryption (Simpan ke DB) - Kriptografi</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <!-- Panggil CSS Eksternal -->
    <link rel="stylesheet" href="../css/textED.css">
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-content">
                <h1>Enkripsi & Simpan Data</h1>
                <div class="user-info">
                    <span class="welcome-text">User: <strong><?php echo htmlspecialchars($username); ?></strong></span>
                    <a href="../php/logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </header>

        <main class="dashboard-main">
            <div class="encryption-container">
                <a href="../dashboard.php" class="back-link">← Kembali ke Dashboard</a>

                <h2 style="color: #e29fa6; margin-bottom: 1rem;">Enkripsi & Simpan Teks ke Database</h2>
                <p style="color: #666; margin-bottom: 2rem;">Masukkan data diri dan/atau pesan bebas. Data akan dienkripsi dan disimpan sebagai *data baru* di database Anda.</p>

                <!-- Tampilkan Error Validasi -->
                <?php if (!empty($errors)): ?>
                    <div class="alert-error">
                        <strong>Gagal Validasi:</strong>
                        <ul>
                            <?php foreach ($errors as $err): ?>
                                <li><?php echo htmlspecialchars($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <!-- Tampilkan Status Simpan DB -->
                <?php if ($save_success): ?>
                    <div class="alert-db-success"><?php echo htmlspecialchars($save_success); ?></div>
                <?php endif; ?>
                
                <?php if ($save_error): ?>
                    <div class="alert-db-error"><?php echo htmlspecialchars($save_error); ?></div>
                <?php endif; ?>

                <!-- Form HTML (Tidak ada yang berubah dari sebelumnya) -->
                <form method="POST" action="">
                    
                    <!-- INPUT BARU: DATA LABEL -->
                    <fieldset>
                        <legend>Informasi Data</legend>
                        <div class="form-group">
                            <label for="data_label">Label Data (Wajib Diisi):</label>
                            <input type="text" name="data_label" id="data_label" placeholder="Misal: Data Pribadi Cadangan, Catatan Meeting 1, dll." value="<?php echo htmlspecialchars($post_data['data_label'] ?? ''); ?>" required>
                            <p class="info-text">Beri nama data ini agar Anda mudah mengenalinya di halaman dekripsi.</p>
                        </div>
                    </fieldset>

                    <!-- GRUP DATA DIRI -->
                    <fieldset>
                        <legend>Bagian A: Data Diri (RC4)</legend>
                        <p class="info-text" style="margin-bottom: 1rem;">Data ini akan dienkripsi per-field menggunakan RC4 dan 1 kunci.</p>
                        
                        <div class="form-group">
                            <label for="kunci_rc4_datadiri">Kunci RC4 Data Diri:</label>
                            <input type="password" name="kunci_rc4_datadiri" id="kunci_rc4_datadiri" placeholder="Kunci rahasia untuk semua data diri" value="<?php echo htmlspecialchars($post_data['kunci_rc4_datadiri'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="nama">Nama:</label>
                            <input type="text" name="nama" id="nama" placeholder="Contoh: Budi Setiawan" value="<?php echo htmlspecialchars($post_data['nama'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="telepon">Nomor Telepon:</label>
                            <input type="text" name="telepon" id="telepon" placeholder="Contoh: +62 812 3456 7890" value="<?php echo htmlspecialchars($post_data['telepon'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="tempat_lahir">Tempat Lahir:</label>
                            <input type="text" name="tempat_lahir" id="tempat_lahir" placeholder="Contoh: Jakarta" value="<?php echo htmlspecialchars($post_data['tempat_lahir'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="tanggal_lahir">Tanggal Lahir:</label>
                            <input type="date" name="tanggal_lahir" id="tanggal_lahir" value="<?php echo htmlspecialchars($post_data['tanggal_lahir'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="alamat">Alamat:</label>
                            <textarea name="alamat" id="alamat" placeholder="Contoh: Jl. Merdeka No. 10, Jakarta Pusat"><?php echo htmlspecialchars($post_data['alamat'] ?? ''); ?></textarea>
                        </div>
                    </fieldset>

                    <!-- GRUP PESAN BEBAS -->
                    <fieldset>
                        <legend>Bagian B: Pesan Bebas (Vigenere + DES)</legend>
                        <p class="info-text" style="margin-bottom: 1rem;">Pesan ini akan dienkripsi 2 lapis (Vigenere lalu DES) menggunakan 2 kunci.</p>
                        
                        <div class="form-group">
                            <label for="kunci_vigenere">Kunci Pesan - Vigenere:</label>
                            <input type="text" name="kunci_vigenere" id="kunci_vigenere" placeholder="Kunci untuk lapis 1 (Vigenere) Pesan Bebas" value="<?php echo htmlspecialchars($post_data['kunci_vigenere'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="kunci_des_pesan">Kunci Pesan - DES:</label>
                            <input type="password" name="kunci_des_pesan" id="kunci_des_pesan" placeholder="Kunci untuk lapis 2 (DES) Pesan Bebas" value="<?php echo htmlspecialchars($post_data['kunci_des_pesan'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="pesan_bebas">Pesan Bebas:</label>
                            <textarea name="pesan_bebas" id="pesan_bebas" placeholder="Tulis pesan rahasia apa saja di sini..."><?php echo htmlspecialchars($post_data['pesan_bebas'] ?? ''); ?></textarea>
                        </div>
                    </fieldset>


                    <div class="btn-group">
                        <button type="submit" name="encrypt" class="btn btn-primary" style="background-color: #28a745; border-color: #28a745;">Enkripsi & Simpan Data Baru</button>
                        <a href="../dashboard.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>

                <!-- HASIL ENKRIPSI (JIKA SUKSES) -->
                <?php if (!empty($results)): ?>
                    <div class="result-group">
                        <h2 style="color: #e29fa6; margin-bottom: 1rem;">Hasil Enkripsi</h2>
                        
                        <!-- Hasil Bagian A: Data Diri -->
                        <?php if (isset($results['data_diri'])): 
                            $res_dd = $results['data_diri'];
                        ?>
                            <fieldset>
                                <legend><?php echo htmlspecialchars($res_dd['title']); ?></legend>
                                
                                <?php foreach ($res_dd['ciphertexts'] as $field_name => $ciphertext): ?>
                                    <div class="result-box-label"><?php echo htmlspecialchars($field_name); ?>:</div>
                                    <div class="result-box"><?php echo htmlspecialchars($ciphertext); ?></div>
                                <?php endforeach; ?>
                                
                                <div class="result-steps">
                                    <h4>Langkah-langkah (Data Diri):</h4>
                                    <?php 
                                        if(isset($res_dd['steps']) && is_array($res_dd['steps'])) {
                                            foreach ($res_dd['steps'] as $step): echo "<p>$step</p>"; endforeach; 
                                        }
                                    ?>
                                </div>
                            </fieldset>
                        <?php endif; ?>

                        <!-- Hasil Bagian B: Pesan Bebas -->
                        <?php if (isset($results['pesan_bebas'])): 
                            $res_pb = $results['pesan_bebas'];
                        ?>
                             <fieldset style="margin-top: 2rem;">
                                <legend><?php echo htmlspecialchars($res_pb['title']); ?></legend>
                                
                                <div class="result-box-label">Ciphertext (Vigenere + DES):</div>
                                <div class="result-box"><?php echo htmlspecialchars($res_pb['ciphertext']); ?></div>
                                
                                <div class="result-steps">
                                    <h4>Langkah-langkah (Pesan Bebas):</h4>
                                    <?php 
                                        if(isset($res_pb['steps']) && is_array($res_pb['steps'])) {
                                            foreach ($res_pb['steps'] as $step): echo "<p>$step</p>"; endforeach; 
                                        }
                                    ?>
                                </div>
                            </fieldset>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>
</body>
</html>

