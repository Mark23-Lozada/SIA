<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: ../../PAGES/login.php");
    exit();
}

require_once __DIR__ . '/../BACKEND/db_inventory.php';

$current_page = basename($_SERVER['PHP_SELF']);

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

    $max_size_bytes = 5 * 1024 * 1024;
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
    } elseif ($price > 1000) {
        $swal_trigger = true;
        $swal_type = "error";
        $swal_title = "Invalid Price!";
        $swal_text = "The price cannot exceed ₱1,000.00 based on items configuration.";
        $isValid = false;
    }

    if ($isValid) {
        if (!empty($new_category_name)) {
            $check_stmt = $conn->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?)");
            $check_stmt->bind_param("s", $new_category_name);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $existing_cat = $check_result->fetch_assoc();
                $final_category_id = (int)$existing_cat['id'];
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
            
            if ($final_category_id > 0) {
                $cat_exist_stmt = $conn->prepare("SELECT id FROM categories WHERE id = ?");
                $cat_exist_stmt->bind_param("i", $final_category_id);
                $cat_exist_stmt->execute();
                $cat_exist_result = $cat_exist_stmt->get_result();
                
                if ($cat_exist_result->num_rows === 0) {
                    $swal_trigger = true;
                    $swal_type = "error";
                    $swal_title = "Category Not Found!";
                    $swal_text = "The selected category does not exist in the database. Please choose a valid category.";
                    $isValid = false;
                }
                $cat_exist_stmt->close();
            } else {
                $swal_trigger = true;
                $swal_type = "warning";
                $swal_title = "Incomplete Form!";
                $swal_text = "Please select an existing category or type a new one.";
                $isValid = false;
            }
        }

        if ($isValid && !$is_duplicate && !empty($item_name) && $final_category_id > 0 && $price > 0) {
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
        } elseif (!$is_duplicate && !$swal_trigger && $isValid) {
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="../LIBRARIES/tailwind.js"></script>
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        #sidebar { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .dropdown-animate { transform-origin: top; animation: scaleIn 0.2s cubic-bezier(0.16, 1, 0.3, 1); }
        @keyframes scaleIn { from { transform: scaleY(0.95); opacity: 0; } to { transform: scaleY(1); opacity: 1; } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    </style>
</head>

<body class="bg-slate-50 flex min-h-screen text-slate-800 m-0 p-0 overflow-x-hidden">
 <div class="flex h-screen w-full overflow-hidden">
  <?php include '../../PAGES/sidebar.php'; ?>

    <div id="main-wrapper" class="flex-grow flex flex-col h-full overflow-y-auto bg-gradient-to-br from-slate-50 via-blue-50/10 to-slate-50">
        <header class="h-16 bg-white/80 backdrop-blur-md border-b border-slate-200 flex items-center justify-between px-6 sticky top-0 z-20 shadow-sm">
            <div class="flex items-center gap-4">
                <button class="p-2 rounded-xl text-slate-500 hover:bg-blue-50 hover:text-amber-500 transition-colors focus:outline-none" type="button" id="burgerToggle">
                    <i class="bi bi-list text-xl"></i>
                </button>
                <h1 class="text-base font-bold text-slate-800 tracking-tight">Add New Product</h1>
            </div>
            <div class="flex items-center gap-2 text-xs text-slate-500 font-medium bg-slate-100/80 px-3 py-1.5 rounded-xl border border-slate-200/60">
                <i class="bi bi-calendar3 text-amber-500"></i>
                <span>POS Hub</span>
            </div>
        </header>
        
        <div class="flex-grow p-6 flex flex-col items-center justify-start lg:pt-10">
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xl p-7 w-full max-w-xl transition-all duration-300 hover:shadow-2xl animate-fade-in">
                <div class="flex items-center gap-3 mb-6 border-b border-slate-100 pb-5">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center text-amber-500 shadow-inner">
                        <i class="bi bi-plus-circle-fill text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Product Details</h3>
                        <p class="text-xs text-slate-400">Configure new menu item properties and pricing</p>
                    </div>
                </div>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data" novalidate class="space-y-5">

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-2 tracking-wide">Item Image (Optional)</label>
                        <div id="imageDropzone" class="border-2 border-dashed border-slate-200 hover:border-amber-500/50 rounded-2xl text-center p-6 cursor-pointer bg-slate-50/50 hover:bg-blue-50/10 transition-all flex flex-col items-center justify-center group">
                            <div id="dropzonePrompt" class="space-y-2">
                                <div class="w-12 h-12 rounded-2xl bg-blue-50 text-amber-500 flex items-center justify-center mx-auto group-hover:scale-110 transition-transform shadow-sm">
                                    <i class="bi bi-cloud-arrow-up-fill text-xl"></i>
                                </div>
                                <p class="text-xs font-medium text-slate-700">Drag & drop image here, or <span class="text-amber-500 font-semibold group-hover:underline">browse</span></p>
                                <p class="text-[10px] text-slate-400">JPG, PNG, or WEBP up to 5MB</p>
                            </div>
                            <img id="imagePreview" src="" alt="Preview" class="hidden img-fluid rounded-xl shadow-md max-h-[160px] mx-auto object-cover">
                        </div>
                        <input type="file" id="item_image" name="item_image" accept="image/jpeg,image/png,image/webp" class="hidden">
                        <button type="button" id="removeImageBtn" class="hidden px-3 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50 rounded-xl mt-2 transition-colors flex items-center gap-1.5">
                            <i class="bi bi-trash"></i> Remove Image
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="new_category_name" class="block text-xs font-semibold text-slate-600 mb-1.5 tracking-wide">Create New Category</label>
                            <input type="text" class="w-full text-sm px-3.5 py-2.5 border border-slate-200 rounded-xl focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/20 placeholder:text-slate-400 transition-all bg-slate-50/30 font-medium" id="new_category_name" name="new_category_name" placeholder="e.g., Beverages">
                        </div>
                        
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5 tracking-wide">Or Select Existing Menu</label>
                            <div class="relative">
                                <button class="w-full text-sm px-3.5 py-2.5 border border-slate-200 rounded-xl bg-slate-50/30 text-left text-slate-700 focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/20 flex items-center justify-between transition-all font-medium" type="button" id="dropdownMenuButton">
                                    <span id="dropdownLabel" class="truncate text-slate-400">-- Choose Category --</span>
                                    <i class="bi bi-chevron-down text-xs text-slate-400"></i>
                                </button>
                                <ul class="absolute left-0 w-full mt-2 bg-white border border-slate-200 rounded-xl shadow-2xl z-30 hidden dropdown-animate max-h-48 overflow-y-auto divide-y divide-slate-100" id="dropdownList">
                                    <?php if ($categories_result && $categories_result->num_rows > 0): ?>
                                        <?php while($cat = $categories_result->fetch_assoc()): ?>
                                            <li>
                                                <a class="block px-4 py-2.5 text-sm text-slate-700 hover:bg-blue-50 hover:text-amber-500 transition-colors select-category font-medium" href="#" data-value="<?php echo $cat['id']; ?>">
                                                    <?php echo htmlspecialchars($cat['name']); ?>
                                                </a>
                                            </li>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <li class="px-4 py-3 text-xs text-slate-400 text-center">No categories found</li>
                                    <?php endif; ?>
                                </ul>
                                <input type="hidden" name="category_id" id="hidden_category_id" value="0">
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 my-4"></div>

                    <div>
                        <label for="item_name" class="block text-xs font-semibold text-slate-600 mb-1.5 tracking-wide">Item Name <span class="text-rose-500">*</span></label>
                        <input type="text" class="w-full text-sm px-3.5 py-2.5 border border-slate-200 rounded-xl focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/20 placeholder:text-slate-400 transition-all font-medium bg-slate-50/30" id="item_name" name="item_name" placeholder="e.g., Iced Caramel Macchiato" required>
                    </div>

                    <div>
                        <label for="price" class="block text-xs font-semibold text-slate-600 mb-1.5 tracking-wide">Price (PHP ₱) <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-2.5 text-sm font-semibold text-slate-400">₱</span>
                            <input type="number" step="0.01" min="0.01" max="1000" class="w-full text-sm pl-8 pr-3.5 py-2.5 border border-slate-200 rounded-xl focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/20 placeholder:text-slate-400 transition-all font-medium bg-slate-50/30" id="price" name="price" placeholder="0.00" required>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1">Maximum price allowed is ₱1,000.00</p>
                    </div>

                    <div class="bg-blue-50/50 rounded-2xl p-4 border border-amber-500/20 flex items-start gap-3 text-xs text-blue-900 leading-relaxed shadow-sm">
                        <i class="bi bi-info-circle-fill text-amber-500 text-base mt-0.5"></i>
                        <p>
                            This item won't be sellable at checkout until you map its recipe rules in 
                            <a href="recipe.php" class="font-semibold text-amber-500 hover:underline">Manage Recipes</a>. 
                            Availability is automated via ingredient counts.
                        </p>
                    </div>

                    <button type="submit" class="w-full py-3 bg-amber-500 hover:bg-amber-500 text-white font-semibold text-sm rounded-xl transition-all shadow-lg shadow-amber-500/20 focus:outline-none focus:ring-4 focus:ring-amber-500/30 flex items-center justify-center gap-2 transform active:scale-[0.99]">
                        <i class="bi bi-save-fill"></i> Save and View Inventory
                    </button>

                </form>
            </div>
        </div>
    </div>
</div>
    <script>
        document.getElementById('burgerToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('hidden');
        });

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
                labelSpan.classList.remove('text-slate-400');
                labelSpan.classList.add('text-slate-700');
                hiddenInput.value = selectedValue;
                dropList.classList.add('hidden');
            });
        });

        document.addEventListener('click', () => dropList.classList.add('hidden'));

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
                dropzone.classList.add('border-amber-500', 'bg-blue-50/20');
            });

            dropzone.addEventListener('dragleave', () => {
                dropzone.classList.remove('border-amber-500', 'bg-blue-50/20');
            });

            dropzone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropzone.classList.remove('border-amber-500', 'bg-blue-50/20');
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

        <?php if ($swal_trigger): ?>
            Swal.fire({
                icon: '<?php echo $swal_type; ?>',
                title: '<?php echo addslashes($swal_title); ?>',
                text: '<?php echo addslashes($swal_text); ?>',
                confirmButtonColor: '#ff6b4a'
            });
        <?php endif; ?>
    </script>
</body>
</html>
<?php $conn->close(); ?>