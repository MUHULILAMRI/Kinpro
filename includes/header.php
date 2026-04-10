<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' — ' : '' ?>KinPro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #0f172a;
            --primary-light: #1e293b;
            --accent: #6366f1;
            --accent2: #8b5cf6;
            --accent-light: #818cf8;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --bg: #f8fafc;
            --card: #ffffff;
            --border: #e2e8f0;
            --border-light: #f1f5f9;
            --text: #0f172a;
            --muted: #64748b;
            --sidebar-w: 260px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            line-height: 1.5;
        }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

        /* SIDEBAR */
        .sidebar {
            position: fixed;
            top: 0; left: 0;
            width: var(--sidebar-w);
            height: 100vh;
            background: #0f1422;
            display: flex;
            flex-direction: column;
            z-index: 100;
            border-right: 1px solid rgba(255,255,255,0.04);
        }

        .sidebar-brand {
            padding: 22px 18px 18px;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .brand-icon {
            width: 38px; height: 38px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(99,102,241,0.35);
        }

        .brand-icon img {
            width: 22px;
            height: 22px;
            object-fit: contain;
            display: block;
            filter: brightness(0) invert(1);
        }

        .brand-text {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .brand-text h2 {
            font-size: 17px;
            font-weight: 800;
            color: white;
            letter-spacing: 0.1px;
            line-height: 1.2;
        }

        .brand-text span {
            font-size: 10.5px;
            color: rgba(255,255,255,0.35);
            font-weight: 400;
            line-height: 1.3;
        }

        /* User profile area */
        .sidebar-profile {
            padding: 0 16px 16px;
            margin-bottom: 4px;
        }

        .sidebar-profile-card {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            background: rgba(255,255,255,0.04);
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.06);
        }

        .sidebar-profile-avatar {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 12px;
            font-weight: 700;
            color: white;
            letter-spacing: 0.5px;
        }

        .sidebar-profile-info h4 {
            font-size: 13px;
            font-weight: 600;
            color: rgba(255,255,255,0.9);
            line-height: 1.3;
        }

        .sidebar-profile-info span {
            font-size: 11px;
            color: rgba(255,255,255,0.35);
        }

        .sidebar-nav {
            flex: 1;
            padding: 4px 12px;
            overflow-y: auto;
        }

        .nav-section { margin-bottom: 2px; }

        .nav-label {
            font-size: 10px;
            font-weight: 700;
            color: rgba(255,255,255,0.2);
            text-transform: uppercase;
            letter-spacing: 1.6px;
            padding: 14px 10px 5px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            border-radius: 9px;
            color: rgba(255,255,255,0.45);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            transition: all 0.18s ease;
            margin-bottom: 1px;
            border-left: 2px solid transparent;
        }

        .nav-item:hover {
            color: rgba(255,255,255,0.85);
            background: rgba(255,255,255,0.05);
            border-left-color: rgba(129,140,248,0.3);
        }

        .nav-item.active {
            color: white;
            background: rgba(99,102,241,0.14);
            border-left-color: #818cf8;
        }

        .nav-icon {
            width: 30px; height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            flex-shrink: 0;
            border-radius: 7px;
            background: rgba(255,255,255,0.04);
            color: rgba(255,255,255,0.3);
            transition: all 0.18s ease;
        }

        .nav-item:hover .nav-icon {
            background: rgba(255,255,255,0.07);
            color: rgba(255,255,255,0.65);
        }

        .nav-item.active .nav-icon {
            background: rgba(99,102,241,0.2);
            color: #818cf8;
        }

        .nav-badge {
            margin-left: auto;
            background: var(--danger);
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 20px;
            min-width: 20px;
            text-align: center;
            line-height: 1.6;
        }

        .nav-divider {
            height: 1px;
            background: rgba(255,255,255,0.05);
            margin: 8px 10px;
        }

        .nav-item-logout {
            color: rgba(239,68,68,0.55) !important;
        }
        .nav-item-logout .nav-icon {
            color: rgba(239,68,68,0.45) !important;
            background: rgba(239,68,68,0.06) !important;
        }
        .nav-item-logout:hover {
            color: #ef4444 !important;
            background: rgba(239,68,68,0.08) !important;
            border-left-color: rgba(239,68,68,0.4) !important;
        }
        .nav-item-logout:hover .nav-icon {
            color: #ef4444 !important;
            background: rgba(239,68,68,0.12) !important;
        }

        .sidebar-footer {
            padding: 14px 16px;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        .user-card {
            display: none;
        }

        .user-avatar {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            color: white;
        }

        .user-info h4 {
            font-size: 13px;
            font-weight: 600;
            color: white;
        }

        .user-info span {
            font-size: 11px;
            color: rgba(255,255,255,0.4);
        }

        /* MAIN */
        .main {
            margin-left: var(--sidebar-w);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .topbar {
            background: white;
            padding: 0 24px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .topbar-left h1 { font-size: 18px; font-weight: 700; }
        .topbar-left p { font-size: 12px; color: var(--muted); margin-top: 2px; }

        .topbar-right { display: flex; align-items: center; gap: 10px; }

        .topbar-btn {
            width: 36px; height: 36px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: white;
            color: var(--muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.2s;
            position: relative;
        }

        .topbar-btn:hover { background: var(--bg); color: var(--accent); }

        .notif-dot {
            position: absolute;
            top: 6px; right: 6px;
            width: 6px; height: 6px;
            background: var(--danger);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.7; }
        }

        /* NOTIFICATION DROPDOWN */
        .notif-dropdown {
            position: relative;
        }

        .notif-popup {
            display: none;
            position: absolute;
            right: 0;
            top: calc(100% + 12px);
            width: 360px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15), 0 0 0 1px rgba(0,0,0,0.05);
            z-index: 1000;
            overflow: hidden;
            animation: slideDown 0.3s ease;
        }

        .notif-popup.show {
            display: block;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .notif-header {
            padding: 16px 20px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .notif-header h4 {
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .notif-count {
            font-size: 11px;
            background: rgba(255,255,255,0.2);
            padding: 4px 10px;
            border-radius: 20px;
        }

        .notif-body {
            max-height: 320px;
            overflow-y: auto;
        }

        .notif-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 20px;
            text-decoration: none;
            transition: all 0.2s;
            border-bottom: 1px solid var(--border-light);
        }

        .notif-item:hover {
            background: var(--bg);
        }

        .notif-item:last-child {
            border-bottom: none;
        }

        .notif-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .notif-content {
            flex: 1;
            min-width: 0;
        }

        .notif-content h5 {
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            margin: 0 0 4px 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .notif-content p {
            font-size: 12px;
            color: var(--muted);
            margin: 0 0 6px 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .notif-time {
            font-size: 11px;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .notif-empty {
            padding: 40px 20px;
            text-align: center;
            color: var(--muted);
        }

        .notif-empty i {
            font-size: 40px;
            color: var(--success);
            margin-bottom: 12px;
            display: block;
        }

        .notif-empty p {
            font-size: 13px;
            margin: 0;
        }

        .notif-footer {
            padding: 14px 20px;
            background: var(--bg);
            border-top: 1px solid var(--border);
            text-align: center;
        }

        .notif-footer a {
            font-size: 13px;
            font-weight: 600;
            color: var(--accent);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: gap 0.2s;
        }

        .notif-footer a:hover {
            gap: 10px;
        }

        .content { padding: 24px; flex: 1; }

        /* CARDS */
        .card {
            background: var(--card);
            border-radius: 14px;
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-title {
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title i { color: var(--accent); }
        .card-body { padding: 20px; }

        /* BUTTONS */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: white;
        }

        .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }

        .btn-outline {
            background: white;
            border: 1px solid var(--border);
            color: var(--text);
        }

        .btn-outline:hover { border-color: var(--accent); color: var(--accent); }

        .btn-danger { background: var(--danger); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }

        /* TABLES */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }

        th {
            padding: 12px 16px;
            background: var(--bg);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--muted);
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-light);
            font-size: 13px;
            vertical-align: middle;
        }

        tr:hover td { background: #fafbfc; }
        tr:last-child td { border-bottom: none; }

        /* BADGES */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-excellent { background: #d1fae5; color: #065f46; }
        .badge-good { background: #dbeafe; color: #1e40af; }
        .badge-average { background: #fef3c7; color: #92400e; }
        .badge-poor { background: #fee2e2; color: #991b1b; }
        .status-active { background: #d1fae5; color: #065f46; }
        .status-inactive { background: #fee2e2; color: #991b1b; }

        /* FORMS */
        .form-group { margin-bottom: 16px; }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            color: var(--text);
            background: white;
            transition: border-color 0.2s;
        }

        .form-control:focus { outline: none; border-color: var(--accent); }
        select.form-control { cursor: pointer; }
        textarea.form-control { resize: vertical; min-height: 80px; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        /* ALERTS */
        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        /* STATS */
        .stat-card {
            background: var(--card);
            border-radius: 14px;
            padding: 20px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.05); }

        .stat-icon {
            width: 50px; height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-info h3 { font-size: 26px; font-weight: 800; line-height: 1; }
        .stat-info p { font-size: 13px; color: var(--muted); margin-top: 4px; }
        .stat-change { font-size: 12px; font-weight: 600; margin-top: 4px; }
        .stat-change.up { color: var(--success); }
        .stat-change.down { color: var(--danger); }

        /* AVATAR */
        .avatar {
            width: 40px; height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            color: white;
        }

        /* MODAL */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .modal.show { display: flex; }

        .modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalIn 0.2s ease;
        }

        @keyframes modalIn {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .modal-header {
            padding: 18px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h3 {
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .close-btn {
            width: 30px; height: 30px;
            border-radius: 8px;
            border: none;
            background: var(--bg);
            font-size: 18px;
            cursor: pointer;
            color: var(--muted);
        }

        .close-btn:hover { background: var(--danger); color: white; }

        .modal-footer {
            padding: 16px 20px;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        /* SEARCH */
        .search-box { position: relative; }
        .search-box input { padding-left: 40px; }
        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 13px;
        }

        /* NILAI BAR */
        .nilai-bar {
            width: 100%;
            height: 6px;
            background: var(--border);
            border-radius: 3px;
            overflow: hidden;
        }

        .nilai-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent), var(--accent2));
            border-radius: 3px;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .page-header h2 { font-size: 22px; font-weight: 800; }
        .page-header p { font-size: 13px; color: var(--muted); margin-top: 2px; }

        /* ======= PAGE TRANSITION OVERLAY ======= */
        .page-transition {
            position: fixed;
            inset: 0;
            z-index: 99999;
            pointer-events: none;
            opacity: 0;
            visibility: hidden;
        }
        .page-transition.active {
            opacity: 1;
            visibility: visible;
            pointer-events: all;
        }

        /* Backdrop blur overlay */
        .pt-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.35);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .page-transition.active .pt-backdrop {
            opacity: 1;
        }

        /* Center loader card */
        .pt-loader {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.8);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            padding: 36px 44px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.2), 0 0 0 1px rgba(0,0,0,0.04);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .page-transition.active .pt-loader {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1);
        }

        /* Orbit spinner */
        .pt-orbit {
            position: relative;
            width: 52px;
            height: 52px;
        }
        .pt-orbit-ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 2.5px solid transparent;
        }
        .pt-orbit-ring:nth-child(1) {
            border-top-color: var(--accent);
            animation: ptSpin 1s cubic-bezier(0.5, 0, 0.5, 1) infinite;
        }
        .pt-orbit-ring:nth-child(2) {
            inset: 6px;
            border-right-color: var(--accent2);
            animation: ptSpin 1.4s cubic-bezier(0.5, 0, 0.5, 1) infinite reverse;
        }
        .pt-orbit-ring:nth-child(3) {
            inset: 12px;
            border-bottom-color: #3b82f6;
            animation: ptSpin 0.8s cubic-bezier(0.5, 0, 0.5, 1) infinite;
        }
        .pt-orbit-dot {
            position: absolute;
            inset: 18px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border-radius: 50%;
            animation: ptPulse 1.2s ease-in-out infinite;
        }
        @keyframes ptSpin {
            to { transform: rotate(360deg); }
        }
        @keyframes ptPulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(0.7); opacity: 0.5; }
        }

        /* Text with dots animation */
        .pt-text {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 2px;
        }
        .pt-dots span {
            display: inline-block;
            animation: ptDotBounce 1.4s ease-in-out infinite;
            font-weight: 800;
            color: var(--accent);
        }
        .pt-dots span:nth-child(1) { animation-delay: 0s; }
        .pt-dots span:nth-child(2) { animation-delay: 0.15s; }
        .pt-dots span:nth-child(3) { animation-delay: 0.3s; }
        @keyframes ptDotBounce {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-5px); }
        }

        /* Progress bar inside card */
        .pt-progress {
            width: 140px;
            height: 3px;
            background: var(--border);
            border-radius: 3px;
            overflow: hidden;
        }
        .pt-progress-bar {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--accent), var(--accent2), #3b82f6);
            background-size: 200% 100%;
            border-radius: 3px;
            animation: ptBarFill 1.6s ease forwards, ptBarShimmer 0.8s linear infinite;
        }
        @keyframes ptBarFill {
            0% { width: 0%; }
            30% { width: 40%; }
            60% { width: 70%; }
            90% { width: 90%; }
            100% { width: 100%; }
        }
        @keyframes ptBarShimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* ======= TOP PROGRESS BAR ======= */
        .loading-bar-top {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: transparent;
            z-index: 100000;
            overflow: hidden;
        }
        .loading-bar-top .bar {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--accent), var(--accent2), #3b82f6, var(--accent));
            background-size: 300% 100%;
            animation: barProgress 1.6s ease-out forwards, barShimmer 0.8s linear infinite;
            box-shadow: 0 0 12px var(--accent), 0 0 4px var(--accent2);
            border-radius: 0 2px 2px 0;
        }
        @keyframes barProgress {
            0% { width: 0%; }
            15% { width: 20%; }
            40% { width: 55%; }
            70% { width: 80%; }
            100% { width: 100%; }
        }
        @keyframes barShimmer {
            0% { background-position: 300% 0; }
            100% { background-position: -300% 0; }
        }
        .loading-bar-top.hide {
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        /* Legacy spinner (kept for backward compat, hidden fast) */
        .loading-spinner-mini {
            display: none !important;
        }

        /* ======= CONTENT ENTER ANIMATION ======= */
        .content {
            animation: contentFadeIn 0.5s ease-out;
        }
        @keyframes contentFadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ======= NAV ITEM CLICK RIPPLE ======= */
        .nav-item {
            position: relative;
            overflow: hidden;
        }
        /* (position/overflow already set above, kept for ripple) */
        .nav-item .nav-ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            transform: scale(0);
            animation: navRipple 0.5s ease-out forwards;
            pointer-events: none;
        }
        @keyframes navRipple {
            to { transform: scale(4); opacity: 0; }
        }

        /* TOAST NOTIFICATIONS */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        }

        .toast {
            background: white;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 320px;
            max-width: 420px;
            transform: translateX(120%);
            transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            pointer-events: auto;
            border-left: 4px solid var(--accent);
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.toast-success { border-left-color: var(--success); }
        .toast.toast-error { border-left-color: var(--danger); }
        .toast.toast-warning { border-left-color: var(--warning); }
        .toast.toast-info { border-left-color: var(--info); }

        .toast-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .toast-success .toast-icon { background: #d1fae5; color: var(--success); }
        .toast-error .toast-icon { background: #fee2e2; color: var(--danger); }
        .toast-warning .toast-icon { background: #fef3c7; color: var(--warning); }
        .toast-info .toast-icon { background: #dbeafe; color: var(--info); }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 600;
            font-size: 14px;
            color: var(--text);
            margin-bottom: 2px;
        }

        .toast-message {
            font-size: 13px;
            color: var(--muted);
        }

        .toast-close {
            background: none;
            border: none;
            color: var(--muted);
            cursor: pointer;
            padding: 4px;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .toast-close:hover {
            background: var(--bg);
            color: var(--text);
        }

        .toast-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent), var(--accent2));
            border-radius: 0 0 0 12px;
            animation: toastProgress 4s linear forwards;
        }

        @keyframes toastProgress {
            from { width: 100%; }
            to { width: 0%; }
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
            .sidebar.show { transform: translateX(0); }
            .main { margin-left: 0; }
            .form-grid { grid-template-columns: 1fr; }
            .toast-container { left: 10px; right: 10px; }
            .toast { min-width: auto; max-width: 100%; }
            .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 99; }
            .sidebar-overlay.show { display: block; }
            .hamburger { display: flex !important; }
        }
        @media (min-width: 769px) {
            .sidebar-overlay { display: none !important; }
            .hamburger { display: none !important; }
        }
        .hamburger {
            display: none;
            width: 36px; height: 36px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: white;
            color: var(--text);
            cursor: pointer;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: all 0.2s;
        }
        .hamburger:hover { background: var(--bg); color: var(--accent); }
    </style>
</head>
<body>

<!-- Page Transition Overlay -->
<div class="page-transition" id="pageTransition">
    <div class="pt-backdrop"></div>
    <div class="pt-loader">
        <div class="pt-orbit">
            <div class="pt-orbit-ring"></div>
            <div class="pt-orbit-ring"></div>
            <div class="pt-orbit-ring"></div>
            <div class="pt-orbit-dot"></div>
        </div>
        <div class="pt-text">
            <span>Memuat</span>
            <span class="pt-dots"><span>.</span><span>.</span><span>.</span></span>
        </div>
        <div class="pt-progress">
            <div class="pt-progress-bar"></div>
        </div>
    </div>
</div>

<!-- Loading Bar (Top) -->
<div class="loading-bar-top" id="loadingBar">
    <div class="bar"></div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<script>
// ======= LOADING BAR & OVERLAY CLEANUP =======
window.addEventListener('load', function() {
    setTimeout(function() {
        var lb = document.getElementById('loadingBar');
        if (lb) lb.classList.add('hide');
        var pt = document.getElementById('pageTransition');
        if (pt) pt.classList.remove('active');
    }, 400);
});

// Always clean overlay on pageshow (handles bfcache back/forward navigation)
window.addEventListener('pageshow', function() {
    var pt = document.getElementById('pageTransition');
    if (pt) pt.classList.remove('active');
});

// Safety: force-remove overlay after 3 seconds no matter what (prevents stuck state)
setTimeout(function() {
    var pt = document.getElementById('pageTransition');
    if (pt) pt.classList.remove('active');
}, 3000);

// ======= PAGE TRANSITION SYSTEM =======
function initPageTransitions() {
    var transition = document.getElementById('pageTransition');
    if (!transition) return;

    function showTransition(href) {
        transition.classList.add('active');
        var bar = transition.querySelector('.pt-progress-bar');
        if (bar) {
            bar.style.animation = 'none';
            bar.offsetHeight; // force reflow
            bar.style.animation = '';
        }
        setTimeout(function() { window.location.href = href; }, 350);
    }

    function addRipple(link, e) {
        var rect = link.getBoundingClientRect();
        var ripple = document.createElement('span');
        ripple.className = 'nav-ripple';
        var size = Math.max(rect.width, rect.height);
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
        ripple.style.top  = (e.clientY - rect.top  - size / 2) + 'px';
        link.appendChild(ripple);
        setTimeout(function() { ripple.remove(); }, 500);
    }

    // Nav item clicks — Init AFTER DOMContentLoaded so sidebar elements exist in DOM
    document.querySelectorAll('a.nav-item[href*="?page="]').forEach(function(link) {
        link.addEventListener('click', function(e) {
            if (this.classList.contains('active')) return; // same page, skip
            e.preventDefault();
            addRipple(this, e);
            showTransition(this.getAttribute('href'));
        });
    });

    // Notification popup links
    document.querySelectorAll('a.notif-item[href*="?page="], .notif-footer a[href*="?page="]').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            showTransition(this.getAttribute('href'));
        });
    });
}

// Init AFTER full DOM is ready so querySelectorAll finds all sidebar nav items
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPageTransitions);
} else {
    initPageTransitions();
}

// Toast notification system
function showToast(type, title, message, duration = 4000) {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    const icons = {
        success: 'fa-check',
        error: 'fa-times',
        warning: 'fa-exclamation',
        info: 'fa-info'
    };
    
    toast.innerHTML = `
        <div class="toast-icon">
            <i class="fas ${icons[type] || icons.info}"></i>
        </div>
        <div class="toast-content">
            <div class="toast-title">${title}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
        <div class="toast-progress" style="animation-duration: ${duration}ms"></div>
    `;
    
    container.appendChild(toast);
    
    // Trigger animation
    setTimeout(() => toast.classList.add('show'), 10);
    
    // Auto remove
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 400);
    }, duration);
}

// Error handler
window.onerror = function(msg, url, lineNo, columnNo, error) {
    showToast('error', 'Terjadi Kesalahan', msg.substring(0, 100));
    return false;
};

// Convert alert boxes to toast notifications
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        let type = 'info';
        let title = 'Info';
        
        if (alert.classList.contains('alert-success')) {
            type = 'success';
            title = 'Berhasil';
        } else if (alert.classList.contains('alert-danger') || alert.classList.contains('alert-error')) {
            type = 'error';
            title = 'Error';
        } else if (alert.classList.contains('alert-warning')) {
            type = 'warning';
            title = 'Perhatian';
        }
        
        const message = alert.textContent.trim();
        if (message) {
            showToast(type, title, message);
        }
        alert.remove();
    });
});
</script>

<?php
$currentPage = $_GET['page'] ?? 'dashboard';
$newAduanCount = 0;
$newIzinCount = 0;
$userIzinCount = 0;
$userRole = $_SESSION['user_role'] ?? 'user';
$isAdmin = ($userRole === 'admin');

try {
    $db = getDB();
    $pendingStr = 'pending';
    $stmtH1 = $db->prepare("SELECT COUNT(*) as c FROM pengaduan WHERE status=?");
    $stmtH1->bind_param('s', $pendingStr);
    $stmtH1->execute();
    $resultH1 = $stmtH1->get_result();
    if ($resultH1) $newAduanCount = $resultH1->fetch_assoc()['c'];

    $stmtH2 = $db->prepare("SELECT COUNT(*) as c FROM izin WHERE status=?");
    $stmtH2->bind_param('s', $pendingStr);
    $stmtH2->execute();
    $resultH2 = $stmtH2->get_result();
    if ($resultH2) $newIzinCount = $resultH2->fetch_assoc()['c'];

    if (!$isAdmin && isset($_SESSION['user_id'])) {
        $uid = (int)$_SESSION['user_id'];
        $stmtH3 = $db->prepare("SELECT COUNT(*) as c FROM izin WHERE id_pegawai=?");
        $stmtH3->bind_param('i', $uid);
        $stmtH3->execute();
        $resultH3 = $stmtH3->get_result();
        if ($resultH3) $userIzinCount = $resultH3->fetch_assoc()['c'];
    }
} catch (Exception $e) {
    // Ignore errors silently
}

// Check for flash messages
$flashMsg = getFlashMessage();
?>

<?php if ($flashMsg): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    showToast('<?= $flashMsg['type'] ?>', '<?= addslashes($flashMsg['title']) ?>', '<?= addslashes($flashMsg['message']) ?>');
});
</script>
<?php endif; ?>

<div class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">
            <div class="brand-icon">
                <img src="<?= getBaseUrl() ?>uploads/pu-logo.png" alt="KP" onerror="this.style.display='none';this.parentElement.innerHTML='<i class=\'fas fa-bolt\' style=\'color:#fff;font-size:16px\'></i>'">
            </div>
            <div class="brand-text">
                <h2>KinPro</h2>
                <span>Sistem Kinerja</span>
            </div>
        </div>
    </div>

    <div class="sidebar-profile">
        <div class="sidebar-profile-card">
            <div class="sidebar-profile-avatar"><?= getUserInitials() ?></div>
            <div class="sidebar-profile-info">
                <h4><?= htmlspecialchars(strlen($_SESSION['user_name'] ?? 'User') > 16 ? substr($_SESSION['user_name'] ?? 'User', 0, 16) . '…' : ($_SESSION['user_name'] ?? 'User')) ?></h4>
                <span><?= $isAdmin ? 'Administrator' : 'Pegawai' ?></span>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-label">Menu Utama</div>
            <a href="?page=dashboard" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-house"></i></span>
                Dashboard
            </a>
        </div>

        <?php if ($isAdmin): ?>
        <div class="nav-section">
            <div class="nav-label">Manajemen</div>
            <a href="?page=pegawai" class="nav-item <?= $currentPage === 'pegawai' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-users"></i></span>
                Data Pegawai
            </a>
            <a href="?page=penilaian" class="nav-item <?= $currentPage === 'penilaian' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-chart-line"></i></span>
                Penilaian Kinerja
            </a>
            <a href="?page=aduan" class="nav-item <?= $currentPage === 'aduan' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-flag"></i></span>
                Pengaduan
                <?php if ($newAduanCount > 0): ?>
                    <span class="nav-badge"><?= $newAduanCount ?></span>
                <?php endif; ?>
            </a>
            <a href="?page=kelola_izin" class="nav-item <?= $currentPage === 'kelola_izin' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-calendar-check"></i></span>
                Kelola Izin
                <?php if ($newIzinCount > 0): ?>
                    <span class="nav-badge"><?= $newIzinCount ?></span>
                <?php endif; ?>
            </a>
            <a href="?page=rating" class="nav-item <?= $currentPage === 'rating' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-ranking-star"></i></span>
                Rating Pegawai
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-label">Personal</div>
            <a href="?page=profil" class="nav-item <?= $currentPage === 'profil' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-circle-user"></i></span>
                Profil Saya
            </a>
            <a href="?page=penilaian_saya" class="nav-item <?= $currentPage === 'penilaian_saya' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-medal"></i></span>
                Penilaian Saya
            </a>
            <a href="?page=izin" class="nav-item <?= $currentPage === 'izin' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-calendar-plus"></i></span>
                Pengajuan Izin
            </a>
        </div>

        <?php else: ?>
        <div class="nav-section">
            <div class="nav-label">Personal</div>
            <a href="?page=profil" class="nav-item <?= $currentPage === 'profil' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-circle-user"></i></span>
                Profil Saya
            </a>
            <a href="?page=penilaian_saya" class="nav-item <?= $currentPage === 'penilaian_saya' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-medal"></i></span>
                Penilaian Saya
            </a>
            <a href="?page=izin" class="nav-item <?= $currentPage === 'izin' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-calendar-plus"></i></span>
                Pengajuan Izin
                <?php if ($userIzinCount > 0): ?>
                    <span class="nav-badge" style="background:var(--info)"><?= $userIzinCount ?></span>
                <?php endif; ?>
            </a>
            <a href="?page=aduan_saya" class="nav-item <?= $currentPage === 'aduan_saya' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-comment-dots"></i></span>
                Pengaduan
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-label">Referensi</div>
            <a href="?page=rating" class="nav-item <?= $currentPage === 'rating' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fas fa-ranking-star"></i></span>
                Rating Pegawai
            </a>
        </div>
        <?php endif; ?>

        <div class="nav-section" style="margin-top: 4px">
            <div class="nav-divider"></div>
            <a href="?logout=1" class="nav-item nav-item-logout" onclick="return confirm('Yakin ingin keluar?')">
                <span class="nav-icon"><i class="fas fa-arrow-right-from-bracket"></i></span>
                Keluar
            </a>
        </div>
    </nav>

    <div class="sidebar-footer">
        <?php
        $userName = $_SESSION['user_name'] ?? 'User';
        $userJabatan = $_SESSION['user_jabatan'] ?? ($isAdmin ? 'Administrator' : 'Pegawai');
        $initials = getUserInitials($userName);
        ?>
    </div>
</div>
