import { expect, test } from '@playwright/test';

test('production PWA shell and entry assets remain available after going offline', async ({ browser }) => {
  const context = await browser.newContext({ serviceWorkers: 'allow' });
  const page = await context.newPage();
  await page.goto('/');
  await expect(page.getByRole('heading', { name: /One platform\. Infinite possibilities for learning\./i })).toBeVisible();
  await expect.poll(() => page.evaluate(() => Boolean(navigator.serviceWorker.controller))).toBe(true);

  const cachedAssets = await page.evaluate(async () => {
    const cache = await caches.open('paathnexus-shell-v2');
    return cache.keys().then((requests) => requests.map((request) => new URL(request.url).pathname));
  });
  expect(cachedAssets).toContain('/');
  expect(cachedAssets.some((path) => path.endsWith('.js') && path.startsWith('/assets/'))).toBe(true);
  expect(cachedAssets.some((path) => path.endsWith('.css') && path.startsWith('/assets/'))).toBe(true);
  expect(cachedAssets.some((path) => path.includes('AuthScreen-') && path.endsWith('.js'))).toBe(true);
  expect(cachedAssets.some((path) => path.includes('WorkspaceApp-') && path.endsWith('.js'))).toBe(true);
  await context.setOffline(true);
  await page.reload();
  await expect(page.getByRole('heading', { name: /One platform\. Infinite possibilities for learning\./i })).toBeVisible();
  await context.close();
});
