<?php
session_start();

// 1. Siguraduhin muna na may naka-login na user
if (!isset($_SESSION['role'])) {
    header("Location: ../../PAGES/login.php");
    exit();
}

include('../BACKEND/db_inventory.php');

$current_page = basename($_SERVER['PHP_SELF']);

// ===================================================================
// POST: add or update one ingredient line in a recipe
// ===================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_recipe_line'])) {
    $item_id       = (int)$_POST['item_id'];
    $ingredient_id = (int)$_POST['ingredient_id'];
    $quantity_used = (float)$_POST['quantity_used'];

    if ($item_id > 0 && $ingredient_id > 0 && $quantity_used > 0) {
        // ON DUPLICATE KEY UPDATE handles both "add new line" and "edit existing line"
        // because (item_id, ingredient_id) is a UNIQUE key.
        $stmt = $conn->prepare(
            "INSERT INTO menu_item_ingredients (item_id, ingredient_id, quantity_used) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity_used = VALUES(quantity_used)"
        );
        $stmt->bind_param("iid", $item_id, $ingredient_id, $quantity_used);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: recipe.php?item_id=" . $item_id);
    exit();
}

// ===================================================================
// POST: remove one ingredient line from a recipe
// ===================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['remove_recipe_line'])) {
    $line_id = (int)$_POST['line_id'];
    $item_id = (int)$_POST['item_id'];

    if ($line_id > 0) {
        $stmt = $conn->prepare("DELETE FROM menu_item_ingredients WHERE id = ?");
        $stmt->bind_param("i", $line_id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: recipe.php?item_id=" . $item_id);
    exit();
}

// ===================================================================
// Load the menu item currently being edited (default: first item found)
// ===================================================================
$all_items_result = $conn->query("SELECT id, item_name, image FROM items ORDER BY item_name ASC");
$all_items = [];
if ($all_items_result) {
    while ($row = $all_items_result->fetch_assoc()) { $all_items[] = $row; }
}

$selected_item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : (count($all_items) ? (int)$all_items[0]['id'] : 0);

// Find the selected item's own data (for the image thumbnail next to the picker)
$selected_item = null;
foreach ($all_items as $it) {
    if ($it['id'] == $selected_item_id) {
        $selected_item = $it;
        break;
    }
}

// Current recipe lines for the selected item (joined with ingredient name/unit)
$recipe_lines = [];
if ($selected_item_id > 0) {
    $stmt = $conn->prepare(
        "SELECT mii.id, mii.ingredient_id, mii.quantity_used, ing.ingredient_name, ing.unit
         FROM menu_item_ingredients mii
         JOIN ingredients ing ON mii.ingredient_id = ing.id
         WHERE mii.item_id = ?
         ORDER BY ing.ingredient_name ASC"
    );
    $stmt->bind_param("i", $selected_item_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $recipe_lines[] = $row; }
    $stmt->close();
}

// Ingredients not yet used in this recipe (for the "add ingredient" dropdown)
$available_ingredients = [];
if ($selected_item_id > 0) {
    $stmt = $conn->prepare(
        "SELECT id, ingredient_name, unit FROM ingredients
         WHERE id NOT IN (
             SELECT ingredient_id FROM menu_item_ingredients WHERE item_id = ?
         )
         ORDER BY ingredient_name ASC"
    );
    $stmt->bind_param("i", $selected_item_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $available_ingredients[] = $row; }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple POS - Recipe Management</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
</head>

<body class="bg-gray-50 text-gray-800 font-sans antialiased">

    <div class="flex h-screen w-full overflow-hidden">
        <div id="sidebar-container" class="transition-all duration-300 dynamic-sidebar">
            <?php include '../../PAGES/sidebar.php'; ?>
        </div>

        <div class="flex-1 flex flex-col h-full overflow-y-auto bg-white">
            <header class="flex items-center justify-between px-6 border-b border-gray-200 bg-white" style="min-height: 60px;">
                <div class="flex items-center gap-4">
            
                    <h1 class="text-xl font-bold text-amber-500">Recipe Management</h1>
                </div>
            </header>

            <main class="p-6 max-w-7xl w-full mx-auto">

                <?php if (empty($all_items)): ?>
                    <div class="p-4 mb-4 text-blue-800 bg-blue-50 border border-blue-200 rounded-lg">
                        No menu items found. Please <a href="add_item.php" class="font-bold underline text-blue-700 hover:text-blue-900">add an item</a> first.
                    </div>
                <?php else: ?>

                <div class="mb-6 flex items-end gap-4 max-w-lg bg-gray-50 p-4 border border-gray-200 rounded-lg">
                    <div class="flex-grow">
                        <label class="block text-sm font-bold text-amber-500 mb-2">Select Menu Item</label>
                        <select class="w-full bg-white border border-gray-300 text-gray-900 rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-all" onchange="window.location.href='recipe.php?item_id=' + this.value">
                            <?php foreach ($all_items as $it): ?>
                                <option value="<?php echo $it['id']; ?>" <?php echo ($it['id'] == $selected_item_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($it['item_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($selected_item): ?>
                        <div class="flex-shrink-0">
                            <?php if (!empty($selected_item['image'])): ?>
                                <img src="../<?php echo htmlspecialchars($selected_item['image']); ?>" alt="<?php echo htmlspecialchars($selected_item['item_name']); ?>" class="rounded-lg w-16 h-16 object-cover border border-gray-200 shadow-sm">
                            <?php else: ?>
                                <div class="rounded-lg flex items-center justify-center w-16 h-16 bg-blue-5 border border-blue-100">
                                    <i class="bi bi-image text-2xl text-blue-400"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="overflow-x-auto border border-gray-200 rounded-lg shadow-sm mb-6 bg-white">
                    <table class="w-full text-sm text-left text-gray-700">
                        <thead class="text-xs text-blue-700 uppercase bg-blue-50 border-b border-gray-200">
                            <tr>
                                <th scope="col" class="px-6 py-4 font-bold text-amber-500">Ingredient</th>
                                <th scope="col" class="px-6 py-4 font-bold text-amber-500">Quantity Used Per Sale</th>
                                <th scope="col" class="px-6 py-4 font-bold text-amber-500 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($recipe_lines)): ?>
                                <tr>
                                    <td colspan="3" class="px-6 py-8 text-center text-gray-500">
                                        <i class="bi bi-inbox text-2xl block mb-2 text-gray-400"></i> No ingredients linked to this item yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recipe_lines as $line): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 font-bold text-gray-900"><?php echo htmlspecialchars($line['ingredient_name']); ?></td>
                                        <td class="px-6 py-4">
                                            <form class="flex items-center gap-2" method="POST" action="recipe.php">
                                                <input type="hidden" name="item_id" value="<?php echo $selected_item_id; ?>">
                                                <input type="hidden" name="ingredient_id" value="<?php echo $line['ingredient_id']; ?>">
                                                <input type="number" step="0.001" min="0.001" name="quantity_used"
                                                       value="<?php echo $line['quantity_used']; ?>"
                                                       class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-1.5 w-24" required>
                                                <span class="text-xs text-gray-500 font-medium"><?php echo htmlspecialchars($line['unit']); ?></span>
                                                <button type="submit" name="save_recipe_line" class="px-3 py-1.5 text-xs font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors">Save</button>
                                            </form>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <form method="POST" action="recipe.php" onsubmit="return confirm('Remove this ingredient from the recipe?');">
                                                <input type="hidden" name="item_id" value="<?php echo $selected_item_id; ?>">
                                                <input type="hidden" name="line_id" value="<?php echo $line['id']; ?>">
                                                <button type="submit" name="remove_recipe_line" class="p-2 text-sm text-white bg-red-500 hover:bg-red-600 rounded-lg transition-colors inline-flex items-center justify-center">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($available_ingredients)): ?>
                    <div class="bg-white border border-gray-200 rounded-lg p-5 shadow-sm max-w-xl">
                        <h6 class="text-base font-bold text-amber-500 mb-4 flex items-center gap-2">
                            <i class="bi bi-plus-circle"></i> Add Ingredient to Recipe
                        </h6>
                        <form method="POST" action="recipe.php" class="flex flex-wrap items-center gap-3">
                            <input type="hidden" name="item_id" value="<?php echo $selected_item_id; ?>">
                            
                            <div class="w-full sm:w-auto flex-1 min-w-[200px]">
                                <select name="ingredient_id" class="w-full bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-2" required>
                                    <?php foreach ($available_ingredients as $ing): ?>
                                        <option value="<?php echo $ing['id']; ?>">
                                            <?php echo htmlspecialchars($ing['ingredient_name']); ?> (<?php echo htmlspecialchars($ing['unit']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="w-full sm:w-auto">
                                <input type="number" step="0.001" min="0.001" name="quantity_used" placeholder="Qty" class="w-full bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-2 w-24" required>
                            </div>

                            <button type="submit" name="save_recipe_line" class="w-full sm:w-auto px-4 py-2 text-sm font-bold text-white bg-blue-500 hover:bg-amber-500 rounded-lg transition-colors shadow-sm">
                                Add
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <p class="text-sm text-gray-500 italic bg-gray-50 p-4 border border-gray-200 rounded-lg inline-block">All ingredients are already linked to this item's recipe.</p>
                <?php endif; ?>

                <?php endif; ?>

            </main>
        </div>
    </div>

    <script>
        // Toggler para sa sidebar
        document.getElementById('burgerToggle').addEventListener('click', function () {
            const sidebar = document.getElementById('sidebar-container');
            if(sidebar) {
                sidebar.classList.toggle('hidden');
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>