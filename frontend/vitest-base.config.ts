import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    // WHY: Angular unit tests in local Windows/Docker workspaces can fail on worker spawn.
    // Keep the test runner on a single threads pool so Vite/Vitest assets stay test-only and
    // production builds continue to use the normal Angular build pipeline.
    pool: 'threads',
    maxWorkers: 1,
    fileParallelism: false,
  },
});
