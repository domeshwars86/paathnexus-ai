/** Encode user-controlled values as spreadsheet-safe UTF-8 CSV. */
export function encodeCsv(rows: readonly (readonly unknown[])[]): string {
  const lines = rows.map((row) => row.map((cell) => {
    const value = String(cell ?? '');
    const safe = /^\s*[=+@-]/u.test(value) ? `'${value}` : value;
    return `"${safe.replaceAll('"', '""')}"`;
  }).join(','));

  return `\uFEFF${lines.join('\r\n')}`;
}
