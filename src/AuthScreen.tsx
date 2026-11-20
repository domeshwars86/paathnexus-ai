import { useEffect, useState, type FormEvent } from 'react';
import { ArrowRight, BookOpen, Check, Eye, EyeOff, GraduationCap, LockKeyhole, Mail, Sparkles, Users } from 'lucide-react';
import { acceptOrganizationInvitation, ApiError, currentSession, register, registerWithInvitation, requestPasswordReset, resendEmailVerification, resetPassword, signIn, signOut, type Session } from './lib/auth';
import PublicHome from './PublicHome';
import { LanguageSwitcher, useLocale } from './lib/locale';

type Props = { onAuthenticated: (session: Session) => void };
type Mode = 'home' | 'login' | 'register' | 'invitation' | 'forgot' | 'reset' | 'verify';
const emailVerificationEnabled = import.meta.env.VITE_REQUIRE_EMAIL_VERIFICATION === 'true';

export default function AuthScreen({ onAuthenticated }: Props) {
  const { locale, t } = useLocale();
  const invitationToken = new URLSearchParams(window.location.search).get('invitation') || '';
  const resetParams = new URLSearchParams(window.location.search);
  const resetToken = resetParams.get('token') || '';
  const verifiedLanding = resetParams.get('verified') === '1';
  const [mode, setMode] = useState<Mode>(invitationToken ? 'invitation' : resetToken && resetParams.get('email') ? 'reset' : verifiedLanding ? 'login' : 'home');
  const [showPassword, setShowPassword] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState(verifiedLanding ? t('auth.verifiedNotice') : '');
  const [form, setForm] = useState({ name: '', email: resetParams.get('email') || '', password: '', password_confirmation: '', organization_name: '', organization_type: 'school' });

  useEffect(() => {
    document.documentElement.lang = locale === 'hi' ? 'hi-IN' : 'en';
  }, [locale]);

  function update(key: keyof typeof form, value: string) {
    setForm((values) => ({ ...values, [key]: value }));
  }

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    setError('');
    try {
      if (mode === 'forgot') {
        await requestPasswordReset(form.email);
        setNotice(t('auth.resetSentNotice'));
        return;
      }
      if (mode === 'verify') {
        await resendEmailVerification(form.email);
        setNotice(t('auth.resendNotice'));
        return;
      }
      if (mode === 'reset') {
        await resetPassword({ email: form.email, token: resetToken, password: form.password, password_confirmation: form.password_confirmation });
        setNotice(t('auth.passwordResetNotice'));
        setMode('login');
        window.history.replaceState({}, '', window.location.pathname);
        return;
      }
      const session = mode === 'invitation'
        ? await registerWithInvitation({ token: invitationToken, name: form.name, password: form.password, password_confirmation: form.password_confirmation })
        : mode === 'login'
          ? await signIn(form.email, form.password)
          : await register(form);
      if ('verification_required' in session) {
        if (!emailVerificationEnabled) {
          setMode('login');
          setError(t('auth.localSettingsError'));
          return;
        }
        setForm((values) => ({ ...values, email: session.email }));
        setMode('verify');
        setNotice(t('auth.waitingVerification'));
        return;
      }
      if (mode === 'invitation') window.history.replaceState({}, '', window.location.pathname);
      if (mode === 'login' && invitationToken) {
        try { await acceptOrganizationInvitation(session.token, invitationToken); }
        catch (cause) { await signOut(session.token); throw cause; }
        const latest = await currentSession(session.token);
        onAuthenticated({ ...latest, token: session.token });
        window.history.replaceState({}, '', window.location.pathname);
        return;
      }
      if (!session.organizations.length) {
        await signOut(session.token);
      throw new ApiError(t('auth.noWorkspaceError'), 403);
      }
      onAuthenticated(session);
    } catch (caught) {
      const caughtMessage = caught instanceof Error ? caught.message : '';
      setError(caughtMessage.toLowerCase().includes('provided credentials') ? t('auth.invalidCredentials') : caughtMessage || t('auth.genericError'));
      if (caught instanceof ApiError && caught.message.toLowerCase().includes('verify your email')) {
        if (emailVerificationEnabled) {
          setMode('verify');
          setNotice(t('auth.verifyBeforeSignIn'));
        } else {
          setMode('login');
          setNotice('');
          setError(t('auth.localSettingsError'));
        }
      }
    } finally {
      setBusy(false);
    }
  }

  function changeMode(next: Mode) {
    setMode(next);
    setError('');
    setNotice('');
  }

  if (mode === 'home') return <PublicHome onSignIn={() => changeMode('login')} onRegister={() => changeMode('register')} />;

  return <main className="auth-shell">
    <aside className="auth-story">
      <div className="auth-brand"><span className="brand-mark"><GraduationCap size={21} strokeWidth={2.4} /></span><span>paath<span className="brand-light">nexus</span><small>AI</small></span></div>
      <div className="story-content"><span className="story-kicker"><Sparkles size={13} /> {t('common.learningConnected')}</span><h1>{t('auth.storyTitle')}</h1><p>{t('auth.storyDescription')}</p><div className="story-features"><span><Check size={14} /> {t('auth.storyFeatureOne')}</span><span><Check size={14} /> {t('auth.storyFeatureTwo')}</span><span><Check size={14} /> {t('auth.storyFeatureThree')}</span></div></div>
      <div className="story-footer">{t('auth.storyFooter')}</div><div className="story-orbit orbit-a"/><div className="story-orbit orbit-b"/><div className="story-orbit orbit-c"/>
    </aside>
    <section className="auth-main"><div className="auth-mobile-brand"><span className="brand-mark"><GraduationCap size={20} /></span><strong>paath<span>nexus</span><small>AI</small></strong></div><div className="auth-card-wrap"><div className="auth-language-row"><LanguageSwitcher /></div><div className="auth-heading"><div className="auth-icon">{mode === 'login' ? <LockKeyhole size={18} /> : <Users size={18} />}</div><span className="auth-overline">{t('auth.workspaceOverline')}</span><h2>{mode === 'login' ? t('auth.welcomeBack') : mode === 'invitation' ? t('auth.joinWorkspace') : mode === 'forgot' ? t('auth.resetPassword') : mode === 'reset' ? t('auth.choosePassword') : mode === 'verify' ? t('auth.verifyEmail') : t('auth.createWorkspace')}</h2><p>{mode === 'login' ? t('auth.signInDescription') : mode === 'invitation' ? t('auth.invitationDescription') : mode === 'forgot' ? t('auth.forgotDescription') : mode === 'reset' ? t('auth.resetDescription') : mode === 'verify' ? t('auth.verifyDescription') : t('auth.registerDescription')}</p></div>
      {mode === 'invitation' ? <div className="auth-tabs"><span>{t('auth.workspaceInvitation')}</span></div> : mode === 'forgot' || mode === 'reset' || mode === 'verify' ? <div className="auth-tabs"><button type="button" onClick={() => changeMode('login')}>{t('auth.backToSignIn')}</button></div> : <div className="auth-tabs" role="group" aria-label={t('auth.accountOptions')}><button type="button" aria-pressed={mode === 'login'} className={mode === 'login' ? 'selected' : ''} onClick={() => changeMode('login')}>{t('nav.signIn')}</button><button type="button" aria-pressed={mode === 'register'} className={mode === 'register' ? 'selected' : ''} onClick={() => changeMode('register')}>{t('auth.createAccount')}</button></div>}
      <form className="auth-form" onSubmit={submit}>
        {(mode === 'register' || mode === 'invitation') && <label>{t('auth.fullName')}<input autoComplete="name" required maxLength={120} placeholder={t('auth.nameExample')} value={form.name} onChange={(event) => update('name', event.target.value)} /></label>}
        {mode === 'register' && <><label>{t('auth.organizationName')}<input autoComplete="organization" required maxLength={160} placeholder={t('auth.organizationExample')} value={form.organization_name} onChange={(event) => update('organization_name', event.target.value)} /></label><label>{t('auth.organizationType')}<select value={form.organization_type} onChange={(event) => update('organization_type', event.target.value)}><option value="school">{t('auth.school')}</option><option value="college">{t('auth.college')}</option><option value="coaching">{t('auth.coaching')}</option><option value="academy">{t('auth.academy')}</option><option value="educator">{t('auth.independentEducator')}</option></select></label></>}
        {mode !== 'invitation' && <label>{t('auth.email')}<span className="input-wrap"><Mail size={15}/><input autoComplete="email" type="email" required maxLength={254} placeholder="you@school.edu" value={form.email} onChange={(event) => update('email', event.target.value)} /></span></label>}
        {mode !== 'forgot' && mode !== 'verify' && <label>{t('auth.password')}<span className="input-wrap"><LockKeyhole size={15}/><input aria-label={t('auth.password')} autoComplete={mode === 'login' ? 'current-password' : 'new-password'} type={showPassword ? 'text' : 'password'} required minLength={mode === 'login' ? 1 : 12} value={form.password} onChange={(event) => update('password', event.target.value)} placeholder={mode === 'login' ? t('auth.passwordPlaceholder') : t('auth.passwordRule')} /><button type="button" aria-label={showPassword ? t('auth.hidePassword') : t('auth.showPassword')} onClick={() => setShowPassword((visible) => !visible)}>{showPassword ? <EyeOff size={15}/> : <Eye size={15}/>}</button></span></label>}
        {(mode === 'register' || mode === 'invitation' || mode === 'reset') && <label>{t('auth.confirmPassword')}<span className="input-wrap"><LockKeyhole size={15}/><input autoComplete="new-password" type={showPassword ? 'text' : 'password'} required minLength={12} value={form.password_confirmation} onChange={(event) => update('password_confirmation', event.target.value)} placeholder={t('auth.passwordAgain')} /></span></label>}
        {error && <div className="auth-error" role="alert">{error}</div>}
        {notice && <div className="auth-success" role="status">{notice}</div>}
        <button className="auth-submit" type="submit" disabled={busy}>{busy ? t('auth.wait') : mode === 'forgot' ? t('auth.sendReset') : mode === 'verify' ? t('auth.resendVerification') : mode === 'reset' ? t('auth.reset') : mode === 'login' ? invitationToken ? t('auth.signInAccept') : t('auth.signInWorkspace') : mode === 'invitation' ? t('auth.createAccountJoin') : t('auth.createWorkspaceButton')} {!busy && <ArrowRight size={16}/>}</button>
      </form>{mode === 'login' && <button className="auth-invite-login" type="button" onClick={() => changeMode('forgot')}>{t('auth.forgot')}</button>}{mode === 'invitation' && <button className="auth-invite-login" type="button" onClick={() => changeMode('login')}>{t('auth.existingAccount')}</button>}<div className="auth-trust"><BookOpen size={14}/><span>{t('auth.trust')}</span></div><div className="auth-legal">{t('auth.legal')}</div></div><footer className="auth-footer"><span>© 2026 PaathNexus AI</span><span>{t('auth.needHelp')} <a href="mailto:support@paathnexus.com">{t('auth.contact')}</a></span></footer></section>
  </main>;
}
