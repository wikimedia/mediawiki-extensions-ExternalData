-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: extensions/ExternalData/sql/ExternalData.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE ed_url_cache (
  id SERIAL NOT NULL, url TEXT NOT NULL,
  req_time INT NOT NULL, result TEXT NOT NULL
);

CREATE UNIQUE INDEX id ON ed_url_cache (id);

CREATE INDEX url ON ed_url_cache (url);
