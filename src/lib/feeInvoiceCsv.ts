import type { FeeInvoice } from './auth';
import { encodeCsv } from './csv';

const headers = [
  'Invoice number', 'Student name', 'Student code', 'Invoice title', 'Description',
  'Due date', 'Status', 'Invoice amount INR', 'Balance due INR', 'Receipt number',
  'Payment amount INR', 'Payment method', 'Transaction reference', 'Payment received at',
];

/** Export one row per payment; unpaid invoices still receive a single summary row. */
export function encodeFeeInvoiceCsv(invoices: readonly FeeInvoice[]): string {
  const rows = invoices.flatMap((invoice) => (invoice.payments.length ? invoice.payments : [null]).map((payment) => [
    invoice.invoice_number,
    invoice.student_name,
    invoice.student_code,
    invoice.title,
    invoice.description,
    invoice.due_on,
    invoice.status,
    (invoice.amount_paise / 100).toFixed(2),
    (invoice.balance_paise / 100).toFixed(2),
    payment?.receipt_number,
    payment ? (payment.amount_paise / 100).toFixed(2) : '',
    payment?.method.replaceAll('_', ' '),
    payment?.transaction_reference,
    payment?.received_at,
  ]));

  return encodeCsv([headers, ...rows]);
}
