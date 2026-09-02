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
        
        $mail->Username   = 'markjosephlozada251@gmail.com'; 
        $mail->Password   = 'rhjd rqed rhdh qkbd';    

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('markjosephlozada251@gmail.com', 'Pannakoda HR Department');
        $mail->addAddress($recipient_email, $recipient_name);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #cbd5e1; border-radius: 8px; color: #1e293b;'>
                <h2 style='color: #172554; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-top: 0;'>Pannakoda Recruitment Update</h2>
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

if (isset($_GET['action']) && $_GET['action'] == 'reject' && $_GET['id']) {
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

if (isset($_GET['action']) && $_GET['action'] == 'approve_hr' && $_GET['id']) {
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
    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        @media print {
            body * { visibility: hidden; }
            #resumeViewerFrame, #resumeViewerFrame * { visibility: visible; }
            #resumeModal { position: absolute; left: 0; top: 0; width: 100%; height: 100%; background: white !important; }
        }
        .applicant-row { cursor: pointer; transition: background-color 0.15s ease-in-out; }
        .applicant-row.selected { background-color: #eff6ff !important; border-left: 4px solid #172554; }

        /* Modern Customizations for Inline Flatpickr inside Modal */
        .flatpickr-calendar.inline {
            background: transparent !important;
            box-shadow: none !important;
            border: none !important;
            width: 100% !important;
            max-width: 100% !important;
        }
        .flatpickr-months {
            padding: 0 4px 8px 4px !important;
        }
        .flatpickr-current-month {
            font-size: 110% !important;
            font-weight: 700 !important;
            color: #1e293b !important;
        }
        .flatpickr-day.selected {
            background: #172554 !important;
            border-color: #172554 !important;
            border-radius: 8px !important;
        }
        .flatpickr-day.today {
            border-color: #172554 !important;
        }
        .flatpickr-day:hover {
            background: #dbeafe !important;
            border-radius: 8px !important;
        }
    </style>
</head>
<body class="bg-slate-50 font-sans antialiased h-screen overflow-hidden">
    <div class="flex h-screen w-full overflow-hidden">
        <?php include 'sidebar.php'; ?>
        <div class="flex-1 h-screen overflow-y-auto p-8 bg-slate-50 min-w-0">
            <div class="max-w-6xl mx-auto">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h1 class="text-2xl font-extrabold text-amber-500 tracking-tight">Recruitment Screening Desk</h1>
                        <p class="text-sm text-slate-500">Click a row to select an applicant, then use the toolbar action buttons below.</p>
                    </div>
                    <span class="bg-amber-500 text-white text-xs px-3 py-1.5 rounded-lg font-bold shadow-sm">HR Workspace</span>
                </div>

                <!-- Action Toolbar Buttons Below -->
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm mb-6 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-2">
                        <div class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse"></div>
                        <span id="selectedApplicantText" class="text-xs font-bold text-slate-600 uppercase tracking-wider">No applicant selected</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" id="btnSetInterview" onclick="triggerSetInterview()" disabled class="px-4 py-2 bg-amber-500 hover:bg-blue-900 disabled:bg-slate-200 disabled:text-slate-400 disabled:cursor-not-allowed text-white font-semibold rounded-xl text-xs transition-all shadow-sm flex items-center gap-1.5">
                            <i class="bi bi-calendar-event"></i> Set Interview
                        </button>
                        <button type="button" id="btnApprove" onclick="triggerApprove()" disabled class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-slate-200 disabled:text-slate-400 disabled:cursor-not-allowed text-white font-semibold rounded-xl text-xs transition-all shadow-sm flex items-center gap-1.5">
                            <i class="bi bi-check-circle"></i> Approve
                        </button>
                        <button type="button" id="btnReject" onclick="triggerReject()" disabled class="px-4 py-2 bg-rose-600 hover:bg-rose-700 disabled:bg-slate-200 disabled:text-slate-400 disabled:cursor-not-allowed text-white font-semibold rounded-xl text-xs transition-all shadow-sm flex items-center gap-1.5">
                            <i class="bi bi-x-circle"></i> Reject
                        </button>
                    </div>
                </div>

                <!-- Clean Table -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/70 text-xs font-bold uppercase tracking-wider text-slate-500">
                                <th class="p-4">Applicant Profile</th>
                                <th class="p-4">Contact Info</th>
                                <th class="p-4">Resume</th>
                                <th class="p-4">Pipeline Status</th>
                                <th class="p-4">Assigned Initial Schedule</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-sm">
                            <?php if($applicants->num_rows == 0): ?>
                                <tr>
                                    <td colspan="5" class="p-8 text-center text-slate-400">No applicants currently active in your HR pipeline view.</td>
                                </tr>
                            <?php endif; ?>
                            <?php while($row = $applicants->fetch_assoc()): ?>
                                <tr class="applicant-row hover:bg-slate-50/50 transition-colors" 
                                    onclick="selectApplicant(this, <?= $row['id'] ?>, '<?= htmlspecialchars($row['full_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['status'], ENT_QUOTES) ?>')">
                                    <td class="p-4 font-bold text-slate-800"><?= htmlspecialchars($row['full_name']) ?></td>
                                    <td class="p-4">
                                        <div class="text-xs text-slate-700 font-medium"><?= htmlspecialchars($row['email']) ?></div>
                                        <div class="text-[11px] text-slate-400 mt-0.5"><?= htmlspecialchars($row['phone']) ?></div>
                                    </td>
                                    <td class="p-4" onclick="event.stopPropagation();">
                                        <?php if (!empty($row['resume_path'])): ?>
                                            <button onclick="openResumeModal('<?= htmlspecialchars($row['resume_path'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['full_name'], ENT_QUOTES) ?>')" class="inline-flex items-center gap-2 px-3 py-2 bg-amber-500 hover:bg-blue-900 text-white rounded-lg text-xs font-semibold border-0 cursor-pointer shadow-sm">Resume</button>
                                        <?php else: ?>
                                            <span class="text-slate-400 text-xs">No Resume</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 text-xs font-medium text-slate-600"><?= htmlspecialchars($row['status']) ?></td>
                                    <td class="p-4 text-xs text-slate-600 font-medium">
                                        <?= $row['interview_date'] ? date('M d, Y - h:i A', strtotime($row['interview_date'])) : '—' ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Resume Viewer Modal -->
    <div id="resumeModal" class="hidden fixed inset-0 bg-slate-900/70 backdrop-blur-sm flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl w-full max-w-4xl shadow-2xl overflow-hidden flex flex-col h-[85vh]">
            <div class="flex justify-between items-center px-6 py-4 bg-slate-900 text-white">
                <h3 id="resumeModalTitle" class="text-base font-bold">Applicant Resume</h3>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="printResume()" class="px-3 py-1.5 bg-amber-500 hover:bg-blue-900 text-white rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all shadow-sm"><i class="bi bi-printer-fill"></i> Print</button>
                    <button type="button" onclick="closeModal('resumeModal')" class="text-slate-300 hover:text-white text-lg font-bold px-2"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div class="flex-1 bg-slate-100 p-2 overflow-hidden">
                <iframe id="resumeViewerFrame" src="" class="w-full h-full rounded-lg border-0 bg-white"></iframe>
            </div>
        </div>
    </div>

    <!-- Modern HR Modal (Inline Calendar Look) -->
    <div id="hrModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
        <div class="bg-white border border-slate-100 p-6 rounded-3xl w-full max-w-md shadow-2xl">
            <h3 class="text-xl font-bold text-slate-900 mb-0.5">Select Date</h3>
            <p id="modalApplicantName" class="text-xs text-slate-400 mb-4"></p>
            
            <form action="applicant.php?action=schedule_hr" method="POST" class="space-y-4">
                <input type="hidden" name="id" id="hr_id">
                
                <!-- Inline Calendar Container -->
                <div class="bg-slate-50/70 p-3 rounded-2xl border border-slate-100">
                    <input type="text" name="interview_date" id="hr_date_inline" required class="hidden">
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="button" onclick="closeModal('hrModal')" class="flex-1 py-3 text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl font-semibold transition-all">Cancel</button>
                    <button type="submit" class="flex-1 py-3 text-xs bg-amber-500 hover:bg-blue-900 text-white rounded-xl font-bold shadow-lg shadow-amber-500/20 transition-all">Confirm</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modern Final Modal (Inline Calendar Look) -->
    <div id="finalModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
        <div class="bg-white border border-slate-100 p-6 rounded-3xl w-full max-w-md shadow-2xl">
            <h3 class="text-xl font-bold text-amber-500 mb-0.5">Select Final Date</h3>
            <p id="modalFinalApplicantName" class="text-xs text-slate-400 mb-4"></p>
            
            <form action="applicant.php?action=schedule_final" method="POST" class="space-y-4">
                <input type="hidden" name="id" id="final_id">
                
                <!-- Inline Calendar Container -->
                <div class="bg-slate-50/70 p-3 rounded-2xl border border-slate-100">
                    <input type="text" name="final_interview_date" id="final_date_inline" required class="hidden">
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="button" onclick="closeModal('finalModal')" class="flex-1 py-3 text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl font-semibold transition-all">Cancel</button>
                    <button type="submit" class="flex-1 py-3 text-xs bg-amber-500 hover:bg-blue-900 text-white rounded-xl font-bold shadow-lg shadow-amber-500/20 transition-all">Confirm</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
    <script>
        // Initialize Inline Flatpickr instances
        flatpickr("#hr_date_inline", {
            inline: true,
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            minDate: new Date().fp_incr(3),
            time_24hr: false
        });

        flatpickr("#final_date_inline", {
            inline: true,
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            minDate: new Date(),
            time_24hr: false
        });

        let selectedId = null;
        let selectedName = '';
        let selectedStatus = '';

        function selectApplicant(rowElement, id, name, status) {
            document.querySelectorAll('.applicant-row').forEach(row => {
                row.classList.remove('selected');
            });
            rowElement.classList.add('selected');
            
            selectedId = id;
            selectedName = name;
            selectedStatus = status;

            document.getElementById('selectedApplicantText').innerText = "Selected: " + name + " (" + status + ")";
            
            const btnSet = document.getElementById('btnSetInterview');
            const btnApprove = document.getElementById('btnApprove');
            const btnReject = document.getElementById('btnReject');

            btnReject.disabled = false;

            if (status === 'Pending') {
                btnSet.disabled = false;
                btnSet.innerHTML = '<i class="bi bi-calendar-event"></i> Set HR Interview';
                btnApprove.disabled = true;
            } else if (status === 'HR Interview Set') {
                btnSet.disabled = true;
                btnApprove.disabled = false;
                btnApprove.innerHTML = '<i class="bi bi-check-circle"></i> Approve HR Interview';
            } else if (status === 'HR Approved') {
                btnSet.disabled = false;
                btnSet.innerHTML = '<i class="bi bi-calendar-plus"></i> Set Final Interview';
                btnApprove.disabled = true;
            } else {
                btnSet.disabled = true;
                btnApprove.disabled = true;
            }
        }

        function triggerSetInterview() {
            if (!selectedId) return;
            if (selectedStatus === 'Pending') {
                openHRModal(selectedId, selectedName);
            } else if (selectedStatus === 'HR Approved') {
                openFinalModal(selectedId, selectedName);
            }
        }

        function triggerApprove() {
            if (!selectedId) return;
            if (selectedStatus === 'HR Interview Set') {
                Swal.fire({
                    title: 'Approve Applicant?',
                    text: "Approve this applicant after interview?",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#16a34a',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Yes, approve'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = "applicant.php?action=approve_hr&id=" + selectedId;
                    }
                });
            }
        }

        function triggerReject() {
            if (!selectedId) return;
            Swal.fire({
                title: 'Reject Applicant?',
                text: "Are you sure you want to reject and drop this entry?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, reject'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "applicant.php?action=reject&id=" + selectedId;
                }
            });
        }

        function openResumeModal(resumePath, applicantName) {
            document.getElementById('resumeModalTitle').innerText = "Resume Preview: " + applicantName;
            document.getElementById('resumeViewerFrame').src = resumePath;
            document.getElementById('resumeModal').classList.remove('hidden');
        }

        function printResume() {
            const iframe = document.getElementById('resumeViewerFrame');
            if (iframe && iframe.contentWindow) {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
            }
        }

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
            if (modalId === 'resumeModal') {
                document.getElementById('resumeViewerFrame').src = '';
            }
        }
    </script>
</body>
</html>