import type { GradingRubricCriterion } from './lib/auth';

export function newRubricCriterion(label: string, weight: number): GradingRubricCriterion {
  return { id: crypto.randomUUID(), label, weight };
}

export function ShortAnswerRubricEditor({ criteria, onChange }: {
  criteria: GradingRubricCriterion[];
  onChange: (criteria: GradingRubricCriterion[]) => void;
}) {
  const totalWeight = criteria.reduce((sum, criterion) => sum + Number(criterion.weight || 0), 0);

  return <fieldset className="answer-key-fieldset rubric-editor">
    <legend>Grading rubric</legend>
    <small className="form-hint">Optional. Give each criterion a weight; weights must total 100. Educators score each criterion from 0 to 100.</small>
    {criteria.map((criterion, index) => <div className="rubric-editor-row" key={criterion.id}>
      <label>Criterion {index + 1}<input maxLength={160} value={criterion.label} onChange={(event) => onChange(criteria.map((item) => item.id === criterion.id ? { ...item, label: event.target.value } : item))} placeholder="e.g. Scientific accuracy" /></label>
      <label>Weight (%)<input type="number" min="1" max="100" value={criterion.weight} onChange={(event) => onChange(criteria.map((item) => item.id === criterion.id ? { ...item, weight: Number(event.target.value) } : item))} /></label>
      <button type="button" className="rubric-remove" disabled={criteria.length <= 2} aria-label={`Remove criterion ${index + 1}`} onClick={() => onChange(criteria.filter((item) => item.id !== criterion.id))}>Remove</button>
    </div>)}
    <div className="rubric-editor-footer"><span className={criteria.length && totalWeight === 100 ? 'rubric-weight-total valid' : 'rubric-weight-total'}>Total weight: {totalWeight}%</span>
      <button type="button" className="task-submit" disabled={criteria.length >= 10} onClick={() => onChange([...criteria, newRubricCriterion('', 1)])}>Add criterion</button>
    </div>
  </fieldset>;
}
