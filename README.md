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

## Usage of Parser Functions

## API Usage

## Configuration Variables

## Maintenance Scripts

## Job System Overview

## Hooks and Database Changes

WordCounter registers and uses several MediaWiki hooks:

- `PageSaveComplete`: Updates word count on page save.
- `PageDelete`: Removes word count when a page is deleted.
- `ParserFirstCallInit`: Registers parser functions.
- `InfoAction`: Adds word count to the page info.
- `SpecialStatsAddExtra`: Adds word count stats to `Special:Statistics`.
- `LoadExtensionSchemaUpdates`: Handles database schema updates.

The extension creates a `wordcounter` table with the following fields:

- `wc_page_id` (int, primary key): The page ID.
- `wc_wordcount` (int): The current word count.
- `wc_timestamp` (binary): Last update timestamp.

Indexes are created for performance. See `sql/wordcounter.sql` for details.

## Extending Word Counting via Hooks

You can extend or modify the word counting logic using the following hooks:

- `WordCounterBeforeCount`: Modify the plain text before counting.
- `WordCounterAfterCount`: Modify or override the word count after counting.

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
