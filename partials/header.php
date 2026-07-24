<header class="header">
    <?php 
    if (isset($_SESSION['autenticado'])) { 
    ?>
        <div class="container header_title" style="justify-content: space-between;">
            
            <a href="dashboard.php" id="title">
                <img src="img/construtech.png" alt="ConstruTECH" class="logo-img">
            </a>

            <nav class="nav">
                <ul>
                    <li><a href="dashboard.php">Painel Inicial</a></li>
                    <li><a href="estoque.php">Ver Estoque</a></li>
                    <li><a href="produtos.php">Cadastrar Produto</a></li>
                    <li><a href="logout.php" style="color: #dd4242;">Sair</a></li>
                </ul>
            </nav>
            
        </div>

    <?php 
    } else { 
    ?>
        <div class="container header_title" style="justify-content: center;">
            
            <a href="index.php" id="title">
                <img src="img/construtech.png" alt="ConstruTECH" class="logo-img">
            </a>
            
        </div>

    <?php 
    } 
    ?>
</header>