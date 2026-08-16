<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role'])) {
    header("Location: ../../PAGES/login.php");
    exit();
}

require_once __DIR__ . '/../BACKEND/db_inventory.php';

$budget_query = "SELECT SUM(sales_items.quantity * sales_items.price_at_sale) as total_company_budget FROM sales_items JOIN sales ON sales_items.sale_id = sales.id WHERE YEAR(sales.created_at) = YEAR(CURDATE())";
$budget_res = $conn->query($budget_query);
$raw_company_budget = ($budget_res && $row = $budget_res->fetch_assoc()) ? ($row['total_company_budget'] ?? 0) : 0;

$deducted_query = "SELECT SUM(amount) as total_deducted FROM budget_requests WHERE status = 'Fully Approved (Admin)' AND YEAR(created_at) = YEAR(CURDATE())";
$deducted_res = $conn->query($deducted_query);
$total_deducted = ($deducted_res && $d_row = $deducted_res->fetch_assoc()) ? ($d_row['total_deducted'] ?? 0) : 0;

$total_company_budget = $raw_company_budget - $total_deducted;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_restock'])) {
    $title = trim($_POST['title']);
    $amount = (float)$_POST['amount'];
    $desc = trim($_POST['desc']);
    $requested_by = $_SESSION['username'] ?? 'Inventory Staff';
    $department = 'Inventory / Kitchen';
    $status = 'Pending';
    
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gradient-to-br from-purple-50/40 via-white to-slate-50 text-slate-800 antialiased font-sans">
    <div class="flex min-h-screen w-full">
        <div class="bg-slate-900 border-r border-slate-800 shadow-xl shrink-0 hidden md:block">
            <?php include '../../PAGES/sidebar.php'; ?>
        </div>

        <div class="flex-1 min-w-0 bg-transparent min-h-screen flex flex-col">
            <header class="h-[60px] bg-white border-b border-purple-100 px-6 flex items-center justify-between shrink-0 shadow-sm">
                <h1 class="text-xl font-black text-purple-700 tracking-wide">Inventory Status & Restock Requests</h1>
            </header>

            <div class="p-6 flex-1 overflow-y-auto">
                <div class="flex gap-2 mb-6 flex-wrap">
                    <?php if (!empty($categories_array)): ?>
                        <?php foreach ($categories_array as $cat): ?>
                            <a href="inventory.php?category_id=<?php echo $cat['id']; ?>"
                               class="px-4 py-2 rounded-xl font-semibold text-sm transition-all duration-300 transform hover:-translate-y-0.5 <?php echo ($active_category_id == $cat['id']) ? 'bg-purple-600 text-white shadow-lg shadow-purple-200 ring-2 ring-purple-400 ring-offset-1' : 'border border-purple-200 text-purple-700 bg-white hover:bg-purple-50'; ?>">
                               <?php echo htmlspecialchars($cat['name']); ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="overflow-x-auto border border-purple-100 rounded-2xl bg-white shadow-xl">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-purple-50/70 border-b border-purple-100">
                                <th class="text-purple-800 font-black px-6 py-4 text-xs uppercase tracking-wider">Ingredient Name</th>
                                <th class="text-purple-800 font-black px-6 py-4 text-xs uppercase tracking-wider">Unit</th>
                                <th class="text-purple-800 font-black px-6 py-4 text-xs uppercase tracking-wider">Price per Unit / kg (PHP)</th>
                                <th class="text-purple-800 font-black px-6 py-4 text-xs uppercase tracking-wider">Stock Status</th>
                                <th class="text-purple-800 font-black px-6 py-4 text-xs uppercase tracking-wider text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-purple-50">
                            <?php if ($ingredients_result && $ingredients_result->num_rows > 0): ?>
                                <?php while ($ingredient = $ingredients_result->fetch_assoc()): ?>
                                    <?php 
                                        $item_price = $ingredient['price'] > 0 ? $ingredient['price'] : 50.00; 
                                    ?>
                                    <tr class="hover:bg-purple-50/40 transition-colors duration-150">
                                        <td class="font-bold text-slate-900 px-6 py-4"><?php echo htmlspecialchars($ingredient['ingredient_name']); ?></td>
                                        <td class="text-slate-600 px-6 py-4 font-medium"><?php echo htmlspecialchars($ingredient['unit']); ?></td>
                                        <td class="text-purple-600 px-6 py-4 font-bold">₱<?php echo number_format($item_price, 2); ?></td>
                                        <td class="px-6 py-4">
                                            <?php $formatted_stock = rtrim(rtrim(number_format($ingredient['stock'], 2), '0'), '.'); ?>
                                            <?php if ($ingredient['stock'] <= 0): ?>
                                                <span class="inline-flex items-center bg-rose-100 text-rose-700 text-xs font-bold px-3 py-1.5 rounded-full animate-pulse">
                                                    Out of Stock (0)
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center bg-purple-50 text-purple-700 text-xs font-semibold px-3 py-1.5 rounded-full border border-purple-200">
                                                    <?php echo $formatted_stock; ?> <?php echo htmlspecialchars($ingredient['unit']); ?> Available
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <button type="button" 
                                                    onclick="openRestockModal('<?php echo htmlspecialchars($ingredient['ingredient_name'], ENT_QUOTES); ?>', <?php echo $item_price; ?>, <?php echo $total_company_budget; ?>, '<?php echo htmlspecialchars($ingredient['unit'], ENT_QUOTES); ?>')"
                                                    class="bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs px-3.5 py-2 rounded-xl shadow-md shadow-purple-100 inline-flex items-center gap-1.5 cursor-pointer transition-all transform hover:-translate-y-0.5 active:translate-y-0">
                                                <i class="bi bi-wallet2"></i> Request Budget
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-12 text-slate-400">No ingredients found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Restock Request Modal -->
    <div id="restockModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs hidden items-center justify-center z-50 transition-all duration-300">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-purple-100 relative transform transition-all animate-scale-up">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-black text-slate-900 flex items-center gap-2">
                    <i class="bi bi-wallet2 text-purple-600 text-xl"></i> Restock Budget Request
                </h3>
                <button type="button" onclick="closeRestockModal()" class="text-slate-400 hover:text-slate-600 cursor-pointer transition-colors">
                    <i class="bi bi-x-lg text-lg"></i>
                </button>
            </div>
            
            <form id="restockForm" action="inventory.php<?php echo isset($_GET['category_id']) ? '?category_id=' . (int)$_GET['category_id'] : ''; ?>" method="POST" onsubmit="return validateBudget(event)">
                <input type="hidden" name="submit_restock" value="1">
                <input type="hidden" name="title" id="modal_title">

                <div class="bg-purple-50/50 border border-purple-100 rounded-xl p-3 mb-4 text-xs">
                    <div class="flex justify-between mb-1.5">
                        <span class="text-slate-500 font-medium">Current Company Budget:</span>
                        <span class="font-black text-slate-800" id="display_current_budget">₱0.00</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 font-medium">Restock Cost:</span>
                        <span class="font-black text-purple-600" id="display_deduction">₱0.00</span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="block text-xs font-black text-slate-600 uppercase mb-1">Item to Restock</label>
                    <input type="text" id="display_ingredient_name" readonly class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm text-slate-700 font-bold">
                </div>

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-black text-slate-600 uppercase mb-1">Price per Unit/kg</label>
                        <input type="text" id="display_unit_price" readonly class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm text-slate-700 font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-black text-slate-600 uppercase mb-1">Quantity</label>
                        <input type="number" id="restock_quantity" value="10" min="1" max="100" step="any" oninput="calculateTotalCost()" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm font-bold focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition-all" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="block text-xs font-black text-slate-600 uppercase mb-1">Total Requested Amount (PHP)</label>
                    <input type="number" step="0.01" name="amount" id="modal_amount" readonly class="w-full bg-purple-50/70 border border-purple-200 rounded-xl px-3.5 py-2.5 text-base text-purple-700 font-black">
                </div>

                <div class="mb-5">
                    <label class="block text-xs font-black text-slate-600 uppercase mb-1">Description / Notes</label>
                    <textarea name="desc" id="modal_desc" rows="2" class="w-full border border-slate-300 rounded-xl px-3.5 py-2 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition-all" required></textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeRestockModal()" class="px-4 py-2.5 border border-slate-200 rounded-xl text-sm font-semibold hover:bg-slate-50 transition-colors cursor-pointer">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-sm font-bold shadow-lg shadow-purple-200 transition-all transform hover:-translate-y-0.5 active:translate-y-0 cursor-pointer">Submit to Finance</button>
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
            const qty = parseFloat(document.getElementById('restock_quantity').value) || 0;
            const requestedAmount = parseFloat(document.getElementById('modal_amount').value) || 0;
            
            if (qty > 100) {
                event.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Quantity!',
                    text: 'The maximum allowed quantity for restock is 100.',
                    confirmButtonColor: '#9333ea'
                });
                return false;
            }

            if (requestedAmount > globalCompanyBudget) {
                event.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Insufficient Balance!',
                    text: 'The requested amount exceeds the current available company budget.',
                    confirmButtonColor: '#9333ea'
                });
                return false;
            }
            return true;
        }

        function closeRestockModal() {
            document.getElementById('restockModal').classList.remove('flex');
            document.getElementById('restockModal').classList.add('hidden');
        }

        <?php if (isset($_SESSION['success_message'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '<?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>',
                confirmButtonColor: '#9333ea'
            });
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: '<?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>',
                confirmButtonColor: '#9333ea'
            });
        <?php endif; ?>
    </script>
</body>
</html>
<?php $conn->close(); ?>