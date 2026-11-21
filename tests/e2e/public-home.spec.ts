import { expect, test } from '@playwright/test';
import { readFile } from 'node:fs/promises';

const responsiveWidths = [320, 375, 430, 768, 1024, 1440];

async function expectNoHorizontalOverflow(page: import('@playwright/test').Page): Promise<void> {
  for (const width of responsiveWidths) {
    await page.setViewportSize({ width, height: 900 });
    const dimensions = await page.evaluate(() => ({
      documentWidth: Math.max(document.documentElement.scrollWidth, document.body.scrollWidth),
      overflowingElements: [...document.querySelectorAll<HTMLElement>('*')].filter((element) => {
        const rect = element.getBoundingClientRect();
        const parentRight = element.parentElement?.getBoundingClientRect().right ?? 0;
        return rect.width > 0 && rect.right > innerWidth + 1 && parentRight <= innerWidth + 1;
      }).slice(0, 6).map((element) => ({ tag: element.tagName, className: element.className, right: element.getBoundingClientRect().right, width: element.getBoundingClientRect().width })),
    }));
    expect(dimensions.documentWidth, `unexpected horizontal overflow at ${width}px; elements: ${JSON.stringify(dimensions.overflowingElements)}`).toBeLessThanOrEqual(width + 1);
  }
}

test('public homepage stays usable without horizontal overflow at target viewport widths', async ({ page }) => {
  await page.goto('/');
  await expect(page.getByRole('banner')).toBeAttached();
  await expect(page.getByRole('main')).toHaveAttribute('id', 'main-content');
  await expect(page.getByRole('contentinfo')).toBeAttached();
  const skipLink = page.getByRole('link', { name: 'Skip to main content' });
  await page.keyboard.press('Tab');
  await expect(skipLink).toBeFocused();
  await skipLink.press('Enter');
  await expect(page.getByRole('main')).toBeFocused();
  await expect(page.getByRole('heading', { level: 1, name: /one platform/i })).toBeVisible();

  for (const width of responsiveWidths) {
    await page.setViewportSize({ width, height: 900 });
    await expect(page.getByRole('heading', { level: 1, name: /one platform/i })).toBeVisible();
    const dimensions = await page.evaluate(() => ({
      viewport: document.documentElement.clientWidth,
      content: document.documentElement.scrollWidth,
    }));
    expect(dimensions.content, `unexpected horizontal overflow at ${width}px`).toBeLessThanOrEqual(dimensions.viewport + 1);
  }

  await expect(page.getByRole('heading', { name: 'Tools for the whole learning community' })).toBeAttached();
  await expect(page.getByRole('heading', { name: 'Choose a starting point' })).toBeAttached();
  await expect(page.getByRole('region', { name: 'Educator workflow' })).toBeAttached();
});

test('public feature, educator, course, and pricing pages support direct routes and responsive layouts', async ({ page }) => {
  await page.route('**/api/v1/catalog/courses**', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [], meta: { total: 0, current_page: 1, last_page: 1 } }) }));
  await page.route('**/api/v1/catalog/educators**', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [{ id: 'educator-1', name: 'Asha Rao', title: 'Biology educator', bio: 'Makes biology approachable.', organization_name: 'Learning Academy', organization_slug: 'learning-academy', organization_type: 'academy' }], meta: { total: 1, current_page: 1, last_page: 1 } }) }));
  await page.route('**/api/v1/catalog/plans', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) }));

  const routes = [
    ['/features', 'Tools for the whole learning community', 'Platform | PaathNexus AI'],
    ['/educators', 'Build your teaching practice around learning.', 'For educators | PaathNexus AI'],
    ['/courses', 'Explore public courses', 'Courses | PaathNexus AI'],
    ['/pricing', 'Choose a starting point', 'Pricing | PaathNexus AI'],
  ] as const;

  for (const [path, heading, title] of routes) {
    await page.goto(path);
    await expect(page.getByRole('banner')).toBeVisible();
    await expect(page.getByRole('main').getByRole('heading', { name: heading })).toBeVisible();
    await expect(page).toHaveTitle(title);
    if (path === '/educators') {
      await expect(page.getByRole('heading', { name: 'Meet our educators' })).toBeVisible();
      await expect(page.getByRole('article').filter({ hasText: 'Asha Rao' })).toContainText('Biology educator');
    }
    await expect(page.getByRole('contentinfo')).toBeVisible();
    await expect(page.locator('.public-hero')).toBeHidden();
    for (const width of responsiveWidths) {
      await page.setViewportSize({ width, height: 900 });
      const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
      expect(scrollWidth, `${path} should not overflow horizontally at ${width}px`).toBeLessThanOrEqual(width + 1);
    }
  }
});

test('public educator profiles are directly shareable and keep contact and account details private', async ({ page }) => {
  const educatorId = '45a7e78a-86eb-49d8-9624-68f1289a11d9';
  await page.route(`**/api/v1/catalog/educators/${educatorId}`, (route) => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({ data: { id: educatorId, name: 'Asha Rao', title: 'Biology educator', bio: 'Makes biology approachable.\nSupports first-generation learners.', organization_name: 'Learning Academy', organization_slug: 'learning-academy', organization_type: 'academy' } }),
  }));
  await page.goto(`/educators/${educatorId}`);
  await expect(page.getByRole('heading', { level: 1, name: 'Asha Rao' })).toBeVisible();
  await expect(page).toHaveTitle('Asha Rao | PaathNexus AI');
  await expect(page.getByText('Makes biology approachable.', { exact: false })).toBeVisible();
  await expect(page.getByText(/contact details and account information are kept private/i)).toBeVisible();
  await page.getByRole('link', { name: /back to educators/i }).click();
  await expect(page).toHaveURL('/educators');
  await expect(page.getByRole('heading', { name: 'Meet our educators' })).toBeVisible();
  for (const width of responsiveWidths) {
    await page.setViewportSize({ width, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth), `educator profile should not overflow at ${width}px`).toBeLessThanOrEqual(width + 1);
  }
});

test('public courses have shareable detail pages that disclose checkout limits and protect lesson content', async ({ page }) => {
  const courseId = 'ea89dfeb-96e6-4a99-bd70-b33878deed40';
  const course = { id: courseId, code: 'BIO-101', title: 'Biology foundations', description: 'Build a strong foundation in life science.', amount_paise: 125000, organization_name: 'Learning Academy', organization_slug: 'learning-academy', organization_type: 'academy', subject: 'Biology', lesson_count: 6, instructors: [{ name: 'Asha Rao', title: 'Biology educator', bio: 'Makes biology approachable.' }] };
  await page.route(`**/api/v1/catalog/courses/${courseId}`, (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: course }) }));
  await page.route('**/api/v1/catalog/courses?**', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [course], meta: { total: 1, current_page: 1, last_page: 1 } }) }));
  await page.goto('/courses');
  await page.getByRole('link', { name: /Open shareable course page/ }).click();
  await expect(page).toHaveURL(`/courses/${courseId}`);
  await expect(page.getByRole('heading', { level: 1, name: 'Biology foundations' })).toBeVisible();
  await expect(page).toHaveTitle('Biology foundations | PaathNexus AI');
  await expect(page.getByText('Biology educator')).toBeVisible();
  await expect(page.getByText(/paid checkout is not available yet/i)).toBeVisible();
  await expect(page.getByText(/learning materials remain private/i)).toBeVisible();
  await page.getByRole('link', { name: /back to courses/i }).click();
  await expect(page).toHaveURL('/courses');
  await expect(page.getByRole('heading', { name: 'Explore public courses' })).toBeVisible();
  for (const width of responsiveWidths) {
    await page.setViewportSize({ width, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth), `course detail should not overflow at ${width}px`).toBeLessThanOrEqual(width + 1);
  }
});

test('public site and sign-in support Hindi and remember the language choice', async ({ page }) => {
  await page.setViewportSize({ width: 320, height: 800 });
  await page.route('**/api/v1/catalog/courses**', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [], meta: { total: 0, current_page: 1, last_page: 1 } }) }));
  await page.route('**/api/v1/catalog/plans', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) }));
  await page.goto('/');
  await page.getByRole('button', { name: 'Open navigation menu' }).click();
  await page.getByRole('button', { name: 'हिन्दी' }).click();
  await expect(page.getByText('सीखने की असीम संभावनाएँ', { exact: true })).toBeVisible();
  await expect.poll(() => page.evaluate(() => document.documentElement.lang)).toBe('hi-IN');
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

  await page.reload();
  await expect(page.getByText('सीखने की असीम संभावनाएँ', { exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'नेविगेशन मेनू खोलें' }).click();
  await page.getByRole('link', { name: 'साइन इन', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'वापसी पर स्वागत है' })).toBeVisible();
  await expect(page.getByLabel('ईमेल पता')).toBeVisible();
});

test('signed-in workspace shell follows the saved language and switches back to English', async ({ page }) => {
  const organizationId = 'locale-workspace-fixture';
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'locale-fixture-token');
    sessionStorage.setItem('paathnexus.active-organization', 'locale-workspace-fixture');
    localStorage.setItem('paathnexus.locale', 'hi');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({
      user: { name: 'Demo Manager', email: 'manager@example.test' },
      organizations: [{ id: organizationId, name: 'Sample Academy', slug: 'sample-academy', type: 'school', role: 'owner', permissions: ['*'], locale: 'en', timezone: 'Asia/Kolkata' }],
    });
    if (path.endsWith('/dashboard')) return send({ active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0, unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null });
    if (path.endsWith('/notifications')) return send({ notifications: [], unread_count: 0 });
    return send([]);
  });

  await page.goto('/');
  const mainNavigation = page.getByRole('navigation', { name: 'मुख्य नेविगेशन' });
  await expect(mainNavigation.getByRole('button', { name: 'अवलोकन', exact: true })).toBeVisible();
  await expect(page.getByRole('button', { name: 'हिन्दी' })).toHaveAttribute('aria-pressed', 'true');
  await expect(page.getByRole('heading', { name: 'सुप्रभात, Demo ✳' })).toBeVisible();
  await expect(page.getByText('आज आपकी कक्षाओं में क्या हो रहा है, यहाँ देखें।')).toBeVisible();
  await expect.poll(() => page.evaluate(() => document.documentElement.lang)).toBe('hi-IN');

  await page.getByRole('button', { name: 'English' }).click();
  await expect(page.getByRole('navigation', { name: 'Main navigation' }).getByRole('button', { name: 'Overview', exact: true })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Good morning, Demo ✳' })).toBeVisible();
  await expect.poll(() => page.evaluate(() => document.documentElement.lang)).toBe('en');

  await page.getByRole('button', { name: 'हिन्दी' }).click();
  await page.setViewportSize({ width: 375, height: 812 });
  await page.getByRole('button', { name: 'नेविगेशन खोलें' }).click();
  await expect(page.getByRole('navigation', { name: 'मुख्य नेविगेशन' }).getByRole('button', { name: 'अवलोकन', exact: true })).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});

test('learner dashboard and study planner follow the selected Hindi locale', async ({ page }) => {
  const organizationId = 'learner-locale-fixture';
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'learner-locale-token');
    sessionStorage.setItem('paathnexus.active-organization', 'learner-locale-fixture');
    localStorage.setItem('paathnexus.locale', 'hi');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({
      user: { name: 'Asha Learner', email: 'learner@example.test' },
      organizations: [{ id: organizationId, name: 'Sample Academy', slug: 'sample-academy', type: 'school', role: 'student', permissions: ['*'], locale: 'en', timezone: 'Asia/Kolkata' }],
    });
    if (path.endsWith('/student/dashboard')) return send({
      student: { name: 'Asha Learner', student_code: 'ST-LOCALE-1' }, study_recommendations: [],
      courses: [], assignments: [], assessment_results: [], today_schedule: [], schedule_notice: null,
      attendance: { sessions_recorded: 0, present_or_late: 0, rate_percent: null, recent: [] },
    });
    if (path.endsWith('/student/study-plans')) return send([]);
    if (path.endsWith('/notifications')) return send({ notifications: [], unread_count: 0 });
    return send([]);
  });

  await page.goto('/');
  await expect(page.getByRole('heading', { name: 'स्वागत है, Asha' })).toBeVisible();
  await expect(page.getByText('मेरे कोर्स', { exact: true })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'ध्यान से पढ़ाई का समय तय करें' })).toBeVisible();
  await expect(page.getByLabel('पढ़ाई का विषय')).toBeVisible();
  await expect(page.getByText('आज कोई आगामी कक्षा निर्धारित नहीं है।')).toBeVisible();
  await page.getByRole('button', { name: 'English' }).click();
  await expect(page.getByRole('heading', { name: 'Welcome, Asha' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Plan a focused session' })).toBeVisible();
});

test('educator task and class attendance workflows follow the selected Hindi locale', async ({ page }) => {
  const organizationId = 'educator-locale-fixture';
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'educator-locale-token');
    sessionStorage.setItem('paathnexus.active-organization', 'educator-locale-fixture');
    localStorage.setItem('paathnexus.locale', 'hi');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({
      user: { name: 'Ari Educator', email: 'educator@example.test' },
      organizations: [{ id: organizationId, name: 'Sample Academy', slug: 'sample-academy', type: 'school', role: 'educator', permissions: ['*'], locale: 'en', timezone: 'Asia/Kolkata' }],
    });
    if (path.endsWith('/dashboard')) return send({ active_students_count: 1, active_classes_count: 1, active_enrollments_count: 1, unassigned_students_count: 0, pending_tasks_count: 1, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null });
    if (path.endsWith('/tasks')) return send([{ id: 'locale-task-1', title: 'Prepare science materials', description: null, due_at: '2026-12-01', assigned_to: 'educator-1', assigned_to_name: 'Ari Educator', status: 'pending', completed_at: null, archived_at: null, created_at: '2026-11-20T10:00:00Z' }]);
    if (path.endsWith('/task-assignees')) return send([{ id: 'educator-1', name: 'Ari Educator', role: 'educator' }]);
    if (path.endsWith('/classes')) return send([{ id: 'locale-class-1', name: 'Class 6A', grade: '6', section: null, can_attend_whole: true, assigned_section_ids: [], student_count: 1, students: [{ id: 'locale-student-1', name: 'Asha Learner', student_code: 'ST-LOCALE-1', section_id: null }] }]);
    if (path.endsWith('/students')) return send([{ id: 'locale-student-1', name: 'Asha Learner', student_code: 'ST-LOCALE-1', section_id: null }]);
    if (path.endsWith('/notifications')) return send({ unread_count: 0, items: [] });
    return send([]);
  });

  await page.goto('/');
  const mainNavigation = page.getByRole('navigation', { name: 'मुख्य नेविगेशन' });
  await mainNavigation.getByRole('button', { name: 'कार्य', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'कार्य सूची' })).toBeVisible();
  await expect(page.getByText('Due', { exact: true })).toHaveCount(0);
  await expect(page.getByText('अंतिम तिथि', { exact: false })).toBeVisible();
  await page.getByRole('button', { name: 'कार्य जोड़ें' }).click();
  await expect(page.getByLabel('कार्य का शीर्षक')).toBeVisible();
  await expect(page.getByLabel('इन्हें सौंपें')).toBeVisible();

  await mainNavigation.getByRole('button', { name: 'मेरी कक्षाएँ', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'कक्षाएँ और विद्यार्थी' })).toBeVisible();
  await expect(page.getByText('Asha Learner')).toBeVisible();
  const attendanceButton = page.locator('.class-card').getByRole('button', { name: 'उपस्थिति दर्ज करें' });
  await attendanceButton.click();
  const attendanceDialog = page.getByRole('dialog', { name: 'Class 6A' });
  await expect(attendanceDialog.getByText('हर नामांकित विद्यार्थी की स्थिति दर्ज करें। उपस्थित और देर से आए विद्यार्थी उपस्थिति दर में गिने जाएँगे।')).toBeVisible();
  await expect(attendanceDialog.getByRole('option', { name: 'अनुपस्थित' })).toBeAttached();
  await page.keyboard.press('Escape');
  await expect(attendanceDialog).toBeHidden();
  await expect(attendanceButton).toBeFocused();
});

test('public and authentication supporting text meets WCAG AA contrast', async ({ page }) => {
  await page.route('**/api/v1/catalog/courses**', (route) => route.fulfill({
    status: 200, contentType: 'application/json', body: JSON.stringify({
      data: [{ id: 'contrast-course', code: 'SCI-1', title: 'Science foundations', description: 'A sample public course.', amount_paise: 0, organization_name: 'Sample Academy', organization_slug: 'sample-academy', organization_type: 'school', subject: 'Science', lesson_count: 4, instructors: [] }],
      meta: { total: 1, current_page: 1, last_page: 1 },
    }),
  }));
  await page.route('**/api/v1/catalog/plans', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) }));
  await page.goto('/');
  await expect(page.getByText('Science foundations')).toBeVisible();

  const contrastRatios = async (selectors: { label: string; selector: string; pseudo?: string }[]) => page.evaluate((items) => {
    const parse = (color: string) => color.match(/[\d.]+/g)?.map(Number) ?? [255, 255, 255];
    const luminance = (color: string) => {
      const channels = parse(color);
      const linear = (channel: number | undefined) => {
        const value = (channel ?? 255) / 255;
        return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
      };
      const [r, g, b] = [linear(channels[0]), linear(channels[1]), linear(channels[2])];
      return 0.2126 * r + 0.7152 * g + 0.0722 * b;
    };
    const background = (element: Element) => {
      let current: Element | null = element;
      while (current) {
        const color = getComputedStyle(current).backgroundColor;
        if (parse(color)[3] !== 0) return color;
        current = current.parentElement;
      }
      return 'rgb(255, 255, 255)';
    };
    return items.map(({ label, selector, pseudo }) => {
      const element = document.querySelector(selector);
      if (!element) throw new Error(`Missing contrast sample: ${selector}`);
      const style = getComputedStyle(element, pseudo);
      const foreground = style.color;
      const bg = background(element);
      const front = luminance(foreground);
      const back = luminance(bg);
      return { label, ratio: (Math.max(front, back) + 0.05) / (Math.min(front, back) + 0.05) };
    });
  }, selectors);

  const publicRatios = await contrastRatios([
    { label: 'course metadata', selector: '.public-course-info>div span' },
    { label: 'public footer', selector: '.public-footer>span' },
    { label: 'public navigation', selector: '.public-nav nav a' },
    { label: 'hero illustration label', selector: '.hero-card-top' },
    { label: 'hero progress text', selector: '.hero-progress-meta' },
    { label: 'educator description', selector: '.public-educator-copy>p' },
    { label: 'educator note', selector: '.public-educator-copy>small' },
    { label: 'educator workflow step', selector: '.public-educator-steps small' },
  ]);
  for (const sample of publicRatios) expect(sample.ratio, `${sample.label} contrast`).toBeGreaterThanOrEqual(4.5);

  await page.getByRole('button', { name: /View course/ }).click();
  const dialogRatios = await contrastRatios([{ label: 'course details', selector: '.public-course-dialog>p' }]);
  for (const sample of dialogRatios) expect(sample.ratio, `${sample.label} contrast`).toBeGreaterThanOrEqual(4.5);
  await page.keyboard.press('Escape');

  await page.getByRole('button', { name: 'Sign in', exact: true }).first().click();
  await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
  const authRatios = await contrastRatios([
    { label: 'auth heading description', selector: '.auth-heading p' },
    { label: 'auth footer', selector: '.auth-footer span' },
    { label: 'auth placeholder', selector: '.auth-form input', pseudo: '::placeholder' },
    { label: 'auth mode tab', selector: '.auth-tabs button' },
    { label: 'auth trust message', selector: '.auth-trust span' },
    { label: 'auth legal text', selector: '.auth-legal' },
  ]);
  for (const sample of authRatios) expect(sample.ratio, `${sample.label} contrast`).toBeGreaterThanOrEqual(4.5);
});

test('local sign-in hides email verification when the API cannot complete verification', async ({ page }) => {
  await page.route('**/api/v1/catalog/courses**', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [], meta: { total: 0, current_page: 1, last_page: 1 } }) }));
  await page.route('**/api/v1/catalog/plans', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) }));
  await page.route('**/api/v1/auth/login', (route) => route.fulfill({ status: 403, contentType: 'application/json', body: JSON.stringify({ message: 'Verify your email before signing in.' }) }));
  await page.goto('/');
  await page.getByRole('button', { name: 'Sign in', exact: true }).first().click();
  await page.getByLabel('Email address').fill('learner@example.test');
  await page.getByLabel('Password', { exact: true }).fill('local-test-password');
  await page.getByRole('button', { name: 'Sign in to workspace' }).click();
  await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Verify your email' })).toHaveCount(0);
  await expect(page.getByText('Sign-in settings are out of date. Refresh the page and try again.')).toBeVisible();
  await expect(page.getByText(/verification/i)).toHaveCount(0);
});

test('academic timetable and dated schedule controls are localized in Hindi workspaces', async ({ page }) => {
  await page.addInitScript(() => {
    localStorage.setItem('paathnexus.locale', 'hi');
    sessionStorage.setItem('paathnexus.session-token', 'hindi-schedule-token');
    sessionStorage.setItem('paathnexus.active-organization', 'hindi-schedule-workspace');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({
      user: { name: 'Workspace Owner', email: 'owner@example.test' },
      organizations: [{ id: 'hindi-schedule-workspace', name: 'Hindi Academy', slug: 'hindi-academy', type: 'school', role: 'owner', permissions: ['*'], locale: 'hi', timezone: 'Asia/Kolkata' }],
    });
    if (path.endsWith('/dashboard')) return send({
      active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0, unassigned_students_count: 0,
      pending_tasks_count: 0, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null,
      attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 1, active_academic_year_id: null,
    });
    if (path.endsWith('/classes')) return send([{ id: 'class-8', name: 'Class 8', grade: '8', section: null, student_count: 0, can_attend_whole: true, assigned_section_ids: [] }]);
    if (path.endsWith('/sections')) return send([]);
    if (path.endsWith('/academic-years')) return send([{ id: 'hindi-year', name: '2026–27', starts_on: '2026-04-01', ends_on: '2027-03-31', status: 'active' }]);
    if (path.endsWith('/timetable-entries')) return send([{
      id: 'hindi-friday-lesson', academic_year_id: 'hindi-year', academic_year: '2026–27', class_id: 'class-8', class_name: 'Class 8',
      section_id: null, section_name: null, subject: 'Science', subject_id: 'science', teacher_profile_id: null, teacher_name: null,
      weekday: 5, starts_at: '10:00', ends_at: '10:45', room: 'Lab 2',
    }]);
    if (path.endsWith('/schedule-exceptions/hindi-extra-lesson') && route.request().method() === 'PUT') {
      const body = route.request().postDataJSON() as { title: string; notes: string | null };
      return send({
        id: 'hindi-extra-lesson', academic_year_id: 'hindi-year', exception_date: '2026-11-21', type: 'extra', title: body.title, notes: body.notes,
        class_id: 'class-8', class_name: 'Class 8', section_id: null, section_name: null, timetable_entry_id: null, subject_id: 'science',
        subject: 'Science', teacher_profile_id: null, teacher_name: null, starts_at: '11:00', ends_at: '11:45', room: 'Lab 2', status: 'active', cancelled_at: null,
      });
    }
    if (path.endsWith('/schedule-exceptions')) return send([{
      id: 'hindi-extra-lesson', academic_year_id: 'hindi-year', exception_date: '2026-11-21', type: 'extra', title: 'Science revision', notes: null,
      class_id: 'class-8', class_name: 'Class 8', section_id: null, section_name: null, timetable_entry_id: null, subject_id: 'science',
      subject: 'Science', teacher_profile_id: null, teacher_name: null, starts_at: '11:00', ends_at: '11:45', room: 'Lab 2', status: 'active', cancelled_at: null,
    }]);
    return send([]);
  });

  await page.goto('/');
  await page.getByRole('button', { name: 'समय-सारणी', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'समय-सारणी और सत्र' })).toBeVisible();
  await expect(page.getByText('साप्ताहिक समय-सारणी', { exact: true })).toBeVisible();
  await expect(page.getByText('तारीख़ के अनुसार बदलाव', { exact: true })).toBeVisible();
  await expect(page.locator('.timetable-form select').nth(5)).toContainText('सोमवार');
  await expect(page.locator('.timetable-day')).toHaveText('शुक्र');
  await expect(page.locator('.schedule-exception-row')).toContainText('अतिरिक्त पाठ');
  await expect(page.locator('.schedule-exception-row')).toContainText('सक्रिय');
  await expect(page.locator('.schedule-exception-row')).toContainText('21');
  await expect(page.getByRole('button', { name: 'तारीख़ का बदलाव सहेजें' })).toBeVisible();
  await page.locator('.schedule-exception-row').getByRole('button', { name: 'विवरण संपादित करें' }).click();
  const editForm = page.locator('.schedule-exception-form').filter({ hasText: 'समय-सारणी बदलाव का विवरण संपादित करें' });
  await editForm.getByLabel('शीर्षक').fill('संशोधित विज्ञान पाठ');
  await editForm.getByLabel('टिप्पणियाँ').fill('नई जानकारी');
  await editForm.getByRole('button', { name: 'विवरण सहेजें' }).click();
  await expect(page.locator('.schedule-exception-row')).toContainText('संशोधित विज्ञान पाठ');
  await expect(page.locator('.schedule-exception-row')).toContainText('नई जानकारी');
  const addForm = page.locator('.schedule-exception-form').filter({ hasText: 'तारीख़ का बदलाव जोड़ें' });
  await addForm.getByLabel('बदलाव का प्रकार').selectOption('cancel');
  await addForm.getByLabel('तारीख़').fill('2026-11-20');
  await addForm.locator('label').filter({ hasText: 'कक्षा चुनें' }).locator('select').selectOption('class-8');
  const lessonChoice = addForm.getByLabel('विशिष्ट पाठ (वैकल्पिक)');
  await expect(lessonChoice).toContainText('Science · 10:00–10:45');
  await lessonChoice.selectOption('hindi-friday-lesson');
});

test('workspace search opens a matching announcement in the manager view', async ({ page }) => {
  const organizationId = 'announcement-search-workspace';
  const announcement = {
    id: 'announcement-search-item', title: 'Parent meeting on Friday', body: 'The orientation begins at ten.',
    audience: 'guardians', status: 'published', published_at: '2026-11-19T09:00:00Z', scheduled_at: null,
    author_name: 'Workspace Owner', recipient_count: 4,
  };
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'announcement-search-token');
    sessionStorage.setItem('paathnexus.active-organization', 'announcement-search-workspace');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({
      user: { name: 'Workspace Owner', email: 'owner@example.test' },
      organizations: [{ id: organizationId, name: 'Family Academy', slug: 'family-academy', type: 'school', role: 'owner', permissions: ['*'], locale: 'en', timezone: 'Asia/Kolkata' }],
    });
    if (path.endsWith('/notifications')) return send({ unread_count: 0, items: [] });
    if (path.endsWith('/search')) return send([{ id: announcement.id, type: 'announcement', title: announcement.title, subtitle: 'Guardians · Published' }]);
    if (path.endsWith('/announcements')) return send([announcement]);
    if (path.endsWith('/dashboard')) return send({ active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0, unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null });
    return send([]);
  });

  await page.goto('/');
  await page.getByRole('textbox', { name: 'Search workspace records' }).fill('orientation');
  await page.getByRole('button', { name: /Parent meeting on Friday/ }).click();
  await expect(page.locator('.breadcrumb')).toContainText('Announcements');
  await expect(page.getByText('Parent meeting on Friday')).toBeVisible();
});

test('workspace search opens scoped attendance history', async ({ page }) => {
  const organizationId = 'attendance-search-workspace';
  const session = {
    id: 'attendance-search-session', class_id: 'attendance-search-class', section_id: null, live_session_id: null,
    class_name: 'Class 8 North', session_date: '2026-11-18', attendance_rate: 87.5, updated_at: '2026-11-18T10:00:00Z', records: [],
  };
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'attendance-search-token');
    sessionStorage.setItem('paathnexus.active-organization', 'attendance-search-workspace');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({
      user: { name: 'Assigned Teacher', email: 'teacher@example.test' },
      organizations: [{ id: organizationId, name: 'North Academy', slug: 'north-academy', type: 'school', role: 'teacher', permissions: ['organization.view', 'academic.view', 'attendance.read'], locale: 'en', timezone: 'Asia/Kolkata' }],
    });
    if (path.endsWith('/notifications')) return send({ unread_count: 0, items: [] });
    if (path.endsWith('/search')) return send([{ id: session.id, type: 'attendance', title: session.class_name, subtitle: '2026-11-18 · 87.5% present' }]);
    if (path.endsWith('/attendance-sessions')) return send([session]);
    if (path.endsWith('/dashboard')) return send({ active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0, unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: 87.5, latest_attendance_class: session.class_name, latest_attendance_date: session.session_date, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 3, active_academic_year_id: null });
    return send([]);
  });

  await page.goto('/');
  await page.getByRole('textbox', { name: 'Search workspace records' }).fill('north');
  await page.getByRole('button', { name: /Class 8 North/ }).click();
  await expect(page.locator('.breadcrumb')).toContainText('Attendance');
  await expect(page.getByRole('heading', { name: 'Recent attendance' })).toBeVisible();
  await expect(page.getByText('87.5% present')).toBeVisible();
});

test('workspace manager can correct a published announcement for its existing audience', async ({ page }) => {
  const organizationId = 'announcement-editor-workspace';
  const announcementId = 'announcement-editor-item';
  let announcement = {
    id: announcementId, title: 'Meeting on Thursday', body: 'Join us after class.', audience: 'guardians',
    published_at: '2026-11-19T09:00:00Z', author_name: 'Workspace Owner', recipient_count: 3,
  };
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'announcement-editor-token');
    sessionStorage.setItem('paathnexus.active-organization', 'announcement-editor-workspace');
  });
  await page.route('**/api/v1/**', async (route) => {
    const url = new URL(route.request().url());
    const path = url.pathname;
    const send = (data: unknown, status = 200) => route.fulfill({ status, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({
      user: { name: 'Workspace Owner', email: 'owner@example.test' },
      organizations: [{ id: organizationId, name: 'Family Academy', slug: 'family-academy', type: 'school', role: 'owner', permissions: ['workspace.view', 'academic.view', 'academic.manage'], locale: 'en', timezone: 'Asia/Kolkata' }],
    });
    if (path.endsWith('/notifications')) return send({ unread_count: 0, items: [] });
    if (path.includes('/announcements/') && route.request().method() === 'PATCH') {
      const input = route.request().postDataJSON() as { title: string; body: string };
      announcement = { ...announcement, ...input };
      return send(announcement);
    }
    if (path.endsWith('/announcements')) return send([announcement]);
    if (path.endsWith('/dashboard')) return send({ active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0, unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null });
    return send([]);
  });

  await page.goto('/');
  await page.getByRole('navigation', { name: 'Tools' }).getByRole('button', { name: 'Announcements' }).click();
  await page.getByRole('button', { name: 'हिन्दी' }).click();
  await page.getByRole('button', { name: 'सूचना संपादित करें' }).click();
  await page.getByRole('textbox', { name: 'सूचना का शीर्षक संपादित करें' }).fill('Meeting moved to Friday');
  await page.getByRole('textbox', { name: 'सूचना का संदेश संपादित करें' }).fill('Join us after class on Friday.');
  await page.getByRole('button', { name: 'बदलाव सहेजें' }).click();
  await expect(page.getByText('Meeting moved to Friday')).toBeVisible();
  await expect(page.getByText(/अभिभावक.*3 प्राप्तकर्ता/)).toBeVisible();
  expect(announcement.audience).toBe('guardians');
});

test('workspace manager can schedule an announcement in the workspace timezone', async ({ page }) => {
  const organizationId = 'scheduled-announcement-workspace';
  let saved: Record<string, unknown> | null = null;
  let capturedScheduledAt = '';
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'scheduled-announcement-token');
    sessionStorage.setItem('paathnexus.active-organization', 'scheduled-announcement-workspace');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown, status = 200) => route.fulfill({ status, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({
      user: { name: 'Workspace Owner', email: 'owner@example.test' },
      organizations: [{ id: organizationId, name: 'Family Academy', slug: 'family-academy', type: 'school', role: 'owner', permissions: ['workspace.view', 'academic.view', 'academic.manage'], locale: 'en', timezone: 'Asia/Kolkata' }],
    });
    if (path.endsWith('/notifications')) return send({ unread_count: 0, items: [] });
    if (path.endsWith('/announcements') && route.request().method() === 'POST') {
      const payload = route.request().postDataJSON() as Record<string, string>;
      capturedScheduledAt = payload.scheduled_at ?? '';
      saved = {
        id: 'scheduled-announcement-item', title: payload.title, body: payload.body,
        audience: payload.audience, status: 'scheduled', published_at: null, scheduled_at: payload.scheduled_at,
        author_name: 'Workspace Owner', recipient_count: null,
      };
      return send(saved, 201);
    }
    if (path.endsWith('/announcements')) return send(saved ? [saved] : []);
    if (path.endsWith('/dashboard')) return send({ active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0, unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null });
    return send([]);
  });

  await page.goto('/');
  await page.getByRole('navigation', { name: 'Tools' }).getByRole('button', { name: 'Announcements' }).click();
  await page.getByRole('button', { name: 'हिन्दी' }).click();
  await page.getByLabel('शीर्षक', { exact: true }).fill('Tomorrow’s science activity');
  await page.getByLabel('संदेश', { exact: true }).fill('Bring your notebook.');
  await page.getByRole('combobox', { name: 'प्राप्तकर्ता' }).selectOption('students');
  const futureWorkspaceDate = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Kolkata', year: 'numeric', month: '2-digit', day: '2-digit',
  }).format(new Date(Date.now() + 48 * 60 * 60 * 1000));
  await page.getByLabel(/प्रकाशन समय/).fill(`${futureWorkspaceDate}T09:30`);
  await page.getByRole('button', { name: 'सूचना शेड्यूल करें' }).click();
  await expect(page.getByText('Tomorrow’s science activity')).toBeVisible();
  await expect(page.getByText(/विद्यार्थी · शेड्यूल की गई/)).toBeVisible();
  expect(capturedScheduledAt).toMatch(/Z$/);
});

test('workspace manager can withdraw and restore an announcement', async ({ page }) => {
  const organizationId = 'announcement-restore-workspace';
  const announcement: Record<string, unknown> = {
    id: 'announcement-to-restore', title: 'Library hours', body: 'The library opens at eight.', audience: 'students',
    status: 'published', published_at: '2026-11-19T08:00:00Z', scheduled_at: null, author_name: 'Workspace Owner', recipient_count: 12,
  };
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'announcement-restore-token');
    sessionStorage.setItem('paathnexus.active-organization', 'announcement-restore-workspace');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown, status = 200) => route.fulfill({ status, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({
      user: { name: 'Workspace Owner', email: 'owner@example.test' },
      organizations: [{ id: organizationId, name: 'Family Academy', slug: 'family-academy', type: 'school', role: 'owner', permissions: ['workspace.view', 'academic.view', 'academic.manage'], locale: 'en', timezone: 'Asia/Kolkata' }],
    });
    if (path.endsWith('/notifications')) return send({ unread_count: 0, items: [] });
    if (path.includes('/announcements/') && route.request().method() === 'POST') {
      announcement.status = path.endsWith('/withdraw') ? 'withdrawn' : 'published';
      return send(announcement);
    }
    if (path.endsWith('/announcements')) return send([announcement]);
    if (path.endsWith('/dashboard')) return send({ active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0, unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null });
    return send([]);
  });

  await page.goto('/');
  await page.getByRole('navigation', { name: 'Tools' }).getByRole('button', { name: 'Announcements' }).click();
  await page.getByRole('button', { name: 'हिन्दी' }).click();
  await page.getByRole('button', { name: 'सूचना वापस लें' }).click();
  await expect(page.getByText(/विद्यार्थी · वापस ली गई/)).toBeVisible();
  await page.getByRole('button', { name: 'सूचना पुनः प्रकाशित करें' }).click();
  await expect(page.getByText(/विद्यार्थी · प्रकाशित/)).toBeVisible();
  await expect(page.getByText(/12 प्राप्तकर्ता/)).toBeVisible();
});

test('mobile navigation opens and closes with Escape and restores keyboard focus', async ({ page }) => {
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('/');
  const menu = page.getByRole('button', { name: 'Open navigation menu' });

  await menu.click();
  await expect(page.getByRole('button', { name: 'Close navigation menu' })).toHaveAttribute('aria-expanded', 'true');
  await page.keyboard.press('Escape');

  const closedMenu = page.getByRole('button', { name: 'Open navigation menu' });
  await expect(closedMenu).toHaveAttribute('aria-expanded', 'false');
  await expect(closedMenu).toBeFocused();

  await closedMenu.click();
  await page.getByRole('navigation', { name: 'Public navigation' }).getByRole('link', { name: 'Platform' }).click();
  await expect(page).toHaveURL(/\/features$/);
  await expect(page.getByRole('heading', { name: 'Tools for the whole learning community' })).toBeVisible();
  await expect(closedMenu).toHaveAttribute('aria-expanded', 'false');
  await expect(closedMenu).toBeFocused();
});

test('public sign-up and sign-in actions open the corresponding accessible forms', async ({ page }) => {
  await page.goto('/');
  await page.getByRole('button', { name: 'Get started' }).first().click();
  await expect(page.getByRole('heading', { name: 'Create your workspace' })).toBeVisible();
  await expect(page.getByLabel('Organization type')).toBeVisible();
  await expect(page.getByRole('group', { name: 'Account options' })).toBeVisible();
  await expect(page.getByRole('tab')).toHaveCount(0);

  const signInMode = page.getByRole('button', { name: 'Sign in', exact: true });
  await signInMode.focus();
  await page.keyboard.press('Space');
  await expect(signInMode).toHaveAttribute('aria-pressed', 'true');
  await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Sign in to workspace' })).toBeVisible();
  const password = page.getByLabel('Password', { exact: true });
  await expect(password).toHaveAttribute('type', 'password');
  await page.getByRole('button', { name: 'Show password' }).click();
  await expect(password).toHaveAttribute('type', 'text');
  const createAccountMode = page.getByRole('button', { name: 'Create account', exact: true });
  await createAccountMode.focus();
  await page.keyboard.press('Enter');
  await expect(createAccountMode).toHaveAttribute('aria-pressed', 'true');
  await expect(page.getByRole('heading', { name: 'Create your workspace' })).toBeVisible();
});

test('accepted learner invitation opens the learner account-linking workflow', async ({ page }) => {
  const organizationId = 'invitation-follow-up-workspace';
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'invitation-follow-up-token');
    sessionStorage.setItem('paathnexus.active-organization', 'invitation-follow-up-workspace');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({
      user: { name: 'Alex Manager', email: 'alex@example.test' },
      organizations: [{ id: organizationId, name: 'Invitation Academy', slug: 'invitation-academy', type: 'school', role: 'educator', permissions: ['workspace.view', 'members.manage', 'academic.manage', 'courses.manage'], locale: 'en', timezone: 'Asia/Kolkata' }],
    });
    if (path.endsWith('/invitations')) return send([
      { id: 'accepted-student-invitation', email: 'learner@example.test', role: 'student', status: 'accepted', inviter_name: 'Alex Manager', expires_at: '2026-11-26T00:00:00Z', accepted_at: '2026-11-19T00:00:00Z', created_at: '2026-11-18T00:00:00Z' },
      { id: 'accepted-teacher-invitation', email: 'teacher@example.test', role: 'teacher', status: 'accepted', inviter_name: 'Alex Manager', expires_at: '2026-11-26T00:00:00Z', accepted_at: '2026-11-19T00:00:00Z', created_at: '2026-11-18T00:00:00Z' },
      { id: 'accepted-guardian-invitation', email: 'guardian@example.test', role: 'guardian', status: 'accepted', inviter_name: 'Alex Manager', expires_at: '2026-11-26T00:00:00Z', accepted_at: '2026-11-19T00:00:00Z', created_at: '2026-11-18T00:00:00Z' },
    ]);
    if (path.endsWith('/dashboard')) return send({ active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0, unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null });
    return send([]);
  });

  await page.goto('/');
  await page.getByRole('navigation', { name: 'Main navigation' }).getByRole('button', { name: 'Students' }).click();
  await expect(page.getByText('learner@example.test')).toBeVisible();
  await expect(page.getByText('If a student profile is ready, link this account from Courses.')).toBeVisible();
  await page.getByRole('button', { name: 'Open Courses' }).click();
  await expect(page.locator('.breadcrumb')).toContainText('Courses');
  await page.getByRole('navigation', { name: 'Main navigation' }).getByRole('button', { name: 'Students' }).click();
  await page.getByRole('button', { name: 'Open Schedule' }).click();
  await expect(page.locator('.breadcrumb')).toContainText('Schedule');
  await page.getByRole('navigation', { name: 'Main navigation' }).getByRole('button', { name: 'Students' }).click();
  await page.getByRole('button', { name: 'Open guardian links' }).click();
  await expect(page.getByRole('heading', { name: 'Guardian links' })).toBeInViewport();
});

test('guardian-link management controls follow the saved Hindi locale', async ({ page }) => {
  const organizationId = 'guardian-link-locale-workspace';
  await page.addInitScript(() => {
    localStorage.setItem('paathnexus.locale', 'hi');
    sessionStorage.setItem('paathnexus.session-token', 'guardian-link-locale-token');
    sessionStorage.setItem('paathnexus.active-organization', 'guardian-link-locale-workspace');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({
      user: { name: 'Asha Manager', email: 'asha@example.test' },
      organizations: [{ id: organizationId, name: 'Hindi Academy', slug: 'hindi-academy', type: 'school', role: 'educator', permissions: ['workspace.view', 'academic.view', 'academic.manage', 'members.manage'], locale: 'hi', timezone: 'Asia/Kolkata' }],
    });
    if (path.endsWith('/dashboard')) return send({ active_students_count: 1, active_classes_count: 0, active_enrollments_count: 0, unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4 });
    if (path.endsWith('/students')) return send([{ id: 'student-1', student_code: 'S-01', name: 'Aarav' }]);
    if (path.endsWith('/guardian-candidates')) return send([{ id: 42, name: 'Meera Rao', email: 'meera@example.test' }]);
    if (path.endsWith('/guardian-student-links') || path.endsWith('/invitations')) return send([]);
    return send([]);
  });

  await page.goto('/');
  await page.getByRole('navigation', { name: 'मुख्य नेविगेशन' }).getByRole('button', { name: 'विद्यार्थी' }).click();
  const panel = page.locator('#guardian-links');
  await panel.scrollIntoViewIfNeeded();
  await expect(panel.getByRole('heading', { name: 'अभिभावक लिंक' })).toBeVisible();
  await expect(panel.getByLabel('विद्यार्थी')).toHaveValue('student-1');
  await expect(panel.getByLabel('अभिभावक खाता')).toHaveValue('42');
  await panel.getByLabel('संबंध').selectOption('grandparent');
  await expect(panel.getByLabel('संबंध')).toHaveValue('grandparent');
  await expect(panel.getByRole('button', { name: 'अभिभावक लिंक सहेजें' })).toBeVisible();
  await expect(panel.getByText('केवल अभिभावक भूमिका वाले सक्रिय कार्यस्थान सदस्य ही जोड़े जा सकते हैं।')).toBeVisible();
  await expect(panel.getByText('कोई सक्रिय अभिभावक लिंक नहीं है। ज़रूरत हो तो पहले अभिभावक सदस्य जोड़ें।')).toBeVisible();
});

test('course creation form follows the Hindi locale and preserves its API values', async ({ page }) => {
  const organizationId = 'course-create-locale-workspace';
  let createdCourse: Record<string, unknown> | null = null;
  await page.addInitScript(() => {
    localStorage.setItem('paathnexus.locale', 'hi');
    sessionStorage.setItem('paathnexus.session-token', 'course-create-locale-token');
    sessionStorage.setItem('paathnexus.active-organization', 'course-create-locale-workspace');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const method = route.request().method();
    const send = (data: unknown) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({
      user: { name: 'Asha Educator', email: 'asha@example.test' },
      organizations: [{ id: organizationId, name: 'Hindi Academy', slug: 'hindi-academy', type: 'school', role: 'educator', permissions: ['workspace.view', 'courses.view', 'courses.manage', 'academic.manage'], locale: 'hi', timezone: 'Asia/Kolkata' }],
    });
    if (path.endsWith('/dashboard')) return send({ active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0, unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4 });
    if (path.endsWith('/courses') && method === 'POST') {
      createdCourse = route.request().postDataJSON() as Record<string, unknown>;
      return send({ id: 'course-locale-1', code: createdCourse.code, title: createdCourse.title, description: createdCourse.description, status: 'active', is_public: false, price_paise: createdCourse.price_paise, academic_year_id: null, academic_year: null, subject_id: null, subject: null, instructors: [], lessons_count: 0, enrollments_count: 0 });
    }
    if (path.endsWith('/courses') && method === 'GET') return send(createdCourse ? [{ id: 'course-locale-1', code: createdCourse.code, title: createdCourse.title, description: createdCourse.description, status: 'active', is_public: false, price_paise: createdCourse.price_paise, academic_year_id: null, academic_year: null, subject_id: null, subject: null, instructors: [], lessons_count: 0, enrollments_count: 0 }] : []);
    return send([]);
  });

  await page.goto('/');
  await page.getByRole('navigation', { name: 'मुख्य नेविगेशन' }).getByRole('button', { name: 'कोर्स', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'कोर्स और पाठ' })).toBeVisible();
  await page.getByLabel('कोर्स का शीर्षक').fill('जीव विज्ञान की नींव');
  await page.getByLabel('कोर्स कोड').fill('BIO-101');
  await page.getByLabel('विवरण', { exact: true }).fill('जीवन विज्ञान की मूल बातें');
  await page.getByLabel('कोर्स शुल्क (INR)').fill('125.50');
  await page.getByRole('button', { name: 'कोर्स बनाएँ' }).click();
  await expect.poll(() => createdCourse).toMatchObject({ code: 'BIO-101', title: 'जीव विज्ञान की नींव', description: 'जीवन विज्ञान की मूल बातें', price_paise: 12550 });
  await expect(page.getByText('जीव विज्ञान की नींव', { exact: true }).first()).toBeVisible();
});

test('workspace managers can configure narrowed admin and educator profiles', async ({ page }) => {
  const organizationId = 'custom-manager-roles-workspace';
  await page.setViewportSize({ width: 1280, height: 1000 });
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'custom-manager-roles-token');
    sessionStorage.setItem('paathnexus.active-organization', 'custom-manager-roles-workspace');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({
      user: { name: 'Workspace Owner', email: 'owner@example.test' },
      organizations: [{ id: organizationId, name: 'Role Academy', slug: 'role-academy', type: 'school', role: 'owner', permissions: ['*'], locale: 'en', timezone: 'Asia/Kolkata' }],
    });
    if (path.endsWith('/roles')) return send({
      base_roles: ['admin', 'teacher', 'student', 'guardian', 'educator'],
      permissions_by_role: {
        admin: ['organization.view', 'organization.update', 'members.view', 'members.manage', 'academic.view', 'academic.manage'],
        teacher: ['organization.view', 'academic.view', 'attendance.read', 'courses.view', 'courses.manage'],
        student: ['organization.view', 'courses.view', 'results.view', 'attendance.read'],
        guardian: ['organization.view', 'students.view', 'attendance.read', 'results.view', 'courses.view'],
        educator: ['organization.view', 'organization.update', 'members.view', 'members.manage', 'academic.view', 'academic.manage', 'courses.view', 'courses.manage', 'tasks.view', 'tasks.manage'],
      },
      roles: [], members: [],
    });
    if (path.endsWith('/dashboard')) return send({ active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0, unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null });
    return send([]);
  });

  await page.goto('/');
  await page.getByRole('button', { name: 'Settings', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Custom member roles' })).toBeVisible();
  await expectNoHorizontalOverflow(page);
  const memberType = page.getByLabel('Member type');
  await memberType.selectOption('admin');
  await expect(page.getByLabel('Manage workspace members')).toBeVisible();
  await memberType.selectOption('educator');
  await expect(page.getByText(/do not inherit the educator role’s wildcard access/i)).toBeVisible();
  await expect(page.getByLabel('Create and manage staff tasks')).toBeVisible();
});

test('workspace manager can create a Hindi invitation and see its private link', async ({ page }) => {
  const organizationId = 'invitation-locale-org';
  let createdInvitation: Record<string, unknown> | null = null;
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'invitation-locale-token');
    sessionStorage.setItem('paathnexus.active-organization', 'invitation-locale-org');
    localStorage.setItem('paathnexus.locale', 'hi');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown, extra: Record<string, unknown> = {}) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data, ...extra }) });
    if (path.endsWith('/auth/me')) return send({ user: { name: 'Workspace Admin', email: 'admin@example.test' }, organizations: [{ id: organizationId, name: 'Fixture Academy', slug: 'fixture-academy', type: 'school', role: 'admin', permissions: ['workspace.view', 'academic.view', 'academic.manage', 'members.manage'], locale: 'hi', timezone: 'Asia/Kolkata' }] });
    if (path.endsWith('/dashboard')) return send({ active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0, unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null });
    if (path.endsWith(`/organizations/${organizationId}/invitations`) && route.request().method() === 'POST') {
      const payload = route.request().postDataJSON() as { email: string; role: 'teacher' | 'student' | 'guardian' };
      createdInvitation = { id: 'invite-locale-id', email: payload.email, role: payload.role, status: 'pending', inviter_name: 'Workspace Admin', expires_at: '2026-11-27T10:00:00Z', accepted_at: null, created_at: '2026-11-20T10:00:00Z' };
      return send(createdInvitation, { token: 'a'.repeat(64) });
    }
    if (path.endsWith(`/organizations/${organizationId}/invitations`)) return send(createdInvitation ? [createdInvitation] : []);
    return send([]);
  });

  await page.goto('/');
  await page.getByRole('navigation', { name: 'मुख्य नेविगेशन' }).getByRole('button', { name: 'विद्यार्थी', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'सदस्य आमंत्रण' })).toBeVisible();
  await page.getByLabel('ईमेल पता').fill('teacher@example.test');
  await page.getByLabel('कार्यस्थान भूमिका').selectOption('teacher');
  await page.getByRole('button', { name: 'आमंत्रण बनाएँ' }).click();
  await expect(page.getByRole('status')).toContainText('ईमेल भेजने की सुविधा सेट नहीं है');
  await expect(page.getByLabel('एक बार इस्तेमाल होने वाला आमंत्रण लिंक')).toHaveValue(/\?invitation=/);
  await expect(page.getByText(/शिक्षक · लंबित · समाप्ति/)).toBeVisible();
});

test('live classroom controls and attendance follow the Hindi locale', async ({ page }) => {
  const organizationId = 'live-session-locale-org';
  let submittedAttendance: unknown;
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'live-session-locale-token');
    sessionStorage.setItem('paathnexus.active-organization', 'live-session-locale-org');
    localStorage.setItem('paathnexus.locale', 'hi');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({ user: { name: 'Workspace Teacher', email: 'teacher@example.test' }, organizations: [{ id: organizationId, name: 'Fixture Academy', slug: 'fixture-academy', type: 'school', role: 'teacher', permissions: ['workspace.view', 'academic.view', 'live_sessions.view', 'attendance.write'], locale: 'hi', timezone: 'Asia/Kolkata' }] });
    if (path.endsWith('/dashboard')) return send({ active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0, unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null });
    if (path.endsWith(`/organizations/${organizationId}/live-sessions/${'live-session-fixture'}/roster`)) return send([{ id: 'student-1', name: 'Asha Student', student_code: 'ST-001' }]);
    if (path.endsWith(`/organizations/${organizationId}/live-sessions/${'live-session-fixture'}/attendance`)) { submittedAttendance = route.request().postDataJSON(); return send({ saved: true }); }
    if (path.endsWith(`/organizations/${organizationId}/live-sessions`)) return send([{ id: 'live-session-fixture', title: 'Science review', description: null, class_id: 'class-1', class_name: 'Class 6', section_id: null, section_name: null, course_id: null, course_title: null, provider: 'google_meet', starts_at: '2026-11-20T10:00:00Z', ends_at: '2026-11-20T11:00:00Z', status: 'live', join_available: true, recording_url: null }]);
    return send([]);
  });

  await page.goto('/');
  await page.getByRole('navigation', { name: 'मुख्य नेविगेशन' }).getByRole('button', { name: 'समय-सारणी', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'लाइव कक्षाएँ' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'बैठक में शामिल हों' })).toBeVisible();
  await expect(page.getByText('Google Meet', { exact: false })).toBeVisible();
  await page.getByRole('button', { name: 'उपस्थिति दर्ज करें' }).click();
  await expect(page.getByText('सत्र की उपस्थिति', { exact: true })).toBeVisible();
  await page.locator('.live-attendance-form select').selectOption('absent');
  await page.getByRole('button', { name: 'उपस्थिति सहेजें' }).click();
  await expect.poll(() => submittedAttendance).toEqual({ records: [{ student_id: 'student-1', status: 'absent' }] });
});

test('restricted admin navigation follows the effective capability profile', async ({ page }) => {
  const organizationId = 'restricted-admin-navigation-workspace';
  await page.setViewportSize({ width: 1280, height: 1000 });
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'restricted-admin-navigation-token');
    sessionStorage.setItem('paathnexus.active-organization', 'restricted-admin-navigation-workspace');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({
      user: { name: 'Limited Admin', email: 'limited-admin@example.test' },
      organizations: [{ id: organizationId, name: 'Restricted Academy', slug: 'restricted-academy', type: 'school', role: 'admin', permissions: ['organization.view'], locale: 'en', timezone: 'Asia/Kolkata' }],
    });
    if (path.endsWith('/dashboard')) return send({ active_students_count: null, active_classes_count: null, active_enrollments_count: null, unassigned_students_count: null, pending_tasks_count: null, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null });
    return send([]);
  });

  await page.goto('/');
  const mainNavigation = page.getByRole('navigation', { name: 'Main navigation' });
  await expect(mainNavigation.getByRole('button', { name: 'Overview' })).toBeVisible();
  for (const destination of ['Students', 'Courses', 'Schedule', 'Attendance', 'Settings']) {
    await expect(page.getByRole('button', { name: destination, exact: true })).toHaveCount(0);
  }
  const toolsNavigation = page.getByRole('navigation', { name: 'Tools' });
  await expect(toolsNavigation.getByRole('button', { name: /AI learning/ })).toHaveCount(0);
  await expect(toolsNavigation.getByRole('button', { name: 'Announcements' })).toHaveCount(0);
});

test('learner AI tutor remains accessible after switching workspaces', async ({ page }) => {
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'workspace-switch-fixture-token');
    sessionStorage.setItem('paathnexus.active-organization', 'staff-workspace-fixture');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const data = path.endsWith('/auth/me')
      ? { user: { name: 'Sam Educator', email: 'sam@example.test' }, organizations: [
        { id: 'staff-workspace-fixture', name: 'Staff Academy', slug: 'staff-academy', type: 'school', role: 'educator', permissions: ['workspace.view', 'courses.view', 'courses.manage', 'academic.manage'], locale: 'en', timezone: 'Asia/Kolkata' },
        { id: 'student-workspace-fixture', name: 'Student Academy', slug: 'student-academy', type: 'school', role: 'student', permissions: ['workspace.view', 'courses.view', 'assessments.take'], locale: 'en', timezone: 'Asia/Kolkata' },
      ] }
      : path.endsWith('/student/dashboard')
        ? { student: null, study_recommendations: [], courses: [], assignments: [], assessment_results: [], today_schedule: [], schedule_notice: null, attendance: { sessions_recorded: 0, present_or_late: 0, rate_percent: null, recent: [] } }
      : path.endsWith('/dashboard')
        ? { active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0, unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null }
      : [];
    return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
  });

  await page.goto('/');
  await page.getByRole('navigation', { name: 'Tools' }).getByRole('button', { name: /AI learning/ }).click();
  await expect(page.getByRole('heading', { name: 'Ask your learning tutor' })).toBeVisible();
  await page.locator('.workspace-switch').click();
  await page.getByRole('menuitemradio', { name: /Student Academy/ }).click();
  await expect(page.getByRole('heading', { name: 'Ask your learning tutor' })).toBeVisible();
  await expect(page.getByText('student workspace', { exact: true })).toBeVisible();
});

test('learner can complete a lesson and submit a quiz using isolated API fixtures', async ({ page }) => {
  const organizationId = 'org-fixture';
  const courseId = 'course-fixture';
  const lessonId = 'lesson-fixture';
  const assessmentId = 'assessment-fixture';
  const attemptId = 'attempt-fixture';
  let lessonCompleted = false;
  let answerSaved = false;
  let quizSubmitted = false;
  let bankQuestionRequested = false;

  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'e2e-fixture-token');
    sessionStorage.setItem('paathnexus.active-organization', 'org-fixture');
  });
  const timezoneSession = await page.context().newCDPSession(page);
  await timezoneSession.send('Emulation.setTimezoneOverride', { timezoneId: 'America/Los_Angeles' });
  await page.route('**/api/v1/**', async (route) => {
    const url = new URL(route.request().url());
    const path = url.pathname;
    const method = route.request().method();
    const send = (data: unknown) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });

    if (path.endsWith('/auth/me')) {
      return send({
        user: { name: 'Asha Learner', email: 'asha@example.test' },
        organizations: [{ id: organizationId, name: 'Fixture Academy', slug: 'fixture-academy', type: 'school', role: 'student', permissions: ['workspace.view', 'courses.view', 'assessments.take', 'results.view'], locale: 'en', timezone: 'Asia/Kolkata' }],
      });
    }
    if (path.endsWith('/student/dashboard')) {
      return send({ student: { name: 'Asha Learner', student_code: 'ST-001' }, study_recommendations: [], courses: [], assignments: [{ id: 'assignment-fixture', title: 'Submit cell model', course_title: 'Foundations of Biology', due_at: '2026-06-15T04:00:00Z', submission_status: null }], assessment_results: [], today_schedule: [], schedule_notice: null, attendance: { sessions_recorded: 0, present_or_late: 0, rate_percent: null, recent: [] } });
    }
    if (path.endsWith('/courses') && method === 'GET') {
      return send([{ id: courseId, code: 'BIO-101', title: 'Foundations of Biology', description: 'A fixture course for browser coverage.', status: 'active', is_public: false, price_paise: 0, academic_year_id: null, academic_year: null, subject_id: null, subject: null, instructors: [], lessons_count: 1, enrollments_count: 1 }]);
    }
    if (path.endsWith(`/courses/${courseId}/lessons/${lessonId}/progress`) && method === 'PUT') {
      lessonCompleted = (JSON.parse(route.request().postData() ?? '{}') as { status?: string }).status === 'completed';
      return route.fulfill({ status: 204, body: '' });
    }
    if (path.endsWith(`/courses/${courseId}/lessons`)) {
      return send([{ id: lessonId, course_id: courseId, title: 'Cells and the basics of life', content: 'Cells are the basic structural and functional units of living organisms.', resource_type: null, resource_url: null, prerequisites: [], prerequisites_met: true, position: 1, status: 'active', my_progress: lessonCompleted ? 'completed' : 'not_started' }]);
    }
    if (path.endsWith(`/courses/${courseId}/assignments`)) return send([{ id: 'assignment-accessible-fixture', course_id: courseId, lesson_id: null, lesson_title: null, title: 'Cell model reflection', instructions: null, due_at: null, max_points: 10, status: 'published', submissions_count: 0, my_submission: null, resource_attachments: [] }]);
    if (path.endsWith(`/courses/${courseId}/questions`)) {
      bankQuestionRequested = true;
      return route.fulfill({ status: 403, contentType: 'application/json', body: JSON.stringify({ message: 'Forbidden' }) });
    }
    if (path.endsWith(`/courses/${courseId}/assessments`)) {
      return send([{ id: assessmentId, title: 'Cell knowledge check', instructions: null, duration_minutes: 10, attempts_allowed: 2, randomize_questions: false, randomized_question_count: null, status: 'published', question_count: 1, my_attempts: quizSubmitted ? [{ id: attemptId, attempt_number: 1, status: 'submitted', review_status: 'not_required', score: 1, max_points: 1, started_at: '2026-11-19T09:00:00Z', expires_at: null, submitted_at: '2026-11-19T09:01:00Z' }] : [] }]);
    }
    if (path.endsWith(`/assessments/${assessmentId}/attempts`) && method === 'POST') {
      return send({ id: attemptId, attempt_number: 1, status: 'in_progress', review_status: 'not_required', score: null, max_points: 1, started_at: '2026-11-19T09:00:00Z', expires_at: '2026-11-19T09:10:00Z', submitted_at: null, questions: [{ id: 'question-fixture', prompt: 'What is the basic unit of life?', type: 'single_choice', points: 1, options: [{ id: 'option-correct', text: 'The cell' }, { id: 'option-wrong', text: 'The atom' }], selected_option_ids: [], response_text: null }] });
    }
    if (path.endsWith(`/assessments/${assessmentId}/attempts/${attemptId}/answers/question-fixture`) && method === 'PUT') {
      answerSaved = true;
      return route.fulfill({ status: 204, body: '' });
    }
    if (path.endsWith(`/assessments/${assessmentId}/attempts/${attemptId}/submit`) && method === 'POST') {
      quizSubmitted = true;
      return send({ id: attemptId, attempt_number: 1, status: 'submitted', review_status: 'not_required', score: 1, max_points: 1, started_at: '2026-11-19T09:00:00Z', expires_at: null, submitted_at: '2026-11-19T09:01:00Z' });
    }
    if (path.endsWith(`/assessments/${assessmentId}/attempts/${attemptId}/result`)) {
      return send({ attempt: { id: attemptId, attempt_number: 1, status: 'submitted', review_status: 'not_required', score: 1, max_points: 1, started_at: '2026-11-19T09:00:00Z', expires_at: null, submitted_at: '2026-11-19T09:01:00Z' }, answers: [{ question_id: 'question-fixture', prompt: 'What is the basic unit of life?', type: 'single_choice', points: 1, points_awarded: 1, response_text: null, selected_options: [{ id: 'option-correct', text: 'The cell' }], grader_feedback: 'Correct answer.', pending_review: false }] });
    }
    return send([]);
  });

  await page.goto('/');
  await expectNoHorizontalOverflow(page);
  const mainNavigation = page.getByRole('navigation', { name: 'Main navigation' });
  await mainNavigation.getByRole('button', { name: 'My learning' }).click();
  await expectNoHorizontalOverflow(page);
  await expect(page.getByText('Your learning', { exact: false }).first()).toBeVisible();
  await expect(page.getByText(/15 Jun 2026/)).toBeVisible();
  await mainNavigation.getByRole('button', { name: 'Courses', exact: true }).click();
  await expectNoHorizontalOverflow(page);
  await expect(page.getByText('Foundations of Biology', { exact: true }).first()).toBeVisible();
  await expect(page.getByText('Cells are the basic structural and functional units of living organisms.')).toBeVisible();
  await expect(page.getByRole('textbox', { name: 'Submission for Cell model reflection' })).toBeVisible();
  await expect(page.getByRole('textbox', { name: 'Submission for Cell model reflection' })).toBeVisible();
  await page.getByRole('button', { name: 'Complete', exact: true }).click();
  await expect.poll(() => lessonCompleted).toBe(true);

  await expect(page.getByText('Cell knowledge check', { exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'हिन्दी' }).click();
  await page.getByRole('button', { name: 'क्विज़ शुरू करें' }).click();
  await expect(page.getByText('What is the basic unit of life?')).toBeVisible();
  await expect(page.getByText(/समय सीमा .* पर समाप्त होगी/)).toBeVisible();
  await page.getByLabel('The cell', { exact: true }).click();
  await expect.poll(() => answerSaved).toBe(true);
  await page.getByRole('button', { name: 'क्विज़ जमा करें' }).click();
  await expect.poll(() => quizSubmitted).toBe(true);
  await expect(page.getByText('प्रयास 1: 1 / 1 अंक')).toBeVisible();
  await expect(page.getByRole('heading', { name: 'मेरे उत्तर और प्रतिक्रिया' })).toBeVisible();
  await page.getByRole('button', { name: 'उत्तर और प्रतिक्रिया देखें' }).click();
  await expect(page.getByText('आपका उत्तर: The cell')).toBeVisible();
  await expect(page.getByText('शिक्षक की प्रतिक्रिया: Correct answer.')).toBeVisible();
  await page.getByRole('button', { name: 'English' }).click();
  await page.getByRole('navigation', { name: 'Tools' }).getByRole('button', { name: /AI learning/ }).click();
  await expect(page.getByRole('heading', { name: 'Ask your learning tutor' })).toBeVisible();
  expect(bankQuestionRequested).toBe(false);
});

test('educator AI tutor and teaching draft controls follow the Hindi locale', async ({ page }) => {
  const organizationId = 'ai-locale-workspace';
  let draftRequest: { draft_type: string; topic: string; language: string } | undefined;
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'ai-locale-token');
    sessionStorage.setItem('paathnexus.active-organization', 'ai-locale-workspace');
    localStorage.setItem('paathnexus.locale', 'hi');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({ user: { name: 'Asha Educator', email: 'educator@example.test' }, organizations: [{ id: organizationId, name: 'Fixture Academy', slug: 'fixture-academy', type: 'school', role: 'teacher', permissions: ['workspace.view', 'courses.view', 'courses.manage'], locale: 'hi', timezone: 'Asia/Kolkata' }] });
    if (path.endsWith('/dashboard')) return send({ active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0, unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null });
    if (path.endsWith(`/organizations/${organizationId}/courses`) && route.request().method() === 'GET') return send([]);
    if (path.endsWith(`/organizations/${organizationId}/ai/tutor`)) return send({ answer: 'भिन्न समान भागों के अनुपात को दर्शाते हैं।', usage: { input_tokens: 5, output_tokens: 9 } });
    if (path.endsWith(`/organizations/${organizationId}/ai/teacher-assistant`)) {
      draftRequest = route.request().postDataJSON() as { draft_type: string; topic: string; language: string };
      return send({ draft: 'भिन्नों पर पाँच मिनट का परिचय।', human_review_required: true, usage: { input_tokens: 8, output_tokens: 12 } });
    }
    return send([]);
  });

  await page.goto('/');
  await page.getByRole('navigation', { name: 'साधन' }).getByRole('button', { name: 'AI सीखना' }).click();
  await expect(page.getByRole('heading', { name: 'अपने लर्निंग ट्यूटर से पूछें' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'शिक्षण मसौदा तैयार करें' })).toBeVisible();
  await expect(page.getByText(/नाम, फ़ोन नंबर, ईमेल या पहचान संख्या शामिल न करें/)).toBeVisible();
  await page.getByLabel('आपका प्रश्न').fill('समतुल्य भिन्न क्या होते हैं?');
  await page.getByRole('button', { name: 'ट्यूटर से पूछें' }).click();
  await expect(page.getByText('भिन्न समान भागों के अनुपात को दर्शाते हैं।')).toBeVisible();
  await page.getByLabel('विषय').fill('समतुल्य भिन्न');
  await page.locator('.ai-teacher-assistant-panel select').nth(1).selectOption('hi');
  await page.getByRole('button', { name: 'मसौदा बनाएँ' }).click();
  await expect(page.getByLabel('तैयार शिक्षण मसौदा')).toHaveValue('भिन्नों पर पाँच मिनट का परिचय।');
  expect(draftRequest).toMatchObject({ draft_type: 'lesson_plan', topic: 'समतुल्य भिन्न', language: 'hi' });
});

test('platform admin sees aggregate usage and daily history for workspaces', async ({ page }) => {
  const organizationId = 'platform-admin-org-fixture';
  let lookedUpEmail = '';
  let moderationStatus: 'visible' | 'hidden' = 'visible';
  let moderationReason: string | null = null;
  let recoveryRequested = false;
  let aiTutorFlag = { key: 'ai_tutor', label: 'AI Tutor', description: 'Allow students and educators to use the workspace AI tutor.', enabled: true, default_enabled: true, updated_at: null, updated_by_id: null };
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'platform-admin-fixture-token');
    sessionStorage.setItem('paathnexus.active-organization', 'platform-admin-org-fixture');
    localStorage.setItem('paathnexus.locale', 'hi');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown, extra: Record<string, unknown> = {}) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data, ...extra }) });
    if (path.endsWith('/auth/me')) return send({ user: { name: 'Platform Admin', email: 'platform@example.test', is_platform_admin: true }, organizations: [{ id: organizationId, name: 'Admin Workspace', slug: 'admin-workspace', type: 'school', role: 'owner', permissions: ['workspace.view'], locale: 'en', timezone: 'Asia/Kolkata' }] });
    if (path.endsWith('/dashboard')) return send({ active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0, unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null });
    if (path.endsWith('/platform/usage')) return send({ organization_count: 3, active_organization_count: 2, suspended_organization_count: 1, active_membership_count: 4, active_student_count: 3, active_course_count: 5, active_subscription_count: 2 });
    if (path.endsWith('/platform/usage/history')) return send({ days: 90, snapshots: [{ snapshot_date: '2026-11-18', organization_count: 2, active_organization_count: 1, suspended_organization_count: 1, active_membership_count: 2, active_student_count: 1, active_course_count: 2, active_subscription_count: 1 }, { snapshot_date: '2026-11-19', organization_count: 3, active_organization_count: 2, suspended_organization_count: 1, active_membership_count: 4, active_student_count: 3, active_course_count: 5, active_subscription_count: 2 }] });
    if (path.endsWith('/platform/organizations')) return send([{ id: 'directory-school', name: 'Sample School', slug: 'sample-school', type: 'school', status: 'active', current_plan_name: 'Learning plan', subscription_status: 'active', active_members_count: 2, active_students_count: 1, active_courses_count: 3, created_at: '2026-06-03T00:00:00Z' }], { meta: { total: 1, current_page: 1, last_page: 1 } });
    if (path.endsWith('/platform/audit-logs')) return send([{ id: 1, actor_id: null, event: 'feature_flag.disabled', subject_type: 'feature_flag', subject_id: 'ai_tutor', created_at: '2026-11-19T10:00:00Z' }]);
    if (path.endsWith('/platform/users/lookup') && route.request().method() === 'POST') {
      lookedUpEmail = (route.request().postDataJSON() as { email: string }).email;
      return send({ id: 42, name: 'Synthetic Learner', email: lookedUpEmail, account_status: 'active', email_verified_at: '2026-11-18T10:00:00Z', created_at: '2026-11-17T10:00:00Z', workspaces: [{ id: 'member-school', name: 'North Academy', slug: 'north-academy', organization_status: 'active', role: 'student', membership_status: 'active' }] });
    }
    if (path.endsWith('/platform/users/42/password-reset') && route.request().method() === 'POST') {
      recoveryRequested = true;
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ message: 'If the account is eligible, a password recovery link has been sent through the configured mail service.' }) });
    }
    if (path.includes('/platform/content-moderation/') && route.request().method() === 'PUT') {
      const update = route.request().postDataJSON() as { status: 'visible' | 'hidden'; reason: string | null };
      moderationStatus = update.status; moderationReason = update.reason;
      return send({ type: 'course', id: '33333333-3333-4333-8333-333333333333', title: 'Public Biology', organization_id: 'member-school', organization_name: 'North Academy', status: moderationStatus, reason: moderationReason, moderated_at: '2026-11-20T10:00:00Z' });
    }
    if (path.endsWith('/platform/content-moderation')) return send([{ type: 'course', id: '33333333-3333-4333-8333-333333333333', title: 'Public Biology', organization_id: 'member-school', organization_name: 'North Academy', status: moderationStatus, reason: moderationReason, moderated_at: moderationStatus === 'hidden' ? '2026-11-20T10:00:00Z' : null }], { meta: { total: 1, current_page: 1, last_page: 1 } });
    if (path.endsWith('/platform/feature-flags/ai_tutor') && route.request().method() === 'PUT') { aiTutorFlag = { ...aiTutorFlag, enabled: false }; return send(aiTutorFlag); }
    if (path.endsWith('/platform/feature-flags')) return send([aiTutorFlag]);
    return send([]);
  });

  await page.goto('/');
  await page.getByRole('navigation', { name: 'साधन' }).getByRole('button', { name: 'प्लेटफ़ॉर्म व्यवस्थापक' }).click();
  await expect(page.getByRole('heading', { name: 'प्लेटफ़ॉर्म योजनाएँ' })).toBeVisible();
  await expect(page.getByText('प्लेटफ़ॉर्म उपयोग')).toBeVisible();
  await expect(page.getByText('सक्रिय: 2 · निलंबित: 1')).toBeVisible();
  const metrics = page.locator('.report-metrics .report-metric');
  await expect(metrics).toHaveCount(5);
  await expect(metrics.nth(0)).toContainText('3');
  await expect(metrics.nth(1)).toContainText('4');
  await expect(metrics.nth(2)).toContainText('3');
  await expect(metrics.nth(3)).toContainText('5');
  await expect(metrics.nth(4)).toContainText('2');
  await expect(page.getByRole('heading', { name: 'दैनिक प्लेटफ़ॉर्म स्नैपशॉट' })).toBeVisible();
  const chart = page.getByRole('img', { name: '2026-11-18 से 2026-11-19 तक सक्रिय कार्यस्थानों का इतिहास; 2 दैनिक स्नैपशॉट।' });
  await expect(chart).toBeVisible();
  const bars = chart.locator('span');
  await expect(bars).toHaveCount(2);
  await expect(bars.nth(0)).toHaveAttribute('title', '2026-11-18: 1 सक्रिय कार्यस्थान');
  await expect(bars.nth(1)).toHaveAttribute('title', '2026-11-19: 2 सक्रिय कार्यस्थान');
  const history = page.getByRole('region', { name: 'दैनिक प्लेटफ़ॉर्म उपयोग स्नैपशॉट' });
  await expect(history.getByRole('columnheader')).toHaveCount(8);
  expect(await history.getByRole('columnheader').allTextContents()).toEqual(['तारीख', 'कार्यस्थान', 'सक्रिय', 'निलंबित', 'सक्रिय सदस्य', 'सक्रिय विद्यार्थी', 'सक्रिय कोर्स', 'सक्रिय सदस्यताएँ']);
  const rows = history.locator('tbody tr');
  await expect(rows).toHaveCount(2);
  await expect(rows.nth(0).getByRole('cell').nth(0)).toHaveText('19 नव॰ 2026');
  await expect(rows.nth(1).getByRole('cell').nth(0)).toHaveText('18 नव॰ 2026');
  await expect(rows.nth(0).getByRole('cell').nth(2)).toHaveText('2');
  await expect(rows.nth(1).getByRole('cell').nth(2)).toHaveText('1');
  await expect(page.locator('.platform-organizations .course-progress-heading strong')).toHaveText('संस्थाएँ');
  await expect(page.getByText('Sample School', { exact: true })).toBeVisible();
  await expect(page.getByText('2 सदस्य')).toBeVisible();
  await expect(page.getByRole('button', { name: 'कार्यस्थान निलंबित करें' })).toBeVisible();
  await expect(page.locator('#platform-feature-flags-title')).toBeVisible();
  await expect(page.getByRole('button', { name: 'AI ट्यूटर बंद करें' })).toHaveAttribute('aria-pressed', 'true');
  await page.getByRole('button', { name: 'AI ट्यूटर बंद करें' }).click();
  await expect(page.getByRole('button', { name: 'AI ट्यूटर चालू करें' })).toHaveAttribute('aria-pressed', 'false');
  await page.getByLabel('खाते का ईमेल').fill('learner@example.test');
  await page.getByRole('button', { name: 'खाता खोजें' }).click();
  await expect(page.getByText('Synthetic Learner')).toBeVisible();
  await expect(page.getByText('North Academy', { exact: true })).toBeVisible();
  expect(lookedUpEmail).toBe('learner@example.test');
  await page.getByRole('button', { name: 'पासवर्ड रीसेट लिंक भेजें' }).click();
  await expect(page.getByText('यदि खाता पात्र है, तो कॉन्फ़िगर की गई ईमेल सेवा से रिकवरी लिंक भेज दिया गया है।')).toBeVisible();
  expect(recoveryRequested).toBe(true);
  await expect(page.getByText('सार्वजनिक सामग्री मॉडरेशन', { exact: true })).toBeVisible();
  await expect(page.getByText('Public Biology')).toBeVisible();
  await page.getByLabel('मॉडरेशन टिप्पणी').fill('नीति समीक्षा');
  await page.getByRole('button', { name: 'सार्वजनिक सूची से छिपाएँ' }).click();
  await expect(page.getByText('छिपा हुआ')).toBeVisible();
  expect(moderationStatus).toBe('hidden');
  expect(moderationReason).toBe('नीति समीक्षा');
  await expect(page.getByText('प्लेटफ़ॉर्म की हाल की गतिविधि')).toBeVisible();
});

test('platform admin plan authoring and review follow the Hindi locale', async ({ page }) => {
  const organizationId = 'platform-plan-locale-org';
  const plan = { id: 'plan-pro', code: 'pro', name: 'Pro', audience: 'institution', description: null, amount_paise: 150000, billing_interval: 'month', entitlements: { max_students: 250 }, is_active: true };
  const requestedPlan = { ...plan, id: 'plan-growth', code: 'growth', name: 'Growth', amount_paise: 300000 };
  let resolution: { decision: string; verification_reference: string } | undefined;
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'platform-plan-locale-token');
    sessionStorage.setItem('paathnexus.active-organization', 'platform-plan-locale-org');
    localStorage.setItem('paathnexus.locale', 'hi');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown, extra: Record<string, unknown> = {}) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data, ...extra }) });
    if (path.endsWith('/auth/me')) return send({ user: { name: 'Platform Admin', email: 'platform@example.test', is_platform_admin: true }, organizations: [{ id: organizationId, name: 'Admin Workspace', slug: 'admin-workspace', type: 'school', role: 'owner', permissions: ['workspace.view'], locale: 'hi', timezone: 'Asia/Kolkata' }] });
    if (path.endsWith('/dashboard')) return send({ active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0, unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null });
    if (path.endsWith('/platform/plans')) return send([plan]);
    if (path.endsWith('/platform/subscription-requests/tenant-request/resolve') && route.request().method() === 'POST') {
      resolution = route.request().postDataJSON() as { decision: string; verification_reference: string };
      return send({ status: 'active' });
    }
    if (path.endsWith('/platform/subscription-requests')) return send([{ organization_id: 'tenant-request', organization_name: 'Sample School', plan, requested_plan: requestedPlan, requested_at: '2026-11-20T09:00:00Z' }]);
    if (path.endsWith('/platform/organizations')) return send([], { meta: { total: 0, current_page: 1, last_page: 1 } });
    if (path.endsWith('/platform/audit-logs')) return send([], { meta: { total: 0, current_page: 1, last_page: 1 } });
    if (path.endsWith('/platform/usage')) return send({ organization_count: 0, active_organization_count: 0, suspended_organization_count: 0, active_membership_count: 0, active_student_count: 0, active_course_count: 0, active_subscription_count: 0 });
    if (path.endsWith('/platform/usage/history')) return send({ days: 90, snapshots: [] });
    if (path.endsWith('/platform/feature-flags')) return send([]);
    return send([]);
  });

  await page.goto('/');
  await page.getByRole('navigation', { name: 'साधन' }).getByRole('button', { name: 'प्लेटफ़ॉर्म व्यवस्थापक' }).click();
  await expect(page.getByRole('heading', { name: 'संस्थाएँ, योजनाएँ और सुविधाएँ' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'योजना बनाएँ' })).toBeVisible();
  await expect(page.getByLabel('लक्षित समूह')).toBeVisible();
  await expect(page.getByLabel('मासिक/वार्षिक मूल्य (INR; कस्टम के लिए खाली छोड़ें)')).toBeVisible();
  await expect(page.getByText('कॉन्फ़िगर की गई योजनाएँ')).toBeVisible();
  await expect(page.getByText('योजना बदलाव अनुरोध', { exact: true })).toBeVisible();
  await page.getByLabel('सत्यापित संदर्भ').fill('contract-ref-2026');
  await page.getByRole('button', { name: 'सत्यापन के बाद मंज़ूर करें' }).click();
  await expect.poll(() => resolution).toEqual({ decision: 'approve', verification_reference: 'contract-ref-2026' });
});

test('guardian sees only the learning summary enabled by their permission profile', async ({ page }) => {
  const organizationId = 'guardian-org-fixture';
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'guardian-fixture-token');
    sessionStorage.setItem('paathnexus.active-organization', 'guardian-org-fixture');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const data = path.endsWith('/auth/me')
      ? { user: { name: 'Mira Guardian', email: 'mira@example.test' }, organizations: [{ id: organizationId, name: 'Fixture Academy', slug: 'fixture-academy', type: 'school', role: 'guardian', permissions: ['workspace.view', 'courses.view'], locale: 'en', timezone: 'Asia/Kolkata' }] }
      : path.endsWith('/guardian/students')
        ? [{ student_id: 'student-fixture', student_name: 'Ravi Student', student_code: 'ST-002', relationship: 'parent', attendance: null, learning: { courses_enrolled: 2, completed_lessons: 3, total_lessons: 8, completion_percent: 38, assignments: [{ id: 'guardian-homework-1', title: 'Plant cell worksheet', course_title: 'Biology', due_at: '2026-12-01T00:00:00Z', submission_status: 'submitted' }] }, results: null }]
        : [];
    return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
  });

  await page.goto('/');
  await expect(page.getByRole('heading', { name: 'My children', level: 1 })).toBeVisible();
  await expect(page.getByText('Ravi Student', { exact: true })).toBeVisible();
  await expect(page.getByText('Lesson progress')).toBeVisible();
  await expect(page.getByText('38%')).toBeVisible();
  await expect(page.getByText('Homework status')).toBeVisible();
  await expect(page.getByText('Plant cell worksheet')).toBeVisible();
  await expect(page.getByText(/Submitted/)).toBeVisible();
  await expect(page.getByText('Attendance', { exact: true })).toHaveCount(0);
  await expect(page.getByText('Assessment average', { exact: true })).toHaveCount(0);
  await expectNoHorizontalOverflow(page);

  const download = page.waitForEvent('download');
  await page.getByRole('button', { name: 'Download summary' }).click();
  expect((await download).suggestedFilename()).toBe('ST-002-learning-summary.csv');
});

test('guardian family summary and homework status follow the selected Hindi locale', async ({ page }) => {
  const organizationId = 'guardian-locale-fixture';
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'guardian-locale-token');
    sessionStorage.setItem('paathnexus.active-organization', 'guardian-locale-fixture');
    localStorage.setItem('paathnexus.locale', 'hi');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({
      user: { name: 'Mira Guardian', email: 'guardian@example.test' },
      organizations: [{ id: organizationId, name: 'Sample Academy', slug: 'sample-academy', type: 'school', role: 'guardian', permissions: ['workspace.view', 'attendance.read', 'courses.view', 'results.view'], locale: 'en', timezone: 'Asia/Kolkata' }],
    });
    if (path.endsWith('/guardian/students')) return send([{
      student_id: 'guardian-locale-student', student_name: 'Ravi Student', student_code: 'ST-LOCALE-2', relationship: 'parent',
      attendance: { sessions_recorded: 2, present: 1, rate_percent: 50, recent: [{ date: '2026-11-20', class_name: 'Class 6A', status: 'present' }] },
      learning: { courses_enrolled: 1, completed_lessons: 2, total_lessons: 4, completion_percent: 50, assignments: [{ id: 'guardian-locale-homework', title: 'Plant cell worksheet', course_title: 'Biology', due_at: '2026-12-01T00:00:00Z', submission_status: 'submitted' }] },
      results: { assessments_completed: 1, average_percent: 80, recent: [{ title: 'Science quiz', score: 8, max_points: 10, completed_at: '2026-11-19T12:00:00Z' }], upcoming_exams: [{ title: 'Term science exam', course_title: 'Science', opens_at: '2026-12-03T04:00:00Z', closes_at: null }] },
    }]);
    if (path.endsWith('/notifications')) return send({ unread_count: 0, items: [] });
    return send([]);
  });

  await page.goto('/');
  await expect(page.getByRole('heading', { name: 'मेरे बच्चे', level: 1 })).toBeVisible();
  await expect(page.getByText('उपस्थिति', { exact: true })).toBeVisible();
  await expect(page.getByText('पाठ की प्रगति')).toBeVisible();
  await expect(page.getByText('मूल्यांकन औसत')).toBeVisible();
  await expect(page.getByText('आगामी परीक्षाएँ')).toBeVisible();
  await expect(page.getByText('गृहकार्य की स्थिति')).toBeVisible();
  await expect(page.getByText('जमा किया')).toBeVisible();
  await expect(page.getByRole('button', { name: 'सारांश डाउनलोड करें' })).toBeVisible();
});

test('guardian with results access sees a future exam for their linked learner', async ({ page }) => {
  const organizationId = 'guardian-exam-org-fixture';
  const opensAt = new Date(Date.now() + 86_400_000).toISOString();
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'guardian-exam-fixture-token');
    sessionStorage.setItem('paathnexus.active-organization', 'guardian-exam-org-fixture');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const data = path.endsWith('/auth/me')
      ? { user: { name: 'Mira Guardian', email: 'mira@example.test' }, organizations: [{ id: organizationId, name: 'Fixture Academy', slug: 'fixture-academy', type: 'school', role: 'guardian', permissions: ['workspace.view', 'courses.view', 'results.view'], locale: 'en', timezone: 'Asia/Kolkata' }] }
      : path.endsWith('/guardian/students')
        ? [{ student_id: 'student-fixture', student_name: 'Ravi Student', student_code: 'ST-002', relationship: 'parent', attendance: null, learning: { courses_enrolled: 1, completed_lessons: 0, total_lessons: 0, completion_percent: 0, assignments: [] }, results: { assessments_completed: 0, average_percent: null, recent: [], upcoming_exams: [{ title: 'Term science exam', course_title: 'Science', opens_at: opensAt, closes_at: null }] } }]
        : [];
    return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
  });

  await page.goto('/');
  await expect(page.getByRole('heading', { name: 'My children', level: 1 })).toBeVisible();
  await expect(page.getByText('Upcoming exams')).toBeVisible();
  await expect(page.getByText('Term science exam')).toBeVisible();
  await expect(page.getByText(/Opens/)).toBeVisible();
  await expectNoHorizontalOverflow(page);
});

test('assessment-only teacher can author quizzes without course-edit controls', async ({ page }) => {
  const organizationId = 'assessment-teacher-org-fixture';
  const courseId = 'assessment-teacher-course-fixture';
  await page.addInitScript(() => {
    localStorage.setItem('paathnexus.locale', 'hi');
    sessionStorage.setItem('paathnexus.session-token', 'assessment-teacher-fixture-token');
    sessionStorage.setItem('paathnexus.active-organization', 'assessment-teacher-org-fixture');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({ user: { name: 'Ari Teacher', email: 'ari@example.test' }, organizations: [{ id: organizationId, name: 'Fixture Academy', slug: 'fixture-academy', type: 'school', role: 'teacher', permissions: ['workspace.view', 'academic.view', 'reports.view', 'courses.view', 'assessments.manage'], locale: 'en', timezone: 'Asia/Kolkata' }] });
    if (path.endsWith('/dashboard')) return send({ active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0, unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null });
    if (path.endsWith('/courses') && route.request().method() === 'GET') return send([{ id: courseId, code: 'SCI-101', title: 'Assessment scope course', description: null, status: 'active', is_public: false, price_paise: 0, academic_year_id: null, academic_year: null, subject_id: null, subject: null, instructors: [], lessons_count: 0, enrollments_count: 0 }]);
    if (path.endsWith(`/courses/${courseId}/lessons`) || path.endsWith(`/courses/${courseId}/assignments`) || path.endsWith(`/courses/${courseId}/questions`) || path.endsWith(`/courses/${courseId}/assessments`)) return send([]);
    return send([]);
  });

  await page.goto('/');
  await page.getByRole('navigation', { name: 'मुख्य नेविगेशन' }).getByRole('button', { name: 'कोर्स', exact: true }).click();
  await expectNoHorizontalOverflow(page);
  await expect(page.getByRole('heading', { name: 'Assessment scope course' })).toBeVisible();
  await expect(page.getByText('क्विज़ और परीक्षाएँ', { exact: true })).toBeVisible();
  await expect(page.getByText('प्रश्न जोड़ें', { exact: true })).toBeVisible();
  await expect(page.getByLabel('प्रश्न', { exact: true })).toBeVisible();
  await expect(page.getByLabel('उत्तर का प्रकार')).toBeVisible();
  await expect(page.getByText('समयबद्ध क्विज़ प्रकाशित करें', { exact: true })).toBeVisible();
  await expect(page.getByLabel('क्विज़ का शीर्षक')).toBeVisible();
  await expect(page.getByLabel('प्रश्न खोजें')).toBeVisible();
  await expect(page.getByText('कोर्स बनाएँ', { exact: true })).toHaveCount(0);
  await expect(page.getByText('पाठ जोड़ें', { exact: true })).toHaveCount(0);
  await expect(page.getByRole('navigation', { name: 'Tools' }).getByRole('button', { name: 'Reports' })).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'This week' })).toHaveCount(0);
  await expect(page.getByRole('button', { name: /Take attendance/ })).toHaveCount(0);
});

test('educator grading fields identify each learner submission', async ({ page }) => {
  const organizationId = 'grading-accessibility-org';
  const courseId = 'grading-accessibility-course';
  const assignmentId = 'grading-accessibility-assignment';
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'grading-accessibility-token');
    sessionStorage.setItem('paathnexus.active-organization', 'grading-accessibility-org');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({ user: { name: 'Ari Educator', email: 'ari@example.test' }, organizations: [{ id: organizationId, name: 'Fixture Academy', slug: 'fixture-academy', type: 'school', role: 'educator', permissions: ['workspace.view', 'academic.view', 'courses.view', 'courses.manage'], locale: 'en', timezone: 'Asia/Kolkata' }] });
    if (path.endsWith('/dashboard')) return send({ active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0, unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null });
    if (path.endsWith('/courses') && route.request().method() === 'GET') return send([{ id: courseId, code: 'WRITING-1', title: 'Writing practice', description: null, status: 'active', is_public: false, price_paise: 0, academic_year_id: null, academic_year: null, subject_id: null, subject: null, instructors: [], lessons_count: 1, enrollments_count: 2 }]);
    if (path.endsWith(`/courses/${courseId}/assignments/${assignmentId}/submissions`)) return send([
      { id: 'submission-asha', status: 'submitted', submission_text: 'A short first response.', submitted_at: '2026-11-19T09:00:00Z', score: null, feedback: null, graded_at: null, attachments: [], student_name: 'Asha Learner', student_code: 'ST-101' },
      { id: 'submission-rohan', status: 'submitted', submission_text: 'A short second response.', submitted_at: '2026-11-19T09:10:00Z', score: null, feedback: null, graded_at: null, attachments: [], student_name: 'Rohan Learner', student_code: 'ST-102' },
    ]);
    if (path.endsWith(`/courses/${courseId}/assignments`)) return send([{ id: assignmentId, course_id: courseId, lesson_id: null, lesson_title: null, title: 'Weekly reflection', instructions: null, due_at: null, max_points: 10, status: 'published', submissions_count: 2, resource_attachments: [] }]);
    return send([]);
  });

  await page.goto('/');
  await page.getByRole('navigation', { name: 'Main navigation' }).getByRole('button', { name: 'Courses', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Writing practice' })).toBeVisible();
  await expect(page.getByRole('spinbutton', { name: 'Score for Asha Learner (out of 10 points)' })).toBeVisible();
  await expect(page.getByRole('spinbutton', { name: 'Score for Rohan Learner (out of 10 points)' })).toBeVisible();
  await expect(page.getByRole('textbox', { name: 'Feedback for Asha Learner' })).toBeVisible();
  await expect(page.getByRole('textbox', { name: 'Feedback for Rohan Learner' })).toBeVisible();
});

test('workspace manager can reply to and resolve member feedback', async ({ page }) => {
  const organizationId = 'feedback-response-org';
  const feedbackId = 'feedback-response-item';
  const feedback: Record<string, unknown> = {
    id: feedbackId, category: 'support', message: 'Could you clarify the science assignment?', status: 'open',
    response: null, created_at: '2026-11-19T09:00:00Z', responded_at: null,
    submitter_name: 'Workspace Teacher', responder_name: null,
  };
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'feedback-response-token');
    sessionStorage.setItem('paathnexus.active-organization', 'feedback-response-org');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown, status = 200) => route.fulfill({ status, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({ user: { name: 'Workspace Manager', email: 'manager@example.test' }, organizations: [{ id: organizationId, name: 'Fixture Academy', slug: 'fixture-academy', type: 'school', role: 'educator', permissions: ['workspace.view', 'members.manage'], locale: 'en', timezone: 'Asia/Kolkata' }] });
    if (path.endsWith('/dashboard')) return send({ active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0, unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null });
    if (path.endsWith('/feedback/mine')) return send([]);
    if (path.endsWith(`/feedback/${feedbackId}`) && route.request().method() === 'PATCH') {
      const payload = route.request().postDataJSON() as { status: 'open' | 'resolved'; response: string | null };
      Object.assign(feedback, payload, { responder_name: 'Workspace Manager', responded_at: '2026-11-19T10:00:00Z' });
      return send(feedback);
    }
    if (path.endsWith('/feedback')) return send([feedback]);
    return send([]);
  });

  await page.goto('/');
  await page.locator('.page-footer').getByRole('button', { name: 'Feedback' }).click();
  await page.getByRole('button', { name: 'हिन्दी' }).click();
  await expect(page.getByText('Could you clarify the science assignment?')).toBeVisible();
  await page.getByLabel(/Workspace Teacher के लिए जवाब/).fill('Please review the examples in lesson two.');
  await page.getByLabel(/Workspace Teacher की स्थिति/).selectOption('resolved');
  await page.getByRole('button', { name: 'जवाब और स्थिति सहेजें' }).click();
  await expect(page.locator('.feedback-card-heading strong').filter({ hasText: 'हल हुई' })).toBeVisible();
  await expect(page.getByLabel(/Workspace Teacher के लिए जवाब/)).toHaveValue('Please review the examples in lesson two.');
  expect(feedback.status).toBe('resolved');
});

test('workspace member can see the response and status for their feedback', async ({ page }) => {
  const organizationId = 'member-feedback-history-org';
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'member-feedback-history-token');
    sessionStorage.setItem('paathnexus.active-organization', 'member-feedback-history-org');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({ user: { name: 'Asha Learner', email: 'learner@example.test' }, organizations: [{ id: organizationId, name: 'Fixture Academy', slug: 'fixture-academy', type: 'school', role: 'student', permissions: ['workspace.view'], locale: 'en', timezone: 'Asia/Kolkata' }] });
    if (path.endsWith('/student/dashboard')) return send({ student: null, study_recommendations: [], courses: [], assignments: [], assessment_results: [], today_schedule: [], schedule_notice: null, attendance: { sessions_recorded: 0, present_or_late: 0, rate_percent: null, recent: [] } });
    if (path.endsWith('/dashboard')) return send({ active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0, unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null });
    if (path.endsWith('/feedback/mine')) return send([{ id: 'my-feedback-item', category: 'support', message: 'Could you clarify the assignment instructions?', status: 'resolved', response: 'Please review the second example in lesson two.', created_at: '2026-11-19T09:00:00Z', responded_at: '2026-11-19T10:00:00Z' }]);
    return send([]);
  });

  await page.goto('/');
  await page.locator('.page-footer').getByRole('button', { name: 'Feedback' }).click();
  await page.getByRole('button', { name: 'हिन्दी' }).click();
  await expect(page.getByText('Could you clarify the assignment instructions?')).toBeVisible();
  await expect(page.getByText('हल हुई ·')).toBeVisible();
  await expect(page.getByText('Please review the second example in lesson two.')).toBeVisible();
  await expect(page.getByText('कार्यस्थान की हाल की प्रतिक्रियाएँ')).toHaveCount(0);
});

test('workspace member can open feedback from an in-app response notification', async ({ page }) => {
  const organizationId = 'feedback-notification-org';
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'feedback-notification-token');
    sessionStorage.setItem('paathnexus.active-organization', 'feedback-notification-org');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({ user: { name: 'Asha Learner', email: 'learner@example.test' }, organizations: [{ id: organizationId, name: 'Fixture Academy', slug: 'fixture-academy', type: 'school', role: 'student', permissions: ['workspace.view'], locale: 'en', timezone: 'Asia/Kolkata' }] });
    if (path.endsWith('/student/dashboard')) return send({ student: null, study_recommendations: [], courses: [], assignments: [], assessment_results: [], today_schedule: [], schedule_notice: null, attendance: { sessions_recorded: 0, present_or_late: 0, rate_percent: null, recent: [] } });
    if (path.endsWith('/dashboard')) return send({ active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0, unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null });
    if (path.endsWith('/notifications') && route.request().method() === 'GET') return send({ unread_count: 1, items: [{ id: 'feedback-notification-item', type: 'feedback', announcement_id: null, feedback_id: 'my-feedback-item', title: 'Response to your feedback', body: 'Status: Resolved. Please review the second example in lesson two.', created_at: '2026-11-19T10:00:00Z', read_at: null }] });
    if (path.endsWith('/feedback-notification-item/read')) return send({ id: 'feedback-notification-item', read_at: '2026-11-19T10:10:00Z' });
    if (path.endsWith('/feedback/mine')) return send([{ id: 'my-feedback-item', category: 'support', message: 'Could you clarify the assignment instructions?', status: 'resolved', response: 'Please review the second example in lesson two.', created_at: '2026-11-19T09:00:00Z', responded_at: '2026-11-19T10:00:00Z' }]);
    return send([]);
  });

  await page.goto('/');
  await page.getByRole('button', { name: /Notifications, 1 unread/ }).click();
  await page.getByRole('button', { name: /Response to your feedback/ }).click();
  await expect(page.getByRole('heading', { name: 'Share feedback' })).toBeVisible();
  await expect(page.getByText('Could you clarify the assignment instructions?')).toBeVisible();
});

test('workspace help centre searches and displays Hindi guidance', async ({ page }) => {
  const organizationId = 'help-centre-localization-org';
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'help-centre-localization-token');
    sessionStorage.setItem('paathnexus.active-organization', 'help-centre-localization-org');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({ user: { name: 'Workspace Owner', email: 'owner@example.test' }, organizations: [{ id: organizationId, name: 'Fixture Academy', slug: 'fixture-academy', type: 'school', role: 'owner', permissions: ['workspace.view'], locale: 'en', timezone: 'Asia/Kolkata' }] });
    if (path.endsWith('/dashboard')) return send({ active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0, unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null });
    return send([]);
  });

  await page.goto('/');
  await page.getByRole('button', { name: 'हिन्दी' }).click();
  await page.getByRole('button', { name: 'सहायता केंद्र खोलें' }).click();
  await expect(page.getByRole('heading', { name: 'हम आपकी सहायता कैसे कर सकते हैं?' })).toBeVisible();
  const addStudent = page.getByText('मैं विद्यार्थी कैसे जोड़ूँ?', { exact: true });
  await addStudent.click();
  await expect(page.getByText(/विद्यार्थी खोलें और विद्यार्थी जोड़ें चुनें/)).toBeVisible();
  await page.getByRole('searchbox', { name: 'सहायता लेख खोजें' }).fill('उपस्थिति');
  await expect(page.getByText('मैं उपस्थिति कहाँ दर्ज करूँ?', { exact: true })).toBeVisible();
  await expect(addStudent).toHaveCount(0);
  await expect(page.getByRole('link', { name: 'ईमेल से सहायता लें' })).toHaveAttribute('href', 'mailto:support@paathnexus.com');
});

test('notification center preserves the server unread count beyond the latest 100 items', async ({ page }) => {
  const organizationId = 'many-notifications-org';
  const items = Array.from({ length: 100 }, (_, index) => ({
    id: `notification-${index + 1}`, type: 'announcement', announcement_id: `announcement-${index + 1}`, feedback_id: null,
    title: `Unread announcement ${index + 1}`, body: 'A workspace announcement.', created_at: `2026-11-19T10:${String(index % 60).padStart(2, '0')}:00Z`, read_at: null,
  }));
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'many-notifications-token');
    sessionStorage.setItem('paathnexus.active-organization', 'many-notifications-org');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const send = (data: unknown) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({ user: { name: 'Workspace Owner', email: 'owner@example.test' }, organizations: [{ id: organizationId, name: 'Fixture Academy', slug: 'fixture-academy', type: 'school', role: 'owner', permissions: ['workspace.view'], locale: 'en', timezone: 'Asia/Kolkata' }] });
    if (path.endsWith('/dashboard')) return send({ active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0, unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null });
    if (path.endsWith('/notifications') && route.request().method() === 'GET') return send({ unread_count: 115, items });
    if (path.endsWith('/read') && route.request().method() === 'POST') return send({ id: 'notification-1', read_at: '2026-11-19T11:00:00Z' });
    return send([]);
  });

  await page.goto('/');
  await expect(page.getByRole('button', { name: 'Notifications, 115 unread' })).toBeVisible();
  await page.getByRole('button', { name: /Notifications, 115 unread/ }).click();
  await expect(page.locator('.notification-popover-heading')).toContainText('115 unread');
  await page.getByText('Unread announcement 1', { exact: true }).click();
  await expect(page.locator('.notification-popover-heading')).toContainText('114 unread');
  await expect(page.getByRole('button', { name: 'Notifications, 114 unread' })).toBeVisible();
});

test('workspace owner can request a plan change from the Hindi billing view', async ({ page }) => {
  const organizationId = 'billing-locale-org';
  const currentPlan = { id: 'plan-basic', code: 'basic', name: 'School Basic', audience: 'institution', description: 'Core workspace features.', amount_paise: 0, billing_interval: 'month', entitlements: { max_students: 100, max_courses: 10 }, is_active: true };
  const growthPlan = { id: 'plan-growth', code: 'growth', name: 'School Growth', audience: 'institution', description: 'More room to grow.', amount_paise: 49900, billing_interval: 'month', entitlements: { max_students: 500, max_courses: 40, max_teachers: 25 }, is_active: true };
  let requestedPlanId = '';
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'billing-locale-token');
    sessionStorage.setItem('paathnexus.active-organization', 'billing-locale-org');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const method = route.request().method();
    const send = (data: unknown) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({ user: { name: 'Mira Owner', email: 'owner@example.test' }, organizations: [{ id: organizationId, name: 'Sample Academy', slug: 'sample-academy', type: 'school', role: 'owner', permissions: ['*'], locale: 'en', timezone: 'Asia/Kolkata' }] });
    if (path.endsWith('/dashboard')) return send({ active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0, unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null });
    if (path.endsWith('/notifications')) return send({ notifications: [], unread_count: 0 });
    if (path.endsWith(`/organizations/${organizationId}/subscription/requests`) && method === 'POST') {
      requestedPlanId = (route.request().postDataJSON() as { plan_id: string }).plan_id;
      return send({ status: 'change_pending', plan: currentPlan, requested_plan: growthPlan, requested_at: '2026-11-20T10:00:00Z', available_plans: [currentPlan, growthPlan] });
    }
    if (path.endsWith(`/organizations/${organizationId}/subscription`)) return send({ status: requestedPlanId ? 'change_pending' : 'active', plan: currentPlan, requested_plan: requestedPlanId ? growthPlan : null, requested_at: requestedPlanId ? '2026-11-20T10:00:00Z' : null, available_plans: [currentPlan, growthPlan] });
    return send([]);
  });

  await page.goto('/');
  await page.getByRole('navigation', { name: 'Main navigation' }).getByRole('button', { name: 'Plan & billing' }).click();
  await expect(page.getByRole('heading', { name: 'Plan & billing', level: 2 })).toBeVisible();
  await page.getByRole('button', { name: 'हिन्दी' }).click();
  await expect(page.getByRole('heading', { name: 'योजना और बिलिंग', level: 2 })).toBeVisible();
  await expect(page.locator('.plan-card').first()).toContainText('अधिकतम विद्यार्थी: 100');
  await expect(page.getByLabel('उपलब्ध योजना')).toBeVisible();
  await expect(page.getByText('मूल्य के लिए संपर्क करें')).toHaveCount(0);
  await page.getByLabel('उपलब्ध योजना').selectOption('plan-growth');
  await page.getByRole('button', { name: 'बदलाव का अनुरोध करें' }).click();
  await expect.poll(() => requestedPlanId).toBe('plan-growth');
  await expect(page.getByText(/अनुरोध लंबित: School Growth/)).toBeVisible();
});

test('educator can create a course and add its first lesson', async ({ page }) => {
  test.setTimeout(60_000);
  const organizationId = 'educator-org-fixture';
  const courseId = 'educator-course-fixture';
  const lessonId = 'educator-lesson-fixture';
  let courseCreated = false;
  let lessonCreated = false;
  let assistantDrafted = false;
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'educator-fixture-token');
    sessionStorage.setItem('paathnexus.active-organization', 'educator-org-fixture');
  });
  const invoiceFixture = Array.from({ length: 26 }, (_, index) => ({
    id: index === 0 ? 'invoice-fixture' : `invoice-fixture-${index + 1}`,
    invoice_number: index === 0 ? 'INV-2026-001' : `INV-2026-${String(index + 1).padStart(3, '0')}`,
    student_id: 'student-fixture', student_name: 'Asha Learner', student_code: 'ST-01',
    title: index === 0 ? 'Term 1 tuition' : index === 25 ? 'Extra activity fee' : `Supplemental fee ${index + 1}`,
    description: null, amount_paise: index === 0 ? 150000 : 10000, balance_paise: index === 0 ? 50000 : 10000,
    payments: index === 0 ? [{ id: 'payment-fixture', amount_paise: 100000, method: 'upi', transaction_reference: 'UPI-2026-001', receipt_number: 'RCT-2026-001', received_at: '2026-11-18T10:00:00+05:30' }] : [],
    due_on: '2026-11-30', status: index === 0 ? 'partially_paid' : 'issued',
    created_at: index % 2 === 1 ? '2026-11-19T10:00:00+05:30' : '2026-11-18T10:00:00+05:30',
  }));
  const collectionFixture = [
    { id: 'collection-1', invoice_id: 'invoice-fixture', invoice_number: 'INV-2026-001', invoice_title: 'Term 1 tuition', student_name: 'Asha Learner', student_code: 'ST-01', amount_paise: 100000, method: 'upi', transaction_reference: 'UPI-2026-001', receipt_number: 'RCT-2026-001', received_at: '2026-11-18T10:00:00+05:30' },
    { id: 'collection-2', invoice_id: 'invoice-fixture-2', invoice_number: 'INV-2026-002', invoice_title: 'Supplemental fee', student_name: 'Asha Learner', student_code: 'ST-01', amount_paise: 40000, method: 'cash', transaction_reference: 'CASH-2026-002', receipt_number: 'RCT-2026-002', received_at: '2026-11-19T10:00:00+05:30' },
  ];
  await page.route('**/api/v1/**', async (route) => {
    const requestUrl = new URL(route.request().url());
    const path = requestUrl.pathname;
    const method = route.request().method();
    const send = (data: unknown) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
    if (path.endsWith('/auth/me')) return send({ user: { name: 'Dev Educator', email: 'dev@example.test' }, organizations: [{ id: organizationId, name: 'Fixture Academy', slug: 'fixture-academy', type: 'school', role: 'educator', permissions: ['workspace.view', 'courses.view', 'courses.manage', 'academic.manage', 'fees.view'], locale: 'en', timezone: 'Asia/Kolkata' }] });
    if (path.endsWith('/dashboard')) return send({ active_students_count: 0, active_classes_count: 0, active_enrollments_count: 0, unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: null, latest_attendance_class: null, latest_attendance_date: null, attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null });
    if (path.endsWith('/search')) {
      if (requestUrl.searchParams.get('q')?.toLowerCase().includes('chlorophyll')) {
        return send([{ id: 'search-course-fixture', type: 'course', title: 'Life Science', subtitle: 'Lesson: Green energy · BIO-101' }]);
      }
      return send([{ id: 'search-invoice-fixture', type: 'invoice', title: 'PN-SEARCH-001', subtitle: 'Term invoice · ST-01' }]);
    }
    if (path.endsWith('/courses') && method === 'GET') return send(courseCreated ? [{ id: courseId, code: 'SCI-101', title: 'Science in Practice', description: null, status: 'active', is_public: false, price_paise: 0, academic_year_id: null, academic_year: null, subject_id: null, subject: null, instructors: [], lessons_count: lessonCreated ? 1 : 0, enrollments_count: 0 }] : []);
    if (path.endsWith('/courses') && method === 'POST') { const body = route.request().postDataJSON() as Record<string, unknown>; courseCreated = true; return send({ id: courseId, code: body.code, title: body.title, description: body.description ?? null, status: 'active', is_public: false, price_paise: body.price_paise ?? 0, academic_year_id: null, academic_year: null, subject_id: null, subject: null, instructors: [], lessons_count: 0, enrollments_count: 0 }); }
    if (path.endsWith(`/courses/${courseId}/lessons`) && method === 'POST') { const body = route.request().postDataJSON() as Record<string, unknown>; lessonCreated = true; return send({ id: lessonId, course_id: courseId, title: body.title, content: body.content ?? null, resource_type: null, resource_url: null, prerequisites: [], prerequisites_met: true, position: 1, status: 'active', my_progress: null }); }
    if (path.endsWith('/ai/teacher-assistant') && method === 'POST') { assistantDrafted = true; return send({ draft: '# Cell ecosystems\n\n1. Observe a school garden.\n2. Record three living and non-living things.', human_review_required: true, usage: { input_tokens: 30, output_tokens: 24 } }); }
    if (path.endsWith('/fee-invoices/summary')) return send({ invoice_count: 2, paid_invoice_count: 1, outstanding_invoice_count: 1, overdue_invoice_count: 1, invoiced_paise: 150000, collected_paise: 100000, outstanding_paise: 50000, overdue_paise: 50000 });
    if (path.endsWith('/fee-invoices/collections')) {
      const from = requestUrl.searchParams.get('from') ?? '';
      const to = requestUrl.searchParams.get('to') ?? '';
      const filtered = collectionFixture.filter((payment) => payment.received_at.slice(0, 10) >= from && payment.received_at.slice(0, 10) <= to);
      const page = Number(requestUrl.searchParams.get('page') ?? 1);
      const perPage = Number(requestUrl.searchParams.get('per_page') ?? 25);
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: filtered.slice((page - 1) * perPage, page * perPage), summary: { payment_count: filtered.length, collected_paise: filtered.reduce((total, payment) => total + payment.amount_paise, 0) }, meta: { current_page: page, last_page: Math.max(1, Math.ceil(filtered.length / perPage)), per_page: perPage, total: filtered.length, from, to } }) });
    }
    if (path.endsWith('/fee-invoices')) {
      const from = requestUrl.searchParams.get('from');
      const to = requestUrl.searchParams.get('to');
      const filtered = invoiceFixture.filter((invoice) => (!from || invoice.created_at.slice(0, 10) >= from) && (!to || invoice.created_at.slice(0, 10) <= to));
      const page = Number(requestUrl.searchParams.get('page') ?? 1);
      const perPage = Number(requestUrl.searchParams.get('per_page') ?? 25);
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: filtered.slice((page - 1) * perPage, page * perPage), meta: { current_page: page, last_page: Math.max(1, Math.ceil(filtered.length / perPage)), per_page: perPage, total: filtered.length } }) });
    }
    if (path.endsWith(`/courses/${courseId}/lessons`)) return send(lessonCreated ? [{ id: lessonId, course_id: courseId, title: 'Observe a local ecosystem', content: 'Record living and non-living components in the school garden.', resource_type: null, resource_url: null, prerequisites: [], prerequisites_met: true, position: 1, status: 'active', my_progress: null }] : []);
    return send([]);
  });

  await page.clock.install({ time: new Date('2026-11-19T12:00:00Z') });
  await page.goto('/');
  await expectNoHorizontalOverflow(page);
  await page.getByLabel('Search workspace records').fill('PN-SEARCH');
  const invoiceSearchResult = page.getByRole('region', { name: 'Search results' }).getByRole('button', { name: /PN-SEARCH-001/ });
  await expect(invoiceSearchResult).toBeVisible();
  await invoiceSearchResult.click();
  await expect(page.getByRole('heading', { name: 'Fee invoices' }).last()).toBeVisible();
  await page.getByRole('navigation', { name: 'Main navigation' }).getByRole('button', { name: 'Courses', exact: true }).click();
  await expectNoHorizontalOverflow(page);
  await page.getByLabel('Search workspace records').fill('chlorophyll');
  const lessonSearchResult = page.getByRole('region', { name: 'Search results' }).getByRole('button', { name: /Life Science.*Green energy/ });
  await expect(lessonSearchResult).toBeVisible();
  await lessonSearchResult.click();
  await expect(page.getByRole('heading', { name: 'Courses and lessons' })).toBeVisible();
  await page.getByLabel('Course title').fill('Science in Practice');
  await page.getByLabel('Course code').fill('SCI-101');
  await page.getByRole('button', { name: 'Create course' }).click();
  await expect.poll(() => courseCreated).toBe(true);
  await expect(page.getByText('Science in Practice', { exact: true }).first()).toBeVisible();

  await page.getByLabel('Lesson title').fill('Observe a local ecosystem');
  await page.getByLabel('Lesson content').fill('Record living and non-living components in the school garden.');
  await page.getByRole('button', { name: 'Add lesson', exact: true }).first().click();
  await expect.poll(() => lessonCreated).toBe(true);
  await expect(page.getByText('Observe a local ecosystem', { exact: true })).toBeVisible();

  await page.getByRole('button', { name: 'हिन्दी' }).click();
  await expect(page.getByRole('heading', { name: 'कोर्स और पाठ' })).toBeVisible();
  const courseEditForm = page.locator('.course-edit-form').first();
  await expect(courseEditForm.getByText('कोर्स संपादित करें')).toBeVisible();
  await expect(courseEditForm.getByLabel('प्रकाशन स्थिति')).toBeVisible();
  await expect(courseEditForm.getByLabel('कोर्स खोज')).toBeVisible();
  await expect(page.getByLabel('पाठ का शीर्षक').first()).toBeVisible();
  await expect(page.getByRole('button', { name: 'पाठ जोड़ें' }).first()).toBeVisible();
  const instructorsForm = page.locator('.course-edit-form').nth(1);
  await expect(instructorsForm.getByText('कोर्स के शिक्षक')).toBeVisible();
  await expect(instructorsForm.getByLabel('नियुक्त शिक्षक')).toBeVisible();
  await expect(instructorsForm.getByRole('button', { name: 'शिक्षक नियुक्तियाँ सहेजें' })).toBeVisible();
  const enrollmentForm = page.locator('.course-actions form').nth(1);
  await expect(enrollmentForm.getByText('विद्यार्थी का नामांकन करें')).toBeVisible();
  await expect(enrollmentForm.getByLabel('विद्यार्थी')).toBeVisible();
  await expect(enrollmentForm.getByRole('button', { name: 'नामांकन करें' })).toBeVisible();
  await expect(enrollmentForm.getByText('0 विद्यार्थी नामांकित हैं।')).toBeVisible();
  const assignmentForm = page.locator('.assignment-create-form');
  await expect(assignmentForm.getByText('असाइनमेंट बनाएँ')).toBeVisible();
  await expect(assignmentForm.getByLabel('शीर्षक')).toBeVisible();
  await expect(assignmentForm.getByLabel('निर्देश')).toBeVisible();
  await expect(assignmentForm.getByLabel('अंक')).toBeVisible();
  await expect(assignmentForm.getByLabel('जमा करने की तारीख (Asia/Kolkata)')).toBeVisible();
  await expect(assignmentForm.getByRole('button', { name: 'असाइनमेंट प्रकाशित करें' })).toBeVisible();
  await page.getByRole('button', { name: 'Edit lesson' }).click();
  const lessonEditForm = page.locator('.lesson-edit-form');
  await expect(lessonEditForm.getByLabel('पहले पूरे किए जाने वाले पाठ')).toBeVisible();
  await expect(lessonEditForm.getByRole('button', { name: 'पाठ सहेजें' })).toBeVisible();
  await lessonEditForm.getByRole('button', { name: 'रद्द करें' }).click();
  await page.getByRole('button', { name: 'English' }).click();

  await page.getByRole('navigation', { name: 'Tools' }).getByRole('button', { name: /AI learning/ }).click();
  await expect(page.getByRole('heading', { name: 'Prepare a teaching draft' })).toBeVisible();
  await page.getByLabel('Topic', { exact: true }).fill('Cell ecosystems');
  await page.getByRole('button', { name: 'Generate draft' }).click();
  await expect.poll(() => assistantDrafted).toBe(true);
  await expect(page.getByLabel('Generated teaching draft')).toContainText('Observe a school garden');

  await page.getByRole('navigation', { name: 'Main navigation' }).getByRole('button', { name: 'Fee invoices' }).click();
  await expect(page.getByRole('heading', { name: 'Fee invoices' }).last()).toBeVisible();
  await expect(page.getByText('Fee overview')).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Collections by received date' })).toBeVisible();
  await expect(page.getByText('₹1,400.00', { exact: true })).toBeVisible();
  await expect(page.locator('div.invoice-amount').getByText('₹1,500.00', { exact: true })).toBeVisible();
  await expect(page.getByText('₹500.00', { exact: true }).first()).toBeVisible();
  const invoiceDownloadPromise = page.waitForEvent('download');
  await page.getByRole('button', { name: 'Export invoices CSV' }).click();
  const invoiceDownload = await invoiceDownloadPromise;
  expect(invoiceDownload.suggestedFilename()).toMatch(/^fixture-academy-fee-invoices-\d{4}-\d{2}-\d{2}\.csv$/);
  const csvStream = await invoiceDownload.createReadStream();
  if (!csvStream) throw new Error('The invoice CSV download stream was unavailable.');
  const csvChunks: Buffer[] = [];
  for await (const chunk of csvStream) csvChunks.push(Buffer.from(chunk));
  const invoiceCsv = Buffer.concat(csvChunks).toString('utf8');
  expect(invoiceCsv).toContain('"INV-2026-026"');
  expect(invoiceCsv).toContain('"UPI-2026-001"');
  const collectionsDownloadPromise = page.waitForEvent('download');
  await page.getByRole('button', { name: 'Export collections CSV' }).click();
  const collectionsDownload = await collectionsDownloadPromise;
  expect(collectionsDownload.suggestedFilename()).toMatch(/^fixture-academy-collections-\d{4}-\d{2}-\d{2}-to-\d{4}-\d{2}-\d{2}\.csv$/);
  const receiptPopupPromise = page.waitForEvent('popup');
  await page.getByRole('button', { name: 'Print / Save receipt PDF' }).click();
  const receiptPopup = await receiptPopupPromise;
  await expect(receiptPopup.getByRole('heading', { name: 'Fee payment received' })).toBeVisible();
  await expect(receiptPopup.getByText('RCT-2026-001')).toBeVisible();
  await expect(receiptPopup.getByText('UPI-2026-001')).toBeVisible();
  await receiptPopup.setViewportSize({ width: 375, height: 812 });
  await expectNoHorizontalOverflow(receiptPopup);
  await page.getByRole('button', { name: 'Next page' }).click();
  await expect(page.getByText('Extra activity fee')).toBeVisible();
  await expect(page.getByText('Page 2 of 2', { exact: true })).toBeVisible();
  await page.getByLabel('Issued from').fill('2026-11-19');
  await page.getByLabel('Issued through').fill('2026-11-19');
  await page.getByRole('button', { name: 'Apply date filters' }).click();
  await expect(page.getByText('13 invoices · Page 1 of 1')).toBeVisible();
  await page.getByLabel('Received from').fill('2026-11-19');
  await page.getByLabel('Received through').fill('2026-11-19');
  await page.getByRole('button', { name: 'Apply collection dates' }).click();
  await expect(page.locator('.collection-table tbody tr').getByRole('cell').last()).toHaveText('₹400.00');
  await page.getByRole('button', { name: 'Clear filters' }).click();
  await expect(page.getByRole('button', { name: 'Print / Save receipt PDF' })).toBeVisible();
  await page.getByRole('button', { name: 'हिन्दी' }).click();
  await expect(page.getByRole('heading', { name: 'शुल्क चालान' }).last()).toBeVisible();
  await expect(page.getByText('शुल्क सारांश')).toBeVisible();
  await expect(page.getByRole('button', { name: 'चालान CSV निर्यात करें' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'भुगतान दर्ज करें' }).first()).toBeVisible();
  await expect(page.getByRole('heading', { name: 'प्राप्ति तिथि के अनुसार संग्रह' })).toBeVisible();
  await expect(page.getByRole('columnheader', { name: 'प्राप्त' })).toBeVisible();
  const hindiReceiptPopupPromise = page.waitForEvent('popup');
  await page.getByRole('button', { name: 'रसीद प्रिंट / PDF सहेजें' }).click();
  const hindiReceiptPopup = await hindiReceiptPopupPromise;
  await expect(hindiReceiptPopup.getByRole('heading', { name: 'शुल्क भुगतान प्राप्त हुआ' })).toBeVisible();
});

test('educator report shows assigned work totals and exports them to CSV', async ({ page }) => {
  test.setTimeout(60_000);
  const organizationId = 'report-org-fixture';
  await page.addInitScript(() => {
    sessionStorage.setItem('paathnexus.session-token', 'report-fixture-token');
    sessionStorage.setItem('paathnexus.active-organization', 'report-org-fixture');
    window.print = () => sessionStorage.setItem('paathnexus.report-print-requested', 'yes');
  });
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    const data = path.endsWith('/auth/me')
      ? { user: { name: 'Riya Educator', email: 'riya@example.test' }, organizations: [{ id: organizationId, name: 'Fixture Academy', slug: 'fixture-academy', type: 'school', role: 'educator', permissions: ['workspace.view', 'academic.view', 'attendance.read', 'reports.view', 'courses.view', 'courses.manage'], locale: 'en', timezone: 'Asia/Kolkata' }] }
      : path.endsWith('/dashboard')
        ? { active_students_count: 1, active_classes_count: 1, active_enrollments_count: 1, unassigned_students_count: 0, pending_tasks_count: 0, latest_attendance_rate: 100, latest_attendance_class: 'Class 8A', latest_attendance_date: '2026-11-19', attendance_series: [], classes: [], today_schedule: [], schedule_notice: null, schedule_weekday: 4, active_academic_year_id: null }
        : path.endsWith('/reports/academic')
          ? { scope: 'workspace', available_classes: [{ id: 'class-report-fixture', name: 'Class 8A' }], filters: { class_id: null, student_id: null, from: null, to: null }, summary: { active_students: 1, attendance_sessions: 2, attendance_percent: 100, courses_enrolled: 1, completed_lessons: 1, total_lessons: 2, course_completion_percent: 50, assignments_assigned: 2, assignments_submitted: 1, assignments_graded: 1, assessments_completed: 1, assessment_average_percent: 80, assessment_score_bands: { under_60: 0, sixty_to_74: 0, seventyfive_to_89: 1, ninety_plus: 0 }, assessment_trend: [{ month: "2026-11", attempts: 1, average_percent: 80 }], assessment_breakdown: [{ assessment_id: "assessment-report-fixture", assessment_title: "Science quiz", course_id: "course-report-fixture", course_title: "Science", attempts: 1, average_percent: 80 }], assessment_course_summary: [{ course_id: "course-report-fixture", course_title: "Science", attempts: 1, average_percent: 80 }] }, students: [{ student_id: 'student-report-fixture', student_code: 'ST-REPORT-1', student_name: 'Asha Learner', classes: 'Class 8A', attendance_sessions: 2, attendance_percent: 100, courses_enrolled: 1, completed_lessons: 1, total_lessons: 2, course_completion_percent: 50, assignments_assigned: 2, assignments_submitted: 1, assignments_graded: 1, assessments_completed: 1, assessment_average_percent: 80 }] }
          : [];
    return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data }) });
  });

  await page.goto('/');
  await page.getByRole('navigation', { name: 'Tools' }).getByRole('button', { name: 'Reports' }).click();
  const reportsNavButton = page.getByRole('navigation', { name: 'Tools' }).getByRole('button', { name: 'Reports' });
  await expect(reportsNavButton).toHaveAttribute('aria-current', 'page');
  await expect(page.getByRole('heading', { name: 'Workspace performance' })).toBeVisible();
  await expect(page.getByText('2 assigned · 1 graded')).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Assessment score distribution' })).toBeVisible();
  await expect(page.locator('.report-band-table tbody tr').nth(2)).toContainText('75–89%');
  await expect(page.locator('.report-band-table tbody tr').nth(2)).toContainText('100%');
  await expect(page.getByText('Monthly assessment trend')).toBeVisible();
  await expect(page.getByText('Results by assessment')).toBeVisible();
  await expect(page.getByRole('row', { name: 'Science Science quiz 1 80%' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Results by course' })).toBeVisible();
  await expect(page.locator('.report-assessment-breakdown').last().locator('tbody tr')).toContainText('Science');
  await expect(page.locator('.report-trend-table tbody tr').last()).toContainText('Nov 2026');
  await expect(page.getByRole('cell', { name: '1/2 assigned 1 graded' })).toBeVisible();
  await expectNoHorizontalOverflow(page);
  await page.getByRole('button', { name: 'हिन्दी' }).click();
  const reportsNavHindiButton = page.getByRole('navigation', { name: 'साधन' }).getByRole('button', { name: 'Reports' });
  await expect(reportsNavHindiButton).toHaveAttribute('aria-current', 'page');
  await expect(page.getByRole('heading', { name: 'कार्यस्थान का प्रदर्शन' })).toBeVisible();
  await expect(page.getByText('2 दिए गए · 1 जाँचे गए')).toBeVisible();
  await expect(page.getByRole('heading', { name: 'मूल्यांकन अंकों का वितरण' })).toBeVisible();
  await expect(page.getByLabel('से')).toBeVisible();
  await expect(page.getByLabel('तक')).toBeVisible();
  await expect(page.getByLabel('कक्षा')).toBeVisible();
  await expect(page.getByLabel('विद्यार्थी')).toBeVisible();
  await expect(page.getByRole('button', { name: 'फ़िल्टर लागू करें' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'CSV निर्यात करें' })).toBeVisible();
  await page.getByRole('button', { name: 'प्रिंट / PDF सहेजें' }).click();
  expect(await page.evaluate(() => sessionStorage.getItem('paathnexus.report-print-requested'))).toBe('yes');
  await page.emulateMedia({ media: 'print' });
  await expect(page.locator('.sidebar')).toBeHidden();
  await expect(page.locator('.report-export-actions')).toBeHidden();
  await expect(page.getByRole('heading', { name: 'कार्यस्थान का प्रदर्शन' })).toBeVisible();
  await expect(page.locator('.report-table').last()).toBeVisible();
  await page.emulateMedia({ media: 'screen' });

  const downloadPromise = page.waitForEvent('download');
  await page.getByRole('button', { name: 'CSV निर्यात करें' }).click();
  const download = await downloadPromise;
  expect(download.suggestedFilename()).toBe('fixture-academy-academic-report.csv');
  const csvPath = await download.path();
  expect(csvPath).not.toBeNull();
  const csv = await readFile(csvPath!, 'utf8');
  expect(csv).toContain('"दिए गए असाइनमेंट","जमा असाइनमेंट","जाँचे असाइनमेंट"');
  expect(csv).toContain('"student","ST-REPORT-1","Asha Learner","Class 8A","2","100","1","1","2","50","2","1","1","1","80"');
  expect(csv).toContain('"score_band"');
  expect(csv).toContain('"monthly_assessment"');
  const assessmentCsvRow = csv.split('\r\n').find((row) => row.startsWith('"assessment",'));
  expect(assessmentCsvRow).toContain('"Science","Science quiz","1","80"');
  expect(csv).toContain('"course"');
  const coursesNavButton = page.getByRole('navigation', { name: 'मुख्य नेविगेशन' }).getByRole('button', { name: 'कोर्स', exact: true });
  await coursesNavButton.click();
  await expect(coursesNavButton).toHaveAttribute('aria-current', 'page');
  await expect(reportsNavHindiButton).not.toHaveAttribute('aria-current');
});
