-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: extensions/MediaUploader/sql/tables.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE mu_campaign (
  campaign_page_id INT NOT NULL,
  campaign_enabled INT DEFAULT 0 NOT NULL,
  campaign_validity INT NOT NULL,
  campaign_content TEXT DEFAULT NULL,
  PRIMARY KEY(campaign_page_id)
);

CREATE UNIQUE INDEX mu_campaign_page ON mu_campaign (campaign_page_id);

CREATE INDEX mu_campaign_enabled ON mu_campaign (
  campaign_enabled, campaign_page_id
);
