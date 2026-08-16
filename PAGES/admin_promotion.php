<?php
ob_start();
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

$current_page = basename($_SERVER['PHP_SELF']);

// ==========================================
// PHPMailer Setup & Professional Email Templates
// ==========================================
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../LIBRARIES/PHPMailer-master/src/Exception.php';
require '../LIBRARIES/PHPMailer-master/src/PHPMailer.php';
require '../LIBRARIES/PHPMailer-master/src/SMTP.php';

function sendPromotionStatusEmail($recipient_email, $recipient_name, $subject, $message_body) {
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
                <h2 style='color: #212121; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-top: 0;'>Promotion & Salary Adjustment Status Update</h2>
                <p>Dear <b>{$recipient_name}</b>,</p>
                <p>We hope this email finds you well.</p>
                <div style='background-color: #f8fafc; padding: 15px; border-radius: 6px; margin: 20px 0;'>
                    {$message_body}
                </div>
                <p>Should you have any inquiries regarding your promotion status, please reach out to HR or administration.</p>
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

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    if (isset($_GET['action']) || $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(["success" => false, "message" => "Database Connection Failed: " . $conn->connect_error]);
        exit;
    }
    die("Database Connection Failed: " . $conn->connect_error);
}

// HANDLE ADMIN APPROVAL / REJECTION ACTION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_admin_promotion') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    $request_id = intval($_POST['request_id'] ?? 0);
    $decision = $conn->real_escape_string($_POST['decision'] ?? ''); 

    if ($request_id <= 0 || !in_array($decision, ['Approved', 'Rejected'])) {
        echo json_encode(["success" => false, "message" => "Invalid parameters provided."]);
        exit;
    }

    $reqQuery = "SELECT pr.*, e.full_name, e.department, e.email FROM promotion_requests pr LEFT JOIN employees e ON pr.employee_id = e.id WHERE pr.id = $request_id LIMIT 1";
    $reqResult = $conn->query($reqQuery);

    if (!$reqResult || $reqResult->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Promotion request not found."]);
        exit;
    }

    $promoData = $reqResult->fetch_assoc();
    $employee_id = intval($promoData['employee_id']);
    $employee_name = $promoData['full_name'] ?? 'Unknown Employee';
    $employee_email = $promoData['email'] ?? '';
    $department = $promoData['department'] ?? 'Management';
    $proposed_position = $conn->real_escape_string($promoData['proposed_position']);
    $new_salary = floatval($promoData['new_salary']);

    if ($decision === 'Approved') {
        $conn->begin_transaction();

        try {
            // 1. Update employee position_title and salary[cite: 1]
            $updateEmp = $conn->prepare("UPDATE employees SET position_title = ?, salary = ? WHERE id = ?");
            $updateEmp->bind_param("sdi", $proposed_position, $new_salary, $employee_id);
            $updateEmp->execute();
            $updateEmp->close();

            // 2. Update promotion request status
            $updateReq = $conn->prepare("UPDATE promotion_requests SET status = 'Approved', admin_status = 'Approved' WHERE id = ?");
            $updateReq->bind_param("i", $request_id);
            $updateReq->execute();
            $updateReq->close();

            // 3. Record transaction to budget_requests
            $transId = "PROMO-" . $request_id;
            $transTitle = "Promotion & Salary Adjustment: " . $proposed_position;
            $insertTrans = $conn->prepare("INSERT INTO budget_requests (request_id, title, requested_by, department, amount, status, created_at) VALUES (?, ?, ?, ?, ?, 'Approved', NOW())");
            $insertTrans->bind_param("ssssd", $transId, $transTitle, $employee_name, $department, $new_salary);
            $insertTrans->execute();
            $insertTrans->close();
            
            $conn->commit();
            echo json_encode(["success" => true, "message" => "Promotion approved successfully! Employee salary/position updated and recorded in transactions."]);
            
            respond_now_then_continue();

            if (!empty($employee_email)) {
                $subject = "Promotion & Salary Adjustment Approved";
                $body = "We are thrilled to inform you that your promotion request to <b>{$proposed_position}</b> with a new salary of <b>₱" . number_format($new_salary, 2) . "</b> has been fully approved by Executive Administration.";
                sendPromotionStatusEmail($employee_email, $employee_name, $subject, $body);
            }
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(["success" => false, "message" => "Transaction failed: " . $e->getMessage()]);
        }
    } else {
        $updateReq = $conn->prepare("UPDATE promotion_requests SET status = 'Rejected', admin_status = 'Rejected' WHERE id = ?");
        $updateReq->bind_param("i", $request_id);
        $success = $updateReq->execute();
        $updateReq->close();

        if ($success) {
            echo json_encode(["success" => true, "message" => "Promotion request has been rejected."]);
            
            respond_now_then_continue();

            if (!empty($employee_email)) {
                $subject = "Promotion Request Status Update: Rejected";
                $body = "We regret to inform you that your promotion request to <b>{$proposed_position}</b> has been rejected by Executive Administration.";
                sendPromotionStatusEmail($employee_email, $employee_name, $subject, $body);
            }
        } else {
            echo json_encode(["success" => false, "message" => "Failed to update request status."]);
        }
    }

    $conn->close();
    exit;
}

// FETCH PENDING ADMIN PROMOTION REQUESTS ENDPOINT
if (isset($_GET['action']) && $_GET['action'] === 'fetch_admin_promotions') {
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $query = "SELECT pr.*, 
                         COALESCE(e.full_name, 'Unknown Employee') as full_name, 
                         COALESCE(e.department, 'Unassigned') as department, 
                         COALESCE(e.salary, 0) as current_salary,
                         COALESCE(e.position_title, 'Staff') as current_position 
                  FROM promotion_requests pr
                  LEFT JOIN employees e ON pr.employee_id = e.id 
                  WHERE pr.finance_status = 'Approved' 
                    AND (pr.status = 'Pending Admin' OR pr.admin_status = 'Pending')
                  ORDER BY pr.id DESC";
        $result = $conn->query($query);
        
        $requests = [];
        while ($row = $result->fetch_assoc()) {
            $row['id'] = intval($row['id']);
            $row['current_salary'] = floatval($row['current_salary'] ?? 0);
            $row['new_salary'] = floatval($row['new_salary'] ?? 0);
            $requests[] = $row;
        }

        echo json_encode($requests);
    } catch (Exception $e) {
        echo json_encode(["error" => true, "message" => $e->getMessage()]);
    }

    $conn->close();
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Promotion & Salary Approval Portal</title>
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
      <div class="max-w-6xl mx-auto space-y-8">
        
        <!-- Top Header & Live Philippine Time Clock Widget -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4" data-aos="fade-down" data-aos-duration="800">
          <div>
            <h1 class="text-2xl font-extrabold text-[#ff6b4a] tracking-tight">PROMOTION & SALARY APPROVALS</h1>
            <p class="text-sm text-slate-500 mt-1">Review and give final executive approval for promotion requests forwarded by Finance.</p>
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
        <div class="flex items-center gap-3 bg-slate-50 p-4 rounded-3xl shadow-sm border border-slate-200/80 admin-card-glow" data-aos="fade-up" data-aos-duration="900">
          <div class="relative flex-1">
            <span class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-slate-400"><i class="bi bi-search"></i></span>
            <input id="searchInput" type="text" class="w-full pl-11 pr-4 py-2.5 bg-white border border-slate-200 rounded-2xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#ff6b4a]/30 focus:border-[#ff6b4a] transition-all duration-300" placeholder="Search by employee name, department, roles, or reason...">
          </div>
        </div>

        <!-- MAIN TABLE SECTION -->
        <div class="bg-slate-50 rounded-3xl shadow-sm border border-slate-200/80 p-6 mb-8 admin-card-glow" data-aos="fade-up" data-aos-duration="1000">
          <h2 class="text-md font-bold text-slate-800 mb-4 flex items-center gap-2">
              <div class="p-2 bg-[#ff6b4a]/10 text-[#ff6b4a] rounded-xl border border-[#ff6b4a]/20">
                  <i class="bi bi-award-fill"></i>
              </div> 
              Pending Promotion Requests Awaiting Admin Action
          </h2>
          <div class="table-responsive bg-white rounded-2xl overflow-hidden border border-slate-100">
            <table class="table table-hover align-middle mb-0 text-sm">
              <thead class="table-dark">
                <tr>
                  <th class="py-3 px-4 bg-[#1a1010] text-white font-semibold border-0">Employee Name</th>
                  <th class="py-3 px-4 bg-[#1a1010] text-white font-semibold border-0">Department</th>
                  <th class="py-3 px-4 bg-[#1a1010] text-white font-semibold border-0">Current Role & Salary</th>
                  <th class="py-3 px-4 bg-[#1a1010] text-white font-semibold border-0">Proposed Role & New Salary</th>
                  <th class="py-3 px-4 bg-[#1a1010] text-white font-semibold border-0">Reason</th>
                  <th class="py-3 px-4 bg-[#1a1010] text-white font-semibold border-0 text-center">Action</th>
                </tr>
              </thead>
              <tbody id="adminPromotionTableBody"></tbody>
            </table>
          </div>
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

    let adminPromotionRequests = [];
    const adminEndpointUrl = '<?php echo $current_page; ?>';

    async function loadAdminPromotionRequests() {
      try {
        const res = await fetch(`${adminEndpointUrl}?action=fetch_admin_promotions`);
        const rawText = await res.text();
        
        try {
          adminPromotionRequests = JSON.parse(rawText);
          renderAdminTable();
        } catch (jsonErr) {
          console.error("JSON Parse Error:", jsonErr);
          adminPromotionRequests = [];
          renderAdminTable();
        }
      } catch (err) {
        console.error("Failed to load requests:", err);
      }
    }

    function renderAdminTable() {
      const query = document.getElementById('searchInput').value.toLowerCase().trim();
      const tbody = document.getElementById('adminPromotionTableBody');
      tbody.innerHTML = '';

      const filtered = adminPromotionRequests.filter(req => {
        const name = String(req.full_name || '').toLowerCase();
        const dept = String(req.department || '').toLowerCase();
        const currentPos = String(req.current_position || '').toLowerCase();
        const proposedPos = String(req.proposed_position || '').toLowerCase();
        const reason = String(req.reason_for_promotion || '').toLowerCase();
        return name.includes(query) || dept.includes(query) || currentPos.includes(query) || proposedPos.includes(query) || reason.includes(query);
      });

      if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-8 text-slate-400 italic">No pending promotion requests requiring admin approval.</td></tr>`;
        return;
      }

      filtered.forEach(req => {
        const tr = document.createElement('tr');
        tr.className = "border-b border-slate-100 hover:bg-orange-50/20 transition-colors";
        tr.innerHTML = `
          <td class="py-3.5 px-4 font-bold text-slate-800">${escapeHtml(req.full_name || 'N/A')}</td>
          <td class="py-3.5 px-4 text-slate-600">${escapeHtml(req.department || 'Unassigned')}</td>
          <td class="py-3.5 px-4">
            <span class="text-xs text-slate-700 font-semibold block">${escapeHtml(req.current_position || 'Staff')}</span>
            <span class="text-xs font-mono font-bold text-slate-900">₱${Number(req.current_salary || 0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})}</span>
          </td>
          <td class="py-3.5 px-4">
            <span class="text-xs text-amber-700 font-semibold block">${escapeHtml(req.proposed_position || 'N/A')}</span>
            <span class="text-xs font-mono font-bold text-emerald-600">₱${Number(req.new_salary || 0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})}</span>
          </td>
          <td class="py-3.5 px-4 text-xs text-slate-600 max-w-xs truncate" title="${escapeHtml(req.reason_for_promotion || '')}">
            ${escapeHtml(req.reason_for_promotion || 'No reason provided')}
          </td>
          <td class="py-3.5 px-4 text-center">
            <div class="flex items-center justify-center gap-2">
              <button onclick="processAdminDecision(${req.id}, 'Approved')" class="btn btn-sm btn-success py-1.5 px-3 text-xs font-semibold rounded-xl bg-emerald-600 hover:bg-emerald-700 border-0 shadow-sm flex items-center gap-1">
                <i class="bi bi-check-lg"></i> Approve
              </button>
              <button onclick="processAdminDecision(${req.id}, 'Rejected')" class="btn btn-sm btn-outline-danger py-1.5 px-2.5 text-xs font-semibold rounded-xl border-red-500 text-red-500 hover:bg-red-500 hover:text-white flex items-center gap-1">
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

    async function processAdminDecision(requestId, decision) {
      const isApprove = decision === 'Approved';
      const confirmColor = isApprove ? '#10b981' : '#ef4444';

      const confirmResult = await Swal.fire({
        title: `${decision} Promotion Request?`,
        text: `Are you sure you want to ${decision.toLowerCase()} this promotion? This will update employee salary, position, and record the transaction.`,
        icon: isApprove ? 'question' : 'warning',
        showCancelButton: true,
        confirmButtonColor: confirmColor,
        cancelButtonColor: '#64748b',
        confirmButtonText: `Yes, ${decision}`,
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

      const formData = new URLSearchParams();
      formData.append('action', 'process_admin_promotion');
      formData.append('request_id', requestId);
      formData.append('decision', decision);

      try {
        const res = await fixedFetch(adminEndpointUrl, formData);
        if (res.success) {
          await loadAdminPromotionRequests();
          Swal.fire({
            title: 'Success!',
            text: res.message,
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
            text: res.message || 'Action failed.',
            icon: 'error',
            confirmButtonColor: '#ff6b4a',
            background: '#09090b',
            color: '#ffffff',
            customClass: { popup: 'rounded-3xl border border-[#ff6b4a]/30 shadow-2xl' }
          });
        }
      } catch (err) {
        Swal.fire({
          title: 'Error!',
          text: 'Failed to communicate with server.',
          icon: 'error',
          confirmButtonColor: '#ff6b4a',
          background: '#09090b',
          color: '#ffffff',
          customClass: { popup: 'rounded-3xl border border-[#ff6b4a]/30 shadow-2xl' }
        });
      }
    }

    async function fixedFetch(url, formData) {
      const response = await fetch(url, { method: 'POST', body: formData });
      return await response.json();
    }

    document.getElementById('searchInput').addEventListener('input', renderAdminTable);

    window.addEventListener('DOMContentLoaded', loadAdminPromotionRequests);

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