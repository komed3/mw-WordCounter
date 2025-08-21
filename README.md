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

**Run the database update** to create the required tables:

```sh
php maintenance/run.php update.php
```

**(Optional)** Adjust configuration variables in `LocalSettings.php` as needed (see below).
