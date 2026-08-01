<a href="#conteudo-principal" class="skip-link">Pular para o conteúdo principal</a>
<header class="header">
    <?php if (isset($_SESSION['autenticado'])): ?>
        <div class="header_title">
            <a href="dashboard.php" id="title" aria-label="Ir para o painel">
                <span class="brand-mark">CT</span>
                <span class="brand-copy">
                    <strong>ConstruTECH</strong>
                    <small>Gastos da piscina</small>
                </span>
            </a>

            <nav class="nav" aria-label="Navegação principal">
                <ul>
                    <li><a href="dashboard.php">Painel</a></li>
                    <li><a href="estoque.php">Gastos</a></li>
                    <li><a href="produtos.php">Novo gasto</a></li>
                    <li><a href="importar.php">Importar</a></li>
                    <li><a href="logout.php" class="logout-link">Sair</a></li>
                </ul>
            </nav>
        </div>
    <?php else: ?>
        <div class="header_title header_title_center">
            <a href="index.php" id="title" aria-label="ConstruTECH">
                <span class="brand-mark">CT</span>
                <span class="brand-copy">
                    <strong>ConstruTECH</strong>
                    <small>Gastos da piscina</small>
                </span>
            </a>
        </div>
    <?php endif; ?>
</header>
