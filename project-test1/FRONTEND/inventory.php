<?php
session_start();

// 1. Siguraduhin muna na may naka-login na user
if (!isset($_SESSION['role'])) {
    header("Location: ../../PAGES/login.php");
    exit();
}

require_once __DIR__ . '/../BACKEND/db_inventory.php';

$current_page = basename($_SERVER['PHP_SELF']);
$MAX_STOCK = 1000;

// Proseso para sa PAG-REFILL ng Stock kapag sinubmit ang popup form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['refill_ingredient'])) {
    $ingredient_id = (int)$_POST['ingredient_id'];
    $add_quantity   = (float)$_POST['add_quantity'];
    $current_cat   = (int)$_POST['current_category_id'];

    if ($ingredient_id > 0 && $add_quantity > 0) {
        $refill_stmt = $conn->prepare("UPDATE ingredients SET stock = LEAST(stock + ?, ?) WHERE id = ?");
        $refill_stmt->bind_param("ddi", $add_quantity, $MAX_STOCK, $ingredient_id);
        $refill_stmt->execute();
        $refill_stmt->close();

        // Refresh ang page sa parehong kategorya
        header("Location: inventory.php?category_id=" . $current_cat);
        exit();
    }
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
    "SELECT id, ingredient_name, unit, stock
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
    <title>Simple POS - Refill Inventory</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
    <style>
        /* Custom toggle for sidebar since Tailwind uses utility-first state */
        .sidebar-hidden #sidebar-container {
            display: none !important;
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-800 antialiased font-sans">

    <div class="flex min-h-screen w-full">
        <div id="sidebar-container" class="bg-white border-r border-gray-200 block">
            <?php include '../../PAGES/sidebar.php'; ?>
        </div>

        <div id="main-wrapper" class="flex-1 min-w-0 bg-white min-h-screen flex flex-col">
            <header class="navbar h-[60px] bg-white border-b border-gray-200 px-6 flex items-center justify-between shrink-0">
                <button class="bg-orange-600 hover:bg-orange-700 text-white p-2 rounded-md focus:outline-none transition-colors" type="button" id="burgerToggle">
                    <i class="bi bi-list text-xl block leading-none"></i>
                </button>
                <h1 class="text-xl font-bold text-orange-600">Refill Inventory</h1>
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
                                <th scope="col" class="w-[40%] text-orange-700 font-bold px-6 py-4 text-sm uppercase tracking-wider">Ingredient Name</th>
                                <th scope="col" class="w-[15%] text-orange-700 font-bold px-6 py-4 text-sm uppercase tracking-wider">Unit</th>
                                <th scope="col" class="w-[25%] text-orange-700 font-bold px-6 py-4 text-sm uppercase tracking-wider">Stock Status</th>
                                <th scope="col" class="w-[20%] text-orange-700 font-bold px-6 py-4 text-sm uppercase tracking-wider text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if ($ingredients_result && $ingredients_result->num_rows > 0): ?>
                                <?php while ($ingredient = $ingredients_result->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="font-semibold text-gray-700 px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($ingredient['ingredient_name']); ?></td>
                                        <td class="text-gray-600 px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($ingredient['unit']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $formatted_stock = rtrim(rtrim(number_format($ingredient['stock'], 2), '0'), '.');
                                            ?>
                                            <?php if ($ingredient['stock'] <= 0): ?>
                                                <span class="inline-flex items-center bg-red-100 text-red-800 text-xs font-bold px-3 py-1.5 rounded-full">
                                                    <i class="bi bi-exclamation-triangle-fill me-1.5"></i> Out of Stock (0)
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center bg-gray-100 text-gray-800 text-xs font-medium px-3 py-1.5 rounded-full border border-gray-200">
                                                    <?php echo $formatted_stock; ?> <?php echo htmlspecialchars($ingredient['unit']); ?> Available
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <button type="button" class="bg-orange-600 hover:bg-orange-700 text-white font-bold text-sm px-4 py-2 rounded shadow-sm inline-flex items-center gap-1.5 transition-colors refill-btn"
                                                    data-id="<?php echo $ingredient['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($ingredient['ingredient_name']); ?>"
                                                    data-unit="<?php echo htmlspecialchars($ingredient['unit']); ?>"
                                                    data-stock="<?php echo $formatted_stock; ?>">
                                                <i class="bi bi-arrow-repeat"></i> Refill
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-12 text-gray-400">
                                        <i class="bi bi-inbox text-4xl block mb-2 text-gray-300"></i> No ingredients found under this category.
                                    </td>
                                endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="refillModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-300">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 overflow-hidden transform scale-95 transition-transform duration-300">
            <div class="border-b border-gray-200 px-6 py-4 flex items-center justify-between bg-white">
                <h5 class="text-lg font-bold text-orange-600 flex items-center"><i class="bi bi-arrow-repeat me-2"></i>Refill Ingredient Stock</h5>
                <button type="button" id="closeModalBtn" class="text-gray-400 hover:text-gray-600 focus:outline-none text-xl leading-none">&times;</button>
            </div>
            <form id="refillForm" action="inventory.php" method="POST" novalidate>
                <div class="p-6 space-y-4">
                    <input type="hidden" name="ingredient_id" id="modal_ingredient_id">
                    <input type="hidden" name="current_category_id" value="<?php echo $active_category_id; ?>">

                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1">Ingredient Name</label>
                        <input type="text" class="w-full px-3 py-2 text-gray-700 bg-gray-100 border border-gray-200 rounded focus:outline-none" id="modal_ingredient_name" disabled>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1">Current Stock</label>
                        <input type="text" class="w-full px-3 py-2 text-gray-700 bg-gray-100 border border-gray-200 rounded focus:outline-none" id="modal_current_stock" disabled>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-orange-600 mb-1">Quantity to Add <span class="text-red-500">*</span></label>
                        <input type="number" step="0.01" min="0.01" class="w-full px-3 py-2 text-gray-800 bg-white border border-orange-400 rounded focus:outline-none focus:ring-2 focus:ring-orange-500" id="modal_add_quantity" name="add_quantity" required>
                    </div>
                </div>
                <div class="border-t border-gray-200 px-6 py-4 flex justify-end gap-2 bg-gray-50">
                    <button type="button" id="cancelModalBtn" class="bg-white border border-gray-300 text-gray-700 text-sm font-medium px-4 py-2 rounded hover:bg-gray-100 transition-colors">Cancel</button>
                    <button type="submit" name="refill_ingredient" class="bg-orange-600 hover:bg-orange-700 text-white text-sm font-bold px-5 py-2 rounded shadow-sm transition-colors">Confirm Refill</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Burger Sidebar Toggle
        document.getElementById('burgerToggle').addEventListener('click', function () {
            document.body.classList.toggle('sidebar-hidden');
        });

        // Modal Functionality (Pure JS replacing Bootstrap Data-Attributes)
        const modal = document.getElementById('refillModal');
        const modalContent = modal.querySelector('.transform');
        
        function openModal() {
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modalContent.classList.remove('scale-95');
                modalContent.classList.add('scale-100');
            }, 10);
        }

        function closeModal() {
            modal.classList.add('opacity-0');
            modalContent.classList.remove('scale-100');
            modalContent.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        document.getElementById('closeModalBtn').addEventListener('click', closeModal);
        document.getElementById('cancelModalBtn').addEventListener('click', closeModal);

        // Ipasa ang data ng pinindot na ingredient papunta sa loob ng Refill Modal
        document.querySelectorAll('.refill-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                document.getElementById('modal_ingredient_id').value = this.getAttribute('data-id');
                document.getElementById('modal_ingredient_name').value = this.getAttribute('data-name');
                document.getElementById('modal_current_stock').value = this.getAttribute('data-stock') + ' ' + this.getAttribute('data-unit');
                document.getElementById('modal_add_quantity').value = '';
                openModal();
            });
        });

        // SweetAlert2 (SWAL) Validation Handler bago mag-submit ang form
        document.getElementById('refillForm').addEventListener('submit', function (e) {
            const qtyField = document.getElementById('modal_add_quantity');
            const qtyInput = parseFloat(qtyField.value);

            if (qtyField.value.trim() === "") {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Quantity Required',
                    text: 'Please enter how much stock you want to add.',
                    confirmButtonColor: '#ea580c' // Tailwind orange-600
                });
                return false;
            }

            if (isNaN(qtyInput) || qtyInput <= 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Quantity',
                    text: 'The quantity to add must be a positive number.',
                    confirmButtonColor: '#ea580c' // Tailwind orange-600
                });
                return false;
            }
        });
    </script>
</body>
</html>
<?php
$ingredients_stmt->close();
$conn->close();
?>