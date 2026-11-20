import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CustomTeacherRolesPanel from '../src/CustomTeacherRolesPanel';
import type { CustomTeacherRoles, Organization } from '../src/lib/auth';

const auth = vi.hoisted(() => ({
  list: vi.fn(), save: vi.fn(), assign: vi.fn(), archive: vi.fn(), restore: vi.fn(),
}));

vi.mock('../src/lib/auth', () => ({
  listCustomTeacherRoles: auth.list,
  saveCustomTeacherRole: auth.save,
  assignCustomTeacherRole: auth.assign,
  deleteCustomTeacherRole: auth.archive,
  restoreCustomTeacherRole: auth.restore,
}));

const permissionsByRole = {
  admin: ['organization.view', 'members.view', 'members.manage', 'academic.view', 'academic.manage', 'courses.manage'],
  teacher: ['organization.view', 'academic.view', 'attendance.read', 'courses.view', 'tasks.view'],
  student: ['organization.view', 'courses.view', 'results.view', 'attendance.read', 'fees.view', 'assessments.take'],
  guardian: ['organization.view', 'students.view', 'attendance.read', 'results.view', 'courses.view', 'fees.view'],
  educator: ['organization.view', 'members.view', 'members.manage', 'academic.view', 'academic.manage', 'courses.view', 'courses.manage', 'tasks.view', 'tasks.manage'],
};

const organization: Organization = {
  id: 'org-1', name: 'Sample School', slug: 'sample-school', type: 'school', role: 'owner', locale: 'en', timezone: 'Asia/Kolkata',
};

function response(overrides: Partial<CustomTeacherRoles> = {}): CustomTeacherRoles {
  return { base_roles: ['admin', 'teacher', 'student', 'guardian', 'educator'], permissions_by_role: permissionsByRole, roles: [], members: [], ...overrides };
}

describe('CustomTeacherRolesPanel', () => {
  beforeEach(() => {
    auth.list.mockResolvedValue(response());
    auth.save.mockResolvedValue({ id: 'new-role', name: 'Family summaries', base_role: 'guardian', permissions: ['organization.view', 'students.view'] });
    auth.assign.mockResolvedValue(undefined);
    auth.archive.mockResolvedValue(undefined);
    auth.restore.mockResolvedValue(undefined);
  });

  it('creates a narrowed guardian profile while keeping its required capabilities', async () => {
    const user = userEvent.setup();
    render(<CustomTeacherRolesPanel token="session-token" organization={organization} />);

    await screen.findByText('No custom member roles yet.');
    await user.selectOptions(screen.getByLabelText('Member type'), 'guardian');
    await user.type(screen.getByLabelText('Role name'), 'Family summaries');
    await user.click(screen.getByLabelText('View attendance'));
    await user.click(screen.getByLabelText('View invoices for this account'));
    await user.click(screen.getByRole('button', { name: 'Create role' }));

    await waitFor(() => expect(auth.save).toHaveBeenCalledWith('session-token', 'org-1', {
      base_role: 'guardian', name: 'Family summaries', permissions: ['organization.view', 'students.view', 'results.view', 'courses.view'],
    }));
    expect(screen.getByRole('status')).toHaveTextContent('Custom role created.');
  });

  it('offers a member only profiles with the same base role', async () => {
    auth.list.mockResolvedValue(response({
      roles: [
        { id: 'teacher-role', name: 'Course teacher', base_role: 'teacher', permissions: permissionsByRole.teacher },
        { id: 'guardian-role', name: 'Family summaries', base_role: 'guardian', permissions: ['organization.view', 'students.view'] },
      ],
      members: [
        { id: 11, name: 'Tara Teacher', base_role: 'teacher', custom_role_id: null },
        { id: 12, name: 'Gita Guardian', base_role: 'guardian', custom_role_id: null },
      ],
    }));
    render(<CustomTeacherRolesPanel token="session-token" organization={organization} />);

    const guardianSelect = await screen.findByLabelText('Custom role for Gita Guardian');
    expect(within(guardianSelect).getByRole('option', { name: 'Family summaries' })).toBeInTheDocument();
    expect(within(guardianSelect).queryByRole('option', { name: 'Course teacher' })).not.toBeInTheDocument();
  });

  it('offers admin and educator profiles from their own capability catalogs', async () => {
    const user = userEvent.setup();
    render(<CustomTeacherRolesPanel token="session-token" organization={organization} />);

    await screen.findByText('No custom member roles yet.');
    await user.selectOptions(screen.getByLabelText('Member type'), 'admin');
    expect(screen.getByLabelText('Manage workspace members')).toBeInTheDocument();
    const academicRead = screen.getByLabelText('View classes and academic records');
    expect(academicRead).toBeDisabled();
    await user.click(screen.getByLabelText('Manage classes and academic records'));
    expect(academicRead).not.toBeDisabled();
    await user.selectOptions(screen.getByLabelText('Member type'), 'educator');
    expect(screen.getByLabelText('Manage workspace members')).toBeInTheDocument();
    expect(screen.getByLabelText('Create and manage staff tasks')).toBeInTheDocument();
    expect(screen.getByText(/do not inherit the educator role’s wildcard access/i)).toBeInTheDocument();
  });

  it('shows archived assignments while retaining their member permissions', async () => {
    auth.list.mockResolvedValue(response({
      roles: [{ id: 'archived-role', name: 'Restricted teacher', base_role: 'teacher', permissions: permissionsByRole.teacher, archived_at: '2026-11-19T10:00:00Z' }],
      members: [{ id: 11, name: 'Tara Teacher', base_role: 'teacher', custom_role_id: 'archived-role' }],
    }));
    render(<CustomTeacherRolesPanel token="session-token" organization={organization} />);

    expect(await screen.findByText('Restricted teacher · Archived; permissions retained')).toBeInTheDocument();
    const assignment = screen.getByLabelText('Custom role for Tara Teacher');
    expect(assignment).toHaveValue('archived-role');
    expect(within(assignment).getByRole('option', { name: 'Archived: Restricted teacher' })).toBeDisabled();
    expect(within(assignment).getByRole('option', { name: 'Standard teacher' })).toBeInTheDocument();
  });

  it('explains that archiving keeps assigned profile permissions', async () => {
    const user = userEvent.setup();
    const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
    auth.list.mockResolvedValue(response({
      roles: [{ id: 'role-1', name: 'Restricted teacher', base_role: 'teacher', permissions: permissionsByRole.teacher }],
      members: [{ id: 11, name: 'Tara Teacher', base_role: 'teacher', custom_role_id: 'role-1' }],
    }));
    render(<CustomTeacherRolesPanel token="session-token" organization={organization} />);

    await user.click(await screen.findByRole('button', { name: 'Archive' }));

    expect(confirm).toHaveBeenCalledWith('Archive “Restricted teacher”? Existing assignments keep these permissions until you restore or unassign the role.');
    expect(auth.archive).not.toHaveBeenCalled();
    confirm.mockRestore();
  });
});
