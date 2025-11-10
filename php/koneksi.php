<?php 

$host = "localhost";
$user = "root";
$pass = "";
$db = "TA_Kripto";

$konek = mysqli_connect($host, $user, $pass, $db);

if (mysqli_connect_errno()) {
    $_SESSION['error_koneksi_db'] = "Gagal terhubung ke database: " . mysqli_connect_error();
    header("Location: ../register.php"); 
    exit(); 
}

mysqli_set_charset($konek, "utf8");
?>