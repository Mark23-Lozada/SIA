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

// Set system time zone to Philippines
date_default_timezone_set('Asia/Manila');

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "pos";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// REMOVED STRICT SESSION ROLE CHECK FOR INSTANT ACCESS

// Filter para sa petsa (Default ay ang kasalukuyang araw ngayon)
$filter_date = isset($_GET['search_date']) ? $_GET['search_date'] : date('Y-m-d');

// Query para pagsamahin ang info ng Employee, Attendance, at Overtime Logs
$query = "SELECT 
            e.id AS emp_raw_id,
            e.full_name,
            e.department,
            a.date AS log_date,
            a.time_in,
            a.time_out,
            a.status_in,
            a.status_out,
            o.ot_hours,
            o.overtime_type
          FROM attendance a
          JOIN employees e ON a.employee_id = e.id
          LEFT JOIN overtime_logs o ON a.id = o.attendance_id
          WHERE a.date = ?
          ORDER BY a.time_in ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $filter_date);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Master Attendance Logs & Sidebar</title>
  
  <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
  <script src="../LIBRARIES/tailwind.js"></script>
  <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
</head>
<body class="bg-[whitesmoke] font-sans antialiased h-screen overflow-hidden">

  <div class="flex h-screen w-full overflow-hidden">
    
  <?php include 'sidebar.php'; ?>
    <div class="flex-1 h-screen overflow-y-auto p-8">
      
      <div class="max-w-7xl mx-auto bg-white p-6 rounded-2xl border border-slate-200 shadow-md">
          
          <div class="flex flex-col md:flex-row justify-between items-start md:items-center border-b pb-5 mb-6 gap-4">
              <div>
                  <h1 class="text-2xl font-black text-slate-800 tracking-tight flex items-center gap-2">
                      <i class="bi bi-calendar-check-fill text-orange-500"></i> Master Attendance & Overtime Logs
                  </h1>
                  <p class="text-xs text-slate-400">Monitor daily log entries, late marks, and system auto-closures.</p>
              </div>
              
              <form method="GET" class="flex items-center gap-2 bg-slate-100 p-2 rounded-xl border">
                  <label class="text-xs font-bold text-slate-500 px-2 uppercase">Select Date:</label>
                  <input type="date" name="search_date" value="<?= htmlspecialchars($filter_date) ?>" class="bg-white border rounded-lg px-3 py-1.5 text-xs font-bold text-slate-700 focus:outline-orange-500">
                  <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold text-xs px-4 py-1.5 rounded-lg transition-all">
                      Filter
                  </button>
              </form>
          </div>

          <div class="overflow-x-auto rounded-xl border border-slate-200 shadow-inner">
              <table class="w-full text-left border-collapse">
                  <thead>
                      <tr class="bg-slate-900 text-white/90 text-xs font-bold uppercase tracking-wider">
                          <th class="p-4">Emp ID</th>
                          <th class="p-4">Employee Name</th>
                          <th class="p-4">Department</th>
                          <th class="p-4">Time In Log</th>
                          <th class="p-4">Time Out Log</th>
                          <th class="p-4">In Status</th>
                          <th class="p-4">Out Status</th>
                          <th class="p-4 text-center">OT Credited</th>
                      </tr>
                  </thead>
                  <tbody class="divide-y text-xs text-slate-700">
                      <?php if ($result->num_rows == 0): ?>
                          <tr>
                              <td colspan="8" class="p-8 text-center text-sm font-medium text-slate-400 bg-slate-50/50">
                                  <i class="bi bi-folder-x text-2xl block mb-2 text-slate-300"></i>
                                  No active system logs recorded for <?= date('F d, Y', strtotime($filter_date)) ?>.
                              </td>
                          </tr>
                      <?php else: ?>
                          <?php while ($row = $result->fetch_assoc()): 
                              $status_in = $row['status_in'] ?? '';
                              $status_out = $row['status_out'] ?? '';

                              $in_badge = ($status_in === 'On Time') ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-rose-100 text-rose-700 border-rose-200';

                              if (empty($status_out)) {
                                  $out_badge = 'bg-slate-100 text-slate-400 border-slate-200 italic';
                                  $display_out_status = 'Active Shift';
                              } elseif ($status_out === 'Normal') {
                                  $out_badge = 'bg-blue-100 text-blue-700 border-blue-200';
                                  $display_out_status = $status_out;
                              } elseif (strpos((string)$status_out, 'Overtime') !== false) { 
                                  $out_badge = 'bg-purple-100 text-purple-700 border-purple-200 font-bold';
                                  $display_out_status = $status_out;
                              } else {
                                  $out_badge = 'bg-amber-100 text-amber-700 border-amber-200';
                                  $display_out_status = $status_out;
                              }
                              
                              $formatted_id = "EMP-" . str_pad($row['emp_raw_id'], 4, "0", STR_PAD_LEFT);
                          ?>
                              <tr class="hover:bg-slate-50/80 transition-colors">
                                  <td class="p-4 font-mono font-bold text-indigo-600"><?= $formatted_id ?></td>
                                  <td class="p-4 font-bold text-slate-900"><?= htmlspecialchars($row['full_name']) ?></td>
                                  <td class="p-4">
                                      <span class="bg-slate-100 px-2 py-0.5 rounded border font-medium text-slate-600"><?= htmlspecialchars($row['department']) ?></span>
                                  </td>
                                  <td class="p-4 font-mono font-semibold text-slate-600">
                                      <?= $row['time_in'] ? date('h:i A', strtotime($row['time_in'])) : '—' ?>
                                  </td>
                                  <td class="p-4 font-mono font-semibold text-slate-600">
                                      <?= $row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : '—' ?>
                                  </td>
                                  <td class="p-4">
                                      <span class="px-2.5 py-1 text-[10px] uppercase font-bold rounded-full border <?= $in_badge ?>">
                                          <?= htmlspecialchars($row['status_in'] ?? '—') ?>
                                      </span>
                                  </td>
                                  <td class="p-4">
                                      <span class="px-2.5 py-1 text-[10px] uppercase font-bold rounded-full border <?= $out_badge ?>">
                                          <?= htmlspecialchars($display_out_status) ?>
                                      </span>
                                  </td>
                                  <td class="p-4 text-center">
                                      <?php if ($row['ot_hours'] !== null): ?>
                                          <div class="inline-flex flex-col items-center">
                                              <span class="bg-purple-600 text-white font-mono font-bold px-2 py-0.5 rounded text-[11px] shadow-sm">
                                                  +<?= number_format($row['ot_hours'], 2) ?> hrs
                                              </span>
                                              <span class="text-[9px] text-purple-400 font-medium block mt-0.5"><?= htmlspecialchars($row['overtime_type']) ?></span>
                                          </div>
                                      <?php else: ?>
                                          <span class="text-slate-300 font-mono">—</span>
                                      <?php endif; ?>
                                  </td>
                              </tr>
                          <?php endwhile; ?>
                      <?php endif; ?>
                  </tbody>
              </table>
          </div>

          <div class="mt-4 flex flex-wrap gap-4 text-[11px] text-slate-400 font-medium bg-slate-50 p-3 rounded-xl border">
              <span class="flex items-center gap-1"><i class="bi bi-circle-fill text-emerald-500 text-[8px]"></i> On Time (7:00am - 7:30am)</span>
              <span class="flex items-center gap-1"><i class="bi bi-circle-fill text-rose-500 text-[8px]"></i> Late (After 7:30am)</span>
              <span class="flex items-center gap-1"><i class="bi bi-circle-fill text-amber-500 text-[8px]"></i> Early Out (Before 5:00pm)</span>
              <span class="flex items-center gap-1"><i class="bi bi-circle-fill text-blue-500 text-[8px]"></i> Normal Out (5:00pm - 6:00pm)</span>
              <span class="flex items-center gap-1"><i class="bi bi-circle-fill text-purple-500 text-[8px]"></i> Overtime Rules Applied</span>
          </div>

      </div>

    </div>

  </div>

  <script>
    document.addEventListener("DOMContentLoaded", function () {
        highlightActiveSidebarLink();
        
        // Logout SweetAlert2
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
    });

    // Sidebar Highlighting Function
    function highlightActiveSidebarLink() {
        const currentPath = window.location.pathname.toLowerCase();
        const navLinks = document.querySelectorAll(".nav-link");
        
        navLinks.forEach(link => {
            const linkPath = link.getAttribute("href").toLowerCase();
            if (linkPath && currentPath.endsWith(linkPath)) {
                link.classList.remove("text-white/80", "hover:bg-white/10", "hover:text-white");
                link.classList.add("bg-[#FF8C00]", "text-white", "shadow-md", "font-semibold");
            }
        });
    }
    // Magre-refresh ang buong pahina tuwing 30 segundo
setInterval(function() {
    location.reload();
}, 30000); // 30000 milliseconds = 30 seconds
  </script>
  
  <script src="../LIBRARIES/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php 
$stmt->close();
$conn->close();
?>