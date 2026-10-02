<?php
namespace Tos\Module\TawasulFinance\Service;

/**
 * Student invoicing, discounts and receipts, all posting through Ledger.
 *
 * Rewritten during the SchoolAccounting merge. The two modules modelled an
 * invoice differently, and TawasulFinance's model is the one that survives:
 *
 *   SchoolAccounting                     TawasulFinance
 *   ---------------------------          -----------------------------------
 *   tawasulSchoolAccountingFeePlan       tawasulFinanceBillingSchedule
 *   tawasulSchoolAccountingFeeItem       tawasulFinanceFee
 *   tawasulSchoolAccountingInvoice       tawasulFinanceInvoice
 *   tawasulSchoolAccountingInvoiceLine   tawasulFinanceInvoiceFee
 *   invoice.tawasulPersonID              invoice -> tawasulFinanceInvoicee
 *
 * Two capabilities did not survive the move and are recorded here rather than
 * quietly dropped. SchoolAccounting billed against a fee plan in numbered
 * installments; the surviving BillingSchedule has no installment sequence and
 * no cost centre, so generateForSchedule() now issues one invoice per invoicee
 * per run. A per-invoice fee line also has no discount column, so line discounts
 * are rolled into the invoice's discountAmount, which is where the surviving
 * model keeps them.
 *
 * See docs/schoolaccounting_chart_merge.md for the account-code mapping and
 * tools/merge/column_map_data.php for the full column mapping.
 */
class Billing
{
    public function __construct(protected Ledger $ledger, protected array $cfg) {}

    /**
     * Raise one invoice per invoicee for the given billing schedule, skipping
     * anyone already invoiced for it. Returns the number of invoices raised.
     */
    public function generateForSchedule($scheduleID, ?string $issueDate = null, ?string $dueDate = null) : int
    {
        $db = $this->ledger->db();

        $schedule = $db->select(
            'SELECT * FROM tawasulFinanceBillingSchedule WHERE tawasulFinanceBillingScheduleID=:id',
            ['id' => $scheduleID]
        )->fetch();
        if (!$schedule) {
            throw new LedgerException('جدول التحصيل غير موجود');
        }

        // A schedule carries no items of its own, so the year's active fees are
        // the lines, each raised on the invoice as an invoice fee.
        $fees = $db->select(
            'SELECT f.tawasulFinanceFeeID, f.name, f.fee, f.tawasulFinanceFeeCategoryID
               FROM tawasulFinanceFee f
              WHERE f.tawasulSchoolYearID=:y AND f.active=:active
              ORDER BY f.name',
            ['y' => $schedule['tawasulSchoolYearID'], 'active' => 'Y']
        )->fetchAll();
        if (!$fees) {
            throw new LedgerException('لا توجد رسوم نشطة للسنة الدراسية');
        }

        $issueDate = $issueDate ?: ($schedule['invoiceIssueDate'] ?: date('Y-m-d'));
        $dueDate = $dueDate ?: ($schedule['invoiceDueDate'] ?: $issueDate);

        // Billing is addressed to an invoicee, not straight to a person.
        $invoicees = $db->select(
            'SELECT tawasulFinanceInvoiceeID, tawasulPersonID, invoiceTo
               FROM tawasulFinanceInvoicee
              ORDER BY tawasulFinanceInvoiceeID'
        )->fetchAll();
        if (!$invoicees) {
            throw new LedgerException('لا يوجد مستفيدون للفوترة');
        }

        $revenueAccount = $this->feeRevenueAccount();
        $receivable = $this->ledger->accountIDByCode($this->cfg['receivableAccountCode']);
        $raised = 0;

        foreach ($invoicees as $invoicee) {
            $already = $db->selectOne(
                "SELECT COUNT(*) FROM tawasulFinanceInvoice
                  WHERE tawasulFinanceInvoiceeID=:i
                    AND tawasulFinanceBillingScheduleID=:s
                    AND status <> 'Cancelled'",
                ['i' => $invoicee['tawasulFinanceInvoiceeID'], 's' => $scheduleID]
            );
            if ($already) {
                continue;
            }

            $discounts = $this->discountsFor($invoicee['tawasulPersonID'], $schedule['tawasulSchoolYearID']);

            $lines = [];
            $invoiceFees = [];
            $gross = 0.0;
            $discount = 0.0;
            $discountByAccount = [];

            foreach ($fees as $fee) {
                $amount = (float) $fee['fee'];
                $lineDiscount = 0.0;
                foreach ($discounts as $d) {
                    $value = $d['method'] === 'Percent'
                        ? round($amount * (float) $d['value'] / 100, 2)
                        : round((float) $d['value'] / max(1, count($fees)), 2);
                    // Never discount a line below zero.
                    $value = min($value, $amount - $lineDiscount);
                    $lineDiscount += $value;
                    $account = $d['tawasulFinanceExpenseAccountID']
                        ?: $this->ledger->accountIDByCode($this->cfg['discountAccountCode']);
                    $discountByAccount[$account] = ($discountByAccount[$account] ?? 0.0) + $value;
                }

                $gross += $amount;
                $discount += $lineDiscount;
                $invoiceFees[] = [$fee, $amount];
                $lines[] = [
                    'tawasulFinanceAccountID' => $revenueAccount,
                    'credit' => $amount,
                    'tawasulPersonID' => $invoicee['tawasulPersonID'],
                    'memo' => $fee['name'],
                ];
            }

            $net = round($gross - $discount, 2);
            if ($net > 0) {
                $lines[] = [
                    'tawasulFinanceAccountID' => $receivable,
                    'debit' => $net,
                    'tawasulPersonID' => $invoicee['tawasulPersonID'],
                ];
            }
            foreach ($discountByAccount as $account => $amount) {
                if ($amount <= 0) {
                    continue;
                }
                $lines[] = [
                    'tawasulFinanceAccountID' => $account,
                    'debit' => $amount,
                    'tawasulPersonID' => $invoicee['tawasulPersonID'],
                    'memo' => 'خصم',
                ];
            }

            $key = $this->ledger->nextNumber('INV');
            $invoiceID = $db->insert(
                "INSERT INTO tawasulFinanceInvoice
                     (tawasulSchoolYearID, tawasulFinanceInvoiceeID, invoiceTo, billingScheduleType,
                      tawasulFinanceBillingScheduleID, `key`, status, separated,
                      invoiceIssueDate, invoiceDueDate, grossAmount, discountAmount, netAmount,
                      tawasulPersonIDCreator, timestampCreator)
                 VALUES (:y, :i, :to, 'Scheduled', :s, :k, :status, 'N',
                         :issue, :due, :gross, :discount, :net, :creator, NOW())",
                [
                    'y' => $schedule['tawasulSchoolYearID'],
                    'i' => $invoicee['tawasulFinanceInvoiceeID'],
                    'to' => $invoicee['invoiceTo'] ?: 'Family',
                    's' => $scheduleID,
                    'k' => $key,
                    'status' => $net > 0 ? 'Issued' : 'Paid',
                    'issue' => $issueDate,
                    'due' => $dueDate,
                    'gross' => $gross,
                    'discount' => $discount,
                    'net' => $net,
                    'creator' => $this->ledger->userID(),
                ]
            );

            $sequence = 0;
            foreach ($invoiceFees as [$fee, $amount]) {
                $db->insert(
                    "INSERT INTO tawasulFinanceInvoiceFee
                         (tawasulFinanceInvoiceID, feeType, tawasulFinanceFeeID, separated,
                          name, tawasulFinanceFeeCategoryID, fee, sequenceNumber)
                     VALUES (:i, 'Fee', :f, 'N', :n, :c, :a, :s)",
                    [
                        'i' => $invoiceID,
                        'f' => $fee['tawasulFinanceFeeID'],
                        'n' => $fee['name'],
                        'c' => $fee['tawasulFinanceFeeCategoryID'],
                        'a' => $amount,
                        's' => ++$sequence,
                    ]
                );
            }

            $entryID = $this->ledger->post(
                $issueDate,
                'فاتورة رسوم '.$key,
                $lines,
                'JV',
                'Invoice',
                $invoiceID
            );

            $db->update(
                'UPDATE tawasulFinanceInvoice
                    SET tawasulFinanceJournalEntryID=:j, postedDate=:d
                  WHERE tawasulFinanceInvoiceID=:i',
                ['j' => $entryID, 'd' => $issueDate, 'i' => $invoiceID]
            );

            $raised++;
        }

        return $raised;
    }

    /** Record a receipt against an invoice and post the cash movement. */
    public function receive($invoiceID, $cashAccountID, string $date, float $amount, string $method, ?string $ref, $userID)
    {
        $db = $this->ledger->db();

        $invoice = $db->select(
            'SELECT * FROM tawasulFinanceInvoice WHERE tawasulFinanceInvoiceID=:i',
            ['i' => $invoiceID]
        )->fetch();
        if (!$invoice || $invoice['status'] === 'Cancelled') {
            throw new LedgerException('الفاتورة غير صالحة');
        }

        $due = round((float) $invoice['netAmount'] - (float) $invoice['paidAmount'], 2);
        if ($amount <= 0 || $amount > $due + Ledger::EPS) {
            throw new LedgerException('المبلغ يتجاوز المتبقي ('.$due.')');
        }

        $cashAccount = $db->selectOne(
            'SELECT tawasulFinanceAccountID FROM tawasulFinanceCashAccount WHERE tawasulFinanceCashAccountID=:c',
            ['c' => $cashAccountID]
        );
        if (!$cashAccount) {
            throw new LedgerException('حساب النقد غير موجود');
        }

        // The billed person is reached through the invoicee.
        $personID = $db->selectOne(
            'SELECT tawasulPersonID FROM tawasulFinanceInvoicee WHERE tawasulFinanceInvoiceeID=:i',
            ['i' => $invoice['tawasulFinanceInvoiceeID']]
        );

        $receivable = $this->ledger->accountIDByCode($this->cfg['receivableAccountCode']);
        $number = $this->ledger->nextNumber('RV');

        $receiptID = $db->insert(
            "INSERT INTO tawasulFinanceReceipt
                 (receiptNumber, tawasulFinanceInvoiceID, tawasulFinanceCashAccountID,
                  date, amount, method, reference, tawasulPersonIDReceiver)
             VALUES (:n, :i, :c, :d, :a, :m, :r, :u)",
            [
                'n' => $number,
                'i' => $invoiceID,
                'c' => $cashAccountID,
                'd' => $date,
                'a' => $amount,
                'm' => $method,
                'r' => $ref,
                'u' => $userID,
            ]
        );

        $entryID = $this->ledger->post($date, 'سند قبض '.$number.' عن فاتورة '.$invoice['key'], [
            ['tawasulFinanceAccountID' => $cashAccount, 'debit' => $amount, 'tawasulPersonID' => $personID],
            ['tawasulFinanceAccountID' => $receivable, 'credit' => $amount, 'tawasulPersonID' => $personID],
        ], 'JV', 'Receipt', $receiptID);

        $paid = round((float) $invoice['paidAmount'] + $amount, 2);
        $db->update(
            "UPDATE tawasulFinanceInvoice
                SET paidAmount=:p, paidDate=:d, status=:s
              WHERE tawasulFinanceInvoiceID=:i",
            [
                'p' => $paid,
                'd' => $date,
                's' => $paid + Ledger::EPS >= (float) $invoice['netAmount'] ? 'Paid' : 'Partial',
                'i' => $invoiceID,
            ]
        );

        $db->update(
            'UPDATE tawasulFinanceReceipt SET tawasulFinanceJournalEntryID=:j WHERE tawasulFinanceReceiptID=:r',
            ['j' => $entryID, 'r' => $receiptID]
        );

        return $receiptID;
    }

    /** Cancel an unpaid invoice, reversing its journal entry. */
    public function cancelInvoice($invoiceID, string $date)
    {
        $db = $this->ledger->db();

        $invoice = $db->select(
            'SELECT * FROM tawasulFinanceInvoice WHERE tawasulFinanceInvoiceID=:i',
            ['i' => $invoiceID]
        )->fetch();
        if (!$invoice) {
            throw new LedgerException('الفاتورة غير موجودة');
        }
        if ((float) $invoice['paidAmount'] > 0) {
            throw new LedgerException('لا يمكن إلغاء فاتورة عليها مدفوعات');
        }

        if ($invoice['tawasulFinanceJournalEntryID']) {
            $this->ledger->reverse($invoice['tawasulFinanceJournalEntryID'], $date, 'إلغاء فاتورة');
        }

        $db->update(
            "UPDATE tawasulFinanceInvoice SET status='Cancelled' WHERE tawasulFinanceInvoiceID=:i",
            ['i' => $invoiceID]
        );
    }

    /** Approved discounts for a person in a year, with the account to charge. */
    private function discountsFor($personID, $schoolYearID) : array
    {
        return $this->ledger->db()->select(
            'SELECT d.method, d.value, d.tawasulFinanceExpenseAccountID
               FROM tawasulFinanceStudentDiscount sd
               JOIN tawasulFinanceDiscount d
                 ON d.tawasulFinanceDiscountID = sd.tawasulFinanceDiscountID
              WHERE sd.tawasulPersonID=:p AND sd.tawasulSchoolYearID=:y',
            ['p' => $personID, 'y' => $schoolYearID]
        )->fetchAll();
    }

    /**
     * The account fee income is credited to: the revenue account recorded on the
     * first fee, falling back to the chart's operating revenue.
     */
    private function feeRevenueAccount()
    {
        $id = $this->ledger->db()->selectOne(
            'SELECT tawasulFinanceRevenueAccountID FROM tawasulFinanceFeeItem ORDER BY tawasulFinanceFeeItemID LIMIT 1'
        );
        if ($id) {
            return $id;
        }
        return $this->ledger->accountIDByCode('4100');
    }
}
