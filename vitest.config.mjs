import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    environment: 'jsdom',
    include: ['tests/js/**/*.test.mjs'],
    coverage: {
      provider: 'v8',
      // Every productive script counts, even if no test imports it.
      include: ['assets/**/*.js'],
      reporter: ['text', 'json-summary', 'lcov'],
      reportsDirectory: 'coverage/js',
      // The strict "> 80 %" gate lives in tests/js/check-coverage.mjs,
      // because Vitest thresholds accept exactly 80 %.
    },
  },
});
