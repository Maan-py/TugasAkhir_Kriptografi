<?php
session_start();

// Jika user belum login
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link rel="stylesheet" href="dashboard.css">
</head>

<body>

    <div class="container">
        <h1 class="header">Kripto K-nya Karten</h1>

        <table border="1">
            <tr>
                <td><a href="text-encryption.php">Text encryption</a></td>
                <td><a href="text-decryption.php">Text decryption</a></td>
            </tr>
            <tr>
                <td><a href="stegano-encryption.php">Stegano encryption</a></td>
                <td><a href="stegano-decryption.php">Stegano decryption</a></td>
            </tr>
            <tr>
                <td><a href="file-encryption.php">File encryption</a></td>
                <td><a href="file-decryption.php">File decryption</a></td>
            </tr>
        </table>
    </div>

    <!-- Tombol logout -->
    <form action="logout.php" method="POST">
        <button class="loginButton logout">
            <h3>logout</h3>
            <div class="buttonBackground"></div>
        </button>
    </form>

</body>

</html>