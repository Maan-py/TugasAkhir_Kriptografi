<?php
session_start(); // mengaktifkan session
session_destroy(); // menghapus semua session
header("location:../index.php?pesan=logout");
