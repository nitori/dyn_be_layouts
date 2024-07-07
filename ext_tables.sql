CREATE TABLE pages (
    tx_dynbelayouts_setup mediumtext,
);

CREATE TABLE tx_dynbelayouts_domain_model_layout (
    page int(11) DEFAULT '0' NOT NULL,
    template varchar(255) DEFAULT '' NOT NULL,
);
