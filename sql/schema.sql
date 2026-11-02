-- Schéma de la base MTS Photographie (MySQL 5.7+ / MariaDB 10.3+)

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login    DATETIME     NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug     VARCHAR(60)  NOT NULL UNIQUE,
    name     VARCHAR(60)  NOT NULL,
    position INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS photos (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file         VARCHAR(255) NOT NULL,
    thumb        VARCHAR(255) NOT NULL,
    width        INT UNSIGNED NOT NULL,
    height       INT UNSIGNED NOT NULL,
    title        VARCHAR(120) NOT NULL DEFAULT '',
    category_id  INT UNSIGNED NULL,
    position     INT          NOT NULL DEFAULT 0,
    visible      TINYINT(1)   NOT NULL DEFAULT 1,
    in_slideshow TINYINT(1)   NOT NULL DEFAULT 0,
    in_band      TINYINT(1)   NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_position (position),
    CONSTRAINT fk_photos_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    icon      VARCHAR(40)  NOT NULL DEFAULT 'camera',
    title     VARCHAR(80)  NOT NULL,
    text      TEXT         NOT NULL,
    link_text VARCHAR(60)  NOT NULL DEFAULT 'SUR DEVIS →',
    position  INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    name  VARCHAR(60) NOT NULL PRIMARY KEY,
    value TEXT        NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Statistiques : aucune adresse IP n'est stockée, seulement une empreinte anonyme qui change chaque jour
CREATE TABLE IF NOT EXISTS page_views (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page       VARCHAR(30)  NOT NULL,
    referrer   VARCHAR(100) NOT NULL DEFAULT '',
    device     VARCHAR(10)  NOT NULL DEFAULT 'ordinateur',
    visitor    CHAR(16)     NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS photo_views (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    photo_id   INT UNSIGNED NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_photo_created (photo_id, created_at),
    CONSTRAINT fk_views_photo FOREIGN KEY (photo_id) REFERENCES photos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_hash      CHAR(64) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ip_time (ip_hash, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
