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
// Handles the optional item image upload.
// ============================================================
function handle_item_image_upload(&$swal_trigger, &$swal_type, &$swal_title, &$swal_text) {
    if (!isset($_FILES['item_image']) || $_FILES['item_image']['error'] === UPLOAD_ERR_NO_FILE) {
        return null; 
    }
    if ($_FILES['item_image']['error'] !== UPLOAD_ERR_OK) {
        $swal_trigger = true;
        $swal_type = "error";
        $swal_title = "Upload Failed!";
        $swal_text = "There was a problem uploading the image. Please try again.";
        return null;
    }

    $allowed_types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = mime_content_type($_FILES['item_image']['tmp_name']);

    if (!isset($allowed_types[$mime])) {
        $swal_trigger = true;
        $swal_type = "error";
        $swal_title = "Invalid Image Type!";
        $swal_text = "Only JPG, PNG, or WEBP images are allowed.";
        return null;
    }

    $max_size_bytes = 5 * 1024 * 1024; // 5MB
    if ($_FILES['item_image']['size'] > $max_size_bytes) {
        $swal_trigger = true;
        $swal_type = "error";
        $swal_title = "Image Too Large!";
        $swal_text = "Please upload an image smaller than 5MB.";
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

    $swal_trigger = true;
    $swal_type = "error";
    $swal_title = "Upload Failed!";
    $swal_text = "The image could not be saved on the server.";
    return null;
}

$swal_trigger = false;
$swal_type = "";
$swal_title = "";
$swal_text = "";

$categories_result = $conn->query(
    "SELECT DISTINCT categories.*
     FROM categories
     JOIN items ON items.category_id = categories.id
     ORDER BY categories.id ASC"
);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dropdown_category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $new_category_name    = preg_replace('/\s+/', ' ', trim($_POST['new_category_name'])); 
    $item_name            = preg_replace('/\s+/', ' ', trim($_POST['item_name']));
    $price                = (float)$_POST['price'];

    $final_category_id = 0;
    $is_duplicate = false;
    $isValid = true;

    if (empty($item_name)) {
        $swal_trigger = true;
        $swal_type = "error";
        $swal_title = "Missing Details!";
        $swal_text = "The Item Name field is required.";
        $isValid = false;
    } elseif ($price <= 0) {
        $swal_trigger = true;
        $swal_type = "error";
        $swal_title = "Invalid Price!";
        $swal_text = "The price must be greater than 0.";
        $isValid = false;
    } elseif ($price > 10000) {
        $swal_trigger = true;
        $swal_type = "error";
        $swal_title = "Invalid Price!";
        $swal_text = "The price cannot exceed ₱10,000.00.";
        $isValid = false;
    }

    if ($isValid) {
        if (!empty($new_category_name)) {
            $check_stmt = $conn->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?)");
            $check_stmt->bind_param("s", $new_category_name);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $swal_trigger = true;
                $swal_type = "warning";
                $swal_title = "Category Already Exists!";
                $swal_text = "The category '" . htmlspecialchars($new_category_name) . "' already exists. Please select it from the dropdown menu instead.";
                $is_duplicate = true;
            } else {
                $insert_cat_stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
                $insert_cat_stmt->bind_param("s", $new_category_name);
                if ($insert_cat_stmt->execute()) {
                    $final_category_id = $insert_cat_stmt->insert_id;
                }
                $insert_cat_stmt->close();
            }
            $check_stmt->close();
        } else {
            $final_category_id = $dropdown_category_id;
        }

        if (!$is_duplicate && !empty($item_name) && $final_category_id > 0 && $price > 0) {
            $item_check_stmt = $conn->prepare("SELECT id FROM items WHERE LOWER(REPLACE(item_name, ' ', '')) = LOWER(REPLACE(?, ' ', '')) AND category_id = ?");
            $item_check_stmt->bind_param("si", $item_name, $final_category_id);
            $item_check_stmt->execute();
            $item_check_result = $item_check_stmt->get_result();

            if ($item_check_result->num_rows > 0) {
                $swal_trigger = true;
                $swal_type = "warning";
                $swal_title = "Duplicate Item Name!";
                $swal_text = "The item '" . htmlspecialchars($item_name) . "' already exists under this selected category.";
            } else {
                $image_path = handle_item_image_upload($swal_trigger, $swal_type, $swal_title, $swal_text);

                if (!$swal_trigger) {
                    $stmt = $conn->prepare("INSERT INTO items (category_id, item_name, image, price) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("issd", $final_category_id, $item_name, $image_path, $price);

                    if ($stmt->execute()) {
                        header("Location: inventory.php?category_id=" . $final_category_id);
                        exit();
                    } elseif ($conn->errno === 1062) {
                        $swal_trigger = true;
                        $swal_type = "warning";
                        $swal_title = "Duplicate Item Name!";
                        $swal_text = "The item '" . htmlspecialchars($item_name) . "' already exists under this selected category.";
                    } else {
                        $swal_trigger = true;
                        $swal_type = "error";
                        $swal_title = "Database Failure!";
                        $swal_text = "Error adding item: " . $conn->error;
                    }
                    $stmt->close();
                }
            }
            $item_check_stmt->close();
        } elseif (!$is_duplicate && !$swal_trigger) {
            $swal_trigger = true;
            $swal_type = "warning";
            $swal_title = "Incomplete Form!";
            $swal_text = "Please select an existing category OR type a new one, and make sure the item name and price are provided.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple POS - Add Item</title>
    <!-- Combined CSS Utilities -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="../LIBRARIES/tailwind.js"></script>
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        #sidebar { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        /* Smooth Custom Dropdown styling */
        .dropdown-animate { transform-origin: top; animation: scaleIn 0.15s ease-out; }
        @keyframes scaleIn { from { transform: scaleY(0); opacity: 0; } to { transform: scaleY(1); opacity: 1; } }
    </style>
</head>

<body class="bg-slate-50 flex min-h-screen text-slate-800 m-0 p-0 overflow-x-hidden">
 <div class="flex h-screen w-full overflow-hidden">
  <?php include '../../PAGES/sidebar.php'; ?>

    <!-- Main Workspace Container -->
    <div id="main-wrapper" class="flex-grow flex flex-col h-full overflow-y-auto">
        <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-6 sticky top-0 z-10 shadow-sm">
            <div class="flex items-center gap-4">
                <button class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 transition-colors focus:outline-none" type="button" id="burgerToggle">
                    <i class="bi bi-list text-xl"></i>
                </button>
                <h1 class="text-lg font-bold text-slate-800 tracking-tight">Add New Product</h1>
            </div>
            <div class="flex items-center gap-2 text-sm text-slate-500 font-medium">
                <i class="bi bi-calendar3 text-orange-500"></i>
                <span>POS Hub</span>
            </div>
        </header>
        
        <!-- Modernized Orange & White View Layer -->
        <div class="flex-grow p-6 flex flex-col items-center justify-start lg:pt-10">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 w-full max-w-xl transition-all duration-300 hover:shadow-md">
                <div class="flex items-center gap-2.5 mb-6 border-b border-slate-100 pb-4">
                    <div class="w-8 h-8 rounded-lg bg-orange-50 flex items-center justify-center">
                        <i class="bi bi-plus-circle-fill text-orange-500 text-base"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-800">Product Details</h3>
                        <p class="text-xs text-slate-400">Configure new menu item properties</p>
                    </div>
                </div>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data" novalidate class="space-y-5">

                    <!-- Image Upload -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-2 tracking-wide">Item Image (Optional)</label>
                        <div id="imageDropzone" class="border-2 border-dashed border-slate-300 hover:border-orange-400 rounded-xl text-center p-6 cursor-pointer bg-slate-50/50 hover:bg-orange-50/10 transition-all flex flex-col items-center justify-center">
                            <div id="dropzonePrompt" class="space-y-1.5">
                                <div class="w-10 h-10 rounded-full bg-orange-50 text-orange-500 flex items-center justify-center mx-auto">
                                    <i class="bi bi-cloud-arrow-up-fill text-lg"></i>
                                </div>
                                <p class="text-xs font-medium text-slate-700">Drag & drop image here, or <span class="text-orange-600 font-semibold">browse</span></p>
                                <p class="text-[10px] text-slate-400">JPG, PNG, or WEBP up to 5MB</p>
                            </div>
                            <img id="imagePreview" src="" alt="Preview" class="hidden img-fluid rounded-lg shadow-sm max-height-[160px] mx-auto object-cover">
                        </div>
                        <input type="file" id="item_image" name="item_image" accept="image/jpeg,image/png,image/webp" class="hidden">
                        <button type="button" id="removeImageBtn" class="hidden px-2.5 py-1 text-xs font-medium text-red-600 hover:bg-red-50 rounded-lg mt-2 transition-colors flex items-center gap-1">
                            <i class="bi bi-trash"></i> Remove Image
                        </button>
                    </div>

                    <!-- Category inputs split logically -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="new_category_name" class="block text-xs font-semibold text-slate-600 mb-1.5 tracking-wide">Create New Category</label>
                            <input type="text" class="w-full text-sm px-3 py-2 border border-slate-300 rounded-xl focus:outline-none focus:border-orange-500 focus:ring-4 focus:ring-orange-100 placeholder:text-slate-400 transition-all" id="new_category_name" name="new_category_name" placeholder="e.g., Beverages">
                        </div>
                        
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5 tracking-wide">Or Select Existing Menu</label>
                            <div class="relative">
                                <button class="w-full text-sm px-3 py-2 border border-slate-300 rounded-xl bg-white text-left text-slate-700 focus:outline-none focus:border-orange-500 focus:ring-4 focus:ring-orange-100 flex items-center justify-between transition-all" type="button" id="dropdownMenuButton">
                                    <span id="dropdownLabel" class="truncate">-- Choose Category --</span>
                                    <i class="bi bi-chevron-down text-xs text-slate-400"></i>
                                </button>
                                <ul class="absolute left-0 w-full mt-1.5 bg-white border border-slate-200 rounded-xl shadow-xl z-30 hidden dropdown-animate max-h-48 overflow-y-auto" id="dropdownList">
                                    <?php if ($categories_result && $categories_result->num_rows > 0): ?>
                                        <?php while($cat = $categories_result->fetch_assoc()): ?>
                                            <li>
                                                <a class="block px-4 py-2 text-sm text-slate-700 hover:bg-orange-50 hover:text-orange-600 transition-colors select-category" href="#" data-value="<?php echo $cat['id']; ?>">
                                                    <?php echo htmlspecialchars($cat['name']); ?>
                                                </a>
                                            </li>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <li class="px-4 py-2 text-xs text-slate-400">No categories found</li>
                                    <?php endif; ?>
                                </ul>
                                <input type="hidden" name="category_id" id="hidden_category_id" value="0">
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 my-4"></div>

                    <!-- Item Identity Info -->
                    <div>
                        <label for="item_name" class="block text-xs font-semibold text-slate-600 mb-1.5 tracking-wide">Item Name <span class="text-red-500">*</span></label>
                        <input type="text" class="w-full text-sm px-3 py-2 border border-slate-300 rounded-xl focus:outline-none focus:border-orange-500 focus:ring-4 focus:ring-orange-100 placeholder:text-slate-400 transition-all" id="item_name" name="item_name" placeholder="e.g., Iced Caramel Macchiato" required>
                    </div>

                    <div>
                        <label for="price" class="block text-xs font-semibold text-slate-600 mb-1.5 tracking-wide">Price (PHP ₱) <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-2 text-sm font-semibold text-slate-400">₱</span>
                            <input type="number" step="0.01" min="0.01" class="w-full text-sm pl-8 pr-3 py-2 border border-slate-300 rounded-xl focus:outline-none focus:border-orange-500 focus:ring-4 focus:ring-orange-100 placeholder:text-slate-400 transition-all font-medium" id="price" name="price" placeholder="0.00" required>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1">Maximum price allowed is ₱10,000.00</p>
                    </div>

                    <!-- Info Alert Component -->
                    <div class="bg-amber-50/60 rounded-xl p-3 border border-amber-200/60 flex items-start gap-2.5 text-xs text-amber-800 leading-relaxed">
                        <i class="bi bi-info-circle-fill text-amber-500 text-sm mt-0.5"></i>
                        <p>
                            This item won't be sellable at checkout until you map its recipe rules in 
                            <a href="recipe.php" class="font-semibold text-orange-600 hover:underline">Manage Recipes</a>. 
                            Availability is automated via ingredient counts.
                        </p>
                    </div>

                    <!-- Submit Engine Trigger -->
                    <button type="submit" class="w-full py-2.5 bg-orange-600 hover:bg-orange-700 text-white font-semibold text-sm rounded-xl transition-all shadow-md shadow-orange-600/10 focus:outline-none focus:ring-4 focus:ring-orange-200 flex items-center justify-center gap-2">
                        <i class="bi bi-save-fill"></i> Save and View Inventory
                    </button>

                </form>
            </div>
        </div>
    </div>
</div>
    <script>
        // Smooth Sidebar Toggle Control System
        document.getElementById('burgerToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('hidden');
        });

        // Tailored Dropdown Handling Architecture
        const dropBtn = document.getElementById('dropdownMenuButton');
        const dropList = document.getElementById('dropdownList');
        const hiddenInput = document.getElementById('hidden_category_id');
        const labelSpan = document.getElementById('dropdownLabel');

        dropBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dropList.classList.toggle('hidden');
        });

        document.querySelectorAll('.select-category').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                let selectedText = this.textContent.trim();
                let selectedValue = this.getAttribute('data-value');
                
                labelSpan.textContent = selectedText;
                hiddenInput.value = selectedValue;
                dropList.classList.add('hidden');
            });
        });

        document.addEventListener('click', () => dropList.classList.add('hidden'));

        // Drag & Drop Media Handling Component Engine
        (function() {
            const dropzone = document.getElementById('imageDropzone');
            const fileInput = document.getElementById('item_image');
            const prompt = document.getElementById('dropzonePrompt');
            const preview = document.getElementById('imagePreview');
            const removeBtn = document.getElementById('removeImageBtn');

            function showPreview(file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                    prompt.classList.add('hidden');
                    removeBtn.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }

            dropzone.addEventListener('click', () => fileInput.click());
            fileInput.addEventListener('change', () => {
                if (fileInput.files && fileInput.files[0]) showPreview(fileInput.files[0]);
            });

            dropzone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropzone.classList.add('border-orange-500', 'bg-orange-50/20');
            });

            dropzone.addEventListener('dragleave', () => {
                dropzone.classList.remove('border-orange-500', 'bg-orange-50/20');
            });

            dropzone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropzone.classList.remove('border-orange-500', 'bg-orange-50/20');
                if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                    fileInput.files = e.dataTransfer.files;
                    showPreview(e.dataTransfer.files[0]);
                }
            });

            removeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                fileInput.value = '';
                preview.src = '';
                preview.classList.add('hidden');
                prompt.classList.remove('hidden');
                removeBtn.classList.add('hidden');
            });
        })();

        // SweetAlert Execution Module
        <?php if ($swal_trigger): ?>
            Swal.fire({
                icon: '<?php echo $swal_type; ?>',
                title: '<?php echo addslashes($swal_title); ?>',
                text: '<?php echo addslashes($swal_text); ?>',
                confirmButtonColor: '#ea580c'
            });
        <?php endif; ?>
    </script>
</body>
</html>
<?php $conn->close(); ?>