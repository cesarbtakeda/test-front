<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require 'db.php';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Token CSRF inválido.";
    } else {
        $username = trim($_POST['username']);
        $password_raw = $_POST['password'];

        if (empty($username) || empty($password_raw)) {
            $error = "Por favor, preencha todos os campos.";
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            $error = "O nome de usuário deve ter entre 3 e 20 caracteres alfanuméricos.";
        } elseif (strlen($password_raw) < 8) {
            $error = "A senha deve ter pelo menos 8 caracteres.";
        } else {
            // Verifica se usuário já existe
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            if ($stmt === false) {
                $error = "Erro no banco de dados.";
            } else {
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    $error = "Usuário já existe. Escolha outro nome.";
                } else {
                    // Insere usuário novo
                    $password = password_hash($password_raw, PASSWORD_DEFAULT);
                    $stmt->close();
                    $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                    if ($stmt === false) {
                        $error = "Erro no banco de dados.";
                    } else {
                        $stmt->bind_param('ss', $username, $password);
                        if ($stmt->execute()) {
                            $_SESSION['user_id'] = $conn->insert_id;
                            $_SESSION['username'] = $username;
                            header("Location: login.php");
                            exit();
                        } else {
                            $error = "Erro ao cadastrar usuário.";
                        }
                    }
                }
                $stmt->close();
            }
        }
    }
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro</title>
    <style>
        /* Mesmo CSS do código anterior */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background: linear-gradient(135deg, #6b73ff 0%, #000dff 100%); 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            display: flex; 
            height: 100vh; 
            justify-content: center; 
            align-items: center; 
            color: #333; 
        }
        .container { 
            background: #fff; 
            padding: 40px 30px; 
            border-radius: 10px; 
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15); 
            width: 350px; 
            text-align: center; 
        }
        h2 { 
            margin-bottom: 25px; 
            color: #222; 
            font-weight: 700; 
            font-size: 28px; 
        }
        form { 
            display: flex; 
            flex-direction: column; 
            gap: 20px; 
        }
        input[type="text"], input[type="password"] { 
            padding: 12px 15px; 
            font-size: 16px; 
            border: 2px solid #ddd; 
            border-radius: 6px; 
            transition: border-color 0.3s ease; 
        }
        input[type="text"]:focus, input[type="password"]:focus { 
            border-color: #6b73ff; 
            outline: none; 
            box-shadow: 0 0 8px rgba(107, 115, 255, 0.5); 
        }
        button { 
            padding: 12px; 
            font-size: 18px; 
            background: #6b73ff; 
            border: none; 
            color: white; 
            border-radius: 6px; 
            cursor: pointer; 
            font-weight: 600; 
            transition: background-color 0.3s ease; 
        }
        button:hover { 
            background: #4a54d1; 
        }
        p { 
            margin-top: 20px; 
            font-size: 14px; 
            color: #555; 
        }
        a { 
            color: #6b73ff; 
            text-decoration: none; 
            font-weight: 600; 
        }
        a:hover { 
            text-decoration: underline; 
        }
        .error { 
            background: #ffdddd; 
            color: #d8000c; 
            padding: 10px 15px; 
            border: 1px solid #d8000c; 
            border-radius: 6px; 
            margin-bottom: 20px; 
            font-weight: 600; 
            font-size: 14px; 
        }
        @media (max-width: 400px) { 
            .container { 
                width: 90%; 
                padding: 30px 20px; 
            } 
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Cadastro</h2>
    <?php if ($error) : ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="text" name="username" placeholder="Usuário" required <?= $error ? '' : 'autofocus' ?> value="<?= isset($username) ? htmlspecialchars($username) : '' ?>">
        <input type="password" name="password" placeholder="Senha" required autocomplete="new-password">
        <button type="submit">Cadastrar</button>
    </form>
    <p>Já tem uma conta? <a href="login.php">Entrar</a></p>
</div>
</body>
</html>