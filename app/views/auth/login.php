<?php

require_once __DIR__ . '/../../core/Auth.php';

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login - MYOL HRIS</title>

    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
        }

        body {
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            background:
                radial-gradient(circle at 15% 20%,
                    rgba(224, 0, 0, 0.12),
                    transparent 30%),
                radial-gradient(circle at 85% 80%,
                    rgba(255, 255, 255, 0.04),
                    transparent 30%),
                #101214;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px;
            overflow: hidden;
            position: relative;
        }


        /* =====================================================
           ANIMATED BACKGROUND
           ===================================================== */

        .background-network {
            position: fixed;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
            z-index: 0;
        }

        .network-dot {
            position: absolute;

            width: 7px;
            height: 7px;

            border-radius: 50%;

            background: #e00000;

            opacity: 0.55;

            box-shadow:
                0 0 12px rgba(224, 0, 0, 0.7),
                0 0 25px rgba(224, 0, 0, 0.35);

            animation: floatDot 5s ease-in-out infinite;
        }

        .network-line {
            position: absolute;
            height: 1px;
            background: linear-gradient(90deg,
                    transparent,
                    rgba(224, 0, 0, 0.16),
                    transparent);
            transform-origin: left center;
            animation: linePulse 5s ease-in-out infinite;
        }

        .dot-1 {
            top: 15%;
            left: 8%;
            animation-delay: 0s;
        }

        .dot-2 {
            top: 30%;
            left: 20%;
            animation-delay: 1.5s;
        }

        .dot-3 {
            top: 75%;
            left: 10%;
            animation-delay: 3s;
        }

        .dot-4 {
            top: 18%;
            right: 13%;
            animation-delay: 2s;
        }

        .dot-5 {
            top: 72%;
            right: 10%;
            animation-delay: 4s;
        }

        .dot-6 {
            top: 85%;
            right: 28%;
            animation-delay: 1s;
        }

        .line-1 {
            width: 180px;
            top: 23%;
            left: 9%;
            transform: rotate(25deg);
        }

        .line-2 {
            width: 230px;
            top: 31%;
            right: 11%;
            transform: rotate(155deg);
        }

        .line-3 {
            width: 190px;
            bottom: 22%;
            left: 9%;
            transform: rotate(-20deg);
        }

        .line-4 {
            width: 210px;
            bottom: 18%;
            right: 13%;
            transform: rotate(20deg);
        }

        @keyframes floatDot {

            0%,
            100% {
                transform: translate(0, 0) scale(1);
                opacity: 0.35;
            }

            25% {
                transform: translate(25px, -20px) scale(1.3);
                opacity: 0.8;
            }

            50% {
                transform: translate(-10px, -40px) scale(0.8);
                opacity: 0.45;
            }

            75% {
                transform: translate(-30px, -15px) scale(1.2);
                opacity: 0.75;
            }
        }

        @keyframes linePulse {

            0%,
            100% {
                opacity: 0.45;
            }

            50% {
                opacity: 0.75;
            }
        }


        /* =====================================================
           MAIN LOGIN WRAPPER
           ===================================================== */

        .login-wrapper {
            position: relative;
            z-index: 2;

            width: 100%;
            max-width: 1050px;
            min-height: 600px;

            display: grid;
            grid-template-columns: 1fr 430px;

            background: rgba(25, 27, 29, 0.94);

            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 18px;

            overflow: hidden;

            box-shadow:
                0 25px 70px rgba(0, 0, 0, 0.5),
                0 0 80px rgba(224, 0, 0, 0.04);

            animation: containerAppear 0.8s ease-out;
        }

        @keyframes containerAppear {

            from {
                opacity: 0;
                transform: translateY(18px) scale(0.98);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }


        /* =====================================================
           LEFT BRANDING SECTION
           ===================================================== */

        .brand-section {
            position: relative;

            display: flex;
            flex-direction: column;
            justify-content: center;

            padding: 70px;

            background:
                linear-gradient(135deg,
                    rgba(224, 0, 0, 0.14),
                    transparent 45%),
                #151719;

            overflow: hidden;
        }

        .brand-section::before {
            content: "";

            position: absolute;

            width: 350px;
            height: 350px;

            right: -170px;
            bottom: -180px;

            border-radius: 50%;

            border: 1px solid rgba(224, 0, 0, 0.12);

            animation: redPulse 5s ease-in-out infinite;
        }

        .brand-section::after {
            content: "";

            position: absolute;

            width: 220px;
            height: 220px;

            right: -105px;
            bottom: -115px;

            border-radius: 50%;

            background: rgba(224, 0, 0, 0.07);

            animation: redPulse 5s ease-in-out infinite 1s;
        }

        @keyframes redPulse {

            0%,
            100% {
                transform: scale(0.9);
                opacity: 0.4;
            }

            50% {
                transform: scale(1.08);
                opacity: 0.9;
            }
        }


        .brand-content {
            position: relative;
            z-index: 2;
        }


        /* =====================================================
           LOGO
           ===================================================== */

        .logo {
            width: 190px;
            height: auto;

            object-fit: contain;

            margin-bottom: 38px;

            background: #ffffff;

            animation: logoAppear 1s ease-out 0.2s both;
        }

        @keyframes logoAppear {

            from {
                opacity: 0;
                transform: translateY(-12px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }


        /* =====================================================
           HRIS TITLE
           ===================================================== */

        .brand-section h1 {
            margin: 0 0 18px;

            font-size: 42px;
            line-height: 1.15;

            font-weight: 700;

            letter-spacing: -1px;

            animation: textAppear 0.8s ease-out 0.35s both;
        }

        .brand-section h1 span {
            color: #e00000;
        }

        .brand-section p {
            max-width: 480px;

            margin: 0;

            color: #b9bdc1;

            font-size: 16px;
            line-height: 1.7;

            animation: textAppear 0.8s ease-out 0.5s both;
        }

        @keyframes textAppear {

            from {
                opacity: 0;
                transform: translateY(12px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }


        /* =====================================================
           HRIS PROCESS FLOW
           ===================================================== */


        /* =====================================================
   HRIS PROCESS FLOW
   ===================================================== */

        .hris-flow {
            display: flex;
            align-items: center;
            margin-top: 35px;
            gap: 9px;

            animation: textAppear 0.8s ease-out 0.65s both;
        }

        .flow-item {
            display: flex;
            align-items: center;
            gap: 6px;

            color: #9da2a6;

            font-size: 10px;
            white-space: nowrap;

            transition: color 0.3s ease;
        }

        .flow-icon {
            position: relative;

            width: 27px;
            height: 27px;

            border-radius: 50%;

            display: flex;
            align-items: center;
            justify-content: center;

            border: 1px solid rgba(224, 0, 0, 0.55);

            color: #ff2020;

            font-size: 9px;
            font-weight: bold;

            background: rgba(224, 0, 0, 0.08);

            box-shadow:
                0 0 8px rgba(224, 0, 0, 0.12);

            animation: iconGlow 2.5s ease-in-out infinite;
        }

        .flow-item:nth-of-type(2) .flow-icon {
            animation-delay: 0.6s;
        }

        .flow-item:nth-of-type(3) .flow-icon {
            animation-delay: 1.2s;
        }

        .flow-item:nth-of-type(4) .flow-icon {
            animation-delay: 1.8s;
        }

        @keyframes iconGlow {

            0%,
            100% {
                transform: scale(1);
                box-shadow:
                    0 0 6px rgba(224, 0, 0, 0.1);
            }

            50% {
                transform: scale(1.15);
                box-shadow:
                    0 0 18px rgba(224, 0, 0, 0.45);
                color: #ffffff;
                border-color: #ff2020;
            }
        }

        .flow-arrow {
            position: relative;

            color: #e00000;

            font-size: 13px;

            width: 20px;

            text-align: center;

            opacity: 0.8;
        }

        .flow-pulse {
            position: absolute;

            width: 5px;
            height: 5px;

            border-radius: 50%;

            background: #ff3030;

            box-shadow:
                0 0 8px #ff3030,
                0 0 15px rgba(255, 30, 30, 0.6);

            left: -2px;
            top: 50%;

            transform: translateY(-50%);

            animation: flowPulse 1.8s linear infinite;
        }

        .flow-arrow:nth-of-type(2) .flow-pulse {
            animation-delay: 0.6s;
        }

        .flow-arrow:nth-of-type(3) .flow-pulse {
            animation-delay: 1.2s;
        }

        @keyframes flowPulse {

            0% {
                left: -3px;
                opacity: 0;
            }

            15% {
                opacity: 1;
            }

            85% {
                opacity: 1;
            }

            100% {
                left: 18px;
                opacity: 0;
            }
        }


        /* =====================================================
           COMPANY NAME
           ===================================================== */

        .company-name {
            position: absolute;

            bottom: 30px;
            left: 70px;

            font-size: 13px;
            letter-spacing: 0.5px;

            z-index: 3;
        }

        .company-name a {
            color: #e00000;
            font-weight: 700;
            text-decoration: underline;
            text-underline-offset: 3px;
            transition: color 0.2s ease;
        }

        .company-name a:hover {
            color: #ff3b3b;
        }


        /* =====================================================
           LOGIN SECTION
           ===================================================== */

        .login-section {
            display: flex;
            align-items: center;
            justify-content: center;

            padding: 55px 45px;

            background: #1c1f22;

            border-left: 1px solid rgba(255, 255, 255, 0.07);

            animation: loginAppear 0.8s ease-out 0.25s both;
        }

        @keyframes loginAppear {

            from {
                opacity: 0;
                transform: translateX(15px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .login-container {
            width: 100%;
            max-width: 340px;
        }


        /* =====================================================
           LOGIN HEADING
           ===================================================== */

        .login-heading {
            margin-bottom: 35px;
        }

        .login-heading h2 {
            margin: 0 0 8px;

            font-size: 30px;

            font-weight: 700;
        }

        .login-heading p {
            margin: 0;

            color: #8f969b;

            font-size: 14px;
        }


        /* =====================================================
           ERROR
           ===================================================== */

        .error {
            background: rgba(220, 0, 0, 0.12);

            border: 1px solid rgba(220, 0, 0, 0.35);

            color: #ff7373;

            padding: 12px 14px;

            border-radius: 7px;

            margin-bottom: 20px;

            font-size: 13px;
        }


        /* =====================================================
           FORM
           ===================================================== */

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;

            margin-bottom: 8px;

            color: #dfe2e4;

            font-size: 13px;

            font-weight: 600;
        }

        input {
            width: 100%;

            padding: 13px 14px;

            border: 1px solid #3a3e42;

            border-radius: 7px;

            outline: none;

            background: #121416;

            color: #ffffff;

            font-size: 14px;

            transition:
                border-color 0.2s,
                box-shadow 0.2s,
                background 0.2s;
        }

        input::placeholder {
            color: #666c71;
        }

        input:focus {
            border-color: #e00000;

            background: #151719;

            box-shadow:
                0 0 0 3px rgba(224, 0, 0, 0.10),
                0 0 18px rgba(224, 0, 0, 0.08);
        }


        /* =====================================================
           LOGIN BUTTON
           ===================================================== */

        .login-button {
            position: relative;

            width: 100%;

            margin-top: 8px;

            padding: 13px;

            border: none;

            border-radius: 7px;

            background: #e00000;

            color: #ffffff;

            font-size: 14px;

            font-weight: 700;

            cursor: pointer;

            overflow: hidden;

            transition:
                background 0.2s,
                transform 0.2s,
                box-shadow 0.2s;
        }

        .login-button::before {
            content: "";

            position: absolute;

            top: 0;
            left: -100%;

            width: 60%;
            height: 100%;

            background: linear-gradient(90deg,
                    transparent,
                    rgba(255, 255, 255, 0.18),
                    transparent);

            transition: left 0.5s;
        }

        .login-button:hover {
            background: #c90000;

            transform: translateY(-1px);

            box-shadow:
                0 8px 20px rgba(224, 0, 0, 0.22);
        }

        .login-button:hover::before {
            left: 140%;
        }

        .login-button:active {
            transform: translateY(0);
        }


        /* =====================================================
           FOOTER
           ===================================================== */

        .login-footer {
            margin-top: 28px;

            text-align: center;

            color: #666c71;

            font-size: 11px;

            line-height: 1.6;
        }


        /* =====================================================
           RESPONSIVE
           ===================================================== */

        @media (max-width: 800px) {

            body {
                padding: 20px;
            }

            .login-wrapper {
                grid-template-columns: 1fr;

                max-width: 500px;

                min-height: auto;
            }

            .brand-section {
                padding: 45px 40px;

                text-align: center;

                align-items: center;
            }

            .brand-section h1 {
                font-size: 32px;
            }

            .brand-section p {
                font-size: 14px;
            }

            .hris-flow {
                justify-content: center;

                flex-wrap: wrap;
            }

            .company-name {
                position: static;

                margin-top: 30px;
            }

            .logo {
                margin-bottom: 30px;
            }

            .login-section {
                border-left: none;

                border-top: 1px solid rgba(255, 255, 255, 0.07);

                padding: 45px 35px;
            }
        }


        /* =====================================================
           REDUCED MOTION
           ===================================================== */

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>


<body>


    <!-- =====================================================
         ANIMATED BACKGROUND
         ===================================================== -->

    <div class="background-network">

        <span class="network-dot dot-1"></span>
        <span class="network-dot dot-2"></span>
        <span class="network-dot dot-3"></span>
        <span class="network-dot dot-4"></span>
        <span class="network-dot dot-5"></span>
        <span class="network-dot dot-6"></span>

        <span class="network-line line-1"></span>
        <span class="network-line line-2"></span>
        <span class="network-line line-3"></span>
        <span class="network-line line-4"></span>

    </div>


    <!-- =====================================================
         MAIN LOGIN WRAPPER
         ===================================================== -->

    <div class="login-wrapper">


        <!-- =================================================
             BRANDING
             ================================================= -->

        <section class="brand-section">

            <div class="brand-content">

                <img src="<?= htmlspecialchars(BASE_URL) ?>/assets/images/myol-logo.jpg" alt="MY Outsourcing Logo"
                    class="logo">


                <h1>
                    HRIS<br>
                    <span>Management System</span>
                </h1>


                <p>
                    A centralized human resource management platform
                    for managing employees, attendance and HR operations.
                </p>


                <!-- =========================================
                     HRIS PROCESS
                     ========================================= -->

                <div class="hris-flow">

                    <div class="flow-item">
                        <span class="flow-icon">E</span>
                        <span>Employee</span>
                    </div>

                    <span class="flow-arrow">
                        <span class="flow-pulse"></span>
                        →
                    </span>

                    <div class="flow-item">
                        <span class="flow-icon">R</span>
                        <span>RFID</span>
                    </div>

                    <span class="flow-arrow">
                        <span class="flow-pulse"></span>
                        →
                    </span>

                    <div class="flow-item">
                        <span class="flow-icon">A</span>
                        <span>Attendance</span>
                    </div>

                    <span class="flow-arrow">
                        <span class="flow-pulse"></span>
                        →
                    </span>

                    <div class="flow-item">
                        <span class="flow-icon">S</span>
                        <span>Salary</span>
                    </div>

                </div>

            </div>


            <div class="company-name">
                <a href="https://www.myolbd.com/" target="_blank" rel="noopener noreferrer">
                    MY Outsourcing Limited
                </a>
            </div>

        </section>


        <!-- =================================================
             LOGIN
             ================================================= -->

        <section class="login-section">

            <div class="login-container">


                <div class="login-heading">

                    <h2>
                        Welcome Back
                    </h2>

                    <p>
                        Sign in to access the HRIS system.
                    </p>

                </div>


                <?php if (!empty($error)): ?>

                    <div class="error">
                        <?= htmlspecialchars($error) ?>
                    </div>

                <?php endif; ?>


                <form method="POST" action="index.php?action=login">


                    <!-- CSRF -->

                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">


                    <!-- Username -->

                    <div class="form-group">

                        <label for="username">
                            Username
                        </label>

                        <input type="text" id="username" name="username" placeholder="Enter your username"
                            autocomplete="username" required>

                    </div>


                    <!-- Password -->

                    <div class="form-group">

                        <label for="password">
                            Password
                        </label>

                        <input type="password" id="password" name="password" placeholder="Enter your password"
                            autocomplete="current-password" required>

                    </div>


                    <!-- Login -->

                    <button type="submit" class="login-button">
                        LOGIN
                    </button>


                </form>


                <div class="login-footer">

                    Human Resource Information System<br>

                    MY Outsourcing Limited

                </div>


            </div>

        </section>

    </div>

</body>

</html>