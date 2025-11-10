<?php

/**
 * @param mysqli 
 * @param array 
 * @return bool|string 
 */

function simpanDataPribadi(mysqli $konek, array $data): int|false
{
    $sql = "INSERT INTO usersData (
                username, data_label, 
                enc_nama, enc_telepon, enc_tempat_lahir, 
                enc_tanggal_lahir, enc_alamat
            ) VALUES (?, ?, ?, ?, ?, ?, ?)"; // Dihilangkan enc_pesan_bebas

    $stmt = $konek->prepare($sql);
    if (!$stmt) return false;

    $stmt->bind_param("sssssss", 
        $data['username'],
        $data['data_label'],
        $data['enc_nama'],
        $data['enc_telepon'],
        $data['enc_tempat_lahir'],
        $data['enc_tanggal_lahir'],
        $data['enc_alamat']
    );

    if ($stmt->execute()) {
        $new_data_id = $konek->insert_id; 
        $stmt->close();
        return $new_data_id;
    } else {
        $stmt->close();
        return false;
    }
}


function simpanCatatanRahasia(mysqli $konek, int $data_id, string $username, ?string $enc_pesan_bebas): bool
{
    $sql = "INSERT INTO usersCatatan (
                data_id, username, enc_pesan_bebas
            ) VALUES (?, ?, ?)";
    
    $stmt = $konek->prepare($sql);
    if (!$stmt) return false;

    $stmt->bind_param("iss", 
        $data_id,
        $username,
        $enc_pesan_bebas
    );

    $eksekusi = $stmt->execute();
    $stmt->close();
    return $eksekusi;
}


/**
 * @param mysqli $konek Koneksi database
 * @param array $data_to_save Array asosiatif berisi data yang sudah siap simpan
 * @return bool|string True jika sukses, string error jika gagal
 */
function simpanDataEnkripsiTerpisah(mysqli $konek, array $data_to_save): bool|string
{
    $konek->begin_transaction();

    try {
        $data_pribadi = [
            'username' => $data_to_save['username'],
            'data_label' => $data_to_save['data_label'],
            'enc_nama' => $data_to_save['enc_nama'],
            'enc_telepon' => $data_to_save['enc_telepon'],
            'enc_tempat_lahir' => $data_to_save['enc_tempat_lahir'],
            'enc_tanggal_lahir' => $data_to_save['enc_tanggal_lahir'],
            'enc_alamat' => $data_to_save['enc_alamat']
        ];
        
        $new_data_id = simpanDataPribadi($konek, $data_pribadi);
        
        if ($new_data_id === false) {
            throw new Exception("Gagal menyimpan data pribadi ke usersData.");
        }

        $pesan_bebas_terenkripsi = $data_to_save['enc_pesan_bebas'] ?? null;
        
        $sukses_catatan = simpanCatatanRahasia(
            $konek, 
            $new_data_id, // Gunakan ID baru dari Langkah 1
            $data_to_save['username'], 
            $pesan_bebas_terenkripsi
        );

        if (!$sukses_catatan) {
            throw new Exception("Gagal menyimpan catatan rahasia ke usersCatatan.");
        }
        
        $konek->commit();
        return true;

    } catch (Exception $e) {
        $konek->rollback();
        return "Gagal menyimpan: " . $e->getMessage();
    }
}
?>
