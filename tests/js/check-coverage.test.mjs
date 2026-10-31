import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import { MINIMUM, checkSummary, coveragePasses, main } from './check-coverage.mjs';

const summary = (covered, total) => ({ total: { lines: { covered, total, pct: (covered / total) * 100 } } });

describe('coveragePasses', () => {
  it('keeps the minimum at 80 %', () => {
    expect(MINIMUM).toBe(80);
  });

  it.each([
    [80, 100, false],
    [4, 5, false],
    [800000, 1000000, false],
    [800001, 1000000, true],
    [799999, 1000000, false],
    [81, 100, true],
    [10, 10, true],
    [0, 0, false],
    [11, 10, false],
    [-1, 10, false],
    [8.5, 10, false],
  ])('%s of %s lines passes: %s', (covered, total, expected) => {
    expect(coveragePasses(covered, total)).toBe(expected);
  });
});

describe('checkSummary', () => {
  it('fails at exactly 80 %', () => {
    expect(checkSummary(summary(8, 10))).toMatchObject({ ok: false, message: expect.stringMatching(/^FAIL/) });
  });

  it('passes above 80 %', () => {
    expect(checkSummary(summary(9, 10))).toMatchObject({ ok: true, message: expect.stringMatching(/^PASS/) });
  });

  it('fails without line totals', () => {
    expect(checkSummary({}).ok).toBe(false);
    expect(checkSummary(null).ok).toBe(false);
  });
});

describe('main', () => {
  const write = (content) => {
    const file = join(mkdtempSync(join(tmpdir(), 'swnp-coverage-')), 'summary.json');
    writeFileSync(file, content);
    return file;
  };

  it('requires a summary path', () => {
    expect(main(['node', 'check']).ok).toBe(false);
  });

  it('fails for missing or invalid files', () => {
    expect(main(['node', 'check', join(tmpdir(), 'swnp-missing', 'summary.json')]).ok).toBe(false);
    expect(main(['node', 'check', write('{')]).ok).toBe(false);
  });

  it('reads the summary file', () => {
    expect(main(['node', 'check', write(JSON.stringify(summary(80, 100)))]).ok).toBe(false);
    expect(main(['node', 'check', write(JSON.stringify(summary(81, 100)))]).ok).toBe(true);
  });
});
