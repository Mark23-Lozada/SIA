<?php
session_start();

// 1. Siguraduhin muna na may naka-login na user
if (!isset($_SESSION['role'])) {
    header("Location: ../../PAGES/login.php");
    exit();
}

require_once __DIR__ . '/../BACKEND/db_inventory.php';

$current_page = basename($_SERVER['PHP_SELF']);

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

// Kumuha ng mga Ingredients base sa pinindot na kategorya
$ingredients_stmt = $conn->prepare(
    "SELECT id, ingredient_name, description, unit, stock
     FROM ingredients
     WHERE category_id = ?
     ORDER BY ingredient_name ASC"
);
$ingredients_stmt->bind_param("i", $active_category_id);
$ingredients_stmt->execute();
$ingredients_result = $ingredients_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-white">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple POS - Ingredients</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Custom transitions para sa sidebar toggle gamit ang vanilla tailwind setup */
        #sidebar-container { transition: margin-left 0.3s ease; }
        .sidebar-hidden #sidebar-container { margin-left: -16rem; } /* -w-64 equivalent */
    </style>
</head>

<body class="h-full text-slate-800 font-sans antialiased">

    <div class="flex h-screen w-full overflow-hidden bg-white">
        <div id="sidebar-container" class="flex-shrink-0 w-64 h-full bg-slate-900">
            <?php include '../../PAGES/sidebar.php'; ?>
        </div>

        <div id="main-wrapper" class="flex-1 flex flex-col h-full overflow-hidden">
            
            <header class="flex items-center justify-between px-6 bg-white border-b border-gray-200" style="height: 60px;">
                <div class="flex items-center gap-4">
                    <button id="burgerToggle" type="button" class="inline-flex items-center justify-center p-2 rounded-md text-white bg-orange-600 hover:bg-orange-700 focus:outline-none transition-colors">
                        <i class="bi bi-list text-xl leading-none"></i>
                    </button>
                    <h1 class="text-xl font-bold text-orange-600">Ingredients</h1>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto p-6 bg-white">

                <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                    <div class="flex flex-wrap gap-2">
                        <?php if (!empty($categories_array)): ?>
                            <?php foreach ($categories_array as $cat): ?>
                                <a href="ingredients.php?category_id=<?php echo $cat['id']; ?>"
                                   class="px-4 py-2 text-sm font-medium rounded-md transition-colors <?php echo ($active_category_id == $cat['id']) ? 'bg-orange-500 text-white shadow-sm' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'; ?>">
                                   <?php echo htmlspecialchars($cat['name']); ?>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <a href="inventory.php?category_id=<?php echo $active_category_id; ?>" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-green-600 hover:bg-green-700 rounded-md shadow-sm transition-colors">
                        <i class="bi bi-arrow-repeat"></i> Refill Stock
                    </a>
                </div>

                <div class="overflow-hidden border border-gray-200 rounded-lg shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="w-[30%] px-6 py-3 text-left text-xs font-bold text-orange-600 uppercase tracking-wider">Ingredient Name</th>
                                    <th scope="col" class="w-[35%] px-6 py-3 text-left text-xs font-bold text-orange-600 uppercase tracking-wider">Description</th>
                                    <th scope="col" class="w-[15%] px-6 py-3 text-left text-xs font-bold text-orange-600 uppercase tracking-wider">Unit</th>
                                    <th scope="col" class="w-[20%] px-6 py-3 text-left text-xs font-bold text-orange-600 uppercase tracking-wider">Stock Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if ($ingredients_result && $ingredients_result->num_rows > 0): ?>
                                    <?php while ($ingredient = $ingredients_result->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                                <?php echo htmlspecialchars($ingredient['ingredient_name']); ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                                                <?php echo htmlspecialchars($ingredient['description']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                                <?php echo htmlspecialchars($ingredient['unit']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <?php
                                                $formatted_stock = rtrim(rtrim(number_format($ingredient['stock'], 2), '0'), '.');
                                                ?>
                                                <?php if ($ingredient['stock'] <= 0): ?>
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                        <i class="bi bi-exclamation-triangle-fill"></i> Out of Stock
                                                    </span>
                                                <?php elseif ($ingredient['stock'] <= 5): ?>
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                                        <i class="bi bi-exclamation-circle-fill"></i> Low: <?php echo $formatted_stock; ?> <?php echo htmlspecialchars($ingredient['unit']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                        <?php echo $formatted_stock; ?> <?php echo htmlspecialchars($ingredient['unit']); ?> Available
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-400">
                                            <i class="bi bi-inbox text-2xl block mb-2 text-gray-300"></i> No ingredients found under this category.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script>
        document.getElementById('burgerToggle').addEventListener('click', function () {
            document.body.classList.toggle('sidebar-hidden');
        });
    </script>
</body>
</html>
<?php
$ingredients_stmt->close();
$conn->close();
?>