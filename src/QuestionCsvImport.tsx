import { useState, type ChangeEvent } from 'react';
import { importQuestionCsv, previewQuestionCsv, type BankQuestion, type QuestionCsvPreview } from './lib/auth';
import { useLocale } from './lib/locale';

type Props = {
  token: string;
  organizationId: string;
  courseId: string;
  onImported: (questions: BankQuestion[]) => void;
};

const csvHeaders = ['prompt', 'type', 'difficulty', 'topic', ...Array.from({ length: 8 }, (_, index) => [`option_${index + 1}`, `option_${index + 1}_correct`]).flat()];

function downloadTemplate() {
  const csv = `${csvHeaders.map((value) => `"${value}"`).join(',')}\r\n`;
  const url = URL.createObjectURL(new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8' }));
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = 'paathnexus-question-import-template.csv';
  anchor.click();
  window.setTimeout(() => URL.revokeObjectURL(url), 0);
}

export function QuestionCsvImport({ token, organizationId, courseId, onImported }: Props) {
  const { t } = useLocale();
  const [file, setFile] = useState<File | null>(null);
  const [preview, setPreview] = useState<QuestionCsvPreview | null>(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [inputKey, setInputKey] = useState(0);

  async function chooseFile(event: ChangeEvent<HTMLInputElement>) {
    const selected = event.target.files?.[0] ?? null;
    setFile(selected);
    setPreview(null);
    setError('');
    if (!selected) return;
    setBusy(true);
    try {
      setPreview(await previewQuestionCsv(token, organizationId, courseId, selected));
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : t('assessment.import.previewError'));
    } finally {
      setBusy(false);
    }
  }

  async function importFile() {
    if (!file || !preview?.valid) return;
    setBusy(true);
    setError('');
    try {
      const result = await importQuestionCsv(token, organizationId, courseId, file);
      onImported(result.questions);
      setFile(null);
      setPreview(null);
      setInputKey((key) => key + 1);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : t('assessment.import.importError'));
    } finally {
      setBusy(false);
    }
  }

  return (
    <details className="question-csv-import">
      <summary>{t('assessment.import.title')}</summary>
      <p>{t('assessment.import.instructions')}</p>
      <button type="button" className="button button-quiet question-csv-template" onClick={downloadTemplate}>{t('assessment.import.downloadTemplate')}</button>
      <label className="question-csv-file">{t('assessment.import.fileLabel')}<input key={inputKey} type="file" accept=".csv,text/csv" onChange={(event) => void chooseFile(event)} disabled={busy} /></label>
      {busy && <p className="question-csv-status" role="status">{preview ? t('assessment.import.importing') : t('assessment.import.checking')}</p>}
      {error && <p className="question-csv-error" role="alert">{error}</p>}
      {preview && <div className="question-csv-preview" aria-live="polite">
        {preview.errors.map((message) => <p className="question-csv-error" key={message}>{message}</p>)}
        {preview.row_count > 0 && <div className="question-csv-summary"><strong>{t('assessment.import.validRows').replace('{valid}', String(preview.valid_count)).replace('{total}', String(preview.row_count))}</strong><span>{preview.valid ? t('assessment.import.ready') : t('assessment.import.fixRows')}</span></div>}
        {preview.rows.map((row) => <article className={`question-csv-row${row.valid ? '' : ' has-errors'}`} key={row.row_number}>
          <div><strong>{t('assessment.import.rowNumber').replace('{number}', String(row.row_number))}</strong><span>{row.valid ? t('assessment.import.valid') : t('assessment.import.needsChanges')}</span></div>
          <p>{row.question.prompt || t('assessment.import.missingQuestion')}</p>
          <small>{row.question.type ? t(row.question.type === 'single_choice' ? 'assessment.singleChoice' : row.question.type === 'multiple_select' ? 'assessment.multipleSelect' : 'assessment.shortAnswer') : t('assessment.import.missingQuestionType')} · {t('assessment.import.level')} {row.question.difficulty || '—'} · {t('assessment.import.completeOptions').replace('{count}', String(row.question.options.length))} · {t('assessment.import.correctOptions').replace('{count}', String(row.question.options.filter((option) => option.is_correct).length))}</small>
          {Object.entries(row.errors).map(([field, messages]) => messages.map((message) => <small className="question-csv-row-error" key={`${field}-${message}`}>{field}: {message}</small>))}
        </article>)}
        <button type="button" className="task-submit" disabled={busy || !preview.valid || !file} onClick={() => void importFile()}>{busy ? t('assessment.import.importing') : t('assessment.import.importCount').replace('{count}', String(preview.valid_count))}</button>
      </div>}
    </details>
  );
}
