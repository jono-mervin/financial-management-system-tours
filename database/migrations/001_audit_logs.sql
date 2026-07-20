-- Run this on an existing tourflow_finance database if you already imported schema.sql
USE tourflow_finance;

CREATE TABLE IF NOT EXISTS audit_logs (
    log_id          BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NULL,
    username        VARCHAR(50) NULL,
    action          VARCHAR(40) NOT NULL,
    module          VARCHAR(60) NOT NULL,
    entity_type     VARCHAR(60) NOT NULL,
    entity_id       INT NULL,
    entity_no       VARCHAR(60) NULL,
    description     VARCHAR(255) NOT NULL,
    old_values      JSON NULL,
    new_values      JSON NULL,
    ip_address      VARCHAR(45) NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_created (created_at DESC),
    INDEX idx_audit_module (module),
    INDEX idx_audit_action (action),
    INDEX idx_audit_entity (entity_type, entity_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;
