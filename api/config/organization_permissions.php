<?php

return [
    'roles' => [
        'owner' => ['*'],
        'admin' => [
            'organization.view', 'organization.update', 'members.view', 'members.manage',
            'academic.view', 'academic.manage', 'attendance.read', 'attendance.write', 'tasks.view', 'tasks.manage', 'courses.manage',
            'assessments.manage', 'reports.view', 'live_sessions.view', 'live_sessions.manage', 'fees.view',
        ],
        'teacher' => [
            'organization.view', 'academic.view', 'attendance.read', 'attendance.write', 'courses.view',
            'tasks.view', 'tasks.manage', 'courses.manage', 'assessments.manage', 'reports.view', 'live_sessions.view', 'live_sessions.manage',
        ],
        'student' => ['organization.view', 'courses.view', 'assessments.take', 'results.view', 'attendance.read', 'live_sessions.view', 'fees.view'],
        'guardian' => ['organization.view', 'students.view', 'attendance.read', 'results.view', 'courses.view', 'fees.view'],
        'educator' => ['*'],
    ],
];
