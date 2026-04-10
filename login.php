<?php
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';
$rateLimited = false;
$waitTime = 0;

// Handle expired session
if (isset($_GET['expired'])) {
    $error = 'Sesi Anda telah berakhir. Silakan login kembali.';
}

// Get client IP for rate limiting
$clientIP = getClientIP();

// Handle login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Sesi tidak valid. Silakan refresh halaman.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Check rate limit
        $rateCheck = checkRateLimit($clientIP, 5, 900); // 5 attempts per 15 minutes
        
        if (!$rateCheck['allowed']) {
            $rateLimited = true;
            $waitTime = $rateCheck['wait_seconds'];
            $waitMinutes = ceil($waitTime / 60);
            $error = "Terlalu banyak percobaan login. Coba lagi dalam {$waitMinutes} menit.";
        } elseif (empty($username) || empty($password)) {
            $error = 'Username dan password harus diisi!';
        } else {
            $result = attemptLogin($username, $password);
            if ($result['success']) {
                // Reset rate limit on successful login
                resetRateLimit($clientIP);
                header("Location: index.php");
                exit;
            } else {
                // Increment failed attempts
                incrementRateLimit($clientIP);
                $remaining = $rateCheck['remaining'] - 1;
                $error = $result['message'];
                if ($remaining > 0 && $remaining <= 3) {
                    $error .= " (Sisa {$remaining} percobaan)";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — KinPro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        /* ======= PRELOADER ======= */
        .preloader {
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: opacity 0.6s ease, visibility 0.6s ease;
        }
        .preloader.hide {
            opacity: 0;
            visibility: hidden;
        }
        .preloader-logo {
            position: relative;
            width: 100px;
            height: 100px;
            margin-bottom: 30px;
        }
        .preloader-ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 3px solid transparent;
            border-top-color: #667eea;
            animation: preloaderSpin 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
        }
        .preloader-ring:nth-child(2) {
            inset: 8px;
            border-top-color: #764ba2;
            animation-delay: -0.3s;
        }
        .preloader-ring:nth-child(3) {
            inset: 16px;
            border-top-color: #f093fb;
            animation-delay: -0.6s;
        }
        .preloader-icon {
            position: absolute;
            inset: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            animation: preloaderPulse 1.5s ease-in-out infinite;
        }
        @keyframes preloaderSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @keyframes preloaderPulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.15); opacity: 0.7; }
        }
        .preloader-text {
            color: white;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 4px;
            text-transform: uppercase;
            overflow: hidden;
        }
        .preloader-text span {
            display: inline-block;
            animation: preloaderLetter 1.5s ease-in-out infinite;
        }
        .preloader-text span:nth-child(1) { animation-delay: 0s; }
        .preloader-text span:nth-child(2) { animation-delay: 0.1s; }
        .preloader-text span:nth-child(3) { animation-delay: 0.2s; }
        .preloader-text span:nth-child(4) { animation-delay: 0.3s; }
        .preloader-text span:nth-child(5) { animation-delay: 0.4s; }
        .preloader-text span:nth-child(6) { animation-delay: 0.5s; }
        @keyframes preloaderLetter {
            0%, 100% { transform: translateY(0); opacity: 0.5; }
            50% { transform: translateY(-10px); opacity: 1; }
        }
        .preloader-bar {
            width: 200px;
            height: 3px;
            background: rgba(255,255,255,0.1);
            border-radius: 3px;
            margin-top: 24px;
            overflow: hidden;
        }
        .preloader-bar-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #667eea, #f093fb, #667eea);
            background-size: 200% 100%;
            border-radius: 3px;
            animation: preloaderBarFill 1.8s ease forwards, preloaderBarShimmer 1s linear infinite;
        }
        @keyframes preloaderBarFill {
            0% { width: 0%; }
            50% { width: 70%; }
            100% { width: 100%; }
        }
        @keyframes preloaderBarShimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* ======= MAIN PAGE ======= */
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            background: linear-gradient(-45deg, #0f0c29, #302b63, #24243e, #1a1a2e);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            overflow: hidden;
        }
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* ======= FLOATING PARTICLES ======= */
        .particles {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }
        .particle {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
            animation: particleFloat linear infinite;
        }
        @keyframes particleFloat {
            0% {
                transform: translateY(100vh) rotate(0deg) scale(0);
                opacity: 0;
            }
            10% {
                opacity: 1;
                transform: translateY(90vh) rotate(36deg) scale(1);
            }
            90% {
                opacity: 0.6;
            }
            100% {
                transform: translateY(-10vh) rotate(360deg) scale(0.5);
                opacity: 0;
            }
        }

        /* ======= GLOWING ORBS ======= */
        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            z-index: 0;
            pointer-events: none;
        }
        .orb-1 {
            width: 400px;
            height: 400px;
            background: rgba(102, 126, 234, 0.3);
            top: -100px;
            left: -100px;
            animation: orbFloat1 8s ease-in-out infinite;
        }
        .orb-2 {
            width: 350px;
            height: 350px;
            background: rgba(118, 75, 162, 0.25);
            bottom: -100px;
            right: -100px;
            animation: orbFloat2 10s ease-in-out infinite;
        }
        .orb-3 {
            width: 250px;
            height: 250px;
            background: rgba(240, 147, 251, 0.15);
            top: 50%;
            left: 30%;
            animation: orbFloat3 12s ease-in-out infinite;
        }
        @keyframes orbFloat1 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(80px, 60px) scale(1.1); }
            66% { transform: translate(-40px, 100px) scale(0.9); }
        }
        @keyframes orbFloat2 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(-60px, -80px) scale(1.15); }
            66% { transform: translate(50px, -40px) scale(0.85); }
        }
        @keyframes orbFloat3 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(60px, -60px) scale(1.2); }
        }

        /* ======= LEFT SIDE - BRANDING ======= */
        .brand-side {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 60px;
            color: white;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .brand-content {
            position: relative;
            z-index: 2;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 50px;
            opacity: 0;
            animation: fadeSlideRight 0.8s ease forwards 0.5s;
        }

        .brand-icon {
            width: 64px;
            height: 64px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            animation: iconGlow 3s ease-in-out infinite;
        }
        @keyframes iconGlow {
            0%, 100% { box-shadow: 0 0 20px rgba(102, 126, 234, 0.3); }
            50% { box-shadow: 0 0 40px rgba(102, 126, 234, 0.6), 0 0 60px rgba(118, 75, 162, 0.3); }
        }

        .brand-text h1 {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .brand-text span {
            font-size: 13px;
            opacity: 0.7;
        }

        .brand-headline {
            opacity: 0;
            animation: fadeSlideRight 0.8s ease forwards 0.7s;
        }
        .brand-headline h2 {
            font-size: 44px;
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #ffffff 0%, #c4b5fd 50%, #f0abfc 100%);
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: textShimmer 4s linear infinite;
        }
        @keyframes textShimmer {
            0% { background-position: 0% center; }
            100% { background-position: 200% center; }
        }

        .brand-headline p {
            font-size: 17px;
            opacity: 0.8;
            line-height: 1.7;
            max-width: 420px;
        }

        .brand-features {
            margin-top: 50px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 14px;
            opacity: 0;
            animation: fadeSlideRight 0.6s ease forwards;
            padding: 10px 16px;
            border-radius: 14px;
            transition: all 0.3s ease;
        }
        .feature-item:hover {
            background: rgba(255,255,255,0.08);
            transform: translateX(8px);
        }
        .feature-item:nth-child(1) { animation-delay: 0.9s; }
        .feature-item:nth-child(2) { animation-delay: 1.05s; }
        .feature-item:nth-child(3) { animation-delay: 1.2s; }
        .feature-item:nth-child(4) { animation-delay: 1.35s; }

        @keyframes fadeSlideRight {
            from { opacity: 0; transform: translateX(-30px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .feature-icon {
            width: 44px;
            height: 44px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: all 0.3s ease;
        }
        .feature-item:hover .feature-icon {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.1) rotate(5deg);
        }

        .feature-text {
            font-size: 15px;
            font-weight: 500;
        }

        /* ======= RIGHT SIDE - LOGIN FORM ======= */
        .login-side {
            width: 520px;
            min-height: 100vh;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(40px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            position: relative;
            z-index: 1;
            box-shadow: -30px 0 60px rgba(0,0,0,0.3);
            opacity: 0;
            animation: loginSlideIn 0.9s cubic-bezier(0.16, 1, 0.3, 1) forwards 0.3s;
        }
        @keyframes loginSlideIn {
            from { opacity: 0; transform: translateX(80px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .login-container {
            width: 100%;
            max-width: 380px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 36px;
        }

        .login-avatar {
            width: 72px;
            height: 72px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: white;
            opacity: 0;
            animation: avatarBounce 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) forwards 0.8s;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        @keyframes avatarBounce {
            from { opacity: 0; transform: scale(0) rotate(-180deg); }
            to { opacity: 1; transform: scale(1) rotate(0deg); }
        }

        .login-header h2 {
            font-size: 26px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 8px;
            opacity: 0;
            animation: fadeSlideUp 0.6s ease forwards 1s;
        }

        .login-header p {
            color: #64748b;
            font-size: 15px;
            opacity: 0;
            animation: fadeSlideUp 0.6s ease forwards 1.1s;
        }

        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-group {
            margin-bottom: 20px;
            opacity: 0;
            animation: fadeSlideUp 0.6s ease forwards;
        }
        .form-group:nth-child(2) { animation-delay: 1.2s; }
        .form-group:nth-child(3) { animation-delay: 1.35s; }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper > i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 16px;
            transition: all 0.3s ease;
            pointer-events: none;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            font-family: inherit;
            color: #1e293b;
            background: #f8fafc;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.12), 0 4px 20px rgba(102, 126, 234, 0.1);
            transform: translateY(-1px);
        }

        .form-control:focus + i,
        .input-wrapper:focus-within > i {
            color: #667eea;
            transform: translateY(-50%) scale(1.15);
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            font-size: 18px;
            padding: 4px;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            transition: all 0.3s ease;
        }

        .password-toggle i {
            position: static;
            transform: none;
        }

        .password-toggle:hover {
            color: #667eea;
            transform: translateY(-50%) scale(1.15);
        }

        .form-extra {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            font-size: 14px;
            opacity: 0;
            animation: fadeSlideUp 0.6s ease forwards 1.45s;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            color: #475569;
            transition: color 0.2s;
        }
        .remember-me:hover { color: #667eea; }

        .remember-me input {
            width: 18px;
            height: 18px;
            accent-color: #667eea;
            cursor: pointer;
        }

        .forgot-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-size: 200% 200%;
            border: none;
            border-radius: 14px;
            color: white;
            font-size: 16px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            overflow: hidden;
            opacity: 0;
            animation: fadeSlideUp 0.6s ease forwards 1.55s, btnGradient 4s ease infinite 2s;
        }
        @keyframes btnGradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.25), transparent);
            transition: left 0.6s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.45), 0 5px 15px rgba(118, 75, 162, 0.3);
        }

        .btn-login:active {
            transform: translateY(0) scale(0.98);
        }

        /* Button loading state */
        .btn-login.loading {
            pointer-events: none;
        }
        .btn-login.loading .btn-text { opacity: 0; }
        .btn-login.loading .btn-spinner { display: flex; }
        .btn-spinner {
            display: none;
            position: absolute;
            align-items: center;
            gap: 8px;
        }
        .btn-spinner-dot {
            width: 8px;
            height: 8px;
            background: white;
            border-radius: 50%;
            animation: spinnerBounce 1.4s ease-in-out infinite;
        }
        .btn-spinner-dot:nth-child(1) { animation-delay: 0s; }
        .btn-spinner-dot:nth-child(2) { animation-delay: 0.16s; }
        .btn-spinner-dot:nth-child(3) { animation-delay: 0.32s; }
        @keyframes spinnerBounce {
            0%, 80%, 100% { transform: scale(0.4); opacity: 0.4; }
            40% { transform: scale(1); opacity: 1; }
        }

        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: alertShake 0.5s ease;
        }

        @keyframes alertShake {
            0% { opacity: 0; transform: translateX(-20px); }
            20% { opacity: 1; transform: translateX(10px); }
            40% { transform: translateX(-8px); }
            60% { transform: translateX(5px); }
            80% { transform: translateX(-2px); }
            100% { transform: translateX(0); }
        }

        .alert-danger {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #dc2626;
            border: 1px solid #fca5a5;
        }

        .alert-success {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            color: #16a34a;
            border: 1px solid #86efac;
        }

        .login-footer {
            text-align: center;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 13px;
            opacity: 0;
            animation: fadeSlideUp 0.6s ease forwards 1.7s;
        }

        .login-footer a {
            color: #667eea;
            font-weight: 600;
            text-decoration: none;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        /* Demo credentials box */
        .demo-box {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 1px solid #bae6fd;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .demo-box h4 {
            color: #0369a1;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .demo-box p {
            color: #0c4a6e;
            font-size: 12px;
            margin: 4px 0;
        }

        .demo-box code {
            background: rgba(255,255,255,0.7);
            padding: 2px 8px;
            border-radius: 4px;
            font-family: 'Fira Code', monospace;
            font-size: 11px;
        }

        /* ======= CONNECTION LINES ======= */
        .grid-lines {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            opacity: 0.04;
            background-image:
                linear-gradient(rgba(255,255,255,0.5) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.5) 1px, transparent 1px);
            background-size: 80px 80px;
            animation: gridMove 20s linear infinite;
        }
        @keyframes gridMove {
            0% { background-position: 0 0; }
            100% { background-position: 80px 80px; }
        }

        /* ======= RIPPLE EFFECT ======= */
        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(102, 126, 234, 0.3);
            animation: rippleExpand 0.6s ease-out forwards;
            pointer-events: none;
        }
        @keyframes rippleExpand {
            from { width: 0; height: 0; opacity: 1; }
            to { width: 200px; height: 200px; opacity: 0; }
        }

        /* ======= SUCCESS ANIMATION ======= */
        .login-success-overlay {
            position: fixed;
            inset: 0;
            z-index: 9998;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.4s ease;
        }
        .login-success-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        .success-checkmark {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        .success-checkmark i {
            font-size: 36px;
            color: white;
            animation: successPop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards 0.2s;
            opacity: 0;
            transform: scale(0);
        }
        @keyframes successPop {
            to { opacity: 1; transform: scale(1); }
        }
        .success-text {
            color: white;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 18px;
            font-weight: 600;
            opacity: 0;
            animation: fadeSlideUp 0.5s ease forwards 0.5s;
        }

        /* ======= RESPONSIVE ======= */
        @media (max-width: 1024px) {
            .brand-side {
                display: none;
            }
            .login-side {
                width: 100%;
                background: rgba(255, 255, 255, 0.9);
            }
        }

        @media (max-width: 480px) {
            .login-side {
                padding: 24px;
            }
            .brand-headline h2 {
                font-size: 32px;
            }
        }
    </style>
</head>
<body>
    <!-- PRELOADER -->
    <div class="preloader" id="preloader">
        <div class="preloader-logo">
            <div class="preloader-ring"></div>
            <div class="preloader-ring"></div>
            <div class="preloader-ring"></div>
            <div class="preloader-icon"><i class="fas fa-chart-line"></i></div>
        </div>
        <div class="preloader-text">
            <span>K</span><span>i</span><span>n</span><span>P</span><span>r</span><span>o</span>
        </div>
        <div class="preloader-bar">
            <div class="preloader-bar-fill"></div>
        </div>
    </div>

    <!-- BACKGROUND EFFECTS -->
    <div class="grid-lines"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>
    <div class="particles" id="particles"></div>

    <!-- SUCCESS OVERLAY -->
    <div class="login-success-overlay" id="successOverlay">
        <div class="success-checkmark"><i class="fas fa-check"></i></div>
        <div class="success-text">Login Berhasil! Mengalihkan...</div>
    </div>

    <!-- Left Side - Branding -->
    <div class="brand-side">
        <div class="brand-content">
            <div class="brand-logo">
                <div class="brand-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="brand-text">
                    <h1>KinPro</h1>
                    <span>Sistem Kinerja Pegawai Profesional</span>
                </div>
            </div>

            <div class="brand-headline">
                <h2>Kelola Kinerja<br>Lebih Efisien</h2>
                <p>Platform modern untuk monitoring, penilaian, dan pengembangan kinerja pegawai secara terintegrasi.</p>
            </div>

            <div class="brand-features">
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <span class="feature-text">Manajemen Data Pegawai</span>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <span class="feature-text">Penilaian Kinerja Berkala</span>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <span class="feature-text">Pengajuan Izin & Cuti Online</span>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <span class="feature-text">Keamanan Data Terjamin</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Side - Login Form -->
    <div class="login-side">
        <div class="login-container">
            <div class="login-header">
                <div class="login-avatar">
                    <i class="fas fa-user-lock"></i>
                </div>
                <h2>Selamat Datang!</h2>
                <p>Silakan masuk ke akun Anda untuk melanjutkan</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <?= csrfField() ?>
                <div class="form-group">
                    <label for="username">Email, Username, atau NIP</label>
                    <div class="input-wrapper">
                        <input type="text" 
                               id="username" 
                               name="username" 
                               class="form-control" 
                               placeholder="Masukkan email, username, atau NIP"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               autocomplete="username"
                               required>
                        <i class="fas fa-user"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-control" 
                               placeholder="Masukkan password"
                               autocomplete="current-password"
                               required>
                        <i class="fas fa-lock"></i>
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="form-extra">
                    <label class="remember-me">
                        <input type="checkbox" name="remember">
                        Ingat saya
                    </label>
                </div>

                <button type="submit" class="btn-login" id="btnLogin">
                    <span class="btn-text"><i class="fas fa-sign-in-alt"></i> Masuk</span>
                    <div class="btn-spinner">
                        <div class="btn-spinner-dot"></div>
                        <div class="btn-spinner-dot"></div>
                        <div class="btn-spinner-dot"></div>
                    </div>
                </button>
            </form>

            <div class="login-footer">
                <p>©Balai Pengembangan Kompetensi PU Wilayah VIII Makassar <?= date('Y') ?> </p>
            </div>
        </div>
    </div>

    <script>
        // ======= PRELOADER =======
        window.addEventListener('load', function() {
            setTimeout(function() {
                document.getElementById('preloader').classList.add('hide');
            }, 2000);
        });

        // ======= PARTICLE SYSTEM =======
        (function createParticles() {
            var container = document.getElementById('particles');
            var colors = [
                'rgba(102, 126, 234, 0.6)',
                'rgba(118, 75, 162, 0.5)',
                'rgba(240, 147, 251, 0.4)',
                'rgba(165, 180, 252, 0.5)',
                'rgba(196, 181, 253, 0.4)'
            ];
            for (var i = 0; i < 30; i++) {
                var particle = document.createElement('div');
                particle.className = 'particle';
                var size = Math.random() * 6 + 2;
                particle.style.width = size + 'px';
                particle.style.height = size + 'px';
                particle.style.background = colors[Math.floor(Math.random() * colors.length)];
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDuration = (Math.random() * 15 + 10) + 's';
                particle.style.animationDelay = (Math.random() * 10) + 's';
                container.appendChild(particle);
            }
        })();

        // ======= PASSWORD TOGGLE =======
        function togglePassword() {
            var passwordInput = document.getElementById('password');
            var toggleIcon = document.getElementById('toggleIcon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // ======= INPUT FOCUS EFFECTS =======
        document.querySelectorAll('.form-control').forEach(function(input) {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
            });
        });

        // ======= BUTTON RIPPLE EFFECT =======
        document.querySelector('.btn-login').addEventListener('mousedown', function(e) {
            var rect = this.getBoundingClientRect();
            var ripple = document.createElement('div');
            ripple.className = 'ripple';
            ripple.style.left = (e.clientX - rect.left - 100) + 'px';
            ripple.style.top = (e.clientY - rect.top - 100) + 'px';
            this.appendChild(ripple);
            setTimeout(function() { ripple.remove(); }, 600);
        });

        // ======= FORM SUBMIT - LOADING STATE =======
        document.querySelector('form').addEventListener('submit', function() {
            var btn = document.getElementById('btnLogin');
            btn.classList.add('loading');
        });

        // ======= INTERACTIVE TILT ON LOGIN CARD (subtle) =======
        (function() {
            var card = document.querySelector('.login-container');
            var side = document.querySelector('.login-side');
            if (!card || !side) return;
            side.addEventListener('mousemove', function(e) {
                var rect = side.getBoundingClientRect();
                var x = (e.clientX - rect.left) / rect.width - 0.5;
                var y = (e.clientY - rect.top) / rect.height - 0.5;
                card.style.transform = 'perspective(1000px) rotateY(' + (x * 3) + 'deg) rotateX(' + (-y * 3) + 'deg)';
            });
            side.addEventListener('mouseleave', function() {
                card.style.transform = 'perspective(1000px) rotateY(0deg) rotateX(0deg)';
                card.style.transition = 'transform 0.5s ease';
            });
            side.addEventListener('mouseenter', function() {
                card.style.transition = 'transform 0.1s ease';
            });
        })();
    </script>
</body>
</html>
