<?php
// =============================================
// Smart Classroom — Entry Point (index.php)
// Redirects to dashboard if logged in, otherwise shows landing page.
// =============================================
require_once __DIR__ . '/config/db.php';

if (isLoggedIn()) {
    $role = userRole();
    redirect(BASE_URL . "/dashboard/{$role}.php");
    exit;
}
?>
<!DOCTYPE html>
<html class="dark" lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>SmartClassroom | The Smarter Way to Manage Your Classroom</title>
  <meta name="description" content="One workspace for teachers, students, and parents. Track progress, manage assignments, and stay connected without the chaos." />
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            'outline-variant': '#2a2d3e',
            'background':             '#05070a',
            'surface-container-lowest': '#080a12',
            'surface-container-low':  '#0d101a',
            'surface-container':      '#0f121d',
            'surface-container-high': '#131622',
            'surface-container-highest':'#1c1f2e',
            'surface-bright':         '#22263a',
            'on-surface':             '#e1e2ec',
            'on-surface-variant':     '#c4c6d0',
            'outline':                '#8e90a6',
            'primary':                '#7986cb',
            'primary-container':      '#1a237e',
            'secondary':              '#9575cd',
            'secondary-container':    '#311b92',
            'tertiary':               '#80cbc4',
            'tertiary-container':     '#004d40',
            'on-primary-container':   '#e8eaf6',
          },
          fontFamily: { sans: ['Manrope', 'sans-serif'] },
          boxShadow: {
            glow: '0 0 70px rgba(121, 134, 203, 0.16)',
            card: '0 30px 80px rgba(0, 0, 0, 0.35)'
          },
          animation: {
            blobSlow: 'pulseSoft 6s ease-in-out infinite, floatBlob 14s ease-in-out infinite',
            blobFast: 'pulseSoft 4s ease-in-out infinite, floatBlob 10s ease-in-out infinite',
            panGrid:  'panGrid 60s linear infinite'
          },
          keyframes: {
            pulseSoft: {
              '0%, 100%': { opacity: '0.5' },
              '50%':       { opacity: '0.9' }
            },
            floatBlob: {
              '0%, 100%': { transform: 'translateY(0) scale(1)' },
              '50%':       { transform: 'translateY(-20px) scale(1.05)' }
            },
            panGrid: {
              '0%':   { backgroundPosition: '0px 0px' },
              '100%': { backgroundPosition: '56px 56px' }
            }
          }
        }
      }
    }
  </script>
  <style>
    html { scroll-behavior: smooth; }
    body {
      font-family: 'Manrope', sans-serif;
      background: linear-gradient(145deg, #05070a 0%, #0e0920 100%);
      color: #e1e2ec;
      overflow-x: hidden;
    }
    .material-symbols-outlined {
      font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }
    .dot-grid {
      background-image: radial-gradient(circle at 1px 1px, rgba(196,198,208,0.09) 1px, transparent 0);
      background-size: 28px 28px;
    }
    .glass-card {
      background: rgba(15, 18, 29, 0.55);
      backdrop-filter: blur(24px);
      -webkit-backdrop-filter: blur(24px);
    }
    .gradient-border {
      position: relative;
    }
    .gradient-border::before {
      content: '';
      position: absolute;
      inset: 0;
      padding: 1px;
      border-radius: inherit;
      background: linear-gradient(135deg, rgba(121,134,203,.25), rgba(149,117,205,.12));
      -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
      -webkit-mask-composite: xor;
      mask-composite: exclude;
      pointer-events: none;
    }
  </style>
</head>
<body class="bg-background text-on-surface min-h-screen selection:bg-primary-container selection:text-white">

  <!-- ── HEADER ── -->
  <header class="fixed top-0 inset-x-0 z-50 bg-[#05070a]/70 backdrop-blur-2xl border-b border-white/5">
    <nav class="max-w-6xl mx-auto px-6 lg:px-10 py-4 flex items-center justify-between">
      <div class="text-lg font-bold tracking-tight text-white">SmartClassroom</div>

      <div class="hidden md:flex items-center gap-6 text-sm font-semibold">
        <a href="#audience" class="text-on-surface-variant hover:text-white transition-colors">Solutions</a>
        <a href="#features" class="text-on-surface-variant hover:text-white transition-colors">Features</a>
        <a href="#cta"      class="text-on-surface-variant hover:text-white transition-colors">Get Started</a>
      </div>

      <div class="flex items-center gap-2">
        <a href="login.php"    class="px-4 py-2 text-sm text-on-surface-variant hover:text-white transition-colors font-semibold">Login</a>
        <a href="register.php" class="px-5 py-2 rounded-xl bg-gradient-to-r from-primary-container to-secondary-container text-white text-sm font-bold hover:brightness-110 transition-all">Start Free</a>
      </div>
    </nav>
  </header>

  <main>

    <!-- ── HERO ── -->
    <section class="relative min-h-screen flex items-center justify-center overflow-hidden pt-20">
      <div class="absolute inset-0 dot-grid animate-panGrid opacity-40 pointer-events-none"></div>
      <div class="absolute top-[18%] right-[12%] w-80 h-80 bg-secondary-container/20 blur-[130px] rounded-full animate-blobSlow pointer-events-none"></div>
      <div class="absolute bottom-[12%] left-[8%]  w-72 h-72 bg-primary-container/20 blur-[130px] rounded-full animate-blobFast pointer-events-none" style="animation-delay: 2s;"></div>

      <div class="max-w-3xl mx-auto px-6 lg:px-10 relative z-10 text-center -translate-y-[2vh]">

        <div class="relative inline-block text-primary text-xs font-bold tracking-[0.2em] uppercase mb-8 text-center">
          <span class="absolute top-1/2 -left-4 -translate-y-1/2 w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
          BUILT FOR REAL CLASSROOMS
        </div>

        <h1 class="text-5xl sm:text-6xl lg:text-[5.25rem] font-bold text-white leading-[1.08] tracking-[-0.04em] mb-6 text-center mx-auto">
          The <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary via-secondary to-tertiary">Smarter</span> Way<br>to Manage<br>Your Classroom
        </h1>

        <p class="text-sm sm:text-base text-on-surface-variant opacity-95 leading-relaxed max-w-lg mx-auto mb-10">
          One workspace for teachers, students, and parents. Track progress, manage work, and stay connected — without the chaos.
        </p>

        <div class="flex flex-wrap justify-center gap-3">
          <a href="register.php" class="px-7 py-3.5 rounded-2xl bg-gradient-to-r from-primary-container to-secondary-container text-white font-bold text-sm shadow-xl shadow-primary-container/20 hover:brightness-110 transition-all">
            Get Started Free
          </a>
          <a href="login.php" class="px-7 py-3.5 rounded-2xl bg-surface-container-highest border border-white/10 text-white font-bold text-sm hover:bg-surface-bright transition-all">
            Login to Dashboard
          </a>
        </div>

      </div>
    </section>

    <!-- ── WHO IT'S FOR ── -->
    <section id="audience" class="py-20 bg-surface-container-lowest relative overflow-hidden">
      <div class="absolute inset-0 dot-grid animate-panGrid opacity-8 pointer-events-none"></div>
      <div class="max-w-6xl mx-auto px-6 lg:px-10 relative z-10">

        <div class="mb-10">
          <p class="text-primary uppercase tracking-[0.2em] text-xs font-bold mb-2">Who it's for</p>
          <h2 class="text-4xl sm:text-5xl font-bold text-white tracking-tight">Whether you teach it, learn it, or live it.</h2>
        </div>

        <div class="grid md:grid-cols-3 gap-5">

          <article id="teachers" class="group rounded-[1.5rem] bg-surface-container p-7 border border-white/5 hover:border-primary/20 transition-all duration-300 relative overflow-hidden">
            <div class="absolute -right-5 -bottom-5 opacity-5 group-hover:opacity-8 transition-opacity pointer-events-none">
              <span class="material-symbols-outlined text-[90px] text-primary" style="font-variation-settings:'FILL' 1;">school</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-primary-container/25 border border-primary/15 flex items-center justify-center mb-5">
              <span class="material-symbols-outlined text-primary">school</span>
            </div>
            <h3 class="text-lg font-bold text-white mb-2">Teachers</h3>
            <p class="text-on-surface-variant text-sm leading-relaxed">You didn't become a teacher to live in spreadsheets. Manage assignments, spot who's struggling early, and generate reports without the manual grind.</p>
          </article>

          <article id="students" class="group rounded-[1.5rem] bg-surface-container p-7 border border-white/5 hover:border-secondary/20 transition-all duration-300 relative overflow-hidden">
            <div class="absolute -right-5 -bottom-5 opacity-5 group-hover:opacity-8 transition-opacity pointer-events-none">
              <span class="material-symbols-outlined text-[90px] text-secondary" style="font-variation-settings:'FILL' 1;">person</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-secondary-container/25 border border-secondary/15 flex items-center justify-center mb-5">
              <span class="material-symbols-outlined text-secondary">person</span>
            </div>
            <h3 class="text-lg font-bold text-white mb-2">Students</h3>
            <p class="text-on-surface-variant text-sm leading-relaxed">No more "wait, when was that due?" Your tasks, grades, and goals all live in one clean space — so you always know what's next, no panic required.</p>
          </article>

          <article id="parents" class="group rounded-[1.5rem] bg-surface-container p-7 border border-white/5 hover:border-tertiary/20 transition-all duration-300 relative overflow-hidden">
            <div class="absolute -right-5 -bottom-5 opacity-5 group-hover:opacity-8 transition-opacity pointer-events-none">
              <span class="material-symbols-outlined text-[90px] text-tertiary" style="font-variation-settings:'FILL' 1;">family_restroom</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-tertiary-container/25 border border-tertiary/15 flex items-center justify-center mb-5">
              <span class="material-symbols-outlined text-tertiary">family_restroom</span>
            </div>
            <h3 class="text-lg font-bold text-white mb-2">Parents</h3>
            <p class="text-on-surface-variant text-sm leading-relaxed">"How was school?" — "Fine." Get real answers. See attendance, grades, and what's coming up — before the report card surprises you.</p>
          </article>

        </div>
      </div>
    </section>

    <!-- ── FEATURES ── -->
    <section id="features" class="py-20 relative">
      <div class="max-w-6xl mx-auto px-6 lg:px-10">

        <div class="text-center max-w-xl mx-auto mb-12">
          <p class="text-primary uppercase tracking-[0.2em] text-xs font-bold mb-2">Why it works</p>
          <h2 class="text-4xl sm:text-5xl font-bold text-white mb-3 tracking-tight">Everything you need.</h2>
          <p class="text-on-surface-variant text-sm leading-relaxed">No training sessions. No confusing menus. Just open it and go.</p>
        </div>

        <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
          <div class="rounded-[1.25rem] bg-surface-container p-6 border border-white/5 hover:border-primary/15 transition-all duration-300">
            <span class="material-symbols-outlined text-primary text-3xl mb-4 block">dashboard</span>
            <h3 class="text-white text-sm font-bold mb-1.5">One place for everything</h3>
            <p class="text-on-surface-variant text-xs leading-relaxed">Assignments, attendance, grades — stop switching between apps.</p>
          </div>

          <div class="rounded-[1.25rem] bg-surface-container p-6 border border-white/5 hover:border-secondary/15 transition-all duration-300">
            <span class="material-symbols-outlined text-secondary text-3xl mb-4 block">task_alt</span>
            <h3 class="text-white text-sm font-bold mb-1.5">Deadlines that get met</h3>
            <p class="text-on-surface-variant text-xs leading-relaxed">Students see what's due. Teachers see who's done. No more chasing.</p>
          </div>

          <div class="rounded-[1.25rem] bg-surface-container p-6 border border-white/5 hover:border-tertiary/15 transition-all duration-300">
            <span class="material-symbols-outlined text-tertiary text-3xl mb-4 block">insights</span>
            <h3 class="text-white text-sm font-bold mb-1.5">Insights that matter</h3>
            <p class="text-on-surface-variant text-xs leading-relaxed">Real signals — not vanity stats — to act before problems grow.</p>
          </div>

          <div class="rounded-[1.25rem] bg-surface-container p-6 border border-white/5 hover:border-primary/15 transition-all duration-300">
            <span class="material-symbols-outlined text-primary text-3xl mb-4 block">bolt</span>
            <h3 class="text-white text-sm font-bold mb-1.5">Fast and familiar</h3>
            <p class="text-on-surface-variant text-xs leading-relaxed">If you can use a smartphone, you can use SmartClassroom.</p>
          </div>
        </div>

      </div>
    </section>

    <!-- ── CTA ── -->
    <section id="cta" class="py-20 relative overflow-hidden">
      <div class="absolute inset-0 dot-grid animate-panGrid opacity-15 pointer-events-none"></div>
      <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-[46rem] h-[22rem] bg-secondary-container/18 blur-[140px] rounded-full animate-blobSlow pointer-events-none"></div>
      <div class="max-w-5xl mx-auto px-6 lg:px-10 relative z-10">
        <div class="rounded-[2.5rem] bg-gradient-to-br from-primary-container via-secondary-container to-surface-container-high p-12 md:p-20 text-center border border-white/10 shadow-card">
          <h2 class="text-4xl sm:text-5xl font-bold text-white tracking-tight mb-4">Elevate Your Classroom</h2>
          <p class="text-on-primary-container/75 text-base sm:text-lg max-w-md mx-auto mb-10 leading-relaxed">Join our community and shape your future</p>
          <div class="flex flex-wrap justify-center gap-3">
            <a href="register.php" class="px-7 py-3 rounded-xl bg-white text-secondary-container font-bold text-sm hover:scale-[1.02] transition-transform">Get Started Free</a>
            <a href="login.php"    class="px-7 py-3 rounded-xl border border-white/20 text-white font-bold text-sm hover:bg-white/10 transition-all">Login</a>
          </div>
        </div>
      </div>
    </section>

  </main>

  <!-- ── FOOTER ── -->
  <footer class="border-t border-white/5 bg-surface-container-lowest py-10">
    <div class="max-w-6xl mx-auto px-6 lg:px-10 flex flex-col sm:flex-row items-start justify-between gap-8">
      <div>
        <div class="text-base font-bold text-white mb-2">SmartClassroom</div>
        <p class="text-xs text-on-surface-variant leading-relaxed max-w-[220px]">Built for teachers, students, and the parents who care about both.</p>
      </div>

      <div class="flex gap-10 text-xs">
        <div>
          <h4 class="text-white font-bold mb-3 uppercase tracking-[0.15em]">Platform</h4>
          <ul class="space-y-2 text-on-surface-variant">
            <li><a href="#features" class="hover:text-white transition-colors">Features</a></li>
            <li><a href="#audience" class="hover:text-white transition-colors">Solutions</a></li>
          </ul>
        </div>
        <div>
          <h4 class="text-white font-bold mb-3 uppercase tracking-[0.15em]">Access</h4>
          <ul class="space-y-2 text-on-surface-variant">
            <li><a href="login.php"    class="hover:text-white transition-colors">Login</a></li>
            <li><a href="register.php" class="hover:text-white transition-colors">Register</a></li>
          </ul>
        </div>
        <div>
          <h4 class="text-white font-bold mb-3 uppercase tracking-[0.15em]">Legal</h4>
          <ul class="space-y-2 text-on-surface-variant">
            <li><a href="#" class="hover:text-white transition-colors">Privacy</a></li>
            <li><a href="#" class="hover:text-white transition-colors">Terms</a></li>
          </ul>
        </div>
      </div>
    </div>
    <div class="max-w-6xl mx-auto px-6 lg:px-10 mt-8 pt-6 border-t border-white/5 text-xs text-outline text-center">
      &copy; <?php echo date('Y'); ?> SmartClassroom. All rights reserved.
    </div>
  </footer>

</body>
</html>
