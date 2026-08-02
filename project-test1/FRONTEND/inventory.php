<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role'])) {
    header("Location: ../../PAGES/login.php");
    exit();
}

require_once __DIR__ . '/../BACKEND/db_inventory.php';

// Kunin ang Total Company Budget mula sa sales at ibawas ang mga naaprubahang restock expenses (Fully Approved)
$budget_query = "SELECT SUM(sales_items.quantity * sales_items.price_at_sale) as total_company_budget FROM sales_items JOIN sales ON sales_items.sale_id = sales.id WHERE YEAR(sales.created_at) = YEAR(CURDATE())";
$budget_res = $conn->query($budget_query);
$raw_company_budget = ($budget_res && $row = $budget_res->fetch_assoc()) ? ($row['total_company_budget'] ?? 0) : 0;

// Kunin ang total na nabawas mula sa mga naaprubahang budget requests ng admin ngayong taon
$deducted_query = "SELECT SUM(amount) as total_deducted FROM budget_requests WHERE status = 'Fully Approved (Admin)' AND YEAR(created_at) = YEAR(CURDATE())";
$deducted_res = $conn->query($deducted_query);
$total_deducted = ($deducted_res && $d_row = $deducted_res->fetch_assoc()) ? ($d_row['total_deducted'] ?? 0) : 0;

// Net Company Budget (Sales minus Approved Restock Requests)
$total_company_budget = $raw_company_budget - $total_deducted;

// Proseso ng pag-submit ng Restock Request mula sa Modal patungo sa database
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_restock'])) {
    $title = trim($_POST['title']);
    $amount = (float)$_POST['amount'];
    $desc = trim($_POST['desc']);
    $requested_by = $_SESSION['username'] ?? 'Inventory Staff';
    $department = 'Inventory / Kitchen';
    $status = 'Pending';
    
    // Server-side validation para sa insufficient balance
    if ($amount > $total_company_budget) {
        $_SESSION['error_message'] = "Insufficient company budget for this restock request!";
    } else {
        $request_id = 'REQ-' . strtoupper(substr(uniqid(), -6));

        $insert_stmt = $conn->prepare("INSERT INTO budget_requests (request_id, title, requested_by, department, amount, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $insert_stmt->bind_param("ssssds", $request_id, $title, $requested_by, $department, $amount, $status);
        
        if ($insert_stmt->execute()) {
            $_SESSION['success_message'] = "Restock budget request successfully sent to finance!";
        } else {
            $_SESSION['error_message'] = "Failed to send budget request.";
        }
        $insert_stmt->close();
    }
    
    header("Location: inventory.php" . (isset($_GET['category_id']) ? '?category_id=' . (int)$_GET['category_id'] : ''));
    exit();
}

$categories_result = $conn->query(
    "SELECT DISTINCT categories.*
     FROM categories
     JOIN ingredients ON ingredients.category_id = categories.id
     ORDER BY categories.id ASC"
);
$categories_array = [];
if ($categories_result) {
    while ($cat = $categories_result->fetch_assoc()) {
        $categories_array[] = $cat;
    }
}
$default_category_id = !empty($categories_array) ? (int)$categories_array[0]['id'] : 0;
$active_category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : $default_category_id;

$ingredients_stmt = $conn->prepare(
    "SELECT id, ingredient_name, unit, stock, price
     FROM ingredients
     WHERE category_id = ?
     ORDER BY ingredient_name ASC"
);
$ingredients_stmt->bind_param("i", $active_category_id);
$ingredients_stmt->execute();
$ingredients_result = $ingredients_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple POS - Inventory Status & Action Stocks</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-50 text-gray-800 antialiased font-sans">
    <div class="flex min-h-screen w-full">
        <div class="bg-white border-r border-gray-200 block">
            <?php include '../../PAGES/sidebar.php'; ?>
        </div>

        <div class="flex-1 min-w-0 bg-white min-h-screen flex flex-col">
            <header class="h-[60px] bg-white border-b border-gray-200 px-6 flex items-center justify-between shrink-0">
                <h1 class="text-xl font-bold text-orange-600">Inventory Status & Restock Requests</h1>
            </header>

            <div class="p-6 flex-1 overflow-y-auto">
                <div class="flex gap-2 mb-6 flex-wrap">
                    <?php if (!empty($categories_array)): ?>
                        <?php foreach ($categories_array as $cat): ?>
                            <a href="inventory.php?category_id=<?php echo $cat['id']; ?>"
                               class="px-4 py-2 rounded-md font-semibold text-sm transition-all duration-200 <?php echo ($active_category_id == $cat['id']) ? 'bg-orange-600 text-white shadow-sm' : 'border border-orange-600 text-orange-600 hover:bg-orange-50'; ?>">
                               <?php echo htmlspecialchars($cat['name']); ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="overflow-x-auto border border-gray-200 rounded-lg bg-white shadow-sm">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-orange-50 border-b border-gray-200">
                                <th class="text-orange-700 font-bold px-6 py-4 text-sm uppercase">Ingredient Name</th>
                                <th class="text-orange-700 font-bold px-6 py-4 text-sm uppercase">Unit</th>
                                <th class="text-orange-700 font-bold px-6 py-4 text-sm uppercase">Price per Unit / kg (PHP)</th>
                                <th class="text-orange-700 font-bold px-6 py-4 text-sm uppercase">Stock Status</th>
                                <th class="text-orange-700 font-bold px-6 py-4 text-sm uppercase text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if ($ingredients_result && $ingredients_result->num_rows > 0): ?>
                                <?php while ($ingredient = $ingredients_result->fetch_assoc()): ?>
                                    <?php 
                                        $item_price = $ingredient['price'] > 0 ? $ingredient['price'] : 50.00; 
                                    ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="font-semibold text-gray-700 px-6 py-4"><?php echo htmlspecialchars($ingredient['ingredient_name']); ?></td>
                                        <td class="text-gray-600 px-6 py-4"><?php echo htmlspecialchars($ingredient['unit']); ?></td>
                                        <td class="text-gray-600 px-6 py-4 font-medium text-orange-600">₱<?php echo number_format($item_price, 2); ?></td>
                                        <td class="px-6 py-4">
                                            <?php $formatted_stock = rtrim(rtrim(number_format($ingredient['stock'], 2), '0'), '.'); ?>
                                            <?php if ($ingredient['stock'] <= 0): ?>
                                                <span class="inline-flex items-center bg-red-100 text-red-800 text-xs font-bold px-3 py-1.5 rounded-full">
                                                    Out of Stock (0)
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center bg-gray-100 text-gray-800 text-xs font-medium px-3 py-1.5 rounded-full border">
                                                    <?php echo $formatted_stock; ?> <?php echo htmlspecialchars($ingredient['unit']); ?> Available
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <button type="button" 
                                                    onclick="openRestockModal('<?php echo htmlspecialchars($ingredient['ingredient_name'], ENT_QUOTES); ?>', <?php echo $item_price; ?>, <?php echo $total_company_budget; ?>, '<?php echo htmlspecialchars($ingredient['unit'], ENT_QUOTES); ?>')"
                                                    class="bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs px-3 py-2 rounded shadow-sm inline-flex items-center gap-1.5 cursor-pointer">
                                                <i class="bi bi-wallet2"></i> Request Budget
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-12 text-gray-400">No ingredients found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Restock Request Modal -->
    <div id="restockModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl max-w-lg w-full p-6 shadow-xl relative">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <i class="bi bi-wallet2 text-orange-600"></i> Restock Budget Request
                </h3>
                <button type="button" onclick="closeRestockModal()" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                    <i class="bi bi-x-lg text-lg"></i>
                </button>
            </div>
            
            <form id="restockForm" action="inventory.php<?php echo isset($_GET['category_id']) ? '?category_id=' . (int)$_GET['category_id'] : ''; ?>" method="POST" onsubmit="return validateBudget(event)">
                <input type="hidden" name="submit_restock" value="1">
                <input type="hidden" name="title" id="modal_title">

                <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 mb-4 text-xs">
                    <div class="flex justify-between mb-1">
                        <span class="text-slate-500 font-medium">Current Company Budget:</span>
                        <span class="font-bold text-slate-800" id="display_current_budget">₱0.00</span>
                    </div>
                    <div class="flex justify-between mb-1">
                        <span class="text-slate-500 font-medium">Restock Cost:</span>
                        <span class="font-bold text-orange-600" id="display_deduction">₱0.00</span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Item to Restock</label>
                    <input type="text" id="display_ingredient_name" readonly class="w-full bg-gray-100 border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-700 font-semibold">
                </div>

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Price per Unit/kg</label>
                        <input type="text" id="display_unit_price" readonly class="w-full bg-gray-100 border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-700 font-semibold">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Quantity</label>
                        <input type="number" id="restock_quantity" value="10" min="1" step="any" oninput="calculateTotalCost()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-bold" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Total Requested Amount (PHP)</label>
                    <input type="number" step="0.01" name="amount" id="modal_amount" readonly class="w-full bg-orange-50 border border-orange-300 rounded-lg px-3 py-2 text-base text-orange-700 font-bold">
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Description / Notes</label>
                    <textarea name="desc" id="modal_desc" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required></textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeRestockModal()" class="px-4 py-2 border rounded-lg text-sm font-semibold cursor-pointer">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg text-sm font-semibold cursor-pointer">Submit to Finance</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentItemPrice = 0;
        let globalCompanyBudget = 0;
        let currentIngredientUnit = '';

        function openRestockModal(ingredientName, unitPrice, companyBudget, unit) {
            currentItemPrice = unitPrice;
            globalCompanyBudget = companyBudget;
            currentIngredientUnit = unit;

            document.getElementById('display_ingredient_name').value = ingredientName;
            document.getElementById('display_unit_price').value = '₱' + unitPrice.toFixed(2);
            document.getElementById('display_current_budget').innerText = '₱' + companyBudget.toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('restock_quantity').value = 10;
            
            calculateTotalCost();
            document.getElementById('restockModal').classList.remove('hidden');
            document.getElementById('restockModal').classList.add('flex');
        }

        function calculateTotalCost() {
            const qty = parseFloat(document.getElementById('restock_quantity').value) || 0;
            const totalCost = qty * currentItemPrice;
            const ingredientName = document.getElementById('display_ingredient_name').value;

            document.getElementById('modal_title').value = `Restock ${qty} ${currentIngredientUnit} of ${ingredientName}`;
            document.getElementById('modal_desc').value = `Restock request for ${qty} ${currentIngredientUnit} of ${ingredientName}. Total cost calculation based on unit price.`;
            
            document.getElementById('modal_amount').value = totalCost.toFixed(2);
            document.getElementById('display_deduction').innerText = '-₱' + totalCost.toLocaleString('en-US', {minimumFractionDigits: 2});
        }

        function validateBudget(event) {
            const requestedAmount = parseFloat(document.getElementById('modal_amount').value) || 0;
            
            if (requestedAmount > globalCompanyBudget) {
                event.preventDefault(); // Pigilan ang pag-submit ng form
                Swal.fire({
                    icon: 'error',
                    title: 'Insufficient Balance!',
                    text: 'The requested amount exceeds the current available company budget.',
                    confirmButtonColor: '#ea580c'
                });
                return false;
            }
            return true;
        }

        function closeRestockModal() {
            document.getElementById('restockModal').classList.remove('flex');
            document.getElementById('restockModal').classList.add('hidden');
        }

        // SweetAlert para sa Session Messages mula sa PHP
        <?php if (isset($_SESSION['success_message'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '<?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>',
                confirmButtonColor: '#ea580c'
            });
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: '<?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>',
                confirmButtonColor: '#ea580c'
            });
        <?php endif; ?>
    </script>
</body>
</html>
<?php $conn->close(); ?>