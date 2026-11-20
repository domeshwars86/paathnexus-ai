import { useState, type ChangeEvent } from 'react';
import { importQuestionCsv, previewQuestionCsv, type BankQuestion, type QuestionCsvPreview } from './lib/auth';

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
      setError(cause instanceof Error ? cause.message : 'Could not preview this CSV.');
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
      setError(cause instanceof Error ? cause.message : 'Could not import these questions.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <details className="question-csv-import">
      <summary>Import questions from CSV</summary>
      <p>Upload up to 100 single-choice or multiple-select questions in a CSV file no larger than 1 MB. Nothing is saved until you review the preview and choose Import.</p>
      <button type="button" className="button button-quiet question-csv-template" onClick={downloadTemplate}>Download CSV template</button>
      <label className="question-csv-file">CSV file<input key={inputKey} type="file" accept=".csv,text/csv" onChange={(event) => void chooseFile(event)} disabled={busy} /></label>
      {busy && <p className="question-csv-status" role="status">{preview ? 'Importing questions…' : 'Checking CSV rows…'}</p>}
      {error && <p className="question-csv-error" role="alert">{error}</p>}
      {preview && <div className="question-csv-preview" aria-live="polite">
        {preview.errors.map((message) => <p className="question-csv-error" key={message}>{message}</p>)}
        {preview.row_count > 0 && <div className="question-csv-summary"><strong>{preview.valid_count} of {preview.row_count} rows are valid</strong><span>{preview.valid ? 'Ready to import' : 'Fix the rows marked below, then select the file again.'}</span></div>}
        {preview.rows.map((row) => <article className={`question-csv-row${row.valid ? '' : ' has-errors'}`} key={row.row_number}>
          <div><strong>Row {row.row_number}</strong><span>{row.valid ? 'Valid' : 'Needs changes'}</span></div>
          <p>{row.question.prompt || 'Question text is missing'}</p>
          <small>{row.question.type || 'Question type missing'} · Level {row.question.difficulty || '—'} · {row.question.options.length} complete options · {row.question.options.filter((option) => option.is_correct).length} correct</small>
          {Object.entries(row.errors).map(([field, messages]) => messages.map((message) => <small className="question-csv-row-error" key={`${field}-${message}`}>{field}: {message}</small>))}
        </article>)}
        <button type="button" className="task-submit" disabled={busy || !preview.valid || !file} onClick={() => void importFile()}>{busy ? 'Importing…' : `Import ${preview.valid_count} questions`}</button>
      </div>}
    </details>
  );
}
