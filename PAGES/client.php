<?php
// 1. DATABASE CONNECTION
$hostname = "localhost";
$username = "root";
$password = "";
$database = "pos"; 

$conn = mysqli_connect($hostname, $username, $password, $database);

// 2. PHP INSERT LOGIC
if (isset($_GET['action']) && $_GET['action'] == 'apply') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    try {
        if (!$conn) {
            echo json_encode(["status" => "error", "message" => "Database connection failed: " . mysqli_connect_error()]);
            exit;
        }

        $full_name     = $_POST['full_name'] ?? '';
        $email         = $_POST['email'] ?? '';
        $phone         = $_POST['phone'] ?? '';
        $address       = $_POST['address'] ?? '';
        $gsis_id       = $_POST['gsis_id'] ?? '';
        $sss_id        = $_POST['sss_id'] ?? '';
        $philhealth_id = $_POST['philhealth_id'] ?? '';
        $pagibig_id    = $_POST['pagibig_id'] ?? '';
        $department    = $_POST['department'] ?? '';

        // --- SERVER-SIDE FIELD VALIDATION (mirrors client-side regex; never trust the browser alone) ---
        if (empty($full_name) || empty($email) || empty($phone) || empty($address) || empty($department)) {
            echo json_encode(["status" => "error", "message" => "Please completely fill out all personal details."]);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(["status" => "error", "message" => "Please provide a valid email address."]);
            exit;
        }

        if (!preg_match('/^09\d{9}$/', $phone)) {
            echo json_encode(["status" => "error", "message" => "Phone number must be 11 digits starting with 09."]);
            exit;
        }

        // GSIS: alphanumeric ID format check
        if (!preg_match('/^[A-Za-z0-9\-]{6,20}$/', $gsis_id)) {
            echo json_encode(["status" => "error", "message" => "Invalid GSIS ID. Please enter your GSIS BP Number as issued to you."]);
            exit;
        }

        // SSS: XX-XXXXXXX-X (2-7-1 digits)
        if (!preg_match('/^\d{2}-\d{7}-\d{1}$/', $sss_id)) {
            echo json_encode(["status" => "error", "message" => "Invalid SSS ID format. Expected XX-XXXXXXX-X."]);
            exit;
        }

        // PhilHealth: XX-XXXXXXXXX-X (2-9-1 digits)
        if (!preg_match('/^\d{2}-\d{9}-\d{1}$/', $philhealth_id)) {
            echo json_encode(["status" => "error", "message" => "Invalid PhilHealth ID format. Expected XX-XXXXXXXXX-X."]);
            exit;
        }

        // Pag-IBIG MID: XXXX-XXXX-XXXX (4-4-4 digits standard format)
        if (!preg_match('/^\d{4}-\d{4}-\d{4}$/', $pagibig_id)) {
            echo json_encode(["status" => "error", "message" => "Invalid Pag-IBIG MID format. Expected XXXX-XXXX-XXXX."]);
            exit;
        }

        // --- DUPLICATE CHECK ---
        $check_query = "SELECT id FROM applicants WHERE email = ? OR full_name = ? LIMIT 1";
        $check_stmt = mysqli_prepare($conn, $check_query);
        if ($check_stmt) {
            mysqli_stmt_bind_param($check_stmt, "ss", $email, $full_name);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            
            if (mysqli_stmt_num_rows($check_stmt) > 0) {
                echo json_encode(["status" => "error", "message" => "Duplicate data: This applicant or email is already registered."]);
                mysqli_stmt_close($check_stmt);
                exit;
            }
            mysqli_stmt_close($check_stmt);
        }

        // File Upload Handling
        $target_dir = "../UPLOADS/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        if (!isset($_FILES["resume"]) || $_FILES["resume"]["error"] != 0) {
            echo json_encode(["status" => "error", "message" => "Please upload a valid resume file."]);
            exit;
        }

        $file_extension = pathinfo($_FILES["resume"]["name"], PATHINFO_EXTENSION);
        $new_filename   = time() . '_' . preg_replace('/[^A-Za-z0-9\-]/', '', $full_name) . '.' . $file_extension;
        $target_file    = $target_dir . $new_filename;

        if (move_uploaded_file($_FILES["resume"]["tmp_name"], $target_file)) {
            $query = "INSERT INTO applicants 
                        (full_name, email, phone, address, gsis_id, sss_id, philhealth_id, pagibig_id, department, resume_path, status, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())";
            
            $stmt = mysqli_prepare($conn, $query);
            if (!$stmt) {
                echo json_encode(["status" => "error", "message" => "Prepare failed: " . mysqli_error($conn)]);
                exit;
            }

            mysqli_stmt_bind_param(
                $stmt, "ssssssssss",
                $full_name, $email, $phone, $address,
                $gsis_id, $sss_id, $philhealth_id, $pagibig_id, $department,
                $target_file
            );

            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(["status" => "success", "message" => "Your application was submitted successfully!"]);
            } else {
                echo json_encode(["status" => "error", "message" => "SQL Execution Error: " . mysqli_stmt_error($stmt)]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to move uploaded resume file. Check folder permissions."]);
        }
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Server Exception: " . $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Careers Portal - PannaKoda</title>
    <!-- Tailwind CSS -->
    <script src="../LIBRARIES/tailwind.js"></script>
    <!-- Bootstrap Icons -->
    <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
    <!-- SweetAlert2 -->
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        
        .swal2-html-container {
            margin: 0.75rem 0 0 0 !important;
            overflow: visible !important;
        }
        body {
            background-color: #f8fafc;
        }
    </style>
</head>
<body class="min-h-screen font-sans antialiased flex flex-col selection:bg-purple-600 selection:text-white">

    <!-- Header -->
    <header class="bg-white/80 backdrop-blur-xl border-b border-slate-100 py-4 px-6 sm:px-12 flex justify-between items-center sticky top-0 z-50 transition-all">
        <div class="flex items-center gap-3.5">
            <div class="w-15 h-15 rounded-2xl bg-gradient-to-tr from-purple-600 to-indigo-600 flex items-center justify-center shadow-lg shadow-purple-500/25">
                <img src="../LIBRARIES/5501d331-f1e5-4dcc-ab9b-8fd56a2b5ea5.png" alt="image" style="width: 50px; height: 50px; border-radius: 20%;">
            </div>
            <div>
                <span class="font-black text-lg tracking-wider text-slate-900 block leading-tight">PannaKoda</span>
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-widest block">Careers Hub</span>
            </div>
        </div>
        <!-- Apply Now Button -->
        <button onclick="openApplicationModal()" class="bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white text-xs sm:text-sm font-extrabold px-6 py-2.5 rounded-xl shadow-lg shadow-purple-500/25 flex items-center gap-2 cursor-pointer transition-all transform active:scale-95">
            <i class="bi bi-pencil-square text-base"></i> Apply Now
        </button>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 flex items-center justify-center p-4 sm:p-6">
        <div class="bg-white w-full max-w-5xl rounded-[2.5rem] shadow-2xl shadow-slate-200/60 border border-slate-100 overflow-hidden flex flex-col animate-fade-in">
            
            <!-- Hero Banner -->
            <div class="bg-gradient-to-r from-purple-900 via-purple-600 to-indigo-600 p-8 sm:p-12 text-center text-white relative overflow-hidden">
                <div class="absolute -right-10 -top-10 w-48 h-48 bg-white/10 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute -left-10 -bottom-10 w-48 h-48 bg-purple-950/30 rounded-full blur-3xl pointer-events-none"></div>
                
                <span class="inline-block bg-white/10 backdrop-blur-md text-purple-100 text-[11px] font-bold px-4 py-1.5 rounded-full uppercase tracking-widest mb-3 shadow-inner border border-white/15">
                    We're Expanding Our Crew!
                </span>
                <h1 class="text-3xl sm:text-5xl font-black tracking-tight uppercase drop-shadow-sm">Join Our Sweet Family</h1>
                <p class="text-xs sm:text-sm font-medium tracking-wide text-purple-200 max-w-lg mx-auto mt-2">
                    Discover open opportunities and build your career with our fast-growing team.
                </p>
            </div>

            <!-- Content Split Section -->
            <div class="grid grid-cols-1 md:grid-cols-12">
                
                <!-- Left: Vacancies Listing -->
                <div class="md:col-span-7 p-6 sm:p-8 flex flex-col justify-between bg-slate-50/50">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xs font-black text-purple-600 uppercase tracking-widest flex items-center gap-1.5">
                                <i class="bi bi-megaphone-fill text-sm"></i> Active Hiring Departments
                            </h2>
                            <span class="text-[11px] font-semibold text-slate-400 flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Live Sync
                            </span>
                        </div>
                        
                        <ul id="job-checklist" class="space-y-3">
                            <div class="py-8 text-slate-400 text-xs font-medium flex items-center justify-center gap-2">
                                <i class="bi bi-arrow-clockwise animate-spin text-purple-600 text-base"></i> Retrieving positions...
                            </div>
                        </ul>
                    </div>

                    <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-400">
                        <span>Need assistance?</span>
                        <a href="mailto:careers@pannakoda.com" class="font-bold text-purple-600 hover:underline">Contact HR Support</a>
                    </div>
                </div>

                <!-- Right: Graphic Card -->
                <div class="md:col-span-5 p-6 sm:p-8 flex flex-col items-center justify-center border-t md:border-t-0 md:border-l border-slate-100 bg-white relative overflow-hidden min-h-[260px]">
                    <div class="absolute w-44 h-44 bg-purple-50 rounded-full blur-3xl opacity-80 -z-0"></div>
                    
                    <div class="relative z-10 text-center space-y-3">
                        <div class="inline-flex items-center justify-center w-200 h-200 rounded-2xl bg-gradient-to-tr from-purple-50 to-indigo-50 text-purple-600 text-3xl shadow-inner shadow-purple-500/10 border border-purple-100">
                            <img src="../LIBRARIES/5501d331-f1e5-4dcc-ab9b-8fd56a2b5ea5.png" alt="image" style="width: 150px; height: 150px; border-radius: 50%;">
                        </div>
                        <div>
                            <h3 class="font-black text-slate-900 text-base">PannaKoda Talent Network</h3>
                            <p class="text-xs text-slate-500 max-w-[210px] mx-auto mt-1 leading-relaxed">Submit your resume today and jumpstart your professional journey with us.</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Core Scripts -->
    <script>
        let cachedActiveJobs = [];

        document.addEventListener("DOMContentLoaded", function() {
            fetchActiveHiringPools();
        });

        function fetchActiveHiringPools() {
            const listContainer = document.getElementById("job-checklist");

            fetch("recruitment.php?action=fetch")
                .then(res => res.json())
                .then(data => {
                    listContainer.innerHTML = "";
                    let activeJobs = data.filter(job => job.status === "Active" && parseInt(job.openings) > 0);
                    cachedActiveJobs = activeJobs;

                    if (activeJobs.length === 0) {
                        listContainer.innerHTML = `
                            <div class="bg-white p-6 rounded-2xl border border-slate-100 text-center shadow-xs">
                                <i class="bi bi-info-circle text-purple-600 text-2xl mb-1.5 block"></i>
                                <p class="text-slate-700 font-bold text-xs">No open vacancies at the moment.</p>
                                <p class="text-slate-400 text-[11px] mt-0.5">Please check back later for updates.</p>
                            </div>
                        `;
                        return;
                    }

                    activeJobs.forEach(job => {
                        const li = `
                            <li class="flex items-center justify-between bg-white p-4 rounded-2xl border border-slate-100 shadow-xs hover:border-purple-300 hover:shadow-md hover:bg-purple-50/10 transition-all group cursor-pointer">
                                <div class="flex items-center gap-3.5">
                                    <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-sm shadow-xs border border-purple-100 group-hover:bg-purple-600 group-hover:text-white transition-all">
                                        <i class="bi bi-briefcase-fill"></i>
                                    </div>
                                    <div>
                                        <span class="font-bold text-slate-800 text-xs sm:text-sm group-hover:text-purple-600 transition-colors block">${job.department}</span>
                                        <button type="button" onclick="viewRequirements(${job.id})" class="text-[10px] font-bold text-purple-600 hover:text-purple-700 hover:underline mt-0.5 inline-flex items-center gap-1 bg-transparent border-0 p-0 cursor-pointer">
                                            <i class="bi bi-file-earmark-text"></i> View Requirements
                                        </button>
                                    </div>
                                </div>
                                <span class="text-xs font-black bg-purple-50 text-purple-600 px-3.5 py-1.5 rounded-xl border border-purple-100 group-hover:bg-purple-600 group-hover:text-white group-hover:border-purple-600 transition-all shadow-2xs">
                                    ${job.openings} Slot${job.openings > 1 ? 's' : ''}
                                </span>
                            </li>
                        `;
                        listContainer.insertAdjacentHTML("beforeend", li);
                    });
                })
                .catch(err => {
                    console.error(err);
                    listContainer.innerHTML = `
                        <div class="bg-rose-50 border border-rose-200 p-4 rounded-2xl text-center text-rose-600 text-xs font-semibold">
                            Failed to sync vacancy data.
                        </div>
                    `;
                });
        }

        window.viewRequirements = function(id) {
            const job = cachedActiveJobs.find(j => j.id == id);
            if (!job) return;

            const reqText = job.requirements && job.requirements.trim()
                ? job.requirements
                : "No specific requirements have been listed for this position yet.";
            const notesText = job.notes && job.notes.trim() ? job.notes : "";
            const salaryText = job.salary ? `₱${Number(job.salary).toLocaleString()} / month` : "";

            Swal.fire({
                html: `
                    <div class="text-left">
                        <span class="text-[11px] font-black uppercase tracking-widest text-purple-600 bg-purple-50 px-3 py-1 rounded-lg border border-purple-100">${job.department}</span>
                        ${salaryText ? `<div class="text-sm font-black text-slate-800 mt-3">${salaryText}</div>` : ''}
                        <h2 class="text-base font-black text-slate-900 mt-4 mb-1.5 flex items-center gap-1.5"><i class="bi bi-card-checklist text-purple-600"></i> Job Requirements</h2>
                        <p class="text-xs sm:text-sm text-slate-600 whitespace-pre-line leading-relaxed bg-slate-50 p-3.5 rounded-2xl border border-slate-100">${reqText}</p>
                        ${notesText ? `
                            <h2 class="text-base font-black text-slate-900 mt-4 mb-1.5 flex items-center gap-1.5"><i class="bi bi-info-circle-fill text-purple-600"></i> Things to Know Before Applying</h2>
                            <p class="text-xs sm:text-sm text-slate-600 whitespace-pre-line leading-relaxed bg-slate-50 p-3.5 rounded-2xl border border-slate-100">${notesText}</p>
                        ` : ''}
                    </div>
                `,
                confirmButtonText: 'Close',
                buttonsStyling: false,
                customClass: {
                    popup: 'rounded-3xl shadow-2xl border border-slate-100 p-6 sm:p-7 max-w-lg w-full text-left',
                    confirmButton: 'w-full py-3 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl border-0 cursor-pointer text-xs sm:text-sm mt-4 shadow-md'
                }
            });
        };

        function populateDepartmentOptions() {
            const select = document.getElementById('department');

            const renderOptions = (jobs) => {
                if (!jobs.length) {
                    select.innerHTML = `<option value="" disabled selected>No open positions available</option>`;
                    return;
                }
                select.innerHTML = `<option value="" disabled selected>Select a department</option>` +
                    jobs.map(job => `<option value="${job.department}">${job.department} (${job.openings} slot${job.openings > 1 ? 's' : ''} left)</option>`).join('');
            };

            if (cachedActiveJobs.length) {
                renderOptions(cachedActiveJobs);
            } else {
                fetch("recruitment.php?action=fetch")
                    .then(res => res.json())
                    .then(data => {
                        const activeJobs = data.filter(job => job.status === "Active" && parseInt(job.openings) > 0);
                        cachedActiveJobs = activeJobs;
                        renderOptions(activeJobs);
                    })
                    .catch(() => {
                        select.innerHTML = `<option value="" disabled selected>Unable to load positions</option>`;
                    });
            }
        }

        window.openApplicationModal = function(formDataValues = {}) {
            Swal.fire({
                html: `
                    <div class="text-left mb-5 pb-3 border-b border-slate-100">
                        <span class="text-[11px] font-black uppercase tracking-widest text-purple-600 bg-purple-50 px-3 py-1 rounded-lg border border-purple-100">Candidate Onboarding</span>
                        <h2 class="text-xl sm:text-2xl font-black text-slate-900 mt-2">Application Registration Form</h2>
                        <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Please fill out all required details accurately to submit your application.</p>
                    </div>

                    <form id="clientForm" enctype="multipart/form-data" class="text-left font-sans space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-5">
                            
                            <!-- Left Column -->
                            <div class="space-y-3.5">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Full Name</label>
                                    <input type="text" id="full_name" name="full_name" value="${formDataValues.full_name || ''}" placeholder="e.g. Juan Dela Cruz" required class="w-full text-xs sm:text-sm px-4 py-3 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-purple-500/25 focus:border-purple-600 font-medium text-slate-700 bg-slate-50/50">
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Department</label>
                                    <select id="department" name="department" required class="w-full text-xs sm:text-sm px-4 py-3 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-purple-500/25 focus:border-purple-600 font-medium text-slate-700 bg-slate-50/50">
                                        <option value="" disabled selected>Loading positions...</option>
                                    </select>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Email</label>
                                        <input type="email" id="email" name="email" value="${formDataValues.email || ''}" placeholder="name@example.com" required class="w-full text-xs sm:text-sm px-3.5 py-3 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-purple-500/25 focus:border-purple-600 font-medium text-slate-700 bg-slate-50/50">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Phone</label>
                                        <input type="text" id="phone" name="phone" value="${formDataValues.phone || ''}" placeholder="09123456789" maxlength="11" required class="w-full text-xs sm:text-sm px-3.5 py-3 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-purple-500/25 focus:border-purple-600 font-medium text-slate-700 bg-slate-50/50">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Home Address</label>
                                    <textarea id="address" name="address" required rows="2" placeholder="Complete address" class="w-full text-xs sm:text-sm px-4 py-2.5 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-purple-500/25 focus:border-purple-600 font-medium text-slate-700 resize-none bg-slate-50/50">${formDataValues.address || ''}</textarea>
                                </div>
                            </div>

                            <!-- Right Column -->
                            <div class="space-y-3.5 flex flex-col justify-between">
                                <!-- Government ID Group Box -->
                                <div class="bg-slate-50/80 border border-slate-100 rounded-2xl p-4 shadow-2xs">
                                    <h4 class="text-[11px] font-black text-slate-700 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                        <i class="bi bi-shield-lock-fill text-purple-600 text-sm"></i> Government IDs
                                    </h4>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">GSIS BP Number</label>
                                            <input type="text" id="gsis_id" name="gsis_id" value="${formDataValues.gsis_id || ''}" required placeholder="GSIS BP Number" class="w-full text-xs px-3 py-2.5 border border-slate-200 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-purple-500/25 focus:border-purple-600 text-slate-700">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">SSS ID</label>
                                            <input type="text" id="sss_id" name="sss_id" value="${formDataValues.sss_id || ''}" required placeholder="XX-XXXXXXX-X" class="w-full text-xs px-3 py-2.5 border border-slate-200 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-purple-500/25 focus:border-purple-600 text-slate-700">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">PhilHealth ID</label>
                                            <input type="text" id="philhealth_id" name="philhealth_id" value="${formDataValues.philhealth_id || ''}" required placeholder="XX-XXXXXXXXX-X" class="w-full text-xs px-3 py-2.5 border border-slate-200 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-purple-500/25 focus:border-purple-600 text-slate-700">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Pag-IBIG MID</label>
                                            <input type="text" id="pagibig_id" name="pagibig_id" value="${formDataValues.pagibig_id || ''}" required placeholder="XXXX-XXXX-XXXX" class="w-full text-xs px-3 py-2.5 border border-slate-200 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-purple-500/25 focus:border-purple-600 text-slate-700">
                                        </div>
                                    </div>
                                </div>

                                <!-- Dropzone File Upload -->
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1.5">Upload Resume / CV</label>
                                    <div id="dropzone" class="border-2 border-dashed border-slate-200 rounded-2xl p-4 text-center bg-slate-50/50 cursor-pointer transition-all flex flex-col items-center justify-center gap-1 hover:border-purple-500 hover:bg-purple-50/20">
                                        <i class="bi bi-cloud-arrow-up-fill text-2xl text-purple-600"></i>
                                        <p class="text-xs sm:text-sm font-bold text-slate-700 drop-text">Drag file here or <span class="text-purple-600 underline">browse</span></p>
                                        <p class="text-[10px] text-slate-400">PDF, DOC, DOCX (Max: 10MB)</p>
                                        <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx" class="hidden">
                                    </div>
                                </div>
                            </div>

                        </div>
                    </form>
                `,
                showCancelButton: true,
                confirmButtonText: 'Submit Application',
                cancelButtonText: 'Discard',
                buttonsStyling: false,
                customClass: {
                    popup: 'rounded-3xl shadow-2xl border border-slate-100 p-6 sm:p-9 max-w-4xl w-full',
                    confirmButton: 'w-full py-3.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-extrabold rounded-2xl border-0 cursor-pointer shadow-lg shadow-purple-500/25 text-xs sm:text-sm mb-2.5 mt-4 transition-all transform active:scale-98',
                    cancelButton: 'w-full py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-2xl border-0 cursor-pointer text-xs sm:text-sm transition-all'
                },
                didOpen: () => {
                    populateDepartmentOptions();
                    
                    if (formDataValues.department) {
                        setTimeout(() => {
                            const deptSelect = document.getElementById('department');
                            if (deptSelect) deptSelect.value = formDataValues.department;
                        }, 300);
                    }

                    const dropzone = document.getElementById('dropzone');
                    const fileInput = document.getElementById('resume');
                    const dropText = dropzone.querySelector('.drop-text');

                    dropzone.addEventListener('click', () => fileInput.click());
                    fileInput.addEventListener('change', (e) => syncFileVisualText(e.target.files[0]));

                    dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('border-purple-500', 'bg-purple-50/20'); });
                    ['dragleave', 'drop'].forEach(event => dropzone.addEventListener(event, () => dropzone.classList.remove('border-purple-500', 'bg-purple-50/20')));
                    
                    dropzone.addEventListener('drop', (e) => { 
                        e.preventDefault(); 
                        if(e.dataTransfer.files.length) { 
                            fileInput.files = e.dataTransfer.files; 
                            syncFileVisualText(e.dataTransfer.files[0]); 
                        } 
                    });

                    function syncFileVisualText(file) {
                        if(!file) return;
                        dropText.innerHTML = `<span class="text-purple-600 font-bold flex items-center justify-center gap-1.5"><i class="bi bi-file-earmark-check-fill"></i> ${file.name}</span>`;
                    }
                },
                preConfirm: () => {
                    const name = document.getElementById('full_name').value.trim();
                    const department = document.getElementById('department').value;
                    const email = document.getElementById('email').value.trim();
                    const phone = document.getElementById('phone').value.trim();
                    const address = document.getElementById('address').value.trim();
                    const gsisId = document.getElementById('gsis_id').value.trim();
                    const sssId = document.getElementById('sss_id').value.trim();
                    const philhealthId = document.getElementById('philhealth_id').value.trim();
                    const pagibigId = document.getElementById('pagibig_id').value.trim();
                    const file = document.getElementById('resume').files[0];

                    const sssPattern = /^\d{2}-\d{7}-\d{1}$/;
                    const philHealthPattern = /^\d{2}-\d{9}-\d{1}$/;
                    const pagibigPattern = /^\d{4}-\d{4}-\d{4}$/;
                    const gsisPattern = /^[A-Za-z0-9\-]{6,20}$/;

                    if (!name || !email || !phone || !address) {
                        Swal.showValidationMessage('Please completely fill out all personal details.');
                        return false;
                    }
                    if (!department) {
                        Swal.showValidationMessage('Please select a target department.');
                        return false;
                    }
                    if (!/^09\d{9}$/.test(phone)) {
                        Swal.showValidationMessage('Phone number must be 11 digits starting with 09.');
                        return false;
                    }
                    if (!gsisPattern.test(gsisId)) {
                        Swal.showValidationMessage('Please enter your GSIS BP Number as issued to you.');
                        return false;
                    }
                    if (!sssPattern.test(sssId)) {
                        Swal.showValidationMessage('Invalid SSS ID format. Expected XX-XXXXXXX-X.');
                        return false;
                    }
                    if (!philHealthPattern.test(philhealthId)) {
                        Swal.showValidationMessage('Invalid PhilHealth ID format. Expected XX-XXXXXXXXX-X.');
                        return false;
                    }
                    if (!pagibigPattern.test(pagibigId)) {
                        Swal.showValidationMessage('Invalid Pag-IBIG MID format. Expected XXXX-XXXX-XXXX.');
                        return false;
                    }
                    if(!file) { 
                        Swal.showValidationMessage('Please attach your resume file asset.'); 
                        return false; 
                    }

                    return new FormData(document.getElementById('clientForm'));
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const rawFormData = result.value;
                    
                    const currentValues = {
                        full_name: rawFormData.get('full_name'),
                        email: rawFormData.get('email'),
                        phone: rawFormData.get('phone'),
                        address: rawFormData.get('address'),
                        gsis_id: rawFormData.get('gsis_id'),
                        sss_id: rawFormData.get('sss_id'),
                        philhealth_id: rawFormData.get('philhealth_id'),
                        pagibig_id: rawFormData.get('pagibig_id'),
                        department: rawFormData.get('department')
                    };

                    Swal.fire({ 
                        title: 'Submitting application...', 
                        html: 'Please wait while we secure your records.',
                        allowOutsideClick: false, 
                        didOpen: () => Swal.showLoading() 
                    });

                    fetch("client.php?action=apply", {
                        method: "POST",
                        body: rawFormData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if(data.status === "success") {
                            Swal.fire({ 
                                icon: 'success', 
                                title: 'Application Submitted!', 
                                text: data.message,
                                customClass: { confirmButton: 'bg-purple-600 hover:bg-purple-700 text-white px-5 py-2.5 rounded-xl font-bold border-0 cursor-pointer shadow-md text-xs' },
                                buttonsStyling: false
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Submission Failed',
                                text: data.message,
                                confirmButtonText: 'Try Again',
                                customClass: { confirmButton: 'bg-purple-600 hover:bg-purple-700 text-white px-5 py-2.5 rounded-xl font-bold border-0 cursor-pointer shadow-md text-xs' },
                                buttonsStyling: false
                            }).then(() => {
                                openApplicationModal(currentValues);
                            });
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        Swal.fire({
                            icon: 'error',
                            title: 'Connection Fault',
                            text: 'Unable to reach the server data node. Please try again later.',
                            confirmButtonText: 'Back',
                            customClass: { confirmButton: 'bg-purple-600 hover:bg-purple-700 text-white px-5 py-2.5 rounded-xl font-bold border-0 cursor-pointer shadow-md text-xs' },
                            buttonsStyling: false
                        }).then(() => {
                            openApplicationModal(currentValues);
                        });
                    });
                }
            });
        };
    </script>
</body>
</html>