<?php
session_start();

// Ensure user is logged in and has correct role[cite: 2]
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

// Handle Profile Update Request[cite: 2]
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_name = trim($_POST['name']);
    $new_gmail = trim($_POST['gmail']);
    $admin_id = isset($_POST['admin_id']) ? $_POST['admin_id'] : 1;
    
    $current_fetch_q = "SELECT name, gmail, image_path FROM admin WHERE id = ?";
    $stmt_curr = $con->prepare($current_fetch_q);
    $stmt_curr->bind_param("i", $admin_id);
    $stmt_curr->execute();
    $curr_res = $stmt_curr->get_result()->fetch_assoc();
    $stmt_curr->close();

    $final_name = !empty($new_name) ? $new_name : $curr_res['name'];
    $final_gmail = !empty($new_gmail) ? $new_gmail : $curr_res['gmail'];
    
    $image_path_update = $curr_res['image_path'];
    $upload_success = true;

    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['profile_image']['tmp_name'];
        $file_name = $_FILES['profile_image']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($file_ext, $allowed_extensions)) {
            $new_file_name = "admin_" . uniqid('', true) . "." . $file_ext;
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
            
            $destination = $upload_dir . $new_file_name;
            if (move_uploaded_file($file_tmp, $destination)) {
                $image_path_update = $destination;
            } else {
                $upload_success = false;
            }
        } else {
            $upload_success = false;
        }
    }

    if ($upload_success) {
        $update_query = "UPDATE admin SET name = ?, gmail = ?, image_path = ? WHERE id = ?";
        $stmt_up = $con->prepare($update_query);
        $stmt_up->bind_param("sssi", $final_name, $final_gmail, $image_path_update, $admin_id);
        if ($stmt_up->execute()) {
            $_SESSION['gmail'] = $final_gmail;
            $_SESSION['swal_success'] = true;
        }
        $stmt_up->close();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$admin_email = $_SESSION['gmail'] ?? $_SESSION['email'] ?? $_SESSION['user_email'] ?? "";
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
    if ($result->num_rows > 0) { $admin_data = $result->fetch_assoc(); }
    $stmt->close();
}

$admin_name = $admin_data['name'] ?? 'Admin User';
$admin_db_gmail = $admin_data['gmail'] ?? $admin_email;
$admin_id_val = $admin_data['id'] ?? 1;
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
    <link href="../LIBRARIES/AOS/aos.css" rel="stylesheet">
    <script src="../LIBRARIES/AOS/AOS.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --sidebar-color: #FFFBEB; }
        .text-custom-sidebar { color: #b45309; }
        
        @keyframes floatSlow { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-4px); } }
        .animate-float-1 { animation: floatSlow 4s ease-in-out infinite; }
        .animate-float-2 { animation: floatSlow 5s ease-in-out infinite 1s; }
        .animate-float-3 { animation: floatSlow 6s ease-in-out infinite 2s; }
        
        .profile-card-modern {
            background: #eca611;
         

            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .profile-card-modern::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 100%;
            background: radial-gradient(circle at top left, rgba(255, 255, 255, 0.15), transparent 60%);
            pointer-events: none;
        }
        .profile-card-modern:hover {
        
            transform: translateY(-2px);
        }
        .social-badge {
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .social-badge:hover {
            transform: translateY(-3px) scale(1.05);
        
        }
    </style>
</head>
<body class="bg-amber-50/30 text-slate-800 font-sans antialiased h-screen overflow-hidden">
    <div class="flex h-screen w-full overflow-hidden">
        <?php include 'sidebar.php'; ?>
        
        <div class="flex-1 h-screen overflow-y-auto p-8 min-w-0 bg-white">
            <!-- Top Header -->
            <div class="flex justify-between items-center mb-8" data-aos="fade-down" data-aos-duration="800">
                <div>
                    <h1 class="text-2xl font-extrabold text-amber-500 tracking-tight">ADMINISTRATOR DASHBOARD</h1>
                    <p class="text-sm text-slate-500 mt-1">Manage Pannakoda Enterprise credentials, security status, and core system modules.</p>
                </div>
                <div class="flex items-center gap-3 bg-white px-4 py-2 rounded-2xl shadow-sm border border-amber-200/60">
                    <span class="relative flex h-3 w-3">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
                    </span>
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-700">System Active</span>
                </div>
            </div>

            <!-- Modern Profile Grid Layout -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                
                <!-- Left Column -->
                <div class="xl:col-span-2 flex flex-col gap-6">
                    
                    <!-- Redesigned Aesthetic Profile Card -->
                    <div data-aos="fade-up" data-aos-duration="900" class="profile-card-modern rounded-3xl p-8 text-white relative">
                        <!-- Decorative background glow -->
                        <div class="absolute -right-16 -top-16 w-56 h-56 bg-white/10 rounded-full blur-3xl pointer-events-none"></div>

                        <div class="relative z-10 flex flex-col md:flex-row items-center md:items-start gap-8">
                            
                            <!-- Avatar Column -->
                            <div class="relative group shrink-0">
                                <div class="w-32 h-40 shadow-2xl overflow-hidden bg-white flex items-center justify-center transition-transform duration-300 group-hover:scale-105 border-2 border-white/50" style="border-radius: 70%;">
                                    <?php 
                                    if (!empty($admin_data['image_path'])) {
                                        $img_path = $admin_data['image_path'];
                                        $display_path = file_exists($img_path) ? $img_path : (file_exists('../' . $img_path) ? '../' . $img_path : $img_path);
                                        echo '<img src="' . htmlspecialchars($display_path) . '" class="w-full h-full object-cover">';
                                    } else {
                                        echo '<i class="bi bi-person-circle text-6xl text-slate-300"></i>';
                                    }
                                    ?>
                                </div>
                                <span class="absolute -bottom-1 -right-1 block h-4 w-4 rounded-full bg-emerald-500 ring-4 ring-amber-700 shadow-md"></span>
                            </div>

                            <!-- Info & Socials Column -->
                            <div class="flex-1 text-left space-y-3">
                                <div>
                                    <div class="inline-block px-3 py-1 rounded-full bg-white/20 text-white text-[11px] font-bold tracking-wider uppercase mb-2 border border-white/30">
                                        <i class="bi bi-shield-check"></i> Super Administrator
                                    </div>
                                    <h2 class="text-3xl font-extrabold tracking-tight text-white">
                                        <?php echo htmlspecialchars($admin_name); ?>
                                    </h2>
                                    <p class="text-amber-100 text-sm flex items-center justify-start gap-2 mt-1">
                                        <i class="bi bi-envelope-at text-white"></i> <?php echo htmlspecialchars($admin_db_gmail); ?>
                                    </p>
                                </div>

                                <!-- Aesthetic Social Links -->
                                <div class="pt-1 flex flex-wrap items-center justify-start gap-3">
                                    <a href="https://facebook.com" target="_blank" title="Facebook" class="social-badge px-3.5 py-1.5 rounded-xl bg-blue-600 border border-white/20 flex items-center gap-2 text-xs font-medium text-white shadow-sm">
                                        <i class="bi bi-facebook text-white"></i> Facebook
                                    </a>
                                    <a href="https://instagram.com" target="_blank" title="Instagram" class="social-badge px-3.5 py-1.5 rounded-xl bg-gradient-to-tr from-[#f58529] via-[#dd2a7b] to-[#8134af] border border-white/20 flex items-center gap-2 text-xs font-medium text-white shadow-sm">
                                        <i class="bi bi-instagram text-white"></i> Instagram
                                    </a>
                                   <a href="https://messenger.com" target="_blank" title="Messenger" class="social-badge px-3.5 py-1.5 rounded-xl border border-white/20 flex items-center gap-2 text-xs font-medium text-white shadow-sm" style="background-color: #0099FF;">
    <i class="bi bi-messenger text-white"></i> Messenger
</a>
                                </div>
                            </div>
                        </div>

                        <!-- Stats Divider -->
                        <div class="grid grid-cols-3 gap-4 my-6 py-4 border-y border-white/20 text-center relative z-10">
                            <div>
                                <div class="text-[15px] uppercase font-black tracking-wider mt-0.5">Active Tasks</div>
                                <div class="text-2xl font-black text-white">14</div>
                                
                            </div>
                            <div class="border-x border-white/20">
                                <div class="text-[15px] uppercase font-black tracking-wider mt-0.5">System Perf</div>
                                <div class="text-2xl font-black text-white">99.4%</div>
                                
                            </div>
                            <div>
                                <div class="text-[15px] uppercase font-black tracking-wider mt-0.5">Daily Logs</div>
                                <div class="text-2xl font-black text-white">52</div>
                                
                            </div>
                        </div>

                        <!-- Card Footer -->
                        <div class="flex flex-wrap items-center justify-between gap-4 text-xs text-amber-100 relative z-10">
                            <div class="flex items-center gap-2">
                                <i class="bi bi-shield-shaded text-white"></i> Pannakoda Enterprise Security Protocol v4.2
                            </div>
                            <div class="flex items-center gap-2 bg-black/20 px-3 py-1.5 rounded-xl border border-white/20">
                                <i class="bi bi-clock text-white"></i> 
                                <span class="text-white font-medium" id="phTimeDisplay">Loading PH Time...</span>
                            </div>
                        </div>
                    </div>

                    <!-- 3 Feature Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div data-aos="fade-up" data-aos-duration="1000" data-aos-delay="100" class="bg-white p-4 rounded-2xl shadow-sm border border-amber-100 flex items-center gap-3.5 animate-float-1 transition-all duration-300 hover:border-amber-400">
                            <div class="p-3 bg-amber-50 text-amber-600 rounded-xl border border-amber-200"><i class="bi bi-headset text-xl"></i></div>
                            <div>
                                <div class="text-amber-500 font-bold uppercase text-[10px]">Contact Admin</div>
                                <div class="text-slate-800 font-bold text-xs truncate">support@pannakoda.com</div>
                            </div>
                        </div>
                        <div data-aos="fade-up" data-aos-duration="1000" data-aos-delay="200" class="bg-white p-4 rounded-2xl shadow-sm border border-amber-100 flex items-center gap-3.5 animate-float-2 transition-all duration-300 hover:border-amber-400">
                            <div class="p-3 bg-amber-50 text-amber-600 rounded-xl border border-amber-200"><i class="bi bi-shield-check text-xl"></i></div>
                            <div>
                                <div class="text-amber-500 font-bold uppercase text-[10px]">Access Level</div>
                                <div class="text-slate-800 font-bold text-xs">Full RBAC Privileges</div>
                            </div>
                        </div>
                        <div data-aos="fade-up" data-aos-duration="1000" data-aos-delay="300" class="bg-white p-4 rounded-2xl shadow-sm border border-amber-100 flex items-center gap-3.5 animate-float-3 transition-all duration-300 hover:border-amber-400">
                            <div class="p-3 bg-amber-50 text-amber-600 rounded-xl border border-amber-200"><i class="bi bi-cpu text-xl"></i></div>
                            <div>
                                <div class="text-amber-500 font-bold uppercase text-[10px]">Module Sync</div>
                                <div class="text-slate-800 font-bold text-xs">HRMS / POS Active</div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Right Column: Settings Form -->
                <div data-aos="fade-left" data-aos-duration="1000" class="bg-white rounded-3xl p-6 shadow-sm border border-amber-100 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-3 mb-5 pb-3 border-b border-amber-50">
                            <div class="p-2.5 bg-amber-50 text-amber-600 rounded-xl border border-amber-200">
                                <i class="bi bi-sliders text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-amber-500 text-base">Account Settings</h3>
                                <p class="text-xs text-slate-500">Update your credentials & photo</p>
                            </div>
                        </div>

                        <form id="settingsForm" action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                            <input type="hidden" name="admin_id" value="<?php echo htmlspecialchars($admin_id_val); ?>">
                            
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-amber-500 mb-1.5">Full Name</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-amber-500"><i class="bi bi-person"></i></span>
                                    <input type="text" id="inputName" name="name" placeholder="Enter new full name..."
                                        class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-amber-500 mb-1.5">Email Address (Gmail)</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-amber-500"><i class="bi bi-envelope"></i></span>
                                    <input type="email" id="inputEmail" name="gmail" placeholder="Enter new email address..."
                                        class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-amber-500 mb-1.5">Profile Picture</label>
                                <label class="flex flex-col items-center justify-center w-full h-24 border-2 border-slate-300 border-dashed rounded-xl cursor-pointer bg-white hover:bg-amber-50/40 hover:border-amber-500 transition-all">
                                    <div class="flex flex-col items-center justify-center pt-2 pb-3 px-3 text-center">
                                        <i class="bi bi-cloud-arrow-up text-xl text-amber-500 mb-1"></i>
                                        <p class="text-xs text-slate-600 font-medium" id="file-label">Drop image file or <span class="text-amber-600 font-semibold underline">browse</span></p>
                                        <p class="text-[10px] text-amber-500 mt-0.5">JPG, PNG, WEBP formats allowed</p>
                                    </div>
                                    <input type="file" name="profile_image" id="profile_image_input" accept=".jpg, .jpeg, .png, .webp" class="hidden" onchange="updateFileName(this)">
                                </label>
                            </div>

                            <button type="button" id="saveChangesBtn" 
                                class="w-full mt-2 bg-gradient-to-r from-amber-600 to-yellow-600 hover:from-amber-700 hover:to-yellow-700 text-white font-bold py-2.5 px-4 rounded-xl shadow-md transition-all flex items-center justify-center gap-2 text-sm cursor-pointer border border-amber-500/30">
                                <i class="bi bi-check2-circle"></i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
    
    <script>
        function updatePhilippineTime() {
            const options = { timeZone: 'Asia/Manila', year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
            const formatter = new Intl.DateTimeFormat([], options);
            const displayElem = document.getElementById('phTimeDisplay');
            if (displayElem) { displayElem.textContent = formatter.format(new Date()); }
        }
        setInterval(updatePhilippineTime, 1000);
        updatePhilippineTime();

        function updateFileName(input) {
            const label = document.getElementById('file-label');
            if (input.files && input.files[0]) {
                label.innerHTML = `<span class="text-amber-600 font-semibold"><i class="bi bi-file-earmark-check"></i> ${input.files[0].name}</span>`;
            }
        }

        <?php if (isset($_SESSION['swal_success']) && $_SESSION['swal_success']): unset($_SESSION['swal_success']); ?>
        document.addEventListener("DOMContentLoaded", function() {
            Swal.fire({
                title: 'Successfully Updated!',
                text: 'Your profile settings have been updated successfully.',
                icon: 'success',
                confirmButtonColor: '#d97706',
                background: '#ffffff',
                color: '#1e293b',
                customClass: { popup: 'rounded-2xl border border-amber-200 shadow-2xl backdrop-blur-xl' }
            });
        });
        <?php endif; ?>

        document.getElementById('saveChangesBtn').addEventListener('click', function(e) {
            const nameInput = document.getElementById('inputName').value.trim();
            const emailInput = document.getElementById('inputEmail').value.trim();
            const fileInput = document.getElementById('profile_image_input').files.length;

            if (nameInput === '' && emailInput === '' && fileInput === 0) {
                Swal.fire({
                    title: 'Empty Fields Detected',
                    text: 'Please modify at least one field before saving changes.',
                    icon: 'warning',
                    confirmButtonColor: '#d97706',
                    background: '#ffffff',
                    color: '#1e293b',
                    customClass: { popup: 'rounded-2xl border border-amber-200 shadow-2xl backdrop-blur-xl' }
                });
                return;
            }

            Swal.fire({
                title: 'Confirm Profile Update',
                text: "Are you sure you want to apply these account changes?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#d97706',
                cancelButtonColor: '#ef4444',
                confirmButtonText: 'Yes, Save Changes',
                cancelButtonText: 'Cancel',
                background: '#ffffff',
                color: '#1e293b',
                customClass: { popup: 'rounded-2xl border border-amber-200 shadow-2xl backdrop-blur-xl' }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('settingsForm').submit();
                }
            });
        });
    </script>
    <script src="../LIBRARIES/bootstrap.bundle.min.js"></script>
    <script>AOS.init({ once: true, offset: 50, duration: 800 });</script>
</body>
</html>