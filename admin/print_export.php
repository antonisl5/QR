<?php
/**
 * admin/print_export.php
 *
 * Generates an A4 print-ready layout of all 'idle' coupons for a given campaign.
 * Uses qrcode.js to render real QR codes pointing to the landing page.
 */

require_once '../includes/auth_guard.php';
$user = require_role(['admin']);
$pageTitle = 'Εκτύπωση Κουπονιών';
require_once '../includes/header.php';
require_once 'sidebar.php';

// Validate campaign ID
$campaign_id = $_GET['campaign_id'] ?? null;
if (!$campaign_id || !is_numeric($campaign_id)) {
    die("Μη έγκυρο ID Καμπάνιας. Παρακαλώ επιστρέψτε και προσπαθήστε ξανά.");
}

$campaign_id = (int)$campaign_id;

try {
    // Database connection
    require_once '../includes/db.php';
    $pdo = Database::getInstance()->getConnection();

    // Fetch Campaign Details
    $stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = ? LIMIT 1");
    $stmt->execute([$campaign_id]);
    $campaign = $stmt->fetch();

    if (!$campaign) {
        die("Η καμπάνια δεν βρέθηκε.");
    }

    // Fetch all Idle Coupons for this campaign
    $stmt = $pdo->prepare("SELECT id, uuid FROM coupons WHERE campaign_id = ? AND status = 'idle' ORDER BY id ASC");
    $stmt->execute([$campaign_id]);
    $coupons = $stmt->fetchAll();

    $total_coupons = count($coupons);
    if ($total_coupons === 0) {
        die("Δεν βρέθηκαν διαθέσιμα (idle) κουπόνια για αυτήν την καμπάνια. Παρακαλώ δημιουργήστε μια παρτίδα πρώτα.");
    }

    // Determine Base URL for QR Codes (e.g., https://example.com/public/landing.php?uuid=)
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    // In production, you might want to hardcode this in .env (e.g., APP_URL)
    $base_url = getenv('APP_URL') ?: "$protocol://$host";
    $landing_url = rtrim($base_url, '/') . '/public/landing.php?uuid=';

} catch (PDOException $e) {
    error_log("Database error in print_export: " . $e->getMessage());
    die("Σφάλμα συστήματος. Παρακαλώ δοκιμάστε αργότερα.");
}

// Calculate grid pages (8 coupons per A4 sheet: 2 cols x 4 rows)
$coupons_per_page = 8;
$pages = ceil($total_coupons / $coupons_per_page);
?>

    <!-- On-screen Print Controls (Hidden during actual print) -->
    <div class="print-controls no-print d-flex flex-column align-items-center justify-content-center">
        <h2 class="mb-3">Προεπισκόπηση Εκτύπωσης</h2>
        <p class="text-muted">Καμπάνια: <strong><?php echo htmlspecialchars($campaign['title']); ?></strong> | Κουπόνια: <strong><?php echo $total_coupons; ?></strong> | Σελίδες A4: <strong><?php echo $pages; ?></strong></p>
        <div>
            <button onclick="window.print()" class="btn btn-primary btn-lg me-2">
                <i class="bi bi-printer"></i> Εκτύπωση (PDF / Χαρτί)
            </button>
            <button onclick="window.close()" class="btn btn-outline-secondary btn-lg">
                Κλείσιμο
            </button>
        </div>
        <div class="alert alert-info mt-3" style="max-width: 600px;">
            <i class="bi bi-info-circle"></i> <strong>Σημαντικό:</strong> Στις ρυθμίσεις εκτύπωσης του browser (π.χ. Chrome), βεβαιωθείτε ότι είναι ενεργοποιημένη η επιλογή <strong>"Background graphics / Γραφικά παρασκηνίου"</strong> και ότι τα Margins είναι στο "None" ή "Minimum".
        </div>
    </div>

    <!-- The Print Container -->
    <div id="print-container">

        <?php
        $coupon_index = 0;
        // Loop through each required A4 page
        for ($p = 0; $p < $pages; $p++):
        ?>
            <!-- A single A4 Sheet Grid containing exactly 8 coupons (or fewer on the last page) -->
            <div class="coupon-grid">

                <?php
                // Loop through 8 coupons for this page
                for ($i = 0; $i < $coupons_per_page; $i++):
                    if ($coupon_index >= $total_coupons) break;

                    $coupon = $coupons[$coupon_index];
                    $coupon_index++;
                    $full_qr_url = $landing_url . $coupon['uuid'];

                    // Fallback visual data from campaign if exists (using design settings from Command 3)
                    // We parse the JSON if it exists, otherwise use defaults
                    $design = [];
                    if (!empty($campaign['design_json'])) {
                        $design = json_decode($campaign['design_json'], true);
                    }
                    $bg_color = $design['background_color'] ?? '#ffffff';
                    $bg_image = $design['background_image'] ?? '';
                    $logo = $design['logo_url'] ?? ''; // Optional logo URL
                ?>

                <!-- Individual Print Coupon Element -->
                <div class="print-coupon" style="background-color: <?php echo htmlspecialchars($bg_color); ?>;">
                    <?php if ($bg_image): ?>
                    <div class="coupon-bg" style="background-image: url('<?php echo htmlspecialchars($bg_image); ?>');"></div>
                    <?php endif; ?>

                    <?php if ($logo): ?>
                    <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo" class="brand-logo">
                    <?php endif; ?>

                    <div class="campaign-title"><?php echo htmlspecialchars($campaign['title']); ?></div>
                    <div class="discount-text"><?php echo htmlspecialchars($campaign['discount_value'] ?? 'ΕΚΠΤΩΣΗ'); ?></div>

                    <!-- QR Code Container: Data attributes used by JS to render -->
                    <div class="qr-code-container" data-url="<?php echo htmlspecialchars($full_qr_url); ?>"></div>

                    <div class="uuid-text"><?php echo htmlspecialchars($coupon['uuid']); ?></div>
                </div>

                <?php endfor; ?>

            </div>
            <!-- /A4 Sheet -->
        <?php endfor; ?>

    </div>

    <!-- Render Logic -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Find all QR code containers
            const qrContainers = document.querySelectorAll('.qr-code-container');

            // Loop and render
            qrContainers.forEach(container => {
                const url = container.getAttribute('data-url');

                // Clear existing content (if any)
                container.innerHTML = '';

                // Generate QR Code onto canvas
                new QRCode(container, {
                    text: url,
                    width: 120, // Render slightly larger for high-DPI print crispness
                    height: 120,
                    colorDark : "#000000",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.M // Medium error correction is sufficient and keeps QR simple
                });
            });

            // Optional: Automatically trigger print dialog after a short delay to ensure QR rendering
            // setTimeout(() => window.print(), 1000);
        });
    </script>
<?php require_once '../includes/footer.php'; ?>