<?php
// modules/error/403.php
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($_SESSION['theme'] ?? 'dark'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0b0f1a">
    <title>403 | DigitalMM</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --bg:#0b0f1a; --card:rgba(15,23,42,.82); --border:rgba(255,255,255,.08); --text:#f8fafc; --muted:#94a3b8; --primary:#6d28d9; --secondary:#ec4899; --accent:#06b6d4; }
        html[data-theme="light"] { --bg:#f8fafc; --card:rgba(255,255,255,.92); --border:#e2e8f0; --text:#0f172a; --muted:#64748b; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; display:grid; place-items:center; font-family:Arial,Helvetica,sans-serif; color:var(--text); background:radial-gradient(circle at top left, rgba(109,40,217,.18), transparent 30%), radial-gradient(circle at top right, rgba(236,72,153,.15), transparent 28%), var(--bg); overflow:hidden; }
        .wrap { width:min(920px, calc(100vw - 32px)); padding:28px; position:relative; }
        .card { position:relative; background:var(--card); border:1px solid var(--border); border-radius:32px; backdrop-filter:blur(20px); padding:40px; box-shadow:0 24px 80px rgba(0,0,0,.28); }
        .code { font-size:clamp(72px, 12vw, 132px); line-height:1; margin:18px 0 8px; font-weight:900; letter-spacing:-.08em; background:linear-gradient(90deg, var(--primary), var(--secondary)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        h1 { font-size:clamp(28px, 4vw, 52px); margin:0 0 10px; }
        p { margin:0; color:var(--muted); font-size:16px; line-height:1.7; }
        .actions { display:flex; flex-wrap:wrap; gap:14px; margin-top:28px; }
        .btn { appearance:none; border:0; text-decoration:none; border-radius:999px; padding:14px 22px; font-weight:800; font-size:14px; transition:transform .2s ease; display:inline-flex; align-items:center; gap:10px; }
        .btn:hover { transform:translateY(-2px); }
        .btn-primary { color:#fff; background:linear-gradient(135deg, var(--primary), var(--secondary)); }
        .btn-secondary { color:var(--text); background:transparent; border:1px solid var(--border); }
        .lock { margin-top:26px; width:112px; height:112px; border-radius:32px; display:grid; place-items:center; border:1px solid var(--border); background:linear-gradient(135deg, rgba(109,40,217,.15), rgba(236,72,153,.12)); font-size:44px; box-shadow:0 0 0 16px rgba(255,255,255,.02); animation: bob 6s ease-in-out infinite; }
        .ribbon { position:absolute; inset:auto; width:230px; height:230px; border-radius:999px; filter:blur(50px); opacity:.55; pointer-events:none; animation: drift 9s ease-in-out infinite; }
        .ribbon.one { background:rgba(109,40,217,.28); top:0; left:0; }
        .ribbon.two { background:rgba(236,72,153,.22); right:0; bottom:0; animation-delay:-4.5s; }
        @keyframes bob { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }
        @keyframes drift { 0%,100%{transform:translate3d(0,0,0) scale(1)} 50%{transform:translate3d(10px,-10px,0) scale(1.08)} }
        @media (max-width: 820px){ .card{padding:28px;} }
    </style>
</head>
<body>
    <main class="wrap">
        <div class="ribbon one"></div>
        <div class="ribbon two"></div>
        <section class="card">
            <div class="eyebrow" style="display:inline-flex;align-items:center;gap:10px;padding:8px 14px;border-radius:999px;border:1px solid var(--border);color:var(--muted);font-size:12px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;">
                <span style="width:10px;height:10px;border-radius:999px;background:linear-gradient(135deg,var(--primary),var(--secondary));display:inline-block;"></span> Error 403
            </div>
            <div class="code">403</div>
            <h1>Access Forbidden</h1>
            <p>You do not have permission to view this page or resource. If this seems wrong, contact support.</p>
            <div class="lock"><i class="fas fa-lock"></i></div>
            <div class="actions">
                <a class="btn btn-primary" href="index.php"><i class="fas fa-house"></i> Back Home</a>
                <a class="btn btn-secondary" href="index.php?module=info&page=support"><i class="fas fa-headset"></i> Contact Support</a>
            </div>
        </section>
    </main>
</body>
</html>
