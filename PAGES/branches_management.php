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
    <title>PannaKoda - API-Driven Nationwide Address Add Branch</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
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

            // 1. Load Regions via PSGC API
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
                })
                .catch(err => console.error("Error loading regions:", err));

            // 2. Load Provinces when Region changes
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

                // May ibang region na walang province (tulad ng NCR), kaya kinukuha rin ang mga direktang munisipalidad/syudad kung sakali
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
                            // Kung walang province (e.g. NCR), direktang kunin ang mga cities/municipalities ng region
                            loadMunicipalitiesForRegion(regionCode);
                        }
                    })
                    .catch(err => console.error("Error loading provinces:", err));
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

            // 3. Load Cities / Municipalities when Province changes
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
                    })
                    .catch(err => console.error("Error loading municipalities:", err));
            });

            // 4. Load Barangays when City/Municipality changes
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
                    })
                    .catch(err => console.error("Error loading barangays:", err));
            });

            barangaySelect.addEventListener("change", function() {
                barangayText.value = this.options[this.selectedIndex].text;
            });
        });
    </script>
</head>
<body class="bg-gray-50 text-gray-800 antialiased font-sans">
    <div class="flex h-screen w-full overflow-hidden">
        
        <!-- Sidebar Integration -->
        <div class="flex-shrink-0 h-full">
            <?php include 'sidebar.php'; ?>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col overflow-y-auto">
            <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between shrink-0">
                <h1 class="text-xl font-bold text-gray-900 tracking-tight flex items-center gap-2">
                    <span class="w-3 h-3 bg-orange-500 rounded-full"></span> Add Company Branch (PSGC Live API)
                </h1>
               
            </header>

            <main class="p-6 max-w-3xl mx-auto w-full mt-6">

                <?php if (!empty($message)): ?>
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm flex items-center gap-2 mb-4">
                        <i class="bi bi-check-circle-fill text-lg"></i> <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm flex items-center gap-2 mb-4">
                        <i class="bi bi-x-circle-fill text-lg"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-6 border-b pb-3 flex items-center gap-2">
                        <i class="bi bi-geo-alt-fill text-orange-500"></i> Nationwide Address Selector via API
                    </h2>
                    
                    <form method="POST" class="space-y-5">
                        <input type="hidden" name="action" value="add_branch">
                        
                        <!-- Hidden inputs para sa actual pangalan ng lugar (Text) na ipapasa sa database -->
                        <input type="hidden" id="regionText" name="region_text">
                        <input type="hidden" id="provinceText" name="province_text">
                        <input type="hidden" id="municipalityText" name="municipality_text">
                        <input type="hidden" id="barangayText" name="barangay_text">

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Branch Name</label>
                            <input type="text" name="branch_name" required placeholder="e.g. Dasmariñas Main Branch" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500">
                        </div>

                        <!-- Cascading Dropdowns na kumukuha sa API -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Region</label>
                                <select id="regionSelect" name="region" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-orange-500">
                                    <option value="">Select Region</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Province</label>
                                <select id="provinceSelect" name="province" disabled class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-orange-500 disabled:bg-gray-100">
                                    <option value="">Select Region first</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">City / Municipality</label>
                                <select id="municipalitySelect" name="municipality" disabled class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-orange-500 disabled:bg-gray-100">
                                    <option value="">Select Province first</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Barangay</label>
                                <select id="barangaySelect" name="barangay" disabled class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-orange-500 disabled:bg-gray-100">
                                    <option value="">Select City / Municipality first</option>
                                </select>
                            </div>
                        </div>

                        <!-- Free Text Inputs for Street & Building -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Street Name / Building / House No.</label>
                                <input type="text" name="street_name" required placeholder="e.g. Aguinaldo Highway / Mabini St." class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Block & Lot / Unit Number <span class="text-xs text-gray-400 font-normal">(Optional)</span></label>
                                <input type="text" name="block_lot" placeholder="e.g. Blk 2 Lot 4" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Contact Number</label>
                            <input type="text" name="contact_number" placeholder="e.g. 09123456789" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-orange-500">
                        </div>
                        
                        <div class="pt-4 border-t border-gray-100">
                            <button type="submit" class="w-full bg-orange-500 hover:bg-orange-600 text-white text-sm font-bold py-3 rounded-lg transition-colors shadow-sm cursor-pointer flex items-center justify-center gap-2">
                                <i class="bi bi-check2-circle"></i> Save Nationwide Branch Location
                            </button>
                        </div>
                    </form>
                </div>

            </main>
        </div>
    </div>
</body>
</html>