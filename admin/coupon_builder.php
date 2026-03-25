<?php
/**
 * admin/coupon_builder.php
 *
 * The main Coupon Builder interface for the Admin panel.
 * Uses a Canvas-like approach for designing printable coupons with
 * absolute positioning and draggable elements.
 */

// Define page title for the layout wrapper (if one exists)
$pageTitle = 'Σχεδιαστής Κουπονιών';

// Basic session checks and config inclusions would go here
// require_once '../includes/auth.php';
// require_once '../includes/config.php';
?>
<!DOCTYPE html>
<html lang="el" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | QR Coupon Platform</title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Remix Icons -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">

    <!-- Toastr CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">

    <!-- Custom Builder CSS -->
    <link rel="stylesheet" href="../assets/css/builder.css">
</head>
<body class="builder-body">

    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg glass-navbar fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php">
                <i class="ri-qr-code-line fs-3 text-primary"></i>
                <span class="fw-bold">CouponBuilder PRO</span>
            </a>

            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-icon theme-toggle" id="themeToggleBtn" title="Εναλλαγή θέματος">
                    <i class="ri-moon-line"></i>
                </button>
                <div class="vr mx-1"></div>
                <button class="btn btn-outline-secondary" id="previewBtn">
                    <i class="ri-eye-line me-1"></i> Προεπισκόπηση
                </button>
                <button class="btn btn-primary btn-save d-flex align-items-center gap-2" id="saveTemplateBtn">
                    <i class="ri-save-line"></i>
                    <span>Αποθήκευση</span>
                    <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                </button>
                <button class="btn btn-success" id="exportPdfBtn">
                    <i class="ri-file-pdf-line me-1"></i> Εξαγωγή PDF
                </button>
            </div>
        </div>
    </nav>

    <!-- Main Layout -->
    <div class="container-fluid builder-container">
        <div class="row h-100">

            <!-- Sidebar: Tools & Properties -->
            <div class="col-12 col-xl-3 sidebar glass-panel d-flex flex-column">

                <ul class="nav nav-tabs nav-justified mb-3" id="builderTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="elements-tab" data-bs-toggle="tab" data-bs-target="#elements-pane" type="button" role="tab" aria-controls="elements-pane" aria-selected="true">
                            <i class="ri-shapes-line me-1"></i> Στοιχεία
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="properties-tab" data-bs-toggle="tab" data-bs-target="#properties-pane" type="button" role="tab" aria-controls="properties-pane" aria-selected="false">
                            <i class="ri-settings-3-line me-1"></i> Ιδιότητες
                        </button>
                    </li>
                </ul>

                <div class="tab-content flex-grow-1 overflow-auto pe-2" id="builderTabsContent">

                    <!-- Elements Tab -->
                    <div class="tab-pane fade show active" id="elements-pane" role="tabpanel" aria-labelledby="elements-tab" tabindex="0">

                        <div class="mb-4">
                            <h6 class="text-uppercase text-muted fs-7 fw-bold mb-3">Φόντο & Καμβάς</h6>
                            <div class="d-grid gap-2">
                                <button class="btn btn-light d-flex justify-content-between align-items-center text-start p-3 add-element-btn" id="uploadBgBtn">
                                    <span><i class="ri-image-add-line fs-5 me-2 text-primary"></i> Ανέβασμα Εικόνας</span>
                                    <i class="ri-add-circle-line text-muted"></i>
                                </button>
                                <input type="file" id="bgFileInput" accept="image/png, image/jpeg, image/webp" class="d-none">
                            </div>
                        </div>

                        <div class="mb-4">
                            <h6 class="text-uppercase text-muted fs-7 fw-bold mb-3">Δυναμικά Στοιχεία</h6>
                            <div class="d-grid gap-2">
                                <button class="btn btn-light d-flex justify-content-between align-items-center text-start p-3 add-element-btn" id="addQrBtn">
                                    <span><i class="ri-qr-code-line fs-5 me-2 text-dark"></i> Προσθήκη QR Code</span>
                                    <i class="ri-add-circle-line text-muted"></i>
                                </button>
                                <button class="btn btn-light d-flex justify-content-between align-items-center text-start p-3 add-element-btn" id="addTextTitleBtn">
                                    <span><i class="ri-text fs-5 me-2 text-dark"></i> Κείμενο - Τίτλος</span>
                                    <i class="ri-add-circle-line text-muted"></i>
                                </button>
                                <button class="btn btn-light d-flex justify-content-between align-items-center text-start p-3 add-element-btn" id="addTextBodyBtn">
                                    <span><i class="ri-align-left fs-5 me-2 text-dark"></i> Κείμενο - Περιγραφή</span>
                                    <i class="ri-add-circle-line text-muted"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h6 class="text-uppercase text-muted fs-7 fw-bold mb-3">Διακοσμητικά & Σήματα</h6>
                            <div class="d-grid gap-2">
                                <button class="btn btn-light d-flex justify-content-between align-items-center text-start p-3 add-element-btn" id="addBadgeDiscountBtn">
                                    <span><i class="ri-price-tag-3-line fs-5 me-2 text-danger"></i> Σήμα Έκπτωσης (-20%)</span>
                                    <i class="ri-add-circle-line text-muted"></i>
                                </button>
                            </div>
                        </div>

                    </div>

                    <!-- Properties Tab -->
                    <div class="tab-pane fade" id="properties-pane" role="tabpanel" aria-labelledby="properties-tab" tabindex="0">

                        <!-- Empty Selection State -->
                        <div id="noSelectionState" class="text-center py-5">
                            <img src="../assets/images/3d-icons/select-element.webp" alt="Επιλογή Στοιχείου" class="img-fluid mb-3 w-50 opacity-50">
                            <h6 class="text-muted">Επιλέξτε ένα στοιχείο</h6>
                            <p class="small text-muted mb-0">Κάντε κλικ σε ένα στοιχείο στον καμβά για να επεξεργαστείτε τις ιδιότητές του.</p>
                        </div>

                        <!-- Active Selection State (Populated via JS) -->
                        <div id="activeSelectionState" class="d-none">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="m-0 fw-bold" id="propElementType">Τύπος Στοιχείου</h6>
                                <button class="btn btn-sm btn-outline-danger" id="deleteElementBtn" title="Διαγραφή">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            </div>

                            <!-- Text Properties -->
                            <div id="textPropertiesGroup" class="d-none">
                                <div class="mb-3">
                                    <label class="form-label small">Περιεχόμενο</label>
                                    <textarea class="form-control form-control-sm" id="propTextContent" rows="3"></textarea>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label small">Μέγεθος (px)</label>
                                        <input type="number" class="form-control form-control-sm" id="propTextSize" min="10" max="120">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small">Χρώμα</label>
                                        <input type="color" class="form-control form-control-sm form-control-color w-100" id="propTextColor" value="#000000">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small">Στοίχιση</label>
                                    <div class="btn-group w-100" role="group">
                                        <button type="button" class="btn btn-sm btn-outline-secondary align-btn" data-align="left"><i class="ri-align-left"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary align-btn active" data-align="center"><i class="ri-align-center"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary align-btn" data-align="right"><i class="ri-align-right"></i></button>
                                    </div>
                                </div>
                            </div>

                            <!-- Dimension Properties (Common) -->
                            <div class="row g-2 mb-3 pt-3 border-top">
                                <div class="col-6">
                                    <label class="form-label small">Πλάτος</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" class="form-control" id="propWidth">
                                        <span class="input-group-text">px</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Ύψος</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" class="form-control" id="propHeight">
                                        <span class="input-group-text">px</span>
                                    </div>
                                </div>
                            </div>

                        </div>

                    </div>
                </div>
            </div>

            <!-- Main Workspace: Canvas Area -->
            <div class="col-12 col-xl-9 workspace d-flex justify-content-center align-items-center">

                <!-- Empty State (Before Base Template) -->
                <div id="canvasEmptyState" class="text-center z-index-1">
                    <img src="../assets/images/3d-icons/start-building.webp" alt="Ξεκινήστε" class="img-fluid mb-4" style="max-width: 250px;">
                    <h3 class="fw-bold">Ξεκινήστε τον Σχεδιασμό</h3>
                    <p class="text-muted mb-4">Ανεβάστε μια εικόνα φόντου για να δημιουργήσετε το κουπόνι σας.</p>
                    <button class="btn btn-primary btn-lg rounded-pill px-4 shadow-sm" onclick="document.getElementById('bgFileInput').click()">
                        <i class="ri-upload-cloud-2-line me-2"></i> Επιλογή Φόντου
                    </button>
                </div>

                <!-- The Actual Canvas Envelope -->
                <div class="canvas-wrapper d-none" id="canvasWrapper">

                    <!-- Print Safe Margin Overlay (Toggleable via Preview) -->
                    <div class="safe-margin-overlay d-none" id="safeMarginOverlay"></div>

                    <!-- The Canvas element handling relative positioning -->
                    <div class="coupon-canvas shadow-lg" id="couponCanvas" style="width: 800px; height: 1200px;">

                        <!-- Background Image Layer -->
                        <img src="" class="canvas-bg w-100 h-100 object-fit-cover" id="canvasBgImage" alt="Φόντο Κουπονιού">

                        <!-- Elements Container (QR, Text, Badges injected here via JS) -->
                        <div id="elementsContainer" class="w-100 h-100 position-absolute top-0 left-0"></div>

                    </div>

                    <!-- Canvas Controls (Zoom) -->
                    <div class="canvas-controls glass-panel d-flex gap-2">
                        <button class="btn btn-sm btn-light" id="zoomOutBtn" title="Σμίκρυνση"><i class="ri-zoom-out-line"></i></button>
                        <span class="d-flex align-items-center fw-medium small px-2" id="zoomLevelIndicator">100%</span>
                        <button class="btn btn-sm btn-light" id="zoomInBtn" title="Μεγέθυνση"><i class="ri-zoom-in-line"></i></button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Scripts -->
    <!-- jQuery (Required for some plugins and easier DOM manipulation) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Interact.js (For complex Drag & Drop, Resizing) -->
    <script src="https://cdn.jsdelivr.net/npm/interactjs/dist/interact.min.js"></script>

    <!-- SweetAlert2 & Toastr -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <!-- HTML2Canvas / jsPDF (For Exporting - Placeholders for next phases) -->
    <!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script> -->
    <!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script> -->

    <!-- Custom Builder JS Engine -->
    <script src="../assets/js/builder.js"></script>
</body>
</html>
