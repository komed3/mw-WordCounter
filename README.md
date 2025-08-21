# WordCounter Extension for MediaWiki

**WordCounter** is a comprehensive MediaWiki extension that automatically counts and tracks the number of words in wiki pages. It provides parser functions for displaying word counts, a special page for listing articles by word count, an API module for programmatic access, maintenance scripts for batch operations, and a robust job system for background processing.

The extension is highly configurable, supports multiple languages, and is designed for performance and extensibility.

**Key Features:**

- **Automatic word counting** on page save and via maintenance scripts.
- **Parser functions** to display word counts in wiki pages.
- **Special page** to list pages with the most words.
- **API module** for retrieving word counts and statistics.
- **Maintenance scripts** for batch counting and database cleanup.
- **Job scheduler integration** for periodic updates and purges.
- **Configurable options** for namespaces, caching, and counting behavior.
- **Support** for multiple languages and scripts.
- **Extensible hooks** for custom word counting logic.

## Installation

**Download** the extension and place it in your `extensions/WordCounter` directory.

**Enable** the extension in your `LocalSettings.php`:

```php
wfLoadExtension( 'WordCounter' );
```

**Run the database update** to create the required table `wordcounter`:

```sh
php maintenance/run.php update.php
```

**(Optional)** Adjust configuration variables in `LocalSettings.php` as needed (see below).

## Parser Functions

## API Usage

## Configuration Variables

All configuration variables can be set in `LocalSettings.php` using `$wgWordCounter...`.

- `$wgWordCounterSupportedNamespaces` – List of namespaces for whose pages words are to be counted.  
  Default: `[ 0 ]`  
  Example: `$wgWordCounterSupportedNamespaces = [ 0, 4 ];`
- `$wgWordCounterCountNumbers` – Whether to count numbers (digits) as words.  
  Default: `false`  
  Example: `$wgWordCounterCountNumbers = true;`
- `$wgWordCounterCustomPattern` – Regular expression pattern to match words; if null, the default pattern is used.  
  Default: `null`  
  Example: `$wgWordCounterCustomPattern = '/\w+/u';`
- `$wgWordCounterCountOnPageSave` – Whether to count words on page save.  
  Default: `true`  
  Example: `$wgWordCounterCountOnPageSave = false;`
- `$wgWordCounterCacheService` – Cache service to use; options are 'local', 'wan', 'micro' or 'main'.  
  Default: `'main'`  
  Example: `$wgWordCounterCacheService = 'wan';`
- `$wgWordCounterCacheSpecialPages` – Whether to cache the query results of special pages.  
  Default: `true`  
  Example: `$wgWordCounterCacheSpecialPages = false;`
- `$wgWordCounterSpecialPageCacheTTL` – Cache expiry time for special pages in seconds.  
  Default: `3600`  
  Example: `$wgWordCounterSpecialPageCacheTTL = 600;`
- `$wgWordCounterSpecialPageMaxResults` – Maximum number of results to return in special pages.  
  Default: `5000`  
  Example: `$wgWordCounterSpecialPageMaxResults = 1000;`
- `$wgWordCounterCountWordsJobLimit` – Number of pages to process per CountWords job (0 to disable jobs).  
  Default: `50`  
  Example: `$wgWordCounterCountWordsJobLimit = 100;`
- `$wgWordCounterCountWordsJobInterval` – Minimum interval in seconds between jobs.  
  Default: `3600`  
  Example: `$wgWordCounterCountWordsJobInterval = 7200;`
- `$wgWordCounterPurgeOrphanedJobLimit` – Number of entries to process per PurgeOrphaned job (0 to disable jobs).  
  Default: `1000`  
  Example: `$wgWordCounterPurgeOrphanedJobLimit = 500;`
- `$wgWordCounterPurgeOrphanedJobInterval` – Minimum interval in seconds between jobs.  
  Default: `86400`  
  Example: `$wgWordCounterPurgeOrphanedJobInterval = 43200;`

**Tips for your Wiki:**

- For large wikis, increase job and batch limits for faster processing.
- Use a custom regex for language-specific word counting if needed.
- In wikis with high edit rates, word counting on page save should be disabled.
- Take advantage of the best cache service available.  
  Visit https://www.mediawiki.org/wiki/Object_cache for more information.

## Maintenance Scripts

WordCounter provides maintenance scripts for batch operations.

**`countWords.php`** – Counts words in articles and updates the database.

```sh
php maintenance/run.php WordCounter:countWords [--dry-run] [--force] [--limit=N] [--pages=Page1|Page2]
```

- `--dry-run` – Show what would be done, but do not change the database.
- `--force` – Recount all articles, even if already counted.
- `--limit=N` – Maximum number of pages to process in this run.
- `--pages=...` – Only process the given pages (separated by `|`).

**`purgeOrphaned.php`** – Removes orphaned or invalid entries from the `wordcounter` table.

```sh
php maintenance/run.php WordCounter:purgeOrphaned [--dry-run] [--limit=N]
```

- `--dry-run` – Show what would be deleted, but do not actually delete.
- `--limit=N` – Maximum number of entries to process per batch.

## Job System

WordCounter integrates with MediaWiki's job queue for background processing:

- **CountWordsJob** – Periodically counts words for new or outdated pages.
- **PurgeOrphanedJob** – Periodically removes orphaned entries from the database.

Jobs are scheduled automatically based on the configured intervals and limits. You can also trigger jobs manually via maintenance scripts.

## Hooks and Database Changes

WordCounter registers and uses several MediaWiki hooks:

- `PageSaveComplete` – Updates word count on page save.
- `PageDelete` – Removes word count when a page is deleted.
- `ParserFirstCallInit` – Registers parser functions.
- `InfoAction` – Adds word count to the page info.
- `SpecialStatsAddExtra` – Adds word count stats to `Special:Statistics`.
- `LoadExtensionSchemaUpdates` – Handles database schema updates.

The extension creates a `wordcounter` table with the following fields:

- `wc_page_id` (int, primary key) – The page ID.
- `wc_wordcount` (int) – The current word count.
- `wc_timestamp` (binary) – Last update timestamp.

Indexes are created for performance. See `sql/wordcounter.sql` for details.

## Extending Word Counting via Hooks

You can extend or modify the word counting logic using the following hooks:

- `WordCounterBeforeCount` – Modify the plain text before counting.
- `WordCounterAfterCount` – Modify or override the word count after counting.

Minimal Example: Custom Hook in `LocalSettings.php`

```php
$wgHooks[ 'WordCounterBeforeCount' ][] = function (
  &$plainText, $revisionRecord, $content, $parserOutput
) {

  // Example: Remove all numbers before counting
  $plainText = preg_replace( '/\d+/', '', $plainText );
  return true;

};

$wgHooks[ 'WordCounterAfterCount' ][] = function (
  &$wordCount, $plainText, $pattern, $revisionRecord, $content, $parserOutput
) {

  // Example: Add 10 to every word count (for demonstration)
  $wordCount += 10;
  return true;

};
```

## Support and Contribution

- **Project page:** https://github.com/komed3/mw-WordCounter
- **License:** MIT
- **Author:** Paul Köhler ([komed3](https://komed3.de))

Contributions, bug reports, and feature requests are welcome!
