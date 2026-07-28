<?php
session_start();

// 1. Siguraduhin muna na may naka-login na user
if (!isset($_SESSION['role'])) {
    header("Location: ../../PAGES/login.php");
    exit();
}
require_once __DIR__ . '/../BACKEND/db_inventory.php';

$current_page = basename($_SERVER['PHP_SELF']);

// ============================================================
// THIS PAGE MANAGES MENU ITEMS (not ingredients -- see ingredients.php).
// - Edit an item's name/price/image
// - Toggle availability on/off (soft delete -- no hard delete, since
//   items may already be referenced by past sales in sales_items)
// ============================================================

// ============================================================
// Handles the optional item image upload (same logic as add_item.php).
// Returns the relative path to store, or null if no new image was uploaded
// (existing image should be left alone) or on failure (flags $swal_trigger).
// ============================================================
function handle_item_image_upload_edit() {
    if (!isset($_FILES['item_image']) || $_FILES['item_image']['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // no new image chosen -- keep whatever is already saved
    }
    if ($_FILES['item_image']['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowed_types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = mime_content_type($_FILES['item_image']['tmp_name']);
    if (!isset($allowed_types[$mime])) {
        return null;
    }

    $max_size_bytes = 5 * 1024 * 1024; // 5MB
    if ($_FILES['item_image']['size'] > $max_size_bytes) {
        return null;
    }

    $upload_dir = __DIR__ . '/../UPLOADS/items/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $extension = $allowed_types[$mime];
    $unique_filename = uniqid('item_', true) . '.' . $extension;
    $destination = $upload_dir . $unique_filename;

    if (move_uploaded_file($_FILES['item_image']['tmp_name'], $destination)) {
        return 'UPLOADS/items/' . $unique_filename;
    }

    return null;
}

// ---------------------------------------------------------
// POST: update item name + price + (optionally) image
// ---------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_item'])) {
    $item_id       = (int)$_POST['item_id'];
    $item_name     = preg_replace('/\s+/', ' ', trim($_POST['item_name']));
    $price         = (float)$_POST['price'];
    $current_cat   = (int)$_POST['current_category_id'];
    $remove_image  = isset($_POST['remove_image']) && $_POST['remove_image'] == '1';

    // Same validation range used when items are first created (add_item.php)
    if ($item_id > 0 && !empty($item_name) && $price >= 10 && $price <= 10000) {
        $new_image_path = handle_item_image_upload_edit();

        if ($new_image_path !== null) {
            // A new image was uploaded -- replace it
            $stmt = $conn->prepare("UPDATE items SET item_name = ?, price = ?, image = ? WHERE id = ?");
            $stmt->bind_param("sdsi", $item_name, $price, $new_image_path, $item_id);
        } elseif ($remove_image) {
            // User explicitly chose to clear the image, no replacement uploaded
            $stmt = $conn->prepare("UPDATE items SET item_name = ?, price = ?, image = NULL WHERE id = ?");
            $stmt->bind_param("sdi", $item_name, $price, $item_id);
        } else {
            // No image change -- leave the existing image untouched
            $stmt = $conn->prepare("UPDATE items SET item_name = ?, price = ? WHERE id = ?");
            $stmt->bind_param("sdi", $item_name, $price, $item_id);
        }
        $stmt->execute();
        $stmt->close();
    }

    header("Location: items.php?category_id=" . $current_cat);
    exit();
}

// ---------------------------------------------------------
// POST: toggle availability (soft delete / restore)
// ---------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['toggle_availability'])) {
    $item_id     = (int)$_POST['item_id'];
    $new_status  = (int)$_POST['new_status']; // 1 = available, 0 = disabled
    $current_cat = (int)$_POST['current_category_id'];

    if ($item_id > 0) {
        $stmt = $conn->prepare("UPDATE items SET is_available = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_status, $item_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: items.php?category_id=" . $current_cat);
    exit();
}

// ---------------------------------------------------------
// Categories (only ones that actually have menu items)
// ---------------------------------------------------------
$categories_result = $conn->query(
    "SELECT DISTINCT categories.*
     FROM categories
     JOIN items ON items.category_id = categories.id
     ORDER BY categories.id ASC"
);
$categories_array = [];
if ($categories_result) {
    while ($cat = $categories_result->fetch_assoc()) {
        $categories_array[] = $cat;
    }
}

// Default to the first actual item category (not a hardcoded ID)
$default_category_id = !empty($categories_array) ? (int)$categories_array[0]['id'] : 0;
$active_category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : $default_category_id;

// ---------------------------------------------------------
// Items in the active category (including disabled ones, so they can be re-enabled)
// ---------------------------------------------------------
$items_stmt = $conn->prepare(
    "SELECT id, item_name, image, price, is_available
     FROM items
     WHERE category_id = ?
     ORDER BY item_name ASC"
);
$items_stmt->bind_param("i", $active_category_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple POS - Manage Items</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
</head>

<body class="bg-gray-50 font-sans antialiased flex flex-row w-screen h-screen overflow-hidden">

    <div class="shrink-0 h-full bg-white border-r border-gray-200">
        <?php include '../../PAGES/sidebar.php'; ?>
    </div>
    
    <div id="main-wrapper" class="flex-1 flex flex-col min-w-0 h-full bg-gray-50 overflow-hidden">
        <header class="navbar bg-white border-b border-gray-200 px-6 flex items-center justify-between shrink-0" style="height: 60px;">
            <button class="btn bg-orange-500 hover:bg-orange-600 text-white flex items-center justify-center p-2 rounded transition-colors duration-200" type="button" id="burgerToggle">
                <span class="navbar-toggler-icon"></span>
            </button>
            <h1 class="text-xl font-bold text-orange-500">Manage Items</h1>
        </header>

        <div class="content-body p-6 flex-1 overflow-y-auto">

            <div class="flex gap-2 mb-6 flex-wrap">
                <?php if (!empty($categories_array)): ?>
                    <?php foreach ($categories_array as $cat): ?>
                        <a href="items.php?category_id=<?php echo $cat['id']; ?>"
                           class="px-4 py-2 text-sm font-medium rounded-md border transition-all duration-200 <?php echo ($active_category_id == $cat['id']) ? 'bg-orange-500 text-white border-orange-500 shadow-sm' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'; ?>">
                           <?php echo htmlspecialchars($cat['name']); ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="overflow-x-auto bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <table class="table min-w-full align-middle mb-0">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th scope="col" style="width: 10%;" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Image</th>
                            <th scope="col" style="width: 30%;" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Item Name</th>
                            <th scope="col" style="width: 20%;" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Price</th>
                            <th scope="col" style="width: 15%;" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" style="width: 25%;" class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if ($items_result && $items_result->num_rows > 0): ?>
                            <?php while ($item = $items_result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition-colors <?php echo $item['is_available'] ? '' : 'text-gray-400 bg-gray-50/50'; ?>">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <?php if (!empty($item['image'])): ?>
                                            <img src="../<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>" class="w-12 h-12 rounded-lg object-cover border border-gray-200 shadow-sm">
                                        <?php else: ?>
                                            <div class="w-12 h-12 rounded-lg flex items-center justify-content-center border border-orange-100 bg-orange-50/50">
                                                <i class="bi bi-image text-orange-400 text-lg"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap font-bold text-gray-900"><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap font-semibold text-gray-800">₱<?php echo number_format($item['price'], 2); ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <?php if ($item['is_available']): ?>
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">Available</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        <div class="btn-group btn-group-sm inline-flex shadow-sm rounded-md" role="group">
                                            <button type="button" class="btn border border-orange-200 text-orange-500 bg-white hover:bg-orange-50 font-bold edit-btn flex items-center gap-1"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editModal"
                                                    data-id="<?php echo $item['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($item['item_name']); ?>"
                                                    data-price="<?php echo $item['price']; ?>"
                                                    data-image="<?php echo !empty($item['image']) ? '../' . htmlspecialchars($item['image']) : ''; ?>">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </button>

                                            <form method="POST" action="items.php" class="inline m-0"
                                                  onsubmit="return confirm('<?php echo $item['is_available'] ? 'Disable this item? It will no longer appear at checkout.' : 'Re-enable this item?'; ?>');">
                                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                <input type="hidden" name="current_category_id" value="<?php echo $active_category_id; ?>">
                                                <input type="hidden" name="new_status" value="<?php echo $item['is_available'] ? 0 : 1; ?>">
                                                <button type="submit" name="toggle_availability" class="btn border-t border-b border-r px-3 py-1.5 transition-colors font-medium rounded-r-md <?php echo $item['is_available'] ? 'border-red-200 text-red-600 bg-white hover:bg-red-50' : 'border-green-200 text-white bg-green-600 hover:bg-green-700'; ?>">
                                                    <?php if ($item['is_available']): ?>
                                                        <i class="bi bi-slash-circle"></i> Disable
                                                    <?php else: ?>
                                                        <i class="bi bi-check-circle"></i> Enable
                                                    <?php endif; ?>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-12 text-gray-400">
                                    <i class="bi bi-inbox text-5xl block mb-3 opacity-30 text-orange-500"></i> 
                                    <span class="text-sm">No items found under this category.</span>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-2xl shadow-xl overflow-hidden bg-white">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h5 class="text-lg font-bold text-orange-500 flex items-center gap-2" id="editModalLabel">
                        <i class="bi bi-pencil-square"></i> Edit Item
                    </h5>
                    <button type="button" class="btn-close text-gray-400 hover:text-gray-600" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editForm" action="items.php" method="POST" enctype="multipart/form-data" novalidate>
                    <div class="p-6 space-y-4">
                        <input type="hidden" name="item_id" id="modal_item_id">
                        <input type="hidden" name="current_category_id" value="<?php echo $active_category_id; ?>">
                        <input type="hidden" name="remove_image" id="modal_remove_image" value="0">

                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-600">Item Image</label>
                            <div id="editImageDropzone" class="border-2 border-dashed border-orange-400 rounded-xl flex flex-col items-center justify-center p-6 cursor-pointer bg-orange-50/30 hover:bg-orange-50/60 transition-all duration-200">
                                <div id="editDropzonePrompt" class="hidden text-center">
                                    <i class="bi bi-cloud-arrow-up-fill text-3xl text-orange-500 mb-2 block"></i>
                                    <span class="text-xs text-gray-500 block">Drag & drop, or click to browse</span>
                                </div>
                                <img id="editImagePreview" src="" alt="Preview" class="hidden max-h-36 rounded-lg border border-gray-200 shadow-sm object-cover">
                            </div>
                            <input type="file" id="modal_item_image" name="item_image" accept="image/jpeg,image/png,image/webp" class="hidden">
                            <button type="button" id="editRemoveImageBtn" class="hidden px-3 py-1.5 bg-white border border-red-200 rounded-md text-xs font-semibold text-red-600 hover:bg-red-50 transition-colors items-center gap-1">
                                <i class="bi bi-trash"></i> Remove Image
                            </button>
                        </div>

                        <div class="space-y-1">
                            <label for="modal_item_name" class="block text-sm font-semibold text-gray-600">Item Name <span class="text-red-500">*</span></label>
                            <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent bg-gray-50" id="modal_item_name" name="item_name" required>
                        </div>

                        <div class="space-y-1">
                            <label for="modal_price" class="block text-sm font-semibold text-gray-600">Price (PHP ₱) <span class="text-red-500">*</span></label>
                            <input type="number" step="0.01" min="10" max="10000" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent bg-gray-50" id="modal_price" name="price" required>
                            <span class="text-xs text-gray-400 block mt-1">Must be between ₱10.00 and ₱10,000.00</span>
                        </div>
                    </div>
                    
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-2">
                        <button type="button" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_item" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white font-bold rounded-lg text-sm shadow-sm transition-colors">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('burgerToggle').addEventListener('click', function () {
            document.body.classList.toggle('sidebar-hidden');
        });

        const editDropzone = document.getElementById('editImageDropzone');
        const editFileInput = document.getElementById('modal_item_image');
        const editPrompt = document.getElementById('editDropzonePrompt');
        const editPreview = document.getElementById('editImagePreview');
        const editRemoveBtn = document.getElementById('editRemoveImageBtn');
        const editRemoveFlag = document.getElementById('modal_remove_image');

        function showEditPreviewFromFile(file) {
            const reader = new FileReader();
            reader.onload = function (e) {
                editPreview.src = e.target.result;
                editPreview.classList.remove('hidden');
                editPrompt.classList.add('hidden');
                editRemoveBtn.classList.remove('hidden');
                editRemoveBtn.classList.add('flex');
                editRemoveFlag.value = '0';
            };
            reader.readAsDataURL(file);
        }

        editDropzone.addEventListener('click', () => editFileInput.click());

        editFileInput.addEventListener('change', function () {
            if (editFileInput.files && editFileInput.files[0]) showEditPreviewFromFile(editFileInput.files[0]);
        });

        editDropzone.addEventListener('dragover', function (e) {
            e.preventDefault();
            editDropzone.classList.add('bg-orange-100/50');
        });

        editDropzone.addEventListener('dragleave', function () {
            editDropzone.classList.remove('bg-orange-100/50');
        });

        editDropzone.addEventListener('drop', function (e) {
            e.preventDefault();
            editDropzone.classList.remove('bg-orange-100/50');
            if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                editFileInput.files = e.dataTransfer.files;
                showEditPreviewFromFile(e.dataTransfer.files[0]);
            }
        });

        editRemoveBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            editFileInput.value = '';
            editPreview.src = '';
            editPreview.classList.add('hidden');
            editPrompt.classList.remove('hidden');
            editRemoveBtn.classList.add('hidden');
            editRemoveBtn.classList.remove('flex');
            editRemoveFlag.value = '1';
        });

        document.querySelectorAll('.edit-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                document.getElementById('modal_item_id').value = this.getAttribute('data-id');
                document.getElementById('modal_item_name').value = this.getAttribute('data-name');
                document.getElementById('modal_price').value = this.getAttribute('data-price');

                editFileInput.value = '';
                editRemoveFlag.value = '0';
                const existingImage = this.getAttribute('data-image');
                if (existingImage) {
                    editPreview.src = existingImage;
                    editPreview.classList.remove('hidden');
                    editPrompt.classList.add('hidden');
                    editRemoveBtn.classList.remove('hidden');
                    editRemoveBtn.classList.add('flex');
                } else {
                    editPreview.src = '';
                    editPreview.classList.add('hidden');
                    editPrompt.classList.remove('hidden');
                    editRemoveBtn.classList.add('hidden');
                    editRemoveBtn.classList.remove('flex');
                }
            });
        });

        document.getElementById('editForm').addEventListener('submit', function (e) {
            const nameField = document.getElementById('modal_item_name');
            const priceField = document.getElementById('modal_price');
            const priceInput = parseFloat(priceField.value);

            if (nameField.value.trim() === "") {
                e.preventDefault();
                Swal.fire({ icon: 'warning', title: 'Item Name Required', text: 'Please enter an item name.', confirmButtonColor: '#f97316' });
                return false;
            }

            if (isNaN(priceInput) || priceInput < 10 || priceInput > 10000) {
                e.preventDefault();
                Swal.fire({ icon: 'error', title: 'Invalid Price', text: 'Price must be between ₱10.00 and ₱10,000.00.', confirmButtonColor: '#f97316' });
                return false;
            }
        });
    </script>
</body>
</html>
<?php
$items_stmt->close();
$conn->close();
?>