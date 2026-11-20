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

test('manager announcements pass automated WCAG 2.2 AA checks on desktop and mobile', async ({ page }, testInfo) => {
  const organizationId = 'accessibility-announcement-workspace';
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'accessibility-announcement-token');
    sessionStorage.setItem('paathnexus.active-organization', 'accessibility-announcement-workspace');
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
      active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0,
      unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null,
      latest_attendance_class: null, latest_attendance_date: null, attendance_series: [],
      classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null,
    });
    if (path.endsWith('/notifications')) return send({ notifications: [], unread_count: 0 });
    if (path.endsWith('/announcements')) return send([{
      id: 'accessibility-announcement', title: 'Family meeting', body: 'Join us in the school hall at ten.',
      audience: 'guardians', status: 'published', published_at: '2026-11-19T09:00:00Z', scheduled_at: null,
      author_name: 'Demo Manager', recipient_count: 3,
    }]);
    return send([]);
  });

  await page.goto('/');
  await page.getByRole('navigation', { name: 'Tools' }).getByRole('button', { name: 'Announcements' }).click();
  await expect(page.getByText('Family meeting')).toBeVisible();

  for (const viewport of responsiveViewports) {
    await page.setViewportSize(viewport);
    await expectNoAxeViolations(page, testInfo, `manager-announcements-${viewport.name}`);
  }
});

test('AI tutor and teacher assistant pass automated WCAG 2.2 AA checks', async ({ page }, testInfo) => {
  const organizationId = 'accessibility-ai-workspace';
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'accessibility-ai-token');
    sessionStorage.setItem('paathnexus.active-organization', 'accessibility-ai-workspace');
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
        id: organizationId, name: 'Sample Academy', slug: 'sample-academy', type: 'school', role: 'teacher',
        permissions: ['workspace.view', 'courses.view', 'courses.manage'], locale: 'en', timezone: 'Asia/Kolkata',
      }],
    });
    if (path.endsWith('/dashboard')) return send({
      active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0,
      unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null,
      latest_attendance_class: null, latest_attendance_date: null, attendance_series: [],
      classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null,
    });
    if (path.endsWith('/notifications')) return send({ notifications: [], unread_count: 0 });
    if (path.endsWith(`/organizations/${organizationId}/courses`)) return send([]);
    if (path.endsWith(`/organizations/${organizationId}/ai/tutor`)) return send({
      answer: 'Fractions describe equal parts of a whole.', usage: { input_tokens: 5, output_tokens: 9 },
    });
    if (path.endsWith(`/organizations/${organizationId}/ai/teacher-assistant`)) return send({
      draft: '1. Show a fraction with a simple diagram.\n2. Ask learners to compare two examples.',
      human_review_required: true, usage: { input_tokens: 8, output_tokens: 12 },
    });
    return send([]);
  });

  await page.goto('/');
  await page.getByRole('navigation', { name: 'Tools' }).getByRole('button', { name: /AI learning/i }).click();
  await expect(page.getByRole('heading', { name: 'Ask your learning tutor' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Prepare a teaching draft' })).toBeVisible();
  for (const viewport of responsiveViewports) {
    await page.setViewportSize(viewport);
    await expectNoAxeViolations(page, testInfo, `ai-learning-form-${viewport.name}`);
  }

  await page.getByLabel('Your question').fill('What are equivalent fractions?');
  await page.getByRole('button', { name: 'Ask tutor' }).click();
  await expect(page.getByText('Fractions describe equal parts of a whole.')).toBeVisible();
  await page.getByLabel('Topic').fill('Equivalent fractions');
  await page.getByRole('button', { name: 'Generate draft' }).click();
  await expect(page.getByLabel('Generated teaching draft')).toContainText('simple diagram');
  for (const viewport of responsiveViewports) {
    await page.setViewportSize(viewport);
    await expectNoAxeViolations(page, testInfo, `ai-learning-results-${viewport.name}`);
  }
});

test('platform admin usage dashboard passes automated WCAG 2.2 AA checks', async ({ page }, testInfo) => {
  const organizationId = 'accessibility-platform-admin';
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'accessibility-platform-admin-token');
    sessionStorage.setItem('paathnexus.active-organization', 'accessibility-platform-admin');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown, extra: Record<string, unknown> = {}) => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data, ...extra }),
    });
    if (path.endsWith('/auth/me')) return send({
      user: { name: 'Platform Admin', email: 'platform@example.test', is_platform_admin: true },
      organizations: [{ id: organizationId, name: 'Admin Workspace', slug: 'admin-workspace', type: 'school', role: 'owner', permissions: ['workspace.view'], locale: 'en', timezone: 'Asia/Kolkata' }],
    });
    if (path.endsWith('/dashboard')) return send({
      active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0,
      unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null,
      latest_attendance_class: null, latest_attendance_date: null, attendance_series: [],
      classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null,
    });
    if (path.endsWith('/notifications')) return send({ notifications: [], unread_count: 0 });
    if (path.endsWith('/platform/usage')) return send({
      organization_count: 3, active_organization_count: 2, suspended_organization_count: 1,
      active_membership_count: 4, active_student_count: 3, active_course_count: 5, active_subscription_count: 2,
    });
    if (path.endsWith('/platform/usage/history')) return send({ days: 90, snapshots: [
      { snapshot_date: '2026-11-18', organization_count: 2, active_organization_count: 1, suspended_organization_count: 1, active_membership_count: 2, active_student_count: 1, active_course_count: 2, active_subscription_count: 1 },
      { snapshot_date: '2026-11-19', organization_count: 3, active_organization_count: 2, suspended_organization_count: 1, active_membership_count: 4, active_student_count: 3, active_course_count: 5, active_subscription_count: 2 },
    ] });
    if (path.endsWith('/platform/organizations')) return send([], { meta: { total: 0, current_page: 1, last_page: 1 } });
    if (path.endsWith('/platform/audit-logs')) return send([], { meta: { total: 0, current_page: 1, last_page: 1 } });
    if (path.endsWith('/platform/feature-flags')) return send([{
      key: 'ai_tutor', label: 'AI Tutor', description: 'Allow the AI tutor.', enabled: true,
      default_enabled: true, updated_at: null, updated_by_id: null,
    }]);
    return send([]);
  });

  await page.goto('/');
  await page.getByRole('navigation', { name: 'Tools' }).getByRole('button', { name: 'Platform admin' }).click();
  await expect(page.getByRole('heading', { name: 'Platform plans' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Daily platform snapshots' })).toBeVisible();
  for (const viewport of responsiveViewports) {
    await page.setViewportSize(viewport);
    await expectNoAxeViolations(page, testInfo, `platform-admin-usage-${viewport.name}`);
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

test('guardian learning summary and fee invoices pass automated WCAG 2.2 AA checks', async ({ page }, testInfo) => {
  const organizationId = 'accessibility-guardian-fixture';
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'accessibility-guardian-token');
    sessionStorage.setItem('paathnexus.active-organization', 'accessibility-guardian-fixture');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown, meta?: unknown) => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data, ...(meta ? { meta } : {}) }),
    });
    if (path.endsWith('/auth/me')) return send({
      user: { name: 'Mira Guardian', email: 'guardian@example.test' },
      organizations: [{
        id: organizationId, name: 'Sample Academy', slug: 'sample-academy', type: 'school', role: 'guardian',
        permissions: ['workspace.view', 'attendance.read', 'courses.view', 'results.view', 'fees.view'], locale: 'en', timezone: 'Asia/Kolkata',
      }],
    });
    if (path.endsWith('/guardian/students')) return send([{
      student_id: 'accessibility-student', student_name: 'Asha Learner', student_code: 'ST-ACCESS-1', relationship: 'parent',
      attendance: { sessions_recorded: 5, present: 4, rate_percent: 80, recent: [{ date: '2026-11-19', class_name: 'Class 6A', status: 'present' }] },
      learning: { courses_enrolled: 2, completed_lessons: 3, total_lessons: 8, completion_percent: 38, assignments: [{ id: 'guardian-homework', title: 'Plant cell worksheet', course_title: 'Biology', due_at: '2026-12-01T00:00:00Z', submission_status: 'submitted' }] },
      results: { assessments_completed: 1, average_percent: 80, recent: [{ title: 'Science quiz', score: 8, max_points: 10, completed_at: '2026-11-19T12:00:00Z' }], upcoming_exams: [{ title: 'Term science exam', course_title: 'Science', opens_at: '2026-12-03T04:00:00Z', closes_at: null }] },
    }]);
    if (path.endsWith('/fee-invoices')) return send([{
      id: 'guardian-invoice', invoice_number: 'PN-2026-001', student_id: 'accessibility-student', student_name: 'Asha Learner', student_code: 'ST-ACCESS-1',
      title: 'Term 1 tuition', description: 'Tuition and learning resources', amount_paise: 1250000, balance_paise: 1250000,
      payments: [], due_on: '2026-12-15', status: 'issued', created_at: '2026-11-01T09:00:00Z',
    }], { current_page: 1, last_page: 1, per_page: 25, total: 1 });
    if (path.endsWith('/notifications')) return send({ unread_count: 0, items: [] });
    return send([]);
  });

  await page.goto('/');
  await expect(page.getByRole('heading', { name: 'My children', level: 1 })).toBeVisible();
  await expect(page.getByText('Plant cell worksheet')).toBeVisible();
  for (const viewport of responsiveViewports) {
    await page.setViewportSize(viewport);
    await expectNoAxeViolations(page, testInfo, `guardian-learning-${viewport.name}`);
  }

  await page.setViewportSize({ width: 1280, height: 900 });
  await page.getByRole('navigation', { name: 'Main navigation' }).getByRole('button', { name: 'Fee invoices' }).click();
  await expect(page.getByText('Term 1 tuition')).toBeVisible();
  for (const viewport of responsiveViewports) {
    await page.setViewportSize(viewport);
    await expectNoAxeViolations(page, testInfo, `guardian-fees-${viewport.name}`);
  }
});

test('assessment authoring forms pass automated WCAG 2.2 AA checks on desktop and mobile', async ({ page }, testInfo) => {
  const organizationId = 'accessibility-assessment-authoring';
  const courseId = 'accessibility-assessment-course';
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'accessibility-assessment-token');
    sessionStorage.setItem('paathnexus.active-organization', 'accessibility-assessment-authoring');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({
      user: { name: 'Ari Teacher', email: 'teacher@example.test' },
      organizations: [{ id: organizationId, name: 'Sample Academy', slug: 'sample-academy', type: 'school', role: 'teacher', permissions: ['workspace.view', 'academic.view', 'reports.view', 'courses.view', 'assessments.manage'], locale: 'en', timezone: 'Asia/Kolkata' }],
    });
    if (path.endsWith('/dashboard')) return send({ active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0, unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null });
    if (path.endsWith('/courses') && route.request().method() === 'GET') return send([{ id: courseId, code: 'SCI-101', title: 'Assessment accessibility course', description: null, status: 'active', is_public: false, price_paise: 0, academic_year_id: null, academic_year: null, subject_id: null, subject: null, instructors: [], lessons_count: 0, enrollments_count: 0 }]);
    if (path.endsWith(`/courses/${courseId}/questions`)) return send([
      { id: 'question-choice', prompt: 'Which organelle stores genetic material?', topic: 'Cell biology', difficulty: 2, type: 'single_choice', options: [{ id: 'option-nucleus', text: 'Nucleus' }, { id: 'option-ribosome', text: 'Ribosome' }] },
      { id: 'question-written', prompt: 'Explain the role of the cell membrane.', topic: 'Cell biology', difficulty: 3, type: 'short_answer', grading_rubric: [{ id: 'rubric-accuracy', label: 'Accuracy', weight: 100 }], options: [] },
    ]);
    return send([]);
  });

  await page.goto('/');
  await page.getByRole('navigation', { name: 'Main navigation' }).getByRole('button', { name: 'Courses', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Assessment accessibility course' })).toBeVisible();
  await expect(page.getByText('Which organelle stores genetic material?')).toBeVisible();
  await expect(page.getByText('Publish timed quiz', { exact: true })).toBeVisible();
  for (const viewport of responsiveViewports) {
    await page.setViewportSize(viewport);
    await expectNoAxeViolations(page, testInfo, `assessment-authoring-${viewport.name}`);
  }
});

test('assessment answer review and rubric grading pass automated WCAG 2.2 AA checks', async ({ page }, testInfo) => {
  const organizationId = 'accessibility-assessment-review';
  const courseId = 'accessibility-review-course';
  const assessmentId = 'accessibility-review-assessment';
  const attemptId = 'accessibility-review-attempt';
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'accessibility-review-token');
    sessionStorage.setItem('paathnexus.active-organization', 'accessibility-assessment-review');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({
      user: { name: 'Ari Teacher', email: 'teacher@example.test' },
      organizations: [{ id: organizationId, name: 'Sample Academy', slug: 'sample-academy', type: 'school', role: 'teacher', permissions: ['workspace.view', 'academic.view', 'reports.view', 'courses.view', 'assessments.manage'], locale: 'en', timezone: 'Asia/Kolkata' }],
    });
    if (path.endsWith('/dashboard')) return send({ active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0, unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null });
    if (path.endsWith('/courses') && route.request().method() === 'GET') return send([{ id: courseId, code: 'WRITE-1', title: 'Writing and review', description: null, status: 'active', is_public: false, price_paise: 0, academic_year_id: null, academic_year: null, subject_id: null, subject: null, instructors: [], lessons_count: 0, enrollments_count: 1 }]);
    if (path.endsWith(`/courses/${courseId}/assessments`)) return send([{ id: assessmentId, title: 'Weekly writing check', instructions: null, duration_minutes: 20, attempts_allowed: 1, randomize_questions: false, randomized_question_count: null, opens_at: null, closes_at: null, status: 'published', question_count: 1 }]);
    if (path.endsWith(`/courses/${courseId}/assessments/${assessmentId}/analytics`)) return send({ assessment_id: assessmentId, question_pool_count: 1, randomized_question_count: null, attempt_count: 1, completed_attempt_count: 1, average_score_percent: 50, question_analytics: [{ question_id: 'question-written', prompt: 'Why does a plant cell need a membrane?', type: 'short_answer', points: 10, attempts_presented: 1, correct_count: 0, accuracy_percent: 0 }] });
    if (path.endsWith(`/courses/${courseId}/assessments/${assessmentId}/attempts/${attemptId}/review`)) return send({
      attempt: { id: attemptId, attempt_number: 1, status: 'submitted', review_status: 'pending', score: 5, max_points: 10, started_at: '2026-11-19T09:00:00Z', expires_at: null, submitted_at: '2026-11-19T09:15:00Z', student_name: 'Asha Learner', student_code: 'ST-ACCESS-1' },
      questions: [{ question_id: 'question-written', prompt: 'Why does a plant cell need a membrane?', type: 'short_answer', points: 10, points_awarded: 0, is_correct: null, response_text: 'It controls what enters and leaves the cell.', grader_feedback: null, graded_at: null, grading_rubric: [{ id: 'rubric-accuracy', label: 'Scientific accuracy', weight: 60 }, { id: 'rubric-explanation', label: 'Clear explanation', weight: 40 }], rubric_scores: [], selected_options: [], correct_options: [] }],
    });
    if (path.endsWith(`/courses/${courseId}/assessments/${assessmentId}/attempts`)) return send([{ id: attemptId, attempt_number: 1, status: 'submitted', review_status: 'pending', score: 5, max_points: 10, started_at: '2026-11-19T09:00:00Z', expires_at: null, submitted_at: '2026-11-19T09:15:00Z', student_id: 'accessibility-student', student_name: 'Asha Learner', student_code: 'ST-ACCESS-1' }]);
    return send([]);
  });

  await page.goto('/');
  await page.getByRole('navigation', { name: 'Main navigation' }).getByRole('button', { name: 'Courses', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Writing and review' })).toBeVisible();
  await page.getByRole('button', { name: 'Apply filters' }).click();
  await expect(page.getByRole('heading', { name: 'Review submitted answers' })).toBeVisible();
  await page.getByRole('button', { name: 'Review answers' }).click();
  await expect(page.getByText('Scientific accuracy')).toBeVisible();
  for (const viewport of responsiveViewports) {
    await page.setViewportSize(viewport);
    await expectNoAxeViolations(page, testInfo, `assessment-review-${viewport.name}`);
  }
});
