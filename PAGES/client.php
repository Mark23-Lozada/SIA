<?php
// 1. DATABASE CONNECTION (Palitan ang 'hrms_db' kung iba ang pangalan ng database mo)
$hostname = "localhost";
$username = "root";
$password = "";
$database = "pos"; 

$conn = mysqli_connect($hostname, $username, $password, $database);

// 2. PHP INSERT LOGIC (Dito tinatanggap at ipinapasok sa database)
if (isset($_GET['action']) && $_GET['action'] == 'apply') {
    header('Content-Type: application/json');

    if (!$conn) {
        echo json_encode(["status" => "error", "message" => "Database connection failed: " . mysqli_connect_error()]);
        exit;
    }

    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email     = mysqli_real_escape_string($conn, $_POST['email']);
    $phone     = mysqli_real_escape_string($conn, $_POST['phone']);
    
    // File Upload handling para sa resume
    $target_dir = "../UPLOADS/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $file_extension = pathinfo($_FILES["resume"]["name"], PATHINFO_EXTENSION);
    $new_filename   = time() . '_' . preg_replace('/[^A-Za-z0-9\-]/', '', $full_name) . '.' . $file_extension;
    $target_file    = $target_dir . $new_filename;

    if (move_uploaded_file($_FILES["resume"]["tmp_name"], $target_file)) {
        
        // Ginamit ang mga eksaktong columns mula sa phpMyAdmin table mo
        $query = "INSERT INTO applicants (full_name, email, phone, resume_path, status, interview_date, created_at) 
                  VALUES ('$full_name', '$email', '$phone', '$target_file', 'Pending', NULL, NOW())";
        
        if (mysqli_query($conn, $query)) {
            echo json_encode(["status" => "success", "message" => "Application submitted successfully!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "SQL Error: " . mysqli_error($conn)]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to upload resume file. Check folder permissions."]);
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

        window.openApplicationModal = function() {
            Swal.fire({
                html: `
                    <form id="clientForm" enctype="multipart/form-data" class="space-y-4 text-left font-sans px-1">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Full Name</label>
                            <input type="text" id="full_name" name="full_name" required class="w-full text-sm px-4 py-2.5 border border-slate-200 rounded-xl focus:outline-none focus:border-orange-500 font-medium text-slate-700 transition-all">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Email Address</label>
                                <input type="email" id="email" name="email" required class="w-full text-sm px-4 py-2.5 border border-slate-200 rounded-xl focus:outline-none focus:border-orange-500 font-medium text-slate-700 transition-all">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Phone Number</label>
                                <input type="text" id="phone" name="phone" placeholder="e.g., 09123456789" maxlength="11" required class="w-full text-sm px-4 py-2.5 border border-slate-200 rounded-xl focus:outline-none focus:border-orange-500 font-medium text-slate-700 transition-all">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Upload Profile Resume (PDF/Docs)</label>
                            <div id="dropzone" class="border-2 border-dashed border-slate-200 rounded-xl p-5 text-center bg-slate-50 hover:bg-slate-100/70 hover:border-orange-400 cursor-pointer transition-all flex flex-col items-center justify-center gap-1 group">
                                <i class="bi bi-cloud-arrow-up-fill text-2xl text-slate-400 group-hover:text-orange-500 transition-colors"></i>
                                <p class="text-xs font-bold text-slate-600 drop-text">Drag file here or click to browse</p>
                                <p class="text-[10px] text-slate-400">Accepts PDF, DOC, DOCX files</p>
                                <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx" required class="hidden">
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
                    const email = document.getElementById('email').value.trim();
                    const phone = document.getElementById('phone').value.trim();
                    const file = document.getElementById('resume').files[0];

                    if(!name || !email || !phone) { Swal.showValidationMessage('Please write up your baseline fields.'); return false; }
                    if(!file) { Swal.showValidationMessage('Please submit or link your resume file asset.'); return false; }

                    return new FormData(document.getElementById('clientForm'));
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    Swal.fire({ title: 'Processing profile...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

                    // DITO: Tinatapon na sa sarili niyang file (`client.php?action=apply`)
                    fetch("client.php?action=apply", {
                        method: "POST",
                        body: result.value
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
                            // Ipapakita nito ang eksaktong error galing sa database para alam mo agad kung bakit ayaw pumunta doon
                            Swal.fire({ icon: 'error', title: 'Registration Failed', text: data.message });
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        Swal.fire({ icon: 'error', title: 'Connection Fault', text: 'Server data node cannot be reached.' });
                    });
                }
            });
        };
    </script>
</body>
</html>