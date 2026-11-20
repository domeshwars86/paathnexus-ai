/** Convert a datetime-local wall time in an IANA timezone to an ISO instant.
 * Ambiguous fall-back times choose the earlier instant; nonexistent spring-forward
 * times return null so callers can ask for a valid local time.
 */
export function workspaceDateTimeToIso(value: string, timeZone: string): string | null {
  const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/.exec(value);
  if (!match) return null;

  const wanted = match.slice(1).map(Number) as [number, number, number, number, number];
  const [year, month, day, hour, minute] = wanted;
  const wallTime = new Date(0);
  wallTime.setUTCFullYear(year, month - 1, day);
  wallTime.setUTCHours(hour, minute, 0, 0);
  const target = wallTime.getTime();
  if (wallTime.getUTCFullYear() !== year || wallTime.getUTCMonth() !== month - 1 || wallTime.getUTCDate() !== day || hour > 23 || minute > 59) return null;

  try {
    const formatter = new Intl.DateTimeFormat('en-CA', {
      timeZone, year: 'numeric', month: '2-digit', day: '2-digit',
      hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    });
    const partsAt = (instant: number): [number, number, number, number, number] => {
      const values = Object.fromEntries(formatter.formatToParts(new Date(instant)).map(({ type, value }) => [type, value]));
      return [Number(values.year), Number(values.month), Number(values.day), Number(values.hour), Number(values.minute)];
    };
    const offsets = new Set<number>();
    for (let hours = -48; hours <= 48; hours += 6) {
      const instant = target + hours * 60 * 60 * 1000;
      const [localYear, localMonth, localDay, localHour, localMinute] = partsAt(instant);
      const localAsUtc = new Date(0);
      localAsUtc.setUTCFullYear(localYear, localMonth - 1, localDay);
      localAsUtc.setUTCHours(localHour, localMinute, 0, 0);
      offsets.add(localAsUtc.getTime() - instant);
    }
    const matches = [...offsets]
      .map((offset) => target - offset)
      .filter((instant) => partsAt(instant).every((part, index) => part === wanted[index]))
      .sort((a, b) => a - b);
    return matches.length ? new Date(matches[0]!).toISOString() : null;
  } catch {
    return null;
  }
}

/** Format an instant as a datetime-local control value in an IANA timezone. */
export function isoToWorkspaceDateTime(instant: Date, timeZone: string): string {
  const values = Object.fromEntries(new Intl.DateTimeFormat('en-CA', {
    timeZone, year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
  }).formatToParts(instant).map(({ type, value }) => [type, value]));
  return `${values.year}-${values.month}-${values.day}T${values.hour}:${values.minute}`;
}
