<?php
session_start();

// 1. Linisin ang lahat ng session variables
$_SESSION = array();

// 2. Burahin ang session cookie kung mayroon man para sa seguridad
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Tuluyan nang sirain ang session
session_destroy();

// 4. Pigilan ang browser caching para hindi na mabalikan ng user gamit ang "Back" button ng browser
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 5. I-redirect pabalik sa login page
header("Location: ../PAGES/login.php");
exit();
?>