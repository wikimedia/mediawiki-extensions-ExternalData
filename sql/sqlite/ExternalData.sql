-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: extensions/ExternalData/sql/ExternalData.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/ed_url_cache (
  id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  url BLOB NOT NULL, req_time INTEGER NOT NULL,
  result CLOB NOT NULL
);

CREATE UNIQUE INDEX id ON /*_*/ed_url_cache (id);

CREATE INDEX url ON /*_*/ed_url_cache (url);
