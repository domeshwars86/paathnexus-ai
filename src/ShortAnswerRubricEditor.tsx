import type { GradingRubricCriterion } from './lib/auth';
import { useLocale } from './lib/locale';

export function newRubricCriterion(label: string, weight: number): GradingRubricCriterion {
  return { id: crypto.randomUUID(), label, weight };
}

export function ShortAnswerRubricEditor({ criteria, onChange }: {
  criteria: GradingRubricCriterion[];
  onChange: (criteria: GradingRubricCriterion[]) => void;
}) {
  const { t } = useLocale();
  const totalWeight = criteria.reduce((sum, criterion) => sum + Number(criterion.weight || 0), 0);

  return <fieldset className="answer-key-fieldset rubric-editor">
    <legend>{t('assessment.rubric')}</legend>
    <small className="form-hint">{t('assessment.rubricHint')}</small>
    {criteria.map((criterion, index) => <div className="rubric-editor-row" key={criterion.id}>
      <label>{t('assessment.criterionNumber').replace('{number}', String(index + 1))}<input maxLength={160} value={criterion.label} onChange={(event) => onChange(criteria.map((item) => item.id === criterion.id ? { ...item, label: event.target.value } : item))} placeholder={t('assessment.rubricExample')} /></label>
      <label>{t('assessment.weight')}<input type="number" min="1" max="100" value={criterion.weight} onChange={(event) => onChange(criteria.map((item) => item.id === criterion.id ? { ...item, weight: Number(event.target.value) } : item))} /></label>
      <button type="button" className="rubric-remove" disabled={criteria.length <= 2} aria-label={t('assessment.removeCriterion').replace('{number}', String(index + 1))} onClick={() => onChange(criteria.filter((item) => item.id !== criterion.id))}>{t('assessment.remove')}</button>
    </div>)}
    <div className="rubric-editor-footer"><span className={criteria.length && totalWeight === 100 ? 'rubric-weight-total valid' : 'rubric-weight-total'}>{t('assessment.totalWeight').replace('{weight}', String(totalWeight))}</span>
      <button type="button" className="task-submit" disabled={criteria.length >= 10} onClick={() => onChange([...criteria, newRubricCriterion('', 1)])}>{t('assessment.addCriterion')}</button>
    </div>
  </fieldset>;
}
