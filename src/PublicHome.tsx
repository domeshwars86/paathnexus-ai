import { useEffect, useRef, useState, type FormEvent, type MouseEvent as ReactMouseEvent } from 'react';
import {
  ArrowRight, BookOpen, CalendarDays, Check, GraduationCap, Menu, Search, ShieldCheck, Sparkles, Users, X,
} from 'lucide-react';
import { getPublicEducator, listPublicCourses, listPublicEducators, listPublicPlans, type PublicCourse, type PublicEducator, type PublicPlan } from './lib/auth';
import { LanguageSwitcher, useLocale } from './lib/locale';

type Props = { onSignIn: () => void; onRegister: () => void };
type PublicPage = 'home' | 'features' | 'educators' | 'educator-profile' | 'courses' | 'pricing';

const publicRoutes: Record<Exclude<PublicPage, 'educator-profile'>, string> = {
  home: '/', features: '/features', educators: '/educators', courses: '/courses', pricing: '/pricing',
};

function pageFromPath(pathname: string): { page: PublicPage; educatorId: string | null } {
  const educator = pathname.match(/^\/educators\/([0-9a-f-]{36})\/?$/i);
  if (educator) return { page: 'educator-profile', educatorId: educator[1] ?? null };
  const route = Object.entries(publicRoutes).find(([, path]) => path === pathname.replace(/\/$/, '') || (path === '/' && pathname === '/'));
  return { page: (route?.[0] as PublicPage | undefined) ?? 'home', educatorId: null };
}

function price(plan: PublicPlan, locale: 'en' | 'hi', translate: (key: 'home.free' | 'home.contactPricing') => string): string {
  if (plan.amount_paise === null || plan.billing_interval === 'custom') return translate('home.contactPricing');
  if (plan.amount_paise === 0) return translate('home.free');
  const interval = locale === 'hi' ? ({ month: 'माह', year: 'वर्ष', custom: 'विशेष' } as const)[plan.billing_interval] : plan.billing_interval;
  return `${new Intl.NumberFormat(locale === 'hi' ? 'hi-IN' : 'en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(plan.amount_paise / 100)} / ${interval}`;
}

function coursePrice(amountPaise: number, locale: 'en' | 'hi', freeLabel: string): string {
  return amountPaise === 0 ? freeLabel : new Intl.NumberFormat(locale === 'hi' ? 'hi-IN' : 'en-IN', { style: 'currency', currency: 'INR' }).format(amountPaise / 100);
}

export default function PublicHome({ onSignIn, onRegister }: Props) {
  const { locale, t } = useLocale();
  const [publicPage, setPublicPage] = useState<PublicPage>(() => pageFromPath(window.location.pathname).page);
  const [educatorProfileId, setEducatorProfileId] = useState<string | null>(() => pageFromPath(window.location.pathname).educatorId);
  const [educatorProfile, setEducatorProfile] = useState<PublicEducator | null>(null);
  const [loadingEducatorProfile, setLoadingEducatorProfile] = useState(false);
  const [educatorProfileError, setEducatorProfileError] = useState(false);
  const [courses, setCourses] = useState<PublicCourse[]>([]);
  const [educators, setEducators] = useState<PublicEducator[]>([]);
  const [plans, setPlans] = useState<PublicPlan[]>([]);
  const [total, setTotal] = useState(0);
  const [lastPage, setLastPage] = useState(1);
  const [page, setPage] = useState(1);
  const [query, setQuery] = useState('');
  const [search, setSearch] = useState('');
  const [educatorQuery, setEducatorQuery] = useState('');
  const [educatorSearch, setEducatorSearch] = useState('');
  const [educatorTotal, setEducatorTotal] = useState(0);
  const [educatorPage, setEducatorPage] = useState(1);
  const [educatorLastPage, setEducatorLastPage] = useState(1);
  const [loadingEducators, setLoadingEducators] = useState(false);
  const [educatorError, setEducatorError] = useState('');
  const [loadingCourses, setLoadingCourses] = useState(true);
  const [loadingPlans, setLoadingPlans] = useState(true);
  const [loadError, setLoadError] = useState('');
  const [planError, setPlanError] = useState('');
  const [mobileNavOpen, setMobileNavOpen] = useState(false);
  const [selectedCourse, setSelectedCourse] = useState<PublicCourse | null>(null);
  const mobileMenuButton = useRef<HTMLButtonElement>(null);
  const closeCourseButton = useRef<HTMLButtonElement>(null);
  const courseDialog = useRef<HTMLElement>(null);
  const courseTrigger = useRef<HTMLElement | null>(null);

  async function loadCourses(term = '', pageNumber = 1, append = false) {
    setLoadingCourses(true);
    setLoadError('');
    try {
      const result = await listPublicCourses(term, pageNumber);
      setCourses((current) => append ? [...current, ...result.items] : result.items);
      setTotal(result.total);
      setPage(result.page);
      setLastPage(result.lastPage);
    } catch (cause) {
      setLoadError(cause instanceof Error ? cause.message : 'Course discovery is temporarily unavailable.');
    } finally {
      setLoadingCourses(false);
    }
  }

  async function loadPlans() {
    setLoadingPlans(true);
    setPlanError('');
    try {
      setPlans(await listPublicPlans());
    } catch (cause) {
      setPlans([]);
      setPlanError(cause instanceof Error ? cause.message : 'Pricing is temporarily unavailable.');
    } finally {
      setLoadingPlans(false);
    }
  }

  async function loadEducators(term = '', pageNumber = 1, append = false) {
    setLoadingEducators(true);
    setEducatorError('');
    try {
      const result = await listPublicEducators(term, pageNumber);
      setEducators((current) => append ? [...current, ...result.items] : result.items);
      setEducatorTotal(result.total);
      setEducatorPage(result.page);
      setEducatorLastPage(result.lastPage);
    } catch (cause) {
      setEducatorError(cause instanceof Error ? cause.message : t('home.noEducators'));
    } finally {
      setLoadingEducators(false);
    }
  }

  useEffect(() => {
    document.documentElement.lang = locale === 'hi' ? 'hi-IN' : 'en';
  }, [locale]);

  useEffect(() => {
    const labels: Record<PublicPage, string> = {
      home: 'One Platform. Infinite Possibilities for Learning.',
      features: t('nav.platform'), educators: t('nav.educators'), 'educator-profile': educatorProfile?.name ?? t('nav.educators'), courses: t('nav.courses'), pricing: t('nav.pricing'),
    };
    document.title = `${labels[publicPage]} | PaathNexus AI`;
  }, [educatorProfile?.name, publicPage, t]);

  useEffect(() => {
    const onPopState = () => {
      const route = pageFromPath(window.location.pathname);
      setPublicPage(route.page);
      setEducatorProfileId(route.educatorId);
    };
    window.addEventListener('popstate', onPopState);
    return () => window.removeEventListener('popstate', onPopState);
  }, []);

  useEffect(() => {
    if (publicPage === 'home' || publicPage === 'courses') void loadCourses();
    if (publicPage === 'home' || publicPage === 'pricing') void loadPlans();
    if (publicPage === 'home' || publicPage === 'educators') void loadEducators();
  }, [publicPage]);

  useEffect(() => {
    if (!educatorProfileId) { setEducatorProfile(null); setEducatorProfileError(false); return; }
    let active = true;
    setLoadingEducatorProfile(true);
    setEducatorProfileError(false);
    void getPublicEducator(educatorProfileId).then((profile) => {
      if (active) setEducatorProfile(profile);
    }).catch(() => {
      if (active) { setEducatorProfile(null); setEducatorProfileError(true); }
    }).finally(() => { if (active) setLoadingEducatorProfile(false); });
    return () => { active = false; };
  }, [educatorProfileId]);

  useEffect(() => {
    if (!selectedCourse) return;
    closeCourseButton.current?.focus();
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setSelectedCourse(null);
      if (event.key !== 'Tab') return;

      const focusable = courseDialog.current?.querySelectorAll<HTMLElement>(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
      );
      if (!focusable?.length) {
        event.preventDefault();
        return;
      }

      const first = focusable.item(0);
      const last = focusable.item(focusable.length - 1);
      if (event.shiftKey && (document.activeElement === first || !courseDialog.current?.contains(document.activeElement))) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && (document.activeElement === last || !courseDialog.current?.contains(document.activeElement))) {
        event.preventDefault();
        first.focus();
      }
    };
    window.addEventListener('keydown', onKeyDown);
    return () => {
      window.removeEventListener('keydown', onKeyDown);
      courseTrigger.current?.focus();
    };
  }, [selectedCourse]);

  useEffect(() => {
    if (!mobileNavOpen) return;
    const closeOnEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setMobileNavOpen(false);
        if (mobileMenuButton.current?.getClientRects().length) mobileMenuButton.current.focus();
      }
    };
    document.addEventListener('keydown', closeOnEscape);
    return () => document.removeEventListener('keydown', closeOnEscape);
  }, [mobileNavOpen]);

  function submitSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const term = query.trim();
    setSearch(term);
    void loadCourses(term);
  }

  function submitEducatorSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const term = educatorQuery.trim();
    setEducatorSearch(term);
    void loadEducators(term);
  }

  function openCourse(event: ReactMouseEvent<HTMLElement>, course: PublicCourse) {
    courseTrigger.current = event.currentTarget;
    setSelectedCourse(course);
  }

  function closeMobileNav(restoreFocus = false) {
    setMobileNavOpen(false);
    if (restoreFocus && window.matchMedia('(max-width: 620px)').matches) {
      requestAnimationFrame(() => mobileMenuButton.current?.focus());
    }
  }

  function navigateTo(event: ReactMouseEvent<HTMLAnchorElement>, nextPage: PublicPage) {
    event.preventDefault();
    if (nextPage === 'educator-profile') return;
    if (publicPage !== nextPage || educatorProfileId) {
      if (publicPage === 'educator-profile' && nextPage === 'educators') window.history.replaceState({}, '', publicRoutes[nextPage]);
      else window.history.pushState({}, '', publicRoutes[nextPage]);
      setPublicPage(nextPage);
      setEducatorProfileId(null);
    }
    window.scrollTo({ top: 0, behavior: 'auto' });
    closeMobileNav(true);
  }

  function openEducator(event: ReactMouseEvent<HTMLAnchorElement>, educator: PublicEducator) {
    event.preventDefault();
    const path = `/educators/${encodeURIComponent(educator.id)}`;
    window.history.pushState({}, '', path);
    setPublicPage('educator-profile');
    setEducatorProfileId(educator.id);
    window.scrollTo({ top: 0, behavior: 'auto' });
    closeMobileNav(true);
  }

  return <div className={`public-site public-page-${publicPage}`}>
    <a className="public-skip-link" href="#main-content">{t('common.skip')}</a>
    <header className="public-nav">
      <a className="public-brand" href="/" aria-label={t('nav.home')} onClick={(event) => navigateTo(event, 'home')}>
        <span className="public-brand-mark"><GraduationCap size={21} /></span>
        <strong>paath<span>nexus</span><small>AI</small></strong>
      </a>
      <nav id="public-navigation" className={`public-nav-links ${mobileNavOpen ? 'is-open' : ''}`} aria-label={t('nav.public')}>
        <a href="/features" aria-current={publicPage === 'features' ? 'page' : undefined} onClick={(event) => navigateTo(event, 'features')}>{t('nav.platform')}</a>
        <a href="/educators" aria-current={publicPage === 'educators' || publicPage === 'educator-profile' ? 'page' : undefined} onClick={(event) => navigateTo(event, 'educators')}>{t('nav.educators')}</a>
        <a href="/courses" aria-current={publicPage === 'courses' ? 'page' : undefined} onClick={(event) => navigateTo(event, 'courses')}>{t('nav.courses')}</a>
        <a href="/pricing" aria-current={publicPage === 'pricing' ? 'page' : undefined} onClick={(event) => navigateTo(event, 'pricing')}>{t('nav.pricing')}</a>
        <a className="public-mobile-signin" href="#signin" onClick={(event) => { event.preventDefault(); closeMobileNav(); onSignIn(); }}>{t('nav.signIn')}</a>
        <LanguageSwitcher className="public-language-switcher" />
      </nav>
      <div className="public-nav-actions">
        <button className="public-signin" onClick={onSignIn}>{t('nav.signIn')}</button>
        <button className="public-start" onClick={onRegister}>{t('nav.getStarted')} <ArrowRight size={15} /></button>
        <button ref={mobileMenuButton} className="public-menu-toggle" aria-label={mobileNavOpen ? t('nav.closeMenu') : t('nav.openMenu')} aria-expanded={mobileNavOpen} aria-controls="public-navigation" onClick={() => setMobileNavOpen((open) => !open)}>
          {mobileNavOpen ? <X size={19} /> : <Menu size={19} />}
        </button>
      </div>
    </header>

    <main id="main-content" tabIndex={-1}>
    <div className="public-standard-pages">
    <section className="public-hero" id="home">
      <div className="public-hero-copy">
        <span className="public-kicker"><Sparkles size={14} /> {t('common.learningConnected')}</span>
        <h1>{t('home.titleFirst')}<br /><em>{t('home.titleEmphasis')}</em><br />{t('home.titleLast')}</h1>
        <p>{t('home.description')}</p>
        <div className="public-hero-actions">
          <button className="public-primary" onClick={onRegister}>{t('home.createWorkspace')} <ArrowRight size={16} /></button>
        <a href="/features" onClick={(event) => navigateTo(event, 'features')}>{t('home.explore')}</a>
        </div>
        <div className="public-proof">
          <span><Check size={14} /> {t('home.proofAcademic')}</span>
          <span><Check size={14} /> {t('home.proofCourses')}</span>
          <span><Check size={14} /> {t('home.proofFamily')}</span>
        </div>
      </div>
      <div className="public-hero-art" aria-hidden="true">
        <div className="hero-orbit hero-orbit-one" /><div className="hero-orbit hero-orbit-two" />
        <div className="hero-learning-card">
          <div className="hero-card-top"><span className="hero-mini-logo"><GraduationCap size={16} /></span><span>{t('home.learningOverview')}</span><i /></div>
          <div className="hero-card-title">{t('home.progressOnePlace')}</div>
          <div className="hero-progress-track"><i /></div>
          <div className="hero-progress-meta"><span>{t('home.courseProgress')}</span><strong>{t('home.trackLearning')}</strong></div>
          <div className="hero-card-divider" />
          <div className="hero-next-class"><span className="hero-calendar"><CalendarDays size={16} /></span><span><strong>{t('home.classSchedule')}</strong><small>{t('home.upcomingLessons')}</small></span><ArrowRight size={15} /></div>
        </div>
        <div className="hero-float-note"><span><BookOpen size={16} /></span><div><strong>{t('home.teachingTools')}</strong><small>{t('home.teachingToolsList')}</small></div></div>
        <div className="hero-float-spark"><Sparkles size={17} /></div>
      </div>
    </section>

    <section className="public-trust">
      <div><Users size={19} /><span>{t('home.forSchools')}</span></div>
      <div><BookOpen size={19} /><span>{t('home.forLearners')}</span></div>
      <div><ShieldCheck size={19} /><span>{t('home.privateWorkspaces')}</span></div>
    </section>

    <section className="public-features" id="features">
      <div className="public-section-heading"><span className="public-kicker">{t('home.connectedWorkspace')}</span><h2>{t('home.communityTitle')}</h2><p>{t('home.communityDescription')}</p></div>
      <div className="public-feature-grid">
        <article><span className="feature-icon violet"><CalendarDays size={20} /></span><h3>{t('home.academicOps')}</h3><p>{t('home.academicOpsDescription')}</p></article>
        <article><span className="feature-icon blue"><BookOpen size={20} /></span><h3>{t('home.teachAssess')}</h3><p>{t('home.teachAssessDescription')}</p></article>
        <article><span className="feature-icon peach"><Users size={20} /></span><h3>{t('home.connectedLearners')}</h3><p>{t('home.connectedLearnersDescription')}</p></article>
        <article><span className="feature-icon mint"><Sparkles size={20} /></span><h3>{t('home.aiSupport')}</h3><p>{t('home.aiSupportDescription')}</p></article>
      </div>
    </section>

    <section className="public-educators" id="educators">
      <div className="public-educator-copy">
        <span className="public-kicker">{t('home.educatorKicker')}</span>
        <h2>{t('home.educatorTitle')}</h2>
        <p>{t('home.educatorDescription')}</p>
        <ul><li><Check size={15} /> {t('home.publishCourses')}</li><li><Check size={15} /> {t('home.inviteLearners')}</li><li><Check size={15} /> {t('home.followProgress')}</li></ul>
        <button className="public-primary" onClick={onRegister}>{t('home.educatorWorkspace')} <ArrowRight size={16} /></button>
        <small>{t('home.checkoutUnavailable')}</small>
      </div>
      <section className="public-educator-steps" aria-label={t('home.educatorWorkflow')}>
        <article><span>01</span><div><strong>{t('home.stepOne')}</strong><small>{t('home.stepOneDescription')}</small></div></article>
        <article><span>02</span><div><strong>{t('home.stepTwo')}</strong><small>{t('home.stepTwoDescription')}</small></div></article>
        <article><span>03</span><div><strong>{t('home.stepThree')}</strong><small>{t('home.stepThreeDescription')}</small></div></article>
      </section>
    </section>

    <section className="public-educator-directory" id="educator-directory">
      <div className="public-section-heading public-educator-directory-heading">
        <div><span className="public-kicker">{t('home.educatorDirectoryKicker')}</span><h2>{t('home.educatorDirectoryTitle')}</h2><p>{t('home.educatorDirectoryDescription')}</p></div>
        <form className="public-course-search" onSubmit={submitEducatorSearch}><Search size={17} /><input aria-label={t('home.educatorSearchLabel')} placeholder={t('home.educatorSearchPlaceholder')} value={educatorQuery} onChange={(event) => setEducatorQuery(event.target.value)} /><button type="submit">{t('home.searchEducators')}</button></form>
      </div>
      {educatorSearch && !loadingEducators && !educatorError && <div className="public-search-count">{educatorTotal} {t('home.educatorSearchResults')} “{educatorSearch}”</div>}
      {educatorError ? <div className="public-empty" role="alert">{educatorError}<button className="public-retry" onClick={() => void loadEducators(educatorSearch)}>{t('common.tryAgain')}</button></div>
        : loadingEducators && educatorPage === 1 ? <div className="public-empty" role="status">{t('home.loadingEducators')}</div>
          : educators.length ? <div className="public-educator-grid">{educators.map((educator) => <article className="public-educator-card" key={educator.id}>
            <span className="public-educator-avatar" aria-hidden="true">{educator.name.slice(0, 1).toUpperCase()}</span><div className="public-educator-card-copy"><h3>{educator.name}</h3><strong>{educator.title || t('home.educator')}</strong><small>{t('home.educatorAt').replace('{organization}', educator.organization_name || 'PaathNexus')}</small>{educator.bio && <p>{educator.bio}</p>}<a className="public-educator-profile-link" href={`/educators/${encodeURIComponent(educator.id)}`} onClick={(event) => openEducator(event, educator)}>{t('home.viewEducatorProfile')} <ArrowRight size={13} /></a></div>
          </article>)}</div>
            : <div className="public-empty">{educatorSearch ? t('home.noEducators') : t('home.noPublicEducators')}</div>}
      {educatorPage < educatorLastPage && !educatorError && <button className="public-load-more" disabled={loadingEducators} onClick={() => void loadEducators(educatorSearch, educatorPage + 1, true)}>{loadingEducators ? t('home.loadingEducators') : t('home.loadMoreEducators')}</button>}
    </section>

    <section className="public-catalog" id="courses">
      <div className="public-section-heading public-catalog-heading">
        <div><span className="public-kicker">{t('home.discovery')}</span><h2>{t('home.exploreCourses')}</h2><p>{t('home.publicCourseNote')}</p></div>
        <form className="public-course-search" onSubmit={submitSearch}><Search size={17} /><input aria-label={t('home.courseSearchLabel')} placeholder={t('home.courseSearchPlaceholder')} value={query} onChange={(event) => setQuery(event.target.value)} /><button type="submit">{t('home.search')}</button></form>
      </div>
      {search && <div className="public-search-count">{total} {t('home.results')} “{search}”</div>}
      {loadError ? <div className="public-empty" role="alert">{loadError}<button className="public-retry" onClick={() => void loadCourses(search)}>{t('common.tryAgain')}</button></div>
        : loadingCourses && page === 1 ? <div className="public-empty" role="status">{t('home.loadingCourses')}</div>
          : courses.length ? <div className="public-course-grid">{courses.map((course) => <article className="public-course-card" key={course.id}>
            <div className="public-course-art"><span>{(course.subject || course.title).slice(0, 1).toUpperCase()}</span><small>{course.subject || t('home.learningCourse')}</small></div>
            <div className="public-course-info"><small>{course.organization_name || t('home.educator')}</small><h3>{course.title}</h3><p>{course.description || t('home.publicCourseDescription')}</p>
              {!!course.instructors.length && <small className="public-course-instructors">{t('home.instructors')}: {course.instructors.map((instructor) => instructor.name).join(', ')}</small>}
              <div><span>{course.lesson_count} {t('home.lessons')} · {coursePrice(course.amount_paise, locale, t('home.free'))}</span><button onClick={(event) => openCourse(event, course)}>{t('home.viewCourse')} <ArrowRight size={13} /></button></div>
            </div>
          </article>)}</div>
            : <div className="public-empty">{search ? t('home.noSearchResults') : t('home.noCourses')}</div>}
      {page < lastPage && !loadError && <button className="public-load-more" disabled={loadingCourses} onClick={() => void loadCourses(search, page + 1, true)}>{loadingCourses ? t('home.loadingMore') : t('home.loadMore')}</button>}
    </section>

    <section className="public-pricing" id="pricing">
      <div className="public-section-heading"><span className="public-kicker">{t('home.plans')}</span><h2>{t('home.choosePlan')}</h2><p>{t('home.planDescription')}</p></div>
      {loadingPlans ? <div className="public-empty" role="status">{t('home.loadingPlans')}</div>
        : planError ? <div className="public-empty" role="alert">{planError}<button className="public-retry" onClick={() => void loadPlans()}>{t('common.tryAgain')}</button></div>
          : plans.length ? <div className="public-plan-grid">{plans.map((plan) => <article className="public-plan-card" key={plan.id}>
            <span>{plan.audience === 'educator' ? t('home.forEducators') : plan.audience === 'institution' ? t('home.forInstitutions') : t('home.flexibleAccess')}</span><h3>{plan.name}</h3><p>{plan.description || t('home.workspaceAccess')}</p><strong>{price(plan, locale, t)}</strong>
            <ul>{Object.entries(plan.entitlements ?? {}).filter(([, value]) => value !== null).map(([key, value]) => <li key={key}><Check size={14} />{key.replaceAll('_', ' ')}: {value}</li>)}</ul><button onClick={onRegister}>{t('nav.getStarted')} <ArrowRight size={14} /></button>
          </article>)}</div>
            : <div className="public-empty">{t('home.noPlans')}</div>}
    </section>

    <section className="public-final-cta"><div><span className="public-kicker">PAATHNEXUS AI</span><h2>{t('home.finalTitle')}</h2><p>{t('home.finalDescription')}</p></div><button className="public-primary" onClick={onRegister}>{t('home.createWorkspace')} <ArrowRight size={16} /></button></section>
    </div>
    {publicPage === 'educator-profile' && <section className="public-educator-profile" aria-labelledby="educator-profile-title">
      <a className="public-educator-back" href="/educators" onClick={(event) => navigateTo(event, 'educators')}>← {t('home.backToEducators')}</a>
      {loadingEducatorProfile ? <div className="public-empty" role="status">{t('home.loadingEducatorProfile')}</div>
        : educatorProfileError || !educatorProfile ? <div className="public-empty" role="alert">{t('home.educatorProfileUnavailable')}</div>
          : <article className="public-educator-profile-card">
            <span className="public-educator-profile-avatar" aria-hidden="true">{educatorProfile.name.slice(0, 1).toUpperCase()}</span>
            <span className="public-kicker">{t('home.educatorProfileKicker')}</span>
            <h1 id="educator-profile-title">{educatorProfile.name}</h1>
            {educatorProfile.title && <h2>{educatorProfile.title}</h2>}
            {educatorProfile.organization_name && <p className="public-educator-profile-organization">{t('home.educatorAt').replace('{organization}', educatorProfile.organization_name)}</p>}
            {educatorProfile.bio && <p className="public-educator-profile-bio">{educatorProfile.bio}</p>}
            <p className="public-educator-profile-privacy">{t('home.educatorProfilePrivacy')}</p>
          </article>}
    </section>}
    </main>
    <footer className="public-footer"><a className="public-brand" href="/" onClick={(event) => navigateTo(event, 'home')}><span className="public-brand-mark"><GraduationCap size={19} /></span><strong>paath<span>nexus</span><small>AI</small></strong></a><span>{t('home.tagline')}</span><a href="mailto:support@paathnexus.com">{t('home.contact')}</a><small>© 2026 PaathNexus AI</small></footer>

    {selectedCourse && <div className="public-course-dialog-backdrop" onMouseDown={(event) => { if (event.target === event.currentTarget) setSelectedCourse(null); }}>
      <section ref={courseDialog} className="public-course-dialog" role="dialog" aria-modal="true" aria-labelledby="public-course-dialog-title">
        <button ref={closeCourseButton} className="public-course-dialog-close" aria-label={t('home.closeCourseDetails')} onClick={() => setSelectedCourse(null)}><X size={19} /></button>
        <span className="public-kicker">{t('home.publicCourse')}</span><h2 id="public-course-dialog-title">{selectedCourse.title}</h2>
        <p className="public-course-dialog-educator">{selectedCourse.organization_name || 'PaathNexus educator'}{selectedCourse.subject ? ` · ${selectedCourse.subject}` : ''}</p>
        <p>{selectedCourse.description || t('home.courseDetailsMissing')}</p>
        {!!selectedCourse.instructors.length && <div className="public-instructor-list"><strong>{selectedCourse.instructors.length > 1 ? t('home.meetEducators') : t('home.meetEducator')}</strong>{selectedCourse.instructors.map((instructor) => <article key={instructor.name}><strong>{instructor.name}</strong>{instructor.title && <span>{instructor.title}</span>}{instructor.bio && <p>{instructor.bio}</p>}</article>)}</div>}
        <div className="public-course-dialog-facts"><span><BookOpen size={16} /> {selectedCourse.lesson_count} {t('home.lessons')}</span><span>{coursePrice(selectedCourse.amount_paise, locale, t('home.free'))}</span><span>{t('home.code')} {selectedCourse.code}</span></div>
        {selectedCourse.amount_paise > 0 && <p className="public-checkout-note">{t('home.paidCheckoutUnavailable')}</p>}
        <div className="public-course-dialog-actions"><button className="public-primary" onClick={onRegister}>{t('home.askAccess')} <ArrowRight size={15} /></button><button className="public-signin" onClick={onSignIn}>{t('home.alreadyAccount')}</button></div>
      </section>
    </div>}
  </div>;
}
