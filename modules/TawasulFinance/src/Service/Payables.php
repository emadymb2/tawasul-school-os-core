<?php
namespace Tos\Module\TawasulFinance\Service;

class Payables
{
    public function __construct(protected Ledger $ledger, protected array $cfg) {}

    public function recordBill(array $d)
    {
        $db = $this->ledger->db();
        $payable = $db->selectOne("SELECT tawasulFinancePayableAccountID FROM tawasulFinanceSupplier WHERE tawasulFinanceSupplierID=:s", ['s' => $d['supplierID']])
            ?: $this->ledger->accountIDByCode($this->cfg['payableAccountCode']);
        $num = $this->ledger->nextNumber('BILL');
        $id = $db->insert("INSERT INTO tawasulFinancePurchaseBill (billNumber,supplierID,date,dueDate,tawasulFinanceExpenseAccountID,tawasulFinanceCostCenterID,amount,taxAmount,description) VALUES (:n,:s,:d,:dd,:e,:cc,:a,:t,:desc)",
            ['n' => $num, 's' => $d['supplierID'], 'd' => $d['date'], 'dd' => $d['dueDate'] ?: null, 'e' => $d['tawasulFinanceExpenseAccountID'], 'cc' => $d['tawasulFinanceCostCenterID'] ?: null, 'a' => $d['amount'], 't' => $d['taxAmount'] ?: 0, 'desc' => $d['description']]);
        $lines = [['tawasulFinanceAccountID' => $d['tawasulFinanceExpenseAccountID'], 'debit' => $d['amount'], 'tawasulFinanceCostCenterID' => $d['tawasulFinanceCostCenterID'] ?: null, 'memo' => $d['description']]];
        if ((float)$d['taxAmount'] > 0) $lines[] = ['tawasulFinanceAccountID' => $this->ledger->accountIDByCode($this->cfg['taxAccountCode']), 'debit' => $d['taxAmount'], 'memo' => 'ضريبة مدخلات'];
        $lines[] = ['tawasulFinanceAccountID' => $payable, 'credit' => round($d['amount'] + ($d['taxAmount'] ?: 0), 2)];
        $je = $this->ledger->post($d['date'], 'فاتورة مشتريات '.$num, $lines, 'JV', 'PurchaseBill', $id);
        $db->update("UPDATE tawasulFinancePurchaseBill SET tawasulFinanceJournalEntryID=:j WHERE tawasulFinancePurchaseBillID=:i", ['j' => $je, 'i' => $id]);
        return $id;
    }

    /** Payment voucher: against a bill (reduces payable) or direct expense when billID is empty. */
    public function pay(array $d)
    {
        $db = $this->ledger->db();
        $cashAcc = $db->selectOne("SELECT tawasulFinanceAccountID FROM tawasulFinanceCashAccount WHERE tawasulFinanceCashAccountID=:c", ['c' => $d['tawasulFinanceCashAccountID']]);
        $amount = round((float)$d['amount'], 2);
        if (!empty($d['billID'])) {
            $bill = $db->select("SELECT b.*, s.tawasulFinancePayableAccountID FROM tawasulFinancePurchaseBill b JOIN tawasulFinanceSupplier s ON s.tawasulFinanceSupplierID=b.supplierID WHERE b.tawasulFinancePurchaseBillID=:b", ['b' => $d['billID']])->fetch();
            $due = round($bill['amount'] + $bill['taxAmount'] - $bill['paidAmount'], 2);
            if ($amount > $due + Ledger::EPS) throw new LedgerException('المبلغ يتجاوز المستحق ('.$due.')');
            $debitAcc = $bill['tawasulFinancePayableAccountID'] ?: $this->ledger->accountIDByCode($this->cfg['payableAccountCode']);
        } else {
            if (empty($d['tawasulFinanceExpenseAccountID'])) throw new LedgerException('حدد حساب المصروف');
            $debitAcc = $d['tawasulFinanceExpenseAccountID'];
        }
        $num = $this->ledger->nextNumber('PV');
        $id = $db->insert("INSERT INTO tawasulFinancePaymentVoucher (voucherNumber,tawasulFinancePurchaseBillID,tawasulFinanceCashAccountID,date,amount,method,reference) VALUES (:n,:b,:c,:d,:a,:m,:r)",
            ['n' => $num, 'b' => $d['billID'] ?: null, 'c' => $d['tawasulFinanceCashAccountID'], 'd' => $d['date'], 'a' => $amount, 'm' => $d['method'], 'r' => $d['reference']]);
        $je = $this->ledger->post($d['date'], 'سند صرف '.$num.' '.($d['memo'] ?? ''), [
            ['tawasulFinanceAccountID' => $debitAcc, 'debit' => $amount, 'tawasulFinanceCostCenterID' => $d['tawasulFinanceCostCenterID'] ?? null],
            ['tawasulFinanceAccountID' => $cashAcc, 'credit' => $amount],
        ], 'JV', 'PaymentVoucher', $id);
        $db->update("UPDATE tawasulFinancePaymentVoucher SET tawasulFinanceJournalEntryID=:j WHERE tawasulFinancePaymentVoucherID=:i", ['j' => $je, 'i' => $id]);
        if (!empty($bill)) {
            $paid = round($bill['paidAmount'] + $amount, 2);
            $db->update("UPDATE tawasulFinancePurchaseBill SET paidAmount=:p, status=:s WHERE tawasulFinancePurchaseBillID=:b",
                ['p' => $paid, 's' => $paid + Ledger::EPS >= $bill['amount'] + $bill['taxAmount'] ? 'Paid' : 'Partial', 'b' => $bill['tawasulFinancePurchaseBillID']]);
        }
        return $id;
    }

    public function transfer($from, $to, string $date, float $amount, string $memo)
    {
        if ($from == $to) throw new LedgerException('لا يمكن التحويل لنفس الصندوق');
        $db = $this->ledger->db();
        $a = fn($c) => $db->selectOne("SELECT tawasulFinanceAccountID FROM tawasulFinanceCashAccount WHERE tawasulFinanceCashAccountID=:c", ['c' => $c]);
        $id = $db->insert("INSERT INTO tawasulFinanceTransfer (tawasulFinanceFromCashAccountID,toCashAccountID,date,amount,memo) VALUES (:f,:t,:d,:a,:m)", ['f' => $from, 't' => $to, 'd' => $date, 'a' => $amount, 'm' => $memo]);
        $je = $this->ledger->post($date, 'تحويل بين الصناديق '.$memo, [['tawasulFinanceAccountID' => $a($to), 'debit' => $amount], ['tawasulFinanceAccountID' => $a($from), 'credit' => $amount]], 'TRF', 'Transfer', $id);
        $db->update("UPDATE tawasulFinanceTransfer SET tawasulFinanceJournalEntryID=:j WHERE tawasulFinanceTransferID=:i", ['j' => $je, 'i' => $id]);
        return $id;
    }
}
