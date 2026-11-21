import { useState } from 'react';
import { getAssessmentAttemptResult, type AssessmentAttemptStudentResult, type AssessmentAttemptSummary, type LearningAssessment } from './lib/auth';
import { useLocale } from './lib/locale';

type Props = {
  token: string;
  organizationId: string;
  assessments: LearningAssessment[];
  attemptsByAssessment: Record<string, AssessmentAttemptSummary[]>;
};

function AttemptResult({ token, organizationId, assessment, attempt }: {
  token: string; organizationId: string; assessment: LearningAssessment; attempt: AssessmentAttemptSummary;
}) {
  const { t } = useLocale();
  const [result, setResult] = useState<AssessmentAttemptStudentResult | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  async function loadResult() {
    setLoading(true);
    setError('');
    try {
      setResult(await getAssessmentAttemptResult(token, organizationId, assessment.id, attempt.id));
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : t('assessment.resultLoadError'));
    } finally {
      setLoading(false);
    }
  }

  const reviewStatus = result?.attempt.review_status ?? attempt.review_status;
  const score = result?.attempt.score ?? attempt.score;
  const maxPoints = result?.attempt.max_points ?? attempt.max_points;

  return <article className="student-attempt-result">
    <div><strong>{t('assessment.attemptNumber').replace('{number}', String(attempt.attempt_number))}</strong><span>{reviewStatus === 'pending' ? t('assessment.awaitingReview') : `${score ?? 0} / ${maxPoints} ${t('assessment.points')}`}</span></div>
    {attempt.status !== 'in_progress' && <button type="button" className="button button-quiet" disabled={loading} onClick={() => void loadResult()}>{loading ? t('assessment.loading') : result ? t('assessment.refreshFeedback') : t('assessment.viewAnswersFeedback')}</button>}
    {error && <small className="question-csv-error" role="alert">{error}</small>}
    {result && <div className="student-attempt-answer-list">{result.answers.map((answer, index) => <section key={answer.question_id}>
      <strong>{index + 1}. {answer.prompt}</strong>
      <small>{t('assessment.yourAnswer')} {answer.type === 'short_answer' ? answer.response_text || t('assessment.noResponse') : answer.selected_options.map((option) => option.text).join(', ') || t('assessment.noAnswer')}</small>
      <small>{t('assessment.points')}: {answer.points_awarded}/{answer.points}</small>
      {answer.pending_review ? <small className="student-answer-pending">{t('assessment.writtenAnswerPending')}</small> : answer.grader_feedback && <small>{t('assessment.educatorFeedback')} {answer.grader_feedback}</small>}
    </section>)}</div>}
  </article>;
}

export function AssessmentStudentResults({ token, organizationId, assessments, attemptsByAssessment }: Props) {
  const { t } = useLocale();
  const items = assessments.flatMap((assessment) => (attemptsByAssessment[assessment.id] ?? [])
    .filter((attempt) => attempt.status !== 'in_progress')
    .map((attempt) => ({ assessment, attempt })));
  if (!items.length) return null;

  return <section className="student-attempt-results" aria-label={t('assessment.answersFeedback')}>
    <h3>{t('assessment.myAnswersFeedback')}</h3>
    {items.map(({ assessment, attempt }) => <div className="student-attempt-result-group" key={attempt.id}>
      <strong>{assessment.title}</strong>
      <AttemptResult token={token} organizationId={organizationId} assessment={assessment} attempt={attempt} />
    </div>)}
  </section>;
}
