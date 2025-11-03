<?php
// File: php/simpan_textEnkrip.php

/**
 * Fungsi untuk menyimpan data terenkripsi ke database (Tabel usersData v2).
 * Menggunakan INSERT INTO untuk data baru.
 *
 * @param mysqli $konek Koneksi database
 * @param array $data_to_save Array asosiatif berisi data yang sudah siap simpan
 * @return bool|string True jika sukses, string error jika gagal
 */
function simpanDataEnkripsi(mysqli $konek, array $data_to_save): bool|string
{
    // Query untuk memasukkan data baru (sesuai tabel v2 kita)
    $sql = "INSERT INTO usersData (
                username, data_label, 
                enc_nama, enc_telepon, enc_tempat_lahir, 
                enc_tanggal_lahir, enc_alamat, enc_pesan_bebas
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $konek->prepare($sql);
    
    if (!$stmt) {
        // Jika Gagal 'prepare', kembalikan pesan error
        return "Gagal menyimpan: Gagal 'prepare statement'.";
    }

    // Bind 8 parameter (s = string)
    $stmt->bind_param("ssssssss", 
        $data_to_save['username'],
        $data_to_save['data_label'],
        $data_to_save['enc_nama'],
        $data_to_save['enc_telepon'],
        $data_to_save['enc_tempat_lahir'],
        $data_to_save['enc_tanggal_lahir'],
        $data_to_save['enc_alamat'],
        $data_to_save['enc_pesan_bebas']
    );

    // Eksekusi
    if ($stmt->execute()) {
        // Jika sukses, tutup dan kembalikan true
        $stmt->close();
        return true;
    } else {
        // Jika gagal, kembalikan pesan error
        $error_msg = "Gagal menyimpan: Gagal 'execute statement'. " . $stmt->error;
        $stmt->close();
        return $error_msg;
    }
}
?>
