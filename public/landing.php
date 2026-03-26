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
    require_once __DIR__ . '/../includes/db.php';
    $pdo = Database::getInstance()->getConnection();
} catch (PDOException $e) {
    die('Σφάλμα σύνδεσης με τη βάση δεδομένων.');
}

$uuid = $_GET['uuid'] ?? '';
$coupons = [];
$error = null;

if (empty($uuid)) {
    $error = 'Μη έγκυρος κωδικός (UUID).';
} else {
    // Fetch all coupons sharing this UUID
    $stmt = $pdo->prepare("
        SELECT
            c.id, c.uuid, c.status, c.activated_at, c.confirmed_at,
            cam.title, cam.description, cam.is_active, cam.start_date, cam.end_date, cam.image_path,
            s.name AS store_name
        FROM coupons c
        JOIN campaigns cam ON c.campaign_id = cam.id
        LEFT JOIN stores s ON cam.store_id = s.id
        WHERE c.uuid = :uuid AND cam.deleted_at IS NULL
    ");
    $stmt->execute([':uuid' => $uuid]);
    $coupons = $stmt->fetchAll();

    if (!$coupons) {
        $error = 'Το κουπόνι δεν βρέθηκε.';
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Digital Wallet - Προσφορές</title>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Remix Icons -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">

    <style>
        :root {
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --accent-color: #3b82f6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-primary);
            padding: 1.5rem 1rem;
            min-height: 100vh;
        }

        .header-section {
            text-align: center;
            margin-bottom: 2rem;
        }

        .header-icon {
            font-size: 3rem;
            color: var(--accent-color);
            margin-bottom: 0.5rem;
        }

        .header-title {
            font-weight: 800;
            font-size: 1.5rem;
            letter-spacing: -0.5px;
        }

        .header-subtitle {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .wallet-container {
            max-width: 500px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .offer-card {
            background: var(--card-bg);
            border-radius: 1.2rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            position: relative;
        }

        .offer-card:active {
            transform: scale(0.98);
        }

        .offer-image {
            width: 100%;
            height: 140px;
            object-fit: cover;
            background-color: #e2e8f0;
        }

        .offer-content {
            padding: 1.5rem;
        }

        .store-badge {
            display: inline-block;
            background-color: rgba(59, 130, 246, 0.1);
            color: var(--accent-color);
            padding: 0.3rem 0.8rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 0.8rem;
        }

        .offer-title {
            font-weight: 700;
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }

        .offer-desc {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }

        .status-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.4rem 1rem;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 0.8rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 10;
        }

        .status-idle { background: rgba(255,255,255,0.9); color: var(--text-primary); backdrop-filter: blur(4px); }
        .status-activated { background: var(--warning-color); color: white; }
        .status-confirmed { background: var(--success-color); color: white; }
        .status-expired { background: #ef4444; color: white; }
        .status-future { background: #64748b; color: white; }

        .btn-activate {
            width: 100%;
            padding: 1rem;
            border-radius: 0.8rem;
            font-weight: 600;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
            transition: all 0.3s;
        }

        .btn-activate.idle {
            background-color: var(--accent-color);
            color: white;
            box-shadow: 0 4px 14px rgba(59, 130, 246, 0.4);
        }

        .btn-activate.idle:hover {
            background-color: #2563eb;
            transform: translateY(-2px);
        }

        .qr-display {
            background: #fff;
            padding: 1rem;
            border-radius: 1rem;
            text-align: center;
            margin-bottom: 1.5rem;
            border: 2px dashed #e2e8f0;
            display: none;
        }

        .qr-display img {
            width: 180px;
            height: 180px;
            margin: 0 auto;
        }

        .qr-code-text {
            font-family: monospace;
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
            word-break: break-all;
        }

        .action-box {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.2);
            border-radius: 0.8rem;
            padding: 1rem;
            text-align: center;
        }

        .action-box p {
            color: #b45309;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0;
        }

        /* Success Checkmark Animation */
        .success-checkmark {
            width: 80px;
            height: 80px;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border-radius: 50%;
            font-size: 2.5rem;
            animation: scaleIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }

        @keyframes scaleIn {
            0% { transform: scale(0); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body>

    <div class="header-section">
        <i class="ri-wallet-3-line header-icon"></i>
        <h1 class="header-title">My Offers</h1>
        <p class="header-subtitle">Οι διαθέσιμες προσφορές σας</p>
    </div>

    <div class="wallet-container">

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 rounded-4 text-center p-4">
                <i class="ri-error-warning-line fs-1 d-block mb-2"></i>
                <h5 class="fw-bold">Σφάλμα</h5>
                <p class="mb-0"><?= htmlspecialchars($error) ?></p>
            </div>
        <?php else: ?>

            <?php
            $currentTimestamp = date('Y-m-d H:i:s');

            foreach ($coupons as $c):
                $status = $c['status'];
                $statusClass = 'status-idle';
                $statusText = 'Διαθέσιμο';
                $btnDisabled = false;
                $btnClass = 'idle';
                $btnText = 'Ενεργοποίηση Προσφοράς';

                // Time Logic Evaluation
                if ((int)$c['is_active'] === 0) {
                    $statusClass = 'status-expired'; $statusText = 'Ανενεργό'; $btnDisabled = true; $btnText = 'Μη Διαθέσιμο';
                } else if ($currentTimestamp > $c['end_date']) {
                    $statusClass = 'status-expired'; $statusText = 'Έληξε'; $btnDisabled = true; $btnText = 'Έληξε';
                } else if ($currentTimestamp < $c['start_date']) {
                    $statusClass = 'status-future';
                    $startDateFormatted = date('d/m/Y', strtotime($c['start_date']));
                    $statusText = 'Από ' . $startDateFormatted;
                    $btnDisabled = true;
                    $btnText = 'Δεν ξεκίνησε ακόμα';
                } else {
                    // It's active in time. Check DB status.
                    if ($status === 'activated') {
                        $statusClass = 'status-activated';
                        $statusText = 'Ενεργοποιημένο';
                        $btnClass = 'activated';
                    } else if ($status === 'confirmed') {
                        $statusClass = 'status-confirmed';
                        $statusText = 'Εξαργυρώθηκε';
                        $btnClass = 'confirmed';
                        $btnDisabled = true;
                    }
                }

                $storeName = $c['store_name'] ?? 'Όλα τα καταστήματα';
                // Fallback image if none provided
                $heroImage = $c['image_path'] ?: 'https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?auto=format&fit=crop&w=800&q=80';
            ?>

            <!-- Offer Card -->
            <div class="offer-card" id="card-<?= $c['id'] ?>">

                <div class="status-badge <?= $statusClass ?>">
                    <i class="ri-record-circle-line me-1"></i> <?= $statusText ?>
                </div>

                <img src="<?= htmlspecialchars($heroImage) ?>" alt="Campaign" class="offer-image">

                <div class="offer-content">
                    <div class="store-badge">
                        <i class="ri-store-2-line me-1"></i> <?= htmlspecialchars($storeName) ?>
                    </div>

                    <h2 class="offer-title"><?= htmlspecialchars($c['title']) ?></h2>
                    <p class="offer-desc"><?= htmlspecialchars($c['description'] ?: 'Δεν υπάρχει περιγραφή.') ?></p>

                    <!-- Dynamic States -->
                    <?php if ($status === 'idle' && !$btnDisabled): ?>
                        <button class="btn-activate <?= $btnClass ?>" onclick="activateCoupon(<?= $c['id'] ?>, '<?= $c['uuid'] ?>', this)">
                            <i class="ri-flashlight-line me-2"></i> <?= $btnText ?>
                        </button>
                    <?php elseif ($status === 'activated'): ?>
                        <div class="action-box">
                            <div class="qr-display d-block">
                                <!-- Re-render the QR so the cashier can scan it -->
                                <div id="qr-<?= $c['id'] ?>"></div>
                                <div class="qr-code-text mt-3 fw-bold text-dark fs-5"><?= htmlspecialchars($c['uuid']) ?></div>
                            </div>
                            <p><i class="ri-scan-2-line me-1"></i> Δείξτε αυτή την οθόνη στο ταμείο για εξαργύρωση.</p>
                            <div class="mt-2 small text-muted">Ενεργοποιήθηκε: <?= date('d/m/Y H:i', strtotime($c['activated_at'])) ?></div>
                        </div>
                        <script>
                            document.addEventListener("DOMContentLoaded", function() {
                                new QRCode(document.getElementById("qr-<?= $c['id'] ?>"), {
                                    text: "<?= htmlspecialchars($c['uuid']) ?>",
                                    width: 150, height: 150,
                                    colorDark : "#000000", colorLight : "#ffffff"
                                });
                            });
                        </script>
                    <?php elseif ($status === 'confirmed'): ?>
                        <div class="text-center py-3">
                            <div class="success-checkmark"><i class="ri-check-double-line"></i></div>
                            <h4 class="fw-bold text-success mb-1">Εξαργυρώθηκε!</h4>
                            <p class="text-muted small">Στις <?= date('d/m/Y H:i', strtotime($c['confirmed_at'])) ?></p>
                        </div>
                    <?php else: ?>
                        <button class="btn-activate" disabled style="background: #e2e8f0; color: #94a3b8;">
                            <?= $btnText ?>
                        </button>
                    <?php endif; ?>

                </div>
            </div>

            <?php endforeach; ?>

            <?php if (empty($coupons)): ?>
                 <!-- Fallback if array is empty but no error -->
            <?php endif; ?>

        <?php endif; ?>

    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <script>
        function activateCoupon(couponId, uuid, btnElement) {
            const btn = $(btnElement);
            const originalHtml = btn.html();

            Swal.fire({
                title: 'Ενεργοποίηση Προσφοράς;',
                text: "Η προσφορά θα κλειδωθεί στο κινητό σας μέχρι να την εξαργυρώσετε στο ταμείο.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ναι, ενεργοποίηση!',
                cancelButtonText: 'Ακύρωση'
            }).then((result) => {
                if (result.isConfirmed) {

                    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Παρακαλώ περιμένετε...');

                    $.ajax({
                        url: '../api/activate_coupon.php',
                        type: 'POST',
                        contentType: 'application/json',
                        // We now pass the specific coupon_id instead of the uuid
                        data: JSON.stringify({ coupon_id: couponId }),
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                // Reload page to show the activated state (QR + text)
                                window.location.reload();
                            } else {
                                Swal.fire('Σφάλμα', response.message || 'Αποτυχία ενεργοποίησης.', 'error');
                                btn.prop('disabled', false).html(originalHtml);
                            }
                        },
                        error: function(xhr) {
                            let msg = 'Σφάλμα επικοινωνίας με τον διακομιστή.';
                            if(xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                            Swal.fire('Σφάλμα', msg, 'error');
                            btn.prop('disabled', false).html(originalHtml);
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>
