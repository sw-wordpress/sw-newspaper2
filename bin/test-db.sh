#!/usr/bin/env bash
# Dedicated disposable database only; refuses existing unrecognized data directories.
set -euo pipefail
DIR="${SWNEWSPAPER2_TEST_DB_DIR:-/tmp/swnewspaper2-test-db}"
PORT="${SWNEWSPAPER2_TEST_DB_PORT:-33112}"
case "${1:-}" in
start)
  if [ -S "$DIR/mysql.sock" ] && mariadb-admin --socket="$DIR/mysql.sock" ping >/dev/null 2>&1; then exit 0; fi
  if [ -e "$DIR" ]; then echo 'Existing inactive database directory: choose a fresh SWNEWSPAPER2_TEST_DB_DIR.' >&2; exit 1; fi
  mkdir -m 700 -p "$DIR/data"
  mariadb-install-db --no-defaults --datadir="$DIR/data" --auth-root-authentication-method=normal >"$DIR/install.log" 2>&1
  nohup mariadbd --no-defaults --datadir="$DIR/data" --socket="$DIR/mysql.sock" --port="$PORT" --bind-address=127.0.0.1 --pid-file="$DIR/mysql.pid" --skip-name-resolve --innodb-flush-log-at-trx-commit=0 >"$DIR/server.log" 2>&1 &
  for _ in {1..60}; do
    if mariadb-admin --socket="$DIR/mysql.sock" ping >/dev/null 2>&1; then break; fi
    sleep 0.5
  done
  mariadb --socket="$DIR/mysql.sock" -uroot -e "CREATE USER 'swnewspaper2'@'127.0.0.1' IDENTIFIED BY 'swnewspaper2'; GRANT ALL ON newspaper_test.* TO 'swnewspaper2'@'127.0.0.1'; GRANT ALL ON swnewspaper2_wp_tests.* TO 'swnewspaper2'@'127.0.0.1'; CREATE DATABASE newspaper_test; CREATE DATABASE swnewspaper2_wp_tests;"
  ;;
stop) mariadb-admin --socket="$DIR/mysql.sock" -uroot shutdown ;;
status) mariadb-admin --socket="$DIR/mysql.sock" ping ;;
*) echo 'Usage: bash bin/test-db.sh start|stop|status' >&2; exit 1 ;;
esac
