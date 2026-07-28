<?php
session_start();

// Which role is logging out? Passed explicitly by the page that owns the
// logout button (e.g. logout.php?role=admin or logout.php?role=staff),
// since guessing from session contents alone isn't reliable when both
// roles could be logged in at once in the same browser.
$role = isset($_GET['role']) ? $_GET['role'] : '';

if ($role === 'admin') {
    unset($_SESSION['admin_id']);
    // Only clear the shared 'role' marker if no staff session is still active
    if (!isset($_SESSION['user_id'])) {
        unset($_SESSION['role']);
    }
} elseif ($role === 'staff') {
    unset($_SESSION['user_id']);
    unset($_SESSION['fullname']);
    if (!isset($_SESSION['admin_id'])) {
        unset($_SESSION['role']);
    }
} else {
    // No role specified (old links, direct visits, etc.) -- fall back to
    // clearing everything, same as the original behavior.
    $_SESSION = array();
}

// Only destroy the actual PHP session + cookie once NOTHING is left logged in.
// This is what makes "clear your browser" or "log out of the last active role"
// still fully log you out, while logging out of ONE role while another is
// still active in a different tab leaves that other role untouched.
if (empty($_SESSION)) {
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

header("Location: login.php");
exit();
?>