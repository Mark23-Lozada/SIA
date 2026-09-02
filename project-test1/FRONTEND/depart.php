<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once __DIR__ . '/../BACKEND/db_inventory.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['mark_served'])) {
    header('Content-Type: application/json');
    $sale_id = isset($_POST['sale_id']) ? (int)$_POST['sale_id'] : 0;
    if ($sale_id > 0) {
        $stmt = $conn->prepare("UPDATE sales SET served_at = NOW() WHERE id = ? AND served_at IS NULL");
        $stmt->bind_param("i", $sale_id);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(["status" => "success"]);
    exit();
}

if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: text/html');

    $orders_result = $conn->query(
        "SELECT id, ready_at
         FROM sales
         WHERE ready_at IS NOT NULL
           AND ready_at <= NOW()
           AND served_at IS NULL
         ORDER BY ready_at ASC"
    );

    $orders = [];
    if ($orders_result) {
        while ($row = $orders_result->fetch_assoc()) {
            $orders[] = $row;
        }
    }

    if (empty($orders)) {
        echo '<div class="col-span-full flex flex-col items-center justify-center py-16 text-slate-400 animate-fade-in">
                <div class="w-16 h-16 rounded-2xl bg-orange-50 flex items-center justify-center text-amber-500 mb-3 shadow-inner border text-amber-500/20">
                    <i class="bi bi-inbox text-3xl"></i>
                </div>
                <p class="text-base font-semibold text-slate-600">No orders ready right now.</p>
                <p class="text-xs text-slate-400 mt-0.5">Completed preparations will appear here for pickup/delivery</p>
              </div>';
        exit();
    }

    $item_stmt = $conn->prepare(
        "SELECT items.item_name, sales_items.quantity
         FROM sales_items
         JOIN items ON sales_items.item_id = items.id
         WHERE sales_items.sale_id = ?"
    );

    foreach ($orders as $order) {
        $item_stmt->bind_param("i", $order['id']);
        $item_stmt->execute();
        $items_res = $item_stmt->get_result();
        $item_lines = [];
        while ($line = $items_res->fetch_assoc()) {
            $item_lines[] = htmlspecialchars($line['quantity'] . '× ' . $line['item_name']);
        }
        ?>
        <div class="bg-white border text-amber-500/30 rounded-2xl p-5 shadow-sm hover:shadow-xl text-amber-500 transition-all duration-300 transform hover:-translate-y-1 flex flex-col justify-between animate-fade-in group">
            <div>
                <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-100">
                    <span class="font-bold text-amber-500 text-lg tracking-tight text-amber-500 transition-colors">#TXN-<?php echo str_pad($order['id'], 5, '0', STR_PAD_LEFT); ?></span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 shadow-sm">
                        <i class="bi bi-check-circle-fill"></i> Ready
                    </span>
                </div>
                <ul class="space-y-2.5 text-slate-700 font-medium mb-6">
                    <?php foreach ($item_lines as $line): ?>
                        <li class="flex items-center gap-2 text-sm bg-slate-50/70 px-3 py-2 rounded-xl border border-slate-100/80">
                            <span class="inline-block w-2 h-2 bg-emerald-500 rounded-full shadow-sm"></span>
                            <?php echo $line; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <button type="button" class="w-full py-2.5 px-4 bg-gradient-to-r from-amber-500 to-amber-500 hover:from-amber-500 hover:to-amber-500 text-white font-semibold text-sm rounded-xl transition-all shadow-md shadow-[#ff6b4a]/20 focus:outline-none focus:ring-4 focus:ring-[#ff6b4a]/30 flex items-center justify-center gap-2 mark-served-btn group-hover:scale-[1.01]" data-sale-id="<?php echo $order['id']; ?>">
                <i class="bi bi-box-arrow-right text-base"></i> Mark as Served
            </button>
        </div>
        <?php
    }
    $item_stmt->close();
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PannaKoda - Kitchen (Ready / Depart)</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    </style>
</head>

<body class="bg-slate-50/80 min-h-screen text-slate-800 font-sans antialiased overflow-hidden">
  <div class="flex h-screen w-full overflow-hidden">
    <?php include '../../PAGES/sidebar.php'; ?>
    <main class="flex-1 overflow-y-auto p-6 lg:p-8 bg-gradient-to-br from-slate-50 via-orange-50/10 to-slate-50">
        <div class="max-w-7xl mx-auto">
            
            <!-- Page Header -->
            <div class="mb-8 border-b border-slate-200/80 pb-5 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-extrabold text-amber-500 tracking-tight">Delivery Display</h1>
                    <p class="text-sm text-slate-500 mt-0.5">Live delivery and pickup monitor</p>
                </div>
                <div class="flex items-center gap-2.5 text-xs font-medium bg-white px-3.5 py-2 rounded-xl shadow-sm border text-amber-500/20">
                    <span class="flex h-2.5 w-2.5 relative">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-[#ff6b4a]"></span>
                    </span>
                    <span class="text-slate-600">Auto-refreshing</span>
                </div>
            </div>

            <!-- Orders Container Grid -->
            <div id="orders-container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="col-span-full flex flex-col items-center justify-center py-16 text-slate-400">
                    <div class="animate-spin rounded-full h-10 w-10 border-4 text-amber-500 border-t-transparent shadow-md"></div>
                    <p class="mt-3 text-sm font-semibold text-slate-600">Loading ready orders...</p>
                </div>
            </div>

        </div>
    </main>
  </div>
    <script>
        function fetchOrders() {
            fetch('depart.php?ajax=1')
                .then(res => res.text())
                .then(html => {
                    document.getElementById('orders-container').innerHTML = html;
                    attachServedHandlers();
                })
                .catch(err => console.error('Error fetching ready orders:', err));
        }

        function attachServedHandlers() {
            document.querySelectorAll('.mark-served-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const saleId = this.getAttribute('data-sale-id');
                    const formData = new FormData();
                    formData.append('mark_served', '1');
                    formData.append('sale_id', saleId);

                    fetch('depart.php', { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') fetchOrders();
                        })
                        .catch(err => console.error('Error marking order served:', err));
                });
            });
        }

        fetchOrders();
        setInterval(fetchOrders, 10000);
    </script>
</body>
</html>
<?php $conn->close(); ?>