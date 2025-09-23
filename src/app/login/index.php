<?php
/* $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && preg_match('/^https:\/\/c56c58f9d7d9\.ngrok-free\.app$/', $origin)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
}
*/
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Inicializar carrinho
if (!isset($_SESSION['carrinho'])) {
    $_SESSION['carrinho'] = [];
}

// Adicionar item ao carrinho
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['acao']) && $_POST['acao'] == 'adicionar') {
    $nome_cliente = htmlspecialchars($_SESSION['username']); // Usa o username do usuário logado
    $produto = explode("|", $_POST['produto']);
    $nome_produto = $produto[0];
    $preco_produto = $produto[1];
    $quantidade = min(50, max(1, (int)$_POST['quantidade']));

    $_SESSION['nome_cliente'] = $nome_cliente;

    $_SESSION['carrinho'][] = [
        'nome' => $nome_produto,
        'preco' => $preco_produto,
        'quantidade' => $quantidade
    ];

    header("Location: index.php?mensagem=Item+adicionado+ao+carrinho");
    exit;
}

$mensagem = isset($_GET['mensagem']) ? urldecode($_GET['mensagem']) : '';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Padaria Kissaten - Cardápio</title>
<link rel="stylesheet" href="/static/style.css">
</head>
<body>
<header>
<h1>Padaria Kissaten</h1>
<h2>Cardápio Digital</h2>
<a href="carrinho.php" class="carrinho-btn">Ir para o Carrinho</a>
<a href="logout.php" class="logout-btn">Sair</a>
</header>

<main>
<?php if ($mensagem) { $classe = strpos($mensagem, "Erro")!==false?'erro':'mensagem'; echo "<p class='$classe'>$mensagem</p>"; } ?>

<p><strong>Cliente:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>

<form action="index.php" method="POST">
<input type="hidden" name="acao" value="adicionar">

<h3>Produtos</h3>

<div class="produto">
    <div>
        <h4>Pão Francês</h4>
        <p>Preço: R$ 1,50</p>
        <label for="quantidade_pao_frances">Qtd:</label>
        <input type="number" id="quantidade_pao_frances" name="quantidade" min="1" max="50" value="1" required>
    </div>
    <img src="https://via.placeholder.com/120?text=P%C3%A3o+Franc%C3%AAs" alt="Pão Francês">
    <button type="submit" name="produto" value="Pão Francês|1.50">Adicionar</button>
</div>

<div class="produto">
    <div>
        <h4>Bolo de Chocolate</h4>
        <p>Preço: R$ 25,00</p>
        <label for="quantidade_bolo_chocolate">Qtd:</label>
        <input type="number" id="quantidade_bolo_chocolate" name="quantidade" min="1" max="50" value="1" required>
    </div>
    <img src="https://via.placeholder.com/120?text=Bolo+Choco" alt="Bolo de Chocolate">
    <button type="submit" name="produto" value="Bolo de Chocolate|25.00">Adicionar</button>
</div>

<div class="produto">
    <div>
        <h4>Croissant</h4>
        <p>Preço: R$ 5,00</p>
        <label for="quantidade_croissant">Qtd:</label>
        <input type="number" id="quantidade_croissant" name="quantidade" min="1" max="50" value="1" required>
    </div>
    <img src="https://via.placeholder.com/120?text=Croissant" alt="Croissant">
    <button type="submit" name="produto" value="Croissant|5.00">Adicionar</button>
</div>

</form>
</main>

<footer>
<p>&copy; 2025 Padaria Kissaten</p>
</footer>
</body>
</html>