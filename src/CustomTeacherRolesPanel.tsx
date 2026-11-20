import { useEffect, useState, type FormEvent } from 'react';
import {
  assignCustomTeacherRole, deleteCustomTeacherRole, listCustomTeacherRoles, restoreCustomTeacherRole, saveCustomTeacherRole,
  type CustomTeacherRole, type CustomRoleMember, type CustomBaseRole, type Organization,
} from './lib/auth';

const permissionLabels: Record<string, string> = {
  'organization.view': 'View the workspace',
  'organization.update': 'Update workspace settings',
  'members.view': 'View workspace members',
  'members.manage': 'Manage workspace members',
  'academic.view': 'View classes and academic records',
  'academic.manage': 'Manage classes and academic records',
  'attendance.read': 'View attendance',
  'attendance.write': 'Record attendance',
  'courses.view': 'View courses and lessons',
  'assessments.take': 'Take assessments',
  'results.view': 'View quiz and assessment results',
  'fees.view': 'View invoices for this account',
  'students.view': 'View linked student summaries',
  'tasks.view': 'View staff tasks',
  'tasks.manage': 'Create and manage staff tasks',
  'courses.manage': 'Manage assigned courses',
  'assessments.manage': 'Manage assessments',
  'reports.view': 'View academic reports',
  'live_sessions.view': 'View and join live sessions',
  'live_sessions.manage': 'Schedule and manage live sessions',
};

export default function CustomTeacherRolesPanel({ token, organization }: { token: string; organization: Organization }) {
  const [roles, setRoles] = useState<CustomTeacherRole[]>([]);
  const [members, setMembers] = useState<CustomRoleMember[]>([]);
  const [permissionsByRole, setPermissionsByRole] = useState<Record<CustomBaseRole, string[]>>({
    admin: ['organization.view', 'members.view', 'academic.view', 'courses.view'],
    teacher: ['organization.view', 'academic.view', 'attendance.read', 'courses.view'],
    student: ['organization.view', 'courses.view', 'results.view'],
    guardian: ['organization.view', 'students.view', 'attendance.read', 'results.view', 'courses.view', 'fees.view'],
    educator: ['organization.view', 'members.view', 'academic.view', 'courses.view'],
  });
  const [baseRole, setBaseRole] = useState<CustomBaseRole>('teacher');
  const [name, setName] = useState('');
  const [selected, setSelected] = useState<string[]>(permissionsByRole.teacher);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const requiredCorePermissions = baseRole === 'teacher'
    ? ['organization.view', 'academic.view', 'attendance.read', 'courses.view']
    : baseRole === 'student' ? ['organization.view', 'courses.view', 'results.view']
      : baseRole === 'guardian' ? ['organization.view', 'students.view'] : ['organization.view'];
  const permissionDependencies: Record<string, string[]> = {
    'academic.manage': ['academic.view'],
    'attendance.write': ['attendance.read', 'academic.view'],
    'reports.view': ['academic.view', 'attendance.read'],
    'tasks.manage': ['tasks.view'],
    'live_sessions.manage': ['live_sessions.view'],
  };
  const requiredPermissions = [...new Set([
    ...requiredCorePermissions,
    ...Object.entries(permissionDependencies).flatMap(([permission, dependencies]) => selected.includes(permission) ? dependencies : []),
  ])];

  async function refresh() {
    setLoading(true);
    setError('');
    try {
      const result = await listCustomTeacherRoles(token, organization.id);
      setRoles(result.roles);
      setMembers(result.members);
      setPermissionsByRole(result.permissions_by_role);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Could not load custom roles.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { void refresh(); }, [token, organization.id]);

  function resetEditor() {
    setEditingId(null);
    setBaseRole('teacher');
    setName('');
    setSelected(permissionsByRole.teacher);
  }

  function edit(role: CustomTeacherRole) {
    setEditingId(role.id);
    setBaseRole(role.base_role);
    setName(role.name);
    setSelected(role.permissions);
    setNotice('');
    setError('');
  }

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSaving(true);
    setError('');
    setNotice('');
    try {
      await saveCustomTeacherRole(token, organization.id, { id: editingId ?? undefined, base_role: baseRole, name: name.trim(), permissions: selected });
      await refresh();
      resetEditor();
      setNotice(editingId ? 'Custom role updated.' : 'Custom role created.');
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Could not save this custom role.');
    } finally {
      setSaving(false);
    }
  }

  async function remove(role: CustomTeacherRole) {
    if (!window.confirm(`Archive “${role.name}”? Existing assignments keep these permissions until you restore or unassign the role.`)) return;
    setSaving(true);
    setError('');
    setNotice('');
    try {
      await deleteCustomTeacherRole(token, organization.id, role.id);
      if (editingId === role.id) resetEditor();
      await refresh();
      setNotice('Role archived. Existing assignments keep these permissions until you restore or unassign the role.');
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Could not archive this role.');
    } finally {
      setSaving(false);
    }
  }

  async function restore(role: CustomTeacherRole) {
    setSaving(true);
    setError('');
    setNotice('');
    try {
      await restoreCustomTeacherRole(token, organization.id, role.id);
      await refresh();
      setNotice('Role restored. Assign it to members when ready.');
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Could not restore this role.');
    } finally {
      setSaving(false);
    }
  }

  async function assign(memberId: number, roleId: string) {
    setSaving(true);
    setError('');
    setNotice('');
    try {
      await assignCustomTeacherRole(token, organization.id, memberId, roleId || null);
      await refresh();
      setNotice('Member permissions updated.');
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Could not update this member’s role.');
    } finally {
      setSaving(false);
    }
  }

  return <section className="panel academic-panel custom-roles-panel">
    <div className="panel-heading"><div><div className="eyebrow">MEMBER ACCESS</div><h2>Custom member roles</h2><p>Create workspace-specific permission profiles for active admins, educators, teachers, students, and guardians. Each profile can only narrow that member type’s standard capabilities. Owners keep full control and cannot be assigned a custom role.</p></div></div>
    {error && <div className="academic-error" role="alert">{error}</div>}
    {notice && <div className="task-message" role="status">{notice}</div>}

    <form className="academic-form custom-role-editor" onSubmit={(event) => void submit(event)}>
      <strong>{editingId ? 'Edit custom role' : 'Create a custom role'}</strong>
      <label>Member type<select value={baseRole} disabled={Boolean(editingId)} onChange={(event) => { const next = event.target.value as CustomBaseRole; setBaseRole(next); setSelected(permissionsByRole[next]); }}><option value="admin">Admin</option><option value="teacher">Teacher</option><option value="student">Student</option><option value="guardian">Guardian</option><option value="educator">Educator</option></select></label>
      <label>Role name<input required maxLength={80} value={name} onChange={(event) => setName(event.target.value)} placeholder="e.g. Attendance assistant" /></label>
      <fieldset className="custom-role-permissions"><legend>Permissions</legend>
        {permissionsByRole[baseRole].map((permission) => <label key={permission}>
          <input type="checkbox" checked={selected.includes(permission)} disabled={requiredPermissions.includes(permission)} onChange={(event) => setSelected((current) => event.target.checked ? [...current, permission] : current.filter((item) => item !== permission))} />
          <span>{permissionLabels[permission] ?? permission}</span>
        </label>)}
        <small className="form-hint">Core access is required, and management permissions keep the read access needed to use those workflows. Unchecked actions are denied by the API. Educator custom profiles use explicit capabilities; they do not inherit the educator role’s wildcard access.</small>
      </fieldset>
      <div className="task-form-actions"><button type="button" className="task-cancel" onClick={resetEditor} disabled={saving}>{editingId ? 'Cancel edit' : 'Clear'}</button><button className="task-submit" disabled={saving || !name.trim() || requiredPermissions.some((permission) => !selected.includes(permission))}>{saving ? 'Saving…' : editingId ? 'Save role' : 'Create role'}</button></div>
    </form>

    <div className="custom-role-list"><div className="course-progress-heading"><strong>Workspace roles</strong><span>{roles.length}</span></div>
      {loading ? <div className="task-message">Loading member roles…</div> : roles.length ? roles.map((role) => <article className="custom-role-card" key={role.id}>
        <div><strong>{role.name}{role.archived_at ? ' · Archived' : ''}</strong><small>{role.base_role} · {role.member_count ?? 0} assigned · {role.permissions.length} permissions</small></div>
        <div className="custom-role-actions">{role.archived_at ? <button type="button" className="task-submit" disabled={saving} onClick={() => void restore(role)}>Restore</button> : <><button type="button" className="task-cancel" disabled={saving} onClick={() => edit(role)}>Edit</button><button type="button" className="invoice-void" disabled={saving} onClick={() => void remove(role)}>Archive</button></>}</div>
      </article>) : <div className="academic-empty">No custom member roles yet.</div>}
    </div>

    <div className="custom-role-list"><div className="course-progress-heading"><strong>Assign roles to members</strong><span>{members.length} active</span></div>
      {loading ? <div className="task-message">Loading members…</div> : members.length ? members.map((member) => {
        const assignedRole = roles.find((role) => role.id === member.custom_role_id);
        return <label className="custom-role-member" key={member.id}><span><strong>{member.name}</strong><small>{assignedRole ? `${assignedRole.name}${assignedRole.archived_at ? ' · Archived; permissions retained' : ''}` : `Standard ${member.base_role}`}</small></span><select aria-label={`Custom role for ${member.name}`} disabled={saving} value={member.custom_role_id ?? ''} onChange={(event) => void assign(member.id, event.target.value)}><option value="">Standard {member.base_role}</option>{assignedRole?.archived_at && <option value={assignedRole.id} disabled>Archived: {assignedRole.name}</option>}{roles.filter((role) => !role.archived_at && role.base_role === member.base_role).map((role) => <option key={role.id} value={role.id}>{role.name}</option>)}</select></label>;
      }) : <div className="academic-empty">No active admin, educator, teacher, student, or guardian memberships to assign.</div>}
    </div>
  </section>;
}
