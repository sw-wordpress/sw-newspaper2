# SW-Newspaper2

[![Release](https://img.shields.io/github/v/release/sw-wordpress/sw-newspaper2?sort=semver)](https://github.com/sw-wordpress/sw-newspaper2/releases)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=sw-wordpress_sw-newspaper2&metric=coverage)](https://sonarcloud.io/summary/overall?id=sw-wordpress_sw-newspaper2)
[![SonarQube Issues](https://sonarcloud.io/api/project_badges/measure?project=sw-wordpress_sw-newspaper2&metric=vulnerabilities)](https://sonarcloud.io/project/issues?id=sw-wordpress_sw-newspaper2&impactSoftwareQualities=SECURITY&resolved=false)

Publishes [PDF](https://en.wikipedia.org/wiki/PDF) newspapers through the WordPress Media Library.
Shows the current issue, a download button and an archive of all issues on your pages via
[shortcodes](https://wordpress.org/documentation/article/shortcode-block/).

## Features

- **Automatic cover image** — The first page of each PDF becomes its cover image.
- **Current issue by date** — The issue with the newest issue date is always the current one, whatever the upload order.
- **Archive grid** — All issues with cover, title and date in a responsive grid.
- **Flipbook view** — Optionally shows the current issue as a [dFlip](https://wordpress.org/plugins/3d-flipbook-dflip-lite/) flipbook.
- **Plain Media Library files** — PDFs and covers are normal media files; no external services.

## Languages

English, German.

## Usage

1. Open **Newspaper → Upload**, enter a **Title** and an **Issue date**, choose a **PDF file** and click **Publish**.
2. Optional: open **Newspaper → Settings**, choose a **dFlip book** and/or **Media folder** and click **Save**.
3. Edit a page and add a shortcode block with one or more of these shortcodes:

   ```text
   [swnewspaper2_current width="300"]
   [swnewspaper2_download label="Download current issue"]
   [swnewspaper2_archive columns="4" limit="12"]
   ```

4. Manage issues under **Newspaper → Archive** (**Open PDF**, **Delete**).

The shortcodes stay empty until at least one issue has been published. All parameters and
details: [docs/user-guide.md](docs/user-guide.md) (German).

**Upgrading from sw-newspaper:** on activation, issues of the predecessor plugin are taken over
automatically as long as sw-newspaper2 has no issues yet; no files are copied, and old shortcodes
must be replaced. Details: [docs/user-guide.md](docs/user-guide.md#automatischer-import-aus-sw-newspaper).

## Configuration

### Requirements

PHP 8.2+ with [Imagick](https://www.php.net/manual/en/book.imagick.php) able to read PDFs (usually
needs [Ghostscript](https://www.ghostscript.com/); otherwise issues show a "PDF" placeholder instead
of a cover), WordPress 6.4+, single site or multisite.

### Settings

**Newspaper → Settings** (administrators only) selects the **dFlip book** and the **Media folder**.
Uploading and the archive need the `upload_files` capability (authors and above by default).

The plugin has no `wp-config.php` constants of its own.

### Integrations

- **[dFlip](https://wordpress.org/plugins/3d-flipbook-dflip-lite/)** — Each new upload becomes the source of the selected book, and `[swnewspaper2_current]` shows it as a flipbook.
- **[WP Media Folder](https://www.joomunited.com/wordpress-products/wp-media-folder)** — New PDFs and covers are sorted into the selected media folder.

Details and pitfalls: [docs/user-guide.md](docs/user-guide.md#optionale-integrationen).

### Deactivation and removal

Deactivating keeps all issues and settings; the shortcodes then appear as plain text. Deleting
the plugin removes only its settings; PDFs and covers stay in the Media Library (on multisite,
settings are removed for the main site only). **Delete** under **Newspaper → Archive** removes
the PDF and its cover permanently, including files shared with an imported sw-newspaper installation.

## Troubleshooting

| Problem | Solution |
|---|---|
| The shortcode appears as text. | Check that the plugin is active, PHP is 8.2+ and the release package is complete. Use a shortcode block, not a code block. |
| Nothing appears. | Publish at least one issue via **Newspaper → Upload**; a PDF in the Media Library alone is not enough. |
| "PDF" appears instead of a cover. | The cover could not be created. Check Imagick, its PDF support, server policies and write permissions. The PDF stays available. |
| The wrong issue is current. | Check the issue dates: the newest issue date wins, not the latest upload. |
| dFlip shows an older or different issue. | The book source changes on every plugin upload, even for older issues. Check the source in dFlip. |
| Clicking the button does not download the file. | The button is a normal PDF link; browser and server decide whether to show or download it. |
| The upload fails. | Use PDF files only, check title and date, the WordPress/PHP upload limits and folder permissions. |

The shortcodes have no access control; PDFs are public via their media URL.

## Built with AI

This plugin was implemented with AI assistance. Why it exists and how AI is used:
[sw-wordpress manifest](https://github.com/sw-wordpress/.github/blob/main/profile/README.md).

AI agents must follow the project rules before changing code:

- Rules and mandatory coverage: [AGENTS.md](AGENTS.md)
- Tests, coverage and SonarQube: [tests/README.md](tests/README.md)
- User guide and release build: [docs/](docs/)
- README structure: [README-STANDARD.md](https://github.com/sw-wordpress/sw-securelogin/blob/master/docs/README-STANDARD.md)

## Development

Requirements: PHP 8.2+, Composer, Xdebug or PCOV for coverage, a disposable MariaDB/MySQL
database with a WordPress copy for the integration scenario, and Docker for the release build.
JavaScript and CSS are plain files; there is no build step.

```sh
composer install
composer validate --strict && composer cs                # Composer config, WordPress coding standards

php tests/bootstrap.php && php tests/legacy-import.php   # standalone regressions
WP_ROOT=/tmp/wordpress WP_DB_HOST=127.0.0.1:3306 \
  php tests/run.php --coverage                           # all scenarios, writes coverage/clover.xml
```

Every change needs tests; line coverage must stay above 80 %. Never point the tests at a
production database; setup: [tests/README.md](tests/README.md). GitHub Actions run the tests on
every push (WordPress 6.4 and latest, PHP 8.2 and 8.4); SonarQube analyzes the default branch and pull requests.

Pushing a version tag (e.g. `2.0.1`) builds the release ZIP with dependencies isolated by
[Mozart](https://github.com/coenjacobs/mozart); local build: [docs/development.md](docs/development.md).

## License

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html) — see [LICENSE](LICENSE). Provided by Stefan Warnat.

No third-party components are bundled.
