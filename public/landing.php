<?php
/**
 * public/landing.php
 *
 * Customer facing mobile-first landing page.
 * Scanned from a printed QR code. Retrieves the UUID from the URL,
 * fetches the current state, and displays the appropriate UI.
 */

declare(strict_types=1);

// Initialize Database Connection (Stubbed)
try {
    $dbHost = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $dbName = $_ENV['DB_NAME'] ?? 'qr_coupons';
    $dbUser = $_ENV['DB_USER'] ?? 'root';
    $dbPass = $_ENV['DB_PASS'] ?? '';

    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die('Σφάλμα σύνδεσης με τη βάση δεδομένων.');
}

$uuid = $_GET['uuid'] ?? '';
$coupon = null;
$error = null;

if (empty($uuid) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
    $error = 'Μη έγκυρος κωδικός κουπονιού.';
} else {
    // Fetch coupon and campaign details
    $stmt = $pdo->prepare("
        SELECT
            c.id, c.uuid, c.status, c.activated_at, c.confirmed_at,
            cam.title, cam.description, cam.is_active, cam.start_date, cam.end_date,
            s.name AS store_name
        FROM coupons c
        JOIN campaigns cam ON c.campaign_id = cam.id
        LEFT JOIN users u ON cam.user_id = u.id
        LEFT JOIN stores s ON u.store_id = s.id
        WHERE c.uuid = :uuid
    ");
    $stmt->execute([':uuid' => $uuid]);
    $coupon = $stmt->fetch();

    if (!$coupon) {
        $error = 'Το κουπόνι δεν βρέθηκε.';
    } else {
        $currentTimestamp = date('Y-m-d H:i:s');
        if ((int)$coupon['is_active'] === 0 || $currentTimestamp < $coupon['start_date'] || $currentTimestamp > $coupon['end_date']) {
            $error = 'Η προσφορά έχει λήξει ή δεν είναι πλέον ενεργή.';
        }
    }
}

// Determine UI State
$uiState = 'error';
if (!$error && $coupon) {
    $uiState = $coupon['status']; // 'idle', 'activated', 'confirmed'
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Η Προσφορά σας</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Remix Icons -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">

    <!-- Custom Landing CSS -->
    <link rel="stylesheet" href="../assets/css/landing.css">
</head>
<body class="landing-body state-<?= htmlspecialchars($uiState) ?>">

    <div class="mobile-container shadow-lg">

        <!-- Store Header (Mock image or logo) -->
        <div class="store-hero position-relative">
            <!-- Using a placeholder image, to be replaced by actual campaign/store image -->
            <img src="https://images.unsplash.com/photo-1555396273-367ea4eb4db5?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80" alt="Κατάστημα" class="w-100 object-fit-cover" style="height: 200px;">
            <div class="overlay-gradient position-absolute bottom-0 w-100 h-50"></div>
            <h2 class="store-name position-absolute bottom-0 start-0 text-white p-4 m-0 fw-bold">
                <?= htmlspecialchars($coupon['store_name'] ?? 'Κατάστημα') ?>
            </h2>
        </div>

        <div class="content-wrapper p-4 bg-white rounded-top-4 position-relative" style="margin-top: -20px; z-index: 10;">

            <?php if ($error): ?>
                <!-- ERROR STATE -->
                <div class="text-center py-5">
                    <img src="../assets/images/3d-icons/error-coupon.webp" alt="Σφάλμα" class="img-fluid mb-3 w-50" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'100\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23dc3545\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'><circle cx=\'12\' cy=\'12\' r=\'10\'></circle><line x1=\'15\' y1=\'9\' x2=\'9\' y2=\'15\'></line><line x1=\'9\' y1=\'9\' x2=\'15\' y2=\'15\'></line></svg>'">
                    <h3 class="fw-bold text-danger mb-3">Ωχ!</h3>
                    <p class="text-muted"><?= htmlspecialchars($error) ?></p>
                </div>
            <?php else: ?>

                <h1 class="campaign-title fw-bold text-dark mb-2"><?= htmlspecialchars($coupon['title']) ?></h1>
                <p class="text-secondary mb-4"><?= nl2br(htmlspecialchars($coupon['description'] ?? '')) ?></p>
                <hr class="text-muted opacity-25">

                <!-- DYNAMIC STATES CONTAINER -->
                <div id="stateContainer">

                    <!-- IDLE STATE: Ready to Activate -->
                    <?php if ($uiState === 'idle'): ?>
                        <div id="idleState" class="text-center py-3 fade-in">
                            <div class="alert alert-primary bg-primary bg-opacity-10 border-0 text-start d-flex align-items-center mb-4 rounded-3 p-3">
                                <i class="ri-information-line fs-3 text-primary me-3"></i>
                                <div>
                                    <h6 class="fw-bold mb-1 text-primary">Οδηγίες</h6>
                                    <p class="mb-0 small text-dark">Πατήστε το κουμπί όταν βρίσκεστε στο ταμείο για να ενεργοποιήσετε την προσφορά.</p>
                                </div>
                            </div>

                            <button id="activateBtn" class="btn btn-primary btn-lg w-100 rounded-pill fw-bold shadow-sm d-flex justify-content-center align-items-center py-3" data-uuid="<?= htmlspecialchars($coupon['uuid']) ?>">
                                <i class="ri-flashlight-fill fs-5 me-2"></i>
                                <span>Ενεργοποίηση Τώρα</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- ACTIVATED STATE: Waiting for store confirmation -->
                    <?php if ($uiState === 'activated'): ?>
                        <div id="activatedState" class="text-center py-4 fade-in">
                            <div class="success-animation mb-3">
                                <img src="../assets/images/3d-icons/verified-badge.webp" alt="Ενεργό" class="img-fluid w-50 drop-shadow-md heartbeat" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'100\' viewBox=\'0 0 24 24\' fill=\'%23198754\'><path d=\'M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm-1.04-10.857l-2.828-2.829-1.415 1.414L10.96 13.97l6.364-6.364-1.414-1.414-4.95 4.95z\'/></svg>'">
                            </div>
                            <h3 class="fw-bold text-success mb-2">Ενεργοποιήθηκε!</h3>
                            <p class="text-dark fw-medium mb-1">Δείξτε αυτή την οθόνη στο προσωπικό του καταστήματος.</p>
                            <p class="text-muted small">Ενεργοποιήθηκε στις: <span id="activationTime"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($coupon['activated_at']))) ?></span></p>

                            <!-- Animated scanning line to indicate it's ready for the cashier -->
                            <div class="scan-area mt-4 p-3 border rounded-3 bg-light position-relative overflow-hidden">
                                <div class="scan-line"></div>
                                <span class="fw-bold font-monospace text-muted ls-2"><?= htmlspecialchars(substr($coupon['uuid'], 0, 8)) ?>...</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- CONFIRMED STATE: Used / Redeemed -->
                    <?php if ($uiState === 'confirmed'): ?>
                        <div id="confirmedState" class="text-center py-4 fade-in opacity-75">
                            <img src="../assets/images/3d-icons/completed-stamp.webp" alt="Εξαργυρώθηκε" class="img-fluid mb-3 w-50 grayscale" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'100\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%236c757d\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'><rect x=\'3\' y=\'3\' width=\'18\' height=\'18\' rx=\'2\' ry=\'2\'></rect><line x1=\'9\' y1=\'9\' x2=\'15\' y2=\'15\'></line><line x1=\'15\' y1=\'9\' x2=\'9\' y2=\'15\'></line></svg>'">
                            <h3 class="fw-bold text-secondary mb-2">Εξαργυρώθηκε</h3>
                            <p class="text-muted">Αυτό το κουπόνι έχει ήδη χρησιμοποιηθεί.</p>
                            <p class="text-muted small">Ημερομηνία εξαργύρωσης:<br><?= htmlspecialchars(date('d/m/Y H:i', strtotime($coupon['confirmed_at']))) ?></p>
                        </div>
                    <?php endif; ?>

                </div> <!-- End State Container -->

                <!-- Client-side injected Activated State (Hidden initially unless activated via AJAX) -->
                <div id="dynamicActivatedState" class="text-center py-4 d-none fade-in">
                    <div class="success-animation mb-3">
                        <img src="../assets/images/3d-icons/verified-badge.webp" alt="Ενεργό" class="img-fluid w-50 drop-shadow-md heartbeat" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'100\' viewBox=\'0 0 24 24\' fill=\'%23198754\'><path d=\'M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm-1.04-10.857l-2.828-2.829-1.415 1.414L10.96 13.97l6.364-6.364-1.414-1.414-4.95 4.95z\'/></svg>'">
                    </div>
                    <h3 class="fw-bold text-success mb-2">Ενεργοποιήθηκε!</h3>
                    <p class="text-dark fw-medium mb-1">Δείξτε αυτή την οθόνη στο προσωπικό του καταστήματος.</p>
                    <p class="text-muted small">Ενεργοποιήθηκε: <span id="dynamicActivationTime">Μόλις τώρα</span></p>
                    <div class="scan-area mt-4 p-3 border rounded-3 bg-light position-relative overflow-hidden">
                        <div class="scan-line"></div>
                        <span class="fw-bold font-monospace text-muted ls-2" id="dynamicUuidCode"><?= htmlspecialchars(substr($uuid, 0, 8)) ?>...</span>
                    </div>
                </div>

            <?php endif; ?>
        </div>

        <div class="text-center py-3 pb-5">
            <small class="text-muted">Powered by CouponBuilder PRO</small>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script src="../assets/js/landing.js"></script>
</body>
</html>
