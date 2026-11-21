import { useEffect, useState, type FormEvent } from 'react';
import { listPlatformModeratableContent, updatePlatformContentModeration, type PlatformModeratableContent } from './lib/auth';
import { useLocale } from './lib/locale';

export function PlatformContentModerationPanel({ token }: { token: string }) {
  const { t } = useLocale();
  const [type, setType] = useState<PlatformModeratableContent['type']>('course');
  const [items, setItems] = useState<PlatformModeratableContent[]>([]);
  const [query, setQuery] = useState('');
  const [submittedQuery, setSubmittedQuery] = useState('');
  const [reason, setReason] = useState('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [savingId, setSavingId] = useState('');
  const [error, setError] = useState('');

  async function load(nextType = type, nextQuery = submittedQuery, nextPage = page) {
    setLoading(true); setError('');
    try {
      const result = await listPlatformModeratableContent(token, nextType, nextQuery, nextPage);
      setItems(result.items); setTotal(result.total); setPage(result.page); setLastPage(result.lastPage);
    } catch (cause) { setError(cause instanceof Error ? cause.message : t('platformModeration.loadError')); }
    finally { setLoading(false); }
  }

  useEffect(() => { void load('course', '', 1); }, [token]);

  async function search(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setSubmittedQuery(query.trim()); await load(type, query.trim(), 1);
  }

  async function changeType(nextType: PlatformModeratableContent['type']) {
    setType(nextType); setQuery(''); setSubmittedQuery(''); await load(nextType, '', 1);
  }

  async function moderate(item: PlatformModeratableContent) {
    const status = item.status === 'hidden' ? 'visible' : 'hidden';
    setSavingId(item.id); setError('');
    try {
      await updatePlatformContentModeration(token, item.type, item.id, status, status === 'hidden' ? reason : '');
      await load(type, submittedQuery, page);
    } catch (cause) { setError(cause instanceof Error ? cause.message : t('platformModeration.updateError')); }
    finally { setSavingId(''); }
  }

  return <section className="plan-catalog-list platform-content-moderation" aria-labelledby="platform-content-moderation-title">
    <div className="course-progress-heading"><div><strong id="platform-content-moderation-title">{t('platformModeration.title')}</strong><small>{t('platformModeration.description')}</small></div><span>{total}</span></div>
    {error && <div className="academic-error" role="alert">{error}</div>}
    <div className="auth-tabs" role="group" aria-label={t('platformModeration.contentType')}>
      <button type="button" aria-pressed={type === 'course'} className={type === 'course' ? 'selected' : ''} onClick={() => void changeType('course')}>{t('platformModeration.courses')}</button>
      <button type="button" aria-pressed={type === 'educator'} className={type === 'educator' ? 'selected' : ''} onClick={() => void changeType('educator')}>{t('platformModeration.educators')}</button>
    </div>
    <form className="platform-org-search" onSubmit={(event) => void search(event)}>
      <label>{t('platformModeration.searchLabel')}<input maxLength={100} value={query} onChange={(event) => setQuery(event.target.value)} placeholder={t('platformModeration.searchPlaceholder')} /></label>
      <button className="task-submit" disabled={loading}>{t('platformModeration.search')}</button>
    </form>
    <label>{t('platformModeration.reason')}<input maxLength={500} value={reason} onChange={(event) => setReason(event.target.value)} placeholder={t('platformModeration.reasonPlaceholder')} /></label>
    {loading ? <div className="task-message" role="status">{t('platformModeration.loading')}</div> : items.length ? <div className="platform-org-list">{items.map((item) => <article className="plan-card platform-org-card" key={`${item.type}:${item.id}`}>
      <div className="platform-org-header"><div><strong>{item.title}</strong><small>{item.organization_name ?? t('platformModeration.unknownOrganization')}</small></div><span className={`assignment-status ${item.status === 'visible' ? 'graded' : ''}`}>{t(item.status === 'hidden' ? 'platformModeration.hidden' : 'platformModeration.visible')}</span></div>
      {item.reason && <small>{t('platformModeration.reason')}: {item.reason}</small>}
      <button type="button" className={item.status === 'hidden' ? 'task-submit' : 'invoice-void'} disabled={savingId === item.id} onClick={() => void moderate(item)}>{savingId === item.id ? t('platformModeration.saving') : t(item.status === 'hidden' ? 'platformModeration.restore' : 'platformModeration.hide')}</button>
    </article>)}</div> : <div className="academic-empty" role="status">{t('platformModeration.empty')}</div>}
    {!loading && lastPage > 1 && <div className="plan-request-actions"><button type="button" className="invoice-void" disabled={page <= 1} onClick={() => void load(type, submittedQuery, page - 1)}>{t('platformAdmin.previous')}</button><span>{page} / {lastPage}</span><button type="button" className="invoice-void" disabled={page >= lastPage} onClick={() => void load(type, submittedQuery, page + 1)}>{t('platformAdmin.next')}</button></div>}
  </section>;
}
