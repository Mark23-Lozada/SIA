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
    <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
</head>
<body class="d-flex align-items-center min-vh-100" style="background-color: whitesmoke;">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div style="background: white; border: 2px solid orange;" class="card p-4">
                <div class="card-body">

                    <div class="text-center mb-4">
                        <h1 style="color:orange;" class="fw-bold m-0">Register Admin</h1>
                    </div>

                    <!-- Added form ID for JS validation interception -->
                    <form method="POST" id="regForm" enctype="multipart/form-data" novalidate>
                        <div class="form-floating mb-3">
                            <input type="text" id="name" name="name" class="form-control" placeholder="Full Name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                            <label class="text-secondary">Full Name</label>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="text" id="gmail" name="gmail" class="form-control" placeholder="name@example.com" value="<?php echo isset($_POST['gmail']) ? htmlspecialchars($_POST['gmail']) : ''; ?>">
                            <label class="text-secondary">Gmail Address</label>
                        </div>

                        <div class="mb-3">
                            <label for="profile_image" class="form-label text-secondary small fw-bold">Profile Image</label>
                            <input type="file" id="profile_image" name="profile_image" class="form-control" accept="image/*">
                        </div>

                        <div class="form-floating mb-1">
                            <input type="password" id="password" name="password" class="form-control" placeholder="Password">
                            <label class="text-secondary">Account Password</label>
                        </div>
                        
                        <!-- Real-time Password Guide Element -->
                        <div id="password-strength-text" class="small mb-3 fw-semibold text-muted text-start" style="font-size: 0.8rem;">
                            Password requirements: Min. 8 characters, 1 Uppercase, 1 Lowercase, 1 Number, 1 Special Character.
                        </div>

                        <div class="form-floating mb-2">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirm Password">
                            <label class="text-secondary">Confirm Account Password</label>
                        </div>
                        
                        <div class="form-check mb-4 text-start">
                            <input class="form-check-input" type="checkbox" id="showPasswordToggle">
                            <label class="form-check-label text-secondary small" style="cursor: pointer;" for="showPasswordToggle">
                                Show Password
                            </label>
                        </div>
                        
                        <button type="submit" name="register" class="btn btn-dark btn-lg w-100 fw-bold shadow-sm" style="background-color: orange; border:none;">
                            Register Account
                        </button>
                    </form> 
                         
                </div>
            </div>
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
            confirmButtonColor: 'orange'
        });
    <?php endif; ?>

    const form = document.getElementById('regForm');
    const nameInput = document.getElementById('name');
    const gmailInput = document.getElementById('gmail');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const strengthText = document.getElementById('password-strength-text');

    // 2. Real-time Password Strength Visual Indicator
    passwordInput.addEventListener('input', function() {
        const val = passwordInput.value;
        const strongRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;

        if (val === "") {
            strengthText.textContent = "Password requirements: Min. 8 characters, 1 Uppercase, 1 Lowercase, 1 Number, 1 Special Character.";
            strengthText.className = "small mb-3 fw-semibold text-muted text-start";
        } else if (strongRegex.test(val)) {
            strengthText.textContent = "✓ Strong Password";
            strengthText.className = "small mb-3 fw-semibold text-success text-start";
        } else {
            strengthText.textContent = "✗ Weak Password (Missing standard requirements listed above)";
            strengthText.className = "small mb-3 fw-semibold text-danger text-start";
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
            e.preventDefault(); // Blocks form submission to the server
            Swal.fire({
                icon: 'warning',
                title: 'Missing Information',
                text: 'All fields are required. Please fill them up.',
                confirmButtonColor: 'orange'
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
                confirmButtonColor: 'orange'
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
                confirmButtonColor: 'orange'
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
                confirmButtonColor: 'orange'
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
