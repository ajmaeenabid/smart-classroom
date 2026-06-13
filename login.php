<?php
// =============================================
// Smart Classroom — Login (login.php)
// =============================================
require_once __DIR__ . '/config/db.php';

if (isLoggedIn()) {
    $role = userRole();
    redirect(BASE_URL . "/dashboard/{$role}.php");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user']    = $user;
            redirect(BASE_URL . "/dashboard/{$user['role']}.php");
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Smart Classroom System – Sign in to your account">
  <title>Sign In — Smart Classroom</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:         #050508;
      --surface:    #0c0c12;
      --card-bg:    rgba(255,255,255,0.04);
      --border:     rgba(255,255,255,0.08);
      --primary:    #6366f1;
      --primary-h:  #818cf8;
      --purple:     #8b5cf6;
      --purple-h:   #a78bfa;
      --text:       #e5e4f0;
      --text-muted: rgba(229,228,240,0.45);
      --input-bg:   rgba(0,0,0,0.3);
      --radius:     1rem;
      --radius-sm:  0.5rem;
    }

    html, body {
      min-height: 100vh;
      font-family: 'Inter', sans-serif;
      background: var(--bg);
      color: var(--text);
      overflow-x: hidden;
    }

    /* ─── Animated Background ─── */
    .bg-canvas {
      position: fixed;
      inset: 0;
      z-index: 0;
      overflow: hidden;
      pointer-events: none;
    }

    .orb {
      position: absolute;
      border-radius: 50%;
      filter: blur(90px);
      animation: orbFloat linear infinite;
      will-change: transform, opacity;
    }

    .orb-1 {
      width: 520px; height: 520px;
      background: radial-gradient(circle, rgba(99,102,241,0.55) 0%, rgba(99,102,241,0) 70%);
      top: -10%; left: -8%;
      animation-duration: 18s;
      animation-name: orb1Move;
    }
    .orb-2 {
      width: 420px; height: 420px;
      background: radial-gradient(circle, rgba(139,92,246,0.5) 0%, rgba(139,92,246,0) 70%);
      bottom: -5%; right: -8%;
      animation-duration: 22s;
      animation-name: orb2Move;
    }
    .orb-3 {
      width: 300px; height: 300px;
      background: radial-gradient(circle, rgba(59,130,246,0.4) 0%, rgba(59,130,246,0) 70%);
      top: 35%; left: 30%;
      animation-duration: 15s;
      animation-name: orb3Move;
    }
    .orb-4 {
      width: 220px; height: 220px;
      background: radial-gradient(circle, rgba(236,72,153,0.35) 0%, rgba(236,72,153,0) 70%);
      top: 60%; left: 55%;
      animation-duration: 19s;
      animation-name: orb4Move;
    }
    .orb-5 {
      width: 350px; height: 350px;
      background: radial-gradient(circle, rgba(16,185,129,0.25) 0%, rgba(16,185,129,0) 70%);
      top: 10%; right: 20%;
      animation-duration: 25s;
      animation-name: orb5Move;
    }

    @keyframes orb1Move {
      0%   { transform: translate(0,0)   scale(1);    opacity: 0.7; }
      25%  { transform: translate(80px,60px) scale(1.12); opacity: 1;   }
      50%  { transform: translate(50px,120px) scale(0.9); opacity: 0.6; }
      75%  { transform: translate(-40px,80px) scale(1.08); opacity: 0.9; }
      100% { transform: translate(0,0)   scale(1);    opacity: 0.7; }
    }
    @keyframes orb2Move {
      0%   { transform: translate(0,0)    scale(1);    opacity: 0.7; }
      30%  { transform: translate(-70px,-50px) scale(1.15); opacity: 1;   }
      60%  { transform: translate(-30px,-100px) scale(0.88); opacity: 0.55; }
      80%  { transform: translate(50px,-60px) scale(1.1); opacity: 0.85; }
      100% { transform: translate(0,0)    scale(1);    opacity: 0.7; }
    }
    @keyframes orb3Move {
      0%   { transform: translate(0,0)  scale(1);    opacity: 0.5; }
      20%  { transform: translate(60px,-80px) scale(1.2); opacity: 0.8; }
      50%  { transform: translate(-50px,60px) scale(0.85); opacity: 0.4; }
      75%  { transform: translate(40px,90px) scale(1.05); opacity: 0.7; }
      100% { transform: translate(0,0)  scale(1);    opacity: 0.5; }
    }
    @keyframes orb4Move {
      0%   { transform: translate(0,0)   scale(1);    opacity: 0.6; }
      35%  { transform: translate(-80px,40px) scale(1.18); opacity: 0.9; }
      65%  { transform: translate(60px,-70px) scale(0.8); opacity: 0.45; }
      85%  { transform: translate(-30px,-40px) scale(1.1); opacity: 0.75; }
      100% { transform: translate(0,0)   scale(1);    opacity: 0.6; }
    }
    @keyframes orb5Move {
      0%   { transform: translate(0,0)   scale(1);    opacity: 0.4; }
      40%  { transform: translate(-60px,100px) scale(1.1); opacity: 0.7; }
      70%  { transform: translate(90px,50px) scale(0.9); opacity: 0.3; }
      100% { transform: translate(0,0)   scale(1);    opacity: 0.4; }
    }

    /* ─── Layout ─── */
    .page-wrapper {
      position: relative;
      z-index: 1;
      min-height: 100vh;
      display: flex;
    }

    /* ─── Left Hero Panel ─── */
    .hero-panel {
      flex: 1.1;
      display: none;
      flex-direction: column;
      justify-content: center;
      padding: 4rem 4rem 4rem 13%;
      position: relative;
    }
    @media (min-width: 900px) { .hero-panel { display: flex; } }

    .badge {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.4rem 1rem;
      background: rgba(255,255,255,0.06);
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 999px;
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.5);
      margin-bottom: 2rem;
    }
    .badge i { color: var(--purple-h); }

    .hero-title {
      font-size: clamp(3rem, 5vw, 5.5rem);
      font-weight: 900;
      line-height: 1.0;
      letter-spacing: -0.04em;
      color: #fff;
      margin-bottom: 1.5rem;
    }
    .hero-title .line {
      display: block;
      opacity: 0;
      transform: translateY(24px);
      animation: lineFadeUp 0.75s cubic-bezier(0.22,1,0.36,1) forwards;
    }
    .hero-title .line:nth-child(1) { animation-delay: 0.1s; }
    .hero-title .line:nth-child(2) { animation-delay: 0.28s; }
    .hero-title .line:nth-child(3) { animation-delay: 0.44s; }
    @keyframes lineFadeUp {
      to { opacity: 1; transform: translateY(0); }
    }
    .hero-title .glow {
      color: var(--purple-h);
      text-shadow: 0 0 40px rgba(139,92,246,0.6);
    }

    .hero-sub {
      font-size: 1.05rem;
      color: rgba(255,255,255,0.45);
      line-height: 1.7;
      max-width: 400px;
      margin-bottom: 2.5rem;
    }

    .feature-list { display: flex; flex-direction: column; gap: 1rem; }
    .feature-item {
      display: flex;
      align-items: center;
      gap: 0.875rem;
      font-size: 0.875rem;
      color: rgba(255,255,255,0.55);
    }
    .feature-icon {
      width: 36px; height: 36px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.8rem;
      flex-shrink: 0;
    }
    .fi-1 { background: rgba(99,102,241,0.18); color: #818cf8; }
    .fi-2 { background: rgba(16,185,129,0.18); color: #34d399; }
    .fi-3 { background: rgba(245,158,11,0.18); color: #fbbf24; }
    .fi-4 { background: rgba(236,72,153,0.18); color: #f472b6; }

    .avatar-row { display: flex; align-items: center; gap: 1rem; margin-top: 2.5rem; }
    .avatars { display: flex; }
    .avatars img {
      width: 38px; height: 38px;
      border-radius: 50%;
      border: 2px solid var(--bg);
      margin-left: -10px;
      object-fit: cover;
    }
    .avatars img:first-child { margin-left: 0; }
    .avatar-text { font-size: 0.8rem; color: rgba(255,255,255,0.35); font-weight: 500; }

    /* ─── Right Auth Panel ─── */
    .auth-panel {
      flex: 0.9;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem;
      position: relative;
    }

    .glass-card {
      width: 100%;
      max-width: 440px;
      background: linear-gradient(
        135deg,
        rgba(255,255,255,0.06) 0%,
        rgba(255,255,255,0.02) 100%
      );
      backdrop-filter: blur(28px);
      -webkit-backdrop-filter: blur(28px);
      border: 1px solid rgba(255,255,255,0.09);
      border-radius: 2rem;
      padding: 2.75rem;
      position: relative;
      box-shadow:
        0 40px 100px -20px rgba(0,0,0,0.8),
        inset 0 0 0 1px rgba(255,255,255,0.05);
      animation: cardIn 0.6s cubic-bezier(0.34,1.56,0.64,1) both;
    }

    .glass-card::after {
      content: '';
      position: absolute;
      inset: -1px;
      border-radius: inherit;
      padding: 1px;
      background: linear-gradient(
        135deg,
        rgba(139,92,246,0.35),
        transparent 40%,
        transparent 60%,
        rgba(99,102,241,0.35)
      );
      -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
      mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
      -webkit-mask-composite: xor;
      mask-composite: exclude;
      pointer-events: none;
    }

    @keyframes cardIn {
      from { opacity: 0; transform: translateY(20px) scale(0.97); }
      to   { opacity: 1; transform: translateY(0)    scale(1); }
    }

    .card-header { margin-bottom: 2rem; }
    .card-title {
      font-size: 1.85rem;
      font-weight: 800;
      color: #fff;
      letter-spacing: -0.03em;
      margin-bottom: 0.35rem;
    }
    .card-sub {
      font-size: 0.855rem;
      color: var(--text-muted);
      font-weight: 400;
    }

    /* ─── Error Banner ─── */
    .error-banner {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      background: rgba(239,68,68,0.1);
      border: 1px solid rgba(239,68,68,0.3);
      border-radius: var(--radius-sm);
      padding: 0.75rem 1rem;
      font-size: 0.82rem;
      color: #f87171;
      margin-bottom: 1.25rem;
      animation: fadeIn 0.3s ease;
    }
    @keyframes fadeIn { from { opacity:0; transform:translateY(-4px); } to { opacity:1; transform:none; } }

    /* ─── Form ─── */
    .form-group { margin-bottom: 1.25rem; }
    .form-label {
      display: block;
      font-size: 0.68rem;
      font-weight: 700;
      letter-spacing: 0.15em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.38);
      margin-bottom: 0.5rem;
      margin-left: 0.25rem;
    }
    .input-wrap { position: relative; }
    .input-icon {
      position: absolute;
      left: 0.875rem;
      top: 50%;
      transform: translateY(-50%);
      color: rgba(255,255,255,0.2);
      font-size: 0.8rem;
      pointer-events: none;
      transition: color 0.3s;
    }
    .form-input {
      width: 100%;
      height: 52px;
      padding: 0 3rem 0 2.5rem;
      background: var(--input-bg);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 0.85rem;
      color: var(--text);
      font-size: 0.9rem;
      font-family: inherit;
      outline: none;
      transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
      box-shadow: inset 0 2px 5px rgba(0,0,0,0.35);
    }
    .form-input::placeholder { color: rgba(255,255,255,0.18); }
    .form-input:focus {
      border-color: rgba(167,139,250,0.55);
      background: rgba(0,0,0,0.4);
      box-shadow:
        0 0 0 1px rgba(167,139,250,0.4),
        0 0 18px rgba(139,92,246,0.2),
        inset 0 2px 5px rgba(0,0,0,0.35);
    }
    .form-input:focus + .input-icon,
    .input-wrap:focus-within .input-icon { color: var(--purple-h); }
    .input-suffix {
      position: absolute;
      right: 0.875rem;
      top: 50%;
      transform: translateY(-50%);
      color: rgba(255,255,255,0.2);
      font-size: 0.8rem;
      transition: color 0.3s;
      cursor: pointer;
      background: none;
      border: none;
      padding: 0;
      line-height: 1;
    }
    .input-suffix:hover { color: rgba(255,255,255,0.5); }

    .form-row-between {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 0.5rem;
    }
    .forgot-link {
      font-size: 0.68rem;
      font-weight: 700;
      color: var(--purple-h);
      text-decoration: none;
      letter-spacing: 0.05em;
      transition: color 0.2s;
    }
    .forgot-link:hover { color: #fff; }

    /* ─── Button ─── */
    .btn-primary {
      width: 100%;
      height: 52px;
      background: linear-gradient(135deg, #4f46e5, #7c3aed);
      border: none;
      border-radius: 0.85rem;
      color: #fff;
      font-size: 0.95rem;
      font-weight: 700;
      font-family: inherit;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
      box-shadow: 0 0 22px rgba(99,102,241,0.45);
      margin-top: 0.5rem;
      letter-spacing: 0.02em;
    }
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 0 35px rgba(99,102,241,0.65), 0 8px 20px rgba(0,0,0,0.4);
    }
    .btn-primary:active { transform: translateY(0); }
    .btn-primary:disabled {
      opacity: 0.65;
      cursor: not-allowed;
      transform: none;
    }

    /* ─── Divider ─── */
    .divider {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin: 1.5rem 0;
      color: rgba(255,255,255,0.15);
      font-size: 0.65rem;
      font-weight: 800;
      letter-spacing: 0.3em;
      text-transform: uppercase;
    }
    .divider::before, .divider::after {
      content: '';
      flex: 1;
      height: 1px;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.07), transparent);
    }

    /* ─── Google Button ─── */
    .btn-google {
      width: 100%;
      height: 50px;
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 0.85rem;
      color: rgba(255,255,255,0.7);
      font-size: 0.875rem;
      font-weight: 600;
      font-family: inherit;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.75rem;
      transition: all 0.3s;
    }
    .btn-google:hover {
      background: rgba(255,255,255,0.07);
      border-color: rgba(255,255,255,0.16);
    }
    .google-icon { display: flex; }

    /* ─── Footer Link ─── */
    .auth-footer {
      text-align: center;
      margin-top: 1.75rem;
      font-size: 0.82rem;
      color: var(--text-muted);
    }
    .auth-footer a {
      color: var(--purple-h);
      font-weight: 700;
      text-decoration: none;
      margin-left: 0.25rem;
      transition: color 0.2s;
    }
    .auth-footer a:hover { color: #fff; }

    /* ─── Spinner ─── */
    @keyframes spin { to { transform: rotate(360deg); } }
    .fa-spin { animation: spin 0.8s linear infinite; }

    /* ─── Mobile header ─── */
    .mobile-brand {
      display: none;
      text-align: center;
      margin-bottom: 1.5rem;
    }
    .mobile-brand h2 {
      font-size: 1.6rem;
      font-weight: 800;
      color: #fff;
      letter-spacing: -0.04em;
    }
    .mobile-brand h2 span { color: var(--purple-h); }
    @media (max-width: 899px) { .mobile-brand { display: block; } }

    /* ─── Demo Creds ─── */
    .demo-creds {
      background: rgba(99,102,241,0.08);
      border: 1px solid rgba(99,102,241,0.2);
      border-radius: 0.6rem;
      padding: 0.75rem 1rem;
      font-size: 0.75rem;
      color: rgba(255,255,255,0.45);
      margin-bottom: 1rem;
      cursor: pointer;
      transition: all 0.3s;
    }
    .demo-creds:hover {
      background: rgba(99,102,241,0.12);
      border-color: rgba(99,102,241,0.35);
    }
    .demo-creds strong { color: var(--purple-h); }

    /* ─── Footer ─── */
    .site-footer {
      position: relative;
      z-index: 5;
      background: rgba(5,5,8,0.92);
      backdrop-filter: blur(24px);
      -webkit-backdrop-filter: blur(24px);
      border-top: 1px solid rgba(255,255,255,0.06);
      padding: 3rem 0 1.5rem;
      margin-top: auto;
    }
    .footer-inner {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 2.5rem;
    }
    .footer-top {
      display: grid;
      grid-template-columns: 1.8fr 1fr 1fr 1fr 1fr;
      gap: 2rem;
      padding-bottom: 2.5rem;
      border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    @media (max-width: 900px) {
      .footer-top { grid-template-columns: 1fr 1fr; }
      .footer-brand { grid-column: 1 / -1; }
    }
    @media (max-width: 520px) {
      .footer-top { grid-template-columns: 1fr; }
    }
    .footer-brand-name {
      font-size: 1.15rem;
      font-weight: 800;
      color: #fff;
      letter-spacing: -0.03em;
      margin-bottom: 0.6rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .footer-brand-name span { color: var(--purple-h); }
    .footer-brand-desc {
      font-size: 0.82rem;
      color: rgba(255,255,255,0.35);
      line-height: 1.7;
      max-width: 240px;
      margin-bottom: 1.25rem;
    }
    .footer-socials {
      display: flex;
      gap: 0.6rem;
    }
    .social-btn {
      width: 34px; height: 34px;
      border-radius: 8px;
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.08);
      color: rgba(255,255,255,0.4);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.78rem;
      text-decoration: none;
      transition: all 0.25s;
    }
    .social-btn:hover {
      background: rgba(139,92,246,0.15);
      border-color: rgba(139,92,246,0.4);
      color: var(--purple-h);
      transform: translateY(-2px);
    }
    .footer-col-title {
      font-size: 0.7rem;
      font-weight: 800;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.55);
      margin-bottom: 1rem;
    }
    .footer-links {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 0.6rem;
    }
    .footer-links a {
      font-size: 0.83rem;
      color: rgba(255,255,255,0.35);
      text-decoration: none;
      transition: color 0.2s;
      display: flex;
      align-items: center;
      gap: 0.4rem;
    }
    .footer-links a:hover { color: rgba(255,255,255,0.75); }
    .footer-links a i { font-size: 0.65rem; opacity: 0.5; }
    .footer-bottom {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding-top: 1.5rem;
      gap: 1rem;
      flex-wrap: wrap;
    }
    .footer-copy {
      font-size: 0.78rem;
      color: rgba(255,255,255,0.22);
    }
    .footer-copy strong { color: rgba(255,255,255,0.4); }
    .footer-badges {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
    }
    .footer-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0.25rem 0.65rem;
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.07);
      border-radius: 999px;
      font-size: 0.68rem;
      color: rgba(255,255,255,0.3);
    }
    .footer-badge i { color: var(--purple-h); font-size: 0.6rem; }
  </style>
</head>
<body>

<!-- ── Animated Orbs ── -->
<div class="bg-canvas">
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  <div class="orb orb-3"></div>
  <div class="orb orb-4"></div>
  <div class="orb orb-5"></div>
</div>

<div class="page-wrapper">

  <!-- ── Left Hero ── -->
  <section class="hero-panel">
    <div>
      <div class="badge">
        <i class="fas fa-sparkles" style="font-size:0.7rem;"></i>
        Platform Evolution
      </div>

      <h1 class="hero-title">
        <span class="line">Smart</span>
        <span class="line glow">Classroom</span>
        <span class="line">System</span>
      </h1>

      <p class="hero-sub">
        Empowering your learning journey with intelligence and simplicity.
        The next generation of digital education.
      </p>

      <div class="feature-list">
        <div class="feature-item">
          <div class="feature-icon fi-1"><i class="fas fa-chalkboard-teacher"></i></div>
          <span>Create &amp; manage classes with live codes</span>
        </div>
        <div class="feature-item">
          <div class="feature-icon fi-2"><i class="fas fa-tasks"></i></div>
          <span>Assign, submit &amp; grade assignments</span>
        </div>
        <div class="feature-item">
          <div class="feature-icon fi-3"><i class="fas fa-question-circle"></i></div>
          <span>Live quizzes &amp; polls with real-time results</span>
        </div>
        <div class="feature-item">
          <div class="feature-icon fi-4"><i class="fas fa-chart-line"></i></div>
          <span>Performance analytics dashboard</span>
        </div>
      </div>

      <div class="avatar-row">
        <div class="avatars">
          <img src="https://i.pravatar.cc/80?img=11" alt="User">
          <img src="https://i.pravatar.cc/80?img=12" alt="User">
          <img src="https://i.pravatar.cc/80?img=13" alt="User">
        </div>
        <span class="avatar-text">Joined by 10k+ learners</span>
      </div>
    </div>
  </section>

  <!-- ── Right Auth Card ── -->
  <section class="auth-panel">
    <div class="glass-card">

      <div class="mobile-brand">
        <h2>Smart <span>Classroom</span></h2>
      </div>

      <div class="card-header">
        <h1 class="card-title">Welcome Back</h1>
        <p class="card-sub">Please enter your credentials to continue.</p>
      </div>

      <?php if ($error): ?>
      <div class="error-banner">
        <i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <form id="login-form" method="POST" action="">
        <!-- Email -->
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <div class="input-wrap">
            <i class="fas fa-envelope input-icon"></i>
            <input
              type="email"
              name="email"
              class="form-input"
              placeholder="name@institution.edu"
              required
              id="login-email"
              autocomplete="email"
              style="padding-left:2.5rem;"
            >
          </div>
        </div>

        <!-- Password -->
        <div class="form-group">
          <div class="form-row-between">
            <label class="form-label" style="margin-bottom:0;">Password</label>
            <a href="#" class="forgot-link">Forgot Password?</a>
          </div>
          <div class="input-wrap" style="margin-top:0.5rem;">
            <i class="fas fa-lock input-icon"></i>
            <input
              type="password"
              name="password"
              class="form-input"
              placeholder="••••••••"
              required
              id="login-pass"
              autocomplete="current-password"
              style="padding-left:2.5rem;"
            >
            <button type="button" class="input-suffix" onclick="togglePass('login-pass', this)" aria-label="Toggle password">
              <i class="fas fa-eye"></i>
            </button>
          </div>
        </div>

        <div class="demo-creds" id="demo-creds" title="Click to auto-fill demo credentials">
          <strong>Demo Accounts:</strong><br>
          Teacher: teacher@demo.com / password123<br>
          Student: student@demo.com / password123<br>
          Guardian: guardian@demo.com / password123
        </div>

        <button type="submit" class="btn-primary" id="login-btn">
          <i class="fas fa-sign-in-alt"></i>
          <span id="btn-label">Sign In</span>
        </button>
      </form>

      <div class="divider">or</div>

      <button class="btn-google" type="button">
        <span class="google-icon">
          <svg width="18" height="18" viewBox="0 0 24 24">
            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
          </svg>
        </span>
        Sign in with Google
      </button>

      <p class="auth-footer">
        Don't have an account?
        <a href="<?= BASE_URL ?>/register.php">Register</a>
      </p>
    </div>
  </section>

</div>

<script>
  // Password toggle
  function togglePass(id, btn) {
    const input = document.getElementById(id);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
      input.type = 'text';
      icon.className = 'fas fa-eye-slash';
    } else {
      input.type = 'password';
      icon.className = 'fas fa-eye';
    }
  }

  // Loading state on submit
  document.getElementById('login-form').addEventListener('submit', function () {
    const btn   = document.getElementById('login-btn');
    const label = document.getElementById('btn-label');
    btn.disabled = true;
    label.textContent = 'Signing in…';
    btn.querySelector('i').className = 'fas fa-spinner fa-spin';
  });

  // Demo quick fill
  document.getElementById('demo-creds').addEventListener('click', function () {
    document.getElementById('login-email').value = 'teacher@demo.com';
    document.getElementById('login-pass').value  = 'password123';
  });
</script>

<!-- ── Footer ── -->
<footer class="site-footer">
  <div class="footer-inner">
    <div class="footer-top">

      <!-- Brand -->
      <div class="footer-brand">
        <div class="footer-brand-name">
          <i class="fas fa-graduation-cap" style="color:var(--purple-h);"></i>
          Smart <span>Classroom</span>
        </div>
        <p class="footer-brand-desc">
          A next-generation Learning Management System built for teachers, students, and guardians — empowering digital education at every level.
        </p>
        <div class="footer-socials">
          <a href="#" class="social-btn" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
          <a href="#" class="social-btn" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
          <a href="#" class="social-btn" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
          <a href="#" class="social-btn" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
          <a href="#" class="social-btn" aria-label="GitHub"><i class="fab fa-github"></i></a>
        </div>
      </div>

      <!-- Platform -->
      <div>
        <p class="footer-col-title">Platform</p>
        <ul class="footer-links">
          <li><a href="#"><i class="fas fa-chevron-right"></i> About Us</a></li>
          <li><a href="#"><i class="fas fa-chevron-right"></i> Features</a></li>
          <li><a href="#"><i class="fas fa-chevron-right"></i> Pricing</a></li>
          <li><a href="#"><i class="fas fa-chevron-right"></i> Roadmap</a></li>
          <li><a href="#"><i class="fas fa-chevron-right"></i> Changelog</a></li>
        </ul>
      </div>

      <!-- Resources -->
      <div>
        <p class="footer-col-title">Resources</p>
        <ul class="footer-links">
          <li><a href="#"><i class="fas fa-chevron-right"></i> Documentation</a></li>
          <li><a href="#"><i class="fas fa-chevron-right"></i> Help Center</a></li>
          <li><a href="#"><i class="fas fa-chevron-right"></i> Tutorials</a></li>
          <li><a href="#"><i class="fas fa-chevron-right"></i> API Reference</a></li>
          <li><a href="#"><i class="fas fa-chevron-right"></i> Status Page</a></li>
        </ul>
      </div>

      <!-- Legal -->
      <div>
        <p class="footer-col-title">Legal</p>
        <ul class="footer-links">
          <li><a href="#"><i class="fas fa-chevron-right"></i> Terms of Service</a></li>
          <li><a href="#"><i class="fas fa-chevron-right"></i> Privacy Policy</a></li>
          <li><a href="#"><i class="fas fa-chevron-right"></i> Cookie Policy</a></li>
          <li><a href="#"><i class="fas fa-chevron-right"></i> Data Processing</a></li>
          <li><a href="#"><i class="fas fa-chevron-right"></i> Accessibility</a></li>
        </ul>
      </div>

      <!-- Contact -->
      <div>
        <p class="footer-col-title">Contact</p>
        <ul class="footer-links">
          <li><a href="mailto:support@smartclassroom.edu"><i class="fas fa-envelope"></i> support@smartclassroom.edu</a></li>
          <li><a href="#"><i class="fas fa-headset"></i> Live Support</a></li>
          <li><a href="#"><i class="fas fa-bug"></i> Report a Bug</a></li>
          <li><a href="#"><i class="fas fa-lightbulb"></i> Feature Request</a></li>
          <li><a href="#"><i class="fas fa-building"></i> Partnerships</a></li>
        </ul>
      </div>

    </div>

    <!-- Bottom bar -->
    <div class="footer-bottom">
      <p class="footer-copy">
        &copy; <?= date('Y') ?> <strong>Smart Classroom System.</strong> All rights reserved.
      </p>
      <div class="footer-badges">
        <span class="footer-badge"><i class="fas fa-shield-alt"></i> SSL Secured</span>
        <span class="footer-badge"><i class="fas fa-lock"></i> GDPR Compliant</span>
        <span class="footer-badge"><i class="fas fa-check-circle"></i> v3.8 Stable</span>
      </div>
    </div>
  </div>
</footer>

</body>
</html>
