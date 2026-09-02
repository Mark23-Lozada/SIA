<?php
include('../BACKEND/db_inventory.php');

session_start();

// Tamang redirection papunta sa login.php na nasa PAGES folder
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'cashier' && $_SESSION['role'] !== 'admin')) {
    header("Location: /HRMS/PAGES/login.php");
    exit();
}

date_default_timezone_set('Asia/Manila');

function respond_json($payload) {
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit();
}

function modifier_multiplier($value) {
    if ($value === 'remove') return 0;
    if ($value === 'extra') return 2;
    return 1;
}

// ===================================================================
// FETCH BEST SELLERS FROM SALES HISTORY (Dagdagan ang limit para masulit ang pag-slide)
// ===================================================================
$best_sellers_array = [];
$best_sellers_query = "
    SELECT items.id, items.item_name, items.image, items.price, SUM(sales_items.quantity) as total_qty
    FROM sales_items
    JOIN items ON sales_items.item_id = items.id
    WHERE items.is_available = 1
    GROUP BY sales_items.item_id
    ORDER BY total_qty DESC
    LIMIT 10";
$best_sellers_result = $conn->query($best_sellers_query);
if ($best_sellers_result) {
    while ($row = $best_sellers_result->fetch_assoc()) {
        $best_sellers_array[] = [
            'id'    => (int)$row['id'],
            'name'  => $row['item_name'],
            'image' => $row['image'] ? '../' . $row['image'] : null,
            'price' => (float)$row['price'],
            'qty'   => (int)$row['total_qty']
        ];
    }
}

// ===================================================================
// CHECKOUT PAGE DATA (categories, menu items, and their live availability)
// ===================================================================

$categories_array = [];
$categories_result = $conn->query(
    "SELECT DISTINCT categories.*
     FROM categories
     JOIN items ON items.category_id = categories.id
     ORDER BY categories.id ASC"
);
if ($categories_result) {
    while ($cat = $categories_result->fetch_assoc()) {
        $categories_array[] = $cat;
    }
}

$recipe_by_item = [];
$recipe_result = $conn->query(
    "SELECT mii.item_id, mii.ingredient_id, ing.ingredient_name, ing.unit, mii.quantity_used, ing.stock AS ingredient_stock
     FROM menu_item_ingredients mii
     JOIN ingredients ing ON mii.ingredient_id = ing.id"
);
if ($recipe_result) {
    while ($row = $recipe_result->fetch_assoc()) {
        $recipe_by_item[(int)$row['item_id']][] = [
            'ingredient_id'    => (int)$row['ingredient_id'],
            'ingredient_name'  => $row['ingredient_name'],
            'unit'             => $row['unit'],
            'quantity_used'    => (float)$row['quantity_used'],
            'ingredient_stock' => (float)$row['ingredient_stock']
        ];
    }
}

$products_json = [];
$items_result = $conn->query(
    "SELECT items.id, items.item_name, items.image, items.price, categories.name AS cat_name
     FROM items
     JOIN categories ON items.category_id = categories.id
     WHERE items.is_available = 1"
);
if ($items_result) {
    while ($item = $items_result->fetch_assoc()) {
        $item_id   = (int)$item['id'];
        $clean_cat = strtolower(str_replace(' ', '', $item['cat_name']));
        $has_recipe = isset($recipe_by_item[$item_id]);

        $available_qty = 0;
        $recipe_for_frontend = [];
        if ($has_recipe) {
            $available_qty = PHP_INT_MAX;
            foreach ($recipe_by_item[$item_id] as $line) {
                $makable_from_this_ingredient = floor($line['ingredient_stock'] / $line['quantity_used']);
                $available_qty = min($available_qty, $makable_from_this_ingredient);

                $recipe_for_frontend[] = [
                    'ingredient_id' => $line['ingredient_id'],
                    'name'          => $line['ingredient_name'],
                    'unit'          => $line['unit']
                ];
            }
        }

        $products_json[] = [
            'id'         => $item_id,
            'name'       => $item['item_name'],
            'image'      => $item['image'] ? '../' . $item['image'] : null,
            'price'      => (float)$item['price'],
            'available'  => (int)$available_qty,
            'has_recipe' => $has_recipe,
            'recipe'     => $recipe_for_frontend,
            'category'   => $clean_cat,
            'cat_original' => $item['cat_name']
        ];
    }
}

// ===================================================================
// PROCESS A SALE
// ===================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'deduct_stock') {
    $cart_data = json_decode($_POST['cart_items'], true);

    if (empty($cart_data)) {
        exit();
    }

    $total_amount = 0;
    foreach ($cart_data as $item) {
        $total_amount += (float)$item['price'] * (int)$item['quantity'];
    }

    $payment_method      = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'Cash';
    $card_digits         = isset($_POST['card_digits']) ? trim($_POST['card_digits']) : '';
    $final_payment_label = ($payment_method === 'Card') ? "Card (**** $card_digits)" : $payment_method;

    if ($payment_method === 'Cash') {
        $amount_tendered = isset($_POST['amount_tendered']) ? (float)$_POST['amount_tendered'] : $total_amount;

        if ($amount_tendered < $total_amount || $amount_tendered > ($total_amount + 1000)) {
            respond_json(["status" => "error", "message" => "Invalid payment amount processed!"]);
        }
        $change = $amount_tendered - $total_amount;
    } else {
        $amount_tendered = $total_amount;
        $change = 0;
    }

    $required_by_ingredient = [];
    $stmt_recipe = $conn->prepare("SELECT ingredient_id, quantity_used FROM menu_item_ingredients WHERE item_id = ?");
    $stmt_item_name = $conn->prepare("SELECT item_name FROM items WHERE id = ?");
    foreach ($cart_data as $item) {
        $qty       = (int)$item['quantity'];
        $id        = (int)$item['id'];
        $modifiers = isset($item['modifiers']) && is_array($item['modifiers']) ? $item['modifiers'] : [];

        $stmt_recipe->bind_param("i", $id);
        $stmt_recipe->execute();
        $res = $stmt_recipe->get_result();

        if ($res->num_rows === 0) {
            $stmt_item_name->bind_param("i", $id);
            $stmt_item_name->execute();
            $name_row = $stmt_item_name->get_result()->fetch_assoc();
            $item_label = $name_row ? $name_row['item_name'] : "Item #$id";
            respond_json(["status" => "error", "message" => "$item_label has no recipe configured and cannot be sold yet."]);
        }

        while ($row = $res->fetch_assoc()) {
            $ingredient_id = (int)$row['ingredient_id'];
            $modifier      = $modifiers[$ingredient_id] ?? ($modifiers[(string)$ingredient_id] ?? 'normal');
            $needed = (float)$row['quantity_used'] * $qty * modifier_multiplier($modifier);
            if ($needed > 0) {
                $required_by_ingredient[$ingredient_id] = ($required_by_ingredient[$ingredient_id] ?? 0) + $needed;
            }
        }
    }
    $stmt_recipe->close();
    $stmt_item_name->close();

    if (!empty($required_by_ingredient)) {
        $ids = array_keys($required_by_ingredient);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmt_check = $conn->prepare("SELECT id, ingredient_name, stock FROM ingredients WHERE id IN ($placeholders)");
        $stmt_check->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stmt_check->execute();
        $check_result = $stmt_check->get_result();

        while ($row = $check_result->fetch_assoc()) {
            $needed = $required_by_ingredient[(int)$row['id']];
            if ((float)$row['stock'] < $needed) {
                respond_json(["status" => "error", "message" => "Not enough " . $row['ingredient_name'] . " in stock to complete this order."]);
            }
        }
        $stmt_check->close();
    }

    $conn->begin_transaction();
    try {
        $stmt_sale = $conn->prepare("INSERT INTO sales (total_amount, amount_tendered, `change`, payment_method) VALUES (?, ?, ?, ?)");
        $stmt_sale->bind_param("ddds", $total_amount, $amount_tendered, $change, $final_payment_label);
        $stmt_sale->execute();
        $sale_id = $conn->insert_id;
        $stmt_sale->close();

        $stmt_insert_sale_item = $conn->prepare("INSERT INTO sales_items (sale_id, item_id, quantity, price_at_sale) VALUES (?, ?, ?, ?)");
        $stmt_get_recipe        = $conn->prepare("SELECT ingredient_id, quantity_used FROM menu_item_ingredients WHERE item_id = ?");
        $stmt_deduct_ingredient = $conn->prepare("UPDATE ingredients SET stock = stock - ? WHERE id = ? AND stock >= ?");
        $stmt_get_prep_time     = $conn->prepare("SELECT prep_time_minutes FROM items WHERE id = ?");

        $max_prep_minutes = 0;

        foreach ($cart_data as $item) {
            $qty       = (int)$item['quantity'];
            $id        = (int)$item['id'];
            $price     = (float)$item['price'];
            $modifiers = isset($item['modifiers']) && is_array($item['modifiers']) ? $item['modifiers'] : [];

            $stmt_insert_sale_item->bind_param("iiid", $sale_id, $id, $qty, $price);
            $stmt_insert_sale_item->execute();

            $stmt_get_prep_time->bind_param("i", $id);
            $stmt_get_prep_time->execute();
            $prep_row = $stmt_get_prep_time->get_result()->fetch_assoc();
            $item_prep_minutes = $prep_row ? (int)$prep_row['prep_time_minutes'] : 10;
            $max_prep_minutes = max($max_prep_minutes, $item_prep_minutes);

            $stmt_get_recipe->bind_param("i", $id);
            $stmt_get_recipe->execute();
            $recipe_rows = $stmt_get_recipe->get_result();
            while ($recipe_row = $recipe_rows->fetch_assoc()) {
                $ingredient_id = (int)$recipe_row['ingredient_id'];
                $modifier      = $modifiers[$ingredient_id] ?? ($modifiers[(string)$ingredient_id] ?? 'normal');
                $amount_to_deduct = (float)$recipe_row['quantity_used'] * $qty * modifier_multiplier($modifier);

                if ($amount_to_deduct > 0) {
                    $stmt_deduct_ingredient->bind_param("dii", $amount_to_deduct, $ingredient_id, $amount_to_deduct);
                    $stmt_deduct_ingredient->execute();
                }
            }
        }

        $stmt_set_ready = $conn->prepare("UPDATE sales SET ready_at = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?");
        $stmt_set_ready->bind_param("ii", $max_prep_minutes, $sale_id);
        $stmt_set_ready->execute();
        $stmt_set_ready->close();

        $stmt_insert_sale_item->close();
        $stmt_get_recipe->close();
        $stmt_deduct_ingredient->close();
        $stmt_get_prep_time->close();
        $conn->commit();

        respond_json([
            "status"         => "success",
            "invoice_id"     => $sale_id,
            "date"           => date("F d, Y h:i A"),
            "total"          => $total_amount,
            "tendered"       => $amount_tendered,
            "change"         => $change,
            "payment_method" => $final_payment_label
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        respond_json(["status" => "error", "message" => $e->getMessage()]);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pannakoda - Point of Sale</title>
    <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- AOS Library CSS -->
    <link href="../../LIBRARIES/AOS/aos.css" rel="stylesheet">
    <!-- Swiper CSS para sa 3D Slider effect -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-accent: #e5a912;
            --primary-hover: #c9930f;
            --primary-light: #fef9e7;
            --primary-border: #fce8b2;
            --bg-main: #f8fafc;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-main);
            color: #1e293b;
            overflow-x: hidden;
        }

        /* Sidebar Navigation Layout with Glassmorphism / Modern Touch */
        .pos-sidebar {
            width: 260px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-right: 1px solid #e2e8f0;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 1050;
            display: flex;
            flex-direction: column;
            padding: 20px;
            overflow-y: auto;
            box-shadow: 4px 0 24px rgba(0,0,0,0.02);
            transition: all 0.3s ease;
        }

        .brand-title {
            color: var(--primary-accent);
            letter-spacing: 0.5px;
            font-size: 1.05rem;
        }

        #main-wrapper {
            margin-left: 260px;
            padding: 30px;
            transition: all 0.3s ease;
        }

        .search-box {
            position: relative;
            width: 100%;
        }
        .search-box input {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 10px 14px 10px 38px;
            font-size: 0.85rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .search-box input:focus {
            background: #fff;
            border-color: var(--primary-accent);
            box-shadow: 0 0 0 4px rgba(229, 169, 18, 0.15);
            transform: translateY(-1px);
        }
        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        /* Sidebar Category Tab Buttons - Modernized with Accent Theme */
        .category-tab-btn {
            background: transparent;
            border: 1px solid transparent;
            border-radius: 12px;
            color: #64748b;
            font-weight: 600;
            font-size: 0.85rem;
            padding: 10px 14px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-align: left;
            width: 100%;
        }

        .category-tab-btn:hover, .category-tab-btn.active {
            background-color: var(--primary-light);
            color: var(--primary-accent) !important;
            border-color: var(--primary-border);
            transform: translateX(6px);
            box-shadow: 0 4px 12px rgba(229, 169, 18, 0.08);
        }

        /* --- 3D SWIPER CAROUSEL STYLES --- */
        .swiper {
            width: 100%;
            padding-top: 20px;
            padding-bottom: 40px;
        }

        .swiper-slide {
            background-position: center;
            background-size: cover;
            width: 400px;
            height: 240px;
            border-radius: 20px;
            cursor: grab;
        }

        .bestseller-card-3d {
            position: relative;
            width: 100%;
            height: 100%;
            border-radius: 20px;
            overflow: hidden;
            background: linear-gradient(135deg, #e5a912 0%, #c9930f 100%);
            box-shadow: 0 10px 25px rgba(229, 169, 18, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            padding-left: 20px;

        }

        .bestseller-card-content {
            flex: 1.2;
            color: #ffffff;
            z-index: 2;
        }

        .bestseller-card-img-container {
            flex: 1;
            height: 100%;

            position: relative;
            display: flex;
            background: #FAF2EF;
            align-items: center;
            justify-content: center;
        }

        .bestseller-card-img {
            max-width: 180px;
            max-height: 150px;
            object-fit: contain;
            filter: drop-shadow(0 10px 15px rgba(0,0,0,0.3));
            transition: transform 0.5s ease;
        }
      

        .bestseller-order-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background-color: #ffffff;
            color: var(--primary-accent);
            font-size: 0.75rem;
            font-weight: 700;
            padding: 8px 14px;
            border-radius: 50rem;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-top: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .bestseller-order-btn:hover {
            background-color: var(--primary-light);
            transform: scale(1.05);
          
        }

        .swiper-pagination-bullet-active {
            background-color: var(--primary-accent) !important;
              width: 19px !important;
        }

.swiper-pagination-bullet {
    width: 13x !important;    
    height: 12px !important;   
    margin: 0 6px !important;  
}
   
        .product-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
            position: relative;
        }
        .product-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 30px -8px rgba(229, 169, 18, 0.18);
            border-color: var(--primary-border);
            z-index: 10;
        }
        
        .product-img-wrapper {
            height: 145px;
            background: radial-gradient(circle, #fef9e7 0%, #fef3c7 100%);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
            border-bottom: 1px solid #f1f5f9;
        }
        .product-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter: drop-shadow(0 6px 10px rgba(0,0,0,0.06));
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .product-card:hover .product-img-wrapper img {
            transform: scale(1.12) rotate(1deg);
        }

        .card-action-btn {
            width: 34px;
            height: 34px;
            background: var(--primary-light);
            border: 1px solid var(--primary-border);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-accent);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .product-card:hover .card-action-btn {
            background: var(--primary-accent);
            color: #fff;
            transform: scale(1.15) rotate(90deg);
            box-shadow: 0 4px 12px rgba(229, 169, 18, 0.3);
        }

        .out-of-stock-card {
            cursor: default;
        }
        
        .out-of-stock-card:hover {
            transform: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
            border-color: #e2e8f0;
        }

        /* Cart & Checkout Panel Styling */
        .cart-panel-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            box-shadow: 0 12px 35px rgba(0,0,0,0.04);
            position: sticky;
            top: 20px;
            transition: all 0.3s ease;
        }
  
        .receipt-card {
            background: #fff;
            color: #000;
            font-family: 'Courier New', Courier, monospace;
            border: 1px dashed #000;
        }

        /* Smooth Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        @media print {
            body * { visibility: hidden; }
            #checkout-receipt-pane, #checkout-receipt-pane * { visibility: visible; }
            #checkout-receipt-pane { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="pb-5 mb-5">

    <!-- Left Sidebar Navigation -->
    <aside class="pos-sidebar" data-aos="fade-right" data-aos-duration="600">
        <!-- Brand Logo & Name -->
        <div class="d-flex align-items-center mb-4 pb-3 border-bottom">
            <div class="p-1 rounded-3 me-2">
                <img src="../../LIBRARIES/5501d331-f1e5-4dcc-ab9b-8fd56a2b5ea5.png" style="width: 38px; height: 38px; border-radius: 100%;">
            </div>
            <div>
                <h4 class="brand-title fw-bold m-0">PANNAKODA</h4>
            <span style="font-size: small;">Pancake & Pastries</span>
            </div>
        </div>

        <!-- Search Box inside Sidebar -->
        <div class="search-box mb-4">
            <i class="bi bi-search"></i>
            <input type="text" id="productSearch" class="form-control" placeholder="Search menu..." oninput="filterProductsByName(this.value)">
        </div>

        <!-- Menu Modal Trigger Button -->
        <div class="mb-4">
            <button type="button" class="btn w-100 py-2.5 fw-bold text-white shadow-sm d-flex align-items-center justify-content-center gap-2 rounded-3" style="background-color: var(--primary-accent); border: none; transition: all 0.3s ease;" data-bs-toggle="modal" data-bs-target="#menuCatalogModal" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 15px rgba(229, 169, 18, 0.35)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                <i class="bi bi-journal-text fs-5"></i> View Full Menu
            </button>
        </div>

        <!-- Categories List -->
        <div class="mb-3">
            <span class="text-uppercase fw-bold px-2 mb-2 d-block" style="font-size: 0.80rem; color: #e5a912; letter-spacing: 0.5px;">Categories</span>
            <div class="d-flex flex-column gap-2" id="sidebarCategoryTabs" role="tablist">
                <?php foreach ($categories_array as $index => $cat): 
                    $target_id = strtolower(str_replace(' ', '', $cat['name']));
                ?>
                    <button class="btn category-tab-btn d-flex align-items-center gap-2 <?php echo $index === 0 ? 'active' : ''; ?>" 
                            id="<?php echo $target_id; ?>-tab" data-bs-toggle="pill" data-bs-target="#cat-<?php echo $target_id; ?>" type="button" role="tab">
                        <i class="bi bi-grid fs-6"></i>
                        <span><?php echo htmlspecialchars($cat['name']); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Sidebar Footer / Utility Controls -->
        <div class="mt-auto pt-3 border-top d-flex align-items-center justify-content-between">
            <button class="btn btn-outline-danger btn-sm rounded-3 px-2 py-1.5 w-100" id="logoutBtn" title="Logout Staff" style="transition: all 0.2s;">
                <i class="bi bi-box-arrow-right"></i> Logout
            </button>
        </div>
    </aside>

    <!-- Full Menu Modal -->
    <div class="modal fade" id="menuCatalogModal" tabindex="-1" aria-labelledby="menuCatalogModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-xl">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" style="background-color: #ffffff;">
                <div class="modal-header text-white" style="background-color: var(--primary-accent); border-bottom: 2px solid rgba(0,0,0,0.05);">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 rounded-3" style="background-color: rgba(255,255,255,0.2);">
                            <i class="bi bi-book-half text-white fs-4"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold m-0" id="menuCatalogModalLabel">MENU CATALOG</h5>
                            <small class="text-white-50" style="font-size: 0.75rem;">Pancakes & Pastries </small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" style="max-height: 75vh; overflow-y: auto; background-color: #f8fafc;">
                    
                    <!-- Reference-style Paper Layout Container -->
                    <div class="p-4 rounded-4 shadow-sm position-relative" style="background-color: #ffffff; border: 1px solid #e2e8f0;">
                        
                        <!-- Header & Brand Top Section -->
                        <div class="row align-items-center mb-4 pb-3 border-bottom border-secondary border-opacity-10">
                            <div class="col-md-7">
                                <h2 class="fw-bold text-uppercase m-0" style="font-family: serif; color: #1e293b; letter-spacing: 1px;">MENU CATALOG</h2>

                            </div>
                            <div class="col-md-5 text-md-end mt-3 mt-md-0">
                                <div class="d-inline-flex align-items-center gap-2 p-2 rounded-3 bg-white border shadow-sm">
                                    <div class="rounded-circle p-1 text-white d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background-color: var(--primary-accent);">
                                        <i class="bi bi-cup-hot-fill fs-6"></i>
                                    </div>
                                    <div class="text-start">
                                        <h6 class="fw-bold m-0" style="font-size: 0.8rem; color: #1e293b;">PANNAKODA</h6>
                                        <small class="text-muted" style="font-size: 0.65rem;">PANCAKE & PASTRIES</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Content Grid with Images and Explanations -->
                        <div class="row g-4" id="modal-menu-categories-container">
                            <!-- Populated dynamically via JS -->
                        </div>

                      
                    </div>

                </div>
                <div class="modal-footer bg-white border-top py-3">
                    <button type="button" class="btn btn-dark px-4 fw-bold rounded-pill" data-bs-dismiss="modal">Close Menu</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Wrapper -->
    <div id="main-wrapper">
        <div class="row g-4 align-items-start">
            
            <!-- Menu Catalog Display Area (Left Side) -->
            <div class="col-lg-8 pb-5">
                
                <!-- 3D Bestseller Slider Section -->
                <?php if (!empty($best_sellers_array)): ?>
                <div class="main-container p-3 rounded-4 shadow-sm border mb-4" data-aos="fade-up" data-aos-duration="600">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h5 class="fw-bold m-0 text-amber-500" >
                            <i class="bi bi-fire text-danger me-2"></i>Popular Picks & Best Sellers
                        </h5>
                    </div>
                    
                    <!-- Swiper 3D Effect Container -->
                    <div class="swiper bestsellerSwiper">
                        <div class="swiper-wrapper" id="bestseller-slider-wrapper">
                            <!-- Populated dynamically via JS -->
                        </div>
                        <div class="swiper-pagination"></div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="bg-white p-4 rounded-4 shadow-sm border mb-4" data-aos="fade-up" data-aos-duration="800" style="overflow: visible;">
                    <div class="tab-content" id="categoryTabContent">
                        <?php foreach ($categories_array as $index => $cat): 
                            $target_id = strtolower(str_replace(' ', '', $cat['name']));
                        ?>
                            <div class="tab-pane fade show <?php echo $index === 0 ? 'active' : ''; ?>" id="cat-<?php echo $target_id; ?>" role="tabpanel">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h5 class="fw-bold m-0" style="color: var(--primary-accent);"><i class="bi bi-bookmark-fill me-2 small"></i><?php echo htmlspecialchars($cat['name']); ?></h5>
                                </div>
                                <div class="row g-3" id="grid-<?php echo $target_id; ?>" style="overflow: visible;"></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Right Side: Current Active Order & Checkout Section -->
            <div class="col-lg-4" data-aos="fade-left" data-aos-duration="700">
                <div class="cart-panel-box p-4">
                    <div id="checkout-interactive-pane">
                        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                            <h5 class="fw-bold m-0 " style="color: var(--primary-accent);">Current Order</h5>
                            <span class="badge rounded-pill px-2.5 py-1 fw-bold" style="background-color: var(--primary-light); color: var(--primary-accent); font-size: 0.75rem;">Active POS</span>
                        </div>

                        <div class="p-3 rounded-3 bg-light border mb-3">
                            <div class="d-flex justify-content-between  fw-bold px-1 mb-2" style="font-size: 0.75rem;">
                                <span style="color: var(--primary-accent);">Item Name</span>
                                <span class="text-center" style="margin-right: 15px; color: var(--primary-accent);">QTY</span>
                                <span class="text-end" style="color: var(--primary-accent);">Price</span>
                            </div>
                            <div id="cart-items-container" class="d-flex flex-column gap-2" style="max-height: 240px; overflow-y: auto; padding-right: 4px;">
                                <p class="text-muted text-center small my-auto py-4">No items added yet.<br>Click a menu item to start.</p>
                            </div>
                            <hr class="border-secondary opacity-25 my-3">
                            
                            <div class="d-flex justify-content-between align-items-center px-1 mb-1.5">
                                <span class="small text-muted">Discount (%)</span>
                                <span class="small fw-bold">0%</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center px-1 mb-1.5">
                                <span class="small text-muted">Sub Total</span>
                                <span class="small fw-bold" id="subtotal-amount-display">₱0.00</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center px-1 mb-2.5">
                                <span class="small text-muted">Tax <span style="color: var(--primary-accent); font-size: 0.7rem;">0.0%</span></span>
                                <span class="small fw-bold">₱0.00</span>
                            </div>
                            <hr class="border-secondary opacity-25 my-2">
                            <div class="d-flex justify-content-between align-items-center px-1">
                                <span class="small fw-bold text-dark">Total</span>
                                <span class="fs-4 fw-bold" style="color: var(--primary-accent);" id="total-amount-display">₱0.00</span>
                            </div>
                        </div>

                        <div class="mb-3 position-relative">
                            <label class="form-label fw-bold text-secondary mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">SELECT PAYMENT METHOD:</label>
                            <div class="dropdown">
                                <button class="btn btn-light border w-100 text-dark fw-semibold text-start d-flex justify-content-between align-items-center py-2.5 bg-white rounded-3 shadow-sm" type="button" id="paymentDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <span id="selected-payment-text"><i class="bi bi-wallet2 me-2" style="color: var(--primary-accent);"></i>Choose Option</span>
                                    <i class="bi bi-chevron-down small text-muted"></i>
                                </button>
                                <ul class="dropdown-menu w-100 shadow-sm border py-2 mt-1 rounded-3" style="z-index: 1080;">
                                    <li><a class="dropdown-item py-2 fw-medium" href="#" onclick="selectPayment('Cash')"><i class="bi bi-cash-stack me-2" style="color: var(--primary-accent);"></i>Cash Payment</a></li>
                                    <li><a class="dropdown-item py-2 fw-medium" href="#" onclick="selectPayment('Card')"><i class="bi bi-credit-card text-primary me-2"></i>Card Terminal</a></li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="mb-3 d-none" id="cash-panel">
                            <label class="form-label fw-bold text-secondary mb-1" style="font-size: 0.75rem;">CASH TENDERED:</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 fw-bold">₱</span>
                                <input type="text" id="amount-tendered" maxlength="5" placeholder="0.00" class="form-control bg-light border-start-0 fw-bold fs-5" oninput="this.value = this.value.replace(/[^0-9]/g, ''); calculateChange();">
                            </div>
                        </div>
                        
                        <div class="mb-3 d-none" id="card-panel">
                            <label class="form-label fw-bold text-secondary mb-1" style="font-size: 0.75rem;">CARD LAST 4 DIGITS:</label>
                            <input type="text" id="card-digits" maxlength="4" placeholder="e.g. 4321" class="form-control bg-light fw-bold fs-5 text-center" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                        </div>

                        <div class="p-3 rounded-3 bg-light border d-flex justify-content-between align-items-center mb-3 d-none" id="change-panel-wrapper">
                            <span class="small fw-bold text-secondary" id="change-label">Change Due:</span>
                            <span class="fs-5 fw-bold text-dark" id="change-display">₱0.00</span>
                        </div>

                        <button type="button" class="btn btn-lg w-100 mt-2 py-3 fw-bold text-white shadow-sm rounded-3" style="background-color: var(--primary-accent); border: none; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='var(--primary-hover)'; this.style.transform='translateY(-2px)';" onmouseout="this.style.backgroundColor='var(--primary-accent)'; this.style.transform='translateY(0)';" onclick="processCheckout()">
                            Pay <span id="pay-btn-amount">(₱0.00)</span> <i class="bi bi-arrow-right-circle-fill ms-1"></i>
                        </button>
                    </div>
                    
                    <div id="checkout-receipt-pane" class="d-none p-3 rounded receipt-card"></div>
                </div>
            </div>

        </div>
    </div>

    <script src="../LIBRARIES/bootstrap.bundle.min.js"></script>
    <!-- AOS Library JS -->
    <script src="../../LIBRARIES/AOS/AOS.js"></script>
    <!-- Swiper JS para sa 3D Slider -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        // Initialize AOS animations
        AOS.init({
            once: true,
            offset: 50,
            duration: 600,
            easing: 'cubic-bezier(0.4, 0, 0.2, 1)'
        });

        let products = <?php echo json_encode($products_json); ?>;
        let bestSellers = <?php echo json_encode($best_sellers_array); ?>;
        let categoriesList = <?php echo json_encode($categories_array); ?>;
        let cart = [], totalAmount = 0, selectedPaymentMethod = '';

        function renderBestSellerWidget() {
            const wrapper = document.getElementById('bestseller-slider-wrapper');
            if (!wrapper || bestSellers.length === 0) return;

            wrapper.innerHTML = '';
            bestSellers.forEach((bs, index) => {
                const matched_prod = products.find(p => p.id === bs.id);
                const isOut = matched_prod ? (matched_prod.available <= 0) : true;

                const slide = document.createElement('div');
                slide.className = 'swiper-slide';
                slide.addEventListener('click', () => {
                    if (swiperInstance) {
                        swiperInstance.slideToLoop(index);
                    }
                });

                slide.innerHTML = `
                    <div class="bestseller-card-3d">
                        <div class="bestseller-card-content">
                            <h4 class="fw-bold text-white mb-1" style="font-size: 1.05rem; white-space: normal; word-break: break-word; line-height: 1.2;" title="${bs.name}">${bs.name}</h4>
                            <div class="fs-5 fw-bold text-white mb-1">₱${parseFloat(bs.price).toFixed(2)}</div>
                            <span class="badge bg-dark bg-opacity-25 text-white border border-white border-opacity-25 mb-2" style="font-size: 0.65rem;">
                                <i class="bi bi-bag-check-fill text-white me-1"></i>${bs.qty} sold 
                                ${isOut ? ' | <span class="text-white fw-bold" style="font-size: 0.55rem;">OUT OF STOCK</span>' : ''}
                            </span>
                            <div>
                                <button class="bestseller-order-btn" ${isOut ? 'disabled style="opacity: 0.6; cursor: not-allowed; background-color: #e2e8f0; color: #94a3b8;"' : `onclick="addToCart(event, ${bs.id})"`}>
                                    ${isOut ? 'Out of Stock' : 'Order Now'} <i class="bi bi-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                        <div class="bestseller-card-img-container">
                            ${bs.image ? `<img src="${bs.image}" alt="${bs.name}" class="bestseller-card-img">` : `<div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted"><i class="bi bi-cup-hot fs-1 opacity-50"></i></div>`}
                        </div>
                    </div>
                `;
                wrapper.appendChild(slide);
            });

            // I-initialize ang Swiper 3D Coverflow Effect at idinagdag ang loop: true
            window.swiperInstance = new Swiper(".bestsellerSwiper", {
                effect: "coverflow",
                grabCursor: true,
                centeredSlides: true,
                slidesPerView: "auto",
                loop: true, // Ginawang infinite loop ang carousel
                watchSlidesProgress: true,
                coverflowEffect: {
                    rotate: 30,
                    stretch: 0,
                    depth: 100,
                    modifier: 1,
                    slideShadows: false,
                },
                pagination: {
                    el: ".swiper-pagination",
                    clickable: true,
                },
            });
        }

        function renderModalMenuCatalog() {
            const container = document.getElementById('modal-menu-categories-container');
            if (!container) return;
            container.innerHTML = '';

            categoriesList.forEach(cat => {
                const cleanCat = cat.name.toLowerCase().replace(/\s+/g, '');
                const catProducts = products.filter(p => p.category === cleanCat);

                let itemsHTML = '';
                if (catProducts.length === 0) {
                    itemsHTML = `<p class="text-muted small fst-italic mb-2">No menu items listed under this category yet.</p>`;
                } else {
                    catProducts.forEach(prod => {
                        const description = prod.recipe && prod.recipe.length > 0 
                            ? `Made with fresh ${prod.recipe.map(r => r.name).join(', ')}.` 
                            : `A delightful house special crafted for your daily refreshment.`;

                        itemsHTML += `
                            <div class="d-flex align-items-center gap-3 p-3 mb-3 bg-white rounded-3 border shadow-sm transition-all" style="border-color: #e2e8f0 !important; transition: transform 0.2s;" onmouseover="this.style.transform='translateX(4px)'" onmouseout="this.style.transform='translateX(0)'">
                                <div style="width: 65px; height: 65px; flex-shrink: 0;" class="rounded-2 overflow-hidden bg-light d-flex align-items-center justify-content-center border">
                                    ${prod.image ? `<img src="${prod.image}" alt="${prod.name}" style="width: 100%; height: 100%; object-fit: contain;">` : `<i class="bi bi-cup-hot text-muted"></i>`}
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="fw-bold text-dark m-0" style="font-size: 0.95rem;">${prod.name}</h6>
                                        <span class="fw-bold" style="font-size: 0.95rem; color: var(--primary-accent);">₱${prod.price.toFixed(2)}</span>
                                    </div>
                                    <p class="text-muted m-0 mt-1" style="font-size: 0.8rem; line-height: 1.4;">${description}</p>
                                </div>
                            </div>`;
                    });
                }

                container.innerHTML += `
                    <div class="col-md-6">
                        <div class="p-3 bg-white rounded-3 border shadow-sm h-100" style="border-color: #e2e8f0 !important;">
                            <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3 text-white shadow-sm" style="background-color: var(--primary-accent); font-size: 0.75rem; font-weight: 700; letter-spacing: 0.5px;">
                                <i class="bi bi-cup-hot"></i> ${cat.name.toUpperCase()}
                            </div>
                            <div class="d-flex flex-column">
                                ${itemsHTML}
                            </div>
                        </div>
                    </div>`;
            });
        }

        function renderProductGrid(filter = '') {
            const categories = [...new Set(products.map(p => p.category))];
            categories.forEach(cat => {
                const grid = document.getElementById(`grid-${cat}`);
                if(!grid) return;
                grid.innerHTML = '';
                const filtered = products.filter(p => p.category === cat && p.name.toLowerCase().includes(filter.toLowerCase()));
                
                filtered.forEach((prod, idx) => {
                    const col = document.createElement('div');
                    col.className = 'col-md-4 col-sm-6';
                    const isOutOfStock = prod.available <= 0;
                    let stockBadge = '';
                    if (!prod.has_recipe) {
                        stockBadge = `<span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1" style="font-size: 0.60rem;"><i class="bi bi-exclamation-triangle"></i> No Recipe</span>`;
                    } else if (isOutOfStock) {
                        stockBadge = `<span class="badge bg-danger text-light rounded-pill px-2 py-1" style="font-size: 0.60rem;">OUT OF STOCK</span>`;
                    } else {
                        stockBadge = `<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1" style="font-size: 0.60rem;">Stock: ${prod.available}</span>`;
                    }

                    col.innerHTML = `
                        <div class="card product-card h-100 ${isOutOfStock ? 'out-of-stock-card' : ''}">
                            <div class="product-img-wrapper" ${isOutOfStock ? '' : `onclick="addToCart(event, ${prod.id})"`}>
                                ${prod.image ? `<img src="${prod.image}" alt="${prod.name}">` : `<div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted"><i class="bi bi-cup-hot fs-2 opacity-50"></i></div>`}
                            </div>
                            <div class="card-body p-3 d-flex flex-column justify-content-between" style="overflow: visible;">
                                <div class="d-flex align-items-start justify-content-between gap-2 mb-2" style="overflow: visible;">
                                    <div class="flex-grow-1" ${isOutOfStock ? '' : `onclick="addToCart(event, ${prod.id})"`} style="cursor: ${isOutOfStock ? 'not-allowed' : 'pointer'};">
                                        <div class="fw-bold mb-1" style="font-size: 0.9rem; color: var(--primary-accent);">₱${prod.price.toFixed(2)}</div>
                                        <h6 class="fw-semibold text-dark m-0" style="font-size: 0.82rem; line-height: 1.2;" title="${prod.name}">${prod.name}</h6>
                                    </div>
                                    <div class="card-action-btn shadow-sm" ${isOutOfStock ? '' : `onclick="addToCart(event, ${prod.id})"`}>
                                        <i class="bi bi-plus-lg fs-6"></i>
                                    </div>
                                </div>
                                <div>${stockBadge}</div>
                            </div>
                        </div>`;
                    grid.appendChild(col);
                });
            });
        }

        function filterProductsByName(query) {
            renderProductGrid(query);
        }

        function selectPayment(method) {
            selectedPaymentMethod = method;
            const iconClass = method === 'Cash' ? 'bi-cash-stack' : 'bi-credit-card text-primary';
            document.getElementById('selected-payment-text').innerHTML = `<i class="bi ${iconClass} me-2" style="${method === 'Cash' ? 'color: var(--primary-accent);' : ''}"></i>${method}`;
            
            const cashPanel = document.getElementById('cash-panel');
            const cardPanel = document.getElementById('card-panel');
            const changeWrapper = document.getElementById('change-panel-wrapper');

            if (method === 'Cash') {
                cashPanel.classList.remove('d-none');
                changeWrapper.classList.remove('d-none');
                cardPanel.classList.add('d-none');
                document.getElementById('card-digits').value = '';
            } else if (method === 'Card') {
                cardPanel.classList.remove('d-none');
                cashPanel.classList.add('d-none');
                changeWrapper.classList.add('d-none');
                document.getElementById('amount-tendered').value = '';
            }
            calculateChange();
        }

        let customizeExpanded = new Set();
        let nextLineId = 1;

        function getTotalQuantityForItem(itemId) {
            return cart.filter(i => i.id === itemId).reduce((sum, i) => sum + i.quantity, 0);
        }

        function addToCart(event, id) {
            if (event) event.stopPropagation();
            const product = products.find(p => p.id === id);
            if (!product) return;
            if (product.available <= 0) return;
            if (getTotalQuantityForItem(id) >= product.available) return;

            const existingPlainLine = cart.find(item => item.id === id && Object.keys(item.modifiers).length === 0);
            if (existingPlainLine) {
                existingPlainLine.quantity += 1;
            } else {
                cart.push({ lineId: nextLineId++, id: product.id, name: product.name, price: product.price, quantity: 1, modifiers: {} });
            }
            renderCart();
        }

        function updateQuantity(lineId, change) {
            const item = cart.find(i => i.lineId === lineId);
            if (!item) return;

            if (change > 0) {
                const product = products.find(p => p.id === item.id);
                if (product && getTotalQuantityForItem(item.id) >= product.available) return;
            }

            item.quantity += change;
            if (item.quantity <= 0) {
                cart = cart.filter(i => i.lineId !== lineId);
                customizeExpanded.delete(lineId);
            }
            renderCart();
        }

        function toggleCustomize(lineId) {
            if (customizeExpanded.has(lineId)) {
                customizeExpanded.delete(lineId);
            } else {
                customizeExpanded.add(lineId);
            }
            renderCart();
        }

        function setModifier(lineId, ingredientId, value) {
            const item = cart.find(i => i.lineId === lineId);
            if (!item) return;
            if (value === 'normal') {
                delete item.modifiers[ingredientId];
            } else {
                item.modifiers[ingredientId] = value;
            }
            renderCart();
        }

        function renderCart() {
            const container = document.getElementById('cart-items-container');
            container.innerHTML = '';
            if (cart.length === 0) {
                container.innerHTML = '<p class="text-muted text-center small my-auto py-4">No items added yet.<br>Click a menu item to start.</p>';
                totalAmount = 0;
                document.getElementById('subtotal-amount-display').innerText = '₱0.00';
                document.getElementById('total-amount-display').innerText = '₱0.00';
                document.getElementById('pay-btn-amount').innerText = '(₱0.00)';
                return;
            }
            totalAmount = 0;
            cart.forEach(item => {
                totalAmount += item.price * item.quantity;
                const product = products.find(p => p.id === item.id);
                const recipe = (product && product.recipe) ? product.recipe : [];
                const isExpanded = customizeExpanded.has(item.lineId);

                const modifierTags = Object.entries(item.modifiers || {}).map(([ingId, val]) => {
                    const ing = recipe.find(r => String(r.ingredient_id) === String(ingId));
                    if (!ing) return '';
                    return val === 'remove' ? `No ${ing.name}` : `Extra ${ing.name}`;
                }).filter(Boolean);

                let customizePanel = '';
                if (isExpanded && recipe.length > 0) {
                    const rows = recipe.map(ing => {
                        const current = (item.modifiers && item.modifiers[ing.ingredient_id]) || 'normal';
                        return `
                            <div class="d-flex justify-content-between align-items-center py-1.5">
                                <span class="small text-secondary fw-medium">${ing.name}</span>
                                <select class="form-select form-select-sm w-auto py-1 px-2 shadow-none" style="font-size: 0.75rem; border-color: #cbd5e1;" onchange="setModifier(${item.lineId}, ${ing.ingredient_id}, this.value)">
                                    <option value="normal" ${current === 'normal' ? 'selected' : ''}>Normal</option>
                                    <option value="remove" ${current === 'remove' ? 'selected' : ''}>No ${ing.name}</option>
                                    <option value="extra" ${current === 'extra' ? 'selected' : ''}>Extra ${ing.name}</option>
                                </select>
                            </div>`;
                    }).join('');
                    customizePanel = `<div class="mt-2.5 p-2.5 rounded-3 border bg-white shadow-sm">${rows}</div>`;
                }

                container.innerHTML += `
                    <div class="bg-white p-3 rounded-3 border shadow-sm transition-all mb-2.5" style="transition: all 0.2s; border-color: #e2e8f0 !important;">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <div style="flex: 2; min-width: 0;">
                                <span class="fw-bold d-block text-dark text-wrap" style="font-size: 0.85rem; line-height: 1.2;" title="${item.name}">${item.name}</span>
                                ${modifierTags.length > 0 ? `<span class="d-block mt-0.5" style="font-size: 0.7rem; color: var(--primary-accent); font-weight: 600;">${modifierTags.join(', ')}</span>` : ''}
                            </div>
                            <div class="d-flex align-items-center gap-1 bg-light border rounded-pill px-2 py-1 shadow-sm" style="flex-shrink: 0;">
                                <button class="btn btn-sm btn-link text-dark p-0 px-1 text-decoration-none fw-bold" style="font-size: 0.9rem;" onclick="updateQuantity(${item.lineId}, -1)">-</button>
                                <span class="fw-bold small px-1 text-dark" style="min-width: 16px; text-align: center;">${item.quantity}</span>
                                <button class="btn btn-sm btn-link text-dark p-0 px-1 text-decoration-none fw-bold" style="font-size: 0.9rem;" onclick="updateQuantity(${item.lineId}, 1)">+</button>
                            </div>
                            <div style="flex: 0.9; text-align: right; flex-shrink: 0;">
                                <span class="fw-bold small text-dark">₱${(item.price * item.quantity).toFixed(2)}</span>
                            </div>
                        </div>
                        ${recipe.length > 0 ? `
                            <div class="text-end mt-2 pt-1 border-top border-light">
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-semibold" style="font-size: 0.7rem; color: var(--primary-accent);" onclick="toggleCustomize(${item.lineId})">
                                    <i class="bi bi-sliders me-1"></i>${isExpanded ? 'Hide Options' : 'Customize Item'}
                                </button>
                            </div>
                        ` : ''}
                        ${customizePanel}
                    </div>`;
            });
            document.getElementById('subtotal-amount-display').innerText = `₱${totalAmount.toFixed(2)}`;
            document.getElementById('total-amount-display').innerText = `₱${totalAmount.toFixed(2)}`;
            document.getElementById('pay-btn-amount').innerText = `(₱${totalAmount.toFixed(2)})`;
            calculateChange();
        }

        function calculateChange() {
            const inputTendered = document.getElementById('amount-tendered').value;
            const changeDisplay = document.getElementById('change-display');
            if (!inputTendered || totalAmount === 0) { changeDisplay.innerText = '₱0.00'; return; }
            
            const parsedTendered = parseFloat(inputTendered);
            const change = parsedTendered - totalAmount;
            
            if (change < 0) {
                changeDisplay.innerText = 'Short Payment';
                changeDisplay.style.color = '#ef4444';
            } else if (parsedTendered > (totalAmount + 1000)) {
                changeDisplay.innerText = 'Excessive Amount';
                changeDisplay.style.color = '#ef4444';
            } else {
                changeDisplay.innerText = `₱${change.toFixed(2)}`;
                changeDisplay.style.color = '#10b981';
            }
        }

        function processCheckout() {
            if (cart.length === 0) {
                Swal.fire({ icon: 'warning', title: 'Empty Cart', text: 'Please add items to your basket before checking out.', confirmButtonColor: '#e5a912' });
                return;
            }
            if (!selectedPaymentMethod) {
                Swal.fire({ icon: 'warning', title: 'Payment Method Required', text: 'Please choose a payment method.', confirmButtonColor: '#e5a912' });
                return;
            }

            const amountTendered = document.getElementById('amount-tendered').value;
            const cardDigits = document.getElementById('card-digits').value.trim();

            if (selectedPaymentMethod === 'Cash') {
                if (!amountTendered || parseFloat(amountTendered) < totalAmount) {
                    Swal.fire({ icon: 'error', title: 'Insufficient Payment', text: `Amount entered is less than the total bill.`, confirmButtonColor: '#e5a912' });
                    return;
                }
                if (parseFloat(amountTendered) > (totalAmount + 1000)) {
                    Swal.fire({ icon: 'error', title: 'Excessive Amount', text: `Maximum allowed change is ₱1,000.`, confirmButtonColor: '#e5a912' });
                    return;
                }
            }

            if (selectedPaymentMethod === 'Card') {
                if (cardDigits.length !== 4) {
                    Swal.fire({ icon: 'error', title: 'Card Verification Required', text: 'Please input the last 4 digits of the card.', confirmButtonColor: '#e5a912' });
                    return;
                }
            }
            
            const formData = new FormData();
            formData.append('action', 'deduct_stock');
            formData.append('cart_items', JSON.stringify(cart));
            formData.append('payment_method', selectedPaymentMethod);
            formData.append('amount_tendered', amountTendered);
            formData.append('card_digits', cardDigits);

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    let receiptHTML = `
                        <div class="text-center mb-2">
                            <h5 class="fw-bold m-0">PANNAKODA</h5>
                            <small>Official Receipt</small><br>
                            <small>OR #: ${data.invoice_id}</small><br>
                            <small>${data.date}</small>
                        </div>
                        <hr style="border-top: 1px dashed #000;">
                        <table class="w-100 small mb-2">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>`
                    
                    cart.forEach(item => {
                        const product = products.find(p => p.id === item.id);
                        const recipe = (product && product.recipe) ? product.recipe : [];
                        const modifierTags = Object.entries(item.modifiers || {}).map(([ingId, val]) => {
                            const ing = recipe.find(r => String(r.ingredient_id) === String(ingId));
                            if (!ing) return '';
                            return val === 'remove' ? `No ${ing.name}` : `Extra ${ing.name}`;
                        }).filter(Boolean);

                        receiptHTML += `
                            <tr>
                                <td>${item.name}${modifierTags.length > 0 ? `<br><span style="font-size:0.75em;">(${modifierTags.join(', ')})</span>` : ''}</td>
                                <td class="text-center">${item.quantity}</td>
                                <td class="text-end">₱${(item.price * item.quantity).toFixed(2)}</td>
                            </tr>`;
                    });

                    receiptHTML += `
                            </tbody>
                        </table>
                        <hr style="border-top: 1px dashed #000;">
                        <div class="small mb-3">
                            <div class="d-flex justify-content-between"><span>TOTAL AMOUNT:</span><span class="fw-bold">₱${data.total.toFixed(2)}</span></div>
                            <div class="d-flex justify-content-between"><span>Payment Mode:<span><span>${data.payment_method}</span></div>
                            <div class="d-flex justify-content-between"><span>Amount Paid:<span><span>₱${parseFloat(data.tendered).toFixed(2)}</span></div>
                            <div class="d-flex justify-content-between"><span>Change Due:</span><span class="fw-bold">₱${parseFloat(data.change).toFixed(2)}</span></div>
                        </div>
                        <div class="text-center no-print">
                            <button class="btn btn-sm btn-dark w-100 mb-2" onclick="window.print()"><i class="bi bi-printer"></i> Print Receipt</button>
                            <button class="btn btn-sm text-white w-100" style="background-color: var(--primary-accent);" onclick="window.location.reload()">Done / New Order</button>
                        </div>`;

                    document.getElementById('checkout-interactive-pane').classList.add('d-none');
                    const receiptPane = document.getElementById('checkout-receipt-pane');
                    receiptPane.innerHTML = receiptHTML;
                    receiptPane.classList.remove('d-none');

                    Swal.fire({
                        icon: 'success',
                        title: 'Sale Completed',
                        html: `<div class="receipt-card p-2 rounded text-start">${receiptHTML}</div>`,
                        confirmButtonColor: '#e5a912',
                        confirmButtonText: 'Close',
                        width: '600px'
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Transaction Failed', text: data.message || 'Unable to complete checkout.', confirmButtonColor: '#ef4444' });
                }
            })
            .catch(err => {
                Swal.fire({ icon: 'error', title: 'Server Error', text: 'Checkout process encountered an error.', confirmButtonColor: '#e5a912' });
            });
        }

        renderProductGrid();
        renderBestSellerWidget();
        renderModalMenuCatalog();

        document.getElementById('logoutBtn').addEventListener('click', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Are you sure?',
                text: "You will be logged out of your account.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e5a912',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Log out',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../PAGES/logout.php?role=staff';
                }
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>