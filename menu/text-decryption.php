<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../index.php?pesan=belum_login");
    exit();
}

include "../php/koneksi.php";
require_once __DIR__ . '/../vendor/autoload.php';
$username = $_SESSION['username'];

use phpseclib3\Crypt\RC4;
use phpseclib3\Math\BigInteger;

function vigenere_decrypt($ciphertext, $key)
{
    $key = strtoupper($key);
    $key_len = strlen($key);
    $key_idx = 0;
    $plaintext = "";

    if ($key_len == 0) return $ciphertext;

    for ($i = 0; $i < strlen($ciphertext); $i++) {
        $char = $ciphertext[$i];

        if (ctype_alpha($char)) {
            $is_upper = ctype_upper($char);
            $char_ord = ord($char);
            $key_char = $key[$key_idx % $key_len];
            $key_ord = ord($key_char);

            $base = $is_upper ? 65 : 97;

            $decrypted_ord = ($char_ord - $base - ($key_ord - 65) + 26) % 26 + $base;
            $plaintext .= chr($decrypted_ord);

            $key_idx++;
        } else {
            $plaintext .= $char;
        }
    }
    return $plaintext;
}


function is_mostly_printable($text)
{
    if ($text === null) return false;
    $len = strlen($text);
    if ($len === 0) return true;
    $printable = 0;
    for ($i = 0; $i < $len; $i++) {
        $ord = ord($text[$i]);
        if (($ord >= 32 && $ord <= 126) || $ord === 9 || $ord === 10 || $ord === 13) {
            $printable++;
        }
    }
    return ($printable / $len) >= 0.85;
}


$errors = [];
$post_data = [];
$decrypted_results = [];
$data_list = [];
$current_data = null;
$data_id = null;

$view = $_GET['view'] ?? 'list';


if ($view == 'list') {
    $stmt_list = $konek->prepare("SELECT data_id, data_label, created_at FROM usersData WHERE username = ? ORDER BY created_at DESC");
    $stmt_list->bind_param("s", $username);
    $stmt_list->execute();
    $data_list = $stmt_list->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_list->close();
} elseif ($view == 'detail') {
    if (!isset($_GET['id'])) {
        $errors[] = "ID Data tidak ditemukan.";
        $view = 'list';
    } else {
        $data_id = (int)$_GET['id'];

        $stmt_detail = $konek->prepare("
            SELECT ud.*, uc.enc_pesan_bebas 
            FROM usersData ud 
            LEFT JOIN usersCatatan uc ON ud.data_id = uc.data_id 
            WHERE ud.data_id = ? AND ud.username = ?
        ");
        $stmt_detail->bind_param("is", $data_id, $username);
        $stmt_detail->execute();
        $current_data = $stmt_detail->get_result()->fetch_assoc();
        $stmt_detail->close();

        if (!$current_data) {
            $errors[] = "Data tidak ditemukan atau Anda tidak punya akses.";
            $view = 'list';
        }
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['decrypt_data']) && $current_data) {

    $kunci_rc4 = isset($_POST['kunci_rc4_datadiri']) ? trim($_POST['kunci_rc4_datadiri']) : '';
    $kunci_vig = isset($_POST['kunci_vigenere']) ? trim($_POST['kunci_vigenere']) : '';
    $rsa_p_raw = isset($_POST['rsa_p']) ? trim($_POST['rsa_p']) : '';
    $rsa_q_raw = isset($_POST['rsa_q']) ? trim($_POST['rsa_q']) : '';
    $rsa_e_raw = isset($_POST['rsa_e']) ? trim($_POST['rsa_e']) : '';

    $post_data = [
        'kunci_rc4_datadiri' => $kunci_rc4,
        'kunci_vigenere' => $kunci_vig,
        'rsa_p' => $rsa_p_raw,
        'rsa_q' => $rsa_q_raw,
        'rsa_e' => $rsa_e_raw
    ];

    $isRc4KeyAttempted = !empty($kunci_rc4);
    $isRsaKeyParamsAttempted = !empty($kunci_vig) || !empty($rsa_p_raw) || !empty($rsa_q_raw) || !empty($rsa_e_raw);

    $isRsaKeySetValid = false;

    // validasi kunci
    if (!$isRc4KeyAttempted && !$isRsaKeyParamsAttempted) {
        $errors[] = "Anda harus memasukkan setidaknya satu set kunci (Kunci Rahasia RC4 ATAU Kunci Vigenere + RSA) untuk membuka data.";
    }

    // Validasi Kunci Vigenere (hanya huruf)
    if (!empty($kunci_vig) && !preg_match('/^[a-zA-Z]+$/', $kunci_vig)) {
        $errors[] = "Kunci Lapis 1 (Vigenere) hanya boleh berisi huruf (A-Z).";
    }

    if ($isRsaKeyParamsAttempted) {
        if (empty($kunci_vig) || empty($rsa_p_raw) || empty($rsa_q_raw) || empty($rsa_e_raw)) {
            $errors[] = "Untuk membuka Catatan Rahasia, Anda harus mengisi LENGKAP semua kunci: Kunci Lapis 1 (Vigenere) DAN parameter RSA (p, q, e).";
        } else {

            $rsa_p = filter_var($rsa_p_raw, FILTER_VALIDATE_INT);
            $rsa_q = filter_var($rsa_q_raw, FILTER_VALIDATE_INT);
            $rsa_e = filter_var($rsa_e_raw, FILTER_VALIDATE_INT);

            if ($rsa_p === false || $rsa_q === false || $rsa_e === false) {
                $errors[] = "Parameter RSA (p, q, e) harus berupa bilangan bulat sempurna (tanpa desimal).";
            } else {
                try {
                    $p_bi = new BigInteger($rsa_p);
                    $q_bi = new BigInteger($rsa_q);
                    $e_bi = new BigInteger($rsa_e);
                    $one_bi = new BigInteger(1);

                    if ($p_bi->equals($q_bi)) $errors[] = "RSA Error: p dan q tidak boleh sama.";
                    if ($e_bi->compare($one_bi) <= 0) $errors[] = "RSA Error: e ('$rsa_e') harus lebih besar dari 1.";

                    if (!$p_bi->isPrime()) $errors[] = "RSA Error: p ('$rsa_p') bukan bilangan prima.";
                    if (!$q_bi->isPrime()) $errors[] = "RSA Error: q ('$rsa_q') bukan bilangan prima.";

                    if (empty($errors)) {
                        $phi = $p_bi->subtract($one_bi)->multiply($q_bi->subtract($one_bi));
                        if (!$e_bi->gcd($phi)->equals($one_bi)) {
                            $errors[] = "RSA Error: e ('$rsa_e') tidak relatif prima dengan (p-1)*(q-1).";
                        }
                    }

                    if (empty($errors)) {
                        $isRsaKeySetValid = true;
                    }
                } catch (Exception $ex) {
                    $errors[] = "RSA Error: " . $ex->getMessage();
                }
            }
        }
    }


    if (empty($errors) && $isRc4KeyAttempted) {
        if (!empty($current_data['enc_nama']) || !empty($current_data['enc_telepon']) || !empty($current_data['enc_tempat_lahir']) || !empty($current_data['enc_tanggal_lahir']) || !empty($current_data['enc_alamat'])) {
            try {
                $rc4 = new RC4();
                $rc4->setKey($kunci_rc4); // Gunakan kunci yang sudah di-trim

                $dec_nama = $rc4->decrypt(hex2bin($current_data['enc_nama']));
                $dec_telp = $rc4->decrypt(hex2bin($current_data['enc_telepon']));
                $dec_tempat = $rc4->decrypt(hex2bin($current_data['enc_tempat_lahir']));
                $dec_tanggal = $rc4->decrypt(hex2bin($current_data['enc_tanggal_lahir']));
                $dec_alamat = $rc4->decrypt(hex2bin($current_data['enc_alamat']));

                $name_ok = is_mostly_printable($dec_nama) && (empty($dec_nama) || preg_match('/^[a-zA-Z\s\'.\-]*$/u', $dec_nama) === 1);
                $tel_ok = is_mostly_printable($dec_telp) && (empty($dec_telp) || preg_match('/^[0-9\+\-\s\(\)]*$/', $dec_telp) === 1);
                $tmp_ok = is_mostly_printable($dec_tempat);
                $tgl_ok = is_mostly_printable($dec_tanggal) && (empty($dec_tanggal) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $dec_tanggal) === 1);
                $alm_ok = is_mostly_printable($dec_alamat);

                $ok_count = 0;
                $filled_fields_count = 0;

                if (!empty($current_data['enc_nama'])) {
                    $filled_fields_count++;
                    if ($name_ok) $ok_count++;
                }
                if (!empty($current_data['enc_telepon'])) {
                    $filled_fields_count++;
                    if ($tel_ok) $ok_count++;
                }
                if (!empty($current_data['enc_tempat_lahir'])) {
                    $filled_fields_count++;
                    if ($tmp_ok) $ok_count++;
                }
                if (!empty($current_data['enc_tanggal_lahir'])) {
                    $filled_fields_count++;
                    if ($tgl_ok) $ok_count++;
                }
                if (!empty($current_data['enc_alamat'])) {
                    $filled_fields_count++;
                    if ($alm_ok) $ok_count++;
                }

                if ($filled_fields_count > 0 && $ok_count == 0) {
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
                $errors[] = "Gagal membuka Data Pribadi: " . $e->getMessage();
            }
        } else {
            $errors[] = "Anda memasukkan Kunci Rahasia (RC4), tetapi tidak ada Data Pribadi tersimpan untuk set data ini.";
        }
    }


    if (empty($errors) && $isRsaKeySetValid) {
        if (!empty($current_data['enc_pesan_bebas'])) {
            try {
                $cipher_text = trim($current_data['enc_pesan_bebas']);
                if ($cipher_text === '') {
                    throw new Exception('Data Catatan Rahasia terkunci kosong.');
                }
                $cipher_tokens = preg_split('/\s+/', $cipher_text);

                $p_bi = new BigInteger($rsa_p);
                $q_bi = new BigInteger($rsa_q);
                $e_bi = new BigInteger($rsa_e);
                $one_bi = new BigInteger(1); 

                $n = $p_bi->multiply($q_bi);
                $phi = $p_bi->subtract($one_bi)->multiply($q_bi->subtract($one_bi));
                $d = $e_bi->modInverse($phi); 

                $plain = '';
                foreach ($cipher_tokens as $tok) {
                    $tok = trim($tok);
                    if ($tok === '') continue;
                    $c = new BigInteger($tok);
                    if ($c->compare($n) >= 0) {
                        throw new Exception("Ciphertext token ('$tok') lebih besar dari n. Kemungkinan parameter RSA salah.");
                    }
                    $m = $c->powMod($d, $n);
                    $plain .= $m->toBytes();
                }

                // 2. Dekripsi Lapis 2 (Vigenere)
                $decrypted_plaintext = vigenere_decrypt($plain, $kunci_vig); // Gunakan kunci yg sudah di-trim

                if (!is_mostly_printable($decrypted_plaintext)) {
                    throw new Exception('Gagal membuka data. Kunci Lapis 1 (Vigenere) atau parameter RSA salah, atau data rusak.');
                }
                $decrypted_results['Catatan Rahasia'] = $decrypted_plaintext;
            } catch (Exception $e) {
                $errors[] = "Gagal membuka Catatan Rahasia: " . $e->getMessage();
            }
        } else {
            $errors[] = "Anda memasukkan kunci Vigenere + RSA, tetapi tidak ada Catatan Rahasia tersimpan untuk set data ini.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Buka Catatan dari Brankas - Brankas Pribadi</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/textED.css">
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
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

                <?php if ($view == 'list'): ?>
                    <a href="../dashboard.php" class="back-link">← Kembali ke Dashboard</a>
                <?php else: ?>
                    <a href="text-decryption.php" class="back-link">← Kembali ke Daftar Catatan</a>
                <?php endif; ?>

                <h2 style="color: #e29fa6; margin-bottom: 1rem;">Buka Kunci Catatan dari Brankas</h2>

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

                <?php if ($view == 'list'): ?>
                    <p style="color: #666; margin-bottom: 2rem;">Berikut adalah daftar catatan terkunci yang Anda simpan di brankas. Pilih satu untuk dibuka.</p>

                    <?php if (empty($data_list)): ?>
                        <div class="alert-info">
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
                                            <a href="text-decryption.php?view=detail&id=<?php echo $item['data_id']; ?>" class="btn-detail">Lihat Detail & Buka Kunci</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                <?php elseif ($view == 'detail' && $current_data): ?>

                    <p style="color: #666; margin-bottom: 2rem;">Anda sedang melihat data: <strong>"<?php echo htmlspecialchars($current_data['data_label']); ?>"</strong> (disimpan pada <?php echo htmlspecialchars(date('d F Y', strtotime($current_data['created_at']))); ?>).</p>

                    <div class="cipher-detail-box">
                        <h3>Rincian Data Terkunci (Ciphertext)</h3>

                        <?php
                        $hasRC4Data = !empty($current_data['enc_nama']) || !empty($current_data['enc_telepon']) || !empty($current_data['enc_tempat_lahir']) || !empty($current_data['enc_tanggal_lahir']) || !empty($current_data['enc_alamat']);
                        if ($hasRC4Data): ?>
                            <div class="cipher-field">
                                <strong>Data Terkunci - Nama:</strong>
                                <span><?php echo htmlspecialchars($current_data['enc_nama'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="cipher-field">
                                <strong>Data Terkunci - Telepon:</strong>
                                <span><?php echo htmlspecialchars($current_data['enc_telepon'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="cipher-field">
                                <strong>Data Terkunci - Tempat Lahir:</strong>
                                <span><?php echo htmlspecialchars($current_data['enc_tempat_lahir'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="cipher-field">
                                <strong>Data Terkunci - Tanggal Lahir:</strong>
                                <span><?php echo htmlspecialchars($current_data['enc_tanggal_lahir'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="cipher-field">
                                <strong>Data Terkunci - Alamat:</strong>
                                <span><?php echo htmlspecialchars($current_data['enc_alamat'] ?? 'N/A'); ?></span>
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

                    <form method="POST" action="text-decryption.php?view=detail&id=<?php echo $data_id; ?>">

                        <fieldset>
                            <legend>Bagian A: Buka Kunci Data Pribadi (RC4)</legend>
                            <p class="info-text" style="margin-bottom: 1rem;">Masukkan Kunci Rahasia RC4 yang sama dengan saat Anda mengunci Data Pribadi ini.</p>

                            <div class="form-group">
                                <label for="kunci_rc4_datadiri">Kunci Rahasia RC4:</label>
                                <input type="text" name="kunci_rc4_datadiri" id="kunci_rc4_datadiri" placeholder="Kunci rahasia untuk semua data pribadi" value="<?php echo htmlspecialchars($post_data['kunci_rc4_datadiri'] ?? ''); ?>">
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend>Bagian B: Buka Kunci Catatan Rahasia (Vigenere + RSA)</legend>
                            <p class="info-text" style="margin-bottom: 1rem;">Masukkan Kunci Lapis 1 (Vigenere) dan parameter RSA (p, q, e) yang sama persis dengan saat mengunci Catatan Rahasia.</p>

                            <div class="form-group">
                                <label for="kunci_vigenere">Kunci Lapis 1 (Vigenere - Hanya Huruf):</label>
                                <input type="text" name="kunci_vigenere" id="kunci_vigenere" placeholder="Kunci untuk lapis 1 (Vigenere)" value="<?php echo htmlspecialchars($post_data['kunci_vigenere'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="rsa_p">RSA p (Prima):</label>
                                <input type="text" name="rsa_p" id="rsa_p" placeholder="Bilangan prima pertama (misal: 17)" value="<?php echo htmlspecialchars($post_data['rsa_p'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="rsa_q">RSA q (Prima):</label>
                                <input type="text" name="rsa_q" id="rsa_q" placeholder="Bilangan prima kedua (misal: 19)" value="<?php echo htmlspecialchars($post_data['rsa_q'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="rsa_e">RSA e (Eksponen Publik):</label>
                                <input type="text" name="rsa_e" id="rsa_e" placeholder="Eksponen publik (misal: 65537)" value="<?php echo htmlspecialchars($post_data['rsa_e'] ?? ''); ?>">
                            </div>
                        </fieldset>

                        <div class="btn-group">
                            <button type="submit" name="decrypt_data" class="btn btn-primary">Buka Kunci Data Ini</button>
                        </div>
                    </form>

                    <?php if (!empty($decrypted_results)): ?>
                        <div class="result-group">
                            <h2 style="color: #e29fa6; margin-bottom: 1rem;">Hasil Data yang Berhasil Dibuka</h2>

                            <?php if (isset($decrypted_results['Data Pribadi'])):
                                $res_dd = $decrypted_results['Data Pribadi'];
                            ?>
                                <fieldset>
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

                            <?php if (isset($decrypted_results['Catatan Rahasia'])):
                                $res_pb = $decrypted_results['Catatan Rahasia'];
                            ?>
                                <fieldset style="margin-top: 2rem;">
                                    <legend>Catatan Rahasia (Berhasil Dibuka)</legend>
                                    <div class="result-box">
                                        <?php echo nl2br(htmlspecialchars($res_pb)); ?>
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