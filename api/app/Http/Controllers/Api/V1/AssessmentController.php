<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentQuestion;
use App\Models\AttemptAnswer;
use App\Models\Course;
use App\Models\Organization;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\StudentProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Services\TeacherCourseScope;
use App\Services\TeacherClassScope;
use App\Services\QuestionCsvImport;

class AssessmentController extends Controller
{
    public function questions(Request $request, string $organizationId, string $courseId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAssessments', $organization), 403);
        $course = $this->scopedCourses($organization, $request)->whereKey($courseId)->firstOrFail();
        $questions = Question::query()->where('organization_id', $organization->id)->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('course_id')->orWhere('course_id', $course->id))
            ->with('options:id,question_id,text,is_correct,position')->orderBy('created_at')->get()
            ->map(fn (Question $question): array => $this->questionData($question, true));
        return response()->json(['data' => $questions]);
    }

    public function storeQuestion(Request $request, string $organizationId, string $courseId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAssessments', $organization), 403);
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:5000'],
            'explanation' => ['nullable', 'string', 'max:5000'],
            'topic' => ['nullable', 'string', 'max:120'],
            'difficulty' => ['required', 'integer', 'between:1,5'],
            'type' => ['required', Rule::in(['single_choice', 'multiple_select', 'short_answer'])],
            'options' => ['exclude_if:type,short_answer', 'required', 'array', 'min:2', 'max:8'],
            'options.*.text' => ['required', 'string', 'max:1000'],
            'options.*.is_correct' => ['required', 'boolean'],
            'grading_rubric' => ['sometimes', 'array', 'min:2', 'max:10'],
            'grading_rubric.*.id' => ['required', 'uuid', 'distinct'],
            'grading_rubric.*.label' => ['required', 'string', 'max:160'],
            'grading_rubric.*.weight' => ['required', 'integer', 'between:1,100'],
        ]);
        if (isset($validated['grading_rubric'])) {
            abort_if($validated['type'] !== 'short_answer', 422, 'Rubrics can only be added to short-answer questions.');
            abort_if(array_sum(array_column($validated['grading_rubric'], 'weight')) !== 100, 422, 'Rubric criterion weights must total 100.');
        }
        $course = $this->assessmentCourses($organization, $request)->whereKey($courseId)->firstOrFail();
        $correctCount = collect($validated['options'] ?? [])->where('is_correct', true)->count();
        abort_if($validated['type'] !== 'short_answer' && ($correctCount < 1 || ($validated['type'] === 'single_choice' && $correctCount !== 1)), 422, 'Choose at least one correct option, and exactly one for single-choice questions.');

        $question = DB::transaction(function () use ($organization, $course, $validated, $request) {
            $question = Question::create([
                'organization_id' => $organization->id, 'course_id' => $course->id,
                'prompt' => $validated['prompt'], 'explanation' => $validated['explanation'] ?? null,
                'topic' => $validated['topic'] ?? null, 'difficulty' => $validated['difficulty'],
                'type' => $validated['type'], 'created_by' => $request->user()->id,
                'grading_rubric' => $validated['grading_rubric'] ?? null,
            ]);
            foreach ($validated['options'] ?? [] as $index => $option) {
                $question->options()->create([
                    'organization_id' => $organization->id,
                    'text' => $option['text'], 'is_correct' => $option['is_correct'], 'position' => $index + 1,
                ]);
            }
            return $question->load('options:id,question_id,text,is_correct,position');
        });

        return response()->json(['data' => $this->questionData($question, true)], 201);
    }

    public function previewQuestionImport(Request $request, string $organizationId, string $courseId, QuestionCsvImport $importer): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAssessments', $organization), 403);
        $course = $this->assessmentCourses($organization, $request)->whereKey($courseId)->firstOrFail();
        $request->validate(['file' => ['required', 'file', 'max:1024', 'extensions:csv']]);

        return response()->json(['data' => $importer->preview($request->file('file'))]);
    }

    public function importQuestions(Request $request, string $organizationId, string $courseId, QuestionCsvImport $importer): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAssessments', $organization), 403);
        $course = $this->assessmentCourses($organization, $request)->whereKey($courseId)->firstOrFail();
        $request->validate(['file' => ['required', 'file', 'max:1024', 'extensions:csv']]);
        $preview = $importer->preview($request->file('file'));
        if (! $preview['valid']) {
            return response()->json(['message' => 'Review and correct the CSV rows before importing.', 'data' => $preview], 422);
        }

        $questions = DB::transaction(function () use ($preview, $organization, $course, $request): array {
            $created = [];
            foreach ($preview['rows'] as $row) {
                $data = $row['question'];
                $question = Question::create([
                    'organization_id' => $organization->id,
                    'course_id' => $course->id,
                    'prompt' => $data['prompt'],
                    'topic' => $data['topic'],
                    'difficulty' => $data['difficulty'],
                    'type' => $data['type'],
                    'created_by' => $request->user()->id,
                ]);
                foreach ($data['options'] as $index => $option) {
                    $question->options()->create([
                        'organization_id' => $organization->id,
                        'text' => $option['text'],
                        'is_correct' => $option['is_correct'],
                        'position' => $index + 1,
                    ]);
                }
                $created[] = $question->load('options:id,question_id,text,is_correct,position');
            }

            return $created;
        });

        return response()->json([
            'data' => [
                'imported_count' => count($questions),
                'questions' => collect($questions)->map(fn (Question $question): array => $this->questionData($question, true)),
            ],
        ], 201);
    }

    public function assessments(Request $request, string $organizationId, string $courseId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        $canManage = $request->user()->can('manageAssessments', $organization);
        abort_unless($canManage || $request->user()->can('viewCourses', $organization), 403);
        $course = $this->assessmentCourses($organization, $request)->whereKey($courseId)->firstOrFail();
        $student = null;
        if (! $canManage) {
            $student = $this->linkedStudent($request, $organization);
            $isTeacher = app(TeacherClassScope::class)->assignedStudentIds($request->user(), $organization) !== null;
            $canManageCourse = $request->user()->can('manageCourses', $organization);
            abort_unless(($student && $course->enrollments()->where('student_id', $student->id)->where('status', 'active')->exists()) || $isTeacher || $canManageCourse, 404);
        }
        $query = Assessment::query()->where('organization_id', $organization->id)->where('course_id', $course->id)
            ->withCount('questions')->orderBy('created_at');
        if (! $canManage) $query->where('status', 'published');
        $items = $query->get()->map(function (Assessment $assessment) use ($student): array {
            $data = $this->assessmentData($assessment);
            if ($student) {
                $data['my_attempts'] = $assessment->attempts()->where('student_id', $student->id)
                    ->orderBy('attempt_number')->get(['id', 'attempt_number', 'status', 'score', 'max_points', 'started_at', 'submitted_at'])
                    ->map(fn (AssessmentAttempt $attempt): array => $this->attemptSummary($attempt));
            }
            return $data;
        });
        return response()->json(['data' => $items]);
    }

    public function storeAssessment(Request $request, string $organizationId, string $courseId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAssessments', $organization), 403);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'duration_minutes' => ['nullable', 'integer', 'between:1,360'],
            'attempts_allowed' => ['required', 'integer', 'between:1,10'],
            'randomize_questions' => ['sometimes', 'boolean'],
            'randomized_question_count' => ['nullable', 'integer', 'between:1,100'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', ...($request->filled('opens_at') ? ['after:opens_at'] : [])],
            'status' => ['sometimes', Rule::in(['draft', 'published'])],
            'questions' => ['required', 'array', 'min:1', 'max:100'],
            'questions.*.question_id' => ['required', 'uuid', 'distinct'],
            'questions.*.points' => ['required', 'numeric', 'min:0.01', 'max:100000'],
        ]);
        $course = $this->assessmentCourses($organization, $request)->whereKey($courseId)->firstOrFail();
        $questionIds = collect($validated['questions'])->pluck('question_id');
        abort_if(($validated['randomized_question_count'] ?? null) > $questionIds->count(), 422, 'The per-attempt question count cannot exceed the selected question pool.');
        $availableCount = Question::query()->where('organization_id', $organization->id)->where('status', 'active')
            ->whereIn('id', $questionIds)->where(fn ($query) => $query->whereNull('course_id')->orWhere('course_id', $course->id))->count();
        abort_unless($availableCount === $questionIds->count(), 404);

        $assessment = DB::transaction(function () use ($organization, $course, $validated, $request) {
            $status = $validated['status'] ?? 'published';
            $assessment = Assessment::create([
                'organization_id' => $organization->id, 'course_id' => $course->id,
                'title' => $validated['title'], 'instructions' => $validated['instructions'] ?? null,
                'duration_minutes' => $validated['duration_minutes'] ?? null,
                'attempts_allowed' => $validated['attempts_allowed'],
                'randomize_questions' => $validated['randomize_questions'] ?? false, 'status' => $status,
                'randomized_question_count' => $validated['randomized_question_count'] ?? null,
                'opens_at' => $validated['opens_at'] ?? null,
                'closes_at' => $validated['closes_at'] ?? null,
                'published_at' => $status === 'published' ? now() : null,
                'created_by' => $request->user()->id,
            ]);
            foreach ($validated['questions'] as $index => $question) {
                AssessmentQuestion::create([
                    'organization_id' => $organization->id, 'course_id' => $course->id,
                    'assessment_id' => $assessment->id, 'question_id' => $question['question_id'],
                    'points' => $question['points'], 'position' => $index + 1,
                ]);
            }
            return $assessment->loadCount('questions');
        });

        return response()->json(['data' => $this->assessmentData($assessment)], 201);
    }

    public function startAttempt(Request $request, string $organizationId, string $assessmentId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('viewCourses', $organization), 403);
        abort_unless($request->user()->can('takeAssessments', $organization), 403);
        $assessment = Assessment::query()->where('organization_id', $organization->id)->where('status', 'published')
            ->with(['questions.question.options'])->whereKey($assessmentId)->firstOrFail();
        $student = $this->linkedStudent($request, $organization);
        abort_unless($student && $assessment->course->enrollments()->where('student_id', $student->id)->where('status', 'active')->exists(), 404);

        $attempt = DB::transaction(function () use ($assessment, $student, $organization): AssessmentAttempt {
            $assessment->newQuery()->whereKey($assessment->id)->lockForUpdate()->firstOrFail();
            $openAttempt = $assessment->attempts()->where('student_id', $student->id)->where('status', 'in_progress')->first();
            if ($openAttempt) {
                if ($openAttempt->expires_at && $openAttempt->expires_at->isPast()) {
                    $openAttempt->forceFill(['status' => 'expired', 'submitted_at' => now()])->save();
                } else {
                    return $openAttempt;
                }
            }
            Course::query()->where('organization_id', $organization->id)->whereKey($assessment->course_id)
                ->where('status', 'active')->lockForUpdate()->firstOrFail();
            $attemptCount = $assessment->attempts()->where('student_id', $student->id)->count();
            abort_if($assessment->opens_at && now()->lt($assessment->opens_at), 422, 'This assessment is not open yet.');
            abort_if($assessment->closes_at && now()->gte($assessment->closes_at), 422, 'The assessment window has closed.');
            abort_if($attemptCount >= $assessment->attempts_allowed, 422, 'No attempts remain for this assessment.');
            $startedAt = now();
            $questionPool = $assessment->questions->shuffle();
            if ($assessment->randomized_question_count) {
                $questionPool = $questionPool->take($assessment->randomized_question_count);
            }
            $questionOrder = $questionPool->pluck('question_id')->values()->all();
            if (! $assessment->randomize_questions && ! $assessment->randomized_question_count) {
                $questionOrder = $assessment->questions->pluck('question_id')->values()->all();
            } elseif (! $assessment->randomize_questions && $assessment->randomized_question_count) {
                $questionOrder = $assessment->questions->whereIn('question_id', $questionOrder)->pluck('question_id')->values()->all();
            }
            $maxPoints = (float) $assessment->questions->whereIn('question_id', $questionOrder)->sum(fn (AssessmentQuestion $item): float => (float) $item->points);
            return $assessment->attempts()->create([
                'organization_id' => $organization->id, 'course_id' => $assessment->course_id,
                'student_id' => $student->id, 'attempt_number' => $attemptCount + 1,
                'status' => 'in_progress', 'max_points' => $maxPoints, 'started_at' => $startedAt,
                'question_order' => $questionOrder,
                'expires_at' => $assessment->duration_minutes ? $startedAt->copy()->addMinutes($assessment->duration_minutes) : null,
            ]);
        });
        $attempt->load('answers');

        return response()->json(['data' => [
            'id' => $attempt->id, 'status' => $attempt->status, 'attempt_number' => $attempt->attempt_number,
            'started_at' => $attempt->started_at?->toIso8601String(), 'expires_at' => $attempt->expires_at?->toIso8601String(),
            'questions' => collect($attempt->question_order ?? $assessment->questions->pluck('question_id')->all())
                ->map(fn (string $questionId) => $assessment->questions->firstWhere('question_id', $questionId))
                ->filter()->values()->map(function (AssessmentQuestion $item) use ($attempt): array {
                $answer = $attempt->answers->firstWhere('question_id', $item->question_id);
                return [
                    'id' => $item->question_id, 'prompt' => $item->question->prompt,
                    'type' => $item->question->type, 'points' => (float) $item->points,
                    'options' => $item->question->options->map(fn (QuestionOption $option): array => ['id' => $option->id, 'text' => $option->text]),
                    'selected_option_ids' => $answer?->selected_option_ids ?? [],
                    'response_text' => $answer?->response_text,
                ];
            }),
        ]]);
    }

    public function saveAnswer(Request $request, string $organizationId, string $assessmentId, string $attemptId, string $questionId): JsonResponse
    {
        [$organization, $assessment, $attempt, $student] = $this->ownedAttempt($request, $organizationId, $assessmentId, $attemptId);
        abort_unless($request->user()->can('takeAssessments', $organization), 403);
        $attemptQuestionIds = $attempt->question_order ?? $assessment->questions()->pluck('question_id')->all();
        $assessmentQuestion = $assessment->questions()->whereIn('question_id', $attemptQuestionIds)->where('question_id', $questionId)->with('question.options')->firstOrFail();
        if ($assessmentQuestion->question->type === 'short_answer') {
            $validated = $request->validate(['response_text' => ['present', 'nullable', 'string', 'max:50000']]);
            $answerAttributes = [
                'course_id' => $assessment->course_id, 'assessment_id' => $assessment->id,
                'selected_option_ids' => [], 'response_text' => trim($validated['response_text'] ?? ''),
                'is_correct' => null, 'points_awarded' => 0, 'grader_feedback' => null,
                'graded_by' => null, 'graded_at' => null,
            ];
        } else {
            $validated = $request->validate(['selected_option_ids' => ['present', 'array', 'max:8'], 'selected_option_ids.*' => ['required', 'uuid', 'distinct']]);
            $optionIds = $validated['selected_option_ids'];
            $validOptionIds = $assessmentQuestion->question->options()->whereIn('id', $optionIds)->pluck('id')->all();
            abort_unless(count($validOptionIds) === count($optionIds), 422, 'One or more selected options do not belong to this question.');
            if ($assessmentQuestion->question->type === 'single_choice') {
                abort_unless(count($optionIds) === 1, 422, 'Select exactly one option for this question.');
            }
            $answerAttributes = [
                'course_id' => $assessment->course_id, 'assessment_id' => $assessment->id,
                'selected_option_ids' => array_values($optionIds), 'response_text' => null,
                'is_correct' => null, 'points_awarded' => 0, 'grader_feedback' => null,
                'graded_by' => null, 'graded_at' => null,
            ];
        }
        $answer = DB::transaction(function () use ($organization, $assessment, $attempt, $student, $assessmentQuestion, $answerAttributes): AttemptAnswer {
            $lockedAttempt = AssessmentAttempt::query()->where('organization_id', $organization->id)
                ->where('assessment_id', $assessment->id)->where('student_id', $student->id)
                ->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedAttempt->status === 'in_progress', 422, 'This attempt is no longer open.');
            abort_if($lockedAttempt->expires_at && $lockedAttempt->expires_at->isPast(), 422, 'The assessment time limit has expired.');

            return AttemptAnswer::updateOrCreate(
                ['organization_id' => $organization->id, 'attempt_id' => $lockedAttempt->id, 'question_id' => $assessmentQuestion->question_id],
                $answerAttributes,
            );
        });

        if ($assessmentQuestion->question->type === 'short_answer') {
            return response()->json(['data' => ['question_id' => $questionId, 'saved' => true, 'response_text' => $answer->response_text]]);
        }

        return response()->json(['data' => ['question_id' => $questionId, 'saved' => true, 'selected_option_ids' => $answer->selected_option_ids]]);
    }

    public function submitAttempt(Request $request, string $organizationId, string $assessmentId, string $attemptId): JsonResponse
    {
        [$organization, $assessment, $attempt] = $this->ownedAttempt($request, $organizationId, $assessmentId, $attemptId);
        abort_unless($request->user()->can('takeAssessments', $organization), 403);
        abort_unless($attempt->status === 'in_progress', 422, 'This attempt has already been submitted.');
        $score = 0.0;
        $maxPoints = 0.0;
        $needsReview = false;
        $attempt = DB::transaction(function () use ($assessment, $attempt, &$score, &$maxPoints, &$needsReview): AssessmentAttempt {
            $attempt = AssessmentAttempt::query()->where('organization_id', $attempt->organization_id)
                ->where('assessment_id', $assessment->id)->where('student_id', $attempt->student_id)
                ->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            abort_unless($attempt->status === 'in_progress', 422, 'This attempt has already been submitted.');
            $attemptQuestionIds = $attempt->question_order ?? $assessment->questions()->pluck('question_id')->all();
            foreach ($assessment->questions()->whereIn('question_id', $attemptQuestionIds)->with('question.options')->get() as $item) {
                $maxPoints += (float) $item->points;
                $answer = $attempt->answers()->where('question_id', $item->question_id)->first();
                if ($item->question->type === 'short_answer') {
                    $needsReview = true;
                    if (! $answer) {
                        $answer = new AttemptAnswer([
                            'organization_id' => $attempt->organization_id, 'course_id' => $attempt->course_id,
                            'assessment_id' => $assessment->id, 'attempt_id' => $attempt->id,
                            'question_id' => $item->question_id, 'selected_option_ids' => [],
                        ]);
                    }
                    $answer->forceFill(['is_correct' => null, 'points_awarded' => 0])->save();
                    continue;
                }
                $selected = collect($answer?->selected_option_ids ?? [])->sort()->values()->all();
                $correct = $item->question->options->where('is_correct', true)->pluck('id')->sort()->values()->all();
                $isCorrect = $selected !== [] && $selected === $correct;
                $points = $isCorrect ? (float) $item->points : 0.0;
                $score += $points;
                if (! $answer) {
                    $answer = new AttemptAnswer([
                        'organization_id' => $attempt->organization_id, 'course_id' => $attempt->course_id,
                        'assessment_id' => $assessment->id, 'attempt_id' => $attempt->id,
                        'question_id' => $item->question_id, 'selected_option_ids' => [],
                    ]);
                }
                $answer->forceFill(['is_correct' => $isCorrect, 'points_awarded' => $points])->save();
            }
            $status = $attempt->expires_at && $attempt->expires_at->isPast() ? 'expired' : 'submitted';
            $attempt->forceFill([
                'status' => $status, 'score' => round($score, 2), 'max_points' => round($maxPoints, 2), 'submitted_at' => now(),
                'review_status' => $needsReview ? 'pending' : 'not_required',
            ])->save();

            return $attempt;
        });
        $attempt->refresh();

        return response()->json(['data' => $this->attemptSummary($attempt)]);
    }

    public function attempts(Request $request, string $organizationId, string $courseId, string $assessmentId): JsonResponse
    {
        $filters = $this->attemptFilters($request);
        $organization = $this->memberOrganization($request, $organizationId);
        $canManage = $request->user()->can('manageAssessments', $organization);
        abort_unless($canManage || $request->user()->can('viewCourses', $organization), 403);
        abort_unless($canManage || $request->user()->can('viewResults', $organization), 403);
        $course = $this->assessmentCourses($organization, $request)->whereKey($courseId)->firstOrFail();
        $assessmentRecord = Assessment::query()->where('organization_id', $organization->id)->where('course_id', $course->id)->whereKey($assessmentId)->firstOrFail();
        abort_unless($canManage || $assessmentRecord->status === 'published', 404);
        $query = $assessmentRecord->attempts()->with('student:id,student_code,name')->orderBy('student_id')->orderBy('attempt_number');
        $this->applyAttemptFilters($query, $filters);
        if ($canManage && ($studentIds = $this->scopedTeacherStudentIds($organization, $request)) !== null) {
            $query->whereIn('student_id', $studentIds);
        }
        if (! $canManage) {
            $student = $this->linkedStudent($request, $organization);
            abort_unless($student && $course->enrollments()->where('student_id', $student->id)->where('status', 'active')->exists(), 404);
            $query->where('student_id', $student->id);
        }
        return response()->json(['data' => $query->get()->map(fn (AssessmentAttempt $attempt): array => [
            ...$this->attemptSummary($attempt), 'student_id' => $canManage ? $attempt->student_id : null,
            'student_name' => $canManage ? $attempt->student?->name : null,
            'student_code' => $canManage ? $attempt->student?->student_code : null,
        ])]);
    }

    public function exportGrades(Request $request, string $organizationId, string $courseId, string $assessmentId)
    {
        $filters = $this->attemptFilters($request);
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAssessments', $organization), 403);
        $course = $this->assessmentCourses($organization, $request)->whereKey($courseId)->firstOrFail();
        $assessment = Assessment::query()->where('organization_id', $organization->id)
            ->where('course_id', $course->id)->whereKey($assessmentId)->firstOrFail();
        $query = $assessment->attempts()->with('student:id,student_code,name')->orderBy('student_id')->orderBy('attempt_number');
        $this->applyAttemptFilters($query, $filters);
        if (($studentIds = $this->scopedTeacherStudentIds($organization, $request)) !== null) {
            $query->whereIn('student_id', $studentIds);
        }

        $output = fopen('php://temp', 'r+');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Assessment', 'Student code', 'Student name', 'Attempt', 'Status', 'Review status', 'Score', 'Maximum points', 'Score percent', 'Submitted at']);
            $safe = static function (?string $value): string {
                $value ??= '';
                return preg_match('/^\s*[=+@\-]/u', $value) ? "'".$value : $value;
            };
            foreach ($query->cursor() as $attempt) {
                $score = $attempt->score === null ? null : (float) $attempt->score;
                $max = (float) $attempt->max_points;
                $row = [
                    $assessment->title, $attempt->student?->student_code, $attempt->student?->name,
                    $attempt->attempt_number, $attempt->status, $attempt->review_status,
                    $score === null ? null : number_format($score, 2, '.', ''), number_format($max, 2, '.', ''),
                    $score === null || $max <= 0 ? null : number_format($score / $max * 100, 2, '.', ''),
                    $attempt->submitted_at?->toIso8601String(),
                ];
                fputcsv($output, array_map(fn ($value): string => $safe($value === null ? null : (string) $value), $row));
            }
        rewind($output);
        $csv = stream_get_contents($output);
            fclose($output);
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=assessment-grades.csv',
        ]);
    }

    public function analytics(Request $request, string $organizationId, string $courseId, string $assessmentId): JsonResponse
    {
        $filters = $this->attemptFilters($request);
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAssessments', $organization), 403);
        $course = $this->assessmentCourses($organization, $request)->whereKey($courseId)->firstOrFail();
        $assessment = Assessment::query()->where('organization_id', $organization->id)
            ->where('course_id', $course->id)->whereKey($assessmentId)->firstOrFail();
        $attemptQuery = $assessment->attempts();
        $this->applyAttemptFilters($attemptQuery, $filters);
        if (($studentIds = $this->scopedTeacherStudentIds($organization, $request)) !== null) {
            $attemptQuery->whereIn('student_id', $studentIds);
        }
        $attemptCount = (clone $attemptQuery)->count();
        $completedAttempts = (clone $attemptQuery)->whereIn('status', ['submitted', 'expired'])
            ->whereNotNull('submitted_at')->get(['id', 'score', 'max_points', 'review_status']);
        $attemptIds = $completedAttempts->pluck('id');
        $answers = $attemptIds->isEmpty()
            ? collect()
            : AttemptAnswer::query()->where('organization_id', $organization->id)->where('assessment_id', $assessment->id)
                ->whereIn('attempt_id', $attemptIds)->whereNotNull('is_correct')->get(['question_id', 'is_correct'])->groupBy('question_id');
        $questions = $assessment->questions()->with('question:id,prompt,type')->get()->map(function (AssessmentQuestion $item) use ($answers): array {
            $questionAnswers = $answers->get($item->question_id, collect());
            $count = $questionAnswers->count();
            $correct = $questionAnswers->where('is_correct', true)->count();

            return [
                'question_id' => $item->question_id,
                'prompt' => $item->question->prompt,
                'type' => $item->question->type,
                'points' => (float) $item->points,
                'attempts_presented' => $count,
                'correct_count' => $correct,
                'accuracy_percent' => $count > 0 ? round(($correct / $count) * 100, 2) : null,
            ];
        });
        $reviewedAttempts = $completedAttempts->where('review_status', '!=', 'pending');
        $averageScorePercent = $reviewedAttempts->isEmpty() ? null : round($reviewedAttempts->avg(
            fn (AssessmentAttempt $attempt): float => $attempt->max_points > 0 ? ((float) $attempt->score / (float) $attempt->max_points) * 100 : 0,
        ), 2);

        return response()->json(['data' => [
            'assessment_id' => $assessment->id,
            'question_pool_count' => $questions->count(),
            'randomized_question_count' => $assessment->randomized_question_count,
            'filters' => ['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null, 'student_id' => $filters['student_id'] ?? null],
            'attempt_count' => $attemptCount,
            'completed_attempt_count' => $completedAttempts->count(),
            'average_score_percent' => $averageScorePercent,
            'question_analytics' => $questions,
        ]]);
    }

    public function reviewAttempt(Request $request, string $organizationId, string $courseId, string $assessmentId, string $attemptId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAssessments', $organization), 403);
        $course = $this->assessmentCourses($organization, $request)->whereKey($courseId)->firstOrFail();
        $assessment = Assessment::query()->where('organization_id', $organization->id)
            ->where('course_id', $course->id)->whereKey($assessmentId)->firstOrFail();
        $attemptQuery = $assessment->attempts()->whereKey($attemptId);
        if (($studentIds = $this->scopedTeacherStudentIds($organization, $request)) !== null) {
            $attemptQuery->whereIn('student_id', $studentIds);
        }
        $attempt = $attemptQuery->with('student:id,student_code,name', 'answers')->firstOrFail();
        abort_unless(in_array($attempt->status, ['submitted', 'expired'], true), 404);

        $questionOrder = $attempt->question_order ?? $assessment->questions()->pluck('question_id')->all();
        $questionMap = $assessment->questions()->whereIn('question_id', $questionOrder)
            ->with('question.options')->get()->keyBy('question_id');
        $questions = collect($questionOrder)->map(function (string $questionId) use ($questionMap, $attempt): ?array {
            $item = $questionMap->get($questionId);
            if (! $item) return null;
            $answer = $attempt->answers->firstWhere('question_id', $questionId);
            $selectedIds = $answer?->selected_option_ids ?? [];

            return [
                'question_id' => $questionId,
                'prompt' => $item->question->prompt,
                'type' => $item->question->type,
                'points' => (float) $item->points,
                'points_awarded' => $answer ? (float) $answer->points_awarded : 0.0,
                'is_correct' => $answer?->is_correct,
                'response_text' => $answer?->response_text,
                'grader_feedback' => $answer?->grader_feedback,
                'graded_at' => $answer?->graded_at?->toIso8601String(),
                'grading_rubric' => $item->question->grading_rubric,
                'rubric_scores' => $answer?->rubric_scores,
                'selected_options' => $item->question->options->whereIn('id', $selectedIds)->map(fn (QuestionOption $option): array => [
                    'id' => $option->id, 'text' => $option->text,
                ])->values(),
                'correct_options' => $item->question->options->where('is_correct', true)->map(fn (QuestionOption $option): array => [
                    'id' => $option->id, 'text' => $option->text,
                ])->values(),
            ];
        })->filter()->values();

        return response()->json(['data' => [
            'attempt' => [
                ...$this->attemptSummary($attempt),
                'student_name' => $attempt->student?->name,
                'student_code' => $attempt->student?->student_code,
            ],
            'questions' => $questions,
        ]]);
    }

    public function gradeShortAnswer(Request $request, string $organizationId, string $courseId, string $assessmentId, string $attemptId, string $questionId): JsonResponse
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('manageAssessments', $organization), 403);
        $course = $this->assessmentCourses($organization, $request)->whereKey($courseId)->firstOrFail();
        $assessment = Assessment::query()->where('organization_id', $organization->id)->where('course_id', $course->id)->whereKey($assessmentId)->firstOrFail();
        $attemptQuery = $assessment->attempts()->whereKey($attemptId);
        if (($studentIds = $this->scopedTeacherStudentIds($organization, $request)) !== null) {
            $attemptQuery->whereIn('student_id', $studentIds);
        }
        $attempt = $attemptQuery->firstOrFail();
        abort_unless(in_array($attempt->status, ['submitted', 'expired'], true), 422, 'Only submitted attempts can be graded.');
        $questionOrder = $attempt->question_order ?? $assessment->questions()->pluck('question_id')->all();
        $item = $assessment->questions()->whereIn('question_id', $questionOrder)->where('question_id', $questionId)
            ->with('question:id,type,grading_rubric')->firstOrFail();
        abort_unless($item->question->type === 'short_answer', 422, 'Only short-answer responses can be graded manually.');
        $rubric = $item->question->grading_rubric;
        $validated = $request->validate([
            'points_awarded' => [$rubric ? 'prohibited' : 'required', 'numeric', 'min:0', 'max:'.$item->points],
            'rubric_scores' => [$rubric ? 'required' : 'prohibited', 'array', 'size:'.count($rubric ?? [])],
            'rubric_scores.*.criterion_id' => ['required', 'uuid', 'distinct'],
            'rubric_scores.*.score' => ['required', 'numeric', 'between:0,100'],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ]);
        $criterionScores = null;
        if ($rubric) {
            $scoresById = collect($validated['rubric_scores'])->keyBy('criterion_id');
            abort_if($scoresById->count() !== count($rubric), 422, 'Score every rubric criterion exactly once.');
            foreach ($rubric as $criterion) {
                abort_unless($scoresById->has($criterion['id']), 422, 'Score every rubric criterion exactly once.');
            }
            $criterionScores = collect($rubric)->map(fn (array $criterion): array => [
                'criterion_id' => $criterion['id'],
                'score' => (float) $scoresById[$criterion['id']]['score'],
            ])->all();
            $weightedPercent = collect($rubric)->sum(fn (array $criterion): float =>
                (float) $criterion['weight'] * ((float) $scoresById[$criterion['id']]['score'])
            ) / 100;
            $validated['points_awarded'] = round((float) $item->points * $weightedPercent / 100, 2);
        }

        $updated = DB::transaction(function () use ($assessment, $attempt, $item, $questionOrder, $validated, $criterionScores, $request): array {
            $attempt = AssessmentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $answer = AttemptAnswer::query()->where('attempt_id', $attempt->id)->where('question_id', $item->question_id)->lockForUpdate()->firstOrFail();
            $points = (float) $validated['points_awarded'];
            $answer->forceFill([
                'points_awarded' => $points,
                'is_correct' => $points >= (float) $item->points,
                'grader_feedback' => $validated['feedback'] ?? null,
                'rubric_scores' => $criterionScores,
                'graded_by' => $request->user()->id,
                'graded_at' => now(),
            ])->save();
            $score = (float) $attempt->answers()->whereIn('question_id', $questionOrder)->sum('points_awarded');
            $maxPoints = (float) $assessment->questions()->whereIn('question_id', $questionOrder)->sum('points');
            $shortQuestionIds = $assessment->questions()->whereIn('question_id', $questionOrder)
                ->whereHas('question', fn ($query) => $query->where('type', 'short_answer'))->pluck('question_id');
            $pendingAnswers = $attempt->answers()->whereIn('question_id', $shortQuestionIds)->whereNull('graded_at')->exists();
            $attempt->forceFill([
                'score' => round($score, 2), 'max_points' => round($maxPoints, 2),
                'review_status' => $pendingAnswers ? 'pending' : 'reviewed',
            ])->save();

            return ['points_awarded' => $points, 'score' => round($score, 2), 'max_points' => round($maxPoints, 2), 'review_status' => $attempt->review_status];
        });

        return response()->json(['data' => [
            'question_id' => $questionId,
            'graded' => true,
            ...$updated,
        ]]);
    }

    public function attemptResult(Request $request, string $organizationId, string $assessmentId, string $attemptId): JsonResponse
    {
        [$organization, $assessment, $attempt] = $this->ownedAttempt($request, $organizationId, $assessmentId, $attemptId);
        abort_unless($request->user()->can('viewResults', $organization), 403);
        abort_unless(in_array($attempt->status, ['submitted', 'expired'], true), 404);
        $attempt->load('answers');
        $questionOrder = $attempt->question_order ?? $assessment->questions()->pluck('question_id')->all();
        $questionMap = $assessment->questions()->whereIn('question_id', $questionOrder)
            ->with('question.options')->get()->keyBy('question_id');
        $answers = collect($questionOrder)->map(function (string $questionId) use ($questionMap, $attempt): ?array {
            $item = $questionMap->get($questionId);
            if (! $item) return null;
            $answer = $attempt->answers->firstWhere('question_id', $questionId);
            $selectedIds = $answer?->selected_option_ids ?? [];

            return [
                'question_id' => $questionId,
                'prompt' => $item->question->prompt,
                'type' => $item->question->type,
                'points' => (float) $item->points,
                'points_awarded' => $answer ? (float) $answer->points_awarded : 0.0,
                'response_text' => $answer?->response_text,
                'selected_options' => $item->question->options->whereIn('id', $selectedIds)->map(fn (QuestionOption $option): array => [
                    'id' => $option->id, 'text' => $option->text,
                ])->values(),
                'grader_feedback' => $answer?->graded_at ? $answer->grader_feedback : null,
                'pending_review' => $item->question->type === 'short_answer' && ! $answer?->graded_at,
            ];
        })->filter()->values();

        return response()->json(['data' => ['attempt' => $this->attemptSummary($attempt), 'answers' => $answers]]);
    }

    private function ownedAttempt(Request $request, string $organizationId, string $assessmentId, string $attemptId): array
    {
        $organization = $this->memberOrganization($request, $organizationId);
        abort_unless($request->user()->can('viewCourses', $organization), 403);
        $assessment = Assessment::query()->where('organization_id', $organization->id)->where('status', 'published')->whereKey($assessmentId)->firstOrFail();
        $student = $this->linkedStudent($request, $organization);
        abort_unless($student && $assessment->course->enrollments()->where('student_id', $student->id)->where('status', 'active')->exists(), 404);
        $attempt = $assessment->attempts()->where('student_id', $student->id)->whereKey($attemptId)->firstOrFail();
        return [$organization, $assessment, $attempt, $student];
    }

    private function memberOrganization(Request $request, string $organizationId): Organization
    {
        return $request->user()->organizations()->wherePivot('status', 'active')
            ->where('organizations.status', 'active')->where('organizations.id', $organizationId)->firstOrFail();
    }

    private function attemptFilters(Request $request): array
    {
        $filters = $request->validate([
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'student_id' => ['sometimes', 'nullable', 'uuid'],
        ]);
        abort_if(! empty($filters['from']) && ! empty($filters['to']) && $filters['from'] > $filters['to'], 422, 'The start date must not be after the end date.');

        return $filters;
    }

    private function applyAttemptFilters($query, array $filters): void
    {
        if (! empty($filters['from'])) $query->whereDate('submitted_at', '>=', $filters['from']);
        if (! empty($filters['to'])) $query->whereDate('submitted_at', '<=', $filters['to']);
        if (! empty($filters['student_id'])) $query->where('student_id', $filters['student_id']);
    }

    private function linkedStudent(Request $request, Organization $organization): ?StudentProfile
    {
        return $organization->students()->where('status', 'active')->where('user_id', $request->user()->id)->first();
    }

    private function assessmentCourses(Organization $organization, Request $request)
    {
        $statuses = $request->user()->can('manageCourses', $organization) ? ['active', 'draft'] : ['active'];
        return $this->scopedCourses($organization, $request)->whereIn('status', $statuses);
    }

    private function scopedCourses(Organization $organization, Request $request)
    {
        return app(TeacherCourseScope::class)->courses($organization, $request->user());
    }

    private function scopedTeacherStudentIds(Organization $organization, Request $request)
    {
        return app(TeacherClassScope::class)->assignedStudentIds($request->user(), $organization);
    }

    private function questionData(Question $question, bool $includeAnswers): array
    {
        return [
            'id' => $question->id, 'course_id' => $question->course_id, 'topic' => $question->topic,
            'type' => $question->type, 'prompt' => $question->prompt, 'explanation' => $question->explanation,
            'difficulty' => $question->difficulty,
            'grading_rubric' => $question->grading_rubric,
            'options' => $question->options->map(fn (QuestionOption $option): array => [
                'id' => $option->id, 'text' => $option->text, ...($includeAnswers ? ['is_correct' => $option->is_correct] : []),
            ]),
        ];
    }

    private function assessmentData(Assessment $assessment): array
    {
        return [
            'id' => $assessment->id, 'course_id' => $assessment->course_id,
            'title' => $assessment->title, 'instructions' => $assessment->instructions,
            'duration_minutes' => $assessment->duration_minutes, 'attempts_allowed' => $assessment->attempts_allowed,
            'randomize_questions' => (bool) $assessment->randomize_questions,
            'randomized_question_count' => $assessment->randomized_question_count,
            'opens_at' => $assessment->opens_at?->toIso8601String(), 'closes_at' => $assessment->closes_at?->toIso8601String(),
            'status' => $assessment->status, 'question_count' => $assessment->questions_count ?? $assessment->questions()->count(),
        ];
    }

    private function attemptSummary(AssessmentAttempt $attempt): array
    {
        return [
            'id' => $attempt->id, 'attempt_number' => $attempt->attempt_number, 'status' => $attempt->status,
            'review_status' => $attempt->review_status ?? 'not_required',
            'score' => $attempt->score === null ? null : (float) $attempt->score,
            'max_points' => (float) $attempt->max_points,
            'started_at' => $attempt->started_at?->toIso8601String(),
            'expires_at' => $attempt->expires_at?->toIso8601String(),
            'submitted_at' => $attempt->submitted_at?->toIso8601String(),
        ];
    }
}
