<?php if (!empty($_SESSION['usuario_id'])): ?>
<button class="ai-fab" type="button" id="ai-fab" aria-label="Abrir assistente da obra" aria-expanded="false">
    <span class="ai-fab-icon">AI</span>
    <span class="ai-fab-pulse" aria-hidden="true"></span>
</button>

<div class="ai-modal" id="ai-modal" data-endpoint="assistant.php" data-csrf-token="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>" hidden>
    <div class="ai-backdrop" data-ai-close></div>
    <section class="ai-panel" role="dialog" aria-modal="true" aria-labelledby="ai-title">
        <header class="ai-header">
            <div>
                <p class="eyebrow">Copiloto da obra</p>
                <h2 id="ai-title">Assistente ConstruTECH</h2>
            </div>
            <button class="icon-button" type="button" data-ai-close aria-label="Fechar assistente">×</button>
        </header>

        <div class="ai-messages" id="ai-messages" aria-live="polite">
            <article class="ai-message assistant">
                <div class="ai-avatar">AI</div>
                <div class="ai-bubble">
                    Olá. Posso consultar gastos, listar materiais, cadastrar compras e preparar alterações com confirmação.
                </div>
            </article>
        </div>

        <div class="ai-typing" id="ai-typing" hidden>
            <span></span><span></span><span></span>
            <b>Assistente analisando</b>
        </div>

        <form class="ai-form" id="ai-form">
            <textarea id="ai-input" rows="1" maxlength="600" placeholder="Pergunte ou peça uma ação. Ex.: Quanto já foi gasto na obra?"></textarea>
            <button type="submit" class="btn" id="ai-send">Enviar</button>
        </form>
    </section>
</div>

<script src="./js/assistant.js"></script>
<?php endif; ?>
