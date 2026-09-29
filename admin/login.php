<?php
require_once '../config.php';

// If already logged in, redirect to dashboard
if (isAdminLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

 $error = '';
 $showPassword = false;
 $logoutSuccess = isset($_GET['success']) && $_GET['success'] === 'logout';
 $rememberMe = isset($_COOKIE['admin_remember_token']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? 1 : 0;
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password';
    } else {
        try {
            $stmt = db()->prepare("SELECT * FROM admin_users WHERE username = ? AND status = 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($password, $admin['password'])) {
                // Regenerate session ID for security
                session_regenerate_id(true);
                
                // Set session data
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_role'] = $admin['role'];
                $_SESSION['login_time'] = date('Y-m-d H:i:s');
                $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
                $_SESSION['last_activity'] = time();
                
                // Set remember me token if requested
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
                    
                    $stmt = db()->prepare("UPDATE admin_users SET remember_token = ?, remember_token_expiry = ? WHERE id = ?");
                    $stmt->execute([$token, $expiry, $admin['id']]);
                    
                    setcookie('admin_remember_token', $token, strtotime('+30 days'), '/', '', true, true);
                }
                
                // Update last login
                $updateStmt = db()->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$admin['id']]);
                
                // Log successful login
                logActivity($admin['id'], 'LOGIN', 'ADMIN', $admin['id'], 
                    "Admin logged in from {$_SERVER['REMOTE_ADDR']} using {$_SERVER['HTTP_USER_AGENT']}");
                
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid username or password';
                logActivity(0, 'LOGIN_FAILED', 'ADMIN', null, "Failed login attempt for username: $username from {$_SERVER['REMOTE_ADDR']}");
            }
        } catch (Exception $e) {
            $error = 'Database error. Please try again.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kaldis Coffee - ECA Branch Admin Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        * { font-family: 'Plus Jakarta Sans', sans-serif; }
        
        body {
            background: linear-gradient(135deg, #FAF6F0 0%, #F5EFEB 40%, #EFE5DB 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        .bg-pattern {
            position: absolute;
            inset: 0;
            background-image: radial-gradient(rgba(180, 83, 9, 0.07) 1.5px, transparent 0);
            background-size: 28px 28px;
            pointer-events: none;
        }
        
        /* Floating coffee elements */
        .floating-icon {
            position: absolute;
            font-size: 38px;
            opacity: 0.18;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.05));
            user-select: none;
            pointer-events: none;
        }
        
        .float-1 { animation: floatAnim1 14s infinite ease-in-out; }
        .float-2 { animation: floatAnim2 18s infinite ease-in-out; }
        .float-3 { animation: floatAnim3 16s infinite ease-in-out; }
        .float-4 { animation: floatAnim4 12s infinite ease-in-out; }
        
        @keyframes floatAnim1 {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-25px) rotate(8deg); }
        }
        
        @keyframes floatAnim2 {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-35px) rotate(-10deg); }
        }
        
        @keyframes floatAnim3 {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(6deg); }
        }

        @keyframes floatAnim4 {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-30px) rotate(-6deg); }
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(245, 230, 215, 0.9);
            border-radius: 28px;
            box-shadow: 0 25px 60px -15px rgba(67, 34, 18, 0.08), 0 10px 20px -5px rgba(0, 0, 0, 0.03);
            max-width: 440px;
            width: 100%;
            overflow: hidden;
            position: relative;
            z-index: 10;
        }
        
        .logo-wrapper {
            width: 90px;
            height: 90px;
            border-radius: 24px;
            background: #ffffff;
            margin: 0 auto 20px;
            padding: 8px;
            border: 1px solid rgba(217, 119, 6, 0.2);
            box-shadow: 0 10px 25px -5px rgba(217, 119, 6, 0.15);
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        .logo-wrapper:hover {
            transform: scale(1.06) rotate(2deg);
            box-shadow: 0 15px 30px -5px rgba(217, 119, 6, 0.25);
        }
        
        .logo-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .form-group {
            position: relative;
            margin-bottom: 20px;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px 18px 15px 48px;
            background: #FAF8F5;
            border: 1.5px solid #EAE3D9;
            border-radius: 16px;
            font-size: 15px;
            font-weight: 500;
            color: #292524;
            transition: all 0.25s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #D97706;
            background: #FFFFFF;
            box-shadow: 0 0 0 4px rgba(217, 119, 6, 0.12);
        }
        
        .form-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #A8A29E;
            font-size: 16px;
            pointer-events: none;
            transition: all 0.25s ease;
        }
        
        .form-group input:focus ~ .form-icon {
            color: #D97706;
        }
        
        .password-toggle {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #A8A29E;
            cursor: pointer;
            transition: all 0.25s ease;
            font-size: 15px;
        }
        
        .password-toggle:hover {
            color: #D97706;
        }
        
        .login-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #D97706 0%, #B45309 100%);
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px -5px rgba(217, 119, 6, 0.35);
            position: relative;
            overflow: hidden;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 28px -5px rgba(217, 119, 6, 0.45);
            background: linear-gradient(135deg, #E58312 0%, #C25E0C 100%);
        }
        
        .login-btn:active {
            transform: translateY(0);
        }
        
        .error-message {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            border-left: 4px solid #EF4444;
            padding: 14px 16px;
            border-radius: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            animation: shake 0.4s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }
        
        .success-message {
            background: #F0FDF4;
            border: 1px solid #BBF7D0;
            border-left: 4px solid #10B981;
            padding: 14px 16px;
            border-radius: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2.5px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="bg-pattern"></div>
    
    <!-- Floating coffee ambiance elements -->
    <div class="floating-icon float-1" style="top: 12%; left: 8%;">☕</div>
    <div class="floating-icon float-2" style="top: 75%; right: 10%;">🥐</div>
    <div class="floating-icon float-3" style="top: 22%; right: 14%;">🫘</div>
    <div class="floating-icon float-4" style="bottom: 18%; left: 12%;">🥪</div>
    <div class="floating-icon float-1" style="top: 60%; left: 6%;">🍰</div>
    <div class="floating-icon float-2" style="top: 18%; right: 6%;">☕</div>
    
    <div class="login-card" data-aos="fade-up" data-aos-duration="700">
        <!-- Header -->
        <div class="text-center pt-8 px-6 pb-2">
            <div class="logo-wrapper">
                <img src="<?php echo htmlspecialchars(getLogoUrl() ?: LOGO_PATH); ?>" alt="Kaldis Coffee Logo">
            </div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-100/80 border border-amber-200 text-amber-900 text-[11px] font-bold uppercase tracking-wider mb-2">
                <i class="fas fa-shield-alt text-amber-600"></i> Admin Portal
            </span>
            <h1 class="text-2xl font-extrabold text-stone-900 tracking-tight">Kaldis Coffee</h1>
            <p class="text-stone-500 text-xs mt-1 font-medium">ECA Branch • Office Ordering Administration</p>
        </div>
        
        <!-- Form Area -->
        <div class="p-6 sm:p-8 pt-4">
            <!-- Success Message -->
            <?php if ($logoutSuccess): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle text-emerald-600 text-base mr-3"></i>
                    <p class="text-emerald-800 text-xs font-semibold">Logged out successfully and securely.</p>
                </div>
            <?php endif; ?>
            
            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle text-red-500 text-base mr-3"></i>
                    <p class="text-red-700 text-xs font-semibold"><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="loginForm">
                <!-- Username Input -->
                <div class="form-group">
                    <input type="text" 
                           name="username" 
                           id="username" 
                           required
                           placeholder="Username"
                           autocomplete="username"
                           value="<?php echo $rememberMe ? 'admin' : ''; ?>">
                    <i class="fas fa-user form-icon"></i>
                </div>
                
                <!-- Password Input -->
                <div class="form-group">
                    <input type="password" 
                           name="password" 
                           id="password" 
                           required
                           placeholder="Password"
                           autocomplete="current-password">
                    <i class="fas fa-lock form-icon"></i>
                    <i class="fas fa-eye password-toggle" id="togglePassword" onclick="togglePassword()"></i>
                </div>
                
                <!-- Remember Me & Options -->
                <div class="flex items-center justify-between mb-6 text-xs">
                    <label class="flex items-center gap-2 text-stone-600 font-medium cursor-pointer select-none">
                        <input type="checkbox" 
                               name="remember" 
                               id="remember" 
                               class="w-4 h-4 rounded border-stone-300 text-amber-600 focus:ring-amber-500 accent-amber-600"
                               <?php echo $rememberMe ? 'checked' : ''; ?>>
                        <span>Remember credentials</span>
                    </label>
                </div>
                
                <!-- Login Button -->
                <button type="submit" 
                        id="loginBtn"
                        class="login-btn">
                    <span id="btnText" class="flex items-center justify-center gap-2">
                        <i class="fas fa-sign-in-alt"></i> Sign In to Dashboard
                    </span>
                    <div id="btnSpinner" class="loading-spinner"></div>
                </button>
            </form>
            
            <!-- Security Badges -->
            <div class="flex items-center justify-center gap-4 py-4 my-2 border-y border-stone-100 text-[11px] text-stone-400 font-medium">
                <span class="flex items-center gap-1.5"><i class="fas fa-shield-halved text-amber-600"></i> SSL 256-bit</span>
                <span class="w-1 h-1 rounded-full bg-stone-300"></span>
                <span class="flex items-center gap-1.5"><i class="fas fa-key text-amber-600"></i> Encrypted</span>
                <span class="w-1 h-1 rounded-full bg-stone-300"></span>
                <span class="flex items-center gap-1.5"><i class="fas fa-user-check text-amber-600"></i> Protected</span>
            </div>

            <!-- Footer -->
            <div class="text-center pt-2 text-xs text-stone-400 space-y-1">
                <p class="font-medium text-stone-500">Kaldis Coffee ECA Management System</p>
                <p class="text-[11px] text-stone-400">© <?php echo date('Y'); ?> All Rights Reserved</p>
            </div>
        </div>
    </div>
    
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 700,
            once: true
        });
        
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('togglePassword');
            
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
        
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('loginBtn');
            const btnText = document.getElementById('btnText');
            const btnSpinner = document.getElementById('btnSpinner');
            
            submitBtn.disabled = true;
            btnText.style.display = 'none';
            btnSpinner.style.display = 'block';
            submitBtn.style.opacity = '0.85';
        });
    </script>
</body>
</html>