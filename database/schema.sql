-- =============================================
-- VaultKeeper — Schema da Base de Dados
-- =============================================

CREATE DATABASE IF NOT EXISTS gestor_credenciais
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE gestor_credenciais;

-- Técnicos (utilizadores)
CREATE TABLE IF NOT EXISTS users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50)  NOT NULL UNIQUE,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    -- Chave pública RSA-4096 em JWK (JSON string)
    public_key      LONGTEXT     NOT NULL,
    -- Chave privada encriptada com AES-256-GCM derivado da password via PBKDF2
    encrypted_private_key  LONGTEXT NOT NULL,
    private_key_salt       VARCHAR(128) NOT NULL,  -- base64
    private_key_iv         VARCHAR(64)  NOT NULL,  -- base64
    -- Chave privada encriptada com código de recuperação
    recovery_encrypted_private_key LONGTEXT,
    recovery_key_salt              VARCHAR(128),
    recovery_key_iv                VARCHAR(64),
    -- Reset de password
    avatar_color    VARCHAR(7)   DEFAULT '#4F8EF7',
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username)
);

-- Empresas
CREATE TABLE IF NOT EXISTS companies (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    nif         VARCHAR(9)   NOT NULL UNIQUE,
    description TEXT,
    owner_id    INT          NOT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_nif (nif),
    INDEX idx_owner (owner_id)
);

-- Credenciais (dados encriptados com AES-256-GCM)
CREATE TABLE IF NOT EXISTS credentials (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    company_id      INT          NOT NULL,
    label           VARCHAR(255) NOT NULL,       -- Nome visível (ex: "FTP Servidor")
    encrypted_data  LONGTEXT     NOT NULL,       -- JSON encriptado: {username, password, url, notes}
    iv              VARCHAR(64)  NOT NULL,       -- AES-GCM IV em base64
    added_by        INT          NOT NULL,
    is_private      TINYINT(1)   DEFAULT 0,      -- 1 = adicionada por técnico externo (só ele vê)
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by)   REFERENCES users(id),
    INDEX idx_company (company_id),
    INDEX idx_added_by (added_by)
);

-- Chaves AES por utilizador (controlo de acesso E2EE)
CREATE TABLE IF NOT EXISTS credential_keys (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    credential_id     INT       NOT NULL,
    user_id           INT       NOT NULL,
    -- AES key encriptada com RSA pública do user
    encrypted_aes_key LONGTEXT  NOT NULL,
    granted_by        INT       NOT NULL,
    granted_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (credential_id) REFERENCES credentials(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)       REFERENCES users(id),
    FOREIGN KEY (granted_by)    REFERENCES users(id),
    UNIQUE KEY unique_cred_user (credential_id, user_id),
    INDEX idx_user_creds (user_id)
);

-- Pedidos de acesso
CREATE TABLE IF NOT EXISTS access_requests (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    type          ENUM('view_credential', 'add_to_company') NOT NULL,
    credential_id INT,
    company_id    INT,
    requester_id  INT          NOT NULL,
    owner_id      INT          NOT NULL,
    status        ENUM('pending','approved','denied') DEFAULT 'pending',
    message       TEXT,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    resolved_at   DATETIME,
    FOREIGN KEY (credential_id) REFERENCES credentials(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id)    REFERENCES companies(id)   ON DELETE CASCADE,
    FOREIGN KEY (requester_id)  REFERENCES users(id),
    FOREIGN KEY (owner_id)      REFERENCES users(id),
    INDEX idx_owner_pending (owner_id, status),
    INDEX idx_requester (requester_id)
);

-- Notificações
CREATE TABLE IF NOT EXISTS notifications (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT          NOT NULL,
    type         VARCHAR(50)  NOT NULL,
    title        VARCHAR(255) NOT NULL,
    body         TEXT,
    related_id   INT,          -- ID do access_request relacionado
    read_at      DATETIME,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, read_at)
);

-- Tokens de reset de password
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    token      VARCHAR(128) NOT NULL UNIQUE,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token)
);
