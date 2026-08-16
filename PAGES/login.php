<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start(); 

$host = "localhost";
$username = "root";
$password_db = ""; 
$db_name = "pos";

$db = mysqli_connect($host, $username, $password_db, $db_name);

if (!$db) {
    die("Connection Failed: " . mysqli_connect_error());
}

// AJAX Handler para sa pag-save sa applicants table
if (isset($_GET['action']) && $_GET['action'] === 'apply') {
    header('Content-Type: application/json');
    
    $full_name = $_POST['full_name'] ?? '';
    $department = $_POST['department'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $gsis_id = $_POST['gsis_id'] ?? '';
    $sss_id = $_POST['sss_id'] ?? '';
    $philhealth_id = $_POST['philhealth_id'] ?? '';
    $pagibig_id = $_POST['pagibig_id'] ?? '';

    $stmt = $db->prepare("INSERT INTO applicants (full_name, department, email, phone, address, gsis_id, sss_id, philhealth_id, pagibig_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
    
    if ($stmt) {
        $stmt->bind_param("sssssssss", $full_name, $department, $email, $phone, $address, $gsis_id, $sss_id, $philhealth_id, $pagibig_id);
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Application successfully submitted for review!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to save application: " . $stmt->error]);
        }
        $stmt->close();
    
    } else {
        echo json_encode(["status" => "error", "message" => "Database table error: " . $db->error]);
    }
    $db->close();
    exit();
}

$message = "";
$messageClass = "";

$check_query = "SELECT COUNT(*) as total FROM admin";
$check_result = $db->query($check_query);
$row_count = $check_result->fetch_assoc();
$admin_exists = $row_count['total'] > 0;

$hr_check_query = "SELECT COUNT(*) as hr_total FROM employees WHERE LOWER(department) = 'hr'";
$hr_check_result = $db->query($hr_check_query);
$hr_row = $hr_check_result->fetch_assoc();
$has_hr = $hr_row['hr_total'] > 0;

if(isset($_POST['login'])){
    $gmail = trim($_POST['gmail']); 
    $password = trim($_POST['password']); 

    $admin_query = "SELECT id, gmail, password FROM admin WHERE gmail = ? LIMIT 1";
    $stmt = $db->prepare($admin_query);
    $stmt->bind_param("s", $gmail);
    $stmt->execute();
    $admin_result = $stmt->get_result();

    if($admin_row = $admin_result->fetch_assoc()){
        if (password_verify($password, $admin_row['password'])) {
            session_unset();
            $_SESSION['admin_id'] = $admin_row['id']; 
            $_SESSION['role'] = 'admin';
            header("Location: admin_home.php");
            exit();
        } else {
            $message = "Incorrect password for Admin.";
            $messageClass = "bg-rose-500/20 border-rose-500/30 text-rose-200 animate-shake";
        }
    } 
    else {
        $emp_query = "SELECT id, full_name, email, employee_gmail, company_gmail, employee_password, department, position FROM employees WHERE email = ? OR employee_gmail = ? OR company_gmail = ? LIMIT 1";
        $stmt2 = $db->prepare($emp_query);
        $stmt2->bind_param("sss", $gmail, $gmail, $gmail);
        $stmt2->execute();
        $emp_result = $stmt2->get_result();

        if($emp_row = $emp_result->fetch_assoc()){
            if (password_verify($password, $emp_row['employee_password'])) {
                session_unset();
                $_SESSION['user_id'] = $emp_row['id']; 
                $_SESSION['fullname'] = $emp_row['full_name'];
                $_SESSION['employee_gmail'] = $emp_row['employee_gmail'] ?? $emp_row['company_gmail'];
                
                $department = strtolower(trim($emp_row['department']));
                $_SESSION['role'] = $department; 

                $is_employee_gmail_login = ($gmail === $emp_row['email'] || $gmail === $emp_row['employee_gmail']);

                if ($is_employee_gmail_login) {
                    header("Location: info.php"); 
                    exit();
                } else {
                    if ($department === 'admin') {
                        header("Location: ../project-test1/FORNTEND/sales_day.php"); 
                        exit();
                    } elseif ($department === 'hr') {
                        header("Location: dashboard.php"); 
                        exit();
                    } elseif ($department === 'finance') {
                        header("Location: ../project-test1/FRONTEND/history.php"); 
                        exit();
                    } elseif ($department === 'manager') {
                        header("Location: ../project-test1/FRONTEND/add_item.php"); 
                    } elseif ($department === 'cashier'){
                        header("Location: ../project-test1/FRONTEND/checkout.php"); 
                    
                        exit();
                    } else {
                        header("Location: ../project-test1/FRONTEND/pos_dash.php"); 
                        exit();
                    }
                }
            } else {
                $message = "Incorrect password.";
                $messageClass = "bg-rose-500/20 border-rose-500/30 text-rose-200 animate-shake";
            }
        } else {
            $message = "Gmail address not found in our records.";
            $messageClass = "bg-rose-500/20 border-rose-500/30 text-rose-200 animate-shake";
        }
    }
}
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pannakoda Enterprise OS - System Login</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- AOS Library CSS -->
 <link href="../LIBRARIES/AOS/aos.css" rel="stylesheet">
<script src="../LIBRARIES/AOS/AOS.js"></script>
    <style>
        :root {
            --sidebar-bg: linear-gradient(135deg, #0f0f11 0%, #1a1518 100%);
            --text-white: #ffffff;
            --accent-coral: #ff6b4a; 
            --accent-glow: rgba(255, 107, 74, 0.3);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px) scale(0.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-6px); }
            40%, 80% { transform: translateX(6px); }
        }
        @keyframes floatSlow {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-12px) scale(1.02); }
        }
        @keyframes pulseGlow {
            0%, 100% { opacity: 0.4; }
            50% { opacity: 0.8; }
        }

        .animate-fade-in { animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .animate-shake { animation: shake 0.4s ease-in-out; }
        .animate-float { animation: floatSlow 8s ease-in-out infinite; }
        
        .page-bg {
            background: linear-gradient(180deg, #09090b 0%, #1a1010 100%);
            position: relative;
            overflow: hidden;
            background-image: url("../LIBRARIES/Gemini_Generated_Image_l00b6cl00b6cl00b.png");
            background-size: cover;
            background-position: center;
        }
        
        .page-bg::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(9, 9, 11, 0.92) 0%, rgba(26, 16, 16, 0.88) 100%);
            z-index: 1;
        }

        .page-bg::after {
            content: '';
            position: absolute;
            top: 20%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255, 107, 74, 0.15) 0%, transparent 70%);
            z-index: 1;
            pointer-events: none;
            animation: pulseGlow 4s ease-in-out infinite;
        }

        .glass-container {
            background: linear-gradient(135deg, rgba(26, 21, 24, 0.75) 0%, rgba(15, 15, 17, 0.85) 100%);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 107, 74, 0.2);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.7), 0 0 35px rgba(255, 107, 74, 0.1);
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .glass-container:hover {
            border-color: rgba(255, 107, 74, 0.4);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.8), 0 0 45px rgba(255, 107, 74, 0.15);
        }

        .input-effect {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .input-effect:focus-within {
            transform: translateY(-2px);
        }

        .swal2-html-container { margin: 0.75rem 0 0 0 !important; overflow: visible !important; }
    </style>
</head>
<body class="page-bg h-screen w-full flex flex-col justify-between overflow-hidden">

    <!-- Navbar -->
    <header class="w-full px-5 py-3.5 flex items-center justify-between bg-[#09090b]/60 backdrop-blur-md border-b border-[#ff6b4a]/20 relative z-20 shadow-lg" data-aos="fade-down" data-aos-duration="800">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center shadow-lg border border-white/20" style="background: linear-gradient(135deg, #ff6b4a 0%, #fa4b2a 100%);">
                <img src="../LIBRARIES/5501d331-f1e5-4dcc-ab9b-8fd56a2b5ea5.png" alt="logo" class="w-7 h-7 rounded-lg object-cover">
            </div>
            <div>
                <span class="text-white font-bold text-sm tracking-wider block font-sans uppercase">Pannakoda</span>
                <span class="text-[9px] font-mono tracking-widest block font-semibold" style="color: #ff6b4a;">ENTERPRISE OS</span>
            </div>
        </div>
    </header>

    <!-- Main Content / Login Form Container (Lagyan ng AOS at Floating Animation) -->
    <div class="flex-1 flex items-center justify-center p-3 relative z-10">
        <div class="w-full max-w-[420px] glass-container flex flex-col justify-between p-6 sm:p-7 rounded-[28px] animate-fade-in animate-float" data-aos="zoom-in-up" data-aos-duration="1000" data-aos-easing="ease-out-back">
            
            <div class="flex justify-end items-center gap-3 mb-1">
                <?php if (!$admin_exists): ?>
                    <button type="button" onclick="location.href='register.php'" 
                            class="bg-white/10 hover:bg-[#ff6b4a]/20 text-orange-200 font-bold text-[11px] uppercase tracking-wider py-1.5 px-3.5 rounded-xl border border-white/20 transition-all duration-150 hover:scale-105 active:scale-95 shadow-sm">
                        Register Admin
                    </button>
                <?php endif; ?>
            </div>

            <div class="w-full py-1">
                <div class="mb-5 transition-transform duration-500 hover:translate-x-1" data-aos="fade-right" data-aos-delay="200">
                    <h2 class="text-2xl sm:text-3xl font-bold text-white tracking-wide font-sans">Login</h2>
                    <p class="text-[9px] sm:text-[10px] text-orange-200/80 mt-0.5 uppercase tracking-widest font-mono font-semibold" style="color: #ff6b4a;">WELCOME BACK PLEASE LOGIN TO YOUR ACCOUNT</p>
                </div>

                <?php if(!empty($message)): ?>
                    <div class="border p-2.5 mb-3 rounded-xl <?php echo $messageClass; ?> text-xs font-medium flex items-center gap-2 shadow-sm animate-shake" role="alert">
                        <i class="bi bi-exclamation-circle-fill text-sm"></i>
                        <span><?php echo $message; ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-3.5" onsubmit="handleLoginSubmit(event)">
                    <!-- 3 Boxes / Input Fields binigyan ng AOS -->
                    <div class="input-effect" data-aos="fade-up" data-aos-delay="300">
                        <div class="relative">
                            <input type="email" name="gmail" id="floatingGmail" placeholder="User Name / Email" required
                                   class="w-full px-4 py-3 rounded-xl bg-black/40 border border-white/15 text-white placeholder-orange-200/50 focus:outline-none focus:ring-2 focus:ring-[#ff6b4a] focus:border-[#ff6b4a] transition-all text-sm shadow-inner pr-12">
                            <span class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-orange-200/70 transition-transform duration-300 group-focus-within:scale-110"><i class="bi bi-person text-lg"></i></span>
                        </div>
                    </div>

                    <div class="input-effect" data-aos="fade-up" data-aos-delay="400">
                        <div class="relative">
                            <input type="password" name="password" id="floatingPassword" placeholder="Password" required
                                   class="w-full px-4 py-3 rounded-xl bg-black/40 border border-white/15 text-white placeholder-orange-200/50 focus:outline-none focus:ring-2 focus:ring-[#ff6b4a] focus:border-[#ff6b4a] transition-all text-sm shadow-inner pr-12">
                            <button type="button" onclick="togglePasswordVisibility()" class="absolute inset-y-0 right-0 pr-4 flex items-center text-orange-200/70 hover:text-white focus:outline-none transition-transform duration-200 active:scale-90">
                                <i id="toggleIcon" class="bi bi-eye-slash"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between text-xs pt-0.5 px-1" data-aos="fade-up" data-aos-delay="500">
                        <label class="flex items-center text-orange-200/90 select-none cursor-pointer hover:text-white transition-colors">
                            <input type="checkbox" id="showpassword" onclick="togglePasswordCheckbox()" class="rounded border-white/30 bg-black/40 text-[#ff6b4a] focus:ring-[#ff6b4a] h-4 w-4 mr-2 transition-transform duration-200 hover:scale-110">
                            <span class="text-[11px]">Remember Me</span>
                        </label>
                        <a href="#" onclick="alert('Please contact your administrator to reset your password.'); return false;" class="text-[11px] text-orange-200/80 hover:text-white transition-all hover:underline">Forgot Password</a>
                    </div>

                    <div class="pt-1" data-aos="fade-up" data-aos-delay="600">
                        <button type="submit" name="login" id="loginBtn"
                                class="w-full text-white font-bold py-3 px-4 rounded-xl shadow-lg transition-all duration-300 transform hover:-translate-y-0.5 active:scale-[0.97] text-sm uppercase tracking-wider border border-white/25 relative overflow-hidden group"
                                style="background: linear-gradient(135deg, #ff6b4a 0%, #fa4b2a 100%); box-shadow: 0 6px 20px rgba(255, 107, 74, 0.4);">
                            <span class="relative z-10 flex items-center justify-center gap-2">
                                <span id="btnText">LOGIN</span>
                                <i class="bi bi-arrow-right transition-transform duration-300 group-hover:translate-x-1"></i>
                            </span>
                            <div class="absolute inset-0 bg-white/20 transform -translate-x-full group-hover:translate-x-full transition-transform duration-700"></div>
                        </button>
                    </div>

                    <?php if (!$has_hr): ?>
                        <div class="pt-1" data-aos="fade-up" data-aos-delay="700">
                            <button type="button" onclick="openApplicationModal()" 
                                    class="w-full bg-white/10 hover:bg-white/20 text-white font-bold text-xs uppercase tracking-wider py-3 px-4 rounded-xl transition-all duration-300 transform hover:-translate-y-0.5 active:scale-[0.97] shadow-md flex items-center justify-center gap-2 cursor-pointer border border-white/20 group">
                                <i class="bi bi-person-plus-fill text-sm transition-transform duration-300 group-hover:rotate-12" style="color: #ff6b4a;"></i> Apply / Register Employee
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
          
        </div>
    </div>

<script src="../LIBRARIES/bootstrap.bundle.min.js"></script>
<!-- AOS Library JS -->

<script>
    // Initialize AOS
    AOS.init({
        once: false,
        mirror: true
    });

let cachedActiveJobs = [];

function fetchActiveHiringPools() {
    fetch("recruitment.php?action=fetch")
        .then(res => res.json())
        .then(data => {
            cachedActiveJobs = data.filter(job => job.status === "Active" && parseInt(job.openings) > 0);
        })
        .catch(err => console.error("Failed to sync vacancy data:", err));
}

document.addEventListener("DOMContentLoaded", function() {
    fetchActiveHiringPools();
});

function handleLoginSubmit(event) {
    const btn = document.getElementById('loginBtn');
    const btnText = document.getElementById('btnText');
    btnText.innerHTML = `
        <span class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2 align-middle"></span>
        Authenticating...
    `;
    btn.classList.add('opacity-90', 'cursor-wait');
}

function togglePasswordVisibility() {
    const passwordInput = document.getElementById('floatingPassword');
    const toggleIcon = document.getElementById('toggleIcon');
    const checkbox = document.getElementById('showpassword');

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.className = 'bi bi-eye';
        checkbox.checked = true;
    } else {
        passwordInput.type = 'password';
        toggleIcon.className = 'bi bi-eye-slash';
        checkbox.checked = false;
    }
}

function togglePasswordCheckbox() {
    const passwordInput = document.getElementById('floatingPassword');
    const toggleIcon = document.getElementById('toggleIcon');
    const checkbox = document.getElementById('showpassword');

    if (checkbox.checked) {
        passwordInput.type = 'text';
        toggleIcon.className = 'bi bi-eye';
    } else {
        passwordInput.type = 'password';
        toggleIcon.className = 'bi bi-eye-slash';
    }
}

function populateDepartmentOptions() {
    const select = document.getElementById('department');
    if (!select) return;

    const renderOptions = (jobs) => {
        if (!jobs.length) {
            select.innerHTML = `<option value="" disabled selected>No open positions available (Default Option)</option>`;
            return;
        }
        select.innerHTML = `<option value="" disabled selected>Select a department</option>` +
            jobs.map(job => `<option value="${job.department}">${job.department} (${job.openings} slot${job.openings > 1 ? 's' : ''} left)</option>`).join('');
    };

    if (cachedActiveJobs.length) {
        renderOptions(cachedActiveJobs);
    } else {
        fetch("recruitment.php?action=fetch")
            .then(res => res.json())
            .then(data => {
                cachedActiveJobs = data.filter(job => job.status === "Active" && parseInt(job.openings) > 0);
                renderOptions(cachedActiveJobs);
            })
            .catch(() => {
                select.innerHTML = `
                    <option value="" disabled selected>Select a department</option>
                    <option value="HR">HR</option>
                    <option value="Finance">Finance</option>
                    <option value="Operations">Operations</option>
                    <option value="Kitchen">Kitchen</option>
                `;
            });
    }
}

window.openApplicationModal = function(formDataValues = {}) {
    Swal.fire({
        html: `
            <div class="text-left mb-4">
                <span class="text-[11px] font-black uppercase tracking-widest text-[#ff6b4a] bg-[#ff6b4a]/10 px-3 py-1 rounded-md border border-[#ff6b4a]/20">Candidate Onboarding</span>
                <h2 class="text-xl sm:text-2xl font-black text-slate-900 mt-1.5">Application Registration Form</h2>
                <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Please fill out all required fields accurately.</p>
            </div>

            <form id="clientForm" enctype="multipart/form-data" class="text-left font-sans">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-5">
                    <div class="space-y-3.5">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">Full Name</label>
                            <input type="text" id="full_name" name="full_name" value="${formDataValues.full_name || ''}" placeholder="e.g. Juan Dela Cruz" required class="w-full text-sm sm:text-base px-4 py-3 border border-slate-200 rounded-xl focus:outline-none focus:border-[#ff6b4a] font-medium text-slate-800 bg-white shadow-sm transition-all duration-200 focus:ring-2 focus:ring-[#ff6b4a]/20">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">Department</label>
                            <select id="department" name="department" required class="w-full text-sm sm:text-base px-4 py-3 border border-slate-200 rounded-xl focus:outline-none focus:border-[#ff6b4a] font-medium text-slate-800 bg-white shadow-sm transition-all duration-200 focus:ring-2 focus:ring-[#ff6b4a]/20">
                                <option value="" disabled selected>Loading positions...</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">Email</label>
                                <input type="email" id="email" name="email" value="${formDataValues.email || ''}" placeholder="name@example.com" required class="w-full text-sm sm:text-base px-3.5 py-3 border border-slate-200 rounded-xl focus:outline-none focus:border-[#ff6b4a] font-medium text-slate-800 bg-white shadow-sm transition-all duration-200 focus:ring-2 focus:ring-[#ff6b4a]/20">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">Phone</label>
                                <input type="text" id="phone" name="phone" value="${formDataValues.phone || ''}" placeholder="09123456789" maxlength="11" required class="w-full text-sm sm:text-base px-3.5 py-3 border border-slate-200 rounded-xl focus:outline-none focus:border-[#ff6b4a] font-medium text-slate-800 bg-white shadow-sm transition-all duration-200 focus:ring-2 focus:ring-[#ff6b4a]/20">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">Home Address</label>
                            <textarea id="address" name="address" required rows="2" placeholder="Complete address" class="w-full text-sm sm:text-base px-4 py-2.5 border border-slate-200 rounded-xl focus:outline-none focus:border-[#ff6b4a] font-medium text-slate-800 resize-none bg-white shadow-sm transition-all duration-200 focus:ring-2 focus:ring-[#ff6b4a]/20">${formDataValues.address || ''}</textarea>
                        </div>
                    </div>

                    <div class="space-y-3.5 flex flex-col justify-between">
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 shadow-sm">
                            <h4 class="text-[11px] font-black text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                <i class="bi bi-shield-lock-fill text-[#ff6b4a] text-sm"></i> Government IDs
                            </h4>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">GSIS ID</label>
                                    <input type="text" id="gsis_id" name="gsis_id" value="${formDataValues.gsis_id || ''}" required placeholder="XX-XXXXXXX-X" class="w-full text-xs sm:text-sm px-3 py-2.5 border border-slate-200 rounded-lg bg-white focus:outline-none focus:border-[#ff6b4a] text-slate-800 shadow-sm transition-all duration-200 focus:ring-2 focus:ring-[#ff6b4a]/20">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">SSS ID</label>
                                    <input type="text" id="sss_id" name="sss_id" value="${formDataValues.sss_id || ''}" required placeholder="XX-XXXXXXX-X" class="w-full text-xs sm:text-sm px-3 py-2.5 border border-slate-200 rounded-lg bg-white focus:outline-none focus:border-[#ff6b4a] text-slate-800 shadow-sm transition-all duration-200 focus:ring-2 focus:ring-[#ff6b4a]/20">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">PhilHealth ID</label>
                                    <input type="text" id="philhealth_id" name="philhealth_id" value="${formDataValues.philhealth_id || ''}" required placeholder="XX-XXXXXXXXX-X" class="w-full text-xs sm:text-sm px-3 py-2.5 border border-slate-200 rounded-lg bg-white focus:outline-none focus:border-[#ff6b4a] text-slate-800 shadow-sm transition-all duration-200 focus:ring-2 focus:ring-[#ff6b4a]/20">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 uppercase mb-1">Pag-IBIG MID</label>
                                    <input type="text" id="pagibig_id" name="pagibig_id" value="${formDataValues.pagibig_id || ''}" required placeholder="XXXX-XXXX-XXXX" class="w-full text-xs sm:text-sm px-3 py-2.5 border border-slate-200 rounded-lg bg-white focus:outline-none focus:border-[#ff6b4a] text-slate-800 shadow-sm transition-all duration-200 focus:ring-2 focus:ring-[#ff6b4a]/20">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">Upload Resume / CV</label>
                            <div id="dropzone" class="border-2 border-dashed border-slate-300 rounded-xl p-3.5 text-center bg-white cursor-pointer transition-all duration-300 flex flex-col items-center justify-center gap-1 hover:border-[#ff6b4a] hover:bg-[#ff6b4a]/5 shadow-sm">
                                <i class="bi bi-cloud-arrow-up-fill text-2xl text-[#ff6b4a] transition-transform duration-300 hover:scale-110"></i>
                                <p class="text-xs sm:text-sm font-bold text-slate-700 drop-text">Drag file or <span class="text-[#ff6b4a] underline">browse</span></p>
                                <p class="text-[10px] text-slate-400">PDF, DOC, DOCX (Max: 10MB)</p>
                                <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx" class="hidden">
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Submit Application',
        cancelButtonText: 'Discard',
        buttonsStyling: false,
        customClass: {
            popup: 'rounded-2xl shadow-2xl bg-white border border-slate-100 p-7 sm:p-9 max-w-4xl w-full animate-fade-in',
            confirmButton: 'w-full py-3.5 text-white font-extrabold rounded-xl border-0 cursor-pointer shadow-md text-sm sm:text-base mb-2.5 mt-4 transition-all duration-200 hover:-translate-y-0.5 active:scale-[0.98]',
            cancelButton: 'w-full py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl border-0 cursor-pointer text-xs sm:text-sm transition-all duration-200'
        },
        didOpen: () => {
            const confirmBtn = Swal.getConfirmButton();
            if (confirmBtn) {
                confirmBtn.style.background = 'linear-gradient(135deg, #ff6b4a 0%, #fa4b2a 100%)';
            }

            populateDepartmentOptions();
            
            if (formDataValues.department) {
                setTimeout(() => {
                    const deptSelect = document.getElementById('department');
                    if (deptSelect) deptSelect.value = formDataValues.department;
                }, 300);
            }

            const dropzone = document.getElementById('dropzone');
            const fileInput = document.getElementById('resume');
            const dropText = dropzone.querySelector('.drop-text');

            dropzone.addEventListener('click', () => fileInput.click());
            fileInput.addEventListener('change', (e) => syncFileVisualText(e.target.files[0]));

            dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('border-[#ff6b4a]', 'bg-[#ff6b4a]/10', 'scale-[1.01]'); });
            ['dragleave', 'drop'].forEach(event => dropzone.addEventListener(event, () => dropzone.classList.remove('border-[#ff6b4a]', 'bg-[#ff6b4a]/10', 'scale-[1.01]')));
            
            dropzone.addEventListener('drop', (e) => { 
                e.preventDefault(); 
                if(e.dataTransfer.files.length) { 
                    fileInput.files = e.dataTransfer.files; 
                    syncFileVisualText(e.dataTransfer.files[0]); 
                } 
            });

            function syncFileVisualText(file) {
                if(!file) return;
                dropText.innerHTML = `<span class="text-[#ff6b4a] font-bold flex items-center justify-center gap-1.5"><i class="bi bi-file-earmark-check-fill animate-bounce"></i> ${file.name}</span>`;
            }
        },
        preConfirm: () => {
            const name = document.getElementById('full_name').value.trim();
            const department = document.getElementById('department').value;
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const address = document.getElementById('address').value.trim();
            const gsisId = document.getElementById('gsis_id').value.trim();
            const sssId = document.getElementById('sss_id').value.trim();
            const philhealthId = document.getElementById('philhealth_id').value.trim();
            const pagibigId = document.getElementById('pagibig_id').value.trim();
            const file = document.getElementById('resume').files[0];

            const govtIdPattern = /^\d{2}-\d{7}-\d{1}$/;
            const philHealthPattern = /^\d{2}-\d{9}-\d{1}$/;
            const pagibigPattern = /^\d{4}-\d{4}-\d{4}$/;

            if (!name || !email || !phone || !address) {
                Swal.showValidationMessage('Please completely fill out all personal details.');
                return false;
            }
            if (!department) {
                Swal.showValidationMessage('Please select a target department.');
                return false;
            }
            if (!/^09\d{9}$/.test(phone)) {
                Swal.showValidationMessage('Phone number must be 11 digits starting with 09.');
                return false;
            }
            if (!govtIdPattern.test(gsisId) || !govtIdPattern.test(sssId)) {
                Swal.showValidationMessage('Invalid Government ID format (XX-XXXXXXX-X)');
                return false;
            }
            if (!philHealthPattern.test(philhealthId)) {
                Swal.showValidationMessage('Invalid PhilHealth ID format (XX-XXXXXXXXX-X)');
                return false;
            }
            if (!pagibigPattern.test(pagibigId)) {
                Swal.showValidationMessage('Invalid Pag-IBIG MID format (XXXX-XXXX-XXXX)');
                return false;
            }
            if(!file) { 
                Swal.showValidationMessage('Please attach your resume file asset.'); 
                return false; 
            }

            return new FormData(document.getElementById('clientForm'));
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            const rawFormData = result.value;
            
            const currentValues = {
                full_name: rawFormData.get('full_name'),
                email: rawFormData.get('email'),
                phone: rawFormData.get('phone'),
                address: rawFormData.get('address'),
                gsis_id: rawFormData.get('gsis_id'),
                sss_id: rawFormData.get('sss_id'),
                philhealth_id: rawFormData.get('philhealth_id'),
                pagibig_id: rawFormData.get('pagibig_id'),
                department: rawFormData.get('department')
            };

            Swal.fire({ 
                title: 'Submitting application...', 
                html: 'Please wait while we secure your records.',
                allowOutsideClick: false, 
                didOpen: () => Swal.showLoading() 
            });

            fetch("login.php?action=apply", {
                method: "POST",
                body: rawFormData
            })
            .then(async res => {
                const text = await res.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error("PHP Response Error:", text);
                    throw new Error("Server Error: " + text.substring(0, 150));
                }
            })
            .then(data => {
                if(data.status === "success") {
                    Swal.fire({ 
                        icon: 'success', 
                        title: 'Application Submitted!', 
                        text: data.message,
                        customClass: { confirmButton: 'bg-[#ff6b4a] hover:bg-[#fa4b2a] text-white px-5 py-2.5 rounded-xl font-bold border-0 cursor-pointer shadow-md text-xs' },
                        buttonsStyling: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Submission Failed',
                        text: data.message,
                        confirmButtonText: 'Try Again',
                        customClass: { confirmButton: 'bg-[#ff6b4a] hover:bg-[#fa4b2a] text-white px-5 py-2.5 rounded-xl font-bold border-0 cursor-pointer shadow-md text-xs' },
                        buttonsStyling: false
                    }).then(() => {
                        openApplicationModal(currentValues);
                    });
                }
            })
            .catch(err => {
                Swal.fire({
                    icon: 'error',
                    title: 'Connection or Script Fault',
                    text: err.message,
                    confirmButtonText: 'Back',
                    customClass: { confirmButton: 'bg-[#ff6b4a] hover:bg-[#fa4b2a] text-white px-5 py-2.5 rounded-xl font-bold border-0 cursor-pointer shadow-md text-xs' },
                    buttonsStyling: false
                }).then(() => {
                    openApplicationModal(currentValues);
                });
            });
        }
    });
};
</script>
</body>
</html>