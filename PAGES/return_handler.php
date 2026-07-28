<?php
session_start();

// 1. Dito mo itatanim kung ano man ang hinahanap na SECURITY SESSION ng dashboard.php mo
// Halimbawa, kung ito ang nagpapaputol sa security ng dashboard mo:
$_SESSION['admin_logged_in'] = true; 
$_SESSION['user_role'] = 'admin'; // Baguhin mo base sa totoong session name mo

// 2. Pagkatapos maitanim ang susi, i-redirect na agad sa dashboard
header("Location: dashboard.php");

exit();
?>