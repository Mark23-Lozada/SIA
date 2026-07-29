<?php
// 1. DATABASE CONNECTION (Palitan ang 'hrms_db' kung iba ang pangalan ng database mo)
$hostname = "localhost";
$username = "root";
$password = "";
$database = "pos"; 

$conn = mysqli_connect($hostname, $username, $password, $database);

// 2. PHP INSERT LOGIC (Dito tinatanggap at ipinapasok sa database)
if (isset($_GET['action']) && $_GET['action'] == 'apply') {
    // Linisin ang anumang naunang buffer para puro JSON lang ang lumabas
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

        // --- DUPLICATE CHECK: Suriin kung existing na ang email o pangalan sa database ---
        $check_query = "SELECT id FROM applicants WHERE email = ? OR full_name = ? LIMIT 1";
        $check_stmt = mysqli_prepare($conn, $check_query);
        if ($check_stmt) {
            mysqli_stmt_bind_param($check_stmt, "ss", $email, $full_name);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            
            if (mysqli_stmt_num_rows($check_stmt) > 0) {
                echo json_encode(["status" => "error", "message" => "Duplicate data: Some Data is already registered."]);
                mysqli_stmt_close($check_stmt);
                exit;
            }
            mysqli_stmt_close($check_stmt);
        }
        // -------------------------------------------------------------------------------

        // File Upload handling para sa resume
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
                echo json_encode(["status" => "success", "message" => "Application submitted successfully!"]);
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
    <script src="../LIBRARIES/tailwind.js"></script>
    <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
    <style>
        .swal2-html-container {
            margin: 1.5rem 0 0 0 !important;
            overflow: visible !important;
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen font-sans antialiased flex flex-col">

    <header class="bg-white border-b border-slate-200 py-4 px-6 sm:px-12 flex justify-between items-center shadow-sm sticky top-0 z-50">
        <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-amber-500 to-orange-600 flex items-center justify-center shadow-md">
                <i class="bi bi-shop text-white text-lg"></i>
            </div>
            <span class="font-black text-xl tracking-wider text-slate-800">PannaKoda</span>
        </div>
        <button onclick="openApplicationModal()" class="bg-[#FF8C00] hover:bg-orange-600 text-white text-xs sm:text-sm font-bold px-5 py-2.5 rounded-xl border-0 shadow-md shadow-orange-500/20 transition-all flex items-center gap-2">
            <i class="bi bi-pencil-square"></i> Apply Now
        </button>
    </header>

    <main class="flex-1 flex items-center justify-center p-4 sm:p-8">
        <div class="bg-white w-full max-w-4xl rounded-3xl shadow-xl border border-slate-200/60 overflow-hidden flex flex-col">
            
            <div class="bg-gradient-to-r from-amber-500 to-[#FF8C00] p-8 sm:p-12 text-center text-white relative overflow-hidden">
                <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full blur-2xl"></div>
                <div class="absolute -left-10 -bottom-10 w-40 h-40 bg-orange-700/20 rounded-full blur-2xl"></div>
                
                <h1 class="text-4xl sm:text-6xl font-black tracking-tighter uppercase drop-shadow-sm">We Are Hiring!</h1>
                <p class="text-sm sm:text-lg font-bold tracking-widest text-amber-100 mt-2 uppercase">Let's Join Our Team Now!</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-12 border-t border-slate-100">
                
                <div class="md:col-span-7 p-8 sm:p-10 flex flex-col justify-between bg-slate-50/50">
                    <div>
                        <h2 class="text-xs font-black text-[#FF8C00] uppercase tracking-widest mb-4 flex items-center gap-2">
                            <i class="bi bi-megaphone-fill"></i> Job Vacancy Open Departments
                        </h2>
                        
                        <ul id="job-checklist" class="space-y-3.5">
                            <div class="py-4 text-slate-400 text-sm font-medium flex items-center gap-2">
                                <i class="bi bi-arrow-clockwise animate-spin text-orange-500"></i> Retrieving active store posts...
                            </div>
                        </ul>
                    </div>
                </div>

                <div class="md:col-span-5 p-8 flex flex-col items-center justify-center border-t md:border-t-0 md:border-l border-slate-100 bg-white relative overflow-hidden min-h-[300px]">
                    <div class="absolute w-48 h-48 bg-orange-50 rounded-full -z-0 opacity-70"></div>
                    
                    <div class="relative z-10 text-center space-y-4">
                        <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-orange-100 text-[#FF8C00] text-5xl shadow-inner animate-bounce">
                            <i class="bi bi-person-fill-add"></i>
                        </div>
                        <div>
                            <h3 class="font-black text-slate-800 text-lg">PannaKoda Careers</h3>
                            <p class="text-xs text-slate-400 max-w-[200px] mx-auto mt-1">Be part of our fast-growing pancake family crew today!</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <script>
        let cachedActiveJobs = [];

        document.addEventListener("DOMContentLoaded", function() {
            fetchActiveHiringPools();
        });

        function fetchActiveHiringPools() {
            const listContainer = document.getElementById("job-checklist");

            // Pinatilihin ito sa recruitment.php kung doon kinukuha ang listahan ng bakante
            fetch("recruitment.php?action=fetch")
                .then(res => res.json())
                .then(data => {
                    listContainer.innerHTML = "";
                    let activeJobs = data.filter(job => job.status === "Active" && parseInt(job.openings) > 0);
                    cachedActiveJobs = activeJobs;

                    if (activeJobs.length === 0) {
                        listContainer.innerHTML = `
                            <li class="text-slate-400 font-medium text-sm flex items-center gap-2 py-2">
                                <i class="bi bi-info-circle"></i> No open vacancies at the moment.
                            </li>
                        `;
                        return;
                    }

                    activeJobs.forEach(job => {
                        const li = `
                            <li class="flex items-center justify-between bg-white p-3.5 rounded-xl border border-slate-100 shadow-sm hover:border-orange-200 transition-all group">
                                <div class="flex items-center gap-3">
                                    <div class="w-5 h-5 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs ring-1 ring-emerald-600/10">
                                        <i class="bi bi-check-lg font-bold"></i>
                                    </div>
                                    <span class="font-bold text-slate-700 text-sm group-hover:text-slate-900">${job.department}</span>
                                </div>
                                <span class="text-[11px] font-bold bg-orange-50 text-[#FF8C00] px-2.5 py-1 rounded-md">
                                    ${job.openings} Slot${job.openings > 1 ? 's' : ''} Left
                                </span>
                            </li>
                        `;
                        listContainer.insertAdjacentHTML("beforeend", li);
                    });
                })
                .catch(err => {
                    console.error(err);
                    listContainer.innerHTML = `<li class="text-rose-500 font-semibold text-sm">Failed to sync vacancy data nodes.</li>`;
                });
        }

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
                // Fallback in case the modal opened before the initial fetch resolved
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
                    <form id="clientForm" enctype="multipart/form-data" class="space-y-4 text-left font-sans px-1">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Full Name</label>
                            <input type="text" id="full_name" name="full_name" value="${formDataValues.full_name || ''}" required class="w-full text-sm px-4 py-2.5 border border-slate-200 rounded-xl focus:outline-none focus:border-orange-500 font-medium text-slate-700 transition-all">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Department You're Applying For</label>
                            <select id="department" name="department" required class="w-full text-sm px-4 py-2.5 border border-slate-200 rounded-xl focus:outline-none focus:border-orange-500 font-medium text-slate-700 transition-all bg-white">
                                <option value="" disabled selected>Loading open positions...</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Email Address</label>
                                <input type="email" id="email" name="email" value="${formDataValues.email || ''}" required class="w-full text-sm px-4 py-2.5 border border-slate-200 rounded-xl focus:outline-none focus:border-orange-500 font-medium text-slate-700 transition-all">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Phone Number</label>
                                <input type="text" id="phone" name="phone" value="${formDataValues.phone || ''}" placeholder="e.g., 09123456789" maxlength="11" required class="w-full text-sm px-4 py-2.5 border border-slate-200 rounded-xl focus:outline-none focus:border-orange-500 font-medium text-slate-700 transition-all">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Home Address</label>
                            <textarea id="address" name="address" required rows="2" placeholder="Enter your complete address" class="w-full text-sm px-4 py-2.5 border border-slate-200 rounded-xl focus:outline-none focus:border-orange-500 font-medium text-slate-700 transition-all resize-none">${formDataValues.address || ''}</textarea>
                        </div>

                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                            <h4 class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-3">Statutory & Government Identification</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">GSIS ID</label>
                                    <input type="text" id="gsis_id" name="gsis_id" value="${formDataValues.gsis_id || ''}" required placeholder="XX-XXXXXXX-X" class="w-full text-sm px-4 py-2 border border-slate-200 rounded-xl bg-white focus:outline-none focus:border-orange-500 font-medium text-slate-700 transition-all">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">SSS ID</label>
                                    <input type="text" id="sss_id" name="sss_id" value="${formDataValues.sss_id || ''}" required placeholder="XX-XXXXXXX-X" class="w-full text-sm px-4 py-2 border border-slate-200 rounded-xl bg-white focus:outline-none focus:border-orange-500 font-medium text-slate-700 transition-all">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">PhilHealth ID</label>
                                    <input type="text" id="philhealth_id" name="philhealth_id" value="${formDataValues.philhealth_id || ''}" required placeholder="XX-XXXXXXXXX-X" class="w-full text-sm px-4 py-2 border border-slate-200 rounded-xl bg-white focus:outline-none focus:border-orange-500 font-medium text-slate-700 transition-all">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Pag-IBIG MID</label>
                                    <input type="text" id="pagibig_id" name="pagibig_id" value="${formDataValues.pagibig_id || ''}" required placeholder="XXXX-XXXX-XXXX" class="w-full text-sm px-4 py-2 border border-slate-200 rounded-xl bg-white focus:outline-none focus:border-orange-500 font-medium text-slate-700 transition-all">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Upload Profile Resume (PDF/Docs)</label>
                            <div id="dropzone" class="border-2 border-dashed border-slate-200 rounded-xl p-5 text-center bg-slate-50 hover:bg-slate-100/70 hover:border-orange-400 cursor-pointer transition-all flex flex-col items-center justify-center gap-1 group">
                                <i class="bi bi-cloud-arrow-up-fill text-2xl text-slate-400 group-hover:text-orange-500 transition-colors"></i>
                                <p class="text-xs font-bold text-slate-600 drop-text">Drag file here or click to browse</p>
                                <p class="text-[10px] text-slate-400">Accepts PDF, DOC, DOCX files</p>
                                <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx" class="hidden">
                            </div>
                        </div>
                    </form>
                `,
                showCancelButton: true,
                confirmButtonText: 'Submit Profile Registration',
                cancelButtonText: 'Cancel',
                buttonsStyling: false,
                customClass: {
                    popup: 'rounded-3xl shadow-2xl border border-slate-100 p-6 max-w-lg w-full',
                    confirmButton: 'w-full py-3 bg-[#FF8C00] hover:bg-orange-600 text-white font-bold rounded-xl border-0 cursor-pointer shadow-md shadow-orange-500/10 transition-all text-sm mb-2.5 mt-4',
                    cancelButton: 'w-full py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-500 font-semibold rounded-xl border-0 cursor-pointer transition-all text-xs'
                },
                didOpen: () => {
                    populateDepartmentOptions();
                    
                    // Kung may pre-selected department galing sa previous attempt
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

                    dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('border-orange-500', 'bg-orange-50/30'); });
                    ['dragleave', 'drop'].forEach(event => dropzone.addEventListener(event, () => dropzone.classList.remove('border-orange-500', 'bg-orange-50/30')));
                    
                    dropzone.addEventListener('drop', (e) => { 
                        e.preventDefault(); 
                        if(e.dataTransfer.files.length) { 
                            fileInput.files = e.dataTransfer.files; 
                            syncFileVisualText(e.dataTransfer.files[0]); 
                        } 
                    });

                    function syncFileVisualText(file) {
                        if(!file) return;
                        dropText.innerHTML = `<span class="text-orange-600 font-bold"><i class="bi bi-file-earmark-check"></i> ${file.name}</span>`;
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

                    const govtIdPattern = /^\d{2}-\d{7}-\d{1}$/;
                    const philHealthPattern = /^\d{2}-\d{9}-\d{1}$/;
                    const pagibigPattern = /^\d{4}-\d{4}-\d{4}$/;

                    if (!name || !email || !phone || !address) {
                        Swal.showValidationMessage('Please write up your baseline fields.');
                        return false;
                    }
                    if (!department) {
                        Swal.showValidationMessage('Please select a department to apply for.');
                        return false;
                    }
                    if (!/^09\d{9}$/.test(phone)) {
                        Swal.showValidationMessage('Phone number must be 11 digits and start with 09.');
                        return false;
                    }
                    if (!govtIdPattern.test(gsisId)) {
                        Swal.showValidationMessage('GSIS ID format should be XX-XXXXXXX-X.');
                        return false;
                    }
                    if (!govtIdPattern.test(sssId)) {
                        Swal.showValidationMessage('SSS ID format should be XX-XXXXXXX-X.');
                        return false;
                    }
                    if (!philHealthPattern.test(philhealthId)) {
                        Swal.showValidationMessage('PhilHealth ID format should be XX-XXXXXXXXX-X.');
                        return false;
                    }
                    if (!pagibigPattern.test(pagibigId)) {
                        Swal.showValidationMessage('Pag-IBIG MID format should be XXXX-XXXX-XXXX.');
                        return false;
                    }
                    if(!file) { 
                        Swal.showValidationMessage('Please submit or link your resume file asset.'); 
                        return false; 
                    }

                    return new FormData(document.getElementById('clientForm'));
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const rawFormData = result.value;
                    
                    // Kunin ang current values para maisuksok pabalik kung sakaling mag-error
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

                    Swal.fire({ title: 'Processing profile...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

                    fetch("client.php?action=apply", {
                        method: "POST",
                        body: rawFormData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if(data.status === "success") {
                            Swal.fire({ 
                                icon: 'success', 
                                title: 'Registration Complete', 
                                text: data.message,
                                customClass: { confirmButton: 'bg-orange-500 text-white px-5 py-2.5 rounded-xl font-bold border-0' },
                                buttonsStyling: false
                            });
                        } else {
                            // DITO: Kapag nag-error (tulad ng duplicate data), ibabalik ulit ang modal kasama ang mga tinype niya
                            Swal.fire({
                                icon: 'error',
                                title: 'Registration Failed',
                                text: data.message,
                                confirmButtonText: 'Try Again',
                                customClass: { confirmButton: 'bg-orange-500 text-white px-5 py-2.5 rounded-xl font-bold border-0' },
                                buttonsStyling: false
                            }).then(() => {
                                openApplicationModal(currentValues); // Binubuksan ulit ang modal at nase-save ang input
                            });
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        Swal.fire({
                            icon: 'error',
                            title: 'Connection Fault',
                            text: 'Server data node cannot be reached.',
                            confirmButtonText: 'Back',
                            customClass: { confirmButton: 'bg-orange-500 text-white px-5 py-2.5 rounded-xl font-bold border-0' },
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