<?php
/**
 * includes/header.php
 *
 * Global UI Shell Header for the Admin Panel.
 * Contains the opening HTML tags, `<head>`, metadata, and shared CSS links.
 */

// If $pageTitle is not set in the parent file, fallback to a default
$pageTitle = $pageTitle ?? 'Πίνακας Ελέγχου';
?>
<!DOCTYPE html>
<html lang="el" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | Πλατφόρμα Κουπονιών</title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Remix Icons -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <!-- DataTables CSS (Optional, but safe to include globally for admin) -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">

    <!-- Toastr CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">

    <!-- Global Layout CSS -->
    <style>
        :root {
            --bg-main: #f4f6f9;
            --text-primary: #1e293b;
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.4);
            --card-shadow: 0 4px 20px rgba(0,0,0,0.05);
            --primary-color: #3b82f6;
            --sidebar-width: 250px;
        }

        [data-theme="dark"] {
            --bg-main: #0f172a;
            --text-primary: #f8fafc;
            --glass-bg: rgba(30, 41, 59, 0.85);
            --glass-border: rgba(255, 255, 255, 0.1);
            --card-shadow: 0 4px 20px rgba(0,0,0,0.4);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-main);
            color: var(--text-primary);
            margin: 0;
            padding: 0;
            display: flex; /* Flex layout to hold sidebar and main content side-by-side */
            min-height: 100vh;
        }

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 1rem;
            box-shadow: var(--card-shadow);
        }

        /* Sidebar Styling */
        #sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-right: 1px solid var(--glass-border);
            box-shadow: 4px 0 24px rgba(0,0,0,0.02);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--primary-color);
            border-bottom: 1px solid var(--glass-border);
        }

        .nav-link {
            color: var(--text-primary);
            padding: 0.8rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }

        .nav-link i {
            font-size: 1.2rem;
            color: #64748b;
            transition: all 0.2s ease;
        }

        .nav-link:hover {
            background-color: rgba(59, 130, 246, 0.05);
            color: var(--primary-color);
        }

        .nav-link:hover i {
            color: var(--primary-color);
        }

        .nav-link.active {
            background-color: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
            border-left-color: var(--primary-color);
        }

        .nav-link.active i {
            color: var(--primary-color);
        }

        /* Main Content Wrapper */
        #content-wrapper {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            padding: 2rem;
            transition: all 0.3s;
        }

        /* Mobile Adjustments */
        @media (max-width: 768px) {
            #sidebar {
                transform: translateX(-100%);
            }
            #sidebar.show {
                transform: translateX(0);
            }
            #content-wrapper {
                margin-left: 0;
                width: 100%;
                padding: 1rem;
            }
        }

        /* Top Bar inside content */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--glass-border);
        }

        .action-btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 0.25rem;
            transition: all 0.2s;
        }

        .action-btn:hover {
            transform: translateY(-2px);
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
    </style>
</head>
<body>