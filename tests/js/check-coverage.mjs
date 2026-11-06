// Strict JavaScript line-coverage gate: the total must be greater than 80 %; exactly 80 % fails.
// Usage: node tests/js/check-coverage.mjs coverage/js/coverage-summary.json
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

export const MINIMUM = 80;

// Integer arithmetic avoids float rounding at the boundary; no measured lines never pass.
export function coveragePasses(covered, total, minimum = MINIMUM) {
  if (!Number.isInteger(covered) || !Number.isInteger(total) || total <= 0 || covered < 0 || covered > total) {
    return false;
  }
  return covered * 100 > minimum * total;
}

export function checkSummary(summary) {
  const lines = summary && summary.total && summary.total.lines;
  if (!lines) {
    return { ok: false, message: 'FAIL: coverage summary contains no line totals.' };
  }
  const ok = coveragePasses(lines.covered, lines.total);
  const measured = `${lines.covered}/${lines.total} lines (${lines.pct} %)`;
  return {
    ok,
    message: ok
      ? `PASS: JavaScript line coverage ${measured} is greater than ${MINIMUM} %.`
      : `FAIL: JavaScript line coverage ${measured} must be greater than ${MINIMUM} %.`,
  };
}

export function main(argv) {
  const file = argv[2];
  if (!file) {
    return { ok: false, message: 'FAIL: pass the path to coverage-summary.json.' };
  }
  let summary;
  try {
    summary = JSON.parse(readFileSync(file, 'utf8'));
  } catch (error) {
    return { ok: false, message: `FAIL: cannot read ${file}: ${error.message}` };
  }
  return checkSummary(summary);
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
  const result = main(process.argv);
  (result.ok ? console.log : console.error)(result.message);
  process.exitCode = result.ok ? 0 : 1;
}
