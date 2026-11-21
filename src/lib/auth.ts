export type Organization = {
  id: string;
  name: string;
  slug: string;
  type: string;
  role: string;
  custom_role_id?: string | null;
  custom_role_name?: string | null;
  custom_role_base_role?: string | null;
  permissions?: string[];
  locale: string;
  timezone: string;
};

export async function updateOrganization(token: string, organizationId: string, input: { name: string; locale: string; timezone: string }): Promise<Organization> {
  const response = await request<{ data: Organization }>(`/organizations/${encodeURIComponent(organizationId)}`, { method: 'PUT', body: JSON.stringify(input) }, token);
  return response.data;
}

export type CustomBaseRole = 'admin' | 'teacher' | 'student' | 'guardian' | 'educator';
export type CustomTeacherRole = { id: string; name: string; base_role: CustomBaseRole; permissions: string[]; archived_at?: string | null; member_count?: number };
export type CustomRoleMember = { id: number; name: string; base_role: CustomBaseRole; custom_role_id: string | null };
export type CustomTeacherRoles = { base_roles: CustomBaseRole[]; permissions_by_role: Record<CustomBaseRole, string[]>; roles: CustomTeacherRole[]; members: CustomRoleMember[] };

export async function listCustomTeacherRoles(token: string, organizationId: string): Promise<CustomTeacherRoles> {
  const response = await request<{ data: CustomTeacherRoles }>(`/organizations/${encodeURIComponent(organizationId)}/roles`, {}, token);
  return response.data;
}

export async function saveCustomTeacherRole(token: string, organizationId: string, input: { id?: string; base_role: CustomBaseRole; name: string; permissions: string[] }): Promise<CustomTeacherRole> {
  const path = `/organizations/${encodeURIComponent(organizationId)}/roles${input.id ? `/${encodeURIComponent(input.id)}` : ''}`;
  const response = await request<{ data: CustomTeacherRole }>(path, { method: input.id ? 'PUT' : 'POST', body: JSON.stringify(input) }, token);
  return response.data;
}

export async function deleteCustomTeacherRole(token: string, organizationId: string, roleId: string): Promise<void> {
  await request(`/organizations/${encodeURIComponent(organizationId)}/roles/${encodeURIComponent(roleId)}`, { method: 'DELETE' }, token);
}

export async function restoreCustomTeacherRole(token: string, organizationId: string, roleId: string): Promise<void> {
  await request(`/organizations/${encodeURIComponent(organizationId)}/roles/${encodeURIComponent(roleId)}/restore`, { method: 'POST' }, token);
}

export async function assignCustomTeacherRole(token: string, organizationId: string, userId: number, roleId: string | null): Promise<void> {
  const path = roleId
    ? `/organizations/${encodeURIComponent(organizationId)}/roles/${encodeURIComponent(roleId)}/members/${userId}`
    : `/organizations/${encodeURIComponent(organizationId)}/role-members/${userId}`;
  await request(path, { method: roleId ? 'PUT' : 'DELETE' }, token);
}

export type User = { name: string; email: string; is_platform_admin?: boolean };
export type SubscriptionPlan = { id: string; code: string; name: string; audience: 'all' | 'institution' | 'educator'; description: string | null; amount_paise: number | null; billing_interval: 'month' | 'year' | 'custom'; entitlements: Record<string, number | null>; is_active: boolean };
export type PublicCourse = { id: string; code: string; title: string; description: string | null; amount_paise: number; organization_name: string | null; organization_slug: string | null; organization_type: string | null; subject: string | null; lesson_count: number; instructors: { name: string; title: string | null; bio: string | null }[] };
export type PublicEducator = { id: string; name: string; title: string | null; bio: string | null; organization_name: string | null; organization_slug: string | null; organization_type: string | null };
export type PublicPlan = Omit<SubscriptionPlan, 'is_active'>;
export type WorkspaceSubscription = { status: 'active' | 'change_pending'; plan: SubscriptionPlan; requested_plan: SubscriptionPlan | null; requested_at: string | null; available_plans: SubscriptionPlan[] };
export type SubscriptionRequest = { organization_id: string; organization_name: string; plan: SubscriptionPlan; requested_plan: SubscriptionPlan; requested_at: string | null };
export type PlatformOrganization = { id: string; name: string; slug: string; type: string; status: 'active' | 'suspended'; current_plan_name: string | null; subscription_status: string | null; active_members_count: number; active_students_count: number; active_courses_count: number; created_at: string };
export type PlatformUserLookup = { id: number; name: string; email: string; account_status: string; email_verified_at: string | null; created_at: string | null; workspaces: { id: string; name: string; slug: string; organization_status: 'active' | 'suspended'; role: string; membership_status: string }[] };
export type PlatformModeratableContent = { type: 'course' | 'educator'; id: string; title: string; organization_id: string; organization_name: string | null; status: 'hidden' | 'visible'; reason: string | null; moderated_at: string | null };
export type PlatformUsageMetrics = { organization_count: number; active_organization_count: number; suspended_organization_count: number; active_membership_count: number; active_student_count: number; active_course_count: number; active_subscription_count: number };
export type PlatformUsageSnapshot = PlatformUsageMetrics & { snapshot_date: string };
export type PlatformUsageHistory = { days: number; snapshots: PlatformUsageSnapshot[] };
export type PlatformFeatureFlag = { key: 'ai_tutor' | 'ai_teacher_assistant'; label: string; description: string; enabled: boolean; default_enabled: boolean; updated_at: string | null; updated_by_id: number | null };
export type PlatformAuditEntry = { id: number; actor_id: number | null; event: string; subject_type: string | null; subject_id: string | null; created_at: string };
export type StudentDashboard = {
  student: { name: string; student_code: string } | null;
  study_recommendations: { id: string; kind: 'assignment' | 'course' | 'review'; title: string; course_id: string; course_title: string; reason: string; duration_minutes: number }[];
  courses: { id: string; code: string; title: string; total_lessons: number; completed_lessons: number; certificate: { id: string; code: string; issued_at: string; completed_lessons: number; total_lessons: number } | null }[];
  assignments: { id: string; title: string; course_title: string | null; due_at: string | null; max_points: number; submission_status: string | null }[];
  assessment_results: { id: string; title: string | null; course_title: string | null; score: number | null; review_status: 'not_required' | 'pending' | 'reviewed'; max_points: number; submitted_at: string | null }[];
  today_schedule: DailyScheduleItem[];
  schedule_notice: string | null;
  attendance: { sessions_recorded: number; present_or_late: number; rate_percent: number | null; recent: { date: string | null; class_name: string | null; status: string }[] };
};
export type StudentStudyPlan = {
  id: string; title: string; course_id: string | null; course_title: string | null;
  scheduled_at: string; duration_minutes: number; status: 'planned' | 'completed'; completed_at: string | null;
};
export type AcademicReport = {
  scope: 'workspace' | 'assigned_classes';
  available_classes: { id: string; name: string }[];
  filters: { class_id: string | null; student_id: string | null; from: string | null; to: string | null };
  summary: { active_students: number; attendance_sessions: number; attendance_percent: number | null; courses_enrolled: number; completed_lessons: number; total_lessons: number; course_completion_percent: number | null; assignments_assigned: number; assignments_submitted: number; assignments_graded: number; assessments_completed: number; assessment_average_percent: number | null; assessment_score_bands: { under_60: number; sixty_to_74: number; seventyfive_to_89: number; ninety_plus: number }; assessment_trend: { month: string; attempts: number; average_percent: number }[]; assessment_breakdown: { assessment_id: string; assessment_title: string; course_id: string; course_title: string; attempts: number; average_percent: number }[]; assessment_course_summary: { course_id: string; course_title: string; attempts: number; average_percent: number }[] };
  students: { student_id: string; student_code: string; student_name: string; classes: string; attendance_sessions: number; attendance_percent: number | null; courses_enrolled: number; completed_lessons: number; total_lessons: number; course_completion_percent: number | null; assignments_assigned: number; assignments_submitted: number; assignments_graded: number; assessments_completed: number; assessment_average_percent: number | null }[];
};
export type Session = { token: string; user: User; organizations: Organization[] };
export type WorkspaceSearchResult = { id: string; type: 'class' | 'student' | 'course' | 'task' | 'invoice' | 'announcement' | 'attendance'; title: string; subtitle: string };
export type VerificationRequired = { verification_required: true; email: string };
export type OrganizationTask = {
  id: string;
  title: string;
  description: string | null;
  due_at: string | null;
  assigned_to: string | null;
  assigned_to_name: string | null;
  status: 'pending' | 'completed';
  completed_at: string | null;
  archived_at: string | null;
  created_at: string;
};
export type SupportFeedback = { id: string; category: 'bug' | 'idea' | 'support' | 'other'; message: string; status: 'open' | 'resolved'; response: string | null; created_at: string; responded_at: string | null; submitter_name?: string; responder_name?: string | null };
export type TaskAssignee = { id: string; name: string; role: string };
export type AttendanceSession = {
  id: string;
  class_id: string | null;
  section_id: string | null;
  class_name: string;
  session_date: string;
  attendance_rate: number;
  updated_at: string;
  records?: { student_id: string; student_name: string | null; student_code: string | null; status: AttendanceStatus }[];
};
export type StudentProfile = { id: string; student_code: string; name: string; section_id?: string | null };
export type FeePayment = { id: string; amount_paise: number; method: 'cash' | 'bank_transfer' | 'upi' | 'cheque' | 'other'; transaction_reference: string; receipt_number: string; received_at: string };
export type FeeInvoice = { id: string; invoice_number: string; student_id: string; student_name: string | null; student_code: string | null; title: string; description: string | null; amount_paise: number; balance_paise: number; payments: FeePayment[]; due_on: string | null; status: 'issued' | 'partially_paid' | 'paid' | 'void'; created_at: string };
export type FeeInvoicePageMeta = { current_page: number; last_page: number; per_page: number; total: number };
export type FeeInvoicePage = { data: FeeInvoice[]; meta: FeeInvoicePageMeta };
export type FeeInvoiceSummary = { invoice_count: number; paid_invoice_count: number; outstanding_invoice_count: number; overdue_invoice_count: number; invoiced_paise: number; collected_paise: number; outstanding_paise: number; overdue_paise: number };
export type FeeCollection = { id: string; invoice_id: string; invoice_number: string | null; invoice_title: string | null; student_name: string | null; student_code: string | null; amount_paise: number; method: FeePayment['method']; transaction_reference: string; receipt_number: string; received_at: string };
export type FeeCollectionReport = { data: FeeCollection[]; summary: { payment_count: number; collected_paise: number }; meta: FeeInvoicePageMeta & { from: string; to: string } };
export type OrganizationClass = {
  id: string;
  name: string;
  grade: string | null;
  section: string | null;
  can_attend_whole: boolean;
  assigned_section_ids: string[];
  student_count: number;
  students: StudentProfile[];
};
export type AttendanceStatus = 'present' | 'absent' | 'late' | 'excused';
export type LiveClassSession = {
  id: string; title: string; description: string | null; class_id: string | null; class_name: string | null;
  section_id: string | null; section_name: string | null; course_id: string | null; course_title: string | null;
  provider: 'zoom' | 'google_meet' | 'microsoft_teams' | 'external'; starts_at: string; ends_at: string;
  status: 'scheduled' | 'live' | 'completed' | 'cancelled'; join_available: boolean; recording_url: string | null;
};
export type LiveSessionRosterStudent = { id: string; name: string; student_code: string };
export type DailyScheduleItem = {
  id: string; subject: string; teacher_name: string | null; class_name: string | null; section_name: string | null;
  starts_at: string; ends_at: string; room: string | null; is_exception?: boolean; title?: string | null;
};
export type ScheduleException = {
  id: string; academic_year_id: string; exception_date: string; type: 'closure' | 'cancel' | 'extra';
  title: string; notes: string | null; class_id: string | null; class_name: string | null;
  section_id: string | null; section_name: string | null; timetable_entry_id: string | null;
  subject_id: string | null; subject: string | null; teacher_profile_id: string | null; teacher_name: string | null;
  starts_at: string | null; ends_at: string | null; room: string | null; status: 'active' | 'cancelled'; cancelled_at: string | null;
};
export type ScheduleExceptionInput = {
  academic_year_id: string; exception_date: string; type: 'closure' | 'cancel' | 'extra'; title: string;
  notes?: string; class_id?: string; section_id?: string; timetable_entry_id?: string;
  subject_id?: string; teacher_profile_id?: string; starts_at?: string; ends_at?: string; room?: string;
};
export type ScheduleExceptionUpdate = { title: string; notes: string | null };
export type DashboardSummary = {
  active_students_count: number | null;
  active_classes_count: number | null;
  active_enrollments_count: number | null;
  unassigned_students_count: number | null;
  pending_tasks_count: number | null;
  latest_attendance_rate: number | null;
  latest_attendance_class: string | null;
  latest_attendance_date: string | null;
  attendance_series: { date: string; rate: number }[];
  classes: Pick<OrganizationClass, 'id' | 'name' | 'grade' | 'section' | 'student_count'>[];
  today_schedule: DailyScheduleItem[];
  schedule_notice: string | null;
  schedule_weekday: number;
  active_academic_year_id: string | null;
};
export type AcademicYear = { id: string; name: string; starts_on: string; ends_on: string; status: 'planned' | 'active' | 'completed' };
export type OrganizationSection = { id: string; class_id: string; class_name: string | null; name: string };
export type OrganizationSubject = { id: string; code: string; name: string; status: 'active' | 'inactive' };
export type TeacherProfile = { id: string; employee_code: string; name: string; status: 'active' | 'inactive'; user_id: number | null; member_name: string | null; public_title: string | null; public_bio: string | null; is_public: boolean | null; is_my_profile?: boolean };
export type TeacherMemberCandidate = { id: number; name: string };
export type ClassTeacherAssignment = {
  id: string;
  class_id: string;
  teacher_profile_id: string;
  teacher_name: string;
  member_name: string | null;
  employee_code: string;
  academic_year_id: string;
  section_id: string | null;
  section_name: string | null;
};
export type SubjectOffering = {
  id: string;
  class_id: string;
  academic_year_id: string;
  section_id: string | null;
  section_name: string | null;
  subject_id: string;
  subject_code: string;
  subject_name: string;
};
export type Course = {
  id: string;
  code: string;
  title: string;
  description: string | null;
  status: 'active' | 'draft';
  is_public: boolean;
  price_paise: number;
  academic_year_id: string | null;
  academic_year: string | null;
  subject_id: string | null;
  subject: string | null;
  instructors: { id: number; name: string }[];
  lessons_count: number;
  enrollments_count: number;
};
export type CourseLesson = {
  id: string;
  course_id: string;
  title: string;
  content: string | null;
  resource_type: 'video' | 'document' | null;
  resource_url: string | null;
  prerequisites: { id: string; title: string }[];
  prerequisites_met: boolean;
  position: number;
  status: 'active' | 'archived';
  my_progress: 'not_started' | 'started' | 'completed' | null;
};
export type CourseEnrollment = { id: string; student_id: string; student_code: string; student_name: string; status: 'active'; enrolled_at: string };
export type CourseProgress = { student_id: string; student_code: string; student_name: string; completed_lessons: number; total_lessons: number; completion_percent: number };
export type StudentMemberCandidate = { id: number; name: string };
export type AssignmentSubmission = {
  id: string;
  status: 'submitted' | 'graded';
  submission_text: string;
  submitted_at: string;
  score: number | null;
  feedback: string | null;
  graded_at: string | null;
  attachments: AssignmentAttachment[];
  student_name?: string;
  student_code?: string;
};
export type CourseAssignment = {
  id: string;
  course_id: string;
  lesson_id: string | null;
  lesson_title: string | null;
  title: string;
  instructions: string | null;
  due_at: string | null;
  max_points: number;
  status: 'draft' | 'published';
  submissions_count: number | null;
  my_submission?: AssignmentSubmission | null;
  resource_attachments: AssignmentAttachment[];
};
export type AssignmentAttachment = { id: string; name: string; mime_type: string; size_bytes: number };
export type GradingRubricCriterion = { id: string; label: string; weight: number };
export type BankQuestion = { id: string; prompt: string; topic: string | null; difficulty: number; type: 'single_choice' | 'multiple_select' | 'short_answer'; grading_rubric?: GradingRubricCriterion[] | null; options: { id: string; text: string; is_correct?: boolean }[] };
export type QuestionCsvPreview = {
  valid: boolean;
  row_count: number;
  valid_count: number;
  errors: string[];
  rows: { row_number: number; valid: boolean; question: { prompt: string; topic: string | null; difficulty: number | string; type: string; options: { text: string; is_correct: boolean }[] }; errors: Record<string, string[]> }[];
};
export type LearningAssessment = { id: string; title: string; instructions: string | null; duration_minutes: number | null; attempts_allowed: number; randomize_questions: boolean; randomized_question_count: number | null; opens_at: string | null; closes_at: string | null; status: 'draft' | 'published'; question_count: number; my_attempts?: AssessmentAttemptSummary[] };
export type AssessmentAnalytics = { assessment_id: string; question_pool_count: number; randomized_question_count: number | null; attempt_count: number; completed_attempt_count: number; average_score_percent: number | null; question_analytics: { question_id: string; prompt: string; type: BankQuestion['type']; points: number; attempts_presented: number; correct_count: number; accuracy_percent: number | null }[] };
export type AssessmentAttemptReview = { attempt: AssessmentAttemptSummary & { student_name: string | null; student_code: string | null }; questions: { question_id: string; prompt: string; type: BankQuestion['type']; points: number; points_awarded: number; is_correct: boolean | null; response_text: string | null; grader_feedback: string | null; graded_at: string | null; grading_rubric: GradingRubricCriterion[] | null; rubric_scores: { criterion_id: string; score: number }[] | null; selected_options: { id: string; text: string }[]; correct_options: { id: string; text: string }[] }[] };
export type AssessmentAttemptForReview = AssessmentAttemptSummary & { student_id: string | null; student_name: string | null; student_code: string | null };
export type AssessmentAnalyticsFilters = { from?: string; to?: string; student_id?: string };
export type AssessmentAttemptStudentResult = { attempt: AssessmentAttemptSummary; answers: { question_id: string; prompt: string; type: BankQuestion['type']; points: number; points_awarded: number; response_text: string | null; selected_options: { id: string; text: string }[]; grader_feedback: string | null; pending_review: boolean }[] };
export type AssessmentAttemptSummary = { id: string; attempt_number: number; status: 'in_progress' | 'submitted' | 'expired'; review_status: 'not_required' | 'pending' | 'reviewed'; score: number | null; max_points: number; started_at: string; expires_at: string | null; submitted_at: string | null; student_name?: string | null; student_code?: string | null };
export type AssessmentAttempt = AssessmentAttemptSummary & { questions: { id: string; prompt: string; type: BankQuestion['type']; points: number; options: { id: string; text: string }[]; selected_option_ids: string[]; response_text: string | null }[] };
export type GuardianCandidate = { id: number; name: string; email: string };
export type GuardianStudentLink = { id: string; student_id: string; student_name: string; student_code: string; guardian_user_id: number; guardian_name: string; guardian_email: string; relationship: string; linked_at: string };
export type GuardianStudentSummary = {
  student_id: string; student_name: string; student_code: string; relationship: string;
  attendance: { sessions_recorded: number; present: number; rate_percent: number | null; recent: { date: string | null; class_name: string | null; status: string }[] } | null;
  learning: { courses_enrolled: number; completed_lessons: number; total_lessons: number; completion_percent: number; assignments: { id: string; title: string; course_title: string | null; due_at: string | null; submission_status: 'submitted' | 'graded' | null }[] } | null;
  results: { assessments_completed: number; average_percent: number | null; recent: { title: string; score: number; max_points: number; completed_at: string | null }[]; upcoming_exams: { title: string; course_title: string | null; opens_at: string; closes_at: string | null }[] } | null;
};
export type TutorReply = { answer: string; usage: { input_tokens: number; output_tokens: number } };
export type AiTeacherDraftType = 'lesson_plan' | 'course_outline' | 'learning_objectives' | 'mcqs' | 'practice_exercises' | 'homework_questions' | 'revision_notes' | 'rubric';
export type AiTeacherDraft = { draft: string; human_review_required: true; usage: { input_tokens: number; output_tokens: number } };
export type Announcement = { id: string; title: string; body: string; audience: 'all_members' | 'students' | 'guardians' | 'teachers'; status: 'published' | 'scheduled' | 'withdrawn'; published_at: string | null; scheduled_at: string | null; author_name: string | null; recipient_count?: number | null };
export type WorkspaceNotification = { id: string; type: 'announcement' | 'feedback'; announcement_id: string | null; feedback_id: string | null; title: string; body: string; created_at: string; read_at: string | null };
export type WorkspaceNotificationList = { unread_count: number; items: WorkspaceNotification[] };
export type ClassTeacherAssignmentEvent = {
  id: string;
  assignment_id: string;
  action: 'assigned' | 'reassigned' | 'removed';
  from_teacher: string | null;
  to_teacher: string | null;
  section_name: string | null;
  actor_name: string;
  created_at: string;
};
export type TimetableEntry = {
  id: string;
  academic_year_id: string;
  academic_year: string | null;
  class_id: string;
  class_name: string | null;
  section_id: string | null;
  section_name: string | null;
  subject: string;
  subject_id: string | null;
  teacher_profile_id: string | null;
  teacher_name: string | null;
  weekday: number;
  starts_at: string;
  ends_at: string;
  room: string | null;
};

const API = (import.meta.env.VITE_API_BASE_URL || '/api/v1').replace(/\/$/, '');
const TOKEN_KEY = 'paathnexus.session-token';
const ORGANIZATION_KEY = 'paathnexus.active-organization';

export class ApiError extends Error {
  constructor(message: string, readonly status: number) {
    super(message);
  }
}

async function request<T>(path: string, options: RequestInit = {}, token?: string): Promise<T> {
  let response: Response;
  try {
    response = await fetch(`${API}${path}`, {
      ...options,
      headers: {
        Accept: 'application/json',
        ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...options.headers,
      },
    });
  } catch {
    throw new ApiError('PaathNexus API is unavailable. Start the Laravel service and try again.', 0);
  }

  const body = await response.json().catch(() => ({}));
  if (!response.ok) {
    const validation = Object.values(body.errors ?? {}).flat()[0];
    throw new ApiError(validation ?? body.message ?? 'The request could not be completed.', response.status);
  }

  return body as T;
}

export async function signIn(email: string, password: string): Promise<Session> {
  const response = await request<{ data: Session }>('/auth/login', {
    method: 'POST', body: JSON.stringify({ email, password }),
  });
  saveToken(response.data.token);
  return response.data;
}

export async function requestPasswordReset(email: string): Promise<void> {
  await request('/auth/forgot-password', { method: 'POST', body: JSON.stringify({ email }) });
}

export async function resetPassword(input: { email: string; token: string; password: string; password_confirmation: string }): Promise<void> {
  await request('/auth/reset-password', { method: 'POST', body: JSON.stringify(input) });
}

export async function resendEmailVerification(email: string): Promise<void> {
  await request('/auth/resend-verification', { method: 'POST', body: JSON.stringify({ email }) });
}

export async function register(input: {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  organization_name: string;
  organization_type: string;
}): Promise<Session | VerificationRequired> {
  const response = await request<{ data: Session | VerificationRequired }>('/auth/register', {
    method: 'POST', body: JSON.stringify(input),
  });
  if (!('verification_required' in response.data)) saveToken(response.data.token);
  return response.data;
}

export async function currentSession(token: string): Promise<Omit<Session, 'token'>> {
  const response = await request<{ data: Omit<Session, 'token'> }>('/auth/me', {}, token);
  return response.data;
}

export async function updateAccountProfile(token: string, input: { name: string }): Promise<User> {
  const response = await request<{ data: { user: User } }>('/auth/profile', {
    method: 'PUT', body: JSON.stringify(input),
  }, token);
  return response.data.user;
}

export async function acceptOrganizationInvitation(token: string, invitationToken: string): Promise<{ id: string; name: string; role: string }> {
  const response = await request<{ data: { organization: { id: string; name: string; role: string } } }>('/invitations/accept', { method: 'POST', body: JSON.stringify({ token: invitationToken }) }, token);
  return response.data.organization;
}

export async function registerWithInvitation(input: { token: string; name: string; password: string; password_confirmation: string }): Promise<Session | VerificationRequired> {
  const response = await request<{ data: Session | VerificationRequired }>('/auth/register-invitation', { method: 'POST', body: JSON.stringify(input) });
  if (!('verification_required' in response.data)) saveToken(response.data.token);
  return response.data;
}

export async function createOrganizationInvitation(token: string, organizationId: string, input: { email: string; role: 'teacher' | 'student' | 'guardian' }): Promise<{ invitation: OrganizationInvitation; token: string }> {
  const response = await request<{ data: OrganizationInvitation; token: string }>(`/organizations/${encodeURIComponent(organizationId)}/invitations`, { method: 'POST', body: JSON.stringify(input) }, token);
  return { invitation: response.data, token: response.token };
}

export type OrganizationInvitation = { id: string; email: string; role: 'teacher' | 'student' | 'guardian'; status: 'pending' | 'accepted' | 'expired' | 'revoked'; inviter_name: string | null; expires_at: string; accepted_at: string | null; created_at: string };

export async function listOrganizationInvitations(token: string, organizationId: string): Promise<OrganizationInvitation[]> {
  const response = await request<{ data: OrganizationInvitation[] }>(`/organizations/${encodeURIComponent(organizationId)}/invitations`, {}, token);
  return response.data;
}

export async function revokeOrganizationInvitation(token: string, organizationId: string, invitationId: string): Promise<void> {
  await request(`/organizations/${encodeURIComponent(organizationId)}/invitations/${encodeURIComponent(invitationId)}`, { method: 'DELETE' }, token);
}

export async function getDashboardSummary(token: string, organizationId: string): Promise<DashboardSummary> {
  const response = await request<{ data: DashboardSummary }>(`/organizations/${encodeURIComponent(organizationId)}/dashboard`, {}, token);
  return response.data;
}

export async function getStudentDashboard(token: string, organizationId: string): Promise<StudentDashboard> {
  const response = await request<{ data: StudentDashboard }>(`/organizations/${encodeURIComponent(organizationId)}/student/dashboard`, {}, token);
  return response.data;
}

export async function listStudentStudyPlans(token: string, organizationId: string): Promise<StudentStudyPlan[]> {
  const response = await request<{ data: StudentStudyPlan[] }>(`/organizations/${encodeURIComponent(organizationId)}/student/study-plans`, {}, token);
  return response.data;
}

export async function createStudentStudyPlan(token: string, organizationId: string, input: { title: string; scheduled_at: string; duration_minutes: number; course_id?: string }): Promise<StudentStudyPlan> {
  const response = await request<{ data: StudentStudyPlan }>(`/organizations/${encodeURIComponent(organizationId)}/student/study-plans`, {
    method: 'POST', body: JSON.stringify(input),
  }, token);
  return response.data;
}

export async function completeStudentStudyPlan(token: string, organizationId: string, planId: string): Promise<StudentStudyPlan> {
  const response = await request<{ data: StudentStudyPlan }>(`/organizations/${encodeURIComponent(organizationId)}/student/study-plans/${encodeURIComponent(planId)}/complete`, {
    method: 'POST',
  }, token);
  return response.data;
}

export async function getAcademicReport(token: string, organizationId: string, filters: { class_id?: string; student_id?: string; from?: string; to?: string } = {}): Promise<AcademicReport> {
  const params = new URLSearchParams();
  if (filters.class_id) params.set('class_id', filters.class_id);
  if (filters.student_id) params.set('student_id', filters.student_id);
  if (filters.from) params.set('from', filters.from);
  if (filters.to) params.set('to', filters.to);
  const query = params.toString();
  const response = await request<{ data: AcademicReport }>(`/organizations/${encodeURIComponent(organizationId)}/reports/academic${query ? `?${query}` : ''}`, {}, token);
  return response.data;
}

export async function listPublicCourses(query = '', page = 1): Promise<{ items: PublicCourse[]; total: number; page: number; lastPage: number }> {
  const search = new URLSearchParams(); if (query.trim()) search.set('q', query.trim());
  search.set('page', String(page));
  const response = await request<{ data: PublicCourse[]; meta: { total: number; current_page: number; last_page: number } }>(`/catalog/courses${search.size ? `?${search}` : ''}`);
  return { items: response.data, total: response.meta.total, page: response.meta.current_page, lastPage: response.meta.last_page };
}

export async function listPublicEducators(query = '', page = 1): Promise<{ items: PublicEducator[]; total: number; page: number; lastPage: number }> {
  const search = new URLSearchParams(); if (query.trim()) search.set('q', query.trim());
  search.set('page', String(page));
  const response = await request<{ data: PublicEducator[]; meta: { total: number; current_page: number; last_page: number } }>(`/catalog/educators?${search}`);
  return { items: response.data, total: response.meta.total, page: response.meta.current_page, lastPage: response.meta.last_page };
}

export async function enrollPublicCourse(token: string, courseId: string): Promise<{ id: string; course_id: string; course_title: string; organization_id: string; organization_name: string; status: 'active'; enrolled_at: string | null }> {
  const response = await request<{ data: { id: string; course_id: string; course_title: string; organization_id: string; organization_name: string; status: 'active'; enrolled_at: string | null } }>(`/catalog/courses/${encodeURIComponent(courseId)}/enroll`, { method: 'POST' }, token);
  return response.data;
}

export async function listPublicPlans(): Promise<PublicPlan[]> {
  const response = await request<{ data: PublicPlan[] }>('/catalog/plans'); return response.data;
}

export async function listAcademicYears(token: string, organizationId: string): Promise<AcademicYear[]> {
  const response = await request<{ data: AcademicYear[] }>(`/organizations/${encodeURIComponent(organizationId)}/academic-years`, {}, token);
  return response.data;
}

export async function createAcademicYear(token: string, organizationId: string, input: { name: string; starts_on: string; ends_on: string; status: 'planned' | 'active' }): Promise<AcademicYear> {
  const response = await request<{ data: AcademicYear }>(`/organizations/${encodeURIComponent(organizationId)}/academic-years`, {
    method: 'POST', body: JSON.stringify(input),
  }, token);
  return response.data;
}

export async function activateAcademicYear(token: string, organizationId: string, yearId: string): Promise<AcademicYear> {
  const response = await request<{ data: AcademicYear }>(`/organizations/${encodeURIComponent(organizationId)}/academic-years/${encodeURIComponent(yearId)}/activate`, {
    method: 'POST',
  }, token);
  return response.data;
}

export async function listSections(token: string, organizationId: string): Promise<OrganizationSection[]> {
  const response = await request<{ data: OrganizationSection[] }>(`/organizations/${encodeURIComponent(organizationId)}/sections`, {}, token);
  return response.data;
}

export async function createSection(token: string, organizationId: string, classId: string, name: string): Promise<OrganizationSection> {
  const response = await request<{ data: OrganizationSection }>(`/organizations/${encodeURIComponent(organizationId)}/classes/${encodeURIComponent(classId)}/sections`, {
    method: 'POST', body: JSON.stringify({ name }),
  }, token);
  return response.data;
}

export async function listSubjects(token: string, organizationId: string): Promise<OrganizationSubject[]> {
  const response = await request<{ data: OrganizationSubject[] }>(`/organizations/${encodeURIComponent(organizationId)}/subjects`, {}, token);
  return response.data;
}

export async function createSubject(token: string, organizationId: string, input: { code: string; name: string }): Promise<OrganizationSubject> {
  const response = await request<{ data: OrganizationSubject }>(`/organizations/${encodeURIComponent(organizationId)}/subjects`, {
    method: 'POST', body: JSON.stringify(input),
  }, token);
  return response.data;
}

export async function listTeacherProfiles(token: string, organizationId: string): Promise<TeacherProfile[]> {
  const response = await request<{ data: TeacherProfile[] }>(`/organizations/${encodeURIComponent(organizationId)}/teachers`, {}, token);
  return response.data;
}

export async function createTeacherProfile(token: string, organizationId: string, input: { employee_code: string; name: string }): Promise<TeacherProfile> {
  const response = await request<{ data: TeacherProfile }>(`/organizations/${encodeURIComponent(organizationId)}/teachers`, {
    method: 'POST', body: JSON.stringify(input),
  }, token);
  return response.data;
}

export async function listTeacherMemberCandidates(token: string, organizationId: string): Promise<TeacherMemberCandidate[]> {
  const response = await request<{ data: TeacherMemberCandidate[] }>(`/organizations/${encodeURIComponent(organizationId)}/teachers/member-candidates`, {}, token);
  return response.data;
}

export async function linkTeacherMember(token: string, organizationId: string, teacherId: string, userId: number | null): Promise<TeacherProfile> {
  const response = await request<{ data: TeacherProfile }>(`/organizations/${encodeURIComponent(organizationId)}/teachers/${encodeURIComponent(teacherId)}/member`, {
    method: 'PUT', body: JSON.stringify({ user_id: userId }),
  }, token);
  return response.data;
}

export async function updateTeacherPublicProfile(token: string, organizationId: string, teacherId: string, input: { public_title: string | null; public_bio: string | null; is_public: boolean }): Promise<TeacherProfile> {
  const response = await request<{ data: TeacherProfile }>(`/organizations/${encodeURIComponent(organizationId)}/teachers/${encodeURIComponent(teacherId)}/public-profile`, {
    method: 'PUT', body: JSON.stringify(input),
  }, token);
  return response.data;
}

export async function listClassTeachers(token: string, organizationId: string, classId: string, yearId?: string): Promise<ClassTeacherAssignment[]> {
  const suffix = yearId ? `?academic_year_id=${encodeURIComponent(yearId)}` : '';
  const response = await request<{ data: ClassTeacherAssignment[] }>(`/organizations/${encodeURIComponent(organizationId)}/classes/${encodeURIComponent(classId)}/teachers${suffix}`, {}, token);
  return response.data;
}

export async function assignClassTeacher(token: string, organizationId: string, classId: string, input: { teacher_profile_id: string; academic_year_id: string; section_id?: string }): Promise<ClassTeacherAssignment> {
  const response = await request<{ data: ClassTeacherAssignment }>(`/organizations/${encodeURIComponent(organizationId)}/classes/${encodeURIComponent(classId)}/teachers`, {
    method: 'POST', body: JSON.stringify(input),
  }, token);
  return response.data;
}

export async function updateClassTeacher(token: string, organizationId: string, classId: string, assignmentId: string, teacherProfileId: string): Promise<ClassTeacherAssignment> {
  const response = await request<{ data: ClassTeacherAssignment }>(`/organizations/${encodeURIComponent(organizationId)}/classes/${encodeURIComponent(classId)}/teachers/${encodeURIComponent(assignmentId)}`, {
    method: 'PUT', body: JSON.stringify({ teacher_profile_id: teacherProfileId }),
  }, token);
  return response.data;
}

export async function endClassTeacher(token: string, organizationId: string, classId: string, assignmentId: string): Promise<void> {
  await request(`/organizations/${encodeURIComponent(organizationId)}/classes/${encodeURIComponent(classId)}/teachers/${encodeURIComponent(assignmentId)}`, { method: 'DELETE' }, token);
}

export async function listClassTeacherHistory(token: string, organizationId: string, classId: string, yearId?: string): Promise<ClassTeacherAssignmentEvent[]> {
  const suffix = yearId ? `?academic_year_id=${encodeURIComponent(yearId)}` : '';
  const response = await request<{ data: ClassTeacherAssignmentEvent[] }>(`/organizations/${encodeURIComponent(organizationId)}/classes/${encodeURIComponent(classId)}/teacher-assignment-history${suffix}`, {}, token);
  return response.data;
}

export async function listSubjectOfferings(token: string, organizationId: string, classId: string, yearId?: string): Promise<SubjectOffering[]> {
  const suffix = yearId ? `?academic_year_id=${encodeURIComponent(yearId)}` : '';
  const response = await request<{ data: SubjectOffering[] }>(`/organizations/${encodeURIComponent(organizationId)}/classes/${encodeURIComponent(classId)}/subject-offerings${suffix}`, {}, token);
  return response.data;
}

export async function createSubjectOffering(token: string, organizationId: string, classId: string, input: { academic_year_id: string; subject_id: string; section_id?: string }): Promise<SubjectOffering> {
  const response = await request<{ data: SubjectOffering }>(`/organizations/${encodeURIComponent(organizationId)}/classes/${encodeURIComponent(classId)}/subject-offerings`, {
    method: 'POST', body: JSON.stringify(input),
  }, token);
  return response.data;
}

export async function endSubjectOffering(token: string, organizationId: string, classId: string, offeringId: string): Promise<void> {
  await request(`/organizations/${encodeURIComponent(organizationId)}/classes/${encodeURIComponent(classId)}/subject-offerings/${encodeURIComponent(offeringId)}`, { method: 'DELETE' }, token);
}

export async function listCourses(token: string, organizationId: string): Promise<Course[]> {
  const response = await request<{ data: Course[] }>(`/organizations/${encodeURIComponent(organizationId)}/courses`, {}, token);
  return response.data;
}

export async function createCourse(token: string, organizationId: string, input: { code: string; title: string; description?: string; price_paise?: number }): Promise<Course> {
  const response = await request<{ data: Course }>(`/organizations/${encodeURIComponent(organizationId)}/courses`, { method: 'POST', body: JSON.stringify(input) }, token);
  return response.data;
}

export async function updateCourse(token: string, organizationId: string, courseId: string, input: { title: string; description?: string; status: 'active' | 'draft'; is_public?: boolean; price_paise?: number }): Promise<Course> {
  const response = await request<{ data: Course }>(`/organizations/${encodeURIComponent(organizationId)}/courses/${encodeURIComponent(courseId)}`, { method: 'PUT', body: JSON.stringify(input) }, token);
  return response.data;
}

export async function listCourseInstructorCandidates(token: string, organizationId: string): Promise<{ id: number; name: string }[]> {
  const response = await request<{ data: { id: number; name: string }[] }>(`/organizations/${encodeURIComponent(organizationId)}/course-instructors`, {}, token);
  return response.data;
}

export async function updateCourseInstructors(token: string, organizationId: string, courseId: string, instructorIds: number[]): Promise<Course> {
  const response = await request<{ data: Course }>(`/organizations/${encodeURIComponent(organizationId)}/courses/${encodeURIComponent(courseId)}/instructors`, { method: 'PUT', body: JSON.stringify({ instructor_ids: instructorIds }) }, token);
  return response.data;
}

export async function listCourseLessons(token: string, organizationId: string, courseId: string): Promise<CourseLesson[]> {
  const response = await request<{ data: CourseLesson[] }>(`/organizations/${encodeURIComponent(organizationId)}/courses/${encodeURIComponent(courseId)}/lessons`, {}, token);
  return response.data;
}

export async function createCourseLesson(token: string, organizationId: string, courseId: string, input: { title: string; content?: string; resource_type?: 'video' | 'document'; resource_url?: string }): Promise<CourseLesson> {
  const response = await request<{ data: CourseLesson }>(`/organizations/${encodeURIComponent(organizationId)}/courses/${encodeURIComponent(courseId)}/lessons`, { method: 'POST', body: JSON.stringify(input) }, token);
  return response.data;
}

export async function updateCourseLesson(token: string, organizationId: string, courseId: string, lessonId: string, input: { title: string; content?: string; resource_type?: 'video' | 'document' | null; resource_url?: string | null; prerequisite_ids?: string[]; status: 'active' | 'archived' }): Promise<CourseLesson> {
  const response = await request<{ data: CourseLesson }>(`/organizations/${encodeURIComponent(organizationId)}/courses/${encodeURIComponent(courseId)}/lessons/${encodeURIComponent(lessonId)}`, { method: 'PUT', body: JSON.stringify(input) }, token);
  return response.data;
}

export async function listCourseEnrollments(token: string, organizationId: string, courseId: string): Promise<CourseEnrollment[]> {
  const response = await request<{ data: CourseEnrollment[] }>(`/organizations/${encodeURIComponent(organizationId)}/courses/${encodeURIComponent(courseId)}/enrollments`, {}, token);
  return response.data;
}

export async function enrollInCourse(token: string, organizationId: string, courseId: string, studentId: string): Promise<void> {
  await request(`/organizations/${encodeURIComponent(organizationId)}/courses/${encodeURIComponent(courseId)}/enrollments`, { method: 'POST', body: JSON.stringify({ student_id: studentId }) }, token);
}

export async function listCourseProgress(token: string, organizationId: string, courseId: string): Promise<CourseProgress[]> {
  const response = await request<{ data: CourseProgress[] }>(`/organizations/${encodeURIComponent(organizationId)}/courses/${encodeURIComponent(courseId)}/progress`, {}, token);
  return response.data;
}

export async function updateLessonProgress(token: string, organizationId: string, courseId: string, lessonId: string, status: 'started' | 'completed', studentId?: string): Promise<void> {
  await request(`/organizations/${encodeURIComponent(organizationId)}/courses/${encodeURIComponent(courseId)}/lessons/${encodeURIComponent(lessonId)}/progress`, {
    method: 'PUT', body: JSON.stringify({ status, ...(studentId ? { student_id: studentId } : {}) }),
  }, token);
}

export async function listStudentMemberCandidates(token: string, organizationId: string): Promise<StudentMemberCandidate[]> {
  const response = await request<{ data: StudentMemberCandidate[] }>(`/organizations/${encodeURIComponent(organizationId)}/students/member-candidates`, {}, token);
  return response.data;
}

export async function linkStudentMember(token: string, organizationId: string, studentId: string, userId: number | null): Promise<void> {
  await request(`/organizations/${encodeURIComponent(organizationId)}/students/${encodeURIComponent(studentId)}/member`, { method: 'PUT', body: JSON.stringify({ user_id: userId }) }, token);
}

export async function listCourseAssignments(token: string, organizationId: string, courseId: string): Promise<CourseAssignment[]> {
  const response = await request<{ data: CourseAssignment[] }>(`/organizations/${encodeURIComponent(organizationId)}/courses/${encodeURIComponent(courseId)}/assignments`, {}, token);
  return response.data;
}

export async function createCourseAssignment(token: string, organizationId: string, courseId: string, input: { title: string; instructions?: string; due_at?: string; max_points: number; lesson_id?: string }): Promise<CourseAssignment> {
  const response = await request<{ data: CourseAssignment }>(`/organizations/${encodeURIComponent(organizationId)}/courses/${encodeURIComponent(courseId)}/assignments`, { method: 'POST', body: JSON.stringify(input) }, token);
  return response.data;
}

export async function submitCourseAssignment(token: string, organizationId: string, assignmentId: string, submissionText: string, files: File[] = []): Promise<AssignmentSubmission> {
  let body: BodyInit;
  if (files.length) {
    const form = new FormData();
    if (submissionText.trim()) form.append('submission_text', submissionText.trim());
    files.forEach((file) => form.append('attachments[]', file));
    body = form;
  } else body = JSON.stringify({ submission_text: submissionText });
  const response = await request<{ data: AssignmentSubmission }>(`/organizations/${encodeURIComponent(organizationId)}/assignments/${encodeURIComponent(assignmentId)}/submissions`, { method: 'POST', body }, token);
  return response.data;
}
export async function uploadAssignmentResources(token: string, organizationId: string, courseId: string, assignmentId: string, files: File[]): Promise<AssignmentAttachment[]> {
  const form = new FormData(); files.forEach((file) => form.append('attachments[]', file));
  const response = await request<{ data: AssignmentAttachment[] }>(`/organizations/${encodeURIComponent(organizationId)}/courses/${encodeURIComponent(courseId)}/assignments/${encodeURIComponent(assignmentId)}/attachments`, { method: 'POST', body: form }, token);
  return response.data;
}
export async function downloadAssignmentAttachment(token: string, organizationId: string, courseId: string, assignmentId: string, attachmentId: string): Promise<Blob> {
  const url = `${API}/organizations/${encodeURIComponent(organizationId)}/courses/${encodeURIComponent(courseId)}/assignments/${encodeURIComponent(assignmentId)}/attachments/${encodeURIComponent(attachmentId)}`;
  let response: Response;
  try { response = await fetch(url, { headers: { Accept: 'application/octet-stream', Authorization: `Bearer ${token}` } }); }
  catch { throw new ApiError('PaathNexus API is unavailable. Start the Laravel service and try again.', 0); }
  if (!response.ok) {
    const body = await response.json().catch(() => ({}));
    throw new ApiError(body.message ?? 'Could not download this attachment.', response.status);
  }
  return response.blob();
}

export async function listAssignmentSubmissions(token: string, organizationId: string, courseId: string, assignmentId: string): Promise<AssignmentSubmission[]> {
  const response = await request<{ data: AssignmentSubmission[] }>(`/organizations/${encodeURIComponent(organizationId)}/courses/${encodeURIComponent(courseId)}/assignments/${encodeURIComponent(assignmentId)}/submissions`, {}, token);
  return response.data;
}

export async function gradeCourseSubmission(token: string, organizationId: string, courseId: string, assignmentId: string, submissionId: string, input: { score: number; feedback?: string }): Promise<AssignmentSubmission> {
  const response = await request<{ data: AssignmentSubmission }>(`/organizations/${encodeURIComponent(organizationId)}/courses/${encodeURIComponent(courseId)}/assignments/${encodeURIComponent(assignmentId)}/submissions/${encodeURIComponent(submissionId)}/grade`, { method: 'PUT', body: JSON.stringify(input) }, token);
  return response.data;
}

export async function listBankQuestions(token: string, organizationId: string, courseId: string): Promise<BankQuestion[]> {
  const response = await request<{ data: BankQuestion[] }>(`/organizations/${encodeURIComponent(organizationId)}/courses/${encodeURIComponent(courseId)}/questions`, {}, token); return response.data;
}
export async function createBankQuestion(token: string, organizationId: string, courseId: string, input: { prompt: string; topic?: string; difficulty: number; type: BankQuestion['type']; grading_rubric?: GradingRubricCriterion[]; options: { text: string; is_correct: boolean }[] }): Promise<BankQuestion> {
  const response = await request<{ data: BankQuestion }>(`/organizations/${encodeURIComponent(organizationId)}/courses/${encodeURIComponent(courseId)}/questions`, { method: 'POST', body: JSON.stringify(input) }, token); return response.data;
}
export async function previewQuestionCsv(token: string, organizationId: string, courseId: string, file: File): Promise<QuestionCsvPreview> {
  const form = new FormData(); form.append('file', file);
  const response = await request<{ data: QuestionCsvPreview }>(`/organizations/${encodeURIComponent(organizationId)}/courses/${encodeURIComponent(courseId)}/questions/import/preview`, { method: 'POST', body: form }, token); return response.data;
}
export async function importQuestionCsv(token: string, organizationId: string, courseId: string, file: File): Promise<{ imported_count: number; questions: BankQuestion[] }> {
  const form = new FormData(); form.append('file', file);
  const response = await request<{ data: { imported_count: number; questions: BankQuestion[] } }>(`/organizations/${encodeURIComponent(organizationId)}/courses/${encodeURIComponent(courseId)}/questions/import`, { method: 'POST', body: form }, token); return response.data;
}
export async function listAssessments(token: string, organizationId: string, courseId: string): Promise<LearningAssessment[]> {
  const response = await request<{ data: LearningAssessment[] }>(`/organizations/${encodeURIComponent(organizationId)}/courses/${encodeURIComponent(courseId)}/assessments`, {}, token); return response.data;
}
export async function createAssessment(token: string, organizationId: string, courseId: string, input: { title: string; instructions?: string; duration_minutes?: number; attempts_allowed: number; randomize_questions?: boolean; randomized_question_count?: number; opens_at?: string; closes_at?: string; questions: { question_id: string; points: number }[] }): Promise<LearningAssessment> {
  const response = await request<{ data: LearningAssessment }>(`/organizations/${encodeURIComponent(organizationId)}/courses/${encodeURIComponent(courseId)}/assessments`, { method: 'POST', body: JSON.stringify(input) }, token); return response.data;
}
export async function startAssessmentAttempt(token: string, organizationId: string, assessmentId: string): Promise<AssessmentAttempt> {
  const response = await request<{ data: AssessmentAttempt }>(`/organizations/${encodeURIComponent(organizationId)}/assessments/${encodeURIComponent(assessmentId)}/attempts`, { method: 'POST' }, token); return response.data;
}
export async function saveAssessmentAnswer(token: string, organizationId: string, assessmentId: string, attemptId: string, questionId: string, optionIds: string[], responseText?: string): Promise<void> {
  const body = responseText === undefined ? { selected_option_ids: optionIds } : { response_text: responseText };
  await request(`/organizations/${encodeURIComponent(organizationId)}/assessments/${encodeURIComponent(assessmentId)}/attempts/${encodeURIComponent(attemptId)}/answers/${encodeURIComponent(questionId)}`, { method: 'PUT', body: JSON.stringify(body) }, token);
}
export async function submitAssessmentAttempt(token: string, organizationId: string, assessmentId: string, attemptId: string): Promise<AssessmentAttemptSummary> {
  const response = await request<{ data: AssessmentAttemptSummary }>(`/organizations/${encodeURIComponent(organizationId)}/assessments/${encodeURIComponent(assessmentId)}/attempts/${encodeURIComponent(attemptId)}/submit`, { method: 'POST' }, token); return response.data;
}
function attemptFilterQuery(filters: AssessmentAnalyticsFilters = {}): string {
  const params = new URLSearchParams();
  if (filters.from) params.set('from', filters.from);
  if (filters.to) params.set('to', filters.to);
  if (filters.student_id) params.set('student_id', filters.student_id);
  const query = params.toString();
  return query ? `?${query}` : '';
}
export async function listAssessmentAttempts(token: string, organizationId: string, courseId: string, assessmentId: string, filters: AssessmentAnalyticsFilters = {}): Promise<AssessmentAttemptForReview[]> {
  const response = await request<{ data: AssessmentAttemptForReview[] }>(`/organizations/${encodeURIComponent(organizationId)}/courses/${encodeURIComponent(courseId)}/assessments/${encodeURIComponent(assessmentId)}/attempts${attemptFilterQuery(filters)}`, {}, token); return response.data;
}
export async function downloadAssessmentGrades(token: string, organizationId: string, courseId: string, assessmentId: string, filters: AssessmentAnalyticsFilters = {}): Promise<Blob> {
  let response: Response;
  try {
    response = await fetch(`${API}/organizations/${encodeURIComponent(organizationId)}/courses/${encodeURIComponent(courseId)}/assessments/${encodeURIComponent(assessmentId)}/grades/export${attemptFilterQuery(filters)}`, {
      headers: { Accept: 'text/csv', Authorization: `Bearer ${token}` },
    });
  } catch {
    throw new ApiError('PaathNexus API is unavailable. Start the Laravel service and try again.', 0);
  }
  if (!response.ok) {
    const body = await response.json().catch(() => ({}));
    throw new ApiError(body.message ?? 'Could not export assessment grades.', response.status);
  }
  return response.blob();
}
export async function getAssessmentAnalytics(token: string, organizationId: string, courseId: string, assessmentId: string, filters: AssessmentAnalyticsFilters = {}): Promise<AssessmentAnalytics> {
  const response = await request<{ data: AssessmentAnalytics }>(`/organizations/${encodeURIComponent(organizationId)}/courses/${encodeURIComponent(courseId)}/assessments/${encodeURIComponent(assessmentId)}/analytics${attemptFilterQuery(filters)}`, {}, token); return response.data;
}
export async function getAssessmentAttemptReview(token: string, organizationId: string, courseId: string, assessmentId: string, attemptId: string): Promise<AssessmentAttemptReview> {
  const response = await request<{ data: AssessmentAttemptReview }>(`/organizations/${encodeURIComponent(organizationId)}/courses/${encodeURIComponent(courseId)}/assessments/${encodeURIComponent(assessmentId)}/attempts/${encodeURIComponent(attemptId)}/review`, {}, token); return response.data;
}
export async function getAssessmentAttemptResult(token: string, organizationId: string, assessmentId: string, attemptId: string): Promise<AssessmentAttemptStudentResult> {
  const response = await request<{ data: AssessmentAttemptStudentResult }>(`/organizations/${encodeURIComponent(organizationId)}/assessments/${encodeURIComponent(assessmentId)}/attempts/${encodeURIComponent(attemptId)}/result`, {}, token); return response.data;
}
export async function gradeShortAnswer(token: string, organizationId: string, courseId: string, assessmentId: string, attemptId: string, questionId: string, input: { points_awarded?: number; rubric_scores?: { criterion_id: string; score: number }[]; feedback?: string }): Promise<{ review_status: 'pending' | 'reviewed'; score: number; max_points: number }> {
  const response = await request<{ data: { review_status: 'pending' | 'reviewed'; score: number; max_points: number } }>(`/organizations/${encodeURIComponent(organizationId)}/courses/${encodeURIComponent(courseId)}/assessments/${encodeURIComponent(assessmentId)}/attempts/${encodeURIComponent(attemptId)}/answers/${encodeURIComponent(questionId)}/grade`, { method: 'PUT', body: JSON.stringify(input) }, token); return response.data;
}

export async function listGuardianCandidates(token: string, organizationId: string): Promise<GuardianCandidate[]> {
  const response = await request<{ data: GuardianCandidate[] }>(`/organizations/${encodeURIComponent(organizationId)}/guardian-candidates`, {}, token); return response.data;
}
export async function listGuardianLinks(token: string, organizationId: string): Promise<GuardianStudentLink[]> {
  const response = await request<{ data: GuardianStudentLink[] }>(`/organizations/${encodeURIComponent(organizationId)}/guardian-student-links`, {}, token); return response.data;
}
export async function linkGuardianStudent(token: string, organizationId: string, input: { student_id: string; guardian_user_id: number; relationship: string }): Promise<GuardianStudentLink> {
  const response = await request<{ data: GuardianStudentLink }>(`/organizations/${encodeURIComponent(organizationId)}/guardian-student-links`, { method: 'POST', body: JSON.stringify(input) }, token); return response.data;
}
export async function endGuardianLink(token: string, organizationId: string, linkId: string): Promise<void> {
  await request(`/organizations/${encodeURIComponent(organizationId)}/guardian-student-links/${encodeURIComponent(linkId)}`, { method: 'DELETE' }, token);
}
export async function listGuardianChildren(token: string, organizationId: string): Promise<GuardianStudentSummary[]> {
  const response = await request<{ data: GuardianStudentSummary[] }>(`/organizations/${encodeURIComponent(organizationId)}/guardian/students`, {}, token); return response.data;
}
export async function askAiTutor(token: string, organizationId: string, input: { question: string; course_id?: string; lesson_id?: string }): Promise<TutorReply> {
  const response = await request<{ data: TutorReply }>(`/organizations/${encodeURIComponent(organizationId)}/ai/tutor`, { method: 'POST', body: JSON.stringify(input) }, token); return response.data;
}
export async function createAiTeacherDraft(token: string, organizationId: string, input: { draft_type: AiTeacherDraftType; topic: string; grade_level?: string; instructions?: string; language: 'en' | 'hi' }): Promise<AiTeacherDraft> {
  const response = await request<{ data: AiTeacherDraft }>(`/organizations/${encodeURIComponent(organizationId)}/ai/teacher-assistant`, { method: 'POST', body: JSON.stringify(input) }, token); return response.data;
}
export async function listAnnouncements(token: string, organizationId: string): Promise<Announcement[]> {
  const response = await request<{ data: Announcement[] }>(`/organizations/${encodeURIComponent(organizationId)}/announcements`, {}, token); return response.data;
}
export async function createAnnouncement(token: string, organizationId: string, input: { title: string; body: string; audience: Announcement['audience']; scheduled_at?: string }): Promise<Announcement> {
  const response = await request<{ data: Announcement }>(`/organizations/${encodeURIComponent(organizationId)}/announcements`, { method: 'POST', body: JSON.stringify(input) }, token); return response.data;
}
export async function updateAnnouncement(token: string, organizationId: string, announcementId: string, input: { title: string; body: string }): Promise<Announcement> {
  const response = await request<{ data: Announcement }>(`/organizations/${encodeURIComponent(organizationId)}/announcements/${encodeURIComponent(announcementId)}`, { method: 'PATCH', body: JSON.stringify(input) }, token); return response.data;
}
export async function withdrawAnnouncement(token: string, organizationId: string, announcementId: string): Promise<Announcement> {
  const response = await request<{ data: Announcement }>(`/organizations/${encodeURIComponent(organizationId)}/announcements/${encodeURIComponent(announcementId)}/withdraw`, { method: 'POST' }, token); return response.data;
}
export async function restoreAnnouncement(token: string, organizationId: string, announcementId: string): Promise<Announcement> {
  const response = await request<{ data: Announcement }>(`/organizations/${encodeURIComponent(organizationId)}/announcements/${encodeURIComponent(announcementId)}/restore`, { method: 'POST' }, token); return response.data;
}
export async function listWorkspaceNotifications(token: string, organizationId: string): Promise<WorkspaceNotificationList> {
  const response = await request<{ data: WorkspaceNotificationList }>(`/organizations/${encodeURIComponent(organizationId)}/notifications`, {}, token); return response.data;
}
export async function markWorkspaceNotificationRead(token: string, organizationId: string, notificationId: string): Promise<void> {
  await request(`/organizations/${encodeURIComponent(organizationId)}/notifications/${encodeURIComponent(notificationId)}/read`, { method: 'POST' }, token);
}

export async function listTimetable(token: string, organizationId: string, yearId?: string): Promise<TimetableEntry[]> {
  const suffix = yearId ? `?academic_year_id=${encodeURIComponent(yearId)}` : '';
  const response = await request<{ data: TimetableEntry[] }>(`/organizations/${encodeURIComponent(organizationId)}/timetable-entries${suffix}`, {}, token);
  return response.data;
}

export type TimetableEntryInput = {
  academic_year_id: string; class_id: string; section_id?: string; subject_id: string; teacher_profile_id?: string;
  weekday: number; starts_at: string; ends_at: string; room?: string;
};
export async function createTimetableEntry(token: string, organizationId: string, input: TimetableEntryInput): Promise<TimetableEntry> {
  const response = await request<{ data: TimetableEntry }>(`/organizations/${encodeURIComponent(organizationId)}/timetable-entries`, {
    method: 'POST', body: JSON.stringify(input),
  }, token);
  return response.data;
}
export async function updateTimetableEntry(token: string, organizationId: string, entryId: string, input: TimetableEntryInput): Promise<TimetableEntry> {
  const response = await request<{ data: TimetableEntry }>(`/organizations/${encodeURIComponent(organizationId)}/timetable-entries/${encodeURIComponent(entryId)}`, {
    method: 'PUT', body: JSON.stringify(input),
  }, token);
  return response.data;
}

export async function listScheduleExceptions(token: string, organizationId: string, yearId?: string): Promise<ScheduleException[]> {
  const suffix = yearId ? `?academic_year_id=${encodeURIComponent(yearId)}` : '';
  const response = await request<{ data: ScheduleException[] }>(`/organizations/${encodeURIComponent(organizationId)}/schedule-exceptions${suffix}`, {}, token);
  return response.data;
}

export async function createScheduleException(token: string, organizationId: string, input: ScheduleExceptionInput): Promise<ScheduleException> {
  const response = await request<{ data: ScheduleException }>(`/organizations/${encodeURIComponent(organizationId)}/schedule-exceptions`, {
    method: 'POST', body: JSON.stringify(input),
  }, token);
  return response.data;
}

export async function updateScheduleException(token: string, organizationId: string, exceptionId: string, input: ScheduleExceptionUpdate): Promise<ScheduleException> {
  const response = await request<{ data: ScheduleException }>(`/organizations/${encodeURIComponent(organizationId)}/schedule-exceptions/${encodeURIComponent(exceptionId)}`, {
    method: 'PUT', body: JSON.stringify(input),
  }, token);
  return response.data;
}

export async function cancelScheduleException(token: string, organizationId: string, exceptionId: string): Promise<ScheduleException> {
  const response = await request<{ data: ScheduleException }>(`/organizations/${encodeURIComponent(organizationId)}/schedule-exceptions/${encodeURIComponent(exceptionId)}/cancel`, {
    method: 'POST',
  }, token);
  return response.data;
}

export async function listTasks(token: string, organizationId: string): Promise<OrganizationTask[]> {
  const response = await request<{ data: OrganizationTask[] }>(`/organizations/${encodeURIComponent(organizationId)}/tasks`, {}, token);
  return response.data;
}

export async function listTaskAssignees(token: string, organizationId: string): Promise<TaskAssignee[]> {
  const response = await request<{ data: TaskAssignee[] }>(`/organizations/${encodeURIComponent(organizationId)}/task-assignees`, {}, token);
  return response.data;
}

export async function createTask(token: string, organizationId: string, input: { title: string; description?: string; due_at?: string; assigned_to?: string }): Promise<OrganizationTask> {
  const response = await request<{ data: OrganizationTask }>(`/organizations/${encodeURIComponent(organizationId)}/tasks`, {
    method: 'POST', body: JSON.stringify(input),
  }, token);
  return response.data;
}

export async function updateTask(token: string, organizationId: string, taskId: string, input: { title: string; description?: string | null; due_at?: string | null; assigned_to?: string | null }): Promise<OrganizationTask> {
  const response = await request<{ data: OrganizationTask }>(`/organizations/${encodeURIComponent(organizationId)}/tasks/${encodeURIComponent(taskId)}`, {
    method: 'PATCH', body: JSON.stringify(input),
  }, token);
  return response.data;
}

export async function completeTask(token: string, organizationId: string, taskId: string): Promise<OrganizationTask> {
  const response = await request<{ data: OrganizationTask }>(`/organizations/${encodeURIComponent(organizationId)}/tasks/${encodeURIComponent(taskId)}/complete`, {
    method: 'POST',
  }, token);
  return response.data;
}

export async function archiveTask(token: string, organizationId: string, taskId: string): Promise<OrganizationTask> {
  const response = await request<{ data: OrganizationTask }>(`/organizations/${encodeURIComponent(organizationId)}/tasks/${encodeURIComponent(taskId)}/archive`, {
    method: 'POST',
  }, token);
  return response.data;
}

export async function restoreTask(token: string, organizationId: string, taskId: string): Promise<OrganizationTask> {
  const response = await request<{ data: OrganizationTask }>(`/organizations/${encodeURIComponent(organizationId)}/tasks/${encodeURIComponent(taskId)}/restore`, {
    method: 'POST',
  }, token);
  return response.data;
}

export async function createSupportFeedback(token: string, organizationId: string, input: { category: SupportFeedback['category']; message: string }): Promise<SupportFeedback> {
  const response = await request<{ data: SupportFeedback }>(`/organizations/${encodeURIComponent(organizationId)}/feedback`, {
    method: 'POST', body: JSON.stringify(input),
  }, token);
  return response.data;
}

export async function listSupportFeedback(token: string, organizationId: string): Promise<SupportFeedback[]> {
  const response = await request<{ data: SupportFeedback[] }>(`/organizations/${encodeURIComponent(organizationId)}/feedback`, {}, token);
  return response.data;
}

export async function listMySupportFeedback(token: string, organizationId: string): Promise<SupportFeedback[]> {
  const response = await request<{ data: SupportFeedback[] }>(`/organizations/${encodeURIComponent(organizationId)}/feedback/mine`, {}, token);
  return response.data;
}

export async function updateSupportFeedback(token: string, organizationId: string, feedbackId: string, input: { status: SupportFeedback['status']; response: string | null }): Promise<SupportFeedback> {
  const result = await request<{ data: SupportFeedback }>(`/organizations/${encodeURIComponent(organizationId)}/feedback/${encodeURIComponent(feedbackId)}`, { method: 'PATCH', body: JSON.stringify(input) }, token);
  return result.data;
}

export async function listAttendanceSessions(token: string, organizationId: string): Promise<AttendanceSession[]> {
  const response = await request<{ data: AttendanceSession[] }>(`/organizations/${encodeURIComponent(organizationId)}/attendance-sessions`, {}, token);
  return response.data;
}

export async function recordAttendance(token: string, organizationId: string, input: { class_name: string; attendance_rate: number }): Promise<AttendanceSession> {
  const response = await request<{ data: AttendanceSession }>(`/organizations/${encodeURIComponent(organizationId)}/attendance-sessions`, {
    method: 'POST', body: JSON.stringify(input),
  }, token);
  return response.data;
}

export async function listClasses(token: string, organizationId: string): Promise<OrganizationClass[]> {
  const response = await request<{ data: OrganizationClass[] }>(`/organizations/${encodeURIComponent(organizationId)}/classes`, {}, token);
  return response.data;
}

export async function listStudents(token: string, organizationId: string): Promise<StudentProfile[]> {
  const response = await request<{ data: StudentProfile[] }>(`/organizations/${encodeURIComponent(organizationId)}/students`, {}, token);
  return response.data;
}

export async function searchWorkspace(token: string, organizationId: string, query: string): Promise<WorkspaceSearchResult[]> {
  const response = await request<{ data: WorkspaceSearchResult[] }>(`/organizations/${encodeURIComponent(organizationId)}/search?q=${encodeURIComponent(query)}`, {}, token);
  return response.data;
}

export async function listFeeInvoices(token: string, organizationId: string, filters: { page?: number; per_page?: number; from?: string; to?: string } = {}): Promise<FeeInvoicePage> {
  const params = new URLSearchParams({ page: String(filters.page ?? 1), per_page: String(filters.per_page ?? 25) });
  if (filters.from) params.set('from', filters.from);
  if (filters.to) params.set('to', filters.to);
  const response = await request<{ data: FeeInvoice[]; meta?: FeeInvoicePageMeta }>(`/organizations/${encodeURIComponent(organizationId)}/fee-invoices?${params.toString()}`, {}, token);
  return { data: response.data, meta: response.meta ?? { current_page: 1, last_page: 1, per_page: response.data.length || 25, total: response.data.length } };
}

export async function getFeeInvoiceSummary(token: string, organizationId: string): Promise<FeeInvoiceSummary> {
  const response = await request<{ data: FeeInvoiceSummary }>(`/organizations/${encodeURIComponent(organizationId)}/fee-invoices/summary`, {}, token);
  return response.data;
}

export async function getFeeCollections(token: string, organizationId: string, filters: { from: string; to: string; page?: number; per_page?: number }): Promise<FeeCollectionReport> {
  const params = new URLSearchParams({ from: filters.from, to: filters.to, page: String(filters.page ?? 1), per_page: String(filters.per_page ?? 25) });
  return request<FeeCollectionReport>(`/organizations/${encodeURIComponent(organizationId)}/fee-invoices/collections?${params.toString()}`, {}, token);
}

export async function createFeeInvoice(token: string, organizationId: string, input: { student_id: string; title: string; description?: string; amount: number; due_on?: string }): Promise<FeeInvoice> {
  const response = await request<{ data: FeeInvoice }>(`/organizations/${encodeURIComponent(organizationId)}/fee-invoices`, { method: 'POST', body: JSON.stringify(input) }, token);
  return response.data;
}

export async function voidFeeInvoice(token: string, organizationId: string, invoiceId: string): Promise<void> {
  await request(`/organizations/${encodeURIComponent(organizationId)}/fee-invoices/${encodeURIComponent(invoiceId)}/void`, { method: 'POST' }, token);
}

export async function recordFeePayment(token: string, organizationId: string, invoiceId: string, input: { amount: number; method: FeePayment['method']; transaction_reference: string; idempotency_key: string }): Promise<FeePayment> {
  const response = await request<{ data: FeePayment }>(`/organizations/${encodeURIComponent(organizationId)}/fee-invoices/${encodeURIComponent(invoiceId)}/payments`, { method: 'POST', body: JSON.stringify(input) }, token);
  return response.data;
}

export async function getWorkspaceSubscription(token: string, organizationId: string): Promise<WorkspaceSubscription> {
  const response = await request<{ data: WorkspaceSubscription }>(`/organizations/${encodeURIComponent(organizationId)}/subscription`, {}, token);
  return response.data;
}

export async function requestSubscriptionPlan(token: string, organizationId: string, planId: string): Promise<Partial<WorkspaceSubscription>> {
  const response = await request<{ data: Partial<WorkspaceSubscription> }>(`/organizations/${encodeURIComponent(organizationId)}/subscription/requests`, { method: 'POST', body: JSON.stringify({ plan_id: planId }) }, token);
  return response.data;
}

export async function listPlatformPlans(token: string): Promise<SubscriptionPlan[]> {
  const response = await request<{ data: SubscriptionPlan[] }>('/platform/plans', {}, token);
  return response.data;
}

export async function createPlatformPlan(token: string, input: Omit<SubscriptionPlan, 'id' | 'is_active'>): Promise<SubscriptionPlan> {
  const response = await request<{ data: SubscriptionPlan }>('/platform/plans', { method: 'POST', body: JSON.stringify(input) }, token);
  return response.data;
}

export async function updatePlatformPlan(token: string, planId: string, input: Partial<Omit<SubscriptionPlan, 'id' | 'code'>>): Promise<SubscriptionPlan> {
  const response = await request<{ data: SubscriptionPlan }>(`/platform/plans/${encodeURIComponent(planId)}`, { method: 'PUT', body: JSON.stringify(input) }, token);
  return response.data;
}

export async function listSubscriptionRequests(token: string): Promise<SubscriptionRequest[]> {
  const response = await request<{ data: SubscriptionRequest[] }>('/platform/subscription-requests', {}, token);
  return response.data;
}

export async function getPlatformUsage(token: string): Promise<PlatformUsageMetrics> {
  const response = await request<{ data: PlatformUsageMetrics }>('/platform/usage', {}, token);
  return response.data;
}

export async function getPlatformUsageHistory(token: string, days = 90): Promise<PlatformUsageHistory> {
  const response = await request<{ data: PlatformUsageHistory }>(`/platform/usage/history?days=${encodeURIComponent(String(days))}`, {}, token);
  return response.data;
}

export async function listPlatformOrganizations(token: string, query = '', page = 1): Promise<{ items: PlatformOrganization[]; total: number; page: number; lastPage: number }> {
  const search = new URLSearchParams(); if (query.trim()) search.set('q', query.trim()); search.set('page', String(page));
  const response = await request<{ data: PlatformOrganization[]; meta: { total: number; current_page: number; last_page: number } }>(`/platform/organizations?${search}`, {}, token);
  return { items: response.data, total: response.meta.total, page: response.meta.current_page, lastPage: response.meta.last_page };
}

export async function lookupPlatformUser(token: string, email: string): Promise<PlatformUserLookup | null> {
  const response = await request<{ data: PlatformUserLookup | null }>('/platform/users/lookup', {
    method: 'POST', body: JSON.stringify({ email: email.trim() }),
  }, token);
  return response.data;
}

export async function requestPlatformUserPasswordReset(token: string, userId: number): Promise<void> {
  await request(`/platform/users/${userId}/password-reset`, { method: 'POST' }, token);
}

export async function listPlatformModeratableContent(token: string, type: PlatformModeratableContent['type'], query = '', page = 1): Promise<{ items: PlatformModeratableContent[]; total: number; page: number; lastPage: number }> {
  const search = new URLSearchParams({ type, page: String(page) });
  if (query.trim()) search.set('q', query.trim());
  const response = await request<{ data: PlatformModeratableContent[]; meta: { total: number; current_page: number; last_page: number } }>(`/platform/content-moderation?${search}`, {}, token);
  return { items: response.data, total: response.meta.total, page: response.meta.current_page, lastPage: response.meta.last_page };
}

export async function updatePlatformContentModeration(token: string, type: PlatformModeratableContent['type'], id: string, status: PlatformModeratableContent['status'], reason = ''): Promise<PlatformModeratableContent> {
  const response = await request<{ data: PlatformModeratableContent }>(`/platform/content-moderation/${type}/${encodeURIComponent(id)}`, {
    method: 'PUT', body: JSON.stringify({ status, reason: reason.trim() || null }),
  }, token);
  return response.data;
}

export async function listPlatformAuditLogs(token: string): Promise<PlatformAuditEntry[]> {
  const response = await request<{ data: PlatformAuditEntry[] }>('/platform/audit-logs', {}, token);
  return response.data;
}

export async function listPlatformFeatureFlags(token: string): Promise<PlatformFeatureFlag[]> {
  const response = await request<{ data: PlatformFeatureFlag[] }>('/platform/feature-flags', {}, token);
  return response.data;
}

export async function updatePlatformFeatureFlag(token: string, key: PlatformFeatureFlag['key'], enabled: boolean): Promise<PlatformFeatureFlag> {
  const response = await request<{ data: PlatformFeatureFlag }>(`/platform/feature-flags/${encodeURIComponent(key)}`, {
    method: 'PUT', body: JSON.stringify({ enabled }),
  }, token);
  return response.data;
}

export async function updatePlatformOrganizationStatus(token: string, organizationId: string, status: 'active' | 'suspended', note?: string): Promise<Pick<PlatformOrganization, 'id' | 'name' | 'status'>> {
  const response = await request<{ data: Pick<PlatformOrganization, 'id' | 'name' | 'status'> }>(`/platform/organizations/${encodeURIComponent(organizationId)}/status`, {
    method: 'PUT', body: JSON.stringify({ status, ...(note ? { note } : {}) }),
  }, token);
  return response.data;
}

export async function resolveSubscriptionRequest(token: string, organizationId: string, input: { decision: 'approve' | 'reject'; verification_reference?: string; note?: string }): Promise<void> {
  await request(`/platform/subscription-requests/${encodeURIComponent(organizationId)}/resolve`, { method: 'POST', body: JSON.stringify(input) }, token);
}

export async function createClass(token: string, organizationId: string, input: { name: string; grade?: string; section?: string }): Promise<OrganizationClass> {
  const response = await request<{ data: OrganizationClass }>(`/organizations/${encodeURIComponent(organizationId)}/classes`, {
    method: 'POST', body: JSON.stringify(input),
  }, token);
  return response.data;
}

export async function createStudent(token: string, organizationId: string, input: { student_code: string; name: string }): Promise<StudentProfile> {
  const response = await request<{ data: StudentProfile }>(`/organizations/${encodeURIComponent(organizationId)}/students`, {
    method: 'POST', body: JSON.stringify(input),
  }, token);
  return response.data;
}

export async function enrollStudent(token: string, organizationId: string, classId: string, studentId: string, sectionId?: string): Promise<void> {
  await request(`/organizations/${encodeURIComponent(organizationId)}/classes/${encodeURIComponent(classId)}/enrollments`, {
    method: 'POST', body: JSON.stringify({ student_id: studentId, ...(sectionId ? { section_id: sectionId } : {}) }),
  }, token);
}

export async function markClassAttendance(token: string, organizationId: string, classId: string, records: { student_id: string; status: AttendanceStatus }[], sectionId?: string): Promise<AttendanceSession> {
  const response = await request<{ data: AttendanceSession }>(`/organizations/${encodeURIComponent(organizationId)}/classes/${encodeURIComponent(classId)}/attendance-sessions`, {
    method: 'POST', body: JSON.stringify({ records, ...(sectionId ? { section_id: sectionId } : {}) }),
  }, token);
  return response.data;
}

export async function listLiveSessions(token: string, organizationId: string): Promise<LiveClassSession[]> {
  const response = await request<{ data: LiveClassSession[] }>(`/organizations/${encodeURIComponent(organizationId)}/live-sessions`, {}, token);
  return response.data;
}

export async function createLiveSession(token: string, organizationId: string, input: { class_id?: string; section_id?: string; course_id?: string; title: string; description?: string; starts_at: string; ends_at: string; meeting_url: string }): Promise<LiveClassSession> {
  const response = await request<{ data: LiveClassSession }>(`/organizations/${encodeURIComponent(organizationId)}/live-sessions`, { method: 'POST', body: JSON.stringify(input) }, token);
  return response.data;
}

export async function updateLiveSession(token: string, organizationId: string, sessionId: string, input: { status: 'live' | 'completed' | 'cancelled'; recording_url?: string }): Promise<LiveClassSession> {
  const response = await request<{ data: LiveClassSession }>(`/organizations/${encodeURIComponent(organizationId)}/live-sessions/${encodeURIComponent(sessionId)}`, { method: 'PUT', body: JSON.stringify(input) }, token);
  return response.data;
}

export async function joinLiveSession(token: string, organizationId: string, sessionId: string): Promise<{ meeting_url: string; ends_at: string }> {
  const response = await request<{ data: { meeting_url: string; ends_at: string } }>(`/organizations/${encodeURIComponent(organizationId)}/live-sessions/${encodeURIComponent(sessionId)}/join`, { method: 'POST' }, token);
  return response.data;
}

export async function getLiveSessionRoster(token: string, organizationId: string, sessionId: string): Promise<LiveSessionRosterStudent[]> {
  const response = await request<{ data: LiveSessionRosterStudent[] }>(`/organizations/${encodeURIComponent(organizationId)}/live-sessions/${encodeURIComponent(sessionId)}/roster`, {}, token);
  return response.data;
}

export async function recordLiveSessionAttendance(token: string, organizationId: string, sessionId: string, records: { student_id: string; status: AttendanceStatus }[]): Promise<void> {
  await request(`/organizations/${encodeURIComponent(organizationId)}/live-sessions/${encodeURIComponent(sessionId)}/attendance`, { method: 'POST', body: JSON.stringify({ records }) }, token);
}

export async function signOut(token?: string): Promise<void> {
  if (token) await request('/auth/logout', { method: 'POST' }, token).catch(() => undefined);
  saveToken(null);
}

export function savedToken(): string | null {
  try { return sessionStorage.getItem(TOKEN_KEY); } catch { return null; }
}

export function savedOrganizationId(): string | null {
  try { return sessionStorage.getItem(ORGANIZATION_KEY); } catch { return null; }
}

export function saveOrganizationId(id: string | null): void {
  try {
    if (id) sessionStorage.setItem(ORGANIZATION_KEY, id);
    else sessionStorage.removeItem(ORGANIZATION_KEY);
  } catch {
    // The active organization stays in memory when storage is unavailable.
  }
}

function saveToken(token: string | null): void {
  try {
    if (token) sessionStorage.setItem(TOKEN_KEY, token);
    else {
      sessionStorage.removeItem(TOKEN_KEY);
      sessionStorage.removeItem(ORGANIZATION_KEY);
    }
  } catch {
    // Private browsing modes may deny storage; the in-memory session still works.
  }
}
