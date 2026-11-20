import { lazy, Suspense, useEffect, useState } from 'react';
import { GraduationCap } from 'lucide-react';
import { currentSession, savedOrganizationId, savedToken, saveOrganizationId, signOut, type Session } from './lib/auth';
import { LocaleProvider } from './lib/locale';

const AuthScreen = lazy(() => import('./AuthScreen'));
const WorkspaceApp = lazy(() => import('./WorkspaceApp'));

export default function App() {
  return <LocaleProvider><AppContent /></LocaleProvider>;
}

function AppContent() {
  const [session, setSession] = useState<Session | null>(null);
  const [activeOrganizationId, setActiveOrganizationId] = useState<string | null>(() => savedOrganizationId());
  const [checking, setChecking] = useState(true);

  useEffect(() => {
    if (session) document.documentElement.lang = 'en';
  }, [session]);

  useEffect(() => {
    const token = savedToken();
    if (!token) {
      setChecking(false);
      return;
    }

    currentSession(token)
      .then((current) => setSession({ ...current, token }))
      .catch(() => signOut(token))
      .finally(() => setChecking(false));
  }, []);

  useEffect(() => {
    if (!session) return;
    const stillMember = session.organizations.some((organization) => organization.id === activeOrganizationId);
    if (!stillMember) {
      const firstOrganization = session.organizations[0]?.id ?? null;
      setActiveOrganizationId(firstOrganization);
      saveOrganizationId(firstOrganization);
    }
  }, [session, activeOrganizationId]);

  if (checking) return <div className="auth-loading"><span className="loading-mark"><GraduationCap size={20} /></span><span>Opening your workspace…</span></div>;
  if (!session) return <Suspense fallback={<div className="auth-loading">Loading PaathNexus…</div>}><AuthScreen onAuthenticated={(next) => { setSession(next); setActiveOrganizationId(next.organizations[0]?.id ?? null); saveOrganizationId(next.organizations[0]?.id ?? null); }} /></Suspense>;

  const activeOrganization = session.organizations.find((organization) => organization.id === activeOrganizationId) ?? session.organizations[0];
  if (!activeOrganization) return <Suspense fallback={<div className="auth-loading">Loading PaathNexus…</div>}><AuthScreen onAuthenticated={setSession} /></Suspense>;

  return <Suspense fallback={<div className="auth-loading">Loading your workspace…</div>}><WorkspaceApp
    token={session.token}
    user={session.user}
    organization={activeOrganization}
    organizations={session.organizations}
    onSwitchOrganization={(id) => { setActiveOrganizationId(id); saveOrganizationId(id); }}
    onOrganizationUpdated={(updated) => setSession((current) => current ? { ...current, organizations: current.organizations.map((item) => item.id === updated.id ? updated : item) } : current)}
    onUserUpdated={(updated) => setSession((current) => current ? { ...current, user: updated } : current)}
    onLogout={() => void signOut(session.token).finally(() => setSession(null))}
  /></Suspense>;
}
