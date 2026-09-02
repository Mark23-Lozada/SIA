<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: ../../PAGES/login.php");
    exit();
}
require_once __DIR__ . '/../BACKEND/db_inventory.php';

$current_page = basename($_SERVER['PHP_SELF']);

function handle_item_image_upload_edit() {
    if (!isset($_FILES['item_image']) || $_FILES['item_image']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES['item_image']['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowed_types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = mime_content_type($_FILES['item_image']['tmp_name']);
    if (!isset($allowed_types[$mime])) {
        return null;
    }

    $max_size_bytes = 5 * 1024 * 1024;
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

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_item'])) {
    $item_id       = (int)$_POST['item_id'];
    $item_name     = preg_replace('/\s+/', ' ', trim($_POST['item_name']));
    $price         = (float)$_POST['price'];
    $current_cat   = (int)$_POST['current_category_id'];
    $remove_image  = isset($_POST['remove_image']) && $_POST['remove_image'] == '1';

    if ($item_id > 0 && !empty($item_name) && $price >= 10 && $price <= 10000) {
        $new_image_path = handle_item_image_upload_edit();

        if ($new_image_path !== null) {
            $stmt = $conn->prepare("UPDATE items SET item_name = ?, price = ?, image = ? WHERE id = ?");
            $stmt->bind_param("sdsi", $item_name, $price, $new_image_path, $item_id);
        } elseif ($remove_image) {
            $stmt = $conn->prepare("UPDATE items SET item_name = ?, price = ?, image = NULL WHERE id = ?");
            $stmt->bind_param("sdi", $item_name, $price, $item_id);
        } else {
            $stmt = $conn->prepare("UPDATE items SET item_name = ?, price = ? WHERE id = ?");
            $stmt->bind_param("sdi", $item_name, $price, $item_id);
        }
        $stmt->execute();
        $stmt->close();
    }

    header("Location: items.php?category_id=" . $current_cat);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['toggle_availability'])) {
    $item_id     = (int)$_POST['item_id'];
    $new_status  = (int)$_POST['new_status'];
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

$default_category_id = !empty($categories_array) ? (int)$categories_array[0]['id'] : 0;
$active_category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : $default_category_id;

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
    <style>
        #sidebar-container { transition: margin-left 0.3s ease-in-out; }
        .sidebar-hidden #sidebar-container { margin-left: -16rem; }
    </style>
</head>

<body class="bg-gradient-to-br from-orange-50/40 via-white to-slate-50 font-sans antialiased flex flex-row w-screen h-screen overflow-hidden">

    <div id="sidebar-container" class="shrink-0 h-full bg-slate-900 border-r border-slate-800 shadow-xl">
        <?php include '../../PAGES/sidebar.php'; ?>
    </div>
    
    <div id="main-wrapper" class="flex-1 flex flex-col min-w-0 h-full bg-transparent overflow-hidden">
        <header class="navbar bg-white border-b border-[#ff6b4a]/20 px-6 flex items-center justify-between shrink-0 shadow-sm" style="height: 60px;">
            <div class="flex items-center gap-4">
                <button id="burgerToggle" type="button" class="inline-flex items-center justify-center p-2 rounded-lg text-white bg-[#ff6b4a] hover:bg-[#fa4b2a] focus:outline-none transition-all transform hover:scale-105 active:scale-95 shadow-md">
                    <i class="bi bi-list text-xl leading-none"></i>
                </button>
                <h1 class="text-xl font-black text-[#fa4b2a] tracking-wide">Manage Items</h1>
            </div>
        </header>

        <div class="content-body p-6 flex-1 overflow-y-auto">

            <div class="flex gap-2 mb-6 flex-wrap">
                <?php if (!empty($categories_array)): ?>
                    <?php foreach ($categories_array as $cat): ?>
                        <a href="items.php?category_id=<?php echo $cat['id']; ?>"
                           class="px-4 py-2 text-sm font-semibold rounded-xl border transition-all duration-300 transform hover:-translate-y-0.5 <?php echo ($active_category_id == $cat['id']) ? 'bg-[#ff6b4a] text-white border-[#ff6b4a] shadow-lg shadow-[#ff6b4a]/20 ring-2 ring-[#ff6b4a]/50 ring-offset-1' : 'bg-white text-[#fa4b2a] border-[#ff6b4a]/30 hover:bg-orange-50'; ?>">
                           <?php echo htmlspecialchars($cat['name']); ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="overflow-x-auto bg-white rounded-2xl shadow-xl border border-[#ff6b4a]/20 p-4">
                <table class="table min-w-full align-middle mb-0">
                    <thead class="bg-orange-50/70 border-b border-[#ff6b4a]/20">
                        <tr>
                            <th scope="col" style="width: 10%;" class="px-4 py-3 text-left text-xs font-black text-[#fa4b2a] uppercase tracking-wider">Image</th>
                            <th scope="col" style="width: 30%;" class="px-4 py-3 text-left text-xs font-black text-[#fa4b2a] uppercase tracking-wider">Item Name</th>
                            <th scope="col" style="width: 20%;" class="px-4 py-3 text-left text-xs font-black text-[#fa4b2a] uppercase tracking-wider">Price</th>
                            <th scope="col" style="width: 15%;" class="px-4 py-3 text-left text-xs font-black text-[#fa4b2a] uppercase tracking-wider">Status</th>
                            <th scope="col" style="width: 25%;" class="px-4 py-3 text-center text-xs font-black text-[#fa4b2a] uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-orange-50">
                        <?php if ($items_result && $items_result->num_rows > 0): ?>
                            <?php while ($item = $items_result->fetch_assoc()): ?>
                                <tr class="hover:bg-orange-50/40 transition-colors duration-150 <?php echo $item['is_available'] ? '' : 'text-slate-400 bg-slate-50/50'; ?>">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <?php if (!empty($item['image'])): ?>
                                            <img src="../<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>" class="w-12 h-12 rounded-xl object-cover border border-[#ff6b4a]/30 shadow-sm">
                                        <?php else: ?>
                                            <div class="w-12 h-12 rounded-xl flex items-center justify-center border border-[#ff6b4a]/30 bg-orange-50">
                                                <i class="bi bi-image text-[#ff6b4a] text-lg"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap font-bold text-slate-900"><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap font-bold text-[#ff6b4a]">₱<?php echo number_format($item['price'], 2); ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <?php if ($item['is_available']): ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">Available</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        <div class="btn-group btn-group-sm inline-flex shadow-sm rounded-xl overflow-hidden" role="group">
                                            <button type="button" class="btn border border-[#ff6b4a]/30 text-[#ff6b4a] bg-white hover:bg-orange-50 font-bold edit-btn flex items-center gap-1.5 px-3 py-1.5 transition-colors"
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
                                                <button type="submit" name="toggle_availability" class="btn border px-3 py-1.5 transition-colors font-semibold <?php echo $item['is_available'] ? 'border-rose-200 text-rose-600 bg-white hover:bg-rose-50' : 'border-emerald-200 text-white bg-emerald-600 hover:bg-emerald-700'; ?>">
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
                                <td colspan="5" class="text-center py-12 text-slate-400">
                                    <i class="bi bi-inbox text-4xl block mb-2 text-orange-300"></i> 
                                    <span class="text-sm font-medium">No items found under this category.</span>
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
            <div class="modal-content border-0 rounded-2xl shadow-2xl overflow-hidden bg-white">
                <div class="px-6 py-4 border-b border-[#ff6b4a]/20 flex items-center justify-between bg-orange-50/50">
                    <h5 class="text-lg font-black text-[#fa4b2a] flex items-center gap-2" id="editModalLabel">
                        <i class="bi bi-pencil-square"></i> Edit Item
                    </h5>
                    <button type="button" class="btn-close text-slate-400 hover:text-slate-600 cursor-pointer" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editForm" action="items.php" method="POST" enctype="multipart/form-data" novalidate>
                    <div class="p-6 space-y-4">
                        <input type="hidden" name="item_id" id="modal_item_id">
                        <input type="hidden" name="current_category_id" value="<?php echo $active_category_id; ?>">
                        <input type="hidden" name="remove_image" id="modal_remove_image" value="0">

                        <div class="space-y-2">
                            <label class="block text-xs font-black text-slate-600 uppercase">Item Image</label>
                            <div id="editImageDropzone" class="border-2 border-dashed border-[#ff6b4a]/40 rounded-2xl flex flex-col items-center justify-center p-6 cursor-pointer bg-orange-50/30 hover:bg-orange-50/70 transition-all duration-300">
                                <div id="editDropzonePrompt" class="hidden text-center">
                                    <i class="bi bi-cloud-arrow-up-fill text-3xl text-[#ff6b4a] mb-2 block"></i>
                                    <span class="text-xs text-slate-500 font-medium block">Drag & drop, or click to browse</span>
                                </div>
                                <img id="editImagePreview" src="" alt="Preview" class="hidden max-h-36 rounded-xl border border-[#ff6b4a]/30 shadow-sm object-cover">
                            </div>
                            <input type="file" id="modal_item_image" name="item_image" accept="image/jpeg,image/png,image/webp" class="hidden">
                            <button type="button" id="editRemoveImageBtn" class="hidden px-3 py-1.5 bg-white border border-rose-200 rounded-xl text-xs font-bold text-rose-600 hover:bg-rose-50 transition-colors items-center gap-1.5">
                                <i class="bi bi-trash"></i> Remove Image
                            </button>
                        </div>

                        <div class="space-y-1">
                            <label for="modal_item_name" class="block text-xs font-black text-slate-600 uppercase">Item Name <span class="text-rose-500">*</span></label>
                            <input type="text" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#ff6b4a] focus:border-[#ff6b4a] bg-slate-50/50 font-bold" id="modal_item_name" name="item_name" required>
                        </div>

                        <div class="space-y-1">
                            <label for="modal_price" class="block text-xs font-black text-slate-600 uppercase">Price (PHP ₱) <span class="text-rose-500">*</span></label>
                            <input type="number" step="0.01" min="10" max="10000" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#ff6b4a] focus:border-[#ff6b4a] bg-slate-50/50 font-bold" id="modal_price" name="price" required>
                            <span class="text-xs text-slate-400 block mt-1">Must be between ₱10.00 and ₱10,000.00</span>
                        </div>
                    </div>
                    
                    <div class="px-6 py-4 bg-orange-50/50 border-t border-[#ff6b4a]/20 flex justify-end gap-3">
                        <button type="button" class="px-4 py-2.5 bg-white border border-slate-300 rounded-xl text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors cursor-pointer" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_item" class="px-5 py-2.5 bg-[#ff6b4a] hover:bg-[#fa4b2a] text-white font-bold rounded-xl text-sm shadow-lg shadow-[#ff6b4a]/20 transition-all transform hover:-translate-y-0.5 active:translate-y-0 cursor-pointer">Save Changes</button>
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
                Swal.fire({ icon: 'warning', title: 'Item Name Required', text: 'Please enter an item name.', confirmButtonColor: '#ff6b4a' });
                return false;
            }

            if (isNaN(priceInput) || priceInput < 10 || priceInput > 10000) {
                e.preventDefault();
                Swal.fire({ icon: 'error', title: 'Invalid Price', text: 'Price must be between ₱10.00 and ₱10,000.00.', confirmButtonColor: '#ff6b4a' });
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