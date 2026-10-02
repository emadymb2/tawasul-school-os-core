<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.

TawasulOS — Standard school chart of accounts.

Bilingual (Arabic first, English second) and ordered so the trial balance reads
in statement order. Headings are not posting accounts: their children carry the
balances and roll up to them.

Seeding is idempotent and matched on the account code, which carries a UNIQUE
index, so this is safe to run on install, on upgrade, and repeatedly.
*/

namespace Tos\Module\TawasulFinance\Domain;

use TawasulOS\Contracts\Database\Connection;

/**
 * Seeds the reference data an accounting system cannot function without: the
 * chart of accounts, the document sequences the posting engine allocates from,
 * cost centres, salary components and an opening fiscal year.
 */
class ChartOfAccounts
{
    private const CHART = [
        // ---------------- Assets (1000-1999) ----------------
        '1000' => ['Asset', 'N', 'Current Assets / الأصول المتداولة', null],
        '1100' => ['Asset', 'Y', 'Cash on Hand / النقد في الصندوق', '1000'],
        '1110' => ['Asset', 'Y', 'Bank Accounts / الحسابات البنكية', '1000'],
        '1120' => ['Asset', 'Y', 'Cash Accounts / حسابات النقد', '1000'],
        '1200' => ['Asset', 'Y', 'Accounts Receivable / ذمم المدينين', '1000'],
        '1210' => ['Asset', 'Y', 'Allowance for Doubtful Accounts / مخصص الديون المشكوك فيها', '1000'],
        '1300' => ['Asset', 'Y', 'Prepaid Expenses / المصروفات المدفوعة مقدماً', '1000'],
        '1400' => ['Asset', 'Y', 'Student Advances / سلف الطلاب', '1000'],
        // Added when the former SchoolAccounting chart was merged in. The two
        // charts shared no codes, and a school advances money to staff as well
        // as to students, which 1400 alone did not cover.
        '1410' => ['Asset', 'Y', 'Staff Advances / سلف الموظفين', '1000'],

        '1500' => ['Asset', 'N', 'Fixed Assets / الأصول الثابتة', null],
        '1510' => ['Asset', 'Y', 'Land and Buildings / الأراضي والمباني', '1500'],
        '1520' => ['Asset', 'Y', 'Furniture and Equipment / الأثاث والمعدات', '1500'],
        '1530' => ['Asset', 'Y', 'Vehicles / المركبات', '1500'],
        '1540' => ['Asset', 'Y', 'Computer Equipment / أجهزة الحاسب', '1500'],
        '1550' => ['Asset', 'Y', 'Accumulated Depreciation / مجمّع الإهلاك', '1500'],

        // ---------------- Liabilities (2000-2999) ----------------
        '2000' => ['Liability', 'N', 'Current Liabilities / الالتزامات المتداولة', null],
        '2100' => ['Liability', 'Y', 'Accounts Payable / ذمم الدائنين', '2000'],
        '2110' => ['Liability', 'Y', 'Accrued Expenses / المصروفات المستحقة', '2000'],
        '2120' => ['Liability', 'Y', 'Salaries Payable / رواتب مستحقة', '2000'],
        '2130' => ['Liability', 'Y', 'Taxes Payable / الضرائب المستحقة', '2000'],
        '2140' => ['Liability', 'Y', 'Student Fees in Advance / رسوم مقدمة', '2000'],
        '2150' => ['Liability', 'Y', 'Payroll Deductions Payable / استقطاعات مستحقة', '2000'],

        '2500' => ['Liability', 'N', 'Long Term Liabilities / الالتزامات طويلة الأجل', null],
        '2510' => ['Liability', 'Y', 'Bank Loans / قروض بنكية', '2500'],
        '2520' => ['Liability', 'Y', 'Lease Obligations / التزامات الإيجار', '2500'],

        // ---------------- Equity (3000-3999) ----------------
        '3000' => ['Equity', 'N', 'Equity / حقوق الملكية', null],
        '3100' => ['Equity', 'Y', 'Opening Balances / أرصدة افتتاحية', '3000'],
        '3200' => ['Equity', 'Y', 'Retained Earnings / الأرباح المُرحّلة', '3000'],
        '3300' => ['Equity', 'Y', 'Current Year Result / نتيجة العام الحالي', '3000'],

        // ---------------- Revenue (4000-4999) ----------------
        '4000' => ['Revenue', 'N', 'Operating Revenue / الإيرادات التشغيلية', null],
        '4100' => ['Revenue', 'Y', 'Tuition Fees / الرسوم الدراسية', '4000'],
        '4110' => ['Revenue', 'Y', 'Registration Fees / رسوم التسجيل', '4000'],
        '4120' => ['Revenue', 'Y', 'Transport Fees / رسوم النقل', '4000'],
        '4130' => ['Revenue', 'Y', 'Cafeteria Income / إيرادات المطعم', '4000'],
        '4140' => ['Revenue', 'Y', 'Uniform and Books / الزي المدرسي والكتب', '4000'],
        '4150' => ['Revenue', 'Y', 'Extracurricular Activities / الأنشطة اللاصفوفية', '4000'],
        '4160' => ['Revenue', 'Y', 'Donations / التبرعات', '4000'],
        '4170' => ['Revenue', 'Y', 'Investment Income / إيرادات الاستثمارات', '4000'],
        // Added when the former SchoolAccounting chart was merged in: the old
        // chart had a catch-all "other income" that this one was missing.
        '4180' => ['Revenue', 'Y', 'Other Income / إيرادات أخرى', '4000'],
        '4190' => ['Revenue', 'Y', 'Discounts Allowed / الخصومات الممنوحة', '4000'],

        // ---------------- Expenses (5000-5999) ----------------
        '5000' => ['Expense', 'N', 'Operating Expenses / المصروفات التشغيلية', null],
        '5100' => ['Expense', 'Y', 'Salaries and Wages / الرواتب والأجور', '5000'],
        // 5105, 5115, 1410 and 4180 were added when the former SchoolAccounting
        // chart was merged in. The old chart separated staff allowances and
        // rent, which this one folded into other lines, and 5120 covers
        // utilities only rather than the rent itself.
        '5105' => ['Expense', 'Y', 'Staff Allowances / البدلات', '5000'],
        '5110' => ['Expense', 'Y', 'Social Insurance / التأمينات الاجتماعية', '5000'],
        '5115' => ['Expense', 'Y', 'Rent / الإيجارات', '5000'],
        '5120' => ['Expense', 'Y', 'Housing and Utilities / السكن والخدمات', '5000'],
        '5130' => ['Expense', 'Y', 'Maintenance and Repairs / الصيانة والإصلاح', '5000'],
        '5140' => ['Expense', 'Y', 'Supplies and Stationery / القرطاسية والمستلزمات', '5000'],
        '5150' => ['Expense', 'Y', 'Transport and Fuel / النقل والوقود', '5000'],
        '5160' => ['Expense', 'Y', 'Cafeteria Cost / تكلفة المطعم', '5000'],
        '5170' => ['Expense', 'Y', 'Insurance / التأمين', '5000'],
        '5180' => ['Expense', 'Y', 'Depreciation Expense / مصروف الإهلاك', '5000'],
        '5190' => ['Expense', 'Y', 'Bank Charges / رسوم بنكية', '5000'],
        '5200' => ['Expense', 'Y', 'Professional Fees / أتعاب مهنية', '5000'],
        '5210' => ['Expense', 'Y', 'Marketing and Printing / التسويق والمطبوعات', '5000'],
        '5220' => ['Expense', 'Y', 'Subscriptions and Licenses / الاشتراكات والتراخيص', '5000'],
        '5230' => ['Expense', 'Y', 'Bad Debt Expense / مصروف الديون المعدومة', '5000'],
        '5240' => ['Expense', 'Y', 'Discounts Granted / الخصومات الممنوحة', '5000'],
        '5290' => ['Expense', 'Y', 'Other Expenses / مصروفات أخرى', '5000'],
    ];

    /**
     * Document sequences the posting engine allocates numbers from. The prefix
     * is prepended to the running number, e.g. JV-1, JV-2.
     */
    private const SEQUENCES = [
        ['JV', 'JV-'],
        ['REV', 'REV-'],
        ['INV', 'INV-'],
        ['BILL', 'BILL-'],
        ['PV', 'PV-'],
        ['RV', 'RV-'],
        ['PAY', 'PAY-'],
        ['DEP', 'DEP-'],
        ['TRF', 'TRF-'],
        ['OPEN', 'OPEN-'],
    ];

    /** Stages a school budgets and reports by. */
    private const COST_CENTRES = [
        ['ADM', 'Administration / الإدارة'],
        ['EDU', 'Teaching / التدريس'],
        ['FIN', 'Finance and Accounts / المالية والمحاسبة'],
        ['SUP', 'Facilities and Maintenance / التشغيل والصيانة'],
        ['STU', 'Student Services / خدمات الطلاب'],
        ['EXT', 'Extra-curricular / الأنشطة'],
    ];

    /** Earnings and deductions, needed before a payroll run can be built. */
    private const SALARY_COMPONENTS = [
        ['Basic Salary / الراتب الأساسي', 'Earning'],
        ['Housing Allowance / بدل سكن', 'Earning'],
        ['Transport Allowance / بدل نقل', 'Earning'],
        ['Overtime / عمل إضافي', 'Earning'],
        ['Social Insurance / التأمينات', 'Deduction'],
        ['Tax Withholding / ضريبة مستحسبة', 'Deduction'],
        ['Pension Contribution / تقاعد', 'Deduction'],
    ];

    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Run every seed step.
     *
     * @return array Counts of what was created, for reporting.
     */
    public function seed() : array
    {
        return [
            'accounts' => $this->seedAccounts(),
            'sequences' => $this->seedSequences(),
            'costCentres' => $this->seedCostCentres(),
            'salaryComponents' => $this->seedSalaryComponents(),
            'fiscalYear' => $this->seedFiscalYear(),
        ];
    }

    /**
     * Insert the chart, resolving parent links in two passes so a child can
     * reference a parent created in the same run.
     *
     * @return array{inserted:int,updated:int}
     */
    public function seedAccounts() : array
    {
        // Read through fetchRows() when the connection offers it (the CLI
        // adapter does); fall back to select(), whose rows carry the same keys.
        $rows = method_exists($this->connection, 'fetchRows')
            ? $this->connection->fetchRows('SELECT code, tawasulFinanceAccountID FROM tawasulFinanceAccount')
            : $this->connection->select('SELECT code, tawasulFinanceAccountID FROM tawasulFinanceAccount')->fetchAll();

        $existing = [];
        foreach ($rows as $row) {
            $existing[(string) $row['code']] = $row['tawasulFinanceAccountID'];
        }

        $inserted = 0;
        $updated = 0;
        $ids = [];

        for ($pass = 1; $pass <= 2; $pass++) {
            foreach (self::CHART as $code => [$type, $isPosting, $name, $parentCode]) {
                if (isset($ids[$code])) {
                    continue;
                }

                $parentID = null;
                if ($parentCode !== null) {
                    if (!isset($ids[$parentCode])) {
                        if ($pass === 2) {
                            throw new \RuntimeException("Account {$code} references unknown parent {$parentCode}");
                        }
                        continue;
                    }
                    $parentID = $ids[$parentCode];
                }

                if (isset($existing[$code])) {
                    // Refresh the label and structure, but leave active alone:
                    // an administrator may have retired an account deliberately.
                    $this->connection->update(
                        'UPDATE tawasulFinanceAccount
                            SET name = :name, type = :type, parentAccountID = :parent, isPosting = :isPosting
                          WHERE code = :code',
                        [':name' => $name, ':type' => $type, ':parent' => $parentID, ':isPosting' => $isPosting, ':code' => $code]
                    );
                    $ids[$code] = $existing[$code];
                    $updated++;
                    continue;
                }

                $this->connection->insert(
                    "INSERT INTO tawasulFinanceAccount (code, name, type, parentAccountID, isPosting, active)
                          VALUES (:code, :name, :type, :parent, :isPosting, 'Y')",
                    [':code' => $code, ':name' => $name, ':type' => $type, ':parent' => $parentID, ':isPosting' => $isPosting]
                );
                $ids[$code] = (int) $this->connection->getConnection()->lastInsertId();
                $inserted++;
            }
        }

        return ['inserted' => $inserted, 'updated' => $updated, 'defined' => count(self::CHART)];
    }

    private function seedSequences() : int
    {
        foreach (self::SEQUENCES as [$type, $prefix]) {
            // nextNumber is left untouched on re-run: resetting it would reissue
            // document numbers that may already appear on posted entries.
            $this->connection->update(
                'INSERT INTO tawasulFinanceSequence (documentType, prefix, nextNumber)
                      VALUES (:type, :prefix, 1)
                 ON DUPLICATE KEY UPDATE prefix = VALUES(prefix)',
                [':type' => $type, ':prefix' => $prefix]
            );
        }

        return count(self::SEQUENCES);
    }

    private function seedCostCentres() : int
    {
        foreach (self::COST_CENTRES as [$code, $name]) {
            $this->connection->update(
                "INSERT INTO tawasulFinanceCostCenter (code, name, type)
                      VALUES (:code, :name, 'Stage')
                 ON DUPLICATE KEY UPDATE name = VALUES(name)",
                [':code' => $code, ':name' => $name]
            );
        }

        return count(self::COST_CENTRES);
    }

    private function seedSalaryComponents() : int
    {
        foreach (self::SALARY_COMPONENTS as [$name, $type]) {
            $this->connection->update(
                "INSERT INTO tawasulFinanceSalaryComponent (name, type)
                      VALUES (:name, :type)
                 ON DUPLICATE KEY UPDATE type = VALUES(type)",
                [':name' => $name, ':type' => $type]
            );
        }

        return count(self::SALARY_COMPONENTS);
    }

    /**
     * Create a fiscal year for the current calendar year if none exists.
     *
     * @return bool True when one was created.
     */
    private function seedFiscalYear() : bool
    {
        $existing = $this->connection->selectOne('SELECT COUNT(*) AS c FROM tawasulFinanceFiscalYear');
        $count = (int) ($existing['c'] ?? $existing['C'] ?? 0);

        if ($count > 0) {
            return false;
        }

        $year = date('Y');
        $fiscalYears = new FiscalYearGateway($this->connection);
        $fiscalYears->createYearWithPeriods(
            "Fiscal Year {$year} / السنة المالية {$year}",
            "{$year}-01-01",
            "{$year}-12-31"
        );

        return true;
    }
}
