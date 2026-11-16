<?php
/**
 * WordPress test suite configuration. Uses ONLY the private throw-away MariaDB instance (bin/test-db.sh),
 * never the development site's database.
 */

define( 'ABSPATH', __DIR__ . '/vendor/johnpbloch/wordpress-core/' );
define( 'WP_DEFAULT_THEME', 'default' );
define( 'WP_DEBUG', true );

define( 'DB_NAME', getenv( 'SWNEWSPAPER2_WP_TEST_DB_NAME' ) ?: 'swnewspaper2_wp_tests' );
define( 'DB_USER', getenv( 'SWNEWSPAPER2_TEST_DB_USER' ) ?: 'swnewspaper2' );
define( 'DB_PASSWORD', false !== getenv( 'SWNEWSPAPER2_TEST_DB_PASSWORD' ) ? getenv( 'SWNEWSPAPER2_TEST_DB_PASSWORD' ) : 'swnewspaper2' );
define( 'DB_HOST', ( getenv( 'SWNEWSPAPER2_TEST_DB_HOST' ) ?: '127.0.0.1' ) . ':' . ( getenv( 'SWNEWSPAPER2_TEST_DB_PORT' ) ?: '33112' ) );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'sw-newspaper2 Tests' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );

$table_prefix = 'wptests_';
