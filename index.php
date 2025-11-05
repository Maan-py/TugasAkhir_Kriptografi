<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login</title>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: Arial, sans-serif;
    }

    body {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      background: linear-gradient(135deg, #f9cdd2, #f3a6b0);
      color: #333;
    }

    .container {
      background-color: #fff;
      padding: 2rem;
      border-radius: 8px;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
      width: 100%;
      max-width: 400px;
      text-align: center;
    }

    h2 {
      color: #e29fa6;
      margin-bottom: 1rem;
    }

    .message {
      color: #d9534f;
      font-size: 0.9rem;
      margin-bottom: 1rem;
    }

    .form-group {
      margin-bottom: 1rem;
      text-align: left;
    }

    label {
      display: block;
      margin-bottom: 0.3rem;
      font-weight: bold;
      color: #b56576;
    }

    input[type="text"],
    input[type="password"] {
      width: 100%;
      padding: 0.8rem;
      margin: 0.3rem 0;
      border: 1px solid #f3b7c0;
      border-radius: 5px;
      outline: none;
      font-size: 1rem;
    }

    input[type="text"]:focus,
    input[type="password"]:focus {
      border-color: #e29fa6;
    }

    input[type="submit"] {
      width: 100%;
      background-color: #f28c98;
      color: #fff;
      border: none;
      padding: 0.8rem;
      font-size: 1rem;
      border-radius: 5px;
      cursor: pointer;
      transition: background 0.3s ease;
    }

    input[type="submit"]:hover {
      background-color: #e0717d;
    }
  </style>
</head>

<body>
  <div class="container">
    <h2>Login</h2>
    <?php
    if (isset($_GET['pesan'])) {
      echo "<div class='message'>";
      if ($_GET['pesan'] == "gagal_login") {
        echo "Login gagal! Username atau password salah!";
      } else if ($_GET['pesan'] == "logout") {
        echo "Anda telah berhasil logout.";
      } else if ($_GET['pesan'] == "belum_login") {
        echo "Anda harus login untuk mengakses halaman lainnya.";
      } else if ($_GET['pesan'] == "sukses_regist") {
        echo "Registrasi berhasil! Silakan login.";
      } else if ($_GET['pesan'] == "sukses_login") {
        echo "Login berhasil!";
      }
      echo "</div>";
    }
    ?>
    <form method="POST" action="php/cek_login.php">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" placeholder="Masukkan username" required />
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="Masukkan password" required />
      </div>
      <div class="mb-3">
        <p>Don't have an account? Register, <a href="register.php"> here.</a></p>
      </div>
      <input type="submit" value="LOGIN" />
    </form>
  </div>
</body>

</html>