-- ============================================================
-- ConstruTECH - Schema do banco de dados
-- Motor: MySQL 8+ / MariaDB 10.4+
-- Charset: utf8mb4 (suporta acentos e emojis usados na UI)
-- ============================================================

CREATE DATABASE IF NOT EXISTS db_2td
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE db_2td;

-- ------------------------------------------------------------
-- USUÁRIOS (login + controle de acesso por nível)
-- Senhas NUNCA em texto puro: sempre password_hash() do PHP.
-- ------------------------------------------------------------
CREATE TABLE usuarios (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome            VARCHAR(120)        NOT NULL,
    usuario         VARCHAR(60)         NOT NULL,
    senha_hash      VARCHAR(255)        NOT NULL,
    nivel_acesso    ENUM('admin','gerente','operador') NOT NULL DEFAULT 'operador',
    ativo           TINYINT(1)          NOT NULL DEFAULT 1,
    criado_em       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuarios_usuario (usuario)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- LOG DE ACESSOS (auditoria de login + base para rate limiting
-- contra força bruta no index.php)
-- ------------------------------------------------------------
CREATE TABLE log_acessos (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id          INT UNSIGNED NULL,
    usuario_tentativa   VARCHAR(60)  NOT NULL,
    sucesso             TINYINT(1)   NOT NULL,
    ip                  VARCHAR(45)  NOT NULL,
    criado_em           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE SET NULL,
    KEY idx_log_usuario_tentativa_data (usuario_tentativa, criado_em)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- CATEGORIAS (antes era um ENUM/texto solto dentro de produtos)
-- ------------------------------------------------------------
CREATE TABLE categorias (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome    VARCHAR(60) NOT NULL,
    UNIQUE KEY uq_categorias_nome (nome)
) ENGINE=InnoDB;

INSERT INTO categorias (nome) VALUES ('Bruto'), ('Ferramentas'), ('Acabamento');

-- ------------------------------------------------------------
-- PRODUTOS
-- Preço em DECIMAL (nunca FLOAT/DOUBLE para dinheiro).
-- valor_investido é CALCULADO (coluna gerada), nunca fica
-- dessincronizado do preço/quantidade real.
-- ------------------------------------------------------------
CREATE TABLE produtos (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome                VARCHAR(150)    NOT NULL,
    categoria_id        INT UNSIGNED    NOT NULL,
    quantidade          INT UNSIGNED    NOT NULL DEFAULT 0,
    preco               DECIMAL(10,2)   NOT NULL,
    valor_investido     DECIMAL(12,2)   AS (quantidade * preco) STORED,
    imagem_url          VARCHAR(500)    NULL,
    criado_em           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_produtos_categoria FOREIGN KEY (categoria_id)
        REFERENCES categorias(id) ON DELETE RESTRICT,
    KEY idx_produtos_nome (nome)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- MOVIMENTAÇÕES DE ESTOQUE
-- Toda alteração de quantidade (+/-/remoção) vira um registro
-- aqui: dá auditoria completa e histórico por produto/usuário.
-- ------------------------------------------------------------
CREATE TABLE movimentacoes_estoque (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    produto_id          INT UNSIGNED    NOT NULL,
    usuario_id          INT UNSIGNED    NULL,
    tipo                ENUM('entrada','saida','ajuste','remocao') NOT NULL,
    quantidade_anterior INT             NOT NULL,
    quantidade_nova     INT             NOT NULL,
    observacao          VARCHAR(255)    NULL,
    criado_em           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mov_produto FOREIGN KEY (produto_id)
        REFERENCES produtos(id) ON DELETE CASCADE,
    CONSTRAINT fk_mov_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE SET NULL,
    KEY idx_mov_produto_data (produto_id, criado_em)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- OBRAS (projetos da construtora) - base para a próxima fase
-- de funcionalidades interativas.
-- ------------------------------------------------------------
CREATE TABLE obras (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome                    VARCHAR(150)    NOT NULL,
    cliente                 VARCHAR(150)    NULL,
    endereco                VARCHAR(255)    NULL,
    status                  ENUM('planejada','em_andamento','concluida','cancelada')
                                            NOT NULL DEFAULT 'planejada',
    data_inicio             DATE            NULL,
    data_prevista_termino   DATE            NULL,
    responsavel_id          INT UNSIGNED    NULL,
    criado_em               DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_obras_responsavel FOREIGN KEY (responsavel_id)
        REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- MATERIAIS RESERVADOS/USADOS POR OBRA (N:N obras <-> produtos)
-- ------------------------------------------------------------
CREATE TABLE obras_materiais (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    obra_id                 INT UNSIGNED    NOT NULL,
    produto_id              INT UNSIGNED    NOT NULL,
    quantidade_reservada    INT UNSIGNED    NOT NULL DEFAULT 0,
    quantidade_utilizada    INT UNSIGNED    NOT NULL DEFAULT 0,
    criado_em               DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_om_obra FOREIGN KEY (obra_id)
        REFERENCES obras(id) ON DELETE CASCADE,
    CONSTRAINT fk_om_produto FOREIGN KEY (produto_id)
        REFERENCES produtos(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_obra_produto (obra_id, produto_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- USUÁRIO ADMIN INICIAL
-- usuário: Vicente | senha: vicente777
-- (troque a senha em produção — este hash é só para dev local)
-- ------------------------------------------------------------
INSERT INTO usuarios (nome, usuario, senha_hash, nivel_acesso) VALUES
('Vicente', 'Vicente', '$2y$10$EJ0eueGe50seuTyPdN4RhO/lTWbbsufn//0Xe.Ihf.ueJeCJXI76O', 'admin');
