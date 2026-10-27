# Tests

Standalone regressions:

```sh
php tests/bootstrap.php
php tests/legacy-import.php
composer cs
```

`archive-performance.php` requires WordPress core and a **disposable** MariaDB/MySQL
database. It installs WordPress under the `swnp_test_` prefix and creates 1,000 PDF
and cover attachment pairs. Never use production credentials or a production
database. No PDFs/images are written to disk or sent over the network. All fixture
attachments are deleted in a finally block; the disposable WordPress tables remain.

```sh
WP_ROOT=/tmp/wordpress WP_DB_NAME=newspaper_test \
  WP_DB_HOST=127.0.0.1:3306 WP_DB_USER=root WP_DB_PASSWORD='' \
  php tests/archive-performance.php
```

The suite checks cold-cache frontend rendering at 10/100/1,000 issues and the real
admin archive controller/template. Its query budgets detect per-cover SQL growth,
without relying on wall-clock timings. Existing default/positive limits, date order,
links, missing/deleted/shared covers and empty selections are covered as well.
Asset registration without a shortcode must not enqueue frontend CSS or query the
archive. CI runs this against the minimum WordPress 6.4 and current WordPress.

Archives still display every selected issue. This optimization reduces SQL roundtrips;
it does not bound HTML size or memory for an unlimited archive. Pagination remains
a separate compatibility-sensitive change.

## SonarQube coverage

`php tests/run.php` runs all three regression scenarios in isolated processes.
Use `XDEBUG_MODE=coverage php tests/run.php --coverage` with Xdebug (or PCOV) and
the same disposable database variables to collect `coverage/clover.xml`. Each
process must succeed and produce its own coverage fragment; the runner merges
only fragments from the current invocation. Unexecuted production PHP files stay
in the coverage denominator and file paths are normalized for the scanner job.

The Actions workflow analyzes same-repository PRs and default-branch pushes.
Configure `SONAR_TOKEN` as an Actions secret and `SONAR_ORGANIZATION` as an
Actions variable, using organization-level bindings where appropriate. Fork PRs
run tests without access to the scanner token. Project mapping is recorded in
`sonar-project.properties`. Coverage and scanner output are development artifacts,
never production plugin files.
