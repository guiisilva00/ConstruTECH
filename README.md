# ConstruTECH - Gastos da piscina

Sistema pessoal para acompanhar os gastos dos materiais usados na construção da piscina de casa.

O projeto deixou de ser um controle de estoque e agora funciona como um painel financeiro da obra. A base de dados e a importação XLSX foram preservadas, mas a experiência visual e os textos foram direcionados para:

- valor total investido;
- materiais comprados;
- quantidade adquirida;
- valor unitário;
- valor total por material;
- distribuição dos gastos por categoria;
- importação de compras por planilha `.xlsx`;
- uso confortável em desktop, tablet e celular.
- assistente integrado para consultas e ações por linguagem natural.

## Telas principais

- `dashboard.php`: visão geral financeira da obra, métricas e distribuição por categoria.
- `estoque.php`: listagem dos registros financeiros dos materiais.
- `produtos.php`: cadastro manual de um novo gasto.
- `importar.php`: importação de materiais por planilha XLSX.
- `movimentacoes.php`: auditoria técnica de alterações antigas.
- `assistant.php`: endpoint JSON do copiloto da obra.
- `includes/assistant_engine.php`: interpretação de mensagens, contexto financeiro e execução de ações.
- `partials/assistant.php`: botão flutuante e modal de chat.
- `js/assistant.js`: comportamento do chat no navegador.

## Assistente da obra

O assistente aparece como um botão flutuante nas telas autenticadas. Ele consegue responder perguntas sobre os gastos e executar algumas ações diretamente no sistema.

Exemplos de comandos:

- `Quanto já foi gasto na obra?`
- `Qual foi o item mais caro?`
- `Liste materiais da categoria Bruto.`
- `Quais materiais custam mais de R$ 500?`
- `Quanto gastamos com cimento?`
- `Adicione 15 sacos de cimento por R$ 36,90.`
- `Remova a última compra.`
- `Atualize o valor da brita para R$ 920.`
- `Sugira melhorias nos registros.`

Ações destrutivas ou alterações importantes pedem confirmação antes de serem executadas.

## Planilha de importação

Colunas aceitas:

| Coluna | Obrigatória | Observação |
| --- | --- | --- |
| Nome | Sim | Nome do material |
| Categoria | Sim | Bruto, Ferramentas ou Acabamento |
| Quantidade | Sim | Quantidade comprada |
| Preço | Sim | Valor unitário |
| Imagem URL | Não | Link da imagem do material |

O arquivo deve ser `.xlsx` e ter até 5MB.

## Observação técnica

Alguns nomes internos ainda usam `produtos` e `estoque.php` para preservar compatibilidade com a estrutura existente do banco e evitar migração neste redesign. Visualmente, o sistema agora é apresentado como acompanhamento financeiro da construção da piscina.
