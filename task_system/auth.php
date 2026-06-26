<?php
/* auth.php
   Ovaj fajl ostaje zbog kompatibilnosti, ali login zaštita je sada centralno u config.php.
   Ako neki fajl već uključuje config.php, ne mora dodatno da uključuje auth.php.
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
