# AGENTS.md

## Quick Start

**Type:** MediaWiki extension (PHP) with JavaScript/JSON/SQL components

**Key commands:**
- `composer test` - run PHP linter, PHPCS, and minus-x
- `composer fix` - auto-fix code style issues
- `composer phan` - static analysis
- `npm test` or `grunt` - ESLint + i18n banana checker

**Test commands:**
- Unit: `phpunit tests/phpunit/unit/`
- Integration: `phpunit tests/phpunit/integration/`

**Update schema:** `php maintenance/update.php` (creates `ed_url_cache` table)

## Architecture

- **Entry points:** `includes/EDParserFunctions.php`, `includes/Hooks.php`, `includes/ScribuntoHooks.php`
- **Core pattern:** Connectors (`EDConnector*`) fetch data, parsers (`EDParser*`) transform it
- **Configuration:** `$wgExternalDataSources` array with per-source and global (`'*'`) settings
- **Caching:** Trait `EDConnectorCached` manages `ed_url_cache` table with configurable expiration

## Connector Selection

Connectors are auto-selected based on parameter presence:

**HTTP-based:** `url`, `host`, `request`, `requestData`
**File-based:** `file`, `path`, `file name`, `directory`
**DB-based:** `db`, `server`, `type`, `prepared`, `driver`
**Other:** `program`, `command`, `domain` (LDAP), `text` (inline)

See `extension.json:Connectors` and `IntegratedConnectors` for full rules.

## Configuration Gotchas

- Cache table: `ed_url_cache` (config var `$wgExternalDataSources['CacheTable']` is obsolete)
- Default cache: 1 hour (`min cache seconds`), set per-source or globally
- String replacements hide sensitive data (API keys) in URLs
- Encodings fallback list: `ASCII`, `UTF-8`, `Windows-1251`, `Windows-1252`, `Windows-1254`, `KOI8-R`, `ISO-8859-1`
- Enable deprecated getters with `$wgExternalDataSources['AllowGetters']` (default true)

## Presets

Load presets via `$wgExternalDataSources`:

- `test` - Dockerized databases (MSSQL, PostgreSQL, MongoDB, LDAP, file sources)
- `reference` - system programs (man, whatis, composer, youtube-dl, etc.)
- `media` - diagram tools (GraphViz, Mermaid, PlantUML, Kroki, etc.)
- `math` - CAS and math tools (Maxima, Asymptote, Octave, MathJax, etc.)

All presets documented in `includes/presets/README.md`.

## i18n

- All messages in `i18n/` directory
- Run `grunt banana` to validate i18n files
- Language files use ISO codes (e.g., `en.json`, `zh-hans.json`)

## Code Style

- PHP: MediaWiki sniff rules with exceptions for assignment-in-condition and `RedundantVarName`
- JS: Wikimedia ESLint config (client + MediaWiki)
- All PHP classes auto-loaded via `extension.json` AutoloadClasses

## Maintenance

- Cache cleanup handled by `EDReparseJob`
- Database patches in `maintenance/archives/$dbType/`
- Schema updates via `LoadExtensionSchemaUpdates` hook

## External Integration

- **Gerrit:** `mediawiki/extensions/ExternalData` on `gerrit.wikimedia.org`
- **Track:** `1` (git review tracking enabled)
- **Default rebase:** off
