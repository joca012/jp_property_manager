<?php
include "config.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Unesite korisničko ime i lozinku.";
    } else {

        $stmt = $conn->prepare("
            SELECT id, username, password_hash, ime, role, active
            FROM users
            WHERE username = ?
            LIMIT 1
        ");

        if (!$stmt) {
            $error = "Greška u pripremi upita.";
        } else {
            $stmt->bind_param("s", $username);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();

                if ((int)$user['active'] === 1 && password_verify($password, $user['password_hash'])) {

                    session_regenerate_id(true);

                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['ime'] = $user['ime'];
                    $_SESSION['role'] = $user['role'];

                    header("Location: index.php");
                    exit;
                }
            }

            $error = "Pogrešno korisničko ime ili lozinka.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Task System - Login</title>

<style>
body {
    font-family: Arial, sans-serif;
    background: #f2f2f2;
    margin: 0;
}

.login-box {
    width: 360px;
    margin: 100px auto;
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

h2 {
    margin-top: 0;
}

input {
    width: 100%;
    padding: 9px;
    box-sizing: border-box;
    margin-top: 5px;
}

button {
    width: 100%;
    padding: 10px;
    background: #222;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}

.error {
    background: #f8d7da;
    color: #842029;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 15px;
}
</style>
</head>

<body>

<div class="login-box">

<h2>Task System</h2>

<?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">

    <label>Korisničko ime</label>
    <input type="text" name="username" required autofocus>

    <br><br>

    <label>Lozinka</label>
    <input type="password" name="password" required>

    <br><br>

    <button type="submit">Prijavi se</button>

</form>

</div>

</body>
</html>
