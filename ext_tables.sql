CREATE TABLE pages (
    tx_seograph_schema_type varchar(50) DEFAULT '' NOT NULL,
    tx_seograph_primary_image int(11) unsigned DEFAULT '0' NOT NULL,
    tx_seograph_author varchar(255) DEFAULT '' NOT NULL,
    tx_seograph_exclude tinyint(4) unsigned DEFAULT '0' NOT NULL
);
