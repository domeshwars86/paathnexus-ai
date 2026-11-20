import { lazy, Suspense, useEffect, useMemo, useRef, useState, type Dispatch, type FormEvent, type SetStateAction } from 'react';
import {
  Activity, Archive, ArrowRight, Bell, BookOpen,
  CalendarDays, Check, ChevronDown, CircleHelp, ClipboardCheck, Clock3,
  GraduationCap, LayoutDashboard, Menu, MessageCircle, MoreHorizontal, Printer,
  Pencil, Plus, Receipt, RotateCcw, Search, Settings2, ShieldCheck, Sparkles, Users, X,
} from 'lucide-react';
import { downloadAssignmentAttachment, uploadAssignmentResources, type AssignmentAttachment } from './lib/auth';
import { createSupportFeedback, listMySupportFeedback, listSupportFeedback, updateSupportFeedback, type SupportFeedback } from './lib/auth';
import { getPlatformUsageHistory, type PlatformUsageSnapshot } from './lib/auth';
import './search.css';
import { askAiTutor, createAiTeacherDraft, createAnnouncement, updateAnnouncement, withdrawAnnouncement, restoreAnnouncement, createFeeInvoice, createOrganizationInvitation, createPlatformPlan, endGuardianLink, getAcademicReport, getFeeCollections, getFeeInvoiceSummary, getPlatformUsage, getWorkspaceSubscription, linkGuardianStudent, listAnnouncements, listFeeInvoices, listGuardianCandidates, listGuardianChildren, listGuardianLinks, listOrganizationInvitations, listPlatformPlans, listPlatformOrganizations, listPlatformAuditLogs, listPlatformFeatureFlags, listStudents, listSubscriptionRequests, listWorkspaceNotifications, markWorkspaceNotificationRead, createLiveSession, getLiveSessionRoster, joinLiveSession, listLiveSessions, recordLiveSessionAttendance, recordFeePayment, updateLiveSession, requestSubscriptionPlan, resolveSubscriptionRequest, revokeOrganizationInvitation, updateAccountProfile, updateOrganization, updatePlatformFeatureFlag, updatePlatformOrganizationStatus, updatePlatformPlan, voidFeeInvoice, type AcademicReport, type AiTeacherDraftType, type Announcement, type FeeCollectionReport, type FeeInvoice, type FeeInvoiceSummary, type FeePayment, type GuardianCandidate, type GuardianStudentLink, type GuardianStudentSummary, type OrganizationInvitation, type PlatformOrganization, type PlatformUsageMetrics, type PlatformAuditEntry, type PlatformFeatureFlag, type SubscriptionPlan, type SubscriptionRequest, type WorkspaceSubscription, type WorkspaceNotification } from './lib/auth';
import { activateAcademicYear, archiveTask, assignClassTeacher, completeStudentStudyPlan, completeTask, createAcademicYear, createAssessment, createBankQuestion, createClass, createCourse, createCourseAssignment, createCourseLesson, createSection, createStudent, createStudentStudyPlan, createSubject, createSubjectOffering, createScheduleException, createTask, cancelScheduleException, createTeacherProfile, createTimetableEntry, enrollInCourse, endClassTeacher, endSubjectOffering, enrollStudent, getDashboardSummary, getStudentDashboard, gradeCourseSubmission, linkStudentMember, linkTeacherMember, listAcademicYears, listAssessmentAttempts, listAssignmentSubmissions, listAssessments, listAttendanceSessions, listBankQuestions, listClasses, listClassTeacherHistory, listClassTeachers, listCourseAssignments, listCourseEnrollments, listCourseInstructorCandidates, listCourseLessons, listCourseProgress, listCourses, listSections, listStudentMemberCandidates, listStudentStudyPlans, listSubjectOfferings, listScheduleExceptions, listSubjects, listTaskAssignees, listTasks, listTeacherMemberCandidates, listTeacherProfiles, listTimetable, markClassAttendance, saveAssessmentAnswer, searchWorkspace, restoreTask, startAssessmentAttempt, submitAssessmentAttempt, submitCourseAssignment, updateClassTeacher, updateCourse, updateCourseInstructors, updateCourseLesson, updateLessonProgress, updateTimetableEntry, updateTeacherPublicProfile, updateTask, type AcademicYear, type AssessmentAttempt, type AssessmentAttemptSummary, type AssignmentSubmission, type AttendanceSession, type AttendanceStatus, type BankQuestion, type ClassTeacherAssignment, type ClassTeacherAssignmentEvent, type Course, type CourseAssignment, type CourseEnrollment, type CourseLesson, type CourseProgress, type DashboardSummary, type LearningAssessment, type LiveClassSession, type LiveSessionRosterStudent, type Organization, type OrganizationClass, type OrganizationSection, type OrganizationSubject, type OrganizationTask, type ScheduleException, type TaskAssignee, type StudentDashboard, type StudentMemberCandidate, type StudentProfile, type StudentStudyPlan, type SubjectOffering, type TeacherMemberCandidate, type TeacherProfile, type TimetableEntry, type User, type WorkspaceSearchResult } from './lib/auth';
import { newRubricCriterion, ShortAnswerRubricEditor } from './ShortAnswerRubricEditor';
import type { GradingRubricCriterion } from './lib/auth';
import { isoToWorkspaceDateTime, workspaceDateTimeToIso } from './lib/timezone';
import { encodeCsv } from './lib/csv';
import { encodeFeeInvoiceCsv } from './lib/feeInvoiceCsv';
import { LanguageSwitcher, useLocale, type Locale, type TranslationKey } from './lib/locale';

const MarketplacePanel = lazy(() => import('./MarketplacePanel'));
const CustomTeacherRolesPanel = lazy(() => import('./CustomTeacherRolesPanel'));
const QuestionCsvImport = lazy(() => import('./QuestionCsvImport').then(({ QuestionCsvImport: Component }) => ({ default: Component })));
const AssessmentAnalyticsPanel = lazy(() => import('./AssessmentAnalytics').then(({ AssessmentAnalyticsPanel: Component }) => ({ default: Component })));
const AssessmentStudentResults = lazy(() => import('./AssessmentStudentResults').then(({ AssessmentStudentResults: Component }) => ({ default: Component })));

type Task = { id: string; title: string; description: string; due_at: string | null; assigned_to: string | null; detail: string; time: string; done: boolean; archived: boolean };

function hasPermission(organization: Organization, permission: string): boolean {
  return organization.permissions === undefined || organization.permissions.includes('*') || organization.permissions.includes(permission);
}

function canViewAcademicReports(organization: Organization): boolean {
  return hasPermission(organization, 'reports.view')
    && hasPermission(organization, 'academic.view')
    && hasPermission(organization, 'attendance.read');
}

function displayTask(task: OrganizationTask, locale: Locale, t: (key: TranslationKey) => string): Task {
  const time = task.due_at
    // Task deadlines are date-only values serialized at UTC midnight; keep their calendar date stable.
    ? `${t('tasks.due')} ${new Intl.DateTimeFormat(locale === 'hi' ? 'hi-IN' : 'en-IN', { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'UTC' }).format(new Date(task.due_at))}`
    : t('tasks.noDueDate');
  return { id: task.id, title: task.title, description: task.description ?? '', due_at: task.due_at, assigned_to: task.assigned_to, archived: task.archived_at !== null, detail: `${task.description || t('tasks.workspaceTask')}${task.assigned_to_name ? ` · ${t('tasks.assignedTo')} ${task.assigned_to_name}` : ''}`, time, done: task.status === 'completed' };
}

function currentWeekToDateRange(): { from: string; to: string } {
  const formatter = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Kolkata', year: 'numeric', month: '2-digit', day: '2-digit' });
  const parts = formatter.formatToParts(new Date());
  const value = (type: string) => Number(parts.find((part) => part.type === type)?.value ?? 0);
  const year = value('year'); const month = value('month'); const day = value('day');
  const today = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
  const indiaToday = new Date(Date.UTC(year, month - 1, day));
  const dayOfWeek = indiaToday.getUTCDay();
  const daysSinceMonday = dayOfWeek === 0 ? 6 : dayOfWeek - 1;
  const monday = new Date(indiaToday.getTime() - daysSinceMonday * 86_400_000);
  return { from: monday.toISOString().slice(0, 10), to: today };
}

type NavigationItem = { label: string; icon: typeof LayoutDashboard; badge?: string };

function WorkspaceSearchPanel({ query, token, organization, onChoose }: {
  query: string;
  token: string;
  organization: Organization;
  onChoose: (result: WorkspaceSearchResult) => void;
}) {
  const [results, setResults] = useState<WorkspaceSearchResult[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const term = query.trim();

  useEffect(() => {
    let active = true;
    if (term.length < 2) {
      setResults([]); setLoading(false); setError('');
      return () => { active = false; };
    }
    setLoading(true); setError(''); setResults([]);
    const timer = window.setTimeout(() => {
      searchWorkspace(token, organization.id, term)
        .then((items) => { if (active) setResults(items); })
        .catch((reason) => { if (active) setError(reason instanceof Error ? reason.message : 'Search is unavailable.'); })
        .finally(() => { if (active) setLoading(false); });
    }, 220);
    return () => { active = false; window.clearTimeout(timer); };
  }, [term, token, organization.id]);

  if (!query) return null;
  return <section className="workspace-search-results" role="region" aria-label="Search results" aria-live="polite">
    <div className="workspace-search-heading"><Search size={15} /><span>{term.length < 2 ? 'Type at least 2 characters' : loading ? 'Searching…' : error || `${results.length} result${results.length === 1 ? '' : 's'} for “${term}”`}</span></div>
    {term.length >= 2 && !loading && !error && results.length === 0 && <p>No matching classes, learners, courses or lessons, tasks, invoices, announcements, or attendance.</p>}
    {results.map((result) => <button className="workspace-search-item" key={`${result.type}-${result.id}`} onClick={() => onChoose(result)}>
      <span className="workspace-search-kind">{result.type}</span><span className="workspace-search-copy"><strong>{result.title}</strong><small>{result.subtitle || 'Workspace record'}</small></span><ArrowRight size={14} />
    </button>)}
  </section>;
}

const navigation: NavigationItem[] = [
  { label: 'Overview', icon: LayoutDashboard },
  { label: 'Students', icon: Users },
  { label: 'Tasks', icon: ClipboardCheck },
  { label: 'My classes', icon: BookOpen },
  { label: 'Courses', icon: GraduationCap },
  { label: 'Discover courses', icon: Search },
  { label: 'Schedule', icon: CalendarDays },
  { label: 'Attendance', icon: ClipboardCheck },
];

const workspaceLabels: Partial<Record<string, TranslationKey>> = {
  Overview: 'workspace.overview', Students: 'workspace.students', Tasks: 'workspace.tasks',
  'My classes': 'workspace.myClasses', Courses: 'workspace.courses',
  'Discover courses': 'workspace.discoverCourses', Schedule: 'workspace.schedule',
  Attendance: 'workspace.attendance', 'My children': 'workspace.myChildren',
  'Fee invoices': 'workspace.feeInvoices', 'My learning': 'workspace.myLearning',
  'Plan & billing': 'workspace.planBilling', 'AI learning': 'workspace.aiLearning',
  'AI Learning': 'workspace.aiLearning',
  Announcements: 'workspace.announcements', Feedback: 'workspace.feedback',
  'Help centre': 'workspace.helpCentre', Settings: 'workspace.settings',
  'Platform plans': 'workspace.platformPlans',
};

function translateWorkspaceLabel(label: string, t: (key: TranslationKey) => string): string {
  const key = workspaceLabels[label];
  return key ? t(key) : label;
}

function translateLearnerStatus(status: string, t: (key: TranslationKey) => string): string {
  const keys: Record<string, TranslationKey> = {
    present: 'learner.statusPresent', late: 'learner.statusLate', absent: 'learner.statusAbsent',
    excused: 'learner.statusExcused', submitted: 'learner.statusSubmitted', graded: 'learner.statusGraded',
  };
  const key = keys[status.toLowerCase()];
  return key ? t(key) : status;
}

function StudentsDirectoryPanel({ token, organization }: { token: string; organization: Organization }) {
  const [students, setStudents] = useState<StudentProfile[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [query, setQuery] = useState('');
  const [name, setName] = useState('');
  const [studentCode, setStudentCode] = useState('');
  const [formOpen, setFormOpen] = useState(false);
  const canManage = hasPermission(organization, 'academic.manage');

  async function refresh() {
    setLoading(true); setError('');
    try { setStudents(await listStudents(token, organization.id)); }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not load the student roster.'); }
    finally { setLoading(false); }
  }

  useEffect(() => { void refresh(); }, [token, organization.id]);

  const filteredStudents = useMemo(() => {
    const normalized = query.trim().toLocaleLowerCase();
    return students.filter((student) => !normalized || student.name.toLocaleLowerCase().includes(normalized) || student.student_code.toLocaleLowerCase().includes(normalized));
  }, [students, query]);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setSaving(true); setError('');
    try {
      const created = await createStudent(token, organization.id, { name: name.trim(), student_code: studentCode.trim() });
      setStudents((items) => [...items, created].sort((left, right) => left.name.localeCompare(right.name)));
      setName(''); setStudentCode(''); setFormOpen(false);
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not add this student.'); }
    finally { setSaving(false); }
  }

  return <section className="panel academic-panel students-directory-panel"><div className="panel-heading"><div><div className="eyebrow">STUDENT ROSTER</div><h2>Students</h2><p>Find active student profiles in this workspace.</p></div>{canManage && <button type="button" className="task-submit" onClick={() => setFormOpen((open) => !open)}>{formOpen ? 'Cancel' : 'Add student'}</button>}</div>
    <div className="students-directory-controls"><label><span className="sr-only">Search students by name or student code</span><Search size={15} /><input type="search" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search name or student code" /></label><span>{loading ? 'Loading…' : `${students.length} active ${students.length === 1 ? 'student' : 'students'}`}</span></div>
    {error && <div className="academic-error" role="alert">{error}</div>}
    {formOpen && canManage && <form className="task-form students-create-form" onSubmit={(event) => void submit(event)}><label>Student name<input autoComplete="off" required maxLength={160} value={name} onChange={(event) => setName(event.target.value)} /></label><label>Student code<input autoComplete="off" required maxLength={64} value={studentCode} onChange={(event) => setStudentCode(event.target.value)} /></label><button className="task-submit" disabled={saving || !name.trim() || !studentCode.trim()}>{saving ? 'Saving…' : 'Add to roster'}</button></form>}
    {loading ? <div className="task-message">Loading student roster…</div> : filteredStudents.length ? <div className="students-directory-grid" aria-live="polite">{filteredStudents.map((student) => <article className="students-directory-card" key={student.id}><span className="student-directory-avatar" aria-hidden="true">{student.name.trim().slice(0, 1).toLocaleUpperCase()}</span><span className="students-directory-identity"><strong>{student.name}</strong><small>{student.student_code}</small></span><span className="student-directory-status">Active</span></article>)}</div> : <div className="academic-empty">{query.trim() ? 'No students match your search.' : 'No active student profiles are in this workspace yet.'}</div>}
  </section>;
}

function MyClassesPanel({ token, organization, onAttendanceRecorded, showAttendanceHistory = false }: { token: string; organization: Organization; onAttendanceRecorded: (rate: number, className: string, date: string) => void; showAttendanceHistory?: boolean }) {
  const { t } = useLocale();
  const [classes, setClasses] = useState<OrganizationClass[]>([]);
  const [sections, setSections] = useState<OrganizationSection[]>([]);
  const [students, setStudents] = useState<StudentProfile[]>([]);
  const [sessions, setSessions] = useState<AttendanceSession[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [className, setClassName] = useState('');
  const [grade, setGrade] = useState('');
  const [studentName, setStudentName] = useState('');
  const [studentCode, setStudentCode] = useState('');
  const [enrollChoice, setEnrollChoice] = useState<Record<string, string>>({});
  const [enrollSectionChoice, setEnrollSectionChoice] = useState<Record<string, string>>({});
  const [rosterSectionChoice, setRosterSectionChoice] = useState<Record<string, string>>({});
  const [attendanceClass, setAttendanceClass] = useState<OrganizationClass | null>(null);
  const [attendanceSectionId, setAttendanceSectionId] = useState('');
  const [attendanceMarks, setAttendanceMarks] = useState<Record<string, AttendanceStatus>>({});
  const attendanceDialogRef = useRef<HTMLFormElement>(null);
  const attendanceTriggerRef = useRef<HTMLElement | null>(null);
  const [saving, setSaving] = useState(false);
  const canManage = hasPermission(organization, 'academic.manage');
  const canMarkAttendance = hasPermission(organization, 'attendance.write');

  async function refresh() {
    setLoading(true);
    setError('');
    try {
      const [nextClasses, nextStudents, nextSessions, nextSections] = await Promise.all([
        listClasses(token, organization.id), listStudents(token, organization.id), listAttendanceSessions(token, organization.id), listSections(token, organization.id),
      ]);
      setClasses(nextClasses);
      setStudents(nextStudents);
      setSessions(nextSessions);
      setSections(nextSections);
      const latest = nextSessions.find((session) => session.class_id) ?? nextSessions[0];
      if (latest) onAttendanceRecorded(latest.attendance_rate, latest.class_name, latest.session_date);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Could not load class data.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { void refresh(); }, [token, organization.id]);

  useEffect(() => {
    if (!attendanceClass) {
      attendanceTriggerRef.current?.focus();
      attendanceTriggerRef.current = null;
      return;
    }

    const dialog = attendanceDialogRef.current;
    const focusable = () => Array.from(dialog?.querySelectorAll<HTMLElement>(
      'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    ) ?? []);
    focusable()[0]?.focus();

    function keepFocusInside(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        event.preventDefault();
        setAttendanceClass(null);
        return;
      }
      if (event.key !== 'Tab') return;
      const controls = focusable();
      const first = controls[0];
      const last = controls.at(-1);
      if (!first || !last) return;
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    }

    document.addEventListener('keydown', keepFocusInside);
    return () => document.removeEventListener('keydown', keepFocusInside);
  }, [attendanceClass]);

  async function addClass(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSaving(true);
    setError('');
    try {
      await createClass(token, organization.id, { name: className.trim(), ...(grade.trim() ? { grade: grade.trim() } : {}) });
      setClassName(''); setGrade('');
      await refresh();
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not create class.'); }
    finally { setSaving(false); }
  }

  async function addStudent(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSaving(true);
    setError('');
    try {
      await createStudent(token, organization.id, { name: studentName.trim(), student_code: studentCode.trim() });
      setStudentName(''); setStudentCode('');
      await refresh();
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not add student.'); }
    finally { setSaving(false); }
  }

  async function enroll(classItem: OrganizationClass) {
    const studentId = enrollChoice[classItem.id];
    if (!studentId) return;
    setSaving(true);
    setError('');
    try {
      await enrollStudent(token, organization.id, classItem.id, studentId, enrollSectionChoice[classItem.id] || undefined);
      setEnrollChoice((choices) => ({ ...choices, [classItem.id]: '' }));
      setEnrollSectionChoice((choices) => ({ ...choices, [classItem.id]: '' }));
      await refresh();
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not enroll student.'); }
    finally { setSaving(false); }
  }

  async function changeStudentSection(classItem: OrganizationClass, student: StudentProfile) {
    const key = `${classItem.id}:${student.id}`;
    const sectionId = rosterSectionChoice[key] ?? student.section_id ?? '';
    if (sectionId === (student.section_id ?? '')) return;
    setSaving(true);
    setError('');
    try {
      await enrollStudent(token, organization.id, classItem.id, student.id, sectionId || undefined);
      await refresh();
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not update the student section.'); }
    finally { setSaving(false); }
  }

  function openAttendance(classItem: OrganizationClass) {
    attendanceTriggerRef.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    const initialSection = classItem.can_attend_whole ? '' : classItem.assigned_section_ids[0] ?? '';
    const latest = sessions.find((session) => session.class_id === classItem.id && session.section_id === (initialSection || null));
    const existing = new Map((latest?.records ?? []).map((record) => [record.student_id, record.status]));
    setAttendanceClass(classItem);
    setAttendanceSectionId(initialSection);
    setAttendanceMarks(Object.fromEntries(classItem.students.map((student) => [student.id, existing.get(student.id) ?? 'present'])));
  }

  const attendanceStudents = attendanceClass?.students.filter((student) => !attendanceSectionId || student.section_id === attendanceSectionId) ?? [];

  async function submitAttendance(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!attendanceClass || !attendanceStudents.length) return;
    setSaving(true);
    setError('');
    try {
      const session = await markClassAttendance(token, organization.id, attendanceClass.id,
        attendanceStudents.map((student) => ({ student_id: student.id, status: attendanceMarks[student.id] ?? 'present' })), attendanceSectionId || undefined);
      onAttendanceRecorded(session.attendance_rate, session.class_name, session.session_date);
      setAttendanceClass(null);
      await refresh();
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not save attendance.'); }
    finally { setSaving(false); }
  }

  return <section className="panel academic-panel">
    <div className="panel-heading"><div><div className="eyebrow">{t('classes.academicRoster')}</div><h2>{t('classes.title')}</h2><p>{t('classes.description')}</p></div></div>
    {error && <div className="academic-error" role="alert">{error}</div>}
    {canManage && <div className="academic-forms">
      <form className="academic-form" onSubmit={(event) => void addClass(event)}><strong>{t('classes.addClass')}</strong><label>{t('classes.className')}<input required maxLength={100} value={className} onChange={(event) => setClassName(event.target.value)} placeholder={t('classes.classExample')} /></label><label>{t('classes.grade')}<input maxLength={40} value={grade} onChange={(event) => setGrade(event.target.value)} placeholder="6" /></label><button className="task-submit" disabled={saving || !className.trim()}>{saving ? t('classes.saving') : t('classes.createClass')}</button></form>
      <form className="academic-form" onSubmit={(event) => void addStudent(event)}><strong>{t('classes.addStudent')}</strong><label>{t('classes.studentName')}<input required maxLength={160} value={studentName} onChange={(event) => setStudentName(event.target.value)} placeholder={t('classes.fullName')} /></label><label>{t('classes.studentId')}<input required maxLength={64} value={studentCode} onChange={(event) => setStudentCode(event.target.value)} placeholder={t('classes.studentExample')} /></label><button className="task-submit" disabled={saving || !studentName.trim() || !studentCode.trim()}>{saving ? t('classes.saving') : t('classes.addStudent')}</button></form>
    </div>}
    {loading ? <div className="task-message">{t('classes.loading')}</div> : classes.length ? <div className="class-cards">{classes.map((classItem) => {
      const enrolledIds = new Set(classItem.students.map((student) => student.id));
      const available = students.filter((student) => !enrolledIds.has(student.id));
      const classSections = sections.filter((section) => section.class_id === classItem.id && (classItem.can_attend_whole || classItem.assigned_section_ids.includes(section.id)));
      return <article className="class-card" key={classItem.id}><div className="class-card-heading"><div><strong>{classItem.name}</strong><span>{classItem.grade ? `${t('classes.gradePrefix')} ${classItem.grade}` : t('classes.class')}{classItem.section ? ` · Section ${classItem.section}` : ''} · {classItem.student_count} {t('classes.students')}</span></div>{canMarkAttendance && <button className="text-button" onClick={() => openAttendance(classItem)} disabled={!classItem.students.length}>{t('classes.takeAttendance')}</button>}</div>{classItem.students.length ? <ul className="roster-list">{classItem.students.map((student) => { const sectionKey = `${classItem.id}:${student.id}`; return <li key={student.id}><span className="student-avatar">{student.name.split(/\s+/).map((part) => part[0]).slice(0, 2).join('').toUpperCase()}</span><span><strong>{student.name}</strong><small>{student.student_code}{student.section_id ? ` · ${classSections.find((section) => section.id === student.section_id)?.name ?? 'Section'}` : ''}</small></span>{canManage && classSections.length > 0 && <span className="roster-section-control"><select aria-label={`${t('classes.sectionFor')} ${student.name}`} value={rosterSectionChoice[sectionKey] ?? student.section_id ?? ''} onChange={(event) => setRosterSectionChoice((choices) => ({ ...choices, [sectionKey]: event.target.value }))}><option value="">{t('classes.noSection')}</option>{classSections.map((section) => <option key={section.id} value={section.id}>{section.name}</option>)}</select><button type="button" onClick={() => void changeStudentSection(classItem, student)} disabled={saving || (rosterSectionChoice[sectionKey] ?? student.section_id ?? '') === (student.section_id ?? '')}>{t('classes.save')}</button></span>}</li>; })}</ul> : <div className="task-message">{t('classes.noStudentsEnrolled')}</div>}{canManage && available.length > 0 && <div className="enroll-row"><select aria-label={`${t('classes.chooseStudent')} ${classItem.name}`} value={enrollChoice[classItem.id] ?? ''} onChange={(event) => setEnrollChoice((choices) => ({ ...choices, [classItem.id]: event.target.value }))}><option value="">{t('classes.enrollStudent')}</option>{available.map((student) => <option key={student.id} value={student.id}>{student.name} · {student.student_code}</option>)}</select><select aria-label={`${t('classes.chooseSection')} ${classItem.name}`} value={enrollSectionChoice[classItem.id] ?? ''} onChange={(event) => setEnrollSectionChoice((choices) => ({ ...choices, [classItem.id]: event.target.value }))}><option value="">{t('classes.noSection')}</option>{classSections.map((section) => <option key={section.id} value={section.id}>{section.name}</option>)}</select><button className="task-cancel" onClick={() => void enroll(classItem)} disabled={saving || !enrollChoice[classItem.id]}>{t('classes.enroll')}</button></div>}</article>;
    })}</div> : <div className="academic-empty">{canManage ? t('classes.noClassesManager') : t('classes.noClassesMember')}</div>}
    {showAttendanceHistory && <section className="attendance-history" aria-labelledby="attendance-history-title"><div className="course-progress-heading"><h3 id="attendance-history-title">{t('classes.recentAttendance')}</h3><span>{t('classes.latestSessions')} {sessions.length}</span></div>{sessions.length ? <div className="attendance-history-list">{sessions.map((session) => <article className="attendance-history-card" key={session.id}><div><strong>{session.class_name}</strong><small>{session.session_date}{session.section_id ? ` · ${t('classes.sectionSession')}` : ''}</small></div><span className="attendance-history-rate">{session.attendance_rate}% {t('classes.present')}</span>{session.records?.length ? <details><summary>{session.records.length} {t('classes.learnerRecords')}</summary><ul>{session.records.map((record) => <li key={record.student_id}>{record.student_name ?? t('classes.learner')} · {translateLearnerStatus(record.status, t)}</li>)}</ul></details> : null}</article>)}</div> : <div className="academic-empty">{t('classes.noAttendance')}</div>}</section>}
    {attendanceClass && <div className="attendance-dialog-backdrop"><form ref={attendanceDialogRef} className="attendance-dialog" role="dialog" aria-modal="true" aria-labelledby="attendance-dialog-title" onSubmit={(event) => void submitAttendance(event)}><div className="attendance-dialog-heading"><div><div className="eyebrow">{t('classes.dailyAttendance')}</div><h3 id="attendance-dialog-title">{attendanceClass.name}</h3></div><button type="button" className="more-button" aria-label={t('classes.closeAttendance')} onClick={() => setAttendanceClass(null)}><X size={17} /></button></div>{(attendanceClass.can_attend_whole || attendanceClass.assigned_section_ids.length > 1) && <label>{t('classes.attendanceRoster')}<select value={attendanceSectionId} onChange={(event) => setAttendanceSectionId(event.target.value)}>{attendanceClass.can_attend_whole && <option value="">{t('classes.wholeClass')}</option>}{attendanceClass.assigned_section_ids.map((sectionId) => <option key={sectionId} value={sectionId}>{sections.find((section) => section.id === sectionId)?.name ?? 'Section'}</option>)}</select></label>}<p>{t('classes.markStudents')}</p><div className="attendance-mark-list">{attendanceStudents.map((student) => <label key={student.id}><span><strong>{student.name}</strong><small>{student.student_code}</small></span><select aria-label={`${t('classes.attendanceFor')} ${student.name}`} value={attendanceMarks[student.id] ?? 'present'} onChange={(event) => setAttendanceMarks((marks) => ({ ...marks, [student.id]: event.target.value as AttendanceStatus }))}><option value="present">{t('classes.presentStatus')}</option><option value="absent">{t('classes.absentStatus')}</option><option value="late">{t('classes.lateStatus')}</option><option value="excused">{t('classes.excusedStatus')}</option></select></label>)}</div><div className="task-form-actions"><button type="button" className="task-cancel" onClick={() => setAttendanceClass(null)}>{t('classes.cancel')}</button><button className="task-submit" disabled={saving || attendanceStudents.length === 0}>{saving ? t('classes.saving') : t('classes.saveAttendance')}</button></div></form></div>}
  </section>;
}

function SchedulePanel({ token, organization }: { token: string; organization: Organization }) {
  const { locale } = useLocale();
  const scheduleText = (english: string, hindi: string) => locale === 'hi' ? hindi : english;
  const weekdayNames = Array.from({ length: 7 }, (_, index) => new Intl.DateTimeFormat(locale === 'hi' ? 'hi-IN' : 'en-IN', { weekday: 'long', timeZone: 'UTC' }).format(new Date(Date.UTC(2024, 0, index + 1))));
  const shortWeekdayNames = Array.from({ length: 7 }, (_, index) => new Intl.DateTimeFormat(locale === 'hi' ? 'hi-IN' : 'en-IN', { weekday: 'short', timeZone: 'UTC' }).format(new Date(Date.UTC(2024, 0, index + 1))));
  const formatScheduleDate = (value: string) => new Intl.DateTimeFormat(locale === 'hi' ? 'hi-IN' : 'en-IN', { dateStyle: 'medium', timeZone: 'UTC' }).format(new Date(`${value}T00:00:00Z`));
  const yearStatusLabel = (status: string) => status === 'active' ? scheduleText('Active', 'सक्रिय') : status === 'planned' ? scheduleText('Planned', 'नियोजित') : scheduleText('Completed', 'पूर्ण');
  const [years, setYears] = useState<AcademicYear[]>([]);
  const [classes, setClasses] = useState<OrganizationClass[]>([]);
  const [sections, setSections] = useState<OrganizationSection[]>([]);
  const [subjects, setSubjects] = useState<OrganizationSubject[]>([]);
  const [teachers, setTeachers] = useState<TeacherProfile[]>([]);
  const [publicTitle, setPublicTitle] = useState('');
  const [publicBio, setPublicBio] = useState('');
  const [publicProfileEnabled, setPublicProfileEnabled] = useState(false);
  const [memberCandidates, setMemberCandidates] = useState<TeacherMemberCandidate[]>([]);
  const [classTeacherAssignments, setClassTeacherAssignments] = useState<ClassTeacherAssignment[]>([]);
  const [classTeacherHistory, setClassTeacherHistory] = useState<ClassTeacherAssignmentEvent[]>([]);
  const [subjectOfferings, setSubjectOfferings] = useState<SubjectOffering[]>([]);
  const [entries, setEntries] = useState<TimetableEntry[]>([]);
  const [scheduleExceptions, setScheduleExceptions] = useState<ScheduleException[]>([]);
  const [exceptionType, setExceptionType] = useState<'closure' | 'cancel' | 'extra'>('closure');
  const [exceptionDate, setExceptionDate] = useState(() => new Intl.DateTimeFormat('en-CA', { timeZone: organization.timezone, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date()));
  const [exceptionTitle, setExceptionTitle] = useState('');
  const [exceptionNotes, setExceptionNotes] = useState('');
  const [exceptionClassId, setExceptionClassId] = useState('');
  const [exceptionSectionId, setExceptionSectionId] = useState('');
  const [exceptionSubjectId, setExceptionSubjectId] = useState('');
  const [exceptionTeacherId, setExceptionTeacherId] = useState('');
  const [exceptionStartsAt, setExceptionStartsAt] = useState('09:00');
  const [exceptionEndsAt, setExceptionEndsAt] = useState('10:00');
  const [exceptionRoom, setExceptionRoom] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const nowYear = new Date().getFullYear();
  const [yearName, setYearName] = useState(`${nowYear}-${nowYear + 1}`);
  const [startsOn, setStartsOn] = useState(`${nowYear}-04-01`);
  const [endsOn, setEndsOn] = useState(`${nowYear + 1}-03-31`);
  const [yearStatus, setYearStatus] = useState<'planned' | 'active'>('active');
  const [sectionClassId, setSectionClassId] = useState('');
  const [sectionName, setSectionName] = useState('');
  const [entryYearId, setEntryYearId] = useState('');
  const [entryClassId, setEntryClassId] = useState('');
  const [entrySectionId, setEntrySectionId] = useState('');
  const [subjectCode, setSubjectCode] = useState('');
  const [subjectName, setSubjectName] = useState('');
  const [teacherCode, setTeacherCode] = useState('');
  const [teacherName, setTeacherName] = useState('');
  const [subjectId, setSubjectId] = useState('');
  const [teacherProfileId, setTeacherProfileId] = useState('');
  const [linkTeacherId, setLinkTeacherId] = useState('');
  const [linkMemberId, setLinkMemberId] = useState('');
  const [assignmentTeacherId, setAssignmentTeacherId] = useState('');
  const [assignmentSectionId, setAssignmentSectionId] = useState('');
  const [offeringSubjectId, setOfferingSubjectId] = useState('');
  const [offeringSectionId, setOfferingSectionId] = useState('');
  const [editingAssignmentId, setEditingAssignmentId] = useState('');
  const [editingTeacherId, setEditingTeacherId] = useState('');
  const [weekday, setWeekday] = useState(1);
  const [startsAt, setStartsAt] = useState('09:00');
  const [endsAt, setEndsAt] = useState('10:00');
  const [room, setRoom] = useState('');
  const [editingTimetableEntryId, setEditingTimetableEntryId] = useState('');
  const canManage = hasPermission(organization, 'academic.manage');
  const activeYear = years.find((year) => year.status === 'active');
  const classSections = sections.filter((section) => section.class_id === entryClassId);
  const assignmentSections = sections.filter((section) => section.class_id === entryClassId);
  const lessonSubjects = subjectOfferings.length
    ? subjects.filter((subject) => subjectOfferings.some((offering) => offering.subject_id === subject.id
      && (!entrySectionId ? !offering.section_id : (!offering.section_id || offering.section_id === entrySectionId))))
    : subjects;

  async function refresh() {
    setLoading(true);
    setError('');
    try {
      const [nextYears, nextClasses, nextSections, nextSubjects, nextTeachers, nextCandidates] = await Promise.all([
        listAcademicYears(token, organization.id), listClasses(token, organization.id), listSections(token, organization.id),
        listSubjects(token, organization.id), listTeacherProfiles(token, organization.id),
        canManage ? listTeacherMemberCandidates(token, organization.id) : Promise.resolve([]),
      ]);
      setYears(nextYears);
      setClasses(nextClasses);
      setSections(nextSections);
      setSubjects(nextSubjects);
      setTeachers(nextTeachers);
      const ownProfile = nextTeachers.find((item) => item.is_my_profile);
      setPublicTitle(ownProfile?.public_title ?? '');
      setPublicBio(ownProfile?.public_bio ?? '');
      setPublicProfileEnabled(ownProfile?.is_public ?? false);
      setMemberCandidates(nextCandidates);
      const nextActiveYear = nextYears.find((year) => year.status === 'active');
      const selectedYear = nextYears.find((year) => year.id === entryYearId && year.status !== 'completed') ?? nextActiveYear ?? nextYears[0];
      setEntryYearId(selectedYear?.id ?? '');
      setSectionClassId((current) => nextClasses.some((item) => item.id === current) ? current : nextClasses[0]?.id || '');
      setEntryClassId((current) => nextClasses.some((item) => item.id === current) ? current : nextClasses[0]?.id || '');
      setLinkTeacherId((current) => nextTeachers.some((item) => item.id === current) ? current : nextTeachers.find((item) => !item.user_id)?.id || '');
      const [nextEntries, nextExceptions] = selectedYear
        ? await Promise.all([listTimetable(token, organization.id, selectedYear.id), listScheduleExceptions(token, organization.id, selectedYear.id)])
        : [[], []];
      setEntries(nextEntries); setScheduleExceptions(nextExceptions);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : scheduleText('Could not load academic schedule.','शैक्षणिक समय-सारणी लोड नहीं हो सकी।'));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { void refresh(); }, [token, organization.id]);

  useEffect(() => {
    if (!entryClassId || !entryYearId) { setClassTeacherAssignments([]); setClassTeacherHistory([]); setSubjectOfferings([]); return; }
    let cancelled = false;
    setSubjectOfferings([]); setClassTeacherHistory([]); setClassTeacherAssignments([]);
    Promise.all([
      listClassTeachers(token, organization.id, entryClassId, entryYearId),
      listClassTeacherHistory(token, organization.id, entryClassId, entryYearId),
      listSubjectOfferings(token, organization.id, entryClassId, entryYearId),
    ]).then(([assignments, history, offerings]) => {
      if (cancelled) return;
      setClassTeacherAssignments(assignments); setClassTeacherHistory(history); setSubjectOfferings(offerings);
    }).catch((cause) => { if (!cancelled) setError(cause instanceof Error ? cause.message : scheduleText('Could not load academic assignments.','शैक्षणिक नियुक्तियाँ लोड नहीं हो सकीं।')); });
    return () => { cancelled = true; };
  }, [token, organization.id, entryClassId, entryYearId]);

  async function refreshClassConfiguration() {
    if (!entryClassId || !entryYearId) return;
    const [assignments, history, offerings] = await Promise.all([
      listClassTeachers(token, organization.id, entryClassId, entryYearId),
      listClassTeacherHistory(token, organization.id, entryClassId, entryYearId),
      listSubjectOfferings(token, organization.id, entryClassId, entryYearId),
    ]);
    setClassTeacherAssignments(assignments); setClassTeacherHistory(history); setSubjectOfferings(offerings);
  }

  async function selectYear(yearId: string) {
    setEntryYearId(yearId);
    setSubjectId('');
    setError('');
    if (!yearId) { setEntries([]); setScheduleExceptions([]); return; }
    try {
      const [nextEntries, nextExceptions] = await Promise.all([listTimetable(token, organization.id, yearId), listScheduleExceptions(token, organization.id, yearId)]);
      setEntries(nextEntries); setScheduleExceptions(nextExceptions);
    }
    catch (cause) { setError(cause instanceof Error ? cause.message : scheduleText('Could not load timetable.','समय-सारणी लोड नहीं हो सकी।')); }
  }

  async function submitYear(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setSaving(true); setError('');
    try {
      await createAcademicYear(token, organization.id, { name: yearName.trim(), starts_on: startsOn, ends_on: endsOn, status: yearStatus });
      await refresh();
    } catch (cause) { setError(cause instanceof Error ? cause.message : scheduleText('Could not save academic year.','शैक्षणिक वर्ष सहेजा नहीं जा सका।')); }
    finally { setSaving(false); }
  }

  async function makeYearActive(yearId: string) {
    setSaving(true); setError('');
    try { await activateAcademicYear(token, organization.id, yearId); await refresh(); }
    catch (cause) { setError(cause instanceof Error ? cause.message : scheduleText('Could not activate academic year.','शैक्षणिक वर्ष सक्रिय नहीं हो सका।')); }
    finally { setSaving(false); }
  }

  async function submitSection(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setSaving(true); setError('');
    try {
      await createSection(token, organization.id, sectionClassId, sectionName.trim());
      setSectionName(''); await refresh();
    } catch (cause) { setError(cause instanceof Error ? cause.message : scheduleText('Could not save section.','अनुभाग सहेजा नहीं जा सका।')); }
    finally { setSaving(false); }
  }

  async function submitSubject(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setSaving(true); setError('');
    try {
      await createSubject(token, organization.id, { code: subjectCode.trim(), name: subjectName.trim() });
      setSubjectCode(''); setSubjectName(''); await refresh();
    } catch (cause) { setError(cause instanceof Error ? cause.message : scheduleText('Could not add subject.','विषय नहीं जोड़ा जा सका।')); }
    finally { setSaving(false); }
  }

  async function submitTeacher(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setSaving(true); setError('');
    try {
      await createTeacherProfile(token, organization.id, { employee_code: teacherCode.trim(), name: teacherName.trim() });
      setTeacherCode(''); setTeacherName(''); await refresh();
    } catch (cause) { setError(cause instanceof Error ? cause.message : scheduleText('Could not add teacher.','शिक्षक नहीं जोड़ा जा सका।')); }
    finally { setSaving(false); }
  }

  async function submitMemberLink(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!linkTeacherId) return;
    setSaving(true); setError('');
    try {
      await linkTeacherMember(token, organization.id, linkTeacherId, linkMemberId ? Number(linkMemberId) : null);
      setLinkMemberId('');
      await refresh();
    } catch (cause) { setError(cause instanceof Error ? cause.message : scheduleText('Could not link teacher account.','शिक्षक खाता लिंक नहीं हो सका।')); }
    finally { setSaving(false); }
  }

  async function savePublicProfile(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const ownProfile = teachers.find((item) => item.is_my_profile);
    if (!ownProfile) return;
    setSaving(true); setError('');
    try {
      const updated = await updateTeacherPublicProfile(token, organization.id, ownProfile.id, {
        public_title: publicTitle.trim() || null, public_bio: publicBio.trim() || null, is_public: publicProfileEnabled,
      });
      setTeachers((current) => current.map((item) => item.id === updated.id ? updated : item));
    } catch (cause) { setError(cause instanceof Error ? cause.message : scheduleText('Could not save public profile.','सार्वजनिक प्रोफ़ाइल सहेजी नहीं जा सकी।')); }
    finally { setSaving(false); }
  }

  async function submitClassTeacher(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!entryYearId || !entryClassId || !assignmentTeacherId) return;
    setSaving(true); setError('');
    try {
      await assignClassTeacher(token, organization.id, entryClassId, {
        teacher_profile_id: assignmentTeacherId, academic_year_id: entryYearId,
        ...(assignmentSectionId ? { section_id: assignmentSectionId } : {}),
      });
      setAssignmentTeacherId(''); setAssignmentSectionId('');
      await refreshClassConfiguration();
    } catch (cause) { setError(cause instanceof Error ? cause.message : scheduleText('Could not assign class teacher.','कक्षा शिक्षक नियुक्त नहीं हो सका।')); }
    finally { setSaving(false); }
  }

  async function saveEditedClassTeacher(event: FormEvent<HTMLFormElement>, assignmentId: string) {
    event.preventDefault(); if (!entryClassId || !editingTeacherId) return;
    setSaving(true); setError('');
    try {
      await updateClassTeacher(token, organization.id, entryClassId, assignmentId, editingTeacherId);
      setEditingAssignmentId(''); setEditingTeacherId(''); await refreshClassConfiguration();
    } catch (cause) { setError(cause instanceof Error ? cause.message : scheduleText('Could not update class teacher.','कक्षा शिक्षक अपडेट नहीं हो सका।')); }
    finally { setSaving(false); }
  }

  async function removeClassTeacher(assignmentId: string) {
    if (!entryClassId) return;
    setSaving(true); setError('');
    try { await endClassTeacher(token, organization.id, entryClassId, assignmentId); await refreshClassConfiguration(); }
    catch (cause) { setError(cause instanceof Error ? cause.message : scheduleText('Could not end class teacher assignment.','कक्षा शिक्षक नियुक्ति समाप्त नहीं हो सकी।')); }
    finally { setSaving(false); }
  }

  async function submitSubjectOffering(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!entryClassId || !entryYearId || !offeringSubjectId) return;
    setSaving(true); setError('');
    try {
      await createSubjectOffering(token, organization.id, entryClassId, {
        academic_year_id: entryYearId, subject_id: offeringSubjectId,
        ...(offeringSectionId ? { section_id: offeringSectionId } : {}),
      });
      setOfferingSubjectId(''); setOfferingSectionId(''); await refreshClassConfiguration();
    } catch (cause) { setError(cause instanceof Error ? cause.message : scheduleText('Could not offer subject to this class.','इस कक्षा को विषय उपलब्ध नहीं कराया जा सका।')); }
    finally { setSaving(false); }
  }

  async function removeSubjectOffering(offeringId: string) {
    if (!entryClassId) return;
    setSaving(true); setError('');
    try { await endSubjectOffering(token, organization.id, entryClassId, offeringId); await refreshClassConfiguration(); }
    catch (cause) { setError(cause instanceof Error ? cause.message : scheduleText('Could not end subject offering.','विषय उपलब्धता समाप्त नहीं हो सकी।')); }
    finally { setSaving(false); }
  }

  async function submitEntry(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setSaving(true); setError('');
    try {
      const input = {
        academic_year_id: entryYearId, class_id: entryClassId,
        ...(entrySectionId ? { section_id: entrySectionId } : {}), subject_id: subjectId,
        ...(teacherProfileId ? { teacher_profile_id: teacherProfileId } : {}), weekday,
        starts_at: startsAt, ends_at: endsAt, ...(room.trim() ? { room: room.trim() } : {}),
      };
      if (editingTimetableEntryId) await updateTimetableEntry(token, organization.id, editingTimetableEntryId, input);
      else await createTimetableEntry(token, organization.id, input);
      setEditingTimetableEntryId(''); setSubjectId(''); setTeacherProfileId(''); setRoom(''); await refresh();
    } catch (cause) { setError(cause instanceof Error ? cause.message : scheduleText('Could not save timetable entry.','समय-सारणी का पाठ सहेजा नहीं जा सका।')); }
    finally { setSaving(false); }
  }

  function editTimetableEntry(entry: TimetableEntry) {
    setEditingTimetableEntryId(entry.id);
    setEntryYearId(entry.academic_year_id);
    setEntryClassId(entry.class_id);
    setEntrySectionId(entry.section_id ?? '');
    setSubjectId(entry.subject_id ?? '');
    setTeacherProfileId(entry.teacher_profile_id ?? '');
    setWeekday(entry.weekday);
    setStartsAt(entry.starts_at);
    setEndsAt(entry.ends_at);
    setRoom(entry.room ?? '');
    setError('');
  }

  function cancelTimetableEdit() {
    setEditingTimetableEntryId(''); setSubjectId(''); setTeacherProfileId(''); setRoom('');
  }

  async function submitScheduleException(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setSaving(true); setError('');
    try {
      const created = await createScheduleException(token, organization.id, {
        academic_year_id: entryYearId, exception_date: exceptionDate, type: exceptionType,
        title: exceptionTitle.trim(), ...(exceptionNotes.trim() ? { notes: exceptionNotes.trim() } : {}),
        ...(exceptionType !== 'closure' ? { class_id: exceptionClassId, ...(exceptionSectionId ? { section_id: exceptionSectionId } : {}) } : {}),
        ...(exceptionType === 'extra' ? {
          subject_id: exceptionSubjectId, ...(exceptionTeacherId ? { teacher_profile_id: exceptionTeacherId } : {}),
          starts_at: exceptionStartsAt, ends_at: exceptionEndsAt, ...(exceptionRoom.trim() ? { room: exceptionRoom.trim() } : {}),
        } : {}),
      });
      setScheduleExceptions((current) => [created, ...current]);
      setExceptionTitle(''); setExceptionNotes(''); setExceptionClassId(''); setExceptionSectionId('');
      setExceptionSubjectId(''); setExceptionTeacherId(''); setExceptionRoom('');
    } catch (cause) { setError(cause instanceof Error ? cause.message : scheduleText('Could not save schedule exception.','तारीख़ का बदलाव सहेजा नहीं जा सका।')); }
    finally { setSaving(false); }
  }

  async function cancelScheduleChange(exceptionId: string) {
    setSaving(true); setError('');
    try {
      const updated = await cancelScheduleException(token, organization.id, exceptionId);
      setScheduleExceptions((current) => current.map((item) => item.id === updated.id ? updated : item));
    } catch (cause) { setError(cause instanceof Error ? cause.message : scheduleText('Could not cancel schedule exception.','समय-सारणी का बदलाव रद्द नहीं हो सका।')); }
    finally { setSaving(false); }
  }

  return <section className="panel academic-panel schedule-panel">
    <div className="panel-heading"><div><div className="eyebrow">{scheduleText('ACADEMIC CALENDAR','शैक्षणिक कैलेंडर')}</div><h2>{scheduleText('Schedule and terms','समय-सारणी और सत्र')}</h2><p>{scheduleText('Set an academic year, organize class sections, and add weekly lesson times.','शैक्षणिक वर्ष तय करें, कक्षा अनुभाग व्यवस्थित करें और साप्ताहिक पाठ समय जोड़ें।')}</p></div></div>
    {error && <div className="academic-error" role="alert">{error}</div>}
    {canManage && <div className="academic-forms schedule-forms">
      <form className="academic-form" onSubmit={(event) => void submitYear(event)}><strong>{scheduleText('Academic year','शैक्षणिक वर्ष')}</strong><label>{scheduleText('Name','नाम')}<input required maxLength={100} value={yearName} onChange={(event) => setYearName(event.target.value)} /></label><div className="academic-form-row"><label>{scheduleText('Starts','शुरू')}<input type="date" required value={startsOn} onChange={(event) => setStartsOn(event.target.value)} /></label><label>{scheduleText('Ends','समाप्त')}<input type="date" required value={endsOn} onChange={(event) => setEndsOn(event.target.value)} /></label></div><label>{scheduleText('Status','स्थिति')}<select value={yearStatus} onChange={(event) => setYearStatus(event.target.value as 'planned' | 'active')}><option value="active">{scheduleText('Active year','सक्रिय वर्ष')}</option><option value="planned">{scheduleText('Planned','नियोजित')}</option></select></label><button className="task-submit" disabled={saving || !yearName.trim()}>{saving ? scheduleText('Saving…','सहेजा जा रहा है…') : scheduleText('Save year','वर्ष सहेजें')}</button></form>
      <form className="academic-form" onSubmit={(event) => void submitSection(event)}><strong>{scheduleText('Class section','कक्षा अनुभाग')}</strong><label>{scheduleText('Class','कक्षा')}<select required value={sectionClassId} onChange={(event) => setSectionClassId(event.target.value)}><option value="">{scheduleText('Choose a class…','कक्षा चुनें…')}</option>{classes.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label><label>{scheduleText('Section name','अनुभाग का नाम')}<input required maxLength={64} value={sectionName} onChange={(event) => setSectionName(event.target.value)} placeholder={scheduleText('e.g. A','जैसे, अ')} /></label><button className="task-submit" disabled={saving || !sectionClassId || !sectionName.trim()}>{saving ? scheduleText('Saving…','सहेजा जा रहा है…') : scheduleText('Add section','अनुभाग जोड़ें')}</button><small className="form-hint">{sections.length ? sections.map((item) => `${item.class_name} · ${item.name}`).join(', ') : scheduleText('No sections yet.','अभी कोई अनुभाग नहीं है।')}</small></form>
      <form className="academic-form" onSubmit={(event) => void submitSubject(event)}><strong>{scheduleText('Subject catalog','विषय सूची')}</strong><label>{scheduleText('Subject name','विषय का नाम')}<input required maxLength={120} value={subjectName} onChange={(event) => setSubjectName(event.target.value)} placeholder={scheduleText('e.g. Mathematics','जैसे, गणित')} /></label><label>{scheduleText('Subject code','विषय कोड')}<input required maxLength={32} value={subjectCode} onChange={(event) => setSubjectCode(event.target.value)} placeholder={scheduleText('e.g. MATH-01','जैसे, गणित-01')} /></label><button className="task-submit" disabled={saving || !subjectName.trim() || !subjectCode.trim()}>{saving ? scheduleText('Saving…','सहेजा जा रहा है…') : scheduleText('Add subject','विषय जोड़ें')}</button><small className="form-hint">{subjects.length ? `${subjects.length} active subjects` : scheduleText('Add a subject before scheduling lessons.','पाठ तय करने से पहले विषय जोड़ें।')}</small></form>
      <form className="academic-form" onSubmit={(event) => void submitTeacher(event)}><strong>{scheduleText('Teacher profile','शिक्षक प्रोफ़ाइल')}</strong><label>{scheduleText('Teacher name','शिक्षक का नाम')}<input required maxLength={160} value={teacherName} onChange={(event) => setTeacherName(event.target.value)} placeholder={scheduleText('Full name','पूरा नाम')} /></label><label>{scheduleText('Employee code','कर्मचारी कोड')}<input required maxLength={64} value={teacherCode} onChange={(event) => setTeacherCode(event.target.value)} placeholder={scheduleText('e.g. T-104','जैसे, शि-104')} /></label><button className="task-submit" disabled={saving || !teacherName.trim() || !teacherCode.trim()}>{saving ? scheduleText('Saving…','सहेजा जा रहा है…') : scheduleText('Add teacher','शिक्षक जोड़ें')}</button><small className="form-hint">{teachers.length ? `${teachers.length} active teachers` : scheduleText('Teacher profiles can be assigned to lessons.','शिक्षक प्रोफ़ाइल पाठों से जोड़ी जा सकती हैं।')}</small></form>
      <form className="academic-form" onSubmit={(event) => void submitMemberLink(event)}><strong>{scheduleText('Link teacher account','शिक्षक खाता जोड़ें')}</strong><label>{scheduleText('Teacher profile','शिक्षक प्रोफ़ाइल')}<select required value={linkTeacherId} onChange={(event) => setLinkTeacherId(event.target.value)}><option value="">{scheduleText('Choose a teacher…','शिक्षक चुनें…')}</option>{teachers.map((item) => <option key={item.id} value={item.id}>{item.name}{item.user_id ? ' · Linked' : ''}</option>)}</select></label><label>{scheduleText('Workspace teacher account','कार्यस्थान शिक्षक खाता')}<select value={linkMemberId} onChange={(event) => setLinkMemberId(event.target.value)}><option value="">{scheduleText('No account linked','कोई खाता जुड़ा नहीं है')}</option>{memberCandidates.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label><button className="task-submit" disabled={saving || !linkTeacherId}>{saving ? scheduleText('Saving…','सहेजा जा रहा है…') : scheduleText('Save account link','खाता लिंक सहेजें')}</button><small className="form-hint">{memberCandidates.length ? scheduleText('Only active teacher members can be linked.','केवल सक्रिय शिक्षक सदस्यों को लिंक किया जा सकता है।') : scheduleText('No unlinked teacher member accounts are available.','लिंक करने के लिए कोई बिना जुड़ा शिक्षक खाता उपलब्ध नहीं है।')}</small></form>
      <form className="academic-form" onSubmit={(event) => void submitClassTeacher(event)}><strong>{scheduleText('Class teacher assignment','कक्षा शिक्षक नियुक्ति')}</strong><label>{scheduleText('Class','कक्षा')}<select required value={entryClassId} onChange={(event) => { setEntryClassId(event.target.value); setAssignmentSectionId(''); }}><option value="">{scheduleText('Choose a class…','कक्षा चुनें…')}</option>{classes.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label><label>{scheduleText('Academic year','शैक्षणिक वर्ष')}<select required value={entryYearId} onChange={(event) => void selectYear(event.target.value)}><option value="">{scheduleText('Choose a year…','वर्ष चुनें…')}</option>{years.filter((year) => year.status !== 'completed').map((year) => <option key={year.id} value={year.id}>{year.name}{year.status === 'active' ? ` · ${scheduleText('Active','सक्रिय')}` : ''}</option>)}</select></label><label>{scheduleText('Section','अनुभाग')}<select value={assignmentSectionId} onChange={(event) => setAssignmentSectionId(event.target.value)}><option value="">{scheduleText('Whole class','पूरी कक्षा')}</option>{assignmentSections.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label><label>{scheduleText('Teacher','शिक्षक')}<select required value={assignmentTeacherId} onChange={(event) => setAssignmentTeacherId(event.target.value)}><option value="">{scheduleText('Choose a teacher…','शिक्षक चुनें…')}</option>{teachers.map((item) => <option key={item.id} value={item.id}>{item.name} · {item.employee_code}</option>)}</select></label><button className="task-submit" disabled={saving || !entryYearId || !entryClassId || !assignmentTeacherId}>{saving ? scheduleText('Saving…','सहेजा जा रहा है…') : scheduleText('Assign class teacher','कक्षा शिक्षक नियुक्त करें')}</button></form>
      <form className="academic-form" onSubmit={(event) => void submitSubjectOffering(event)}><strong>{scheduleText('Class subject offering','कक्षा के विषय')}</strong><label>{scheduleText('Class','कक्षा')}<select required value={entryClassId} onChange={(event) => { setEntryClassId(event.target.value); setOfferingSectionId(''); }}><option value="">{scheduleText('Choose a class…','कक्षा चुनें…')}</option>{classes.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label><label>{scheduleText('Academic year','शैक्षणिक वर्ष')}<select required value={entryYearId} onChange={(event) => void selectYear(event.target.value)}><option value="">{scheduleText('Choose a year…','वर्ष चुनें…')}</option>{years.filter((year) => year.status !== 'completed').map((year) => <option key={year.id} value={year.id}>{year.name}{year.status === 'active' ? ` · ${scheduleText('Active','सक्रिय')}` : ''}</option>)}</select></label><label>{scheduleText('Section','अनुभाग')}<select value={offeringSectionId} onChange={(event) => setOfferingSectionId(event.target.value)}><option value="">{scheduleText('Whole class','पूरी कक्षा')}</option>{assignmentSections.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label><label>{scheduleText('Subject','विषय')}<select required value={offeringSubjectId} onChange={(event) => setOfferingSubjectId(event.target.value)}><option value="">{scheduleText('Choose a subject…','विषय चुनें…')}</option>{subjects.map((item) => <option key={item.id} value={item.id}>{item.name} · {item.code}</option>)}</select></label><button className="task-submit" disabled={saving || !entryYearId || !entryClassId || !offeringSubjectId}>{saving ? scheduleText('Saving…','सहेजा जा रहा है…') : scheduleText('Offer subject','विषय उपलब्ध करें')}</button></form>
      <form className="academic-form timetable-form" onSubmit={(event) => void submitEntry(event)}><strong>{editingTimetableEntryId ? scheduleText('Edit weekly lesson','साप्ताहिक पाठ संपादित करें') : scheduleText('Weekly lesson','साप्ताहिक पाठ')}</strong><label>{scheduleText('Academic year','शैक्षणिक वर्ष')}<select required value={entryYearId} onChange={(event) => void selectYear(event.target.value)}><option value="">{scheduleText('Choose a year…','वर्ष चुनें…')}</option>{years.filter((year) => year.status !== 'completed').map((year) => <option key={year.id} value={year.id}>{year.name}{year.status === 'active' ? ` · ${scheduleText('Active','सक्रिय')}` : ''}</option>)}</select></label><div className="academic-form-row"><label>{scheduleText('Class','कक्षा')}<select required value={entryClassId} onChange={(event) => { setEntryClassId(event.target.value); setEntrySectionId(''); setSubjectId(''); }}><option value="">{scheduleText('Choose a class…','कक्षा चुनें…')}</option>{classes.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label><label>{scheduleText('Section','अनुभाग')}<select value={entrySectionId} onChange={(event) => { setEntrySectionId(event.target.value); setSubjectId(''); }}><option value="">{scheduleText('Whole class','पूरी कक्षा')}</option>{classSections.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label></div><label>{scheduleText('Subject','विषय')}<select required value={subjectId} onChange={(event) => setSubjectId(event.target.value)}><option value="">{scheduleText('Choose a subject…','विषय चुनें…')}</option>{lessonSubjects.map((item) => <option key={item.id} value={item.id}>{item.name} · {item.code}</option>)}</select></label>{subjectOfferings.length > 0 && <small className="form-hint">{scheduleText('Showing subjects offered to the selected class and section.','चुनी हुई कक्षा और अनुभाग को दिए गए विषय दिखाए जा रहे हैं।')}</small>}<label>{scheduleText('Teacher','शिक्षक')}<select value={teacherProfileId} onChange={(event) => setTeacherProfileId(event.target.value)}><option value="">{scheduleText('Unassigned','नियत नहीं')}</option>{teachers.map((item) => <option key={item.id} value={item.id}>{item.name} · {item.employee_code}</option>)}</select></label><div className="academic-form-row"><label>{scheduleText('Day','दिन')}<select value={weekday} onChange={(event) => setWeekday(Number(event.target.value))}>{weekdayNames.map((name, index) => <option key={name} value={index + 1}>{name}</option>)}</select></label><label>{scheduleText('Room','कमरा')}<input maxLength={80} value={room} onChange={(event) => setRoom(event.target.value)} placeholder="Optional" /></label></div><div className="academic-form-row"><label>{scheduleText('Starts','शुरू')}<input type="time" required value={startsAt} onChange={(event) => setStartsAt(event.target.value)} /></label><label>{scheduleText('Ends','समाप्त')}<input type="time" required value={endsAt} onChange={(event) => setEndsAt(event.target.value)} /></label></div><button className="task-submit" disabled={saving || !entryYearId || !entryClassId || !subjectId}>{saving ? scheduleText('Saving…','सहेजा जा रहा है…') : editingTimetableEntryId ? scheduleText('Save changes','बदलाव सहेजें') : scheduleText('Add lesson','पाठ जोड़ें')}</button>{editingTimetableEntryId && <button type="button" className="task-cancel" onClick={cancelTimetableEdit}>{scheduleText('Cancel edit','संपादन रद्द करें')}</button>}</form>
    </div>}
    {canManage && <div className="academic-years-list">{years.map((year) => <div key={year.id}><span><strong>{year.name}</strong><small>{formatScheduleDate(year.starts_on)} – {formatScheduleDate(year.ends_on)} · {yearStatusLabel(year.status)}</small></span>{year.status === 'planned' && <button type="button" className="task-cancel" onClick={() => void makeYearActive(year.id)} disabled={saving}>{scheduleText('Make active','सक्रिय करें')}</button>}</div>)}</div>}
    {!canManage && <div className="academic-years-readonly">{years.map((year) => <span key={year.id}>{year.name} · {yearStatusLabel(year.status)}</span>)}</div>}
    {teachers.find((item) => item.is_my_profile) && <form className="academic-form public-profile-form" onSubmit={(event) => void savePublicProfile(event)}><strong>{scheduleText('Your public educator profile','आपकी सार्वजनिक शिक्षक प्रोफ़ाइल')}</strong><p>{scheduleText('Publishing is optional. Your profile appears only with public courses assigned to you.','प्रोफ़ाइल प्रकाशित करना वैकल्पिक है। यह केवल आपको दिए गए सार्वजनिक कोर्स के साथ दिखेगी।')}</p><label>{scheduleText('Teaching title','शिक्षण परिचय')}<input maxLength={120} value={publicTitle} onChange={(event) => setPublicTitle(event.target.value)} placeholder={scheduleText('e.g. Mathematics educator','जैसे, गणित शिक्षक')} /></label><label>{scheduleText('Short introduction','संक्षिप्त परिचय')}<textarea maxLength={1200} value={publicBio} onChange={(event) => setPublicBio(event.target.value)} placeholder={scheduleText('Share a short professional introduction.','अपना संक्षिप्त पेशेवर परिचय लिखें।')} /></label><label className="public-profile-toggle"><input type="checkbox" checked={publicProfileEnabled} onChange={(event) => setPublicProfileEnabled(event.target.checked)} /> {scheduleText('Show my profile with public courses','सार्वजनिक कोर्स के साथ मेरी प्रोफ़ाइल दिखाएँ')}</label><button className="task-submit" disabled={saving}>{saving ? scheduleText('Saving…','सहेजा जा रहा है…') : scheduleText('Save educator profile','शिक्षक प्रोफ़ाइल सहेजें')}</button></form>}
    <div className="class-teacher-list"><div className="timetable-list-heading"><div><div className="eyebrow">{scheduleText('SUBJECT OFFERINGS','उपलब्ध विषय')}</div><h3>{classes.find((item) => item.id === entryClassId)?.name ?? scheduleText('Choose a class','कक्षा चुनें')} · {years.find((year) => year.id === entryYearId)?.name ?? scheduleText('Choose a year','वर्ष चुनें')}</h3></div></div>{subjectOfferings.length ? subjectOfferings.map((offering) => <div className="class-teacher-row" key={offering.id}><span><strong>{offering.subject_name}</strong><small>{offering.subject_code} · {offering.section_name ? `Section ${offering.section_name}` : scheduleText('Whole class','पूरी कक्षा')}</small></span>{canManage && <button type="button" className="task-cancel" onClick={() => void removeSubjectOffering(offering.id)} disabled={saving}>{scheduleText('End offering','विषय हटाएँ')}</button>}</div>) : <div className="academic-empty">{scheduleText('No subjects offered to this class for the selected year.','चुने हुए वर्ष में इस कक्षा को कोई विषय उपलब्ध नहीं है।')}</div>}</div>
    <div className="class-teacher-list"><div className="timetable-list-heading"><div><div className="eyebrow">{scheduleText('CLASS TEACHERS','कक्षा शिक्षक')}</div><h3>{classes.find((item) => item.id === entryClassId)?.name ?? scheduleText('Choose a class','कक्षा चुनें')} · {years.find((year) => year.id === entryYearId)?.name ?? scheduleText('Choose a year','वर्ष चुनें')}</h3></div></div>{classTeacherAssignments.length ? classTeacherAssignments.map((assignment) => <div className="class-teacher-row class-teacher-row-editable" key={assignment.id}>{editingAssignmentId === assignment.id ? <form onSubmit={(event) => void saveEditedClassTeacher(event, assignment.id)}><label>{scheduleText('Teacher','शिक्षक')}<select required value={editingTeacherId} onChange={(event) => setEditingTeacherId(event.target.value)}>{teachers.map((item) => <option key={item.id} value={item.id}>{item.name} · {item.employee_code}</option>)}</select></label><button className="task-submit" disabled={saving || !editingTeacherId}>{saving ? scheduleText('Saving…','सहेजा जा रहा है…') : scheduleText('Save','सहेजें')}</button><button type="button" className="task-cancel" onClick={() => { setEditingAssignmentId(''); setEditingTeacherId(''); }}>{scheduleText('Cancel','रद्द करें')}</button></form> : <><span><strong>{assignment.teacher_name}</strong><small>{assignment.section_name ? `Section ${assignment.section_name}` : scheduleText('Whole class','पूरी कक्षा')}{assignment.member_name ? ` · Account: ${assignment.member_name}` : ' · No account linked'}</small></span>{canManage && <div className="class-teacher-actions"><button type="button" className="text-button" onClick={() => { setEditingAssignmentId(assignment.id); setEditingTeacherId(assignment.teacher_profile_id); }}>{scheduleText('Change','बदलें')}</button><button type="button" className="task-cancel" onClick={() => void removeClassTeacher(assignment.id)} disabled={saving}>{scheduleText('End assignment','नियुक्ति समाप्त करें')}</button></div>}</>}</div>) : <div className="academic-empty">{scheduleText('No class teacher assignments for this class and year yet.','इस कक्षा और वर्ष के लिए अभी कोई कक्षा शिक्षक नियुक्त नहीं है।')}</div>}</div>
    {!!classTeacherHistory.length && <div className="class-teacher-history"><strong>{scheduleText('Assignment history','नियुक्ति इतिहास')}</strong>{classTeacherHistory.map((event) => <div key={event.id}><span>{event.action === 'assigned' ? `${scheduleText('Assigned','नियुक्त')} ${event.to_teacher}` : event.action === 'reassigned' ? `${scheduleText('Changed','बदला')} ${event.from_teacher} ${scheduleText('to','से')} ${event.to_teacher}` : `${scheduleText('Ended','समाप्त')} ${event.from_teacher}`}{event.section_name ? ` · ${scheduleText('Section','अनुभाग')} ${event.section_name}` : ''}</span><small>{event.actor_name} · {new Intl.DateTimeFormat(locale === 'hi' ? 'hi-IN' : 'en-IN', { dateStyle: 'medium', timeStyle: 'short', timeZone: organization.timezone }).format(new Date(event.created_at))}</small></div>)}</div>}
    <div className="timetable-list-heading"><div><div className="eyebrow">{scheduleText('WEEKLY TIMETABLE','साप्ताहिक समय-सारणी')}</div><h3>{activeYear?.name ?? (entryYearId ? years.find((year) => year.id === entryYearId)?.name : null) ?? scheduleText('No academic year selected','कोई शैक्षणिक वर्ष नहीं चुना गया')}</h3></div></div>
    {loading ? <div className="task-message">{scheduleText('Loading timetable…','समय-सारणी लोड हो रही है…')}</div> : entries.length ? <div className="timetable-entry-list">{entries.map((entry) => <article className="timetable-entry" key={entry.id}>{canManage && <button type="button" className="text-button timetable-entry-edit" aria-label={`${scheduleText('Edit','संपादित करें')} ${entry.subject} ${scheduleText('timetable entry','समय-सारणी प्रविष्टि')}`} onClick={() => editTimetableEntry(entry)}><Pencil size={14} />{scheduleText('Edit','संपादित करें')}</button>}<div className="timetable-day">{shortWeekdayNames[entry.weekday - 1]}</div><div className="timetable-time">{entry.starts_at}<small>{entry.ends_at}</small></div><div className="agenda-color violet" /><div className="agenda-copy"><strong>{entry.subject}</strong><span>{entry.class_name}{entry.section_name ? ` · ${entry.section_name}` : ''}{entry.teacher_name ? ` · ${entry.teacher_name}` : ''}{entry.room ? ` · ${entry.room}` : ''}</span></div></article>)}</div> : <div className="academic-empty">{activeYear ? scheduleText('No lessons scheduled for this academic year yet.','इस शैक्षणिक वर्ष के लिए अभी कोई पाठ तय नहीं है।') : scheduleText('Create an academic year and add lessons to build a timetable.','समय-सारणी बनाने के लिए शैक्षणिक वर्ष बनाएँ और पाठ जोड़ें।')}</div>}
    {canManage && <div className="class-teacher-list schedule-exceptions-panel"><div className="timetable-list-heading"><div><div className="eyebrow">{scheduleText('DATED SCHEDULE CHANGES','तारीख़ के अनुसार बदलाव')}</div><h3>{scheduleText('Closures and one-day lessons','बंद रहने के दिन और एक-दिवसीय पाठ')}</h3><p>{scheduleText('Apply a closure, class cancellation, or extra lesson to a date in the selected academic year.','चुने हुए शैक्षणिक वर्ष की किसी तारीख़ पर अवकाश, कक्षा रद्द करना या अतिरिक्त पाठ जोड़ें।')}</p></div></div>
      <form className="academic-form schedule-exception-form" onSubmit={(event) => void submitScheduleException(event)}><strong>{scheduleText('Add a dated change','तारीख़ का बदलाव जोड़ें')}</strong><div className="academic-form-row"><label>{scheduleText('Change type','बदलाव का प्रकार')}<select value={exceptionType} onChange={(event) => { setExceptionType(event.target.value as 'closure' | 'cancel' | 'extra'); setExceptionClassId(''); setExceptionSectionId(''); }}><option value="closure">{scheduleText('School closure','विद्यालय बंद')}</option><option value="cancel">{scheduleText('Cancel a class day','कक्षा का दिन रद्द करें')}</option><option value="extra">{scheduleText('Add an extra lesson','अतिरिक्त पाठ जोड़ें')}</option></select></label><label>{scheduleText('Date','तारीख़')}<input type="date" required value={exceptionDate} onChange={(event) => setExceptionDate(event.target.value)} /></label></div>
        <label>{scheduleText('Title','शीर्षक')}<input required maxLength={120} value={exceptionTitle} onChange={(event) => setExceptionTitle(event.target.value)} placeholder={exceptionType === 'closure' ? scheduleText('e.g. Diwali holiday','जैसे, दिवाली की छुट्टी') : exceptionType === 'cancel' ? scheduleText('e.g. Teacher training day','जैसे, शिक्षक प्रशिक्षण दिवस') : scheduleText('e.g. Science revision class','जैसे, विज्ञान दोहराव कक्षा')} /></label>
        {exceptionType !== 'closure' && <div className="academic-form-row"><label>{scheduleText('Class','कक्षा')}<select required value={exceptionClassId} onChange={(event) => { setExceptionClassId(event.target.value); setExceptionSectionId(''); }}><option value="">{scheduleText('Choose a class…','कक्षा चुनें…')}</option>{classes.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label><label>{scheduleText('Section','अनुभाग')}<select value={exceptionSectionId} onChange={(event) => setExceptionSectionId(event.target.value)}><option value="">{scheduleText('Whole class','पूरी कक्षा')}</option>{sections.filter((item) => item.class_id === exceptionClassId).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label></div>}
        {exceptionType === 'extra' && <><label>{scheduleText('Subject','विषय')}<select required value={exceptionSubjectId} onChange={(event) => setExceptionSubjectId(event.target.value)}><option value="">{scheduleText('Choose a subject…','विषय चुनें…')}</option>{subjects.filter((item) => item.status === 'active').map((item) => <option key={item.id} value={item.id}>{item.name} · {item.code}</option>)}</select></label><label>{scheduleText('Teacher','शिक्षक')}<select value={exceptionTeacherId} onChange={(event) => setExceptionTeacherId(event.target.value)}><option value="">{scheduleText('Unassigned','नियत नहीं')}</option>{teachers.filter((item) => item.status === 'active').map((item) => <option key={item.id} value={item.id}>{item.name} · {item.employee_code}</option>)}</select></label><div className="academic-form-row"><label>{scheduleText('Starts','शुरू')}<input type="time" required value={exceptionStartsAt} onChange={(event) => setExceptionStartsAt(event.target.value)} /></label><label>{scheduleText('Ends','समाप्त')}<input type="time" required value={exceptionEndsAt} onChange={(event) => setExceptionEndsAt(event.target.value)} /></label></div><label>{scheduleText('Room','कमरा')}<input maxLength={80} value={exceptionRoom} onChange={(event) => setExceptionRoom(event.target.value)} placeholder="Optional" /></label></>}
        <label>{scheduleText('Notes','टिप्पणियाँ')}<textarea maxLength={2000} value={exceptionNotes} onChange={(event) => setExceptionNotes(event.target.value)} placeholder={scheduleText('Optional details for learners and staff','विद्यार्थियों और कर्मचारियों के लिए वैकल्पिक जानकारी')} /></label><small className="form-hint">{scheduleText('A school closure suppresses the full day\'s timetable. A class cancellation applies to the selected class or section only.','विद्यालय बंद होने पर पूरे दिन की समय-सारणी रोक दी जाती है। कक्षा रद्द करने का बदलाव केवल चुनी हुई कक्षा या अनुभाग पर लागू होता है।')}</small><button className="task-submit" disabled={saving || !entryYearId || !exceptionTitle.trim() || (exceptionType !== 'closure' && !exceptionClassId) || (exceptionType === 'extra' && !exceptionSubjectId)}>{saving ? scheduleText('Saving…','सहेजा जा रहा है…') : scheduleText('Save dated change','तारीख़ का बदलाव सहेजें')}</button>
      </form>
      <div className="schedule-exception-list">{scheduleExceptions.length ? scheduleExceptions.map((item) => <article className="schedule-exception-row" key={item.id}><div><strong>{item.title}</strong><small>{formatScheduleDate(item.exception_date)} · {item.type === 'closure' ? scheduleText('School closure','विद्यालय बंद') : item.type === 'cancel' ? `${scheduleText('Cancelled','रद्द')} ${item.class_name ?? scheduleText('class','कक्षा')}${item.section_name ? ` · ${scheduleText('Section','अनुभाग')} ${item.section_name}` : ''}` : `${scheduleText('Extra lesson','अतिरिक्त पाठ')} · ${item.class_name ?? scheduleText('Class','कक्षा')}${item.section_name ? ` · ${scheduleText('Section','अनुभाग')} ${item.section_name}` : ''} · ${item.starts_at}–${item.ends_at}`}</small>{item.notes && <small>{item.notes}</small>}</div><span className={`schedule-exception-status ${item.status}`}>{item.status === 'active' ? scheduleText('Active','सक्रिय') : scheduleText('Cancelled','रद्द')}</span>{item.status === 'active' && <button type="button" className="task-cancel" onClick={() => void cancelScheduleChange(item.id)} disabled={saving}>{scheduleText('Cancel change','बदलाव रद्द करें')}</button>}</article>) : <div className="academic-empty">{scheduleText('No dated schedule changes for this academic year.','इस शैक्षणिक वर्ष के लिए तारीख़ के अनुसार कोई बदलाव नहीं है।')}</div>}</div>
    </div>}  </section>;
}

function LiveClassroomsPanel({ token, organization }: { token: string; organization: Organization }) {
  const { locale, t } = useLocale();
  const [sessions, setSessions] = useState<LiveClassSession[]>([]);
  const [classes, setClasses] = useState<OrganizationClass[]>([]);
  const [sections, setSections] = useState<OrganizationSection[]>([]);
  const [courses, setCourses] = useState<Course[]>([]);
  const [targetType, setTargetType] = useState<'class' | 'course'>('class');
  const [classId, setClassId] = useState('');
  const [sectionId, setSectionId] = useState('');
  const [courseId, setCourseId] = useState('');
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [meetingUrl, setMeetingUrl] = useState('');
  const [startsAt, setStartsAt] = useState('');
  const [endsAt, setEndsAt] = useState('');
  const [recordingUrls, setRecordingUrls] = useState<Record<string, string>>({});
  const [rosterSessionId, setRosterSessionId] = useState('');
  const [roster, setRoster] = useState<LiveSessionRosterStudent[]>([]);
  const [attendanceMarks, setAttendanceMarks] = useState<Record<string, AttendanceStatus>>({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const canCreate = hasPermission(organization, 'live_sessions.manage');
  const canManageAttendance = hasPermission(organization, 'attendance.write');

  async function refresh() {
    setLoading(true); setError('');
    try {
      const [nextSessions, nextClasses, nextSections, nextCourses] = await Promise.all([
        listLiveSessions(token, organization.id),
        canCreate ? listClasses(token, organization.id) : Promise.resolve([]),
        canCreate ? listSections(token, organization.id) : Promise.resolve([]),
        canCreate ? listCourses(token, organization.id) : Promise.resolve([]),
      ]);
      setSessions(nextSessions); setClasses(nextClasses); setSections(nextSections); setCourses(nextCourses);
      setClassId((current) => nextClasses.some((item) => item.id === current) ? current : nextClasses[0]?.id ?? '');
      setCourseId((current) => nextCourses.some((item) => item.id === current) ? current : nextCourses[0]?.id ?? '');
    } catch (cause) { setError(cause instanceof Error ? cause.message : t('live.loadError')); }
    finally { setLoading(false); }
  }
  useEffect(() => { void refresh(); }, [token, organization.id]);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!title.trim() || !meetingUrl.trim() || !startsAt || !endsAt) return;
    const start = workspaceDateTimeToIso(startsAt, organization.timezone);
    const end = workspaceDateTimeToIso(endsAt, organization.timezone);
    if (!start || !end) { setError(t('live.timezoneError').replace('{timezone}', organization.timezone)); return; }
    if (new Date(end).getTime() <= new Date(start).getTime()) { setError(t('live.endBeforeStart')); return; }
    setSaving(true); setError('');
    try {
      await createLiveSession(token, organization.id, {
        ...(targetType === 'class' ? { class_id: classId, ...(sectionId ? { section_id: sectionId } : {}) } : { course_id: courseId }),
        title: title.trim(), ...(description.trim() ? { description: description.trim() } : {}),
        starts_at: start, ends_at: end, meeting_url: meetingUrl.trim(),
      });
      setTitle(''); setDescription(''); setMeetingUrl(''); setStartsAt(''); setEndsAt(''); await refresh();
    } catch (cause) { setError(cause instanceof Error ? cause.message : t('live.scheduleError')); }
    finally { setSaving(false); }
  }

  async function join(session: LiveClassSession) {
    setError('');
    try { const access = await joinLiveSession(token, organization.id, session.id); window.location.assign(access.meeting_url); }
    catch (cause) { setError(cause instanceof Error ? cause.message : t('live.joinError')); }
  }

  async function changeStatus(session: LiveClassSession, status: 'live' | 'completed' | 'cancelled') {
    setSaving(true); setError('');
    try {
      const recordingUrl = recordingUrls[session.id]?.trim();
      const updated = await updateLiveSession(token, organization.id, session.id, {
        status, ...(status === 'completed' && recordingUrl ? { recording_url: recordingUrl } : {}),
      });
      setSessions((items) => items.map((item) => item.id === updated.id ? updated : item));
    } catch (cause) { setError(cause instanceof Error ? cause.message : t('live.updateError')); }
    finally { setSaving(false); }
  }

  async function openAttendance(session: LiveClassSession) {
    setSaving(true); setError('');
    try {
      const students = await getLiveSessionRoster(token, organization.id, session.id);
      setRoster(students); setRosterSessionId(session.id);
      setAttendanceMarks(Object.fromEntries(students.map((student) => [student.id, 'present' as AttendanceStatus])));
    } catch (cause) { setError(cause instanceof Error ? cause.message : t('live.rosterError')); }
    finally { setSaving(false); }
  }

  async function saveAttendance(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!rosterSessionId || !roster.length) return;
    setSaving(true); setError('');
    try {
      await recordLiveSessionAttendance(token, organization.id, rosterSessionId, roster.map((student) => ({ student_id: student.id, status: attendanceMarks[student.id] ?? 'present' })));
      setRosterSessionId(''); setRoster([]); await refresh();
    } catch (cause) { setError(cause instanceof Error ? cause.message : t('live.attendanceSaveError')); }
    finally { setSaving(false); }
  }

  const dateTime = (value: string) => new Date(value).toLocaleString(locale === 'hi' ? 'hi-IN' : 'en-IN', { dateStyle: 'medium', timeStyle: 'short', timeZone: organization.timezone });
  const activeSections = sections.filter((section) => section.class_id === classId);
  return <section className="panel academic-panel live-classrooms-panel">
    <div className="panel-heading"><div><div className="eyebrow">{t('live.eyebrow')}</div><h2>{t('live.title')}</h2><p>{t('live.description')}</p></div></div>
    {error && <div className="academic-error" role="alert">{error}</div>}
    {canCreate && <form className="academic-form live-session-form" onSubmit={(event) => void submit(event)}><strong>{t('live.schedule')}</strong>
      <label>{t('live.audience')}<select value={targetType} onChange={(event) => setTargetType(event.target.value as 'class' | 'course')}><option value="class">{t('live.classSection')}</option><option value="course">{t('live.courseLearners')}</option></select></label>
      {targetType === 'class' ? <div className="academic-form-row"><label>{t('live.class')}<select required value={classId} onChange={(event) => { setClassId(event.target.value); setSectionId(''); }}><option value="">{t('live.chooseClass')}</option>{classes.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label><label>{t('live.section')}<select value={sectionId} onChange={(event) => setSectionId(event.target.value)}><option value="">{t('live.wholeClass')}</option>{activeSections.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label></div>
        : <label>{t('live.course')}<select required value={courseId} onChange={(event) => setCourseId(event.target.value)}><option value="">{t('live.chooseCourse')}</option>{courses.map((course) => <option key={course.id} value={course.id}>{course.title}</option>)}</select></label>}
      <label>{t('live.sessionTitle')}<input required maxLength={180} value={title} onChange={(event) => setTitle(event.target.value)} placeholder={t('live.titleExample')} /></label>
      <label>{t('live.descriptionField')}<textarea maxLength={3000} value={description} onChange={(event) => setDescription(event.target.value)} /></label>
      <div className="academic-form-row"><label>{t('live.starts')} ({organization.timezone})<input type="datetime-local" required value={startsAt} onChange={(event) => setStartsAt(event.target.value)} /></label><label>{t('live.ends')} ({organization.timezone})<input type="datetime-local" required value={endsAt} onChange={(event) => setEndsAt(event.target.value)} /></label></div>
      <label>{t('live.meetingUrl')}<input type="url" required pattern="https://.*" value={meetingUrl} onChange={(event) => setMeetingUrl(event.target.value)} placeholder="https://meet.google.com/…" /></label>
      <button className="task-submit" disabled={saving || !title.trim() || !(targetType === 'class' ? classId : courseId)}>{saving ? t('live.saving') : t('live.scheduleButton')}</button>
      <small className="form-hint">{t('live.providerNote')}</small>
    </form>}
    <div className="live-session-list"><div className="course-progress-heading"><strong>{canCreate ? t('live.workspaceSessions') : t('live.yourSessions')}</strong><span>{sessions.length}</span></div>
      {loading ? <div className="task-message">{t('live.loading')}</div> : sessions.length ? sessions.map((session) => <article className="live-session-card" key={session.id}>
        <div className="live-session-main"><div><span className={`live-session-status ${session.status}`}>{t(`live.status.${session.status}`)}</span><h3>{session.title}</h3><p>{session.class_name ? `${session.class_name}${session.section_name ? ` · ${t('live.section')} ${session.section_name}` : ''}` : session.course_title ?? t('live.classSession')}</p><small>{dateTime(session.starts_at)} – {dateTime(session.ends_at)} · {t(`live.provider.${session.provider}`)}</small>{session.description && <p>{session.description}</p>}</div>
          <div className="live-session-actions">{session.join_available && <button type="button" className="task-submit" disabled={saving} onClick={() => void join(session)}>{t('live.join')}</button>}{session.recording_url && <a className="task-cancel" href={session.recording_url} target="_blank" rel="noreferrer">{t('live.openRecording')}</a>}
            {canCreate && session.status === 'scheduled' && <><button type="button" className="task-cancel" disabled={saving} onClick={() => void changeStatus(session, 'live')}>{t('live.start')}</button><button type="button" className="task-cancel" disabled={saving} onClick={() => void changeStatus(session, 'cancelled')}>{t('live.cancel')}</button></>}
            {canCreate && session.status === 'live' && <><input aria-label={t('live.recordingUrl')} type="url" pattern="https://.*" placeholder={t('live.optionalRecordingUrl')} value={recordingUrls[session.id] ?? ''} onChange={(event) => setRecordingUrls((values) => ({ ...values, [session.id]: event.target.value }))} /><button type="button" className="task-cancel" disabled={saving} onClick={() => void changeStatus(session, 'completed')}>{t('live.endSession')}</button><button type="button" className="task-cancel" disabled={saving} onClick={() => void changeStatus(session, 'cancelled')}>{t('live.cancel')}</button></>}
            {canManageAttendance && ['live', 'completed'].includes(session.status) && <button type="button" className="task-cancel" disabled={saving} onClick={() => void openAttendance(session)}>{t('live.takeAttendance')}</button>}
          </div>
        </div>
        {rosterSessionId === session.id && <form className="live-attendance-form" onSubmit={(event) => void saveAttendance(event)}><strong>{t('live.attendanceTitle')}</strong>{roster.map((student) => <label className="live-attendance-row" key={student.id}><span>{student.name}<small>{student.student_code}</small></span><select value={attendanceMarks[student.id] ?? 'present'} onChange={(event) => setAttendanceMarks((marks) => ({ ...marks, [student.id]: event.target.value as AttendanceStatus }))}><option value="present">{t('live.present')}</option><option value="absent">{t('live.absent')}</option><option value="late">{t('live.late')}</option><option value="excused">{t('live.excused')}</option></select></label>)}<div className="lesson-edit-actions"><button className="task-submit" disabled={saving || !roster.length}>{saving ? t('live.saving') : t('live.saveAttendance')}</button><button type="button" className="task-cancel" onClick={() => { setRosterSessionId(''); setRoster([]); }}>{t('live.close')}</button></div><small className="form-hint">{t('live.attendanceNote')}</small></form>}
      </article>) : <div className="academic-empty">{t('live.noSessions')}</div>}
    </div>
  </section>;
}

function NotificationsPopover({ token, organization, onUnreadCount, onOpenFeedback }: { token: string; organization: Organization; onUnreadCount: Dispatch<SetStateAction<number>>; onOpenFeedback: () => void }) {
  const [items, setItems] = useState<WorkspaceNotification[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [itemsOrganizationId, setItemsOrganizationId] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelled = false;
    setItems([]); setUnreadCount(0); setItemsOrganizationId(''); setLoading(true); setError('');
    listWorkspaceNotifications(token, organization.id).then((result) => {
      if (!cancelled) { setItems(result.items); setUnreadCount(result.unread_count); setItemsOrganizationId(organization.id); onUnreadCount(result.unread_count); }
    }).catch((cause) => { if (!cancelled) setError(cause instanceof Error ? cause.message : 'Could not load notifications.'); })
      .finally(() => { if (!cancelled) setLoading(false); });
    return () => { cancelled = true; };
  }, [token, organization.id]);

  async function markRead(item: WorkspaceNotification) {
    if (item.read_at) return;
    try {
      await markWorkspaceNotificationRead(token, organization.id, item.id);
      const next = items.map((current) => current.id === item.id ? { ...current, read_at: new Date().toISOString() } : current);
      setItems(next);
      setUnreadCount((count) => Math.max(0, count - 1));
      onUnreadCount((currentCount) => Math.max(0, currentCount - 1));
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not mark this notification read.'); }
  }

  const visibleItems = itemsOrganizationId === organization.id ? items : [];
  return <aside className="notification-popover" aria-label="Notifications"><div className="notification-popover-heading"><strong>Notifications</strong><span>{unreadCount} unread</span></div>{error && <div className="academic-error" role="alert">{error}</div>}{loading || itemsOrganizationId !== organization.id ? <div className="task-message">Loading notifications…</div> : visibleItems.length ? <div className="notification-items">{visibleItems.map((item) => <button type="button" key={item.id} className={`notification-item ${item.read_at ? 'notification-read' : ''}`} onClick={() => { void markRead(item); if (item.type === 'feedback') onOpenFeedback(); }}><span className="notification-item-title">{item.title}</span><span>{item.body}</span><small>{new Date(item.created_at).toLocaleString()}</small>{!item.read_at && <i aria-label="Unread" />}</button>)}</div> : <div className="task-message">You’re all caught up.</div>}</aside>;
}

function AnnouncementsPanel({ token, organization }: { token: string; organization: Organization }) {
  const { locale, t } = useLocale();
  const [announcements, setAnnouncements] = useState<Announcement[]>([]);
  const [title, setTitle] = useState('');
  const [body, setBody] = useState('');
  const [audience, setAudience] = useState<Announcement['audience']>('all_members');
  const [scheduledAt, setScheduledAt] = useState('');
  const [editingId, setEditingId] = useState('');
  const [editTitle, setEditTitle] = useState('');
  const [editBody, setEditBody] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelled = false;
    listAnnouncements(token, organization.id).then((items) => { if (!cancelled) setAnnouncements(items); })
      .catch((cause) => { if (!cancelled) setError(cause instanceof Error ? cause.message : t('announcements.loadError')); })
      .finally(() => { if (!cancelled) setLoading(false); });
    return () => { cancelled = true; };
  }, [token, organization.id]);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!title.trim() || !body.trim()) return;
    setSaving(true); setError('');
    try {
      const scheduled = scheduledAt ? workspaceDateTimeToIso(scheduledAt, organization.timezone) : undefined;
      if (scheduledAt && !scheduled) throw new Error(t('announcements.invalidTime').replace('{timezone}', organization.timezone));
      const next = await createAnnouncement(token, organization.id, { title: title.trim(), body: body.trim(), audience, ...(scheduled ? { scheduled_at: scheduled } : {}) });
      setAnnouncements((items) => [next, ...items]); setTitle(''); setBody(''); setScheduledAt('');
    } catch (cause) { setError(cause instanceof Error ? cause.message : t('announcements.saveError')); }
    finally { setSaving(false); }
  }

  async function saveEdit(event: FormEvent<HTMLFormElement>, announcement: Announcement) {
    event.preventDefault(); if (!editTitle.trim() || !editBody.trim()) return;
    setSaving(true); setError('');
    try {
      const updated = await updateAnnouncement(token, organization.id, announcement.id, { title: editTitle.trim(), body: editBody.trim() });
      setAnnouncements((items) => items.map((item) => item.id === updated.id ? { ...item, ...updated, recipient_count: item.recipient_count } : item));
      setEditingId(''); setEditTitle(''); setEditBody('');
    } catch (cause) { setError(cause instanceof Error ? cause.message : t('announcements.updateError')); }
    finally { setSaving(false); }
  }

  async function changeAnnouncementStatus(announcement: Announcement, action: 'withdraw' | 'restore') {
    setSaving(true); setError('');
    try {
      const updated = action === 'withdraw'
        ? await withdrawAnnouncement(token, organization.id, announcement.id)
        : await restoreAnnouncement(token, organization.id, announcement.id);
      setAnnouncements((items) => items.map((item) => item.id === updated.id ? { ...item, ...updated, recipient_count: item.recipient_count } : item));
      setEditingId('');
    } catch (cause) { setError(cause instanceof Error ? cause.message : t('announcements.statusError')); }
    finally { setSaving(false); }
  }

  const editingAnnouncement = announcements.find((item) => item.id === editingId);

  const localeTag = locale === 'hi' ? 'hi-IN' : 'en-IN';
  const audienceKeys: Record<Announcement['audience'], TranslationKey> = { all_members: 'announcements.allMembers', students: 'announcements.students', guardians: 'announcements.guardians', teachers: 'announcements.teachers' };
  return <section className="panel academic-panel announcement-panel"><div className="panel-heading"><div><div className="eyebrow">{t('announcements.eyebrow')}</div><h2>{t('announcements.title')}</h2><p>{t('announcements.description')}</p></div></div>{error && <div className="academic-error" role="alert">{error}</div>}
    <form className="academic-form announcement-form" onSubmit={(event) => void submit(event)}><strong>{t('announcements.write')}</strong><label>{t('announcements.titleLabel')}<input required maxLength={180} value={title} onChange={(event) => setTitle(event.target.value)} placeholder={t('announcements.titlePlaceholder')} /></label><label>{t('announcements.message')}<textarea required maxLength={10000} value={body} onChange={(event) => setBody(event.target.value)} placeholder={t('announcements.messagePlaceholder')} /></label><label>{t('announcements.audience')}<select value={audience} onChange={(event) => setAudience(event.target.value as Announcement['audience'])}><option value="all_members">{t('announcements.allMembers')}</option><option value="students">{t('announcements.students')}</option><option value="guardians">{t('announcements.guardians')}</option><option value="teachers">{t('announcements.teachers')}</option></select></label><label>{t('announcements.publishAt')} ({organization.timezone}, {t('announcements.optional')})<input type="datetime-local" min={isoToWorkspaceDateTime(new Date(Date.now() + 60_000), organization.timezone)} value={scheduledAt} onChange={(event) => setScheduledAt(event.target.value)} /></label><button className="task-submit" disabled={saving || !title.trim() || !body.trim()}>{saving ? t('announcements.saving') : scheduledAt ? t('announcements.schedule') : t('announcements.publish')}</button><small className="form-hint">{scheduledAt ? t('announcements.scheduledHint') : t('announcements.publishHint')}</small></form>
    {editingAnnouncement && <form className="academic-form announcement-form" onSubmit={(event) => void saveEdit(event, editingAnnouncement)}><strong>{t('announcements.edit')}</strong><label>{t('announcements.titleLabel')}<input aria-label={t('announcements.editTitle')} required maxLength={180} value={editTitle} onChange={(event) => setEditTitle(event.target.value)} /></label><label>{t('announcements.message')}<textarea aria-label={t('announcements.editMessage')} required maxLength={10000} value={editBody} onChange={(event) => setEditBody(event.target.value)} /></label><div className="lesson-edit-actions"><button className="task-submit" disabled={saving || !editTitle.trim() || !editBody.trim()}>{saving ? t('announcements.saving') : t('announcements.saveChanges')}</button><button type="button" className="task-cancel" disabled={saving} onClick={() => setEditingId('')}>{t('announcements.cancel')}</button></div><small className="form-hint">{t('announcements.editHint')}</small></form>}
    <div className="announcement-list"><div className="course-progress-heading"><strong>{t('announcements.listHeading')}</strong><span>{announcements.length}</span></div>{loading ? <div className="task-message">{t('announcements.loading')}</div> : announcements.length ? announcements.map((item) => {
      const statusText = item.status === 'withdrawn' ? t('announcements.withdrawn') : item.status === 'scheduled' ? t('announcements.scheduled') : t('announcements.published');
      const statusDate = item.status === 'scheduled' && item.scheduled_at ? new Date(item.scheduled_at).toLocaleString(localeTag, { timeZone: organization.timezone }) : item.published_at ? new Date(item.published_at).toLocaleString(localeTag, { timeZone: organization.timezone }) : '';
      const details = [t(audienceKeys[item.audience]), statusDate ? `${statusText} ${statusDate}` : statusText, item.author_name ? `${t('announcements.by')} ${item.author_name}` : '', typeof item.recipient_count === 'number' ? `${item.recipient_count} ${t('announcements.recipients')}` : ''].filter(Boolean).join(' · ');
      return <article className={`announcement-card ${item.status === 'withdrawn' ? 'announcement-withdrawn' : ''}`} key={item.id}><div><strong>{item.title}</strong><small>{details}</small></div><p>{item.body}</p><div className="lesson-edit-actions">{item.status !== 'withdrawn' && <button type="button" className="task-cancel" disabled={saving} onClick={() => { setEditingId(item.id); setEditTitle(item.title); setEditBody(item.body); }}>{t('announcements.edit')}</button>}{item.status === 'withdrawn' ? <button type="button" className="task-cancel" disabled={saving} onClick={() => void changeAnnouncementStatus(item, 'restore')}>{saving ? t('announcements.saving') : t('announcements.restore')}</button> : <button type="button" className="task-cancel" disabled={saving} onClick={() => void changeAnnouncementStatus(item, 'withdraw')}>{saving ? t('announcements.saving') : t('announcements.withdraw')}</button>}</div></article>;
    }) : <div className="academic-empty">{t('announcements.empty')}</div>}</div>
  </section>;
}

function escapeReceiptHtml(value: string) {
  return value.replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character] ?? character);
}

function localizedFeeStatus(status: FeeInvoice['status'], t: (key: TranslationKey) => string) {
  const keys: Record<FeeInvoice['status'], TranslationKey> = { issued: 'fees.statusIssued', partially_paid: 'fees.statusPartiallyPaid', paid: 'fees.statusPaid', void: 'fees.statusVoid' };
  return t(keys[status]);
}

function localizedPaymentMethod(method: FeePayment['method'], t: (key: TranslationKey) => string) {
  const keys: Record<FeePayment['method'], TranslationKey> = { bank_transfer: 'fees.bankTransfer', upi: 'fees.upi', cash: 'fees.cash', cheque: 'fees.cheque', other: 'fees.other' };
  return t(keys[method]);
}

function printFeeReceipt(organization: Organization, invoice: FeeInvoice, payment: FeePayment, locale: Locale, t: (key: TranslationKey) => string) {
  const receiptWindow = window.open('', '_blank', 'popup,width=760,height=820');
  if (!receiptWindow) return;
  receiptWindow.opener = null;
  const escape = escapeReceiptHtml;
  const localeTag = locale === 'hi' ? 'hi-IN' : 'en-IN';
  const receivedOn = new Date(payment.received_at).toLocaleString(localeTag, { timeZone: organization.timezone });
  const method = localizedPaymentMethod(payment.method, t);
  const note = t('fees.receiptNote').replace('{organization}', organization.name);
  receiptWindow.document.write(`<!doctype html><html lang="${locale === 'hi' ? 'hi-IN' : 'en'}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>${escape(t('fees.receiptTitle'))} ${escape(payment.receipt_number)}</title><style>
    *{box-sizing:border-box}body{margin:0;padding:32px;background:#f3f4f8;color:#172033;font:15px/1.5 Arial,sans-serif}.receipt{max-width:720px;margin:0 auto;padding:40px;background:#fff;border:1px solid #dfe3ec;border-radius:12px}.brand{font-size:22px;font-weight:700;color:#373851}.eyebrow{margin-top:28px;color:#686b7b;font-size:12px;letter-spacing:.12em;text-transform:uppercase}.title{margin:4px 0 24px;font-size:28px}.receipt-number{padding:14px 16px;background:#f5f5fb;border-radius:8px;font-weight:700;overflow-wrap:anywhere}.row{display:flex;justify-content:space-between;gap:20px;padding:12px 0;border-bottom:1px solid #e8eaf0}.row span{color:#5c6270}.row strong{max-width:72%;overflow-wrap:anywhere;text-align:right}.amount{margin:22px 0;padding:20px 16px;border:1px solid #dfe3ec;border-radius:8px}.amount span{display:block;color:#5c6270;font-size:13px}.amount strong{font-size:30px}.note{margin-top:24px;color:#5c6270;font-size:12px}.actions{display:flex;justify-content:center;margin-top:20px}.actions button{padding:10px 18px;border:0;border-radius:7px;background:#4f46a5;color:#fff;font:inherit;cursor:pointer}@media(max-width:520px){body{padding:12px}.receipt{padding:20px}.row{align-items:flex-start;flex-direction:column;gap:3px}.row strong{max-width:100%;text-align:left}}@media print{@page{margin:16mm}body{padding:0;background:#fff}.receipt{max-width:none;padding:0;border:0;border-radius:0}.actions{display:none}}
  </style></head><body><main class="receipt"><div class="brand">${escape(organization.name)}</div><div class="eyebrow">${escape(t('fees.receiptTitle'))}</div><h1 class="title">${escape(t('fees.receiptReceived'))}</h1><div class="receipt-number">${escape(t('fees.receiptTitle'))} ${escape(payment.receipt_number)}</div><div class="row"><span>${escape(t('fees.receiptStudent'))}</span><strong>${escape(invoice.student_name ?? t('fees.receiptStudent'))}</strong></div>${invoice.student_code ? `<div class="row"><span>${escape(t('guardian.studentCode'))}</span><strong>${escape(invoice.student_code)}</strong></div>` : ''}<div class="row"><span>${escape(t('fees.receiptInvoice'))}</span><strong>${escape(invoice.invoice_number)}</strong></div><div class="row"><span>${escape(t('fees.receiptFor'))}</span><strong>${escape(invoice.title)}</strong></div><div class="row"><span>${escape(t('fees.receivedOn'))}</span><strong>${escape(receivedOn)}</strong></div><div class="row"><span>${escape(t('fees.paymentMethod'))}</span><strong>${escape(method)}</strong></div><div class="row"><span>${escape(t('fees.transactionReference'))}</span><strong>${escape(payment.transaction_reference)}</strong></div><div class="amount"><span>${escape(t('fees.amountReceived'))}</span><strong>${new Intl.NumberFormat(localeTag, { style: 'currency', currency: 'INR' }).format(payment.amount_paise / 100)}</strong></div><p class="note">${escape(note)}</p><div class="actions"><button type="button" onclick="window.print()">${escape(t('fees.printSavePdf'))}</button></div></main></body></html>`);
  receiptWindow.document.close();
  receiptWindow.focus();
}

function workspaceDateString(timeZone: string, date = new Date()) {
  const parts = new Intl.DateTimeFormat('en-CA', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(date);
  const value = (type: string) => parts.find((part) => part.type === type)?.value ?? '';
  return `${value('year')}-${value('month')}-${value('day')}`;
}

function FeeCollectionsPanel({ token, organization }: { token: string; organization: Organization }) {
  const { locale, t } = useLocale();
  const today = workspaceDateString(organization.timezone);
  const monthStart = `${today.slice(0, 7)}-01`;
  const pageSize = 25;
  const [draftFrom, setDraftFrom] = useState(monthStart);
  const [draftTo, setDraftTo] = useState(today);
  const [appliedFrom, setAppliedFrom] = useState(monthStart);
  const [appliedTo, setAppliedTo] = useState(today);
  const [report, setReport] = useState<FeeCollectionReport | null>(null);
  const [loading, setLoading] = useState(true);
  const [exporting, setExporting] = useState(false);
  const [error, setError] = useState('');

  async function loadCollections(page: number, from = appliedFrom, to = appliedTo) {
    setLoading(true); setError(''); setReport(null);
    try { setReport(await getFeeCollections(token, organization.id, { from, to, page, per_page: pageSize })); }
    catch (cause) { setError(cause instanceof Error ? cause.message : t('fees.loadCollectionsError')); }
    finally { setLoading(false); }
  }

  useEffect(() => { void loadCollections(1); }, [token, organization.id]);

  async function exportCollections() {
    if (!report?.summary.payment_count || exporting) return;
    setExporting(true); setError('');
    try {
      const payments: FeeCollectionReport['data'] = [];
      let page = 1;
      let lastPage = 1;
      do {
        const next = await getFeeCollections(token, organization.id, { from: appliedFrom, to: appliedTo, page, per_page: 100 });
        payments.push(...next.data);
        lastPage = next.meta.last_page;
        page += 1;
      } while (page <= lastPage);
      const csv = encodeCsv([
        [t('fees.receivedOn'), t('fees.receiptTitle'), t('fees.transactionReference'), t('fees.student'), t('guardian.studentCode'), t('fees.invoice'), t('fees.invoiceTitle'), t('fees.method'), `${t('fees.amount')} INR`],
        ...payments.map((payment) => [payment.received_at, payment.receipt_number, payment.transaction_reference, payment.student_name, payment.student_code, payment.invoice_number, payment.invoice_title, payment.method.replaceAll('_', ' '), (payment.amount_paise / 100).toFixed(2)]),
      ]);
      const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
      const anchor = document.createElement('a');
      anchor.href = url;
      anchor.download = `${organization.slug}-collections-${appliedFrom}-to-${appliedTo}.csv`;
      anchor.click();
      URL.revokeObjectURL(url);
    } catch (cause) { setError(cause instanceof Error ? cause.message : t('fees.exportCollectionsError')); }
    finally { setExporting(false); }
  }

  function applyFilters(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (draftFrom > draftTo) { setError(t('fees.endAfterStart')); return; }
    setAppliedFrom(draftFrom); setAppliedTo(draftTo);
    void loadCollections(1, draftFrom, draftTo);
  }

  const localeTag = locale === 'hi' ? 'hi-IN' : 'en-IN';
  const money = (paise: number) => new Intl.NumberFormat(localeTag, { style: 'currency', currency: 'INR' }).format(paise / 100);
  return <section className="panel academic-panel collections-panel" aria-labelledby="collections-heading" aria-busy={loading}>
    <div className="panel-heading"><div><div className="eyebrow">{t('fees.collectionsEyebrow')}</div><h2 id="collections-heading">{t('fees.collectionsTitle')}</h2><p>{t('fees.collectionsDescription')}</p></div><button type="button" className="button button-quiet" onClick={() => void exportCollections()} disabled={!report?.summary.payment_count || loading || exporting}>{exporting ? t('fees.prepareCsv') : t('fees.exportCollections')}</button></div>
    {error && <div className="academic-error" role="alert">{error}</div>}
    <form className="invoice-date-filters" aria-label={t('fees.filterCollections')} onSubmit={applyFilters}><label>{t('fees.receivedFrom')}<input type="date" required value={draftFrom} max={draftTo || undefined} onChange={(event) => setDraftFrom(event.target.value)} /></label><label>{t('fees.receivedThrough')}<input type="date" required value={draftTo} min={draftFrom || undefined} onChange={(event) => setDraftTo(event.target.value)} /></label><button type="submit" className="task-submit" disabled={loading}>{t('fees.applyCollectionDates')}</button><small className="form-hint">{t('fees.collectionDateHint').replace('{timezone}', organization.timezone)}</small></form>
    {report && <div className="report-metrics collections-metrics"><article className="report-metric"><span>{t('fees.receivedInPeriod')}</span><strong>{money(report.summary.collected_paise)}</strong><small>{report.summary.payment_count} {t('fees.reconciledPayments')}</small></article></div>}
    {loading ? <div className="task-message">{t('fees.loadingCollections')}</div> : report?.data.length ? <><div className="collection-table-wrap"><table className="collection-table"><caption className="sr-only">{t('fees.collectionCaption').replace('{from}', appliedFrom).replace('{to}', appliedTo)}</caption><thead><tr><th>{t('fees.received')}</th><th>{t('fees.student')}</th><th>{t('fees.invoice')}</th><th>{t('fees.receiptReference')}</th><th>{t('fees.method')}</th><th>{t('fees.amount')}</th></tr></thead><tbody>{report.data.map((payment) => <tr key={payment.id}><td>{new Date(payment.received_at).toLocaleString(localeTag, { timeZone: organization.timezone })}</td><td>{payment.student_name ?? t('fees.student')}<small>{payment.student_code ?? ''}</small></td><td>{payment.invoice_number ?? t('fees.invoice')}<small>{payment.invoice_title ?? ''}</small></td><td>{payment.receipt_number}<small>{payment.transaction_reference}</small></td><td>{localizedPaymentMethod(payment.method, t)}</td><td>{money(payment.amount_paise)}</td></tr>)}</tbody></table></div>
      {report.meta.last_page > 1 && <nav className="invoice-pagination" aria-label={t('fees.filterCollections')}><button type="button" className="task-cancel" disabled={loading || report.meta.current_page <= 1} onClick={() => void loadCollections(report.meta.current_page - 1)}>{t('fees.previousPage')}</button><span aria-live="polite">{t('fees.page')} {report.meta.current_page} {t('fees.pageOf')} {report.meta.last_page} · {report.meta.total} {t('fees.totalPayments')}</span><button type="button" className="task-cancel" disabled={loading || report.meta.current_page >= report.meta.last_page} onClick={() => void loadCollections(report.meta.current_page + 1)}>{t('fees.nextPage')}</button></nav>}
    </> : <div className="academic-empty">{t('fees.noCollections')}</div>}
  </section>;
}

function FeeInvoicesPanel({ token, organization }: { token: string; organization: Organization }) {
  const { locale, t } = useLocale();
  const canManage = hasPermission(organization, 'academic.manage');
  const pageSize = 25;
  const [invoices, setInvoices] = useState<FeeInvoice[]>([]);
  const [invoiceMeta, setInvoiceMeta] = useState({ current_page: 1, last_page: 1, per_page: pageSize, total: 0 });
  const [issuedFromDraft, setIssuedFromDraft] = useState('');
  const [issuedToDraft, setIssuedToDraft] = useState('');
  const [appliedIssuedFrom, setAppliedIssuedFrom] = useState('');
  const [appliedIssuedTo, setAppliedIssuedTo] = useState('');
  const [summary, setSummary] = useState<FeeInvoiceSummary | null>(null);
  const [students, setStudents] = useState<StudentProfile[]>([]);
  const [studentId, setStudentId] = useState('');
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [amount, setAmount] = useState('');
  const [dueOn, setDueOn] = useState('');
  const [paymentInvoiceId, setPaymentInvoiceId] = useState('');
  const [paymentAmount, setPaymentAmount] = useState('');
  const [paymentMethod, setPaymentMethod] = useState<FeePayment['method']>('bank_transfer');
  const [paymentReference, setPaymentReference] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [exporting, setExporting] = useState(false);
  const [error, setError] = useState('');

  async function refresh(page = invoiceMeta.current_page, range = { from: appliedIssuedFrom, to: appliedIssuedTo }) {
    setLoading(true); setError('');
    try {
      const [nextInvoices, nextStudents, nextSummary] = await Promise.all([
        listFeeInvoices(token, organization.id, { page, per_page: pageSize, ...(range.from ? { from: range.from } : {}), ...(range.to ? { to: range.to } : {}) }),
        canManage ? listStudents(token, organization.id) : Promise.resolve([]),
        canManage ? getFeeInvoiceSummary(token, organization.id) : Promise.resolve(null),
      ]);
      setInvoices(nextInvoices.data); setInvoiceMeta(nextInvoices.meta); setStudents(nextStudents); setSummary(nextSummary);
      setStudentId((current) => nextStudents.some((student) => student.id === current) ? current : nextStudents[0]?.id ?? '');
    } catch (cause) { setError(cause instanceof Error ? cause.message : t('fees.loadInvoicesError')); }
    finally { setLoading(false); }
  }

  useEffect(() => { void refresh(); }, [token, organization.id]);

  async function issueInvoice(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!studentId || !title.trim() || !amount) return;
    setSaving(true); setError('');
    try {
      await createFeeInvoice(token, organization.id, { student_id: studentId, title: title.trim(), ...(description.trim() ? { description: description.trim() } : {}), amount: Number(amount), ...(dueOn ? { due_on: dueOn } : {}) });
      setTitle(''); setDescription(''); setAmount(''); setDueOn(''); setIssuedFromDraft(''); setIssuedToDraft(''); setAppliedIssuedFrom(''); setAppliedIssuedTo(''); await refresh(1, { from: '', to: '' });
    } catch (cause) { setError(cause instanceof Error ? cause.message : t('fees.issueInvoiceError')); }
    finally { setSaving(false); }
  }

  async function cancelInvoice(invoice: FeeInvoice) {
    setError('');
    try { await voidFeeInvoice(token, organization.id, invoice.id); setInvoices((items) => items.map((item) => item.id === invoice.id ? { ...item, status: 'void' } : item)); }
    catch (cause) { setError(cause instanceof Error ? cause.message : t('fees.voidInvoiceError')); }
  }

  async function submitPayment(event: FormEvent<HTMLFormElement>, invoice: FeeInvoice) {
    event.preventDefault(); if (!paymentAmount || !paymentReference.trim()) return;
    setSaving(true); setError('');
    try {
      await recordFeePayment(token, organization.id, invoice.id, { amount: Number(paymentAmount), method: paymentMethod, transaction_reference: paymentReference.trim(), idempotency_key: crypto.randomUUID() });
      setPaymentInvoiceId(''); setPaymentAmount(''); setPaymentReference(''); await refresh();
    } catch (cause) { setError(cause instanceof Error ? cause.message : t('fees.recordPaymentError')); }
    finally { setSaving(false); }
  }

  async function exportInvoicesCsv() {
    if (!invoiceMeta.total || exporting) return;
    setExporting(true); setError('');
    try {
      const allInvoices: FeeInvoice[] = [];
      let page = 1;
      let lastPage = 1;
      do {
        const result = await listFeeInvoices(token, organization.id, { page, per_page: 100, ...(appliedIssuedFrom ? { from: appliedIssuedFrom } : {}), ...(appliedIssuedTo ? { to: appliedIssuedTo } : {}) });
        allInvoices.push(...result.data);
        lastPage = result.meta.last_page;
        page += 1;
      } while (page <= lastPage);
      const csv = encodeFeeInvoiceCsv(allInvoices);
      const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
      const anchor = document.createElement('a');
      anchor.href = url;
      anchor.download = `${organization.slug}-fee-invoices${appliedIssuedFrom || appliedIssuedTo ? `-${appliedIssuedFrom || 'start'}-to-${appliedIssuedTo || 'end'}` : ''}-${new Date().toISOString().slice(0, 10)}.csv`;
      anchor.click();
      URL.revokeObjectURL(url);
    } catch (cause) { setError(cause instanceof Error ? cause.message : t('fees.exportInvoicesError')); }
    finally { setExporting(false); }
  }

  const localeTag = locale === 'hi' ? 'hi-IN' : 'en-IN';
  const money = (paise: number) => new Intl.NumberFormat(localeTag, { style: 'currency', currency: 'INR' }).format(paise / 100);
  return <section className="panel academic-panel invoice-panel"><div className="panel-heading"><div><div className="eyebrow">{t('fees.eyebrow')}</div><h2>{t('fees.title')}</h2><p>{t('fees.description')}</p></div><button type="button" className="button button-quiet" onClick={() => void exportInvoicesCsv()} disabled={!invoiceMeta.total || loading || exporting}>{exporting ? t('fees.prepareCsv') : t('fees.exportInvoices')}</button></div>{error && <div className="academic-error" role="alert">{error}</div>}
    {canManage && summary && <div className="panel academic-panel"><div className="course-progress-heading"><strong>{t('fees.overview')}</strong><span>{t('fees.allTimeReceipts')}</span></div><div className="report-metrics"><article className="report-metric"><span>{t('fees.invoiced')}</span><strong>{money(summary.invoiced_paise)}</strong><small>{summary.invoice_count} {t('fees.activeInvoices')}</small></article><article className="report-metric"><span>{t('fees.collected')}</span><strong>{money(summary.collected_paise)}</strong><small>{summary.paid_invoice_count} {t('fees.paidFull')}</small></article><article className="report-metric"><span>{t('fees.outstanding')}</span><strong>{money(summary.outstanding_paise)}</strong><small>{summary.outstanding_invoice_count} {t('fees.withBalance')}</small></article><article className="report-metric"><span>{t('fees.overdue')}</span><strong>{money(summary.overdue_paise)}</strong><small>{summary.overdue_invoice_count} {t('fees.pastDueInvoices')}</small></article></div></div>}
    <form className="invoice-date-filters" aria-label={t('fees.filterIssueDate')} onSubmit={(event) => { event.preventDefault(); if (issuedFromDraft && issuedToDraft && issuedFromDraft > issuedToDraft) { setError(t('fees.endAfterStart')); return; } setAppliedIssuedFrom(issuedFromDraft); setAppliedIssuedTo(issuedToDraft); void refresh(1, { from: issuedFromDraft, to: issuedToDraft }); }}><label>{t('fees.issuedFrom')}<input type="date" value={issuedFromDraft} max={issuedToDraft || undefined} onChange={(event) => setIssuedFromDraft(event.target.value)} /></label><label>{t('fees.issuedThrough')}<input type="date" value={issuedToDraft} min={issuedFromDraft || undefined} onChange={(event) => setIssuedToDraft(event.target.value)} /></label><button type="submit" className="task-submit" disabled={loading}>{t('fees.applyDateFilters')}</button><button type="button" className="task-cancel" disabled={loading || (!issuedFromDraft && !issuedToDraft)} onClick={() => { setIssuedFromDraft(''); setIssuedToDraft(''); setAppliedIssuedFrom(''); setAppliedIssuedTo(''); void refresh(1, { from: '', to: '' }); }}>{t('fees.clearFilters')}</button><small className="form-hint">{t('fees.issueDateNote')}</small></form>
    {canManage && <form className="academic-form invoice-form" onSubmit={(event) => void issueInvoice(event)}><strong>{t('fees.issueInvoice')}</strong><label>{t('fees.student')}<select required value={studentId} onChange={(event) => setStudentId(event.target.value)}><option value="" disabled>{t('fees.selectStudent')}</option>{students.map((student) => <option value={student.id} key={student.id}>{student.name} · {student.student_code}</option>)}</select></label><label>{t('fees.invoiceTitle')}<input required maxLength={180} value={title} onChange={(event) => setTitle(event.target.value)} placeholder={t('fees.exampleTitle')} /></label><label>{t('fees.amountInr')}<input required type="number" min="0.01" max="10000000" step="0.01" value={amount} onChange={(event) => setAmount(event.target.value)} /></label><label>{t('fees.dueDate')}<input type="date" min={new Date().toISOString().slice(0, 10)} value={dueOn} onChange={(event) => setDueOn(event.target.value)} /></label><label className="invoice-description">{t('fees.descriptionOptional')}<textarea maxLength={2000} value={description} onChange={(event) => setDescription(event.target.value)} /></label><button className="task-submit" disabled={saving || !studentId || !title.trim() || !amount}>{saving ? t('fees.saving') : t('fees.issueButton')}</button></form>}
    <div className="announcement-list"><div className="course-progress-heading"><strong>{canManage ? t('fees.workspaceInvoices') : t('fees.yourInvoices')}</strong><span>{invoiceMeta.total} {t('fees.invoiceCount')} · {t('fees.page')} {invoiceMeta.current_page} {t('fees.pageOf')} {invoiceMeta.last_page}</span></div>
      {loading ? <div className="task-message">{t('fees.loading')}</div> : invoices.length ? invoices.map((invoice) => <article className="invoice-card" key={invoice.id}>
        <div className="invoice-heading"><div><strong>{invoice.title}</strong><small>{invoice.invoice_number} · {invoice.student_name}{invoice.due_on ? ` · ${t('fees.due')} ${new Date(`${invoice.due_on}T12:00:00Z`).toLocaleDateString(localeTag, { timeZone: 'UTC' })}` : ''}</small></div><span className={`assignment-status ${invoice.status === 'void' ? '' : 'graded'}`}>{localizedFeeStatus(invoice.status, t)}</span></div>
        <p>{invoice.description || t('fees.noDetails')}</p><div className="invoice-amount">{money(invoice.amount_paise)}</div><small>{t('fees.balanceDue')}: {money(invoice.balance_paise)}</small>
        {invoice.payments.map((payment) => <div className="invoice-payment" key={payment.id}><div><span>{money(payment.amount_paise)} · {localizedPaymentMethod(payment.method, t)}</span><small>{payment.receipt_number} · {payment.transaction_reference} · {new Date(payment.received_at).toLocaleDateString(localeTag, { timeZone: organization.timezone })}</small></div><button type="button" className="task-cancel" onClick={() => printFeeReceipt(organization, invoice, payment, locale, t)}>{t('fees.printReceipt')}</button></div>)}
        {canManage && ['issued', 'partially_paid'].includes(invoice.status) && (paymentInvoiceId === invoice.id ? <form className="academic-form invoice-payment-form" onSubmit={(event) => void submitPayment(event, invoice)}><strong>{t('fees.recordPayment')}</strong><label>{t('fees.amountInr')}<input required type="number" min="0.01" max={(invoice.balance_paise / 100).toFixed(2)} step="0.01" value={paymentAmount} onChange={(event) => setPaymentAmount(event.target.value)} /></label><label>{t('fees.method')}<select value={paymentMethod} onChange={(event) => setPaymentMethod(event.target.value as FeePayment['method'])}><option value="bank_transfer">{t('fees.bankTransfer')}</option><option value="upi">{t('fees.upi')}</option><option value="cash">{t('fees.cash')}</option><option value="cheque">{t('fees.cheque')}</option><option value="other">{t('fees.other')}</option></select></label><label>{t('fees.reference')}<input required maxLength={120} value={paymentReference} onChange={(event) => setPaymentReference(event.target.value)} placeholder={t('fees.transactionPlaceholder')} /></label><div className="lesson-edit-actions"><button className="task-submit" disabled={saving || !paymentAmount || !paymentReference.trim()}>{saving ? t('fees.saving') : t('fees.saveReceipt')}</button><button type="button" className="task-cancel" onClick={() => setPaymentInvoiceId('')}>{t('fees.cancel')}</button></div><small className="form-hint">{t('fees.reconcileHint')}</small></form> : <button className="task-cancel" onClick={() => { setPaymentInvoiceId(invoice.id); setPaymentAmount((invoice.balance_paise / 100).toFixed(2)); }}>{t('fees.recordPaymentButton')}</button>)}
        {canManage && invoice.status === 'issued' && invoice.payments.length === 0 && <button className="invoice-void" onClick={() => void cancelInvoice(invoice)}>{t('fees.voidInvoice')}</button>}
      </article>) : <div className="academic-empty">{invoiceMeta.total ? t('fees.noPageInvoices') : appliedIssuedFrom || appliedIssuedTo ? t('fees.noMatchingDates') : t('fees.noneAvailable')}</div>}
      {invoiceMeta.last_page > 1 && <nav className="invoice-pagination" aria-label={t('fees.filterIssueDate')}><button type="button" className="task-cancel" disabled={loading || invoiceMeta.current_page <= 1} onClick={() => void refresh(invoiceMeta.current_page - 1)}>{t('fees.previousPage')}</button><span aria-live="polite">{t('fees.page')} {invoiceMeta.current_page} {t('fees.pageOf')} {invoiceMeta.last_page}</span><button type="button" className="task-cancel" disabled={loading || invoiceMeta.current_page >= invoiceMeta.last_page} onClick={() => void refresh(invoiceMeta.current_page + 1)}>{t('fees.nextPage')}</button></nav>}
    </div>
  </section>;
}

function AcademicReportsPanel({ token, organization, initialPeriod }: { token: string; organization: Organization; initialPeriod: { from: string; to: string } | null }) {
  const { locale, t } = useLocale();
  const [report, setReport] = useState<AcademicReport | null>(null);
  const [availableStudents, setAvailableStudents] = useState<AcademicReport['students']>([]);
  const [classId, setClassId] = useState('');
  const [studentId, setStudentId] = useState('');
  const [from, setFrom] = useState(initialPeriod?.from ?? '');
  const [to, setTo] = useState(initialPeriod?.to ?? '');
  const [loading, setLoading] = useState(true); const [error, setError] = useState('');
  useEffect(() => {
    let current = true;
    setLoading(true);
    setError(''); setClassId(''); setStudentId('');
    setFrom(initialPeriod?.from ?? ''); setTo(initialPeriod?.to ?? '');
    getAcademicReport(token, organization.id, { ...(initialPeriod ? { from: initialPeriod.from, to: initialPeriod.to } : {}) }).then((value) => { if (current) { setReport(value); setAvailableStudents(value.students); } })
      .catch((cause: Error) => { if (current) setError(cause.message); })
      .finally(() => { if (current) setLoading(false); });
    return () => { current = false; };
  }, [token, organization.id, initialPeriod?.from, initialPeriod?.to]);
  async function applyReportFilters() {
    if (from && to && from > to) { setError(t('reports.endAfterStart')); return; }
    setLoading(true); setError('');
    try { setReport(await getAcademicReport(token, organization.id, { ...(classId ? { class_id: classId } : {}), ...(studentId ? { student_id: studentId } : {}), ...(from ? { from } : {}), ...(to ? { to } : {}) })); }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not load academic report.'); }
    finally { setLoading(false); }
  }
  function downloadCsv() {
    if (!report) return;
    const headers = ['reports.recordType', 'reports.studentId', 'reports.studentName', 'reports.classes', 'reports.attendanceSessions', 'reports.attendancePercent', 'reports.courses', 'reports.lessonsComplete', 'reports.lessonsTotal', 'reports.courseCompletionPercent', 'reports.assignmentsAssigned', 'reports.assignmentsSubmitted', 'reports.assignmentsGraded', 'reports.assessmentsCompleted', 'reports.assessmentAveragePercent', 'reports.course', 'reports.assessment', 'reports.attempts', 'reports.averagePercent', 'reports.scoreRange', 'reports.share', 'reports.month'].map((key) => t(key as TranslationKey));
    const blankStudentFields = Array.from({ length: 14 }, () => '');
    const studentRows = report.students.map((student) => [
      'student', student.student_code, student.student_name, student.classes, student.attendance_sessions,
      student.attendance_percent ?? '', student.courses_enrolled, student.completed_lessons, student.total_lessons,
      student.course_completion_percent ?? '', student.assignments_assigned, student.assignments_submitted,
      student.assignments_graded, student.assessments_completed, student.assessment_average_percent ?? '',
      '', '', '', '', '', '', '',
    ]);
    const bands: [string, number][] = [
      [t('reports.below60'), report.summary.assessment_score_bands.under_60],
      [t('reports.range60to74'), report.summary.assessment_score_bands.sixty_to_74],
      [t('reports.range75to89'), report.summary.assessment_score_bands.seventyfive_to_89],
      [t('reports.above90'), report.summary.assessment_score_bands.ninety_plus],
    ];
    const summaryRows = [
      ...bands.map(([label, count]) => ['score_band', ...blankStudentFields, '', '', count, '', label, report.summary.assessments_completed ? `${Math.round((count / report.summary.assessments_completed) * 100)}%` : t('reports.noAttempts'), '']),
      ...report.summary.assessment_trend.map((item) => ['monthly_assessment', ...blankStudentFields, '', '', item.attempts, item.average_percent, '', '', item.month]),
      ...report.summary.assessment_breakdown.map((item) => ['assessment', ...blankStudentFields, item.course_title, item.assessment_title, item.attempts, item.average_percent, '', '', '']),
      ...report.summary.assessment_course_summary.map((item) => ['course', ...blankStudentFields, item.course_title, '', item.attempts, item.average_percent, '', '', '']),
    ];
    const rows = [...studentRows, ...summaryRows];
    const csv = encodeCsv([headers, ...rows]);
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    const anchor = document.createElement('a'); anchor.href = url; anchor.download = `${organization.slug}-academic-report${report.filters.from ? `-${report.filters.from}-to-${report.filters.to}` : ''}.csv`; anchor.click(); URL.revokeObjectURL(url);
  }
  const summary = report?.summary;
  const percent = (value: number | null | undefined) => value === null || value === undefined ? t('reports.noData') : `${new Intl.NumberFormat(locale === 'hi' ? 'hi-IN' : 'en-IN').format(value)}%`;
  const cards = summary ? [
    [t('reports.attendanceRate'), percent(summary.attendance_percent), `${summary.attendance_sessions} ${t('reports.attendanceRecords')}`],
    [t('reports.courseCompletion'), percent(summary.course_completion_percent), `${summary.completed_lessons}/${summary.total_lessons} ${t('reports.lessonsCompleted')}`],
    [t('reports.assignments'), `${summary.assignments_submitted} ${t('reports.submitted')}`, `${summary.assignments_assigned} ${t('reports.assigned')} · ${summary.assignments_graded} ${t('reports.graded')}`],
    [t('reports.assessmentAverage'), percent(summary.assessment_average_percent), `${summary.assessments_completed} ${t('reports.completedAttempts')}`],
  ] : [];
  return <section className="report-page"><div className="panel academic-panel report-summary-panel"><div className="panel-heading"><div><div className="eyebrow">{t('reports.eyebrow')}</div><h2>{report?.scope === 'assigned_classes' ? t('reports.assignedTitle') : t('reports.workspaceTitle')}</h2><p>{t('reports.description')}</p></div><div className="report-export-actions"><button type="button" className="button button-quiet" onClick={() => window.print()} disabled={!report?.students.length}><Printer size={16} /> {t('reports.printPdf')}</button><button type="button" className="button button-quiet" onClick={downloadCsv} disabled={!report?.students.length}>{t('reports.exportCsv')}</button></div></div>
    {error && <div className="academic-error" role="alert">{error}</div>}{loading ? <div className="task-message">{t('reports.loading')}</div> : summary && <><div className="report-filters"><label>{t('reports.from')}<input type="date" value={from} max={to || undefined} onChange={(event) => setFrom(event.target.value)} /></label><label>{t('reports.to')}<input type="date" value={to} min={from || undefined} onChange={(event) => setTo(event.target.value)} /></label><label>{t('reports.class')}<select value={classId} onChange={(event) => { setClassId(event.target.value); setStudentId(''); }}><option value="">{t('reports.allClasses')}</option>{report.available_classes.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label><label>{t('reports.student')}<select value={studentId} onChange={(event) => setStudentId(event.target.value)}><option value="">{t('reports.allStudents')}</option>{availableStudents.map((student) => <option key={student.student_id} value={student.student_id}>{student.student_name} · {student.student_code}</option>)}</select></label><button type="button" className="task-submit" disabled={loading || Boolean(from && to && from > to)} onClick={() => void applyReportFilters()}>{t('reports.apply')}</button></div>{report.filters.from && <div className="task-message" role="status">{t('reports.activityFrom').replace('{from}', report.filters.from).replace('{to}', report.filters.to ?? '')}</div>}<div className="report-metrics">{cards.map(([label, value, detail]) => <article className="report-metric" key={label}><span>{label}</span><strong>{value}</strong><small>{detail}</small></article>)}</div><AssessmentInsightsPanel summary={summary} /><div className="report-student-heading"><strong>{t('reports.studentSummary')}</strong><span>{summary.active_students} {t('reports.activeProfiles')}</span></div>{report?.students.length ? <div className="report-table-wrap"><table className="report-table"><thead><tr><th>{t('reports.student')}</th><th>{t('reports.class')}</th><th>{t('reports.attendanceRate')}</th><th>{t('reports.courseProgress')}</th><th>{t('reports.assignments')}</th><th>{t('reports.assessmentAverage')}</th></tr></thead><tbody>{report.students.map((student) => <tr key={student.student_id}><td><strong>{student.student_name}</strong><small>{student.student_code}</small></td><td>{student.classes || t('reports.unassigned')}</td><td>{percent(student.attendance_percent)}<small>{student.attendance_sessions} {t('reports.records')}</small></td><td>{percent(student.course_completion_percent)}<small>{student.completed_lessons}/{student.total_lessons} {t('reports.lessons')}</small></td><td>{student.assignments_submitted}/{student.assignments_assigned} {t('reports.assigned')}<small>{student.assignments_graded} {t('reports.graded')}</small></td><td>{percent(student.assessment_average_percent)}<small>{student.assessments_completed} {t('reports.attempts')}</small></td></tr>)}</tbody></table></div> : <div className="academic-empty">{t('reports.noStudents')}</div>}</>}
  </div></section>;
}

function AssessmentInsightsPanel({ summary }: { summary: AcademicReport['summary'] }) {
  const { locale, t } = useLocale();
  const bands: [TranslationKey, number][] = [
    ['reports.below60', summary.assessment_score_bands.under_60],
    ['reports.range60to74', summary.assessment_score_bands.sixty_to_74],
    ['reports.range75to89', summary.assessment_score_bands.seventyfive_to_89],
    ['reports.above90', summary.assessment_score_bands.ninety_plus],
  ];
  const monthLabel = (month: string) => {
    const date = new Date(`${month}-01T12:00:00Z`);
    return Number.isNaN(date.getTime()) ? month : date.toLocaleDateString(locale === 'hi' ? 'hi-IN' : 'en-IN', { month: 'short', year: 'numeric', timeZone: 'UTC' });
  };
  return <section className="report-assessment-insights" aria-labelledby="report-score-band-heading">
    <div className="report-student-heading"><h3 id="report-score-band-heading">{t('reports.scoreDistribution')}</h3><span>{summary.assessments_completed} {t('reports.completedAttempts')}</span></div>
    <p>{t('reports.completedGraded')}</p>
    <div className="report-table-wrap"><table className="report-table report-band-table"><thead><tr><th scope="col">{t('reports.scoreRange')}</th><th scope="col">{t('reports.attempts')}</th><th scope="col">{t('reports.share')}</th></tr></thead><tbody>{bands.map(([key, count]) => <tr key={key}><th scope="row">{t(key)}</th><td>{count}</td><td>{summary.assessments_completed ? `${Math.round((count / summary.assessments_completed) * 100)}%` : t('reports.noAttempts')}</td></tr>)}</tbody></table></div>
    <div className="report-student-heading report-trend-heading"><h3>{t('reports.monthlyTrend')}</h3><span>{t('reports.bySubmissionMonth')}</span></div>
    {summary.assessment_trend.length ? <div className="report-table-wrap"><table className="report-table report-band-table report-trend-table"><thead><tr><th scope="col">{t('reports.month')}</th><th scope="col">{t('reports.attempts')}</th><th scope="col">{t('reports.averageScore')}</th></tr></thead><tbody>{summary.assessment_trend.map((item) => <tr key={item.month}><th scope="row">{monthLabel(item.month)}</th><td>{item.attempts}</td><td>{item.average_percent}%</td></tr>)}</tbody></table></div> : <div className="academic-empty">{t('reports.noCompletedAttempts')}</div>}
    <div className="report-student-heading report-trend-heading"><h3>{t('reports.resultsByAssessment')}</h3><span>{summary.assessment_breakdown.length} {t('reports.assessmentCount')}</span></div>
    {summary.assessment_breakdown.length ? <div className="report-table-wrap"><table className="report-table report-assessment-breakdown"><thead><tr><th scope="col">{t('reports.course')}</th><th scope="col">{t('reports.assessment')}</th><th scope="col">{t('reports.attempts')}</th><th scope="col">{t('reports.averageScore')}</th></tr></thead><tbody>{summary.assessment_breakdown.map((item) => <tr key={item.assessment_id}><td>{item.course_title}</td><th scope="row">{item.assessment_title}</th><td>{item.attempts}</td><td>{item.average_percent}%</td></tr>)}</tbody></table></div> : <div className="academic-empty">{t('reports.noCompletedAssessments')}</div>}
    <div className="report-student-heading report-trend-heading"><h3>{t('reports.resultsByCourse')}</h3><span>{summary.assessment_course_summary.length} {t('reports.courseCount')}</span></div>
    {summary.assessment_course_summary.length ? <div className="report-table-wrap"><table className="report-table report-assessment-breakdown"><thead><tr><th scope="col">{t('reports.course')}</th><th scope="col">{t('reports.attempts')}</th><th scope="col">{t('reports.averageScore')}</th></tr></thead><tbody>{summary.assessment_course_summary.map((item) => <tr key={item.course_id}><th scope="row">{item.course_title}</th><td>{item.attempts}</td><td>{item.average_percent}%</td></tr>)}</tbody></table></div> : <div className="academic-empty">{t('reports.noCourseResults')}</div>}
  </section>;
}

function StudentDashboardPanel({ token, organization }: { token: string; organization: Organization }) {
  const { locale, t } = useLocale();
  const [dashboard, setDashboard] = useState<StudentDashboard | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  useEffect(() => {
    let cancelled = false;
    getStudentDashboard(token, organization.id).then((data) => { if (!cancelled) setDashboard(data); })
      .catch((cause) => { if (!cancelled) setError(cause instanceof Error ? cause.message : t('learner.loadSummaryError')); })
      .finally(() => { if (!cancelled) setLoading(false); });
    return () => { cancelled = true; };
  }, [token, organization.id]);
  const dueDate = (value: string | null) => value ? new Date(value).toLocaleString(locale === 'hi' ? 'hi-IN' : 'en-IN', { dateStyle: 'medium', timeStyle: 'short', timeZone: organization.timezone }) : t('learner.noDueDate');
  const canViewResults = hasPermission(organization, 'results.view');
  const canViewAttendance = hasPermission(organization, 'attendance.read');
  function printCertificate(course: StudentDashboard['courses'][number]) {
    if (!dashboard?.student || !course.certificate) return;
    const popup = window.open('', '_blank');
    if (!popup) { setError('Allow pop-ups to open your printable certificate.'); return; }
    popup.opener = null;
    const escapeHtml = (value: string) => value.replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character] ?? character);
    const issuedDate = new Date(course.certificate.issued_at).toLocaleDateString('en-IN', { dateStyle: 'long' });
    popup.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>Course certificate</title><style>body{font-family:Arial,sans-serif;color:#17233d;margin:0;padding:42px;background:#f4f6fb}.certificate{max-width:900px;margin:0 auto;background:white;border:10px double #5b55ad;padding:64px;text-align:center}.brand{color:#5b55ad;font-size:14px;letter-spacing:4px;text-transform:uppercase}h1{font-family:Georgia,serif;font-size:46px;margin:30px 0 8px}p{font-size:18px}.name{font-family:Georgia,serif;font-size:34px;font-weight:bold;margin:20px}.course{font-size:24px;font-weight:bold;margin:18px}.meta{margin-top:42px;color:#58627a;font-size:13px}@media print{body{background:white;padding:0}.certificate{max-width:none;height:6.8in;margin:0;border-width:8px;padding:55px}}</style></head><body><main class="certificate"><div class="brand">PaathNexus AI · ${escapeHtml(organization.name)}</div><h1>Certificate of Completion</h1><p>This certifies that</p><div class="name">${escapeHtml(dashboard.student.name)}</div><p>has completed all required active lessons in</p><div class="course">${escapeHtml(course.title)}</div><p>${course.certificate.completed_lessons} of ${course.certificate.total_lessons} lessons completed</p><div class="meta">Issued ${escapeHtml(issuedDate)} · Certificate ${escapeHtml(course.certificate.code)}</div></main><script>window.addEventListener('load',()=>setTimeout(()=>window.print(),300))</script></body></html>`);
    popup.document.close();
  }
  return <section className="student-dashboard"><div className="panel-heading"><div><div className="eyebrow">{t('learner.yourLearning')}</div><h2>{dashboard?.student ? `${t('learner.welcome')}, ${dashboard.student.name.split(' ')[0]}` : t('learner.myLearning')}</h2><p>{t('learner.dashboardDescription')}</p></div></div>{error && <div className="academic-error" role="alert">{error}</div>}{loading ? <div className="task-message">{t('learner.loading')}</div> : dashboard && <>
    {!dashboard.student && <div className="academic-empty">{t('learner.unlinked')}</div>}
    <div className="student-dashboard-grid"><article className="panel student-overview-card"><div className="eyebrow">{t('learner.courses')}</div><h3>{dashboard.courses.length}</h3><span>{t('learner.activeCourses')}</span><div className="student-course-progress-list">{dashboard.courses.length ? dashboard.courses.map((course) => { const percent = course.total_lessons ? Math.round((course.completed_lessons / course.total_lessons) * 100) : 0; return <div className="student-course-progress" key={course.id}><div><span><strong>{course.title}</strong><small>{course.code}</small></span><small>{course.completed_lessons}/{course.total_lessons} lessons · {percent}%</small></div><div className="student-progress-track"><i style={{ width: `${percent}%` }} /></div>{course.certificate && <button type="button" className="task-cancel" onClick={() => printCertificate(course)}>{t('learner.printCertificate')}</button>}</div>; }) : <div className="task-message">{t('learner.noCourses')}</div>}</div></article>
      <article className="panel student-overview-card"><div className="eyebrow">{t('learner.upcomingWork')}</div><h3>{dashboard.assignments.length}</h3><span>{t('learner.publishedAssignments')}</span><div className="student-dashboard-list">{dashboard.assignments.length ? dashboard.assignments.map((item) => <div className="student-dashboard-row" key={item.id}><span><strong>{item.title}</strong><small>{item.course_title ?? t('learner.course')}</small></span><span><small>{item.submission_status ? translateLearnerStatus(item.submission_status, t) : t('learner.notSubmitted')}</small><small>{dueDate(item.due_at)}</small></span></div>) : <div className="task-message">{t('learner.noAssignments')}</div>}</div></article>
      <article className="panel student-overview-card"><div className="eyebrow">{t('learner.todayClasses')}</div><h3>{dashboard.today_schedule.length}</h3><span>{t('learner.scheduledClasses')}</span>{dashboard.schedule_notice && <small className="form-hint">{dashboard.schedule_notice}</small>}<div className="student-dashboard-list">{dashboard.today_schedule.length ? dashboard.today_schedule.map((item) => <div className="student-dashboard-row" key={item.id}><span><strong>{item.subject}{item.is_exception ? ` · ${t('learner.extraClass')}` : ''}</strong><small>{item.class_name}{item.teacher_name ? ` · ${item.teacher_name}` : ''}</small></span><span><small>{item.starts_at}–{item.ends_at}</small><small>{item.room || t('learner.roomNotSet')}</small></span></div>) : <div className="task-message">{t('learner.noClassesToday')}</div>}</div></article>
      {canViewResults && <article className="panel student-overview-card"><div className="eyebrow">{t('learner.recentResults')}</div><h3>{dashboard.assessment_results.length}</h3><span>{t('learner.submittedAssessments')}</span><div className="student-dashboard-list">{dashboard.assessment_results.length ? dashboard.assessment_results.map((item) => <div className="student-dashboard-row" key={item.id}><span><strong>{item.title ?? t('learner.assessment')}</strong><small>{item.course_title ?? t('learner.course')}</small></span><span><strong>{item.review_status === 'pending' ? t('learner.awaitingReview') : (item.score ?? 0) + ' / ' + item.max_points}</strong><small>{item.submitted_at ? new Date(item.submitted_at).toLocaleDateString() : ''}</small></span></div>) : <div className="task-message">{t('learner.noQuizResults')}</div>}</div></article>}
      {canViewAttendance && <article className="panel student-overview-card"><div className="eyebrow">{t('learner.attendance')}</div><h3>{dashboard.attendance.rate_percent === null ? '—' : `${dashboard.attendance.rate_percent}%`}</h3><span>{dashboard.attendance.present_or_late} {t('learner.presentOrLate')} · {dashboard.attendance.sessions_recorded} {t('learner.recordedSessions')}</span><div className="student-dashboard-list">{dashboard.attendance.recent.length ? dashboard.attendance.recent.map((item, index) => <div className="student-dashboard-row" key={`${item.date}-${item.class_name}-${index}`}><span><strong>{item.class_name ?? t('learner.class')}</strong><small>{item.date ?? t('learner.dateUnavailable')}</small></span><span><small>{translateLearnerStatus(item.status, t)}</small></span></div>) : <div className="task-message">{t('learner.noAttendance')}</div>}</div></article>}
    </div>
  </>}</section>;
}

function StudentStudyPlanner({ token, organization }: { token: string; organization: Organization }) {
  const { locale, t } = useLocale();
  const [plans, setPlans] = useState<StudentStudyPlan[]>([]);
  const [courses, setCourses] = useState<Course[]>([]);
  const [recommendations, setRecommendations] = useState<StudentDashboard['study_recommendations']>([]);
  const [title, setTitle] = useState('');
  const [courseId, setCourseId] = useState('');
  const [duration, setDuration] = useState(30);
  const [scheduledAt, setScheduledAt] = useState(() => {
    return isoToWorkspaceDateTime(new Date(Date.now() + 60 * 60 * 1000), organization.timezone);
  });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    let current = true;
    Promise.all([listStudentStudyPlans(token, organization.id), listCourses(token, organization.id), getStudentDashboard(token, organization.id)])
      .then(([nextPlans, nextCourses, dashboard]) => { if (current) { setPlans(nextPlans); setCourses(nextCourses); setRecommendations(dashboard.study_recommendations); } })
      .catch((cause) => { if (current) setError(cause instanceof Error ? cause.message : 'Could not load your study plan.'); })
      .finally(() => { if (current) setLoading(false); });
    return () => { current = false; };
  }, [token, organization.id]);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const scheduled = workspaceDateTimeToIso(scheduledAt, organization.timezone);
    if (!scheduled) { setError(`Choose a valid time in the workspace timezone (${organization.timezone}).`); return; }
    setSaving(true); setError('');
    try {
      const plan = await createStudentStudyPlan(token, organization.id, {
        title: title.trim(), scheduled_at: scheduled, duration_minutes: duration,
        ...(courseId ? { course_id: courseId } : {}),
      });
      setPlans((current) => [...current, plan].sort((a, b) => a.scheduled_at.localeCompare(b.scheduled_at)));
      setTitle('');
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not add this study session.'); }
    finally { setSaving(false); }
  }

  async function complete(planId: string) {
    setError('');
    try {
      const updated = await completeStudentStudyPlan(token, organization.id, planId);
      setPlans((current) => current.map((plan) => plan.id === updated.id ? updated : plan));
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not complete this study session.'); }
  }

  const formatTime = (value: string) => new Intl.DateTimeFormat(locale === 'hi' ? 'hi-IN' : 'en-IN', {
    dateStyle: 'medium', timeStyle: 'short', timeZone: organization.timezone,
  }).format(new Date(value));

  return <section className="panel student-study-planner">
    <div className="panel-heading"><div><div className="eyebrow">{t('learner.studyPlan')}</div><h2>{t('learner.planFocusedSession')}</h2><p>{t('learner.planDescription')}</p></div></div>
    {error && <div className="academic-error" role="alert">{error}</div>}
    <form className="student-study-form" onSubmit={(event) => void submit(event)}>
      <label>{t('learner.studyFocus')}<input required maxLength={140} value={title} onChange={(event) => setTitle(event.target.value)} placeholder={t('learner.studyFocusPlaceholder')} /></label>
      <label>{t('learner.course')}<select value={courseId} onChange={(event) => setCourseId(event.target.value)}><option value="">{t('learner.independentStudy')}</option>{courses.map((course) => <option key={course.id} value={course.id}>{course.title}</option>)}</select></label>
      <label>{t('learner.startTime')} ({organization.timezone})<input type="datetime-local" required value={scheduledAt} onChange={(event) => setScheduledAt(event.target.value)} /></label>
      <label>{t('learner.minutes')}<input type="number" min={5} max={240} required value={duration} onChange={(event) => setDuration(Number(event.target.value))} /></label>
      <button className="task-submit" disabled={saving || !title.trim()}>{saving ? t('learner.saving') : t('learner.addToPlan')}</button>
    </form>
    {recommendations.length > 0 && <div className="study-recommendations"><div><strong>{t('learner.suggestedNextSteps')}</strong><small>{t('learner.suggestionBasis')}</small></div><div className="study-recommendation-list">{recommendations.map((item) => <article key={item.id}><span><strong>{item.title}</strong><small>{item.course_title} · {item.reason}</small></span><button type="button" className="task-cancel" onClick={() => { setTitle(item.title); setCourseId(item.course_id); setDuration(item.duration_minutes); }}>{t('learner.useSuggestion')}</button></article>)}</div></div>}
    <div className="student-plan-list" aria-live="polite">
      {loading ? <div className="task-message">{t('learner.loadingPlan')}</div> : plans.length ? plans.map((plan) => <article className={`student-plan-item ${plan.status === 'completed' ? 'study-plan-complete' : ''}`} key={plan.id}>
        <span className="study-plan-check">{plan.status === 'completed' ? <Check size={15} /> : <Clock3 size={15} />}</span>
        <div><strong>{plan.title}</strong><small>{plan.course_title || t('learner.independentStudy')} · {plan.duration_minutes} min · {formatTime(plan.scheduled_at)}</small></div>
        {plan.status === 'planned' ? <button className="task-cancel" onClick={() => void complete(plan.id)}>{t('learner.markComplete')}</button> : <span className="study-plan-status">{t('learner.completed')}</span>}
      </article>) : <div className="task-message">{t('learner.noStudySessions')}</div>}
    </div>
  </section>;
}

function PlanAndBillingPanel({ token, organization }: { token: string; organization: Organization }) {
  const { locale, t } = useLocale();
  const [subscription, setSubscription] = useState<WorkspaceSubscription | null>(null);
  const [selectedPlan, setSelectedPlan] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  async function refresh() {
    setLoading(true); setError('');
    try { const next = await getWorkspaceSubscription(token, organization.id); setSubscription(next); setSelectedPlan(next.available_plans.find((plan) => plan.id !== next.plan.id)?.id ?? ''); }
    catch (cause) { setError(cause instanceof Error ? cause.message : t('billing.loadError')); }
    finally { setLoading(false); }
  }
  useEffect(() => { void refresh(); }, [token, organization.id]);
  async function requestChange(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!selectedPlan) return;
    setSaving(true); setError('');
    try { await requestSubscriptionPlan(token, organization.id, selectedPlan); await refresh(); }
    catch (cause) { setError(cause instanceof Error ? cause.message : t('billing.changeError')); }
    finally { setSaving(false); }
  }
  const localeTag = locale === 'hi' ? 'hi-IN' : 'en-IN';
  const intervalKey: Record<SubscriptionPlan['billing_interval'], TranslationKey> = { month: 'billing.perMonth', year: 'billing.perYear', custom: 'billing.customInterval' };
  const audienceKey: Record<SubscriptionPlan['audience'], TranslationKey> = { all: 'billing.audienceAll', institution: 'billing.audienceInstitution', educator: 'billing.audienceEducator' };
  const entitlementKey: Record<string, TranslationKey> = { max_students: 'billing.limitMaxStudents', max_courses: 'billing.limitMaxCourses', max_teachers: 'billing.limitMaxTeachers', monthly_ai_requests: 'billing.limitMonthlyAiRequests' };
  const price = (plan: SubscriptionPlan) => plan.amount_paise === null ? t('billing.contactPricing') : plan.amount_paise === 0 ? t('billing.free') : `${new Intl.NumberFormat(localeTag, { style: 'currency', currency: 'INR' }).format(plan.amount_paise / 100)} / ${t(intervalKey[plan.billing_interval])}`;
  const entitlements = (plan: SubscriptionPlan) => Object.entries(plan.entitlements ?? {}).filter(([, value]) => value !== null).map(([key, value]) => `${entitlementKey[key] ? t(entitlementKey[key]) : key.replaceAll('_', ' ')}: ${value}`).join(' · ');
  const status = subscription?.status === 'change_pending' ? t('billing.pending') : t('billing.active');
  return <section className="panel academic-panel subscription-panel"><div className="panel-heading"><div><div className="eyebrow">{t('billing.eyebrow')}</div><h2>{t('billing.title')}</h2><p>{t('billing.description')}</p></div></div>{error && <div className="academic-error" role="alert">{error}</div>}{loading ? <div className="task-message">{t('billing.loading')}</div> : subscription && <><article className="subscription-current"><div><small>{t('billing.current')}</small><h3>{subscription.plan.name}</h3><p>{price(subscription.plan)}</p><span>{entitlements(subscription.plan) || t('billing.noLimits')}</span></div><span className={`assignment-status ${subscription.status === 'change_pending' ? '' : 'graded'}`}>{status}</span></article>{subscription.requested_plan && <div className="task-message">{t('billing.requestPending')}: {subscription.requested_plan.name} · {t('billing.requested')} {subscription.requested_at ? new Date(subscription.requested_at).toLocaleDateString(localeTag, { timeZone: organization.timezone }) : t('billing.recently')}.</div>}<form className="academic-form subscription-request-form" onSubmit={(event) => void requestChange(event)}><strong>{t('billing.requestChange')}</strong><label>{t('billing.availablePlan')}<select required value={selectedPlan} onChange={(event) => setSelectedPlan(event.target.value)} disabled={subscription.status === 'change_pending'}><option value="">{t('billing.choosePlan')}</option>{subscription.available_plans.filter((plan) => plan.id !== subscription.plan.id).map((plan) => <option value={plan.id} key={plan.id}>{plan.name} · {price(plan)}</option>)}</select></label><button className="task-submit" disabled={saving || !selectedPlan || subscription.status === 'change_pending'}>{saving ? t('billing.sending') : t('billing.requestButton')}</button><small className="form-hint">{t('billing.reviewNote')}</small></form><div className="plan-catalog-list">{subscription.available_plans.map((plan) => <article className="plan-card" key={plan.id}><div><strong>{plan.name}</strong><small>{t(audienceKey[plan.audience])} · {price(plan)}</small></div><p>{plan.description || t('billing.noDescription')}</p><span>{entitlements(plan) || t('billing.noLimits')}</span></article>)}</div></>}</section>;
}

function PlatformPlansPanel({ token }: { token: string }) {
  const { locale, t } = useLocale();
  const formatDate = (value: string, options: Intl.DateTimeFormatOptions = { dateStyle: 'medium' }) => new Date(value).toLocaleDateString(locale === 'hi' ? 'hi-IN' : 'en-IN', options);
  const [usage, setUsage] = useState<PlatformUsageMetrics | null>(null);
  const [usageHistory, setUsageHistory] = useState<PlatformUsageSnapshot[]>([]);
  const maximumActiveWorkspaces = Math.max(1, ...usageHistory.map((item) => item.active_organization_count));
  const [plans, setPlans] = useState<SubscriptionPlan[]>([]);
  const [featureFlags, setFeatureFlags] = useState<PlatformFeatureFlag[]>([]);
  const [requests, setRequests] = useState<SubscriptionRequest[]>([]);
  const [auditEntries, setAuditEntries] = useState<PlatformAuditEntry[]>([]);
  const [organizations, setOrganizations] = useState<PlatformOrganization[]>([]);
  const [organizationQuery, setOrganizationQuery] = useState('');
  const [organizationSearch, setOrganizationSearch] = useState('');
  const [organizationPage, setOrganizationPage] = useState(1);
  const [organizationLastPage, setOrganizationLastPage] = useState(1);
  const [organizationTotal, setOrganizationTotal] = useState(0);
  const [organizationLoading, setOrganizationLoading] = useState(false);
  const [code, setCode] = useState(''); const [name, setName] = useState(''); const [audience, setAudience] = useState<SubscriptionPlan['audience']>('institution');
  const [amount, setAmount] = useState(''); const [interval, setInterval] = useState<SubscriptionPlan['billing_interval']>('month');
  const [description, setDescription] = useState(''); const [entitlementJson, setEntitlementJson] = useState('{"max_students": 250, "max_courses": 25}');
  const [edits, setEdits] = useState<Record<string, { amount: string; entitlements: string; is_active: boolean }>>({});
  const [references, setReferences] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(true); const [saving, setSaving] = useState(false); const [error, setError] = useState('');
  async function refresh() {
    setLoading(true); setError('');
    try {
      const [nextPlans, nextRequests, organizationResult, nextAuditEntries, nextUsage, nextUsageHistory, nextFeatureFlags] = await Promise.all([
        listPlatformPlans(token), listSubscriptionRequests(token), listPlatformOrganizations(token, organizationQuery, organizationPage), listPlatformAuditLogs(token), getPlatformUsage(token), getPlatformUsageHistory(token), listPlatformFeatureFlags(token),
      ]);
      setPlans(nextPlans); setRequests(nextRequests);
      setFeatureFlags(nextFeatureFlags);
      setAuditEntries(nextAuditEntries);
      setUsage(nextUsage);
      setUsageHistory(nextUsageHistory.snapshots);
      setOrganizations(organizationResult.items); setOrganizationTotal(organizationResult.total); setOrganizationLastPage(organizationResult.lastPage);
      setEdits(Object.fromEntries(nextPlans.map((plan) => [plan.id, { amount: plan.amount_paise === null ? '' : String(plan.amount_paise / 100), entitlements: JSON.stringify(plan.entitlements ?? {}, null, 2), is_active: plan.is_active }])));
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not load plan administration.'); }
    finally { setLoading(false); }
  }
  useEffect(() => { void refresh(); }, [token]);
  async function loadOrganizations(query = organizationQuery, page = organizationPage) {
    setOrganizationLoading(true); setError('');
    try {
      const result = await listPlatformOrganizations(token, query, page);
      setOrganizationQuery(query); setOrganizationPage(result.page); setOrganizations(result.items);
      setOrganizationTotal(result.total); setOrganizationLastPage(result.lastPage);
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not load organizations.'); }
    finally { setOrganizationLoading(false); }
  }
  async function searchOrganizations(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); await loadOrganizations(organizationSearch.trim(), 1);
  }
  async function changeOrganizationStatus(organization: PlatformOrganization) {
    const status = organization.status === 'active' ? 'suspended' : 'active';
    const action = status === 'suspended' ? 'suspend' : 'reactivate';
    const actionLabel = t(status === 'suspended' ? 'platformAdmin.suspendVerb' : 'platformAdmin.reactivateVerb');
    if (!window.confirm(t('platformAdmin.confirmStatusChange').replace('{action}', actionLabel).replace('{organization}', organization.name))) return;
    setSaving(true); setError('');
    try { await updatePlatformOrganizationStatus(token, organization.id, status, 'Status changed in platform administration.'); await refresh(); }
    catch (cause) { setError(cause instanceof Error ? cause.message : `Could not ${action} workspace.`); }
    finally { setSaving(false); }
  }
  async function toggleFeatureFlag(flag: PlatformFeatureFlag) {
    setSaving(true); setError('');
    try {
      const updated = await updatePlatformFeatureFlag(token, flag.key, !flag.enabled);
      setFeatureFlags((current) => current.map((item) => item.key === updated.key ? updated : item));
      setAuditEntries(await listPlatformAuditLogs(token));
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not update the feature flag.'); }
    finally { setSaving(false); }
  }
  async function createPlan(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); let entitlements: Record<string, number | null>;
    try { entitlements = JSON.parse(entitlementJson) as Record<string, number | null>; if (!entitlements || Array.isArray(entitlements) || typeof entitlements !== 'object') throw new Error('Enter a JSON object.'); }
    catch { setError(t('platformPlans.invalidEntitlements')); return; }
    setSaving(true); setError('');
    try { await createPlatformPlan(token, { code: code.trim(), name: name.trim(), audience, description: description.trim() || null, amount_paise: amount === '' ? null : Math.round(Number(amount) * 100), billing_interval: interval, entitlements }); setCode(''); setName(''); setDescription(''); setAmount(''); await refresh(); }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not create the plan.'); }
    finally { setSaving(false); }
  }
  async function savePlan(plan: SubscriptionPlan) {
    const edit = edits[plan.id]; if (!edit) return; let entitlements: Record<string, number | null>;
    try { entitlements = JSON.parse(edit.entitlements) as Record<string, number | null>; }
    catch { setError(`Entitlements for ${plan.name} must be valid JSON.`); return; }
    setSaving(true); setError('');
    try { await updatePlatformPlan(token, plan.id, { amount_paise: edit.amount === '' ? null : Math.round(Number(edit.amount) * 100), entitlements, is_active: edit.is_active }); await refresh(); }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not update the plan.'); }
    finally { setSaving(false); }
  }
  async function resolve(item: SubscriptionRequest, decision: 'approve' | 'reject') {
    setSaving(true); setError('');
    try { await resolveSubscriptionRequest(token, item.organization_id, { decision, ...(decision === 'approve' ? { verification_reference: references[item.organization_id] ?? '' } : {}) }); await refresh(); }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not resolve the request.'); }
    finally { setSaving(false); }
  }
  return <section className="panel academic-panel subscription-panel"><div className="panel-heading"><div><div className="eyebrow">{t('platformPlans.eyebrow')}</div><h2>{t('platformPlans.title')}</h2><p>{t('platformPlans.description')}</p></div></div>{error && <div className="academic-error" role="alert">{error}</div>}{loading ? <div className="task-message">{t('platformPlans.loading')}</div> : <>
    {usage && <div className="panel academic-panel"><div className="course-progress-heading"><strong>{t('platformAdmin.usage')}</strong><span>{t('platformAdmin.activeOnly')}</span></div><div className="report-metrics"><article className="report-metric"><span>{t('platformAdmin.workspaces')}</span><strong>{usage.organization_count}</strong><small>{t('platformAdmin.active')}: {usage.active_organization_count} · {t('platformAdmin.suspended')}: {usage.suspended_organization_count}</small></article><article className="report-metric"><span>{t('platformAdmin.members')}</span><strong>{usage.active_membership_count}</strong><small>{t('platformAdmin.acrossActiveWorkspaces')}</small></article><article className="report-metric"><span>{t('platformAdmin.learners')}</span><strong>{usage.active_student_count}</strong><small>{t('platformAdmin.activeProfiles')}</small></article><article className="report-metric"><span>{t('platformAdmin.courses')}</span><strong>{usage.active_course_count}</strong><small>{t('platformAdmin.acrossActiveWorkspaces')}</small></article><article className="report-metric"><span>{t('platformAdmin.subscriptions')}</span><strong>{usage.active_subscription_count}</strong><small>{t('platformAdmin.acrossActiveWorkspaces')}</small></article></div><section className="usage-history" aria-labelledby="usage-history-title"><div className="course-progress-heading"><h3 id="usage-history-title">{t('platformAdmin.historyTitle')}</h3><span>{t('platformAdmin.historyPeriod')}</span></div>{usageHistory.length ? <><div className="usage-history-chart" role="img" aria-label={t('platformAdmin.chartLabel').replace('{start}', usageHistory[0]?.snapshot_date ?? 'unknown').replace('{end}', usageHistory.at(-1)?.snapshot_date ?? 'unknown').replace('{count}', String(usageHistory.length))}>{usageHistory.map((snapshot) => { const height = Math.max(4, snapshot.active_organization_count / maximumActiveWorkspaces * 100); return <span key={snapshot.snapshot_date} title={t('platformAdmin.barTitle').replace('{date}', snapshot.snapshot_date).replace('{count}', String(snapshot.active_organization_count))} style={{ height: `${height}%` }} />; })}</div><div className="report-table-wrap usage-history-table-wrap" role="region" aria-label={t('platformAdmin.historyRegion')} tabIndex={0}><table className="report-table"><thead><tr><th scope="col">{t('platformAdmin.date')}</th><th scope="col">{t('platformAdmin.workspaces')}</th><th scope="col">{t('platformAdmin.active')}</th><th scope="col">{t('platformAdmin.suspended')}</th><th scope="col">{t('platformAdmin.members')}</th><th scope="col">{t('platformAdmin.learners')}</th><th scope="col">{t('platformAdmin.courses')}</th><th scope="col">{t('platformAdmin.subscriptions')}</th></tr></thead><tbody>{[...usageHistory].reverse().map((snapshot) => <tr key={snapshot.snapshot_date}><td>{formatDate(snapshot.snapshot_date, { dateStyle: 'medium', timeZone: 'UTC' })}</td><td>{snapshot.organization_count}</td><td>{snapshot.active_organization_count}</td><td>{snapshot.suspended_organization_count}</td><td>{snapshot.active_membership_count}</td><td>{snapshot.active_student_count}</td><td>{snapshot.active_course_count}</td><td>{snapshot.active_subscription_count}</td></tr>)}</tbody></table></div></> : <div className="academic-empty">{t('platformAdmin.noHistory')}</div>}</section></div>}
    <PlatformFeatureFlagsPanel flags={featureFlags} saving={saving} onToggle={(flag) => void toggleFeatureFlag(flag)} />
    <div className="plan-catalog-list platform-organizations"><div className="course-progress-heading"><div><strong>{t('platformAdmin.organizations')}</strong><small>{organizationTotal} {t('platformAdmin.workspacesUsageNote')}</small></div></div><form className="platform-org-search" onSubmit={(event) => void searchOrganizations(event)}><label>{t('platformAdmin.searchOrganizations')}<input maxLength={100} value={organizationSearch} onChange={(event) => setOrganizationSearch(event.target.value)} placeholder={t('platformAdmin.findOrganization')} /></label><button className="task-submit" disabled={organizationLoading}>{organizationLoading ? t('platformAdmin.searching') : t('platformAdmin.search')}</button></form>{organizationLoading ? <div className="task-message">{t('platformAdmin.loadingOrganizations')}</div> : organizations.length ? <div className="platform-org-list">{organizations.map((organization) => <article className="plan-card platform-org-card" key={organization.id}><div className="platform-org-header"><div><strong>{organization.name}</strong><small>{organization.slug} · {organization.type} · {t('platformAdmin.created')} {formatDate(organization.created_at)}</small></div><span className={`assignment-status ${organization.status === 'active' ? 'graded' : ''}`}>{t(organization.status === 'active' ? 'platformAdmin.active' : 'platformAdmin.suspended')}</span></div><div className="platform-org-metrics"><span>{t('platformAdmin.membersCount').replace('{count}', String(organization.active_members_count))}</span><span>{t('platformAdmin.studentsCount').replace('{count}', String(organization.active_students_count))}</span><span>{t('platformAdmin.coursesCount').replace('{count}', String(organization.active_courses_count))}</span><span>{organization.current_plan_name ?? t('platformAdmin.noPlan')}</span></div><button type="button" className={organization.status === 'active' ? 'invoice-void' : 'task-submit'} disabled={saving} onClick={() => void changeOrganizationStatus(organization)}>{t(organization.status === 'active' ? 'platformAdmin.suspend' : 'platformAdmin.reactivate')}</button></article>)}</div> : <div className="academic-empty">{organizationQuery ? t('platformAdmin.noSearchResults') : t('platformAdmin.noOrganizations')}</div>}{organizationLastPage > 1 && <div className="platform-org-pagination"><button type="button" className="task-cancel" disabled={organizationLoading || organizationPage <= 1} onClick={() => void loadOrganizations(organizationQuery, organizationPage - 1)}>{t('platformAdmin.previous')}</button><span>{t('platformAdmin.page').replace('{current}', String(organizationPage)).replace('{total}', String(organizationLastPage))}</span><button type="button" className="task-cancel" disabled={organizationLoading || organizationPage >= organizationLastPage} onClick={() => void loadOrganizations(organizationQuery, organizationPage + 1)}>{t('platformAdmin.next')}</button></div>}</div>
    <div className="plan-catalog-list platform-audit"><div className="course-progress-heading"><div><strong>{t('platformAdmin.auditTitle')}</strong><small>{t('platformAdmin.auditSummary').replace('{count}', String(auditEntries.length))}</small></div></div>{auditEntries.length ? <div className="platform-audit-list">{auditEntries.map((entry) => <article className="plan-card platform-audit-entry" key={entry.id}><div><strong>{entry.event.replaceAll('.', ' ')}</strong><small>{entry.subject_type ?? t('platformAdmin.system')} · {entry.subject_id ?? t('platformAdmin.noSubject')} · {t('platformAdmin.actor')} {entry.actor_id ?? 'system'}</small></div><time dateTime={entry.created_at}>{new Date(entry.created_at).toLocaleString(locale === 'hi' ? 'hi-IN' : 'en-IN')}</time></article>)}</div> : <div className="academic-empty">{t('platformAdmin.noActions')}</div>}</div>
    <form className="academic-form plan-create-form" onSubmit={(event) => void createPlan(event)}><strong>{t('platformPlans.createTitle')}</strong><label>{t('platformPlans.code')}<input required maxLength={50} pattern="[A-Za-z0-9_-]+" value={code} onChange={(event) => setCode(event.target.value)} placeholder="institution-growth" /></label><label>{t('platformPlans.name')}<input required maxLength={120} value={name} onChange={(event) => setName(event.target.value)} /></label><label>{t('platformPlans.audience')}<select value={audience} onChange={(event) => setAudience(event.target.value as SubscriptionPlan['audience'])}><option value="institution">{t('platformPlans.audience.institution')}</option><option value="educator">{t('platformPlans.audience.educator')}</option><option value="all">{t('platformPlans.audience.all')}</option></select></label><label>{t('platformPlans.priceCreate')}<input type="number" min="0" max="10000000" step="0.01" value={amount} onChange={(event) => setAmount(event.target.value)} /></label><label>{t('platformPlans.interval')}<select value={interval} onChange={(event) => setInterval(event.target.value as SubscriptionPlan['billing_interval'])}><option value="month">{t('platformPlans.interval.month')}</option><option value="year">{t('platformPlans.interval.year')}</option><option value="custom">{t('platformPlans.interval.custom')}</option></select></label><label>{t('platformPlans.descriptionField')}<input maxLength={2000} value={description} onChange={(event) => setDescription(event.target.value)} /></label><label className="entitlement-input">{t('platformPlans.entitlementsJson')}<textarea required value={entitlementJson} onChange={(event) => setEntitlementJson(event.target.value)} /></label><button className="task-submit" disabled={saving || !code.trim() || !name.trim()}>{saving ? t('platformPlans.saving') : t('platformPlans.create')}</button></form>
    <div className="plan-catalog-list"><div className="course-progress-heading"><strong>{t('platformPlans.configured')}</strong><span>{plans.length}</span></div>{plans.map((plan) => { const edit = edits[plan.id] ?? { amount: '', entitlements: '{}', is_active: plan.is_active }; const audienceLabel = t(`platformPlans.audience.${plan.audience}`); const intervalLabel = t(`platformPlans.interval.${plan.billing_interval}`); return <article className="plan-card plan-admin-card" key={plan.id}><div><strong>{plan.name} <small>({plan.code})</small></strong><span>{audienceLabel} · {intervalLabel}</span></div><label>{t('platformPlans.price')}<input type="number" min="0" step="0.01" value={edit.amount} onChange={(event) => setEdits((all) => ({ ...all, [plan.id]: { ...(all[plan.id] ?? edit), amount: event.target.value } }))} /></label><label>{t('platformPlans.entitlementsJson')}<textarea value={edit.entitlements} onChange={(event) => setEdits((all) => ({ ...all, [plan.id]: { ...(all[plan.id] ?? edit), entitlements: event.target.value } }))} /></label><label className="plan-active-toggle"><input type="checkbox" checked={edit.is_active} disabled={plan.code === 'free'} onChange={(event) => setEdits((all) => ({ ...all, [plan.id]: { ...(all[plan.id] ?? edit), is_active: event.target.checked } }))} /> {t('platformPlans.available')}</label><button className="task-submit" disabled={saving} onClick={() => void savePlan(plan)}>{t('platformPlans.save')}</button></article>; })}</div>
    <div className="plan-catalog-list"><div className="course-progress-heading"><strong>{t('platformPlans.requests')}</strong><span>{t('platformPlans.pending').replace('{count}', String(requests.length))}</span></div>{requests.length ? requests.map((item) => <article className="plan-card" key={item.organization_id}><strong>{item.organization_name}</strong><small>{item.plan.name} → {item.requested_plan.name}</small><label>{t('platformPlans.verifiedReference')}<input required value={references[item.organization_id] ?? ''} onChange={(event) => setReferences((all) => ({ ...all, [item.organization_id]: event.target.value }))} placeholder={t('platformPlans.referencePlaceholder')} /></label><div className="plan-request-actions"><button className="task-submit" disabled={saving || !references[item.organization_id]?.trim()} onClick={() => void resolve(item, 'approve')}>{t('platformPlans.approve')}</button><button className="invoice-void" disabled={saving} onClick={() => void resolve(item, 'reject')}>{t('platformPlans.reject')}</button></div></article>) : <div className="academic-empty">{t('platformPlans.noRequests')}</div>}</div>
  </>}</section>;
}

function PlatformFeatureFlagsPanel({ flags, saving, onToggle }: { flags: PlatformFeatureFlag[]; saving: boolean; onToggle: (flag: PlatformFeatureFlag) => void }) {
  const { t } = useLocale();
  return <section className="plan-catalog-list platform-feature-flags" aria-labelledby="platform-feature-flags-title">
    <div className="course-progress-heading"><div><strong id="platform-feature-flags-title">{t('platformAdmin.featureFlags')}</strong><small>{t('platformAdmin.flagsDescription')}</small></div></div>
    {flags.length ? <div className="platform-org-list">{flags.map((flag) => <article className="plan-card platform-org-card" key={flag.key}>
      <div className="platform-org-header"><div><strong>{flag.key === 'ai_tutor' ? t('platformAdmin.flag.aiTutor') : flag.key === 'ai_teacher_assistant' ? t('platformAdmin.flag.teacherAssistant') : flag.label}</strong><small>{flag.key === 'ai_tutor' ? t('platformAdmin.flag.aiTutorDescription') : flag.key === 'ai_teacher_assistant' ? t('platformAdmin.flag.teacherAssistantDescription') : flag.description}</small></div><span className={`assignment-status ${flag.enabled ? 'graded' : ''}`}>{t(flag.enabled ? 'platformAdmin.enabled' : 'platformAdmin.disabled')}</span></div>
      <small>{t('platformAdmin.default').replace('{state}', t(flag.default_enabled ? 'platformAdmin.enabled' : 'platformAdmin.disabled').toLocaleLowerCase())}</small>
      <button type="button" className={flag.enabled ? 'invoice-void' : 'task-submit'} disabled={saving} aria-pressed={flag.enabled} onClick={() => onToggle(flag)}>{(flag.enabled ? t('platformAdmin.disable') : t('platformAdmin.enable')).replace('{feature}', flag.key === 'ai_tutor' ? t('platformAdmin.flag.aiTutor') : flag.key === 'ai_teacher_assistant' ? t('platformAdmin.flag.teacherAssistant') : flag.label)}</button>
    </article>)}</div> : <div className="academic-empty">{t('platformAdmin.noFlags')}</div>}
  </section>;
}

function AiTutorPanel({ token, organization }: { token: string; organization: Organization }) {
  const { t } = useLocale();
  const [courses, setCourses] = useState<Course[]>([]);
  const [lessons, setLessons] = useState<CourseLesson[]>([]);
  const [courseId, setCourseId] = useState('');
  const [lessonId, setLessonId] = useState('');
  const [question, setQuestion] = useState('');
  const [answer, setAnswer] = useState('');
  const [loadingCourses, setLoadingCourses] = useState(true);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelled = false;
    listCourses(token, organization.id).then((items) => { if (!cancelled) { setCourses(items); setCourseId(items[0]?.id ?? ''); } })
      .catch((cause) => { if (!cancelled) setError(cause instanceof Error ? cause.message : 'Could not load authorized courses.'); })
      .finally(() => { if (!cancelled) setLoadingCourses(false); });
    return () => { cancelled = true; };
  }, [token, organization.id]);

  useEffect(() => {
    if (!courseId) { setLessons([]); setLessonId(''); return; }
    let cancelled = false;
    listCourseLessons(token, organization.id, courseId).then((items) => { if (!cancelled) { setLessons(items); setLessonId((current) => items.some((lesson) => lesson.id === current) ? current : ''); } })
      .catch((cause) => { if (!cancelled) setError(cause instanceof Error ? cause.message : 'Could not load course lessons.'); });
    return () => { cancelled = true; };
  }, [token, organization.id, courseId]);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!question.trim()) return;
    setSending(true); setError(''); setAnswer('');
    try {
      const result = await askAiTutor(token, organization.id, {
        question: question.trim(), ...(courseId ? { course_id: courseId } : {}), ...(lessonId ? { lesson_id: lessonId } : {}),
      });
      setAnswer(result.answer); setQuestion('');
    } catch (cause) { setError(cause instanceof Error ? cause.message : t('ai.tutorUnavailable')); }
    finally { setSending(false); }
  }

  return <section className="panel academic-panel ai-tutor-panel"><div className="panel-heading"><div><div className="eyebrow">{t('ai.tutorEyebrow')}</div><h2>{t('ai.tutorTitle')}</h2><p>{t('ai.tutorDescription')}</p></div></div>
    <div className="ai-privacy-note">{t('ai.tutorPrivacy')}</div>
    {error && <div className="academic-error" role="alert">{error}</div>}
    <form className="academic-form ai-question-form" onSubmit={(event) => void submit(event)}><label>{t('ai.courseContext')}<select value={courseId} onChange={(event) => { setCourseId(event.target.value); setLessonId(''); }}><option value="">{t('ai.noCourse')}</option>{courses.map((course) => <option key={course.id} value={course.id}>{course.title}</option>)}</select></label><label>{t('ai.lessonContext')}<select value={lessonId} onChange={(event) => setLessonId(event.target.value)} disabled={!courseId || !lessons.length}><option value="">{t('ai.noLesson')}</option>{lessons.map((lesson) => <option key={lesson.id} value={lesson.id}>{lesson.title}</option>)}</select></label><label>{t('ai.yourQuestion')}<textarea required minLength={3} maxLength={2000} value={question} onChange={(event) => setQuestion(event.target.value)} placeholder={t('ai.questionPlaceholder')} /></label><button className="task-submit" disabled={sending || loadingCourses || !question.trim()}>{sending ? t('ai.thinking') : t('ai.askTutor')}</button><small className="form-hint">{t('ai.providerUnavailable')}</small></form>
    {answer && <article className="ai-answer-card"><strong>{t('ai.tutorResponse')}</strong><p>{answer}</p></article>}
  </section>;
}

function AiTeacherAssistantPanel({ token, organization }: { token: string; organization: Organization }) {
  const { t } = useLocale();
  const [draftType, setDraftType] = useState<AiTeacherDraftType>('lesson_plan');
  const [topic, setTopic] = useState('');
  const [gradeLevel, setGradeLevel] = useState('');
  const [instructions, setInstructions] = useState('');
  const [language, setLanguage] = useState<'en' | 'hi'>('en');
  const [draft, setDraft] = useState('');
  const [generating, setGenerating] = useState(false);
  const [error, setError] = useState('');
  const hasDraftPermission = hasPermission(organization, 'courses.manage');

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!topic.trim()) return;
    setGenerating(true); setError(''); setDraft('');
    try {
      const result = await createAiTeacherDraft(token, organization.id, {
        draft_type: draftType, topic: topic.trim(), ...(gradeLevel.trim() ? { grade_level: gradeLevel.trim() } : {}),
        ...(instructions.trim() ? { instructions: instructions.trim() } : {}), language,
      });
      setDraft(result.draft);
    } catch (cause) { setError(cause instanceof Error ? cause.message : t('ai.assistantUnavailable')); }
    finally { setGenerating(false); }
  }

  if (!hasDraftPermission) return null;
  return <section className="panel academic-panel ai-teacher-assistant-panel"><div className="panel-heading"><div><div className="eyebrow">{t('ai.teacherEyebrow')}</div><h2>{t('ai.teacherTitle')}</h2><p>{t('ai.teacherDescription')}</p></div></div>
    <div className="ai-privacy-note">{t('ai.teacherPrivacy')}</div>
    {error && <div className="academic-error" role="alert">{error}</div>}
    <form className="academic-form ai-question-form" onSubmit={(event) => void submit(event)}>
      <label>{t('ai.draftType')}<select value={draftType} onChange={(event) => setDraftType(event.target.value as AiTeacherDraftType)}><option value="lesson_plan">{t('ai.type.lessonPlan')}</option><option value="course_outline">{t('ai.type.courseOutline')}</option><option value="learning_objectives">{t('ai.type.objectives')}</option><option value="mcqs">{t('ai.type.mcqs')}</option><option value="practice_exercises">{t('ai.type.exercises')}</option><option value="homework_questions">{t('ai.type.homework')}</option><option value="revision_notes">{t('ai.type.revision')}</option><option value="rubric">{t('ai.type.rubric')}</option></select></label>
      <label>{t('ai.topic')}<input required minLength={3} maxLength={160} value={topic} onChange={(event) => setTopic(event.target.value)} placeholder={t('ai.topicPlaceholder')} /></label>
      <div className="academic-form-row"><label>{t('ai.grade')}<input maxLength={80} value={gradeLevel} onChange={(event) => setGradeLevel(event.target.value)} placeholder={t('ai.gradePlaceholder')} /></label><label>{t('ai.language')}<select value={language} onChange={(event) => setLanguage(event.target.value as 'en' | 'hi')}><option value="en">{t('ai.english')}</option><option value="hi">{t('ai.hindi')}</option></select></label></div>
      <label>{t('ai.requirements')}<textarea maxLength={1000} value={instructions} onChange={(event) => setInstructions(event.target.value)} placeholder={t('ai.requirementsPlaceholder')} /></label>
      <button className="task-submit" disabled={generating || topic.trim().length < 3}>{generating ? t('ai.preparing') : t('ai.generate')}</button>
    </form>
    {draft && <div className="ai-answer-card"><strong>{t('ai.draftReview')}</strong><label>{t('ai.editableDraft')}<textarea aria-label={t('ai.generatedDraft')} rows={14} maxLength={20000} value={draft} onChange={(event) => setDraft(event.target.value)} /></label><small className="form-hint">{t('ai.draftNotPublished')}</small></div>}
  </section>;
}

function InvitationsPanel({ token, organization, onNavigate }: { token: string; organization: Organization; onNavigate: (page: string) => void }) {
  const { locale, t } = useLocale();
  const [invitations, setInvitations] = useState<OrganizationInvitation[]>([]);
  const [email, setEmail] = useState('');
  const [role, setRole] = useState<'teacher' | 'student' | 'guardian'>('teacher');
  const [shareUrl, setShareUrl] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  async function refresh() {
    setLoading(true); setError('');
    try { setInvitations(await listOrganizationInvitations(token, organization.id)); }
    catch (cause) { setError(cause instanceof Error ? cause.message : t('invite.loadError')); }
    finally { setLoading(false); }
  }
  useEffect(() => { void refresh(); }, [token, organization.id]);

  async function invite(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setSaving(true); setError(''); setMessage(''); setShareUrl('');
    try {
      const result = await createOrganizationInvitation(token, organization.id, { email: email.trim(), role });
      const url = new URL(window.location.href); url.search = ''; url.searchParams.set('invitation', result.token);
      setShareUrl(url.toString()); setEmail(''); setMessage(t('invite.createdMessage'));
      await refresh();
    } catch (cause) { setError(cause instanceof Error ? cause.message : t('invite.createError')); }
    finally { setSaving(false); }
  }

  async function revoke(invitationId: string) {
    setSaving(true); setError('');
    try { await revokeOrganizationInvitation(token, organization.id, invitationId); await refresh(); }
    catch (cause) { setError(cause instanceof Error ? cause.message : t('invite.revokeError')); }
    finally { setSaving(false); }
  }

  async function copyLink() {
    try { await navigator.clipboard.writeText(shareUrl); setMessage(t('invite.copiedMessage')); }
    catch { setMessage(t('invite.copyUnavailable')); }
  }

  const roleLabel = (value: OrganizationInvitation['role']) => t(`invite.${value}`);
  const statusLabel = (value: OrganizationInvitation['status']) => t(`invite.${value}`);
  return <section className="panel academic-panel invitation-panel"><div className="panel-heading"><div><div className="eyebrow">{t('invite.eyebrow')}</div><h2>{t('invite.title')}</h2><p>{t('invite.description')}</p></div></div>
    {error && <div className="academic-error" role="alert">{error}</div>}{message && <div className="task-message" role="status">{message}</div>}
    <form className="academic-form invitation-form" onSubmit={(event) => void invite(event)}><strong>{t('invite.createTitle')}</strong><label>{t('invite.email')}<input required type="email" maxLength={254} value={email} onChange={(event) => setEmail(event.target.value)} placeholder="person@example.com" /></label><label>{t('invite.role')}<select value={role} onChange={(event) => setRole(event.target.value as typeof role)}><option value="teacher">{t('invite.teacher')}</option><option value="student">{t('invite.student')}</option><option value="guardian">{t('invite.guardian')}</option></select></label><button className="task-submit" disabled={saving || !email.trim()}>{saving ? t('invite.creating') : t('invite.create')}</button></form>
    {shareUrl && <label className="invite-share-link">{t('invite.shareTitle')}<input readOnly value={shareUrl} onFocus={(event) => event.currentTarget.select()} /><button type="button" className="task-cancel" onClick={() => void copyLink()}>{t('invite.copy')}</button></label>}
    <div className="guardian-link-list"><div className="course-progress-heading"><strong>{t('invite.recent')}</strong><span>{invitations.length}</span></div>{loading ? <div className="task-message">{t('invite.loading')}</div> : invitations.length ? invitations.map((item) => <div className="guardian-link-row" key={item.id}><span><strong>{item.email}</strong><small>{roleLabel(item.role)} · {statusLabel(item.status)} · {t('invite.expires')} {new Date(item.expires_at).toLocaleDateString(locale === 'hi' ? 'hi-IN' : 'en-IN')}</small>{item.status === 'accepted' && hasPermission(organization, 'academic.manage') && <small>{item.role === 'teacher' ? t('invite.linkTeacherHint') : item.role === 'student' ? t('invite.linkStudentHint') : t('invite.linkGuardianHint')}</small>}</span>{item.status === 'pending' ? <button type="button" className="task-cancel" disabled={saving} onClick={() => void revoke(item.id)}>{t('invite.revoke')}</button> : item.status === 'accepted' && hasPermission(organization, 'academic.manage') && <button type="button" className="task-cancel" onClick={() => item.role === 'guardian' ? document.getElementById('guardian-links')?.scrollIntoView({ behavior: 'smooth', block: 'start' }) : onNavigate(item.role === 'teacher' ? 'Schedule' : 'Courses')}>{item.role === 'guardian' ? t('invite.openGuardianLinks') : item.role === 'teacher' ? t('invite.openSchedule') : t('invite.openCourses')}</button>}</div>) : <div className="academic-empty">{t('invite.noInvitations')}</div>}</div>
  </section>;
}

function GuardianLinksPanel({ token, organization }: { token: string; organization: Organization }) {
  const [students, setStudents] = useState<StudentProfile[]>([]);
  const [guardians, setGuardians] = useState<GuardianCandidate[]>([]);
  const [links, setLinks] = useState<GuardianStudentLink[]>([]);
  const [studentId, setStudentId] = useState('');
  const [guardianId, setGuardianId] = useState('');
  const [relationship, setRelationship] = useState('parent');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  async function refresh() {
    setLoading(true); setError('');
    try {
      const [nextStudents, nextGuardians, nextLinks] = await Promise.all([
        listStudents(token, organization.id), listGuardianCandidates(token, organization.id), listGuardianLinks(token, organization.id),
      ]);
      setStudents(nextStudents); setGuardians(nextGuardians); setLinks(nextLinks);
      setStudentId((current) => nextStudents.some((student) => student.id === current) ? current : nextStudents[0]?.id ?? '');
      setGuardianId((current) => nextGuardians.some((guardian) => guardian.id.toString() === current) ? current : nextGuardians[0]?.id?.toString() ?? '');
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not load guardian links.'); }
    finally { setLoading(false); }
  }

  useEffect(() => { void refresh(); }, [token, organization.id]);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!studentId || !guardianId) return;
    setSaving(true); setError('');
    try { await linkGuardianStudent(token, organization.id, { student_id: studentId, guardian_user_id: Number(guardianId), relationship }); await refresh(); }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not link guardian.'); }
    finally { setSaving(false); }
  }

  async function remove(linkId: string) {
    setSaving(true); setError('');
    try { await endGuardianLink(token, organization.id, linkId); setLinks((current) => current.filter((link) => link.id !== linkId)); }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not end guardian access.'); }
    finally { setSaving(false); }
  }

  return <section id="guardian-links" className="panel academic-panel guardian-admin-panel"><div className="panel-heading"><div><div className="eyebrow">FAMILY ACCESS</div><h2>Guardian links</h2><p>Give an active guardian account access to one student’s attendance and learning summaries.</p></div></div>
    {error && <div className="academic-error" role="alert">{error}</div>}
    <form className="academic-form guardian-link-form" onSubmit={(event) => void submit(event)}><strong>Link a guardian</strong><label>Student<select required value={studentId} onChange={(event) => setStudentId(event.target.value)}><option value="">Choose a student…</option>{students.map((student) => <option key={student.id} value={student.id}>{student.name} · {student.student_code}</option>)}</select></label><label>Guardian account<select required value={guardianId} onChange={(event) => setGuardianId(event.target.value)}><option value="">Choose a guardian…</option>{guardians.map((guardian) => <option key={guardian.id} value={guardian.id}>{guardian.name} · {guardian.email}</option>)}</select></label><label>Relationship<select value={relationship} onChange={(event) => setRelationship(event.target.value)}><option value="parent">Parent</option><option value="guardian">Guardian</option><option value="grandparent">Grandparent</option><option value="caregiver">Caregiver</option><option value="other">Other</option></select></label><button className="task-submit" disabled={saving || loading || !studentId || !guardianId}>{saving ? 'Saving…' : 'Save guardian link'}</button><small className="form-hint">Only active workspace members with the guardian role can be linked.</small></form>
    <div className="guardian-link-list"><div className="course-progress-heading"><strong>Active links</strong><span>{links.length}</span></div>{loading ? <div className="task-message">Loading guardian links…</div> : links.length ? links.map((link) => <div className="guardian-link-row" key={link.id}><span><strong>{link.student_name}</strong><small>{link.student_code} · {link.guardian_name} ({link.relationship}) · {link.guardian_email}</small></span><button type="button" className="task-cancel" disabled={saving} onClick={() => void remove(link.id)}>End access</button></div>) : <div className="academic-empty">No active guardian links. Add guardian memberships first if needed.</div>}</div>
  </section>;
}

function GuardianSummaryPanel({ token, organization }: { token: string; organization: Organization }) {
  const { locale, t } = useLocale();
  const [children, setChildren] = useState<GuardianStudentSummary[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelled = false;
    listGuardianChildren(token, organization.id).then((items) => { if (!cancelled) setChildren(items); })
      .catch((cause) => { if (!cancelled) setError(cause instanceof Error ? cause.message : 'Could not load linked student summaries.'); })
      .finally(() => { if (!cancelled) setLoading(false); });
    return () => { cancelled = true; };
  }, [token, organization.id]);

  function download(child: GuardianStudentSummary) {
    const rows = [
      [t('guardian.student'), child.student_name], [t('guardian.studentCode'), child.student_code], [t('guardian.relationship'), child.relationship],
      ...(child.attendance ? [[t('guardian.attendanceSessions'), child.attendance.sessions_recorded], [t('guardian.attendanceRate'), child.attendance.rate_percent ?? t('guardian.noData')]] : []),
      ...(child.learning ? [[t('guardian.coursesEnrolled'), child.learning.courses_enrolled], [t('guardian.lessonsCompleted'), `${child.learning.completed_lessons}/${child.learning.total_lessons}`], [t('guardian.lessonCompletion'), child.learning.completion_percent]] : []),
      ...(child.learning?.assignments.map((item) => ['Homework', item.title, item.course_title ?? '', item.submission_status ? translateLearnerStatus(item.submission_status, t) : t('guardian.notSubmitted'), item.due_at ?? t('guardian.noDueDate')]) ?? []),
      ...(child.results ? [[t('guardian.assessmentsCompleted'), child.results.assessments_completed], [t('guardian.averageAssessment'), child.results.average_percent ?? t('guardian.noData')]] : []),
    ];
    const csv = encodeCsv(rows);
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    const anchor = document.createElement('a'); anchor.href = url; anchor.download = `${child.student_code}-learning-summary.csv`; anchor.click(); URL.revokeObjectURL(url);
  }

  const canViewAttendance = hasPermission(organization, 'attendance.read');
  const canViewCourses = hasPermission(organization, 'courses.view');
  const canViewResults = hasPermission(organization, 'results.view');

  return <section className="panel academic-panel guardian-summary-panel"><div className="panel-heading"><div><div className="eyebrow">{t('guardian.familyPortal')}</div><h2>{t('guardian.myChildren')}</h2><p>{t('guardian.description')}</p></div></div>
    {error && <div className="academic-error" role="alert">{error}</div>}
    {loading ? <div className="task-message">{t('guardian.loading')}</div> : children.length ? <div className="guardian-child-list">{children.map((child) => <article className="guardian-child-card" key={child.student_id}>
      <div className="guardian-child-heading"><div><strong>{child.student_name}</strong><small>{child.student_code} · {child.relationship}</small></div><button type="button" className="task-cancel" onClick={() => download(child)}>{t('guardian.download')}</button></div>
      <div className="guardian-metrics">
        {canViewAttendance && child.attendance && <div><span>{t('guardian.attendance')}</span><strong>{child.attendance.rate_percent === null ? t('guardian.noData') : `${child.attendance.rate_percent}%`}</strong><small>{child.attendance.present}/{child.attendance.sessions_recorded} {t('guardian.sessionsPresent')}</small></div>}
        {canViewCourses && child.learning && <div><span>{t('guardian.lessonProgress')}</span><strong>{child.learning.completion_percent}%</strong><small>{child.learning.completed_lessons}/{child.learning.total_lessons} {t('guardian.lessons')} · {child.learning.courses_enrolled} {t('guardian.courses')}</small></div>}
        {canViewResults && child.results && <div><span>{t('guardian.assessmentAverage')}</span><strong>{child.results.average_percent === null ? t('guardian.noData') : `${child.results.average_percent}%`}</strong><small>{child.results.assessments_completed} {t('guardian.completed')}</small></div>}
      </div>
      <div className="guardian-recent-grid">
        {canViewAttendance && child.attendance && <div><strong>{t('guardian.recentAttendance')}</strong>{child.attendance.recent.length ? child.attendance.recent.map((item, index) => <small key={`${item.date}-${index}`}>{item.date ?? t('learner.dateUnavailable')} · {item.class_name ?? t('learner.class')} · {translateLearnerStatus(item.status, t)}</small>) : <small>{t('guardian.noAttendance')}</small>}</div>}
        {canViewResults && child.results && <div><strong>{t('guardian.recentResults')}</strong>{child.results.recent.length ? child.results.recent.map((item, index) => <small key={`${item.title}-${index}`}>{item.title} · {item.score}/{item.max_points}</small>) : <small>{t('guardian.noResults')}</small>}</div>}
        {canViewResults && child.results && <div><strong>{t('guardian.upcomingExams')}</strong>{child.results.upcoming_exams.length ? child.results.upcoming_exams.map((item, index) => <small key={`${item.title}-${index}`}><b>{item.title}</b>{item.course_title ? ` · ${item.course_title}` : ''} · {t('guardian.opens')} {new Date(item.opens_at).toLocaleString(locale === 'hi' ? 'hi-IN' : 'en-IN', { dateStyle: 'medium', timeStyle: 'short', timeZone: organization.timezone })}{item.closes_at ? ` · ${t('guardian.closes')} ${new Date(item.closes_at).toLocaleString(locale === 'hi' ? 'hi-IN' : 'en-IN', { dateStyle: 'medium', timeStyle: 'short', timeZone: organization.timezone })}` : ''}</small>) : <small>{t('guardian.noExams')}</small>}</div>}
        {canViewCourses && child.learning && <div><strong>{t('guardian.homework')}</strong>{child.learning.assignments.length ? child.learning.assignments.map((item) => <small key={item.id}><b>{item.title}</b>{item.course_title ? ` · ${item.course_title}` : ''} · {item.submission_status === 'graded' ? t('guardian.graded') : item.submission_status === 'submitted' ? t('guardian.submitted') : t('guardian.notSubmitted')} · {item.due_at ? `${t('guardian.due')} ${new Date(item.due_at).toLocaleString(locale === 'hi' ? 'hi-IN' : 'en-IN', { dateStyle: 'medium', timeZone: organization.timezone })}` : t('guardian.noDueDate')}</small>) : <small>{t('guardian.noHomework')}</small>}</div>}
      </div>
    </article>)}</div> : <div className="academic-empty">{t('guardian.noLinkedStudents')}</div>}
  </section>;
}

function AssignmentAttachmentList({ token, organizationId, courseId, assignmentId, attachments, label }: {
  token: string; organizationId: string; courseId: string; assignmentId: string; attachments: AssignmentAttachment[]; label: string;
}) {
  const [downloadingId, setDownloadingId] = useState('');
  const [error, setError] = useState('');
  async function download(attachment: AssignmentAttachment) {
    setDownloadingId(attachment.id); setError('');
    try {
      const blob = await downloadAssignmentAttachment(token, organizationId, courseId, assignmentId, attachment.id);
      const url = URL.createObjectURL(blob); const anchor = document.createElement('a');
      anchor.href = url; anchor.download = attachment.name; anchor.click(); URL.revokeObjectURL(url);
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not download this file.'); }
    finally { setDownloadingId(''); }
  }
  if (!attachments.length) return null;
  return <div className="assignment-attachments"><strong>{label}</strong>{attachments.map((attachment) => <button key={attachment.id} type="button" className="assignment-attachment-link" disabled={downloadingId === attachment.id} onClick={() => void download(attachment)}>{downloadingId === attachment.id ? 'Downloading…' : `${attachment.name} · ${(attachment.size_bytes / 1024 / 1024).toFixed(2)} MB`}</button>)}{error && <small className="academic-error" role="alert">{error}</small>}</div>;
}

function CoursesPanel({ token, organization }: { token: string; organization: Organization }) {
  const [courses, setCourses] = useState<Course[]>([]);
  const [students, setStudents] = useState<StudentProfile[]>([]);
  const [instructorCandidates, setInstructorCandidates] = useState<{ id: number; name: string }[]>([]);
  const [courseInstructorIds, setCourseInstructorIds] = useState<number[]>([]);
  const [studentCandidates, setStudentCandidates] = useState<StudentMemberCandidate[]>([]);
  const [lessons, setLessons] = useState<CourseLesson[]>([]);
  const [assignments, setAssignments] = useState<CourseAssignment[]>([]);
  const [bankQuestions, setBankQuestions] = useState<BankQuestion[]>([]);
  const [assessments, setAssessments] = useState<LearningAssessment[]>([]);
  const [assessmentResults, setAssessmentResults] = useState<Record<string, AssessmentAttemptSummary[]>>({});
  const [activeAttempt, setActiveAttempt] = useState<{ assessmentId: string; attempt: AssessmentAttempt } | null>(null);
  const [submissions, setSubmissions] = useState<AssignmentSubmission[]>([]);
  const [enrollments, setEnrollments] = useState<CourseEnrollment[]>([]);
  const [progress, setProgress] = useState<CourseProgress[]>([]);
  const [selectedCourseId, setSelectedCourseId] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [courseCode, setCourseCode] = useState('');
  const [courseTitle, setCourseTitle] = useState('');
  const [courseDescription, setCourseDescription] = useState('');
  const [coursePrice, setCoursePrice] = useState('0');
  const [courseEditTitle, setCourseEditTitle] = useState('');
  const [courseEditDescription, setCourseEditDescription] = useState('');
  const [courseEditPrice, setCourseEditPrice] = useState('0');
  const [courseEditStatus, setCourseEditStatus] = useState<'active' | 'draft'>('active');
  const [courseEditPublic, setCourseEditPublic] = useState(false);
  const [lessonTitle, setLessonTitle] = useState('');
  const [lessonContent, setLessonContent] = useState('');
  const [lessonResourceType, setLessonResourceType] = useState<'' | 'video' | 'document'>('');
  const [lessonResourceUrl, setLessonResourceUrl] = useState('');
  const [editingLessonId, setEditingLessonId] = useState('');
  const [lessonEditTitle, setLessonEditTitle] = useState('');
  const [lessonEditContent, setLessonEditContent] = useState('');
  const [lessonEditResourceType, setLessonEditResourceType] = useState<'' | 'video' | 'document'>('');
  const [lessonEditResourceUrl, setLessonEditResourceUrl] = useState('');
  const [lessonPrerequisiteIds, setLessonPrerequisiteIds] = useState<string[]>([]);
  const [lessonEditStatus, setLessonEditStatus] = useState<'active' | 'archived'>('active');
  const [enrollStudentId, setEnrollStudentId] = useState('');
  const [linkStudentId, setLinkStudentId] = useState('');
  const [linkMemberId, setLinkMemberId] = useState('');
  const [progressStudentId, setProgressStudentId] = useState('');
  const [assignmentTitle, setAssignmentTitle] = useState('');
  const [assignmentInstructions, setAssignmentInstructions] = useState('');
  const [assignmentPoints, setAssignmentPoints] = useState('100');
  const [assignmentDueAt, setAssignmentDueAt] = useState('');
  const [assignmentResourceFiles, setAssignmentResourceFiles] = useState<File[]>([]);
  const [submissionFiles, setSubmissionFiles] = useState<Record<string, File[]>>({});
  const [questionPrompt, setQuestionPrompt] = useState('');
  const [questionOptions, setQuestionOptions] = useState(['', '', '', '']);
  const [correctOption, setCorrectOption] = useState(0);
  const [correctOptions, setCorrectOptions] = useState<number[]>([0]);
  const [questionType, setQuestionType] = useState<BankQuestion['type']>('single_choice');
  const [questionTopic, setQuestionTopic] = useState('');
  const [questionDifficulty, setQuestionDifficulty] = useState(2);
  const [gradingRubric, setGradingRubric] = useState<GradingRubricCriterion[]>(() => [newRubricCriterion('Accuracy', 50), newRubricCriterion('Explanation', 50)]);
  const [questionBankSearch, setQuestionBankSearch] = useState('');
  const [questionBankTopic, setQuestionBankTopic] = useState('all');
  const [questionBankDifficulty, setQuestionBankDifficulty] = useState('all');
  const [assessmentTitle, setAssessmentTitle] = useState('');
  const [assessmentDuration, setAssessmentDuration] = useState('20');
  const [assessmentAttemptsAllowed, setAssessmentAttemptsAllowed] = useState('1');
  const [assessmentOpensAt, setAssessmentOpensAt] = useState('');
  const [assessmentClosesAt, setAssessmentClosesAt] = useState('');
  const [assessmentRandomizeQuestions, setAssessmentRandomizeQuestions] = useState(false);
  const [assessmentRandomizedQuestionCount, setAssessmentRandomizedQuestionCount] = useState('');
  const [assessmentQuestionIds, setAssessmentQuestionIds] = useState<string[]>([]);
  const [assessmentPoints, setAssessmentPoints] = useState<Record<string, string>>({});
  const shortAnswerSaveTimers = useRef<Record<string, number>>({});
  const questionBankTopics = useMemo(() => [...new Set(bankQuestions.map((question) => question.topic).filter((topic): topic is string => Boolean(topic)))].sort((left, right) => left.localeCompare(right)), [bankQuestions]);
  const filteredBankQuestions = useMemo(() => {
    const search = questionBankSearch.trim().toLocaleLowerCase();
    return bankQuestions.filter((question) => (!search || question.prompt.toLocaleLowerCase().includes(search))
      && (questionBankTopic === 'all' || question.topic === questionBankTopic)
      && (questionBankDifficulty === 'all' || String(question.difficulty) === questionBankDifficulty));
  }, [bankQuestions, questionBankSearch, questionBankTopic, questionBankDifficulty]);
  useEffect(() => {
    setQuestionBankSearch('');
    setQuestionBankTopic('all');
    setQuestionBankDifficulty('all');
  }, [selectedCourseId]);
  const [selectedAssignmentId, setSelectedAssignmentId] = useState('');
  const [submissionDrafts, setSubmissionDrafts] = useState<Record<string, string>>({});
  const [gradingScores, setGradingScores] = useState<Record<string, string>>({});
  const [gradingFeedback, setGradingFeedback] = useState<Record<string, string>>({});
  const canManageCourses = hasPermission(organization, 'courses.manage');
  const canManageAssessments = hasPermission(organization, 'assessments.manage');
  const canViewResults = hasPermission(organization, 'results.view');
  const canTakeAssessments = hasPermission(organization, 'assessments.take');
  const canManageAcademic = hasPermission(organization, 'academic.manage');
  const selectedCourse = courses.find((course) => course.id === selectedCourseId);

  useEffect(() => {
    if (!selectedCourse) return;
    setCourseEditTitle(selectedCourse.title); setCourseEditDescription(selectedCourse.description ?? '');
    setCourseEditPrice((selectedCourse.price_paise / 100).toFixed(2));
    setCourseEditStatus(selectedCourse.status);
    setCourseEditPublic(selectedCourse.is_public);
    setCourseInstructorIds((selectedCourse.instructors ?? []).map((instructor) => instructor.id));
  }, [selectedCourse?.id, selectedCourse?.title, selectedCourse?.description, selectedCourse?.status, selectedCourse?.is_public, selectedCourse?.price_paise, selectedCourse?.instructors]);

  async function refreshCourses() {
    setLoading(true); setError('');
    try {
      const [nextCourses, nextStudents, nextCandidates] = await Promise.all([
        listCourses(token, organization.id),
        canManageCourses ? listStudents(token, organization.id) : Promise.resolve([]),
        canManageAcademic ? listStudentMemberCandidates(token, organization.id) : Promise.resolve([]),
      ]);
      setCourses(nextCourses); setStudents(nextStudents); setStudentCandidates(nextCandidates);
      setInstructorCandidates(canManageAcademic ? await listCourseInstructorCandidates(token, organization.id) : []);
      setSelectedCourseId((current) => nextCourses.some((course) => course.id === current) ? current : nextCourses[0]?.id ?? '');
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not load courses.'); }
    finally { setLoading(false); }
  }

  useEffect(() => { void refreshCourses(); }, [token, organization.id]);

  useEffect(() => {
    if (!selectedCourseId) { setLessons([]); setAssignments([]); setEnrollments([]); setProgress([]); setSubmissions([]); setBankQuestions([]); setAssessments([]); return; }
    let cancelled = false;
    Promise.all([
      listCourseLessons(token, organization.id, selectedCourseId),
      listCourseAssignments(token, organization.id, selectedCourseId),
      canManageAssessments ? listBankQuestions(token, organization.id, selectedCourseId) : Promise.resolve([]),
      listAssessments(token, organization.id, selectedCourseId),
      canManageCourses ? listCourseEnrollments(token, organization.id, selectedCourseId) : Promise.resolve([]),
      canManageCourses ? listCourseProgress(token, organization.id, selectedCourseId) : Promise.resolve([]),
    ]).then(([lessonData, assignmentData, questionData, assessmentData, enrollmentData, progressData]) => {
      if (cancelled) return;
      setLessons(lessonData as CourseLesson[]);
      const nextAssignments = assignmentData as CourseAssignment[];
      setAssignments(nextAssignments);
      setBankQuestions(questionData as BankQuestion[]);
      setAssessments(assessmentData as LearningAssessment[]);
      const availableQuestions = questionData as BankQuestion[];
      setAssessmentQuestionIds((current) => {
        const retained = current.filter((id) => availableQuestions.some((question) => question.id === id));
        return retained.length ? retained : availableQuestions.slice(0, 1).map((question) => question.id);
      });
      setAssessmentPoints((current) => Object.fromEntries(availableQuestions.map((question) => [question.id, current[question.id] ?? '1'])));
      setAssessmentResults(Object.fromEntries((assessmentData as LearningAssessment[]).map((assessment) => [assessment.id, assessment.my_attempts ?? []])));
      setEnrollments((enrollmentData as CourseEnrollment[] | undefined) ?? []);
      setProgress((progressData as CourseProgress[] | undefined) ?? []);
      setSelectedAssignmentId((current) => nextAssignments.some((assignment) => assignment.id === current) ? current : nextAssignments[0]?.id ?? '');
    }).catch((cause) => { if (!cancelled) setError(cause instanceof Error ? cause.message : 'Could not load course details.'); });
    return () => { cancelled = true; };
  }, [token, organization.id, selectedCourseId, canManageCourses, canManageAssessments]);

  useEffect(() => {
    if (!canManageCourses || !selectedCourseId || !selectedAssignmentId) { setSubmissions([]); return; }
    let cancelled = false;
    listAssignmentSubmissions(token, organization.id, selectedCourseId, selectedAssignmentId)
      .then((items) => { if (!cancelled) setSubmissions(items); })
      .catch((cause) => { if (!cancelled) setError(cause instanceof Error ? cause.message : 'Could not load assignment submissions.'); });
    return () => { cancelled = true; };
  }, [token, organization.id, selectedCourseId, selectedAssignmentId, canManageCourses]);

  useEffect(() => {
    if (!canManageAssessments || !selectedCourseId || !assessments.length) return;
    let cancelled = false;
    Promise.all(assessments.map(async (assessment) => [assessment.id, await listAssessmentAttempts(token, organization.id, selectedCourseId, assessment.id)] as const))
      .then((items) => { if (!cancelled) setAssessmentResults(Object.fromEntries(items)); })
      .catch((cause) => { if (!cancelled) setError(cause instanceof Error ? cause.message : 'Could not load quiz results.'); });
    return () => { cancelled = true; };
  }, [token, organization.id, selectedCourseId, assessments, canManageAssessments]);

  async function refreshDetails() {
    if (!selectedCourseId) return;
    const [nextLessons, nextAssignments, nextQuestions, nextAssessments, nextEnrollments, nextProgress] = await Promise.all([
      listCourseLessons(token, organization.id, selectedCourseId),
      listCourseAssignments(token, organization.id, selectedCourseId),
      canManageAssessments ? listBankQuestions(token, organization.id, selectedCourseId) : Promise.resolve([]),
      listAssessments(token, organization.id, selectedCourseId),
      canManageCourses ? listCourseEnrollments(token, organization.id, selectedCourseId) : Promise.resolve([]),
      canManageCourses ? listCourseProgress(token, organization.id, selectedCourseId) : Promise.resolve([]),
    ]);
    setLessons(nextLessons); setAssignments(nextAssignments); setEnrollments(nextEnrollments); setProgress(nextProgress);
    setBankQuestions(nextQuestions); setAssessments(nextAssessments); setAssessmentResults(Object.fromEntries(nextAssessments.map((assessment) => [assessment.id, assessment.my_attempts ?? []])));
    if (!nextAssignments.some((assignment) => assignment.id === selectedAssignmentId)) setSelectedAssignmentId(nextAssignments[0]?.id ?? '');
    if (canManageCourses && selectedAssignmentId) setSubmissions(await listAssignmentSubmissions(token, organization.id, selectedCourseId, selectedAssignmentId));
    await refreshCourses();
  }

  async function submitCourse(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setSaving(true); setError('');
    try {
      const course = await createCourse(token, organization.id, { code: courseCode.trim(), title: courseTitle.trim(), ...(courseDescription.trim() ? { description: courseDescription.trim() } : {}), price_paise: Math.round(Number(coursePrice || 0) * 100) });
      setCourseCode(''); setCourseTitle(''); setCourseDescription(''); setCoursePrice('0'); await refreshCourses(); setSelectedCourseId(course.id);
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not create course.'); }
    finally { setSaving(false); }
  }

  async function submitLesson(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!selectedCourseId) return;
    setSaving(true); setError('');
    try { await createCourseLesson(token, organization.id, selectedCourseId, { title: lessonTitle.trim(), ...(lessonContent.trim() ? { content: lessonContent.trim() } : {}), ...(lessonResourceType && lessonResourceUrl.trim() ? { resource_type: lessonResourceType, resource_url: lessonResourceUrl.trim() } : {}) }); setLessonTitle(''); setLessonContent(''); setLessonResourceType(''); setLessonResourceUrl(''); await refreshDetails(); }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not add lesson.'); }
    finally { setSaving(false); }
  }

  async function submitCourseEdit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!selectedCourseId || !courseEditTitle.trim()) return;
    setSaving(true); setError('');
    try {
      const updated = await updateCourse(token, organization.id, selectedCourseId, { title: courseEditTitle.trim(), description: courseEditDescription.trim(), status: courseEditStatus, is_public: courseEditPublic, price_paise: Math.round(Number(courseEditPrice || 0) * 100) });
      setCourses((items) => items.map((item) => item.id === updated.id ? updated : item));
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not update this course.'); }
    finally { setSaving(false); }
  }

  async function submitInstructorAssignment(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!selectedCourseId) return;
    setSaving(true); setError('');
    try {
      const updated = await updateCourseInstructors(token, organization.id, selectedCourseId, courseInstructorIds);
      setCourses((items) => items.map((item) => item.id === updated.id ? updated : item));
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not update course instructors.'); }
    finally { setSaving(false); }
  }

  async function submitLessonEdit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!selectedCourseId || !editingLessonId || !lessonEditTitle.trim()) return;
    setSaving(true); setError('');
    try {
      await updateCourseLesson(token, organization.id, selectedCourseId, editingLessonId, { title: lessonEditTitle.trim(), content: lessonEditContent.trim(), resource_type: lessonEditResourceType || null, resource_url: lessonEditResourceUrl.trim() || null, prerequisite_ids: lessonPrerequisiteIds, status: lessonEditStatus });
      setEditingLessonId(''); await refreshDetails();
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not update this lesson.'); }
    finally { setSaving(false); }
  }

  async function submitEnrollment(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!selectedCourseId || !enrollStudentId) return;
    setSaving(true); setError('');
    try { await enrollInCourse(token, organization.id, selectedCourseId, enrollStudentId); setEnrollStudentId(''); await refreshDetails(); }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not enroll learner.'); }
    finally { setSaving(false); }
  }

  async function submitStudentLink(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!linkStudentId) return;
    setSaving(true); setError('');
    try { await linkStudentMember(token, organization.id, linkStudentId, linkMemberId ? Number(linkMemberId) : null); setLinkMemberId(''); await refreshCourses(); }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not link learner account.'); }
    finally { setSaving(false); }
  }

  async function recordProgress(lessonId: string, status: 'started' | 'completed', studentId?: string) {
    if (!selectedCourseId) return;
    setSaving(true); setError('');
    try { await updateLessonProgress(token, organization.id, selectedCourseId, lessonId, status, studentId); await refreshDetails(); }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not save lesson progress.'); }
    finally { setSaving(false); }
  }

  async function submitAssignmentForm(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!selectedCourseId) return;
    const due = assignmentDueAt ? workspaceDateTimeToIso(assignmentDueAt, organization.timezone) : undefined;
    if (assignmentDueAt && !due) { setError(`Choose a valid due time in the workspace timezone (${organization.timezone}).`); return; }
    setSaving(true); setError('');
    try {
      const files = assignmentResourceFiles;
      const assignment = await createCourseAssignment(token, organization.id, selectedCourseId, {
        title: assignmentTitle.trim(), ...(assignmentInstructions.trim() ? { instructions: assignmentInstructions.trim() } : {}),
        max_points: Number(assignmentPoints), ...(due ? { due_at: due } : {}),
      });
      setAssignmentTitle(''); setAssignmentInstructions(''); setAssignmentPoints('100'); setAssignmentDueAt(''); setAssignmentResourceFiles([]); await refreshDetails();
      if (files.length) {
        try { await uploadAssignmentResources(token, organization.id, selectedCourseId, assignment.id, files); await refreshDetails(); }
        catch (cause) { await refreshDetails(); setError(`Assignment published, but its files did not upload. Add them from the assignment card. ${cause instanceof Error ? cause.message : ''}`); }
      }
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not create assignment.'); }
    finally { setSaving(false); }
  }

  async function addAssignmentResources(assignmentId: string, files: File[]) {
    if (!selectedCourseId || !files.length) return;
    setSaving(true); setError('');
    try { await uploadAssignmentResources(token, organization.id, selectedCourseId, assignmentId, files); await refreshDetails(); }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not upload assignment files.'); }
    finally { setSaving(false); }
  }

  async function submitLearnerWork(assignmentId: string) {
    const text = submissionDrafts[assignmentId]?.trim() ?? '';
    const files = submissionFiles[assignmentId] ?? [];
    if (!text && !files.length) return;
    setSaving(true); setError('');
    try { await submitCourseAssignment(token, organization.id, assignmentId, text, files); setSubmissionDrafts((drafts) => ({ ...drafts, [assignmentId]: '' })); setSubmissionFiles((current) => ({ ...current, [assignmentId]: [] })); await refreshDetails(); }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not submit assignment.'); }
    finally { setSaving(false); }
  }

  async function gradeWork(event: FormEvent<HTMLFormElement>, assignmentId: string, submissionId: string) {
    event.preventDefault(); const score = Number(gradingScores[submissionId]);
    if (!selectedCourseId || !Number.isFinite(score)) return;
    setSaving(true); setError('');
    try { await gradeCourseSubmission(token, organization.id, selectedCourseId, assignmentId, submissionId, { score, ...(gradingFeedback[submissionId]?.trim() ? { feedback: gradingFeedback[submissionId].trim() } : {}) }); await refreshDetails(); }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not save grade.'); }
    finally { setSaving(false); }
  }

  async function saveQuestion(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!selectedCourseId) return;
    setSaving(true); setError('');
    try {
      const question = await createBankQuestion(token, organization.id, selectedCourseId, {
        prompt: questionPrompt.trim(), ...(questionTopic.trim() ? { topic: questionTopic.trim() } : {}),
        difficulty: questionDifficulty, type: questionType,
        ...(questionType === 'short_answer' && gradingRubric.some((criterion) => criterion.label.trim()) ? { grading_rubric: gradingRubric.map((criterion) => ({ ...criterion, label: criterion.label.trim() })) } : {}),
        options: questionType === 'short_answer' ? [] : questionOptions.map((text, index) => ({ text: text.trim(), is_correct: questionType === 'single_choice' ? index === correctOption : correctOptions.includes(index) })),
      });
      setBankQuestions((current) => [...current, question]); setAssessmentQuestionIds((current) => [...current, question.id]);
      setAssessmentPoints((current) => ({ ...current, [question.id]: '1' }));
      setQuestionPrompt(''); setQuestionOptions(['', '', '', '']); setQuestionTopic(''); setCorrectOption(0); setCorrectOptions([0]);
      setGradingRubric([newRubricCriterion('Accuracy', 50), newRubricCriterion('Explanation', 50)]);
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not save question.'); }
    finally { setSaving(false); }
  }

  async function publishAssessment(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!selectedCourseId || !assessmentQuestionIds.length) return;
    const opensAt = assessmentOpensAt ? workspaceDateTimeToIso(assessmentOpensAt, organization.timezone) : null;
    const closesAt = assessmentClosesAt ? workspaceDateTimeToIso(assessmentClosesAt, organization.timezone) : null;
    if ((assessmentOpensAt && !opensAt) || (assessmentClosesAt && !closesAt)) {
      setError(`Enter a valid date and time in the workspace timezone (${organization.timezone}).`);
      return;
    }
    if (opensAt && closesAt && new Date(closesAt).getTime() <= new Date(opensAt).getTime()) {
      setError('The closing time must be later than the opening time.');
      return;
    }
    if (assessmentRandomizedQuestionCount && Number(assessmentRandomizedQuestionCount) > assessmentQuestionIds.length) {
      setError(`Questions per attempt cannot exceed the ${assessmentQuestionIds.length} selected pool questions.`);
      return;
    }
    setSaving(true); setError('');
    try {
      await createAssessment(token, organization.id, selectedCourseId, {
        title: assessmentTitle.trim(), duration_minutes: Number(assessmentDuration), attempts_allowed: Number(assessmentAttemptsAllowed),
        randomize_questions: assessmentRandomizeQuestions,
        ...(opensAt ? { opens_at: opensAt } : {}),
        ...(closesAt ? { closes_at: closesAt } : {}),
        ...(assessmentRandomizedQuestionCount ? { randomized_question_count: Number(assessmentRandomizedQuestionCount) } : {}),
        questions: bankQuestions.filter((question) => assessmentQuestionIds.includes(question.id))
          .map((question) => ({ question_id: question.id, points: Number(assessmentPoints[question.id] ?? '1') })),
      });
      setAssessmentTitle(''); setAssessmentAttemptsAllowed('1'); setAssessmentRandomizedQuestionCount(''); setAssessmentOpensAt(''); setAssessmentClosesAt(''); await refreshDetails();
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not publish assessment.'); }
    finally { setSaving(false); }
  }

  async function beginAssessment(assessment: LearningAssessment) {
    setSaving(true); setError('');
    try { const attempt = await startAssessmentAttempt(token, organization.id, assessment.id); setActiveAttempt({ assessmentId: assessment.id, attempt }); }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not start assessment.'); }
    finally { setSaving(false); }
  }

  async function answerAssessment(questionId: string, optionIds: string[], responseText?: string) {
    if (!activeAttempt) return;
    setSaving(true); setError('');
    try {
      await saveAssessmentAnswer(token, organization.id, activeAttempt.assessmentId, activeAttempt.attempt.id, questionId, optionIds, responseText);
      setActiveAttempt((current) => current ? { ...current, attempt: { ...current.attempt, questions: current.attempt.questions.map((question) => question.id === questionId ? { ...question, selected_option_ids: optionIds, response_text: responseText ?? question.response_text } : question) } } : null);
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not save answer.'); }
    finally { setSaving(false); }
  }

  function updateShortAnswerDraft(questionId: string, responseText: string) {
    setActiveAttempt((current) => current ? {
      ...current,
      attempt: {
        ...current.attempt,
        questions: current.attempt.questions.map((question) => question.id === questionId ? { ...question, response_text: responseText } : question),
      },
    } : null);
    window.clearTimeout(shortAnswerSaveTimers.current[questionId]);
    shortAnswerSaveTimers.current[questionId] = window.setTimeout(() => {
      void answerAssessment(questionId, [], responseText);
      delete shortAnswerSaveTimers.current[questionId];
    }, 650);
  }

  async function finishAssessment() {
    if (!activeAttempt) return;
    Object.values(shortAnswerSaveTimers.current).forEach((timer) => window.clearTimeout(timer));
    shortAnswerSaveTimers.current = {};
    setSaving(true); setError('');
    try {
      for (const question of activeAttempt.attempt.questions.filter((item) => item.type === 'short_answer')) {
        await saveAssessmentAnswer(token, organization.id, activeAttempt.assessmentId, activeAttempt.attempt.id, question.id, [], question.response_text ?? '');
      }
      const result = await submitAssessmentAttempt(token, organization.id, activeAttempt.assessmentId, activeAttempt.attempt.id);
      setAssessmentResults((current) => ({ ...current, [activeAttempt.assessmentId]: [...(current[activeAttempt.assessmentId] ?? []), result] }));
      setActiveAttempt(null);
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not submit assessment.'); }
    finally { setSaving(false); }
  }

  return <section className="panel academic-panel learning-panel">
    <div className="panel-heading"><div><div className="eyebrow">LEARNING LIBRARY</div><h2>Courses and lessons</h2><p>Build course lessons, enroll learners, and follow lesson completion.</p></div></div>
    {error && <div className="academic-error" role="alert">{error}</div>}
    {canManageCourses && <div className="academic-forms learning-forms">
      <form className="academic-form" onSubmit={(event) => void submitCourse(event)}><strong>Create course</strong><label>Course title<input required maxLength={160} value={courseTitle} onChange={(event) => setCourseTitle(event.target.value)} placeholder="e.g. Foundations of Biology" /></label><label>Course code<input required maxLength={64} value={courseCode} onChange={(event) => setCourseCode(event.target.value)} placeholder="BIO-101" /></label><label>Description<textarea maxLength={5000} value={courseDescription} onChange={(event) => setCourseDescription(event.target.value)} placeholder="What learners will study" /></label><label>Course price (INR)<input type="number" min="0" max="10000000" step="0.01" value={coursePrice} onChange={(event) => setCoursePrice(event.target.value)} /></label><small className="form-hint">Enter in INR. Paid course checkout is not available yet.</small><button className="task-submit" disabled={saving || !courseTitle.trim() || !courseCode.trim()}>{saving ? 'Saving…' : 'Create course'}</button></form>
      {canManageAcademic && <form className="academic-form" onSubmit={(event) => void submitStudentLink(event)}><strong>Link learner account</strong><label>Student profile<select required value={linkStudentId} onChange={(event) => setLinkStudentId(event.target.value)}><option value="">Choose a student…</option>{students.map((student) => <option key={student.id} value={student.id}>{student.name} · {student.student_code}</option>)}</select></label><label>Workspace student account<select value={linkMemberId} onChange={(event) => setLinkMemberId(event.target.value)}><option value="">No account linked</option>{studentCandidates.map((candidate) => <option key={candidate.id} value={candidate.id}>{candidate.name}</option>)}</select></label><button className="task-submit" disabled={saving || !linkStudentId}>{saving ? 'Saving…' : 'Save account link'}</button><small className="form-hint">Linking lets a learner view enrolled courses and save their own lesson progress.</small></form>}
    </div>}
    {loading ? <div className="task-message">Loading courses…</div> : courses.length ? <div className="course-layout"><div className="course-list">{courses.map((course) => <button type="button" key={course.id} className={`course-choice ${course.id === selectedCourseId ? 'course-choice-active' : ''}`} onClick={() => setSelectedCourseId(course.id)}><span><strong>{course.title}</strong><small>{course.code}{course.subject ? ` · ${course.subject}` : ''}{course.status === 'draft' ? ' · draft' : ''}{course.instructors?.length ? ` · taught by ${course.instructors.map((teacher) => teacher.name).join(', ')}` : ''}</small></span><small>{course.lessons_count} lessons</small></button>)}</div>{selectedCourse && <div className="course-detail"><div className="course-detail-heading"><div><div className="eyebrow">{selectedCourse.code}{selectedCourse.academic_year ? ` · ${selectedCourse.academic_year}` : ''}</div><h3>{selectedCourse.title}</h3>{selectedCourse.description && <p>{selectedCourse.description}</p>}</div></div>
      {canManageCourses && <form className="academic-form course-edit-form" onSubmit={(event) => void submitCourseEdit(event)}><strong>Edit course</strong><label>Title<input required maxLength={160} value={courseEditTitle} onChange={(event) => setCourseEditTitle(event.target.value)} /></label><label>Description<textarea maxLength={5000} value={courseEditDescription} onChange={(event) => setCourseEditDescription(event.target.value)} /></label><label>Visibility<select value={courseEditStatus} onChange={(event) => setCourseEditStatus(event.target.value as 'active' | 'draft')}><option value="active">Published</option><option value="draft">Draft</option></select></label><label>Course discovery<select value={courseEditPublic ? "public" : "private"} onChange={(event) => setCourseEditPublic(event.target.value === "public")}><option value="private">Private to this workspace</option><option value="public">Show in public catalog</option></select></label><label>Course price (INR)<input type="number" min="0" max="10000000" step="0.01" value={courseEditPrice} onChange={(event) => setCourseEditPrice(event.target.value)} /></label><small className="form-hint">Enter in INR. Paid course checkout is not available yet.</small><button className="task-submit" disabled={saving || !courseEditTitle.trim()}>{saving ? 'Saving…' : 'Save course'}</button><small className="form-hint">Draft courses stay visible to staff and hidden from learners. Progress and submissions are preserved.</small></form>}
      {canManageAcademic && selectedCourse && <form className="academic-form course-edit-form" onSubmit={(event) => void submitInstructorAssignment(event)}><strong>Course instructors</strong><label>Assigned teachers<select multiple size={Math.min(5, Math.max(2, instructorCandidates.length))} value={courseInstructorIds.map(String)} onChange={(event) => setCourseInstructorIds(Array.from(event.currentTarget.selectedOptions, (option) => Number(option.value)))}>{instructorCandidates.map((teacher) => <option key={teacher.id} value={teacher.id}>{teacher.name}</option>)}</select></label><button className="task-submit" disabled={saving}>{saving ? 'Saving…' : 'Save instructor assignments'}</button><small className="form-hint">Teachers assigned here can manage this course and its enrolled learners. Hold Ctrl or Command to select more than one teacher.</small></form>}
      {canManageCourses && <div className="academic-forms learning-forms course-actions"><form className="academic-form" onSubmit={(event) => void submitLesson(event)}><strong>Add lesson</strong><label>Lesson title<input required maxLength={180} value={lessonTitle} onChange={(event) => setLessonTitle(event.target.value)} /></label><label>Lesson content<textarea maxLength={20000} value={lessonContent} onChange={(event) => setLessonContent(event.target.value)} placeholder="Optional learning notes" /></label><label>External resource<select value={lessonResourceType} onChange={(event) => setLessonResourceType(event.target.value as '' | 'video' | 'document')}><option value="">No resource</option><option value="video">Video link</option><option value="document">Document link</option></select></label>{lessonResourceType && <label>Secure resource URL<input type="url" required pattern="https://.*" value={lessonResourceUrl} onChange={(event) => setLessonResourceUrl(event.target.value)} placeholder="https://…" /></label>}<button className="task-submit" disabled={saving || !lessonTitle.trim()}>{saving ? 'Saving…' : 'Add lesson'}</button></form><form className="academic-form" onSubmit={(event) => void submitEnrollment(event)}><strong>Enroll learner</strong><label>Student<select required value={enrollStudentId} onChange={(event) => setEnrollStudentId(event.target.value)}><option value="">Choose a learner…</option>{students.map((student) => <option key={student.id} value={student.id}>{student.name} · {student.student_code}</option>)}</select></label><button className="task-submit" disabled={saving || !enrollStudentId}>{saving ? 'Saving…' : 'Enroll'}</button><small className="form-hint">{enrollments.length} learners enrolled.</small></form></div>}
      <div className="course-lesson-list">{lessons.length ? lessons.map((lesson) => <article className={`course-lesson ${lesson.status === 'archived' ? 'course-lesson-archived' : ''}`} key={lesson.id}><div className="course-lesson-number">{lesson.position.toString().padStart(2, '0')}</div><div className="course-lesson-copy"><strong>{lesson.title}{lesson.status === 'archived' && <span className="lesson-archived-label"> · archived</span>}</strong>{lesson.content && <p>{lesson.content}</p>}{lesson.resource_url && <a className="lesson-resource-link" href={lesson.resource_url} target="_blank" rel="noreferrer">Open {lesson.resource_type === 'video' ? 'video' : 'document'} resource</a>}{!canManageCourses && lesson.prerequisites.length > 0 && <small>Complete first: {lesson.prerequisites.map((item) => item.title).join(', ')}</small>}{!canManageCourses && <small>Status: {lesson.my_progress?.replace('_', ' ') ?? 'not started'}</small>}{canManageCourses && editingLessonId === lesson.id && <form className="academic-form lesson-edit-form" onSubmit={(event) => void submitLessonEdit(event)}><label>Title<input required maxLength={180} value={lessonEditTitle} onChange={(event) => setLessonEditTitle(event.target.value)} /></label><label>Content<textarea maxLength={20000} value={lessonEditContent} onChange={(event) => setLessonEditContent(event.target.value)} /></label><label>External resource<select value={lessonEditResourceType} onChange={(event) => setLessonEditResourceType(event.target.value as '' | 'video' | 'document')}><option value="">No resource</option><option value="video">Video link</option><option value="document">Document link</option></select></label>{lessonEditResourceType && <label>Secure resource URL<input type="url" required pattern="https://.*" value={lessonEditResourceUrl} onChange={(event) => setLessonEditResourceUrl(event.target.value)} /></label>}<label>Prerequisite lessons<select multiple size={Math.min(4, Math.max(2, lessons.filter((item) => item.id !== editingLessonId && item.status === 'active' && item.position < (lessons.find((lesson) => lesson.id === editingLessonId)?.position ?? 0)).length))} value={lessonPrerequisiteIds} onChange={(event) => setLessonPrerequisiteIds(Array.from(event.currentTarget.selectedOptions, (option) => option.value))}>{lessons.filter((item) => item.id !== editingLessonId && item.status === 'active' && item.position < (lessons.find((lesson) => lesson.id === editingLessonId)?.position ?? 0)).map((item) => <option key={item.id} value={item.id}>{item.title}</option>)}</select></label><label>Visibility<select value={lessonEditStatus} onChange={(event) => setLessonEditStatus(event.target.value as 'active' | 'archived')}><option value="active">Available to learners</option><option value="archived">Archived</option></select></label><div className="lesson-edit-actions"><button className="task-submit" disabled={saving || !lessonEditTitle.trim()}>{saving ? 'Saving…' : 'Save lesson'}</button><button type="button" className="task-cancel" onClick={() => setEditingLessonId('')}>Cancel</button></div></form>}</div>{canManageCourses ? <div className="lesson-progress-actions">{editingLessonId !== lesson.id && <button type="button" className="task-cancel" onClick={() => { setEditingLessonId(lesson.id); setLessonEditTitle(lesson.title); setLessonEditContent(lesson.content ?? ''); setLessonEditResourceType(lesson.resource_type ?? ''); setLessonEditResourceUrl(lesson.resource_url ?? ''); setLessonPrerequisiteIds(lesson.prerequisites.map((item) => item.id)); setLessonEditStatus(lesson.status); }}>Edit lesson</button>}{lesson.status === 'active' && progressStudentId && <button type="button" className="task-cancel" disabled={saving || !enrollments.some((item) => item.student_id === progressStudentId)} onClick={() => void recordProgress(lesson.id, 'completed', progressStudentId)}>Mark complete</button>}</div> : <div className="lesson-progress-actions">{lesson.my_progress !== 'completed' && <><button type="button" className="task-cancel" disabled={saving || !lesson.prerequisites_met} onClick={() => void recordProgress(lesson.id, 'started')}>Start</button><button type="button" className="task-submit" disabled={saving || !lesson.prerequisites_met} onClick={() => void recordProgress(lesson.id, 'completed')}>Complete</button></>}</div>}</article>) : <div className="academic-empty">No lessons in this course yet.</div>}</div>
      <div className="course-assignment-panel"><div className="course-progress-heading"><strong>Assignments</strong><span>{assignments.length} total</span></div>{canManageCourses && <form className="academic-form assignment-create-form" onSubmit={(event) => void submitAssignmentForm(event)}><strong>Create assignment</strong><label>Title<input required maxLength={180} value={assignmentTitle} onChange={(event) => setAssignmentTitle(event.target.value)} /></label><label>Instructions<textarea maxLength={10000} value={assignmentInstructions} onChange={(event) => setAssignmentInstructions(event.target.value)} /></label><div className="academic-form-row"><label>Points<input type="number" min="0.01" max="100000" step="0.01" required value={assignmentPoints} onChange={(event) => setAssignmentPoints(event.target.value)} /></label><label>Due date ({organization.timezone})<input type="datetime-local" value={assignmentDueAt} onChange={(event) => setAssignmentDueAt(event.target.value)} /></label></div><label>Resource files (PDF, Word, images, or text; up to 5 files)<input type="file" multiple accept=".pdf,.doc,.docx,.png,.jpg,.jpeg,.txt" onChange={(event) => setAssignmentResourceFiles(Array.from(event.target.files ?? []))} /></label>{assignmentResourceFiles.length > 0 && <small className="form-hint">{assignmentResourceFiles.length} file(s) selected · up to 10 MB each</small>}<button className="task-submit" disabled={saving || !assignmentTitle.trim()}>{saving ? 'Saving…' : 'Publish assignment'}</button></form>}
        {assignments.length ? <div className="assignment-list">{assignments.map((assignment) => {
          const mySubmission = assignment.my_submission;
          const isPastDue = Boolean(assignment.due_at && new Date(assignment.due_at).getTime() < Date.now());
          return <article className="assignment-card" key={assignment.id}><div className="assignment-card-heading"><div><strong>{assignment.title}</strong><small>{assignment.lesson_title ? `Lesson: ${assignment.lesson_title} · ` : ''}{assignment.max_points} points{assignment.due_at ? ` · Due ${new Date(assignment.due_at).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short', timeZone: organization.timezone })}` : ''}</small>{assignment.instructions && <p>{assignment.instructions}</p>}</div>{canManageCourses ? <button type="button" className="text-button" onClick={() => setSelectedAssignmentId(assignment.id)}>{selectedAssignmentId === assignment.id ? 'Reviewing' : `Review ${assignment.submissions_count ?? 0}`}</button> : <span className={`assignment-status ${mySubmission?.status ?? 'not-submitted'}`}>{mySubmission?.status ?? 'not submitted'}</span>}</div>
            <AssignmentAttachmentList token={token} organizationId={organization.id} courseId={selectedCourseId} assignmentId={assignment.id} attachments={assignment.resource_attachments} label="Assignment files" />{canManageCourses && <label className="assignment-file-picker">Add resource files<input type="file" multiple accept=".pdf,.doc,.docx,.png,.jpg,.jpeg,.txt" disabled={saving} onChange={(event) => { const files = Array.from(event.target.files ?? []); event.target.value = ""; if (files.length) void addAssignmentResources(assignment.id, files); }} /><small>Up to five files total · 10 MB each</small></label>}{mySubmission && <AssignmentAttachmentList token={token} organizationId={organization.id} courseId={selectedCourseId} assignmentId={assignment.id} attachments={mySubmission.attachments} label="Your submitted files" />}{!canManageCourses && <div className="student-submission"><textarea aria-label={`Submission for ${assignment.title}`} maxLength={50000} value={submissionDrafts[assignment.id] ?? mySubmission?.submission_text ?? ''} onChange={(event) => setSubmissionDrafts((drafts) => ({ ...drafts, [assignment.id]: event.target.value }))} placeholder="Write your submission here"/><label className="assignment-file-picker">Attach files<input type="file" multiple accept=".pdf,.doc,.docx,.png,.jpg,.jpeg,.txt" onChange={(event) => { const files = Array.from(event.target.files ?? []); event.target.value = ""; setSubmissionFiles((current) => ({ ...current, [assignment.id]: files })); }} /><small>{(submissionFiles[assignment.id] ?? []).length} file(s) selected · up to 5 files, 10 MB each</small></label><button className="task-submit" disabled={saving || isPastDue || (!(submissionDrafts[assignment.id] ?? mySubmission?.submission_text ?? '').trim() && !(submissionFiles[assignment.id]?.length))} onClick={() => void submitLearnerWork(assignment.id)}>{saving ? 'Sending…' : mySubmission ? 'Resubmit work' : 'Submit work'}</button>{isPastDue && <small>The submission deadline has passed.</small>}{mySubmission?.score !== null && mySubmission?.score !== undefined && <div className="submission-feedback"><strong>Score: {mySubmission.score} / {assignment.max_points}</strong>{mySubmission.feedback && <span>{mySubmission.feedback}</span>}</div>}</div>}
            {canManageCourses && selectedAssignmentId === assignment.id && <div className="submission-list">{submissions.length ? submissions.map((submission) => <form className="submission-card" key={submission.id} onSubmit={(event) => void gradeWork(event, assignment.id, submission.id)}><div><strong>{submission.student_name}</strong><small>{submission.student_code} · {submission.submitted_at ? new Date(submission.submitted_at).toLocaleString() : ''}</small><p>{submission.submission_text}</p><AssignmentAttachmentList token={token} organizationId={organization.id} courseId={selectedCourseId} assignmentId={assignment.id} attachments={submission.attachments} label="Submitted files" /></div><div className="submission-grade-fields"><label>Score for {submission.student_name} (out of {assignment.max_points} points)<input type="number" min="0" max={assignment.max_points} step="0.01" required value={gradingScores[submission.id] ?? (submission.score === null ? '' : String(submission.score))} onChange={(event) => setGradingScores((values) => ({ ...values, [submission.id]: event.target.value }))}/></label><label>Feedback for {submission.student_name}<textarea maxLength={5000} value={gradingFeedback[submission.id] ?? submission.feedback ?? ''} onChange={(event) => setGradingFeedback((values) => ({ ...values, [submission.id]: event.target.value }))}/></label><button className="task-submit" disabled={saving}>{saving ? 'Saving…' : submission.status === 'graded' ? 'Update grade' : 'Save grade'}</button></div></form>) : <div className="task-message">No learner submissions yet.</div>}</div>}
          </article>;
        })}</div> : <div className="academic-empty">No assignments in this course yet.</div>}
      </div>
      <div className="course-assignment-panel assessment-panel"><div className="course-progress-heading"><strong>Quizzes and exams</strong><span>{assessments.length} available</span></div>
        {canManageAssessments && <div className="assessment-editor">
          <form className="academic-form" onSubmit={(event) => void saveQuestion(event)}><strong>Add question</strong><label>Question<input required maxLength={5000} value={questionPrompt} onChange={(event) => setQuestionPrompt(event.target.value)} placeholder="e.g. Which organelles are found in plant cells?" /></label><div className="academic-form-row"><label>Answer type<select value={questionType} onChange={(event) => setQuestionType(event.target.value as BankQuestion['type'])}><option value="single_choice">Single choice</option><option value="multiple_select">Select all that apply</option><option value="short_answer">Short answer</option></select></label><label>Difficulty<select value={questionDifficulty} onChange={(event) => setQuestionDifficulty(Number(event.target.value))}>{[1, 2, 3, 4, 5].map((level) => <option key={level} value={level}>{level} · {['Very easy', 'Easy', 'Medium', 'Hard', 'Very hard'][level - 1]}</option>)}</select></label></div><label>Topic (optional)<input maxLength={120} value={questionTopic} onChange={(event) => setQuestionTopic(event.target.value)} placeholder="e.g. Cell biology" /></label>{questionType !== 'short_answer' && questionOptions.map((option, index) => <label key={index}>Option {index + 1}<input required maxLength={1000} value={option} onChange={(event) => setQuestionOptions((items) => items.map((item, itemIndex) => itemIndex === index ? event.target.value : item))} /></label>)}{questionType === 'single_choice' ? <label>Correct answer<select value={correctOption} onChange={(event) => setCorrectOption(Number(event.target.value))}>{questionOptions.map((_, index) => <option key={index} value={index}>Option {index + 1}</option>)}</select></label> : questionType === 'multiple_select' ? <fieldset className="answer-key-fieldset"><legend>Correct answers</legend>{questionOptions.map((_, index) => <label key={index}><input type="checkbox" checked={correctOptions.includes(index)} onChange={(event) => setCorrectOptions((current) => event.target.checked ? [...current, index] : current.filter((item) => item !== index))} />Option {index + 1}</label>)}</fieldset> : <><ShortAnswerRubricEditor criteria={gradingRubric} onChange={setGradingRubric} /><small className="form-hint">Learners will enter a written response for educator review.</small></>}<button className="task-submit" disabled={saving || !questionPrompt.trim() || (questionType === 'multiple_select' && correctOptions.length === 0) || (questionType === 'short_answer' && gradingRubric.some((criterion) => criterion.label.trim()) && (gradingRubric.some((criterion) => !criterion.label.trim()) || gradingRubric.reduce((sum, criterion) => sum + criterion.weight, 0) !== 100))}>{saving ? 'Saving…' : 'Add to question bank'}</button></form>
          <form className="academic-form" onSubmit={(event) => void publishAssessment(event)}><strong>Publish timed quiz</strong><label>Quiz title<input required maxLength={180} value={assessmentTitle} onChange={(event) => setAssessmentTitle(event.target.value)} placeholder="e.g. Cell structures" /></label><div className="question-bank-filters"><label>Search questions<input type="search" value={questionBankSearch} onChange={(event) => setQuestionBankSearch(event.target.value)} placeholder="Search question text" onKeyDown={(event) => { if (event.key === 'Enter') event.preventDefault(); }} /></label><label>Topic<select value={questionBankTopic} onChange={(event) => setQuestionBankTopic(event.target.value)}><option value="all">All topics</option>{questionBankTopics.map((topic) => <option key={topic} value={topic}>{topic}</option>)}</select></label><label>Difficulty<select value={questionBankDifficulty} onChange={(event) => setQuestionBankDifficulty(event.target.value)}><option value="all">All levels</option>{[1, 2, 3, 4, 5].map((level) => <option key={level} value={String(level)}>Level {level}</option>)}</select></label></div><div className="assessment-question-picker"><span className="form-hint">Select one or more questions and set points for each.</span>{filteredBankQuestions.map((question) => { const selected = assessmentQuestionIds.includes(question.id); return <article className="assessment-question-choice" key={question.id}><label><input type="checkbox" checked={selected} onChange={(event) => setAssessmentQuestionIds((current) => event.target.checked ? [...current, question.id] : current.filter((id) => id !== question.id))} /><span><strong>{question.prompt}</strong><small>{question.type === 'short_answer' ? 'Short answer' : question.type === 'multiple_select' ? 'Multiple-select' : 'Single choice'} · Level {question.difficulty}{question.topic ? ` · ${question.topic}` : ''}</small></span></label>{selected && <label className="assessment-question-points">Points<input type="number" min="0.01" max="100000" step="0.01" required value={assessmentPoints[question.id] ?? '1'} onChange={(event) => setAssessmentPoints((current) => ({ ...current, [question.id]: event.target.value }))} /></label>}</article>; })}{!filteredBankQuestions.length && <small className="form-hint">{bankQuestions.length ? "No questions match these filters." : "Add a question above before publishing a quiz."}</small>}</div><div className="academic-form-row"><label>Time limit (minutes)<input type="number" min="1" max="360" required value={assessmentDuration} onChange={(event) => setAssessmentDuration(event.target.value)} /></label><label>Attempts allowed<input type="number" min="1" max="10" required value={assessmentAttemptsAllowed} onChange={(event) => setAssessmentAttemptsAllowed(event.target.value)} /></label></div><div className="academic-form-row"><label>Opens at (optional, {organization.timezone})<input type="datetime-local" value={assessmentOpensAt} onChange={(event) => setAssessmentOpensAt(event.target.value)} /></label><label>Closes at (optional, {organization.timezone})<input type="datetime-local" min={assessmentOpensAt || undefined} value={assessmentClosesAt} onChange={(event) => setAssessmentClosesAt(event.target.value)} /></label></div><small className="form-hint">Times use the workspace timezone ({organization.timezone}). Learners can start between these times. Leave both blank for immediate access.</small><label><input type="checkbox" checked={assessmentRandomizeQuestions} onChange={(event) => setAssessmentRandomizeQuestions(event.target.checked)} /> Randomize question order for each attempt</label><button className="task-submit" disabled={saving || !assessmentQuestionIds.length || !assessmentQuestionIds.every((id) => Number(assessmentPoints[id] ?? '1') > 0) || !assessmentTitle.trim()}>{saving ? 'Publishing…' : 'Publish quiz'}</button><small className="form-hint">{assessmentRandomizeQuestions ? 'Each learner gets a stable randomized order when they start or resume.' : 'Questions are included in the order shown above.'} Correct answers remain private to educators.</small></form>
        </div>}
        {canManageAssessments && selectedCourseId && <Suspense fallback={<div className="task-message">Loading question import…</div>}><QuestionCsvImport token={token} organizationId={organization.id} courseId={selectedCourseId} onImported={(questions) => {
          setBankQuestions((current) => [...current, ...questions]);
          setAssessmentQuestionIds((current) => [...new Set([...current, ...questions.map((question) => question.id)])]);
          setAssessmentPoints((current) => ({ ...current, ...Object.fromEntries(questions.map((question) => [question.id, '1'])) }));
        }} /></Suspense>}
        {canManageAssessments && <label className="question-sampling-control">Questions per attempt (optional)<input type="number" min="1" max={assessmentQuestionIds.length || 1} value={assessmentRandomizedQuestionCount} onChange={(event) => setAssessmentRandomizedQuestionCount(event.target.value)} placeholder={`All ${assessmentQuestionIds.length || 'selected'} selected questions`} /><small>Choose a number to draw a random subset from the selected quiz pool on each new attempt. Leave blank to include the whole pool.</small></label>}
        {canManageAssessments && selectedCourseId && assessments.length > 0 && <Suspense fallback={<div className="task-message">Loading assessment analytics…</div>}><AssessmentAnalyticsPanel key={selectedCourseId} token={token} organizationId={organization.id} courseId={selectedCourseId} assessments={assessments} /></Suspense>}
      {!canManageAssessments && canViewResults && <Suspense fallback={<div className="task-message">Loading results…</div>}><AssessmentStudentResults token={token} organizationId={organization.id} assessments={assessments} attemptsByAssessment={assessmentResults} /></Suspense>}
        {activeAttempt ? <div className="assessment-take"><h4>{assessments.find((item) => item.id === activeAttempt.assessmentId)?.title ?? 'Assessment'}</h4>{activeAttempt.attempt.expires_at && <small>Time limit ends {new Date(activeAttempt.attempt.expires_at).toLocaleTimeString()}</small>}{activeAttempt.attempt.questions.map((question, index) => <fieldset key={question.id}><legend>{index + 1}. {question.prompt}</legend>{question.type === 'short_answer' && <label className="assessment-short-answer">Your response<textarea maxLength={50000} value={question.response_text ?? ''} onChange={(event) => updateShortAnswerDraft(question.id, event.target.value)} placeholder="Write your answer here" /><small>Your response saves automatically.</small></label>}{question.type === 'multiple_select' && <small className="assessment-answer-hint">Select all that apply. Your choices save automatically.</small>}{question.type !== 'short_answer' && question.options.map((option) => <label key={option.id}><input disabled={saving} type={question.type === 'multiple_select' ? 'checkbox' : 'radio'} name={question.id} checked={question.selected_option_ids.includes(option.id)} onChange={(event) => { const selected = question.type === 'multiple_select' ? (event.target.checked ? [...question.selected_option_ids, option.id] : question.selected_option_ids.filter((id) => id !== option.id)) : [option.id]; void answerAssessment(question.id, selected); }} />{option.text}</label>)}</fieldset>)}<button className="task-submit" disabled={saving} onClick={() => void finishAssessment()}>{saving ? 'Saving…' : 'Submit quiz'}</button></div> : assessments.length ? <div className="assessment-list">{assessments.map((assessment) => <article key={assessment.id} className="assignment-card"><div className="assignment-card-heading"><div><strong>{assessment.title}</strong><small>{assessment.question_count} question{assessment.question_count === 1 ? '' : 's'} · {assessment.duration_minutes ?? 'No'} minute limit</small>{(assessment.opens_at || assessment.closes_at) && <small>{assessment.opens_at ? `Opens ${new Date(assessment.opens_at).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short', timeZone: organization.timezone })}` : 'Available now'}{assessment.closes_at ? ` · Closes ${new Date(assessment.closes_at).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short', timeZone: organization.timezone })}` : ''}</small>}{assessment.instructions && <p>{assessment.instructions}</p>}</div>{canManageAssessments ? <span>{assessment.status}</span> : canTakeAssessments && <button type="button" className="task-submit" disabled={saving || ((assessmentResults[assessment.id] ?? []).every((item) => item.status !== 'in_progress') && ((assessment.opens_at !== null && new Date(assessment.opens_at).getTime() > Date.now()) || (assessment.closes_at !== null && new Date(assessment.closes_at).getTime() <= Date.now()) || (assessmentResults[assessment.id] ?? []).filter((item) => item.status !== 'in_progress').length >= assessment.attempts_allowed))} onClick={() => void beginAssessment(assessment)}>{(assessmentResults[assessment.id] ?? []).some((item) => item.status === 'in_progress') ? 'Resume quiz' : assessment.opens_at && new Date(assessment.opens_at).getTime() > Date.now() ? 'Opens later' : assessment.closes_at && new Date(assessment.closes_at).getTime() <= Date.now() ? 'Closed' : 'Start quiz'}</button>}</div>{!canManageAssessments && (assessmentResults[assessment.id] ?? []).map((result) => <small key={result.id} className="assessment-result">Attempt {result.attempt_number}: {result.status === 'in_progress' ? 'in progress' : result.review_status === 'pending' ? 'awaiting educator review' : `${result.score ?? 0} / ${result.max_points} points`}</small>)}{canManageAssessments && assessmentResults[assessment.id]?.map((result) => <div key={result.id} className="assessment-result">{result.student_name} ({result.student_code}) · Attempt {result.attempt_number} · {result.score ?? 0} / {result.max_points} · {result.status}</div>)}</article>)}</div> : <div className="academic-empty">{canManageAssessments ? 'Add a question to publish your first quiz.' : 'No quizzes are published for this course yet.'}</div>}
      </div>
      {canManageCourses && !!enrollments.length && <div className="course-progress-panel"><div className="course-progress-heading"><strong>Learner progress</strong><label>Update learner<select value={progressStudentId} onChange={(event) => setProgressStudentId(event.target.value)}><option value="">Select a learner…</option>{enrollments.map((enrollment) => <option key={enrollment.id} value={enrollment.student_id}>{enrollment.student_name} · {enrollment.student_code}</option>)}</select></label></div>{progress.map((item) => <div className="course-progress-row" key={item.student_id}><span><strong>{item.student_name}</strong><small>{item.student_code} · {item.completed_lessons}/{item.total_lessons} lessons</small></span><span>{item.completion_percent}%</span></div>)}</div>}
    </div>}</div> : <div className="academic-empty">{canManageCourses ? 'No courses yet. Create a course to add lessons and enroll learners.' : 'No courses are assigned to your learner account yet. Ask a teacher to link your account and enroll you.'}</div>}
  </section>;
}

function HelpCentrePanel({ onFeedback }: { onFeedback: () => void }) {
  const { t } = useLocale();
  const [query, setQuery] = useState('');
  const filtered = useMemo(() => {
    const helpArticles = [
      { question: t('help.q.addStudent'), answer: t('help.a.addStudent') },
      { question: t('help.q.attendance'), answer: t('help.a.attendance') },
      { question: t('help.q.courses'), answer: t('help.a.courses') },
      { question: t('help.q.invite'), answer: t('help.a.invite') },
      { question: t('help.q.ai'), answer: t('help.a.ai') },
      { question: t('help.q.reports'), answer: t('help.a.reports') },
    ];
    const normalized = query.trim().toLocaleLowerCase();
    return helpArticles.filter((item) => !normalized || `${item.question} ${item.answer}`.toLocaleLowerCase().includes(normalized));
  }, [query, t]);
  return <section className="panel academic-panel help-centre-panel"><div className="panel-heading"><div><div className="eyebrow">{t('help.eyebrow')}</div><h2>{t('help.title')}</h2><p>{t('help.description')}</p></div><button type="button" className="task-submit" onClick={onFeedback}>{t('help.sendFeedback')}</button></div><label className="help-search"><Search size={15} /><span className="sr-only">{t('help.searchLabel')}</span><input type="search" value={query} onChange={(event) => setQuery(event.target.value)} placeholder={t('help.searchPlaceholder')} /></label><div className="help-article-list">{filtered.length ? filtered.map((item) => <details className="help-article" key={item.question}><summary>{item.question}</summary><p>{item.answer}</p></details>) : <div className="academic-empty">{t('help.noMatches')}</div>}</div><div className="help-contact"><div><strong>{t('help.stillNeed')}</strong><span>{t('help.contactDescription')}</span></div><a className="task-cancel" href="mailto:support@paathnexus.com">{t('help.emailSupport')}</a></div></section>;
}

function SupportFeedbackPanel({ token, organization }: { token: string; organization: Organization }) {
  const { locale, t } = useLocale();
  const [category, setCategory] = useState<SupportFeedback['category']>('support');
  const [message, setMessage] = useState('');
  const [items, setItems] = useState<SupportFeedback[]>([]);
  const [myItems, setMyItems] = useState<SupportFeedback[]>([]);
  const [responseDrafts, setResponseDrafts] = useState<Record<string, string>>({});
  const [statusDrafts, setStatusDrafts] = useState<Record<string, SupportFeedback['status']>>({});
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [savingFeedbackId, setSavingFeedbackId] = useState('');
  const [error, setError] = useState('');
  const [listError, setListError] = useState('');
  const [success, setSuccess] = useState('');
  const canReview = hasPermission(organization, 'members.manage');

  useEffect(() => {
    let current = true;
    setLoading(true); setListError('');
    Promise.all([listMySupportFeedback(token, organization.id), canReview ? listSupportFeedback(token, organization.id) : Promise.resolve(null)])
      .then(([mine, workspace]) => {
        if (!current) return;
        setMyItems(mine);
        if (workspace) setItems(workspace);
      })
      .catch((cause) => { if (current) setListError(cause instanceof Error ? cause.message : t('feedback.loadWorkspaceError')); })
      .finally(() => { if (current) setLoading(false); });
    return () => { current = false; };
  }, [token, organization.id, canReview]);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setSaving(true); setError(''); setSuccess('');
    try {
      const created = await createSupportFeedback(token, organization.id, { category, message: message.trim() });
      setMessage(''); setSuccess(t('feedback.success'));
      setMyItems((current) => [created, ...current]);
      if (canReview) setItems((current) => [{ ...created, submitter_name: t('feedback.memberYou'), responder_name: null }, ...current]);
    } catch (cause) { setError(cause instanceof Error ? cause.message : t('feedback.saveError')); }
    finally { setSaving(false); }
  }

  async function saveManagerResponse(item: SupportFeedback) {
    setSavingFeedbackId(item.id); setListError('');
    try {
      const updated = await updateSupportFeedback(token, organization.id, item.id, {
        status: statusDrafts[item.id] ?? item.status,
        response: responseDrafts[item.id] ?? item.response,
      });
      setItems((current) => current.map((feedback) => feedback.id === updated.id ? updated : feedback));
      setMyItems((current) => current.map((feedback) => feedback.id === updated.id ? updated : feedback));
      setResponseDrafts((current) => ({ ...current, [updated.id]: updated.response ?? '' }));
      setStatusDrafts((current) => ({ ...current, [updated.id]: updated.status }));
    } catch (cause) { setListError(cause instanceof Error ? cause.message : t('feedback.updateError')); }
    finally { setSavingFeedbackId(''); }
  }

  const formatFeedbackDate = (value: string) => new Date(value).toLocaleString(locale === 'hi' ? 'hi-IN' : 'en-IN', { dateStyle: 'medium', timeStyle: 'short', timeZone: organization.timezone });
  const categoryKeys: Record<SupportFeedback['category'], TranslationKey> = { support: 'feedback.support', bug: 'feedback.bug', idea: 'feedback.idea', other: 'feedback.other' };
  const statusLabel = (status: SupportFeedback['status']) => t(status === 'resolved' ? 'feedback.resolved' : 'feedback.open');

  return (
    <section className="panel academic-panel support-feedback-panel">
      <div className="panel-heading"><div><div className="eyebrow">{t('feedback.eyebrow')}</div><h2>{t('feedback.title')}</h2><p>{t('feedback.description').replace('{organization}', organization.name)}</p></div></div>
      {error && <div className="academic-error" role="alert">{error}</div>}
      {success && <div className="task-message" role="status">{success}</div>}
      <form className="academic-form feedback-form" onSubmit={(event) => void submit(event)}>
        <label>{t('feedback.category')}<select value={category} onChange={(event) => setCategory(event.target.value as SupportFeedback['category'])}><option value="support">{t('feedback.support')}</option><option value="bug">{t('feedback.bug')}</option><option value="idea">{t('feedback.idea')}</option><option value="other">{t('feedback.other')}</option></select></label>
        <label>{t('feedback.message')}<textarea required minLength={10} maxLength={5000} rows={6} value={message} onChange={(event) => setMessage(event.target.value)} placeholder={t('feedback.messagePlaceholder')} /><small>{t('feedback.characterCount').replace('{count}', String(message.length))}</small></label>
        <p className="form-hint">{t('feedback.privacy')}</p>
        <button className="task-submit" disabled={saving || message.trim().length < 10}>{saving ? t('feedback.saving') : t('feedback.submit')}</button>
      </form>
      {!canReview && <div className="feedback-inbox"><div className="course-progress-heading"><strong>{t('feedback.yours')}</strong><span>{myItems.length}</span></div>
        {listError && <div className="academic-error" role="alert">{listError}</div>}
        {loading ? <div className="task-message">{t('feedback.loadingYours')}</div> : myItems.length ? <div className="feedback-list">{myItems.map((item) => <article className="feedback-card" key={item.id}>
          <div className="feedback-card-heading"><strong>{t(categoryKeys[item.category])}</strong><span>{statusLabel(item.status)} · {formatFeedbackDate(item.created_at)}</span></div>
          <p>{item.message}</p>
          {item.response && <div className="submission-feedback"><strong>{t('feedback.managerResponse')}{item.responded_at ? ` · ${formatFeedbackDate(item.responded_at)}` : ''}</strong><span>{item.response}</span></div>}
        </article>)}</div> : !listError && <div className="academic-empty">{t('feedback.emptyYours')}</div>}
      </div>}
      {canReview && <div className="feedback-inbox"><div className="course-progress-heading"><strong>{t('feedback.recentWorkspace')}</strong><span>{t('feedback.latest100')}</span></div>
        {listError && <div className="academic-error" role="alert">{listError}</div>}
        {loading ? <div className="task-message">{t('feedback.loading')}</div> : items.length ? <div className="feedback-list">{items.map((item) => <article className="feedback-card" key={item.id}>
          <div className="feedback-card-heading"><strong>{t(categoryKeys[item.category])} · {statusLabel(item.status)}</strong><span>{item.submitter_name ?? t('feedback.workspaceMember')} · {formatFeedbackDate(item.created_at)}</span></div>
          <p>{item.message}</p>
          <form className="academic-form" onSubmit={(event) => { event.preventDefault(); void saveManagerResponse(item); }}>
            <label>{t('feedback.responseTo').replace('{member}', item.submitter_name ?? t('feedback.workspaceMember')).replace('{date}', formatFeedbackDate(item.created_at))}<textarea maxLength={5000} value={responseDrafts[item.id] ?? item.response ?? ''} onChange={(event) => setResponseDrafts((current) => ({ ...current, [item.id]: event.target.value }))} /></label>
            <label>{t('feedback.statusFor').replace('{member}', item.submitter_name ?? t('feedback.workspaceMember')).replace('{date}', formatFeedbackDate(item.created_at))}<select value={statusDrafts[item.id] ?? item.status} onChange={(event) => setStatusDrafts((current) => ({ ...current, [item.id]: event.target.value as SupportFeedback['status'] }))}><option value="open">{t('feedback.open')}</option><option value="resolved">{t('feedback.resolved')}</option></select></label>
            <button className="task-submit" disabled={savingFeedbackId === item.id}>{savingFeedbackId === item.id ? t('feedback.saving') : t('feedback.saveResponse')}</button>
          </form>
          {item.responder_name && item.responded_at && <small>{t('feedback.lastResponseBy').replace('{name}', item.responder_name)} · {formatFeedbackDate(item.responded_at)}</small>}
        </article>)}</div> : !listError && <div className="academic-empty">{t('feedback.emptyWorkspace')}</div>}
      </div>}
    </section>
  );
}

function WorkspaceSettingsPanel({ token, organization, onUpdated }: { token: string; organization: Organization; onUpdated: (updated: Organization) => void }) {
  const [name, setName] = useState(organization.name); const [locale, setLocale] = useState(organization.locale || 'en');
  const [timezone, setTimezone] = useState(organization.timezone || 'Asia/Kolkata');
  const [saving, setSaving] = useState(false); const [error, setError] = useState(''); const [saved, setSaved] = useState(false);
  useEffect(() => { setName(organization.name); setLocale(organization.locale || 'en'); setTimezone(organization.timezone || 'Asia/Kolkata'); }, [organization.id, organization.name, organization.locale, organization.timezone]);
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setSaving(true); setError(''); setSaved(false);
    try { const updated = await updateOrganization(token, organization.id, { name: name.trim(), locale, timezone }); onUpdated(updated); setSaved(true); }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not save workspace settings.'); }
    finally { setSaving(false); }
  }
  return <section className="panel academic-panel workspace-settings-panel"><div className="panel-heading"><div><div className="eyebrow">WORKSPACE SETTINGS</div><h2>Organization profile</h2><p>Set the name, language, and time zone used across this workspace.</p></div></div>{error && <div className="academic-error" role="alert">{error}</div>}{saved && <div className="task-message" role="status">Workspace settings saved.</div>}<form className="academic-form settings-form" onSubmit={(event) => void submit(event)}><label>Workspace name<input required maxLength={160} value={name} onChange={(event) => setName(event.target.value)} /></label><label>Interface language<select value={locale} onChange={(event) => setLocale(event.target.value)}><option value="en">English</option><option value="hi">हिन्दी</option></select></label><label>Time zone<select value={timezone} onChange={(event) => setTimezone(event.target.value)}>{![ 'Asia/Kolkata', 'Asia/Kathmandu', 'Asia/Dubai', 'UTC', 'Europe/London', 'America/New_York' ].includes(timezone) && <option value={timezone}>{timezone}</option>}<option value="Asia/Kolkata">Asia/Kolkata</option><option value="Asia/Kathmandu">Asia/Kathmandu</option><option value="Asia/Dubai">Asia/Dubai</option><option value="UTC">UTC</option><option value="Europe/London">Europe/London</option><option value="America/New_York">America/New_York</option></select></label><div><button className="task-submit" disabled={saving || !name.trim()}>{saving ? 'Saving…' : 'Save settings'}</button></div><small className="form-hint">Locale selection is saved. Full interface translation coverage is still in progress.</small></form></section>;
}

function ProfilePanel({ token, user, organization, organizations, onUserUpdated, onLogout }: { token: string; user: User; organization: Organization; organizations: Organization[]; onUserUpdated: (user: User) => void; onLogout: () => void }) {
  const [name, setName] = useState(user.name);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [saved, setSaved] = useState(false);
  useEffect(() => { setName(user.name); }, [user.name]);
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setSaving(true); setError(''); setSaved(false);
    try { const updated = await updateAccountProfile(token, { name: name.trim() }); onUserUpdated(updated); setSaved(true); }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not update your profile.'); }
    finally { setSaving(false); }
  }
  return <section className="panel academic-panel profile-panel"><div className="panel-heading"><div><div className="eyebrow">ACCOUNT</div><h2>Your profile</h2><p>Update the name shown across your workspaces.</p></div></div>{error && <div className="academic-error" role="alert">{error}</div>}{saved && <div className="task-message" role="status">Profile updated.</div>}<form className="academic-form settings-form" onSubmit={(event) => void submit(event)}><label>Display name<input autoComplete="name" required maxLength={120} value={name} onChange={(event) => setName(event.target.value)} /></label><div><button className="task-submit" disabled={saving || !name.trim() || name.trim() === user.name}>{saving ? 'Saving…' : 'Save profile'}</button></div></form><div className="profile-details"><div><span>Email address</span><strong>{user.email}</strong></div><div><span>Current workspace</span><strong>{organization.name}</strong></div><div><span>Workspace role</span><strong>{organization.custom_role_name ?? organization.role}</strong></div><div><span>Active workspaces</span><strong>{organizations.length}</strong></div></div><button className="task-cancel" onClick={onLogout}>Sign out of PaathNexus</button></section>;
}

type WorkspaceProps = {
  token: string;
  user: User;
  organization: Organization;
  organizations: Organization[];
  onSwitchOrganization: (id: string) => void;
  onOrganizationUpdated: (updated: Organization) => void;
  onUserUpdated: (updated: User) => void;
  onLogout: () => void;
};

function Workspace({ token, user, organization, organizations, onSwitchOrganization, onOrganizationUpdated, onUserUpdated, onLogout }: WorkspaceProps) {
  const { locale, t } = useLocale();
  const [activePage, setActivePage] = useState(organization.role === 'guardian' ? 'My children' : organization.role === 'student' ? 'My learning' : 'Overview');
  const [tasks, setTasks] = useState<Task[]>([]);
  const [tasksLoading, setTasksLoading] = useState(true);
  const [tasksError, setTasksError] = useState('');
  const [taskFormOpen, setTaskFormOpen] = useState(false);
  const [taskTitle, setTaskTitle] = useState('');
  const [taskDescription, setTaskDescription] = useState('');
  const [taskDueDate, setTaskDueDate] = useState('');
  const [taskAssigneeId, setTaskAssigneeId] = useState('');
  const [taskAssignees, setTaskAssignees] = useState<TaskAssignee[]>([]);
  const [editingTaskId, setEditingTaskId] = useState<string | null>(null);
  const [taskSaving, setTaskSaving] = useState(false);
  const [query, setQuery] = useState('');
  const [mobileNav, setMobileNav] = useState(false);
  const [notice, setNotice] = useState('');
  const [reportPeriod, setReportPeriod] = useState<{ from: string; to: string } | null>(null);
  const [attendance, setAttendance] = useState<number | null>(null);
  const [attendanceSavedDate, setAttendanceSavedDate] = useState<string | null>(null);
  const [attendanceClassName, setAttendanceClassName] = useState('Class 6A');
  const [dashboard, setDashboard] = useState<DashboardSummary | null>(null);
  const [dashboardLoading, setDashboardLoading] = useState(true);
  const [dashboardError, setDashboardError] = useState('');
  const [showProfile, setShowProfile] = useState(false);
  const [showWorkspaceMenu, setShowWorkspaceMenu] = useState(false);
  const [showNotifications, setShowNotifications] = useState(false);
  const [unreadNotifications, setUnreadNotifications] = useState(0);
  const activeTasks = tasks.filter((task) => !task.archived);
  const archivedTasks = tasks.filter((task) => task.archived);
  const completed = activeTasks.filter((task) => task.done).length;
  const [taskStatusFilter, setTaskStatusFilter] = useState<'open' | 'completed' | 'all' | 'archived'>('open');
  const filteredTasks = taskStatusFilter === 'archived'
    ? archivedTasks
    : activeTasks.filter((task) => taskStatusFilter === 'all' || (taskStatusFilter === 'completed' ? task.done : !task.done));

  useEffect(() => {
    document.documentElement.lang = locale === 'hi' ? 'hi-IN' : 'en';
  }, [locale]);

  useEffect(() => {
    const focusSearch = (event: KeyboardEvent) => {
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        document.querySelector<HTMLInputElement>('.search-box input')?.focus();
      }
    };
    window.addEventListener('keydown', focusSearch);
    return () => window.removeEventListener('keydown', focusSearch);
  }, []);

  useEffect(() => {
    let current = true;
    listWorkspaceNotifications(token, organization.id)
      .then((result) => { if (current) setUnreadNotifications(result.unread_count); })
      .catch(() => { if (current) setUnreadNotifications(0); });
    return () => { current = false; };
  }, [token, organization.id]);

  useEffect(() => {
    if (organization.role === 'guardian') setActivePage('My children');
    else if (organization.role === 'student') {
      const studentPages = ['My learning', 'Courses', 'Discover courses', 'AI Learning', ...(hasPermission(organization, 'fees.view') ? ['Fee invoices'] : [])];
      if (!studentPages.includes(activePage)) setActivePage('My learning');
    } else {
      const manager = ['owner', 'admin', 'educator'].includes(organization.role);
      const staff = ['owner', 'admin', 'educator', 'teacher'].includes(organization.role);
      const canViewAcademic = hasPermission(organization, 'academic.view') || hasPermission(organization, 'academic.manage');
      const canViewCourses = hasPermission(organization, 'courses.view') || hasPermission(organization, 'courses.manage');
      const canManageMembers = hasPermission(organization, 'members.manage');
      const canOpenSettings = hasPermission(organization, 'organization.update') || canManageMembers;
      const canViewAttendance = hasPermission(organization, 'attendance.read') && canViewAcademic;
      const canUseAiLearning = hasPermission(organization, 'courses.view') || hasPermission(organization, 'courses.manage');
      const availablePages = ['Overview', ...(canViewAcademic ? ['My classes', 'Schedule'] : []), ...(canViewCourses ? ['Courses'] : []), 'Discover courses', ...(canViewAttendance ? ['Attendance'] : []), ...(canUseAiLearning ? ['AI Learning'] : []), 'Profile', 'Help centre', 'Feedback', ...(manager && (canViewAcademic || canManageMembers) ? ['Students'] : []), ...(manager && hasPermission(organization, 'fees.view') ? ['Fee invoices'] : []), ...(manager && hasPermission(organization, 'academic.manage') ? ['Announcements'] : []), ...(manager && canOpenSettings ? ['Settings'] : []), ...(staff && canViewAcademicReports(organization) ? ['Reports'] : []), ...(staff && hasPermission(organization, 'tasks.view') ? ['Tasks'] : []), ...(organization.role === 'owner' ? ['Plan & billing'] : []), ...(user.is_platform_admin ? ['Platform plans'] : [])];
      if (!availablePages.includes(activePage)) setActivePage('Overview');
    }
  }, [organization.id, organization.role, organization.permissions?.join(','), user.is_platform_admin]);

  useEffect(() => {
    if (organization.role === 'guardian' || organization.role === 'student') {
      setTasks([]); setTaskAssignees([]); setTasksError(''); setTasksLoading(false);
      return;
    }
    if (!hasPermission(organization, 'tasks.view')) {
      setTasks([]); setTaskAssignees([]); setTasksError(''); setTasksLoading(false);
      return;
    }
    let current = true;
    setTasksLoading(true);
    setTasksError('');
    Promise.all([listTasks(token, organization.id), hasPermission(organization, 'tasks.manage') ? listTaskAssignees(token, organization.id) : Promise.resolve([])])
      .then(([items, assignees]) => { if (current) { setTasks(items.map((task) => displayTask(task, locale, t))); setTaskAssignees(assignees); } })
      .catch((error: Error) => { if (current) setTasksError(error.message); })
      .finally(() => { if (current) setTasksLoading(false); });
    return () => { current = false; };
  }, [token, organization.id, organization.role, organization.permissions?.join(','), locale]);
  const dateLabel = new Intl.DateTimeFormat(locale === 'hi' ? 'hi-IN' : 'en-IN', {
    weekday: 'long', day: '2-digit', month: 'long', year: 'numeric', timeZone: organization.timezone,
  }).format(new Date()).toUpperCase();
  useEffect(() => {
    if (organization.role === 'guardian' || organization.role === 'student') {
      setDashboard(null); setDashboardError(''); setDashboardLoading(false);
      return;
    }
    let current = true;
    setDashboardLoading(true);
    setDashboardError('');
    getDashboardSummary(token, organization.id)
      .then((summary) => {
        if (!current) return;
        setDashboard(summary);
        setAttendance(summary.latest_attendance_rate);
        setAttendanceSavedDate(summary.latest_attendance_date);
        setAttendanceClassName(summary.latest_attendance_class ?? 'No sessions yet');
      })
      .catch((error: Error) => { if (current) setDashboardError(error.message); })
      .finally(() => { if (current) setDashboardLoading(false); });
    return () => { current = false; };
  }, [token, organization.id, organization.role, activePage]);

  const pageTitle = useMemo(() => activePage === 'Overview' ? t('workspace.teachingDay') : translateWorkspaceLabel(activePage, t), [activePage, t]);
  const pageDescription = useMemo(() => {
    if (organization.role === 'guardian') return t('workspace.guardianOverviewDescription');
    if (organization.role === 'student') {
      if (activePage === 'Courses') return t('workspace.studentCoursesDescription');
      if (activePage === 'Fee invoices') return t('workspace.studentFeesDescription');
      return t('workspace.studentLearningDescription');
    }
    if (activePage === 'AI Learning') return t('workspace.aiLearningDescription');
    if (activePage === 'Overview') return t('workspace.overviewDescription');
    if (activePage === 'Platform plans') return t('platformAdmin.pageDescription');
    return `A focused view of ${activePage.toLowerCase()} across your workspace.`;
  }, [activePage, organization.role, t]);

  async function toggleTask(task: Task) {
    if (task.done || task.archived) return;
    try {
      const completedTask = await completeTask(token, organization.id, task.id);
      setTasks((items) => items.map((item) => item.id === task.id ? displayTask(completedTask, locale, t) : item));
    } catch (error) {
      setTasksError(error instanceof Error ? error.message : 'Could not complete this task.');
    }
  }

  async function toggleTaskArchive(task: Task) {
    try {
      const updated = task.archived
        ? await restoreTask(token, organization.id, task.id)
        : await archiveTask(token, organization.id, task.id);
      setTasks((items) => items.map((item) => item.id === task.id ? displayTask(updated, locale, t) : item));
      setTasksError('');
    } catch (error) {
      setTasksError(error instanceof Error ? error.message : task.archived ? 'Could not restore this task.' : 'Could not archive this task.');
    }
  }

  async function submitTask(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setTaskSaving(true);
    setTasksError('');
    try {
      const task = editingTaskId
        ? await updateTask(token, organization.id, editingTaskId, {
          title: taskTitle.trim(),
          description: taskDescription.trim() || null,
          due_at: taskDueDate || null,
          assigned_to: taskAssigneeId || null,
        })
        : await createTask(token, organization.id, {
          title: taskTitle.trim(),
          ...(taskDescription.trim() ? { description: taskDescription.trim() } : {}),
          ...(taskDueDate ? { due_at: taskDueDate } : {}),
          ...(taskAssigneeId ? { assigned_to: taskAssigneeId } : {}),
        });
      setTasks((items) => editingTaskId
        ? items.map((item) => item.id === task.id ? displayTask(task, locale, t) : item)
        : [displayTask(task, locale, t), ...items]);
      setTaskTitle('');
      setTaskDescription('');
      setTaskDueDate('');
      setTaskAssigneeId('');
      setEditingTaskId(null);
      setTaskFormOpen(false);
    } catch (error) {
      setTasksError(error instanceof Error ? error.message : editingTaskId ? 'Could not update this task.' : 'Could not create this task.');
    } finally {
      setTaskSaving(false);
    }
  }

  function startTaskEdit(task: Task) {
    setEditingTaskId(task.id);
    setTaskTitle(task.title);
    setTaskDescription(task.description);
    setTaskDueDate(task.due_at?.slice(0, 10) ?? '');
    setTaskAssigneeId(task.assigned_to ?? '');
    setTaskFormOpen(true);
  }

  function cancelTaskEdit() {
    setEditingTaskId(null);
    setTaskTitle('');
    setTaskDescription('');
    setTaskDueDate('');
    setTaskAssigneeId('');
    setTaskFormOpen(false);
  }

  function showAttendance() {
    setActivePage('Attendance');
  }

  return <div className="app-shell">
    <aside className={`sidebar ${mobileNav ? 'sidebar-open' : ''}`}>
      <div className="brand"><span className="brand-mark"><GraduationCap size={21} strokeWidth={2.4} /></span><span>paath<span className="brand-light">nexus</span><small>AI</small></span><button className="icon-button close-nav" onClick={() => setMobileNav(false)} aria-label={t('workspace.closeNavigation')}><X size={19} /></button></div>
      <div className="workspace-picker"><button className="workspace-switch" aria-haspopup="menu" aria-expanded={showWorkspaceMenu} onClick={() => setShowWorkspaceMenu((open) => !open)}><div className="school-mark">{organization.name.slice(0, 1).toUpperCase()}</div><div className="workspace-copy"><strong>{organization.name}</strong><span>{organization.role} workspace</span></div><ChevronDown size={15} /></button>{showWorkspaceMenu && <div className="workspace-menu" role="menu" aria-label="Switch organization">{organizations.map((item) => <button key={item.id} role="menuitemradio" aria-checked={item.id === organization.id} onClick={() => { onSwitchOrganization(item.id); setShowWorkspaceMenu(false); }}><span className="workspace-menu-mark">{item.name.slice(0, 1).toUpperCase()}</span><span className="workspace-menu-copy"><strong>{item.name}</strong><small>{item.role} · {item.type}</small></span>{item.id === organization.id && <Check size={15} />}</button>)}</div>}</div>
      <div className="nav-label">WORKSPACE</div>
      <nav aria-label={t('workspace.mainNavigation')}>{(organization.role === 'guardian' ? [{ label: 'My children', icon: Users }, ...(hasPermission(organization, 'fees.view') ? [{ label: 'Fee invoices', icon: Receipt }] : [])] : organization.role === 'student' ? [{ label: 'My learning', icon: LayoutDashboard }, { label: 'Courses', icon: GraduationCap }, { label: 'Discover courses', icon: Search }, ...(hasPermission(organization, 'fees.view') ? [{ label: 'Fee invoices', icon: Receipt }] : [])] : [...navigation.filter((item) => (organization.role !== 'teacher' || item.label !== 'Students') && (item.label !== 'Tasks' || (['owner', 'admin', 'educator', 'teacher'].includes(organization.role) && hasPermission(organization, 'tasks.view'))) && (item.label !== 'Attendance' || (hasPermission(organization, 'attendance.read') && (hasPermission(organization, 'academic.view') || hasPermission(organization, 'academic.manage')))) && (!['My classes', 'Schedule'].includes(item.label) || hasPermission(organization, 'academic.view') || hasPermission(organization, 'academic.manage')) && (item.label !== 'Courses' || hasPermission(organization, 'courses.view') || hasPermission(organization, 'courses.manage')) && (item.label !== 'Students' || hasPermission(organization, 'academic.view') || hasPermission(organization, 'academic.manage') || hasPermission(organization, 'members.manage'))), ...(['owner', 'admin', 'educator'].includes(organization.role) && hasPermission(organization, 'fees.view') ? [{ label: 'Fee invoices', icon: Receipt }] : []), ...(organization.role === 'owner' ? [{ label: 'Plan & billing', icon: Receipt }] : [])]).map(({ label, icon: Icon, badge }) => <button key={label} aria-current={activePage === label ? 'page' : undefined} className={`nav-item ${activePage === label ? 'nav-active' : ''}`} onClick={() => { setActivePage(label); setMobileNav(false); }}><Icon size={18} strokeWidth={1.8} /><span>{translateWorkspaceLabel(label, t)}</span>{badge && <em>{badge}</em>}</button>)}</nav>
      {organization.role !== 'guardian' && <><div className="nav-label tools-label">TOOLS</div>
      <nav aria-label={t('workspace.tools')}>{(hasPermission(organization, 'courses.view') || hasPermission(organization, 'courses.manage')) && <button aria-current={activePage === 'AI Learning' ? 'page' : undefined} className={`nav-item ${activePage === 'AI Learning' ? 'nav-active' : ''}`} onClick={() => setActivePage('AI Learning')}><Sparkles size={18} /><span>{t('workspace.aiLearning')}</span><span className="new-pill">NEW</span></button>}{['owner', 'admin', 'educator'].includes(organization.role) && hasPermission(organization, 'academic.manage') && <button aria-current={activePage === 'Announcements' ? 'page' : undefined} className={`nav-item ${activePage === 'Announcements' ? 'nav-active' : ''}`} onClick={() => setActivePage('Announcements')}><MessageCircle size={18} /><span>Announcements</span></button>}{['owner', 'admin', 'educator', 'teacher'].includes(organization.role) && canViewAcademicReports(organization) && <button aria-current={activePage === 'Reports' ? 'page' : undefined} className={`nav-item ${activePage === 'Reports' ? 'nav-active' : ''}`} onClick={() => setActivePage('Reports')}><Activity size={18} /><span>Reports</span></button>}{user.is_platform_admin && <button aria-current={activePage === 'Platform plans' ? 'page' : undefined} className={`nav-item ${activePage === 'Platform plans' ? 'nav-active' : ''}`} onClick={() => setActivePage('Platform plans')}><ShieldCheck size={18} /><span>{t('platformAdmin.nav')}</span></button>}</nav></>}
      <div className="sidebar-bottom"><div className="help-card"><div className="help-icon"><CircleHelp size={17} /></div><strong>{t('help.sidebarTitle')}</strong><span>{t('help.sidebarDescription')}</span><button aria-current={activePage === 'Help centre' ? 'page' : undefined} onClick={() => { setActivePage('Help centre'); setMobileNav(false); }}>{t('help.openCentre')} <ArrowRight size={13} /></button></div>{['owner', 'admin', 'educator'].includes(organization.role) && (hasPermission(organization, 'organization.update') || hasPermission(organization, 'members.manage')) && <button aria-current={activePage === 'Settings' ? 'page' : undefined} className="nav-item settings-link" onClick={() => setActivePage('Settings')}><Settings2 size={18} /><span>Settings</span></button>}</div>
      <div className="sidebar-foot">PAATHNEXUS AI <span>·</span> v0.1</div>
    </aside>

    {mobileNav && <button className="scrim" onClick={() => setMobileNav(false)} aria-label="Close navigation overlay" />}
    <main className="main-area">
      <header className="topbar"><div className="top-left"><button className="icon-button menu-button" onClick={() => setMobileNav(true)} aria-label={t('workspace.openNavigation')}><Menu size={20} /></button><div className="breadcrumb">{t('workspace.workspace')} <span>/</span> <strong>{translateWorkspaceLabel(activePage, t)}</strong></div></div><div className="top-actions"><label className="search-box"><Search size={16} /><input aria-label={t('workspace.searchLabel')} placeholder={t('workspace.searchPlaceholder')} value={query} onChange={(event) => setQuery(event.target.value)} /><kbd>⌘ K</kbd></label><div className="notification-control"><button className="icon-button notification-button" aria-label={`${t('workspace.notifications')}${unreadNotifications ? `, ${unreadNotifications} unread` : ''}`} aria-expanded={showNotifications} onClick={() => setShowNotifications((open) => !open)}><Bell size={18} />{unreadNotifications > 0 && <i />}</button>{showNotifications && <NotificationsPopover token={token} organization={organization} onUnreadCount={setUnreadNotifications} onOpenFeedback={() => { setActivePage('Feedback'); setShowNotifications(false); }} />}</div><LanguageSwitcher className="workspace-language" /><div className="top-divider" /><button className="profile-button" onClick={() => setShowProfile((open) => !open)}><span className="avatar avatar-top">{user.name.split(/\s+/).map((part) => part[0]).slice(0, 2).join('').toUpperCase()}</span><span className="profile-name"><strong>{user.name}</strong><small>{organization.role}</small></span><ChevronDown size={14} /></button>{showProfile && <div className="profile-menu"><strong>{user.name}</strong><span>{user.email}</span><button onClick={() => { setActivePage('Profile'); setShowProfile(false); }}>{t('workspace.viewProfile')}</button><button onClick={onLogout}>{t('workspace.signOut')}</button></div>}</div></header>

      <div className="page-content">
        <div className="welcome-row"><div><div className="date-line"><span className="date-dot" /> {dateLabel}</div><h1>{activePage === 'Overview' ? <>{t('workspace.goodMorning')}, {user.name.split(' ')[0]} <span className="wave">✳</span></> : pageTitle}</h1><p>{pageDescription}</p></div>{['owner', 'admin', 'educator', 'teacher'].includes(organization.role) && <div className="welcome-actions">{canViewAcademicReports(organization) && <button className="button button-quiet" onClick={() => { setReportPeriod(currentWeekToDateRange()); setActivePage('Reports'); }}><CalendarDays size={16} /> {t('workspace.thisWeek')} <ChevronDown size={14} /></button>}{hasPermission(organization, 'attendance.read') && <button className="button button-primary" onClick={showAttendance}><Plus size={16} /> {t('workspace.takeAttendance')}</button>}</div>}</div>

        <WorkspaceSearchPanel query={query} token={token} organization={organization} onChoose={(result) => {
          const canManageAnnouncements = ['owner', 'admin', 'educator'].includes(organization.role) && hasPermission(organization, 'academic.manage');
          const page = result.type === 'announcement' ? canManageAnnouncements ? 'Announcements' : activePage
            : result.type === 'attendance' ? 'Attendance'
              : result.type === 'course' ? 'Courses' : result.type === 'invoice' ? 'Fee invoices' : result.type === 'task' ? 'Overview'
            : result.type === 'student' ? organization.role === 'guardian' ? 'My children' : organization.role === 'student' ? 'My learning' : 'My classes'
              : organization.role === 'student' ? 'My learning' : organization.role === 'guardian' ? 'My children' : 'My classes';
          setActivePage(page); setQuery(''); setMobileNav(false);
          if (result.type === 'announcement' && !canManageAnnouncements) setShowNotifications(true);
        }} />
        {notice && <div className="toast" role="status"><span><Check size={15} /></span>{notice}<button onClick={() => setNotice('')} aria-label="Dismiss"><X size={15} /></button></div>}

        {(activePage === 'My classes' || activePage === 'Attendance') && (hasPermission(organization, 'academic.view') || hasPermission(organization, 'academic.manage')) && (activePage !== 'Attendance' || hasPermission(organization, 'attendance.read')) && <MyClassesPanel key={organization.id} token={token} organization={organization} showAttendanceHistory={activePage === 'Attendance'} onAttendanceRecorded={(rate, className, date) => { setAttendance(rate); setAttendanceClassName(className); setAttendanceSavedDate(date); void getDashboardSummary(token, organization.id).then(setDashboard).catch((error: Error) => setDashboardError(error.message)); }} />}
        {activePage === 'Students' && ['owner', 'admin', 'educator'].includes(organization.role) && <>{(hasPermission(organization, 'academic.view') || hasPermission(organization, 'academic.manage')) && <StudentsDirectoryPanel key={`students-${organization.id}`} token={token} organization={organization} />}{hasPermission(organization, 'members.manage') && <InvitationsPanel key={`invites-${organization.id}`} token={token} organization={organization} onNavigate={(page) => { setActivePage(page); setMobileNav(false); }} />}{hasPermission(organization, 'academic.manage') && <GuardianLinksPanel key={`guardians-${organization.id}`} token={token} organization={organization} />}</>}
        {activePage === 'Reports' && ['owner', 'admin', 'educator', 'teacher'].includes(organization.role) && canViewAcademicReports(organization) && <AcademicReportsPanel key={organization.id} token={token} organization={organization} initialPeriod={reportPeriod} />}
        {activePage === 'Announcements' && ['owner', 'admin', 'educator'].includes(organization.role) && hasPermission(organization, 'academic.manage') && <AnnouncementsPanel key={organization.id} token={token} organization={organization} />}
        {activePage === 'Fee invoices' && ['owner', 'admin', 'educator', 'student', 'guardian'].includes(organization.role) && hasPermission(organization, 'fees.view') && <><FeeInvoicesPanel key={`invoices-${organization.id}`} token={token} organization={organization} />{hasPermission(organization, 'academic.manage') && <FeeCollectionsPanel key={`collections-${organization.id}`} token={token} organization={organization} />}</>}
        {activePage === 'My learning' && organization.role === 'student' && <><StudentDashboardPanel key={organization.id} token={token} organization={organization} /><StudentStudyPlanner key={`study-${organization.id}`} token={token} organization={organization} /></>}
        {activePage === 'Plan & billing' && organization.role === 'owner' && <PlanAndBillingPanel key={organization.id} token={token} organization={organization} />}
        {activePage === 'Platform plans' && user.is_platform_admin && <PlatformPlansPanel token={token} />}
        {activePage === 'Settings' && ['owner', 'admin', 'educator'].includes(organization.role) && <>{hasPermission(organization, 'organization.update') && <WorkspaceSettingsPanel key={organization.id} token={token} organization={organization} onUpdated={onOrganizationUpdated} />}{hasPermission(organization, 'members.manage') && <Suspense fallback={<div className="task-message">Loading role settings…</div>}><CustomTeacherRolesPanel key={`roles-${organization.id}`} token={token} organization={organization} /></Suspense>}</>}
        {activePage === 'Profile' && <ProfilePanel token={token} user={user} organization={organization} organizations={organizations} onUserUpdated={onUserUpdated} onLogout={onLogout} />}
        {activePage === 'Help centre' && <HelpCentrePanel onFeedback={() => setActivePage('Feedback')} />}
        {activePage === 'Feedback' && <SupportFeedbackPanel key={organization.id} token={token} organization={organization} />}
        {organization.role === 'guardian' && <GuardianSummaryPanel key={organization.id} token={token} organization={organization} />}
        {activePage === 'Courses' && organization.role !== 'guardian' && (hasPermission(organization, 'courses.view') || hasPermission(organization, 'courses.manage')) && <CoursesPanel key={organization.id} token={token} organization={organization} />}
        {activePage === 'Discover courses' && organization.role !== 'guardian' && <Suspense fallback={<div className="task-message">Loading course discovery…</div>}><MarketplacePanel key={`marketplace-${organization.id}`} token={token} organizations={organizations} onOpenWorkspace={(id) => { onSwitchOrganization(id); setActivePage('Courses'); }} /></Suspense>}
        {activePage === 'AI Learning' && organization.role !== 'guardian' && <>{hasPermission(organization, 'courses.view') && <AiTutorPanel key={`tutor-${organization.id}`} token={token} organization={organization} />}{hasPermission(organization, 'courses.manage') && <AiTeacherAssistantPanel key={`teacher-ai-${organization.id}`} token={token} organization={organization} />}</>}
        {activePage === 'Schedule' && organization.role !== 'guardian' && (hasPermission(organization, 'academic.view') || hasPermission(organization, 'academic.manage')) && <><SchedulePanel key={`schedule-${organization.id}`} token={token} organization={organization} />{hasPermission(organization, 'live_sessions.view') ? <LiveClassroomsPanel key={`live-${organization.id}`} token={token} organization={organization} /> : <div className="task-message">Live session access is not included in your assigned member role.</div>}</>}

        {activePage === 'Overview' && organization.role !== 'guardian' && <section className="stat-grid" aria-label="Workspace overview">
          <article className="stat-card"><div className="stat-top"><span className="stat-icon lilac"><Users size={17} /></span><span className="trend neutral">Workspace</span></div><div className="stat-number">{dashboardLoading ? '—' : dashboard?.active_students_count ?? '—'}</div><div className="stat-caption">Active students</div><div className="stat-foot"><span>Across active classes</span><strong>{dashboard?.active_enrollments_count ?? '—'} enrollments</strong></div></article>
          <article className="stat-card"><div className="stat-top"><span className="stat-icon mint"><ClipboardCheck size={17} /></span><span className="trend neutral">Latest session</span></div><div className="stat-number">{attendance === null ? '—' : <>{attendance}<small>%</small></>}</div><div className="stat-caption">Attendance recorded</div><div className="stat-progress"><span style={{ width: `${attendance ?? 0}%` }} /></div><div className="stat-foot"><span>{attendanceSavedDate ? `${attendanceSavedDate} · ${attendanceClassName}` : 'No attendance recorded'}</span><strong>{dashboard?.attendance_series.length ?? 0} days</strong></div></article>
          <article className="stat-card"><div className="stat-top"><span className="stat-icon peach"><BookOpen size={17} /></span><span className="trend neutral">Active</span></div><div className="stat-number">{dashboardLoading ? '—' : dashboard?.active_classes_count ?? '—'}</div><div className="stat-caption">Classes in this workspace</div><div className="stat-foot"><span>Active enrollments</span><strong>{dashboard?.active_enrollments_count ?? '—'}</strong></div></article>
          <article className="stat-card"><div className="stat-top"><span className="stat-icon blue"><ClipboardCheck size={17} /></span><span className="trend neutral">To enroll</span></div><div className="stat-number">{dashboardLoading ? '—' : dashboard?.unassigned_students_count ?? '—'}</div><div className="stat-caption">Students without a class</div><button className="review-link" onClick={() => setActivePage('My classes')}>Manage roster <ArrowRight size={14} /></button></article>
        </section>}

        {activePage === 'Overview' && organization.role !== 'guardian' && <section className="primary-grid">
          <article className="panel learning-panel"><div className="panel-heading"><div><div className="eyebrow">ATTENDANCE</div><h2>Recent attendance</h2><p>Daily average across recorded class sessions</p></div><span className="select-button">Last 5 days</span></div><div className="chart-legend"><span><i className="legend-indigo" /> Present or late</span><span><i className="legend-lilac" /> Absent or excused</span><span className="chart-total"><strong>{attendance === null ? '—' : `${attendance}%`}</strong> latest session</span></div><div className="chart-wrap"><div className="y-labels"><span>100%</span><span>75%</span><span>50%</span><span>25%</span><span>0%</span></div><div className="chart"><div className="grid-lines"><i /><i /><i /><i /><i /></div>{dashboard?.attendance_series.map((day) => { const present = day.rate; const absent = Math.max(0, 100 - day.rate); const label = new Intl.DateTimeFormat('en-IN', { weekday: 'short', timeZone: 'UTC' }).format(new Date(`${day.date}T12:00:00Z`)); return <div className="bar-group" key={day.date}><div className="bars"><span className="bar present-bar" style={{ height: `${present}%` }} title={`${present}% present or late`} /><span className="bar engaged-bar" style={{ height: `${absent}%` }} title={`${absent.toFixed(1)}% absent or excused`} /></div><span className="bar-label">{label}</span></div>; })}</div></div><div className="chart-note"><span className="note-check"><Check size={13} /></span><span>{dashboard?.attendance_series.length ? 'Rates are calculated from student marks for each recorded class session.' : 'Record attendance for an enrolled class to see the trend.'}</span><button onClick={() => setActivePage('Attendance')}>Take attendance <ArrowRight size={14} /></button></div></article>

          <article className="panel agenda-panel"><div className="panel-heading agenda-heading"><div><div className="eyebrow">ACTIVE TIMETABLE</div><h2>Today’s lessons <span className="today-count">{dashboard?.today_schedule.length ?? '—'}</span></h2></div><button className="more-button" aria-label="Manage timetable" onClick={() => setActivePage('Schedule')}><MoreHorizontal size={19} /></button></div><div className="agenda-list dashboard-schedule-list">{dashboardLoading ? <div className="task-message">Loading today’s timetable…</div> : dashboard?.today_schedule.length ? dashboard.today_schedule.map((item, index) => <div className="agenda-item" key={item.id}><div className="agenda-time">{item.starts_at}<small>{item.ends_at}</small></div><div className={`agenda-color ${['violet', 'mint', 'peach'][index % 3]}`} /><div className="agenda-copy"><strong>{item.subject}{item.is_exception ? ' · Extra' : ''}</strong><span>{item.class_name}{item.section_name ? ` · ${item.section_name}` : ''}{item.teacher_name ? ` · ${item.teacher_name}` : ''}{item.room ? ` · ${item.room}` : ''}</span></div></div>) : <div className="task-message">{dashboardError || dashboard?.schedule_notice || 'No lessons scheduled for today.'}</div>}</div><button className="agenda-link" onClick={() => setActivePage('Schedule')}>View timetable <ArrowRight size={14} /></button></article>
        </section>}

        {activePage === 'Tasks' && ['owner', 'admin', 'educator', 'teacher'].includes(organization.role) && <section className="panel academic-panel task-management-panel"><div className="panel-heading"><div><div className="eyebrow">{t('tasks.workspaceTasks')}</div><h2>{t('tasks.list')}</h2><p>{t('tasks.description')}</p></div>{hasPermission(organization, 'tasks.manage') && <button className="task-submit" onClick={() => taskFormOpen ? cancelTaskEdit() : (setEditingTaskId(null), setTaskFormOpen(true))}>{taskFormOpen ? t('classes.cancel') : t('tasks.add')}</button>}</div><div className="task-filter-tabs" role="group" aria-label={t('tasks.filter')}>{(['open', 'completed', 'all', 'archived'] as const).map((filter) => <button key={filter} type="button" className={taskStatusFilter === filter ? 'active' : ''} aria-pressed={taskStatusFilter === filter} onClick={() => setTaskStatusFilter(filter)}>{filter === 'open' ? `${t('tasks.open')} (${activeTasks.length - completed})` : filter === 'completed' ? `${t('tasks.completed')} (${completed})` : filter === 'archived' ? `${t('tasks.archived')} (${archivedTasks.length})` : `${t('tasks.all')} (${activeTasks.length})`}</button>)}</div>{tasksError && <div className="task-error" role="alert">{tasksError}</div>}{taskFormOpen && hasPermission(organization, 'tasks.manage') && <form className="task-form" onSubmit={(event) => void submitTask(event)}><label>{t('tasks.title')}<input autoFocus required maxLength={160} value={taskTitle} onChange={(event) => setTaskTitle(event.target.value)} placeholder={t('tasks.taskPlaceholder')} /></label><label>{t('tasks.descriptionLabel')} <span>({t('tasks.optional')})</span><input maxLength={2000} value={taskDescription} onChange={(event) => setTaskDescription(event.target.value)} placeholder={t('tasks.contextPlaceholder')} /></label><label>{t('tasks.dueDate')} <span>({t('tasks.optional')})</span><input type="date" value={taskDueDate} onChange={(event) => setTaskDueDate(event.target.value)} /></label><label>{t('tasks.assignTo')} <span>({t('tasks.optional')})</span><select value={taskAssigneeId} onChange={(event) => setTaskAssigneeId(event.target.value)}><option value="">{t('tasks.unassigned')}</option>{taskAssignees.map((assignee) => <option key={assignee.id} value={assignee.id}>{assignee.name} · {assignee.role}</option>)}</select></label><div className="task-form-actions"><button type="button" className="task-cancel" onClick={cancelTaskEdit}>Cancel</button><button type="submit" className="task-submit" disabled={taskSaving || !taskTitle.trim()}>{taskSaving ? t('tasks.saving') : editingTaskId ? t('tasks.update') : t('tasks.save')}</button></div></form>}{tasksLoading ? <div className="task-message">{t('tasks.loading')}</div> : filteredTasks.length ? <div className="task-list task-management-list">{filteredTasks.map((task) => <div className={`task-row ${task.done ? 'task-done' : ''}`} key={task.id}>{hasPermission(organization, 'tasks.manage') && <button className="task-check" onClick={() => void toggleTask(task)} disabled={task.done || task.archived} aria-label={`${task.done ? t('tasks.completed') : t('tasks.complete')} ${task.title}`}>{task.done && <Check size={12} />}</button>}<div className="task-copy"><strong>{task.title}</strong><span>{task.detail}</span></div>{hasPermission(organization, 'tasks.manage') && (task.archived ? <button type="button" className="task-edit-button" aria-label={`${t('tasks.restore')} ${task.title}`} onClick={() => void toggleTaskArchive(task)}><RotateCcw size={13} /></button> : <><button type="button" className="task-edit-button" aria-label={`${t('tasks.edit')} ${task.title}`} onClick={() => startTaskEdit(task)}><Pencil size={13} /></button><button type="button" className="task-edit-button" aria-label={`${t('tasks.archive')} ${task.title}`} onClick={() => void toggleTaskArchive(task)}><Archive size={13} /></button></>)}<span className="task-time"><Clock3 size={13} /> {task.time}</span></div>)}</div> : <div className="academic-empty">{taskStatusFilter === 'archived' ? t('tasks.noArchived') : taskStatusFilter === 'completed' ? t('tasks.noCompleted') : taskStatusFilter === 'open' ? (activeTasks.length ? t('tasks.allComplete') : t('tasks.noTasks')) : t('tasks.noTasks')}</div>}</section>}

        {activePage === 'Overview' && organization.role !== 'guardian' && <section className="secondary-grid"><article className="panel tasks-panel"><div className="panel-heading"><div><div className="eyebrow">KEEP THINGS MOVING</div><h2>Your tasks <span className="task-count">{activeTasks.length - completed}</span></h2></div><button className="text-button" onClick={() => setActivePage('Tasks')}>View all <ArrowRight size={14} /></button></div><div className="task-list">{tasksLoading ? <div className="task-message">Loading tasks…</div> : activeTasks.some((task) => !task.done) ? activeTasks.filter((task) => !task.done).slice(0, 4).map((task) => <div className="task-row" key={task.id}>{hasPermission(organization, 'tasks.manage') && <button className="task-check" onClick={() => void toggleTask(task)} aria-label={`Complete ${task.title}`}><span className="sr-only">Complete</span></button>}<div className="task-copy"><strong>{task.title}</strong><span>{task.detail}</span></div>{hasPermission(organization, 'tasks.manage') && (task.archived ? <button type="button" className="task-edit-button" aria-label={`Restore ${task.title}`} onClick={() => void toggleTaskArchive(task)}><RotateCcw size={13} /></button> : <><button type="button" className="task-edit-button" aria-label={`Edit ${task.title}`} onClick={() => startTaskEdit(task)}><Pencil size={13} /></button><button type="button" className="task-edit-button" aria-label={`Archive ${task.title}`} onClick={() => void toggleTaskArchive(task)}><Archive size={13} /></button></>)}<span className="task-time"><Clock3 size={13} /> {task.time}</span></div>) : <div className="task-message">{activeTasks.length ? 'All tasks are complete.' : 'No tasks yet. Add one to keep things moving.'}</div>}</div>{tasksError && <div className="task-error" role="alert">{tasksError}</div>}{taskFormOpen && hasPermission(organization, 'tasks.manage') && <form className="task-form" onSubmit={(event) => void submitTask(event)}><label>Task title<input autoFocus required maxLength={160} value={taskTitle} onChange={(event) => setTaskTitle(event.target.value)} placeholder="e.g. Prepare next week’s lessons" /></label><label>Description <span>(optional)</span><input maxLength={2000} value={taskDescription} onChange={(event) => setTaskDescription(event.target.value)} placeholder="Add a little context" /></label><label>Due date <span>(optional)</span><input type="date" value={taskDueDate} onChange={(event) => setTaskDueDate(event.target.value)} /></label><label>Assign to <span>(optional)</span><select value={taskAssigneeId} onChange={(event) => setTaskAssigneeId(event.target.value)}><option value="">Unassigned</option>{taskAssignees.map((assignee) => <option key={assignee.id} value={assignee.id}>{assignee.name} · {assignee.role}</option>)}</select></label><div className="task-form-actions"><button type="button" className="task-cancel" onClick={cancelTaskEdit}>Cancel</button><button type="submit" className="task-submit" disabled={taskSaving || !taskTitle.trim()}>{taskSaving ? 'Saving…' : editingTaskId ? 'Update task' : 'Save task'}</button></div></form>}{!taskFormOpen && hasPermission(organization, 'tasks.manage') && <button className="add-task" onClick={() => setTaskFormOpen(true)}><Plus size={14} /> Add a task</button>}</article>

          <article className="panel spotlight-panel"><div className="spotlight-top"><span className="spotlight-icon"><Sparkles size={17} /></span><span className="eyebrow">PAATHNEXUS AI</span><button className="more-button" aria-label="AI feature details" onClick={() => setNotice('AI learning preview · connect a provider to enable tutoring.') }><MoreHorizontal size={19} /></button></div><h2>A little extra help<br />goes a long way.</h2><p>Give every student a space to ask questions and learn at their own pace.</p><button className="spotlight-button" onClick={() => setActivePage('AI Learning')}>Explore AI learning <ArrowRight size={14} /></button><div className="spotlight-decoration deco-one" /><div className="spotlight-decoration deco-two" /></article></section>}
        <footer className="page-footer"><span>© 2026 PaathNexus AI</span><span>Made for the people who make learning happen <span className="footer-heart">♥</span></span><button onClick={() => setActivePage('Feedback')}><MessageCircle size={14} /> Feedback</button></footer>
      </div>
    </main>
  </div>;
}


export default function WorkspaceApp(props: WorkspaceProps) {
  return <Workspace {...props} />;
}
