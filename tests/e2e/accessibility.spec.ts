import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';

const responsiveViewports = [
  { name: 'desktop', width: 1280, height: 900 },
  { name: 'mobile', width: 375, height: 812 },
];

async function expectNoAxeViolations(page: Page, testInfo: TestInfo, surface: string): Promise<void> {
  const results = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
    .analyze();
  await testInfo.attach(`${surface}-axe-results`, {
    body: JSON.stringify(results, null, 2),
    contentType: 'application/json',
  });

  const violations = results.violations.map(({ id, impact, help, nodes }) => ({
    id,
    impact,
    help,
    examples: [...new Map(nodes.map(({ target, failureSummary }) => [failureSummary, { target, failureSummary }])).values()],
  }));
  expect(violations, `${surface} accessibility violations`).toEqual([]);
}

test('public homepage passes automated WCAG 2.2 AA checks on desktop and mobile', async ({ page }, testInfo) => {
  await page.route('**/api/v1/catalog/courses**', (route) => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({ data: [], meta: { total: 0, current_page: 1, last_page: 1 } }),
  }));
  await page.route('**/api/v1/catalog/plans', (route) => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({ data: [] }),
  }));
  await page.goto('/');
  await expect(page.getByRole('heading', { level: 1, name: /one platform/i })).toBeVisible();

  for (const viewport of responsiveViewports) {
    await page.setViewportSize(viewport);
    await expectNoAxeViolations(page, testInfo, `public-home-${viewport.name}`);
  }
});

test('sign-in screen passes automated WCAG 2.2 AA checks on desktop and mobile', async ({ page }, testInfo) => {
  await page.route('**/api/v1/catalog/courses**', (route) => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({ data: [], meta: { total: 0, current_page: 1, last_page: 1 } }),
  }));
  await page.route('**/api/v1/catalog/plans', (route) => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({ data: [] }),
  }));
  await page.goto('/');
  await page.getByRole('button', { name: 'Sign in', exact: true }).first().click();
  await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
  await expect(page.getByText(/verification/i)).toHaveCount(0);

  for (const viewport of responsiveViewports) {
    await page.setViewportSize(viewport);
    await expectNoAxeViolations(page, testInfo, `sign-in-${viewport.name}`);
  }
});

test('workspace dashboard passes automated WCAG 2.2 AA checks on desktop and mobile', async ({ page }, testInfo) => {
  const organizationId = 'accessibility-workspace-fixture';
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'accessibility-fixture-token');
    sessionStorage.setItem('paathnexus.active-organization', 'accessibility-workspace-fixture');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown) => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data }),
    });

    if (path.endsWith('/auth/me')) return send({
      user: { name: 'Demo Manager', email: 'manager@example.test' },
      organizations: [{
        id: organizationId, name: 'Sample Academy', slug: 'sample-academy', type: 'school', role: 'owner',
        permissions: ['*'], locale: 'en', timezone: 'Asia/Kolkata',
      }],
    });
    if (path.endsWith('/dashboard')) return send({
      active_students_count: 0,
      active_classes_count: 0,
      active_enrollments_count: 0,
      unassigned_students_count: 0,
      pending_tasks_count: 0,
      latest_attendance_rate: null,
      latest_attendance_class: null,
      latest_attendance_date: null,
      attendance_series: [],
      classes: [],
      today_schedule: [],
      schedule_notice: null,
      schedule_weekday: 4,
      active_academic_year_id: null,
    });
    if (path.endsWith('/notifications')) return send({ notifications: [], unread_count: 0 });
    return send([]);
  });

  await page.goto('/');
  await expect(page.getByRole('heading', { name: 'Good morning, Demo ✳' })).toBeVisible();

  for (const viewport of responsiveViewports) {
    await page.setViewportSize(viewport);
    await expectNoAxeViolations(page, testInfo, `workspace-dashboard-${viewport.name}`);
  }
});
