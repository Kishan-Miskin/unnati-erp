<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Already logged in
if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/' . (isAdmin() ? 'admin' : 'employee') . '/dashboard.php');
    exit;
}

$error = '';
$success = '';

if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out') {
    $success = 'You have been logged out successfully.';
}
if (isset($_GET['timeout'])) {
    $error = 'Your session expired. Please log in again.';
}

// Handle login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username and password are required.';
    } else {
        $user = db()->fetchOne(
            "SELECT id, full_name, username, password, role, status, can_enter_stock, can_apply_discount, max_discount_pct
             FROM users WHERE username = ? LIMIT 1",
            [$username]
        );

        if ($user && $user['status'] === 'active' && password_verify($password, $user['password'])) {
            loginUser($user);
            logActivity('User logged in', 'auth', $user['id'], 'user');
            $redirect = isAdmin() ? 'admin/dashboard.php' : 'employee/dashboard.php';
            header('Location: ' . APP_URL . '/' . $redirect);
            exit;
        } else {
            $error = 'Invalid username or password. Please try again.';
            // Log failed attempt
            logActivity('Failed login attempt for username: ' . $username, 'auth');
        }
    }
}

$businessName = getSetting('business_name', 'Unnatti Enterprises');
$businessTagline = getSetting('business_tagline', 'Quality Products, Trusted Service');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — <?= e($businessName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Poppins', sans-serif;
    min-height: 100vh;
    overflow: hidden;
    background: #0f172a;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
  }

  canvas#bg { position: fixed; inset: 0; z-index: 0; }

  .login-container {
    position: relative;
    z-index: 10;
    width: 100%;
    max-width: 430px;
    padding: 20px;
  }

  .login-card {
    background: rgba(255,255,255,0.06);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 24px;
    padding: 44px 40px;
    box-shadow: 0 32px 64px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.1);
    animation: slideUp 0.5s cubic-bezier(0.34,1.56,0.64,1);
  }
  @keyframes slideUp {
    from { opacity: 0; transform: translateY(30px) scale(0.96); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
  }

  .brand-area {
    text-align: center;
    margin-bottom: 32px;
  }
  .brand-icon-wrap {
    width: 68px; height: 68px;
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    border-radius: 20px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.8rem;
    color: #fff;
    margin-bottom: 16px;
    box-shadow: 0 8px 24px rgba(37,99,235,0.4);
  }
  .brand-name {
    font-size: 1.3rem;
    font-weight: 700;
    color: #f1f5f9;
    display: block;
    letter-spacing: -0.01em;
  }
  .brand-tagline {
    font-size: 0.78rem;
    color: #94a3b8;
    display: block;
    margin-top: 4px;
  }
  .login-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: #94a3b8;
    text-align: center;
    margin-bottom: 24px;
    letter-spacing: 0.06em;
    text-transform: uppercase;
  }

  .form-group { margin-bottom: 18px; }
  .form-label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: #94a3b8;
    margin-bottom: 7px;
    letter-spacing: 0.03em;
  }
  .input-wrap { position: relative; }
  .input-icon {
    position: absolute;
    left: 14px; top: 50%; transform: translateY(-50%);
    color: #64748b;
    font-size: 0.9rem;
    pointer-events: none;
    transition: color 0.2s;
  }
  .form-control {
    width: 100%;
    padding: 12px 14px 12px 40px;
    background: rgba(255,255,255,0.07);
    border: 1.5px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    color: #f1f5f9;
    font-family: 'Poppins', sans-serif;
    font-size: 0.9rem;
    transition: all 0.2s;
    outline: none;
  }
  .form-control:focus {
    border-color: #3b82f6;
    background: rgba(255,255,255,0.1);
    box-shadow: 0 0 0 3px rgba(59,130,246,0.2);
  }
  .form-control::placeholder { color: #475569; }
  .form-control.password-field { padding-right: 44px; }

  .toggle-password {
    position: absolute;
    right: 12px; top: 50%; transform: translateY(-50%);
    background: none; border: none;
    color: #64748b; cursor: pointer;
    font-size: 0.85rem;
    transition: color 0.2s;
    padding: 4px;
  }
  .toggle-password:hover { color: #94a3b8; }

  .btn-login {
    width: 100%;
    padding: 13px;
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    border: none;
    border-radius: 10px;
    color: #fff;
    font-family: 'Poppins', sans-serif;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.25s;
    margin-top: 6px;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    box-shadow: 0 4px 16px rgba(37,99,235,0.3);
  }
  .btn-login:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 24px rgba(37,99,235,0.45);
    background: linear-gradient(135deg, #1d4ed8, #6d28d9);
  }
  .btn-login:active { transform: translateY(0); }

  .alert {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 14px;
    border-radius: 10px;
    margin-bottom: 18px;
    font-size: 0.85rem;
    font-weight: 500;
  }
  .alert-danger { background: rgba(220,38,38,0.15); border: 1px solid rgba(220,38,38,0.3); color: #fca5a5; }
  .alert-success { background: rgba(5,150,105,0.15); border: 1px solid rgba(5,150,105,0.3); color: #6ee7b7; }

  .login-footer {
    text-align: center;
    margin-top: 20px;
  }
  .login-footer a {
    color: #60a5fa;
    font-size: 0.82rem;
    text-decoration: none;
    transition: color 0.2s;
  }
  .login-footer a:hover { color: #93c5fd; }

  .version-badge {
    text-align: center;
    margin-top: 24px;
    font-size: 0.72rem;
    color: rgba(255,255,255,0.2);
  }
</style>
</head>
<body>

<canvas id="bg"></canvas>

<div class="login-container">
  <div class="login-card">
    <div class="brand-area">
      <div class="brand-icon-wrap"><i class="fas fa-store"></i></div>
      <span class="brand-name"><?= e($businessName) ?></span>
      <span class="brand-tagline"><?= e($businessTagline) ?></span>
    </div>
    <div class="login-title"><i class="fas fa-lock" style="margin-right:6px"></i>Sign In to Your Account</div>

    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="" id="loginForm">
      <?= csrfField() ?>

      <div class="form-group">
        <label class="form-label" for="username">Username</label>
        <div class="input-wrap">
          <i class="fas fa-user input-icon"></i>
          <input type="text" id="username" name="username" class="form-control"
                 placeholder="Enter your username" autocomplete="username"
                 value="<?= e($_POST['username'] ?? '') ?>" required autofocus>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <div class="input-wrap">
          <i class="fas fa-lock input-icon"></i>
          <input type="password" id="password" name="password" class="form-control password-field"
                 placeholder="Enter your password" autocomplete="current-password" required>
          <button type="button" class="toggle-password" onclick="togglePwd()" tabindex="-1">
            <i class="fas fa-eye" id="togglePwdIcon"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-login" id="loginBtn">
        <i class="fas fa-sign-in-alt"></i> Sign In
      </button>
    </form>

    <div class="login-footer">
      <a href="forgot_password.php"><i class="fas fa-question-circle"></i> Forgot password?</a>
    </div>
  </div>

  <div class="version-badge">Unnatti ERP v1.0 &nbsp;|&nbsp; Powered by PHP &amp; MySQL</div>
</div>

<script>
// Animated particle background
(function() {
  const canvas = document.getElementById('bg');
  const ctx = canvas.getContext('2d');
  let W, H, particles = [];

  function resize() {
    W = canvas.width = window.innerWidth;
    H = canvas.height = window.innerHeight;
  }
  resize();
  window.addEventListener('resize', resize);

  function Particle() {
    this.x = Math.random() * W;
    this.y = Math.random() * H;
    this.r = Math.random() * 1.8 + 0.4;
    this.vx = (Math.random() - 0.5) * 0.35;
    this.vy = (Math.random() - 0.5) * 0.35;
    this.alpha = Math.random() * 0.5 + 0.1;
  }

  for (let i = 0; i < 90; i++) particles.push(new Particle());

  function draw() {
    ctx.clearRect(0, 0, W, H);

    // Gradient bg
    const grad = ctx.createLinearGradient(0, 0, W, H);
    grad.addColorStop(0, '#0f172a');
    grad.addColorStop(0.5, '#1e3a5f');
    grad.addColorStop(1, '#312e81');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, W, H);

    // Connect nearby particles
    for (let i = 0; i < particles.length; i++) {
      for (let j = i + 1; j < particles.length; j++) {
        const dx = particles[i].x - particles[j].x;
        const dy = particles[i].y - particles[j].y;
        const dist = Math.sqrt(dx*dx + dy*dy);
        if (dist < 120) {
          ctx.beginPath();
          ctx.moveTo(particles[i].x, particles[i].y);
          ctx.lineTo(particles[j].x, particles[j].y);
          ctx.strokeStyle = 'rgba(96,165,250,' + (0.06 * (1 - dist/120)) + ')';
          ctx.lineWidth = 0.5;
          ctx.stroke();
        }
      }
    }

    // Draw particles
    particles.forEach(function(p) {
      ctx.beginPath();
      ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(148,163,184,' + p.alpha + ')';
      ctx.fill();

      p.x += p.vx;
      p.y += p.vy;
      if (p.x < 0 || p.x > W) p.vx *= -1;
      if (p.y < 0 || p.y > H) p.vy *= -1;
    });

    requestAnimationFrame(draw);
  }
  draw();
})();

function togglePwd() {
  const pwd = document.getElementById('password');
  const icon = document.getElementById('togglePwdIcon');
  if (pwd.type === 'password') {
    pwd.type = 'text';
    icon.classList.replace('fa-eye', 'fa-eye-slash');
  } else {
    pwd.type = 'password';
    icon.classList.replace('fa-eye-slash', 'fa-eye');
  }
}

document.getElementById('loginForm').addEventListener('submit', function() {
  const btn = document.getElementById('loginBtn');
  btn.disabled = true;
  btn.innerHTML = '<span style="display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:spin 0.7s linear infinite"></span> Signing in...';
});
</script>
<style>@keyframes spin{to{transform:rotate(360deg)}}</style>
</body>
</html>
