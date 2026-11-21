import { useEffect, useState } from 'react';
import { ArrowRight, Check, Circle, RotateCcw } from 'lucide-react';
import { listAcademicYears, listClasses, listOrganizationInvitations, type Organization } from './lib/auth';
import { useLocale } from './lib/locale';
import './onboarding.css';

type SetupSteps = { academicYear: boolean; firstClass: boolean; invitedTeam: boolean };

export function WorkspaceOnboarding({ token, organization, onNavigate }: {
  token: string;
  organization: Organization;
  onNavigate: (page: string) => void;
}) {
  const { t } = useLocale();
  const storageKey = `paathnexus.onboarding.${organization.id}`;
  const [reviewed, setReviewed] = useState(() => localStorage.getItem(`${storageKey}.reviewed`) === 'true');
  const [dismissed, setDismissed] = useState(() => localStorage.getItem(`${storageKey}.dismissed`) === 'true');
  const [steps, setSteps] = useState<SetupSteps>({ academicYear: false, firstClass: false, invitedTeam: false });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  async function refresh() {
    setLoading(true);
    setError('');
    try {
      const [years, classes, invitations] = await Promise.all([
        listAcademicYears(token, organization.id),
        listClasses(token, organization.id),
        listOrganizationInvitations(token, organization.id),
      ]);
      setSteps({
        academicYear: years.some((year) => year.status === 'active'),
        firstClass: classes.length > 0,
        invitedTeam: invitations.some((invitation) => invitation.status === 'pending' || invitation.status === 'accepted'),
      });
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : t('onboarding.loadError'));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { void refresh(); }, [token, organization.id]);

  const completed = Number(reviewed) + Number(steps.academicYear) + Number(steps.firstClass) + Number(steps.invitedTeam);
  const total = 4;
  const rows = [
    { key: 'profile', title: t('onboarding.profile'), description: t('onboarding.profileDescription'), done: reviewed, page: 'Settings', action: t('onboarding.openSettings') },
    { key: 'year', title: t('onboarding.year'), description: t('onboarding.yearDescription'), done: steps.academicYear, page: 'Schedule', action: t('onboarding.openSchedule') },
    { key: 'class', title: t('onboarding.class'), description: t('onboarding.classDescription'), done: steps.firstClass, page: 'My classes', action: t('onboarding.openClasses') },
    { key: 'team', title: t('onboarding.team'), description: t('onboarding.teamDescription'), done: steps.invitedTeam, page: 'Students', action: t('onboarding.invitePeople') },
  ];

  function setReviewedState() {
    localStorage.setItem(`${storageKey}.reviewed`, 'true');
    setReviewed(true);
  }

  function setDismissedState(value: boolean) {
    localStorage.setItem(`${storageKey}.dismissed`, String(value));
    setDismissed(value);
  }

  if (dismissed) return <section className="panel onboarding-resume-panel" aria-label={t('onboarding.title')}>
    <span>{t('onboarding.paused')}</span>
    <button type="button" onClick={() => setDismissedState(false)}><RotateCcw size={14} /> {t('onboarding.resume')}</button>
  </section>;

  return <section className="panel onboarding-panel" aria-labelledby="workspace-onboarding-title">
    <div className="onboarding-heading">
      <div><div className="eyebrow">{t('onboarding.eyebrow')}</div><h2 id="workspace-onboarding-title">{t('onboarding.title')}</h2><p>{t('onboarding.description')}</p></div>
      <div className="onboarding-progress" role="progressbar" aria-label={t('onboarding.progress')} aria-valuemin={0} aria-valuemax={total} aria-valuenow={completed}>
        <strong>{t('onboarding.completed').replace('{completed}', String(completed)).replace('{total}', String(total))}</strong><span><i style={{ width: `${completed / total * 100}%` }} /></span>
      </div>
    </div>
    {loading ? <p className="onboarding-message" role="status">{t('onboarding.loading')}</p> : <ol className="onboarding-steps">
      {rows.map((step) => <li className={step.done ? 'onboarding-step is-complete' : 'onboarding-step'} key={step.key}>
        <span className="onboarding-step-marker" aria-hidden="true">{step.done ? <Check size={15} /> : <Circle size={16} />}</span>
        <div className="onboarding-step-copy"><strong>{step.title}</strong><small>{step.description}</small></div>
        {step.key === 'profile' && !step.done
          ? <span className="onboarding-step-actions"><button type="button" className="onboarding-action-secondary" onClick={() => setReviewedState()}>{t('onboarding.markReviewed')}</button><button type="button" className="onboarding-action" onClick={() => onNavigate(step.page)}>{step.action} <ArrowRight size={14} /></button></span>
          : step.done ? <span className="onboarding-done">{t('onboarding.done')}</span>
            : <button type="button" className="onboarding-action" onClick={() => onNavigate(step.page)}>{step.action} <ArrowRight size={14} /></button>}
      </li>)}
    </ol>}
    {error && <div className="onboarding-error" role="alert">{error}<button type="button" onClick={() => void refresh()}>{t('onboarding.retry')}</button></div>}
    <div className="onboarding-footer"><span>{completed === total ? t('onboarding.complete') : t('onboarding.returnHint')}</span><button type="button" className="onboarding-dismiss" onClick={() => setDismissedState(true)}>{t('onboarding.dismiss')}</button></div>
  </section>;
}
