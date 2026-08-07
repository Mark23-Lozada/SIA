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

$message = "";
$messageClass = "";

$check_query = "SELECT COUNT(*) as total FROM admin";
$check_result = $db->query($check_query);
$row_count = $check_result->fetch_assoc();
$admin_exists = $row_count['total'] > 0;

if(isset($_POST['login'])){
    $gmail = trim($_POST['gmail']); 
    $password = trim($_POST['password']); 

    // 1. ADMIN LOGIN CHECK
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
            header("Location: dashboard.php");
            exit();
        } else {
            $message = "Incorrect password for Admin.";
            $messageClass = "bg-red-50 border-red-200 text-red-600";
        }
    } 
    else {
        // 2. EMPLOYEE LOGIN CHECK
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
                        exit();
                    } else {
                        header("Location: ../project-test1/FRONTEND/pos_dash.php"); 
                        exit();
                    }
                }
            } else {
                $message = "Incorrect password.";
                $messageClass = "bg-red-50 border-red-200 text-red-600";
            }
        } else {
            $message = "Gmail address not found in our records.";
            $messageClass = "bg-red-50 border-red-200 text-red-600";
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
    <title>PannaKoda Pancake House - System Login</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }
        .wave-bg {
            background: linear-gradient(135deg, #FF8C00 0%, #ff7200 50%, #e07b00 100%);
            border-top-right-radius: 120px;
            border-bottom-right-radius: 120px;
        }
    </style>
</head>
<body class="bg-slate-50 font-sans antialiased h-screen overflow-hidden m-0 p-0">

    <div class="flex h-screen w-full overflow-hidden bg-white">
        
        <!-- LEFT SIDE: Pancake Business Features with Orange Wave Design -->
        <div class="hidden lg:flex lg:w-5/12 wave-bg flex-col justify-between p-12 text-white relative shadow-2xl z-10">
            <div>
                <div class="inline-flex items-center justify-center w-12 h-12 bg-white/25 backdrop-blur-md rounded-2xl text-white shadow-md mb-6 transition-all duration-300 hover:bg-white/35 hover:scale-105">
                    <i class="bi bi-shop text-xl"></i>
                </div>
                <span class="text-xs uppercase tracking-widest font-bold text-orange-100/85">PannaKoda House</span>
                <h1 class="text-4xl font-black tracking-tight mt-1 mb-3 leading-tight">Fresh Stack, Sweet Success!</h1>
            </div>

            <!-- Pancake Business Feature Highlights na may Mouse-Over / Hover Effect -->
            <div class="space-y-6 my-auto">
                <div class="flex items-start gap-4 bg-white/10 backdrop-blur-md p-4 rounded-2xl border border-white/10 transition-all duration-300 hover:bg-white/25 hover:border-white/30 hover:shadow-lg hover:scale-[1.02] cursor-pointer">
                    <div class="p-3 bg-white/20 rounded-xl text-white">
                        <i class="bi bi-egg-fried text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-sm tracking-wide">Daily Fresh Batters</h3>
                        <p class="text-xs text-orange-100/80 mt-0.5">Manage daily pancake & waffle inventory</p>
                    </div>
                </div>

                <div class="flex items-start gap-4 bg-white/10 backdrop-blur-md p-4 rounded-2xl border border-white/10 transition-all duration-300 hover:bg-white/25 hover:border-white/30 hover:shadow-lg hover:scale-[1.02] cursor-pointer">
                    <div class="p-3 bg-white/20 rounded-xl text-white">
                        <i class="bi bi-receipt text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-sm tracking-wide">Quick POS Orders</h3>
                        <p class="text-xs text-orange-100/80 mt-0.5">Fast checkout for syrups, toppings & stacks</p>
                    </div>
                </div>

                <div class="flex items-start gap-4 bg-white/10 backdrop-blur-md p-4 rounded-2xl border border-white/10 transition-all duration-300 hover:bg-white/25 hover:border-white/30 hover:shadow-lg hover:scale-[1.02] cursor-pointer">
                    <div class="p-3 bg-white/20 rounded-xl text-white">
                        <i class="bi bi-people-fill text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-sm tracking-wide">Staff & Payroll</h3>
                        <p class="text-xs text-orange-100/80 mt-0.5">Seamless crew schedules and attendance</p>
                    </div>
                </div>
            </div>

            <div class="text-xs text-orange-100/70">
                <span>PannaKoda POS Portal &copy; 2026</span>
            </div>
        </div>

        <!-- RIGHT SIDE: Login Form -->
        <div class="w-full lg:w-7/12 h-full bg-white flex flex-col justify-between p-8 sm:p-12 lg:p-16 overflow-y-auto animate-fade-in">
            
            <!-- Top Right Register / Actions Header -->
            <div class="flex justify-end items-center">
                <?php if (!$admin_exists): ?>
                    <button type="button" onclick="location.href='register.php'" 
                            class="bg-orange-50 hover:bg-orange-100 text-[#FF8C00] font-bold text-xs uppercase tracking-wider py-2.5 px-5 rounded-xl border border-orange-200 transition-all shadow-sm">
                        Register Admin
                    </button>
                <?php endif; ?>
            </div>

            <!-- Main Form Container -->
            <div class="max-w-md w-full mx-auto my-auto py-6">
                
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-14 h-14 bg-orange-50 text-[#FF8C00] rounded-2xl mb-3 shadow-inner border border-orange-100">
                        <i class="bi bi-lock-fill text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-black text-slate-800 tracking-tight">Login to your account</h2>
                    <p class="text-xs text-slate-400 mt-1 uppercase tracking-wider font-semibold">Enter your credentials to continue</p>
                </div>

                <?php if(!empty($message)): ?>
                    <div class="border p-3.5 mb-6 rounded-xl <?php echo $messageClass; ?> text-xs font-medium flex items-center gap-2 shadow-sm" role="alert">
                        <i class="bi bi-exclamation-circle-fill text-sm"></i>
                        <span><?php echo $message; ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">
                    <div>
                        <label for="floatingGmail" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Email Address</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                                <i class="bi bi-envelope"></i>
                            </span>
                            <input type="email" name="gmail" id="floatingGmail" placeholder="Enter your email address" required
                                   class="w-full pl-10 pr-4 py-3 rounded-xl bg-slate-50/50 border border-slate-200 text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#FF8C00] focus:border-[#FF8C00] transition-all text-sm">
                        </div>
                    </div>

                    <div>
                        <label for="floatingPassword" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Password</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                                <i class="bi bi-lock"></i>
                            </span>
                            <input type="password" name="password" id="floatingPassword" placeholder="Enter your password" required
                                   class="w-full pl-10 pr-10 py-3 rounded-xl bg-slate-50/50 border border-slate-200 text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#FF8C00] focus:border-[#FF8C00] transition-all text-sm">
                            <button type="button" onclick="togglePasswordVisibility()" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none">
                                <i id="toggleIcon" class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between text-xs pt-1">
                        <label class="flex items-center text-slate-500 select-none cursor-pointer hover:text-slate-700">
                            <input type="checkbox" id="showpassword" onclick="togglePasswordCheckbox()" class="rounded border-slate-300 text-[#FF8C00] focus:ring-[#FF8C00] h-4 w-4 mr-2">
                            <span>Remember me</span>
                        </label>
                        <a href="#" onclick="alert('Please contact your administrator to reset your password.'); return false;" class="text-slate-400 hover:text-[#FF8C00] transition-colors">Forgot password?</a>
                    </div>

                    <div class="pt-3">
                        <button type="submit" name="login" 
                                class="w-full bg-[#FF8C00] hover:bg-[#e07b00] text-white font-semibold py-3 px-4 rounded-xl shadow-lg shadow-orange-500/20 hover:shadow-xl hover:shadow-orange-500/30 transition-all duration-200 transform active:scale-[0.98] text-sm">
                            Continue
                        </button>
                    </div>
                </form>
            </div>

            <!-- Footer Help Section -->
            <div class="text-center text-xs text-slate-400 pt-4">
                <span>Need help ? <a href="#" class="text-[#FF8C00] font-bold hover:underline">Contact admin</a></span>
            </div>
        </div>

    </div>

<script>
function togglePasswordVisibility() {
    const passwordInput = document.getElementById('floatingPassword');
    const toggleIcon = document.getElementById('toggleIcon');
    const checkbox = document.getElementById('showpassword');

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.className = 'bi bi-eye-slash';
        checkbox.checked = true;
    } else {
        passwordInput.type = 'password';
        toggleIcon.className = 'bi bi-eye';
        checkbox.checked = false;
    }
}

function togglePasswordCheckbox() {
    const passwordInput = document.getElementById('floatingPassword');
    const toggleIcon = document.getElementById('toggleIcon');
    const checkbox = document.getElementById('showpassword');

    if (checkbox.checked) {
        passwordInput.type = 'text';
        toggleIcon.className = 'bi bi-eye-slash';
    } else {
        passwordInput.type = 'password';
        toggleIcon.className = 'bi bi-eye';
    }
}
</script>
</body>
</html>