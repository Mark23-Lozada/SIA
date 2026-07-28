<?php
// Database connection - Siguraduhin na tugma ang settings mo
$db = mysqli_connect("localhost", "root", "", "pos");

if (!$db) {
    die("Connection Failed: " . mysqli_connect_error());
}

$alert = "";

if (isset($_POST['register_hr'])) {
    $gmail = trim($_POST['gmail']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // 1. Password Match Validation
    if ($password !== $confirm_password) {
        $alert = "Swal.fire('Error', 'Passwords do not match.', 'error');";
    } 
    // 2. Password Strength Validation
    else {
        $uppercase = preg_match('@[A-Z]@', $password);
        $lowercase = preg_match('@[a-z]@', $password);
        $number    = preg_match('@[0-9]@', $password);
        $specialChars = preg_match('@[^\w]@', $password);

        if(!$uppercase || !$lowercase || !$number || !$specialChars || strlen($password) < 8) {
            $alert = "Swal.fire('Error', 'Password must be at least 8 characters long, include uppercase, lowercase, numbers, and special characters.', 'error');";
        } 
        // 3. Email Validation
        elseif (!filter_var($gmail, FILTER_VALIDATE_EMAIL)) {
            $alert = "Swal.fire('Error', 'Invalid email format.', 'error');";
        } 
        // 4. Secure Hashing and Insertion
        else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO hr_accounts (gmail, password, role) VALUES (?, ?, 'hr')";
            $stmt = $db->prepare($query);
            $stmt->bind_param("ss", $gmail, $hashed_password);
            
            if ($stmt->execute()) {
                $alert = "Swal.fire({title: 'Success', text: 'HR Account registered!', icon: 'success'}).then(() => { window.location.href = 'login.php'; });";
            } else {
                $alert = "Swal.fire('Error', 'Registration failed. Email might already be taken.', 'error');";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register HR - PannaKoda</title>
    <!-- Siguraduhin na tama ang path ng tailwind.js mo -->
    <script src="../LIBRARIES/tailwind.js"></script>
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
</head>
<body class="bg-slate-100 flex items-center justify-center min-h-screen">

<div class="w-full max-w-md p-4">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden p-8">
        
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-slate-800">Add HR Account</h1>
            <p class="text-sm text-slate-500">Create a secure HR account</p>
        </div>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Gmail Address</label>
                <input type="email" name="gmail" required class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-orange-500 text-sm outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Password</label>
                <input type="password" name="password" id="password" required class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-orange-500 text-sm outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm_password" required class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-orange-500 text-sm outline-none">
            </div>
            
            <div class="flex items-center text-sm">
                <input type="checkbox" id="showPassword" onclick="togglePasswords()" class="rounded text-orange-500 mr-2 focus:ring-orange-500">
                <label for="showPassword" class="text-slate-600 cursor-pointer select-none">Show Passwords</label>
            </div>
            
            <button type="submit" name="register_hr" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-semibold py-3 rounded-xl shadow-lg transition-all active:scale-[0.99] mt-2">
                Create HR Account
            </button>
        </form>
    </div>
</div>

<script>
    function togglePasswords() {
        const pass = document.getElementById('password');
        const confirm = document.getElementById('confirm_password');
        const type = pass.type === 'password' ? 'text' : 'password';
        pass.type = type;
        confirm.type = type;
    }

    <?php if ($alert) echo $alert; ?>
</script>

</body>
</html>