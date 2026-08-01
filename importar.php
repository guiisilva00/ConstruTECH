<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/xlsx_reader.php';
require_nivel(['admin', 'gerente']);

$pdo = get_pdo();

const IMPORT_MAX_BYTES = 5 * 1024 * 1024;
const IMPORT_MAX_LINHAS = 2000;

const CABECALHOS_ACEITOS = [
    'nome'      => ['nome', 'item', 'produto', 'material', 'nome do item'],
    'categoria' => ['categoria'],
    'quantidade'=> ['quantidade', 'qtd', 'quantidade inicial', 'comprado'],
    'preco'     => ['preco', 'preco unitario', 'valor', 'valor unitario'],
    'imagem_url'=> ['imagem', 'imagem url', 'url imagem', 'link da imagem', 'foto'],
];

function normalizar_cabecalho(string $texto): string
{
    $texto = mb_strtolower(trim($texto));
    $semAcento = @iconv('UTF-8', 'ASCII//TRANSLIT', $texto);
    return $semAcento !== false ? $semAcento : $texto;
}

function mapear_colunas(array $linhaCabecalho): array
{
    $normalizados = array_map('normalizar_cabecalho', $linhaCabecalho);
    $mapa = [];

    foreach (CABECALHOS_ACEITOS as $campo => $variacoes) {
        foreach ($normalizados as $indice => $cabecalho) {
            if (in_array($cabecalho, $variacoes, true)) {
                $mapa[$campo] = $indice;
                break;
            }
        }
    }

    return $mapa;
}

$erroGeral = '';
$relatorio = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    if (empty($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
        $erroGeral = 'Nenhum arquivo válido foi enviado.';
    } elseif ($_FILES['arquivo']['size'] > IMPORT_MAX_BYTES) {
        $erroGeral = 'Arquivo muito grande. O limite é de 5MB.';
    } elseif (strtolower(pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
        $erroGeral = 'Envie um arquivo no formato .xlsx.';
    } else {
        $assinatura = file_get_contents($_FILES['arquivo']['tmp_name'], false, null, 0, 2);
        if ($assinatura !== 'PK') {
            $erroGeral = 'O arquivo enviado não parece ser um .xlsx válido.';
        } else {
            $caminhoTemp = sys_get_temp_dir() . '/import_' . bin2hex(random_bytes(8)) . '.xlsx';

            if (!move_uploaded_file($_FILES['arquivo']['tmp_name'], $caminhoTemp)) {
                $erroGeral = 'Falha ao processar o upload. Tente novamente.';
            } else {
                try {
                    $linhas = XlsxReader::read($caminhoTemp, IMPORT_MAX_LINHAS + 1);

                    if (count($linhas) < 2) {
                        $erroGeral = 'A planilha precisa ter cabeçalho e ao menos uma linha de dados.';
                    } elseif (count($linhas) - 1 > IMPORT_MAX_LINHAS) {
                        $erroGeral = 'Planilha excede o limite de ' . IMPORT_MAX_LINHAS . ' linhas por importação.';
                    } else {
                        $mapa = mapear_colunas($linhas[0]);
                        $obrigatorios = ['nome', 'categoria', 'quantidade', 'preco'];
                        $faltando = array_diff($obrigatorios, array_keys($mapa));

                        if ($faltando) {
                            $erroGeral = 'Colunas obrigatórias não encontradas: ' . implode(', ', $faltando) . '.';
                        } else {
                            $relatorio = importar_linhas($pdo, $linhas, $mapa);
                        }
                    }
                } catch (XlsxReadException $e) {
                    $erroGeral = $e->getMessage();
                } finally {
                    @unlink($caminhoTemp);
                }
            }
        }
    }
}

function importar_linhas(PDO $pdo, array $linhas, array $mapa): array
{
    $sucesso = 0;
    $erros = [];

    $stmtCategoria = $pdo->prepare('SELECT id FROM categorias WHERE LOWER(nome) = LOWER(:nome)');
    $stmtInsert = $pdo->prepare(
        'INSERT INTO produtos (nome, categoria_id, quantidade, preco, imagem_url)
         VALUES (:nome, :categoria_id, :quantidade, :preco, :imagem_url)'
    );

    for ($i = 1; $i < count($linhas); $i++) {
        $numeroLinha = $i + 1;
        $linha = $linhas[$i];

        $nome       = trim($linha[$mapa['nome']] ?? '');
        $categoria  = trim($linha[$mapa['categoria']] ?? '');
        $qtdRaw     = str_replace(',', '.', trim($linha[$mapa['quantidade']] ?? ''));
        $precoRaw   = str_replace(',', '.', trim($linha[$mapa['preco']] ?? ''));
        $imagemUrl  = isset($mapa['imagem_url']) ? trim($linha[$mapa['imagem_url']] ?? '') : '';

        if ($nome === '' && $categoria === '' && $qtdRaw === '' && $precoRaw === '') {
            continue;
        }

        if ($nome === '' || mb_strlen($nome) > 150) {
            $erros[] = "Linha $numeroLinha: material inválido ou ausente.";
            continue;
        }
        if (!is_numeric($qtdRaw) || (float) $qtdRaw < 0) {
            $erros[] = "Linha $numeroLinha: quantidade inválida ('$qtdRaw').";
            continue;
        }
        if (!is_numeric($precoRaw) || (float) $precoRaw < 0) {
            $erros[] = "Linha $numeroLinha: valor unitário inválido ('$precoRaw').";
            continue;
        }
        if ($imagemUrl !== '' && !filter_var($imagemUrl, FILTER_VALIDATE_URL)) {
            $erros[] = "Linha $numeroLinha: link de imagem inválido.";
            continue;
        }

        $stmtCategoria->execute([':nome' => $categoria]);
        $categoriaId = $stmtCategoria->fetchColumn();

        if (!$categoriaId) {
            $erros[] = "Linha $numeroLinha: categoria '$categoria' não cadastrada.";
            continue;
        }

        try {
            $stmtInsert->execute([
                ':nome'         => $nome,
                ':categoria_id' => $categoriaId,
                ':quantidade'   => (int) round((float) $qtdRaw),
                ':preco'        => round((float) $precoRaw, 2),
                ':imagem_url'   => $imagemUrl !== '' ? $imagemUrl : null,
            ]);
            $sucesso++;
        } catch (PDOException $e) {
            error_log('Erro ao importar linha ' . $numeroLinha . ': ' . $e->getMessage());
            $erros[] = "Linha $numeroLinha: erro ao salvar no banco de dados.";
        }
    }

    return ['sucesso' => $sucesso, 'erros' => $erros];
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ConstruTECH | Importar gastos</title>
    <link rel="stylesheet" href="./css/style.css">
    <link rel="stylesheet" href="./css/header.css">
    <link rel="stylesheet" href="./css/produtos.css">
    <link rel="stylesheet" href="./css/footer.css">
    <link rel="stylesheet" href="./css/modal.css">
    <link rel="shortcut icon" href="./img/favicon.png" type="image/x-icon">
</head>

<body>
    <?php require_once 'partials/header.php'; ?>

    <main class="app-shell form-page" id="conteudo-principal">
        <section class="page-intro reveal">
            <div>
                <p class="eyebrow">Importação XLSX</p>
                <h1>Importar gastos da planilha</h1>
                <p>Carregue a planilha de materiais comprados para atualizar o acompanhamento financeiro da piscina.</p>
            </div>
        </section>

        <section class="panel reveal import-guide">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Modelo esperado</p>
                    <h2>Colunas da planilha</h2>
                </div>
                <a href="templates/modelo_importacao.csv" class="btn btn-secondary">Baixar CSV</a>
            </div>

            <div class="mini-grid">
                <span>Nome</span>
                <span>Categoria</span>
                <span>Quantidade</span>
                <span>Preço</span>
                <span>Imagem URL</span>
            </div>
        </section>

        <?php if ($erroGeral): ?>
            <p class="msg-erro"><?php echo htmlspecialchars($erroGeral); ?></p>
        <?php endif; ?>

        <?php if ($relatorio): ?>
            <div class="msg-erro <?php echo $relatorio['erros'] ? '' : 'msg-sucesso'; ?>" role="status">
                <strong><?php echo (int) $relatorio['sucesso']; ?> material(is) importado(s).</strong>
                <?php if ($relatorio['erros']): ?>
                    <p><?php echo count($relatorio['erros']); ?> linha(s) ignorada(s):</p>
                    <ul>
                        <?php foreach ($relatorio['erros'] as $erroLinha): ?>
                            <li><?php echo htmlspecialchars($erroLinha); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="importar.php" enctype="multipart/form-data" class="form-cadastro panel reveal">
            <?php echo csrf_field(); ?>

            <div class="form-group">
                <label for="arquivo">Arquivo .xlsx</label>
                <input type="file" id="arquivo" name="arquivo" accept=".xlsx" required>
            </div>

            <div class="form-actions">
                <a href="estoque.php" class="btn btn-secondary">Ver registros</a>
                <button type="submit" class="btn">Importar gastos</button>
            </div>
        </form>
    </main>

    <?php require_once 'partials/footer.php'; ?>
</body>

</html>
