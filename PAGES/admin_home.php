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

// Handle Profile Update Request (Only updates fields that are provided/filled)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_name = trim($_POST['name']);
    $new_gmail = trim($_POST['gmail']);
    $admin_id = isset($_POST['admin_id']) ? $_POST['admin_id'] : 1;
    
    // Kunin ang kasalukuyang data mula sa database para kung blangko ang input, manatili ang dati
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

    // Handle image upload if a new file is provided
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['profile_image']['tmp_name'];
        $file_name = $_FILES['profile_image']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($file_ext, $allowed_extensions)) {
            $new_file_name = "admin_" . uniqid('', true) . "." . $file_ext;
            
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
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
            $_SESSION['gmail'] = $final_gmail; // Update session email if changed
            $_SESSION['swal_success'] = true;  // Flag para sa SweetAlert pagkatapos mag-redirect
        }
        $stmt_up->close();
    }
    
    // PRG Pattern: I-redirect agad para maiwasan ang form resubmission error at infinite sweetalert loop pag nag-refresh
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch current admin/hr details from session and database for display header only
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
    
    <!-- AOS Library CSS & JS -->
    <link href="../LIBRARIES/AOS/aos.css" rel="stylesheet">
    <script src="../LIBRARIES/AOS/AOS.js"></script>
    
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @keyframes floatSlow {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
        }
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 0 15px rgba(255, 107, 74, 0.15); }
            50% { box-shadow: 0 0 25px rgba(255, 107, 74, 0.35); }
        }
        .animate-float-1 { animation: floatSlow 4s ease-in-out infinite; }
        .animate-float-2 { animation: floatSlow 5s ease-in-out infinite 1s; }
        .animate-float-3 { animation: floatSlow 6s ease-in-out infinite 2s; }
        .feature-box-glow:hover {
            animation: pulseGlow 2s infinite;
        }
        /* Custom Hover Glowing Effect for Profile Banner */
        .profile-banner-glow {
            transition: all 0.4s ease-in-out;
        }
        .profile-banner-glow:hover {
            box-shadow: 0 0 35px rgba(255, 107, 74, 0.45), inset 0 0 20px rgba(255, 107, 74, 0.15);
            border-color: rgba(255, 107, 74, 0.7);
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="bg-white text-slate-800 font-sans antialiased h-screen overflow-hidden">
    <div class="flex h-screen w-full overflow-hidden">
        <?php include 'sidebar.php'; ?>
        
        <div class="flex-1 h-screen overflow-y-auto p-8 bg-white min-w-0">
            <!-- Top Header & Notifications -->
            <div class="flex justify-between items-center mb-8" data-aos="fade-down" data-aos-duration="800">
                <div>
                    <h1 class="text-2xl font-extrabold text-[#ff6b4a] tracking-tight">ADMINISTRATOR DASHBOARD</h1>
                    <p class="text-sm text-slate-500 mt-1">Manage Pannakoda Enterprise credentials, security status, and core system modules.</p>
                </div>
                <div class="flex items-center gap-3 bg-orange-50/60 px-4 py-2 rounded-2xl shadow-sm border border-[#ff6b4a]/20 transition-transform duration-300 hover:scale-105">
                    <span class="relative flex h-3 w-3">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                    </span>
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-700">System Active</span>
                </div>
            </div>

            <!-- Modern Profile Grid Layout -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                
                <!-- Left Column (Spans 2 columns): Compact Coral/Dark Banner + 3 Animated Feature Cards Outside -->
                <div class="xl:col-span-2 flex flex-col gap-5">
                    
                    <!-- Compact Profile Banner Card with Mouse-over Glow Effect -->
                    <div data-aos="fade-up" data-aos-duration="900" class="bg-gradient-to-br from-[#1a1010] via-[#1f1212] to-[#09090b] rounded-3xl p-6 text-white shadow-xl border border-[#ff6b4a]/30 relative overflow-hidden flex flex-col justify-between profile-banner-glow cursor-pointer">
                        <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-[#ff6b4a]/10 rounded-full blur-2xl pointer-events-none"></div>
                        
                        <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6 relative z-10 pb-4 border-b border-white/10">
                            
                            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5">
                                <!-- Profile Image Container with Active Dot (Original oval shape preserved, bg-white) -->
                                <div class="relative group">
                                    <div class="bg-white border-4 border-[#ff6b4a]/40 shadow-xl overflow-hidden flex items-center justify-center shrink-0 transition-transform duration-500 group-hover:scale-105 group-hover:rotate-1" style="width: 100px; height: 120px; border-radius: 100%;">
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
                                            echo '<img src="' . htmlspecialchars($display_path) . '" class="w-full h-full object-cover transition-transform duration-700 hover:scale-110">';
                                        } else {
                                            echo '<i class="bi bi-person-circle text-5xl text-slate-400"></i>';
                                        }
                                        ?>
                                    </div>
                                    <!-- Active Indicator Badge -->
                                    <span class="absolute bottom-1.5 right-1.5 block h-3.5 w-3.5 rounded-full bg-emerald-500 ring-4 ring-[#09090b] shadow-md animate-pulse"></span>
                                </div>

                                <!-- User Info -->
                                <div class="space-y-1">
                                   
                                    <h2 class="text-[30px] font-bold tracking-tight text-white mt-0.5">
                                        <?php echo htmlspecialchars($admin_name); ?>
                                    </h2>
                                    <p class="text-slate-300 text-[13px] flex items-center gap-1.5">
                                        <i class="bi bi-envelope-at text-[#ff6b4a]"></i> <?php echo htmlspecialchars($admin_db_gmail); ?>
                                    </p>
                                
                                </div>
                            </div>

                        </div>

                        <!-- Footer notes and Live Philippine Time inside compact banner -->
                        <div class="mt-3 pt-3 border-t border-white/10 flex flex-wrap items-center justify-between gap-4 text-[11px] text-slate-400 relative z-10">
                            <div class="flex items-center gap-1.5">
                                <i class="bi bi-shield-shaded text-[#ff6b4a]"></i> Pannakoda Enterprise Security Protocol v4.2
                            </div>
                            <!-- Live Philippine Time Clock Widget -->
                            <div class="flex items-center gap-1.5 bg-white/5 px-2.5 py-1 rounded-lg border border-white/10">
                                <i class="bi bi-clock text-[#ff6b4a]"></i> 
                                <span class="text-slate-200 font-medium" id="phTimeDisplay">Loading PH Time...</span>
                            </div>
                        </div>
                    </div>

                    <!-- 3 Highly Animated Feature & Contact Cards Placed Outside in a Row -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Box 1 -->
                        <div data-aos="fade-up" data-aos-duration="1000" data-aos-delay="100" class="bg-white-50 p-4 rounded-2xl shadow-sm border border-slate-200/80 flex items-center gap-3.5 animate-float-1 feature-box-glow transition-all duration-300 hover:-translate-y-1 hover:border-[#ff6b4a]/50 hover:bg-white cursor-pointer group">
                            <div class="p-3 bg-[#ff6b4a]/10 text-[#ff6b4a] rounded-xl border border-[#ff6b4a]/20 transition-transform duration-500 group-hover:scale-110 group-hover:rotate-6">
                                <i class="bi bi-headset text-xl"></i>
                            </div>
                            <div>
                                <div class="text-slate-400 font-bold uppercase text-[10px] tracking-wider transition-colors group-hover:text-[#ff6b4a]">Contact Admin</div>
                                <div class="text-slate-800 font-bold text-xs truncate">support@pannakoda.com</div>
                            </div>
                        </div>

                        <!-- Box 2 -->
                        <div data-aos="fade-up" data-aos-duration="1000" data-aos-delay="200" class="bg-white-50 p-4 rounded-2xl shadow-sm border border-slate-200/80 flex items-center gap-3.5 animate-float-2 feature-box-glow transition-all duration-300 hover:-translate-y-1 hover:border-[#ff6b4a]/50 hover:bg-white cursor-pointer group">
                            <div class="p-3 bg-[#ff6b4a]/10 text-[#ff6b4a] rounded-xl border border-[#ff6b4a]/20 transition-transform duration-500 group-hover:scale-110 group-hover:rotate-6">
                                <i class="bi bi-shield-check text-xl"></i>
                            </div>
                            <div>
                                <div class="text-slate-400 font-bold uppercase text-[10px] tracking-wider transition-colors group-hover:text-[#ff6b4a]">Access Level</div>
                                <div class="text-slate-800 font-bold text-xs">Full RBAC Privileges</div>
                            </div>
                        </div>

                        <!-- Box 3 -->
                        <div data-aos="fade-up" data-aos-duration="1000" data-aos-delay="300" class="bg-white-50 p-4 rounded-2xl shadow-sm border border-slate-200/80 flex items-center gap-3.5 animate-float-3 feature-box-glow transition-all duration-300 hover:-translate-y-1 hover:border-[#ff6b4a]/50 hover:bg-white cursor-pointer group">
                            <div class="p-3 bg-[#ff6b4a]/10 text-[#ff6b4a] rounded-xl border border-[#ff6b4a]/20 transition-transform duration-500 group-hover:scale-110 group-hover:rotate-6">
                                <i class="bi bi-cpu text-xl"></i>
                            </div>
                            <div>
                                <div class="text-slate-400 font-bold uppercase text-[10px] tracking-wider transition-colors group-hover:text-[#ff6b4a]">Module Sync</div>
                                <div class="text-slate-800 font-bold text-xs">HRMS / POS Active</div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Right Column: Quick Settings Form Card -->
                <div data-aos="fade-left" data-aos-duration="1000" class="bg-white-50 rounded-3xl p-6 shadow-sm border border-white-200/80 flex flex-col justify-between transition-all duration-500 hover:shadow-lg hover:border-[#ff6b4a]/30">
                    <div>
                        <div class="flex items-center gap-3 mb-5 pb-3 border-b border-white-200">
                            <div class="p-2.5 bg-[#ff6b4a]/10 text-[#ff6b4a] rounded-xl border border-[#ff6b4a]/20 transition-transform duration-300 hover:rotate-12">
                                <i class="bi bi-sliders text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800 text-base">Account Settings</h3>
                                <p class="text-xs text-slate-500">Update your credentials & photo</p>
                            </div>
                        </div>

                        <form id="settingsForm" action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                            <input type="hidden" name="admin_id" value="<?php echo htmlspecialchars($admin_id_val); ?>">
                            
                            <div class="group">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5 transition-colors group-hover:text-[#ff6b4a]">Full Name</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400"><i class="bi bi-person"></i></span>
                                    <input type="text" id="inputName" name="name" value="" placeholder="Enter new full name..."
                                        class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#ff6b4a]/30 focus:border-[#ff6b4a] transition-all duration-300 hover:border-slate-300">
                                </div>
                            </div>

                            <div class="group">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5 transition-colors group-hover:text-[#ff6b4a]">Email Address (Gmail)</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400"><i class="bi bi-envelope"></i></span>
                                    <input type="email" id="inputEmail" name="gmail" value="" placeholder="Enter new email address..."
                                        class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#ff6b4a]/30 focus:border-[#ff6b4a] transition-all duration-300 hover:border-slate-300">
                                </div>
                            </div>

                            <!-- Modern Drop & Select Image Zone -->
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Profile Picture</label>
                                <div class="relative">
                                    <label class="flex flex-col items-center justify-center w-full h-24 border-2 border-slate-300 border-dashed rounded-xl cursor-pointer bg-white hover:bg-orange-50/30 hover:border-[#ff6b4a] transition-all duration-300 group">
                                        <div class="flex flex-col items-center justify-center pt-2 pb-3 px-3 text-center">
                                            <i class="bi bi-cloud-arrow-up text-xl text-slate-400 group-hover:text-[#ff6b4a] group-hover:scale-110 transition-transform duration-300 mb-1"></i>
                                            <p class="text-xs text-slate-600 font-medium" id="file-label">Drop image file or <span class="text-[#ff6b4a] font-semibold underline">browse</span></p>
                                            <p class="text-[10px] text-slate-400 mt-0.5">JPG, PNG, WEBP formats allowed</p>
                                        </div>
                                        <input type="file" name="profile_image" id="profile_image_input" accept=".jpg, .jpeg, .png, .webp" class="hidden" onchange="updateFileName(this)">
                                    </label>
                                </div>
                            </div>

                            <button type="button" id="saveChangesBtn" 
                                class="w-full mt-2 bg-gradient-to-r from-[#ff6b4a] to-[#fa4b2a] hover:from-[#fa4b2a] hover:to-[#e03a1a] text-white font-semibold py-2.5 px-4 rounded-xl shadow-lg shadow-[#ff6b4a]/20 transition-all duration-300 transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2 text-sm">
                                <i class="bi bi-check2-circle"></i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
    
    <!-- Script para sa Live PH Time, Dynamic File Name Preview, Form Validation & SweetAlert Verification/Success -->
    <script>
        // Real-time Philippine Time Clock Function
        function updatePhilippineTime() {
            const options = {
                timeZone: 'Asia/Manila',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            };
            const formatter = new Intl.DateTimeFormat([], options);
            const timeString = formatter.format(new Date());
            const displayElem = document.getElementById('phTimeDisplay');
            if (displayElem) {
                displayElem.textContent = timeString;
            }
        }
        setInterval(updatePhilippineTime, 1000);
        updatePhilippineTime();

        function updateFileName(input) {
            const label = document.getElementById('file-label');
            if (input.files && input.files[0]) {
                label.innerHTML = `<span class="text-emerald-600 font-semibold"><i class="bi bi-file-earmark-check"></i> ${input.files[0].name}</span>`;
            }
        }

        // Trigger Success SweetAlert kung galing sa session flag pagkatapos ng redirect
        <?php if (isset($_SESSION['swal_success']) && $_SESSION['swal_success']): unset($_SESSION['swal_success']); ?>
        document.addEventListener("DOMContentLoaded", function() {
            Swal.fire({
                title: 'Successfully Updated!',
                text: 'Your profile settings have been updated successfully.',
                icon: 'success',
                confirmButtonColor: '#ff6b4a',
                background: '#09090b',
                color: '#ffffff',
                customClass: {
                    popup: 'rounded-2xl border border-[#ff6b4a]/30 shadow-2xl backdrop-blur-xl'
                }
            });
        });
        <?php endif; ?>

        // Form Validation and Confirmation SweetAlert bago mag-submit
        document.getElementById('saveChangesBtn').addEventListener('click', function(e) {
            const nameInput = document.getElementById('inputName').value.trim();
            const emailInput = document.getElementById('inputEmail').value.trim();
            const fileInput = document.getElementById('profile_image_input').files.length;

            // Validation: Kung lahat ay blangko
            if (nameInput === '' && emailInput === '' && fileInput === 0) {
                Swal.fire({
                    title: 'Empty Fields Detected',
                    text: 'Please modify at least one field (Name, Email, or Profile Picture) before saving changes.',
                    icon: 'warning',
                    confirmButtonColor: '#ff6b4a',
                    background: '#09090b',
                    color: '#ffffff',
                    customClass: {
                        popup: 'rounded-2xl border border-[#ff6b4a]/30 shadow-2xl backdrop-blur-xl'
                    }
                });
                return;
            }

            // Verification Dialog bago ituloy ang pag-update
            Swal.fire({
                title: 'Confirm Profile Update',
                text: "Are you sure you want to apply these account changes?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#ff6b4a',
                cancelButtonColor: '#ef4444',
                confirmButtonText: 'Yes, Save Changes',
                cancelButtonText: 'Cancel',
                background: '#09090b',
                color: '#ffffff',
                customClass: {
                    popup: 'rounded-2xl border border-[#ff6b4a]/30 shadow-2xl backdrop-blur-xl'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('settingsForm').submit();
                }
            });
        });

        // Global SweetAlert Logout Interceptor
        document.addEventListener('click', function(e) {
            const logoutBtn = e.target.closest('#sidebarLogoutBtn, .logout-btn, a[href*="logout.php"]');
            
            if (logoutBtn) {
                e.preventDefault();
                e.stopPropagation();
                if (typeof e.stopImmediatePropagation === 'function') {
                    e.stopImmediatePropagation();
                }

                const logoutUrl = logoutBtn.getAttribute('href') || "logout.php";
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'System Sign Out',
                        text: "Are you sure you want to end your current session?",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#ff6b4a', 
                        cancelButtonColor: '#ef4444',
                        confirmButtonText: 'Yes, Sign Out',
                        cancelButtonText: 'Cancel',
                        background: '#09090b',
                        color: '#ffffff',
                        customClass: {
                            popup: 'rounded-2xl border border-[#ff6b4a]/30 shadow-2xl backdrop-blur-xl'
                        }
                    }).then((result) => {
                        if (result.isConfirmed && logoutUrl && logoutUrl !== '#') {
                            window.location.href = logoutUrl; 
                        }
                    });
                } else {
                    if (confirm("Are you sure you want to log out?")) {
                        window.location.href = logoutUrl;
                    }
                }
            }
        }, true);
    </script>
    <script src="../LIBRARIES/bootstrap.bundle.min.js"></script>
    
    <script>
        AOS.init({
            once: true,
            offset: 50,
            duration: 800,
        });
    </script>
</body>
</html>