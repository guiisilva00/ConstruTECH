<?php
session_start();

if (!isset($_SESSION['autenticado'])) {
    header("Location: index.php");
    exit();
}

if (!isset($_SESSION['estoque'])) {
    $_SESSION['estoque'] = [];
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

    header("Location: produtos.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CT | Cadastro de produto</title>
    <link rel="stylesheet" href="./css/style.css">
    <link rel="stylesheet" href="./css/header.css">
    <link rel="stylesheet" href="./css/produtos.css">
    <link rel="stylesheet" href="./css/footer.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="./img/favicon.png" type="image/x-icon">

</head>

<body>
    <?php require_once 'partials/header.php'; ?>

    <main>
        <div class="title-cadastro">
            <h1>Cadastro de <span class="amarelin">produtos</span></h1>
        </div>

        <form action="estoque.php" method="POST" class="form-cadastro">
            <div class="form-group campo-nome">
                <label for="nome">Nome do Item:</label>
                <input type="text" id="nome" name="nome" required placeholder="Ex: Cimento 50KG">
            </div>

            <div class="form-group">
                <label for="categoria">Categoria:</label>
                <select id="categoria" name="categoria" required>
                    <option value="Bruto">Bruto</option>
                    <option value="Ferramentas">Ferramentas</option>
                    <option value="Acabamento">Acabamento</option>
                </select>
            </div>
            <div class="form-group">
                <label for="quantidade">Quantidade inicial:</label>
                <input type="number" id="quantidade" name="quantidade" min="1" required placeholder="Ex: 120">
            </div>

            <div class="form-group">
                <label for="preco">Preço unitário <span class="amarelin">(R$)</span>:</label>
                <input type="number" id="preco" name="preco" min="0" step="0.01" required placeholder="Ex: R$ 21.99">
            </div>

            <div class="form-group campo-imagem">
                <label for="imagem_url">Link da Imagem <span class="amarelin">(URL)</span>:</label>
                <input type="url" name="imagem_url" id="imagem_url" placeholder="https://exemplo.com/foto.jpg">
            </div>

            <button type="submit" class="btn">Adicionar Item</button>
        </form>


    </main>
    <?php require_once 'partials/footer.php' ?>
</body>

</html>