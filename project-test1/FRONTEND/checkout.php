<?php

include('../BACKEND/db_inventory.php');

date_default_timezone_set('Asia/Manila');

// Small helper so every JSON error response is consistent and short to write.
function respond_json($payload) {
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit();
}

// Converts a cart line's per-ingredient modifier choice into a quantity multiplier.
// 'remove' = 0x (skip entirely), 'extra' = 2x, anything else = 1x (normal).
function modifier_multiplier($value) {
    if ($value === 'remove') return 0;
    if ($value === 'extra') return 2;
    return 1;
}

// ===================================================================
// CHECKOUT PAGE DATA (categories, menu items, and their live availability)
// ===================================================================

// Categories -> used for the sidebar tabs / tab panes
// Only categories that actually have menu items -- excludes ingredient
// categories, since categories is a shared table between items and ingredients.
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

// Every recipe line (item -> ingredient -> qty used) joined with the ingredient's
// CURRENT stock, in one query. This is the only place ingredient stock is read
// for display purposes. Also carries ingredient id/name/unit so the frontend
// can offer per-order "remove" / "extra" customization limited to an item's
// actual recipe ingredients.
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

// Menu items. Items no longer carry their own manual stock number --
// availability (in the ingredient sense) is computed below from recipe data.
// is_available is a separate manual on/off switch (e.g. "86'd" items,
// seasonal items) -- only items marked available show up at checkout.
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

        // No recipe defined = NOT available at checkout (0), not "unlimited".
        // This forces every sellable item to have its ingredients mapped in
        // recipe.php before it can actually be sold.
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
// PROCESS A SALE (deduct ingredient stock, record the sale)
// ===================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'deduct_stock') {
    $cart_data = json_decode($_POST['cart_items'], true);

    if (empty($cart_data)) {
        exit(); // nothing to process
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

        // Server-side verification for invalid cash ranges
        if ($amount_tendered < $total_amount || $amount_tendered > ($total_amount + 1000)) {
            respond_json(["status" => "error", "message" => "Invalid payment amount processed!"]);
        }
        $change = $amount_tendered - $total_amount;
    } else {
        $amount_tendered = $total_amount;
        $change = 0;
    }

    // --- STEP 1: Total up how much of each ingredient this whole cart needs ---
    // Also reject any item that has NO recipe at all -- items without a recipe
    // are not sellable, even if someone bypasses the frontend and posts directly.
    //
    // Modifier multipliers (see modifier_multiplier() near the top of this file):
    // 'remove' = 0x (skip), 'extra' = 2x, anything else = 1x (normal).
    // A modifier is only ever applied to an ingredient_id that came from THIS
    // item's own recipe rows (fetched from the DB below) -- client-supplied
    // modifier keys for ingredients outside the recipe are simply never looked at.
    $required_by_ingredient = []; // ingredient_id => total amount needed
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

    // --- STEP 2: Verify enough stock exists BEFORE touching the database ---
    if (!empty($required_by_ingredient)) {
        $ids = array_keys($required_by_ingredient);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmt_check = $conn->prepare("SELECT id, ingredient_name, stock FROM ingredients WHERE id IN ($placeholders)");
        // Requires PHP 8.1+ for variadic bind_param. Tell me if your server is older
        // and I'll swap this for a call_user_func_array-based binding instead.
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

    // --- STEP 3: Record the sale and deduct ingredient stock, atomically ---
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

        $max_prep_minutes = 0; // the order is ready when its SLOWEST item finishes (kitchen works items in parallel)

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

            // Deduct every ingredient this menu item's recipe uses, respecting
            // any remove/extra modifier chosen for this cart line.
            // The "AND stock >= ?" is a race-condition safety net; the real
            // sufficiency check already happened in STEP 2 above.
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

        // Stamp when this order should be ready -- cooking.php / depart.php
        // read this timestamp to decide which list an order belongs in.
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
    <title>Simple POS - Checkout Panel</title>
    <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
    <link href="../CSS/checkout.css" rel="stylesheet">
    <style>
        @media print {
            body * { visibility: hidden; }
            #checkout-receipt-pane, #checkout-receipt-pane * { visibility: visible; }
            #checkout-receipt-pane { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
        }
        .receipt-card { background: #fff; color: #000; font-family: 'Courier New', Courier, monospace; border: 1px dashed #000; }
    </style>
</head>
<body>

    <div id="sidebar" class="d-flex flex-column p-3 ">
        <h4 class="text-center mb-4 fw-bold" style="color: white;"> PANNAKODA</h4>
        <hr class="border-secondary mb-2">

        <span class=" mb-2 px-2 fw-bold text-uppercase" style="color: orange;">Menu Categories</span>
        <hr class="border-secondary mb-2">
        <div class="nav flex-column nav-pills" id="sidebarCategoryTabs" role="tablist">
            <?php foreach ($categories_array as $index => $cat): 
                $target_id = strtolower(str_replace(' ', '', $cat['name']));
            ?>
                <button class="nav-link text-start sidebar-link <?php echo $index === 0 ? 'active' : ''; ?>" 
                        id="<?php echo $target_id; ?>-tab" data-bs-toggle="pill" data-bs-target="#cat-<?php echo $target_id; ?>" type="button" role="tab">
                    <i class="bi bi-tag me-2"></i> <?php echo htmlspecialchars($cat['name']); ?>
                </button>
            <?php endforeach; ?>
        </div>
      
    </div>

    <div id="main-wrapper">
        <header class="navbar navbar-dark px-3 d-flex justify-content-between" style="height: 60px;">
            <button style="background: orange;" class="btn" type="button" id="burgerToggle"><span class="navbar-toggler-icon"></span></button>
          
        </header>

        <main class="content-body">
            <div class="d-flex gap-3 align-items-stretch" style="min-height: calc(100vh - 110px);">
                <div class="p-4 flex-grow-1" style="width: 60%; background: white; border-radius: 10px; border: 1px solid orange;">
                    <div class="tab-content" id="categoryTabContent">
                        <?php foreach ($categories_array as $index => $cat): 
                            $target_id = strtolower(str_replace(' ', '', $cat['name']));
                        ?>
                            <div class="tab-pane fade show <?php echo $index === 0 ? 'active' : ''; ?>" id="cat-<?php echo $target_id; ?>" role="tabpanel">
                                <h4 style="color: #911d1d;" class="mb-4"><i class="bi bi-grid-fill me-2"></i><?php echo htmlspecialchars($cat['name']); ?></h4>
                                <div class="row g-3" id="grid-<?php echo $target_id; ?>"></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="p-3 d-flex flex-column justify-content-between" style="width: 40%; background: white; border-radius: 10px; border: 1px solid orange;">
                    <div id="checkout-interactive-pane">
                        <h4 style="color: #911d1d;" class="text-center mb-3"><i class="bi bi-receipt-cutoff me-2"></i>Current Order</h4>
                        <div style="background: white;" id="cart-wrapper" class="p-2 rounded border border-secondary">
                            <div id="cart-items-container" class="d-flex flex-column gap-2">
                                <p style="color: orange;" class=" text-center small my-auto py-4">Select items to add</p>
                            </div>
                            <hr class="border-secondary my-2">
                            <div class="d-flex justify-content-between align-items-center fw-bold text-white px-1">
                                <span class="small " style="color: orange;">TOTAL AMOUNT:</span>
                                <span class="fs-4" style="color: orange;" id="total-amount-display">₱0.00</span>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="dropdown mb-3">
                                <label style="color: orange;" class=" form-label fw-bold small mb-1">SELECT PAYMENT METHOD:</label>
                                <button class="btn btn-danger dropdown-toggle w-100" type="button" id="paymentDropdown" data-bs-toggle="dropdown"><i class="bi bi-wallet2 me-2"></i>Choose Option</button>
                                <ul class="dropdown-menu w-100 dropdown-menu-light text-dark">
                                    <li><a class="dropdown-item" href="#" onclick="selectPayment('Cash')">Cash</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="selectPayment('Card')">Card Terminal</a></li>
                                </ul>
                            </div>
                            
                            <div class="mb-3 d-none" id="cash-panel">
                                <label class=" form-label fw-bold small mb-0">CASH TENDERED:</label>
                                <input type="text" id="amount-tendered" maxlength="5" placeholder="e.g. 500" class="form-control bg-light text-dark border-secondary fw-bold fs-5" oninput="this.value = this.value.replace(/[^0-9]/g, ''); calculateChange();">
                            </div>
                            
                            <div class="mb-3 d-none" id="card-panel">
                                <label class="form-label fw-bold small mb-0 text-danger">CARD LAST 4 DIGITS:</label>
                                <input type="text" id="card-digits" maxlength="4" placeholder="e.g. 4321" class="form-control bg-light text-dark border-secondary fw-bold fs-5 text-center" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                            </div>

                            <div class="p-2 rounded bg-light border border-secondary d-flex justify-content-between align-items-center d-none" id="change-panel-wrapper">
                                <span class="small fw-bold text-dark" id="change-label">Change Due:</span>
                                <span class="fs-5 fw-bold text-dark" id="change-display">₱0.00</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary btn-lg w-100 mt-3" onclick="processCheckout()">Checkout & Print <i class="bi bi-arrow-right-circle-fill ms-1"></i></button>
                    </div>
                    
                    <div id="checkout-receipt-pane" class="d-none p-3 rounded receipt-card"></div>
                </div>
            </div>
        </main>
    </div>

    <script src="../LIBRARIES/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('burgerToggle').addEventListener('click', function() {
            document.body.classList.toggle('sidebar-hidden');
        });

        let products = <?php echo json_encode($products_json); ?>;
        let cart = [], totalAmount = 0, selectedPaymentMethod = '';

        function renderProductGrid() {
            const categories = [...new Set(products.map(p => p.category))];
            categories.forEach(cat => {
                const grid = document.getElementById(`grid-${cat}`);
                if(!grid) return;
                grid.innerHTML = '';
                const filtered = products.filter(p => p.category === cat);
                filtered.forEach(prod => {
                    const col = document.createElement('div');
                    col.className = 'col-md-4 col-sm-6';
                    const isOutOfStock = prod.available <= 0;
                    let stockLabel = '';
                    if (!prod.has_recipe) {
                        // Distinct from a real stock-out: this item was never configured, not just running low.
                        stockLabel = `<p class="text-danger fw-bold small mb-0 mt-2"><i class="bi bi-exclamation-triangle"></i> No Recipe Set</p>`;
                    } else if (isOutOfStock) {
                        stockLabel = `<p class="text-danger fw-bold small mb-0 mt-2">OUT OF STOCK</p>`;
                    } else {
                        stockLabel = `<p style="color: #911d1d;" class="small mb-0 mt-2">Makeable: ${prod.available}</p>`;
                    }
                    col.innerHTML = `
                        <div class="card product-card text-white p-3 text-center ${isOutOfStock ? 'out-of-stock-card' : ''}" ${isOutOfStock ? '' : `onclick="addToCart(${prod.id})"`}>
                            ${prod.image ? `<img src="${prod.image}" alt="${prod.name}" class="rounded mb-2" style="width: 100%; height: 90px; object-fit: cover;">` : `<div class="rounded mb-2 d-flex align-items-center justify-content-center" style="width: 100%; height: 90px; background: rgba(145,29,29,0.08);"><i class="bi bi-image" style="font-size: 1.5rem; color: #911d1d; opacity: 0.4;"></i></div>`}
                            <h6 style="color: #911d1d;">${prod.name}</h6>
                            <p style="color: #911d1d;" class="fw-bold mb-0">₱${prod.price.toFixed(2)}</p>
                            ${stockLabel}
                        </div>`;
                    grid.appendChild(col);
                });
            });
        }

        function selectPayment(method) {
            selectedPaymentMethod = method;
            document.getElementById('paymentDropdown').innerHTML = `<i class="bi bi-wallet2 me-2"></i>${method}`;
            
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

        let customizeExpanded = new Set(); // which cart line's ingredient panel is open
        let nextLineId = 1; // each cart entry (a specific item + its customization) gets its own id

        // Total quantity across ALL cart lines for a given item id -- used to
        // enforce ingredient availability across the whole item, not per-line.
        function getTotalQuantityForItem(itemId) {
            return cart.filter(i => i.id === itemId).reduce((sum, i) => sum + i.quantity, 0);
        }

        function addToCart(id) {
            const product = products.find(p => p.id === id);
            if (!product) return;
            if (product.available <= 0) return;
            if (getTotalQuantityForItem(id) >= product.available) return;

            // Only merge into an existing UNCUSTOMIZED line for this item.
            // A line with any active modifier stays separate, since "2 pancakes,
            // one with no butter" needs to print/deduct differently from a plain one.
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

        // value is 'normal' | 'remove' | 'extra'. Only ever called with an
        // ingredient_id pulled from that item's own product.recipe array below,
        // so there's no way to set a modifier for an ingredient that isn't
        // actually part of the item's recipe. Changing a modifier never merges
        // this line back into another -- it stays its own distinct cart entry.
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
                container.innerHTML = '<p class="text-dark text-center small my-auto py-4">Select items to add</p>';
                totalAmount = 0;
                document.getElementById('total-amount-display').innerText = '₱0.00';
                return;
            }
            totalAmount = 0;
            cart.forEach(item => {
                totalAmount += item.price * item.quantity;
                const product = products.find(p => p.id === item.id);
                const recipe = (product && product.recipe) ? product.recipe : [];
                const isExpanded = customizeExpanded.has(item.lineId);

                // Small inline summary of any active modifiers, shown even when collapsed
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
                                <span class="small text-dark">${ing.name}</span>
                                <select class="form-select form-select-sm" style="width: auto;" onchange="setModifier(${item.lineId}, ${ing.ingredient_id}, this.value)">
                                    <option value="normal" ${current === 'normal' ? 'selected' : ''}>Normal</option>
                                    <option value="remove" ${current === 'remove' ? 'selected' : ''}>No ${ing.name}</option>
                                    <option value="extra" ${current === 'extra' ? 'selected' : ''}>Extra ${ing.name}</option>
                                </select>
                            </div>`;
                    }).join('');
                    customizePanel = `<div class="mt-2 p-2 rounded border border-secondary bg-white bg-opacity-50">${rows}</div>`;
                }

                container.innerHTML += `
                    <div class="bg-secondary bg-opacity-25 p-2 rounded text-white border border-secondary">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="fw-bold d-block small text-dark">${item.name}</span>
                                ${modifierTags.length > 0 ? `<span class="d-block text-dark" style="font-size: 0.7rem; opacity: 0.75;">${modifierTags.join(', ')}</span>` : ''}
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <button class="btn btn-sm btn-danger px-2 py-0" onclick="updateQuantity(${item.lineId}, -1)">-</button>
                                <span class="fw-bold small text-dark">${item.quantity}</span>
                                <button class="btn btn-sm btn-success px-2 py-0" onclick="updateQuantity(${item.lineId}, 1)">+</button>
                            </div>
                            <span class="fw-bold small text-dark">₱${(item.price * item.quantity).toFixed(2)}</span>
                        </div>
                        ${recipe.length > 0 ? `
                            <button type="button" class="btn btn-link btn-sm p-0 mt-1 text-decoration-none" style="font-size: 0.75rem;" onclick="toggleCustomize(${item.lineId})">
                                <i class="bi bi-sliders"></i> ${isExpanded ? 'Hide' : 'Customize'} ingredients
                            </button>
                        ` : ''}
                        ${customizePanel}
                    </div>`;
            });
            document.getElementById('total-amount-display').innerText = `₱${totalAmount.toFixed(2)}`;
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
                changeDisplay.style.color = '#000000';
            }
        }

        function processCheckout() {
            if (cart.length === 0) {
                Swal.fire({ icon: 'warning', title: 'Empty Cart', text: 'Please add items to your basket before checking out.', confirmButtonColor: '#911d1d' });
                return;
            }
            if (!selectedPaymentMethod) {
                Swal.fire({ icon: 'warning', title: 'Payment Method Required', text: 'Please choose a payment method.', confirmButtonColor: '#911d1d' });
                return;
            }

            const amountTendered = document.getElementById('amount-tendered').value;
            const cardDigits = document.getElementById('card-digits').value.trim();

            if (selectedPaymentMethod === 'Cash') {
                if (!amountTendered || parseFloat(amountTendered) < totalAmount) {
                    Swal.fire({ 
                        icon: 'error', 
                        title: 'Insufficient Payment', 
                        text: `The amount entered (₱${parseFloat(amountTendered || 0).toFixed(2)}) is less than the total bill (₱${totalAmount.toFixed(2)}).`, 
                        confirmButtonColor: '#ef4444' 
                    });
                    return;
                }
                
                // Block crazy numbers like the one shown in image_ad9976.png
                if (parseFloat(amountTendered) > (totalAmount + 1000)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Excessive Amount',
                        text: `The entered amount is realistically too high for this transaction. Maximum allowed change is ₱1,000.`,
                        confirmButtonColor: '#ef4444'
                    });
                    return;
                }
            }

            if (selectedPaymentMethod === 'Card') {
                if (cardDigits.length !== 4) {
                    Swal.fire({ icon: 'error', title: 'Card Verification Required', text: 'Please input the last 4 digits of the card before verifying.', confirmButtonColor: '#ef4444' });
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
                            <div class="d-flex justify-content-between"><span>Payment Mode:</span><span>${data.payment_method}</span></div>
                            <div class="d-flex justify-content-between"><span>Amount Paid:</span><span>₱${parseFloat(data.tendered).toFixed(2)}</span></div>
                            <div class="d-flex justify-content-between"><span>Change Due:</span><span class="fw-bold">₱${parseFloat(data.change).toFixed(2)}</span></div>
                        </div>
                        <div class="text-center no-print">
                            <button class="btn btn-sm btn-dark w-100 mb-2" onclick="window.print()"><i class="bi bi-printer"></i> Print Receipt</button>
                            <button class="btn btn-sm btn-danger w-100" onclick="window.location.reload()">Done / New Order</button>
                        </div>`;

                    document.getElementById('checkout-interactive-pane').classList.add('d-none');
                    const receiptPane = document.getElementById('checkout-receipt-pane');
                    receiptPane.innerHTML = receiptHTML;
                    receiptPane.classList.remove('d-none');

                    Swal.fire({ icon: 'success', title: 'Sale Completed', text: 'Transaction Successful!', confirmButtonColor: '#911d1d' });
                } else {
                    Swal.fire({ icon: 'error', title: 'Transaction Failed', text: data.message || 'Unable to complete checkout.', confirmButtonColor: '#ef4444' });
                }
            })
            .catch(err => {
                Swal.fire({ icon: 'error', title: 'Server Error', text: 'Checkout process encountered an error.', confirmButtonColor: '#ef4444' });
            });
        }

        renderProductGrid();
         document.getElementById('logoutBtn').addEventListener('click', function(e) {
            e.preventDefault(); // Hinihinto ang normal na action

            Swal.fire({
    title: 'Are you sure?',
    text: "You will be logged out of your account.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#911d1d',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, Log out',
    cancelButtonText: 'Cancel',
    reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'logout.php?role=staff'; // Redirects here to end the staff session only
                }
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>