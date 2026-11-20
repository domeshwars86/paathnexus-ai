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
  const violations = results.violations.map(({ id, impact, help, nodes }) => ({
    id,
    impact,
    help,
    examples: [...new Map(nodes.map(({ target, failureSummary }) => [failureSummary, { target, failureSummary }])).values()],
  }));
  if (violations.length) {
    await testInfo.attach(`${surface}-axe-results`, {
      body: JSON.stringify(results, null, 2),
      contentType: 'application/json',
    });
  }
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

test('learner dashboard passes automated WCAG 2.2 AA checks on desktop and mobile', async ({ page }, testInfo) => {
  const organizationId = 'accessibility-learner-fixture';
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'accessibility-learner-token');
    sessionStorage.setItem('paathnexus.active-organization', 'accessibility-learner-fixture');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown) => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data }),
    });
    if (path.endsWith('/auth/me')) return send({
      user: { name: 'Asha Learner', email: 'learner@example.test' },
      organizations: [{
        id: organizationId, name: 'Sample Academy', slug: 'sample-academy', type: 'school', role: 'student',
        permissions: ['*'], locale: 'en', timezone: 'Asia/Kolkata',
      }],
    });
    if (path.endsWith('/student/dashboard')) return send({
      student: { name: 'Asha Learner', student_code: 'ST-ACCESS-1' },
      study_recommendations: [], courses: [], assignments: [], assessment_results: [], today_schedule: [],
      schedule_notice: null,
      attendance: { sessions_recorded: 0, present_or_late: 0, rate_percent: null, recent: [] },
    });
    if (path.endsWith('/student/study-plans')) return send([]);
    if (path.endsWith('/notifications')) return send({ notifications: [], unread_count: 0 });
    return send([]);
  });

  await page.goto('/');
  await expect(page.getByRole('heading', { name: 'Welcome, Asha' })).toBeVisible();
  for (const viewport of responsiveViewports) {
    await page.setViewportSize(viewport);
    await expectNoAxeViolations(page, testInfo, `learner-dashboard-${viewport.name}`);
  }
});

test('educator attendance dialog passes automated WCAG 2.2 AA checks on desktop and mobile', async ({ page }, testInfo) => {
  const organizationId = 'accessibility-educator-fixture';
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'accessibility-educator-token');
    sessionStorage.setItem('paathnexus.active-organization', 'accessibility-educator-fixture');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown) => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data }),
    });
    if (path.endsWith('/auth/me')) return send({
      user: { name: 'Ari Educator', email: 'educator@example.test' },
      organizations: [{
        id: organizationId, name: 'Sample Academy', slug: 'sample-academy', type: 'school', role: 'educator',
        permissions: ['*'], locale: 'en', timezone: 'Asia/Kolkata',
      }],
    });
    if (path.endsWith('/dashboard')) return send({
      active_students_count: 1, active_classes_count: 1, active_enrollments_count: 1,
      unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null,
      latest_attendance_class: null, latest_attendance_date: null, attendance_series: [],
      classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null,
    });
    if (path.endsWith('/classes')) return send([{
      id: 'accessibility-class', name: 'Class 6A', grade: '6', section: null, can_attend_whole: true,
      assigned_section_ids: [], student_count: 1,
      students: [{ id: 'accessibility-student', name: 'Asha Learner', student_code: 'ST-ACCESS-1', section_id: null }],
    }]);
    if (path.endsWith('/students')) return send([{ id: 'accessibility-student', name: 'Asha Learner', student_code: 'ST-ACCESS-1', section_id: null }]);
    if (path.endsWith('/notifications')) return send({ unread_count: 0, items: [] });
    return send([]);
  });

  await page.goto('/');
  await page.getByRole('navigation', { name: 'Main navigation' }).getByRole('button', { name: 'My classes' }).click();
  await page.locator('.class-card').getByRole('button', { name: 'Take attendance' }).click();
  const dialog = page.getByRole('dialog', { name: 'Class 6A' });
  await expect(dialog).toBeVisible();
  for (const viewport of responsiveViewports) {
    await page.setViewportSize(viewport);
    await expectNoAxeViolations(page, testInfo, `educator-attendance-${viewport.name}`);
  }
});
