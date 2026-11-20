import { defineConfig } from '@playwright/test';

const port = Number(process.env.PLAYWRIGHT_PWA_PORT) || 5175;
const baseURL = `http://127.0.0.1:${port}`;

export default defineConfig({
  testDir: './tests/pwa',
  workers: 1,
  reporter: 'list',
  use: {
    baseURL,
    browserName: 'chromium',
    headless: true,
    serviceWorkers: 'allow',
  },
  webServer: {
    command: `npm run preview -- --host 127.0.0.1 --port ${port} --strictPort`,
    url: baseURL,
    reuseExistingServer: !process.env.CI,
    timeout: 60_000,
  },
});
