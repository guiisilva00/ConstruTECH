<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_login();

$pdo = get_pdo();

$stats = $pdo->query(
    'SELECT
        COUNT(*) AS total_materiais,
        COALESCE(SUM(valor_investido), 0) AS total_investido,
        COALESCE(AVG(valor_investido), 0) AS custo_medio,
        COALESCE(MAX(valor_investido), 0) AS maior_gasto
     FROM produtos'
)->fetch();

$categorias = $pdo->query(
    'SELECT c.nome, COUNT(p.id) AS total_itens, COALESCE(SUM(p.valor_investido), 0) AS total
     FROM categorias c
     LEFT JOIN produtos p ON p.categoria_id = c.id
     GROUP BY c.id, c.nome
     ORDER BY total DESC'
)->fetchAll();

$recentes = $pdo->query(
    'SELECT p.nome, p.quantidade, p.preco, p.valor_investido, p.imagem_url, p.criado_em, c.nome AS categoria
     FROM produtos p
     JOIN categorias c ON c.id = p.categoria_id
     ORDER BY p.criado_em DESC
     LIMIT 6'
)->fetchAll();

$totalInvestido = (float) $stats['total_investido'];
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ConstruTECH | Gastos da piscina</title>
    <link rel="stylesheet" href="./css/style.css">
    <link rel="stylesheet" href="./css/dashboard.css">
    <link rel="stylesheet" href="./css/header.css">
    <link rel="stylesheet" href="./css/footer.css">
    <link rel="stylesheet" href="./css/modal.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="./img/favicon.png" type="image/x-icon">
</head>

<body>
    <?php require_once 'partials/header.php'; ?>

    <main class="app-shell dashboard-container" id="conteudo-principal">
        <section class="hero-panel reveal">
            <div>
                <p class="eyebrow">Obra da piscina</p>
                <h1>Acompanhamento financeiro dos materiais da construção.</h1>
                <p class="hero-copy">
                    Olá, <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?>. Aqui você acompanha quanto já foi investido,
                    quais categorias pesam mais no orçamento e os lançamentos recentes da obra.
                </p>
            </div>
            <div class="hero-total" aria-label="Valor total investido">
                <span>Total investido</span>
                <strong>R$ <?php echo number_format($totalInvestido, 2, ',', '.'); ?></strong>
            </div>
        </section>

        <section class="metric-grid reveal" aria-label="Resumo financeiro">
            <article class="metric-card metric-card-primary">
                <span class="metric-icon">R$</span>
                <p>Valor investido</p>
                <strong>R$ <?php echo number_format($totalInvestido, 2, ',', '.'); ?></strong>
            </article>
            <article class="metric-card">
                <span class="metric-icon">#</span>
                <p>Materiais registrados</p>
                <strong><?php echo (int) $stats['total_materiais']; ?></strong>
            </article>
            <article class="metric-card">
                <span class="metric-icon">Ø</span>
                <p>Custo médio por item</p>
                <strong>R$ <?php echo number_format((float) $stats['custo_medio'], 2, ',', '.'); ?></strong>
            </article>
            <article class="metric-card">
                <span class="metric-icon">MAX</span>
                <p>Maior lançamento</p>
                <strong>R$ <?php echo number_format((float) $stats['maior_gasto'], 2, ',', '.'); ?></strong>
            </article>
        </section>

        <section class="dashboard-layout">
            <div class="panel reveal">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Categorias</p>
                        <h2>Distribuição do orçamento</h2>
                    </div>
                    <a href="estoque.php" class="btn btn-secondary">Ver materiais</a>
                </div>

                <div class="category-list">
                    <?php foreach ($categorias as $categoria):
                        $totalCategoria = (float) $categoria['total'];
                        $percentual = $totalInvestido > 0 ? min(100, ($totalCategoria / $totalInvestido) * 100) : 0;
                    ?>
                        <article class="category-row">
                            <div class="category-row-top">
                                <div>
                                    <strong><?php echo htmlspecialchars($categoria['nome']); ?></strong>
                                    <span><?php echo (int) $categoria['total_itens']; ?> material(is)</span>
                                </div>
                                <b>R$ <?php echo number_format($totalCategoria, 2, ',', '.'); ?></b>
                            </div>
                            <div class="finance-bar" aria-hidden="true">
                                <span style="width: <?php echo number_format($percentual, 2, '.', ''); ?>%;"></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <aside class="panel reveal quick-actions">
                <p class="eyebrow">Atalhos</p>
                <h2>Registrar e revisar</h2>
                <a href="produtos.php" class="action-card">
                    <span>+</span>
                    <div>
                        <strong>Novo gasto</strong>
                        <small>Lançar material comprado</small>
                    </div>
                </a>
                <a href="importar.php" class="action-card">
                    <span>XLS</span>
                    <div>
                        <strong>Importar planilha</strong>
                        <small>Carregar compras em lote</small>
                    </div>
                </a>
                <a href="estoque.php" class="action-card">
                    <span>R$</span>
                    <div>
                        <strong>Ver registros</strong>
                        <small>Consultar todos os materiais</small>
                    </div>
                </a>
            </aside>
        </section>

        <section class="panel reveal">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Últimos lançamentos</p>
                    <h2>Compras recentes</h2>
                </div>
            </div>

            <div class="recent-grid">
                <?php if (!$recentes): ?>
                    <p class="empty-state">Nenhum gasto registrado ainda.</p>
                <?php endif; ?>

                <?php foreach ($recentes as $item): ?>
                    <article class="material-card compact">
                        <div class="material-image">
                            <?php if (!empty($item['imagem_url'])): ?>
                                <img src="<?php echo htmlspecialchars($item['imagem_url']); ?>" alt="Foto de <?php echo htmlspecialchars($item['nome']); ?>">
                            <?php else: ?>
                                <span><?php echo htmlspecialchars(mb_substr($item['nome'], 0, 1)); ?></span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="pill"><?php echo htmlspecialchars($item['categoria']); ?></span>
                            <h3><?php echo htmlspecialchars($item['nome']); ?></h3>
                            <p><?php echo (int) $item['quantidade']; ?> un. x R$ <?php echo number_format((float) $item['preco'], 2, ',', '.'); ?></p>
                        </div>
                        <strong>R$ <?php echo number_format((float) $item['valor_investido'], 2, ',', '.'); ?></strong>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <?php require_once 'partials/footer.php'; ?>
</body>

</html>
