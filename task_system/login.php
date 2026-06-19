<?php
include "config.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    $result = $conn->query("
        SELECT *
        FROM users
        WHERE username = '$username'
        AND active = 1
        LIMIT 1
    ");

    if ($result && $result->num_rows == 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['ime'] = $user['ime'];
            $_SESSION['role'] = $user['role'];

            header("Location: index.php");
            exit;
        }
    }

    $error = "Pogrešno korisničko ime ili lozinka.";
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Login</title>
</head>
<body style="font-family:Arial; background:#f2f2f2;">

<div style="
    width:360px;
    margin:100px auto;
    background:white;
    padding:25px;
    border-radius:8px;
    box-shadow:0 2px 8px rgba(0,0,0,0.15);
">

<h2>Task System Login</h2>

<?php if ($error): ?>
    <p style="color:red;"><?= $error ?></p>
<?php endif; ?>

<form method="POST">

    <label>Korisničko ime</label><br>
    <input type="text" name="username" required style="width:100%;padding:8px;">

    <br><br>

    <label>Lozinka</label><br>
    <input type="password" name="password" required style="width:100%;padding:8px;">

    <br><br>

    <button type="submit" style="
        width:100%;
        padding:10px;
        background:#222;
        color:white;
        border:none;
        border-radius:5px;
    ">
        Prijavi se
    </button>

</form>

</div>

</body>
</html>