# Tests

Line coverage of productive code must be **greater than 80 %** for PHP and JavaScript
each; exactly 80 % fails (see `AGENTS.md`).

Standalone regressions (no database):

```sh
php tests/coverage-gate.php
php tests/bootstrap.php
php tests/legacy-import.php
composer cs
npm ci && npm test        # Node >= 20, assets/admin.js
```

## WordPress scenarios

`archive-performance.php` and `admin-workflows.php` require WordPress core, the PHP
extensions `mysqli`, `curl` and `imagick`, and a **disposable** MariaDB/MySQL database.
`wordpress.php` installs WordPress under the `swnp_test_` prefix. Never use production
credentials or a production database. Fixtures are deleted in `finally` blocks; the
disposable WordPress tables remain.

```sh
WP_ROOT=/tmp/wordpress WP_DB_NAME=newspaper_test \
  WP_DB_HOST=127.0.0.1:3306 WP_DB_USER=root WP_DB_PASSWORD='' \
  composer test            # = php tests/run.php, all scenarios in separate processes
```

`archive-performance.php` creates 1,000 PDF/cover attachment pairs (no files on disk)
and checks cold-cache frontend rendering at 10/100/1,000 issues and the admin archive.
Query budgets detect per-cover SQL growth without wall-clock timings; asset registration
without a shortcode must not enqueue CSS or query the archive. Archives still render
every selected issue; pagination remains a separate compatibility-sensitive change.

`admin-workflows.php` covers settings, upload validation, the upload service, cover
rendering, folder/dFlip integration (registered stand-ins), admin views, nonces and
capabilities of all handlers, deletion, shortcodes and uninstall. Successful uploads
need real multipart requests (`is_uploaded_file()`), so the scenario starts PHP's
built-in server on a random local port with `upload-endpoint.php` as router (query
flags enable test-only filters). Covers are rendered from PNG payloads, independent
of Ghostscript and the host's ImageMagick PDF policy; a real PDF goes through the
admin handler as well.

CI runs all scenarios against WordPress 6.4 and the current release.

## Coverage

```sh
XDEBUG_MODE=coverage composer test:coverage   # = php tests/run.php --coverage
npm run test:coverage
```

PHP needs Xdebug or PCOV and the database variables above. Each process, and each
request of the upload server, writes its own fragment; the runner merges only
fragments of the current invocation into `coverage/clover.xml`. Unexecuted production
files stay in the denominator. `coverage/report.php` fails through `coverage/gate.php`
unless covered × 100 > 80 × executable lines.

JavaScript uses Vitest (jsdom, V8 coverage over `assets/**/*.js`, reports in
`coverage/js/`). `js/check-coverage.mjs` enforces the strict gate, since Vitest
thresholds accept exactly 80 %. Both gates have boundary tests.

## SonarQube

The Actions workflow analyzes same-repository PRs and default-branch pushes and imports
`coverage/clover.xml` and `coverage/js/lcov.info`. Configure `SONAR_TOKEN` as an Actions
secret and `SONAR_ORGANIZATION` as an Actions variable. Fork PRs run tests without the
scanner token. Project mapping: `sonar-project.properties`. Coverage and scanner output
are development artifacts, never production plugin files.
