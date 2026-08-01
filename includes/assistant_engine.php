<?php

function assistant_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

function assistant_normalize(string $text): string
{
    $text = mb_strtolower(trim($text));
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    $text = $ascii !== false ? $ascii : $text;
    return preg_replace('/\s+/u', ' ', $text);
}

function assistant_money(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function assistant_parse_money(string $value): ?float
{
    $value = trim($value);
    $value = str_replace(['R$', 'r$', ' '], '', $value);
    $value = str_replace('.', '', $value);
    $value = str_replace(',', '.', $value);
    return is_numeric($value) ? round((float) $value, 2) : null;
}

function assistant_clean_target(string $target): string
{
    $target = trim($target, " .\"'");
    $target = preg_replace('/^(o|a|os|as|de|do|da|dos|das|esse|essa|desse|dessa|registro|item|material)\s+/iu', '', $target);
    $target = preg_replace('/^(o|a|os|as|de|do|da|dos|das)\s+/iu', '', trim($target));
    return trim($target, " .\"'");
}

function assistant_fetch_stats(PDO $pdo): array
{
    return $pdo->query(
        'SELECT COUNT(*) AS total_materiais,
                COALESCE(SUM(valor_investido), 0) AS total_investido,
                COALESCE(AVG(valor_investido), 0) AS custo_medio,
                COALESCE(MAX(valor_investido), 0) AS maior_gasto
         FROM produtos'
    )->fetch();
}

function assistant_find_materials(PDO $pdo, string $term, int $limit = 8): array
{
    $stmt = $pdo->prepare(
        'SELECT p.id, p.nome, p.quantidade, p.preco, p.valor_investido, p.imagem_url, p.criado_em, c.nome AS categoria
         FROM produtos p
         JOIN categorias c ON c.id = p.categoria_id
         WHERE p.nome LIKE :term
         ORDER BY p.criado_em DESC
         LIMIT ' . (int) $limit
    );
    $stmt->execute([':term' => '%' . $term . '%']);
    return $stmt->fetchAll();
}

function assistant_format_material(array $item): string
{
    return $item['nome'] . ' · ' . $item['categoria'] . ' · ' .
        (int) $item['quantidade'] . ' un. x ' . assistant_money((float) $item['preco']) .
        ' = ' . assistant_money((float) $item['valor_investido']);
}

function assistant_bullets(array $items): string
{
    if (!$items) {
        return 'Não encontrei registros com esses critérios.';
    }

    $lines = [];
    foreach ($items as $item) {
        $lines[] = '• ' . assistant_format_material($item);
    }
    return implode("\n", $lines);
}

function assistant_categories(PDO $pdo): array
{
    return $pdo->query('SELECT id, nome FROM categorias ORDER BY nome')->fetchAll();
}

function assistant_category_id(PDO $pdo, string $name): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM categorias WHERE LOWER(nome) = LOWER(:name)');
    $stmt->execute([':name' => $name]);
    $id = $stmt->fetchColumn();
    return $id ? (int) $id : null;
}

function assistant_detect_category(PDO $pdo, string $message): ?string
{
    $normalized = assistant_normalize($message);
    foreach (assistant_categories($pdo) as $category) {
        if (str_contains($normalized, assistant_normalize($category['nome']))) {
            return $category['nome'];
        }
    }
    return null;
}

function assistant_store_pending(array $action): array
{
    if (empty($_SESSION['assistant_pending_actions'])) {
        $_SESSION['assistant_pending_actions'] = [];
    }

    $id = bin2hex(random_bytes(8));
    $_SESSION['assistant_pending_actions'][$id] = [
        'action' => $action,
        'created_at' => time(),
    ];

    return ['id' => $id, 'action' => $action];
}

function assistant_execute_pending(PDO $pdo, string $id): array
{
    $pending = $_SESSION['assistant_pending_actions'][$id] ?? null;
    if (!$pending || time() - (int) $pending['created_at'] > 600) {
        return ['reply' => 'Essa confirmação expirou. Me peça a ação novamente para eu refazer com segurança.'];
    }

    unset($_SESSION['assistant_pending_actions'][$id]);
    $action = $pending['action'];

    if ($action['type'] === 'delete_material') {
        $stmt = $pdo->prepare('DELETE FROM produtos WHERE id = :id');
        $stmt->execute([':id' => (int) $action['id']]);
        $_SESSION['assistant_last_removed'] = $action['snapshot'];
        return ['reply' => 'Removi "' . $action['snapshot']['nome'] . '" dos registros financeiros.'];
    }

    if ($action['type'] === 'update_price') {
        $stmt = $pdo->prepare('UPDATE produtos SET preco = :preco WHERE id = :id');
        $stmt->execute([':preco' => (float) $action['preco'], ':id' => (int) $action['id']]);
        return ['reply' => 'Atualizei o valor unitário de "' . $action['nome'] . '" para ' . assistant_money((float) $action['preco']) . '.'];
    }

    if ($action['type'] === 'update_category') {
        $stmt = $pdo->prepare('UPDATE produtos SET categoria_id = :categoria_id WHERE id = :id');
        $stmt->execute([':categoria_id' => (int) $action['categoria_id'], ':id' => (int) $action['id']]);
        return ['reply' => 'Atualizei a categoria de "' . $action['nome'] . '" para ' . $action['categoria'] . '.'];
    }

    return ['reply' => 'Não consegui reconhecer a ação pendente.'];
}

function assistant_suggestions(PDO $pdo): string
{
    $items = $pdo->query(
        'SELECT p.id, p.nome, p.quantidade, p.preco, p.valor_investido, c.nome AS categoria
         FROM produtos p
         JOIN categorias c ON c.id = p.categoria_id
         ORDER BY p.valor_investido DESC'
    )->fetchAll();

    if (!$items) {
        return 'Ainda não há materiais suficientes para gerar sugestões.';
    }

    $suggestions = [];
    $seen = [];
    foreach ($items as $item) {
        $key = assistant_normalize($item['nome']);
        $seen[$key][] = $item;
    }

    foreach ($seen as $group) {
        if (count($group) > 1) {
            $suggestions[] = 'Possível duplicidade: "' . $group[0]['nome'] . '" aparece ' . count($group) . ' vezes.';
        }
    }

    $top = $items[0];
    $total = array_sum(array_map(fn ($item) => (float) $item['valor_investido'], $items));
    if ($total > 0 && ((float) $top['valor_investido'] / $total) >= 0.15) {
        $suggestions[] = '"' . $top['nome'] . '" tem grande impacto no orçamento: ' . assistant_money((float) $top['valor_investido']) . '.';
    }

    foreach ($seen as $group) {
        if (count($group) > 1) {
            $prices = array_map(fn ($item) => (float) $item['preco'], $group);
            if (max($prices) - min($prices) > 50) {
                $suggestions[] = 'Há variação relevante de preço em "' . $group[0]['nome'] . '". Vale revisar se são o mesmo material.';
                break;
            }
        }
    }

    if (!$suggestions) {
        return 'Os registros parecem consistentes. Minha sugestão agora é manter nomes padronizados e usar a mesma categoria para materiais equivalentes.';
    }

    return "Encontrei algumas oportunidades:\n" . implode("\n", array_map(fn ($item) => '• ' . $item, array_slice($suggestions, 0, 5)));
}

function assistant_handle_message(PDO $pdo, string $message): array
{
    $message = sanitize_texto($message, 600);
    $normalized = assistant_normalize($message);

    if ($message === '') {
        return ['reply' => 'Me diga o que você quer consultar ou alterar nos gastos da piscina.'];
    }

    if (str_contains($normalized, 'sugest')) {
        return ['reply' => assistant_suggestions($pdo)];
    }

    if ((str_contains($normalized, 'adicione novamente') || str_contains($normalized, 'adiciona novamente') || str_contains($normalized, 'cadastre novamente')) && !empty($_SESSION['assistant_last_removed'])) {
        $last = $_SESSION['assistant_last_removed'];
        $quantity = (int) ($last['quantidade'] ?? 0);
        if (preg_match('/(?:quantidade|qtd|com)\s+(\d+)/iu', $message, $match)) {
            $quantity = (int) $match[1];
        }

        $categoryId = assistant_category_id($pdo, $last['categoria'] ?? 'Bruto') ?? assistant_category_id($pdo, 'Bruto');
        if ($quantity <= 0 || !$categoryId) {
            return ['reply' => 'Não consegui recuperar a quantidade desse item. Me diga a quantidade para cadastrar novamente.'];
        }

        $stmt = $pdo->prepare(
            'INSERT INTO produtos (nome, categoria_id, quantidade, preco, imagem_url)
             VALUES (:nome, :categoria_id, :quantidade, :preco, :imagem_url)'
        );
        $stmt->execute([
            ':nome' => $last['nome'],
            ':categoria_id' => $categoryId,
            ':quantidade' => $quantity,
            ':preco' => (float) $last['preco'],
            ':imagem_url' => $last['imagem_url'] ?? null,
        ]);

        return ['reply' => 'Cadastrei novamente "' . $last['nome'] . '" com ' . $quantity . ' unidade(s), valor unitário de ' . assistant_money((float) $last['preco']) . '.'];
    }

    if (preg_match('/(quanto|total).*(gasto|investido|obra|piscina)/', $normalized)) {
        $stats = assistant_fetch_stats($pdo);
        return ['reply' => 'Até agora foram investidos ' . assistant_money((float) $stats['total_investido']) . ' em ' . (int) $stats['total_materiais'] . ' materiais registrados.'];
    }

    if (str_contains($normalized, 'item mais caro') || str_contains($normalized, 'material mais caro') || str_contains($normalized, 'maior gasto')) {
        $item = $pdo->query(
            'SELECT p.*, c.nome AS categoria
             FROM produtos p
             JOIN categorias c ON c.id = p.categoria_id
             ORDER BY p.valor_investido DESC
             LIMIT 1'
        )->fetch();
        return ['reply' => $item ? 'O maior gasto é: ' . assistant_format_material($item) . '.' : 'Ainda não há materiais registrados.'];
    }

    if (preg_match('/categoria\s+([a-zA-ZÀ-ÿ]+)/u', $message, $match) && (str_contains($normalized, 'liste') || str_contains($normalized, 'listar') || str_contains($normalized, 'materiais'))) {
        $category = trim($match[1]);
        $stmt = $pdo->prepare(
            'SELECT p.*, c.nome AS categoria
             FROM produtos p
             JOIN categorias c ON c.id = p.categoria_id
             WHERE LOWER(c.nome) = LOWER(:categoria)
             ORDER BY p.valor_investido DESC
             LIMIT 30'
        );
        $stmt->execute([':categoria' => $category]);
        return ['reply' => assistant_bullets($stmt->fetchAll())];
    }

    if (preg_match('/(?:mais de|acima de|maior que)\s*r?\$?\s*([\d.,]+)/i', $message, $match)) {
        $value = assistant_parse_money($match[1]);
        if ($value !== null) {
            $stmt = $pdo->prepare(
                'SELECT p.*, c.nome AS categoria
                 FROM produtos p
                 JOIN categorias c ON c.id = p.categoria_id
                 WHERE p.valor_investido > :value
                 ORDER BY p.valor_investido DESC
                 LIMIT 30'
            );
            $stmt->execute([':value' => $value]);
            return ['reply' => assistant_bullets($stmt->fetchAll())];
        }
    }

    if (str_contains($normalized, 'esta semana') || str_contains($normalized, 'semana')) {
        $items = $pdo->query(
            'SELECT p.*, c.nome AS categoria
             FROM produtos p
             JOIN categorias c ON c.id = p.categoria_id
             WHERE p.criado_em >= (CURDATE() - INTERVAL 7 DAY)
             ORDER BY p.criado_em DESC
             LIMIT 30'
        )->fetchAll();
        return ['reply' => assistant_bullets($items)];
    }

    if (preg_match('/quanto.*(?:com|de)\s+(.+)$/i', $message, $match)) {
        $term = trim($match[1], " ?.");
        $items = assistant_find_materials($pdo, $term, 30);
        $total = array_sum(array_map(fn ($item) => (float) $item['valor_investido'], $items));
        return ['reply' => $items ? 'Encontrei ' . count($items) . ' registro(s) relacionado(s) a "' . $term . '", somando ' . assistant_money($total) . ".\n" . assistant_bullets($items) : 'Não encontrei gastos relacionados a "' . $term . '".'];
    }

    if (preg_match('/(?:adicione|adicionar|cadastre|registre|inclua).*(\d+)\s+(?:unidades?\s+de\s+|un\.?\s+de\s+|de\s+)?(.+?)\s+(?:por|a|ao valor de|custando)\s*r?\$?\s*([\d.,]+)/iu', $message, $match)) {
        $quantity = (int) $match[1];
        $name = trim($match[2], " .");
        $price = assistant_parse_money($match[3]);
        $category = assistant_detect_category($pdo, $message) ?? 'Bruto';
        $categoryId = assistant_category_id($pdo, $category);

        if ($quantity <= 0 || $price === null || !$categoryId || $name === '') {
            return ['reply' => 'Não consegui montar esse cadastro. Tente algo como: "Adicione 15 sacos de cimento por R$ 36,90 na categoria Bruto".'];
        }

        $stmt = $pdo->prepare(
            'INSERT INTO produtos (nome, categoria_id, quantidade, preco, imagem_url)
             VALUES (:nome, :categoria_id, :quantidade, :preco, NULL)'
        );
        $stmt->execute([
            ':nome' => $name,
            ':categoria_id' => $categoryId,
            ':quantidade' => $quantity,
            ':preco' => $price,
        ]);

        return ['reply' => 'Cadastrei "' . $name . '" com ' . $quantity . ' unidade(s), valor unitário de ' . assistant_money($price) . ' e categoria ' . $category . '.'];
    }

    if (preg_match('/(?:remova|remover|exclua|excluir|apague|apagar)\s+(.+)$/iu', $message, $match)) {
        $target = assistant_clean_target($match[1]);
        if (str_contains(assistant_normalize($target), 'ultima compra')) {
            $items = $pdo->query(
                'SELECT p.*, c.nome AS categoria
                 FROM produtos p
                 JOIN categorias c ON c.id = p.categoria_id
                 ORDER BY p.criado_em DESC
                 LIMIT 1'
            )->fetchAll();
        } else {
            $items = assistant_find_materials($pdo, $target, 5);
        }

        if (!$items) {
            return ['reply' => 'Não encontrei um material para remover com esse nome.'];
        }
        if (count($items) > 1) {
            return ['reply' => "Encontrei mais de um registro. Qual deles você quer remover?\n" . assistant_bullets($items)];
        }

        $item = $items[0];
        $pending = assistant_store_pending([
            'type' => 'delete_material',
            'id' => (int) $item['id'],
            'snapshot' => $item,
        ]);

        return [
            'reply' => 'Encontrei "' . $item['nome'] . '" no valor total de ' . assistant_money((float) $item['valor_investido']) . '. Deseja realmente remover esse registro?',
            'confirmation' => [
                'id' => $pending['id'],
                'label' => 'Remover material',
            ],
        ];
    }

    if (preg_match('/(?:atualize|altere|mude).*(?:preco|preço|valor).*?(.+?)\s+(?:para|por)\s*r?\$?\s*([\d.,]+)/iu', $message, $match)) {
        $target = assistant_clean_target($match[1]);
        $price = assistant_parse_money($match[2]);
        $items = assistant_find_materials($pdo, $target, 5);
        if (!$items || $price === null) {
            return ['reply' => 'Não encontrei o material ou o novo valor. Exemplo: "Atualize o valor da brita para R$ 920".'];
        }
        if (count($items) > 1) {
            return ['reply' => "Encontrei mais de um registro. Qual deles devo atualizar?\n" . assistant_bullets($items)];
        }

        $item = $items[0];
        $pending = assistant_store_pending([
            'type' => 'update_price',
            'id' => (int) $item['id'],
            'nome' => $item['nome'],
            'preco' => $price,
        ]);

        return [
            'reply' => 'Posso atualizar "' . $item['nome'] . '" de ' . assistant_money((float) $item['preco']) . ' para ' . assistant_money($price) . '. Confirmar alteração?',
            'confirmation' => [
                'id' => $pending['id'],
                'label' => 'Atualizar valor',
            ],
        ];
    }

    if (preg_match('/(?:categoria|coloca|coloque|mude|altere).*?(.+?)\s+(?:para|na categoria)\s+(Bruto|Ferramentas|Acabamento)/iu', $message, $match)) {
        $target = assistant_clean_target($match[1]);
        $category = $match[2];
        $categoryId = assistant_category_id($pdo, $category);
        $items = assistant_find_materials($pdo, $target, 5);

        if (!$items || !$categoryId) {
            return ['reply' => 'Não encontrei o material ou a categoria. As categorias atuais são Bruto, Ferramentas e Acabamento.'];
        }
        if (count($items) > 1) {
            return ['reply' => "Encontrei mais de um registro. Qual deles devo alterar?\n" . assistant_bullets($items)];
        }

        $item = $items[0];
        $pending = assistant_store_pending([
            'type' => 'update_category',
            'id' => (int) $item['id'],
            'nome' => $item['nome'],
            'categoria_id' => $categoryId,
            'categoria' => $category,
        ]);

        return [
            'reply' => 'Posso alterar "' . $item['nome'] . '" para a categoria ' . $category . '. Confirmar?',
            'confirmation' => [
                'id' => $pending['id'],
                'label' => 'Alterar categoria',
            ],
        ];
    }

    if (preg_match('/(?:busque|buscar|procure|pesquise|localize|liste|listar)\s+(.+)$/iu', $message, $match)) {
        $term = assistant_clean_target($match[1]);
        $items = assistant_find_materials($pdo, $term, 20);
        return ['reply' => assistant_bullets($items)];
    }

    return [
        'reply' => "Posso consultar gastos, listar materiais, buscar itens, cadastrar compras e preparar alterações com confirmação.\nExemplos:\n• Quanto já foi gasto na obra?\n• Liste materiais da categoria Bruto.\n• Adicione 15 sacos de cimento por R$ 36,90.\n• Remova a última compra.\n• Sugira melhorias nos registros.",
    ];
}
