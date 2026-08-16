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

function sendBudgetStatusEmail($recipient_email, $recipient_name, $subject, $message_body) {
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
                <h2 style='color: #212121; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-top: 0;'>Budget Request Update</h2>
                <p>Dear <b>{$recipient_name}</b>,</p>
                <p>We hope this email finds you well.</p>
                <div style='background-color: #f8fafc; padding: 15px; border-radius: 6px; margin: 20px 0;'>
                    {$message_body}
                </div>
                <p>Should you have any inquiries regarding this budget request, please reach out to administration.</p>
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

// 1. FETCH BUDGET REQUESTS VIA AJAX
if (isset($_GET['action']) && $_GET['action'] === 'fetch_requests') {
    header('Content-Type: application/json');
    $conn = new mysqli($host, $user, $pass, $dbname);
    if ($conn->connect_error) {
        echo json_encode([]);
        exit;
    }

    $query = "SELECT * FROM budget_requests WHERE status = 'Approved by Finance' OR status LIKE '%Fully Approved%' OR status LIKE '%Rejected by Admin%' ORDER BY created_at DESC";
    $result = $conn->query($query);
    $requests = [];
    
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $row['id'] = intval($row['id']);
            $row['amount'] = floatval($row['amount']);
            $requests[] = $row;
        }
    }
    echo json_encode($requests);
    $conn->close();
    exit;
}

// 2. PROCESS APPROVAL ACTION
if (isset($_GET['action']) && $_GET['action'] === 'approve_request' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $req_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($req_id > 0) {
        $conn = new mysqli($host, $user, $pass, $dbname);

        $req_query = $conn->prepare("SELECT title, requested_by, email, amount FROM budget_requests WHERE id = ?");
        $req_query->bind_param("i", $req_id);
        $req_query->execute();
        $req_res = $req_query->get_result()->fetch_assoc();
        $req_query->close();
        
        if ($req_res) {
            $title = $req_res['title'];
            
            if (preg_match('/Restock\s+([0-9.]+)\s+\S+\s+of\s+(.+)/i', $title, $matches)) {
                $restock_qty = (float)$matches[1];
                $ingredient_name = trim($matches[2]);
                
                $update_stock = $conn->prepare("UPDATE ingredients SET stock = stock + ? WHERE ingredient_name = ?");
                $update_stock->bind_param("ds", $restock_qty, $ingredient_name);
                $update_stock->execute();
                $update_stock->close();
            }
        }

        $new_status = 'Fully Approved (Admin)';
        $update_stmt = $conn->prepare("UPDATE budget_requests SET status = ? WHERE id = ?");
        $update_stmt->bind_param("si", $new_status, $req_id);
        $update_stmt->execute();
        $update_stmt->close();
        $conn->close();

        echo json_encode(['success' => true, 'message' => 'Budget request successfully approved and inventory updated!']);
        respond_now_then_continue();

        if ($req_res && !empty($req_res['email'])) {
            $subject = "Budget Request Fully Approved";
            $body = "Your budget request (<b>ID: {$req_id}</b> - {$title}) amounting to <b>₱" . number_format($req_res['amount'], 2) . "</b> has been fully approved by the Administration, and inventory stock has been adjusted accordingly.";
            sendBudgetStatusEmail($req_res['email'], $req_res['requested_by'], $subject, $body);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid Request ID.']);
    }
    exit;
}

// 3. PROCESS REJECTION ACTION
if (isset($_GET['action']) && $_GET['action'] === 'reject_request' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $req_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($req_id > 0) {
        $conn = new mysqli($host, $user, $pass, $dbname);

        $req_query = $conn->prepare("SELECT title, requested_by, email, amount FROM budget_requests WHERE id = ?");
        $req_query->bind_param("i", $req_id);
        $req_query->execute();
        $req_res = $req_query->get_result()->fetch_assoc();
        $req_query->close();

        $new_status = 'Rejected by Admin';
        $update_stmt = $conn->prepare("UPDATE budget_requests SET status = ? WHERE id = ?");
        $update_stmt->bind_param("si", $new_status, $req_id);
        $update_stmt->execute();
        $update_stmt->close();
        $conn->close();

        echo json_encode(['success' => true, 'message' => 'Budget request has been rejected.']);
        respond_now_then_continue();

        if ($req_res && !empty($req_res['email'])) {
            $subject = "Budget Request Status Update: Rejected";
            $body = "We regret to inform you that your budget request (<b>ID: {$req_id}</b> - {${$req_res['title']}}) amounting to <b>₱" . number_format($req_res['amount'], 2) . "</b> has been rejected by the Administration.";
            sendBudgetStatusEmail($req_res['email'], $req_res['requested_by'], $subject, $body);
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
    <title>Admin - Final Budget Approval</title>
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
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 0 15px rgba(255, 107, 74, 0.15); }
            50% { box-shadow: 0 0 25px rgba(255, 107, 74, 0.35); }
        }
        .animate-float-1 { animation: floatSlow 4s ease-in-out infinite; }
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
          <h1 class="text-2xl font-extrabold text-[#ff6b4a] tracking-tight">ADMIN FINAL BUDGET APPROVAL</h1>
          <p class="text-sm text-slate-500 mt-1">Review forwarded budget requests from finance, execute inventory restock updates, and manage approvals.</p>
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
          <input id="searchInput" type="text" class="w-full pl-11 pr-4 py-2.5 bg-white border border-slate-200 rounded-2xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#ff6b4a]/30 focus:border-[#ff6b4a] transition-all duration-300" placeholder="Search requests by title, request ID, or requester...">
        </div>
      </div>

      <!-- MAIN TABLE SECTION -->
      <div class="bg-slate-50 rounded-3xl shadow-sm border border-slate-200/80 p-6 mb-8 admin-card-glow" data-aos="fade-up" data-aos-duration="1000">
        <h3 class="text-md font-bold text-slate-800 mb-4 flex items-center gap-2">
          <div class="p-2 bg-[#ff6b4a]/10 text-[#ff6b4a] rounded-xl border border-[#ff6b4a]/20">
            <i class="bi bi-wallet2"></i>
          </div> 
          Queue from Finance for Final Approval
        </h3>
        <div class="table-responsive bg-white rounded-2xl overflow-hidden border border-slate-100">
          <table id="budgetTable" class="table table-hover align-middle mb-0 text-sm">
            <thead class="table-dark">
              <tr>
                <th class="py-3 px-4 bg-[#1a1010] text-white font-semibold border-0">Request ID / Title</th>
                <th class="py-3 px-4 bg-[#1a1010] text-white font-semibold border-0">Requested By</th>
                <th class="py-3 px-4 bg-[#1a1010] text-white font-semibold border-0 text-end">Amount</th>
                <th class="py-3 px-4 bg-[#1a1010] text-white font-semibold border-0 text-center">Status</th>
                <th class="py-3 px-4 bg-[#1a1010] text-white font-semibold border-0 text-center">Actions</th>
              </tr>
            </thead>
            <tbody id="budgetBody"></tbody>
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

    let allRequests = [];
    const phpEndpoint = "<?php echo $current_page; ?>";

    async function loadRequests() {
      try {
        const response = await fetch(`${phpEndpoint}?action=fetch_requests`);
        allRequests = await response.json();
        renderTable();
      } catch (err) { 
        console.error("Failed to load budget requests:", err); 
      }
    }

    function renderTable() {
      const query = document.getElementById('searchInput').value.toLowerCase().trim();
      const tbody = document.getElementById('budgetBody');
      tbody.innerHTML = '';

      const filtered = allRequests.filter(req => {
        const idStr = String(req.request_id || req.id || '').toLowerCase();
        const title = String(req.title || '').toLowerCase();
        const requestedBy = String(req.requested_by || '').toLowerCase();
        return idStr.includes(query) || title.includes(query) || requestedBy.includes(query);
      });

      if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-8 text-slate-400 italic">No forwarded budget requests found.</td></tr>`;
        return;
      }

      filtered.forEach(req => {
        const status = (req.status || '').trim();
        const isApproved = status.includes('Fully Approved');
        
        let statusBadge = '';
        if (isApproved) {
            statusBadge = '<span class="bg-emerald-50 text-emerald-700 border-emerald-200 px-3 py-1 rounded-lg text-xs font-semibold border">Fully Approved (Admin)</span>';
        } else if (status.includes('Rejected')) {
            statusBadge = '<span class="bg-red-50 text-red-700 border-red-200 px-3 py-1 rounded-lg text-xs font-semibold border">Rejected by Admin</span>';
        } else {
            statusBadge = '<span class="bg-blue-50 text-blue-700 border-blue-200 px-3 py-1 rounded-lg text-xs font-semibold border">Approved by Finance</span>';
        }

        let actionContent = '';
        if (status === 'Approved by Finance') {
            actionContent = `
                <div class="flex items-center justify-center gap-2">
                    <button onclick="processAction(${req.id}, 'approve', '${(req.title || '').replace(/'/g, "\\'")}')" class="btn btn-sm btn-success py-1.5 px-3 text-xs font-semibold rounded-xl bg-emerald-600 hover:bg-emerald-700 border-0 shadow-sm">
                        <i class="bi bi-check-lg"></i> Approve & Deduct
                    </button>
                    <button onclick="processAction(${req.id}, 'reject', '${(req.title || '').replace(/'/g, "\\'")}')" class="btn btn-sm btn-outline-danger py-1.5 px-2.5 text-xs font-semibold rounded-xl border-red-500 text-red-500 hover:bg-red-500 hover:text-white">
                        <i class="bi bi-x-lg"></i> Reject
                    </button>
                </div>
            `;
        } else {
            actionContent = `<span class="text-xs text-slate-400 font-medium">Completed</span>`;
        }

        const tr = document.createElement('tr');
        tr.className = "border-b border-slate-100 hover:bg-orange-50/20 transition-colors";
        tr.innerHTML = `
          <td class="py-3.5 px-4">
              <div class="font-mono font-bold text-slate-800">${req.request_id || '#' + req.id}</div>
              <div class="text-xs text-slate-500">${req.title || ''}</div>
          </td>
          <td class="py-3.5 px-4 text-slate-600 font-medium">${req.requested_by || ''}</td>
          <td class="py-3.5 px-4 text-end font-bold text-slate-800">₱${Number(req.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
          <td class="py-3.5 px-4 text-center">${statusBadge}</td>
          <td class="py-3.5 px-4 text-center">${actionContent}</td>
        `;
        tbody.appendChild(tr);
      });
    }

    async function processAction(id, actionType, title) {
      const actionName = actionType === 'approve' ? 'Final Approve & Deduct' : 'Reject Request';
      const confirmColor = actionType === 'approve' ? '#10b981' : '#ef4444';

      const confirmResult = await Swal.fire({
        title: `${actionName}?`,
        text: `Are you sure you want to process request #${id}: "${title}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: confirmColor,
        cancelButtonColor: '#64748b',
        confirmButtonText: `Yes, ${actionType === 'approve' ? 'Approve' : 'Reject'}`,
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

      const endpointAction = actionType === 'approve' ? 'approve_request' : 'reject_request';

      try {
        const res = await fetch(`${phpEndpoint}?action=${endpointAction}`, { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
          await loadRequests();
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
      loadRequests();
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