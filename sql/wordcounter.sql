-- Word counter table
CREATE TABLE /*_*/wordcounter (
  wc_page_id int unsigned NOT NULL PRIMARY KEY,
  wc_word_count int unsigned NOT NULL DEFAULT 0,
  wc_updated binary( 14 ) NOT NULL
) /*$wgDBTableOptions*/;

-- Index for sorting by word count
CREATE INDEX /*i*/wc_word_count ON /*_*/wordcounter ( wc_word_count );

-- Index for updated timestamp
CREATE INDEX /*i*/wc_updated ON /*_*/wordcounter ( wc_updated );
