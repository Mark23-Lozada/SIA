<?php
include('../BACKEND/db_inventory.php');

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
// FETCH BEST SELLERS FROM SALES HISTORY
// ===================================================================
$best_sellers_array = [];
$best_sellers_query = "
    SELECT items.id, items.item_name, items.image, items.price, SUM(sales_items.quantity) as total_qty
    FROM sales_items
    JOIN items ON sales_items.item_id = items.id
    WHERE items.is_available = 1
    GROUP BY sales_items.item_id
    ORDER BY total_qty DESC
    LIMIT 6";
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
            'category'   => $clean_cat
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
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --purple-primary: #8b5cf6;
            --purple-hover: #7c3aed;
            --purple-light: #f5f3ff;
            --purple-border: #ddd6fe;
            --bg-main: #f8fafc;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-main);
            color: #1e293b;
            overflow-x: hidden;
        }

        /* Top Bar Navigation */
        .pos-topbar {
            height: 65px;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1050;
        }

        .brand-title {
            color: var(--purple-primary);
            letter-spacing: 0.5px;
            font-size: 1.05rem;
        }

        #main-wrapper {
            margin-top: 75px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .search-box {
            position: relative;
            max-width: 380px;
            width: 100%;
        }
        .search-box input {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 8px 14px 8px 38px;
            font-size: 0.85rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .search-box input:focus {
            background: #fff;
            border-color: var(--purple-primary);
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.15);
        }
        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        /* Category Navigation Bar */
        .pos-category-bar {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            position: fixed;
            top: 65px;
            left: 0;
            right: 0;
            z-index: 1040;
            padding: 10px 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
        }

        .category-tab-btn {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            color: #64748b;
            font-weight: 600;
            font-size: 0.85rem;
            padding: 8px 18px;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            min-width: 110px;
            text-align: center;
        }

        .category-tab-btn:hover, .category-tab-btn.active {
            background-color: var(--purple-light);
            color: var(--purple-primary) !important;
            border-color: var(--purple-primary);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.15);
            transform: translateY(-1px);
        }

        /* --- TACO BELL STYLE BANNER (OPTIMIZED IMAGES) --- */
        .bestseller-hero-wrapper {
            position: relative;
            width: 100%;
            height: 280px;
            border-radius: 20px;
            overflow: hidden;
            background: linear-gradient(135deg, #4c1d95 0%, #2e1065 100%);
            cursor: pointer;
            box-shadow: 0 10px 25px rgba(76, 29, 149, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .bestseller-hero-wrapper:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(76, 29, 149, 0.25);
        }

        /* Left Side Text Content */
        .bestseller-banner-content {
            flex: 1.2;
            padding: 35px;
            color: #ffffff;
            z-index: 2;
        }

        /* Right Side Image Container (Fixed Scaling) */
        .bestseller-banner-img-container {
            flex: 1;
            height: 100%;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 15px;
        }
        .bestseller-hero-img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            filter: drop-shadow(0 10px 15px rgba(0,0,0,0.3));
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .bestseller-hero-wrapper:hover .bestseller-hero-img {
            transform: scale(1.06) rotate(1deg);
        }

        .bestseller-order-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: #d97706;
            color: #fff;
            font-size: 0.85rem;
            font-weight: 700;
            padding: 10px 20px;
            border-radius: 50rem;
            border: none;
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
            margin-top: 15px;
            transition: background-color 0.2s ease, transform 0.2s ease;
        }
        .bestseller-order-btn:hover {
            background-color: #b45309;
            transform: scale(1.02);
        }

        /* Dots styling for switching items */
        .dots-container {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 14px;
        }

        .dot {
            height: 8px;
            width: 8px;
            background-color: #cbd5e1;
            border-radius: 50%;
            display: inline-block;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .dot.active {
            width: 24px;
            border-radius: 4px;
            background-color: var(--purple-primary);
        }
        .dot:hover {
            background-color: var(--purple-primary);
        }

        /* Modern Product Card Layout (Optimized Images) */
        .product-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            position: relative;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px -6px rgba(139, 92, 246, 0.15);
            border-color: var(--purple-border);
        }
        
        .product-img-wrapper {
            height: 135px;
            background: radial-gradient(circle, #fbf7ff 0%, #f3e8ff 100%);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
        }
        .product-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: contain; /* Prevents stretching and cropping */
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.06));
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .product-card:hover .product-img-wrapper img {
            transform: scale(1.1);
        }

        .card-action-btn {
            width: 32px;
            height: 32px;
            background: var(--purple-light);
            border: 1px solid var(--purple-border);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--purple-primary);
            transition: all 0.2s ease;
        }
        .product-card:hover .card-action-btn {
            background: var(--purple-primary);
            color: #fff;
            transform: scale(1.1);
        }

        .out-of-stock-card {
            opacity: 0.55;
            cursor: not-allowed;
            filter: grayscale(30%);
        }
        .out-of-stock-card:hover {
            transform: none;
            box-shadow: none;
            border-color: #e2e8f0;
        }

        /* Cart & Checkout Panel Styling */
        .cart-panel-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
            position: sticky;
            top: 135px;
            transition: all 0.3s ease;
        }

        .receipt-card {
            background: #fff;
            color: #000;
            font-family: 'Courier New', Courier, monospace;
            border: 1px dashed #000;
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

    <!-- Top Navigation Bar -->
    <header class="navbar pos-topbar px-4 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <div class="d-flex align-items-center">
                <div class="bg-purple bg-opacity-10 p-2 rounded-3 me-2 text-purple" style="background-color: var(--purple-light); color: var(--purple-primary);">
                    <i class="bi bi-cup-hot-fill fs-6"></i>
                </div>
                <h4 class="brand-title fw-bold m-0">PANNAKODA</h4>
            </div>
        </div>

        <div class="search-box mx-3">
            <i class="bi bi-search"></i>
            <input type="text" id="productSearch" class="form-control" placeholder="Search menu items..." oninput="filterProductsByName(this.value)">
        </div>

        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-light border btn-sm rounded-3 px-2 py-1 text-secondary" title="Network Status"><i class="bi bi-hdd-network"></i></button>
            <button class="btn btn-light border btn-sm rounded-3 px-2 py-1 text-secondary" title="Store Info"><i class="bi bi-shop"></i></button>
            <button class="btn btn-light border btn-sm rounded-3 px-2 py-1 text-danger" id="logoutBtn" title="Logout Staff"><i class="bi bi-box-arrow-right"></i></button>
        </div>
    </header>

    <!-- Fixed Category Navigation Bar -->
    <div class="pos-category-bar">
        <div class="container-fluid">
            <div class="d-flex align-items-center justify-content-start gap-2 overflow-auto py-1" id="sidebarCategoryTabs" role="tablist">
                <?php foreach ($categories_array as $index => $cat): 
                    $target_id = strtolower(str_replace(' ', '', $cat['name']));
                ?>
                    <button class="btn category-tab-btn d-flex align-items-center justify-content-center gap-2 py-2 <?php echo $index === 0 ? 'active' : ''; ?>" 
                            id="<?php echo $target_id; ?>-tab" data-bs-toggle="pill" data-bs-target="#cat-<?php echo $target_id; ?>" type="button" role="tab">
                        <i class="bi bi-grid fs-6"></i>
                        <span><?php echo htmlspecialchars($cat['name']); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Main Content Wrapper -->
    <div id="main-wrapper" style="margin-top: 135px;">
        <div class="row g-4 align-items-start">
            
            <!-- Menu Catalog Display Area (Left Side) -->
            <div class="col-lg-8 pb-5">
                
                <!-- Bestseller Banner Section (Taco Bell Style Layout) -->
                <?php if (!empty($best_sellers_array)): ?>
                <div class="bg-white p-4 rounded-4 shadow-sm border mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="fw-bold m-0" style="color: var(--purple-primary);">
                            <i class="bi bi-fire text-danger me-2"></i>Popular Picks & Best Sellers
                        </h5>
                        <span class="badge bg-light text-secondary border fw-semibold px-2 py-1" style="font-size: 0.7rem;">Click dots to switch items</span>
                    </div>
                    
                    <div id="bestseller-widget-wrapper">
                        <!-- Rendered dynamically via JS below -->
                    </div>
                </div>
                <?php endif; ?>

                <div class="bg-white p-4 rounded-4 shadow-sm border mb-4">
                    <div class="tab-content" id="categoryTabContent">
                        <?php foreach ($categories_array as $index => $cat): 
                            $target_id = strtolower(str_replace(' ', '', $cat['name']));
                        ?>
                            <div class="tab-pane fade show <?php echo $index === 0 ? 'active' : ''; ?>" id="cat-<?php echo $target_id; ?>" role="tabpanel">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h5 class="fw-bold m-0" style="color: var(--purple-primary);"><i class="bi bi-bookmark-fill me-2 small"></i><?php echo htmlspecialchars($cat['name']); ?></h5>
                                </div>
                                <div class="row g-3" id="grid-<?php echo $target_id; ?>"></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Right Side: Current Active Order & Checkout Section -->
            <div class="col-lg-4">
                <div class="cart-panel-box p-4">
                    <div id="checkout-interactive-pane">
                        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                            <h5 class="fw-bold m-0 text-dark">Current Order</h5>
                            <span class="badge rounded-pill px-2 py-1 small fw-bold" style="background-color: var(--purple-light); color: var(--purple-primary);">Active POS</span>
                        </div>

                        <div class="p-2 rounded-3 bg-light border mb-3">
                            <div class="d-flex justify-content-between text-muted fw-bold px-1 mb-2" style="font-size: 0.75rem;">
                                <span>Item Name</span>
                                <span class="text-center">QTY</span>
                                <span class="text-end">Price</span>
                            </div>
                            <div id="cart-items-container" class="d-flex flex-column gap-2" style="max-height: 220px; overflow-y: auto; padding-right: 4px;">
                                <p class="text-muted text-center small my-auto py-4">No items added yet.<br>Click a menu item to start.</p>
                            </div>
                            <hr class="border-secondary opacity-25 my-2">
                            
                            <div class="d-flex justify-content-between align-items-center px-1 mb-1">
                                <span class="small text-muted">Discount (%)</span>
                                <span class="small fw-bold">0%</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center px-1 mb-1">
                                <span class="small text-muted">Sub Total</span>
                                <span class="small fw-bold" id="subtotal-amount-display">₱0.00</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center px-1 mb-2">
                                <span class="small text-muted">Tax <span class="text-success" style="font-size: 0.7rem;">0.0%</span></span>
                                <span class="small fw-bold">₱0.00</span>
                            </div>
                            <hr class="border-secondary opacity-25 my-1">
                            <div class="d-flex justify-content-between align-items-center px-1">
                                <span class="small fw-bold text-dark">Total</span>
                                <span class="fs-4 fw-bold" style="color: var(--purple-primary);" id="total-amount-display">₱0.00</span>
                            </div>
                        </div>

                        <div class="mb-3 position-relative">
                            <label class="form-label fw-bold text-secondary mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">SELECT PAYMENT METHOD:</label>
                            <div class="dropdown">
                                <button class="btn btn-light border w-100 text-dark fw-semibold text-start d-flex justify-content-between align-items-center py-2 bg-white" type="button" id="paymentDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <span id="selected-payment-text"><i class="bi bi-wallet2 text-purple me-2"></i>Choose Option</span>
                                    <i class="bi bi-chevron-down small"></i>
                                </button>
                                <ul class="dropdown-menu w-100 shadow-sm border py-2 mt-1" style="z-index: 1080;">
                                    <li><a class="dropdown-item py-2 fw-medium" href="#" onclick="selectPayment('Cash')"><i class="bi bi-cash-stack text-success me-2"></i>Cash Payment</a></li>
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

                        <button type="button" class="btn btn-lg w-100 mt-2 py-3 fw-bold text-white shadow-sm rounded-3" style="background-color: var(--purple-primary); border: none; transition: all 0.2s ease;" onclick="processCheckout()">
                            Pay <span id="pay-btn-amount">(₱0.00)</span> <i class="bi bi-arrow-right-circle-fill ms-1"></i>
                        </button>
                    </div>
                    
                    <div id="checkout-receipt-pane" class="d-none p-3 rounded receipt-card"></div>
                </div>
            </div>

        </div>
    </div>

    <script src="../LIBRARIES/bootstrap.bundle.min.js"></script>
    <script>
        let products = <?php echo json_encode($products_json); ?>;
        let bestSellers = <?php echo json_encode($best_sellers_array); ?>;
        let cart = [], totalAmount = 0, selectedPaymentMethod = '';
        
        let currentBestSellerIndex = 0;

        function renderBestSellerWidget() {
            const container = document.getElementById('bestseller-widget-wrapper');
            if (!container || bestSellers.length === 0) return;

            const bs = bestSellers[currentBestSellerIndex];
            const matched_prod = products.find(p => p.id === bs.id);
            const isOut = matched_prod ? (matched_prod.available <= 0) : true;

            let dotsHTML = '';
            bestSellers.forEach((item, idx) => {
                const activeClass = idx === currentBestSellerIndex ? 'active' : '';
                dotsHTML += `<span class="dot ${activeClass}" onclick="switchBestSeller(${idx})"></span>`;
            });

            container.innerHTML = `
                <div class="bestseller-hero-wrapper ${isOut ? 'out-of-stock-card' : ''}" ${isOut ? '' : `onclick="addToCart(${bs.id})"`} style="cursor: ${isOut ? 'not-allowed' : 'pointer'};">
                    <!-- Left Side Text & Action -->
                    <div class="bestseller-banner-content">
                        <h3 class="fw-bold text-white mb-1 text-uppercase" style="letter-spacing: 0.5px; font-size: 1.4rem;">${bs.name}</h3>
                        <div class="fs-4 fw-bold text-warning mb-2">₱${parseFloat(bs.price).toFixed(2)}</div>
                        <p class="text-light opacity-75 small mb-3">Your favorites, made better. Click to add directly to order.</p>
                        <div class="d-flex align-items-center gap-2">
                            <button class="bestseller-order-btn m-0">
                                Order Now <i class="bi bi-arrow-right"></i>
                            </button>
                            <span class="badge bg-dark bg-opacity-25 text-light border border-light border-opacity-25 ms-2"><i class="bi bi-bag-check-fill text-success me-1"></i>${bs.qty} sold</span>
                        </div>
                    </div>

                    <!-- Right Side Big Image -->
                    <div class="bestseller-banner-img-container">
                        ${bs.image ? `<img src="${bs.image}" alt="${bs.name}" class="bestseller-hero-img">` : `<div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted bg-light"><i class="bi bi-cup-hot fs-1 opacity-50"></i></div>`}
                    </div>
                </div>
                <div class="dots-container">${dotsHTML}</div>
            `;
        }

        function switchBestSeller(index) {
            currentBestSellerIndex = index;
            renderBestSellerWidget();
        }

        function renderProductGrid(filter = '') {
            const categories = [...new Set(products.map(p => p.category))];
            categories.forEach(cat => {
                const grid = document.getElementById(`grid-${cat}`);
                if(!grid) return;
                grid.innerHTML = '';
                const filtered = products.filter(p => p.category === cat && p.name.toLowerCase().includes(filter.toLowerCase()));
                
                filtered.forEach(prod => {
                    const col = document.createElement('div');
                    col.className = 'col-md-3 col-sm-6';
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
                        <div class="card product-card h-100 ${isOutOfStock ? 'out-of-stock-card' : ''}" ${isOutOfStock ? '' : `onclick="addToCart(${prod.id})"`}>
                            <div class="product-img-wrapper">
                                ${prod.image ? `<img src="${prod.image}" alt="${prod.name}">` : `<div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted"><i class="bi bi-cup-hot fs-2 opacity-50"></i></div>`}
                            </div>
                            <div class="card-body p-3 d-flex flex-column justify-content-between">
                                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                    <div>
                                        <div class="fw-bold mb-1" style="font-size: 0.9rem; color: var(--purple-primary);">₱${prod.price.toFixed(2)}</div>
                                        <h6 class="fw-semibold text-dark text-truncate m-0" style="font-size: 0.82rem; max-width: 120px;" title="${prod.name}">${prod.name}</h6>
                                    </div>
                                    <div class="card-action-btn shadow-sm">
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
            const iconClass = method === 'Cash' ? 'bi-cash-stack text-success' : 'bi-credit-card text-primary';
            document.getElementById('selected-payment-text').innerHTML = `<i class="bi ${iconClass} me-2"></i>${method}`;
            
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

        function addToCart(id) {
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
                            <div class="d-flex justify-content-between align-items-center py-1">
                                <span class="small text-secondary">${ing.name}</span>
                                <select class="form-select form-select-sm w-auto py-0 px-2" style="font-size: 0.75rem;" onchange="setModifier(${item.lineId}, ${ing.ingredient_id}, this.value)">
                                    <option value="normal" ${current === 'normal' ? 'selected' : ''}>Normal</option>
                                    <option value="remove" ${current === 'remove' ? 'selected' : ''}>No ${ing.name}</option>
                                    <option value="extra" ${current === 'extra' ? 'selected' : ''}>Extra ${ing.name}</option>
                                </select>
                            </div>`;
                    }).join('');
                    customizePanel = `<div class="mt-2 p-2 rounded-2 border bg-white">${rows}</div>`;
                }

                container.innerHTML += `
                    <div class="bg-white p-2 rounded-3 border shadow-sm">
                        <div class="d-flex justify-content-between align-items-center">
                            <div style="max-width: 120px;">
                                <span class="fw-bold d-block text-dark text-truncate" style="font-size: 0.80rem;" title="${item.name}">${item.name}</span>
                                ${modifierTags.length > 0 ? `<span class="d-block text-purple" style="font-size: 0.60rem; color: var(--purple-primary);">${modifierTags.join(', ')}</span>` : ''}
                            </div>
                            <div class="d-flex align-items-center gap-1 bg-light border rounded-pill px-1 py-0">
                                <button class="btn btn-sm btn-link text-dark p-0 px-1 text-decoration-none fw-bold" onclick="updateQuantity(${item.lineId}, -1)">-</button>
                                <span class="fw-bold small px-1">${item.quantity}</span>
                                <button class="btn btn-sm btn-link text-dark p-0 px-1 text-decoration-none fw-bold" onclick="updateQuantity(${item.lineId}, 1)">+</button>
                            </div>
                            <span class="fw-bold small text-dark">₱${(item.price * item.quantity).toFixed(2)}</span>
                        </div>
                        ${recipe.length > 0 ? `
                            <div class="text-end mt-1">
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" style="font-size: 0.65rem; color: var(--purple-primary);" onclick="toggleCustomize(${item.lineId})">
                                    <i class="bi bi-sliders"></i> ${isExpanded ? 'Hide' : 'Customize'}
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
                Swal.fire({ icon: 'warning', title: 'Empty Cart', text: 'Please add items to your basket before checking out.', confirmButtonColor: '#8b5cf6' });
                return;
            }
            if (!selectedPaymentMethod) {
                Swal.fire({ icon: 'warning', title: 'Payment Method Required', text: 'Please choose a payment method.', confirmButtonColor: '#8b5cf6' });
                return;
            }

            const amountTendered = document.getElementById('amount-tendered').value;
            const cardDigits = document.getElementById('card-digits').value.trim();

            if (selectedPaymentMethod === 'Cash') {
                if (!amountTendered || parseFloat(amountTendered) < totalAmount) {
                    Swal.fire({ icon: 'error', title: 'Insufficient Payment', text: `Amount entered is less than the total bill.`, confirmButtonColor: '#8b5cf6' });
                    return;
                }
                if (parseFloat(amountTendered) > (totalAmount + 1000)) {
                    Swal.fire({ icon: 'error', title: 'Excessive Amount', text: `Maximum allowed change is ₱1,000.`, confirmButtonColor: '#8b5cf6' });
                    return;
                }
            }

            if (selectedPaymentMethod === 'Card') {
                if (cardDigits.length !== 4) {
                    Swal.fire({ icon: 'error', title: 'Card Verification Required', text: 'Please input the last 4 digits of the card.', confirmButtonColor: '#8b5cf6' });
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
                            <div class="d-flex justify-content-between"><span>Amount Paid:</span><span>₱${parseFloat(data.tendered).toFixed(2)}</span></div>
                            <div class="d-flex justify-content-between"><span>Change Due:</span><span class="fw-bold">₱${parseFloat(data.change).toFixed(2)}</span></div>
                        </div>
                        <div class="text-center no-print">
                            <button class="btn btn-sm btn-dark w-100 mb-2" onclick="window.print()"><i class="bi bi-printer"></i> Print Receipt</button>
                            <button class="btn btn-sm text-white w-100" style="background-color: var(--purple-primary);" onclick="window.location.reload()">Done / New Order</button>
                        </div>`;

                    document.getElementById('checkout-interactive-pane').classList.add('d-none');
                    const receiptPane = document.getElementById('checkout-receipt-pane');
                    receiptPane.innerHTML = receiptHTML;
                    receiptPane.classList.remove('d-none');

                    Swal.fire({
                        icon: 'success',
                        title: 'Sale Completed',
                        html: `<div class="receipt-card p-2 rounded text-start">${receiptHTML}</div>`,
                        confirmButtonColor: '#911d1d',
                        confirmButtonText: 'Close',
                        width: '360px'
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Transaction Failed', text: data.message || 'Unable to complete checkout.', confirmButtonColor: '#ef4444' });
                }
            })
            .catch(err => {
                Swal.fire({ icon: 'error', title: 'Server Error', text: 'Checkout process encountered an error.', confirmButtonColor: '#8b5cf6' });
            });
        }

        renderProductGrid();
        renderBestSellerWidget();

        document.getElementById('logoutBtn').addEventListener('click', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Are you sure?',
                text: "You will be logged out of your account.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#8b5cf6',
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