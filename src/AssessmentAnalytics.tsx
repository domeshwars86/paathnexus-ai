import { useState, type FormEvent } from 'react';
import { useLocale } from './lib/locale';
import { downloadAssessmentGrades, getAssessmentAnalytics, getAssessmentAttemptReview, gradeShortAnswer, listAssessmentAttempts, type AssessmentAnalytics, type AssessmentAnalyticsFilters, type AssessmentAttemptForReview, type AssessmentAttemptReview, type LearningAssessment } from './lib/auth';

type Props = {
  token: string;
  organizationId: string;
  courseId: string;
  assessments: LearningAssessment[];
};

type ReviewQuestion = AssessmentAttemptReview['questions'][number];

function ShortAnswerGrader({ token, organizationId, courseId, assessmentId, attemptId, question, onSaved }: {
  token: string; organizationId: string; courseId: string; assessmentId: string; attemptId: string;
  question: ReviewQuestion; onSaved: () => Promise<void>;
}) {
  const { t } = useLocale();
  const [points, setPoints] = useState(String(question.points_awarded));
  const [rubricScores, setRubricScores] = useState<Record<string, string>>(() => Object.fromEntries((question.rubric_scores ?? []).map((item) => [item.criterion_id, String(item.score)])));
  const [feedback, setFeedback] = useState(question.grader_feedback ?? '');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const score = Number(points);
    if (!Number.isFinite(score) || score < 0 || score > question.points) return;
    const rubric = question.grading_rubric ?? [];
    const submittedRubricScores = rubric.map((criterion) => ({ criterion_id: criterion.id, score: Number(rubricScores[criterion.id]) }));
    if (rubric.length && submittedRubricScores.some((item) => !Number.isFinite(item.score) || item.score < 0 || item.score > 100)) return;
    setSaving(true);
    setError('');
    try {
      await gradeShortAnswer(token, organizationId, courseId, assessmentId, attemptId, question.question_id, {
        ...(rubric.length ? { rubric_scores: submittedRubricScores } : { points_awarded: score }),
        ...(feedback.trim() ? { feedback: feedback.trim() } : {}),
      });
      await onSaved();
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : t('assessment.analytics.saveGradeError'));
    } finally {
      setSaving(false);
    }
  }

  const rubric = question.grading_rubric ?? [];
  const rubricPoints = rubric.reduce((sum, criterion) => sum + criterion.weight * Number(rubricScores[criterion.id] ?? 0), 0) / 10000 * question.points;

  return <form className="short-answer-grade-form" onSubmit={(event) => void submit(event)}>
    {rubric.length ? <div className="rubric-grade-fields"><strong>{t('assessment.analytics.rubricScore')}: {rubricPoints.toFixed(2)} / {question.points}</strong>{rubric.map((criterion) => <label key={criterion.id}>{criterion.label} · {criterion.weight}%<input aria-label={`${criterion.label}, score from zero to one hundred`} type="number" min="0" max="100" step="1" required value={rubricScores[criterion.id] ?? ''} onChange={(event) => setRubricScores((current) => ({ ...current, [criterion.id]: event.target.value }))} /></label>)}</div> : <label>{t('assessment.analytics.pointsAwarded')}<input type="number" min="0" max={question.points} step="0.01" required value={points} onChange={(event) => setPoints(event.target.value)} /></label>}
    <label>{t('assessment.analytics.feedback')}<textarea maxLength={5000} value={feedback} onChange={(event) => setFeedback(event.target.value)} /></label>
    {error && <small className="assessment-analytics-error" role="alert">{error}</small>}
    <button type="submit" className="task-submit" disabled={saving || (rubric.length ? rubric.some((criterion) => rubricScores[criterion.id] === undefined || rubricScores[criterion.id] === '' || !Number.isFinite(Number(rubricScores[criterion.id])) || Number(rubricScores[criterion.id]) < 0 || Number(rubricScores[criterion.id]) > 100) : !Number.isFinite(Number(points)) || Number(points) < 0 || Number(points) > question.points)}>{saving ? t('assessment.loading') : t('assessment.analytics.saveGrade')}</button>
  </form>;
}

export function AssessmentAnalyticsPanel({ token, organizationId, courseId, assessments }: Props) {
  const { t } = useLocale();
  const [assessmentId, setAssessmentId] = useState(assessments[0]?.id ?? '');
  const [analytics, setAnalytics] = useState<AssessmentAnalytics | null>(null);
  const [attempts, setAttempts] = useState<AssessmentAttemptForReview[]>([]);
  const [availableStudents, setAvailableStudents] = useState<AssessmentAttemptForReview[]>([]);
  const [attemptId, setAttemptId] = useState('');
  const [fromDate, setFromDate] = useState('');
  const [toDate, setToDate] = useState('');
  const [studentId, setStudentId] = useState('');
  const [review, setReview] = useState<AssessmentAttemptReview | null>(null);
  const [loading, setLoading] = useState(false);
  const [reviewLoading, setReviewLoading] = useState(false);
  const [exporting, setExporting] = useState(false);
  const [error, setError] = useState('');

  function filters(): AssessmentAnalyticsFilters {
    return { ...(fromDate ? { from: fromDate } : {}), ...(toDate ? { to: toDate } : {}), ...(studentId ? { student_id: studentId } : {}) };
  }

  async function loadAnalytics() {
    if (!assessmentId) return;
    if (fromDate && toDate && fromDate > toDate) {
      setError(t('assessment.analytics.invalidDateRange'));
      return;
    }
    setLoading(true);
    setError('');
    try {
      const filter = filters();
      const [nextAnalytics, nextAttempts, allAttempts] = await Promise.all([
        getAssessmentAnalytics(token, organizationId, courseId, assessmentId, filter),
        listAssessmentAttempts(token, organizationId, courseId, assessmentId, filter),
        listAssessmentAttempts(token, organizationId, courseId, assessmentId),
      ]);
      setAnalytics(nextAnalytics);
      const submittedAttempts = nextAttempts.filter((attempt) => ['submitted', 'expired'].includes(attempt.status));
      setAttempts(submittedAttempts);
      setAvailableStudents(allAttempts);
      setAttemptId(submittedAttempts[0]?.id ?? '');
      setReview(null);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : t('assessment.analytics.loadError'));
      setAnalytics(null);
    } finally {
      setLoading(false);
    }
  }

  async function loadReview() {
    if (!attemptId) return;
    setReviewLoading(true);
    setError('');
    try {
      setReview(await getAssessmentAttemptReview(token, organizationId, courseId, assessmentId, attemptId));
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : t('assessment.analytics.reviewLoadError'));
      setReview(null);
    } finally {
      setReviewLoading(false);
    }
  }

  async function refreshAfterGrade() {
    const filter = filters();
    const [nextReview, nextAnalytics, nextAttempts] = await Promise.all([
      getAssessmentAttemptReview(token, organizationId, courseId, assessmentId, attemptId),
      getAssessmentAnalytics(token, organizationId, courseId, assessmentId, filter),
      listAssessmentAttempts(token, organizationId, courseId, assessmentId, filter),
    ]);
    setReview(nextReview);
    setAnalytics(nextAnalytics);
    setAttempts(nextAttempts.filter((attempt) => ['submitted', 'expired'].includes(attempt.status)));
  }

  async function exportGrades() {
    if (!assessmentId || (fromDate && toDate && fromDate > toDate)) return;
    setExporting(true);
    setError('');
    try {
      const blob = await downloadAssessmentGrades(token, organizationId, courseId, assessmentId, filters());
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `${assessments.find((item) => item.id === assessmentId)?.title ?? 'assessment'}-grades.csv`.replace(/[^a-z0-9._-]+/gi, '-');
      link.click();
      URL.revokeObjectURL(url);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : t('assessment.analytics.exportError'));
    } finally {
      setExporting(false);
    }
  }

  return (
    <section className="assessment-analytics" aria-labelledby="assessment-analytics-title">
      <div className="assessment-analytics-heading">
        <div><strong id="assessment-analytics-title">{t('assessment.analytics.title')}</strong><small>{t('assessment.analytics.accuracyDescription')}</small></div>
        <label>{t('assessment.analytics.assessment')}<select value={assessmentId} onChange={(event) => { setAssessmentId(event.target.value); setAnalytics(null); setAttempts([]); setAttemptId(''); setReview(null); }}>
          {assessments.map((assessment) => <option key={assessment.id} value={assessment.id}>{assessment.title}</option>)}
        </select></label>
      </div>
      <div className="assessment-analytics-filters">
        <label>{t('assessment.analytics.fromDate')}<input type="date" value={fromDate} onChange={(event) => setFromDate(event.target.value)} /></label>
        <label>{t('assessment.analytics.toDate')}<input type="date" value={toDate} onChange={(event) => setToDate(event.target.value)} /></label>
        <label>{t('assessment.analytics.student')}<select value={studentId} onChange={(event) => { setStudentId(event.target.value); setReview(null); }}><option value="">{t('assessment.analytics.allLearners')}</option>{Array.from(new Map(availableStudents.filter((attempt) => attempt.student_id).map((attempt) => [attempt.student_id, attempt])).values()).map((attempt) => <option key={attempt.student_id} value={attempt.student_id ?? ''}>{attempt.student_name ?? t('assessment.analytics.learner')}{attempt.student_code ? ` · ${attempt.student_code}` : ''}</option>)}</select></label>
        <button type="button" className="task-submit" disabled={loading || !assessmentId || Boolean(fromDate && toDate && fromDate > toDate)} onClick={() => void loadAnalytics()}>{loading ? t('assessment.loading') : t('assessment.analytics.applyFilters')}</button>
        <button type="button" className="task-submit" disabled={exporting || !assessmentId || Boolean(fromDate && toDate && fromDate > toDate)} onClick={() => void exportGrades()}>{exporting ? t('assessment.analytics.exporting') : t('assessment.analytics.exportGrades')}</button>
      </div>
      {error && <p className="assessment-analytics-error" role="alert">{error}</p>}
      {analytics && <>
        <div className="assessment-analytics-metrics">
          <article><span>{t('assessment.analytics.attempts')}</span><strong>{analytics.attempt_count}</strong><small>{analytics.completed_attempt_count} {t('assessment.analytics.completed')}</small></article>
          <article><span>{t('assessment.analytics.averageScore')}</span><strong>{analytics.average_score_percent === null ? '—' : `${analytics.average_score_percent}%`}</strong><small>{t('assessment.analytics.sampledPoints')}</small></article>
          <article><span>{t('assessment.analytics.questionPool')}</span><strong>{analytics.question_pool_count}</strong><small>{analytics.randomized_question_count ? `${analytics.randomized_question_count} ${t('assessment.analytics.perAttempt')}` : t('assessment.analytics.fullPool')}</small></article>
        </div>
        <div className="assessment-analytics-table-wrap" role="region" aria-label={t('assessment.analytics.questionAccuracy')} tabIndex={0}><table className="assessment-analytics-table">
          <thead><tr><th>{t('assessment.question')}</th><th>{t('assessment.points')}</th><th>{t('assessment.analytics.presented')}</th><th>{t('assessment.analytics.correct')}</th><th>{t('assessment.analytics.accuracy')}</th></tr></thead>
          <tbody>{analytics.question_analytics.map((question) => <tr key={question.question_id}>
            <td>{question.prompt}</td><td>{question.points}</td><td>{question.attempts_presented}</td><td>{question.correct_count}</td>
            <td>{question.accuracy_percent === null ? '—' : `${question.accuracy_percent}%`}</td>
          </tr>)}</tbody>
        </table></div>
        <div className="assessment-review">
          <h3>{t('assessment.analytics.reviewAnswers')}</h3>
          {attempts.length ? <div className="assessment-review-controls">
            <label>{t('assessment.analytics.learnerAttempt')}<select value={attemptId} onChange={(event) => { setAttemptId(event.target.value); setReview(null); }}>
              {attempts.map((attempt) => <option key={attempt.id} value={attempt.id}>{attempt.student_name ?? t('assessment.analytics.learner')}{attempt.student_code ? ` · ${attempt.student_code}` : ''} · {t('assessment.attemptNumber').replace('{number}', String(attempt.attempt_number))} · {attempt.score ?? 0}/{attempt.max_points}</option>)}
            </select></label>
            <button type="button" className="task-submit" disabled={reviewLoading || !attemptId} onClick={() => void loadReview()}>{reviewLoading ? t('assessment.loading') : t('assessment.analytics.reviewButton')}</button>
          </div> : <p className="assessment-review-empty">{t('assessment.analytics.noAttempts')}</p>}
          {review && <div className="assessment-review-detail">
            <p><strong>{review.attempt.student_name ?? t('assessment.analytics.learner')}</strong>{review.attempt.student_code ? ` · ${review.attempt.student_code}` : ''} · {t('assessment.analytics.score')} {review.attempt.score ?? 0}/{review.attempt.max_points}</p>
            {review.questions.map((question, index) => <article className="assessment-review-question" key={question.question_id}>
              <div><strong>{index + 1}. {question.prompt}</strong><span className={question.is_correct === true ? 'is-correct' : question.is_correct === false ? 'is-incorrect' : ''}>{question.is_correct === true ? t('assessment.analytics.correct') : question.is_correct === false ? t('assessment.analytics.incorrect') : t('assessment.analytics.pendingReview')}</span></div>
              <small>{t('assessment.analytics.learnerAnswer')} {question.type === 'short_answer' ? question.response_text || t('assessment.noResponse') : question.selected_options.map((option) => option.text).join(', ') || t('assessment.noAnswer')}</small>
              {question.type !== 'short_answer' && <small>{t('assessment.analytics.correctAnswer')} {question.correct_options.map((option) => option.text).join(', ') || t('assessment.analytics.notApplicable')}</small>}
              <small>{t('assessment.points')}: {question.points_awarded}/{question.points}</small>
              {question.grader_feedback && <small>{t('assessment.analytics.feedbackSummary')} {question.grader_feedback}</small>}
              {question.type === 'short_answer' && <ShortAnswerGrader key={`${question.question_id}-${question.graded_at ?? 'pending'}`} token={token} organizationId={organizationId} courseId={courseId} assessmentId={assessmentId} attemptId={review.attempt.id} question={question} onSaved={refreshAfterGrade} />}
            </article>)}
          </div>}
        </div>
      </>}
    </section>
  );
}
