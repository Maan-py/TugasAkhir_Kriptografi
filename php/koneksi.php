<?php
$hostname = "localhost"; //hostname
$username = "root"; //username untuk login ke mysql
$password = ""; //password untuk login ke mysql
$database = "TA_Kripto"; //nama database

$konek = new mysqli($hostname,$username,$password, $database);

if ($konek->connect_error){
    die('Maaf koneksi gagal: '. $konek->connect_error);
}
?>
