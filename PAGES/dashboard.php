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
    header("Location: login.php"); 
    exit();
}

// 4. Kumonekta sa Database
$conn = new mysqli("localhost", "root", "", "pos");

if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Database Connection Failed"]));
}

// 5. API ENDPOINT: Kung may request para sa live data, ibalik ang JSON data at ihinto agad ang script
$action = $_GET['action'] ?? '';
if ($action === 'fetch_dashboard_data') {
    header('Content-Type: application/json');
    
    $stats = [
        'total_employees' => $conn->query("SELECT COUNT(*) FROM applicants WHERE LOWER(TRIM(status)) = 'employee'")->fetch_row()[0],
        'active_applicants' => $conn->query("SELECT COUNT(*) FROM applicants WHERE LOWER(TRIM(status)) IN ('applied', 'for interview', 'interview set', 'hr approved', 'passed', 'for offer', 'accepted')")->fetch_row()[0],
        'for_interview' => $conn->query("SELECT COUNT(*) FROM applicants WHERE LOWER(TRIM(status)) IN ('for interview', 'interview set')")->fetch_row()[0],
        'under_review' => $conn->query("SELECT COUNT(*) FROM applicants WHERE LOWER(TRIM(status)) IN ('hr approved', 'passed', 'for offer', 'accepted')")->fetch_row()[0]
    ];

    $apps = [];
    $query = $conn->query("SELECT * FROM applicants WHERE LOWER(TRIM(status)) NOT IN ('employee', 'rejected', 'failed', 'declined') ORDER BY id DESC");
    while($row = $query->fetch_assoc()) {
        $apps[] = [
            'full_name' => htmlspecialchars($row['full_name'] ?? $row['name'] ?? 'No Name'),
            'email' => htmlspecialchars($row['email'] ?? 'No Email'),
            'status' => htmlspecialchars($row['status'] ?? 'Pending')
        ];
    }

    echo json_encode(['stats' => $stats, 'applicants' => $apps]);
    $conn->close();
    exit(); 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Responsive Dashboard with Active Sidebar</title>
  
  <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
  <script src="../LIBRARIES/tailwind.js"></script>
  <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
</head>
<body class="bg-[whitesmoke] font-sans antialiased h-screen overflow-hidden">

  <div class="flex h-screen w-full overflow-hidden">
    
   <?php include 'sidebar.php'; ?>

    <div class="flex-1 h-screen overflow-y-auto p-8 bg-slate-100 min-w-0">

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
          <div class="flex flex-col items-center justify-center bg-white h-36 px-4 shadow-sm rounded-xl border border-gray-100">
            <h1 class="font-bold text-sm text-gray-500 mb-1 text-center">Total Employee</h1>
            <span class="font-black text-2xl text-slate-800" id="totalEmployeeCount">...</span>
          </div>

          <div class="flex flex-col items-center justify-center bg-white h-36 px-4 shadow-sm rounded-xl border border-gray-100">
              <h1 class="font-bold text-sm text-gray-500 mb-1 text-center">Active Applicants</h1>
              <span class="font-black text-2xl text-orange-500" id="activeApplicantsCount">...</span>
          </div>

          <div class="flex flex-col items-center justify-center bg-white h-36 px-4 shadow-sm rounded-xl border border-gray-100">
              <h1 class="font-bold text-sm text-gray-500 mb-1 text-center">For Interview</h1>
              <span class="font-black text-2xl text-blue-500" id="interviewCount">...</span>
          </div>

          <div class="flex flex-col items-center justify-center bg-white h-36 px-4 shadow-sm rounded-xl border border-gray-100">
              <h1 class="font-bold text-sm text-gray-500 mb-1 text-center">Under Review / Offer</h1>
              <span class="font-black text-2xl text-emerald-500" id="reviewCount">...</span>
          </div>
        </div>

          
          <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
            <div class="flex justify-between items-center mb-4">
              <h2 class="font-bold text-lg text-gray-800 flex items-center gap-2">
                <i class="bi bi-people-fill text-orange-500"></i> Applicants On Process
              </h2>
              <span class="text-xs bg-orange-100 text-orange-600 px-2.5 py-1 rounded-full font-semibold">Live Processing</span>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-left border-collapse">
                <thead>
                  <tr class="border-b border-gray-100 text-gray-400 text-xs uppercase tracking-wider">
                    <th class="pb-3 font-semibold">Applicant Name</th>
                    <th class="pb-3 font-semibold">Email / Contact</th>
                    <th class="pb-3 font-semibold">Current Process Stage</th>
                  </tr>
                </thead>
                <tbody id="applicantsTableBody" class="text-sm divide-y divide-gray-50 text-gray-600">
                  <tr>
                    <td colspan="3" class="text-center py-4 text-gray-400">Loading applicants data...</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          

        </div>

     </div>
  </div>

  <script>
    document.addEventListener("DOMContentLoaded", function () {
        highlightActiveSidebarLink();
        loadDashboardData();
        setInterval(loadDashboardData, 5000);

        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', function(e) {
                e.preventDefault(); 

                Swal.fire({
                    title: 'Log out',
                    text: "Are you sure you want to Log out?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#FF8C00', 
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes,
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

    function highlightActiveSidebarLink() {
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll(".sidebar-link");
        
        navLinks.forEach(link => {
            const linkPath = link.getAttribute("href");
            if (linkPath && currentPath.endsWith(linkPath)) {
                link.classList.remove("text-white/80", "hover:bg-white/10", "hover:text-white", "text-inherit");
                link.classList.add("bg-[#FF8C00]", "text-white", "shadow-md", "font-semibold");
            }
        });
    }
   
    function loadDashboardData() {
        fetch("dashboard.php?action=fetch_dashboard_data")
            .then(response => {
                if (!response.ok) {
                    throw new Error("Network error: " + response.status);
                }
                return response.json();
            })
            .then(data => {
                document.getElementById('totalEmployeeCount').textContent = data.stats.total_employees;
                document.getElementById('activeApplicantsCount').textContent = data.stats.active_applicants;
                document.getElementById('interviewCount').textContent = data.stats.for_interview;
                document.getElementById('reviewCount').textContent = data.stats.under_review;

                const tableBody = document.getElementById("applicantsTableBody");
                tableBody.innerHTML = ""; 
                
                if (data.applicants && data.applicants.length > 0) {
                    data.applicants.forEach(app => {
                        tableBody.innerHTML += `
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="py-3 font-medium text-gray-700">${app.full_name}</td>
                                <td class="py-3 text-gray-500">${app.email}</td>
                                <td class="py-3">
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-orange-100 text-orange-600 uppercase tracking-wider">
                                        ${app.status}
                                    </span>
                                </td>
                            </tr>`;
                    });
                } else {
                    tableBody.innerHTML = `<tr><td colspan="3" class="text-center py-4 text-gray-400">No Active Applicants.</td></tr>`;
                }
            })
            .catch(error => {
                console.error("Dashboard Error:", error);
            });
    }
  </script>
  
  <script src="../LIBRARIES/bootstrap.bundle.min.js"></script>
</body>
</html>