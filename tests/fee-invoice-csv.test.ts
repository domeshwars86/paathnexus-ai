import { describe, expect, it } from 'vitest';
import { encodeFeeInvoiceCsv } from '../src/lib/feeInvoiceCsv';
import type { FeeInvoice } from '../src/lib/auth';

const invoice = (overrides: Partial<FeeInvoice> = {}): FeeInvoice => ({
  id: 'invoice-1', invoice_number: 'PN-INV-1', student_id: 'student-1', student_name: 'Asha Learner', student_code: 'ST-01',
  title: 'Term 1 tuition', description: null, amount_paise: 150000, balance_paise: 50000,
  payments: [
    { id: 'payment-1', amount_paise: 50000, method: 'upi', transaction_reference: 'UPI-1', receipt_number: 'RCT-1', received_at: '2026-11-18T10:00:00+05:30' },
    { id: 'payment-2', amount_paise: 50000, method: 'cash', transaction_reference: 'CASH-2', receipt_number: 'RCT-2', received_at: '2026-11-19T10:00:00+05:30' },
  ], due_on: '2026-11-30', status: 'partially_paid', created_at: '2026-11-01T09:00:00+05:30', ...overrides,
});

describe('fee invoice CSV export', () => {
  it('writes one row per reconciled payment with invoice totals', () => {
    const csv = encodeFeeInvoiceCsv([invoice()]);
    expect(csv.split('\r\n')).toHaveLength(3);
    expect(csv).toContain('"RCT-1"');
    expect(csv).toContain('"RCT-2"');
    expect(csv).toContain('"1500.00"');
    expect(csv).toContain('"500.00"');
  });

  it('retains unpaid invoices and neutralizes spreadsheet formulas in user fields', () => {
    const csv = encodeFeeInvoiceCsv([invoice({
      title: '=HYPERLINK("https://example.test")',
      payments: [],
      status: 'issued',
    })]);
    expect(csv.split('\r\n')).toHaveLength(2);
    expect(csv).toContain('"\'=HYPERLINK(""https://example.test"")"');
  });
});
