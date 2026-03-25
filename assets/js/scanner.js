/**
 * assets/js/scanner.js
 *
 * Logic for initializing the html5-qrcode camera scanner, parsing scanned URLs,
 * pausing/resuming the camera to prevent AJAX floods, and handling the manual fallback.
 */

document.addEventListener('DOMContentLoaded', function () {

    const qrCodeRegionId = "qr-reader";
    const manualForm = document.getElementById('manualConfirmForm');
    const manualInput = document.getElementById('manualUuid');
    const confirmBtn = document.getElementById('confirmBtn');

    // Check if the html5-qrcode library is loaded
    if (typeof Html5QrcodeScanner === 'undefined') {
        console.error("html5-qrcode library is missing.");
        Swal.fire('Σφάλμα', 'Η βιβλιοθήκη σάρωσης δεν φορτώθηκε.', 'error');
        return;
    }

    // Initialize the scanner
    // fps: frames per second, qrbox: size of scanning box (250px square)
    const html5QrcodeScanner = new Html5QrcodeScanner(
        qrCodeRegionId,
        {
            fps: 10,
            qrbox: { width: 250, height: 250 },
            aspectRatio: 1.0,
            showTorchButtonIfSupported: true,
            supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA]
        },
        /* verbose= */ false
    );

    // State flag to prevent multiple rapid scans
    let isProcessing = false;

    /**
     * Extracts UUID from a scanned URL.
     * Often the QR code is the full landing page URL like: https://domain.com/public/landing.php?uuid=XYZ
     * If the QR is just raw text, it returns the text.
     */
    function extractUuidFromUrl(scannedText) {
        try {
            // Check if it's a valid URL
            const url = new URL(scannedText);
            // Get the uuid query parameter
            const params = new URLSearchParams(url.search);
            if (params.has('uuid')) {
                return params.get('uuid');
            }
        } catch (e) {
            // Not a URL, return the raw text assuming it's the raw UUID
        }
        return scannedText.trim();
    }

    /**
     * Makes the AJAX call to confirm the coupon.
     */
    function processCoupon(uuid) {
        // Validation format (basic 36-char check, though backend does strict checking)
        if (!uuid || uuid.length < 32) {
            Swal.fire('Σφάλμα', 'Μη έγκυρη μορφή κωδικού.', 'warning').then(() => {
                resumeScanner();
            });
            return;
        }

        // Show loading state
        Swal.fire({
            title: 'Επεξεργασία...',
            text: 'Παρακαλώ περιμένετε',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // AJAX POST to confirmation endpoint
        fetch('/api/confirm_coupon.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ uuid: uuid })
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(errData => {
                    // confirm_coupon.php returns error messages in the 'message' field, not 'error'
                    throw new Error(errData.message || 'Άγνωστο σφάλμα διακομιστή.');
                }).catch(() => {
                    // Fallback if response isn't JSON
                    throw new Error('Σφάλμα διακομιστή.');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Determine icon based on the returned text or status
                let iconType = 'success';
                let titleText = 'Επιτυχής Εξαργύρωση!';

                if (data.message && data.message.includes('ήδη εξαργυρωθεί')) {
                    iconType = 'info';
                    titleText = 'Προσοχή';
                }

                Swal.fire({
                    icon: iconType,
                    title: titleText,
                    html: `
                        <div class="mb-3">${data.message}</div>
                        ${data.data && data.data.coupon_id ? `<div class="small text-muted">ID: ${data.data.coupon_id}</div>` : ''}
                    `,
                    confirmButtonColor: '#3b82f6',
                    confirmButtonText: 'Επόμενο Κουπόνι'
                }).then(() => {
                    resumeScanner();
                    manualInput.value = ''; // clear input
                });
            } else {
                throw new Error(data.message || 'Αποτυχία εξαργύρωσης.');
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Σφάλμα',
                text: error.message,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Κλείσιμο'
            }).then(() => {
                resumeScanner();
            });
        });
    }

    /**
     * Resumes the camera scanning process.
     */
    function resumeScanner() {
        isProcessing = false;
        // The scanner actually continues running in the background, but our isProcessing flag
        // determines if we act upon the decoded text.
        // Note: html5-qrcode does have a pause/resume API in its newer versions, but boolean gating is foolproof.
    }

    /**
     * Callback when a QR code is successfully decoded by the camera.
     */
    function onScanSuccess(decodedText, decodedResult) {
        if (isProcessing) return; // Prevent AJAX flooding
        isProcessing = true;

        console.log(`Scan result: ${decodedText}`);

        // Optional: Play a tiny beep sound
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioCtx.createOscillator();
            const gainNode = audioCtx.createGain();
            oscillator.connect(gainNode);
            gainNode.connect(audioCtx.destination);
            oscillator.type = 'sine';
            oscillator.frequency.value = 800; // Hz
            gainNode.gain.setValueAtTime(0.1, audioCtx.currentTime);
            oscillator.start();
            oscillator.stop(audioCtx.currentTime + 0.1);
        } catch(e) { /* ignore audio errors */ }

        // Extract and Process
        const uuid = extractUuidFromUrl(decodedText);

        // Auto-fill manual input just for visual feedback
        manualInput.value = uuid;

        processCoupon(uuid);
    }

    function onScanFailure(error) {
        // Handle scan failure, usually better to ignore and keep scanning.
        // Console logging every frame failure gets noisy.
    }

    // Start the camera
    html5QrcodeScanner.render(onScanSuccess, onScanFailure);

    // Manual Form Submission Hook
    if (manualForm) {
        manualForm.addEventListener('submit', function(e) {
            e.preventDefault();

            if (isProcessing) return;
            isProcessing = true;

            const uuid = manualInput.value.trim();
            if (!uuid) {
                isProcessing = false;
                return;
            }

            // Disable button visually
            const originalBtnText = confirmBtn.innerHTML;
            confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            confirmBtn.disabled = true;

            // Wait a tiny bit then process, passing a callback to re-enable button
            setTimeout(() => {
                processCoupon(uuid);
                confirmBtn.innerHTML = originalBtnText;
                confirmBtn.disabled = false;
            }, 100);
        });
    }
});