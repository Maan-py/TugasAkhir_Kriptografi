<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../index.php?pesan=belum_login");
    exit();
}

include "../php/koneksi.php";
require_once __DIR__ . '/../vendor/autoload.php';
include_once "../php/simpan_textEnkrip.php";

use phpseclib3\Crypt\RC4;
use phpseclib3\Math\BigInteger;

// --- FUNGSI VIGENERE ENCRYPT ---
function vigenere_encrypt($plaintext, $key)
{
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

// Helper: hitung kunci RSA dari p, q, e
function rsa_compute_keys($p_str, $q_str, $e_str)
{
    $p = new BigInteger($p_str);
    $q = new BigInteger($q_str);
    $e = new BigInteger($e_str);
    $one = new BigInteger(1);

    $n = $p->multiply($q);
    $phi = $p->subtract($one)->multiply($q->subtract($one));

    if (!$e->gcd($phi)->equals($one)) {
        throw new Exception('e tidak relatif prima dengan phi(n).');
    }

    $d = $e->modInverse($phi);

    return [
        'n' => $n,
        'e' => $e,
        'd' => $d,
        'p' => $p,
        'q' => $q
    ];
}

function rsa_build_components($p_str, $q_str, $e_str)
{
    $p = new BigInteger($p_str);
    $q = new BigInteger($q_str);
    $e = new BigInteger($e_str);
    $one = new BigInteger(1);

    $n = $p->multiply($q);
    $phi = $p->subtract($one)->multiply($q->subtract($one));
    if (!$e->gcd($phi)->equals($one)) {
        throw new Exception('e tidak relatif prima dengan phi(n).');
    }
    $d = $e->modInverse($phi);

    return ['n' => $n, 'e' => $e, 'd' => $d];
}


// Inisialisasi variabel
$errors = [];
$post_data = [];
$results = [];
$save_success = false;
$save_error = false;
$username = $_SESSION['username'];

// Proses enkripsi
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['encrypt'])) {

    $data_label = isset($_POST['data_label']) ? trim($_POST['data_label']) : '';
    $nama = isset($_POST['nama']) ? trim($_POST['nama']) : '';
    $telepon = isset($_POST['telepon']) ? trim($_POST['telepon']) : '';
    $tempat_lahir = isset($_POST['tempat_lahir']) ? trim($_POST['tempat_lahir']) : '';
    $tanggal_lahir = isset($_POST['tanggal_lahir']) ? trim($_POST['tanggal_lahir']) : ''; // type date tidak perlu trim, tapi good practice
    $alamat = isset($_POST['alamat']) ? trim($_POST['alamat']) : '';
    $pesan_bebas = isset($_POST['pesan_bebas']) ? trim($_POST['pesan_bebas']) : '';

    $kunci_rc4 = isset($_POST['kunci_rc4_datadiri']) ? trim($_POST['kunci_rc4_datadiri']) : '';
    $kunci_vig = isset($_POST['kunci_vigenere']) ? trim($_POST['kunci_vigenere']) : '';
    $rsa_p = isset($_POST['rsa_p']) ? trim($_POST['rsa_p']) : '';
    $rsa_q = isset($_POST['rsa_q']) ? trim($_POST['rsa_q']) : '';
    $rsa_e = isset($_POST['rsa_e']) ? trim($_POST['rsa_e']) : '';

    $post_data = [
        'data_label' => $data_label,
        'nama' => $nama,
        'telepon' => $telepon,
        'tempat_lahir' => $tempat_lahir,
        'tanggal_lahir' => $tanggal_lahir,
        'alamat' => $alamat,
        'pesan_bebas' => $pesan_bebas,
        'kunci_rc4_datadiri' => $kunci_rc4,
        'kunci_vigenere' => $kunci_vig,
        'rsa_p' => $rsa_p,
        'rsa_q' => $rsa_q,
        'rsa_e' => $rsa_e
    ];

    if (empty($data_label)) {
        $errors[] = "Label Data tidak boleh kosong. Ini wajib untuk nama data Anda.";
    }

    if (!empty($_POST['nama']) && empty($nama)) $errors[] = "Nama tidak boleh diisi spasi saja.";
    if (!empty($_POST['telepon']) && empty($telepon)) $errors[] = "Nomor Telepon tidak boleh diisi spasi saja.";
    if (!empty($_POST['tempat_lahir']) && empty($tempat_lahir)) $errors[] = "Tempat Lahir tidak boleh diisi spasi saja.";
    if (!empty($_POST['alamat']) && empty($alamat)) $errors[] = "Alamat tidak boleh diisi spasi saja.";

    if (!empty($_POST['pesan_bebas']) && empty($pesan_bebas)) $errors[] = "Catatan Rahasia tidak boleh diisi spasi saja.";


    $isDataDiriFilled = !empty($nama) || !empty($telepon) || !empty($tempat_lahir) || !empty($tanggal_lahir) || !empty($alamat);
    $isPesanBebasFilled = !empty($pesan_bebas);

    if (!$isDataDiriFilled && !$isPesanBebasFilled) {
        $errors[] = "Data Pribadi atau Catatan Rahasia harus diisi untuk dikunci.";
    }

    if (!empty($_POST['kunci_rc4_datadiri']) && empty($kunci_rc4)) $errors[] = "Kunci Rahasia (Data Pribadi) tidak boleh diisi spasi saja.";
    if (!empty($_POST['kunci_vigenere']) && empty($kunci_vig)) $errors[] = "Kunci Lapis 1 (Vigenere) tidak boleh diisi spasi saja.";
    if (!empty($_POST['rsa_p']) && empty($rsa_p)) $errors[] = "Parameter RSA (p) tidak boleh diisi spasi saja.";
    if (!empty($_POST['rsa_q']) && empty($rsa_q)) $errors[] = "Parameter RSA (q) tidak boleh diisi spasi saja.";
    if (!empty($_POST['rsa_e']) && empty($rsa_e)) $errors[] = "Parameter RSA (e) tidak boleh diisi spasi saja.";


    if ($isDataDiriFilled && empty($kunci_rc4)) {
        $errors[] = "Kunci Rahasia (Data Pribadi) tidak boleh kosong jika Data Pribadi diisi.";
    }

    if ($isPesanBebasFilled) {
        if (empty($kunci_vig)) $errors[] = "Kunci Lapis 1 (Vigenere) tidak boleh kosong jika Catatan Rahasia diisi.";
        if (empty($rsa_p)) $errors[] = "Parameter RSA (p) tidak boleh kosong jika Catatan Rahasia diisi.";
        if (empty($rsa_q)) $errors[] = "Parameter RSA (q) tidak boleh kosong jika Catatan Rahasia diisi.";
        if (empty($rsa_e)) $errors[] = "Parameter RSA (e) tidak boleh kosong jika Catatan Rahasia diisi.";
    }

    if ($isDataDiriFilled) {
        if (!empty($nama) && !preg_match('/^[a-zA-Z\s\'.\-]+$/', $nama)) $errors[] = "Nama hanya boleh berisi huruf, spasi, titik, dan apostrof.";

        if (!empty($telepon) && !preg_match('/^[0-9\+\-\s\(\)]+$/', $telepon)) {
            $errors[] = "Nomor Telepon hanya boleh berisi angka, spasi, dan simbol (+, -, (, )).";
        }

        if (!empty($tempat_lahir) && !preg_match('/[a-zA-Z]/', $tempat_lahir)) $errors[] = "Tempat Lahir harus berisi setidaknya satu huruf (tidak boleh hanya angka/simbol).";
    }

    if ($isPesanBebasFilled) {
        if (!empty($kunci_vig) && !preg_match('/^[a-zA-Z]+$/', $kunci_vig)) {
            $errors[] = "Kunci Lapis 1 (Vigenere) hanya boleh berisi huruf (A-Z).";
        }
        if (!empty($pesan_bebas) && !preg_match('/^[a-zA-Z\s]+$/', $pesan_bebas)) {
            $errors[] = "Catatan Rahasia (Vigenere) hanya boleh berisi huruf (A-Z) dan spasi.";
        }
    }

    // kalau ga eror, lanjut enkrip
    if (empty($errors)) {

        $data_to_save = [
            'username' => $username,
            'data_label' => $data_label,
            'enc_nama' => null,
            'enc_telepon' => null,
            'enc_tempat_lahir' => null,
            'enc_tanggal_lahir' => null,
            'enc_alamat' => null,
            'enc_pesan_bebas' => null
        ];

        if ($isDataDiriFilled) {
            try {
                $rc4_datadiri = new RC4(); // algoritma rc4
                $rc4_datadiri->setKey($kunci_rc4);

                $fields_to_encrypt = [
                    'Nama' => $nama,
                    'Telepon' => $telepon,
                    'Tempat Lahir' => $tempat_lahir,
                    'Tanggal Lahir' => $tanggal_lahir,
                    'Alamat' => $alamat
                ];

                $encrypted_fields_hex = [];
                foreach ($fields_to_encrypt as $key => $value) {
                    if (!empty($value)) {
                        $encrypted_raw = $rc4_datadiri->encrypt($value);
                        $hex_value = bin2hex($encrypted_raw);
                        $encrypted_fields_hex[$key] = $hex_value;

                        $db_col = 'enc_' . strtolower(str_replace(' ', '_', $key));
                        if (array_key_exists($db_col, $data_to_save)) {
                            $data_to_save[$db_col] = $hex_value;
                        }
                    } else {
                        $encrypted_fields_hex[$key] = null;
                    }
                }

                $results['data_diri'] = [
                    'title' => 'Data Pribadi (Terkunci)',
                    'kunci' => $kunci_rc4,
                    'ciphertexts' => $encrypted_fields_hex, // Ini mungkin berisi null
                    'steps' => [
                        "<b>1. Penyiapan Kunci:</b> Kunci Rahasia (yang Anda masukkan) digunakan untuk KSA (Key-Scheduling Algorithm).",
                        "<b>2. Penguncian Field-by-Field:</b> Objek RC4 yang sama (dengan keystream berkelanjutan) digunakan untuk mengunci 'Nama', lalu 'Telepon', 'Tempat Lahir', 'Tanggal Lahir', dan 'Alamat' (jika diisi).",
                        "<b>3. Finalisasi:</b> Setiap hasil biner di-encode ke Heksadesimal."
                    ]
                ];
            } catch (Exception $e) {
                $errors[] = "Gagal mengunci Data Pribadi: " . $e->getMessage();
            }
        }

        if ($isPesanBebasFilled) {
            try {
                $vigenere_result = vigenere_encrypt($pesan_bebas, $kunci_vig);

                $rsa = rsa_build_components($rsa_p, $rsa_q, $rsa_e);

                $nBits = strlen($rsa['n']->toBits());
                $k = (int)ceil($nBits / 8);
                $mBlockMax = max(1, $k - 1);
                $cipher_numbers = [];
                for ($i = 0; $i < strlen($vigenere_result); $i += $mBlockMax) {
                    $chunk = substr($vigenere_result, $i, $mBlockMax);
                    $m = new BigInteger($chunk, 256);
                    if ($m->compare($rsa['n']) >= 0) {
                        throw new Exception('Blok pesan >= n. Gunakan p,q lebih besar.');
                    }
                    $c = $m->powMod($rsa['e'], $rsa['n']);
                    $cipher_numbers[] = $c->toString();
                }
                $cipher_serialized = implode(' ', $cipher_numbers);

                $data_to_save['enc_pesan_bebas'] = $cipher_serialized;

                $results['pesan_bebas'] = [
                    'title' => 'Catatan Rahasia (Perlindungan Ganda: Vigenere + RSA)',
                    'ciphertext' => $cipher_serialized,
                    'steps' => [
                        "<b>1. Input Catatan:</b><br><span class='step-data'>" . htmlspecialchars($pesan_bebas) . "</span>",
                        "<b>2. Kunci Lapis 1 (Vigenere):</b> Catatan dikunci dengan Vigenere (Kunci: " . htmlspecialchars($kunci_vig) . ").<br><b>Hasil Vigenere:</b> <span class='step-data'>" . htmlspecialchars($vigenere_result) . "</span>",
                        "<b>3. Penyiapan Kunci RSA:</b> Hitung n = p*q, phi=(p-1)(q-1), verifikasi gcd(e, phi)=1, hitung d = e^{-1} mod phi.",
                        "<b>4. Kunci Lapis 2 (RSA):</b> Hasil Vigenere dikunci lagi per blok dengan RSA (tanpa padding).",
                        "<b>5. Finalisasi:</b> Teks terkunci diserialisasi dengan pemisah spasi."
                    ]
                ];
            } catch (Exception $e) {
                $errors[] = "Gagal mengunci Catatan Rahasia: " . $e->getMessage();
            }
        }

        if (empty($errors) && !empty($results)) {

            $simpan_result = simpanDataEnkripsiTerpisah($konek, $data_to_save);

            if ($simpan_result === true) {
                $save_success = "Sukses! Data baru dengan label '" . htmlspecialchars($data_to_save['data_label']) . "' berhasil disimpan di brankas Anda.";
            } else {
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
    <title>Tulis Catatan Baru - Brankas Pribadi</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/textED.css">
</head>

<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-content">
                <h1>Kunci & Simpan Catatan Baru</h1>
                <div class="user-info">
                    <span class="welcome-text">User: <strong><?php echo htmlspecialchars($username); ?></strong></span>
                    <a href="../php/logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </header>

        <main class="dashboard-main">
            <div class="encryption-container">
                <a href="../dashboard.php" class="back-link">← Kembali ke Dashboard</a>

                <h2 style="color: #e29fa6; margin-bottom: 1rem;">Tulis Catatan Baru ke Brankas Pribadi</h2>
                <p style="color: #666; margin-bottom: 2rem;">Isi data yang ingin kamu amankan. Data akan dikunci (dienkripsi) dan disimpan sebagai *data baru* di brankasmu.</p>

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

                <?php if ($save_success): ?>
                    <div class="alert-db-success"><?php echo htmlspecialchars($save_success); ?></div>
                <?php endif; ?>

                <?php if ($save_error): ?>
                    <div class="alert-db-error"><?php echo htmlspecialchars($save_error); ?></div>
                <?php endif; ?>

                <form method="POST" action="">

                    <fieldset>
                        <legend>Informasi Data</legend>
                        <div class="form-group">
                            <label for="data_label">Label Catatan (Wajib Diisi):</label>
                            <input type="text" name="data_label" id="data_label" placeholder="Misal: Data Pribadi Cadangan, Catatan Meeting 1, dll." value="<?php echo htmlspecialchars($post_data['data_label'] ?? ''); ?>" required>
                            <p class="info-text">Beri nama data ini agar Anda mudah mengenalinya di halaman dekripsi.</p>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Bagian A: Data Pribadi (Kunci Simetris RC4)</legend>
                        <p class="info-text" style="margin-bottom: 1rem;">Data ini akan dikunci per-field menggunakan RC4 dan 1 kunci.</p>

                        <div class="form-group">
                            <label for="kunci_rc4_datadiri">Kunci Rahasia (Data Pribadi):</label>
                            <input type="text" name="kunci_rc4_datadiri" id="kunci_rc4_datadiri" placeholder="Buat sebuah kunci untuk data pribadimu" value="<?php echo htmlspecialchars($post_data['kunci_rc4_datadiri'] ?? ''); ?>">
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

                    <fieldset>
                        <legend>Bagian B: Catatan Rahasia (Perlindungan Ganda Vigenere + RSA)</legend>
                        <p class="info-text" style="margin-bottom: 1rem;">Catatan ini akan dikunci 2 lapis (Vigenere lalu RSA). Masukkan p, q (bilangan prima besar) dan e sedemikian rupa sehingga gcd(e, (p-1)(q-1)) = 1.</p>

                        <div class="form-group">
                            <label for="kunci_vigenere">Kunci Lapis 1 (Vigenere - Hanya Huruf):</label>
                            <input type="text" name="kunci_vigenere" id="kunci_vigenere" placeholder="Masukkan kata kunci (hanya huruf, misal: RAHASIA)" value="<?php echo htmlspecialchars($post_data['kunci_vigenere'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="rsa_p">RSA p (prima):</label>
                            <input type="text" name="rsa_p" id="rsa_p" placeholder="Contoh: bilangan prima besar" value="<?php echo htmlspecialchars($post_data['rsa_p'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="rsa_q">RSA q (prima):</label>
                            <input type="text" name="rsa_q" id="rsa_q" placeholder="Contoh: bilangan prima besar" value="<?php echo htmlspecialchars($post_data['rsa_q'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="rsa_e">RSA e (eksponen publik):</label>
                            <input type="text" name="rsa_e" id="rsa_e" placeholder="Contoh: 65537" value="<?php echo htmlspecialchars($post_data['rsa_e'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="pesan_bebas">Isi Catatan Rahasia (Hanya Huruf & Spasi):</label>
                            <textarea name="pesan_bebas" id="pesan_bebas" placeholder="Tulis catatan rahasia apa saja di sini..."><?php echo htmlspecialchars($post_data['pesan_bebas'] ?? ''); ?></textarea>
                        </div>
                    </fieldset>


                    <div class="btn-group">
                        <button type="submit" name="encrypt" class="btn btn-primary" style="background-color: #28a745; border-color: #28a745;">Kunci & Simpan ke Brankas</button>
                        <a href="../dashboard.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>

                <!-- hasil enkrip kalau sukses -->
                <?php if (!empty($results)): ?>
                    <div class="result-group">
                        <h2 style="color: #e29fa6; margin-bottom: 1rem;">Data Berhasil Dikunci</h2>

                        <?php if (isset($results['data_diri'])):
                            $res_dd = $results['data_diri'];
                        ?>
                            <fieldset>
                                <legend><?php echo htmlspecialchars($res_dd['title']); ?></legend>

                                <?php foreach ($res_dd['ciphertexts'] as $field_name => $ciphertext): ?>
                                    <?php if ($ciphertext !== null): ?>
                                        <div class="result-box-label"><?php echo htmlspecialchars($field_name); ?>:</div>
                                        <div class="result-box"><?php echo htmlspecialchars($ciphertext); ?></div>
                                    <?php endif; ?>
                                <?php endforeach; ?>

                                <div class="result-steps">
                                    <h4>Proses Penguncian (Data Pribadi):</h4>
                                    <?php
                                    if (isset($res_dd['steps']) && is_array($res_dd['steps'])) {
                                        foreach ($res_dd['steps'] as $step): echo "<p>$step</p>";
                                        endforeach;
                                    }
                                    ?>
                                </div>
                            </fieldset>
                        <?php endif; ?>

                        <?php if (isset($results['pesan_bebas'])):
                            $res_pb = $results['pesan_bebas'];
                        ?>
                            <fieldset style="margin-top: 2rem;">
                                <legend><?php echo htmlspecialchars($res_pb['title']); ?></legend>

                                <div class="result-box-label">Teks Terkunci (Vigenere + RSA):</div>
                                <div class="result-box"><?php echo htmlspecialchars($res_pb['ciphertext']); ?></div>

                                <div class="result-steps">
                                    <h4>Proses Penguncian (Catatan Rahasia):</h4>
                                    <?php
                                    if (isset($res_pb['steps']) && is_array($res_pb['steps'])) {
                                        foreach ($res_pb['steps'] as $step): echo "<p>$step</p>";
                                        endforeach;
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