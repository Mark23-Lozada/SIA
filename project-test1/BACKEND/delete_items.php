<?php
// delete_item.php
require_once __DIR__ . '/db_inventory.php';

if (isset($_GET['id']) && isset($_GET['category_id'])) {
    $id = (int)$_GET['id'];
    $category_id = (int)$_GET['category_id'];

    $stmt = $conn->prepare("DELETE FROM items WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // Ibalik ang user sa parehong kategorya sa inventory pagkatapos magbura
    header("Location: ../FRONTEND/inventory.php?category_id=" . $category_id);
    exit();
}
$conn->close();
?>