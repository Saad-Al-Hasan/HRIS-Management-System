<?php
require_once __DIR__ . '/../../core/Auth.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login - HRIS Management System</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f2f4f7;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .login-container {
            width: 350px;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
        }

        input {
            width: 100%;
            box-sizing: border-box;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button {
            width: 100%;
            padding: 11px;
            border: none;
            border-radius: 5px;
            background: #333;
            color: white;
            cursor: pointer;
        }

        button:hover {
            background: #555;
        }

        .error {
            background: #ffe5e5;
            color: #b00000;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>

    <div class="login-container">

        <h1>HRIS Management System</h1>

        <?php if (!empty($error)): ?>
            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="index.php?action=login">

            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">

            <label for="username">Username</label>

            <input type="text" id="username" name="username" required>

            <label for="password">Password</label>

            <input type="password" id="password" name="password" required>

            <button type="submit">
                Login
            </button>

        </form>

    </div>

</body>

</html>