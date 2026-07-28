<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../PANEL/login.php");
    exit();
}

include_once "./db_inventory.php";

$ingredient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$category_id   = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 1;

if ($ingredient_id > 0 && isset($conn)) {
    $delete_stmt = $conn->prepare("DELETE FROM ingredients WHERE id = ?");
    $delete_stmt->bind_param("i", $ingredient_id);
    $delete_stmt->execute();
    $delete_stmt->close();
}

if (isset($conn)) {
    $conn->close();
}

// Bumalik sa parehong kategorya sa inventory.php
header("Location: ../PANEL/inventory.php?category_id=" . $category_id);
exit();
?>