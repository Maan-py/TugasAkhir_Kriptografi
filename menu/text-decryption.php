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
use phpseclib3\Math\BigInteger;

// --- FUNGSI VIGENERE DECRYPT ---
function vigenere_decrypt($ciphertext, $key)
{
    // ... (Fungsi Vigenere utuh, tidak ada perubahan) ...
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


// Helper: cek apakah string mayoritas printable (heuristik untuk deteksi kunci salah)
function is_mostly_printable($text)
{
    // ... (Fungsi is_mostly_printable utuh, tidak ada perubahan) ...
    if ($text === null) return false;
    $len = strlen($text);
    if ($len === 0) return true; // String kosong dianggap printable
    $printable = 0;
    for ($i = 0; $i < $len; $i++) {
        $ord = ord($text[$i]);
        // printable ASCII range + tab, newline, carriage return
        if (($ord >= 32 && $ord <= 126) || $ord === 9 || $ord === 10 || $ord === 13) {
            $printable++;
        }
    }
    // Jika ada karakter, minimal 85% harus printable
    return ($printable / $len) >= 0.85;
}


// Inisialisasi variabel
$errors = [];
$post_data = []; // Untuk menyimpan kunci yang di-submit
$decrypted_results = []; // Untuk menyimpan hasil dekripsi
$data_list = []; // Untuk daftar data
$current_data = null; // Untuk data yang sedang dilihat detailnya
$data_id = null;

// Tentukan Tampilan (View)
$view = $_GET['view'] ?? 'list';


if ($view == 'list') {
    // ... (Logika $view == 'list' utuh, tidak ada perubahan) ...
    // TAMPILAN 1: Ambil DAFTAR data milik user
    $stmt_list = $konek->prepare("SELECT data_id, data_label, created_at FROM usersData WHERE username = ? ORDER BY created_at DESC");
    $stmt_list->bind_param("s", $username);
    $stmt_list->execute();
    $data_list = $stmt_list->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_list->close();
} elseif ($view == 'detail') {
    // ... (Logika $view == 'detail' utuh, tidak ada perubahan) ...
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


// --- PROSES BUKA KUNCI (SAAT FORM DI-SUBMIT) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['decrypt_data']) && $current_data) {

    // (ROMBAKAN) 1. AMBIL & BERSIHKAN SEMUA INPUT KUNCI (TRIM)
    $kunci_rc4 = isset($_POST['kunci_rc4_datadiri']) ? trim($_POST['kunci_rc4_datadiri']) : '';
    $kunci_vig = isset($_POST['kunci_vigenere']) ? trim($_POST['kunci_vigenere']) : '';
    $rsa_p = isset($_POST['rsa_p']) ? trim($_POST['rsa_p']) : '';
    $rsa_q = isset($_POST['rsa_q']) ? trim($_POST['rsa_q']) : '';
    $rsa_e = isset($_POST['rsa_e']) ? trim($_POST['rsa_e']) : '';

    // Simpan kunci yang sudah bersih ke post_data untuk re-populasi form
    $post_data = [
        'kunci_rc4_datadiri' => $kunci_rc4,
        'kunci_vigenere' => $kunci_vig,
        'rsa_p' => $rsa_p,
        'rsa_q' => $rsa_q,
        'rsa_e' => $rsa_e
    ];

    // (ROMBAKAN) 2. VALIDASI KUNCI
    $isRc4KeyFilled = !empty($kunci_rc4);
    $isRsaKeyFilled = !empty($kunci_vig) && !empty($rsa_p) && !empty($rsa_q) && !empty($rsa_e);
    
    // Cek jika tidak ada kunci yang dimasukkan (gunakan var yang sudah di-trim)
    if (!$isRc4KeyFilled && !$isRsaKeyFilled) {
        $errors[] = "Anda harus memasukkan setidaknya satu set kunci (Kunci Rahasia atau Kunci Lapis 1 + RSA) untuk membuka data.";
    }

    // (Poin 5) Validasi Kunci Vigenere (hanya huruf) - SAMA SEPERTI DI ENKRIPSI
    if (!empty($kunci_vig) && !preg_match('/^[a-zA-Z]+$/', $kunci_vig)) {
        $errors[] = "Kunci Lapis 1 (Vigenere) hanya boleh berisi huruf (A-Z).";
    }

    // (Tambahan) Validasi RSA (WAJIB!) - SAMA SEPERTI DI ENKRIPSI
    if ($isRsaKeyFilled) {
        if (!is_numeric($rsa_p) || !is_numeric($rsa_q) || !is_numeric($rsa_e)) {
             $errors[] = "Parameter RSA (p, q, e) harus berupa angka.";
        } else {
            try {
                $p = new BigInteger($rsa_p);
                $q = new BigInteger($rsa_q);
                $e_val = new BigInteger($rsa_e);
                $one = new BigInteger(1);

                // CEK BUG (e=1) DAN (p=q)
                if ($p->equals($q)) $errors[] = "RSA Error: p dan q tidak boleh sama.";
                if ($e_val->compare($one) <= 0) $errors[] = "RSA Error: e ('$rsa_e') harus lebih besar dari 1.";
                
                // --- (PERBAIKAN BUG) ---
                // Ganti isProbablyPrime() menjadi isPrime()
                if (!$p->isPrime()) $errors[] = "RSA Error: p ('$rsa_p') bukan bilangan prima.";
                if (!$q->isPrime()) $errors[] = "RSA Error: q ('$rsa_q') bukan bilangan prima.";
                // --- (AKHIR PERBAIKAN BUG) ---
                
                // Cek gcd(e, phi)
                $phi = $p->subtract($one)->multiply($q->subtract($one));
                if (!$e_val->gcd($phi)->equals($one)) {
                    $errors[] = "RSA Error: e ('$rsa_e') tidak relatif prima dengan (p-1)*(q-1).";
                }

            } catch (Exception $ex) {
                $errors[] = "RSA Error: " . $ex->getMessage();
            }
        }
    }


    // --- PROSES BAGIAN A: DATA PRIBADI (RC4) ---
    // Hanya proses jika tidak ada error & kunci RC4 diisi
    if (empty($errors) && $isRc4KeyFilled) {
        // ... (Logika RC4 utuh, tidak ada perubahan) ...
        if (!empty($current_data['enc_nama'])) {
            try {
                $rc4 = new RC4();
                $rc4->setKey($kunci_rc4); // Gunakan kunci yang sudah di-trim

                // Urutan dekripsi HARUS SAMA PERSIS dengan enkripsi
                $dec_nama = $rc4->decrypt(hex2bin($current_data['enc_nama']));
                $dec_telp = $rc4->decrypt(hex2bin($current_data['enc_telepon']));
                $dec_tempat = $rc4->decrypt(hex2bin($current_data['enc_tempat_lahir']));
                $dec_tanggal = $rc4->decrypt(hex2bin($current_data['enc_tanggal_lahir']));
                $dec_alamat = $rc4->decrypt(hex2bin($current_data['enc_alamat']));

                // Validasi sederhana untuk mendeteksi kunci salah (heuristik)
                $name_ok = is_mostly_printable($dec_nama) && preg_match('/^[a-zA-Z\s\'.\-]*$/u', $dec_nama) === 1;
                $tel_ok = is_mostly_printable($dec_telp) && preg_match('/^[0-9\+\-\s\(\)]*$/', $dec_telp) === 1;
                $tmp_ok = is_mostly_printable($dec_tempat); // Tempat lahir bisa apa saja
                $tgl_ok = is_mostly_printable($dec_tanggal) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dec_tanggal) === 1;
                $alm_ok = is_mostly_printable($dec_alamat); // Alamat bisa apa saja
                
                $ok_count = 0;
                $filled_fields = 0;

                if(!empty($current_data['enc_nama'])) { $filled_fields++; if($name_ok) $ok_count++; }
                if(!empty($current_data['enc_telepon'])) { $filled_fields++; if($tel_ok) $ok_count++; }
                if(!empty($current_data['enc_tempat_lahir'])) { $filled_fields++; if($tmp_ok) $ok_count++; }
                if(!empty($current_data['enc_tanggal_lahir'])) { $filled_fields++; if($tgl_ok) $ok_count++; }
                if(!empty($current_data['enc_alamat'])) { $filled_fields++; if($alm_ok) $ok_count++; }
                
                // Jika ada field yg diisi, dan yg OK 0, anggap error
                if ($filled_fields > 0 && $ok_count == 0) { 
                    // (Bahasa Awam) Pesan Error diubah
                    throw new Exception('Kunci Rahasia (Data Pribadi) salah atau data rusak.');
                }

                $decrypted_results['Data Pribadi'] = [
                    'Nama' => $dec_nama,
                    'Telepon' => $dec_telp,
                    'Tempat Lahir' => $dec_tempat,
                    'Tanggal Lahir' => $dec_tanggal,
                    'Alamat' => $dec_alamat,
                ];
            } catch (Exception $e) {
                // (Bahasa Awam) Pesan Error diubah
                $errors[] = "Gagal membuka Data Pribadi: " . $e->getMessage();
            }
        } else {
            $errors[] = "Anda memasukkan Kunci Rahasia (RC4), tapi Data Pribadi tidak ditemukan di set data ini.";
        }
    }

    // --- PROSES BAGIAN B: CATATAN RAHASIA (RSA -> VIGENERE) ---
    // Hanya proses jika tidak ada error & semua kunci Vigenere+RSA diisi
    if (empty($errors) && $isRsaKeyFilled) {
        // ... (Logika RSA/Vigenere utuh, tidak ada perubahan) ...
        if (!empty($current_data['enc_pesan_bebas'])) {
            try {
                // 1. Parse deretan angka desimal yang dipisah spasi
                $cipher_text = trim($current_data['enc_pesan_bebas']);
                if ($cipher_text === '') {
                    throw new Exception('Data terkunci kosong.');
                }
                $cipher_tokens = preg_split('/\s+/', $cipher_text);

                // Gunakan kunci yang sudah di-trim
                $p = new BigInteger($rsa_p);
                $q = new BigInteger($rsa_q);
                $e = new BigInteger($rsa_e);
                $one = new BigInteger(1);
                $n = $p->multiply($q);
                $phi = $p->subtract($one)->multiply($q->subtract($one));
                
                // Cek ulang (sebenarnya sudah divalidasi di atas, tapi ini double check)
                if (!$e->gcd($phi)->equals($one)) {
                    throw new Exception('e tidak relatif prima dengan phi(n).');
                }
                $d = $e->modInverse($phi);

                $plain = '';
                foreach ($cipher_tokens as $tok) {
                    $tok = trim($tok);
                    if ($tok === '') continue;
                    $c = new BigInteger($tok);
                    $m = $c->powMod($d, $n);
                    $plain .= $m->toBytes();
                }

                // 2. Dekripsi Lapis 2 (Vigenere)
                $decrypted_plaintext = vigenere_decrypt($plain, $kunci_vig); // Gunakan kunci yg sudah di-trim
                
                // Heuristik validasi: hasil harus mayoritas printable DAN formatnya benar
                if (!is_mostly_printable($decrypted_plaintext) || !preg_match('/^[a-zA-Z\s]*$/', $decrypted_plaintext)) {
                    // (Bahasa Awam) Pesan Error diubah
                    throw new Exception('Gagal membuka data. Kemungkinan Kunci Vigenere atau parameter RSA salah.');
                }
                $decrypted_results['Catatan Rahasia'] = $decrypted_plaintext;
            } catch (Exception $e) {
                // (Bahasa Awam) Pesan Error diubah
                $errors[] = "Gagal membuka Catatan Rahasia: " . $e->getMessage();
            }
        } else {
            $errors[] = "Anda memasukkan kunci Vigenere+RSA, tapi Catatan Rahasia tidak ditemukan di set data ini.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <!-- (HTML Head utuh, tidak ada perubahan) -->
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <!-- (Bahasa Awam) Title diubah -->
    <title>Buka Catatan dari Brankas - Brankas Pribadi</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/textED.css">
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <!-- (HTML Header utuh, tidak ada perubahan) -->
            <div class="header-content">
                <h1>Buka Catatan dari Brankas</h1>
                <div class="user-info">
                    <span class="welcome-text">User: <strong><?php echo htmlspecialchars($username); ?></strong></span>
                    <a href="../php/logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </header>

        <main class="dashboard-main">
            <div class="encryption-container">

                <!-- (HTML Link 'Kembali' utuh, tidak ada perubahan) -->
                <?php if ($view == 'list'): ?>
                    <a href="../dashboard.php" class="back-link">← Kembali ke Dashboard</a>
                <?php else: ?>
                    <a href="text-decryption.php" class="back-link">← Kembali ke Daftar Catatan</a>
                <?php endif; ?>

                <!-- (HTML Judul Halaman utuh, tidak ada perubahan) -->
                <h2 style="color: #e29fa6; margin-bottom: 1rem;">Buka Kunci Catatan dari Brankas</h2>

                <!-- (HTML Blok Error utuh, tidak ada perubahan) -->
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

                    <!-- (HTML Tampilan List utuh, tidak ada perubahan) -->
                    <p style="color: #666; margin-bottom: 2rem;">Berikut adalah daftar catatan terkunci yang Anda simpan di brankas. Pilih satu untuk dibuka.</p>

                    <?php if (empty($data_list)): ?>
                        <div class="alert-info">
                            <!-- (Bahasa Awam) Teks diubah -->
                            Brankas Anda masih kosong. Silakan gunakan menu "Tulis & Kunci Catatan Baru" terlebih dahulu.
                        </div>
                    <?php else: ?>
                        <table class="data-list-table">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Label Catatan</th>
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
                                            <!-- (Bahasa Awam) Tombol diubah -->
                                            <a href="text-decryption.php?view=detail&id=<?php echo $item['data_id']; ?>" class="btn-detail">Lihat Detail & Buka Kunci</a>
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

                    <!-- (HTML Tampilan Detail utuh, tidak ada perubahan) -->
                    <p style="color: #666; margin-bottom: 2rem;">Anda sedang melihat data: <strong>"<?php echo htmlspecialchars($current_data['data_label']); ?>"</strong> (disimpan pada <?php echo htmlspecialchars(date('d F Y', strtotime($current_data['created_at']))); ?>).</p>

                    <!-- (Bahasa Awam) RINCIAN CIPHERTEXT -->
                    <div class="cipher-detail-box">
                        <h3>Rincian Data Terkunci (Ciphertext)</h3>

                        <?php if (!empty($current_data['enc_nama'])): ?>
                            <div class="cipher-field">
                                <strong>Data Terkunci - Nama:</strong>
                                <span><?php echo htmlspecialchars($current_data['enc_nama']); ?></span>
                            </div>
                            <div class="cipher-field">
                                <strong>Data Terkunci - Telepon:</strong>
                                <span><?php echo htmlspecialchars($current_data['enc_telepon']); ?></span>
                            </div>
                            <div class="cipher-field">
                                <strong>Data Terkunci - Tempat Lahir:</strong>
                                <span><?php echo htmlspecialchars($current_data['enc_tempat_lahir']); ?></span>
                            </div>
                            <div class="cipher-field">
                                <strong>Data Terkunci - Tanggal Lahir:</strong>
                                <span><?php echo htmlspecialchars($current_data['enc_tanggal_lahir']); ?></span>
                            </div>
                            <div class="cipher-field">
                                <strong>Data Terkunci - Alamat:</strong>
                                <span><?php echo htmlspecialchars($current_data['enc_alamat']); ?></span>
                            </div>
                        <?php else: ?>
                            <div class="alert-info" style="margin-bottom: 1rem;">Tidak ada Data Pribadi tersimpan di set data ini.</div>
                        <?php endif; ?>

                        <hr style="border:0; border-top: 1px solid #f3b7c0; margin: 1.5rem 0;">

                        <?php if (!empty($current_data['enc_pesan_bebas'])): ?>
                            <div class="cipher-field">
                                <strong>Data Terkunci - Catatan Rahasia (Vigenere+RSA):</strong>
                                <span><?php echo htmlspecialchars($current_data['enc_pesan_bebas']); ?></span>
                            </div>
                        <?php else: ?>
                            <div class="alert-info" style="margin-bottom: 1rem;">Tidak ada Catatan Rahasia tersimpan di set data ini.</div>
                        <?php endif; ?>
                    </div>

                    <!-- FORM KUNCI DEKRIPSI -->
                    <form method="POST" action="text-decryption.php?view=detail&id=<?php echo $data_id; ?>">

                        <!-- (HTML Form Kunci utuh, tidak ada perubahan) -->
                        <fieldset>
                            <!-- (Bahasa Awam) Judul diubah -->
                            <legend>Bagian A: Buka Kunci Data Pribadi (RC4)</legend>
                            <p class="info-text" style="margin-bottom: 1rem;">Masukkan kunci RC4 untuk membuka Data Pribadi di atas.</p>

                            <div class="form-group">
                                <label for="kunci_rc4_datadiri">Kunci Rahasia (Data Pribadi):</label>
                                <!-- (ROMBAKAN) value="" sekarang pakai $post_data -->
                                <input type="text" name="kunci_rc4_datadiri" id="kunci_rc4_datadiri" placeholder="Kunci rahasia untuk semua data pribadi" value="<?php echo htmlspecialchars($post_data['kunci_rc4_datadiri'] ?? ''); ?>">
                            </div>
                        </fieldset>

                        <!-- GRUP KUNCI PESAN BEBAS -->
                        <fieldset>
                            <!-- (Bahasa Awam) Judul diubah -->
                            <legend>Bagian B: Buka Kunci Catatan Rahasia (Vigenere + RSA)</legend>
                            <p class="info-text" style="margin-bottom: 1rem;">Masukkan Kunci Lapis 1 (Vigenere) dan parameter RSA (p, q, e) yang sama dengan saat mengunci.</p>

                            <div class="form-group">
                                <label for="kunci_vigenere">Kunci Lapis 1 (Vigenere - Hanya Huruf):</label>
                                <input type="text" name="kunci_vigenere" id="kunci_vigenere" placeholder="Kunci untuk lapis 1 (Vigenere)" value="<?php echo htmlspecialchars($post_data['kunci_vigenere'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="rsa_p">RSA p (prima):</label>
                                <input type="text" name="rsa_p" id="rsa_p" placeholder="Bilangan prima yang dipakai saat mengunci" value="<?php echo htmlspecialchars($post_data['rsa_p'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="rsa_q">RSA q (prima):</label>
                                <input type="text" name="rsa_q" id="rsa_q" placeholder="Bilangan prima yang dipakai saat mengunci" value="<?php echo htmlspecialchars($post_data['rsa_q'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="rsa_e">RSA e (eksponen publik):</label>
                                <input type="text" name="rsa_e" id="rsa_e" placeholder="Contoh: 65537" value="<?php echo htmlspecialchars($post_data['rsa_e'] ?? ''); ?>">
                            </div>
                        </fieldset>

                        <div class="btn-group">
                            <!-- (Bahasa Awam) Tombol diubah -->
                            <button type="submit" name="decrypt_data" class="btn btn-primary">Buka Kunci Data Ini</button>
                        </div>
                    </form>

                    <!-- (HTML Hasil Dekripsi utuh, tidak ada perubahan) -->
                    <?php if (!empty($decrypted_results)): ?>
                        <div class="result-group">
                            <!-- (Bahasa Awam) Judul diubah -->
                            <h2 style="color: #e29fa6; margin-bottom: 1rem;">Hasil Data yang Berhasil Dibuka</h2>

                            <!-- Hasil Bagian A: Data Diri -->
                            <?php if (isset($decrypted_results['Data Pribadi'])):
                                $res_dd = $decrypted_results['Data Pribadi'];
                            ?>
                                <fieldset>
                                    <!-- (Bahasa Awam) Judul diubah -->
                                    <legend>Data Pribadi (Berhasil Dibuka)</legend>
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
                            <?php if (isset($decrypted_results['Catatan Rahasia'])):
                                $res_pb = $decrypted_results['Catatan Rahasia'];
                            ?>
                                <fieldset style="margin-top: 2rem;">
                                    <!-- (Bahasa Awam) Judul diubah -->
                                    <legend>Catatan Rahasia (Berhasil Dibuka)</legend>
                                    <div class="result-box">
                                        <?php echo nl2br(htmlspecialchars($res_pb)); // nl2br agar baris baru di plaintext tampil 
                                        ?>
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

