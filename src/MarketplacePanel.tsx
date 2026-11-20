import { useEffect, useState, type FormEvent } from 'react';
import { ArrowRight, BookOpen, Search, UserRound } from 'lucide-react';
import { enrollPublicCourse, listPublicCourses, type Organization, type PublicCourse } from './lib/auth';

type EnrollmentResult = { organizationId: string; organizationName: string };

type Props = {
  token: string;
  organizations: Organization[];
  onOpenWorkspace: (organizationId: string) => void;
};

function formatPrice(amountPaise: number): string {
  return amountPaise === 0 ? 'Free' : new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(amountPaise / 100);
}

export default function MarketplacePanel({ token, organizations, onOpenWorkspace }: Props) {
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

  function openEnrolledWorkspace(courseId: string) {
    const enrollment = enrolled[courseId];
    const membership = organizations.find((organization) => organization.id === enrollment?.organizationId && organization.role === 'student');
    if (membership) onOpenWorkspace(membership.id);
  }

  return <section className="panel academic-panel marketplace-panel">
    <div className="panel-heading"><div><div className="eyebrow">PAATHNEXUS MARKETPLACE</div><h2>Discover courses</h2><p>Browse published courses. Enrollment is available to linked student accounts in the course workspace.</p></div></div>
    <form className="marketplace-search" onSubmit={(event) => void submitSearch(event)}><label htmlFor="marketplace-query">Search public courses</label><div><Search size={16} /><input id="marketplace-query" maxLength={100} value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Course, subject, or school" /><button className="task-submit" disabled={loading}>Search</button></div></form>
    {search && <p className="form-hint">{total} result{total === 1 ? '' : 's'} for “{search}”.</p>}
    {error && <div className="academic-error" role="alert">{error}</div>}
    {loading ? <div className="task-message" role="status">Loading public courses…</div> : courses.length ? <div className="marketplace-grid">{courses.map((course) => {
      const enrollment = enrolled[course.id];
      const membershipAvailable = organizations.some((organization) => organization.id === enrollment?.organizationId && organization.role === 'student');
      return <article className="marketplace-card" key={course.id}>
        <div className="marketplace-card-top"><span className="marketplace-icon"><BookOpen size={18} /></span><span>{course.subject || 'Learning course'}</span></div>
        <div className="marketplace-card-copy"><small>{course.organization_name || 'PaathNexus educator'}</small><h3>{course.title}</h3><p>{course.description || 'A published course from the PaathNexus learning community.'}</p></div>
        {!!course.instructors.length && <div className="marketplace-instructors"><UserRound size={14} /><span>{course.instructors.map((instructor) => instructor.name).join(', ')}</span></div>}
        <div className="marketplace-card-footer"><span>{course.lesson_count} lessons · {formatPrice(course.amount_paise)}</span>{enrollment
          ? <button type="button" className="task-submit" disabled={!membershipAvailable} onClick={() => openEnrolledWorkspace(course.id)}>{membershipAvailable ? 'Open learning workspace' : `Enrolled · ${enrollment.organizationName}`} {membershipAvailable && <ArrowRight size={14} />}</button>
          : course.amount_paise > 0
            ? <button type="button" className="task-cancel" disabled title="Checkout is not available yet">Checkout unavailable</button>
            : <button type="button" className="task-submit" disabled={busyCourse === course.id} onClick={() => void enroll(course)}>{busyCourse === course.id ? 'Enrolling…' : 'Enroll as student'} <ArrowRight size={14} /></button>}</div>
        {course.amount_paise > 0 && <small className="form-hint">Paid checkout is not available yet. No payment will be collected.</small>}
      </article>;
    })}</div> : <div className="academic-empty">{search ? 'No public courses match this search.' : 'No public courses are available yet.'}</div>}
    {!loading && lastPage > 1 && <div className="marketplace-pagination"><button type="button" className="task-cancel" disabled={page <= 1} onClick={() => void load(search, page - 1)}>Previous</button><span>Page {page} of {lastPage}</span><button type="button" className="task-cancel" disabled={page >= lastPage} onClick={() => void load(search, page + 1)}>Next</button></div>}
  </section>;
}
