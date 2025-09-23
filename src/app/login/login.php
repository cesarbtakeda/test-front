<?php
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict'
]);

require __DIR__ . '/db.php';
$error = '';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate limiting configuration
$max_attempts = 5;
$lockout_time = 900; // 15 minutes in seconds

// Check rate limiting
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt'] = time();
}

if ($_SESSION['login_attempts'] >= $max_attempts && (time() - $_SESSION['last_attempt']) < $lockout_time) {
    $error = "Muitas tentativas de login. Tente novamente em " . ceil(($lockout_time - (time() - $_SESSION['last_attempt'])) / 60) . " minutos.";
} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Erro de validação. Tente novamente.";
    } else {
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING) ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = "Preencha todos os campos.";
            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt'] = time();
        } else {
            try {
                $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $res = $stmt->get_result();

                if ($res->num_rows === 1) {
                    $user = $res->fetch_assoc();
                    if (password_verify($password, $user['password'])) {
                        // Regenerate session ID to prevent session fixation
                        session_regenerate_id(true);
                        $_SESSION['username'] = $username;
                        $_SESSION['login_attempts'] = 0; // Reset attempts on successful login
                        unset($_SESSION['csrf_token']); // Clear CSRF token
                        header("Location: index.php");
                        exit();
                    } else {
                        $error = "Senha incorreta.";
                        $_SESSION['login_attempts']++;
                        $_SESSION['last_attempt'] = time();
                    }
                } else {
                    $error = "Usuário não encontrado.";
                    $_SESSION['login_attempts']++;
                    $_SESSION['last_attempt'] = time();
                }
            } catch (Exception $e) {
                $error = "Erro no servidor. Tente novamente mais tarde.";
                error_log("Login error: " . $e->getMessage());
            }
        }
    }
    // Regenerate CSRF token after each POST
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'">
    <title>Login</title>
    <style>
        /* Reset */
        * {
            margin: 0; padding: 0; box-sizing: border-box;
        }
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #74ebd5, #ACB6E5);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            max-width: 400px;
            width: 100%;
        }
        h2 {
            text-align: center;
            margin-bottom: 25px;
            color: #333;
        }
        input[type=text], input[type=password], input[type=hidden] {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        input[type=text]:focus, input[type=password]:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        button {
            width: 100%;
            padding: 12px;
            background-color: #007bff;
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        button:hover {
            background-color: #0056b3;
        }
        .error-msg {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            text-align: center;
        }
        .register-link {
            text-align: center;
            font-size: 14px;
            margin-top: 15px;
        }
        .register-link a {
            color: #007bff;
            text-decoration: none;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
    <script>
        function validateForm() {
            const username = document.forms["loginForm"]["username"].value.trim();
            const password = document.forms["loginForm"]["password"].value.trim();
            if (username === "" || password === "") {
                alert("Por favor, preencha todos os campos.");
                return false;
            }
            return true;
        }
    </script>
</head>
<body>
    <div class="container">
        <form name="loginForm" method="post" onsubmit="return validateForm()">
            <h2>Login</h2>
            <?php if ($error): ?>
                <div class="error-msg"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="text" name="username" placeholder="Usuário" required />
            <input type="password" name="password" placeholder="Senha" required />
            <button type="submit">Entrar</button>
            <p class="register-link">
                Não tem uma conta? <a href="register.php">Cadastre-se</a>
            </p>
        </form>
    </div>
</body>
</html>