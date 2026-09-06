<?php
/**
 * Landing Page Oficial — Sistema OCR & Conciliación Documental Inteligente
 * Modo Claro Tecnológico | Gráfico de Inferencia a la Derecha | RapidOCR + PDF417 + PyMuPDF
 */
$base = defined('BASE_PATH') ? BASE_PATH : '/SistemaOCR/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo ?? 'Sistema OCR — Conciliación Documental Inteligente con Visión Artificial') ?></title>
    <meta name="description" content="Plataforma de alta precisión para auditoría de documentos de identidad colombianos en PDF contra listados oficiales en Excel mediante RapidOCR neuronal y decodificación PDF417.">

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?= $base ?>public/img/favicon.svg">
    <link rel="alternate icon" href="<?= $base ?>public/img/favicon.svg">

    <!-- Tipografía Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Iconos Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --bg-canvas: #f8fafc;
            --bg-card: #ffffff;
            --primary: #4f46e5;
            --primary-dark: #3730a3;
            --primary-light: #e0e7ff;
            --cyan: #0284c7;
            --cyan-accent: #0ea5e9;
            --emerald: #10b981;
            --emerald-light: #ecfdf5;
            --slate-dark: #0f172a;
            --slate-muted: #475569;
            --slate-dim: #64748b;
            --border-light: #e2e8f0;
            --border-subtle: #f1f5f9;
            --font-main: 'Plus Jakarta Sans', -apple-system, sans-serif;
            --font-display: 'Space Grotesk', sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
            --shadow-card: 0 20px 45px -10px rgba(15, 23, 42, 0.1), 0 10px 20px -5px rgba(15, 23, 42, 0.05);
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font-main);
            background-color: var(--bg-canvas);
            color: var(--slate-muted);
            line-height: 1.6;
            overflow-x: hidden;
        }

        h1, h2, h3, h4 {
            font-family: var(--font-display);
            color: var(--slate-dark);
            letter-spacing: -0.025em;
        }

        /* ── ANIMACIONES DE INFERENCIA Y LÁSER ── */
        @keyframes laserScanContinuous {
            0% { top: 4%; opacity: 0.25; }
            15% { opacity: 1; }
            85% { opacity: 1; }
            100% { top: 94%; opacity: 0.25; }
        }

        @keyframes cardGentleFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-7px); }
        }

        @keyframes pulseDotLive {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.4); opacity: 0.6; }
        }

        @keyframes sweepButtonShine {
            0% { left: -100%; }
            50%, 100% { left: 140%; }
        }

        /* ── NAVBAR MODO CLARO ELEGANTE ── */
        .navbar-clean {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.85);
            transition: all 0.3s;
        }

        .navbar-inner {
            max-width: 1340px;
            margin: 0 auto;
            padding: 12px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand-link-box {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .brand-icon-scanner {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary) 0%, #312e81 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 20px;
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.3);
            transition: transform 0.3s;
        }

        .brand-link-box:hover .brand-icon-scanner {
            transform: translateY(-2px) scale(1.04);
        }

        .brand-text-wrapper {
            display: flex;
            flex-direction: column;
        }

        .brand-title {
            font-family: var(--font-display);
            font-size: 21px;
            font-weight: 800;
            line-height: 1.1;
            color: var(--slate-dark);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .brand-title span.badge-ocr-text {
            color: var(--primary);
        }

        .brand-subtitle-tag {
            font-family: var(--font-mono);
            font-size: 10px;
            font-weight: 700;
            color: var(--slate-dim);
            letter-spacing: 0.6px;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 2px;
        }

        .live-dot-indicator {
            width: 6.5px;
            height: 6.5px;
            border-radius: 50%;
            background: var(--emerald);
            box-shadow: 0 0 8px var(--emerald);
            animation: pulseDotLive 2s infinite;
        }

        .nav-menu-links {
            display: flex;
            align-items: center;
            gap: 32px;
            list-style: none;
        }

        .nav-menu-links a {
            text-decoration: none;
            color: var(--slate-muted);
            font-size: 14.5px;
            font-weight: 600;
            transition: color 0.25s;
        }

        .nav-menu-links a:hover {
            color: var(--primary);
        }

        .btn-portal-action {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #ffffff;
            border: none;
            padding: 11px 24px;
            border-radius: 9999px;
            font-family: var(--font-display);
            font-size: 14px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 9px;
            cursor: pointer;
            box-shadow: 0 6px 18px rgba(79, 70, 229, 0.35);
            transition: all 0.3s;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        .btn-portal-action::after {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 60%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.35), transparent);
            transform: skewX(-25deg);
            animation: sweepButtonShine 4s infinite;
        }

        .btn-portal-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(79, 70, 229, 0.45);
            color: #ffffff;
        }

        /* ── HERO EN MODO CLARO CON GRÁFICO A LA DERECHA ── */
        .hero-stage-section {
            padding: 155px 28px 90px;
            position: relative;
            background: 
                radial-gradient(circle at 88% 25%, rgba(79, 70, 229, 0.09) 0%, transparent 55%),
                radial-gradient(circle at 12% 75%, rgba(2, 132, 199, 0.08) 0%, transparent 60%),
                linear-gradient(180deg, #ffffff 0%, var(--bg-canvas) 100%);
            overflow: hidden;
        }

        .hero-stage-section::before {
            content: '';
            position: absolute;
            inset: 0;
            background-size: 40px 40px;
            background-image: 
                linear-gradient(to right, rgba(226, 232, 240, 0.6) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(226, 232, 240, 0.6) 1px, transparent 1px);
            opacity: 0.7;
            pointer-events: none;
        }

        .hero-grid-container {
            max-width: 1340px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1.05fr;
            gap: 50px;
            align-items: center;
            position: relative;
            z-index: 10;
        }

        .ai-chip-pill {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            background: var(--primary-light);
            color: var(--primary-dark);
            border: 1px solid #c7d2fe;
            padding: 7px 18px;
            border-radius: 9999px;
            font-family: var(--font-mono);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 22px;
            box-shadow: 0 2px 8px rgba(79, 70, 229, 0.1);
        }

        .hero-title-main {
            font-size: 48px;
            line-height: 1.15;
            color: var(--slate-dark);
            font-weight: 800;
            margin-bottom: 22px;
        }

        .hero-title-main .gradient-text-hero {
            background: linear-gradient(135deg, var(--primary) 0%, var(--cyan) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: inline-block;
        }

        .hero-lead-text {
            font-size: 17px;
            color: var(--slate-muted);
            margin-bottom: 34px;
            max-width: 580px;
            line-height: 1.7;
        }

        .hero-cta-row {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
            margin-bottom: 38px;
        }

        .btn-outline-clear {
            background: #ffffff;
            color: var(--slate-dark);
            border: 1.8px solid #cbd5e1;
            padding: 11px 26px;
            border-radius: 9999px;
            font-family: var(--font-display);
            font-size: 14.5px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 9px;
            transition: all 0.25s ease;
            box-shadow: 0 2px 6px rgba(0,0,0,0.03);
        }

        .btn-outline-clear:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(79, 70, 229, 0.12);
        }

        .hero-stats-row {
            display: flex;
            align-items: center;
            gap: 34px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
        }

        .stat-box {
            display: flex;
            flex-direction: column;
        }

        .stat-val {
            font-family: var(--font-display);
            font-size: 26px;
            font-weight: 800;
            color: var(--slate-dark);
            line-height: 1;
        }

        .stat-lbl {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--slate-dim);
            margin-top: 4px;
        }

        /* ── ESTRUCTURA DIFERENCIADA OCR: ESTACIÓN HOLOGRÁFICA CYBER-TECH ── */
        .hero-showcase-wrapper {
            position: relative;
        }

        .hero-showcase-wrapper::before {
            content: '';
            position: absolute;
            inset: -15px;
            background: radial-gradient(circle, rgba(79, 70, 229, 0.18) 0%, rgba(6, 182, 212, 0.12) 60%, transparent 80%);
            border-radius: 36px;
            filter: blur(28px);
            z-index: 1;
        }

        .card-showcase-container {
            background: #090e1a;
            border-radius: 26px;
            padding: 26px;
            border: 1px solid #1e293b;
            box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.35), 0 0 0 1px rgba(79, 70, 229, 0.2);
            position: relative;
            z-index: 2;
            color: #ffffff;
        }

        .dash-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            margin-bottom: 18px;
        }

        .dash-header-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .dash-status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #06b6d4;
            box-shadow: 0 0 12px #06b6d4, 0 0 20px #06b6d4;
            animation: pulseRadar 2s infinite;
        }

        .dash-header-text h3 {
            font-size: 15.5px;
            font-weight: 800;
            color: #ffffff;
            margin: 0;
            letter-spacing: 0.3px;
        }

        .dash-header-text span {
            font-size: 11.5px;
            color: #94a3b8;
            font-family: var(--font-mono);
        }

        .dash-live-badge {
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: 0.8px;
            color: #38bdf8;
            background: rgba(56, 189, 248, 0.12);
            padding: 4px 11px;
            border-radius: 6px;
            border: 1px solid rgba(56, 189, 248, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: var(--font-mono);
        }

        /* ══ EL GRÁFICO INTERACTIVO DE ESCANEO OCR VS EXCEL CON LÁSER ══ */
        .scanner-interactive-card {
            background: #0f172a;
            border-radius: 18px;
            padding: 20px;
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.09);
            position: relative;
            overflow: hidden;
            margin-bottom: 18px;
            box-shadow: inset 0 0 30px rgba(0,0,0,0.6);
        }

        /* Línea Láser animada continua de escaneo OCR */
        .scan-laser-line {
            position: absolute;
            left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, #06b6d4, #4f46e5, transparent);
            box-shadow: 0 0 14px #06b6d4, 0 0 24px #4f46e5;
            animation: laserScanContinuous 3s ease-in-out infinite alternate;
            z-index: 5;
            pointer-events: none;
        }

        .scanner-grid-split {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            position: relative;
            z-index: 2;
        }

        .scan-col-doc {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            padding: 14px;
        }

        .scan-col-title {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .scan-badge-ocr { color: #38bdf8; }
        .scan-badge-excel { color: #34d399; }

        .mock-doc-box {
            font-family: var(--font-mono);
            font-size: 11.5px;
            line-height: 1.5;
            color: #cbd5e1;
        }

        .mock-doc-box .doc-val {
            color: #ffffff;
            font-weight: 700;
        }

        .mock-mrz-stream {
            font-family: var(--font-mono);
            font-size: 10px;
            color: #64748b;
            letter-spacing: 1px;
            margin-top: 8px;
            padding-top: 6px;
            border-top: 1px dashed rgba(255, 255, 255, 0.1);
            word-break: break-all;
        }

        .match-badge-pill {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 14px;
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.4);
            color: #34d399;
            padding: 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        /* NUEVO DISEÑO INFERIOR OCR: PIPELINE DE INFERENCIA EN TIEMPO REAL (TERMINAL HUD) */
        .dash-pipeline-strip {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-radius: 12px;
            padding: 10px 14px;
            font-family: var(--font-mono);
            font-size: 11px;
        }

        .pipeline-step-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #cbd5e1;
        }

        .pipeline-step-item i {
            color: #06b6d4;
            font-size: 13px;
        }

        .pipeline-arrow {
            color: #475569;
            font-size: 10px;
        }

        .floating-badge-bottom {
            position: absolute;
            bottom: -16px;
            right: 25px;
            background: #0f172a;
            border: 1px solid rgba(6, 182, 212, 0.4);
            border-radius: 9999px;
            padding: 7px 18px;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            font-size: 11.5px;
            font-weight: 700;
            color: #38bdf8;
            font-family: var(--font-mono);
            z-index: 10;
        }

        .floating-badge-bottom i {
            color: #34d399;
            font-size: 14px;
        }

        /* ── SECCIONES INSTITUCIONALES EN MODO CLARO ── */
        .section-container {
            max-width: 1340px;
            margin: 0 auto;
            padding: 90px 28px;
        }

        .section-tagline-ai {
            display: inline-block;
            font-family: var(--font-mono);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .section-title-clear {
            font-size: 38px;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 18px;
            color: var(--slate-dark);
        }

        .about-split-view {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
        }

        .tech-feature-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .tech-card-item {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.03);
            transition: all 0.3s;
        }

        .tech-card-item:hover {
            transform: translateY(-3px);
            border-color: var(--primary);
            box-shadow: 0 12px 28px rgba(79, 70, 229, 0.12);
        }

        .tech-icon-container {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 14px;
        }

        .tech-card-item h4 {
            font-size: 17px;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .tech-card-item p {
            font-size: 13.5px;
            color: var(--slate-dim);
            line-height: 1.55;
        }

        /* ── FLUJO OPERATIVO (3 PASOS) ── */
        .process-flow-strip {
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            padding: 90px 28px;
        }

        .process-grid-3-col {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 28px;
            margin-top: 40px;
        }

        .step-process-box {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 22px;
            padding: 34px 28px;
            position: relative;
            transition: all 0.3s;
        }

        .step-process-box:hover {
            border-color: var(--cyan);
            transform: translateY(-3px);
            box-shadow: 0 14px 30px rgba(2, 132, 199, 0.12);
            background: #ffffff;
        }

        .step-badge-number {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--cyan) 100%);
            color: #ffffff;
            font-family: var(--font-display);
            font-size: 17px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .step-process-box h3 {
            font-size: 19px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .step-process-box p {
            font-size: 14.5px;
            color: var(--slate-dim);
            line-height: 1.6;
        }

        /* ── FOOTER CLARO Y MODERNO ── */
        .footer-clear-tech {
            background: #0f172a;
            color: #cbd5e1;
            padding: 70px 28px 30px;
            position: relative;
        }

        .footer-top-wave-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding-bottom: 40px;
            border-bottom: 1px solid #1e293b;
            margin-bottom: 40px;
        }

        .footer-tech-tag {
            font-family: var(--font-mono);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: #38bdf8;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .footer-3-columns {
            max-width: 1340px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 50px;
            margin-bottom: 40px;
        }

        .footer-col-title {
            color: #ffffff;
            font-size: 16px;
            font-weight: 800;
            margin-bottom: 16px;
        }

        .footer-copyright-bar {
            max-width: 1340px;
            margin: 0 auto;
            padding-top: 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: #64748b;
        }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .hero-grid-container { grid-template-columns: 1fr; text-align: center; gap: 45px; }
            .hero-title-main { font-size: 40px; }
            .hero-lead-text { margin: 0 auto 30px; }
            .hero-cta-row { justify-content: center; }
            .hero-stats-row { justify-content: center; }
            .about-split-view { grid-template-columns: 1fr; }
            .tech-feature-cards { grid-template-columns: 1fr; }
            .process-grid-3-col { grid-template-columns: 1fr; }
            .nav-menu-links { display: none; }
            .footer-3-columns { grid-template-columns: 1fr; }
            .floating-badge-bottom { left: 50%; transform: translateX(-50%); }
        }

        @media (max-width: 640px) {
            .scanner-grid-split { grid-template-columns: 1fr; }
            .dash-metrics-grid { grid-template-columns: 1fr; }
            .hero-title-main { font-size: 32px; }
        }
    </style>
</head>
<body>

    <!-- ══ NAVBAR MODO CLARO ══ -->
    <header class="navbar-clean">
        <div class="navbar-inner">
            <a href="<?= $base ?>" class="brand-link-box" title="Sistema OCR & Conciliación">
                <div class="brand-icon-scanner">
                    <i class="bi bi-qr-code-scan"></i>
                </div>
                <div class="brand-text-wrapper">
                    <div class="brand-title">
                        SISTEMA <span class="badge-ocr-text">OCR</span>
                    </div>
                    <span class="brand-subtitle-tag">
                        <span class="live-dot-indicator"></span> CONCILIACIÓN DOCUMENTAL
                    </span>
                </div>
            </a>

            <ul class="nav-menu-links">
                <li><a href="#capacidades">Capacidades OCR</a></li>
                <li><a href="#proceso">Flujo de Cotejo</a></li>
                <li><a href="#arquitectura">Arquitectura</a></li>
            </ul>

            <a href="<?= $base ?>index.php?ruta=home/index" class="btn-portal-action">
                <i class="bi bi-speedometer2"></i> Acceso a la Plataforma
            </a>
        </div>
    </header>

    <!-- ══ HERO SECTION MODO CLARO CON EL GRÁFICO A LA DERECHA ══ -->
    <section class="hero-stage-section">
        <div class="hero-grid-container">
            <!-- Columna Izquierda: Información de alto impacto -->
            <div>
                <div class="ai-chip-pill">
                    <span class="live-dot-indicator"></span>
                    RapidOCR &bull; PDF417 &bull; Python Multihilo
                </div>

                <h1 class="hero-title-main">
                    Auditoría documental y cotejo masivo con <span class="gradient-text-hero">Visión Artificial</span>
                </h1>

                <p class="hero-lead-text">
                    Plataforma especializada en la extracción de cédulas de ciudadanía colombianas (digitales, físicas y contraseñas). Contrasta de forma automática las inscripciones en <strong>Excel</strong> contra los expedientes en <strong>PDF</strong> con resolución de discrepancias en tiempo real.
                </p>

                <div class="hero-cta-row">
                    <a href="<?= $base ?>index.php?ruta=home/index" class="btn-portal-action" style="padding: 13px 30px; font-size: 15px;">
                        <i class="bi bi-box-arrow-in-right"></i> Entrar al Panel de Control
                    </a>
                    <a href="<?= $base ?>index.php?ruta=ficha/subir" class="btn-outline-clear">
                        <i class="bi bi-cloud-arrow-up-fill" style="color:var(--primary);"></i> Cargar Nueva Ficha
                    </a>
                </div>

                <div class="hero-stats-row">
                    <div class="stat-box">
                        <span class="stat-val">99.8%</span>
                        <span class="stat-lbl">Precisión de Extracción</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-val">&lt; 1.2s</span>
                        <span class="stat-lbl">Inferencia por Documento</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-val">MRZ / 2D</span>
                        <span class="stat-lbl">Cédula Digital y Tradicional</span>
                    </div>
                </div>
            </div>

            <!-- Columna Derecha: EL GRÁFICO EXACTO CON EL LÁSER Y EL COTEJO LADO A LADO -->
            <div class="hero-showcase-wrapper">
                <div class="card-showcase-container">
                    <div class="dash-card-header">
                        <div class="dash-header-title">
                            <span class="dash-status-dot"></span>
                            <div class="dash-header-text">
                                <h3>Consola de Inferencia OCR</h3>
                                <span>Cotejo Automático en Tiempo Real</span>
                            </div>
                        </div>
                        <span class="dash-live-badge">
                            <i class="bi bi-circle-fill" style="font-size:7px;"></i> MOTOR ACTIVO
                        </span>
                    </div>

                    <!-- Exhibidor del Scanner (El gráfico que te gustó) -->
                    <div class="scanner-interactive-card">
                        <div class="scan-laser-line"></div>
                        <div class="scanner-grid-split">
                            <!-- Columna OCR Documento -->
                            <div class="scan-col-doc">
                                <span class="scan-col-title scan-badge-ocr">
                                    <i class="bi bi-camera-fill"></i> OCR / PDF417 Extraído
                                </span>
                                <div class="mock-doc-box">
                                    <div>TIPO: <span class="doc-val">CC</span></div>
                                    <div>NUMERO: <span class="doc-val">1.025.448.910</span></div>
                                    <div>APELLIDOS: <span class="doc-val">LIZCANO SUAREZ</span></div>
                                    <div>NOMBRES: <span class="doc-val">SANTIAGO</span></div>
                                </div>
                                <div class="mock-mrz-stream">
                                    I<COL1025448910<<<<<<<<<<<8<br>
                                    0001017M3012314COL<<<<<<<<
                                </div>
                            </div>

                            <!-- Columna Planilla Excel -->
                            <div class="scan-col-doc">
                                <span class="scan-col-title scan-badge-excel">
                                    <i class="bi bi-file-earmark-spreadsheet-fill"></i> Registro en Excel
                                </span>
                                <div class="mock-doc-box">
                                    <div>DOC: <span class="doc-val" style="color:#a7f3d0;">1025448910</span></div>
                                    <div>NOMBRE REGISTRADO: <br><span class="doc-val" style="color:#a7f3d0;">SANTIAGO LIZCANO SUAREZ</span></div>
                                    <div style="margin-top:10px;">ESTADO: <span class="doc-val" style="color:#34d399;">PREINSCRITO</span></div>
                                </div>
                            </div>
                        </div>

                        <!-- Match Confirmado -->
                        <div class="match-badge-pill">
                            <i class="bi bi-shield-check"></i>
                            <span>CONCILIADO — COINCIDENCIA BIOMÉTRICA AL 100%</span>
                        </div>
                    </div>

                    <!-- Pipeline de Inferencia Holográfica HUD (Diferente a Impobiomedical) -->
                    <div class="dash-pipeline-strip">
                        <div class="pipeline-step-item">
                            <i class="bi bi-file-earmark-pdf"></i>
                            <span>PDF Ingest</span>
                        </div>
                        <span class="pipeline-arrow"><i class="bi bi-chevron-right"></i></span>
                        <div class="pipeline-step-item">
                            <i class="bi bi-cpu-fill"></i>
                            <span>RapidOCR 4.1</span>
                        </div>
                        <span class="pipeline-arrow"><i class="bi bi-chevron-right"></i></span>
                        <div class="pipeline-step-item">
                            <i class="bi bi-qr-code"></i>
                            <span>PDF417 Decode</span>
                        </div>
                        <span class="pipeline-arrow"><i class="bi bi-chevron-right"></i></span>
                        <div class="pipeline-step-item" style="color: #34d399;">
                            <i class="bi bi-shield-check"></i>
                            <span>Cotejo 100%</span>
                        </div>
                    </div>
                </div>

                <div class="floating-badge-bottom">
                    <i class="bi bi-terminal-fill"></i>
                    <span>Microservicio Python Multihilo & FastInference</span>
                </div>
            </div>
        </div>
    </section>

    <!-- ══ SECCIÓN DE CAPACIDADES TÉCNICAS (MODO CLARO) ══ -->
    <section class="section-container" id="capacidades">
        <div class="about-split-view">
            <div>
                <span class="section-tagline-ai">// Visión por Computadora</span>
                <h2 class="section-title-clear">Procesamiento inteligente de cédulas frente y reverso</h2>
                <p style="font-size: 16px; color: var(--slate-dim); margin-bottom: 24px; line-height: 1.7;">
                    El motor analiza expedientes complejos unificando automáticamente las dos caras del documento de identidad (frente con fotografía y reverso con código de barras PDF417 o zona mecánica MRZ).
                </p>
                <div style="display:flex; flex-direction:column; gap:14px;">
                    <div style="display:flex; align-items:flex-start; gap:12px;">
                        <i class="bi bi-check-circle-fill" style="color:var(--primary); font-size:20px; margin-top:2px;"></i>
                        <div>
                            <strong style="color:var(--slate-dark);">Inferencia con ONNX Runtime:</strong>
                            <p style="font-size:14px; color:var(--slate-dim);">Detección rápida de textos y decodificación de números de cédula con alta tolerancia a rotaciones y desenfoques.</p>
                        </div>
                    </div>
                    <div style="display:flex; align-items:flex-start; gap:12px;">
                        <i class="bi bi-check-circle-fill" style="color:var(--primary); font-size:20px; margin-top:2px;"></i>
                        <div>
                            <strong style="color:var(--slate-dark);">Rasterizado PyMuPDF en Memoria:</strong>
                            <p style="font-size:14px; color:var(--slate-dim);">Conversión ultrarrápida de archivos PDF a matrices de imagen optimizadas sin degradar el almacenamiento.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tech-feature-cards">
                <div class="tech-card-item">
                    <div class="tech-icon-container"><i class="bi bi-upc-scan"></i></div>
                    <h4>Código PDF417</h4>
                    <p>Decodificación de la huella bidimensional en cédulas colombianas tradicionales.</p>
                </div>
                <div class="tech-card-item">
                    <div class="tech-icon-container"><i class="bi bi-person-badge"></i></div>
                    <h4>Cédula Digital MRZ</h4>
                    <p>Lectura de las dos líneas de verificación mecánica según estándar OACI / ICAO.</p>
                </div>
                <div class="tech-card-item">
                    <div class="tech-icon-container"><i class="bi bi-layers-half"></i></div>
                    <h4>Agrupación Frente/Reverso</h4>
                    <p>Fusión algorítmica de páginas correspondientes al mismo aspirante.</p>
                </div>
                <div class="tech-card-item">
                    <div class="tech-icon-container"><i class="bi bi-file-earmark-excel"></i></div>
                    <h4>Informe XLSX Comparativo</h4>
                    <p>Generación de reportes corporativos lado a lado con marcas de color según estado.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ══ FLUJO OPERATIVO (3 PASOS) ══ -->
    <section class="process-flow-strip" id="proceso">
        <div style="max-width:1340px; margin:0 auto;">
            <div style="text-align:center; max-width:680px; margin:0 auto;">
                <span class="section-tagline-ai">// Ciclo de Auditoría</span>
                <h2 class="section-title-clear">De los archivos brutos a la conciliación en 3 etapas</h2>
            </div>

            <div class="process-grid-3-col">
                <div class="step-process-box">
                    <div class="step-badge-number">1</div>
                    <h3>Carga de Insumos</h3>
                    <p>Se sube el reporte oficial de inscripciones en Excel (.xlsx) y el PDF multipágina con las cédulas digitalizadas de la cohorte.</p>
                </div>
                <div class="step-process-box">
                    <div class="step-badge-number">2</div>
                    <h3>Inferencia y Cotejo</h3>
                    <p>El microservicio multihilo procesa las páginas, extrae nombres y documentos, cotejando cada participante contra la base de datos.</p>
                </div>
                <div class="step-process-box">
                    <div class="step-badge-number">3</div>
                    <h3>Auditoría y Exportación</h3>
                    <p>El auditor visualiza las discrepancias con edición en vivo y descarga el dictamen final en Excel con total rigor formal.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ══ FOOTER CLARO Y PROFESIONAL ══ -->
    <footer class="footer-clear-tech" id="arquitectura">
        <div class="footer-top-wave-row">
            <div class="footer-tech-tag">
                <i class="bi bi-cpu"></i> Motor de Extracción Documental &bull; Inferencia Activa
            </div>
            <div style="font-family:var(--font-mono); font-size:12px; color:#94a3b8;">
                RapidOCR &bull; Python Flask / Waitress &bull; MySQL
            </div>
        </div>

        <div class="footer-3-columns">
            <div>
                <div class="brand-title" style="color:#ffffff; margin-bottom:14px;">
                    SISTEMA <span style="color:#818cf8; margin-left:4px;">OCR</span>
                </div>
                <p style="font-size:14px; line-height:1.65; color:#94a3b8; max-width:420px;">
                    Solución de software de alto rendimiento para auditoría de documentos de identidad y planillas institucionales. Diseñado con arquitectura limpia en PHP 8.2 y motor neuronal de visión por computadora.
                </p>
            </div>

            <div>
                <h4 class="footer-col-title">Módulos del Sistema</h4>
                <ul style="list-style:none; font-size:14px; display:flex; flex-direction:column; gap:8px;">
                    <li><a href="<?= $base ?>index.php?ruta=home/index" style="color:#cbd5e1; text-decoration:none;">Dashboard de Fichas</a></li>
                    <li><a href="<?= $base ?>index.php?ruta=ficha/subir" style="color:#cbd5e1; text-decoration:none;">Carga de Expedientes</a></li>
                    <li><a href="<?= $base ?>index.php?ruta=cruce/informe" style="color:#cbd5e1; text-decoration:none;">Matriz de Conciliación</a></li>
                </ul>
            </div>

            <div>
                <h4 class="footer-col-title">Acceso Operativo</h4>
                <p style="font-size:13.5px; color:#94a3b8; margin-bottom:16px;">
                    Ingresa a la consola operativa para gestionar y auditar las cohortes de inscripción.
                </p>
                <a href="<?= $base ?>index.php?ruta=home/index" class="btn-portal-action" style="width:100%; justify-content:center; padding:10px 18px; font-size:13.5px;">
                    <i class="bi bi-speedometer2"></i> Abrir Consola
                </a>
            </div>
        </div>

        <div class="footer-copyright-bar">
            <span>&copy; <?= date('Y') ?> Sistema OCR &bull; Conciliación y Auditoría Documental Inteligente. Todos los derechos reservados.</span>
            <span>PHP 8.2 &bull; RapidOCR &bull; Python &bull; MySQL</span>
        </div>
    </footer>

</body>
</html>