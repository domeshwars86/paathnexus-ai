<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

class ExportOpenApi extends Command
{
    protected $signature = 'openapi:export {--check : Verify the checked-in document matches the current API routes}';

    protected $description = 'Export or verify the OpenAPI 3.1 route contract';

    public function handle(): int
    {
        $json = json_encode($this->document(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        $path = dirname(base_path()).DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'openapi.json';

        if ($this->option('check')) {
            if (! is_file($path) || file_get_contents($path) !== $json) {
                $this->components->error('docs/openapi.json is out of date. Run php artisan openapi:export.');
                return self::FAILURE;
            }

            $this->components->info('docs/openapi.json matches the registered API routes.');
            return self::SUCCESS;
        }

        if (file_put_contents($path, $json, LOCK_EX) === false) {
            $this->components->error('Could not write docs/openapi.json.');
            return self::FAILURE;
        }

        $this->components->info('Wrote docs/openapi.json from the current API route collection.');
        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function document(): array
    {
        $paths = [];
        $tags = [];
        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/')) continue;
            $path = '/'.substr($route->uri(), strlen('api/v1/'));
            $tag = $this->tag($route);
            $tags[$tag] = ['name' => $tag];

            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                $httpMethod = strtolower($method);
                $operationId = $this->operationId($route, $httpMethod, $path);
                $operation = [
                    'operationId' => $operationId,
                    'summary' => $this->summary($route, $httpMethod),
                    'description' => 'Request validation, response fields, capabilities, and tenant scope are documented in [API_SPECIFICATION.md](API_SPECIFICATION.md).',
                    'tags' => [$tag],
                    'parameters' => $this->pathParameters($path),
                    'responses' => $this->responses($route, $httpMethod),
                ];

                $queryParameters = $this->queryParameters($path, $httpMethod);
                if ($queryParameters !== []) {
                    $operation['parameters'] = [...$operation['parameters'], ...$queryParameters];
                }
                if ($route->uri() === 'api/v1/webhooks/payments/sandbox') {
                    $operation['parameters'][] = [
                        'name' => 'X-PaathNexus-Signature', 'in' => 'header', 'required' => true,
                        'description' => 'Lowercase hexadecimal HMAC-SHA256 of the exact raw JSON request body.',
                        'schema' => ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'],
                    ];
                }

                if ($this->requiresBearerToken($route)) {
                    $operation['security'] = [['sanctumBearer' => []]];
                }

                if (in_array($httpMethod, ['post', 'put', 'patch'], true) && $this->acceptsJsonBody($route)) {
                    $operation['requestBody'] = [
                        'required' => $this->requestBodyRequired($route),
                        'description' => str_contains($route->uri(), '/attachments')
                            ? 'Upload one or more private assignment files. See API_SPECIFICATION.md for limits and accepted file types.'
                            : 'The endpoint accepts JSON input. See API_SPECIFICATION.md for field-level validation.',
                        'content' => $this->requestContent($route),
                    ];
                }

                $paths[$path][$httpMethod] = $operation;
            }
        }

        ksort($paths);
        ksort($tags);

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'PaathNexus AI API',
                'version' => '1.0.0',
                'description' => 'Versioned Laravel API. This route contract is generated from the registered API routes; field-level behavior and authorization rules are described in API_SPECIFICATION.md.',
            ],
            'servers' => [['url' => '/api/v1', 'description' => 'Current API version']],
            'tags' => array_values($tags),
            'paths' => (object) $paths,
            'components' => [
                'securitySchemes' => [
                    'sanctumBearer' => [
                        'type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'Sanctum personal access token',
                        'description' => 'Use the token returned by the sign-in endpoint. Tokens are tab-scoped and expire according to SANCTUM_TOKEN_EXPIRATION.',
                    ],
                ],
                'schemas' => [
                    'JsonObject' => ['type' => 'object', 'additionalProperties' => true],
                    'StoreSupportFeedbackRequest' => [
                        'type' => 'object', 'required' => ['category', 'message'],
                        'properties' => [
                            'category' => ['type' => 'string', 'enum' => ['bug', 'idea', 'support', 'other']],
                            'message' => ['type' => 'string', 'minLength' => 10, 'maxLength' => 5000],
                        ],
                    ],
                    'UpdateSupportFeedbackRequest' => [
                        'type' => 'object', 'required' => ['status'],
                        'properties' => [
                            'status' => ['type' => 'string', 'enum' => ['open', 'resolved']],
                            'response' => ['type' => ['string', 'null'], 'maxLength' => 5000],
                        ],
                    ],
                    'SupportFeedbackItem' => [
                        'type' => 'object', 'required' => ['id', 'category', 'message', 'status', 'response', 'created_at', 'responded_at'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'],
                            'category' => ['type' => 'string', 'enum' => ['bug', 'idea', 'support', 'other']],
                            'message' => ['type' => 'string'],
                            'status' => ['type' => 'string', 'enum' => ['open', 'resolved']],
                            'response' => ['type' => ['string', 'null']],
                            'created_at' => ['type' => 'string', 'format' => 'date-time'],
                            'responded_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                        ],
                    ],
                    'SupportFeedbackManagerItem' => [
                        'allOf' => [
                            ['$ref' => '#/components/schemas/SupportFeedbackItem'],
                            [
                                'type' => 'object', 'required' => ['submitter_name', 'responder_name'],
                                'properties' => [
                                    'submitter_name' => ['type' => ['string', 'null']],
                                    'responder_name' => ['type' => ['string', 'null']],
                                ],
                            ],
                        ],
                    ],
                    'SupportFeedbackResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/SupportFeedbackItem']],
                    ],
                    'SupportFeedbackManagerResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/SupportFeedbackManagerItem']],
                    ],
                    'SupportFeedbackListResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'maxItems' => 100, 'items' => ['$ref' => '#/components/schemas/SupportFeedbackManagerItem']]],
                    ],
                    'SupportFeedbackMineResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'maxItems' => 100, 'items' => ['$ref' => '#/components/schemas/SupportFeedbackItem']]],
                    ],
                    'StoreOrganizationRoleRequest' => [
                        'type' => 'object', 'required' => ['base_role', 'name', 'permissions'],
                        'properties' => [
                            'base_role' => ['type' => 'string', 'enum' => ['admin', 'teacher', 'student', 'guardian', 'educator']],
                            'name' => ['type' => 'string', 'maxLength' => 80],
                            'permissions' => ['type' => 'array', 'minItems' => 1, 'uniqueItems' => true, 'items' => ['type' => 'string'], 'description' => 'Must be allowed for base_role and retain all core and dependency permissions.'],
                        ],
                    ],
                    'UpdateOrganizationRoleRequest' => [
                        'type' => 'object', 'required' => ['name', 'permissions'],
                        'properties' => [
                            'base_role' => ['type' => 'string', 'enum' => ['admin', 'teacher', 'student', 'guardian', 'educator'], 'description' => 'Optional; when supplied it must match the role’s existing base_role.'],
                            'name' => ['type' => 'string', 'maxLength' => 80],
                            'permissions' => ['type' => 'array', 'minItems' => 1, 'uniqueItems' => true, 'items' => ['type' => 'string'], 'description' => 'Must be allowed for the role’s base_role and retain all core and dependency permissions.'],
                        ],
                    ],
                    'OrganizationRole' => [
                        'type' => 'object', 'required' => ['id', 'name', 'base_role', 'permissions', 'archived_at'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'name' => ['type' => 'string'],
                            'base_role' => ['type' => 'string', 'enum' => ['admin', 'teacher', 'student', 'guardian', 'educator']],
                            'permissions' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'archived_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                        ],
                    ],
                    'OrganizationRoleListItem' => [
                        'allOf' => [
                            ['$ref' => '#/components/schemas/OrganizationRole'],
                            ['type' => 'object', 'required' => ['member_count'], 'properties' => ['member_count' => ['type' => 'integer', 'minimum' => 0]]],
                        ],
                    ],
                    'OrganizationRoleMember' => [
                        'type' => 'object', 'required' => ['id', 'name', 'base_role', 'custom_role_id'],
                        'properties' => [
                            'id' => ['type' => 'integer'], 'name' => ['type' => 'string'],
                            'base_role' => ['type' => 'string', 'enum' => ['admin', 'teacher', 'student', 'guardian', 'educator']],
                            'custom_role_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                        ],
                    ],
                    'OrganizationRolesIndexResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => [
                            'type' => 'object', 'required' => ['base_roles', 'permissions_by_role', 'roles', 'members'],
                            'properties' => [
                                'base_roles' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['admin', 'teacher', 'student', 'guardian', 'educator']]],
                                'permissions_by_role' => ['type' => 'object', 'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']], 'description' => 'Assignable permissions vary by base role; educator permissions are the configured cross-role set excluding wildcard grants.'],
                                'roles' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/OrganizationRoleListItem']],
                                'members' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/OrganizationRoleMember']],
                            ],
                        ]],
                    ],
                    'OrganizationRoleResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/OrganizationRole']],
                    ],
                    'OrganizationRoleArchivedResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'object', 'required' => ['id', 'archived'], 'properties' => ['id' => ['type' => 'string', 'format' => 'uuid'], 'archived' => ['const' => true]]]],
                    ],
                    'OrganizationRoleAssignmentResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'object', 'required' => ['user_id', 'custom_role_id'], 'properties' => ['user_id' => ['type' => 'integer'], 'custom_role_id' => ['type' => 'string', 'format' => 'uuid']]]],
                    ],
                    'OrganizationRoleUnassignmentResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'object', 'required' => ['user_id', 'custom_role_id'], 'properties' => ['user_id' => ['type' => 'integer'], 'custom_role_id' => ['type' => 'null']]]],
                    ],
                    'WorkspaceNotificationItem' => [
                        'type' => 'object', 'required' => ['id', 'type', 'announcement_id', 'feedback_id', 'title', 'body', 'created_at', 'read_at'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'],
                            'type' => ['type' => 'string', 'enum' => ['announcement', 'feedback']],
                            'announcement_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                            'feedback_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                            'title' => ['type' => 'string'], 'body' => ['type' => 'string'],
                            'created_at' => ['type' => 'string', 'format' => 'date-time'],
                            'read_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                        ],
                        'description' => 'Exactly one of announcement_id or feedback_id is non-null, according to type. Items are visible only to their recipient.',
                    ],
                    'WorkspaceNotificationsResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'object', 'required' => ['unread_count', 'items'], 'properties' => [
                            'unread_count' => ['type' => 'integer', 'minimum' => 0],
                            'items' => ['type' => 'array', 'maxItems' => 100, 'items' => ['$ref' => '#/components/schemas/WorkspaceNotificationItem']],
                        ]]],
                    ],
                    'WorkspaceNotificationReadResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'object', 'required' => ['id', 'read_at'], 'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'],
                            'read_at' => ['type' => 'string', 'format' => 'date-time'],
                        ]]],
                    ],
                    'AuthLoginRequest' => [
                        'type' => 'object', 'required' => ['email', 'password'],
                        'properties' => [
                            'email' => ['type' => 'string', 'format' => 'email', 'maxLength' => 254],
                            'password' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200],
                        ],
                    ],
                    'UpdateAuthProfileRequest' => [
                        'type' => 'object', 'required' => ['name'],
                        'properties' => ['name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 120]],
                    ],
                    'AuthProfileUpdateResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'object', 'required' => ['user'], 'properties' => ['user' => ['$ref' => '#/components/schemas/AuthUser']]]],
                    ],
                    'AuthRegisterRequest' => [
                        'type' => 'object', 'required' => ['name', 'email', 'password', 'password_confirmation', 'organization_name', 'organization_type'],
                        'properties' => [
                            'name' => ['type' => 'string', 'maxLength' => 120],
                            'email' => ['type' => 'string', 'format' => 'email', 'maxLength' => 254],
                            'password' => ['type' => 'string', 'minLength' => 12, 'description' => 'Must contain letters, mixed case, and numbers.'],
                            'password_confirmation' => ['type' => 'string', 'minLength' => 12],
                            'organization_name' => ['type' => 'string', 'maxLength' => 160],
                            'organization_type' => ['type' => 'string', 'enum' => ['school', 'college', 'coaching', 'academy', 'educator']],
                        ],
                    ],
                    'AuthInvitationRegistrationRequest' => [
                        'type' => 'object', 'required' => ['token', 'name', 'password', 'password_confirmation'],
                        'properties' => [
                            'token' => ['type' => 'string', 'minLength' => 64, 'maxLength' => 64],
                            'name' => ['type' => 'string', 'maxLength' => 120],
                            'password' => ['type' => 'string', 'minLength' => 12, 'description' => 'Must contain letters, mixed case, and numbers.'],
                            'password_confirmation' => ['type' => 'string', 'minLength' => 12],
                        ],
                    ],
                    'AuthEmailRequest' => [
                        'type' => 'object', 'required' => ['email'],
                        'properties' => ['email' => ['type' => 'string', 'format' => 'email', 'maxLength' => 254]],
                    ],
                    'AuthResetPasswordRequest' => [
                        'type' => 'object', 'required' => ['email', 'token', 'password', 'password_confirmation'],
                        'properties' => [
                            'email' => ['type' => 'string', 'format' => 'email', 'maxLength' => 254],
                            'token' => ['type' => 'string'],
                            'password' => ['type' => 'string', 'minLength' => 12, 'description' => 'Must contain letters, mixed case, and numbers.'],
                            'password_confirmation' => ['type' => 'string', 'minLength' => 12],
                        ],
                    ],
                    'AuthUser' => [
                        'type' => 'object', 'required' => ['name', 'email', 'is_platform_admin'],
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'email' => ['type' => 'string', 'format' => 'email'],
                            'is_platform_admin' => ['type' => 'boolean'],
                        ],
                    ],
                    'OrganizationMembership' => [
                        'type' => 'object', 'required' => ['id', 'name', 'slug', 'type', 'role', 'custom_role_id', 'custom_role_name', 'custom_role_base_role', 'permissions', 'locale', 'timezone'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'],
                            'name' => ['type' => 'string'], 'slug' => ['type' => 'string'],
                            'type' => ['type' => 'string', 'enum' => ['school', 'college', 'coaching', 'academy', 'educator']],
                            'role' => ['type' => 'string', 'enum' => ['owner', 'admin', 'educator', 'teacher', 'student', 'guardian']],
                            'custom_role_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                            'custom_role_name' => ['type' => ['string', 'null']],
                            'custom_role_base_role' => ['type' => ['string', 'null'], 'enum' => ['admin', 'educator', 'teacher', 'student', 'guardian', null]],
                            'permissions' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'locale' => ['type' => 'string'], 'timezone' => ['type' => 'string'],
                        ],
                    ],
                    'StoreOrganizationRequest' => [
                        'type' => 'object', 'required' => ['name', 'type'],
                        'properties' => [
                            'name' => ['type' => 'string', 'maxLength' => 160],
                            'type' => ['type' => 'string', 'enum' => ['school', 'college', 'coaching', 'academy', 'educator']],
                            'locale' => ['type' => 'string', 'maxLength' => 12],
                            'timezone' => ['type' => 'string', 'maxLength' => 64],
                        ],
                    ],
                    'UpdateOrganizationRequest' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 160],
                            'locale' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 12],
                            'timezone' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 64],
                        ],
                    ],
                    'OrganizationResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/OrganizationMembership']],
                    ],
                    'OrganizationListResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/OrganizationMembership']]],
                    ],
                    'OrganizationMember' => [
                        'type' => 'object', 'required' => ['id', 'name', 'email', 'role', 'custom_role_id', 'custom_role_name', 'status', 'joined_at'],
                        'properties' => [
                            'id' => ['type' => 'integer'], 'name' => ['type' => 'string'], 'email' => ['type' => 'string', 'format' => 'email'],
                            'role' => ['type' => 'string', 'enum' => ['owner', 'admin', 'educator', 'teacher', 'student', 'guardian']],
                            'custom_role_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                            'custom_role_name' => ['type' => ['string', 'null']],
                            'status' => ['const' => 'active'], 'joined_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                        ],
                    ],
                    'OrganizationMembersResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/OrganizationMember']]],
                    ],
                    'StoreOrganizationInvitationRequest' => [
                        'type' => 'object', 'required' => ['email', 'role'],
                        'properties' => [
                            'email' => ['type' => 'string', 'format' => 'email', 'maxLength' => 254],
                            'role' => ['type' => 'string', 'enum' => ['teacher', 'student', 'guardian']],
                        ],
                    ],
                    'AcceptOrganizationInvitationRequest' => [
                        'type' => 'object', 'required' => ['token'],
                        'properties' => ['token' => ['type' => 'string', 'minLength' => 64, 'maxLength' => 64]],
                    ],
                    'OrganizationInvitation' => [
                        'type' => 'object', 'required' => ['id', 'email', 'role', 'status', 'inviter_name', 'expires_at', 'accepted_at', 'created_at'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'],
                            'email' => ['type' => 'string', 'format' => 'email'],
                            'role' => ['type' => 'string', 'enum' => ['teacher', 'student', 'guardian']],
                            'status' => ['type' => 'string', 'enum' => ['pending', 'accepted', 'expired', 'revoked']],
                            'inviter_name' => ['type' => ['string', 'null']],
                            'expires_at' => ['type' => 'string', 'format' => 'date-time'],
                            'accepted_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                            'created_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                        ],
                    ],
                    'OrganizationInvitationResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/OrganizationInvitation']],
                    ],
                    'OrganizationInvitationCreatedResponse' => [
                        'type' => 'object', 'required' => ['data', 'token'],
                        'properties' => [
                            'data' => ['$ref' => '#/components/schemas/OrganizationInvitation'],
                            'token' => ['type' => 'string', 'minLength' => 64, 'maxLength' => 64, 'description' => 'One-time invitation token; keep private and deliver only to the invited email.'],
                        ],
                    ],
                    'OrganizationInvitationListResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'maxItems' => 100, 'items' => ['$ref' => '#/components/schemas/OrganizationInvitation']]],
                    ],
                    'OrganizationInvitationAcceptedResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'object', 'required' => ['organization'], 'properties' => ['organization' => ['type' => 'object', 'required' => ['id', 'name', 'role'], 'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'name' => ['type' => 'string'],
                            'role' => ['type' => 'string', 'enum' => ['teacher', 'student', 'guardian']],
                        ]]]]],
                    ],
                    'StoreFeeInvoiceRequest' => [
                        'type' => 'object', 'required' => ['student_id', 'title', 'amount'],
                        'properties' => [
                            'student_id' => ['type' => 'string', 'format' => 'uuid'],
                            'title' => ['type' => 'string', 'maxLength' => 180],
                            'description' => ['type' => ['string', 'null'], 'maxLength' => 2000],
                            'amount' => ['type' => 'number', 'exclusiveMinimum' => 0, 'maximum' => 10000000, 'multipleOf' => 0.01],
                            'due_on' => ['type' => ['string', 'null'], 'format' => 'date', 'description' => 'If supplied, must be today or a future date.'],
                        ],
                    ],
                    'RecordFeePaymentRequest' => [
                        'type' => 'object', 'required' => ['amount', 'method', 'transaction_reference', 'idempotency_key'],
                        'properties' => [
                            'amount' => ['type' => 'number', 'exclusiveMinimum' => 0, 'maximum' => 10000000, 'multipleOf' => 0.01],
                            'method' => ['type' => 'string', 'enum' => ['cash', 'bank_transfer', 'upi', 'cheque', 'other']],
                            'transaction_reference' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 120],
                            'idempotency_key' => ['type' => 'string', 'format' => 'uuid'],
                        ],
                    ],
                    'FeePayment' => [
                        'type' => 'object', 'required' => ['id', 'amount_paise', 'method', 'transaction_reference', 'receipt_number', 'received_at'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'amount_paise' => ['type' => 'integer', 'minimum' => 1],
                            'method' => ['type' => 'string', 'enum' => ['cash', 'bank_transfer', 'upi', 'cheque', 'other']],
                            'transaction_reference' => ['type' => 'string'], 'receipt_number' => ['type' => 'string'],
                            'received_at' => ['type' => 'string', 'format' => 'date-time'],
                        ],
                    ],
                    'FeeInvoice' => [
                        'type' => 'object', 'required' => ['id', 'invoice_number', 'student_id', 'student_name', 'student_code', 'title', 'description', 'amount_paise', 'due_on', 'balance_paise', 'payments', 'status', 'created_at'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'invoice_number' => ['type' => 'string'],
                            'student_id' => ['type' => 'string', 'format' => 'uuid'],
                            'student_name' => ['type' => ['string', 'null']], 'student_code' => ['type' => ['string', 'null']],
                            'title' => ['type' => 'string'], 'description' => ['type' => ['string', 'null']],
                            'amount_paise' => ['type' => 'integer', 'minimum' => 1], 'due_on' => ['type' => ['string', 'null'], 'format' => 'date'],
                            'balance_paise' => ['type' => 'integer', 'minimum' => 0],
                            'payments' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/FeePayment']],
                            'status' => ['type' => 'string', 'enum' => ['issued', 'partially_paid', 'paid', 'void']],
                            'created_at' => ['type' => 'string', 'format' => 'date-time'],
                        ],
                    ],
                    'FeeInvoiceResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/FeeInvoice']],
                    ],
                    'FeeInvoiceListResponse' => [
                        'type' => 'object', 'required' => ['data', 'meta'],
                        'properties' => [
                            'data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/FeeInvoice']],
                            'meta' => ['$ref' => '#/components/schemas/FeeInvoicePaginationMeta'],
                        ],
                    ],
                    'FeeInvoicePaginationMeta' => [
                        'type' => 'object', 'required' => ['current_page', 'last_page', 'per_page', 'total'],
                        'properties' => [
                            'current_page' => ['type' => 'integer', 'minimum' => 1], 'last_page' => ['type' => 'integer', 'minimum' => 1],
                            'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100], 'total' => ['type' => 'integer', 'minimum' => 0],
                        ],
                    ],
                    'FeePaymentResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/FeePayment']],
                    ],
                    'FeeInvoiceVoidResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'object', 'required' => ['id', 'status'], 'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'status' => ['const' => 'void'],
                        ]]],
                    ],
                    'FeeInvoiceSummaryResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'object', 'required' => ['invoice_count', 'invoiced_paise', 'collected_paise', 'outstanding_paise', 'overdue_paise', 'paid_invoice_count', 'outstanding_invoice_count', 'overdue_invoice_count'], 'properties' => [
                            'invoice_count' => ['type' => 'integer', 'minimum' => 0], 'invoiced_paise' => ['type' => 'integer', 'minimum' => 0],
                            'collected_paise' => ['type' => 'integer', 'minimum' => 0], 'outstanding_paise' => ['type' => 'integer', 'minimum' => 0],
                            'overdue_paise' => ['type' => 'integer', 'minimum' => 0], 'paid_invoice_count' => ['type' => 'integer', 'minimum' => 0],
                            'outstanding_invoice_count' => ['type' => 'integer', 'minimum' => 0], 'overdue_invoice_count' => ['type' => 'integer', 'minimum' => 0],
                        ]]],
                    ],
                    'FeeCollectionPayment' => [
                        'type' => 'object', 'required' => ['id', 'invoice_id', 'invoice_number', 'invoice_title', 'student_name', 'student_code', 'amount_paise', 'method', 'transaction_reference', 'receipt_number', 'received_at'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'invoice_id' => ['type' => 'string', 'format' => 'uuid'],
                            'invoice_number' => ['type' => ['string', 'null']], 'invoice_title' => ['type' => ['string', 'null']],
                            'student_name' => ['type' => ['string', 'null']], 'student_code' => ['type' => ['string', 'null']],
                            'amount_paise' => ['type' => 'integer', 'minimum' => 1],
                            'method' => ['type' => 'string', 'enum' => ['cash', 'bank_transfer', 'upi', 'cheque', 'other']],
                            'transaction_reference' => ['type' => 'string'], 'receipt_number' => ['type' => 'string'],
                            'received_at' => ['type' => 'string', 'format' => 'date-time'],
                        ],
                    ],
                    'FeeCollectionsResponse' => [
                        'type' => 'object', 'required' => ['data', 'summary', 'meta'],
                        'properties' => [
                            'data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/FeeCollectionPayment']],
                            'summary' => ['type' => 'object', 'required' => ['payment_count', 'collected_paise'], 'properties' => [
                                'payment_count' => ['type' => 'integer', 'minimum' => 0], 'collected_paise' => ['type' => 'integer', 'minimum' => 0],
                            ]],
                            'meta' => ['$ref' => '#/components/schemas/FeeCollectionsMeta'],
                        ],
                    ],
                    'FeeCollectionsMeta' => [
                        'type' => 'object', 'required' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
                        'properties' => [
                            'current_page' => ['type' => 'integer', 'minimum' => 1], 'last_page' => ['type' => 'integer', 'minimum' => 1],
                            'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100], 'total' => ['type' => 'integer', 'minimum' => 0],
                            'from' => ['type' => 'string', 'format' => 'date'], 'to' => ['type' => 'string', 'format' => 'date'],
                        ],
                    ],
                    'AuthSessionData' => [
                        'type' => 'object', 'required' => ['user', 'token', 'organizations'],
                        'properties' => [
                            'user' => ['$ref' => '#/components/schemas/AuthUser'],
                            'token' => ['type' => 'string'],
                            'organizations' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/OrganizationMembership']],
                        ],
                    ],
                    'AuthSessionResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/AuthSessionData']],
                    ],
                    'CurrentUserResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'object', 'required' => ['user', 'organizations'], 'properties' => [
                            'user' => ['$ref' => '#/components/schemas/AuthUser'],
                            'organizations' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/OrganizationMembership']],
                        ]]],
                    ],
                    'EmailVerificationRequiredResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => [
                            'type' => 'object', 'required' => ['verification_required', 'email'],
                            'properties' => ['verification_required' => ['const' => true], 'email' => ['type' => 'string', 'format' => 'email']],
                        ]],
                    ],
                    'ApiMessageResponse' => [
                        'type' => 'object', 'required' => ['message'],
                        'properties' => ['message' => ['type' => 'string']],
                    ],
                    'PublicCourseInstructor' => [
                        'type' => 'object', 'required' => ['name', 'title', 'bio'],
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'title' => ['type' => ['string', 'null']],
                            'bio' => ['type' => ['string', 'null']],
                        ],
                    ],
                    'PublicCourse' => [
                        'type' => 'object', 'required' => ['id', 'code', 'title', 'description', 'organization_name', 'amount_paise', 'organization_slug', 'organization_type', 'subject', 'lesson_count', 'instructors'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'code' => ['type' => 'string'], 'title' => ['type' => 'string'],
                            'description' => ['type' => ['string', 'null']], 'organization_name' => ['type' => ['string', 'null']],
                            'amount_paise' => ['type' => 'integer', 'minimum' => 0], 'organization_slug' => ['type' => ['string', 'null']],
                            'organization_type' => ['type' => ['string', 'null'], 'enum' => ['school', 'college', 'coaching', 'academy', 'educator', null]], 'subject' => ['type' => ['string', 'null']],
                            'lesson_count' => ['type' => 'integer', 'minimum' => 0],
                            'instructors' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/PublicCourseInstructor']],
                        ],
                    ],
                    'PublicCourseCatalogResponse' => [
                        'type' => 'object', 'required' => ['data', 'meta'],
                        'properties' => [
                            'data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/PublicCourse']],
                            'meta' => ['type' => 'object', 'required' => ['current_page', 'last_page', 'total'], 'properties' => [
                                'current_page' => ['type' => 'integer'], 'last_page' => ['type' => 'integer'], 'total' => ['type' => 'integer'],
                            ]],
                        ],
                    ],
                    'PublicEducator' => [
                        'type' => 'object', 'required' => ['id', 'name', 'title', 'bio', 'organization_name', 'organization_slug', 'organization_type'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'name' => ['type' => 'string'],
                            'title' => ['type' => ['string', 'null']], 'bio' => ['type' => ['string', 'null']],
                            'organization_name' => ['type' => ['string', 'null']], 'organization_slug' => ['type' => ['string', 'null']],
                            'organization_type' => ['type' => ['string', 'null'], 'enum' => ['school', 'college', 'coaching', 'academy', 'educator', null]],
                        ],
                    ],
                    'PublicEducatorCatalogResponse' => [
                        'type' => 'object', 'required' => ['data', 'meta'],
                        'properties' => [
                            'data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/PublicEducator']],
                            'meta' => ['type' => 'object', 'required' => ['current_page', 'last_page', 'total'], 'properties' => [
                                'current_page' => ['type' => 'integer'], 'last_page' => ['type' => 'integer'], 'total' => ['type' => 'integer'],
                            ]],
                        ],
                    ],
                    'SubscriptionPlan' => [
                        'type' => 'object', 'required' => ['id', 'code', 'name', 'audience', 'description', 'amount_paise', 'billing_interval', 'entitlements'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'code' => ['type' => 'string'], 'name' => ['type' => 'string'],
                            'audience' => ['type' => 'string', 'enum' => ['all', 'institution', 'educator']], 'description' => ['type' => ['string', 'null']],
                            'amount_paise' => ['type' => ['integer', 'null'], 'minimum' => 0], 'billing_interval' => ['type' => 'string', 'enum' => ['month', 'year', 'custom']],
                            'entitlements' => ['oneOf' => [
                                ['type' => 'object', 'additionalProperties' => true],
                                ['type' => 'array', 'items' => ['type' => ['object', 'array', 'string', 'number', 'boolean', 'null']]],
                            ]],
                        ],
                    ],
                    'PublicPlanCatalogResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/SubscriptionPlan']]],
                    ],
                    'WorkspaceSearchResult' => [
                        'type' => 'object', 'required' => ['id', 'type', 'title', 'subtitle'],
                        'properties' => [
                            'id' => ['oneOf' => [
                                ['type' => 'string'],
                                ['type' => 'integer'],
                            ]],
                            'type' => ['type' => 'string', 'enum' => ['class', 'student', 'course', 'task', 'invoice', 'announcement', 'attendance']],
                            'title' => ['type' => 'string'], 'subtitle' => ['type' => 'string'],
                        ],
                    ],
                    'WorkspaceSearchResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'maxItems' => 20, 'items' => ['$ref' => '#/components/schemas/WorkspaceSearchResult']]],
                    ],
                    'WorkspaceDashboardClass' => [
                        'type' => 'object', 'required' => ['id', 'name', 'grade', 'section', 'student_count'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'name' => ['type' => 'string'],
                            'grade' => ['type' => ['string', 'null']], 'section' => ['type' => ['string', 'null']],
                            'student_count' => ['type' => 'integer', 'minimum' => 0],
                        ],
                    ],
                    'WorkspaceDashboardAttendancePoint' => [
                        'type' => 'object', 'required' => ['date', 'rate'],
                        'properties' => [
                            'date' => ['type' => 'string', 'format' => 'date'],
                            'rate' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
                        ],
                    ],
                    'WorkspaceDashboardScheduleEntry' => [
                        'type' => 'object', 'required' => ['id', 'subject', 'teacher_name', 'class_name', 'section_name', 'starts_at', 'ends_at', 'room', 'is_exception', 'title'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'subject' => ['type' => 'string'],
                            'teacher_name' => ['type' => ['string', 'null']], 'class_name' => ['type' => ['string', 'null']],
                            'section_name' => ['type' => ['string', 'null']], 'starts_at' => ['type' => 'string', 'pattern' => '^\\d{2}:\\d{2}$'],
                            'ends_at' => ['type' => 'string', 'pattern' => '^\\d{2}:\\d{2}$'], 'room' => ['type' => ['string', 'null']],
                            'is_exception' => ['type' => 'boolean'], 'title' => ['type' => ['string', 'null']],
                        ],
                    ],
                    'WorkspaceDashboardResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'object', 'required' => [
                            'active_students_count', 'active_classes_count', 'active_enrollments_count', 'unassigned_students_count', 'pending_tasks_count',
                            'latest_attendance_rate', 'latest_attendance_class', 'latest_attendance_date', 'attendance_series', 'classes',
                            'today_schedule', 'schedule_notice', 'schedule_weekday', 'active_academic_year_id',
                        ], 'properties' => [
                            'active_students_count' => ['type' => ['integer', 'null'], 'minimum' => 0],
                            'active_classes_count' => ['type' => ['integer', 'null'], 'minimum' => 0],
                            'active_enrollments_count' => ['type' => ['integer', 'null'], 'minimum' => 0],
                            'unassigned_students_count' => ['type' => ['integer', 'null'], 'minimum' => 0],
                            'pending_tasks_count' => ['type' => ['integer', 'null'], 'minimum' => 0],
                            'latest_attendance_rate' => ['type' => ['number', 'null'], 'minimum' => 0, 'maximum' => 100],
                            'latest_attendance_class' => ['type' => ['string', 'null']],
                            'latest_attendance_date' => ['type' => ['string', 'null'], 'format' => 'date'],
                            'attendance_series' => ['type' => 'array', 'maxItems' => 5, 'items' => ['$ref' => '#/components/schemas/WorkspaceDashboardAttendancePoint']],
                            'classes' => ['type' => 'array', 'maxItems' => 6, 'items' => ['$ref' => '#/components/schemas/WorkspaceDashboardClass']],
                            'today_schedule' => ['type' => 'array', 'maxItems' => 6, 'items' => ['$ref' => '#/components/schemas/WorkspaceDashboardScheduleEntry']],
                            'schedule_notice' => ['type' => ['string', 'null']],
                            'schedule_weekday' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 7],
                            'active_academic_year_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                        ]]],
                    ],
                    'StudentCourseCertificate' => [
                        'type' => 'object', 'required' => ['id', 'code', 'issued_at', 'completed_lessons', 'total_lessons'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'code' => ['type' => 'string', 'format' => 'uuid'],
                            'issued_at' => ['type' => 'string', 'format' => 'date-time'],
                            'completed_lessons' => ['type' => 'integer', 'minimum' => 0],
                            'total_lessons' => ['type' => 'integer', 'minimum' => 0],
                        ],
                    ],
                    'StudentDashboardCourse' => [
                        'type' => 'object', 'required' => ['id', 'code', 'title', 'total_lessons', 'completed_lessons', 'certificate'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'code' => ['type' => 'string'], 'title' => ['type' => 'string'],
                            'total_lessons' => ['type' => 'integer', 'minimum' => 0], 'completed_lessons' => ['type' => 'integer', 'minimum' => 0],
                            'certificate' => ['oneOf' => [['$ref' => '#/components/schemas/StudentCourseCertificate'], ['type' => 'null']]],
                        ],
                    ],
                    'StudentDashboardAssignment' => [
                        'type' => 'object', 'required' => ['id', 'title', 'course_title', 'due_at', 'max_points', 'submission_status'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'title' => ['type' => 'string'],
                            'course_title' => ['type' => ['string', 'null']], 'due_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                            'max_points' => ['type' => 'number', 'minimum' => 0], 'submission_status' => ['type' => ['string', 'null'], 'enum' => ['submitted', 'graded', null]],
                        ],
                    ],
                    'StudentStudyRecommendation' => [
                        'type' => 'object', 'required' => ['id', 'kind', 'title', 'course_id', 'course_title', 'reason', 'duration_minutes'],
                        'properties' => [
                            'id' => ['type' => 'string'], 'kind' => ['type' => 'string', 'enum' => ['assignment', 'course', 'review']],
                            'title' => ['type' => 'string'], 'course_id' => ['type' => 'string', 'format' => 'uuid'],
                            'course_title' => ['type' => ['string', 'null']], 'reason' => ['type' => 'string'],
                            'duration_minutes' => ['type' => 'integer', 'minimum' => 1],
                        ],
                    ],
                    'StudentAssessmentResult' => [
                        'type' => 'object', 'required' => ['id', 'title', 'course_title', 'score', 'review_status', 'max_points', 'submitted_at'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'title' => ['type' => ['string', 'null']],
                            'course_title' => ['type' => ['string', 'null']], 'score' => ['type' => ['number', 'null'], 'minimum' => 0],
                            'review_status' => ['type' => 'string', 'enum' => ['not_required', 'pending', 'reviewed']], 'max_points' => ['type' => 'number', 'minimum' => 0],
                            'submitted_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                        ],
                    ],
                    'StudentAttendanceSummary' => [
                        'type' => 'object', 'required' => ['sessions_recorded', 'present_or_late', 'rate_percent', 'recent'],
                        'properties' => [
                            'sessions_recorded' => ['type' => 'integer', 'minimum' => 0],
                            'present_or_late' => ['type' => 'integer', 'minimum' => 0],
                            'rate_percent' => ['type' => ['number', 'null'], 'minimum' => 0, 'maximum' => 100],
                            'recent' => ['type' => 'array', 'maxItems' => 5, 'items' => ['type' => 'object', 'required' => ['date', 'class_name', 'status'], 'properties' => [
                                'date' => ['type' => ['string', 'null'], 'format' => 'date'], 'class_name' => ['type' => ['string', 'null']],
                                'status' => ['type' => 'string', 'enum' => ['present', 'absent', 'late', 'excused']],
                            ]]],
                        ],
                    ],
                    'StudentDashboardResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'object', 'required' => [
                            'student', 'courses', 'assignments', 'study_recommendations', 'assessment_results', 'today_schedule', 'schedule_notice', 'attendance',
                        ], 'properties' => [
                            'student' => ['oneOf' => [
                                ['type' => 'object', 'required' => ['name', 'student_code'], 'properties' => [
                                    'name' => ['type' => 'string'], 'student_code' => ['type' => 'string'],
                                ]],
                                ['type' => 'null'],
                            ]],
                            'courses' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/StudentDashboardCourse']],
                            'assignments' => ['type' => 'array', 'maxItems' => 8, 'items' => ['$ref' => '#/components/schemas/StudentDashboardAssignment']],
                            'study_recommendations' => ['type' => 'array', 'maxItems' => 5, 'items' => ['$ref' => '#/components/schemas/StudentStudyRecommendation']],
                            'assessment_results' => ['type' => 'array', 'maxItems' => 8, 'items' => ['$ref' => '#/components/schemas/StudentAssessmentResult']],
                            'today_schedule' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/WorkspaceDashboardScheduleEntry']],
                            'schedule_notice' => ['type' => ['string', 'null']],
                            'attendance' => ['$ref' => '#/components/schemas/StudentAttendanceSummary'],
                        ]]],
                    ],
                    'AcademicReportAssessmentScoreBands' => [
                        'type' => 'object', 'required' => ['under_60', 'sixty_to_74', 'seventyfive_to_89', 'ninety_plus'],
                        'properties' => [
                            'under_60' => ['type' => 'integer', 'minimum' => 0], 'sixty_to_74' => ['type' => 'integer', 'minimum' => 0],
                            'seventyfive_to_89' => ['type' => 'integer', 'minimum' => 0], 'ninety_plus' => ['type' => 'integer', 'minimum' => 0],
                        ],
                    ],
                    'AcademicReportAssessmentTrendPoint' => [
                        'type' => 'object', 'required' => ['month', 'attempts', 'average_percent'],
                        'properties' => [
                            'month' => ['type' => 'string', 'pattern' => '^\\d{4}-\\d{2}$'],
                            'attempts' => ['type' => 'integer', 'minimum' => 0],
                            'average_percent' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
                        ],
                    ],
                    'AcademicReportAssessmentBreakdown' => [
                        'type' => 'object', 'required' => ['assessment_id', 'assessment_title', 'course_id', 'course_title', 'attempts', 'average_percent'],
                        'properties' => [
                            'assessment_id' => ['type' => 'string', 'format' => 'uuid'], 'assessment_title' => ['type' => 'string'],
                            'course_id' => ['type' => 'string', 'format' => 'uuid'], 'course_title' => ['type' => 'string'],
                            'attempts' => ['type' => 'integer', 'minimum' => 0], 'average_percent' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
                        ],
                    ],
                    'AcademicReportCourseSummary' => [
                        'type' => 'object', 'required' => ['course_id', 'course_title', 'attempts', 'average_percent'],
                        'properties' => [
                            'course_id' => ['type' => 'string', 'format' => 'uuid'], 'course_title' => ['type' => 'string'],
                            'attempts' => ['type' => 'integer', 'minimum' => 0], 'average_percent' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
                        ],
                    ],
                    'AcademicReportSummary' => [
                        'type' => 'object', 'required' => [
                            'active_students', 'attendance_sessions', 'attendance_percent', 'courses_enrolled', 'completed_lessons', 'total_lessons',
                            'course_completion_percent', 'assignments_assigned', 'assignments_submitted', 'assignments_graded', 'assessments_completed',
                            'assessment_average_percent', 'assessment_score_bands', 'assessment_trend', 'assessment_breakdown', 'assessment_course_summary',
                        ],
                        'properties' => [
                            'active_students' => ['type' => 'integer', 'minimum' => 0], 'attendance_sessions' => ['type' => 'integer', 'minimum' => 0],
                            'attendance_percent' => ['type' => ['number', 'null'], 'minimum' => 0, 'maximum' => 100],
                            'courses_enrolled' => ['type' => 'integer', 'minimum' => 0], 'completed_lessons' => ['type' => 'integer', 'minimum' => 0],
                            'total_lessons' => ['type' => 'integer', 'minimum' => 0],
                            'course_completion_percent' => ['type' => ['number', 'null'], 'minimum' => 0, 'maximum' => 100],
                            'assignments_assigned' => ['type' => 'integer', 'minimum' => 0], 'assignments_submitted' => ['type' => 'integer', 'minimum' => 0],
                            'assignments_graded' => ['type' => 'integer', 'minimum' => 0], 'assessments_completed' => ['type' => 'integer', 'minimum' => 0],
                            'assessment_average_percent' => ['type' => ['number', 'null'], 'minimum' => 0, 'maximum' => 100],
                            'assessment_score_bands' => ['$ref' => '#/components/schemas/AcademicReportAssessmentScoreBands'],
                            'assessment_trend' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AcademicReportAssessmentTrendPoint']],
                            'assessment_breakdown' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AcademicReportAssessmentBreakdown']],
                            'assessment_course_summary' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AcademicReportCourseSummary']],
                        ],
                    ],
                    'AcademicReportStudent' => [
                        'type' => 'object', 'required' => [
                            'student_id', 'student_code', 'student_name', 'classes', 'attendance_sessions', 'attendance_percent', 'courses_enrolled',
                            'completed_lessons', 'total_lessons', 'course_completion_percent', 'assignments_assigned', 'assignments_submitted',
                            'assignments_graded', 'assessments_completed', 'assessment_average_percent',
                        ],
                        'properties' => [
                            'student_id' => ['type' => 'string', 'format' => 'uuid'], 'student_code' => ['type' => 'string'],
                            'student_name' => ['type' => 'string'], 'classes' => ['type' => 'string'],
                            'attendance_sessions' => ['type' => 'integer', 'minimum' => 0],
                            'attendance_percent' => ['type' => ['number', 'null'], 'minimum' => 0, 'maximum' => 100],
                            'courses_enrolled' => ['type' => 'integer', 'minimum' => 0], 'completed_lessons' => ['type' => 'integer', 'minimum' => 0],
                            'total_lessons' => ['type' => 'integer', 'minimum' => 0],
                            'course_completion_percent' => ['type' => ['number', 'null'], 'minimum' => 0, 'maximum' => 100],
                            'assignments_assigned' => ['type' => 'integer', 'minimum' => 0], 'assignments_submitted' => ['type' => 'integer', 'minimum' => 0],
                            'assignments_graded' => ['type' => 'integer', 'minimum' => 0], 'assessments_completed' => ['type' => 'integer', 'minimum' => 0],
                            'assessment_average_percent' => ['type' => ['number', 'null'], 'minimum' => 0, 'maximum' => 100],
                        ],
                    ],
                    'AcademicReportResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'object', 'required' => ['summary', 'scope', 'students', 'available_classes', 'filters'], 'properties' => [
                            'summary' => ['$ref' => '#/components/schemas/AcademicReportSummary'],
                            'scope' => ['type' => 'string', 'enum' => ['workspace', 'assigned_classes']],
                            'students' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AcademicReportStudent']],
                            'available_classes' => ['type' => 'array', 'items' => ['type' => 'object', 'required' => ['id', 'name'], 'properties' => [
                                'id' => ['type' => 'string', 'format' => 'uuid'], 'name' => ['type' => 'string'],
                            ]]],
                            'filters' => ['type' => 'object', 'required' => ['class_id', 'student_id', 'from', 'to'], 'properties' => [
                                'class_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                                'student_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                                'from' => ['type' => ['string', 'null'], 'format' => 'date'],
                                'to' => ['type' => ['string', 'null'], 'format' => 'date'],
                            ]],
                        ]]],
                    ],
                    'AssessmentAttemptSummary' => [
                        'type' => 'object', 'required' => ['id', 'attempt_number', 'status', 'review_status', 'score', 'max_points', 'started_at', 'expires_at', 'submitted_at'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'attempt_number' => ['type' => 'integer', 'minimum' => 1],
                            'status' => ['type' => 'string', 'enum' => ['in_progress', 'submitted', 'expired']],
                            'review_status' => ['type' => 'string', 'enum' => ['not_required', 'pending', 'reviewed']],
                            'score' => ['type' => ['number', 'null'], 'minimum' => 0], 'max_points' => ['type' => 'number', 'minimum' => 0],
                            'started_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                            'expires_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                            'submitted_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                        ],
                    ],
                    'AskAiTutorRequest' => [
                        'type' => 'object', 'required' => ['question'],
                        'properties' => [
                            'question' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 2000, 'description' => 'The maximum is configurable with AI_MAX_QUESTION_CHARACTERS. Do not include personal contact information or identity numbers.'],
                            'course_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                            'lesson_id' => ['type' => ['string', 'null'], 'format' => 'uuid', 'description' => 'Requires course_id and an active lesson in that course.'],
                        ],
                    ],
                    'CreateTeacherAssistantDraftRequest' => [
                        'type' => 'object', 'required' => ['draft_type', 'topic', 'language'],
                        'properties' => [
                            'draft_type' => ['type' => 'string', 'enum' => ['lesson_plan', 'course_outline', 'learning_objectives', 'mcqs', 'practice_exercises', 'homework_questions', 'revision_notes', 'rubric']],
                            'topic' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 160],
                            'grade_level' => ['type' => ['string', 'null'], 'maxLength' => 80],
                            'instructions' => ['type' => ['string', 'null'], 'maxLength' => 1000, 'description' => 'Do not include personal contact information or identity numbers.'],
                            'language' => ['type' => 'string', 'enum' => ['en', 'hi']],
                        ],
                    ],
                    'AiUsageTokens' => [
                        'type' => 'object', 'required' => ['input_tokens', 'output_tokens'],
                        'properties' => ['input_tokens' => ['type' => 'integer', 'minimum' => 0], 'output_tokens' => ['type' => 'integer', 'minimum' => 0]],
                    ],
                    'AiTutorResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'object', 'required' => ['answer', 'usage'], 'properties' => ['answer' => ['type' => 'string'], 'usage' => ['$ref' => '#/components/schemas/AiUsageTokens']]]],
                    ],
                    'TeacherAssistantDraftResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'object', 'required' => ['draft', 'human_review_required', 'usage'], 'properties' => ['draft' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 20000], 'human_review_required' => ['const' => true], 'usage' => ['$ref' => '#/components/schemas/AiUsageTokens']]]],
                    ],
                    'StoreAnnouncementRequest' => [
                        'type' => 'object', 'required' => ['title', 'body', 'audience'],
                        'properties' => [
                            'title' => ['type' => 'string', 'maxLength' => 180], 'body' => ['type' => 'string', 'maxLength' => 10000],
                            'audience' => ['type' => 'string', 'enum' => ['all_members', 'students', 'guardians', 'teachers']],
                            'scheduled_at' => ['type' => ['string', 'null'], 'description' => 'A parseable date/time that must be in the future; omitted or null publishes immediately.'],
                        ],
                    ],
                    'UpdateAnnouncementRequest' => [
                        'type' => 'object', 'required' => ['title', 'body'],
                        'properties' => ['title' => ['type' => 'string', 'maxLength' => 180], 'body' => ['type' => 'string', 'maxLength' => 10000]],
                        'not' => ['required' => ['audience']],
                        'description' => 'The published or scheduled notice keeps its original audience; audience is not accepted during edits.',
                    ],
                    'StoreOrganizationTaskRequest' => [
                        'type' => 'object', 'required' => ['title'],
                        'properties' => [
                            'title' => ['type' => 'string', 'maxLength' => 160], 'description' => ['type' => ['string', 'null'], 'maxLength' => 2000],
                            'due_at' => ['type' => ['string', 'null'], 'description' => 'Optional parseable date/time; date-only values are supported.'],
                            'assigned_to' => ['type' => ['integer', 'null'], 'minimum' => 1, 'description' => 'Must be an active owner, admin, educator, or teacher in this workspace.'],
                        ],
                    ],
                    'UpdateOrganizationTaskRequest' => [
                        'type' => 'object', 'minProperties' => 1,
                        'properties' => [
                            'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 160], 'description' => ['type' => ['string', 'null'], 'maxLength' => 2000],
                            'due_at' => ['type' => ['string', 'null'], 'description' => 'Optional parseable date/time; date-only values are supported.'],
                            'assigned_to' => ['type' => ['integer', 'null'], 'minimum' => 1, 'description' => 'Must be an active owner, admin, educator, or teacher in this workspace.'],
                        ],
                        'description' => 'Provide at least one field; all supplied fields are updated without changing omitted values.',
                    ],
                    'PlanEntitlementsInput' => [
                        'type' => 'object', 'additionalProperties' => false,
                        'properties' => [
                            'max_students' => ['type' => ['integer', 'null'], 'minimum' => 1], 'max_courses' => ['type' => ['integer', 'null'], 'minimum' => 1],
                            'max_teachers' => ['type' => ['integer', 'null'], 'minimum' => 1], 'ai_requests_per_day' => ['type' => ['integer', 'null'], 'minimum' => 0],
                            'storage_mb' => ['type' => ['integer', 'null'], 'minimum' => 1],
                        ],
                    ],
                    'CreateSubscriptionPlanRequest' => [
                        'type' => 'object', 'required' => ['code', 'name', 'audience', 'billing_interval', 'entitlements'],
                        'properties' => [
                            'code' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9_-]+$', 'maxLength' => 50], 'name' => ['type' => 'string', 'maxLength' => 120],
                            'audience' => ['type' => 'string', 'enum' => ['all', 'institution', 'educator']], 'description' => ['type' => ['string', 'null'], 'maxLength' => 2000],
                            'amount_paise' => ['type' => ['integer', 'null'], 'minimum' => 0, 'maximum' => 1000000000],
                            'billing_interval' => ['type' => 'string', 'enum' => ['month', 'year', 'custom']], 'entitlements' => ['$ref' => '#/components/schemas/PlanEntitlementsInput'],
                        ],
                    ],
                    'UpdateSubscriptionPlanRequest' => [
                        'type' => 'object', 'minProperties' => 1,
                        'properties' => [
                            'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 120], 'audience' => ['type' => 'string', 'enum' => ['all', 'institution', 'educator']],
                            'description' => ['type' => ['string', 'null'], 'maxLength' => 2000], 'amount_paise' => ['type' => ['integer', 'null'], 'minimum' => 0, 'maximum' => 1000000000],
                            'billing_interval' => ['type' => 'string', 'enum' => ['month', 'year', 'custom']], 'entitlements' => ['$ref' => '#/components/schemas/PlanEntitlementsInput'],
                            'is_active' => ['type' => 'boolean'],
                        ],
                        'description' => 'Supply at least one editable field. The Free plan cannot be deactivated.',
                    ],
                    'RequestSubscriptionChangeRequest' => [
                        'type' => 'object', 'required' => ['plan_id'],
                        'properties' => ['plan_id' => ['type' => 'string', 'format' => 'uuid']],
                    ],
                    'UpdateOrganizationStatusRequest' => [
                        'type' => 'object', 'required' => ['status'],
                        'properties' => ['status' => ['type' => 'string', 'enum' => ['active', 'suspended']], 'note' => ['type' => ['string', 'null'], 'maxLength' => 500]],
                    ],
                    'ResolveSubscriptionRequest' => [
                        'type' => 'object', 'required' => ['decision'],
                        'properties' => [
                            'decision' => ['type' => 'string', 'enum' => ['approve', 'reject']],
                            'verification_reference' => ['type' => ['string', 'null'], 'maxLength' => 180, 'description' => 'Required when approving a paid or custom-priced plan; not required when rejecting.'],
                            'note' => ['type' => ['string', 'null'], 'maxLength' => 1000],
                        ],
                    ],
                    'AnnouncementRecord' => [
                        'type' => 'object', 'required' => ['id', 'title', 'body', 'audience', 'status', 'published_at', 'scheduled_at', 'author_name'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'title' => ['type' => 'string'], 'body' => ['type' => 'string'],
                            'audience' => ['type' => 'string', 'enum' => ['all_members', 'students', 'guardians', 'teachers']],
                            'status' => ['type' => 'string', 'enum' => ['published', 'scheduled', 'withdrawn']],
                            'published_at' => ['type' => ['string', 'null'], 'format' => 'date-time'], 'scheduled_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                            'author_name' => ['type' => ['string', 'null']],
                        ],
                    ],
                    'AnnouncementListResponse' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AnnouncementRecord']]]],
                    'AnnouncementResponse' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['$ref' => '#/components/schemas/AnnouncementRecord']]],
                    'AnnouncementCreatedResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['allOf' => [
                            ['$ref' => '#/components/schemas/AnnouncementRecord'],
                            ['type' => 'object', 'required' => ['recipient_count'], 'properties' => ['recipient_count' => ['type' => ['integer', 'null'], 'minimum' => 0]]],
                        ]]],
                    ],
                    'OrganizationTaskRecord' => [
                        'type' => 'object', 'required' => ['id', 'title', 'description', 'due_at', 'assigned_to', 'assigned_to_name', 'status', 'completed_at', 'archived_at', 'created_at'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'title' => ['type' => 'string'], 'description' => ['type' => ['string', 'null']],
                            'due_at' => ['type' => ['string', 'null'], 'format' => 'date-time'], 'assigned_to' => ['type' => ['string', 'null']],
                            'assigned_to_name' => ['type' => ['string', 'null']], 'status' => ['type' => 'string', 'enum' => ['pending', 'completed']],
                            'completed_at' => ['type' => ['string', 'null'], 'format' => 'date-time'], 'archived_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                            'created_at' => ['type' => 'string', 'format' => 'date-time'],
                        ],
                    ],
                    'OrganizationTaskResponse' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['$ref' => '#/components/schemas/OrganizationTaskRecord']]],
                    'OrganizationTaskListResponse' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/OrganizationTaskRecord']]]],
                    'TaskAssigneesResponse' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['type' => 'array', 'items' => ['type' => 'object', 'required' => ['id', 'name', 'role'], 'properties' => ['id' => ['type' => 'string'], 'name' => ['type' => 'string'], 'role' => ['type' => 'string', 'enum' => ['owner', 'admin', 'educator', 'teacher']]]]]]],
                    'SubscriptionPlanRecord' => [
                        'type' => 'object', 'required' => ['id', 'code', 'name', 'audience', 'description', 'amount_paise', 'billing_interval', 'entitlements', 'is_active'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'code' => ['type' => 'string'], 'name' => ['type' => 'string'],
                            'audience' => ['type' => 'string', 'enum' => ['all', 'institution', 'educator']], 'description' => ['type' => ['string', 'null']],
                            'amount_paise' => ['type' => ['integer', 'null'], 'minimum' => 0], 'billing_interval' => ['type' => 'string', 'enum' => ['month', 'year', 'custom']],
                            'entitlements' => ['type' => ['object', 'array'], 'description' => 'Named plan limits. An empty entitlement set may serialize as an empty array.', 'properties' => [
                                'max_students' => ['type' => ['integer', 'null'], 'minimum' => 1], 'max_courses' => ['type' => ['integer', 'null'], 'minimum' => 1],
                                'max_teachers' => ['type' => ['integer', 'null'], 'minimum' => 1], 'ai_requests_per_day' => ['type' => ['integer', 'null'], 'minimum' => 0], 'storage_mb' => ['type' => ['integer', 'null'], 'minimum' => 1],
                            ]],
                            'is_active' => ['type' => 'boolean'],
                        ],
                    ],
                    'SubscriptionPlanListResponse' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/SubscriptionPlanRecord']]]],
                    'SubscriptionPlanResponse' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['$ref' => '#/components/schemas/SubscriptionPlanRecord']]],
                    'OrganizationSubscriptionResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'object', 'required' => ['status', 'plan', 'requested_plan', 'requested_at', 'available_plans'], 'properties' => [
                            'status' => ['type' => 'string', 'enum' => ['active', 'change_pending']], 'plan' => ['$ref' => '#/components/schemas/SubscriptionPlanRecord'],
                            'requested_plan' => ['oneOf' => [['$ref' => '#/components/schemas/SubscriptionPlanRecord'], ['type' => 'null']]],
                            'requested_at' => ['type' => ['string', 'null'], 'format' => 'date-time'], 'available_plans' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/SubscriptionPlanRecord']],
                        ]]],
                    ],
                    'SubscriptionChangeResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'object', 'required' => ['status', 'plan'], 'properties' => [
                            'status' => ['type' => 'string', 'enum' => ['active', 'change_pending']], 'plan' => ['$ref' => '#/components/schemas/SubscriptionPlanRecord'],
                            'requested_plan' => ['oneOf' => [['$ref' => '#/components/schemas/SubscriptionPlanRecord'], ['type' => 'null']]],
                            'requested_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                        ]]],
                    ],
                    'PlatformOrganizationStatusResponse' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['type' => 'object', 'required' => ['id', 'name', 'status'], 'properties' => ['id' => ['type' => 'string', 'format' => 'uuid'], 'name' => ['type' => 'string'], 'status' => ['type' => 'string', 'enum' => ['active', 'suspended']]]]]],
                    'SubscriptionRequestResolutionResponse' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['type' => 'object', 'required' => ['organization_id', 'status', 'decision'], 'properties' => ['organization_id' => ['type' => 'string', 'format' => 'uuid'], 'status' => ['const' => 'active'], 'decision' => ['type' => 'string', 'enum' => ['approve', 'reject']]]]]],
                    'PlatformFeatureFlagRecord' => [
                        'type' => 'object', 'required' => ['key', 'label', 'description', 'enabled', 'default_enabled', 'updated_at', 'updated_by_id'],
                        'properties' => [
                            'key' => ['type' => 'string', 'enum' => ['ai_tutor', 'ai_teacher_assistant']],
                            'label' => ['type' => 'string'], 'description' => ['type' => 'string'],
                            'enabled' => ['type' => 'boolean'], 'default_enabled' => ['type' => 'boolean'],
                            'updated_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                            'updated_by_id' => ['type' => ['integer', 'null'], 'minimum' => 1],
                        ],
                    ],
                    'PlatformFeatureFlagsResponse' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/PlatformFeatureFlagRecord']]]],
                    'PlatformFeatureFlagResponse' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['$ref' => '#/components/schemas/PlatformFeatureFlagRecord']]],
                    'SetPlatformFeatureFlagRequest' => ['type' => 'object', 'required' => ['enabled'], 'properties' => ['enabled' => ['type' => 'boolean']]],
                    'PlatformOrganizationRecord' => [
                        'type' => 'object', 'required' => ['id', 'name', 'slug', 'type', 'status', 'current_plan_name', 'subscription_status', 'active_members_count', 'active_students_count', 'active_courses_count', 'created_at'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'name' => ['type' => 'string'], 'slug' => ['type' => 'string'],
                            'type' => ['type' => 'string', 'enum' => ['school', 'college', 'coaching', 'academy', 'educator']],
                            'status' => ['type' => 'string', 'enum' => ['active', 'suspended']],
                            'current_plan_name' => ['type' => ['string', 'null']], 'subscription_status' => ['type' => ['string', 'null'], 'enum' => ['active', 'change_pending', null]],
                            'active_members_count' => ['type' => 'integer', 'minimum' => 0], 'active_students_count' => ['type' => 'integer', 'minimum' => 0],
                            'active_courses_count' => ['type' => 'integer', 'minimum' => 0], 'created_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                        ],
                    ],
                    'PlatformPaginationMeta' => ['type' => 'object', 'required' => ['current_page', 'last_page', 'total'], 'properties' => ['current_page' => ['type' => 'integer', 'minimum' => 1], 'last_page' => ['type' => 'integer', 'minimum' => 1], 'total' => ['type' => 'integer', 'minimum' => 0]]],
                    'PlatformOrganizationsResponse' => ['type' => 'object', 'required' => ['data', 'meta'], 'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/PlatformOrganizationRecord']], 'meta' => ['$ref' => '#/components/schemas/PlatformPaginationMeta']]],
                    'PlatformAuditLogRecord' => [
                        'type' => 'object', 'required' => ['id', 'actor_id', 'event', 'subject_type', 'subject_id', 'created_at'],
                        'properties' => ['id' => ['type' => 'integer', 'minimum' => 1], 'actor_id' => ['type' => ['integer', 'null']], 'event' => ['type' => 'string'], 'subject_type' => ['type' => ['string', 'null']], 'subject_id' => ['type' => ['string', 'null']], 'created_at' => ['type' => 'string']],
                    ],
                    'PlatformAuditLogsResponse' => ['type' => 'object', 'required' => ['data', 'meta'], 'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/PlatformAuditLogRecord']], 'meta' => ['$ref' => '#/components/schemas/PlatformPaginationMeta']]],
                    'PlatformUserWorkspaceRecord' => ['type' => 'object', 'required' => ['id', 'name', 'slug', 'organization_status', 'role', 'membership_status'], 'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'], 'name' => ['type' => 'string'], 'slug' => ['type' => 'string'],
                        'organization_status' => ['type' => 'string', 'enum' => ['active', 'suspended']], 'role' => ['type' => 'string'],
                        'membership_status' => ['type' => 'string'],
                    ]],
                    'PlatformUserRecord' => ['type' => 'object', 'required' => ['id', 'name', 'email', 'account_status', 'email_verified_at', 'created_at', 'workspaces'], 'properties' => [
                        'id' => ['type' => 'integer', 'minimum' => 1], 'name' => ['type' => 'string'], 'email' => ['type' => 'string', 'format' => 'email'],
                        'account_status' => ['type' => 'string'], 'email_verified_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                        'created_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                        'workspaces' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/PlatformUserWorkspaceRecord']],
                    ]],
                    'PlatformUserLookupResponse' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['oneOf' => [
                        ['$ref' => '#/components/schemas/PlatformUserRecord'], ['type' => 'null'],
                    ]]]],
                    'PlatformUserLookupRequest' => ['type' => 'object', 'required' => ['email'], 'properties' => [
                        'email' => ['type' => 'string', 'format' => 'email', 'maxLength' => 254],
                    ]],
                    'PlatformModeratableContentRecord' => ['type' => 'object', 'required' => ['type', 'id', 'title', 'organization_id', 'organization_name', 'status', 'reason', 'moderated_at'], 'properties' => [
                        'type' => ['type' => 'string', 'enum' => ['course', 'educator']], 'id' => ['type' => 'string', 'format' => 'uuid'],
                        'title' => ['type' => 'string'], 'organization_id' => ['type' => 'string', 'format' => 'uuid'],
                        'organization_name' => ['type' => ['string', 'null']], 'status' => ['type' => 'string', 'enum' => ['hidden', 'visible']],
                        'reason' => ['type' => ['string', 'null'], 'maxLength' => 500], 'moderated_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                    ]],
                    'PlatformModeratableContentListResponse' => ['type' => 'object', 'required' => ['data', 'meta'], 'properties' => [
                        'data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/PlatformModeratableContentRecord']],
                        'meta' => ['$ref' => '#/components/schemas/PlatformPaginationMeta'],
                    ]],
                    'PlatformModerationResponse' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['$ref' => '#/components/schemas/PlatformModeratableContentRecord']]],
                    'UpdatePlatformModerationRequest' => ['type' => 'object', 'required' => ['status'], 'properties' => [
                        'status' => ['type' => 'string', 'enum' => ['hidden', 'visible']], 'reason' => ['type' => ['string', 'null'], 'maxLength' => 500],
                    ]],
                    'PlatformUsageCounts' => [
                        'type' => 'object', 'required' => ['organization_count', 'active_organization_count', 'suspended_organization_count', 'active_membership_count', 'active_student_count', 'active_course_count', 'active_subscription_count'],
                        'properties' => ['organization_count' => ['type' => 'integer', 'minimum' => 0], 'active_organization_count' => ['type' => 'integer', 'minimum' => 0], 'suspended_organization_count' => ['type' => 'integer', 'minimum' => 0], 'active_membership_count' => ['type' => 'integer', 'minimum' => 0], 'active_student_count' => ['type' => 'integer', 'minimum' => 0], 'active_course_count' => ['type' => 'integer', 'minimum' => 0], 'active_subscription_count' => ['type' => 'integer', 'minimum' => 0]],
                    ],
                    'PlatformUsageResponse' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['$ref' => '#/components/schemas/PlatformUsageCounts']]],
                    'PlatformUsageHistoryRecord' => [
                        'type' => 'object', 'required' => ['snapshot_date', 'organization_count', 'active_organization_count', 'suspended_organization_count', 'active_membership_count', 'active_student_count', 'active_course_count', 'active_subscription_count'],
                        'properties' => ['snapshot_date' => ['type' => 'string', 'format' => 'date'], 'organization_count' => ['type' => 'integer', 'minimum' => 0], 'active_organization_count' => ['type' => 'integer', 'minimum' => 0], 'suspended_organization_count' => ['type' => 'integer', 'minimum' => 0], 'active_membership_count' => ['type' => 'integer', 'minimum' => 0], 'active_student_count' => ['type' => 'integer', 'minimum' => 0], 'active_course_count' => ['type' => 'integer', 'minimum' => 0], 'active_subscription_count' => ['type' => 'integer', 'minimum' => 0]],
                    ],
                    'PlatformUsageHistoryResponse' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['type' => 'object', 'required' => ['days', 'snapshots'], 'properties' => ['days' => ['type' => 'integer', 'minimum' => 7, 'maximum' => 365], 'snapshots' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/PlatformUsageHistoryRecord']]]]]],
                    'PendingSubscriptionRequestRecord' => ['type' => 'object', 'required' => ['organization_id', 'organization_name', 'plan', 'requested_plan', 'requested_at'], 'properties' => ['organization_id' => ['type' => 'string', 'format' => 'uuid'], 'organization_name' => ['type' => ['string', 'null']], 'plan' => ['$ref' => '#/components/schemas/SubscriptionPlanRecord'], 'requested_plan' => ['$ref' => '#/components/schemas/SubscriptionPlanRecord'], 'requested_at' => ['type' => ['string', 'null'], 'format' => 'date-time']]],
                    'PendingSubscriptionRequestsResponse' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/PendingSubscriptionRequestRecord']]]],
                    'SavedObjectiveAssessmentAnswerResponse' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['type' => 'object', 'required' => ['question_id', 'saved', 'selected_option_ids'], 'properties' => ['question_id' => ['type' => 'string', 'format' => 'uuid'], 'saved' => ['const' => true], 'selected_option_ids' => ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'uuid']]]]]],
                    'SavedShortAnswerAssessmentAnswerResponse' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['type' => 'object', 'required' => ['question_id', 'saved', 'response_text'], 'properties' => ['question_id' => ['type' => 'string', 'format' => 'uuid'], 'saved' => ['const' => true], 'response_text' => ['type' => ['string', 'null']]]]]],
                    'SavedAssessmentAnswerResponse' => ['oneOf' => [['$ref' => '#/components/schemas/SavedObjectiveAssessmentAnswerResponse'], ['$ref' => '#/components/schemas/SavedShortAnswerAssessmentAnswerResponse']]],
                    'AssessmentRubricScoreRecord' => ['type' => 'object', 'required' => ['criterion_id', 'score'], 'properties' => ['criterion_id' => ['type' => 'string', 'format' => 'uuid'], 'score' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100]]],
                    'AssessmentAnswerGradeResponse' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['type' => 'object', 'required' => ['question_id', 'graded', 'points_awarded', 'score', 'max_points', 'review_status'], 'properties' => ['question_id' => ['type' => 'string', 'format' => 'uuid'], 'graded' => ['const' => true], 'points_awarded' => ['type' => 'number', 'minimum' => 0], 'score' => ['type' => 'number', 'minimum' => 0], 'max_points' => ['type' => 'number', 'exclusiveMinimum' => 0], 'review_status' => ['type' => 'string', 'enum' => ['pending', 'reviewed']]]]]],
                    'AssessmentReviewQuestion' => [
                        'type' => 'object', 'required' => ['question_id', 'prompt', 'type', 'points', 'points_awarded', 'is_correct', 'response_text', 'grader_feedback', 'graded_at', 'grading_rubric', 'rubric_scores', 'selected_options', 'correct_options'],
                        'properties' => [
                            'question_id' => ['type' => 'string', 'format' => 'uuid'], 'prompt' => ['type' => 'string'], 'type' => ['type' => 'string', 'enum' => ['single_choice', 'multiple_select', 'short_answer']],
                            'points' => ['type' => 'number', 'exclusiveMinimum' => 0], 'points_awarded' => ['type' => 'number', 'minimum' => 0], 'is_correct' => ['type' => ['boolean', 'null']],
                            'response_text' => ['type' => ['string', 'null']], 'grader_feedback' => ['type' => ['string', 'null']], 'graded_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                            'grading_rubric' => ['type' => ['array', 'null'], 'items' => ['$ref' => '#/components/schemas/AssessmentRubricCriterionInput']],
                            'rubric_scores' => ['type' => ['array', 'null'], 'items' => ['$ref' => '#/components/schemas/AssessmentRubricScoreRecord']],
                            'selected_options' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AssessmentAnswerOption']],
                            'correct_options' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AssessmentAnswerOption']],
                        ],
                    ],
                    'AssessmentAttemptReviewResponse' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['type' => 'object', 'required' => ['attempt', 'questions'], 'properties' => ['attempt' => ['allOf' => [['$ref' => '#/components/schemas/AssessmentAttemptSummary'], ['type' => 'object', 'required' => ['student_name', 'student_code'], 'properties' => ['student_name' => ['type' => ['string', 'null']], 'student_code' => ['type' => ['string', 'null']]]]]], 'questions' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AssessmentReviewQuestion']]]]]],
                    'AssessmentGradesCsvResponse' => ['type' => 'string', 'format' => 'binary', 'description' => 'UTF-8 CSV download with formula-safe text cells.'],
                    'HealthResponse' => ['type' => 'object', 'required' => ['status', 'service'], 'properties' => ['status' => ['const' => 'ok'], 'service' => ['const' => 'paathnexus-api']]],
                    'ReadinessResponse' => ['type' => 'object', 'required' => ['status', 'service', 'checks'], 'properties' => ['status' => ['type' => 'string', 'enum' => ['ready', 'not_ready']], 'service' => ['const' => 'paathnexus-api'], 'checks' => ['type' => 'object', 'required' => ['database'], 'properties' => ['database' => ['type' => 'string', 'enum' => ['ok', 'unavailable']]]]]],
                    'AssignmentAttachmentFile' => ['type' => 'string', 'format' => 'binary', 'description' => 'Private file download; response includes Content-Disposition and nosniff headers.'],
                    'PublicCourseEnrollmentResponse' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['type' => 'object', 'required' => ['id', 'course_id', 'course_title', 'organization_id', 'organization_name', 'status', 'enrolled_at'], 'properties' => ['id' => ['type' => 'string', 'format' => 'uuid'], 'course_id' => ['type' => 'string', 'format' => 'uuid'], 'course_title' => ['type' => 'string'], 'organization_id' => ['type' => 'string', 'format' => 'uuid'], 'organization_name' => ['type' => 'string'], 'status' => ['const' => 'active'], 'enrolled_at' => ['type' => ['string', 'null'], 'format' => 'date-time']]]]],
                    'CourseOrderRecord' => ['type' => 'object', 'required' => ['id', 'course_id', 'organization_id', 'amount_paise', 'status', 'expires_at', 'paid_at'], 'properties' => ['id' => ['type' => 'string', 'format' => 'uuid'], 'course_id' => ['type' => 'string', 'format' => 'uuid'], 'organization_id' => ['type' => 'string', 'format' => 'uuid'], 'amount_paise' => ['type' => 'integer', 'minimum' => 1], 'status' => ['type' => 'string', 'enum' => ['pending', 'paid', 'expired']], 'expires_at' => ['type' => ['string', 'null'], 'format' => 'date-time'], 'paid_at' => ['type' => ['string', 'null'], 'format' => 'date-time']]],
                    'CourseOrderResponse' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['$ref' => '#/components/schemas/CourseOrderRecord']]],
                    'CourseOrderCheckoutResponse' => ['type' => 'object', 'required' => ['data', 'checkout_mode'], 'properties' => ['data' => ['$ref' => '#/components/schemas/CourseOrderRecord'], 'checkout_mode' => ['const' => 'sandbox']]],
                    'SandboxPaymentWebhookInvoiceRequest' => ['type' => 'object', 'required' => ['event_id', 'type', 'organization_id', 'invoice_id', 'amount_paise', 'method', 'transaction_reference'], 'properties' => ['event_id' => ['type' => 'string', 'maxLength' => 120], 'type' => ['const' => 'payment.captured'], 'organization_id' => ['type' => 'string', 'format' => 'uuid'], 'invoice_id' => ['type' => 'string', 'format' => 'uuid'], 'amount_paise' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000000000], 'method' => ['type' => 'string', 'enum' => ['cash', 'bank_transfer', 'upi', 'cheque', 'other']], 'transaction_reference' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 120, 'pattern' => '\\S']], 'not' => ['required' => ['course_order_id']]],
                    'SandboxPaymentWebhookCourseRequest' => ['type' => 'object', 'required' => ['event_id', 'type', 'organization_id', 'course_order_id', 'amount_paise', 'method', 'transaction_reference'], 'properties' => ['event_id' => ['type' => 'string', 'maxLength' => 120], 'type' => ['const' => 'course.payment_captured'], 'organization_id' => ['type' => 'string', 'format' => 'uuid'], 'course_order_id' => ['type' => 'string', 'format' => 'uuid'], 'amount_paise' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000000000], 'method' => ['type' => 'string', 'enum' => ['cash', 'bank_transfer', 'upi', 'cheque', 'other']], 'transaction_reference' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 120, 'pattern' => '\\S']], 'not' => ['required' => ['invoice_id']]],
                    'SandboxPaymentWebhookRequest' => ['oneOf' => [['$ref' => '#/components/schemas/SandboxPaymentWebhookInvoiceRequest'], ['$ref' => '#/components/schemas/SandboxPaymentWebhookCourseRequest']], 'description' => 'Sign the exact raw JSON body with X-PaathNexus-Signature, a lowercase hexadecimal HMAC-SHA256 digest. Event IDs are idempotent; reusing an event ID with a different body is rejected.'],
                    'SandboxPaymentWebhookResponse' => ['type' => 'object', 'required' => ['status', 'payment_id', 'order_id'], 'properties' => ['status' => ['type' => 'string', 'enum' => ['processed', 'duplicate']], 'payment_id' => ['type' => ['string', 'null'], 'format' => 'uuid'], 'order_id' => ['type' => ['string', 'null'], 'format' => 'uuid']]],
                    'AssessmentRecord' => [
                        'type' => 'object', 'required' => ['id', 'course_id', 'title', 'instructions', 'duration_minutes', 'attempts_allowed', 'randomize_questions', 'randomized_question_count', 'opens_at', 'closes_at', 'status', 'question_count'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'course_id' => ['type' => 'string', 'format' => 'uuid'],
                            'title' => ['type' => 'string'], 'instructions' => ['type' => ['string', 'null']],
                            'duration_minutes' => ['type' => ['integer', 'null'], 'minimum' => 1, 'maximum' => 360],
                            'attempts_allowed' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
                            'randomize_questions' => ['type' => 'boolean'],
                            'randomized_question_count' => ['type' => ['integer', 'null'], 'minimum' => 1, 'maximum' => 100],
                            'opens_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                            'closes_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                            'status' => ['type' => 'string', 'enum' => ['draft', 'published']],
                            'question_count' => ['type' => 'integer', 'minimum' => 0],
                            'my_attempts' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AssessmentAttemptSummary']],
                        ],
                    ],
                    'AssessmentListResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AssessmentRecord']]],
                    ],
                    'AssessmentQuestionOptionInput' => [
                        'type' => 'object', 'required' => ['text', 'is_correct'],
                        'properties' => ['text' => ['type' => 'string', 'maxLength' => 1000], 'is_correct' => ['type' => 'boolean']],
                    ],
                    'AssessmentQuestionOptionRecord' => [
                        'type' => 'object', 'required' => ['id', 'text', 'is_correct'],
                        'properties' => ['id' => ['type' => 'string', 'format' => 'uuid'], 'text' => ['type' => 'string'], 'is_correct' => ['type' => 'boolean']],
                    ],
                    'AssessmentRubricCriterionInput' => [
                        'type' => 'object', 'required' => ['id', 'label', 'weight'],
                        'properties' => ['id' => ['type' => 'string', 'format' => 'uuid'], 'label' => ['type' => 'string', 'maxLength' => 160], 'weight' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]],
                    ],
                    'StoreAssessmentQuestionRequest' => [
                        'type' => 'object', 'required' => ['prompt', 'difficulty', 'type'],
                        'properties' => [
                            'prompt' => ['type' => 'string', 'maxLength' => 5000],
                            'explanation' => ['type' => ['string', 'null'], 'maxLength' => 5000],
                            'topic' => ['type' => ['string', 'null'], 'maxLength' => 120],
                            'difficulty' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
                            'type' => ['type' => 'string', 'enum' => ['single_choice', 'multiple_select', 'short_answer']],
                            'options' => ['type' => 'array', 'minItems' => 2, 'maxItems' => 8, 'items' => ['$ref' => '#/components/schemas/AssessmentQuestionOptionInput'], 'description' => 'Required for objective questions and excluded for short_answer. Single-choice requires exactly one correct option; other objective questions require at least one.'],
                            'grading_rubric' => ['type' => 'array', 'minItems' => 2, 'maxItems' => 10, 'items' => ['$ref' => '#/components/schemas/AssessmentRubricCriterionInput'], 'description' => 'Optional and only valid for short_answer questions. Criterion weights must total 100.'],
                        ],
                        'allOf' => [['oneOf' => [
                            ['type' => 'object', 'required' => ['type', 'options'], 'properties' => ['type' => ['const' => 'single_choice']], 'not' => ['required' => ['grading_rubric']]],
                            ['type' => 'object', 'required' => ['type', 'options'], 'properties' => ['type' => ['const' => 'multiple_select']], 'not' => ['required' => ['grading_rubric']]],
                            ['type' => 'object', 'required' => ['type'], 'properties' => ['type' => ['const' => 'short_answer']], 'not' => ['required' => ['options']]],
                        ]]],
                    ],
                    'AssessmentQuestionRecord' => [
                        'type' => 'object', 'required' => ['id', 'course_id', 'topic', 'type', 'prompt', 'explanation', 'difficulty', 'grading_rubric', 'options'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'course_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                            'topic' => ['type' => ['string', 'null']], 'type' => ['type' => 'string', 'enum' => ['single_choice', 'multiple_select', 'short_answer']],
                            'prompt' => ['type' => 'string'], 'explanation' => ['type' => ['string', 'null']], 'difficulty' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
                            'grading_rubric' => ['type' => ['array', 'null'], 'items' => ['$ref' => '#/components/schemas/AssessmentRubricCriterionInput']],
                            'options' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AssessmentQuestionOptionRecord']],
                        ],
                    ],
                    'AssessmentQuestionResponse' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['$ref' => '#/components/schemas/AssessmentQuestionRecord']]],
                    'AssessmentQuestionListResponse' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AssessmentQuestionRecord']]]],
                    'QuestionImportRow' => [
                        'type' => 'object', 'required' => ['row_number', 'valid', 'question', 'errors'],
                        'properties' => [
                            'row_number' => ['type' => 'integer', 'minimum' => 2], 'valid' => ['type' => 'boolean'],
                            'question' => ['type' => 'object', 'required' => ['prompt', 'topic', 'difficulty', 'type', 'options'], 'properties' => [
                                'prompt' => ['type' => 'string'], 'topic' => ['type' => ['string', 'null']], 'difficulty' => ['type' => ['integer', 'string']],
                                'type' => ['type' => 'string'], 'options' => ['type' => 'array', 'maxItems' => 8, 'items' => ['type' => 'object', 'required' => ['text', 'is_correct'], 'properties' => ['text' => ['type' => 'string'], 'is_correct' => ['type' => 'boolean']]]],
                            ]],
                            'errors' => ['type' => 'object', 'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']]],
                        ],
                    ],
                    'QuestionImportPreview' => [
                        'type' => 'object', 'required' => ['valid', 'row_count', 'valid_count', 'rows', 'errors'],
                        'properties' => [
                            'valid' => ['type' => 'boolean'], 'row_count' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                            'valid_count' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100], 'rows' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/QuestionImportRow']],
                            'errors' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                    ],
                    'QuestionImportPreviewResponse' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['$ref' => '#/components/schemas/QuestionImportPreview']]],
                    'QuestionImportResponse' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['type' => 'object', 'required' => ['imported_count', 'questions'], 'properties' => ['imported_count' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100], 'questions' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AssessmentQuestionRecord']]]]]],
                    'QuestionImportValidationErrorResponse' => ['type' => 'object', 'required' => ['message', 'data'], 'properties' => ['message' => ['type' => 'string'], 'data' => ['$ref' => '#/components/schemas/QuestionImportPreview']]],
                    'StoreAssessmentRequest' => [
                        'type' => 'object', 'required' => ['title', 'attempts_allowed', 'questions'],
                        'properties' => [
                            'title' => ['type' => 'string', 'maxLength' => 180],
                            'instructions' => ['type' => ['string', 'null'], 'maxLength' => 5000],
                            'duration_minutes' => ['type' => ['integer', 'null'], 'minimum' => 1, 'maximum' => 360],
                            'attempts_allowed' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
                            'randomize_questions' => ['type' => 'boolean'],
                            'randomized_question_count' => ['type' => ['integer', 'null'], 'minimum' => 1, 'maximum' => 100, 'description' => 'Cannot exceed the number of selected questions.'],
                            'opens_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                            'closes_at' => ['type' => ['string', 'null'], 'format' => 'date-time', 'description' => 'Must be later than opens_at when both are supplied.'],
                            'status' => ['type' => 'string', 'enum' => ['draft', 'published'], 'default' => 'published'],
                            'questions' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 100, 'items' => ['$ref' => '#/components/schemas/AssessmentQuestionSelectionInput']],
                        ],
                    ],
                    'AssessmentQuestionSelectionInput' => [
                        'type' => 'object', 'required' => ['question_id', 'points'],
                        'properties' => ['question_id' => ['type' => 'string', 'format' => 'uuid'], 'points' => ['type' => 'number', 'exclusiveMinimum' => 0, 'maximum' => 100000]],
                    ],
                    'SaveObjectiveAssessmentAnswerRequest' => [
                        'type' => 'object', 'required' => ['selected_option_ids'],
                        'properties' => ['selected_option_ids' => ['type' => 'array', 'maxItems' => 8, 'uniqueItems' => true, 'items' => ['type' => 'string', 'format' => 'uuid'], 'description' => 'Must contain exactly one option for single-choice questions.']],
                    ],
                    'SaveShortAnswerAssessmentAnswerRequest' => [
                        'type' => 'object', 'required' => ['response_text'],
                        'properties' => ['response_text' => ['type' => ['string', 'null'], 'maxLength' => 50000]],
                    ],
                    'SaveAssessmentAnswerRequest' => [
                        'oneOf' => [['$ref' => '#/components/schemas/SaveObjectiveAssessmentAnswerRequest'], ['$ref' => '#/components/schemas/SaveShortAnswerAssessmentAnswerRequest']],
                        'description' => 'Use selected_option_ids for objective questions or response_text for short-answer questions.',
                    ],
                    'AssessmentRubricScoreInput' => [
                        'type' => 'object', 'required' => ['criterion_id', 'score'],
                        'properties' => ['criterion_id' => ['type' => 'string', 'format' => 'uuid'], 'score' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100]],
                    ],
                    'ManualAssessmentGradeRequest' => [
                        'oneOf' => [
                            ['type' => 'object', 'required' => ['points_awarded'], 'properties' => ['points_awarded' => ['type' => 'number', 'minimum' => 0, 'description' => 'Cannot exceed the question points configured in its assessment.'], 'feedback' => ['type' => ['string', 'null'], 'maxLength' => 5000]], 'not' => ['required' => ['rubric_scores']]],
                            ['type' => 'object', 'required' => ['rubric_scores'], 'properties' => ['rubric_scores' => ['type' => 'array', 'minItems' => 2, 'maxItems' => 10, 'items' => ['$ref' => '#/components/schemas/AssessmentRubricScoreInput'], 'description' => 'Score every configured criterion exactly once; actual array size is the question rubric size.'], 'feedback' => ['type' => ['string', 'null'], 'maxLength' => 5000]], 'not' => ['required' => ['points_awarded']]],
                        ],
                        'description' => 'For questions with a rubric, submit rubric_scores and omit points_awarded. For questions without a rubric, submit points_awarded and omit rubric_scores.',
                    ],
                    'AssessmentResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/AssessmentRecord']],
                    ],
                    'AssessmentAttemptListItem' => [
                        'allOf' => [
                            ['$ref' => '#/components/schemas/AssessmentAttemptSummary'],
                            ['type' => 'object', 'properties' => [
                                'student_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                                'student_name' => ['type' => ['string', 'null']], 'student_code' => ['type' => ['string', 'null']],
                            ]],
                        ],
                    ],
                    'AssessmentAttemptsResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AssessmentAttemptListItem']]],
                    ],
                    'StudentAttemptQuestion' => [
                        'type' => 'object', 'required' => ['id', 'prompt', 'type', 'points', 'options', 'selected_option_ids', 'response_text'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'prompt' => ['type' => 'string'],
                            'type' => ['type' => 'string', 'enum' => ['single_choice', 'multiple_select', 'short_answer']],
                            'points' => ['type' => 'number', 'exclusiveMinimum' => 0],
                            'options' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AssessmentAnswerOption']],
                            'selected_option_ids' => ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'uuid']],
                            'response_text' => ['type' => ['string', 'null']],
                        ],
                    ],
                    'StudentAssessmentAttemptResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'object', 'required' => ['id', 'status', 'attempt_number', 'started_at', 'expires_at', 'questions'], 'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'],
                            'status' => ['type' => 'string', 'enum' => ['in_progress']], 'attempt_number' => ['type' => 'integer', 'minimum' => 1],
                            'started_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                            'expires_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                            'questions' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/StudentAttemptQuestion']],
                        ]]],
                    ],
                    'AssessmentAttemptResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/AssessmentAttemptSummary']],
                    ],
                    'AssessmentAnalyticsQuestion' => [
                        'type' => 'object', 'required' => ['question_id', 'prompt', 'type', 'points', 'attempts_presented', 'correct_count', 'accuracy_percent'],
                        'properties' => [
                            'question_id' => ['type' => 'string', 'format' => 'uuid'], 'prompt' => ['type' => 'string'],
                            'type' => ['type' => 'string', 'enum' => ['single_choice', 'multiple_select', 'short_answer']],
                            'points' => ['type' => 'number', 'exclusiveMinimum' => 0],
                            'attempts_presented' => ['type' => 'integer', 'minimum' => 0], 'correct_count' => ['type' => 'integer', 'minimum' => 0],
                            'accuracy_percent' => ['type' => ['number', 'null'], 'minimum' => 0, 'maximum' => 100],
                        ],
                    ],
                    'AssessmentAnalyticsResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'object', 'required' => [
                            'assessment_id', 'question_pool_count', 'randomized_question_count', 'filters', 'attempt_count', 'completed_attempt_count', 'average_score_percent', 'question_analytics',
                        ], 'properties' => [
                            'assessment_id' => ['type' => 'string', 'format' => 'uuid'],
                            'question_pool_count' => ['type' => 'integer', 'minimum' => 0],
                            'randomized_question_count' => ['type' => ['integer', 'null'], 'minimum' => 1],
                            'filters' => ['type' => 'object', 'required' => ['from', 'to', 'student_id'], 'properties' => [
                                'from' => ['type' => ['string', 'null'], 'format' => 'date'], 'to' => ['type' => ['string', 'null'], 'format' => 'date'],
                                'student_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                            ]],
                            'attempt_count' => ['type' => 'integer', 'minimum' => 0], 'completed_attempt_count' => ['type' => 'integer', 'minimum' => 0],
                            'average_score_percent' => ['type' => ['number', 'null'], 'minimum' => 0, 'maximum' => 100],
                            'question_analytics' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AssessmentAnalyticsQuestion']],
                        ]]],
                    ],
                    'AssessmentAnswerOption' => [
                        'type' => 'object', 'required' => ['id', 'text'],
                        'properties' => ['id' => ['type' => 'string', 'format' => 'uuid'], 'text' => ['type' => 'string']],
                    ],
                    'StudentAssessmentAnswer' => [
                        'type' => 'object', 'required' => ['question_id', 'prompt', 'type', 'points', 'points_awarded', 'response_text', 'selected_options', 'grader_feedback', 'pending_review'],
                        'properties' => [
                            'question_id' => ['type' => 'string', 'format' => 'uuid'], 'prompt' => ['type' => 'string'],
                            'type' => ['type' => 'string', 'enum' => ['single_choice', 'multiple_select', 'short_answer']],
                            'points' => ['type' => 'number', 'exclusiveMinimum' => 0], 'points_awarded' => ['type' => 'number', 'minimum' => 0],
                            'response_text' => ['type' => ['string', 'null']],
                            'selected_options' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AssessmentAnswerOption']],
                            'grader_feedback' => ['type' => ['string', 'null']], 'pending_review' => ['type' => 'boolean'],
                        ],
                    ],
                    'StudentAssessmentResultResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'object', 'required' => ['attempt', 'answers'], 'properties' => [
                            'attempt' => ['$ref' => '#/components/schemas/AssessmentAttemptSummary'],
                            'answers' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/StudentAssessmentAnswer']],
                        ]]],
                    ],
                    'CourseInstructor' => [
                        'type' => 'object', 'required' => ['id', 'name'],
                        'properties' => ['id' => ['type' => 'integer'], 'name' => ['type' => 'string']],
                    ],
                    'CourseRecord' => [
                        'type' => 'object', 'required' => ['id', 'code', 'title', 'description', 'status', 'is_public', 'price_paise', 'academic_year_id', 'academic_year', 'subject_id', 'subject', 'instructors', 'lessons_count', 'enrollments_count'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'code' => ['type' => 'string'], 'title' => ['type' => 'string'],
                            'description' => ['type' => ['string', 'null']], 'status' => ['type' => 'string', 'enum' => ['active', 'draft']],
                            'is_public' => ['type' => 'boolean'], 'price_paise' => ['type' => 'integer', 'minimum' => 0],
                            'academic_year_id' => ['type' => ['string', 'null'], 'format' => 'uuid'], 'academic_year' => ['type' => ['string', 'null']],
                            'subject_id' => ['type' => ['string', 'null'], 'format' => 'uuid'], 'subject' => ['type' => ['string', 'null']],
                            'instructors' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/CourseInstructor']],
                            'lessons_count' => ['type' => 'integer', 'minimum' => 0], 'enrollments_count' => ['type' => 'integer', 'minimum' => 0],
                        ],
                    ],
                    'CourseListResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/CourseRecord']]],
                    ],
                    'CourseResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/CourseRecord']],
                    ],
                    'CourseInstructorsResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/CourseInstructor']]],
                    ],
                    'CourseEnrollmentRecord' => [
                        'type' => 'object', 'required' => ['id', 'student_id', 'student_code', 'student_name', 'status', 'enrolled_at'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'student_id' => ['type' => 'string', 'format' => 'uuid'],
                            'student_code' => ['type' => ['string', 'null']], 'student_name' => ['type' => ['string', 'null']],
                            'status' => ['const' => 'active'], 'enrolled_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                        ],
                    ],
                    'CourseEnrollmentsResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/CourseEnrollmentRecord']]],
                    ],
                    'CourseEnrollmentCreated' => [
                        'type' => 'object', 'required' => ['id', 'course_id', 'student_id', 'student_name', 'status'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'course_id' => ['type' => 'string', 'format' => 'uuid'],
                            'student_id' => ['type' => 'string', 'format' => 'uuid'], 'student_name' => ['type' => 'string'], 'status' => ['const' => 'active'],
                        ],
                    ],
                    'CourseEnrollmentResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/CourseEnrollmentCreated']],
                    ],
                    'EnrollStudentRequest' => [
                        'type' => 'object', 'required' => ['student_id'],
                        'properties' => ['student_id' => ['type' => 'string', 'format' => 'uuid']],
                    ],
                    'LessonPrerequisite' => [
                        'type' => 'object', 'required' => ['id', 'title'],
                        'properties' => ['id' => ['type' => 'string', 'format' => 'uuid'], 'title' => ['type' => 'string']],
                    ],
                    'LessonRecord' => [
                        'type' => 'object', 'required' => ['id', 'course_id', 'title', 'content', 'resource_type', 'resource_url', 'position', 'status', 'prerequisites', 'prerequisites_met', 'my_progress'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'course_id' => ['type' => 'string', 'format' => 'uuid'],
                            'title' => ['type' => 'string'], 'content' => ['type' => ['string', 'null']],
                            'resource_type' => ['type' => ['string', 'null'], 'enum' => ['video', 'document', null]],
                            'resource_url' => ['type' => ['string', 'null'], 'format' => 'uri'], 'position' => ['type' => 'integer', 'minimum' => 1],
                            'status' => ['type' => 'string', 'enum' => ['active', 'archived']],
                            'prerequisites' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/LessonPrerequisite']],
                            'prerequisites_met' => ['type' => 'boolean'],
                            'my_progress' => ['type' => ['string', 'null'], 'enum' => ['not_started', 'started', 'completed', null]],
                        ],
                    ],
                    'LessonListResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/LessonRecord']]],
                    ],
                    'LessonResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/LessonRecord']],
                    ],
                    'CourseProgressEntry' => [
                        'type' => 'object', 'required' => ['student_id', 'student_code', 'student_name', 'completed_lessons', 'total_lessons', 'completion_percent'],
                        'properties' => [
                            'student_id' => ['type' => 'string', 'format' => 'uuid'], 'student_code' => ['type' => ['string', 'null']],
                            'student_name' => ['type' => ['string', 'null']], 'completed_lessons' => ['type' => 'integer', 'minimum' => 0],
                            'total_lessons' => ['type' => 'integer', 'minimum' => 0], 'completion_percent' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                        ],
                    ],
                    'CourseProgressResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/CourseProgressEntry']]],
                    ],
                    'LessonProgressRecord' => [
                        'type' => 'object', 'required' => ['lesson_id', 'student_id', 'status', 'completed_at'],
                        'properties' => [
                            'lesson_id' => ['type' => 'string', 'format' => 'uuid'], 'student_id' => ['type' => 'string', 'format' => 'uuid'],
                            'status' => ['type' => 'string', 'enum' => ['started', 'completed']], 'completed_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                        ],
                    ],
                    'LessonProgressResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/LessonProgressRecord']],
                    ],
                    'StoreCourseRequest' => [
                        'type' => 'object', 'required' => ['code', 'title'],
                        'properties' => [
                            'code' => ['type' => 'string', 'maxLength' => 64], 'title' => ['type' => 'string', 'maxLength' => 160],
                            'description' => ['type' => ['string', 'null'], 'maxLength' => 5000],
                            'price_paise' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000000000],
                            'academic_year_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                            'subject_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                        ],
                    ],
                    'UpdateCourseRequest' => [
                        'type' => 'object', 'required' => ['title', 'status'],
                        'properties' => [
                            'title' => ['type' => 'string', 'maxLength' => 160], 'description' => ['type' => ['string', 'null'], 'maxLength' => 5000],
                            'status' => ['type' => 'string', 'enum' => ['active', 'draft']], 'is_public' => ['type' => 'boolean'],
                            'price_paise' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000000000],
                        ],
                    ],
                    'UpdateCourseInstructorsRequest' => [
                        'type' => 'object', 'required' => ['instructor_ids'],
                        'properties' => ['instructor_ids' => ['type' => 'array', 'maxItems' => 30, 'uniqueItems' => true, 'items' => ['type' => 'integer', 'minimum' => 1]]],
                    ],
                    'StoreLessonRequest' => [
                        'type' => 'object', 'required' => ['title'],
                        'properties' => [
                            'title' => ['type' => 'string', 'maxLength' => 180], 'content' => ['type' => ['string', 'null'], 'maxLength' => 20000],
                            'resource_url' => ['type' => ['string', 'null'], 'format' => 'uri', 'pattern' => '^https://', 'maxLength' => 2048],
                            'resource_type' => ['type' => ['string', 'null'], 'enum' => ['video', 'document', null]],
                        ],
                        'allOf' => [
                            ['if' => ['required' => ['resource_url'], 'properties' => ['resource_url' => ['type' => 'string']]], 'then' => ['required' => ['resource_type']]],
                            ['if' => ['required' => ['resource_type'], 'properties' => ['resource_type' => ['type' => 'string']]], 'then' => ['required' => ['resource_url']]],
                        ],
                    ],
                    'UpdateLessonRequest' => [
                        'allOf' => [
                            ['$ref' => '#/components/schemas/StoreLessonRequest'],
                            ['type' => 'object', 'required' => ['status'], 'properties' => [
                                'prerequisite_ids' => ['type' => 'array', 'maxItems' => 30, 'uniqueItems' => true, 'items' => ['type' => 'string', 'format' => 'uuid']],
                                'status' => ['type' => 'string', 'enum' => ['active', 'archived']],
                            ]],
                        ],
                    ],
                    'UpdateLessonProgressRequest' => [
                        'type' => 'object', 'required' => ['status'],
                        'properties' => [
                            'status' => ['type' => 'string', 'enum' => ['started', 'completed']],
                            'student_id' => ['type' => ['string', 'null'], 'format' => 'uuid', 'description' => 'Accepted only for course managers; teacher scope is still enforced.'],
                        ],
                    ],
                    'StudentStudyPlanRecord' => [
                        'type' => 'object', 'required' => ['id', 'title', 'course_id', 'course_title', 'scheduled_at', 'duration_minutes', 'status', 'completed_at'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'title' => ['type' => 'string'],
                            'course_id' => ['type' => ['string', 'null'], 'format' => 'uuid'], 'course_title' => ['type' => ['string', 'null']],
                            'scheduled_at' => ['type' => 'string', 'format' => 'date-time'],
                            'duration_minutes' => ['type' => 'integer', 'minimum' => 5, 'maximum' => 240],
                            'status' => ['type' => 'string', 'enum' => ['planned', 'completed']],
                            'completed_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                        ],
                    ],
                    'StudentStudyPlanListResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'maxItems' => 100, 'items' => ['$ref' => '#/components/schemas/StudentStudyPlanRecord']]],
                    ],
                    'StudentStudyPlanResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/StudentStudyPlanRecord']],
                    ],
                    'StoreStudentStudyPlanRequest' => [
                        'type' => 'object', 'required' => ['title', 'scheduled_at', 'duration_minutes'],
                        'properties' => [
                            'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 140, 'pattern' => '.*\S.*'],
                            'scheduled_at' => ['type' => 'string', 'format' => 'date-time', 'description' => 'Must be a future timestamp.'],
                            'duration_minutes' => ['type' => 'integer', 'minimum' => 5, 'maximum' => 240],
                            'course_id' => ['type' => ['string', 'null'], 'format' => 'uuid', 'description' => 'If supplied, must be an actively enrolled course.'],
                        ],
                    ],
                    'StudentMemberCandidate' => [
                        'type' => 'object', 'required' => ['id', 'name'],
                        'properties' => ['id' => ['type' => 'integer'], 'name' => ['type' => 'string']],
                    ],
                    'StudentMemberCandidatesResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/StudentMemberCandidate']]],
                    ],
                    'StudentMemberLinkRequest' => [
                        'type' => 'object', 'required' => ['user_id'],
                        'properties' => ['user_id' => ['type' => ['integer', 'null'], 'minimum' => 1]],
                    ],
                    'StudentMemberLinkResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'object', 'required' => ['id', 'name', 'user_id', 'member_name'], 'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'name' => ['type' => 'string'],
                            'user_id' => ['type' => ['integer', 'null']], 'member_name' => ['type' => ['string', 'null']],
                        ]]],
                    ],
                    'GuardianCandidate' => [
                        'type' => 'object', 'required' => ['id', 'name', 'email'],
                        'properties' => ['id' => ['type' => 'integer'], 'name' => ['type' => 'string'], 'email' => ['type' => 'string', 'format' => 'email']],
                    ],
                    'GuardianCandidatesResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/GuardianCandidate']]],
                    ],
                    'GuardianLinkRecord' => [
                        'type' => 'object', 'required' => ['id', 'student_id', 'student_name', 'student_code', 'guardian_user_id', 'guardian_name', 'guardian_email', 'relationship', 'linked_at'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'student_id' => ['type' => 'string', 'format' => 'uuid'],
                            'student_name' => ['type' => ['string', 'null']], 'student_code' => ['type' => ['string', 'null']],
                            'guardian_user_id' => ['type' => 'integer'], 'guardian_name' => ['type' => ['string', 'null']],
                            'guardian_email' => ['type' => ['string', 'null'], 'format' => 'email'],
                            'relationship' => ['type' => 'string', 'enum' => ['parent', 'guardian', 'grandparent', 'caregiver', 'other']],
                            'linked_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                        ],
                    ],
                    'GuardianLinksResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/GuardianLinkRecord']]],
                    ],
                    'GuardianLinkResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/GuardianLinkRecord']],
                    ],
                    'StoreGuardianLinkRequest' => [
                        'type' => 'object', 'required' => ['student_id', 'guardian_user_id', 'relationship'],
                        'properties' => [
                            'student_id' => ['type' => 'string', 'format' => 'uuid'], 'guardian_user_id' => ['type' => 'integer', 'minimum' => 1],
                            'relationship' => ['type' => 'string', 'enum' => ['parent', 'guardian', 'grandparent', 'caregiver', 'other']],
                        ],
                    ],
                    'GuardianLinkEndedResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'object', 'required' => ['id', 'status'], 'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'status' => ['const' => 'ended'],
                        ]]],
                    ],
                    'GuardianAttendanceRecord' => [
                        'type' => 'object', 'required' => ['date', 'class_name', 'status'],
                        'properties' => [
                            'date' => ['type' => ['string', 'null'], 'format' => 'date'], 'class_name' => ['type' => ['string', 'null']],
                            'status' => ['type' => 'string', 'enum' => ['present', 'absent', 'late', 'excused']],
                        ],
                    ],
                    'GuardianAttendanceSummary' => [
                        'type' => 'object', 'required' => ['sessions_recorded', 'present', 'rate_percent', 'recent'],
                        'properties' => [
                            'sessions_recorded' => ['type' => 'integer', 'minimum' => 0], 'present' => ['type' => 'integer', 'minimum' => 0],
                            'rate_percent' => ['type' => ['number', 'null'], 'minimum' => 0, 'maximum' => 100],
                            'recent' => ['type' => 'array', 'maxItems' => 10, 'items' => ['$ref' => '#/components/schemas/GuardianAttendanceRecord']],
                        ],
                    ],
                    'GuardianLearningSummary' => [
                        'type' => 'object', 'required' => ['courses_enrolled', 'completed_lessons', 'total_lessons', 'completion_percent', 'assignments'],
                        'properties' => [
                            'courses_enrolled' => ['type' => 'integer', 'minimum' => 0], 'completed_lessons' => ['type' => 'integer', 'minimum' => 0],
                            'total_lessons' => ['type' => 'integer', 'minimum' => 0], 'completion_percent' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
                            'assignments' => ['type' => 'array', 'maxItems' => 8, 'items' => ['$ref' => '#/components/schemas/GuardianHomework']],
                        ],
                    ],
                    'GuardianHomework' => [
                        'type' => 'object', 'required' => ['id', 'title', 'course_title', 'due_at', 'submission_status'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'title' => ['type' => 'string'],
                            'course_title' => ['type' => ['string', 'null']], 'due_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                            'submission_status' => ['type' => ['string', 'null'], 'enum' => ['submitted', 'graded', null]],
                        ],
                    ],
                    'GuardianAssessmentResult' => [
                        'type' => 'object', 'required' => ['title', 'score', 'max_points', 'completed_at'],
                        'properties' => [
                            'title' => ['type' => ['string', 'null']], 'score' => ['type' => 'number', 'minimum' => 0],
                            'max_points' => ['type' => 'number', 'exclusiveMinimum' => 0], 'completed_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                        ],
                    ],
                    'GuardianUpcomingExam' => [
                        'type' => 'object', 'required' => ['title', 'course_title', 'opens_at', 'closes_at'],
                        'properties' => [
                            'title' => ['type' => 'string'], 'course_title' => ['type' => ['string', 'null']],
                            'opens_at' => ['type' => 'string', 'format' => 'date-time'], 'closes_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                        ],
                    ],
                    'GuardianResultsSummary' => [
                        'type' => 'object', 'required' => ['assessments_completed', 'average_percent', 'recent', 'upcoming_exams'],
                        'properties' => [
                            'assessments_completed' => ['type' => 'integer', 'minimum' => 0], 'average_percent' => ['type' => ['number', 'null'], 'minimum' => 0, 'maximum' => 100],
                            'recent' => ['type' => 'array', 'maxItems' => 10, 'items' => ['$ref' => '#/components/schemas/GuardianAssessmentResult']],
                            'upcoming_exams' => ['type' => 'array', 'maxItems' => 10, 'items' => ['$ref' => '#/components/schemas/GuardianUpcomingExam']],
                        ],
                    ],
                    'GuardianChildSummary' => [
                        'type' => 'object', 'required' => ['student_id', 'student_name', 'student_code', 'relationship'],
                        'properties' => [
                            'student_id' => ['type' => 'string', 'format' => 'uuid'], 'student_name' => ['type' => 'string'],
                            'student_code' => ['type' => 'string'], 'relationship' => ['type' => 'string', 'enum' => ['parent', 'guardian', 'grandparent', 'caregiver', 'other']],
                            'attendance' => ['$ref' => '#/components/schemas/GuardianAttendanceSummary'],
                            'learning' => ['$ref' => '#/components/schemas/GuardianLearningSummary'],
                            'results' => ['$ref' => '#/components/schemas/GuardianResultsSummary'],
                        ],
                    ],
                    'GuardianChildrenResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/GuardianChildSummary']]],
                    ],
                    'ClassRosterStudent' => [
                        'type' => 'object', 'required' => ['id', 'student_code', 'name', 'section_id'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'student_code' => ['type' => 'string'],
                            'name' => ['type' => 'string'], 'section_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                        ],
                    ],
                    'ClassRecord' => [
                        'type' => 'object', 'required' => ['id', 'name', 'grade', 'section', 'can_attend_whole', 'assigned_section_ids', 'student_count', 'students'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'name' => ['type' => 'string'],
                            'grade' => ['type' => ['string', 'null']], 'section' => ['type' => ['string', 'null']],
                            'can_attend_whole' => ['type' => 'boolean'],
                            'assigned_section_ids' => ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'uuid']],
                            'student_count' => ['type' => 'integer', 'minimum' => 0],
                            'students' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/ClassRosterStudent']],
                        ],
                    ],
                    'ClassListResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/ClassRecord']]],
                    ],
                    'ClassResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/ClassRecord']],
                    ],
                    'StoreClassRequest' => [
                        'type' => 'object', 'required' => ['name'],
                        'properties' => [
                            'name' => ['type' => 'string', 'maxLength' => 100], 'grade' => ['type' => ['string', 'null'], 'maxLength' => 40],
                            'section' => ['type' => ['string', 'null'], 'maxLength' => 40],
                        ],
                    ],
                    'StudentRecord' => [
                        'type' => 'object', 'required' => ['id', 'student_code', 'name', 'status', 'created_at'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'student_code' => ['type' => 'string'],
                            'name' => ['type' => 'string'], 'status' => ['type' => 'string', 'enum' => ['active', 'inactive']],
                            'created_at' => ['type' => 'string', 'format' => 'date-time'],
                        ],
                    ],
                    'StudentListResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/StudentRecord']]],
                    ],
                    'StudentCreated' => [
                        'type' => 'object', 'required' => ['id', 'student_code', 'name', 'status'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'student_code' => ['type' => 'string'],
                            'name' => ['type' => 'string'], 'status' => ['type' => 'string', 'enum' => ['active', 'inactive']],
                        ],
                    ],
                    'StudentResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/StudentCreated']],
                    ],
                    'StoreStudentRequest' => [
                        'type' => 'object', 'required' => ['student_code', 'name'],
                        'properties' => ['student_code' => ['type' => 'string', 'maxLength' => 64], 'name' => ['type' => 'string', 'maxLength' => 160]],
                    ],
                    'ClassEnrollmentStudent' => [
                        'type' => 'object', 'required' => ['id', 'student_code', 'name'],
                        'properties' => ['id' => ['type' => 'string', 'format' => 'uuid'], 'student_code' => ['type' => 'string'], 'name' => ['type' => 'string']],
                    ],
                    'ClassEnrollmentCreated' => [
                        'type' => 'object', 'required' => ['id', 'class_id', 'section_id', 'section_name', 'student', 'status', 'enrolled_on'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'class_id' => ['type' => 'string', 'format' => 'uuid'],
                            'section_id' => ['type' => ['string', 'null'], 'format' => 'uuid'], 'section_name' => ['type' => ['string', 'null']],
                            'student' => ['$ref' => '#/components/schemas/ClassEnrollmentStudent'], 'status' => ['const' => 'active'],
                            'enrolled_on' => ['type' => 'string', 'format' => 'date'],
                        ],
                    ],
                    'ClassEnrollmentResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/ClassEnrollmentCreated']],
                    ],
                    'EnrollClassStudentRequest' => [
                        'type' => 'object', 'required' => ['student_id'],
                        'properties' => ['student_id' => ['type' => 'string', 'format' => 'uuid'], 'section_id' => ['type' => ['string', 'null'], 'format' => 'uuid']],
                    ],
                    'AttendanceRecordStudent' => [
                        'type' => 'object', 'required' => ['student_id', 'student_name', 'student_code', 'status'],
                        'properties' => [
                            'student_id' => ['type' => 'string', 'format' => 'uuid'], 'student_name' => ['type' => ['string', 'null']],
                            'student_code' => ['type' => ['string', 'null']], 'status' => ['type' => 'string', 'enum' => ['present', 'absent', 'late', 'excused']],
                        ],
                    ],
                    'AttendanceSessionRecord' => [
                        'type' => 'object', 'required' => ['id', 'class_id', 'section_id', 'live_session_id', 'class_name', 'session_date', 'attendance_rate', 'updated_at'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'class_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                            'section_id' => ['type' => ['string', 'null'], 'format' => 'uuid'], 'live_session_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                            'class_name' => ['type' => 'string'], 'session_date' => ['type' => 'string', 'format' => 'date'],
                            'attendance_rate' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
                            'updated_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                            'records' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AttendanceRecordStudent']],
                        ],
                    ],
                    'AttendanceSessionsResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'maxItems' => 30, 'items' => ['$ref' => '#/components/schemas/AttendanceSessionRecord']]],
                    ],
                    'AttendanceSessionResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/AttendanceSessionRecord']],
                    ],
                    'LegacyAttendanceSessionRequest' => [
                        'type' => 'object', 'required' => ['class_name', 'attendance_rate'],
                        'properties' => [
                            'class_name' => ['type' => 'string', 'maxLength' => 100],
                            'attendance_rate' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
                        ],
                    ],
                    'MarkClassAttendanceRequest' => [
                        'type' => 'object', 'required' => ['records'],
                        'properties' => [
                            'section_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                            'records' => ['type' => 'array', 'minItems' => 1, 'description' => 'Include each active roster member exactly once; student_id values must be distinct.', 'items' => [
                                'type' => 'object', 'required' => ['student_id', 'status'],
                                'properties' => [
                                    'student_id' => ['type' => 'string', 'format' => 'uuid'],
                                    'status' => ['type' => 'string', 'enum' => ['present', 'absent', 'late', 'excused']],
                                ],
                            ]],
                        ],
                    ],
                    'AcademicYearRecord' => [
                        'type' => 'object', 'required' => ['id', 'name', 'starts_on', 'ends_on', 'status'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'name' => ['type' => 'string'],
                            'starts_on' => ['type' => 'string', 'format' => 'date'], 'ends_on' => ['type' => 'string', 'format' => 'date'],
                            'status' => ['type' => 'string', 'enum' => ['planned', 'active', 'completed']],
                        ],
                    ],
                    'AcademicYearsResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AcademicYearRecord']]],
                    ],
                    'AcademicYearResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/AcademicYearRecord']],
                    ],
                    'StoreAcademicYearRequest' => [
                        'type' => 'object', 'required' => ['name', 'starts_on', 'ends_on'],
                        'properties' => [
                            'name' => ['type' => 'string', 'maxLength' => 100],
                            'starts_on' => ['type' => 'string', 'format' => 'date'],
                            'ends_on' => ['type' => 'string', 'format' => 'date', 'description' => 'Must be later than starts_on.'],
                            'status' => ['type' => 'string', 'enum' => ['planned', 'active', 'completed']],
                        ],
                    ],
                    'SectionRecord' => [
                        'type' => 'object', 'required' => ['id', 'class_id', 'class_name', 'name'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'class_id' => ['type' => 'string', 'format' => 'uuid'],
                            'class_name' => ['type' => ['string', 'null']], 'name' => ['type' => 'string'],
                        ],
                    ],
                    'SectionsResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/SectionRecord']]],
                    ],
                    'SectionResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/SectionRecord']],
                    ],
                    'StoreSectionRequest' => [
                        'type' => 'object', 'required' => ['name'],
                        'properties' => ['name' => ['type' => 'string', 'maxLength' => 64]],
                    ],
                    'SubjectRecord' => [
                        'type' => 'object', 'required' => ['id', 'code', 'name', 'status'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'code' => ['type' => 'string'],
                            'name' => ['type' => 'string'], 'status' => ['const' => 'active'],
                        ],
                    ],
                    'SubjectsResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/SubjectRecord']]],
                    ],
                    'SubjectResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/SubjectRecord']],
                    ],
                    'StoreSubjectRequest' => [
                        'type' => 'object', 'required' => ['code', 'name'],
                        'properties' => ['code' => ['type' => 'string', 'maxLength' => 32], 'name' => ['type' => 'string', 'maxLength' => 120]],
                    ],
                    'SubjectOfferingRecord' => [
                        'type' => 'object', 'required' => ['id', 'class_id', 'academic_year_id', 'section_id', 'section_name', 'subject_id', 'subject_code', 'subject_name'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'class_id' => ['type' => 'string', 'format' => 'uuid'],
                            'academic_year_id' => ['type' => 'string', 'format' => 'uuid'],
                            'section_id' => ['type' => ['string', 'null'], 'format' => 'uuid'], 'section_name' => ['type' => ['string', 'null']],
                            'subject_id' => ['type' => 'string', 'format' => 'uuid'], 'subject_code' => ['type' => ['string', 'null']],
                            'subject_name' => ['type' => ['string', 'null']],
                        ],
                    ],
                    'SubjectOfferingsResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/SubjectOfferingRecord']]],
                    ],
                    'SubjectOfferingResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/SubjectOfferingRecord']],
                    ],
                    'StoreSubjectOfferingRequest' => [
                        'type' => 'object', 'required' => ['academic_year_id', 'subject_id'],
                        'properties' => [
                            'academic_year_id' => ['type' => 'string', 'format' => 'uuid'],
                            'subject_id' => ['type' => 'string', 'format' => 'uuid'], 'section_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                        ],
                    ],
                    'SubjectOfferingEndedResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'object', 'required' => ['id', 'status'], 'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'status' => ['const' => 'ended'],
                        ]]],
                    ],
                    'ClassTeacherAssignmentRecord' => [
                        'type' => 'object', 'required' => ['id', 'class_id', 'teacher_profile_id', 'teacher_name', 'member_name', 'employee_code', 'academic_year_id', 'section_id', 'section_name'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'class_id' => ['type' => 'string', 'format' => 'uuid'],
                            'teacher_profile_id' => ['type' => 'string', 'format' => 'uuid'], 'teacher_name' => ['type' => ['string', 'null']],
                            'member_name' => ['type' => ['string', 'null']], 'employee_code' => ['type' => ['string', 'null']],
                            'academic_year_id' => ['type' => 'string', 'format' => 'uuid'], 'section_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                            'section_name' => ['type' => ['string', 'null']],
                        ],
                    ],
                    'ClassTeacherAssignmentsResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/ClassTeacherAssignmentRecord']]],
                    ],
                    'ClassTeacherAssignmentResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/ClassTeacherAssignmentRecord']],
                    ],
                    'StoreClassTeacherAssignmentRequest' => [
                        'type' => 'object', 'required' => ['teacher_profile_id', 'academic_year_id'],
                        'properties' => [
                            'teacher_profile_id' => ['type' => 'string', 'format' => 'uuid'],
                            'academic_year_id' => ['type' => 'string', 'format' => 'uuid'], 'section_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                        ],
                    ],
                    'UpdateClassTeacherAssignmentRequest' => [
                        'type' => 'object', 'required' => ['teacher_profile_id'],
                        'properties' => ['teacher_profile_id' => ['type' => 'string', 'format' => 'uuid']],
                    ],
                    'ClassTeacherAssignmentEndedResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'object', 'required' => ['id', 'status'], 'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'status' => ['const' => 'ended'],
                        ]]],
                    ],
                    'ClassTeacherAssignmentEvent' => [
                        'type' => 'object', 'required' => ['id', 'assignment_id', 'action', 'from_teacher', 'to_teacher', 'section_name', 'actor_name', 'created_at'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'assignment_id' => ['type' => 'string', 'format' => 'uuid'],
                            'action' => ['type' => 'string', 'enum' => ['assigned', 'reassigned', 'removed']],
                            'from_teacher' => ['type' => ['string', 'null']], 'to_teacher' => ['type' => ['string', 'null']],
                            'section_name' => ['type' => ['string', 'null']], 'actor_name' => ['type' => ['string', 'null']],
                            'created_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                        ],
                    ],
                    'ClassTeacherAssignmentHistoryResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'maxItems' => 50, 'items' => ['$ref' => '#/components/schemas/ClassTeacherAssignmentEvent']]],
                    ],
                    'TimetableEntryRecord' => [
                        'type' => 'object', 'required' => ['id', 'academic_year_id', 'academic_year', 'class_id', 'class_name', 'section_id', 'section_name', 'subject', 'subject_id', 'teacher_profile_id', 'teacher_name', 'weekday', 'starts_at', 'ends_at', 'room'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'academic_year_id' => ['type' => 'string', 'format' => 'uuid'],
                            'academic_year' => ['type' => ['string', 'null']], 'class_id' => ['type' => 'string', 'format' => 'uuid'],
                            'class_name' => ['type' => ['string', 'null']], 'section_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                            'section_name' => ['type' => ['string', 'null']], 'subject' => ['type' => 'string'],
                            'subject_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                            'teacher_profile_id' => ['type' => ['string', 'null'], 'format' => 'uuid'], 'teacher_name' => ['type' => ['string', 'null']],
                            'weekday' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 7],
                            'starts_at' => ['type' => 'string', 'pattern' => '^([01][0-9]|2[0-3]):[0-5][0-9]$'],
                            'ends_at' => ['type' => 'string', 'pattern' => '^([01][0-9]|2[0-3]):[0-5][0-9]$'],
                            'room' => ['type' => ['string', 'null']],
                        ],
                    ],
                    'TimetableEntriesResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/TimetableEntryRecord']]],
                    ],
                    'TimetableEntryResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/TimetableEntryRecord']],
                    ],
                    'StoreTimetableEntryRequest' => [
                        'type' => 'object', 'required' => ['academic_year_id', 'class_id', 'weekday', 'starts_at', 'ends_at'],
                        'properties' => [
                            'academic_year_id' => ['type' => 'string', 'format' => 'uuid'], 'class_id' => ['type' => 'string', 'format' => 'uuid'],
                            'section_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                            'subject_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                            'subject' => ['type' => ['string', 'null'], 'maxLength' => 120],
                            'teacher_profile_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                            'weekday' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 7],
                            'starts_at' => ['type' => 'string', 'pattern' => '^([01][0-9]|2[0-3]):[0-5][0-9]$'],
                            'ends_at' => ['type' => 'string', 'pattern' => '^([01][0-9]|2[0-3]):[0-5][0-9]$'],
                            'room' => ['type' => ['string', 'null'], 'maxLength' => 80],
                        ],
                        'anyOf' => [
                            ['required' => ['subject_id'], 'properties' => ['subject_id' => ['type' => 'string']]],
                            ['required' => ['subject'], 'properties' => ['subject' => ['type' => 'string']]],
                        ],
                    ],
                    'TeacherProfileFields' => [
                        'type' => 'object', 'required' => ['id', 'employee_code', 'name', 'status', 'user_id', 'member_name'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'employee_code' => ['type' => 'string'],
                            'name' => ['type' => 'string'], 'status' => ['type' => 'string', 'enum' => ['active', 'inactive']],
                            'user_id' => ['type' => ['integer', 'null']], 'member_name' => ['type' => ['string', 'null']],
                        ],
                    ],
                    'TeacherProfileRecord' => [
                        'allOf' => [
                            ['$ref' => '#/components/schemas/TeacherProfileFields'],
                            ['type' => 'object', 'required' => ['is_my_profile', 'public_title', 'public_bio', 'is_public'], 'properties' => [
                                'is_my_profile' => ['type' => 'boolean'],
                                'public_title' => ['type' => ['string', 'null']], 'public_bio' => ['type' => ['string', 'null']],
                                'is_public' => ['type' => ['boolean', 'null']],
                            ]],
                        ],
                    ],
                    'TeacherProfilesResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/TeacherProfileRecord']]],
                    ],
                    'TeacherProfileResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/TeacherProfileFields']],
                    ],
                    'TeacherPublicProfileResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['allOf' => [
                            ['$ref' => '#/components/schemas/TeacherProfileFields'],
                            ['type' => 'object', 'required' => ['public_title', 'public_bio', 'is_public', 'is_my_profile'], 'properties' => [
                                'public_title' => ['type' => ['string', 'null']], 'public_bio' => ['type' => ['string', 'null']],
                                'is_public' => ['type' => 'boolean'], 'is_my_profile' => ['const' => true],
                            ]],
                        ]]],
                    ],
                    'ScheduleExceptionRecord' => [
                        'type' => 'object', 'required' => ['id', 'academic_year_id', 'exception_date', 'type', 'title', 'notes', 'class_id', 'class_name', 'section_id', 'section_name', 'timetable_entry_id', 'subject_id', 'subject', 'teacher_profile_id', 'teacher_name', 'starts_at', 'ends_at', 'room', 'status', 'cancelled_at'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'academic_year_id' => ['type' => 'string', 'format' => 'uuid'],
                            'exception_date' => ['type' => 'string', 'format' => 'date'], 'type' => ['type' => 'string', 'enum' => ['closure', 'cancel', 'extra']],
                            'title' => ['type' => 'string'], 'notes' => ['type' => ['string', 'null']], 'class_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                            'class_name' => ['type' => ['string', 'null']], 'section_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                            'section_name' => ['type' => ['string', 'null']], 'timetable_entry_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                            'subject_id' => ['type' => ['string', 'null'], 'format' => 'uuid'], 'subject' => ['type' => ['string', 'null']],
                            'teacher_profile_id' => ['type' => ['string', 'null'], 'format' => 'uuid'], 'teacher_name' => ['type' => ['string', 'null']],
                            'starts_at' => ['type' => ['string', 'null'], 'pattern' => '^([01][0-9]|2[0-3]):[0-5][0-9]$'],
                            'ends_at' => ['type' => ['string', 'null'], 'pattern' => '^([01][0-9]|2[0-3]):[0-5][0-9]$'],
                            'room' => ['type' => ['string', 'null']], 'status' => ['type' => 'string', 'enum' => ['active', 'cancelled']],
                            'cancelled_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                        ],
                    ],
                    'ScheduleExceptionsResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'maxItems' => 200, 'items' => ['$ref' => '#/components/schemas/ScheduleExceptionRecord']]],
                    ],
                    'ScheduleExceptionResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/ScheduleExceptionRecord']],
                    ],
                    'StoreScheduleExceptionRequest' => [
                        'type' => 'object', 'required' => ['academic_year_id', 'exception_date', 'type', 'title'],
                        'properties' => [
                            'academic_year_id' => ['type' => 'string', 'format' => 'uuid'], 'exception_date' => ['type' => 'string', 'format' => 'date'],
                            'type' => ['type' => 'string', 'enum' => ['closure', 'cancel', 'extra']], 'title' => ['type' => 'string', 'maxLength' => 120],
                            'notes' => ['type' => ['string', 'null'], 'maxLength' => 2000], 'class_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                            'section_id' => ['type' => ['string', 'null'], 'format' => 'uuid'], 'timetable_entry_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                            'subject_id' => ['type' => ['string', 'null'], 'format' => 'uuid'], 'teacher_profile_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                            'starts_at' => ['type' => ['string', 'null'], 'pattern' => '^([01][0-9]|2[0-3]):[0-5][0-9]$'],
                            'ends_at' => ['type' => ['string', 'null'], 'pattern' => '^([01][0-9]|2[0-3]):[0-5][0-9]$'],
                            'room' => ['type' => ['string', 'null'], 'maxLength' => 80],
                        ],
                        'description' => 'Conditional fields depend on type: closure has no class/section/timetable/subject/teacher/timing/room; cancel requires class and may reference a timetable entry; extra requires class, subject, starts_at, and ends_at.',
                    ],
                    'UpdateScheduleExceptionRequest' => [
                        'type' => 'object', 'required' => ['title', 'notes'],
                        'properties' => [
                            'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 120],
                            'notes' => ['type' => ['string', 'null'], 'maxLength' => 2000],
                        ],
                        'description' => 'Updates the display title and notes of an active dated schedule change. The date and schedule scope are immutable after creation.',
                    ],
                    'LiveSessionRecord' => [
                        'type' => 'object', 'required' => ['id', 'title', 'description', 'class_id', 'class_name', 'section_id', 'section_name', 'course_id', 'course_title', 'provider', 'starts_at', 'ends_at', 'status', 'join_available', 'recording_url'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'title' => ['type' => 'string'], 'description' => ['type' => ['string', 'null']],
                            'class_id' => ['type' => ['string', 'null'], 'format' => 'uuid'], 'class_name' => ['type' => ['string', 'null']],
                            'section_id' => ['type' => ['string', 'null'], 'format' => 'uuid'], 'section_name' => ['type' => ['string', 'null']],
                            'course_id' => ['type' => ['string', 'null'], 'format' => 'uuid'], 'course_title' => ['type' => ['string', 'null']],
                            'provider' => ['type' => 'string', 'enum' => ['zoom', 'google_meet', 'microsoft_teams', 'external']],
                            'starts_at' => ['type' => 'string', 'format' => 'date-time'], 'ends_at' => ['type' => 'string', 'format' => 'date-time'],
                            'status' => ['type' => 'string', 'enum' => ['scheduled', 'live', 'completed', 'cancelled']],
                            'join_available' => ['type' => 'boolean'], 'recording_url' => ['type' => ['string', 'null'], 'format' => 'uri'],
                        ],
                    ],
                    'LiveSessionsResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'maxItems' => 200, 'items' => ['$ref' => '#/components/schemas/LiveSessionRecord']]],
                    ],
                    'LiveSessionResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/LiveSessionRecord']],
                    ],
                    'StoreLiveSessionRequest' => [
                        'type' => 'object', 'required' => ['title', 'starts_at', 'ends_at', 'meeting_url'],
                        'properties' => [
                            'class_id' => ['type' => ['string', 'null'], 'format' => 'uuid'], 'section_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                            'course_id' => ['type' => ['string', 'null'], 'format' => 'uuid'], 'title' => ['type' => 'string', 'maxLength' => 180],
                            'description' => ['type' => ['string', 'null'], 'maxLength' => 3000], 'starts_at' => ['type' => 'string', 'format' => 'date-time'],
                            'ends_at' => ['type' => 'string', 'format' => 'date-time'], 'meeting_url' => ['type' => 'string', 'format' => 'uri', 'maxLength' => 2048, 'pattern' => '^https://'],
                        ],
                        'anyOf' => [
                            ['required' => ['class_id'], 'properties' => ['class_id' => ['type' => 'string'], 'course_id' => ['type' => 'null']]],
                            ['required' => ['course_id'], 'properties' => ['course_id' => ['type' => 'string'], 'class_id' => ['type' => 'null']]],
                        ],
                    ],
                    'UpdateLiveSessionRequest' => [
                        'type' => 'object', 'required' => ['status'],
                        'properties' => [
                            'status' => ['type' => 'string', 'enum' => ['live', 'completed', 'cancelled']],
                            'recording_url' => ['type' => ['string', 'null'], 'format' => 'uri', 'maxLength' => 2048, 'pattern' => '^https://'],
                        ],
                        'description' => 'Recording URLs may be supplied only when completing a live session.',
                    ],
                    'LiveSessionJoinResponse' => [
                        'type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['type' => 'object', 'required' => ['meeting_url', 'ends_at'], 'properties' => [
                            'meeting_url' => ['type' => 'string', 'format' => 'uri'], 'ends_at' => ['type' => 'string', 'format' => 'date-time'],
                        ]]],
                    ],
                    'LiveSessionRosterRecord' => [
                        'type' => 'object', 'required' => ['id', 'name', 'student_code'],
                        'properties' => ['id' => ['type' => 'string', 'format' => 'uuid'], 'name' => ['type' => 'string'], 'student_code' => ['type' => ['string', 'null']]],
                    ],
                    'LiveSessionRosterResponse' => [
                        'type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/LiveSessionRosterRecord']]],
                    ],
                    'RecordLiveSessionAttendanceRequest' => [
                        'type' => 'object', 'required' => ['records'], 'properties' => ['records' => ['type' => 'array', 'minItems' => 1, 'items' => [
                            'type' => 'object', 'required' => ['student_id', 'status'], 'properties' => [
                                'student_id' => ['type' => 'string', 'format' => 'uuid'], 'status' => ['type' => 'string', 'enum' => ['present', 'absent', 'late', 'excused']],
                            ],
                        ]]],
                        'description' => 'Include exactly one entry for every active student in the live-session audience roster.',
                    ],
                    'RecordLiveSessionAttendanceResponse' => [
                        'type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['type' => 'object', 'required' => ['attendance_session_id', 'live_session_id', 'session_date', 'attendance_rate', 'records_count'], 'properties' => [
                            'attendance_session_id' => ['type' => 'string', 'format' => 'uuid'], 'live_session_id' => ['type' => 'string', 'format' => 'uuid'],
                            'session_date' => ['type' => 'string', 'format' => 'date'], 'attendance_rate' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
                            'records_count' => ['type' => 'integer', 'minimum' => 1],
                        ]]],
                    ],
                    'StoreTeacherProfileRequest' => [
                        'type' => 'object', 'required' => ['employee_code', 'name'],
                        'properties' => ['employee_code' => ['type' => 'string', 'maxLength' => 64], 'name' => ['type' => 'string', 'maxLength' => 160]],
                    ],
                    'TeacherMemberCandidate' => [
                        'type' => 'object', 'required' => ['id', 'name'],
                        'properties' => ['id' => ['type' => 'integer'], 'name' => ['type' => 'string']],
                    ],
                    'TeacherMemberCandidatesResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/TeacherMemberCandidate']]],
                    ],
                    'LinkTeacherMemberRequest' => [
                        'type' => 'object', 'required' => ['user_id'],
                        'properties' => ['user_id' => ['type' => ['integer', 'null'], 'minimum' => 1]],
                    ],
                    'UpdateTeacherPublicProfileRequest' => [
                        'type' => 'object', 'required' => ['is_public'],
                        'properties' => [
                            'public_title' => ['type' => ['string', 'null'], 'maxLength' => 120],
                            'public_bio' => ['type' => ['string', 'null'], 'maxLength' => 1200], 'is_public' => ['type' => 'boolean'],
                        ],
                    ],
                    'AssignmentAttachment' => [
                        'type' => 'object', 'required' => ['id', 'name', 'mime_type', 'size_bytes'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'name' => ['type' => 'string'],
                            'mime_type' => ['type' => 'string'], 'size_bytes' => ['type' => 'integer', 'minimum' => 0],
                        ],
                    ],
                    'AssignmentSubmission' => [
                        'type' => 'object', 'required' => ['id', 'status', 'submission_text', 'submitted_at', 'score', 'feedback', 'graded_at', 'attachments'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'status' => ['type' => 'string', 'enum' => ['submitted', 'graded']],
                            'submission_text' => ['type' => ['string', 'null']], 'submitted_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                            'score' => ['type' => ['number', 'null'], 'minimum' => 0], 'feedback' => ['type' => ['string', 'null']],
                            'graded_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                            'attachments' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AssignmentAttachment']],
                        ],
                    ],
                    'AssignmentRecord' => [
                        'type' => 'object', 'required' => ['id', 'course_id', 'lesson_id', 'lesson_title', 'title', 'instructions', 'due_at', 'max_points', 'status', 'submissions_count', 'resource_attachments'],
                        'properties' => [
                            'id' => ['type' => 'string', 'format' => 'uuid'], 'course_id' => ['type' => 'string', 'format' => 'uuid'],
                            'lesson_id' => ['type' => ['string', 'null'], 'format' => 'uuid'], 'lesson_title' => ['type' => ['string', 'null']],
                            'title' => ['type' => 'string'], 'instructions' => ['type' => ['string', 'null']],
                            'due_at' => ['type' => ['string', 'null'], 'format' => 'date-time'], 'max_points' => ['type' => 'number', 'exclusiveMinimum' => 0],
                            'status' => ['type' => 'string', 'enum' => ['draft', 'published']],
                            'submissions_count' => ['type' => ['integer', 'null'], 'minimum' => 0],
                            'resource_attachments' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AssignmentAttachment']],
                            'my_submission' => ['oneOf' => [['$ref' => '#/components/schemas/AssignmentSubmission'], ['type' => 'null']]],
                        ],
                    ],
                    'AssignmentListResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AssignmentRecord']]],
                    ],
                    'AssignmentResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/AssignmentRecord']],
                    ],
                    'AssignmentSubmissionListItem' => [
                        'allOf' => [
                            ['$ref' => '#/components/schemas/AssignmentSubmission'],
                            ['type' => 'object', 'required' => ['student_name', 'student_code'], 'properties' => [
                                'student_name' => ['type' => ['string', 'null']], 'student_code' => ['type' => ['string', 'null']],
                            ]],
                        ],
                    ],
                    'AssignmentSubmissionsResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AssignmentSubmissionListItem']]],
                    ],
                    'AssignmentSubmissionResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['$ref' => '#/components/schemas/AssignmentSubmission']],
                    ],
                    'AssignmentAttachmentsResponse' => [
                        'type' => 'object', 'required' => ['data'],
                        'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AssignmentAttachment']]],
                    ],
                    'StoreAssignmentRequest' => [
                        'type' => 'object', 'required' => ['title', 'max_points'],
                        'properties' => [
                            'title' => ['type' => 'string', 'maxLength' => 180], 'instructions' => ['type' => ['string', 'null'], 'maxLength' => 10000],
                            'lesson_id' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                            'due_at' => ['type' => ['string', 'null'], 'description' => 'A date value accepted by Laravel date validation.'],
                            'max_points' => ['type' => 'number', 'minimum' => 0.01, 'maximum' => 100000],
                            'status' => ['type' => 'string', 'enum' => ['draft', 'published']],
                        ],
                    ],
                    'SubmitAssignmentRequest' => [
                        'type' => 'object', 'required' => ['submission_text'],
                        'properties' => ['submission_text' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 50000]],
                    ],
                    'SubmitAssignmentMultipartRequest' => [
                        'type' => 'object', 'properties' => [
                            'submission_text' => ['type' => 'string', 'maxLength' => 50000],
                            'attachments' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 5, 'items' => ['type' => 'string', 'format' => 'binary']],
                        ],
                        'anyOf' => [['required' => ['submission_text']], ['required' => ['attachments']]],
                    ],
                    'GradeAssignmentSubmissionRequest' => [
                        'type' => 'object', 'required' => ['score'],
                        'properties' => [
                            'score' => ['type' => 'number', 'minimum' => 0],
                            'feedback' => ['type' => ['string', 'null'], 'maxLength' => 5000],
                        ],
                    ],
                    'ApiResponse' => [
                        'type' => 'object', 'additionalProperties' => true,
                        'properties' => [
                            'data' => ['oneOf' => [
                                ['type' => 'object', 'additionalProperties' => true],
                                ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
                            ]],
                            'message' => ['type' => 'string'],
                        ],
                    ],
                    'ApiError' => [
                        'type' => 'object', 'required' => ['message'],
                        'properties' => [
                            'message' => ['type' => 'string'],
                            'errors' => ['type' => 'object', 'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']]],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function pathParameters(string $path): array
    {
        preg_match_all('/\{([^}]+)\}/', $path, $matches);
        return array_map(fn (string $name): array => [
            'name' => $name, 'in' => 'path', 'required' => true,
            'schema' => ['type' => 'string'],
        ], $matches[1]);
    }

    /** @return list<array<string, mixed>> */
    private function queryParameters(string $path, string $method): array
    {
        $parameters = match ($path) {
            '/catalog/courses' => [
                ['name' => 'q', 'required' => false, 'description' => 'Search public course titles, codes, and subjects.', 'schema' => ['type' => 'string', 'maxLength' => 100]],
                ['name' => 'page', 'required' => false, 'description' => 'One-based result page.', 'schema' => ['type' => 'integer', 'minimum' => 1]],
            ],
            '/catalog/educators' => [
                ['name' => 'q', 'required' => false, 'description' => 'Search opted-in public educator names, titles, biographies, and organization names.', 'schema' => ['type' => 'string', 'maxLength' => 100]],
                ['name' => 'page', 'required' => false, 'description' => 'One-based result page.', 'schema' => ['type' => 'integer', 'minimum' => 1]],
            ],
            '/platform/content-moderation' => [
                ['name' => 'type', 'required' => true, 'description' => 'Public listing type to review.', 'schema' => ['type' => 'string', 'enum' => ['course', 'educator']]],
                ['name' => 'q', 'required' => false, 'description' => 'Search opted-in public listing titles, profiles, and workspace names.', 'schema' => ['type' => 'string', 'maxLength' => 100]],
                ['name' => 'page', 'required' => false, 'description' => 'One-based result page.', 'schema' => ['type' => 'integer', 'minimum' => 1]],
            ],
            '/organizations/{organizationId}/fee-invoices' => [
                ['name' => 'page', 'required' => false, 'description' => 'One-based result page.', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000000]],
                ['name' => 'per_page', 'required' => false, 'description' => 'Page size (1–100; defaults to 50).', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]],
                ['name' => 'from', 'required' => false, 'description' => 'Inclusive invoice issue date in the workspace timezone (YYYY-MM-DD).', 'schema' => ['type' => ['string', 'null'], 'format' => 'date']],
                ['name' => 'to', 'required' => false, 'description' => 'Inclusive invoice issue date in the workspace timezone (YYYY-MM-DD); must not be earlier than from.', 'schema' => ['type' => ['string', 'null'], 'format' => 'date']],
            ],
            '/organizations/{organizationId}/fee-invoices/collections' => [
                ['name' => 'from', 'required' => true, 'description' => 'Inclusive payment received date in the workspace timezone (YYYY-MM-DD).', 'schema' => ['type' => 'string', 'format' => 'date']],
                ['name' => 'to', 'required' => true, 'description' => 'Inclusive payment received date in the workspace timezone (YYYY-MM-DD); must be on or after from.', 'schema' => ['type' => 'string', 'format' => 'date']],
                ['name' => 'page', 'required' => false, 'description' => 'One-based result page.', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000000]],
                ['name' => 'per_page', 'required' => false, 'description' => 'Page size (1–100; defaults to 25).', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]],
            ],
            '/organizations/{organizationId}/search' => [
                ['name' => 'q', 'required' => true, 'description' => 'Workspace search phrase (2–80 characters).', 'schema' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 80]],
            ],
            '/organizations/{organizationId}/reports/academic' => [
                ['name' => 'class_id', 'required' => false, 'description' => 'Limit results to an active class visible to the caller.', 'schema' => ['type' => 'string', 'format' => 'uuid']],
                ['name' => 'student_id', 'required' => false, 'description' => 'Limit results to a student visible to the caller.', 'schema' => ['type' => 'string', 'format' => 'uuid']],
                ['name' => 'from', 'required' => false, 'description' => 'Inclusive activity start date (YYYY-MM-DD).', 'schema' => ['type' => 'string', 'format' => 'date']],
                ['name' => 'to', 'required' => false, 'description' => 'Inclusive activity end date (YYYY-MM-DD).', 'schema' => ['type' => 'string', 'format' => 'date']],
            ],
            '/organizations/{organizationId}/courses/{courseId}/assessments/{assessmentId}/attempts' => $this->assessmentAttemptQueryParameters(),
            '/organizations/{organizationId}/courses/{courseId}/assessments/{assessmentId}/analytics' => $this->assessmentAttemptQueryParameters(),
            '/organizations/{organizationId}/courses/{courseId}/assessments/{assessmentId}/grades/export' => $this->assessmentAttemptQueryParameters(),
            '/organizations/{organizationId}/classes/{classId}/subject-offerings',
            '/organizations/{organizationId}/classes/{classId}/teachers',
            '/organizations/{organizationId}/classes/{classId}/teacher-assignment-history' => [
                ['name' => 'academic_year_id', 'required' => false, 'description' => 'Limit the query to this workspace academic year.', 'schema' => ['type' => 'string', 'format' => 'uuid']],
            ],
            '/organizations/{organizationId}/timetable-entries' => [
                ['name' => 'academic_year_id', 'required' => false, 'description' => 'Limit entries to this workspace academic year; defaults to the active year.', 'schema' => ['type' => 'string', 'format' => 'uuid']],
                ['name' => 'weekday', 'required' => false, 'description' => 'ISO weekday number, Monday=1 through Sunday=7.', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 7]],
            ],
            '/organizations/{organizationId}/schedule-exceptions' => [
                ['name' => 'academic_year_id', 'required' => false, 'description' => 'Filter exceptions to this workspace academic year; defaults to the active year.', 'schema' => ['type' => 'string', 'format' => 'uuid']],
            ],
            '/platform/organizations' => [
                ['name' => 'q', 'required' => false, 'description' => 'Organization name or slug search.', 'schema' => ['type' => 'string', 'maxLength' => 100]],
                ['name' => 'status', 'required' => false, 'description' => 'Filter by active or suspended state.', 'schema' => ['type' => 'string', 'enum' => ['active', 'suspended']]],
                ['name' => 'page', 'required' => false, 'description' => 'One-based result page.', 'schema' => ['type' => 'integer', 'minimum' => 1]],
            ],
            '/platform/audit-logs' => [
                ['name' => 'event', 'required' => false, 'description' => 'Filter by audit event name.', 'schema' => ['type' => 'string', 'maxLength' => 100]],
                ['name' => 'subject_id', 'required' => false, 'description' => 'Filter by subject identifier.', 'schema' => ['type' => 'string', 'maxLength' => 64]],
                ['name' => 'page', 'required' => false, 'description' => 'One-based result page.', 'schema' => ['type' => 'integer', 'minimum' => 1]],
            ],
            '/platform/usage/history' => [
                ['name' => 'days', 'required' => false, 'description' => 'Number of calendar days to include (7–365; defaults to 90).', 'schema' => ['type' => 'integer', 'minimum' => 7, 'maximum' => 365]],
            ],
            default => [],
        };

        if ($method !== 'get') return [];
        return array_map(fn (array $parameter): array => [
            'name' => $parameter['name'], 'in' => 'query', 'required' => $parameter['required'],
            'description' => $parameter['description'], 'schema' => $parameter['schema'] ?? ['type' => 'string'],
        ], $parameters);
    }

    /** @return list<array<string, mixed>> */
    private function assessmentAttemptQueryParameters(): array
    {
        return [
            ['name' => 'from', 'required' => false, 'description' => 'Inclusive submitted-at start date (YYYY-MM-DD).', 'schema' => ['type' => 'string', 'format' => 'date']],
            ['name' => 'to', 'required' => false, 'description' => 'Inclusive submitted-at end date (YYYY-MM-DD).', 'schema' => ['type' => 'string', 'format' => 'date']],
            ['name' => 'student_id', 'required' => false, 'description' => 'Filter to a student visible to the caller.', 'schema' => ['type' => 'string', 'format' => 'uuid']],
        ];
    }

    /** @return array<string, mixed> */
    private function responses(Route $route, string $method): array
    {
        $uri = $route->uri();
        if ($uri === 'api/v1/auth/verify-email/{id}/{hash}') {
            return ['302' => ['description' => 'Redirects the browser to the configured frontend verification landing page.', 'headers' => ['Location' => ['schema' => ['type' => 'string', 'format' => 'uri']]]], 'default' => $this->errorResponse()];
        }
        if ($uri === 'api/v1/organizations/{organizationId}/courses/{courseId}/assignments/{assignmentId}/attachments/{attachmentId}') {
            return ['200' => ['description' => 'Private assignment attachment download.', 'content' => ['application/octet-stream' => ['schema' => ['$ref' => '#/components/schemas/AssignmentAttachmentFile']]]], 'default' => $this->errorResponse()];
        }
        if ($uri === 'api/v1/health/ready') {
            return [
                '200' => ['description' => 'API and database are ready.', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ReadinessResponse']]]],
                '503' => ['description' => 'API is live but the database readiness check failed.', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ReadinessResponse']]]],
                'default' => $this->errorResponse(),
            ];
        }
        if ($uri === 'api/v1/webhooks/payments/sandbox') {
            return ['200' => ['description' => 'Payment capture processed or exact event redelivery acknowledged.', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/SandboxPaymentWebhookResponse']]]], 'default' => $this->errorResponse()];
        }
        if ($uri === 'api/v1/organizations/{organizationId}/courses/{courseId}/assessments/{assessmentId}/grades/export') {
            return [
                '200' => ['description' => 'UTF-8 CSV download with spreadsheet formula-safe text cells.', 'content' => ['text/csv' => ['schema' => ['$ref' => '#/components/schemas/AssessmentGradesCsvResponse']]]],
                'default' => $this->errorResponse(),
            ];
        }
        $responseSchema = match ($uri) {
            'api/v1/auth/login', 'api/v1/auth/logout' => $uri === 'api/v1/auth/login' ? 'AuthSessionResponse' : 'ApiMessageResponse',
            'api/v1/auth/register', 'api/v1/auth/register-invitation' => 'AuthSessionResponse',
            'api/v1/auth/forgot-password', 'api/v1/auth/reset-password', 'api/v1/auth/resend-verification' => 'ApiMessageResponse',
            'api/v1/auth/me' => 'CurrentUserResponse',
            'api/v1/health' => 'HealthResponse',
            'api/v1/auth/profile' => 'AuthProfileUpdateResponse',
            'api/v1/organizations' => $method === 'post' ? 'OrganizationResponse' : 'OrganizationListResponse',
            'api/v1/organizations/{organizationId}' => 'OrganizationResponse',
            'api/v1/organizations/{organizationId}/members' => 'OrganizationMembersResponse',
            'api/v1/organizations/{organizationId}/invitations' => $method === 'post' ? 'OrganizationInvitationCreatedResponse' : 'OrganizationInvitationListResponse',
            'api/v1/organizations/{organizationId}/invitations/{invitationId}' => 'OrganizationInvitationResponse',
            'api/v1/invitations/accept' => 'OrganizationInvitationAcceptedResponse',
            'api/v1/organizations/{organizationId}/fee-invoices' => $method === 'post' ? 'FeeInvoiceResponse' : 'FeeInvoiceListResponse',
            'api/v1/organizations/{organizationId}/fee-invoices/summary' => 'FeeInvoiceSummaryResponse',
            'api/v1/organizations/{organizationId}/fee-invoices/collections' => 'FeeCollectionsResponse',
            'api/v1/organizations/{organizationId}/fee-invoices/{invoiceId}/void' => 'FeeInvoiceVoidResponse',
            'api/v1/organizations/{organizationId}/fee-invoices/{invoiceId}/payments' => 'FeePaymentResponse',
            'api/v1/catalog/courses' => 'PublicCourseCatalogResponse',
            'api/v1/catalog/educators' => 'PublicEducatorCatalogResponse',
            'api/v1/catalog/plans' => 'PublicPlanCatalogResponse',
            'api/v1/catalog/course-orders/{orderId}' => 'CourseOrderResponse',
            'api/v1/catalog/courses/{courseId}/enroll' => 'PublicCourseEnrollmentResponse',
            'api/v1/catalog/courses/{courseId}/orders' => 'CourseOrderCheckoutResponse',
            'api/v1/organizations/{organizationId}/search' => 'WorkspaceSearchResponse',
            'api/v1/organizations/{organizationId}/dashboard' => 'WorkspaceDashboardResponse',
            'api/v1/organizations/{organizationId}/student/dashboard' => 'StudentDashboardResponse',
            'api/v1/organizations/{organizationId}/reports/academic' => 'AcademicReportResponse',
            'api/v1/organizations/{organizationId}/courses' => $method === 'post' ? 'CourseResponse' : 'CourseListResponse',
            'api/v1/organizations/{organizationId}/courses/{courseId}' => 'CourseResponse',
            'api/v1/organizations/{organizationId}/course-instructors' => 'CourseInstructorsResponse',
            'api/v1/organizations/{organizationId}/courses/{courseId}/instructors' => 'CourseResponse',
            'api/v1/organizations/{organizationId}/courses/{courseId}/lessons' => $method === 'post' ? 'LessonResponse' : 'LessonListResponse',
            'api/v1/organizations/{organizationId}/courses/{courseId}/lessons/{lessonId}' => 'LessonResponse',
            'api/v1/organizations/{organizationId}/courses/{courseId}/progress' => 'CourseProgressResponse',
            'api/v1/organizations/{organizationId}/courses/{courseId}/lessons/{lessonId}/progress' => 'LessonProgressResponse',
            'api/v1/organizations/{organizationId}/courses/{courseId}/enrollments' => $method === 'post' ? 'CourseEnrollmentResponse' : 'CourseEnrollmentsResponse',
            'api/v1/organizations/{organizationId}/student/study-plans' => $method === 'post' ? 'StudentStudyPlanResponse' : 'StudentStudyPlanListResponse',
            'api/v1/organizations/{organizationId}/student/study-plans/{planId}/complete' => 'StudentStudyPlanResponse',
            'api/v1/organizations/{organizationId}/students/member-candidates' => 'StudentMemberCandidatesResponse',
            'api/v1/organizations/{organizationId}/students/{studentId}/member' => 'StudentMemberLinkResponse',
            'api/v1/organizations/{organizationId}/guardian-candidates' => 'GuardianCandidatesResponse',
            'api/v1/organizations/{organizationId}/guardian-student-links' => $method === 'post' ? 'GuardianLinkResponse' : 'GuardianLinksResponse',
            'api/v1/organizations/{organizationId}/guardian-student-links/{linkId}' => 'GuardianLinkEndedResponse',
            'api/v1/organizations/{organizationId}/guardian/students' => 'GuardianChildrenResponse',
            'api/v1/organizations/{organizationId}/classes' => $method === 'post' ? 'ClassResponse' : 'ClassListResponse',
            'api/v1/organizations/{organizationId}/students' => $method === 'post' ? 'StudentResponse' : 'StudentListResponse',
            'api/v1/organizations/{organizationId}/classes/{classId}/enrollments' => 'ClassEnrollmentResponse',
            'api/v1/organizations/{organizationId}/attendance-sessions' => $method === 'post' ? 'AttendanceSessionResponse' : 'AttendanceSessionsResponse',
            'api/v1/organizations/{organizationId}/classes/{classId}/attendance-sessions' => 'AttendanceSessionResponse',
            'api/v1/organizations/{organizationId}/academic-years' => $method === 'post' ? 'AcademicYearResponse' : 'AcademicYearsResponse',
            'api/v1/organizations/{organizationId}/academic-years/{yearId}/activate' => 'AcademicYearResponse',
            'api/v1/organizations/{organizationId}/sections' => 'SectionsResponse',
            'api/v1/organizations/{organizationId}/classes/{classId}/sections' => 'SectionResponse',
            'api/v1/organizations/{organizationId}/subjects' => $method === 'post' ? 'SubjectResponse' : 'SubjectsResponse',
            'api/v1/organizations/{organizationId}/classes/{classId}/subject-offerings' => $method === 'post' ? 'SubjectOfferingResponse' : 'SubjectOfferingsResponse',
            'api/v1/organizations/{organizationId}/classes/{classId}/subject-offerings/{offeringId}' => 'SubjectOfferingEndedResponse',
            'api/v1/organizations/{organizationId}/classes/{classId}/teachers' => $method === 'post' ? 'ClassTeacherAssignmentResponse' : 'ClassTeacherAssignmentsResponse',
            'api/v1/organizations/{organizationId}/classes/{classId}/teacher-assignment-history' => 'ClassTeacherAssignmentHistoryResponse',
            'api/v1/organizations/{organizationId}/classes/{classId}/teachers/{assignmentId}' => $method === 'delete' ? 'ClassTeacherAssignmentEndedResponse' : 'ClassTeacherAssignmentResponse',
            'api/v1/organizations/{organizationId}/timetable-entries' => $method === 'post' ? 'TimetableEntryResponse' : 'TimetableEntriesResponse',
            'api/v1/organizations/{organizationId}/timetable-entries/{entryId}' => 'TimetableEntryResponse',
            'api/v1/organizations/{organizationId}/teachers' => $method === 'post' ? 'TeacherProfileResponse' : 'TeacherProfilesResponse',
            'api/v1/organizations/{organizationId}/teachers/member-candidates' => 'TeacherMemberCandidatesResponse',
            'api/v1/organizations/{organizationId}/teachers/{teacherId}/member' => 'TeacherProfileResponse',
            'api/v1/organizations/{organizationId}/teachers/{teacherId}/public-profile' => 'TeacherPublicProfileResponse',
            'api/v1/organizations/{organizationId}/schedule-exceptions' => $method === 'post' ? 'ScheduleExceptionResponse' : 'ScheduleExceptionsResponse',
            'api/v1/organizations/{organizationId}/schedule-exceptions/{exceptionId}' => 'ScheduleExceptionResponse',
            'api/v1/organizations/{organizationId}/schedule-exceptions/{exceptionId}/cancel' => 'ScheduleExceptionResponse',
            'api/v1/organizations/{organizationId}/live-sessions' => $method === 'post' ? 'LiveSessionResponse' : 'LiveSessionsResponse',
            'api/v1/organizations/{organizationId}/live-sessions/{sessionId}' => 'LiveSessionResponse',
            'api/v1/organizations/{organizationId}/live-sessions/{sessionId}/join' => 'LiveSessionJoinResponse',
            'api/v1/organizations/{organizationId}/live-sessions/{sessionId}/roster' => 'LiveSessionRosterResponse',
            'api/v1/organizations/{organizationId}/live-sessions/{sessionId}/attendance' => 'RecordLiveSessionAttendanceResponse',
            'api/v1/organizations/{organizationId}/courses/{courseId}/assessments' => $method === 'post' ? 'AssessmentResponse' : 'AssessmentListResponse',
            'api/v1/organizations/{organizationId}/ai/tutor' => 'AiTutorResponse',
            'api/v1/organizations/{organizationId}/ai/teacher-assistant' => 'TeacherAssistantDraftResponse',
            'api/v1/organizations/{organizationId}/announcements' => $method === 'post' ? 'AnnouncementCreatedResponse' : 'AnnouncementListResponse',
            'api/v1/organizations/{organizationId}/announcements/{announcementId}' => 'AnnouncementResponse',
            'api/v1/organizations/{organizationId}/announcements/{announcementId}/withdraw',
            'api/v1/organizations/{organizationId}/announcements/{announcementId}/restore' => 'AnnouncementResponse',
            'api/v1/organizations/{organizationId}/tasks' => $method === 'post' ? 'OrganizationTaskResponse' : 'OrganizationTaskListResponse',
            'api/v1/organizations/{organizationId}/tasks/{taskId}',
            'api/v1/organizations/{organizationId}/tasks/{taskId}/archive',
            'api/v1/organizations/{organizationId}/tasks/{taskId}/complete',
            'api/v1/organizations/{organizationId}/tasks/{taskId}/restore' => 'OrganizationTaskResponse',
            'api/v1/organizations/{organizationId}/task-assignees' => 'TaskAssigneesResponse',
            'api/v1/organizations/{organizationId}/subscription' => 'OrganizationSubscriptionResponse',
            'api/v1/organizations/{organizationId}/subscription/requests' => 'SubscriptionChangeResponse',
            'api/v1/platform/plans' => $method === 'post' ? 'SubscriptionPlanResponse' : 'SubscriptionPlanListResponse',
            'api/v1/platform/plans/{planId}' => 'SubscriptionPlanResponse',
            'api/v1/platform/feature-flags' => 'PlatformFeatureFlagsResponse',
            'api/v1/platform/feature-flags/{flag}' => 'PlatformFeatureFlagResponse',
            'api/v1/platform/organizations/{organizationId}/status' => 'PlatformOrganizationStatusResponse',
            'api/v1/platform/organizations' => 'PlatformOrganizationsResponse',
            'api/v1/platform/audit-logs' => 'PlatformAuditLogsResponse',
            'api/v1/platform/users/lookup' => 'PlatformUserLookupResponse',
            'api/v1/platform/content-moderation' => 'PlatformModeratableContentListResponse',
            'api/v1/platform/content-moderation/{type}/{contentId}' => 'PlatformModerationResponse',
            'api/v1/platform/usage' => 'PlatformUsageResponse',
            'api/v1/platform/usage/history' => 'PlatformUsageHistoryResponse',
            'api/v1/platform/subscription-requests' => 'PendingSubscriptionRequestsResponse',
            'api/v1/platform/subscription-requests/{organizationId}/resolve' => 'SubscriptionRequestResolutionResponse',
            'api/v1/organizations/{organizationId}/courses/{courseId}/questions' => $method === 'post' ? 'AssessmentQuestionResponse' : 'AssessmentQuestionListResponse',
            'api/v1/organizations/{organizationId}/courses/{courseId}/questions/import/preview' => 'QuestionImportPreviewResponse',
            'api/v1/organizations/{organizationId}/courses/{courseId}/questions/import' => 'QuestionImportResponse',
            'api/v1/organizations/{organizationId}/courses/{courseId}/assessments/{assessmentId}/attempts' => 'AssessmentAttemptsResponse',
            'api/v1/organizations/{organizationId}/courses/{courseId}/assessments/{assessmentId}/analytics' => 'AssessmentAnalyticsResponse',
            'api/v1/organizations/{organizationId}/courses/{courseId}/assessments/{assessmentId}/attempts/{attemptId}/review' => 'AssessmentAttemptReviewResponse',
            'api/v1/organizations/{organizationId}/assessments/{assessmentId}/attempts/{attemptId}/answers/{questionId}' => 'SavedAssessmentAnswerResponse',
            'api/v1/organizations/{organizationId}/courses/{courseId}/assessments/{assessmentId}/attempts/{attemptId}/answers/{questionId}/grade' => 'AssessmentAnswerGradeResponse',
            'api/v1/organizations/{organizationId}/assessments/{assessmentId}/attempts' => 'StudentAssessmentAttemptResponse',
            'api/v1/organizations/{organizationId}/assessments/{assessmentId}/attempts/{attemptId}/submit' => 'AssessmentAttemptResponse',
            'api/v1/organizations/{organizationId}/assessments/{assessmentId}/attempts/{attemptId}/result' => 'StudentAssessmentResultResponse',
            'api/v1/organizations/{organizationId}/courses/{courseId}/assignments' => $method === 'post' ? 'AssignmentResponse' : 'AssignmentListResponse',
            'api/v1/organizations/{organizationId}/courses/{courseId}/assignments/{assignmentId}/submissions' => 'AssignmentSubmissionsResponse',
            'api/v1/organizations/{organizationId}/assignments/{assignmentId}/submissions' => 'AssignmentSubmissionResponse',
            'api/v1/organizations/{organizationId}/courses/{courseId}/assignments/{assignmentId}/submissions/{submissionId}/grade' => 'AssignmentSubmissionResponse',
            'api/v1/organizations/{organizationId}/courses/{courseId}/assignments/{assignmentId}/attachments' => 'AssignmentAttachmentsResponse',
            'api/v1/organizations/{organizationId}/feedback' => $method === 'post' ? 'SupportFeedbackResponse' : 'SupportFeedbackListResponse',
            'api/v1/organizations/{organizationId}/feedback/mine' => 'SupportFeedbackMineResponse',
            'api/v1/organizations/{organizationId}/feedback/{feedbackId}' => 'SupportFeedbackManagerResponse',
            'api/v1/organizations/{organizationId}/roles' => $method === 'post' ? 'OrganizationRoleResponse' : 'OrganizationRolesIndexResponse',
            'api/v1/organizations/{organizationId}/roles/{roleId}' => $method === 'delete' ? 'OrganizationRoleArchivedResponse' : 'OrganizationRoleResponse',
            'api/v1/organizations/{organizationId}/roles/{roleId}/restore' => 'OrganizationRoleResponse',
            'api/v1/organizations/{organizationId}/roles/{roleId}/members/{userId}' => 'OrganizationRoleAssignmentResponse',
            'api/v1/organizations/{organizationId}/role-members/{userId}' => 'OrganizationRoleUnassignmentResponse',
            'api/v1/organizations/{organizationId}/notifications' => 'WorkspaceNotificationsResponse',
            'api/v1/organizations/{organizationId}/notifications/{notificationId}/read' => 'WorkspaceNotificationReadResponse',
            default => null,
        };
        if ($responseSchema !== null) {
            $statuses = match ($uri) {
                'api/v1/auth/register', 'api/v1/auth/register-invitation' => ['200', '202'],
                'api/v1/invitations/accept' => ['200'],
                'api/v1/organizations', 'api/v1/organizations/{organizationId}/invitations' => $method === 'post' ? ['201'] : ['200'],
                'api/v1/organizations/{organizationId}/fee-invoices' => $method === 'post' ? ['201'] : ['200'],
                'api/v1/organizations/{organizationId}/fee-invoices/{invoiceId}/payments' => ['201'],
                'api/v1/organizations/{organizationId}/courses/{courseId}/assessments' => $method === 'post' ? ['201'] : ['200'],
                'api/v1/catalog/courses/{courseId}/orders' => ['200', '201'],
                'api/v1/organizations/{organizationId}/announcements' => $method === 'post' ? ['201'] : ['200'],
                'api/v1/organizations/{organizationId}/tasks' => $method === 'post' ? ['201'] : ['200'],
                'api/v1/organizations/{organizationId}/subscription/requests' => ['200', '202'],
                'api/v1/platform/plans' => $method === 'post' ? ['201'] : ['200'],
                'api/v1/organizations/{organizationId}/courses/{courseId}/questions' => $method === 'post' ? ['201'] : ['200'],
                'api/v1/organizations/{organizationId}/courses/{courseId}/questions/import/preview' => ['200'],
                'api/v1/organizations/{organizationId}/courses/{courseId}/questions/import' => ['201'],
                'api/v1/organizations/{organizationId}/courses' => $method === 'post' ? ['201'] : ['200'],
                'api/v1/organizations/{organizationId}/courses/{courseId}/lessons' => $method === 'post' ? ['201'] : ['200'],
                'api/v1/organizations/{organizationId}/courses/{courseId}/enrollments' => $method === 'post' ? ['201'] : ['200'],
                'api/v1/organizations/{organizationId}/student/study-plans' => $method === 'post' ? ['201'] : ['200'],
                'api/v1/organizations/{organizationId}/guardian-student-links' => $method === 'post' ? ['200', '201'] : ['200'],
                'api/v1/organizations/{organizationId}/classes' => $method === 'post' ? ['201'] : ['200'],
                'api/v1/organizations/{organizationId}/students' => $method === 'post' ? ['201'] : ['200'],
                'api/v1/organizations/{organizationId}/classes/{classId}/enrollments' => ['201'],
                'api/v1/organizations/{organizationId}/attendance-sessions', 'api/v1/organizations/{organizationId}/classes/{classId}/attendance-sessions' => ['200', '201'],
                'api/v1/organizations/{organizationId}/academic-years' => $method === 'post' ? ['201'] : ['200'],
                'api/v1/organizations/{organizationId}/classes/{classId}/sections' => ['201'],
                'api/v1/organizations/{organizationId}/subjects' => $method === 'post' ? ['201'] : ['200'],
                'api/v1/organizations/{organizationId}/classes/{classId}/subject-offerings' => $method === 'post' ? ['201'] : ['200'],
                'api/v1/organizations/{organizationId}/classes/{classId}/teachers' => $method === 'post' ? ['201'] : ['200'],
                'api/v1/organizations/{organizationId}/timetable-entries' => $method === 'post' ? ['201'] : ['200'],
                'api/v1/organizations/{organizationId}/teachers' => $method === 'post' ? ['201'] : ['200'],
                'api/v1/organizations/{organizationId}/schedule-exceptions' => $method === 'post' ? ['201'] : ['200'],
                'api/v1/organizations/{organizationId}/schedule-exceptions/{exceptionId}/cancel' => ['200'],
                'api/v1/organizations/{organizationId}/live-sessions' => $method === 'post' ? ['201'] : ['200'],
                'api/v1/organizations/{organizationId}/courses/{courseId}/assignments' => $method === 'post' ? ['201'] : ['200'],
                'api/v1/organizations/{organizationId}/assignments/{assignmentId}/submissions' => ['201'],
                'api/v1/organizations/{organizationId}/courses/{courseId}/assignments/{assignmentId}/attachments' => ['201'],
                'api/v1/organizations/{organizationId}/feedback' => $method === 'post' ? ['201'] : ['200'],
                'api/v1/organizations/{organizationId}/roles' => $method === 'post' ? ['201'] : ['200'],
                default => ['200'],
            };
            $responses = [];
            foreach ($statuses as $status) {
                $schema = $status === '202' ? 'EmailVerificationRequiredResponse' : $responseSchema;
                $responses[$status] = [
                    'description' => $status === '202' ? 'Email verification is required before sign-in.' : 'Successful response.',
                    'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/'.$schema]]],
                ];
            }
            if ($uri === 'api/v1/organizations/{organizationId}/courses/{courseId}/questions/import') {
                $responses['422'] = [
                    'description' => 'The preview contains invalid rows; no questions were imported.',
                    'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/QuestionImportValidationErrorResponse']]],
                ];
            }
            $responses['default'] = $this->errorResponse();
            return $responses;
        }

        $successStatuses = $method === 'post' ? ['200', '201', '202'] : ['200'];
        $responses = [];
        foreach ($successStatuses as $status) {
            $responses[$status] = [
                'description' => $status === '201' ? 'Resource created.' : ($status === '202' ? 'Request accepted.' : 'Successful response.'),
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ApiResponse']]],
            ];
        }
        $responses['default'] = $this->errorResponse();
        return $responses;
    }

    /** @return array<string, mixed> */
    private function errorResponse(): array
    {
        return [
            'description' => 'Error response. Status and field errors depend on the operation.',
            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ApiError']]],
        ];
    }

    /** @return array<string, mixed> */
    private function requestContent(Route $route): array
    {
        if (in_array($route->uri(), [
            'api/v1/organizations/{organizationId}/courses/{courseId}/questions/import',
            'api/v1/organizations/{organizationId}/courses/{courseId}/questions/import/preview',
        ], true)) {
            return ['multipart/form-data' => ['schema' => [
                'type' => 'object', 'required' => ['file'],
                'properties' => ['file' => ['type' => 'string', 'format' => 'binary', 'description' => 'CSV file up to 1 MiB. Imports accept up to 100 question rows.']],
            ]]];
        }

        if (str_contains($route->uri(), '/attachments')) {
            return ['multipart/form-data' => ['schema' => [
                'type' => 'object', 'required' => ['attachments'],
                'properties' => ['attachments' => [
                    'type' => 'array', 'minItems' => 1, 'maxItems' => 5,
                    'items' => ['type' => 'string', 'format' => 'binary'],
                ]],
            ]]];
        }

        if ($route->uri() === 'api/v1/organizations/{organizationId}/assignments/{assignmentId}/submissions') {
            return [
                'application/json' => ['schema' => ['$ref' => '#/components/schemas/SubmitAssignmentRequest']],
                'multipart/form-data' => ['schema' => ['$ref' => '#/components/schemas/SubmitAssignmentMultipartRequest']],
            ];
        }

        $schema = match ($route->uri()) {
            'api/v1/webhooks/payments/sandbox' => 'SandboxPaymentWebhookRequest',
            'api/v1/auth/login' => 'AuthLoginRequest',
            'api/v1/auth/register' => 'AuthRegisterRequest',
            'api/v1/auth/register-invitation' => 'AuthInvitationRegistrationRequest',
            'api/v1/auth/forgot-password', 'api/v1/auth/resend-verification' => 'AuthEmailRequest',
            'api/v1/auth/reset-password' => 'AuthResetPasswordRequest',
            'api/v1/auth/profile' => 'UpdateAuthProfileRequest',
            'api/v1/organizations' => 'StoreOrganizationRequest',
            'api/v1/organizations/{organizationId}' => 'UpdateOrganizationRequest',
            'api/v1/organizations/{organizationId}/invitations' => 'StoreOrganizationInvitationRequest',
            'api/v1/invitations/accept' => 'AcceptOrganizationInvitationRequest',
            'api/v1/organizations/{organizationId}/fee-invoices' => 'StoreFeeInvoiceRequest',
            'api/v1/organizations/{organizationId}/fee-invoices/{invoiceId}/payments' => 'RecordFeePaymentRequest',
            'api/v1/organizations/{organizationId}/ai/tutor' => 'AskAiTutorRequest',
            'api/v1/organizations/{organizationId}/ai/teacher-assistant' => 'CreateTeacherAssistantDraftRequest',
            'api/v1/organizations/{organizationId}/announcements' => 'StoreAnnouncementRequest',
            'api/v1/organizations/{organizationId}/announcements/{announcementId}' => 'UpdateAnnouncementRequest',
            'api/v1/organizations/{organizationId}/tasks' => 'StoreOrganizationTaskRequest',
            'api/v1/organizations/{organizationId}/tasks/{taskId}' => 'UpdateOrganizationTaskRequest',
            'api/v1/organizations/{organizationId}/subscription/requests' => 'RequestSubscriptionChangeRequest',
            'api/v1/platform/organizations/{organizationId}/status' => 'UpdateOrganizationStatusRequest',
            'api/v1/platform/users/lookup' => 'PlatformUserLookupRequest',
            'api/v1/platform/content-moderation/{type}/{contentId}' => 'UpdatePlatformModerationRequest',
            'api/v1/platform/feature-flags/{flag}' => 'SetPlatformFeatureFlagRequest',
            'api/v1/platform/plans' => 'CreateSubscriptionPlanRequest',
            'api/v1/platform/plans/{planId}' => 'UpdateSubscriptionPlanRequest',
            'api/v1/platform/subscription-requests/{organizationId}/resolve' => 'ResolveSubscriptionRequest',
            'api/v1/organizations/{organizationId}/courses/{courseId}/questions' => 'StoreAssessmentQuestionRequest',
            'api/v1/organizations/{organizationId}/courses/{courseId}/assessments' => 'StoreAssessmentRequest',
            'api/v1/organizations/{organizationId}/assessments/{assessmentId}/attempts/{attemptId}/answers/{questionId}' => 'SaveAssessmentAnswerRequest',
            'api/v1/organizations/{organizationId}/courses/{courseId}/assessments/{assessmentId}/attempts/{attemptId}/answers/{questionId}/grade' => 'ManualAssessmentGradeRequest',
            'api/v1/organizations/{organizationId}/courses' => 'StoreCourseRequest',
            'api/v1/organizations/{organizationId}/courses/{courseId}' => 'UpdateCourseRequest',
            'api/v1/organizations/{organizationId}/courses/{courseId}/instructors' => 'UpdateCourseInstructorsRequest',
            'api/v1/organizations/{organizationId}/courses/{courseId}/lessons' => 'StoreLessonRequest',
            'api/v1/organizations/{organizationId}/courses/{courseId}/lessons/{lessonId}' => 'UpdateLessonRequest',
            'api/v1/organizations/{organizationId}/courses/{courseId}/lessons/{lessonId}/progress' => 'UpdateLessonProgressRequest',
            'api/v1/organizations/{organizationId}/courses/{courseId}/enrollments' => 'EnrollStudentRequest',
            'api/v1/organizations/{organizationId}/student/study-plans' => 'StoreStudentStudyPlanRequest',
            'api/v1/organizations/{organizationId}/students/{studentId}/member' => 'StudentMemberLinkRequest',
            'api/v1/organizations/{organizationId}/guardian-student-links' => 'StoreGuardianLinkRequest',
            'api/v1/organizations/{organizationId}/classes' => 'StoreClassRequest',
            'api/v1/organizations/{organizationId}/students' => 'StoreStudentRequest',
            'api/v1/organizations/{organizationId}/classes/{classId}/enrollments' => 'EnrollClassStudentRequest',
            'api/v1/organizations/{organizationId}/attendance-sessions' => 'LegacyAttendanceSessionRequest',
            'api/v1/organizations/{organizationId}/classes/{classId}/attendance-sessions' => 'MarkClassAttendanceRequest',
            'api/v1/organizations/{organizationId}/academic-years' => 'StoreAcademicYearRequest',
            'api/v1/organizations/{organizationId}/classes/{classId}/sections' => 'StoreSectionRequest',
            'api/v1/organizations/{organizationId}/subjects' => 'StoreSubjectRequest',
            'api/v1/organizations/{organizationId}/classes/{classId}/subject-offerings' => 'StoreSubjectOfferingRequest',
            'api/v1/organizations/{organizationId}/classes/{classId}/teachers' => 'StoreClassTeacherAssignmentRequest',
            'api/v1/organizations/{organizationId}/classes/{classId}/teachers/{assignmentId}' => 'UpdateClassTeacherAssignmentRequest',
            'api/v1/organizations/{organizationId}/timetable-entries' => 'StoreTimetableEntryRequest',
            'api/v1/organizations/{organizationId}/timetable-entries/{entryId}' => 'StoreTimetableEntryRequest',
            'api/v1/organizations/{organizationId}/teachers' => 'StoreTeacherProfileRequest',
            'api/v1/organizations/{organizationId}/teachers/{teacherId}/member' => 'LinkTeacherMemberRequest',
            'api/v1/organizations/{organizationId}/teachers/{teacherId}/public-profile' => 'UpdateTeacherPublicProfileRequest',
            'api/v1/organizations/{organizationId}/schedule-exceptions' => 'StoreScheduleExceptionRequest',
            'api/v1/organizations/{organizationId}/schedule-exceptions/{exceptionId}' => 'UpdateScheduleExceptionRequest',
            'api/v1/organizations/{organizationId}/live-sessions' => 'StoreLiveSessionRequest',
            'api/v1/organizations/{organizationId}/live-sessions/{sessionId}' => 'UpdateLiveSessionRequest',
            'api/v1/organizations/{organizationId}/live-sessions/{sessionId}/attendance' => 'RecordLiveSessionAttendanceRequest',
            'api/v1/organizations/{organizationId}/courses/{courseId}/assignments' => 'StoreAssignmentRequest',
            'api/v1/organizations/{organizationId}/courses/{courseId}/assignments/{assignmentId}/submissions/{submissionId}/grade' => 'GradeAssignmentSubmissionRequest',
            'api/v1/organizations/{organizationId}/feedback' => 'StoreSupportFeedbackRequest',
            'api/v1/organizations/{organizationId}/feedback/{feedbackId}' => 'UpdateSupportFeedbackRequest',
            'api/v1/organizations/{organizationId}/roles' => 'StoreOrganizationRoleRequest',
            'api/v1/organizations/{organizationId}/roles/{roleId}' => 'UpdateOrganizationRoleRequest',
            default => 'JsonObject',
        };

        return ['application/json' => ['schema' => ['$ref' => '#/components/schemas/'.$schema]]];
    }

    private function requiresBearerToken(Route $route): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'auth:sanctum')) return true;
        }
        return false;
    }

    private function acceptsJsonBody(Route $route): bool
    {
        return ! in_array($route->uri(), [
                'api/v1/auth/logout',
                'api/v1/catalog/courses/{courseId}/enroll',
                'api/v1/catalog/courses/{courseId}/orders',
                'api/v1/organizations/{organizationId}/academic-years/{yearId}/activate',
                'api/v1/organizations/{organizationId}/announcements/{announcementId}/restore',
                'api/v1/organizations/{organizationId}/announcements/{announcementId}/withdraw',
                'api/v1/organizations/{organizationId}/assessments/{assessmentId}/attempts',
                'api/v1/organizations/{organizationId}/assessments/{assessmentId}/attempts/{attemptId}/submit',
                'api/v1/organizations/{organizationId}/fee-invoices/{invoiceId}/void',
                'api/v1/organizations/{organizationId}/live-sessions/{sessionId}/join',
                'api/v1/organizations/{organizationId}/roles/{roleId}/restore',
                'api/v1/organizations/{organizationId}/roles/{roleId}/members/{userId}',
                'api/v1/organizations/{organizationId}/schedule-exceptions/{exceptionId}/cancel',
                'api/v1/organizations/{organizationId}/student/study-plans/{planId}/complete',
                'api/v1/organizations/{organizationId}/tasks/{taskId}/archive',
                'api/v1/organizations/{organizationId}/tasks/{taskId}/complete',
                'api/v1/organizations/{organizationId}/tasks/{taskId}/restore',
                'api/v1/organizations/{organizationId}/notifications/{notificationId}/read',
            ], true);
    }

    private function requestBodyRequired(Route $route): bool
    {
        return in_array($route->uri(), [
            'api/v1/auth/register',
            'api/v1/auth/register-invitation',
            'api/v1/auth/login',
            'api/v1/auth/forgot-password',
            'api/v1/auth/reset-password',
            'api/v1/auth/resend-verification',
            'api/v1/auth/profile',
            'api/v1/webhooks/payments/sandbox',
            'api/v1/organizations',
            'api/v1/organizations/{organizationId}/invitations',
            'api/v1/invitations/accept',
            'api/v1/organizations/{organizationId}/fee-invoices',
            'api/v1/organizations/{organizationId}/fee-invoices/{invoiceId}/payments',
            'api/v1/organizations/{organizationId}/courses/{courseId}/questions',
            'api/v1/organizations/{organizationId}/courses/{courseId}/questions/import',
            'api/v1/organizations/{organizationId}/courses/{courseId}/questions/import/preview',
            'api/v1/organizations/{organizationId}/courses/{courseId}/assessments',
            'api/v1/organizations/{organizationId}/assessments/{assessmentId}/attempts/{attemptId}/answers/{questionId}',
            'api/v1/organizations/{organizationId}/courses/{courseId}/assessments/{assessmentId}/attempts/{attemptId}/answers/{questionId}/grade',
            'api/v1/organizations/{organizationId}/roles',
            'api/v1/organizations/{organizationId}/roles/{roleId}',
            'api/v1/organizations/{organizationId}/courses',
            'api/v1/organizations/{organizationId}/courses/{courseId}',
            'api/v1/organizations/{organizationId}/courses/{courseId}/instructors',
            'api/v1/organizations/{organizationId}/courses/{courseId}/lessons',
            'api/v1/organizations/{organizationId}/courses/{courseId}/lessons/{lessonId}',
            'api/v1/organizations/{organizationId}/courses/{courseId}/lessons/{lessonId}/progress',
            'api/v1/organizations/{organizationId}/courses/{courseId}/enrollments',
            'api/v1/organizations/{organizationId}/student/study-plans',
            'api/v1/organizations/{organizationId}/students/{studentId}/member',
            'api/v1/organizations/{organizationId}/guardian-student-links',
            'api/v1/organizations/{organizationId}/classes',
            'api/v1/organizations/{organizationId}/students',
            'api/v1/organizations/{organizationId}/classes/{classId}/enrollments',
            'api/v1/organizations/{organizationId}/attendance-sessions',
            'api/v1/organizations/{organizationId}/classes/{classId}/attendance-sessions',
            'api/v1/organizations/{organizationId}/academic-years',
            'api/v1/organizations/{organizationId}/classes/{classId}/sections',
            'api/v1/organizations/{organizationId}/subjects',
            'api/v1/organizations/{organizationId}/classes/{classId}/subject-offerings',
            'api/v1/organizations/{organizationId}/classes/{classId}/teachers',
            'api/v1/organizations/{organizationId}/classes/{classId}/teachers/{assignmentId}',
            'api/v1/organizations/{organizationId}/timetable-entries',
            'api/v1/organizations/{organizationId}/timetable-entries/{entryId}',
            'api/v1/organizations/{organizationId}/teachers',
            'api/v1/organizations/{organizationId}/teachers/{teacherId}/member',
            'api/v1/organizations/{organizationId}/teachers/{teacherId}/public-profile',
            'api/v1/organizations/{organizationId}/schedule-exceptions',
            'api/v1/organizations/{organizationId}/schedule-exceptions/{exceptionId}',
            'api/v1/organizations/{organizationId}/live-sessions',
            'api/v1/organizations/{organizationId}/live-sessions/{sessionId}',
            'api/v1/organizations/{organizationId}/live-sessions/{sessionId}/attendance',
            'api/v1/organizations/{organizationId}/courses/{courseId}/assignments',
            'api/v1/organizations/{organizationId}/assignments/{assignmentId}/submissions',
            'api/v1/organizations/{organizationId}/courses/{courseId}/assignments/{assignmentId}/submissions/{submissionId}/grade',
            'api/v1/organizations/{organizationId}/courses/{courseId}/assignments/{assignmentId}/attachments',
            'api/v1/platform/users/lookup',
        ], true);
    }

    private function tag(Route $route): string
    {
        [$controller] = array_pad(explode('@', $route->getActionName(), 2), 1, '');
        if ($controller === 'Closure') return 'System';
        $name = class_basename($controller);
        $name = Str::before($name, 'Controller');
        return Str::headline($name) ?: 'System';
    }

    private function summary(Route $route, string $method): string
    {
        $action = $route->getActionMethod();
        $special = match ($action) {
            'login' => 'Sign in',
            'register' => 'Create account',
            'registerInvitation' => 'Accept invitation',
            'me' => 'Get current user',
            'search' => 'Search workspace',
            default => null,
        };
        if ($special !== null) return $special;

        $segments = array_values(array_filter(explode('/', preg_replace('/\{[^}]+\}/', '', $route->uri()) ?? $route->uri())));
        $resource = Str::headline((string) end($segments)) ?: 'Resource';
        if (in_array($action, ['__invoke', 'Closure', 'index', 'show', 'store', 'update', 'destroy'], true)) {
            $action = match ($action) {
                'index' => 'list', 'show' => 'get', 'store' => 'create', 'update' => 'update', 'destroy' => 'delete',
                default => $method === 'post' ? $resource : 'get',
            };
        }
        if (in_array($action, ['archive', 'complete', 'restore', 'withdraw', 'activate', 'resolve', 'revoke', 'markRead'], true)) {
            $action = Str::headline($action);
        }
        $verb = match ($action) {
            'list' => 'List', 'get' => 'Get', 'create' => 'Create', 'update' => 'Update', 'delete' => 'Delete',
            default => match ($method) {
                'get' => 'Get', 'post' => 'Perform', 'put', 'patch' => 'Update', 'delete' => 'Delete', default => strtoupper($method),
            },
        };
        $target = in_array($action, ['list', 'get', 'create', 'update', 'delete'], true) ? $resource : Str::headline($action);
        return trim($verb.' '.$target);
    }

    private function operationId(Route $route, string $method, string $path): string
    {
        $controllerClass = class_basename(Str::before($route->getActionName(), '@'));
        $controller = $controllerClass === 'Closure' ? 'System' : Str::before($controllerClass, 'Controller');
        $action = $controllerClass === 'Closure' ? '' : $route->getActionMethod();
        $raw = $method.' '.$controller.' '.$action.' '.$path;
        return Str::camel(preg_replace('/[^A-Za-z0-9]+/', ' ', $raw) ?? $raw);
    }
}
