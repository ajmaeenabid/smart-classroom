<?php
// =============================================
// Smart Classroom — Register (register.php)
// =============================================
require_once __DIR__ . '/config/db.php';

if (isLoggedIn()) {
    $role = userRole();
    redirect(BASE_URL . "/dashboard/{$role}.php");
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm']  ?? '');
    $role     = trim($_POST['role']     ?? 'student');

    if ($name && $email && $password && in_array($role, ['teacher','student','guardian'])) {
        if ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email already registered.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)");
                $stmt->execute([$name, $email, $hash, $role]);
                $id   = $pdo->lastInsertId();
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $user = $stmt->fetch();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user']    = $user;
                redirect(BASE_URL . "/dashboard/{$user['role']}.php");
            }
        }
    } else {
        $error = 'Please complete all fields correctly.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Smart Classroom System – Create your account and join the future of digital education">
  <title>Register — Smart Classroom</title>
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
      --teal:       #a78bfa;
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
      will-change: transform, opacity;
    }

    .orb-1 {
      width: 600px; height: 600px;
      background: radial-gradient(circle, rgba(139,92,246,0.5) 0%, rgba(139,92,246,0) 70%);
      top: -15%; right: -10%;
      animation: orb1Reg 20s ease-in-out infinite;
    }
    .orb-2 {
      width: 450px; height: 450px;
      background: radial-gradient(circle, rgba(99,102,241,0.45) 0%, rgba(99,102,241,0) 70%);
      bottom: -10%; left: -8%;
      animation: orb2Reg 24s ease-in-out infinite;
    }
    .orb-3 {
      width: 320px; height: 320px;
      background: radial-gradient(circle, rgba(99,102,241,0.45) 0%, rgba(99,102,241,0) 70%);
      top: 40%; left: 20%;
      animation: orb3Reg 17s ease-in-out infinite;
    }
    .orb-4 {
      width: 260px; height: 260px;
      background: radial-gradient(circle, rgba(236,72,153,0.35) 0%, rgba(236,72,153,0) 70%);
      top: 15%; left: 45%;
      animation: orb4Reg 21s ease-in-out infinite;
    }
    .orb-5 {
      width: 380px; height: 380px;
      background: radial-gradient(circle, rgba(59,130,246,0.3) 0%, rgba(59,130,246,0) 70%);
      bottom: 20%; right: 15%;
      animation: orb5Reg 28s ease-in-out infinite;
    }
    .orb-6 {
      width: 200px; height: 200px;
      background: radial-gradient(circle, rgba(245,158,11,0.3) 0%, rgba(245,158,11,0) 70%);
      top: 70%; left: 60%;
      animation: orb6Reg 16s ease-in-out infinite;
    }

    @keyframes orb1Reg {
      0%,100% { transform: translate(0,0)    scale(1);    opacity: 0.7; }
      30%     { transform: translate(-80px,60px) scale(1.12); opacity: 1;   }
      65%     { transform: translate(-40px,110px) scale(0.9); opacity: 0.55; }
    }
    @keyframes orb2Reg {
      0%,100% { transform: translate(0,0)    scale(1);    opacity: 0.65; }
      25%     { transform: translate(70px,-60px) scale(1.15); opacity: 0.95; }
      60%     { transform: translate(40px,-110px) scale(0.85); opacity: 0.5; }
    }
    @keyframes orb3Reg {
      0%,100% { transform: translate(0,0)    scale(1);    opacity: 0.5; }
      20%     { transform: translate(60px,70px)  scale(1.2);  opacity: 0.8; }
      55%     { transform: translate(-50px,50px) scale(0.85); opacity: 0.4; }
      80%     { transform: translate(30px,-60px) scale(1.1);  opacity: 0.7; }
    }
    @keyframes orb4Reg {
      0%,100% { transform: translate(0,0)    scale(1);    opacity: 0.55; }
      35%     { transform: translate(-60px,80px) scale(1.18); opacity: 0.85; }
      70%     { transform: translate(70px,40px)  scale(0.8);  opacity: 0.4; }
    }
    @keyframes orb5Reg {
      0%,100% { transform: translate(0,0)    scale(1);    opacity: 0.4; }
      40%     { transform: translate(-70px,-80px) scale(1.1); opacity: 0.65; }
      75%     { transform: translate(50px,-50px)  scale(0.9); opacity: 0.3; }
    }
    @keyframes orb6Reg {
      0%,100% { transform: translate(0,0)    scale(1);    opacity: 0.45; }
      30%     { transform: translate(-40px,-70px) scale(1.25); opacity: 0.75; }
      68%     { transform: translate(60px,-30px)  scale(0.8);  opacity: 0.35; }
    }

    /* ─── Layout ─── */
    .page-wrapper {
      position: relative;
      z-index: 1;
      min-height: 100vh;
      display: flex;
    }

    /* ─── Left Hero ─── */
    .hero-panel {
      flex: 1.1;
      display: none;
      flex-direction: column;
      justify-content: center;
      padding: 4rem 4rem 4rem 13%;
      position: relative;
    }
    @media (min-width: 960px) { .hero-panel { display: flex; } }

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
      font-size: clamp(2.8rem, 4.5vw, 5rem);
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
    .hero-title .glow-teal {
      color: var(--purple-h);
      text-shadow: 0 0 40px rgba(167,139,250,0.6);
    }

    .hero-sub {
      font-size: 1.05rem;
      color: rgba(255,255,255,0.45);
      line-height: 1.7;
      max-width: 400px;
      margin-bottom: 2.5rem;
    }

    /* Steps */
    .steps { display: flex; flex-direction: column; gap: 1.25rem; }
    .step-item { display: flex; align-items: flex-start; gap: 1rem; }
    .step-num {
      width: 32px; height: 32px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), var(--purple));
      color: #fff;
      font-size: 0.75rem;
      font-weight: 800;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      box-shadow: 0 0 12px rgba(99,102,241,0.4);
    }
    .step-text { padding-top: 0.3rem; }
    .step-text strong {
      display: block;
      font-size: 0.875rem;
      color: rgba(255,255,255,0.8);
      margin-bottom: 0.15rem;
    }
    .step-text span {
      font-size: 0.78rem;
      color: rgba(255,255,255,0.35);
    }

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
      max-width: 470px;
      background: linear-gradient(
        135deg,
        rgba(255,255,255,0.06) 0%,
        rgba(255,255,255,0.02) 100%
      );
      backdrop-filter: blur(28px);
      -webkit-backdrop-filter: blur(28px);
      border: 1px solid rgba(255,255,255,0.09);
      border-radius: 2rem;
      padding: 2.5rem 2.75rem;
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

    .card-header { margin-bottom: 1.75rem; }
    .card-title {
      font-size: 1.75rem;
      font-weight: 800;
      color: #fff;
      letter-spacing: -0.03em;
      margin-bottom: 0.35rem;
    }
    .card-sub {
      font-size: 0.855rem;
      color: var(--text-muted);
    }

    /* ─── Error / Success ─── */
    .error-banner {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      background: rgba(239,68,68,0.1);
      border: 1px solid rgba(239,68,68,0.3);
      border-radius: 0.6rem;
      padding: 0.75rem 1rem;
      font-size: 0.82rem;
      color: #f87171;
      margin-bottom: 1.25rem;
      animation: fadeIn 0.3s ease;
    }
    @keyframes fadeIn { from { opacity:0; transform:translateY(-4px); } to { opacity:1; transform:none; } }

    /* ─── Form ─── */
    .form-grid-2 {
      display: flex;
      flex-direction: column;
      gap: 0;
    }

    .form-group { margin-bottom: 1.1rem; }
    .form-label {
      display: block;
      font-size: 0.67rem;
      font-weight: 700;
      letter-spacing: 0.15em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.38);
      margin-bottom: 0.45rem;
      margin-left: 0.25rem;
    }
    .input-wrap { position: relative; }
    .form-input {
      width: 100%;
      height: 48px;
      padding: 0 2.75rem 0 1.1rem;
      background: var(--input-bg);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 0.8rem;
      color: var(--text);
      font-size: 0.875rem;
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
    .input-suffix {
      position: absolute;
      right: 0.75rem;
      top: 50%;
      transform: translateY(-50%);
      color: rgba(255,255,255,0.2);
      font-size: 0.8rem;
      background: none;
      border: none;
      cursor: pointer;
      padding: 0;
      line-height: 1;
      transition: color 0.3s;
    }
    .input-suffix:hover { color: rgba(255,255,255,0.5); }

    /* ─── Role Selector ─── */
    .role-group { margin-bottom: 1.25rem; }
    .role-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 0.75rem;
    }
    .role-card {
      cursor: pointer;
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 0.85rem;
      background: rgba(255,255,255,0.03);
      padding: 0.9rem 0.5rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
      position: relative;
      overflow: hidden;
    }
    .role-card:hover {
      border-color: rgba(255,255,255,0.15);
      background: rgba(255,255,255,0.05);
    }
    .role-card.active {
      border-color: rgba(139,92,246,0.55);
      background: rgba(139,92,246,0.08);
      box-shadow: 0 0 15px rgba(139,92,246,0.2);
    }
    .role-icon {
      font-size: 1.3rem;
      color: rgba(255,255,255,0.3);
      transition: color 0.3s, transform 0.3s;
    }
    .role-card.active .role-icon { color: var(--purple-h); transform: scale(1.1); }
    .role-card:hover:not(.active) .role-icon { transform: scale(1.05); }
    .role-label {
      font-size: 0.72rem;
      font-weight: 700;
      color: rgba(255,255,255,0.45);
      letter-spacing: 0.05em;
      transition: color 0.3s;
    }
    .role-card.active .role-label { color: rgba(255,255,255,0.8); }

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
      margin-top: 0.75rem;
      letter-spacing: 0.02em;
    }
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 0 35px rgba(99,102,241,0.65), 0 8px 20px rgba(0,0,0,0.4);
    }
    .btn-primary:active { transform: translateY(0); }
    .btn-primary:disabled { opacity: 0.65; cursor: not-allowed; transform: none; }

    /* ─── Footer ─── */
    .auth-footer {
      text-align: center;
      margin-top: 1.5rem;
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

    /* ─── Terms ─── */
    .terms-note {
      font-size: 0.72rem;
      color: rgba(255,255,255,0.25);
      text-align: center;
      margin-top: 0.75rem;
      line-height: 1.6;
    }
    .terms-note a { color: rgba(255,255,255,0.45); text-decoration: underline; }

    /* ─── Spinner ─── */
    @keyframes spin { to { transform: rotate(360deg); } }
    .fa-spin { animation: spin 0.8s linear infinite; }

    /* ─── Mobile brand ─── */
    .mobile-brand { display: none; text-align: center; margin-bottom: 1.5rem; }
    .mobile-brand h2 { font-size: 1.5rem; font-weight: 800; color: #fff; letter-spacing: -0.04em; }
    .mobile-brand h2 span { color: var(--purple-h); }
    @media (max-width: 959px) { .mobile-brand { display: block; } }

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
    .footer-socials { display: flex; gap: 0.6rem; }
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
    .footer-copy { font-size: 0.78rem; color: rgba(255,255,255,0.22); }
    .footer-copy strong { color: rgba(255,255,255,0.4); }
    .footer-badges { display: flex; gap: 0.5rem; flex-wrap: wrap; }
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
  <div class="orb orb-6"></div>
</div>

<div class="page-wrapper">

  <!-- ── Left Hero ── -->
  <section class="hero-panel">
    <div>
      <div class="badge">
        <i class="fas fa-wand-magic-sparkles" style="font-size:0.7rem;"></i>
        Join the Platform
      </div>

      <h1 class="hero-title">
        <span class="line">Start Your</span>
        <span class="line glow-teal">Learning</span>
        <span class="line">Journey</span>
      </h1>

      <p class="hero-sub">
        Create your account in seconds and gain access to a full suite of
        classroom management, quizzes, and analytics tools.
      </p>

      <div class="steps">
        <div class="step-item">
          <div class="step-num">1</div>
          <div class="step-text">
            <strong>Create your account</strong>
            <span>Enter your name, email and a secure password.</span>
          </div>
        </div>
        <div class="step-item">
          <div class="step-num">2</div>
          <div class="step-text">
            <strong>Pick your role</strong>
            <span>Student, Teacher or Guardian — each with a tailored experience.</span>
          </div>
        </div>
        <div class="step-item">
          <div class="step-num">3</div>
          <div class="step-text">
            <strong>Access your dashboard</strong>
            <span>You're in! Start joining classes or managing your school.</span>
          </div>
        </div>
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
        <h1 class="card-title">Create Account</h1>
        <p class="card-sub">Join the future of digital education.</p>
      </div>

      <?php if ($error): ?>
      <div class="error-banner">
        <i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <form id="reg-form" method="POST" action="">

        <!-- Name & Email -->
        <div class="form-grid-2">
          <div class="form-group">
            <label class="form-label">Full Name</label>
            <div class="input-wrap">
              <input type="text" name="name" class="form-input" placeholder="John Doe" required autocomplete="name">
              <i class="fas fa-user input-suffix" style="pointer-events:none;"></i>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Email Address</label>
            <div class="input-wrap">
              <input type="email" name="email" class="form-input" placeholder="email@domain.com" required autocomplete="email">
              <i class="fas fa-envelope input-suffix" style="pointer-events:none;"></i>
            </div>
          </div>
        </div>

        <!-- Password & Confirm -->
        <div class="form-grid-2">
          <div class="form-group">
            <label class="form-label">Password</label>
            <div class="input-wrap">
              <input type="password" name="password" id="pass1" class="form-input" placeholder="Min 6 chars" required minlength="6" autocomplete="new-password">
              <button type="button" class="input-suffix" onclick="togglePass('pass1',this)" aria-label="Toggle">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Confirm Password</label>
            <div class="input-wrap">
              <input type="password" name="confirm" id="pass2" class="form-input" placeholder="Repeat password" required autocomplete="new-password">
              <button type="button" class="input-suffix" onclick="togglePass('pass2',this)" aria-label="Toggle">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Role -->
        <div class="role-group">
          <label class="form-label">I am a…</label>
          <div class="role-grid" id="role-grid">
            <div class="role-card active" data-role="student" onclick="selectRole(this)">
              <i class="fas fa-user-graduate role-icon"></i>
              <span class="role-label">Student</span>
            </div>
            <div class="role-card" data-role="teacher" onclick="selectRole(this)">
              <i class="fas fa-chalkboard-teacher role-icon"></i>
              <span class="role-label">Teacher</span>
            </div>
            <div class="role-card" data-role="guardian" onclick="selectRole(this)">
              <i class="fas fa-user-shield role-icon"></i>
              <span class="role-label">Guardian</span>
            </div>
          </div>
          <input type="hidden" name="role" id="role-value" value="student">
        </div>

        <button type="submit" class="btn-primary" id="reg-btn">
          <i class="fas fa-user-plus"></i>
          <span id="btn-label">Create Account</span>
        </button>

        <p class="terms-note">
          By registering you agree to our
          <a href="#">Terms of Service</a> &amp; <a href="#">Privacy Policy</a>.
        </p>
      </form>

      <p class="auth-footer">
        Already have an account?
        <a href="<?= BASE_URL ?>/login.php">Sign In</a>
      </p>
    </div>
  </section>

</div>

<script>
  // Role selection
  function selectRole(card) {
    document.querySelectorAll('.role-card').forEach(c => c.classList.remove('active'));
    card.classList.add('active');
    document.getElementById('role-value').value = card.dataset.role;
  }

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

  // Client-side password match validation
  document.getElementById('reg-form').addEventListener('submit', function (e) {
    const p1  = document.getElementById('pass1').value;
    const p2  = document.getElementById('pass2').value;
    if (p1 !== p2) {
      e.preventDefault();
      alert('Passwords do not match.');
      return;
    }
    // Loading state
    const btn   = document.getElementById('reg-btn');
    const label = document.getElementById('btn-label');
    btn.disabled = true;
    label.textContent = 'Creating account…';
    btn.querySelector('i').className = 'fas fa-spinner fa-spin';
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
