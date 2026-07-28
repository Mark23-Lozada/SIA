<?php
session_start();

// 1. Siguraduhin muna na may naka-login na user
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

// 2. Kunin ang role at gawing lowercase para iwas sa error sa malaki/maliit na titik
$current_role = strtolower($_SESSION['role']);

// 3. Harangin kung HINDI siya admin at HINDI rin hr
if ($current_role !== 'admin' && $current_role !== 'hr') {
    header("Location: login.php"); // Pwedeng palitan ng unauthorized.php
    exit();
}


$host = "localhost";
$user = "root";
$pass = "";
$dbname = "pos";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// ==========================================
// 2. BACKEND API ACTIONS
// ==========================================

// A. MULA SA CLIENT FORM (INSERT DATA & UPLOAD RESUME)
if (isset($_GET['action']) && $_GET['action'] == 'encode' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');
    
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    
    $target_dir = "uploads/";
    if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
    
    $file_name = time() . "_" . basename($_FILES["resume"]["name"]);
    $target_file = $target_dir . $file_name;
    
    if (move_uploaded_file($_FILES["resume"]["tmp_name"], $target_file)) {
        $stmt = $conn->prepare("INSERT INTO applicants (full_name, email, phone, resume_path, status) VALUES (?, ?, ?, ?, 'Pending')");
        $stmt->bind_param("ssss", $full_name, $email, $phone, $target_file);
        
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Application successfully saved!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Database error: " . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to upload resume."]);
    }
    exit;
}
// Halimbawa sa Action C

// B. REJECT O BURAHIN ANG DATA
if (isset($_GET['action']) && $_GET['action'] == 'reject' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    $res = $conn->query("SELECT resume_path FROM applicants WHERE id = $id");
    if($row = $res->fetch_assoc()) {
        if(!empty($row['resume_path']) && file_exists($row['resume_path'])) { unlink($row['resume_path']); }
    }
    
    $conn->query("DELETE FROM applicants WHERE id = $id");
    header("Location: applicant.php");
    exit;
}

// C. SET HR INTERVIEW SCHEDULE
if (isset($_GET['action']) && $_GET['action'] == 'schedule_hr' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['id']);
    $interview_date = $_POST['interview_date'];
    // Halimbawa sa Action C
$min_date = date('Y-m-d', strtotime('+3 days'));
if (date('Y-m-d', strtotime($_POST['interview_date'])) < $min_date) {
    // I-handle ang error (e.g., redirect with error message)
    exit("Invalid date selected.");
}
    $stmt = $conn->prepare("UPDATE applicants SET status = 'HR Interview Set', interview_date = ? WHERE id = ?");
    $stmt->bind_param("si", $interview_date, $id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: applicant.php");
    exit;
}

// D. APPROVE HR INTERVIEW
if (isset($_GET['action']) && $_GET['action'] == 'approve_hr' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $conn->query("UPDATE applicants SET status = 'HR Approved' WHERE id = $id");
    header("Location: applicant.php");
    exit;
}

// E. SET FINAL INTERVIEW (Mawawala na ang data kapag nalagyan ng Executive Schedule)
if (isset($_GET['action']) && $_GET['action'] == 'schedule_final' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['id']);
    $final_interview_date = $_POST['final_interview_date'];
    
    $stmt = $conn->prepare("UPDATE applicants SET status = 'Final Interview Set', final_interview_date = ? WHERE id = ?");
    $stmt->bind_param("si", $final_interview_date, $id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: applicant.php");
    exit;
}

// Ipakita lang sa HR ang Pending, HR Interview Set, at HR Approved. Mawawala na rito kapag nasend na sa Admin.
// Idinagdag ang 'Interview Set' sa listahan
$applicants = $conn->query("SELECT * FROM applicants WHERE status IN ('Pending', 'HR Interview Set', 'HR Approved', 'Interview Set') ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicant Management - HR Node</title>
    <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
    <script src="../LIBRARIES/tailwind.js"></script>
</head>
<body class="bg-slate-50 font-sans antialiased h-screen overflow-hidden">

    <div class="flex h-screen w-full overflow-hidden">

    <?php include 'sidebar.php'; ?>

        <div class="flex-1 h-screen overflow-y-auto p-8 bg-slate-50 min-w-0">
            <div class="max-w-6xl mx-auto">
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h1 class="text-2xl font-extrabold text-slate-800 tracking-tight">Recruitment Screening Desk</h1>
                        <p class="text-sm text-slate-500">Handle initial screenings and progress applicants down the active hiring funnel.</p>
                    </div>
                    <span class="bg-orange-500 text-white text-xs px-3 py-1.5 rounded-lg font-bold shadow-sm">HR Workspace</span>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/70 text-xs font-bold uppercase tracking-wider text-slate-500">
                                <th class="p-4">Applicant Profile</th>
                                <th class="p-4">Contact Info</th>
                                <th class="p-4">Resume</th>
                                <th class="p-4">Pipeline Status</th>
                                <th class="p-4">Assigned Initial Schedule</th>
                                <th class="p-4 text-right">Routing Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-sm">
                            <?php if($applicants->num_rows == 0): ?>
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-slate-400">No applicants currently active in your HR pipeline view.</td>
                                </tr>
                            <?php endif; ?>
                            <?php while($row = $applicants->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="p-4 font-bold text-slate-800"><?= htmlspecialchars($row['full_name']) ?></td>
                                    <td class="p-4">
                                        <div class="text-xs text-slate-700 font-medium"><?= htmlspecialchars($row['email']) ?></div>
                                        <div class="text-[11px] text-slate-400 mt-0.5"><?= htmlspecialchars($row['phone']) ?></div>
                                    </td>
                                    <td class="p-4">
                                        <?php if (!empty($row['resume_path'])): ?>
                                            <a href="<?= htmlspecialchars($row['resume_path']) ?>"
                                            target="_blank"
                                            class="inline-flex items-center gap-2 px-3 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-xs font-semibold no-underline">
                                                <i class="bi bi-file-earmark-pdf-fill"></i>
                                                Resume
                                            </a>
                                        <?php else: ?>
                                            <span class="text-slate-400 text-xs">No Resume</span>
                                        <?php endif; ?>
                                    </td>
                                   <td class="p-4 text-right space-x-1">
                                        <?php if($row['status'] == 'Pending'): ?>
                                            <p onclick="openHRModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['full_name']) ?>')" class="...">Set HR Interview</p>
                                        
                                        <?php elseif($row['status'] == 'HR Interview Set' || $row['status'] == 'Interview Set'): ?>
                                            <a href="applicant.php?action=approve_hr&id=<?= $row['id'] ?>" ...>Approve</a>
                                            
                                        <?php elseif($row['status'] == 'HR Approved'): ?>
                                            <p onclick="openFinalModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['full_name']) ?>')" class="...">Set Final Interview</p>
                                        <?php endif; ?>
                                        
                                        <p href="applicant.php?action=reject&id=<?= $row['id'] ?>" ...>Reject</p>
                                    </td>
                                    <td class="p-4 text-xs text-slate-600 font-medium">
                                        <?= $row['interview_date'] ? date('M d, Y - h:i A', strtotime($row['interview_date'])) : '—' ?>
                                    </td>
                                    <td class="p-4 text-right space-x-1">
                                        <?php 
                                            $isTimeArrived = !empty($row['interview_date']) && strtotime($row['interview_date']) <= time();
                                        ?>
                                        <?php if($row['status'] == 'Pending'): ?>
                                            <button onclick="openHRModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['full_name']) ?>')" class="px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white font-semibold rounded-lg text-xs transition-all shadow-sm">
                                                Set HR Interview
                                            </button>
                                        <?php elseif($row['status'] == 'HR Interview Set'): ?>
                                            <button <?= $isTimeArrived ? 'onclick="openHRModal(' . $row['id'] . ', \'' . htmlspecialchars($row['full_name'], ENT_QUOTES) . '\')"' : 'disabled' ?>
                                                class="px-3 py-1.5 font-semibold rounded-lg text-xs transition-all shadow-sm <?= $isTimeArrived ? 'bg-orange-500 hover:bg-orange-600 text-white cursor-pointer' : 'bg-gray-300 text-gray-500 cursor-not-allowed opacity-60' ?>"
                                                href="applicant.php?action=approve_hr&id=<?= $row['id'] ?>" onclick="return confirm('Approve this applicant after interview?')" class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg text-xs transition-all shadow-sm no-underline inline-block">
                                                Approve
                                        </button>
                                        <?php elseif($row['status'] == 'HR Approved'): ?>
                                            <button <?= $isTimeArrived ? 'onclick="openHRModal(' . $row['id'] . ', \'' . htmlspecialchars($row['full_name'], ENT_QUOTES) . '\')"' : 'disabled' ?>
                                                class="px-3 py-1.5 font-semibold rounded-lg text-xs transition-all shadow-sm <?= $isTimeArrived ? 'bg-indigo-600 hover:bg-indigo-700 text-white cursor-pointer' : 'bg-gray-300 text-gray-500 cursor-not-allowed opacity-60' ?>">
                                                Set Final Interview
                                            </button>
                                        <?php endif; ?>
                                        
                                        <a href="applicant.php?action=reject&id=<?= $row['id'] ?>" onclick="return confirm('Reject and drop this entry?')" class="px-2.5 py-1.5 text-slate-500 hover:bg-rose-50 hover:text-rose-600 font-semibold rounded-lg text-xs transition-all no-underline">
                                            Reject
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="hrModal" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4 z-50">
        <div class="bg-white border border-slate-200 p-6 rounded-2xl w-full max-w-sm shadow-xl">
            <h3 class="text-base font-bold text-slate-900 mb-1">Setup Initial HR Call</h3>
            <p id="modalApplicantName" class="text-xs text-slate-500 mb-4"></p>
           <form action="applicant.php?action=schedule_hr" method="POST" class="space-y-4" onsubmit="return validateDate(event, 'hr_date')">
                <input type="hidden" name="id" id="hr_id">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Target Assessment Schedule</label>
                   <input type="datetime-local" name="interview_date" id="hr_date" required 
       class="w-full ...">
                </div>
                <div class="flex justify-end space-x-2 pt-2">
                    <button type="button" onclick="closeModal('hrModal')" class="px-4 py-2 text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-medium">Dismiss</button>
                    <button type="submit" class="px-4 py-2 text-xs bg-orange-500 hover:bg-orange-600 rounded-xl font-semibold text-white shadow-sm">Commit Schedule</button>
                </div>
            </form>
        </div>
    </div>

    <div id="finalModal" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4 z-50">
        <div class="bg-white border border-slate-200 p-6 rounded-2xl w-full max-w-sm shadow-xl">
            <h3 class="text-base font-bold text-indigo-600 mb-1">Dispatch to Final Executive Board</h3>
            <p id="modalFinalApplicantName" class="text-xs text-slate-500 mb-4"></p>
           <form action="applicant.php?action=schedule_final" method="POST" class="space-y-4" onsubmit="return validateDate(event, 'final_date')">
                <input type="hidden" name="id" id="final_id">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Proposed Executive Interview Slot</label>
                 <input type="datetime-local" name="final_interview_date" id="final_date" required 
       class="w-full ...">
                </div>
                <div class="flex justify-end space-x-2 pt-2">
                    <button type="button" onclick="closeModal('finalModal')" class="px-4 py-2 text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-medium">Cancel</button>
                    <button type="submit" class="px-4 py-2 text-xs bg-indigo-600 hover:bg-indigo-700 rounded-xl font-semibold text-white shadow-sm">Dispatch to Admin</button>
                </div>
            </form>
        </div>
    </div>
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>

    <script>
        
                
document.addEventListener("DOMContentLoaded", function () {

    const currentPage = window.location.pathname.split("/").pop();

    document.querySelectorAll(".nav-link").forEach(link => {

        if (link.getAttribute("href") === currentPage) {

            link.classList.add("bg-[#FF8C00]", "text-white", "shadow-lg");

        }

    });

});
function validateDate(event, inputId) {
    const dateInput = document.getElementById(inputId).value;
    if (!dateInput) return true; 

    const selectedDate = new Date(dateInput);
    const now = new Date();
    
    // Alisin ang butal na oras sa "now"
    now.setHours(0, 0, 0, 0);

    // I-compute ang 3 days from now
    const minDate = new Date(now);
    minDate.setDate(now.getDate() + 3);

    // I-block kapag past date o wala pang 3 days
    if (selectedDate < minDate) {
        event.preventDefault(); // Pumipigil sa paglipat ng page
        
        Swal.fire({
            icon: 'error',
            title: 'Invalid Schedule',
            text: 'The interview must be scheduled at least 3 days from today.',
            confirmButtonColor: '#FF8C00',
            confirmButtonText: 'Understood'
        });
        
        return false;
    }
    
    return true; 
}
document.addEventListener("DOMContentLoaded", function () {
    // Pag-highlight ng active menu
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll(".sidebar-link");
    
    navLinks.forEach(link => {
        const linkPath = link.getAttribute("href");
        if (linkPath && currentPath.endsWith(linkPath)) {
            link.classList.remove("text-white/80", "hover:bg-white/10", "hover:text-white", "text-inherit");
            link.classList.add("bg-[#FF8C00]", "text-white", "shadow-md", "font-semibold");
        }
    });

    // SweetAlert2 para sa Logout Confirmation
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault(); 
            Swal.fire({
                title: 'Log out',
                text: "Are you sure you want to Log out",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#FF8C00', 
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes',
                cancelButtonText: 'Cancel',
                background: '#ffffff',
                color: '#212121'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "logout.php"; 
                }
            });
        });
    }
});
  
        function openHRModal(id, name) {
            document.getElementById('hr_id').value = id;
            document.getElementById('modalApplicantName').innerText = "Applicant Profile: " + name;
            document.getElementById('hrModal').classList.remove('hidden');
        }
        function openFinalModal(id, name) {
            document.getElementById('final_id').value = id;
            document.getElementById('modalFinalApplicantName').innerText = "Applicant Profile: " + name;
            document.getElementById('finalModal').classList.remove('hidden');
        }
        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }
        document.addEventListener("DOMContentLoaded", function() {
            const currentPath = window.location.pathname;
            const navLinks = document.querySelectorAll(".nav-link");
            navLinks.forEach(link => {
                const linkPath = link.getAttribute("href");
                if (linkPath && currentPath.endsWith(linkPath)) {
                    link.classList.add("bg-orange-500", "text-white", "shadow-sm");
                }
            });
        });
        // Magre-refresh ang buong pahina tuwing 30 segundo
setInterval(function() {
    location.reload();
}, 30000); // 30000 milliseconds = 30 seconds
    </script>
</body>
</html>