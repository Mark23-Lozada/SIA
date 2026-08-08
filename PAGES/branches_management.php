<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '../../project-test1/BACKEND/db_inventory.php';

$message = '';
$error = '';

// Handle Add Branch Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_branch') {
    $branch_name = trim($_POST['branch_name']);
    $region = trim($_POST['region_text'] ?? '');
    $province = trim($_POST['province_text'] ?? '');
    $municipality = trim($_POST['municipality_text'] ?? '');
    $barangay = trim($_POST['barangay_text'] ?? '');
    $street_name = trim($_POST['street_name'] ?? '');
    $block_lot = trim($_POST['block_lot'] ?? ''); 
    $contact_number = trim($_POST['contact_number'] ?? '');

    $city_province_combined = $municipality . ', ' . $province;

    if (!empty($branch_name) && !empty($region) && !empty($province) && !empty($municipality) && !empty($barangay) && !empty($street_name)) {
        
        $check_stmt = $conn->prepare("SELECT branch_id FROM branches WHERE branch_name = ?");
        $check_stmt->bind_param("s", $branch_name);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            $error = "Reject: Branch Name already exists in the system!";
        } else {
            $branch_name = ucwords(strtolower($branch_name));
            $street_name = ucwords(strtolower($street_name));

            $insert_stmt = $conn->prepare("INSERT INTO branches (branch_name, block_lot, street_name, barangay, municipality, city, contact_number) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("sssssss", $branch_name, $block_lot, $street_name, $barangay, $municipality, $city_province_combined, $contact_number);
            
            if ($insert_stmt->execute()) {
                $message = "Branch successfully added using Live PSGC API Address Selector!";
            } else {
                $error = "Error saving branch data.";
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    } else {
        $error = "Please complete all required location fields (Region down to Street).";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PannaKoda - Branch Management</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .glass-header { background: linear-gradient(135deg, #3b0764 0%, #6b21a8 100%); }
        @keyframes fadeInSlide {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-slide {
            animation: fadeInSlide 0.4s ease-out forwards;
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const regionSelect = document.getElementById("regionSelect");
            const provinceSelect = document.getElementById("provinceSelect");
            const municipalitySelect = document.getElementById("municipalitySelect");
            const barangaySelect = document.getElementById("barangaySelect");
            
            const regionText = document.getElementById("regionText");
            const provinceText = document.getElementById("provinceText");
            const municipalityText = document.getElementById("municipalityText");
            const barangayText = document.getElementById("barangayText");

            fetch("https://psgc.gitlab.io/api/regions/")
                .then(res => res.json())
                .then(data => {
                    data.sort((a, b) => a.name.localeCompare(b.name));
                    data.forEach(region => {
                        let option = document.createElement("option");
                        option.value = region.code;
                        option.textContent = region.name;
                        regionSelect.appendChild(option);
                    });
                });

            regionSelect.addEventListener("change", function() {
                const regionCode = this.value;
                regionText.value = this.options[this.selectedIndex].text;
                provinceSelect.innerHTML = '<option value="">Select Province</option>';
                municipalitySelect.innerHTML = '<option value="">Select City / Municipality</option>';
                barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
                provinceSelect.disabled = true;
                municipalitySelect.disabled = true;
                barangaySelect.disabled = true;

                if (!regionCode) return;
                fetch(`https://psgc.gitlab.io/api/regions/${regionCode}/provinces/`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.length > 0) {
                            data.sort((a, b) => a.name.localeCompare(b.name));
                            data.forEach(prov => {
                                let option = document.createElement("option");
                                option.value = prov.code;
                                option.textContent = prov.name;
                                provinceSelect.appendChild(option);
                            });
                            provinceSelect.disabled = false;
                        } else {
                            loadMunicipalitiesForRegion(regionCode);
                        }
                    });
            });

            function loadMunicipalitiesForRegion(regionCode) {
                fetch(`https://psgc.gitlab.io/api/regions/${regionCode}/cities-municipalities/`)
                    .then(res => res.json())
                    .then(data => {
                        data.sort((a, b) => a.name.localeCompare(b.name));
                        data.forEach(mun => {
                            let option = document.createElement("option");
                            option.value = mun.code;
                            option.textContent = mun.name;
                            municipalitySelect.appendChild(option);
                        });
                        municipalitySelect.disabled = false;
                    });
            }

            provinceSelect.addEventListener("change", function() {
                const provinceCode = this.value;
                provinceText.value = this.options[this.selectedIndex].text;
                municipalitySelect.innerHTML = '<option value="">Select City / Municipality</option>';
                barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
                municipalitySelect.disabled = true;
                barangaySelect.disabled = true;

                if (!provinceCode) return;
                fetch(`https://psgc.gitlab.io/api/provinces/${provinceCode}/cities-municipalities/`)
                    .then(res => res.json())
                    .then(data => {
                        data.sort((a, b) => a.name.localeCompare(b.name));
                        data.forEach(mun => {
                            let option = document.createElement("option");
                            option.value = mun.code;
                            option.textContent = mun.name;
                            municipalitySelect.appendChild(option);
                        });
                        municipalitySelect.disabled = false;
                    });
            });

            municipalitySelect.addEventListener("change", function() {
                const munCode = this.value;
                municipalityText.value = this.options[this.selectedIndex].text;
                barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
                barangaySelect.disabled = true;
                if (!munCode) return;
                fetch(`https://psgc.gitlab.io/api/cities-municipalities/${munCode}/barangays/`)
                    .then(res => res.json())
                    .then(data => {
                        data.sort((a, b) => a.name.localeCompare(b.name));
                        data.forEach(brgy => {
                            let option = document.createElement("option");
                            option.value = brgy.code;
                            option.textContent = brgy.name;
                            barangaySelect.appendChild(option);
                        });
                        barangaySelect.disabled = false;
                    });
            });

            barangaySelect.addEventListener("change", function() {
                barangayText.value = this.options[this.selectedIndex].text;
            });
        });
    </script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans">
    <div class="flex h-screen w-full overflow-hidden">
        <div class="flex-shrink-0 h-full">
            <?php include 'sidebar.php'; ?>
        </div>

        <div class="flex-1 flex flex-col overflow-y-auto">
            <!-- Modern Header -->
            <header class="glass-header text-white px-8 py-8 flex items-center justify-between shadow-lg m-6 rounded-3xl animate-fade-in-slide">
                <div>
                    <span class="bg-white/20 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider mb-2 inline-block">Branches Overview</span>
                    <h1 class="text-3xl font-extrabold tracking-tight">Company Branch Management (National Footprint)</h1>
                    <p class="text-purple-100 mt-1 opacity-90">Manage and configure all corporate branches across regions seamlessly.</p>
                </div>
            </header>

            <main class="px-6 pb-12 max-w-5xl mx-auto w-full animate-fade-in-slide" style="animation-delay: 0.1s;">
                <?php if (!empty($message)): ?>
                    <div class="bg-emerald-500 text-white px-6 py-4 rounded-2xl flex items-center gap-3 mb-6 shadow-md transition-all duration-300">
                        <i class="bi bi-check-circle-fill text-xl"></i> <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="bg-red-500 text-white px-6 py-4 rounded-2xl flex items-center gap-3 mb-6 shadow-md transition-all duration-300">
                        <i class="bi bi-x-circle-fill text-xl"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="bg-white border border-slate-200 rounded-3xl shadow-xl p-8 transition-all duration-300 hover:shadow-2xl">
                    <h2 class="text-xl font-bold text-slate-900 mb-8 flex items-center gap-3">
                        <i class="bi bi-plus-circle text-purple-600 animate-spin" style="animation-duration: 10s;"></i> Register New Branch Location
                    </h2>
                    
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="add_branch">
                        <input type="hidden" id="regionText" name="region_text">
                        <input type="hidden" id="provinceText" name="province_text">
                        <input type="hidden" id="municipalityText" name="municipality_text">
                        <input type="hidden" id="barangayText" name="barangay_text">

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Branch Name</label>
                            <input type="text" name="branch_name" required placeholder="e.g. Dasmariñas Main Branch" class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-purple-600 focus:border-purple-600 outline-none transition-all duration-200">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Region</label>
                                <select id="regionSelect" name="region" required class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm bg-white focus:ring-2 focus:ring-purple-600 outline-none transition-all duration-200">
                                    <option value="">Select Region</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Province</label>
                                <select id="provinceSelect" name="province" disabled class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm bg-slate-50 focus:ring-2 focus:ring-purple-600 outline-none disabled:opacity-50 transition-all duration-200">
                                    <option value="">Select Region first</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">City / Municipality</label>
                                <select id="municipalitySelect" name="municipality" disabled class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm bg-slate-50 focus:ring-2 focus:ring-purple-600 outline-none disabled:opacity-50 transition-all duration-200">
                                    <option value="">Select Province first</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Barangay</label>
                                <select id="barangaySelect" name="barangay" disabled class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm bg-slate-50 focus:ring-2 focus:ring-purple-600 outline-none disabled:opacity-50 transition-all duration-200">
                                    <option value="">Select City / Municipality first</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Street Name / Building</label>
                                <input type="text" name="street_name" required placeholder="e.g. Aguinaldo Highway" class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-purple-600 outline-none transition-all duration-200">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Block & Lot / Unit Number</label>
                                <input type="text" name="block_lot" placeholder="e.g. Blk 2 Lot 4" class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-purple-600 outline-none transition-all duration-200">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Contact Number</label>
                            <input type="text" name="contact_number" placeholder="e.g. 09123456789" class="w-full border border-slate-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-purple-600 outline-none transition-all duration-200">
                        </div>
                        
                        <div class="pt-6">
                            <button type="submit" class="w-full bg-slate-900 hover:bg-purple-700 text-white font-bold py-4 rounded-xl transition-all duration-300 shadow-lg shadow-slate-200 hover:shadow-purple-500/20 active:scale-[0.99] flex items-center justify-center gap-2">
                                <i class="bi bi-save2"></i> Submit & Register Branch
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>
</body>
</html>