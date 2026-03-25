<?php
// If already logged in, redirect them based on their valid JWT
require_once '../includes/auth_guard.php';

$jwt = get_jwt_from_request();
if ($jwt) {
    $payload = verify_jwt($jwt);
    if ($payload) {
        // Valid token, redirect based on role
        if ($payload['role'] === 'store_staff') {
            header('Location: /store/confirm.php');
            exit;
        } else {
            header('Location: /admin/dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Σύνδεση - Πλατφόρμα Κουπονιών</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Modern Glassmorphism & Gradient Background */
        body {
            background: linear-gradient(135deg, #e0c3fc 0%, #8ec5fc 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
            padding: 3rem 2rem;
            width: 100%;
            max-width: 400px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.2);
        }

        .brand-icon {
            font-size: 3rem;
            color: #4a00e0;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }

        .form-control {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 10px;
            padding: 0.8rem 1rem;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
            transition: all 0.3s;
        }

        .form-control:focus {
            background: #fff;
            border-color: #8ec5fc;
            box-shadow: 0 0 0 0.25rem rgba(142, 197, 252, 0.25);
        }

        .input-group-text {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 10px 0 0 10px;
            color: #6c757d;
        }

        .form-control {
            border-radius: 0 10px 10px 0;
        }

        .btn-primary {
            background: linear-gradient(to right, #8ec5fc, #e0c3fc);
            border: none;
            border-radius: 10px;
            padding: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            color: #333;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .btn-primary:hover {
            background: linear-gradient(to right, #7ab6fa, #d6b2fb);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
            color: #000;
        }

        .btn-primary:active {
            transform: translateY(1px);
        }

        #error-message {
            display: none;
            border-radius: 10px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

    <div class="container d-flex justify-content-center">
        <div class="glass-card text-center">
            <i class="bi bi-qr-code-scan brand-icon"></i>
            <h2 class="mb-4 fw-bold" style="color: #333;">Καλώς Ήλθατε</h2>
            <p class="text-muted mb-4">Συνδεθείτε στην πλατφόρμα διαχείρισης κουπονιών.</p>

            <div id="error-message" class="alert alert-danger shadow-sm mb-3" role="alert">
                <!-- Error text injected via JS -->
            </div>

            <form id="loginForm">
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control" id="email" placeholder="Email" required autocomplete="email">
                </div>

                <div class="input-group mb-4">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="password" placeholder="Κωδικός" required autocomplete="current-password">
                </div>

                <button type="submit" class="btn btn-primary w-100" id="loginBtn">
                    ΣΥΝΔΕΣΗ
                </button>
            </form>

            <div class="mt-4 text-muted" style="font-size: 0.8rem;">
                &copy; <?php echo date('Y'); ?> QR Coupon Platform
            </div>
        </div>
    </div>

    <!-- Bootstrap & Custom JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/login.js"></script>
</body>
</html>