<?php

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && preg_match('/^https:\/\/c56c58f9d7d9\.ngrok-free\.app$/', $origin)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
}
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$rotas_validas = ['/', '/home', '/sobre', '/contato','/carrinho.php', 'index.php'];
if (!in_array($request_uri, $rotas_validas)) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

session_start();

// Inicializar carrinho
if(!isset($_SESSION['carrinho'])) $_SESSION['carrinho']=[];

// Funções de pagamento e finalização
function criarPagamentoPix($total,$nome_cliente){
    $access_token='APP_USR-1818596915493242-082721-95ac706b087e9ae9b5a977eb2d75d42e-1132256640';
    $url='https://api.mercadopago.com/v1/payments';
    $transaction_amount=number_format((float)$total,2,'.','');
    $email_cliente='test@test.com';
    $data=[
        'transaction_amount'=>(float)$transaction_amount,
        'description'=>'Compra na Padaria Kissaten',
        'payment_method_id'=>'pix',
        'payer'=>['email'=>$email_cliente],
        'external_reference'=>'PEDIDO_'.time()
    ];
    $idempotency_key=uniqid('pedido_',true);
    $curl=curl_init();
    curl_setopt_array($curl,[
        CURLOPT_URL=>$url,
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>json_encode($data),
        CURLOPT_HTTPHEADER=>[
            'Content-Type: application/json',
            'Authorization: Bearer '.$access_token,
            'X-Idempotency-Key: '.$idempotency_key
        ]
    ]);
    $resp=curl_exec($curl);
    if(curl_errno($curl)){
        $erro=curl_error($curl);
        curl_close($curl);
        return ['erro'=>$erro];
    }
    curl_close($curl);
    $resultado=json_decode($resp,true);
    $resultado['pix_qr_code']=$resultado['point_of_interaction']['transaction_data']['qr_code_base64'] ?? '';
    $resultado['pix_codigo']=$resultado['point_of_interaction']['transaction_data']['qr_code'] ?? ''; // código PIX real
    return $resultado;
}

function verificarPagamento($payment_id){
    $access_token='APP_USR-1818596915493242-082721-95ac706b087e9ae9b5a977eb2d75d42e-1132256640';
    $url="https://api.mercadopago.com/v1/payments/$payment_id";
    $curl=curl_init();
    curl_setopt_array($curl,[
        CURLOPT_URL=>$url,
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$access_token]
    ]);
    $resp=curl_exec($curl);
    curl_close($curl);
    $data=json_decode($resp,true);
    return $data['status']??'pending';
}

// Processar ações
$mensagem='';
if($_SERVER["REQUEST_METHOD"]=="POST" && isset($_POST['acao'])){
    $acao=$_POST['acao'];

    if($acao=='atualizar'){
        $i=(int)$_POST['index'];
        $qtd=max(1,min(50,(int)$_POST['quantidade']));
        if(isset($_SESSION['carrinho'][$i])) $_SESSION['carrinho'][$i]['quantidade']=$qtd;

        // Reiniciar pagamento PIX
        unset($_SESSION['payment_id'], $_SESSION['pix_qr_code'], $_SESSION['pix_codigo']);

    } elseif($acao=='remover'){
        $i=(int)$_POST['index'];
        if(isset($_SESSION['carrinho'][$i])){
            unset($_SESSION['carrinho'][$i]);
            $_SESSION['carrinho']=array_values($_SESSION['carrinho']);
        }

        // Reiniciar pagamento PIX
        unset($_SESSION['payment_id'], $_SESSION['pix_qr_code'], $_SESSION['pix_codigo']);

    } elseif($acao=='iniciar_pagamento'){
        if(empty($_SESSION['payment_id'])){
            if(!empty($_SESSION['carrinho']) && !empty($_SESSION['nome_cliente'])){
                $total=0;
                foreach($_SESSION['carrinho'] as $item) $total+=$item['preco']*$item['quantidade'];
                $pagamento=criarPagamentoPix($total,$_SESSION['nome_cliente']);
                if(isset($pagamento['id'])){
                    $_SESSION['payment_id']=$pagamento['id'];
                    $_SESSION['pix_qr_code']=$pagamento['pix_qr_code'];
                    $_SESSION['pix_codigo']=$pagamento['pix_codigo'];
                    $mensagem="Escaneie o QR Code ou Copie o Codigo para pagar!";
                } else $mensagem="Erro ao gerar pagamento PIX: ".json_encode($pagamento);
            } else $mensagem="Carrinho vazio ou nome do cliente não informado!";
        } else {
            $mensagem="Pagamento em aberto! Aguarde finalizar ou recarregue a página.";
        }

    } elseif($acao=='finalizar'){
        if(!empty($_SESSION['carrinho']) && isset($_SESSION['payment_id'])){
            $status_pagamento=verificarPagamento($_SESSION['payment_id']);
            if($status_pagamento==='approved'){
                $nome_cliente=htmlspecialchars($_SESSION['nome_cliente']??'Cliente');
                $arquivo_contador='contador.txt';
                $numero_atual=file_exists($arquivo_contador)?(int)file_get_contents($arquivo_contador):0;
                $numero_pedido=($numero_atual%1000)+1;
                file_put_contents($arquivo_contador,$numero_pedido);

                $conteudo="Padaria Kissaten\n";
                $conteudo.="$nome_cliente | Pedido #$numero_pedido\n---------------------------------\n";
                foreach($_SESSION['carrinho'] as $item){
                    $subtotal=$item['preco']*$item['quantidade'];
                    $conteudo.="{$item['nome']} | {$item['quantidade']} x R$ {$item['preco']} = R$ ".number_format($subtotal,2,',','.')."\n";
                }
                $conteudo.="=================================\n\n";
                file_put_contents('pedido.txt',$conteudo,FILE_APPEND | LOCK_EX);

                $_SESSION['carrinho']=[];
                unset($_SESSION['payment_id'], $_SESSION['pix_qr_code'], $_SESSION['pix_codigo']);
                $mensagem="Pedido finalizado com sucesso!";
            } else $mensagem="Pagamento ainda não aprovado. Status: $status_pagamento";
        } else $mensagem="Carrinho vazio ou pagamento não encontrado!";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Carrinho - Padaria Kissaten</title>
<link rel="stylesheet" href="/static/style2.css">
</head>
<body>
<header>
<h1>Padaria Kissaten | Carrinho de Compras</h1>
</header>

<main>
<?php if(!empty($_SESSION['carrinho'])): ?>
<section class="carrinho">
<table>
<tr><th>Produto</th><th>Preço</th><th>Qtd</th><th>Subtotal</th><th>Ações</th></tr>
<?php
$total=0;
foreach($_SESSION['carrinho'] as $i=>$item):
$subtotal=$item['preco']*$item['quantidade'];
$total+=$subtotal;
?>
<tr>
<td><?php echo htmlspecialchars($item['nome']); ?></td>
<td>R$ <?php echo number_format($item['preco'],2,',','.'); ?></td>
<td>
<form action="carrinho.php" method="POST">
<input type="hidden" name="acao" value="atualizar">
<input type="hidden" name="index" value="<?php echo $i; ?>">
<input type="number" name="quantidade" value="<?php echo $item['quantidade']; ?>" min="1" max="50" style="width:70px;">
<button type="submit">Atualizar</button>
</form>
</td>
<td>R$ <?php echo number_format($subtotal,2,',','.'); ?></td>
<td>
<form action="carrinho.php" method="POST">
<input type="hidden" name="acao" value="remover">
<input type="hidden" name="index" value="<?php echo $i; ?>">
<button type="submit" class="btn-remover">Remover</button>
</form>
</td>
</tr>
<?php endforeach; ?>
<tr><td colspan="3"><strong>Total</strong></td><td colspan="2"><strong>R$ <?php echo number_format($total,2,',','.'); ?></strong></td></tr>
</table>

<form action="carrinho.php" method="POST">
<input type="hidden" name="acao" value="iniciar_pagamento">
<button type="submit" <?php if(isset($_SESSION['payment_id'])) echo 'disabled title="Pagamento em aberto"'; ?>>
<?php echo isset($_SESSION['payment_id'])?'🔒 Em aberto':'Iniciar Pagamento PIX'; ?>
</button>
</form>

</section>
<?php else: ?>
<p class="mensagem">Carrinho vazio.</p>
<?php endif; ?>

<?php if($mensagem) echo "<p class='mensagem'>$mensagem</p>"; ?>

<?php if(isset($_SESSION['pix_qr_code']) && !empty($_SESSION['pix_qr_code'])): ?>
<div class="qrcode">
<h3>Pagamento via PIX</h3>
<img src="data:image/png;base64,<?php echo $_SESSION['pix_qr_code']; ?>" alt="QR Code PIX" width="200">

<p>Escaneie o QR Code para pagar.</p>

<!-- Botão copiar código PIX abaixo do QR Code -->
<button id="copiarPixBtn">Copiar Código PIX</button>
<input type="text" id="pixCodigo" value="<?php echo htmlspecialchars($_SESSION['pix_codigo']); ?>" readonly style="position:absolute; left:-9999px;">

<form action="carrinho.php" method="POST" style="margin-top:15px;">
<input type="hidden" name="acao" value="finalizar">
<button type="submit">Confirmar Pagamento</button>
</form>
</div>

<script>
document.getElementById('copiarPixBtn').addEventListener('click', function() {
    var pixInput = document.getElementById('pixCodigo');
    pixInput.select();
    pixInput.setSelectionRange(0, 99999);
    document.execCommand('copy');
    alert('Código PIX copiado para a área de transferência!');
});
</script>
<?php endif; ?>

<div class="footer-container">
<a href="index.php">Voltar ao Cardápio</a>
</div>
</main>

<footer>
<p>&copy; 2025 Padaria Kissaten</p>
</footer>
</body>
</html>