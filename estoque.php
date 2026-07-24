<?php
session_start();

if (isset($_GET['acao'])) {
    $acao = $_GET['acao'];
    $id = $_GET['id'];

    if ($acao == 'aumentar') {
        $_SESSION['estoque'][$id]['quantidade']++;

    } else if ($acao == 'diminuir') {
        if ($_SESSION['estoque'][$id]['quantidade'] > 0) {
            $_SESSION['estoque'][$id]['quantidade']--;
        }

    } else if ($acao == 'remover') {
        unset($_SESSION['estoque'][$id]);

    }
    if (isset($_SESSION['estoque'][$id]['quantidade'])) {
        $_SESSION['estoque'][$id]['valor_investido'] = $_SESSION['estoque'][$id]['quantidade'] * $_SESSION['estoque'][$id]['preco'];
    }
    header("Location: estoque.php");
    exit();
}

if (!isset($_SESSION['estoque'])) {
    $_SESSION['estoque'] = [];
}

if (isset($_GET['limpar'])) {
    $_SESSION['estoque'] = [];
    header("Location: estoque.php");
    exit();
}


if (isset($_POST['nome'])) {
    $nome = $_POST['nome'];
    $categoria = $_POST['categoria'];
    $quantidade = $_POST['quantidade'];
    $preco = $_POST['preco'];

    $valor_investido = $preco * $quantidade;

    $novo_produto = [
        'nome' => $nome,
        'categoria' => $categoria,
        'quantidade' => $quantidade,
        'preco' => $preco,
        'valor_investido' => $valor_investido,
        'data_cadastro' => date('d/m/Y'),
        'imagem_url' => $_POST['imagem_url']
    ];

    $_SESSION['estoque'][] = $novo_produto;

    header("Location: estoque.php");
    exit();
}

?>



<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CT | Gestão de Estoque</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="./img/favicon.png" type="image/x-icon">
    <link rel="stylesheet" href="./css/style.css">
    <link rel="stylesheet" href="./css/produtos.css">
    <link rel="stylesheet" href="./css/header.css">
    <link rel="stylesheet" href="./css/footer.css">
</head>

<body>
    <?php
    require_once 'partials/header.php'
        ?>
    <main class="container">

        <div>
            <h1 class="title-sublinhado">Controle de estoque</h1>
        </div>

        <form action="estoque.php" method="GET" class="form-busca">
            <label for="busca">Buscar Produto:</label>
            <input type="text" id="busca" name="busca" placeholder="Digite o nome do item">
            <button type="submit" class="btn">Buscar</button>
        </form>
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Categoria</th>
                    <th>Quantidade</th>
                    <th>Preço (R$)</th>
                    <th>Total Investido (R$)</th>
                    <th>Data de Cadastro</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $termo_busca = isset($_GET['busca']) ? ($_GET['busca']) : '';
                $total_geral = 0;

                foreach ($_SESSION['estoque'] as $index => $produto) { ?>
                    <?php
                    if ($termo_busca != '' && stripos($produto['nome'], $termo_busca) === false) {
                        continue;
                    }
                    $total_geral += $produto['valor_investido'];

                    $alerta = "";

                    if ($produto['quantidade'] < 10) {
                        $alerta = "style ='background-color: #ffee00; color: #1a1a1a;'";
                    }
                    if ($produto['quantidade'] <= 5) {
                        $alerta = "style ='background-color: #dd4242; color: #1a1a1a;'";
                    }
                    ?>
                    <tr <?php echo $alerta; ?>>
                        <td class="coluna-produto">
                            <img src="<?php echo $produto['imagem_url']; ?>" alt="Foto de <?php echo $produto['nome']; ?>">
                            <span><?php echo htmlspecialchars($produto['nome']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($produto['quantidade']); ?></td>
                        <td><?php echo htmlspecialchars($produto['categoria']); ?></td>
                        <td><?php echo number_format($produto['preco'], 2, ',', '.'); ?></td>
                        <td><?php echo number_format($produto['valor_investido'], 2, ',', '.'); ?></td>
                        <td><?php echo $produto['data_cadastro']; ?></td>
                        <td class="coluna-acoes">
                            <a href="estoque.php?acao=aumentar&id=<?php echo $index; ?>" class="btn-acao btn-aumentar"
                                title="Aumentar quantidade">➕</a>
                            <a href="estoque.php?acao=diminuir&id=<?php echo $index; ?>" class="btn-acao btn-diminuir"
                                title="Diminuir quantidade">➖</a>
                            <a href="estoque.php?acao=remover&id=<?php echo $index; ?>" class="btn-acao btn-remover"
                                title="Remover produto"
                                onclick="return confirm('Tem certeza que deseja excluir este item?');">
                                🗑️
                            </a>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4"><strong>Total Geral Investido (R$):</strong></td>
                    <td><?php echo number_format($total_geral, 2, ',', '.'); ?></td>
                </tr>
            </tfoot>
        </table>

        <br><a href="estoque.php?limpar=sim" class="btn" id="limpar">Limpar Estoque</a>
    </main>

    <?php
    require_once 'partials/footer.php';
    ?>

</body>

</html>