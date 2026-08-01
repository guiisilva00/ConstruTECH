<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_login();

$pdo = get_pdo();
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nome'])) {
    verificar_csrf();

    $nome        = trim($_POST['nome'] ?? '');
    $categoria   = trim($_POST['categoria'] ?? '');
    $quantidade  = filter_var($_POST['quantidade'] ?? '', FILTER_VALIDATE_INT);
    $preco       = filter_var($_POST['preco'] ?? '', FILTER_VALIDATE_FLOAT);
    $imagemUrl   = trim($_POST['imagem_url'] ?? '');

    $categoriasValidas = ['Bruto', 'Ferramentas', 'Acabamento'];

    if ($nome === '' || mb_strlen($nome) > 150) {
        $erro = 'Informe um nome de material válido.';
    } elseif (!in_array($categoria, $categoriasValidas, true)) {
        $erro = 'Escolha uma categoria válida.';
    } elseif ($quantidade === false || $quantidade < 0) {
        $erro = 'Informe a quantidade comprada.';
    } elseif ($preco === false || $preco < 0) {
        $erro = 'Informe um valor unitário válido.';
    } elseif ($imagemUrl !== '' && !filter_var($imagemUrl, FILTER_VALIDATE_URL)) {
        $erro = 'O link da imagem precisa ser uma URL válida.';
    } else {
        $stmtCat = $pdo->prepare('SELECT id FROM categorias WHERE nome = :nome');
        $stmtCat->execute([':nome' => $categoria]);
        $categoriaId = $stmtCat->fetchColumn();

        $stmt = $pdo->prepare(
            'INSERT INTO produtos (nome, categoria_id, quantidade, preco, imagem_url)
             VALUES (:nome, :categoria_id, :quantidade, :preco, :imagem_url)'
        );
        $stmt->execute([
            ':nome'         => $nome,
            ':categoria_id' => $categoriaId,
            ':quantidade'   => $quantidade,
            ':preco'        => $preco,
            ':imagem_url'   => $imagemUrl !== '' ? $imagemUrl : null,
        ]);

        set_flash('sucesso', 'Gasto registrado com sucesso.');
        header('Location: produtos.php');
        exit();
    }
}

$flash = get_flash();
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ConstruTECH | Novo gasto</title>
    <link rel="stylesheet" href="./css/style.css">
    <link rel="stylesheet" href="./css/header.css">
    <link rel="stylesheet" href="./css/produtos.css">
    <link rel="stylesheet" href="./css/footer.css">
    <link rel="stylesheet" href="./css/modal.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="./img/favicon.png" type="image/x-icon">
</head>

<body>
    <?php require_once 'partials/header.php'; ?>

    <main class="app-shell form-page" id="conteudo-principal">
        <section class="page-intro reveal">
            <div>
                <p class="eyebrow">Novo lançamento</p>
                <h1>Registrar material comprado</h1>
                <p>Adicione uma compra feita para a piscina com quantidade, valor unitário e imagem de referência.</p>
            </div>
        </section>

        <?php if ($erro): ?>
            <p class="msg-erro"><?php echo htmlspecialchars($erro); ?></p>
        <?php elseif ($flash): ?>
            <p class="msg-erro msg-sucesso"><?php echo htmlspecialchars($flash['mensagem']); ?></p>
        <?php endif; ?>

        <form action="produtos.php" method="POST" class="form-cadastro panel reveal">
            <?php echo csrf_field(); ?>

            <div class="form-group campo-nome">
                <label for="nome">Material</label>
                <input type="text" id="nome" name="nome" required maxlength="150" placeholder="Ex.: Cimento 50 kg"
                    value="<?php echo htmlspecialchars($_POST['nome'] ?? ''); ?>">
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="categoria">Categoria</label>
                    <select id="categoria" name="categoria" required>
                        <option value="Bruto">Bruto</option>
                        <option value="Ferramentas">Ferramentas</option>
                        <option value="Acabamento">Acabamento</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="quantidade">Quantidade comprada</label>
                    <input type="number" id="quantidade" name="quantidade" min="0" required placeholder="Ex.: 20"
                        value="<?php echo htmlspecialchars($_POST['quantidade'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="preco">Valor unitário (R$)</label>
                    <input type="number" id="preco" name="preco" min="0" step="0.01" required placeholder="Ex.: 31.90"
                        value="<?php echo htmlspecialchars($_POST['preco'] ?? ''); ?>">
                </div>
                <div class="form-group campo-imagem">
                    <label for="imagem_url">Imagem do material</label>
                    <input type="url" name="imagem_url" id="imagem_url" placeholder="https://exemplo.com/foto.jpg"
                        value="<?php echo htmlspecialchars($_POST['imagem_url'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-actions">
                <a href="estoque.php" class="btn btn-secondary">Ver registros</a>
                <button type="submit" class="btn">Salvar gasto</button>
            </div>
        </form>
    </main>

    <?php require_once 'partials/footer.php' ?>
</body>

</html>
