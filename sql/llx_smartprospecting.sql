-- ============================================================
-- Tables SQL pour le module SmartProspecting
-- Dolibarr 17+
-- ============================================================

-- Table principale des sessions de recherche
CREATE TABLE IF NOT EXISTS `llx_smartprospecting_search` (
    `rowid`             int(11)         NOT NULL AUTO_INCREMENT,
    `ref`               varchar(50)     NOT NULL,
    `fk_user`           int(11)         NOT NULL,
    `date_creation`     datetime        NOT NULL,
    `date_last_update`  datetime,
    `status`            smallint(6)     NOT NULL DEFAULT 0 COMMENT '0=brouillon,1=en cours,2=terminé,3=erreur',
    `source`            varchar(50)     NOT NULL COMMENT 'insee,pappers,google_places,hunter',
    `search_query`      text            COMMENT 'Paramètres JSON de la recherche',
    `nb_results`        int(11)         DEFAULT 0,
    `nb_imported`       int(11)         DEFAULT 0,
    `nb_duplicates`     int(11)         DEFAULT 0,
    `nb_errors`         int(11)         DEFAULT 0,
    `entity`            int(11)         NOT NULL DEFAULT 1,
    `import_key`        varchar(14),
    PRIMARY KEY (`rowid`),
    UNIQUE KEY `idx_smartprospecting_search_ref` (`ref`),
    KEY `idx_smartprospecting_search_fk_user` (`fk_user`),
    KEY `idx_smartprospecting_search_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table des prospects trouvés (avant import Dolibarr)
CREATE TABLE IF NOT EXISTS `llx_smartprospecting_prospect` (
    `rowid`             int(11)         NOT NULL AUTO_INCREMENT,
    `fk_search`         int(11)         NOT NULL,
    `siret`             varchar(14),
    `siren`             varchar(9),
    `nom`               varchar(255)    NOT NULL,
    `forme_juridique`   varchar(100),
    `code_naf`          varchar(10),
    `libelle_naf`       varchar(255),
    `adresse`           varchar(500),
    `cp`                varchar(10),
    `ville`             varchar(100),
    `departement`       varchar(5),
    `pays`              varchar(50)     DEFAULT 'FR',
    `telephone`         varchar(30),
    `email`             varchar(255),
    `site_web`          varchar(255),
    `dirigeant_nom`     varchar(255),
    `dirigeant_prenom`  varchar(255),
    `dirigeant_email`   varchar(255),
    `effectif`          varchar(50),
    `chiffre_affaires`  double,
    `date_creation_soc` date,
    `latitude`          decimal(10,7),
    `longitude`         decimal(10,7),
    `score`             int(3)          DEFAULT 50 COMMENT 'Score de pertinence 0-100',
    `status`            smallint(6)     DEFAULT 0 COMMENT '0=trouvé,1=importé,2=doublon,3=erreur,4=exclu',
    `fk_societe`        int(11)         COMMENT 'ID Dolibarr si importé',
    `source_data`       longtext        COMMENT 'JSON brut de la source',
    `date_creation`     datetime        NOT NULL,
    `date_import`       datetime,
    `entity`            int(11)         NOT NULL DEFAULT 1,
    PRIMARY KEY (`rowid`),
    KEY `idx_sp_prospect_fk_search` (`fk_search`),
    KEY `idx_sp_prospect_siret` (`siret`),
    KEY `idx_sp_prospect_status` (`status`),
    KEY `idx_sp_prospect_score` (`score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table des séquences de relance
CREATE TABLE IF NOT EXISTS `llx_smartprospecting_sequence` (
    `rowid`             int(11)         NOT NULL AUTO_INCREMENT,
    `ref`               varchar(50)     NOT NULL,
    `label`             varchar(255)    NOT NULL,
    `description`       text,
    `fk_user_creat`     int(11)         NOT NULL,
    `status`            smallint(6)     DEFAULT 1 COMMENT '0=inactif,1=actif',
    `nb_etapes`         int(11)         DEFAULT 0,
    `date_creation`     datetime        NOT NULL,
    `date_last_update`  datetime,
    `entity`            int(11)         NOT NULL DEFAULT 1,
    PRIMARY KEY (`rowid`),
    UNIQUE KEY `idx_sp_sequence_ref` (`ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table des étapes de séquences
CREATE TABLE IF NOT EXISTS `llx_smartprospecting_sequence_step` (
    `rowid`             int(11)         NOT NULL AUTO_INCREMENT,
    `fk_sequence`       int(11)         NOT NULL,
    `position`          int(11)         NOT NULL DEFAULT 1,
    `type`              varchar(30)     NOT NULL COMMENT 'email,task,call,sms',
    `delai_jours`       int(11)         DEFAULT 0 COMMENT 'Délai en jours depuis étape précédente',
    `label`             varchar(255),
    `sujet_email`       varchar(500),
    `corps_email`       longtext,
    `fk_modele_email`   int(11),
    `date_creation`     datetime        NOT NULL,
    `entity`            int(11)         NOT NULL DEFAULT 1,
    PRIMARY KEY (`rowid`),
    KEY `idx_sp_step_fk_sequence` (`fk_sequence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table de suivi des relances par prospect
CREATE TABLE IF NOT EXISTS `llx_smartprospecting_relance` (
    `rowid`             int(11)         NOT NULL AUTO_INCREMENT,
    `fk_societe`        int(11)         NOT NULL,
    `fk_sequence`       int(11)         NOT NULL,
    `fk_step`           int(11),
    `fk_user`           int(11)         NOT NULL,
    `status`            smallint(6)     DEFAULT 0 COMMENT '0=planifié,1=envoyé,2=ouvert,3=répondu,4=stop',
    `date_planifiee`    datetime,
    `date_execution`    datetime,
    `resultat`          text,
    `date_creation`     datetime        NOT NULL,
    `entity`            int(11)         NOT NULL DEFAULT 1,
    PRIMARY KEY (`rowid`),
    KEY `idx_sp_relance_fk_societe` (`fk_societe`),
    KEY `idx_sp_relance_fk_sequence` (`fk_sequence`),
    KEY `idx_sp_relance_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table des logs d'API (pour monitoring et debug)
CREATE TABLE IF NOT EXISTS `llx_smartprospecting_api_log` (
    `rowid`             int(11)         NOT NULL AUTO_INCREMENT,
    `fk_search`         int(11),
    `source`            varchar(50)     NOT NULL,
    `endpoint`          varchar(500),
    `status_code`       int(5),
    `nb_results`        int(11),
    `response_time_ms`  int(11),
    `error_message`     text,
    `date_creation`     datetime        NOT NULL,
    `entity`            int(11)         NOT NULL DEFAULT 1,
    PRIMARY KEY (`rowid`),
    KEY `idx_sp_apilog_fk_search` (`fk_search`),
    KEY `idx_sp_apilog_source` (`source`),
    KEY `idx_sp_apilog_date` (`date_creation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
