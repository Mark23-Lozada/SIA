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
            $messageClass = "bg-red-100 border-red-400 text-red-700";
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

                // Suriin kung personal/employee email o company email ang ginamit
                $is_employee_gmail_login = ($gmail === $emp_row['email'] || $gmail === $emp_row['employee_gmail']);

                if ($is_employee_gmail_login) {
                    header("Location: info.php"); 
                    exit();
                } else {
                    // Pag-route batay sa department kapag company_gmail ang ginamit
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
                $messageClass = "bg-red-100 border-red-400 text-red-700";
            }
        } else {
            $message = "Gmail address not found in our records.";
            $messageClass = "bg-red-100 border-red-400 text-red-700";
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
    <title>System Login - PannaKoda</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-slate-100 flex items-center justify-center min-h-screen">

<div class="w-full max-w-md p-4">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden p-8">
        
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 bg-orange-500 rounded-xl text-white mb-3 shadow-md shadow-orange-200">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">PannaKoda Portal</h1>
            <p class="text-sm text-slate-500 mt-1">Sign in as Admin or Employee</p>
        </div>

        <?php if(!empty($message)): ?>
            <div class="border-l-4 p-4 mb-6 rounded <?php echo $messageClass; ?> relative text-sm" role="alert">
                <span class="block sm:inline"><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label for="floatingGmail" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Gmail / Email Address</label>
                <input type="email" name="gmail" id="floatingGmail" placeholder="name@example.com" required
                       class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500 text-slate-700 placeholder-slate-400 transition-all text-sm">
            </div>

            <div>
                <label for="floatingPassword" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Password</label>
                <input type="password" name="password" id="floatingPassword" placeholder="••••••••" required
                       class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500 text-slate-700 placeholder-slate-400 transition-all text-sm">
            </div>
            
            <div class="flex items-center justify-between text-sm pt-1">
                <label class="flex items-center text-slate-600 select-none cursor-pointer">
                    <input type="checkbox" id="showpassword" onclick="togglePasswordVisibility()" class="rounded border-slate-300 text-orange-500 focus:ring-orange-500 h-4 w-4 mr-2">
                    <span>Show Password</span>
                </label>
            </div>

            <div class="pt-2 space-y-3">
                <button type="submit" name="login" 
                        class="w-full bg-orange-500 hover:bg-orange-600 text-white font-semibold py-3 px-4 rounded-xl shadow-lg shadow-orange-100 hover:shadow-xl transition-all duration-200 transform active:scale-[0.99]">
                    Sign In
                </button>

                <div class="pt-2 space-y-3">
                    <?php if (!$admin_exists): ?>
                        <button type="button" onclick="location.href='register.php'" 
                                class="w-full bg-slate-500 hover:bg-slate-600 text-white font-semibold py-3 px-4 rounded-xl shadow-md transition-all duration-200 transform active:scale-[0.99]">
                            Register Initial Admin Account
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>      
    </div>
</div>

<script>
function togglePasswordVisibility() {
    const passwordInput = document.getElementById('floatingPassword');
    passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>