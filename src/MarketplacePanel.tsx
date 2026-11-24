import { useEffect, useState, type FormEvent } from 'react';
import { ArrowRight, BookOpen, Search, UserRound } from 'lucide-react';
import { createPublicCourseOrder, enrollPublicCourse, getCourseOrder, listPublicCourses, simulateSandboxCourseCapture, type CourseOrderRecord, type Organization, type PublicCourse } from './lib/auth';
import { useLocale } from './lib/locale';

type EnrollmentResult = { organizationId: string; organizationName: string };

type Props = {
  token: string;
  organizations: Organization[];
  onOpenWorkspace: (organizationId: string) => void;
};

function readSavedOrders(): Record<string, string> {
  try {
    const value: unknown = JSON.parse(sessionStorage.getItem('paathnexus.marketplace.orders') ?? '{}');
    if (!value || typeof value !== 'object' || Array.isArray(value)) return {};
    return Object.fromEntries(Object.entries(value).filter((entry): entry is [string, string] => typeof entry[1] === 'string' && entry[1].length > 0));
  } catch {
    return {};
  }
}

function formatPrice(amountPaise: number, locale: 'en' | 'hi'): string {
  return amountPaise === 0 ? (locale === 'hi' ? 'निःशुल्क' : 'Free') : new Intl.NumberFormat(locale === 'hi' ? 'hi-IN' : 'en-IN', { style: 'currency', currency: 'INR' }).format(amountPaise / 100);
}

export default function MarketplacePanel({ token, organizations, onOpenWorkspace }: Props) {
  const { locale, t } = useLocale();
  const [courses, setCourses] = useState<PublicCourse[]>([]);
  const [query, setQuery] = useState('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [busyCourse, setBusyCourse] = useState('');
  const [enrolled, setEnrolled] = useState<Record<string, EnrollmentResult>>({});
  const [courseOrders, setCourseOrders] = useState<Record<string, CourseOrderRecord>>({});
  const [orderBusy, setOrderBusy] = useState('');

  function rememberOrder(courseId: string, order: CourseOrderRecord) {
    setCourseOrders((current) => ({ ...current, [courseId]: order }));
    const saved = readSavedOrders();
    sessionStorage.setItem('paathnexus.marketplace.orders', JSON.stringify({ ...saved, [courseId]: order.id }));
    if (order.status === 'paid') {
      const membership = organizations.find((organization) => organization.id === order.organization_id && organization.role === 'student');
      if (membership) setEnrolled((current) => ({ ...current, [courseId]: { organizationId: membership.id, organizationName: membership.name } }));
    }
  }

  async function load(term = search, pageNumber = 1) {
    setLoading(true);
    setError('');
    try {
      const result = await listPublicCourses(term, pageNumber);
      setCourses(result.items);
      setPage(result.page);
      setLastPage(result.lastPage);
      setTotal(result.total);
      setSearch(term);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Public courses are temporarily unavailable.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { void load('', 1); }, [token]);

  useEffect(() => {
    const saved = readSavedOrders();
    Object.entries(saved).forEach(([courseId, orderId]) => {
      void getCourseOrder(token, orderId).then((order) => rememberOrder(courseId, order)).catch(() => {
        const latest = readSavedOrders();
        delete latest[courseId];
        sessionStorage.setItem('paathnexus.marketplace.orders', JSON.stringify(latest));
      });
    });
  }, [token, organizations]);

  async function submitSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    await load(query.trim(), 1);
  }

  async function enroll(course: PublicCourse) {
    setBusyCourse(course.id);
    setError('');
    try {
      const result = await enrollPublicCourse(token, course.id);
      setEnrolled((current) => ({ ...current, [course.id]: { organizationId: result.organization_id, organizationName: result.organization_name } }));
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Could not enroll in this course.');
    } finally {
      setBusyCourse('');
    }
  }

  async function beginCheckout(course: PublicCourse) {
    setOrderBusy(course.id);
    setError('');
    try {
      rememberOrder(course.id, await createPublicCourseOrder(token, course.id));
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : t('marketplace.checkout.createError'));
    } finally {
      setOrderBusy('');
    }
  }

  async function refreshCheckout(courseId: string, orderId: string) {
    setOrderBusy(courseId);
    setError('');
    try {
      rememberOrder(courseId, await getCourseOrder(token, orderId));
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : t('marketplace.checkout.statusError'));
    } finally {
      setOrderBusy('');
    }
  }

  async function simulatePayment(courseId: string, orderId: string) {
    if (!import.meta.env.DEV) return;
    setOrderBusy(courseId);
    setError('');
    try {
      rememberOrder(courseId, await simulateSandboxCourseCapture(token, orderId));
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : t('marketplace.checkout.captureError'));
    } finally {
      setOrderBusy('');
    }
  }

  function openEnrolledWorkspace(courseId: string) {
    const enrollment = enrolled[courseId];
    const membership = organizations.find((organization) => organization.id === enrollment?.organizationId && organization.role === 'student');
    if (membership) onOpenWorkspace(membership.id);
  }

  return <section className="panel academic-panel marketplace-panel">
    <div className="panel-heading"><div><div className="eyebrow">{t('marketplace.eyebrow')}</div><h2>{t('marketplace.title')}</h2><p>{t('marketplace.description')}</p></div></div>
    <form className="marketplace-search" onSubmit={(event) => void submitSearch(event)}><label htmlFor="marketplace-query">{t('marketplace.searchLabel')}</label><div><Search size={16} /><input id="marketplace-query" maxLength={100} value={query} onChange={(event) => setQuery(event.target.value)} placeholder={t('marketplace.searchPlaceholder')} /><button className="task-submit" disabled={loading}>{t('marketplace.search')}</button></div></form>
    {search && <p className="form-hint">{t(total === 1 ? 'marketplace.resultOne' : 'marketplace.resultMany').replace('{count}', String(total))} “{search}”.</p>}
    {error && <div className="academic-error" role="alert">{error}</div>}
    {loading ? <div className="task-message" role="status">{t('marketplace.loading')}</div> : courses.length ? <div className="marketplace-grid">{courses.map((course) => {
      const enrollment = enrolled[course.id];
      const order = courseOrders[course.id];
      const membershipAvailable = organizations.some((organization) => organization.id === enrollment?.organizationId && organization.role === 'student');
      return <article className="marketplace-card" key={course.id}>
        <div className="marketplace-card-top"><span className="marketplace-icon"><BookOpen size={18} /></span><span>{course.subject || t('marketplace.courseType')}</span></div>
        <div className="marketplace-card-copy"><small>{course.organization_name || t('marketplace.educator')}</small><h3>{course.title}</h3><p>{course.description || t('marketplace.courseDescription')}</p></div>
        {!!course.instructors.length && <div className="marketplace-instructors"><UserRound size={14} /><span>{course.instructors.map((instructor) => instructor.name).join(', ')}</span></div>}
        <div className="marketplace-card-footer"><span>{t('marketplace.lessonsCount').replace('{count}', String(course.lesson_count))} · {formatPrice(course.amount_paise, locale)}</span>{enrollment
          ? <button type="button" className="task-submit" disabled={!membershipAvailable} onClick={() => openEnrolledWorkspace(course.id)}>{membershipAvailable ? t('marketplace.openWorkspace') : t('marketplace.enrolledName').replace('{name}', enrollment.organizationName)} {membershipAvailable && <ArrowRight size={14} />}</button>
          : course.amount_paise > 0
            ? import.meta.env.DEV && order?.status === 'pending'
              ? <span className="marketplace-order-status">{t('marketplace.checkout.pending')}</span>
              : order?.status === 'paid'
                ? <button type="button" className="task-submit" disabled={!membershipAvailable} onClick={() => openEnrolledWorkspace(course.id)}>{t('marketplace.openWorkspace')} <ArrowRight size={14} /></button>
                : import.meta.env.DEV
                  ? <button type="button" className="task-submit" disabled={orderBusy === course.id} onClick={() => void beginCheckout(course)}>{orderBusy === course.id ? t('marketplace.checkout.starting') : t('marketplace.checkout.start')}</button>
                  : <button type="button" className="task-cancel" disabled title={t('marketplace.checkout.unavailable')}>{t('marketplace.checkout.unavailable')}</button>
            : <button type="button" className="task-submit" disabled={busyCourse === course.id} onClick={() => void enroll(course)}>{busyCourse === course.id ? t('marketplace.enrolling') : t('marketplace.enroll')} <ArrowRight size={14} /></button>}</div>
        {course.amount_paise > 0 && import.meta.env.DEV && !order && <small className="form-hint">{t('marketplace.checkout.disclosure')}</small>}
        {course.amount_paise > 0 && import.meta.env.DEV && order && <div className="marketplace-checkout" role="group" aria-label={t('marketplace.checkout.panel')}>
          <strong>{order.status === 'paid' ? t('marketplace.checkout.paid') : t('marketplace.checkout.pending')}</strong>
          <p>{t('marketplace.checkout.disclosure')}</p>
          <small>{t('marketplace.checkout.orderAmount').replace('{amount}', formatPrice(order.amount_paise, locale))}</small>
          {order.status === 'pending' && <div className="marketplace-checkout-actions">
            <button type="button" className="task-cancel" disabled={orderBusy === course.id} onClick={() => void refreshCheckout(course.id, order.id)}>{orderBusy === course.id ? t('marketplace.checkout.checking') : t('marketplace.checkout.checkStatus')}</button>
            <button type="button" className="task-submit" disabled={orderBusy === course.id} onClick={() => void simulatePayment(course.id, order.id)}>{orderBusy === course.id ? t('marketplace.checkout.processing') : t('marketplace.checkout.simulate')}</button>
          </div>}
        </div>}
      </article>;
    })}</div> : <div className="academic-empty">{search ? t('marketplace.noSearchResults') : t('marketplace.empty')}</div>}
    {!loading && lastPage > 1 && <div className="marketplace-pagination"><button type="button" className="task-cancel" disabled={page <= 1} onClick={() => void load(search, page - 1)}>{t('marketplace.previous')}</button><span>{t('marketplace.page').replace('{page}', String(page)).replace('{total}', String(lastPage))}</span><button type="button" className="task-cancel" disabled={page >= lastPage} onClick={() => void load(search, page + 1)}>{t('marketplace.next')}</button></div>}
  </section>;
}
