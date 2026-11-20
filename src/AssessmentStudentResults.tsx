import { useState } from 'react';
import { getAssessmentAttemptResult, type AssessmentAttemptStudentResult, type AssessmentAttemptSummary, type LearningAssessment } from './lib/auth';

type Props = {
  token: string;
  organizationId: string;
  assessments: LearningAssessment[];
  attemptsByAssessment: Record<string, AssessmentAttemptSummary[]>;
};

function AttemptResult({ token, organizationId, assessment, attempt }: {
  token: string; organizationId: string; assessment: LearningAssessment; attempt: AssessmentAttemptSummary;
}) {
  const [result, setResult] = useState<AssessmentAttemptStudentResult | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  async function loadResult() {
    setLoading(true);
    setError('');
    try {
      setResult(await getAssessmentAttemptResult(token, organizationId, assessment.id, attempt.id));
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Could not load your answers.');
    } finally {
      setLoading(false);
    }
  }

  const reviewStatus = result?.attempt.review_status ?? attempt.review_status;
  const score = result?.attempt.score ?? attempt.score;
  const maxPoints = result?.attempt.max_points ?? attempt.max_points;

  return <article className="student-attempt-result">
    <div><strong>Attempt {attempt.attempt_number}</strong><span>{reviewStatus === 'pending' ? 'Awaiting educator review' : `${score ?? 0} / ${maxPoints} points`}</span></div>
    {attempt.status !== 'in_progress' && <button type="button" className="button button-quiet" disabled={loading} onClick={() => void loadResult()}>{loading ? 'Loading…' : result ? 'Refresh feedback' : 'View answers and feedback'}</button>}
    {error && <small className="question-csv-error" role="alert">{error}</small>}
    {result && <div className="student-attempt-answer-list">{result.answers.map((answer, index) => <section key={answer.question_id}>
      <strong>{index + 1}. {answer.prompt}</strong>
      <small>Your answer: {answer.type === 'short_answer' ? answer.response_text || 'No response' : answer.selected_options.map((option) => option.text).join(', ') || 'No answer'}</small>
      <small>Points: {answer.points_awarded}/{answer.points}</small>
      {answer.pending_review ? <small className="student-answer-pending">Your written response is waiting for educator review.</small> : answer.grader_feedback && <small>Educator feedback: {answer.grader_feedback}</small>}
    </section>)}</div>}
  </article>;
}

export function AssessmentStudentResults({ token, organizationId, assessments, attemptsByAssessment }: Props) {
  const items = assessments.flatMap((assessment) => (attemptsByAssessment[assessment.id] ?? [])
    .filter((attempt) => attempt.status !== 'in_progress')
    .map((attempt) => ({ assessment, attempt })));
  if (!items.length) return null;

  return <section className="student-attempt-results" aria-label="Assessment answers and feedback">
    <h3>My answers and feedback</h3>
    {items.map(({ assessment, attempt }) => <div className="student-attempt-result-group" key={attempt.id}>
      <strong>{assessment.title}</strong>
      <AttemptResult token={token} organizationId={organizationId} assessment={assessment} attempt={attempt} />
    </div>)}
  </section>;
}
