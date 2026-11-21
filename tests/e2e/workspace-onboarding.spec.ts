import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

async function openNewOwnerWorkspace(page: Page, organizationId: string, setupData: { years: unknown[]; classes: unknown[]; invitations: unknown[] } = { years: [], classes: [], invitations: [] }): Promise<void> {
  await page.addInitScript((id: string) => {
    sessionStorage.setItem('paathnexus.session-token', 'onboarding-fixture-token');
    sessionStorage.setItem('paathnexus.active-organization', id);
  }, organizationId);
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({
      user: { name: 'Setup Owner', email: 'owner@example.test' },
      organizations: [{ id: organizationId, name: 'New Academy', slug: 'new-academy', type: 'school', role: 'owner', permissions: ['*'], locale: 'en', timezone: 'Asia/Kolkata' }],
    });
    if (path.endsWith('/dashboard')) return send({
      active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0,
      unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null,
      latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [],
      today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null,
    });
    if (path.endsWith('/notifications')) return send({ notifications: [], unread_count: 0 });
    if (path.endsWith('/academic-years')) return send(setupData.years);
    if (path.endsWith('/classes')) return send(setupData.classes);
    if (path.endsWith('/invitations')) return send(setupData.invitations);
    return send([]);
  });
  await page.goto('/');
  await expect(page.getByRole('heading', { name: 'Set up your workspace' })).toBeVisible();
}

test('new owner setup can be paused and resumed across reloads', async ({ page }) => {
  await openNewOwnerWorkspace(page, 'onboarding-new-owner');
  await expect(page.getByRole('progressbar', { name: 'Workspace setup progress' })).toHaveAttribute('aria-valuenow', '0');
  await page.getByRole('button', { name: 'Mark reviewed' }).click();
  await expect(page.getByText('1 of 4 complete')).toBeVisible();
  await page.reload();
  await expect(page.getByText('1 of 4 complete')).toBeVisible();

  await page.getByRole('button', { name: 'Skip for now' }).click();
  await expect(page.getByText('Workspace setup is paused. Your progress is saved.')).toBeVisible();
  await page.getByRole('button', { name: 'Continue setup' }).click();
  await expect(page.getByRole('heading', { name: 'Set up your workspace' })).toBeVisible();
  await page.getByRole('button', { name: 'Open schedule' }).click();
  await expect(page.getByRole('main').getByRole('heading', { name: 'Schedule', exact: true })).toBeVisible();
  await page.getByRole('navigation', { name: 'Main navigation' }).getByRole('button', { name: 'Overview' }).click();
  await expect(page.getByText('1 of 4 complete')).toBeVisible();

  await page.setViewportSize({ width: 375, height: 812 });
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(376);
});

test('setup checklist reflects academic years, classes, and active invitations from the workspace', async ({ page }) => {
  await openNewOwnerWorkspace(page, 'onboarding-existing-setup', {
    years: [{ id: 'year-1', name: '2026–27', status: 'active' }],
    classes: [{ id: 'class-1', name: 'Class 6' }],
    invitations: [{ id: 'invite-1', email: 'teacher@example.test', role: 'teacher', status: 'pending' }],
  });
  await expect(page.getByText('3 of 4 complete')).toBeVisible();
  await expect(page.getByText('Set up an academic year')).toBeVisible();
  await expect(page.getByText('Add your first class')).toBeVisible();
  await expect(page.getByText('Invite your team')).toBeVisible();
  await expect(page.getByText('Done', { exact: true })).toHaveCount(3);
});

test('new owner workspace onboarding passes automated accessibility checks on desktop and mobile', async ({ page }) => {
  await openNewOwnerWorkspace(page, 'onboarding-accessibility');
  for (const viewport of [{ width: 1280, height: 900 }, { width: 375, height: 812 }]) {
    await page.setViewportSize(viewport);
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
    expect(results.violations.map(({ id, impact, help }) => ({ id, impact, help }))).toEqual([]);
  }
});
