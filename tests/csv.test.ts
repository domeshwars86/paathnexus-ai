import { describe, expect, it } from 'vitest';
import { encodeCsv } from '../src/lib/csv';

describe('encodeCsv', () => {
  it('quotes cells, doubles quotes, and escapes formula prefixes after whitespace', () => {
    expect(encodeCsv([
      ['name', 'value'],
      ['Learner', '  =HYPERLINK("https://example.invalid")'],
      ['Another learner', '\t@SUM(1,1)'],
      ['Safe', 'ordinary text'],
    ])).toBe('\uFEFF"name","value"\r\n"Learner","\'  =HYPERLINK(""https://example.invalid"")"\r\n"Another learner","\'\t@SUM(1,1)"\r\n"Safe","ordinary text"');
  });

  it('represents null cells as empty values', () => {
    expect(encodeCsv([['value', null]])).toBe('\uFEFF"value",""');
  });
});
