-- ChurchCRM 7.8.0 — Drop the predefined-query tables.
-- People queries now live at /people/reports. The two pledge queries that
-- remained are retired with the tables. Idempotent.

DROP TABLE IF EXISTS `queryparameteroptions_qpo`;
DROP TABLE IF EXISTS `queryparameters_qrp`;
DROP TABLE IF EXISTS `query_qry`;

DELETE FROM `config_cfg` WHERE `cfg_name` = 'aFinanceQueries';
