-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: extensions/ExternalData/sql/ExternalData.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/ed_url_cache (
  id INT UNSIGNED AUTO_INCREMENT NOT NULL,
  url VARBINARY(255) NOT NULL,
  req_time INT NOT NULL,
  result LONGTEXT NOT NULL,
  UNIQUE INDEX id (id),
  INDEX url (url)
) /*$wgDBTableOptions*/;
