<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OpenApiExportTest extends TestCase
{
    public function test_openapi_document_matches_registered_routes_and_describes_authentication(): void
    {
        $exitCode = Artisan::call('openapi:export', ['--check' => true]);
        $this->assertSame(0, $exitCode, Artisan::output());

        $document = json_decode(
            file_get_contents(base_path('../docs/openapi.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        foreach ($document['paths'] as $path => $operations) {
            foreach ($operations as $operation) {
                foreach ($operation['requestBody']['content'] ?? [] as $content) {
                    $this->assertNotSame('#/components/schemas/JsonObject', $content['schema']['$ref'] ?? null, "Generic request schema remains on {$path}.");
                }
                foreach ($operation['responses'] ?? [] as $response) {
                    foreach ($response['content'] ?? [] as $content) {
                        $this->assertNotSame('#/components/schemas/ApiResponse', $content['schema']['$ref'] ?? null, "Generic response schema remains on {$path}.");
                    }
                }
            }
        }

        $this->assertSame('3.1.0', $document['openapi']);
        $this->assertSame('/api/v1', $document['servers'][0]['url']);
        $this->assertSame('Sign in', $document['paths']['/auth/login']['post']['summary']);
        $this->assertArrayNotHasKey('security', $document['paths']['/auth/login']['post']);
        $this->assertSame(
            '#/components/schemas/AuthLoginRequest',
            $document['paths']['/auth/login']['post']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $this->assertTrue($document['paths']['/auth/login']['post']['requestBody']['required']);
        $this->assertSame(['email', 'password'], $document['components']['schemas']['AuthLoginRequest']['required']);
        $this->assertSame(
            '#/components/schemas/UpdateAuthProfileRequest',
            $document['paths']['/auth/profile']['put']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(120, $document['components']['schemas']['UpdateAuthProfileRequest']['properties']['name']['maxLength']);
        $this->assertSame(
            '#/components/schemas/AuthProfileUpdateResponse',
            $document['paths']['/auth/profile']['put']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertArrayNotHasKey('requestBody', $document['paths']['/auth/logout']['post']);
        foreach ([
            '/catalog/courses/{courseId}/enroll',
            '/catalog/courses/{courseId}/orders',
            '/organizations/{organizationId}/academic-years/{yearId}/activate',
            '/organizations/{organizationId}/announcements/{announcementId}/restore',
            '/organizations/{organizationId}/announcements/{announcementId}/withdraw',
            '/organizations/{organizationId}/assessments/{assessmentId}/attempts',
            '/organizations/{organizationId}/assessments/{assessmentId}/attempts/{attemptId}/submit',
            '/organizations/{organizationId}/fee-invoices/{invoiceId}/void',
            '/organizations/{organizationId}/live-sessions/{sessionId}/join',
            '/organizations/{organizationId}/schedule-exceptions/{exceptionId}/cancel',
            '/organizations/{organizationId}/student/study-plans/{planId}/complete',
            '/organizations/{organizationId}/tasks/{taskId}/archive',
            '/organizations/{organizationId}/tasks/{taskId}/complete',
            '/organizations/{organizationId}/tasks/{taskId}/restore',
        ] as $bodylessPath) {
            $method = isset($document['paths'][$bodylessPath]['post']) ? 'post' : 'put';
            $this->assertArrayNotHasKey('requestBody', $document['paths'][$bodylessPath][$method], $bodylessPath);
        }

        $organizations = $document['paths']['/organizations'];
        $this->assertSame(
            '#/components/schemas/StoreOrganizationRequest',
            $organizations['post']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $this->assertTrue($organizations['post']['requestBody']['required']);
        $this->assertSame(['name', 'type'], $document['components']['schemas']['StoreOrganizationRequest']['required']);
        $this->assertSame(
            '#/components/schemas/OrganizationListResponse',
            $organizations['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $orgIdPath = '/organizations/{organizationId}';
        $this->assertSame(
            '#/components/schemas/UpdateOrganizationRequest',
            $document['paths'][$orgIdPath]['put']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/OrganizationMembersResponse',
            $document['paths'][$orgIdPath.'/members']['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $invitations = $document['paths'][$orgIdPath.'/invitations'];
        $this->assertSame(
            '#/components/schemas/StoreOrganizationInvitationRequest',
            $invitations['post']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/OrganizationInvitationCreatedResponse',
            $invitations['post']['responses']['201']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(64, $document['components']['schemas']['OrganizationInvitationCreatedResponse']['properties']['token']['maxLength']);
        $acceptInvitation = $document['paths']['/invitations/accept']['post'];
        $this->assertTrue($acceptInvitation['requestBody']['required']);
        $this->assertSame(
            '#/components/schemas/OrganizationInvitationAcceptedResponse',
            $acceptInvitation['responses']['200']['content']['application/json']['schema']['$ref'],
        );

        $feeInvoicesPath = '/organizations/{organizationId}/fee-invoices';
        $feeInvoices = $document['paths'][$feeInvoicesPath];
        $this->assertSame(
            '#/components/schemas/StoreFeeInvoiceRequest',
            $feeInvoices['post']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(['student_id', 'title', 'amount'], $document['components']['schemas']['StoreFeeInvoiceRequest']['required']);
        $this->assertSame(0.01, $document['components']['schemas']['StoreFeeInvoiceRequest']['properties']['amount']['multipleOf']);
        $this->assertSame(
            '#/components/schemas/FeeInvoiceListResponse',
            $feeInvoices['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $feeInvoiceSchema = $document['components']['schemas']['FeeInvoice'];
        $this->assertSame(['id', 'invoice_number', 'student_id', 'student_name', 'student_code', 'title', 'description', 'amount_paise', 'due_on', 'balance_paise', 'payments', 'status', 'created_at'], $feeInvoiceSchema['required']);
        $this->assertSame(['string', 'null'], $feeInvoiceSchema['properties']['student_name']['type']);
        $this->assertSame(['string', 'null'], $feeInvoiceSchema['properties']['student_code']['type']);
        $this->assertSame(['string', 'null'], $feeInvoiceSchema['properties']['description']['type']);
        $this->assertSame(['string', 'null'], $feeInvoiceSchema['properties']['due_on']['type']);
        $this->assertSame(['issued', 'partially_paid', 'paid', 'void'], $feeInvoiceSchema['properties']['status']['enum']);
        $this->assertSame('#/components/schemas/FeePayment', $feeInvoiceSchema['properties']['payments']['items']['$ref']);
        $this->assertSame(['id', 'amount_paise', 'method', 'transaction_reference', 'receipt_number', 'received_at'], $document['components']['schemas']['FeePayment']['required']);
        $this->assertSame(['cash', 'bank_transfer', 'upi', 'cheque', 'other'], $document['components']['schemas']['FeePayment']['properties']['method']['enum']);
        $this->assertSame(['current_page', 'last_page', 'per_page', 'total'], $document['components']['schemas']['FeeInvoicePaginationMeta']['required']);
        $feeInvoiceParameters = array_column($feeInvoices['get']['parameters'], null, 'name');
        $this->assertArrayHasKey('page', $feeInvoiceParameters);
        $this->assertArrayHasKey('per_page', $feeInvoiceParameters);
        $this->assertArrayHasKey('from', $feeInvoiceParameters);
        $this->assertArrayHasKey('to', $feeInvoiceParameters);
        $this->assertSame(
            '#/components/schemas/FeeInvoiceSummaryResponse',
            $document['paths'][$feeInvoicesPath.'/summary']['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(['invoice_count', 'invoiced_paise', 'collected_paise', 'outstanding_paise', 'overdue_paise', 'paid_invoice_count', 'outstanding_invoice_count', 'overdue_invoice_count'], $document['components']['schemas']['FeeInvoiceSummaryResponse']['properties']['data']['required']);
        $collections = $document['paths'][$feeInvoicesPath.'/collections']['get'];
        $collectionParameters = array_column($collections['parameters'], null, 'name');
        $this->assertTrue($collectionParameters['from']['required']);
        $this->assertTrue($collectionParameters['to']['required']);
        $this->assertSame(
            '#/components/schemas/FeeCollectionsResponse',
            $collections['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(['data', 'summary', 'meta'], $document['components']['schemas']['FeeCollectionsResponse']['required']);
        $this->assertSame(['string', 'null'], $document['components']['schemas']['FeeCollectionPayment']['properties']['invoice_number']['type']);
        $this->assertSame(['string', 'null'], $document['components']['schemas']['FeeCollectionPayment']['properties']['student_name']['type']);
        $this->assertSame(['current_page', 'last_page', 'per_page', 'total', 'from', 'to'], $document['components']['schemas']['FeeCollectionsMeta']['required']);
        $paymentPath = $document['paths'][$feeInvoicesPath.'/{invoiceId}/payments']['post'];
        $this->assertTrue($paymentPath['requestBody']['required']);
        $this->assertSame(
            '#/components/schemas/RecordFeePaymentRequest',
            $paymentPath['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame('uuid', $document['components']['schemas']['RecordFeePaymentRequest']['properties']['idempotency_key']['format']);
        $voidInvoice = $document['paths'][$feeInvoicesPath.'/{invoiceId}/void']['post'];
        $this->assertArrayNotHasKey('requestBody', $voidInvoice);
        $this->assertSame(
            '#/components/schemas/FeeInvoiceVoidResponse',
            $voidInvoice['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            ['school', 'college', 'coaching', 'academy', 'educator'],
            $document['components']['schemas']['AuthRegisterRequest']['properties']['organization_type']['enum'],
        );
        $this->assertSame(
            '#/components/schemas/AuthSessionResponse',
            $document['paths']['/auth/login']['post']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/EmailVerificationRequiredResponse',
            $document['paths']['/auth/register']['post']['responses']['202']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(['user', 'token', 'organizations'], $document['components']['schemas']['AuthSessionData']['required']);
        $this->assertSame(['verification_required', 'email'], $document['components']['schemas']['EmailVerificationRequiredResponse']['properties']['data']['required']);
        $this->assertSame(
            ['not_required', 'pending', 'reviewed'],
            $document['components']['schemas']['StudentAssessmentResult']['properties']['review_status']['enum'],
        );
        $this->assertSame(
            ['submitted', 'graded', null],
            $document['components']['schemas']['StudentDashboardAssignment']['properties']['submission_status']['enum'],
        );
        $studentDashboardPath = '/organizations/{organizationId}/student/dashboard';
        $this->assertSame(
            '#/components/schemas/StudentDashboardResponse',
            $document['paths'][$studentDashboardPath]['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $studentDashboard = $document['components']['schemas']['StudentDashboardResponse']['properties']['data'];
        $this->assertSame(['student', 'courses', 'assignments', 'study_recommendations', 'assessment_results', 'today_schedule', 'schedule_notice', 'attendance'], $studentDashboard['required']);
        $this->assertSame(8, $studentDashboard['properties']['assignments']['maxItems']);
        $this->assertSame(5, $studentDashboard['properties']['study_recommendations']['maxItems']);
        $this->assertSame(8, $studentDashboard['properties']['assessment_results']['maxItems']);
        $this->assertSame(['string', 'null'], $document['components']['schemas']['StudentDashboardAssignment']['properties']['course_title']['type']);
        $this->assertSame(['string', 'null'], $document['components']['schemas']['StudentDashboardAssignment']['properties']['due_at']['type']);
        $this->assertSame(['number', 'null'], $document['components']['schemas']['StudentAssessmentResult']['properties']['score']['type']);
        $this->assertSame(['string', 'null'], $document['components']['schemas']['StudentAssessmentResult']['properties']['submitted_at']['type']);
        $this->assertSame(5, $document['components']['schemas']['StudentAttendanceSummary']['properties']['recent']['maxItems']);
        $this->assertSame(['number', 'null'], $document['components']['schemas']['StudentAttendanceSummary']['properties']['rate_percent']['type']);
        $this->assertSame(
            'string',
            $document['components']['schemas']['WorkspaceDashboardScheduleEntry']['properties']['subject']['type'],
        );
        $this->assertSame(0, $document['components']['schemas']['StudentAssessmentResult']['properties']['score']['minimum']);
        $this->assertSame(['all', 'institution', 'educator'], $document['components']['schemas']['SubscriptionPlan']['properties']['audience']['enum']);
        $this->assertSame(['month', 'year', 'custom'], $document['components']['schemas']['SubscriptionPlan']['properties']['billing_interval']['enum']);
        $this->assertSame(['school', 'college', 'coaching', 'academy', 'educator', null], $document['components']['schemas']['PublicCourse']['properties']['organization_type']['enum']);
        $this->assertSame(['active', 'change_pending', null], $document['components']['schemas']['PlatformOrganizationRecord']['properties']['subscription_status']['enum']);
        $catalog = $document['paths']['/catalog/courses']['get'];
        $this->assertSame(
            '#/components/schemas/PublicCourseCatalogResponse',
            $catalog['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $query = array_column($catalog['parameters'], null, 'name');
        $this->assertSame(['type' => 'integer', 'minimum' => 1], $query['page']['schema']);
        $this->assertSame(100, $query['q']['schema']['maxLength']);
        $this->assertContains('lesson_count', array_keys($document['components']['schemas']['PublicCourse']['properties']));
        $this->assertContains('instructors', array_keys($document['components']['schemas']['PublicCourse']['properties']));
        $this->assertSame(
            '#/components/schemas/PublicPlanCatalogResponse',
            $document['paths']['/catalog/plans']['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(200, array_key_first($document['paths']['/health/ready']['get']['responses']));

        $tutorPath = '/organizations/{organizationId}/ai/tutor';
        $teacherAssistantPath = '/organizations/{organizationId}/ai/teacher-assistant';
        $this->assertSame('#/components/schemas/AskAiTutorRequest', $document['paths'][$tutorPath]['post']['requestBody']['content']['application/json']['schema']['$ref']);
        $this->assertSame(['question'], $document['components']['schemas']['AskAiTutorRequest']['required']);
        $this->assertSame(2000, $document['components']['schemas']['AskAiTutorRequest']['properties']['question']['maxLength']);
        $this->assertSame('#/components/schemas/AiTutorResponse', $document['paths'][$tutorPath]['post']['responses']['200']['content']['application/json']['schema']['$ref']);
        $this->assertSame('#/components/schemas/CreateTeacherAssistantDraftRequest', $document['paths'][$teacherAssistantPath]['post']['requestBody']['content']['application/json']['schema']['$ref']);
        $this->assertSame(['lesson_plan', 'course_outline', 'learning_objectives', 'mcqs', 'practice_exercises', 'homework_questions', 'revision_notes', 'rubric'], $document['components']['schemas']['CreateTeacherAssistantDraftRequest']['properties']['draft_type']['enum']);
        $this->assertSame('#/components/schemas/TeacherAssistantDraftResponse', $document['paths'][$teacherAssistantPath]['post']['responses']['200']['content']['application/json']['schema']['$ref']);

        $assertRequestSchema = function (string $path, string $method, string $schema) use ($document): void {
            $this->assertSame('#/components/schemas/'.$schema, $document['paths'][$path][$method]['requestBody']['content']['application/json']['schema']['$ref']);
        };
        $assertRequestSchema('/organizations/{organizationId}/announcements', 'post', 'StoreAnnouncementRequest');
        $assertRequestSchema('/organizations/{organizationId}/announcements/{announcementId}', 'patch', 'UpdateAnnouncementRequest');
        $this->assertSame(['title', 'body', 'audience'], $document['components']['schemas']['StoreAnnouncementRequest']['required']);
        $this->assertSame(['title', 'body'], $document['components']['schemas']['UpdateAnnouncementRequest']['required']);
        $assertRequestSchema('/organizations/{organizationId}/tasks', 'post', 'StoreOrganizationTaskRequest');
        $assertRequestSchema('/organizations/{organizationId}/tasks/{taskId}', 'patch', 'UpdateOrganizationTaskRequest');
        $this->assertSame(160, $document['components']['schemas']['StoreOrganizationTaskRequest']['properties']['title']['maxLength']);
        $this->assertSame(1, $document['components']['schemas']['UpdateOrganizationTaskRequest']['minProperties']);
        $assertRequestSchema('/organizations/{organizationId}/subscription/requests', 'post', 'RequestSubscriptionChangeRequest');
        $assertRequestSchema('/platform/organizations/{organizationId}/status', 'put', 'UpdateOrganizationStatusRequest');
        $assertRequestSchema('/platform/plans', 'post', 'CreateSubscriptionPlanRequest');
        $assertRequestSchema('/platform/plans/{planId}', 'put', 'UpdateSubscriptionPlanRequest');
        $assertRequestSchema('/platform/subscription-requests/{organizationId}/resolve', 'post', 'ResolveSubscriptionRequest');
        $assertRequestSchema('/platform/feature-flags/{flag}', 'put', 'SetPlatformFeatureFlagRequest');
        $this->assertSame('#/components/schemas/AnnouncementListResponse', $document['paths']['/organizations/{organizationId}/announcements']['get']['responses']['200']['content']['application/json']['schema']['$ref']);
        $this->assertSame('#/components/schemas/AnnouncementCreatedResponse', $document['paths']['/organizations/{organizationId}/announcements']['post']['responses']['201']['content']['application/json']['schema']['$ref']);
        $this->assertSame('#/components/schemas/OrganizationTaskListResponse', $document['paths']['/organizations/{organizationId}/tasks']['get']['responses']['200']['content']['application/json']['schema']['$ref']);
        $this->assertSame('#/components/schemas/OrganizationTaskResponse', $document['paths']['/organizations/{organizationId}/tasks']['post']['responses']['201']['content']['application/json']['schema']['$ref']);
        $this->assertContains('assigned_to_name', $document['components']['schemas']['OrganizationTaskRecord']['required']);
        $this->assertSame('#/components/schemas/OrganizationSubscriptionResponse', $document['paths']['/organizations/{organizationId}/subscription']['get']['responses']['200']['content']['application/json']['schema']['$ref']);
        $this->assertSame('#/components/schemas/SubscriptionPlanListResponse', $document['paths']['/platform/plans']['get']['responses']['200']['content']['application/json']['schema']['$ref']);
        $this->assertSame('#/components/schemas/SubscriptionRequestResolutionResponse', $document['paths']['/platform/subscription-requests/{organizationId}/resolve']['post']['responses']['200']['content']['application/json']['schema']['$ref']);
        $this->assertSame('#/components/schemas/PlatformOrganizationsResponse', $document['paths']['/platform/organizations']['get']['responses']['200']['content']['application/json']['schema']['$ref']);
        $this->assertSame('#/components/schemas/PlatformAuditLogsResponse', $document['paths']['/platform/audit-logs']['get']['responses']['200']['content']['application/json']['schema']['$ref']);
        $this->assertSame('#/components/schemas/PlatformUsageResponse', $document['paths']['/platform/usage']['get']['responses']['200']['content']['application/json']['schema']['$ref']);
        $this->assertSame('#/components/schemas/PlatformUsageHistoryResponse', $document['paths']['/platform/usage/history']['get']['responses']['200']['content']['application/json']['schema']['$ref']);
        $this->assertSame('#/components/schemas/PlatformFeatureFlagsResponse', $document['paths']['/platform/feature-flags']['get']['responses']['200']['content']['application/json']['schema']['$ref']);
        $this->assertSame('#/components/schemas/PlatformFeatureFlagResponse', $document['paths']['/platform/feature-flags/{flag}']['put']['responses']['200']['content']['application/json']['schema']['$ref']);
        $this->assertSame(365, $document['components']['schemas']['PlatformUsageHistoryResponse']['properties']['data']['properties']['days']['maximum']);
        $this->assertSame('#/components/schemas/PendingSubscriptionRequestsResponse', $document['paths']['/platform/subscription-requests']['get']['responses']['200']['content']['application/json']['schema']['$ref']);
        $webhook = $document['paths']['/webhooks/payments/sandbox']['post'];
        $this->assertTrue($webhook['requestBody']['required']);
        $this->assertSame('#/components/schemas/SandboxPaymentWebhookRequest', $webhook['requestBody']['content']['application/json']['schema']['$ref']);
        $this->assertSame('header', $webhook['parameters'][0]['in']);
        $this->assertSame('X-PaathNexus-Signature', $webhook['parameters'][0]['name']);
        $this->assertSame('#/components/schemas/SandboxPaymentWebhookResponse', $webhook['responses']['200']['content']['application/json']['schema']['$ref']);
        $this->assertSame('#/components/schemas/HealthResponse', $document['paths']['/health']['get']['responses']['200']['content']['application/json']['schema']['$ref']);
        $this->assertArrayHasKey('503', $document['paths']['/health/ready']['get']['responses']);
        $verifyEmailResponse = $document['paths']['/auth/verify-email/{id}/{hash}']['get']['responses']['302'];
        $this->assertArrayHasKey('Location', $verifyEmailResponse['headers']);
        $this->assertSame(['application/octet-stream'], array_keys($document['paths']['/organizations/{organizationId}/courses/{courseId}/assignments/{assignmentId}/attachments/{attachmentId}']['get']['responses']['200']['content']));
        $this->assertArrayHasKey('200', $document['paths']['/catalog/courses/{courseId}/orders']['post']['responses']);
        $this->assertArrayHasKey('201', $document['paths']['/catalog/courses/{courseId}/orders']['post']['responses']);

        $feedback = $document['paths']['/organizations/{organizationId}/feedback'];
        $this->assertSame(
            '#/components/schemas/StoreSupportFeedbackRequest',
            $feedback['post']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(['category', 'message'], $document['components']['schemas']['StoreSupportFeedbackRequest']['required']);
        $this->assertSame(['bug', 'idea', 'support', 'other'], $document['components']['schemas']['StoreSupportFeedbackRequest']['properties']['category']['enum']);
        $this->assertSame(
            '#/components/schemas/SupportFeedbackResponse',
            $feedback['post']['responses']['201']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/SupportFeedbackListResponse',
            $feedback['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/SupportFeedbackMineResponse',
            $document['paths']['/organizations/{organizationId}/feedback/mine']['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertArrayNotHasKey('submitter_name', $document['components']['schemas']['SupportFeedbackItem']['properties']);
        $this->assertArrayHasKey('submitter_name', $document['components']['schemas']['SupportFeedbackManagerItem']['allOf'][1]['properties']);

        $roles = $document['paths']['/organizations/{organizationId}/roles'];
        $this->assertSame(
            '#/components/schemas/OrganizationRolesIndexResponse',
            $roles['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/StoreOrganizationRoleRequest',
            $roles['post']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(['base_role', 'name', 'permissions'], $document['components']['schemas']['StoreOrganizationRoleRequest']['required']);
        $this->assertSame(
            '#/components/schemas/OrganizationRoleArchivedResponse',
            $document['paths']['/organizations/{organizationId}/roles/{roleId}']['delete']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $restore = $document['paths']['/organizations/{organizationId}/roles/{roleId}/restore']['post'];
        $this->assertArrayNotHasKey('requestBody', $restore);
        $this->assertSame(
            '#/components/schemas/OrganizationRoleResponse',
            $restore['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $assignment = $document['paths']['/organizations/{organizationId}/roles/{roleId}/members/{userId}']['put'];
        $this->assertArrayNotHasKey('requestBody', $assignment);
        $this->assertSame(
            '#/components/schemas/OrganizationRoleAssignmentResponse',
            $assignment['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/OrganizationRoleUnassignmentResponse',
            $document['paths']['/organizations/{organizationId}/role-members/{userId}']['delete']['responses']['200']['content']['application/json']['schema']['$ref'],
        );

        $notifications = $document['paths']['/organizations/{organizationId}/notifications'];
        $this->assertSame(
            '#/components/schemas/WorkspaceNotificationsResponse',
            $notifications['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(['announcement', 'feedback'], $document['components']['schemas']['WorkspaceNotificationItem']['properties']['type']['enum']);
        $markRead = $document['paths']['/organizations/{organizationId}/notifications/{notificationId}/read']['post'];
        $this->assertArrayNotHasKey('requestBody', $markRead);
        $this->assertSame(
            '#/components/schemas/WorkspaceNotificationReadResponse',
            $markRead['responses']['200']['content']['application/json']['schema']['$ref'],
        );

        $search = $document['paths']['/organizations/{organizationId}/search']['get'];
        $this->assertSame([['sanctumBearer' => []]], $search['security']);
        $this->assertContains('q', array_column($search['parameters'], 'name'));
        $searchQuery = array_column($search['parameters'], null, 'name')['q'];
        $this->assertSame(['type' => 'string', 'minLength' => 2, 'maxLength' => 80], $searchQuery['schema']);
        $this->assertSame(
            '#/components/schemas/WorkspaceSearchResponse',
            $search['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            ['class', 'student', 'course', 'task', 'invoice', 'announcement', 'attendance'],
            $document['components']['schemas']['WorkspaceSearchResult']['properties']['type']['enum'],
        );
        $dashboard = $document['paths']['/organizations/{organizationId}/dashboard']['get'];
        $this->assertSame(
            '#/components/schemas/WorkspaceDashboardResponse',
            $dashboard['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            ['integer', 'null'],
            $document['components']['schemas']['WorkspaceDashboardResponse']['properties']['data']['properties']['active_students_count']['type'],
        );
        $workspaceDashboard = $document['components']['schemas']['WorkspaceDashboardResponse']['properties']['data'];
        $this->assertSame([
            'active_students_count', 'active_classes_count', 'active_enrollments_count', 'unassigned_students_count', 'pending_tasks_count',
            'latest_attendance_rate', 'latest_attendance_class', 'latest_attendance_date', 'attendance_series', 'classes', 'today_schedule',
            'schedule_notice', 'schedule_weekday', 'active_academic_year_id',
        ], $workspaceDashboard['required']);
        $nullableWorkspaceMetrics = [
            'active_classes_count' => ['integer', 'null'], 'active_enrollments_count' => ['integer', 'null'],
            'unassigned_students_count' => ['integer', 'null'], 'pending_tasks_count' => ['integer', 'null'],
            'latest_attendance_rate' => ['number', 'null'], 'latest_attendance_class' => ['string', 'null'],
            'latest_attendance_date' => ['string', 'null'], 'schedule_notice' => ['string', 'null'],
            'active_academic_year_id' => ['string', 'null'],
        ];
        foreach ($nullableWorkspaceMetrics as $field => $types) {
            $this->assertSame($types, $workspaceDashboard['properties'][$field]['type'], "Workspace dashboard {$field} nullability must match permission-gated output.");
        }
        $workspaceSchedule = $document['components']['schemas']['WorkspaceDashboardScheduleEntry']['properties'];
        $this->assertSame('string', $workspaceSchedule['subject']['type']);
        foreach (['teacher_name', 'class_name', 'section_name', 'room', 'title'] as $field) {
            $this->assertSame(['string', 'null'], $workspaceSchedule[$field]['type'], "Workspace schedule {$field} can be absent for a valid schedule entry.");
        }
        $studentDashboard = $document['paths']['/organizations/{organizationId}/student/dashboard']['get'];
        $this->assertSame(
            '#/components/schemas/StudentDashboardResponse',
            $studentDashboard['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertContains('certificate', $document['components']['schemas']['StudentDashboardCourse']['required']);
        $this->assertSame(['assignment', 'course', 'review'], $document['components']['schemas']['StudentStudyRecommendation']['properties']['kind']['enum']);
        $this->assertSame(5, $document['components']['schemas']['StudentAttendanceSummary']['properties']['recent']['maxItems']);
        $academicReport = $document['paths']['/organizations/{organizationId}/reports/academic']['get'];
        $this->assertSame(
            '#/components/schemas/AcademicReportResponse',
            $academicReport['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            ['under_60', 'sixty_to_74', 'seventyfive_to_89', 'ninety_plus'],
            $document['components']['schemas']['AcademicReportAssessmentScoreBands']['required'],
        );
        $reportFilters = array_column($academicReport['parameters'], null, 'name');
        $this->assertSame(['type' => 'string', 'format' => 'date'], $reportFilters['from']['schema']);
        $this->assertSame(['type' => 'string', 'format' => 'uuid'], $reportFilters['student_id']['schema']);
        $assessmentPath = '/organizations/{organizationId}/courses/{courseId}/assessments';
        $this->assertSame(
            '#/components/schemas/AssessmentListResponse',
            $document['paths'][$assessmentPath]['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/AssessmentResponse',
            $document['paths'][$assessmentPath]['post']['responses']['201']['content']['application/json']['schema']['$ref'],
        );
        $assessmentCreate = $document['paths'][$assessmentPath]['post']['requestBody'];
        $this->assertTrue($assessmentCreate['required']);
        $this->assertSame('#/components/schemas/StoreAssessmentRequest', $assessmentCreate['content']['application/json']['schema']['$ref']);
        $this->assertSame(['title', 'attempts_allowed', 'questions'], $document['components']['schemas']['StoreAssessmentRequest']['required']);
        $this->assertSame(100, $document['components']['schemas']['StoreAssessmentRequest']['properties']['questions']['maxItems']);
        $questionPath = '/organizations/{organizationId}/courses/{courseId}/questions';
        $this->assertSame('#/components/schemas/StoreAssessmentQuestionRequest', $document['paths'][$questionPath]['post']['requestBody']['content']['application/json']['schema']['$ref']);
        $this->assertSame(8, $document['components']['schemas']['StoreAssessmentQuestionRequest']['properties']['options']['maxItems']);
        $this->assertSame(100, $document['components']['schemas']['AssessmentRubricCriterionInput']['properties']['weight']['maximum']);
        $this->assertSame('#/components/schemas/AssessmentQuestionResponse', $document['paths'][$questionPath]['post']['responses']['201']['content']['application/json']['schema']['$ref']);
        $this->assertSame('#/components/schemas/AssessmentQuestionListResponse', $document['paths'][$questionPath]['get']['responses']['200']['content']['application/json']['schema']['$ref']);
        $this->assertCount(3, $document['components']['schemas']['StoreAssessmentQuestionRequest']['allOf'][0]['oneOf']);
        foreach (['/organizations/{organizationId}/courses/{courseId}/questions/import', '/organizations/{organizationId}/courses/{courseId}/questions/import/preview'] as $importPath) {
            $importBody = $document['paths'][$importPath]['post']['requestBody'];
            $this->assertTrue($importBody['required']);
            $this->assertSame(['multipart/form-data'], array_keys($importBody['content']));
            $this->assertSame('binary', $importBody['content']['multipart/form-data']['schema']['properties']['file']['format']);
        }
        $this->assertSame('#/components/schemas/QuestionImportPreviewResponse', $document['paths']['/organizations/{organizationId}/courses/{courseId}/questions/import/preview']['post']['responses']['200']['content']['application/json']['schema']['$ref']);
        $this->assertSame('#/components/schemas/QuestionImportResponse', $document['paths']['/organizations/{organizationId}/courses/{courseId}/questions/import']['post']['responses']['201']['content']['application/json']['schema']['$ref']);
        $this->assertSame('#/components/schemas/QuestionImportValidationErrorResponse', $document['paths']['/organizations/{organizationId}/courses/{courseId}/questions/import']['post']['responses']['422']['content']['application/json']['schema']['$ref']);
        $answerPath = '/organizations/{organizationId}/assessments/{assessmentId}/attempts/{attemptId}/answers/{questionId}';
        $this->assertSame('#/components/schemas/SaveAssessmentAnswerRequest', $document['paths'][$answerPath]['put']['requestBody']['content']['application/json']['schema']['$ref']);
        $this->assertCount(2, $document['components']['schemas']['SaveAssessmentAnswerRequest']['oneOf']);
        $gradePath = '/organizations/{organizationId}/courses/{courseId}/assessments/{assessmentId}/attempts/{attemptId}/answers/{questionId}/grade';
        $this->assertSame('#/components/schemas/ManualAssessmentGradeRequest', $document['paths'][$gradePath]['put']['requestBody']['content']['application/json']['schema']['$ref']);
        $this->assertCount(2, $document['components']['schemas']['ManualAssessmentGradeRequest']['oneOf']);
        $analytics = $document['paths']['/organizations/{organizationId}/courses/{courseId}/assessments/{assessmentId}/analytics']['get'];
        $this->assertSame(
            '#/components/schemas/AssessmentAnalyticsResponse',
            $analytics['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertContains('student_id', array_column($analytics['parameters'], 'name'));
        $savedAnswerPath = '/organizations/{organizationId}/assessments/{assessmentId}/attempts/{attemptId}/answers/{questionId}';
        $this->assertSame('#/components/schemas/SavedAssessmentAnswerResponse', $document['paths'][$savedAnswerPath]['put']['responses']['200']['content']['application/json']['schema']['$ref']);
        $gradeAnswerPath = '/organizations/{organizationId}/courses/{courseId}/assessments/{assessmentId}/attempts/{attemptId}/answers/{questionId}/grade';
        $this->assertSame('#/components/schemas/AssessmentAnswerGradeResponse', $document['paths'][$gradeAnswerPath]['put']['responses']['200']['content']['application/json']['schema']['$ref']);
        $reviewPath = '/organizations/{organizationId}/courses/{courseId}/assessments/{assessmentId}/attempts/{attemptId}/review';
        $this->assertSame('#/components/schemas/AssessmentAttemptReviewResponse', $document['paths'][$reviewPath]['get']['responses']['200']['content']['application/json']['schema']['$ref']);
        $this->assertSame(['id', 'attempt_number', 'status', 'review_status', 'score', 'max_points', 'started_at', 'expires_at', 'submitted_at'], $document['components']['schemas']['AssessmentAttemptSummary']['required']);
        $this->assertSame(['in_progress', 'submitted', 'expired'], $document['components']['schemas']['AssessmentAttemptSummary']['properties']['status']['enum']);
        $reviewResponseData = $document['components']['schemas']['AssessmentAttemptReviewResponse']['properties']['data'];
        $this->assertSame(['attempt', 'questions'], $reviewResponseData['required']);
        $reviewQuestion = $document['components']['schemas']['AssessmentReviewQuestion'];
        $this->assertSame(['question_id', 'prompt', 'type', 'points', 'points_awarded', 'is_correct', 'response_text', 'grader_feedback', 'graded_at', 'grading_rubric', 'rubric_scores', 'selected_options', 'correct_options'], $reviewQuestion['required']);
        $this->assertSame(['boolean', 'null'], $reviewQuestion['properties']['is_correct']['type']);
        $this->assertSame(['string', 'null'], $reviewQuestion['properties']['grader_feedback']['type']);
        $this->assertSame(['string', 'null'], $reviewQuestion['properties']['graded_at']['type']);
        $this->assertSame(['array', 'null'], $reviewQuestion['properties']['grading_rubric']['type']);
        $this->assertSame(['array', 'null'], $reviewQuestion['properties']['rubric_scores']['type']);
        $this->assertSame('#/components/schemas/AssessmentRubricCriterionInput', $reviewQuestion['properties']['grading_rubric']['items']['$ref']);
        $this->assertSame('#/components/schemas/AssessmentRubricScoreRecord', $reviewQuestion['properties']['rubric_scores']['items']['$ref']);
        $this->assertSame(['id', 'label', 'weight'], $document['components']['schemas']['AssessmentRubricCriterionInput']['required']);
        $this->assertSame(['criterion_id', 'score'], $document['components']['schemas']['AssessmentRubricScoreRecord']['required']);
        $gradeExport = $document['paths']['/organizations/{organizationId}/courses/{courseId}/assessments/{assessmentId}/grades/export']['get']['responses']['200'];
        $this->assertSame(['text/csv'], array_keys($gradeExport['content']));
        $this->assertSame('#/components/schemas/AssessmentGradesCsvResponse', $gradeExport['content']['text/csv']['schema']['$ref']);
        $result = $document['paths']['/organizations/{organizationId}/assessments/{assessmentId}/attempts/{attemptId}/result']['get'];
        $this->assertSame(
            '#/components/schemas/StudentAssessmentResultResponse',
            $result['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(['attempt', 'answers'], $document['components']['schemas']['StudentAssessmentResultResponse']['properties']['data']['required']);
        $studentAnswer = $document['components']['schemas']['StudentAssessmentAnswer'];
        $this->assertSame(['question_id', 'prompt', 'type', 'points', 'points_awarded', 'response_text', 'selected_options', 'grader_feedback', 'pending_review'], $studentAnswer['required']);
        $this->assertSame(['string', 'null'], $studentAnswer['properties']['response_text']['type']);
        $this->assertSame(['string', 'null'], $studentAnswer['properties']['grader_feedback']['type']);
        $this->assertSame('#/components/schemas/AssessmentAnswerOption', $studentAnswer['properties']['selected_options']['items']['$ref']);
        $this->assertArrayNotHasKey('is_correct', $document['components']['schemas']['StudentAssessmentAnswer']['properties']);
        $this->assertArrayNotHasKey('is_correct', $document['components']['schemas']['StudentAttemptQuestion']['properties']);
        $coursePath = '/organizations/{organizationId}/courses';
        $this->assertSame(
            '#/components/schemas/CourseListResponse',
            $document['paths'][$coursePath]['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/CourseResponse',
            $document['paths'][$coursePath]['post']['responses']['201']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/StoreCourseRequest',
            $document['paths'][$coursePath]['post']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/UpdateCourseRequest',
            $document['paths']['/organizations/{organizationId}/courses/{courseId}']['put']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/CourseInstructorsResponse',
            $document['paths']['/organizations/{organizationId}/course-instructors']['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $instructorAssignment = $document['paths']['/organizations/{organizationId}/courses/{courseId}/instructors']['put'];
        $this->assertSame(
            '#/components/schemas/CourseResponse',
            $instructorAssignment['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/UpdateCourseInstructorsRequest',
            $instructorAssignment['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $lessonPath = '/organizations/{organizationId}/courses/{courseId}/lessons';
        $this->assertSame(
            '#/components/schemas/LessonListResponse',
            $document['paths'][$lessonPath]['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/LessonResponse',
            $document['paths'][$lessonPath]['post']['responses']['201']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/StoreLessonRequest',
            $document['paths'][$lessonPath]['post']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/LessonResponse',
            $document['paths']['/organizations/{organizationId}/courses/{courseId}/lessons/{lessonId}']['put']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/UpdateLessonRequest',
            $document['paths']['/organizations/{organizationId}/courses/{courseId}/lessons/{lessonId}']['put']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $progressPath = '/organizations/{organizationId}/courses/{courseId}/progress';
        $this->assertSame(
            '#/components/schemas/CourseProgressResponse',
            $document['paths'][$progressPath]['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $lessonProgressPath = '/organizations/{organizationId}/courses/{courseId}/lessons/{lessonId}/progress';
        $this->assertSame(
            '#/components/schemas/LessonProgressResponse',
            $document['paths'][$lessonProgressPath]['put']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/UpdateLessonProgressRequest',
            $document['paths'][$lessonProgressPath]['put']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $enrollmentPath = '/organizations/{organizationId}/courses/{courseId}/enrollments';
        $this->assertSame(
            '#/components/schemas/CourseEnrollmentsResponse',
            $document['paths'][$enrollmentPath]['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/CourseEnrollmentResponse',
            $document['paths'][$enrollmentPath]['post']['responses']['201']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/EnrollStudentRequest',
            $document['paths'][$enrollmentPath]['post']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $studyPlanPath = '/organizations/{organizationId}/student/study-plans';
        $this->assertSame(
            '#/components/schemas/StudentStudyPlanListResponse',
            $document['paths'][$studyPlanPath]['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/StudentStudyPlanResponse',
            $document['paths'][$studyPlanPath]['post']['responses']['201']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/StoreStudentStudyPlanRequest',
            $document['paths'][$studyPlanPath]['post']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/StudentStudyPlanResponse',
            $document['paths']['/organizations/{organizationId}/student/study-plans/{planId}/complete']['post']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $memberLinkPath = '/organizations/{organizationId}/students/{studentId}/member';
        $this->assertSame(
            '#/components/schemas/StudentMemberCandidatesResponse',
            $document['paths']['/organizations/{organizationId}/students/member-candidates']['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/StudentMemberLinkResponse',
            $document['paths'][$memberLinkPath]['put']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/StudentMemberLinkRequest',
            $document['paths'][$memberLinkPath]['put']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $guardianCandidatesPath = '/organizations/{organizationId}/guardian-candidates';
        $this->assertSame(
            '#/components/schemas/GuardianCandidatesResponse',
            $document['paths'][$guardianCandidatesPath]['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $guardianLinksPath = '/organizations/{organizationId}/guardian-student-links';
        $this->assertSame(
            '#/components/schemas/GuardianLinksResponse',
            $document['paths'][$guardianLinksPath]['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/GuardianLinkResponse',
            $document['paths'][$guardianLinksPath]['post']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/GuardianLinkResponse',
            $document['paths'][$guardianLinksPath]['post']['responses']['201']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/StoreGuardianLinkRequest',
            $document['paths'][$guardianLinksPath]['post']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/GuardianLinkEndedResponse',
            $document['paths']['/organizations/{organizationId}/guardian-student-links/{linkId}']['delete']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $guardianChildrenPath = '/organizations/{organizationId}/guardian/students';
        $this->assertSame(
            '#/components/schemas/GuardianChildrenResponse',
            $document['paths'][$guardianChildrenPath]['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            ['student_id', 'student_name', 'student_code', 'relationship'],
            $document['components']['schemas']['GuardianChildSummary']['required'],
        );
        $guardianChild = $document['components']['schemas']['GuardianChildSummary'];
        $this->assertNotContains('attendance', $guardianChild['required']);
        $this->assertNotContains('learning', $guardianChild['required']);
        $this->assertNotContains('results', $guardianChild['required']);
        $this->assertSame(['sessions_recorded', 'present', 'rate_percent', 'recent'], $document['components']['schemas']['GuardianAttendanceSummary']['required']);
        $this->assertSame(['courses_enrolled', 'completed_lessons', 'total_lessons', 'completion_percent', 'assignments'], $document['components']['schemas']['GuardianLearningSummary']['required']);
        $this->assertSame(['id', 'title', 'course_title', 'due_at', 'submission_status'], $document['components']['schemas']['GuardianHomework']['required']);
        $this->assertSame(['assessments_completed', 'average_percent', 'recent', 'upcoming_exams'], $document['components']['schemas']['GuardianResultsSummary']['required']);
        $this->assertSame(['number', 'null'], $document['components']['schemas']['GuardianAttendanceSummary']['properties']['rate_percent']['type']);
        $this->assertSame(10, $document['components']['schemas']['GuardianAttendanceSummary']['properties']['recent']['maxItems']);
        $this->assertSame(['string', 'null'], $document['components']['schemas']['GuardianAttendanceRecord']['properties']['date']['type']);
        $this->assertSame(['string', 'null'], $document['components']['schemas']['GuardianAttendanceRecord']['properties']['class_name']['type']);
        $this->assertContains('assignments', $document['components']['schemas']['GuardianLearningSummary']['required']);
        $this->assertSame(
            '#/components/schemas/GuardianHomework',
            $document['components']['schemas']['GuardianLearningSummary']['properties']['assignments']['items']['$ref'],
        );
        $this->assertSame(8, $document['components']['schemas']['GuardianLearningSummary']['properties']['assignments']['maxItems']);
        $this->assertSame(['string', 'null'], $document['components']['schemas']['GuardianHomework']['properties']['course_title']['type']);
        $this->assertSame(['string', 'null'], $document['components']['schemas']['GuardianHomework']['properties']['due_at']['type']);
        $this->assertSame(['submitted', 'graded', null], $document['components']['schemas']['GuardianHomework']['properties']['submission_status']['enum']);
        $this->assertSame(['string', 'null'], $document['components']['schemas']['GuardianAssessmentResult']['properties']['title']['type']);
        $this->assertSame(['string', 'null'], $document['components']['schemas']['GuardianAssessmentResult']['properties']['completed_at']['type']);
        $this->assertSame(['string', 'null'], $document['components']['schemas']['GuardianUpcomingExam']['properties']['course_title']['type']);
        $this->assertSame(['string', 'null'], $document['components']['schemas']['GuardianUpcomingExam']['properties']['closes_at']['type']);
        $this->assertSame(['number', 'null'], $document['components']['schemas']['GuardianResultsSummary']['properties']['average_percent']['type']);
        $this->assertSame(10, $document['components']['schemas']['GuardianResultsSummary']['properties']['recent']['maxItems']);
        $this->assertSame(10, $document['components']['schemas']['GuardianResultsSummary']['properties']['upcoming_exams']['maxItems']);
        $classPath = '/organizations/{organizationId}/classes';
        $this->assertSame(
            '#/components/schemas/ClassListResponse',
            $document['paths'][$classPath]['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/ClassResponse',
            $document['paths'][$classPath]['post']['responses']['201']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/StoreClassRequest',
            $document['paths'][$classPath]['post']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $studentPath = '/organizations/{organizationId}/students';
        $this->assertSame(
            '#/components/schemas/StudentListResponse',
            $document['paths'][$studentPath]['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/StudentResponse',
            $document['paths'][$studentPath]['post']['responses']['201']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/StoreStudentRequest',
            $document['paths'][$studentPath]['post']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $classEnrollmentPath = '/organizations/{organizationId}/classes/{classId}/enrollments';
        $this->assertSame(
            '#/components/schemas/ClassEnrollmentResponse',
            $document['paths'][$classEnrollmentPath]['post']['responses']['201']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/EnrollClassStudentRequest',
            $document['paths'][$classEnrollmentPath]['post']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $attendancePath = '/organizations/{organizationId}/attendance-sessions';
        $this->assertSame(
            '#/components/schemas/AttendanceSessionsResponse',
            $document['paths'][$attendancePath]['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/AttendanceSessionResponse',
            $document['paths'][$attendancePath]['post']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/LegacyAttendanceSessionRequest',
            $document['paths'][$attendancePath]['post']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $markAttendancePath = '/organizations/{organizationId}/classes/{classId}/attendance-sessions';
        $this->assertSame(
            '#/components/schemas/AttendanceSessionResponse',
            $document['paths'][$markAttendancePath]['post']['responses']['201']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/MarkClassAttendanceRequest',
            $document['paths'][$markAttendancePath]['post']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $academicYearsPath = '/organizations/{organizationId}/academic-years';
        $this->assertSame(
            '#/components/schemas/AcademicYearsResponse',
            $document['paths'][$academicYearsPath]['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/AcademicYearResponse',
            $document['paths'][$academicYearsPath]['post']['responses']['201']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/StoreAcademicYearRequest',
            $document['paths'][$academicYearsPath]['post']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/AcademicYearResponse',
            $document['paths']['/organizations/{organizationId}/academic-years/{yearId}/activate']['post']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/SectionsResponse',
            $document['paths']['/organizations/{organizationId}/sections']['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/SectionResponse',
            $document['paths']['/organizations/{organizationId}/classes/{classId}/sections']['post']['responses']['201']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/SubjectsResponse',
            $document['paths']['/organizations/{organizationId}/subjects']['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/SubjectResponse',
            $document['paths']['/organizations/{organizationId}/subjects']['post']['responses']['201']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/StoreSubjectRequest',
            $document['paths']['/organizations/{organizationId}/subjects']['post']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $subjectOfferingsPath = '/organizations/{organizationId}/classes/{classId}/subject-offerings';
        $this->assertSame(
            '#/components/schemas/SubjectOfferingsResponse',
            $document['paths'][$subjectOfferingsPath]['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertContains('academic_year_id', array_column($document['paths'][$subjectOfferingsPath]['get']['parameters'], 'name'));
        $this->assertSame(
            '#/components/schemas/SubjectOfferingResponse',
            $document['paths'][$subjectOfferingsPath]['post']['responses']['201']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/StoreSubjectOfferingRequest',
            $document['paths'][$subjectOfferingsPath]['post']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/SubjectOfferingEndedResponse',
            $document['paths'][$subjectOfferingsPath.'/{offeringId}']['delete']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $classTeachersPath = '/organizations/{organizationId}/classes/{classId}/teachers';
        $this->assertSame(
            '#/components/schemas/ClassTeacherAssignmentsResponse',
            $document['paths'][$classTeachersPath]['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/ClassTeacherAssignmentResponse',
            $document['paths'][$classTeachersPath]['post']['responses']['201']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/StoreClassTeacherAssignmentRequest',
            $document['paths'][$classTeachersPath]['post']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $teacherAssignmentPath = $classTeachersPath.'/{assignmentId}';
        $this->assertSame(
            '#/components/schemas/ClassTeacherAssignmentResponse',
            $document['paths'][$teacherAssignmentPath]['put']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/ClassTeacherAssignmentEndedResponse',
            $document['paths'][$teacherAssignmentPath]['delete']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $historyPath = '/organizations/{organizationId}/classes/{classId}/teacher-assignment-history';
        $this->assertSame(
            '#/components/schemas/ClassTeacherAssignmentHistoryResponse',
            $document['paths'][$historyPath]['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $timetablePath = '/organizations/{organizationId}/timetable-entries';
        $this->assertSame(
            '#/components/schemas/TimetableEntriesResponse',
            $document['paths'][$timetablePath]['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertContains('weekday', array_column($document['paths'][$timetablePath]['get']['parameters'], 'name'));
        $this->assertSame(
            '#/components/schemas/TimetableEntryResponse',
            $document['paths'][$timetablePath]['post']['responses']['201']['content']['application/json']['schema']['$ref'],
        );
        $timetableUpdatePath = $timetablePath.'/{entryId}';
        $this->assertSame(
            '#/components/schemas/TimetableEntryResponse',
            $document['paths'][$timetableUpdatePath]['put']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/StoreTimetableEntryRequest',
            $document['paths'][$timetableUpdatePath]['put']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $teachersPath = '/organizations/{organizationId}/teachers';
        $this->assertSame(
            '#/components/schemas/TeacherProfilesResponse',
            $document['paths'][$teachersPath]['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/TeacherProfileResponse',
            $document['paths'][$teachersPath]['post']['responses']['201']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/StoreTeacherProfileRequest',
            $document['paths'][$teachersPath]['post']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $teacherStatus = $document['components']['schemas']['TeacherProfileFields']['properties']['status'];
        $this->assertSame(['active', 'inactive'], $teacherStatus['enum']);
        $this->assertSame(
            '#/components/schemas/TeacherMemberCandidatesResponse',
            $document['paths'][$teachersPath.'/member-candidates']['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $teacherMemberPath = $teachersPath.'/{teacherId}/member';
        $this->assertSame(
            '#/components/schemas/TeacherProfileResponse',
            $document['paths'][$teacherMemberPath]['put']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/LinkTeacherMemberRequest',
            $document['paths'][$teacherMemberPath]['put']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $publicTeacherProfilePath = $teachersPath.'/{teacherId}/public-profile';
        $this->assertSame(
            '#/components/schemas/TeacherPublicProfileResponse',
            $document['paths'][$publicTeacherProfilePath]['put']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/UpdateTeacherPublicProfileRequest',
            $document['paths'][$publicTeacherProfilePath]['put']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            ['string', 'null'],
            $document['components']['schemas']['TeacherProfileRecord']['allOf'][1]['properties']['public_title']['type'],
        );
        $scheduleExceptionsPath = '/organizations/{organizationId}/schedule-exceptions';
        $this->assertSame(
            '#/components/schemas/ScheduleExceptionsResponse',
            $document['paths'][$scheduleExceptionsPath]['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertContains('academic_year_id', array_column($document['paths'][$scheduleExceptionsPath]['get']['parameters'], 'name'));
        $this->assertSame(
            '#/components/schemas/ScheduleExceptionResponse',
            $document['paths'][$scheduleExceptionsPath]['post']['responses']['201']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/StoreScheduleExceptionRequest',
            $document['paths'][$scheduleExceptionsPath]['post']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/ScheduleExceptionResponse',
            $document['paths'][$scheduleExceptionsPath.'/{exceptionId}/cancel']['post']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $liveSessionsPath = '/organizations/{organizationId}/live-sessions';
        $this->assertSame(
            '#/components/schemas/LiveSessionsResponse',
            $document['paths'][$liveSessionsPath]['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/LiveSessionResponse',
            $document['paths'][$liveSessionsPath]['post']['responses']['201']['content']['application/json']['schema']['$ref'],
        );
        $this->assertTrue($document['paths'][$liveSessionsPath]['post']['requestBody']['required']);
        $this->assertSame(
            '#/components/schemas/LiveSessionJoinResponse',
            $document['paths'][$liveSessionsPath.'/{sessionId}/join']['post']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/LiveSessionRosterResponse',
            $document['paths'][$liveSessionsPath.'/{sessionId}/roster']['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/RecordLiveSessionAttendanceResponse',
            $document['paths'][$liveSessionsPath.'/{sessionId}/attendance']['post']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/RecordLiveSessionAttendanceRequest',
            $document['paths'][$liveSessionsPath.'/{sessionId}/attendance']['post']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $assignmentPath = '/organizations/{organizationId}/courses/{courseId}/assignments';
        $this->assertSame(
            '#/components/schemas/AssignmentListResponse',
            $document['paths'][$assignmentPath]['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $assignmentRecord = $document['components']['schemas']['AssignmentRecord'];
        $this->assertSame(['id', 'course_id', 'lesson_id', 'lesson_title', 'title', 'instructions', 'due_at', 'max_points', 'status', 'submissions_count', 'resource_attachments'], $assignmentRecord['required']);
        $this->assertNotContains('my_submission', $assignmentRecord['required']);
        $this->assertSame(['integer', 'null'], $assignmentRecord['properties']['submissions_count']['type']);
        $this->assertSame(['draft', 'published'], $assignmentRecord['properties']['status']['enum']);
        $assignmentSubmission = $document['components']['schemas']['AssignmentSubmission'];
        $this->assertSame(['id', 'status', 'submission_text', 'submitted_at', 'score', 'feedback', 'graded_at', 'attachments'], $assignmentSubmission['required']);
        $this->assertSame(['submitted', 'graded'], $assignmentSubmission['properties']['status']['enum']);
        $this->assertSame(['string', 'null'], $assignmentSubmission['properties']['submission_text']['type']);
        $this->assertSame(['number', 'null'], $assignmentSubmission['properties']['score']['type']);
        $this->assertSame(['string', 'null'], $assignmentSubmission['properties']['feedback']['type']);
        $this->assertSame(['string', 'null'], $assignmentSubmission['properties']['graded_at']['type']);
        $this->assertSame('#/components/schemas/AssignmentAttachment', $assignmentSubmission['properties']['attachments']['items']['$ref']);
        $this->assertSame(['id', 'name', 'mime_type', 'size_bytes'], $document['components']['schemas']['AssignmentAttachment']['required']);
        $this->assertSame(
            '#/components/schemas/AssignmentResponse',
            $document['paths'][$assignmentPath]['post']['responses']['201']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            '#/components/schemas/StoreAssignmentRequest',
            $document['paths'][$assignmentPath]['post']['requestBody']['content']['application/json']['schema']['$ref'],
        );
        $submissionPath = '/organizations/{organizationId}/assignments/{assignmentId}/submissions';
        $this->assertArrayHasKey('multipart/form-data', $document['paths'][$submissionPath]['post']['requestBody']['content']);
        $this->assertTrue($document['paths'][$submissionPath]['post']['requestBody']['required']);
        $this->assertSame(
            '#/components/schemas/AssignmentSubmissionResponse',
            $document['paths'][$submissionPath]['post']['responses']['201']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(['submission_text'], $document['components']['schemas']['SubmitAssignmentRequest']['required']);
        $this->assertSame(50000, $document['components']['schemas']['SubmitAssignmentRequest']['properties']['submission_text']['maxLength']);
        $this->assertSame(5, $document['components']['schemas']['SubmitAssignmentMultipartRequest']['properties']['attachments']['maxItems']);
        $this->assertSame(
            '#/components/schemas/AssignmentSubmissionsResponse',
            $document['paths']['/organizations/{organizationId}/courses/{courseId}/assignments/{assignmentId}/submissions']['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );

        $operationIds = [];
        foreach ($document['paths'] as $path) {
            foreach ($path as $operation) {
                $operationIds[] = $operation['operationId'];
            }
        }
        $this->assertCount(count(array_unique($operationIds)), $operationIds, 'OpenAPI operation IDs must be unique.');
        $this->assertGreaterThanOrEqual(160, count($operationIds));
    }
}
