<?php
ob_start();
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$current_role = strtolower($_SESSION['role']);
if ($current_role !== 'admin' && $current_role !== 'finance' && $current_role !== 'hr') {
    header("Location: login.php"); 
    exit();
}

$host = "localhost";
$user = "root"; 
$pass = ""; 
$dbname = "pos";

$current_page = basename($_SERVER['PHP_SELF']);

// ==========================================
// PHPMailer Setup & Professional Email Templates
// ==========================================
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../LIBRARIES/PHPMailer-master/src/Exception.php';
require '../LIBRARIES/PHPMailer-master/src/PHPMailer.php';
require '../LIBRARIES/PHPMailer-master/src/SMTP.php';

function sendCashAdvanceStatusEmail($recipient_email, $recipient_name, $subject, $message_body) {
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

        $mail->setFrom('markjosephlozada251@gmail.com', 'Pannakoda Executive Board');
        $mail->addAddress($recipient_email, $recipient_name);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; color: #334155;'>
                <h2 style='color: #212121; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-top: 0;'>Cash Advance Status Update</h2>
                <p>Dear <b>{$recipient_name}</b>,</p>
                <p>We hope this email finds you well.</p>
                <div style='background-color: #f8fafc; padding: 15px; border-radius: 6px; margin: 20px 0;'>
                    {$message_body}
                </div>
                <p>Should you have any inquiries regarding your cash advance request, please reach out to administration.</p>
                <br>
                <p>Best regards,</p>
                <p><b>Executive Management Team</b><br>Pannakoda</p>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// ==========================================
// Background Request Continuation Helper
// ==========================================
function respond_now_then_continue() {
    if (session_id()) {
        session_write_close();
    }

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
        return;
    }

    ignore_user_abort(true);
    header('Connection: close');
    $size = ob_get_length();
    if ($size !== false) {
        header('Content-Length: ' . $size);
    }
    @ob_end_flush();
    @flush();
}

// 1. FETCH SALARY ADVANCES VIA AJAX
if (isset($_GET['action']) && $_GET['action'] === 'fetch_advances') {
    header('Content-Type: application/json');
    $conn = new mysqli($host, $user, $pass, $dbname);
    if ($conn->connect_error) {
        echo json_encode([]);
        exit;
    }

    $query = "SELECT sa.*, e.full_name, e.department, e.email FROM salary_advances sa JOIN employees e ON sa.employee_id = e.id WHERE sa.status = 'Finance Approved' ORDER BY sa.created_at DESC";
    $result = $conn->query($query);
    $advances = [];
    
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $row['id'] = intval($row['id']);
            $row['amount'] = floatval($row['amount']);
            $advances[] = $row;
        }
    }
    echo json_encode($advances);
    $conn->close();
    exit;
}

// 2. PROCESS APPROVAL ACTION
if (isset($_GET['action']) && $_GET['action'] === 'approve' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id > 0) {
        $conn = new mysqli($host, $user, $pass, $dbname);

        $req_query = $conn->prepare("SELECT sa.amount, sa.reason, e.full_name, e.email FROM salary_advances sa JOIN employees e ON sa.employee_id = e.id WHERE sa.id = ?");
        $req_query->bind_param("i", $id);
        $req_query->execute();
        $req_res = $req_query->get_result()->fetch_assoc();
        $req_query->close();

        $stmt = $conn->prepare("UPDATE salary_advances SET status = 'Approved' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $conn->close();

        echo json_encode(['success' => true, 'message' => 'Cash advance request successfully approved!']);
        respond_now_then_continue();

        if ($req_res && !empty($req_res['email'])) {
            $subject = "Cash Advance Request Approved";
            $body = "We are pleased to inform you that your cash advance request amounting to <b>₱" . number_format($req_res['amount'], 2) . "</b> has been fully approved by the Administration.";
            sendCashAdvanceStatusEmail($req_res['email'], $req_res['full_name'], $subject, $body);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid Request ID.']);
    }
    exit;
}

// 3. PROCESS REJECTION ACTION
if (isset($_GET['action']) && $_GET['action'] === 'reject' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id > 0) {
        $conn = new mysqli($host, $user, $pass, $dbname);

        $req_query = $conn->prepare("SELECT sa.amount, sa.reason, e.full_name, e.email FROM salary_advances sa JOIN employees e ON sa.employee_id = e.id WHERE sa.id = ?");
        $req_query->bind_param("i", $id);
        $req_query->execute();
        $req_res = $req_query->get_result()->fetch_assoc();
        $req_query->close();

        $stmt = $conn->prepare("UPDATE salary_advances SET status = 'Rejected' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $conn->close();

        echo json_encode(['success' => true, 'message' => 'Cash advance request has been rejected.']);
        respond_now_then_continue();

        if ($req_res && !empty($req_res['email'])) {
            $subject = "Cash Advance Request Status Update: Rejected";
            $body = "We regret to inform you that your cash advance request amounting to <b>₱" . number_format($req_res['amount'], 2) . "</b> has been rejected by the Administration.";
            sendCashAdvanceStatusEmail($req_res['email'], $req_res['full_name'], $subject, $body);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid Request ID.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Salary Advance Approval</title>
    <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
    <script src="../LIBRARIES/tailwind.js"></script> 
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- AOS Library CSS & JS -->
    <link href="../LIBRARIES/AOS/aos.css" rel="stylesheet">
    <script src="../LIBRARIES/AOS/AOS.js"></script>

    <style>
        @keyframes floatSlow {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
        }
        .admin-card-glow {
            transition: all 0.4s ease-in-out;
        }
        .admin-card-glow:hover {
            box-shadow: 0 0 35px rgba(255, 107, 74, 0.25);
            border-color: rgba(255, 107, 74, 0.5);
        }
    </style>
</head>
<body class="bg-white text-slate-800 font-sans antialiased h-screen overflow-hidden">
  <div class="flex h-screen w-full overflow-hidden">
    <?php include 'sidebar.php'; ?>
    <div class="flex-1 h-screen overflow-y-auto p-8 bg-white min-w-0">
      
      <!-- Top Header & Live Philippine Time Clock Widget -->
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4" data-aos="fade-down" data-aos-duration="800">
        <div>
          <h1 class="text-2xl font-extrabold text-[#ff6b4a] tracking-tight">ADMIN CASH ADVANCE FINAL REVIEW</h1>
          <p class="text-sm text-slate-500 mt-1">Review forwarded salary advance requests from finance, execute final approvals, and manage records.</p>
        </div>
        <div class="flex items-center gap-3">
          <div class="flex items-center gap-1.5 bg-slate-50 px-3 py-2 rounded-2xl shadow-sm border border-slate-200/80">
            <i class="bi bi-clock text-[#ff6b4a]"></i> 
            <span class="text-slate-700 font-medium text-xs" id="phTimeDisplay">Loading PH Time...</span>
          </div>
          <div class="flex items-center gap-3 bg-orange-50/60 px-4 py-2 rounded-2xl shadow-sm border border-[#ff6b4a]/20 transition-transform duration-300 hover:scale-105">
            <span class="relative flex h-3 w-3">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
            </span>
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-700">System Active</span>
          </div>
        </div>
      </div>
      
      <!-- Search Bar -->
      <div class="mb-6 flex items-center gap-3 bg-slate-50 p-4 rounded-3xl shadow-sm border border-slate-200/80 admin-card-glow" data-aos="fade-up" data-aos-duration="900">
        <div class="relative flex-1">
          <span class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-slate-400"><i class="bi bi-search"></i></span>
          <input id="searchInput" type="text" class="w-full pl-11 pr-4 py-2.5 bg-white border border-slate-200 rounded-2xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#ff6b4a]/30 focus:border-[#ff6b4a] transition-all duration-300" placeholder="Search by employee name, department, or reason...">
        </div>
      </div>

      <!-- MAIN TABLE SECTION -->
      <div class="bg-slate-50 rounded-3xl shadow-sm border border-slate-200/80 p-6 mb-8 admin-card-glow" data-aos="fade-up" data-aos-duration="1000">
        <h3 class="text-md font-bold text-slate-800 mb-4 flex items-center gap-2">
          <div class="p-2 bg-[#ff6b4a]/10 text-[#ff6b4a] rounded-xl border border-[#ff6b4a]/20">
            <i class="bi bi-cash-stack"></i>
          </div> 
          Requests for Admin Approval (From Finance)
        </h3>
        <div class="table-responsive bg-white rounded-2xl overflow-hidden border border-slate-100">
          <table id="advanceTable" class="table table-hover align-middle mb-0 text-sm">
            <thead class="table-dark">
              <tr>
                <th class="py-3 px-4 bg-[#1a1010] text-white font-semibold border-0">Employee Name</th>
                <th class="py-3 px-4 bg-[#1a1010] text-white font-semibold border-0">Department</th>
                <th class="py-3 px-4 bg-[#1a1010] text-white font-semibold border-0 text-end">Amount</th>
                <th class="py-3 px-4 bg-[#1a1010] text-white font-semibold border-0">Reason</th>
                <th class="py-3 px-4 bg-[#1a1010] text-white font-semibold border-0 text-center">Admin Action</th>
              </tr>
            </thead>
            <tbody id="advanceBody"></tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  <script src="../LIBRARIES/bootstrap.bundle.min.js"></script>
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

    let allAdvances = [];
    const phpEndpoint = "<?php echo $current_page; ?>";

    async function loadAdvances() {
      try {
        const response = await fetch(`${phpEndpoint}?action=fetch_advances`);
        allAdvances = await response.json();
        renderTable();
      } catch (err) { 
        console.error("Failed to load cash advances:", err); 
      }
    }

    function renderTable() {
      const query = document.getElementById('searchInput').value.toLowerCase().trim();
      const tbody = document.getElementById('advanceBody');
      tbody.innerHTML = '';

      const filtered = allAdvances.filter(adv => {
        const name = String(adv.full_name || '').toLowerCase();
        const dept = String(adv.department || '').toLowerCase();
        const reason = String(adv.reason || '').toLowerCase();
        return name.includes(query) || dept.includes(query) || reason.includes(query);
      });

      if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-8 text-slate-400 italic">No salary advances waiting for admin approval.</td></tr>`;
        return;
      }

      filtered.forEach(adv => {
        const tr = document.createElement('tr');
        tr.className = "border-b border-slate-100 hover:bg-orange-50/20 transition-colors";
        tr.innerHTML = `
          <td class="py-3.5 px-4 font-bold text-slate-800">${escapeHtml(adv.full_name)}</td>
          <td class="py-3.5 px-4 text-slate-600">${escapeHtml(adv.department)}</td>
          <td class="py-3.5 px-4 text-end font-mono font-bold text-emerald-600">₱${Number(adv.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
          <td class="py-3.5 px-4 text-slate-600">${escapeHtml(adv.reason)}</td>
          <td class="py-3.5 px-4 text-center">
              <div class="flex items-center justify-center gap-2">
                  <button onclick="confirmAdminAction(${adv.id}, 'approve', '${escapeHtml(adv.full_name)}')" class="btn btn-sm btn-success py-1.5 px-3 text-xs font-semibold rounded-xl bg-emerald-600 hover:bg-emerald-700 border-0 shadow-sm">
                      <i class="bi bi-check-lg"></i> Final Approve
                  </button>
                  <button onclick="confirmAdminAction(${adv.id}, 'reject', '${escapeHtml(adv.full_name)}')" class="btn btn-sm btn-outline-danger py-1.5 px-2.5 text-xs font-semibold rounded-xl border-red-500 text-red-500 hover:bg-red-500 hover:text-white">
                      <i class="bi bi-x-lg"></i> Reject
                  </button>
              </div>
          </td>
        `;
        tbody.appendChild(tr);
      });
    }

    function escapeHtml(text) {
        if (!text) return '';
        return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    async function confirmAdminAction(id, action, employeeName) {
      const isApprove = action === 'approve';
      const actionName = isApprove ? 'Final Approval' : 'Reject Request';
      const confirmColor = isApprove ? '#10b981' : '#ef4444';

      const confirmResult = await Swal.fire({
        title: `${actionName}?`,
        text: `Are you sure you want to process the cash advance for ${employeeName}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: confirmColor,
        cancelButtonColor: '#64748b',
        confirmButtonText: `Yes, ${isApprove ? 'Approve' : 'Reject'}`,
        background: '#09090b',
        color: '#ffffff',
        customClass: { popup: 'rounded-3xl border border-[#ff6b4a]/30 shadow-2xl' }
      });

      if (!confirmResult.isConfirmed) return;

      Swal.fire({
        title: 'Processing Request...',
        text: 'Please wait a moment.',
        allowOutsideClick: false,
        background: '#09090b',
        color: '#ffffff',
        customClass: { popup: 'rounded-3xl border border-[#ff6b4a]/30 shadow-2xl backdrop-blur-xl' },
        didOpen: () => { Swal.showLoading(); }
      });

      const fd = new FormData();
      fd.append('id', id);

      try {
        const res = await fetch(`${phpEndpoint}?action=${action}`, { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
          await loadAdvances();
          Swal.fire({
            title: 'Success!',
            text: data.message,
            icon: 'success',
            timer: 1200,
            showConfirmButton: false,
            background: '#09090b',
            color: '#ffffff',
            customClass: { popup: 'rounded-3xl border border-[#ff6b4a]/30 shadow-2xl' }
          });
        } else {
          Swal.fire({
            title: 'Error!',
            text: data.message || 'Action failed.',
            icon: 'error',
            confirmButtonColor: '#ff6b4a',
            background: '#09090b',
            color: '#ffffff',
            customClass: { popup: 'rounded-3xl border border-[#ff6b4a]/30 shadow-2xl' }
          });
        }
      } catch (e) {
        Swal.fire({
          title: 'Error!',
          text: 'An unexpected connection error occurred.',
          icon: 'error',
          confirmButtonColor: '#ff6b4a',
          background: '#09090b',
          color: '#ffffff',
          customClass: { popup: 'rounded-3xl border border-[#ff6b4a]/30 shadow-2xl' }
        });
      }
    }

    document.getElementById('searchInput').addEventListener('input', renderTable);

    window.addEventListener('DOMContentLoaded', () => {
      loadAdvances();
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
                        popup: 'rounded-3xl border border-[#ff6b4a]/30 shadow-2xl backdrop-blur-xl'
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

    // Initialize AOS animations
    AOS.init({
        once: true,
        offset: 50,
        duration: 800,
    });
  </script>
</body>
</html>
<?php 
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>