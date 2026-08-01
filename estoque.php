<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_login();

$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'remover' && isset($_POST['id'])) {
    verificar_csrf();

    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if ($id !== false) {
        $pdo->prepare('DELETE FROM produtos WHERE id = :id')->execute([':id' => $id]);
        set_flash('sucesso', 'Material removido dos registros da obra.');
    }

    header('Location: estoque.php');
    exit();
}

if (isset($_GET['limpar'])) {
    require_nivel(['admin']);
    verificar_csrf();
    $pdo->exec('DELETE FROM movimentacoes_estoque');
    $pdo->exec('DELETE FROM obras_materiais');
    $pdo->exec('DELETE FROM produtos');
    set_flash('sucesso', 'Todos os registros financeiros foram limpos.');
    header('Location: estoque.php');
    exit();
}

$termoBusca = trim($_GET['busca'] ?? '');
$categoriaFiltro = trim($_GET['categoria'] ?? '');

$categorias = $pdo->query('SELECT nome FROM categorias ORDER BY nome')->fetchAll(PDO::FETCH_COLUMN);

$sql = 'SELECT p.id, p.nome, p.quantidade, p.preco, p.valor_investido, p.imagem_url,
               p.criado_em, c.nome AS categoria
        FROM produtos p
        JOIN categorias c ON c.id = p.categoria_id';
$where = [];
$params = [];

if ($termoBusca !== '') {
    $where[] = 'p.nome LIKE :busca';
    $params[':busca'] = '%' . $termoBusca . '%';
}

if ($categoriaFiltro !== '') {
    $where[] = 'c.nome = :categoria';
    $params[':categoria'] = $categoriaFiltro;
}

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY p.criado_em DESC, p.nome';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$produtos = $stmt->fetchAll();

$totalGeral = 0;
$totalUnidades = 0;
foreach ($produtos as $p) {
    $totalGeral += (float) $p['valor_investido'];
    $totalUnidades += (int) $p['quantidade'];
}

$custoMedio = count($produtos) > 0 ? $totalGeral / count($produtos) : 0;
$flash = get_flash();
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ConstruTECH | Gastos da piscina</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="./img/favicon.png" type="image/x-icon">
    <link rel="stylesheet" href="./css/style.css">
    <link rel="stylesheet" href="./css/produtos.css">
    <link rel="stylesheet" href="./css/header.css">
    <link rel="stylesheet" href="./css/footer.css">
    <link rel="stylesheet" href="./css/modal.css">
</head>

<body>
    <?php require_once 'partials/header.php'; ?>

    <main class="app-shell container expense-page" id="conteudo-principal">
        <section class="page-intro reveal">
            <div>
                <p class="eyebrow">Materiais da obra</p>
                <h1>Registro financeiro da construção da piscina</h1>
                <p>Consulte gastos, valores unitários, categorias e imagens dos materiais comprados.</p>
            </div>
            <a href="produtos.php" class="btn">Novo gasto</a>
        </section>

        <div aria-live="polite" class="sr-live">
            <?php if ($flash): ?>
                <p class="msg-erro <?php echo $flash['tipo'] === 'sucesso' ? 'msg-sucesso' : ''; ?>">
                    <?php echo htmlspecialchars($flash['mensagem']); ?>
                </p>
            <?php endif; ?>
        </div>

        <section class="summary-strip reveal" aria-label="Resumo dos registros filtrados">
            <article>
                <span>Total filtrado</span>
                <strong>R$ <?php echo number_format($totalGeral, 2, ',', '.'); ?></strong>
            </article>
            <article>
                <span>Materiais</span>
                <strong><?php echo count($produtos); ?></strong>
            </article>
            <article>
                <span>Quantidade comprada</span>
                <strong><?php echo $totalUnidades; ?></strong>
            </article>
            <article>
                <span>Custo médio</span>
                <strong>R$ <?php echo number_format($custoMedio, 2, ',', '.'); ?></strong>
            </article>
        </section>

        <section class="toolbar panel reveal">
            <form action="estoque.php" method="GET" class="form-busca" role="search">
                <div class="form-group">
                    <label for="busca">Buscar material</label>
                    <input type="text" id="busca" name="busca" placeholder="Ex.: cimento, areia, filtro"
                        value="<?php echo htmlspecialchars($termoBusca); ?>">
                </div>
                <div class="form-group">
                    <label for="categoria">Categoria</label>
                    <select id="categoria" name="categoria">
                        <option value="">Todas</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?php echo htmlspecialchars($categoria); ?>" <?php echo $categoriaFiltro === $categoria ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($categoria); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn">Filtrar</button>
                <a href="importar.php" class="btn btn-secondary">Importar XLSX</a>
            </form>
        </section>

        <section class="panel reveal">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Lançamentos</p>
                    <h2>Materiais registrados</h2>
                </div>
            </div>

            <div class="table-wrap">
                <table class="finance-table">
                    <caption class="sr-only">Lista de gastos com materiais da construção da piscina</caption>
                    <thead>
                        <tr>
                            <th scope="col">Material</th>
                            <th scope="col">Categoria</th>
                            <th scope="col">Qtd.</th>
                            <th scope="col">Valor unitário</th>
                            <th scope="col">Valor total</th>
                            <th scope="col">Cadastro</th>
                            <th scope="col">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$produtos): ?>
                            <tr><td colspan="7">Nenhum material encontrado.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($produtos as $produto): ?>
                            <tr>
                                <td class="coluna-produto" data-label="Material">
                                    <div class="thumb">
                                        <?php if (!empty($produto['imagem_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($produto['imagem_url']); ?>"
                                                 alt="Foto de <?php echo htmlspecialchars($produto['nome']); ?>">
                                        <?php else: ?>
                                            <span><?php echo htmlspecialchars(mb_substr($produto['nome'], 0, 1)); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span><?php echo htmlspecialchars($produto['nome']); ?></span>
                                </td>
                                <td data-label="Categoria"><span class="pill"><?php echo htmlspecialchars($produto['categoria']); ?></span></td>
                                <td data-label="Qtd."><?php echo (int) $produto['quantidade']; ?></td>
                                <td data-label="Valor unitário">R$ <?php echo number_format((float) $produto['preco'], 2, ',', '.'); ?></td>
                                <td data-label="Valor total"><strong>R$ <?php echo number_format((float) $produto['valor_investido'], 2, ',', '.'); ?></strong></td>
                                <td data-label="Cadastro"><?php echo date('d/m/Y', strtotime($produto['criado_em'])); ?></td>
                                <td class="coluna-acoes" data-label="Ações">
                                    <form method="POST" action="estoque.php" onsubmit="return confirm('Remover este material dos registros financeiros?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="acao" value="remover">
                                        <input type="hidden" name="id" value="<?php echo (int) $produto['id']; ?>">
                                        <button type="submit" class="icon-button danger" title="Remover material" aria-label="Remover <?php echo htmlspecialchars($produto['nome']); ?>">×</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php if (($_SESSION['nivel_acesso'] ?? '') === 'admin'): ?>
            <form method="GET" action="estoque.php" class="danger-zone"
                  onsubmit="return confirm('Isso vai apagar TODOS os registros financeiros da obra. Continuar?');">
                <input type="hidden" name="limpar" value="sim">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <button type="submit" class="btn btn-danger">Limpar registros</button>
            </form>
        <?php endif; ?>
    </main>

    <?php require_once 'partials/footer.php'; ?>
</body>

</html>
