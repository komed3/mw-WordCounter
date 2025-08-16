# WordCounter Extension

A comprehensive MediaWiki extension that counts and tracks words in articles.

## Features

- Automatic word counting when pages are saved
- Word count display on page info
- Magic words: `{{ARTICLEWORDS}}` and `{{TOTALWORDS}}`
- Special page listing articles by word count
- `API` integration for word count data
- Total word count on `Special:Statistics`
- Maintenance script for batch processing

## Installation

1. Download and place the files in `extensions/WordCounter/`
2. Add to `LocalSettings.php`:

```php
wfLoadExtension( 'WordCounter' );
```
