<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$current_role = strtolower($_SESSION['role']);
if ($current_role !== 'admin' && $current_role !== 'hr') {
    header("Location: login.php"); 
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
// PHPMailer Setup & Professional Email Templates
// ==========================================
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../LIBRARIES/PHPMailer-master/src/Exception.php';
require '../LIBRARIES/PHPMailer-master/src/PHPMailer.php';
require '../LIBRARIES/PHPMailer-master/src/SMTP.php';

function sendApplicantEmail($recipient_email, $recipient_name, $subject, $message_body) {
    if (empty($recipient_email) || !filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        
        // --- FIXED GMAIL AND APP PASSWORD ---
        $mail->Username   = 'markjosephlozada251@gmail.com'; 
        $mail->Password   = 'rhjd rqed rhdh qkbd';    
        // ------------------------------------

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('markjosephlozada251@gmail.com', 'Pannakoda HR Department');
        $mail->addAddress($recipient_email, $recipient_name);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; color: #334155;'>
                <h2 style='color: #FF8C00; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-top: 0;'>Pannakoda Recruitment Update</h2>
                <p>Dear <b>{$recipient_name}</b>,</p>
                <p>We hope this email finds you well.</p>
                <div style='background-color: #f8fafc; padding: 15px; border-radius: 6px; margin: 20px 0;'>
                    {$message_body}
                </div>
                <p>If you have any questions or require further assistance, please feel free to reach out to us by replying directly to this email.</p>
                <br>
                <p>Best regards,</p>
                <p><b>Human Resources Department</b><br>Pannakoda</p>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// ==========================================
// BACKEND API ACTIONS
// ==========================================

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

if (isset($_GET['action']) && $_GET['action'] == 'reject' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $res_get = $conn->query("SELECT full_name, email, resume_path FROM applicants WHERE id = $id")->fetch_assoc();
    if($res_get) {
        $subject = "Application Status Update - Pannakoda";
        $body = "Thank you for your interest in joining Pannakoda and for taking the time to go through our application process. After careful review, we regret to inform you that we will not be moving forward with your application at this time. We wish you the absolute best in your professional endeavors.";
        sendApplicantEmail($res_get['email'], $res_get['full_name'], $subject, $body);
        if(!empty($res_get['resume_path']) && file_exists($res_get['resume_path'])) { unlink($res_get['resume_path']); }
    }
    
    $conn->query("DELETE FROM applicants WHERE id = $id");
    header("Location: applicant.php");
    exit;
}

// SET HR INTERVIEW SCHEDULE
if (isset($_GET['action']) && $_GET['action'] == 'schedule_hr' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['id']);
    $interview_date = $_POST['interview_date'];
    
    $min_date = date('Y-m-d', strtotime('+3 days'));
    if (date('Y-m-d', strtotime($_POST['interview_date'])) < $min_date) {
        exit("Invalid date selected.");
    }

    $stmt_get = $conn->prepare("SELECT full_name, email FROM applicants WHERE id = ?");
    $stmt_get->bind_param("i", $id);
    $stmt_get->execute();
    $res_get = $stmt_get->get_result()->fetch_assoc();
    $stmt_get->close();

    if ($res_get) {
        $formatted_date = date('F d, Y - h:i A', strtotime($interview_date));
        $subject = "HR Interview Schedule Confirmation";
        $body = "We are pleased to invite you to an initial HR Interview to discuss your qualifications and background further.<br><br><b>Scheduled Date & Time:</b> {$formatted_date}<br><br>Please make sure to arrive or log in a few minutes prior to your scheduled time.";
        sendApplicantEmail($res_get['email'], $res_get['full_name'], $subject, $body);
    }

    $stmt = $conn->prepare("UPDATE applicants SET status = 'HR Interview Set', interview_date = ? WHERE id = ?");
    $stmt->bind_param("si", $interview_date, $id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: applicant.php");
    exit;
}

// APPROVE HR INTERVIEW
if (isset($_GET['action']) && $_GET['action'] == 'approve_hr' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    $res_get = $conn->query("SELECT full_name, email FROM applicants WHERE id = $id")->fetch_assoc();
    if ($res_get) {
        $subject = "HR Interview Result - Passed";
        $body = "Congratulations! We are delighted to inform you that you have successfully passed your initial HR Interview. Your application is now being endorsed to the next phase of our evaluation process.";
        sendApplicantEmail($res_get['email'], $res_get['full_name'], $subject, $body);
    }

    $conn->query("UPDATE applicants SET status = 'HR Approved' WHERE id = $id");
    header("Location: applicant.php");
    exit;
}

// SET FINAL INTERVIEW
if (isset($_GET['action']) && $_GET['action'] == 'schedule_final' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['id']);
    $final_interview_date = $_POST['final_interview_date'];
    
    $stmt_get = $conn->prepare("SELECT full_name, email FROM applicants WHERE id = ?");
    $stmt_get->bind_param("i", $id);
    $stmt_get->execute();
    $res_get = $stmt_get->get_result()->fetch_assoc();
    $stmt_get->close();

    if ($res_get) {
        $formatted_date = date('F d, Y - h:i A', strtotime($final_interview_date));
        $subject = "Final Executive Interview Schedule";
        $body = "You have been scheduled for your Final Executive Interview with our leadership board.<br><br><b>Final Interview Slot:</b> {$formatted_date}<br><br>We look forward to meeting with you.";
        sendApplicantEmail($res_get['email'], $res_get['full_name'], $subject, $body);
    }

    $stmt = $conn->prepare("UPDATE applicants SET status = 'Final Interview Set', final_interview_date = ? WHERE id = ?");
    $stmt->bind_param("si", $final_interview_date, $id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: applicant.php");
    exit;
}

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
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
                                            <a href="<?= htmlspecialchars($row['resume_path']) ?>" target="_blank" class="inline-flex items-center gap-2 px-3 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-xs font-semibold no-underline">Resume</a>
                                        <?php else: ?>
                                            <span class="text-slate-400 text-xs">No Resume</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 text-xs font-medium text-slate-600"><?= htmlspecialchars($row['status']) ?></td>
                                    <td class="p-4 text-xs text-slate-600 font-medium">
                                        <?= $row['interview_date'] ? date('M d, Y - h:i A', strtotime($row['interview_date'])) : '—' ?>
                                    </td>
                                    <td class="p-4 text-right space-x-1">
                                        <?php if($row['status'] == 'Pending'): ?>
                                            <button onclick="openHRModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['full_name']) ?>')" class="px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white font-semibold rounded-lg text-xs transition-all shadow-sm">Set HR Interview</button>
                                        <?php elseif($row['status'] == 'HR Interview Set'): ?>
                                            <a href="applicant.php?action=approve_hr&id=<?= $row['id'] ?>" onclick="return confirm('Approve this applicant after interview?')" class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg text-xs transition-all shadow-sm no-underline inline-block">Approve</a>
                                        <?php elseif($row['status'] == 'HR Approved'): ?>
                                            <button onclick="openFinalModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['full_name']) ?>')" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg text-xs transition-all shadow-sm">Set Final Interview</button>
                                        <?php endif; ?>
                                        <a href="applicant.php?action=reject&id=<?= $row['id'] ?>" onclick="return confirm('Reject and drop this entry?')" class="px-2.5 py-1.5 text-slate-500 hover:bg-rose-50 hover:text-rose-600 font-semibold rounded-lg text-xs transition-all no-underline">Reject</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- HR Modal -->
    <div id="hrModal" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4 z-50">
        <div class="bg-white border border-slate-200 p-6 rounded-2xl w-full max-w-sm shadow-xl">
            <h3 class="text-base font-bold text-slate-900 mb-1">Setup Initial HR Call</h3>
            <p id="modalApplicantName" class="text-xs text-slate-500 mb-4"></p>
            <form action="applicant.php?action=schedule_hr" method="POST" class="space-y-4" onsubmit="return validateDate(event, 'hr_date')">
                <input type="hidden" name="id" id="hr_id">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Target Assessment Schedule</label>
                    <input type="datetime-local" name="interview_date" id="hr_date" required class="w-full form-control text-sm">
                </div>
                <div class="flex justify-end space-x-2 pt-2">
                    <button type="button" onclick="closeModal('hrModal')" class="px-4 py-2 text-xs bg-slate-100 text-slate-700 rounded-xl font-medium">Dismiss</button>
                    <button type="submit" class="px-4 py-2 text-xs bg-orange-500 text-white rounded-xl font-semibold">Commit Schedule</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Final Modal -->
    <div id="finalModal" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4 z-50">
        <div class="bg-white border border-slate-200 p-6 rounded-2xl w-full max-w-sm shadow-xl">
            <h3 class="text-base font-bold text-indigo-600 mb-1">Dispatch to Final Executive Board</h3>
            <p id="modalFinalApplicantName" class="text-xs text-slate-500 mb-4"></p>
            <form action="applicant.php?action=schedule_final" method="POST" class="space-y-4">
                <input type="hidden" name="id" id="final_id">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Proposed Executive Interview Slot</label>
                    <input type="datetime-local" name="final_interview_date" id="final_date" required class="w-full form-control text-sm">
                </div>
                <div class="flex justify-end space-x-2 pt-2">
                    <button type="button" onclick="closeModal('finalModal')" class="px-4 py-2 text-xs bg-slate-100 text-slate-700 rounded-xl font-medium">Cancel</button>
                    <button type="submit" class="px-4 py-2 text-xs bg-indigo-600 text-white rounded-xl font-semibold">Dispatch</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
    <script>
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
        function validateDate(event, inputId) {
            const dateInput = document.getElementById(inputId).value;
            if (!dateInput) return true; 
            const selectedDate = new Date(dateInput);
            const now = new Date();
            now.setHours(0, 0, 0, 0);
            const minDate = new Date(now);
            minDate.setDate(now.getDate() + 3);

            if (selectedDate < minDate) {
                event.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Schedule',
                    text: 'The interview must be scheduled at least 3 days from today.',
                    confirmButtonColor: '#FF8C00'
                });
                return false;
            }
            return true; 
        }
    </script>
</body>
</html>