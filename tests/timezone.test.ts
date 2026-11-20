import { describe, expect, it } from 'vitest';
import { isoToWorkspaceDateTime, workspaceDateTimeToIso } from '../src/lib/timezone';

describe('workspaceDateTimeToIso', () => {
  it('converts organization wall time independently of the browser timezone', () => {
    expect(workspaceDateTimeToIso('2026-06-15T09:30', 'Asia/Kolkata')).toBe('2026-06-15T04:00:00.000Z');
    expect(isoToWorkspaceDateTime(new Date('2026-06-15T04:00:00.000Z'), 'Asia/Kolkata')).toBe('2026-06-15T09:30');
  });

  it('chooses the earlier instant for an ambiguous fall-back time', () => {
    expect(workspaceDateTimeToIso('2026-11-01T01:30', 'America/New_York')).toBe('2026-11-01T05:30:00.000Z');
  });

  it('rejects nonexistent spring-forward times and invalid input', () => {
    expect(workspaceDateTimeToIso('2026-03-08T02:30', 'America/New_York')).toBeNull();
    expect(workspaceDateTimeToIso('2026-02-30T10:00', 'Asia/Kolkata')).toBeNull();
    expect(workspaceDateTimeToIso('2026-06-15T10:00', 'Not/AZone')).toBeNull();
  });
});
