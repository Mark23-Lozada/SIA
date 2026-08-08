<?php
session_start();

// Ensure user is logged in and has correct role
if (!isset($_SESSION['role']) || (strtolower($_SESSION['role']) !== 'admin' && strtolower($_SESSION['role']) !== 'hr')) {
    header("Location: login.php");
    exit();
}

$host = "localhost";
$username = "root";
$password = "";
$db_name = "pos";

$con = mysqli_connect($host, $username, $password, $db_name);

if (!$con) {
    die("Connection Failed: " . mysqli_connect_error());
}

// Handle Profile Update Request
$update_message = "";
$update_status = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_name = trim($_POST['name']);
    $new_gmail = trim($_POST['gmail']);
    $admin_id = isset($_POST['admin_id']) ? $_POST['admin_id'] : 1;
    
    $image_path_update = "";
    $upload_success = true;

    // Handle image upload if a new file is provided
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['profile_image']['tmp_name'];
        $file_name = $_FILES['profile_image']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($file_ext, $allowed_extensions)) {
            $new_file_name = "admin_" . uniqid('', true) . "." . $file_ext;
            
            // Check target directory structure
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $destination = $upload_dir . $new_file_name;
            if (move_uploaded_file($file_tmp, $destination)) {
                $image_path_update = $destination;
            } else {
                $upload_success = false;
                $update_message = "Failed to move uploaded file.";
                $update_status = "error";
            }
        } else {
            $upload_success = false;
            $update_message = "Invalid file extension. Allowed: JPG, JPEG, PNG, WEBP.";
            $update_status = "error";
        }
    }

    if ($upload_success) {
        if (!empty($image_path_update)) {
            $update_query = "UPDATE admin SET name = ?, gmail = ?, image_path = ? WHERE id = ?";
            $stmt_up = $con->prepare($update_query);
            $stmt_up->bind_param("sssi", $new_name, $new_gmail, $image_path_update, $admin_id);
        } else {
            $update_query = "UPDATE admin SET name = ?, gmail = ? WHERE id = ?";
            $stmt_up = $con->prepare($update_query);
            $stmt_up->bind_param("ssi", $new_name, $new_gmail, $admin_id);
        }

        if ($stmt_up->execute()) {
            $_SESSION['gmail'] = $new_gmail; // Update session email
            $update_message = "Profile updated successfully!";
            $update_status = "success";
        } else {
            $update_message = "Error updating profile: " . $stmt_up->error;
            $update_status = "error";
        }
        $stmt_up->close();
    }
}

// Fetch current admin/hr details from session and database
$admin_email = "";
if (isset($_SESSION['gmail'])) {
    $admin_email = $_SESSION['gmail'];
} elseif (isset($_SESSION['email'])) {
    $admin_email = $_SESSION['email'];
} elseif (isset($_SESSION['user_email'])) {
    $admin_email = $_SESSION['user_email'];
}

$admin_data = [];

if (empty($admin_email)) {
    $fallback_query = "SELECT * FROM admin LIMIT 1";
    $fallback_result = mysqli_query($con, $fallback_query);
    if ($fallback_result && mysqli_num_rows($fallback_result) > 0) {
        $admin_data = mysqli_fetch_assoc($fallback_result);
        $admin_email = $admin_data['gmail'];
    }
} else {
    $query = "SELECT * FROM admin WHERE gmail = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("s", $admin_email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $admin_data = $result->fetch_assoc();
    }
    $stmt->close();
}

$admin_name = isset($admin_data['name']) ? $admin_data['name'] : 'Admin User';
$admin_db_gmail = isset($admin_data['gmail']) ? $admin_data['gmail'] : $admin_email;
$admin_id_val = isset($admin_data['id']) ? $admin_data['id'] : 1;
$admin_role = isset($_SESSION['role']) ? ucfirst($_SESSION['role']) : 'Admin';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Home - Dashboard</title>
    <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
    <script src="../LIBRARIES/tailwind.js"></script> 
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-slate-50 font-sans antialiased h-screen overflow-hidden">
    <div class="flex h-screen w-full overflow-hidden">
        <?php include 'sidebar.php'; ?>
        
        <div class="flex-1 h-screen overflow-y-auto p-8 bg-slate-100/60 min-w-0">
            <!-- Top Header & Notifications -->
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-2xl font-extrabold text-purple-900 tracking-tight">ADMINISTRATOR</h1>
                    <p class="text-sm text-slate-500 mt-1">Manage Pannakoda Enterprise credentials, security status, and system modules.</p>
                </div>
                <div class="flex items-center gap-3 bg-white px-4 py-2 rounded-2xl shadow-sm border border-slate-200/60">
                    <span class="relative flex h-3 w-3">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                    </span>
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-700">System Active</span>
                </div>
            </div>

            <?php if (!empty($update_message)): ?>
                <div class="mb-6 p-4 rounded-2xl text-sm font-medium flex items-center gap-3 shadow-sm border <?php echo $update_status === 'success' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200'; ?>">
                    <i class="bi <?php echo $update_status === 'success' ? 'bi-check-circle-fill text-emerald-500' : 'bi-exclamation-triangle-fill text-rose-500'; ?> text-lg"></i>
                    <span><?php echo htmlspecialchars($update_message); ?></span>
                </div>
            <?php endif; ?>

            <!-- Modern Profile Grid Layout -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                
                <!-- Left Column (Spans 2 columns): Compact Purple Banner + 3 Feature Cards Outside -->
                <div class="xl:col-span-2 flex flex-col gap-5">
                    
                    <!-- Compact Purple Profile Banner Card -->
                    <div class="bg-gradient-to-br from-[#7e22ce] via-purple-600 to-indigo-700 rounded-3xl p-6 text-white shadow-xl relative overflow-hidden flex flex-col justify-between">
                        <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
                        
                        <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6 relative z-10 pb-4 border-b border-white/20">
                            
                            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5">
                                <!-- Profile Image Container with Facebook-Style Active Dot -->
                                <div class="relative group">
                                    <div class="rounded-2xl bg-white border-4 border-white/80 shadow-xl overflow-hidden flex items-center justify-center shrink-0 transition-transform duration-300 group-hover:scale-[1.02]" style="width: 100px; height: 120px; border-radius: 100%;">
                                        <?php 
                                        if (!empty($admin_data['image_path'])) {
                                            $img_path = $admin_data['image_path'];
                                            $display_path = '';
                                            if (file_exists($img_path)) {
                                                $display_path = $img_path;
                                            } elseif (file_exists('../' . $img_path)) {
                                                $display_path = '../' . $img_path;
                                            } else {
                                                $display_path = $img_path;
                                            }
                                            echo '<img src="' . htmlspecialchars($display_path) . '" class="w-full h-full object-cover">';
                                        } else {
                                            echo '<i class="bi bi-person-circle text-5xl text-slate-300"></i>';
                                        }
                                        ?>
                                    </div>
                                    <!-- Facebook-style Active Indicator Badge -->
                                    <span class="absolute bottom-1.5 right-1.5 block h-3.5 w-3.5 rounded-full bg-emerald-500 ring-4 ring-white shadow-md"></span>
                                </div>

                                <!-- User Info -->
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block bg-white/25 backdrop-blur-md text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider text-purple-100">
                                            <?php echo htmlspecialchars($admin_role); ?> Profile
                                        </span>
                                        <span class="inline-block bg-emerald-500/30 backdrop-blur-md text-[9px] font-bold px-2.5 py-0.5 rounded-full text-emerald-100 border border-emerald-400/30">
                                            Pannakoda Verified
                                        </span>
                                    </div>
                                    <h2 class="text-xl font-bold tracking-tight text-white mt-0.5">
                                        <?php echo htmlspecialchars($admin_name); ?>
                                    </h2>
                                    <p class="text-purple-100/90 text-xs flex items-center gap-1.5">
                                        <i class="bi bi-envelope-at"></i> <?php echo htmlspecialchars($admin_db_gmail); ?>
                                    </p>
                                    <p class="text-purple-200/80 text-[11px] flex items-center gap-1.5">
                                        <i class="bi bi-shield-lock-fill"></i> Primary System Administrator
                                    </p>
                                </div>
                            </div>

                        </div>

                        <!-- Footer notes inside compact banner -->
                        <div class="mt-3 pt-3 border-t border-white/20 flex flex-wrap items-center justify-between gap-4 text-[11px] text-purple-100/80 relative z-10">
                            <div class="flex items-center gap-1.5">
                                <i class="bi bi-shield-shaded"></i> Pannakoda Enterprise Security Protocol v4.2
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i class="bi bi-activity"></i> Real-time Telemetry: Online
                            </div>
                        </div>
                    </div>

                    <!-- 3 Feature & Contact Cards Placed Outside in a Row -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-white p-4 rounded-2xl shadow-md border border-slate-200/70 flex items-center gap-3.5">
                            <div class="p-3 bg-purple-50 text-[#7e22ce] rounded-xl">
                                <i class="bi bi-headset text-xl"></i>
                            </div>
                            <div>
                                <div class="text-slate-400 font-bold uppercase text-[10px]">Contact Admin</div>
                                <div class="text-slate-800 font-bold text-xs truncate">support@pannakoda.com</div>
                            </div>
                        </div>

                        <div class="bg-white p-4 rounded-2xl shadow-md border border-slate-200/70 flex items-center gap-3.5">
                            <div class="p-3 bg-purple-50 text-[#7e22ce] rounded-xl">
                                <i class="bi bi-shield-check text-xl"></i>
                            </div>
                            <div>
                                <div class="text-slate-400 font-bold uppercase text-[10px]">Access Level</div>
                                <div class="text-slate-800 font-bold text-xs">Full RBAC Privileges</div>
                            </div>
                        </div>

                        <div class="bg-white p-4 rounded-2xl shadow-md border border-slate-200/70 flex items-center gap-3.5">
                            <div class="p-3 bg-purple-50 text-[#7e22ce] rounded-xl">
                                <i class="bi bi-cpu text-xl"></i>
                            </div>
                            <div>
                                <div class="text-slate-400 font-bold uppercase text-[10px]">Module Sync</div>
                                <div class="text-slate-800 font-bold text-xs">HRMS / POS Active</div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Right Column: Quick Settings Form Card -->
                <div class="bg-white rounded-3xl p-6 shadow-xl border border-slate-200/70 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-3 mb-5 pb-3 border-b border-slate-100">
                            <div class="p-2.5 bg-purple-50 text-[#7e22ce] rounded-xl">
                                <i class="bi bi-sliders text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800 text-base">Account Settings</h3>
                                <p class="text-xs text-slate-400">Update your credentials & photo</p>
                            </div>
                        </div>

                        <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                            <input type="hidden" name="admin_id" value="<?php echo htmlspecialchars($admin_id_val); ?>">
                            
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Full Name</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400"><i class="bi bi-person"></i></span>
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($admin_name); ?>" required
                                        class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#7e22ce]/20 focus:border-[#7e22ce] transition">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Email Address (Gmail)</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400"><i class="bi bi-envelope"></i></span>
                                    <input type="email" name="gmail" value="<?php echo htmlspecialchars($admin_db_gmail); ?>" required
                                        class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#7e22ce]/20 focus:border-[#7e22ce] transition">
                                </div>
                            </div>

                            <!-- Modern Drop & Select Image Zone -->
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Profile Picture</label>
                                <div class="relative">
                                    <label class="flex flex-col items-center justify-center w-full h-24 border-2 border-slate-200 border-dashed rounded-xl cursor-pointer bg-slate-50 hover:bg-purple-50/40 hover:border-[#7e22ce] transition group">
                                        <div class="flex flex-col items-center justify-center pt-2 pb-3 px-3 text-center">
                                            <i class="bi bi-cloud-arrow-up text-xl text-slate-400 group-hover:text-[#7e22ce] transition mb-1"></i>
                                            <p class="text-xs text-slate-600 font-medium" id="file-label">I-drop ang image o <span class="text-[#7e22ce] font-semibold underline">i-browse</span></p>
                                            <p class="text-[10px] text-slate-400 mt-0.5">JPG, PNG, WEBP</p>
                                        </div>
                                        <input type="file" name="profile_image" id="profile_image_input" accept=".jpg, .jpeg, .png, .webp" class="hidden" onchange="updateFileName(this)">
                                    </label>
                                </div>
                            </div>

                            <button type="submit" name="update_profile" 
                                class="w-full mt-2 bg-gradient-to-r from-[#7e22ce] to-purple-600 hover:from-purple-800 hover:to-purple-700 text-white font-semibold py-2.5 px-4 rounded-xl shadow-lg shadow-[#7e22ce]/20 transition-all duration-200 flex items-center justify-center gap-2 text-sm">
                                <i class="bi bi-check2-circle"></i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
    
    <!-- Script para sa Dynamic File Name Preview -->
    <script>
        function updateFileName(input) {
            const label = document.getElementById('file-label');
            if (input.files && input.files[0]) {
                label.innerHTML = `<span class="text-emerald-600 font-semibold"><i class="bi bi-file-earmark-check"></i> ${input.files[0].name}</span>`;
            }
        }
    </script>
    <script src="../LIBRARIES/bootstrap.bundle.min.js"></script>
</body>
</html>