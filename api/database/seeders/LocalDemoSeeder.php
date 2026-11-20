<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassTeacherAssignment;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\Enrollment;
use App\Models\FeeInvoice;
use App\Models\GuardianStudentLink;
use App\Models\LessonProgress;
use App\Models\Organization;
use App\Models\OrganizationClass;
use App\Models\OrganizationSubscription;
use App\Models\OrganizationTask;
use App\Models\StudentProfile;
use App\Models\SubscriptionPlan;
use App\Models\TeacherProfile;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Services\AnnouncementDelivery;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class LocalDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('PaathNexus demo data can only be seeded when APP_ENV=local.');
        }

        if (! filter_var(env('PAATHNEXUS_DEMO_SEED', false), FILTER_VALIDATE_BOOL)) {
            throw new RuntimeException('Set PAATHNEXUS_DEMO_SEED=true in api/.env to enable local demo data.');
        }

        $password = (string) env('PAATHNEXUS_DEMO_PASSWORD', '');
        Validator::make(['password' => $password], [
            'password' => ['required', 'string', Password::min(12)->letters()->mixedCase()->numbers()],
        ])->validate();

        DB::transaction(function () use ($password): void {
            $owner = $this->user('owner@demo.paathnexus.test', 'Demo Owner', $password);
            $teacher = $this->user('teacher@demo.paathnexus.test', 'Demo Teacher', $password);
            $studentUser = $this->user('student@demo.paathnexus.test', 'Demo Student', $password);
            $guardian = $this->user('guardian@demo.paathnexus.test', 'Demo Guardian', $password);

            $academy = Organization::firstOrCreate(['slug' => 'paathnexus-demo-academy'], [
                'name' => 'PaathNexus Demo Academy', 'type' => 'school', 'status' => 'active', 'locale' => 'en', 'timezone' => 'Asia/Kolkata',
            ]);
            $studio = Organization::firstOrCreate(['slug' => 'ananya-demo-learning-studio'], [
                'name' => 'Ananya Demo Learning Studio', 'type' => 'educator', 'status' => 'active', 'locale' => 'en', 'timezone' => 'Asia/Kolkata',
            ]);

            $this->membership($academy, $owner, 'owner');
            $this->membership($academy, $teacher, 'teacher');
            $this->membership($academy, $studentUser, 'student');
            $this->membership($academy, $guardian, 'guardian');
            $this->membership($studio, $owner, 'owner');

            $freePlan = SubscriptionPlan::query()->where('code', 'free')->firstOrFail();
            foreach ([$academy, $studio] as $organization) {
                OrganizationSubscription::firstOrCreate(
                    ['organization_id' => $organization->id],
                    ['plan_id' => $freePlan->id, 'status' => 'active'],
                );
            }

            $yearNumber = now('Asia/Kolkata')->year;
            if (now('Asia/Kolkata')->month < 4) $yearNumber--;
            $academicYear = AcademicYear::firstOrCreate([
                'organization_id' => $academy->id, 'name' => $yearNumber.'-'.($yearNumber + 1),
            ], [
                'starts_on' => $yearNumber.'-04-01', 'ends_on' => ($yearNumber + 1).'-03-31', 'status' => 'active',
            ]);

            $class = OrganizationClass::firstOrCreate([
                'organization_id' => $academy->id, 'name' => 'Grade 8',
            ], ['grade' => '8', 'section' => 'A', 'status' => 'active']);
            $teacherProfile = TeacherProfile::firstOrCreate([
                'organization_id' => $academy->id, 'employee_code' => 'DEMO-T-01',
            ], ['name' => 'Demo Teacher', 'user_id' => $teacher->id, 'status' => 'active']);
            ClassTeacherAssignment::firstOrCreate([
                'organization_id' => $academy->id, 'class_id' => $class->id,
                'academic_year_id' => $academicYear->id, 'section_id' => null,
            ], ['teacher_profile_id' => $teacherProfile->id, 'assigned_by' => $owner->id]);

            $student = StudentProfile::firstOrCreate([
                'organization_id' => $academy->id, 'student_code' => 'DEMO-1001',
            ], ['name' => 'Asha Rao', 'user_id' => $studentUser->id, 'status' => 'active']);
            $secondStudent = StudentProfile::firstOrCreate([
                'organization_id' => $academy->id, 'student_code' => 'DEMO-1002',
            ], ['name' => 'Kabir Mehta', 'status' => 'active']);
            foreach ([$student, $secondStudent] as $learner) {
                Enrollment::firstOrCreate([
                    'organization_id' => $academy->id, 'class_id' => $class->id, 'student_id' => $learner->id,
                ], ['status' => 'active', 'enrolled_on' => now('Asia/Kolkata')->toDateString()]);
            }

            GuardianStudentLink::firstOrCreate([
                'organization_id' => $academy->id, 'student_id' => $student->id, 'guardian_user_id' => $guardian->id,
            ], ['relationship' => 'parent', 'status' => 'active', 'linked_by' => $owner->id, 'linked_at' => now()]);

            $course = Course::firstOrCreate([
                'organization_id' => $academy->id, 'code' => 'SCI-8-DEMO',
            ], [
                'academic_year_id' => $academicYear->id, 'title' => 'Science Explorations',
                'description' => 'A synthetic demo course about ecosystems and observation.',
                'status' => 'active', 'is_public' => false, 'price_paise' => 0, 'created_by' => $teacher->id,
            ]);
            $lesson = CourseLesson::firstOrCreate([
                'organization_id' => $academy->id, 'course_id' => $course->id, 'position' => 1,
            ], [
                'title' => 'Observe a local ecosystem',
                'content' => 'Record living and non-living components in a school garden. This synthetic lesson contains no learner data.',
                'status' => 'active', 'created_by' => $teacher->id,
            ]);
            CourseLesson::firstOrCreate([
                'organization_id' => $academy->id, 'course_id' => $course->id, 'position' => 2,
            ], [
                'title' => 'Describe a food web',
                'content' => 'Explain how energy moves between producers and consumers.',
                'status' => 'active', 'created_by' => $teacher->id,
            ]);
            $course->instructors()->syncWithoutDetaching([
                $teacher->id => ['organization_id' => $academy->id, 'assigned_by' => $owner->id],
            ]);
            CourseEnrollment::firstOrCreate([
                'organization_id' => $academy->id, 'course_id' => $course->id, 'student_id' => $student->id,
            ], ['status' => 'active', 'enrolled_by' => $owner->id, 'enrolled_at' => now()]);
            LessonProgress::firstOrCreate([
                'organization_id' => $academy->id, 'lesson_id' => $lesson->id, 'student_id' => $student->id,
            ], [
                'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now(), 'updated_by' => $studentUser->id,
            ]);

            $today = now('Asia/Kolkata');
            TimetableEntry::firstOrCreate([
                'organization_id' => $academy->id, 'class_id' => $class->id,
                'academic_year_id' => $academicYear->id, 'weekday' => $today->dayOfWeekIso,
                'starts_at' => '10:00:00',
            ], [
                'subject' => 'Science', 'teacher_profile_id' => $teacherProfile->id,
                'ends_at' => '10:45:00', 'room' => 'Room 8A',
            ]);

            $attendanceSession = AttendanceSession::firstOrCreate([
                'organization_id' => $academy->id, 'class_name' => $class->name,
                'session_date' => $today->toDateString(),
            ], [
                'class_id' => $class->id, 'recorded_by' => $teacher->id, 'attendance_rate' => 50,
            ]);
            AttendanceRecord::firstOrCreate([
                'organization_id' => $academy->id, 'attendance_session_id' => $attendanceSession->id, 'student_id' => $student->id,
            ], ['recorded_by' => $teacher->id, 'status' => 'present']);
            AttendanceRecord::firstOrCreate([
                'organization_id' => $academy->id, 'attendance_session_id' => $attendanceSession->id, 'student_id' => $secondStudent->id,
            ], ['recorded_by' => $teacher->id, 'status' => 'absent']);

            OrganizationTask::firstOrCreate([
                'organization_id' => $academy->id, 'title' => 'Prepare the science observation kit',
            ], [
                'created_by' => $owner->id, 'assigned_to' => $teacher->id,
                'description' => 'Collect notebooks and magnifiers for the demonstration.',
                'due_at' => now()->addDays(2), 'status' => 'pending',
            ]);

            FeeInvoice::firstOrCreate([
                'organization_id' => $academy->id, 'invoice_number' => 'DEMO-2026-001',
            ], [
                'student_id' => $student->id, 'title' => 'Learning materials', 'description' => 'Synthetic demo invoice.',
                'amount_paise' => 45000, 'due_on' => $today->copy()->addDays(14)->toDateString(),
                'status' => 'issued', 'issued_by' => $owner->id,
            ]);

            $announcement = Announcement::firstOrCreate([
                'organization_id' => $academy->id, 'title' => 'Welcome to the demo academy',
            ], [
                'body' => 'Explore the sample timetable, course, attendance and learner portal. All records in this workspace are synthetic.',
                'audience' => 'all_members', 'status' => 'published', 'created_by' => $owner->id, 'published_at' => now(),
            ]);
            if ($announcement->wasRecentlyCreated) app(AnnouncementDelivery::class)->deliver($academy, $announcement);

            $publicCourse = Course::firstOrCreate([
                'organization_id' => $studio->id, 'code' => 'DISCOVERY-01',
            ], [
                'title' => 'Learning Through Discovery', 'description' => 'A synthetic public course for exploring course discovery.',
                'status' => 'active', 'is_public' => true, 'price_paise' => 0, 'created_by' => $owner->id,
            ]);
            CourseLesson::firstOrCreate([
                'organization_id' => $studio->id, 'course_id' => $publicCourse->id, 'position' => 1,
            ], [
                'title' => 'Start with a question',
                'content' => 'Choose an everyday observation and write down one question you would like to investigate.',
                'status' => 'active', 'created_by' => $owner->id,
            ]);
        });

        $this->command?->info('Seeded synthetic PaathNexus demo workspaces. Demo account passwords use PAATHNEXUS_DEMO_PASSWORD.');
    }

    private function user(string $email, string $name, string $password): User
    {
        return User::firstOrCreate(['email' => $email], [
            'name' => $name, 'password' => Hash::make($password), 'email_verified_at' => now(), 'account_status' => 'active',
        ]);
    }

    private function membership(Organization $organization, User $user, string $role): void
    {
        $organization->users()->syncWithoutDetaching([$user->id => [
            'role' => $role, 'status' => 'active', 'joined_at' => now(),
        ]]);
    }
}
