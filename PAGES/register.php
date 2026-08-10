<?php
ob_start();
session_start();

$host = "localhost";
$username = "root";
$password = "";
$db_name = "pos";

$con = mysqli_connect($host, $username, $password, $db_name);

if (!$con) {
    die("Connection Failed: " . mysqli_connect_error());
}

$message = "";
$messageType = ""; 

// REGISTRATION PROCESS (DIRECT TO DATABASE, NO OTP Required)
if (isset($_POST['register'])) {
    $name = trim($_POST['name'] ?? '');
    $gmail = trim($_POST['gmail']); 
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Image Upload Handling
    $image_path = "";
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['profile_image']['tmp_name'];
        $file_name = $_FILES['profile_image']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($file_ext, $allowed_extensions)) {
            // Create uploads directory if it doesn't exist
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $new_file_name = uniqid('admin_', true) . '.' . $file_ext;
            $destination = $upload_dir . $new_file_name;
            
            if (move_uploaded_file($file_tmp, $destination)) {
                $image_path = $destination;
            }
        }
    }

    // PHP Backend Fallback Validations (For Security)
    if (empty($name) || empty($gmail) || empty($password) || empty($confirm_password)) {
        $message = "All fields are required. Please fill them up.";
        $messageType = "error";
    }
    elseif (!filter_var($gmail, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format. Please enter a valid email address.";
        $messageType = "error";
    }
    // Strict Strong Password Policy on Backend
    elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
        $message = "Password must be at least 8 characters long, contain an uppercase letter, lowercase letter, number, and a special character.";
        $messageType = "error";
    }
    elseif ($password !== $confirm_password) {
        $message = "Passwords do not match. Please try again.";
        $messageType = "error";
    } else {
        // Check if an admin already exists in the database
        $check = mysqli_query($con, "SELECT COUNT(*) as total FROM admin");
        $row = mysqli_fetch_assoc($check);
        
        if ($row['total'] > 0) {
            $message = "An admin account already exists. Registration is disabled.";
            $messageType = "error";
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            
            // Updated query to include name and image_path columns
            $stmt = $con->prepare("INSERT INTO admin (name, gmail, password, image_path) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $gmail, $hashed_password, $image_path);
            
            if ($stmt->execute()) {
                $_SESSION['swal_success'] = "Admin registered successfully!";
                header("Location: login.php");
                exit();
            } else {
                $message = "Database Error: " . mysqli_error($con);
                $messageType = "error";
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Registration</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fadeIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        
        .page-bg {
            background-color: #0c0714;
            position: relative;
            overflow: hidden;
        }
        .page-bg::before {
            content: '';
            position: absolute;
            top: -10%;
            left: -15%;
            width: 550px;
            height: 550px;
            background: radial-gradient(circle, rgba(168, 85, 247, 0.7) 0%, rgba(107, 33, 168, 0.3) 60%, transparent 100%);
            filter: blur(80px);
            border-radius: 50%;
            z-index: 0;
        }
        .page-bg::after {
            content: '';
            position: absolute;
            bottom: -15%;
            right: -10%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(216, 180, 254, 0.4) 0%, rgba(126, 34, 206, 0.2) 50%, transparent 100%);
            filter: blur(100px);
            border-radius: 50%;
            z-index: 0;
        }

        .glass-container {
            background: linear-gradient(135deg, rgba(107, 33, 168, 0.3) 0%, rgba(45, 21, 85, 0.5) 100%);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.6);
        }
    </style>
</head>
<body class="bg-slate-950 font-sans antialiased min-h-screen overflow-x-hidden m-0 p-0 selection:bg-purple-600 selection:text-white">

    <div class="flex min-h-screen w-full items-center justify-center page-bg p-4 sm:p-6">
        
        <div class="w-full max-w-[480px] glass-container flex flex-col justify-between p-6 sm:p-8 rounded-[35px] animate-fade-in relative z-10 my-8">
            
            <div class="w-full">
                <div class="mb-6 text-center">
                    <h2 class="text-2xl sm:text-3xl font-light text-white tracking-wide font-serif">Register Admin</h2>
                    <p class="text-[10px] sm:text-[11px] text-purple-200/80 mt-1 uppercase tracking-widest font-medium">CREATE YOUR ADMINISTRATIVE ACCESS ACCOUNT</p>
                </div>

                <!-- Added form ID for JS validation interception -->
                <form method="POST" id="regForm" enctype="multipart/form-data" novalidate class="space-y-4">
                    
                    <!-- Full Name -->
                    <div>
                        <div class="relative">
                            <input type="text" id="name" name="name" placeholder="Full Name" 
                                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                                   class="w-full px-4 py-3 rounded-full bg-white/10 border border-white/25 text-white placeholder-purple-200/60 focus:outline-none focus:ring-2 focus:ring-white focus:border-white transition-all text-sm shadow-inner pr-12">
                            <span class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-purple-200/80"><i class="bi bi-person text-lg"></i></span>
                        </div>
                    </div>

                    <!-- Gmail Address -->
                    <div>
                        <div class="relative">
                            <input type="text" id="gmail" name="gmail" placeholder="Gmail Address (name@example.com)" 
                                   value="<?php echo isset($_POST['gmail']) ? htmlspecialchars($_POST['gmail']) : ''; ?>"
                                   class="w-full px-4 py-3 rounded-full bg-white/10 border border-white/25 text-white placeholder-purple-200/60 focus:outline-none focus:ring-2 focus:ring-white focus:border-white transition-all text-sm shadow-inner pr-12">
                            <span class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-purple-200/80"><i class="bi bi-envelope text-lg"></i></span>
                        </div>
                    </div>

                    <!-- Drag and Drop Profile Image Upload -->
                    <div>
                        <label class="block text-[11px] font-bold text-purple-200 uppercase tracking-wider mb-1.5 ml-1">Profile Image</label>
                        <div id="dropzone" class="border-2 border-dashed border-white/30 rounded-2xl p-4 text-center bg-white/5 cursor-pointer transition-all flex flex-col items-center justify-center gap-1.5 hover:border-white hover:bg-white/10 shadow-inner">
                            <i class="bi bi-cloud-arrow-up-fill text-2xl text-purple-300"></i>
                            <p class="text-xs sm:text-sm font-bold text-purple-100 drop-text">Drag profile image here or <span class="text-white underline">browse</span></p>
                            <p class="text-[10px] text-purple-300/70">JPG, JPEG, PNG, GIF (Max: 10MB)</p>
                            <input type="file" id="profile_image" name="profile_image" accept="image/*" class="hidden">
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <div class="relative">
                            <input type="password" id="password" name="password" placeholder="Account Password"
                                   class="w-full px-4 py-3 rounded-full bg-white/10 border border-white/25 text-white placeholder-purple-200/60 focus:outline-none focus:ring-2 focus:ring-white focus:border-white transition-all text-sm shadow-inner pr-12">
                            <span class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-purple-200/80"><i class="bi bi-lock text-lg"></i></span>
                        </div>
                        <!-- Real-time Password Guide Element -->
                        <div id="password-strength-text" class="text-[11px] mt-1.5 ml-3 font-medium text-purple-200/80 text-start">
                            Min. 8 characters, 1 Uppercase, 1 Lowercase, 1 Number, 1 Special Character.
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <div class="relative">
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Account Password"
                                   class="w-full px-4 py-3 rounded-full bg-white/10 border border-white/25 text-white placeholder-purple-200/60 focus:outline-none focus:ring-2 focus:ring-white focus:border-white transition-all text-sm shadow-inner pr-12">
                            <span class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-purple-200/80"><i class="bi bi-lock-fill text-lg"></i></span>
                        </div>
                    </div>
                    
                    <!-- Show Password Checkbox -->
                    <div class="flex items-center text-xs px-1 pt-0.5">
                        <label class="flex items-center text-purple-200/90 select-none cursor-pointer hover:text-white">
                            <input type="checkbox" id="showPasswordToggle" class="rounded border-white/40 bg-white/10 text-purple-600 focus:ring-white h-4 w-4 mr-2">
                            <span class="text-[11px]">Show Password</span>
                        </label>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="pt-2">
                        <button type="submit" name="register" 
                                class="w-full bg-gradient-to-r from-purple-600 via-purple-500 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-bold py-3.5 px-4 rounded-full shadow-lg shadow-purple-600/30 transition-all duration-200 transform active:scale-[0.98] text-sm uppercase tracking-wider border border-white/30 cursor-pointer">
                            Register Account
                        </button>
                    </div>
                </form> 
            </div>

            <div class="text-center text-[11px] text-purple-200/80 pt-6">
                <span>Already have an admin account? <a href="login.php" class="text-white font-semibold hover:underline">Login here</a></span>
            </div>
        </div>
    </div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // 1. PHP Backend SweetAlert Fallback (For handling database/session errors)
    <?php if(!empty($message)): ?>
        Swal.fire({
            icon: '<?php echo $messageType; ?>',
            title: '<?php echo ($messageType === "success") ? "Success!" : "Oops..."; ?>',
            text: '<?php echo $message; ?>',
            customClass: { confirmButton: 'bg-purple-600 hover:bg-purple-700 text-white px-5 py-2.5 rounded-xl font-bold border-0 cursor-pointer shadow-md text-xs' },
            buttonsStyling: false
        });
    <?php endif; ?>

    const form = document.getElementById('regForm');
    const nameInput = document.getElementById('name');
    const gmailInput = document.getElementById('gmail');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const strengthText = document.getElementById('password-strength-text');

    // Drag and Drop implementation for profile image
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('profile_image');
    const dropText = dropzone.querySelector('.drop-text');

    dropzone.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', (e) => syncFileVisualText(e.target.files[0]));

    dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('border-white', 'bg-white/20'); });
    ['dragleave', 'drop'].forEach(event => dropzone.addEventListener(event, () => dropzone.classList.remove('border-white', 'bg-white/20')));
    
    dropzone.addEventListener('drop', (e) => { 
        e.preventDefault(); 
        if(e.dataTransfer.files.length) { 
            fileInput.files = e.dataTransfer.files; 
            syncFileVisualText(e.dataTransfer.files[0]); 
        } 
    });

    function syncFileVisualText(file) {
        if(!file) return;
        dropText.innerHTML = `<span class="text-white font-bold flex items-center justify-center gap-1.5"><i class="bi bi-file-earmark-check-fill text-purple-300"></i> ${file.name}</span>`;
    }

    // 2. Real-time Password Strength Visual Indicator
    passwordInput.addEventListener('input', function() {
        const val = passwordInput.value;
        const strongRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;

        if (val === "") {
            strengthText.textContent = "Min. 8 characters, 1 Uppercase, 1 Lowercase, 1 Number, 1 Special Character.";
            strengthText.className = "text-[11px] mt-1.5 ml-3 font-medium text-purple-200/80 text-start";
        } else if (strongRegex.test(val)) {
            strengthText.textContent = "✓ Strong Password";
            strengthText.className = "text-[11px] mt-1.5 ml-3 font-semibold text-green-300 text-start";
        } else {
            strengthText.textContent = "✗ Weak Password (Missing standard requirements)";
            strengthText.className = "text-[11px] mt-1.5 ml-3 font-semibold text-rose-300 text-start";
        }
    });

    // 3. JAVASCRIPT VALIDATION (Triggers before form submission)
    form.addEventListener('submit', function(e) {
        const name = nameInput.value.trim();
        const gmail = gmailInput.value.trim();
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;

        // Regex for Email/Gmail format validation
        const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        
        // Regex for Strong Password policy:
        const strongPasswordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;

        // Check for Empty Fields
        if (name === "" || gmail === "" || password === "" || confirmPassword === "") {
            e.preventDefault(); 
            Swal.fire({
                icon: 'warning',
                title: 'Missing Information',
                text: 'All fields are required. Please fill them up.',
                customClass: { confirmButton: 'bg-purple-600 hover:bg-purple-700 text-white px-5 py-2.5 rounded-xl font-bold border-0 cursor-pointer shadow-md text-xs' },
                buttonsStyling: false
            });
            return;
        }

        // Validate Email Format
        if (!emailRegex.test(gmail)) {
            e.preventDefault(); 
            Swal.fire({
                icon: 'error',
                title: 'Invalid Email',
                text: 'Please enter a valid email or Gmail address (e.g., name@gmail.com).',
                customClass: { confirmButton: 'bg-purple-600 hover:bg-purple-700 text-white px-5 py-2.5 rounded-xl font-bold border-0 cursor-pointer shadow-md text-xs' },
                buttonsStyling: false
            });
            return;
        }

        // Validate Strong Password
        if (!strongPasswordRegex.test(password)) {
            e.preventDefault(); 
            Swal.fire({
                icon: 'error',
                title: 'Weak Password',
                text: 'Password must be at least 8 characters long and include an uppercase letter, a lowercase letter, a number, and a special character (e.g., @, $, !, %, *, ?, &).',
                customClass: { confirmButton: 'bg-purple-600 hover:bg-purple-700 text-white px-5 py-2.5 rounded-xl font-bold border-0 cursor-pointer shadow-md text-xs' },
                buttonsStyling: false
            });
            return;
        }

        // Check if Passwords Match
        if (password !== confirmPassword) {
            e.preventDefault(); 
            Swal.fire({
                icon: 'error',
                title: 'Password Mismatch',
                text: 'Passwords do not match. Please retype and try again.',
                customClass: { confirmButton: 'bg-purple-600 hover:bg-purple-700 text-white px-5 py-2.5 rounded-xl font-bold border-0 cursor-pointer shadow-md text-xs' },
                buttonsStyling: false
            });
            return;
        }
    });

    // SHOW/HIDE PASSWORD JAVASCRIPT
    const showPasswordToggle = document.getElementById('showPasswordToggle');
    showPasswordToggle.addEventListener('change', function() {
        const type = this.checked ? 'text' : 'password';
        passwordInput.type = type;
        confirmPasswordInput.type = type;
    });
});
</script>

</body>
</html>
<?php 
ob_end_flush(); 
?>