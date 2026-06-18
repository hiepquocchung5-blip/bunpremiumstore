<?php
// modules/error/404.php
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($_SESSION['theme'] ?? 'dark'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0b0f1a">
    <title>404 | DigitalMM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg: #0b0f1a;
            --card: rgba(15, 23, 42, 0.82);
            --border: rgba(255,255,255,0.08);
            --text: #f8fafc;
            --muted: #94a3b8;
            --primary: #6d28d9;
            --secondary: #ec4899;
            --accent: #06b6d4;
        }
        html[data-theme="light"] {
            --bg: #f8fafc;
            --card: rgba(255,255,255,0.92);
            --border: #e2e8f0;
            --text: #0f172a;
            --muted: #64748b;
            --primary: #6d28d9;
            --secondary: #ec4899;
            --accent: #06b6d4;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: Arial, Helvetica, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(109,40,217,.18), transparent 30%),
                radial-gradient(circle at top right, rgba(236,72,153,.15), transparent 28%),
                radial-gradient(circle at bottom center, rgba(6,182,212,.12), transparent 32%),
                var(--bg);
            overflow: hidden;
        }
        .wrap {
            width: min(920px, calc(100vw - 32px));
            position: relative;
            padding: 28px;
        }
        .glow {
            position: absolute;
            inset: auto;
            width: 240px;
            height: 240px;
            border-radius: 999px;
            filter: blur(50px);
            opacity: .55;
            pointer-events: none;
            animation: drift 9s ease-in-out infinite;
        }
        .glow.one { background: rgba(109,40,217,.30); top: 0; left: 0; }
        .glow.two { background: rgba(236,72,153,.24); right: 0; bottom: 0; animation-delay: -4.5s; }
        .card {
            position: relative;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 32px;
            backdrop-filter: blur(20px);
            padding: 40px;
            box-shadow: 0 24px 80px rgba(0,0,0,.28);
        }
        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            border-radius: 999px;
            border: 1px solid var(--border);
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .18em;
            text-transform: uppercase;
        }
        .code {
            font-size: clamp(72px, 12vw, 132px);
            line-height: 1;
            margin: 18px 0 8px;
            font-weight: 900;
            letter-spacing: -0.08em;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        h1 {
            font-size: clamp(28px, 4vw, 52px);
            margin: 0 0 10px;
        }
        p {
            margin: 0;
            color: var(--muted);
            font-size: 16px;
            line-height: 1.7;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-top: 28px;
        }
        .btn {
            appearance: none;
            border: 0;
            text-decoration: none;
            border-radius: 999px;
            padding: 14px 22px;
            font-weight: 800;
            font-size: 14px;
            transition: transform .2s ease, opacity .2s ease, box-shadow .2s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .btn:hover { transform: translateY(-2px); }
        .btn-primary {
            color: #fff;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            box-shadow: 0 18px 40px rgba(109,40,217,.22);
        }
        .btn-secondary {
            color: var(--text);
            background: transparent;
            border: 1px solid var(--border);
        }
        .grid {
            display: grid;
            grid-template-columns: 1.2fr .8fr;
            gap: 26px;
            align-items: center;
        }
        .orb {
            position: relative;
            min-height: 320px;
            display: grid;
            place-items: center;
        }
        .planet {
            width: 210px;
            height: 210px;
            border-radius: 50%;
            background:
                radial-gradient(circle at 30% 30%, rgba(255,255,255,.85), rgba(255,255,255,.04) 22%, transparent 23%),
                radial-gradient(circle at 50% 50%, rgba(6,182,212,.15), rgba(109,40,217,.22) 58%, rgba(15,23,42,.9) 100%);
            box-shadow: 0 0 0 18px rgba(255,255,255,.03), 0 0 60px rgba(109,40,217,.18);
            animation: float 7s ease-in-out infinite;
        }
        .ring {
            position: absolute;
            width: 320px;
            height: 120px;
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,.08);
            transform: rotate(-18deg);
            animation: spin 16s linear infinite;
        }
        .ring.two {
            width: 260px;
            height: 96px;
            opacity: .55;
            animation-direction: reverse;
        }
        .status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 18px;
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .pulse {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            box-shadow: 0 0 0 0 rgba(109,40,217,.35);
            animation: pulse 2s infinite;
        }
        @keyframes float { 0%,100% { transform: translateY(0px); } 50% { transform: translateY(-12px); } }
        @keyframes spin { from { transform: rotate(-18deg) scale(1); } to { transform: rotate(342deg) scale(1); } }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(109,40,217,.32); }
            70% { box-shadow: 0 0 0 16px rgba(109,40,217,0); }
            100% { box-shadow: 0 0 0 0 rgba(109,40,217,0); }
        }
        @keyframes drift {
            0%,100% { transform: translate3d(0,0,0) scale(1); }
            50% { transform: translate3d(10px,-10px,0) scale(1.08); }
        }
        @media (max-width: 820px) {
            .grid { grid-template-columns: 1fr; }
            .orb { min-height: 220px; order: -1; }
            .card { padding: 28px; }
        }
    </style>
</head>
<body>
    <main class="wrap">
        <div class="glow one"></div>
        <div class="glow two"></div>
        <section class="card">
            <div class="grid">
                <div>
                    <div class="eyebrow"><span class="pulse"></span> Error 404</div>
                    <div class="code">404</div>
                    <h1>Page Not Found</h1>
                    <p>The page you requested could not be found. It may have moved, been renamed, or never existed.</p>
                    <div class="status"><span class="pulse"></span> Navigation interrupted</div>
                    <div class="actions">
                        <a class="btn btn-primary" href="index.php"><i class="fas fa-house"></i> Back Home</a>
                        <a class="btn btn-secondary" href="index.php?module=shop&page=category"><i class="fas fa-store"></i> Browse Store</a>
                    </div>
                </div>
                <div class="orb" aria-hidden="true">
                    <div class="ring"></div>
                    <div class="ring two"></div>
                    <div class="planet"></div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
