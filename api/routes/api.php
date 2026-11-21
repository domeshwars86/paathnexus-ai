<?php

use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn () => response()->json([
        'status' => 'ok',
        'service' => 'paathnexus-api',
    ]))->name('api.v1.health');

    Route::get('/health/ready', function (): \Illuminate\Http\JsonResponse {
        try {
            \Illuminate\Support\Facades\DB::select('select 1');
        } catch (\Throwable) {
            return response()->json([
                'status' => 'not_ready',
                'service' => 'paathnexus-api',
                'checks' => ['database' => 'unavailable'],
            ], 503);
        }

        return response()->json([
            'status' => 'ready',
            'service' => 'paathnexus-api',
            'checks' => ['database' => 'ok'],
        ]);
    })->name('api.v1.health.ready');

    Route::post('/webhooks/payments/sandbox', [\App\Http\Controllers\Api\V1\PaymentWebhookController::class, 'sandbox'])
        ->middleware('throttle:30,1');

    Route::middleware('throttle:60,1')->prefix('catalog')->group(function (): void {
        Route::get('/courses', [\App\Http\Controllers\Api\V1\PublicCatalogController::class, 'courses']);
        Route::get('/educators', [\App\Http\Controllers\Api\V1\PublicCatalogController::class, 'educators']);
        Route::get('/plans', [\App\Http\Controllers\Api\V1\PublicCatalogController::class, 'plans']);
    });

    Route::prefix('auth')->middleware('throttle:6,1')->group(function (): void {
        Route::post('/register', [\App\Http\Controllers\Api\V1\AuthController::class, 'register']);
        Route::post('/register-invitation', [\App\Http\Controllers\Api\V1\AuthController::class, 'registerInvitation']);
        Route::post('/login', [\App\Http\Controllers\Api\V1\AuthController::class, 'login']);
        Route::post('/forgot-password', [\App\Http\Controllers\Api\V1\AuthController::class, 'requestPasswordReset']);
        Route::post('/reset-password', [\App\Http\Controllers\Api\V1\AuthController::class, 'resetPassword']);
        Route::post('/resend-verification', [\App\Http\Controllers\Api\V1\AuthController::class, 'resendVerification']);
    });

    Route::get('/auth/verify-email/{id}/{hash}', [\App\Http\Controllers\Api\V1\AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:10,1'])->whereNumber('id')->name('api.v1.auth.verify-email');

    Route::middleware(['auth:sanctum', EnsureActiveAccount::class, \App\Http\Middleware\EnsureVerifiedEmail::class])->group(function (): void {
        Route::get('/auth/me', [\App\Http\Controllers\Api\V1\AuthController::class, 'me']);
        Route::put('/auth/profile', [\App\Http\Controllers\Api\V1\AuthController::class, 'updateProfile']);
        Route::post('/auth/logout', [\App\Http\Controllers\Api\V1\AuthController::class, 'logout']);
        Route::post('/catalog/courses/{courseId}/enroll', [\App\Http\Controllers\Api\V1\PublicCatalogController::class, 'enroll'])->whereUuid('courseId');
        Route::post('/catalog/courses/{courseId}/orders', [\App\Http\Controllers\Api\V1\PublicCatalogController::class, 'createCourseOrder'])->whereUuid('courseId')->middleware('throttle:10,1');
        Route::get('/catalog/course-orders/{orderId}', [\App\Http\Controllers\Api\V1\PublicCatalogController::class, 'showCourseOrder'])->whereUuid('orderId');
        Route::get('/organizations', [\App\Http\Controllers\Api\V1\OrganizationController::class, 'index']);
        Route::post('/organizations', [\App\Http\Controllers\Api\V1\OrganizationController::class, 'store']);
        Route::get('/organizations/{organizationId}/subscription', [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'show'])
            ->whereUuid('organizationId');
        Route::post('/organizations/{organizationId}/subscription/requests', [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'requestChange'])
            ->whereUuid('organizationId');
        Route::post('/invitations/accept', [\App\Http\Controllers\Api\V1\OrganizationInvitationController::class, 'accept']);
        Route::get('/organizations/{organizationId}/invitations', [\App\Http\Controllers\Api\V1\OrganizationInvitationController::class, 'index'])->whereUuid('organizationId');
        Route::post('/organizations/{organizationId}/invitations', [\App\Http\Controllers\Api\V1\OrganizationInvitationController::class, 'store'])->whereUuid('organizationId');
        Route::delete('/organizations/{organizationId}/invitations/{invitationId}', [\App\Http\Controllers\Api\V1\OrganizationInvitationController::class, 'revoke'])
            ->whereUuid('organizationId')->whereUuid('invitationId');
        Route::get('/platform/plans', [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'plans']);
        Route::post('/platform/plans', [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'createPlan']);
        Route::put('/platform/plans/{planId}', [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'updatePlan'])->whereUuid('planId');
        Route::get('/platform/usage', [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'usage']);
        Route::get('/platform/usage/history', [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'usageHistory']);
        Route::get('/platform/organizations', [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'organizations']);
        Route::post('/platform/users/lookup', [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'lookupUser'])->middleware('throttle:10,1');
        Route::get('/platform/content-moderation', [\App\Http\Controllers\Api\V1\PlatformContentModerationController::class, 'index']);
        Route::put('/platform/content-moderation/{type}/{contentId}', [\App\Http\Controllers\Api\V1\PlatformContentModerationController::class, 'update'])
            ->where('type', 'course|educator')->whereUuid('contentId');
        Route::get('/platform/audit-logs', [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'auditLogs']);
        Route::get('/platform/feature-flags', [\App\Http\Controllers\Api\V1\PlatformFeatureFlagController::class, 'index']);
        Route::put('/platform/feature-flags/{flag}', [\App\Http\Controllers\Api\V1\PlatformFeatureFlagController::class, 'update'])
            ->where('flag', '[a-z][a-z0-9_]{1,63}');
        Route::put('/platform/organizations/{organizationId}/status', [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'updateOrganizationStatus'])->whereUuid('organizationId');
        Route::get('/platform/subscription-requests', [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'pendingRequests']);
        Route::post('/platform/subscription-requests/{organizationId}/resolve', [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'resolveRequest'])->whereUuid('organizationId');
        Route::get('/organizations/{organizationId}/tasks', [\App\Http\Controllers\Api\V1\OrganizationTaskController::class, 'index'])
            ->whereUuid('organizationId');
        Route::get('/organizations/{organizationId}/task-assignees', [\App\Http\Controllers\Api\V1\OrganizationTaskController::class, 'assignees'])
            ->whereUuid('organizationId');
        Route::get('/organizations/{organizationId}/feedback', [\App\Http\Controllers\Api\V1\SupportFeedbackController::class, 'index'])
            ->whereUuid('organizationId');
        Route::get('/organizations/{organizationId}/feedback/mine', [\App\Http\Controllers\Api\V1\SupportFeedbackController::class, 'mine'])
            ->whereUuid('organizationId');
        Route::post('/organizations/{organizationId}/feedback', [\App\Http\Controllers\Api\V1\SupportFeedbackController::class, 'store'])
            ->whereUuid('organizationId')->middleware('throttle:5,1');
        Route::patch('/organizations/{organizationId}/feedback/{feedbackId}', [\App\Http\Controllers\Api\V1\SupportFeedbackController::class, 'update'])
            ->whereUuid('organizationId')->whereUuid('feedbackId')->middleware('throttle:30,1');
        Route::get('/organizations/{organizationId}/dashboard', [\App\Http\Controllers\Api\V1\OrganizationDashboardController::class, 'show'])
            ->whereUuid('organizationId');
        Route::get('/organizations/{organizationId}/student/dashboard', [\App\Http\Controllers\Api\V1\StudentDashboardController::class, 'show'])
            ->whereUuid('organizationId');
        Route::get('/organizations/{organizationId}/student/study-plans', [\App\Http\Controllers\Api\V1\StudentStudyPlanController::class, 'index'])
            ->whereUuid('organizationId');
        Route::post('/organizations/{organizationId}/student/study-plans', [\App\Http\Controllers\Api\V1\StudentStudyPlanController::class, 'store'])
            ->whereUuid('organizationId');
        Route::post('/organizations/{organizationId}/student/study-plans/{planId}/complete', [\App\Http\Controllers\Api\V1\StudentStudyPlanController::class, 'complete'])
            ->whereUuid('organizationId')->whereUuid('planId');
        Route::get('/organizations/{organizationId}/reports/academic', [\App\Http\Controllers\Api\V1\AcademicReportController::class, 'show'])
            ->whereUuid('organizationId');
        Route::post('/organizations/{organizationId}/tasks', [\App\Http\Controllers\Api\V1\OrganizationTaskController::class, 'store'])
            ->whereUuid('organizationId');
        Route::patch('/organizations/{organizationId}/tasks/{taskId}', [\App\Http\Controllers\Api\V1\OrganizationTaskController::class, 'update'])
            ->whereUuid('organizationId')->whereUuid('taskId')->middleware('throttle:30,1');
        Route::post('/organizations/{organizationId}/tasks/{taskId}/archive', [\App\Http\Controllers\Api\V1\OrganizationTaskController::class, 'archive'])
            ->whereUuid('organizationId')->whereUuid('taskId')->middleware('throttle:30,1');
        Route::post('/organizations/{organizationId}/tasks/{taskId}/restore', [\App\Http\Controllers\Api\V1\OrganizationTaskController::class, 'restore'])
            ->whereUuid('organizationId')->whereUuid('taskId')->middleware('throttle:30,1');
        Route::post('/organizations/{organizationId}/tasks/{taskId}/complete', [\App\Http\Controllers\Api\V1\OrganizationTaskController::class, 'complete'])
            ->whereUuid('organizationId')->whereUuid('taskId');
        Route::get('/organizations/{organizationId}/attendance-sessions', [\App\Http\Controllers\Api\V1\AttendanceSessionController::class, 'index'])
            ->whereUuid('organizationId');
        Route::post('/organizations/{organizationId}/attendance-sessions', [\App\Http\Controllers\Api\V1\AttendanceSessionController::class, 'store'])
            ->whereUuid('organizationId');
        Route::post('/organizations/{organizationId}/classes/{classId}/attendance-sessions', [\App\Http\Controllers\Api\V1\AttendanceSessionController::class, 'markClass'])
            ->whereUuid('organizationId')->whereUuid('classId');
        Route::get('/organizations/{organizationId}/classes', [\App\Http\Controllers\Api\V1\AcademicController::class, 'classes'])
            ->whereUuid('organizationId');
        Route::post('/organizations/{organizationId}/classes', [\App\Http\Controllers\Api\V1\AcademicController::class, 'storeClass'])
            ->whereUuid('organizationId');
        Route::get('/organizations/{organizationId}/students', [\App\Http\Controllers\Api\V1\AcademicController::class, 'students'])
            ->whereUuid('organizationId');
        Route::post('/organizations/{organizationId}/students', [\App\Http\Controllers\Api\V1\AcademicController::class, 'storeStudent'])
            ->whereUuid('organizationId');
        Route::post('/organizations/{organizationId}/classes/{classId}/enrollments', [\App\Http\Controllers\Api\V1\AcademicController::class, 'enroll'])
            ->whereUuid('organizationId')->whereUuid('classId');
        Route::get('/organizations/{organizationId}/academic-years', [\App\Http\Controllers\Api\V1\AcademicScheduleController::class, 'years'])
            ->whereUuid('organizationId');
        Route::post('/organizations/{organizationId}/academic-years', [\App\Http\Controllers\Api\V1\AcademicScheduleController::class, 'storeYear'])
            ->whereUuid('organizationId');
        Route::post('/organizations/{organizationId}/academic-years/{yearId}/activate', [\App\Http\Controllers\Api\V1\AcademicScheduleController::class, 'activateYear'])
            ->whereUuid('organizationId')->whereUuid('yearId');
        Route::get('/organizations/{organizationId}/sections', [\App\Http\Controllers\Api\V1\AcademicScheduleController::class, 'sections'])
            ->whereUuid('organizationId');
        Route::get('/organizations/{organizationId}/subjects', [\App\Http\Controllers\Api\V1\AcademicScheduleController::class, 'subjects'])
            ->whereUuid('organizationId');
        Route::post('/organizations/{organizationId}/subjects', [\App\Http\Controllers\Api\V1\AcademicScheduleController::class, 'storeSubject'])
            ->whereUuid('organizationId');
        Route::get('/organizations/{organizationId}/teachers', [\App\Http\Controllers\Api\V1\AcademicScheduleController::class, 'teachers'])
            ->whereUuid('organizationId');
        Route::post('/organizations/{organizationId}/teachers', [\App\Http\Controllers\Api\V1\AcademicScheduleController::class, 'storeTeacher'])
            ->whereUuid('organizationId');
        Route::get('/organizations/{organizationId}/teachers/member-candidates', [\App\Http\Controllers\Api\V1\AcademicScheduleController::class, 'teacherMemberCandidates'])
            ->whereUuid('organizationId');
        Route::put('/organizations/{organizationId}/teachers/{teacherId}/member', [\App\Http\Controllers\Api\V1\AcademicScheduleController::class, 'linkTeacherMember'])
            ->whereUuid('organizationId')->whereUuid('teacherId');
        Route::put('/organizations/{organizationId}/teachers/{teacherId}/public-profile', [\App\Http\Controllers\Api\V1\AcademicScheduleController::class, 'updatePublicProfile'])
            ->whereUuid('organizationId')->whereUuid('teacherId');
        Route::get('/organizations/{organizationId}/classes/{classId}/subject-offerings', [\App\Http\Controllers\Api\V1\AcademicScheduleController::class, 'subjectOfferings'])
            ->whereUuid('organizationId')->whereUuid('classId');
        Route::post('/organizations/{organizationId}/classes/{classId}/subject-offerings', [\App\Http\Controllers\Api\V1\AcademicScheduleController::class, 'storeSubjectOffering'])
            ->whereUuid('organizationId')->whereUuid('classId');
        Route::delete('/organizations/{organizationId}/classes/{classId}/subject-offerings/{offeringId}', [\App\Http\Controllers\Api\V1\AcademicScheduleController::class, 'endSubjectOffering'])
            ->whereUuid('organizationId')->whereUuid('classId')->whereUuid('offeringId');
        Route::get('/organizations/{organizationId}/classes/{classId}/teachers', [\App\Http\Controllers\Api\V1\AcademicScheduleController::class, 'classTeachers'])
            ->whereUuid('organizationId')->whereUuid('classId');
        Route::post('/organizations/{organizationId}/classes/{classId}/teachers', [\App\Http\Controllers\Api\V1\AcademicScheduleController::class, 'storeClassTeacher'])
            ->whereUuid('organizationId')->whereUuid('classId');
        Route::get('/organizations/{organizationId}/classes/{classId}/teacher-assignment-history', [\App\Http\Controllers\Api\V1\AcademicScheduleController::class, 'classTeacherHistory'])
            ->whereUuid('organizationId')->whereUuid('classId');
        Route::put('/organizations/{organizationId}/classes/{classId}/teachers/{assignmentId}', [\App\Http\Controllers\Api\V1\AcademicScheduleController::class, 'updateClassTeacher'])
            ->whereUuid('organizationId')->whereUuid('classId')->whereUuid('assignmentId');
        Route::delete('/organizations/{organizationId}/classes/{classId}/teachers/{assignmentId}', [\App\Http\Controllers\Api\V1\AcademicScheduleController::class, 'endClassTeacher'])
            ->whereUuid('organizationId')->whereUuid('classId')->whereUuid('assignmentId');
        Route::post('/organizations/{organizationId}/classes/{classId}/sections', [\App\Http\Controllers\Api\V1\AcademicScheduleController::class, 'storeSection'])
            ->whereUuid('organizationId')->whereUuid('classId');
        Route::get('/organizations/{organizationId}/timetable-entries', [\App\Http\Controllers\Api\V1\AcademicScheduleController::class, 'timetable'])
            ->whereUuid('organizationId');
        Route::post('/organizations/{organizationId}/timetable-entries', [\App\Http\Controllers\Api\V1\AcademicScheduleController::class, 'storeTimetableEntry'])
            ->whereUuid('organizationId');
        Route::put('/organizations/{organizationId}/timetable-entries/{entryId}', [\App\Http\Controllers\Api\V1\AcademicScheduleController::class, 'updateTimetableEntry'])
            ->whereUuid('organizationId')->whereUuid('entryId');
        Route::get('/organizations/{organizationId}/schedule-exceptions', [\App\Http\Controllers\Api\V1\ScheduleExceptionController::class, 'index'])
            ->whereUuid('organizationId');
        Route::post('/organizations/{organizationId}/schedule-exceptions', [\App\Http\Controllers\Api\V1\ScheduleExceptionController::class, 'store'])
            ->whereUuid('organizationId');
        Route::put('/organizations/{organizationId}/schedule-exceptions/{exceptionId}', [\App\Http\Controllers\Api\V1\ScheduleExceptionController::class, 'update'])
            ->whereUuid('organizationId')->whereUuid('exceptionId');
        Route::post('/organizations/{organizationId}/schedule-exceptions/{exceptionId}/cancel', [\App\Http\Controllers\Api\V1\ScheduleExceptionController::class, 'cancel'])
            ->whereUuid('organizationId')->whereUuid('exceptionId');
        Route::get('/organizations/{organizationId}/live-sessions', [\App\Http\Controllers\Api\V1\LiveSessionController::class, 'index'])
            ->whereUuid('organizationId');
        Route::post('/organizations/{organizationId}/live-sessions', [\App\Http\Controllers\Api\V1\LiveSessionController::class, 'store'])
            ->whereUuid('organizationId');
        Route::put('/organizations/{organizationId}/live-sessions/{sessionId}', [\App\Http\Controllers\Api\V1\LiveSessionController::class, 'update'])
            ->whereUuid('organizationId')->whereUuid('sessionId');
        Route::post('/organizations/{organizationId}/live-sessions/{sessionId}/join', [\App\Http\Controllers\Api\V1\LiveSessionController::class, 'join'])
            ->whereUuid('organizationId')->whereUuid('sessionId');
        Route::get('/organizations/{organizationId}/live-sessions/{sessionId}/roster', [\App\Http\Controllers\Api\V1\LiveSessionController::class, 'roster'])
            ->whereUuid('organizationId')->whereUuid('sessionId');
        Route::post('/organizations/{organizationId}/live-sessions/{sessionId}/attendance', [\App\Http\Controllers\Api\V1\LiveSessionController::class, 'recordAttendance'])
            ->whereUuid('organizationId')->whereUuid('sessionId');
        Route::get('/organizations/{organizationId}/courses', [\App\Http\Controllers\Api\V1\LearningController::class, 'courses'])
            ->whereUuid('organizationId');
        Route::post('/organizations/{organizationId}/courses', [\App\Http\Controllers\Api\V1\LearningController::class, 'storeCourse'])
            ->whereUuid('organizationId');
        Route::put('/organizations/{organizationId}/courses/{courseId}', [\App\Http\Controllers\Api\V1\LearningController::class, 'updateCourse'])
            ->whereUuid('organizationId')->whereUuid('courseId');
        Route::get('/organizations/{organizationId}/course-instructors', [\App\Http\Controllers\Api\V1\LearningController::class, 'courseInstructorCandidates'])
            ->whereUuid('organizationId');
        Route::put('/organizations/{organizationId}/courses/{courseId}/instructors', [\App\Http\Controllers\Api\V1\LearningController::class, 'updateCourseInstructors'])
            ->whereUuid('organizationId')->whereUuid('courseId');
        Route::get('/organizations/{organizationId}/courses/{courseId}/lessons', [\App\Http\Controllers\Api\V1\LearningController::class, 'lessons'])
            ->whereUuid('organizationId')->whereUuid('courseId');
        Route::post('/organizations/{organizationId}/courses/{courseId}/lessons', [\App\Http\Controllers\Api\V1\LearningController::class, 'storeLesson'])
            ->whereUuid('organizationId')->whereUuid('courseId');
        Route::put('/organizations/{organizationId}/courses/{courseId}/lessons/{lessonId}', [\App\Http\Controllers\Api\V1\LearningController::class, 'updateLesson'])
            ->whereUuid('organizationId')->whereUuid('courseId')->whereUuid('lessonId');
        Route::get('/organizations/{organizationId}/courses/{courseId}/enrollments', [\App\Http\Controllers\Api\V1\LearningController::class, 'enrollments'])
            ->whereUuid('organizationId')->whereUuid('courseId');
        Route::post('/organizations/{organizationId}/courses/{courseId}/enrollments', [\App\Http\Controllers\Api\V1\LearningController::class, 'enrollStudent'])
            ->whereUuid('organizationId')->whereUuid('courseId');
        Route::get('/organizations/{organizationId}/courses/{courseId}/assignments', [\App\Http\Controllers\Api\V1\LearningController::class, 'assignments'])
            ->whereUuid('organizationId')->whereUuid('courseId');
        Route::post('/organizations/{organizationId}/courses/{courseId}/assignments', [\App\Http\Controllers\Api\V1\LearningController::class, 'storeAssignment'])
            ->whereUuid('organizationId')->whereUuid('courseId');
        Route::post('/organizations/{organizationId}/courses/{courseId}/assignments/{assignmentId}/attachments', [\App\Http\Controllers\Api\V1\LearningController::class, 'uploadAssignmentResources'])
            ->whereUuid('organizationId')->whereUuid('courseId')->whereUuid('assignmentId');
        Route::get('/organizations/{organizationId}/courses/{courseId}/assignments/{assignmentId}/attachments/{attachmentId}', [\App\Http\Controllers\Api\V1\LearningController::class, 'downloadAssignmentAttachment'])
            ->whereUuid('organizationId')->whereUuid('courseId')->whereUuid('assignmentId')->whereUuid('attachmentId');
        Route::get('/organizations/{organizationId}/courses/{courseId}/questions', [\App\Http\Controllers\Api\V1\AssessmentController::class, 'questions'])
            ->whereUuid('organizationId')->whereUuid('courseId');
        Route::post('/organizations/{organizationId}/courses/{courseId}/questions', [\App\Http\Controllers\Api\V1\AssessmentController::class, 'storeQuestion'])
            ->whereUuid('organizationId')->whereUuid('courseId');
        Route::post('/organizations/{organizationId}/courses/{courseId}/questions/import/preview', [\App\Http\Controllers\Api\V1\AssessmentController::class, 'previewQuestionImport'])
            ->whereUuid('organizationId')->whereUuid('courseId');
        Route::post('/organizations/{organizationId}/courses/{courseId}/questions/import', [\App\Http\Controllers\Api\V1\AssessmentController::class, 'importQuestions'])
            ->whereUuid('organizationId')->whereUuid('courseId');
        Route::get('/organizations/{organizationId}/courses/{courseId}/assessments', [\App\Http\Controllers\Api\V1\AssessmentController::class, 'assessments'])
            ->whereUuid('organizationId')->whereUuid('courseId');
        Route::post('/organizations/{organizationId}/courses/{courseId}/assessments', [\App\Http\Controllers\Api\V1\AssessmentController::class, 'storeAssessment'])
            ->whereUuid('organizationId')->whereUuid('courseId');
        Route::get('/organizations/{organizationId}/courses/{courseId}/assessments/{assessmentId}/attempts', [\App\Http\Controllers\Api\V1\AssessmentController::class, 'attempts'])
            ->whereUuid('organizationId')->whereUuid('courseId')->whereUuid('assessmentId');
        Route::get('/organizations/{organizationId}/courses/{courseId}/assessments/{assessmentId}/grades/export', [\App\Http\Controllers\Api\V1\AssessmentController::class, 'exportGrades'])
            ->whereUuid('organizationId')->whereUuid('courseId')->whereUuid('assessmentId');
        Route::get('/organizations/{organizationId}/courses/{courseId}/assessments/{assessmentId}/analytics', [\App\Http\Controllers\Api\V1\AssessmentController::class, 'analytics'])
            ->whereUuid('organizationId')->whereUuid('courseId')->whereUuid('assessmentId');
        Route::get('/organizations/{organizationId}/courses/{courseId}/assessments/{assessmentId}/attempts/{attemptId}/review', [\App\Http\Controllers\Api\V1\AssessmentController::class, 'reviewAttempt'])
            ->whereUuid('organizationId')->whereUuid('courseId')->whereUuid('assessmentId')->whereUuid('attemptId');
        Route::post('/organizations/{organizationId}/assessments/{assessmentId}/attempts', [\App\Http\Controllers\Api\V1\AssessmentController::class, 'startAttempt'])
            ->whereUuid('organizationId')->whereUuid('assessmentId');
        Route::put('/organizations/{organizationId}/assessments/{assessmentId}/attempts/{attemptId}/answers/{questionId}', [\App\Http\Controllers\Api\V1\AssessmentController::class, 'saveAnswer'])
            ->whereUuid('organizationId')->whereUuid('assessmentId')->whereUuid('attemptId')->whereUuid('questionId');
        Route::put('/organizations/{organizationId}/courses/{courseId}/assessments/{assessmentId}/attempts/{attemptId}/answers/{questionId}/grade', [\App\Http\Controllers\Api\V1\AssessmentController::class, 'gradeShortAnswer'])
            ->whereUuid('organizationId')->whereUuid('courseId')->whereUuid('assessmentId')->whereUuid('attemptId')->whereUuid('questionId');
        Route::post('/organizations/{organizationId}/assessments/{assessmentId}/attempts/{attemptId}/submit', [\App\Http\Controllers\Api\V1\AssessmentController::class, 'submitAttempt'])
            ->whereUuid('organizationId')->whereUuid('assessmentId')->whereUuid('attemptId');
        Route::get('/organizations/{organizationId}/assessments/{assessmentId}/attempts/{attemptId}/result', [\App\Http\Controllers\Api\V1\AssessmentController::class, 'attemptResult'])
            ->whereUuid('organizationId')->whereUuid('assessmentId')->whereUuid('attemptId');
        Route::get('/organizations/{organizationId}/courses/{courseId}/assignments/{assignmentId}/submissions', [\App\Http\Controllers\Api\V1\LearningController::class, 'assignmentSubmissions'])
            ->whereUuid('organizationId')->whereUuid('courseId')->whereUuid('assignmentId');
        Route::put('/organizations/{organizationId}/courses/{courseId}/assignments/{assignmentId}/submissions/{submissionId}/grade', [\App\Http\Controllers\Api\V1\LearningController::class, 'gradeSubmission'])
            ->whereUuid('organizationId')->whereUuid('courseId')->whereUuid('assignmentId')->whereUuid('submissionId');
        Route::post('/organizations/{organizationId}/assignments/{assignmentId}/submissions', [\App\Http\Controllers\Api\V1\LearningController::class, 'submitAssignment'])
            ->whereUuid('organizationId')->whereUuid('assignmentId');
        Route::get('/organizations/{organizationId}/courses/{courseId}/progress', [\App\Http\Controllers\Api\V1\LearningController::class, 'progress'])
            ->whereUuid('organizationId')->whereUuid('courseId');
        Route::put('/organizations/{organizationId}/courses/{courseId}/lessons/{lessonId}/progress', [\App\Http\Controllers\Api\V1\LearningController::class, 'updateProgress'])
            ->whereUuid('organizationId')->whereUuid('courseId')->whereUuid('lessonId');
        Route::get('/organizations/{organizationId}/students/member-candidates', [\App\Http\Controllers\Api\V1\LearningController::class, 'studentMemberCandidates'])
            ->whereUuid('organizationId');
        Route::put('/organizations/{organizationId}/students/{studentId}/member', [\App\Http\Controllers\Api\V1\LearningController::class, 'linkStudentMember'])
            ->whereUuid('organizationId')->whereUuid('studentId');
        Route::get('/organizations/{organizationId}/guardian-candidates', [\App\Http\Controllers\Api\V1\GuardianController::class, 'candidates'])
            ->whereUuid('organizationId');
        Route::get('/organizations/{organizationId}/guardian-student-links', [\App\Http\Controllers\Api\V1\GuardianController::class, 'links'])
            ->whereUuid('organizationId');
        Route::post('/organizations/{organizationId}/guardian-student-links', [\App\Http\Controllers\Api\V1\GuardianController::class, 'store'])
            ->whereUuid('organizationId');
        Route::delete('/organizations/{organizationId}/guardian-student-links/{linkId}', [\App\Http\Controllers\Api\V1\GuardianController::class, 'end'])
            ->whereUuid('organizationId')->whereUuid('linkId');
        Route::get('/organizations/{organizationId}/guardian/students', [\App\Http\Controllers\Api\V1\GuardianController::class, 'children'])
            ->whereUuid('organizationId');
        Route::post('/organizations/{organizationId}/ai/tutor', [\App\Http\Controllers\Api\V1\AiTutorController::class, 'ask'])
            ->whereUuid('organizationId')->middleware('throttle:ai');
        Route::post('/organizations/{organizationId}/ai/teacher-assistant', [\App\Http\Controllers\Api\V1\AiTutorController::class, 'teacherDraft'])
            ->whereUuid('organizationId')->middleware('throttle:ai');
        Route::get('/organizations/{organizationId}/announcements', [\App\Http\Controllers\Api\V1\AnnouncementController::class, 'index'])
            ->whereUuid('organizationId');
        Route::post('/organizations/{organizationId}/announcements', [\App\Http\Controllers\Api\V1\AnnouncementController::class, 'store'])
            ->whereUuid('organizationId');
        Route::patch('/organizations/{organizationId}/announcements/{announcementId}', [\App\Http\Controllers\Api\V1\AnnouncementController::class, 'update'])
            ->whereUuid('organizationId')->whereUuid('announcementId')->middleware('throttle:30,1');
        Route::post('/organizations/{organizationId}/announcements/{announcementId}/withdraw', [\App\Http\Controllers\Api\V1\AnnouncementController::class, 'withdraw'])
            ->whereUuid('organizationId')->whereUuid('announcementId')->middleware('throttle:30,1');
        Route::post('/organizations/{organizationId}/announcements/{announcementId}/restore', [\App\Http\Controllers\Api\V1\AnnouncementController::class, 'restore'])
            ->whereUuid('organizationId')->whereUuid('announcementId')->middleware('throttle:30,1');
        Route::get('/organizations/{organizationId}/notifications', [\App\Http\Controllers\Api\V1\AnnouncementController::class, 'notifications'])
            ->whereUuid('organizationId');
        Route::post('/organizations/{organizationId}/notifications/{notificationId}/read', [\App\Http\Controllers\Api\V1\AnnouncementController::class, 'markRead'])
            ->whereUuid('organizationId')->whereUuid('notificationId');
        Route::get('/organizations/{organizationId}/fee-invoices', [\App\Http\Controllers\Api\V1\FeeInvoiceController::class, 'index'])
            ->whereUuid('organizationId');
        Route::get('/organizations/{organizationId}/fee-invoices/summary', [\App\Http\Controllers\Api\V1\FeeInvoiceController::class, 'summary'])
            ->whereUuid('organizationId');
        Route::get('/organizations/{organizationId}/fee-invoices/collections', [\App\Http\Controllers\Api\V1\FeeInvoiceController::class, 'collections'])
            ->whereUuid('organizationId');
        Route::post('/organizations/{organizationId}/fee-invoices', [\App\Http\Controllers\Api\V1\FeeInvoiceController::class, 'store'])
            ->whereUuid('organizationId');
        Route::post('/organizations/{organizationId}/fee-invoices/{invoiceId}/void', [\App\Http\Controllers\Api\V1\FeeInvoiceController::class, 'void'])
            ->whereUuid('organizationId')->whereUuid('invoiceId');
        Route::post('/organizations/{organizationId}/fee-invoices/{invoiceId}/payments', [\App\Http\Controllers\Api\V1\FeeInvoiceController::class, 'recordPayment'])
            ->whereUuid('organizationId')->whereUuid('invoiceId');
        Route::get('/organizations/{organizationId}/members', [\App\Http\Controllers\Api\V1\OrganizationController::class, 'members'])
            ->whereUuid('organizationId');
        Route::get('/organizations/{organizationId}/roles', [\App\Http\Controllers\Api\V1\OrganizationRoleController::class, 'index'])
            ->whereUuid('organizationId');
        Route::post('/organizations/{organizationId}/roles', [\App\Http\Controllers\Api\V1\OrganizationRoleController::class, 'store'])
            ->whereUuid('organizationId');
        Route::put('/organizations/{organizationId}/roles/{roleId}', [\App\Http\Controllers\Api\V1\OrganizationRoleController::class, 'update'])
            ->whereUuid('organizationId')->whereUuid('roleId');
        Route::delete('/organizations/{organizationId}/roles/{roleId}', [\App\Http\Controllers\Api\V1\OrganizationRoleController::class, 'destroy'])
            ->whereUuid('organizationId')->whereUuid('roleId');
        Route::post('/organizations/{organizationId}/roles/{roleId}/restore', [\App\Http\Controllers\Api\V1\OrganizationRoleController::class, 'restore'])
            ->whereUuid('organizationId')->whereUuid('roleId');
        Route::put('/organizations/{organizationId}/roles/{roleId}/members/{userId}', [\App\Http\Controllers\Api\V1\OrganizationRoleController::class, 'assign'])
            ->whereUuid('organizationId')->whereUuid('roleId')->whereNumber('userId');
        Route::delete('/organizations/{organizationId}/role-members/{userId}', [\App\Http\Controllers\Api\V1\OrganizationRoleController::class, 'unassign'])
            ->whereUuid('organizationId')->whereNumber('userId');
        Route::get('/organizations/{organizationId}', [\App\Http\Controllers\Api\V1\OrganizationController::class, 'show'])
            ->whereUuid('organizationId');
        Route::get('/organizations/{organizationId}/search', [\App\Http\Controllers\Api\V1\WorkspaceSearchController::class, 'search'])
            ->whereUuid('organizationId')->middleware('throttle:60,1');
        Route::put('/organizations/{organizationId}', [\App\Http\Controllers\Api\V1\OrganizationController::class, 'update'])
            ->whereUuid('organizationId');
    });
});
