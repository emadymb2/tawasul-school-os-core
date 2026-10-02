<?php
/**
 * The chart-of-accounts mapping used when the former SchoolAccounting module
 * was merged into TawasulFinance.
 *
 * The two charts shared no codes at all: SchoolAccounting numbered its accounts
 * 1/11/1101/5109 with Arabic labels only, while TawasulFinance numbers them
 * 1000/1100/5100 with bilingual "English / Arabic" labels and is the chart that
 * real postings hang off. So every SchoolAccounting code is resolved onto a
 * TawasulFinance code rather than inserted as a second, parallel account.
 *
 * This file is the single source of truth: tools/merge/chart_map.php generates
 * both docs/schoolaccounting_chart_merge.md and the UPDATE statements in the
 * v1.2.00 CHANGEDB migration, so the documentation cannot drift from the code.
 */

return [
    // ---- structural nodes -------------------------------------------------
    // SchoolAccounting nested every asset under a single root account, whereas
    // TawasulFinance splits current and fixed assets into two roots. These rows
    // therefore have no single target: the root is represented by the set of
    // accounts it decomposed into, and nothing is posted to a root directly.
    'structural' => [
        '1' => ['1000', '1500'],
        '2' => ['2000', '2500'],
    ],

    // ---- leaf accounts ----------------------------------------------------
    'accounts' => [
        // code => [tawasulFinanceCode, note]
        '11' => ['1000', ''],
        '12' => ['1500', ''],

        '1101' => ['1100', ''],
        '1102' => ['1110', ''],
        '1103' => ['1200', 'receivableAccountCode setting'],
        '1104' => ['1410', 'new account: no staff-advances equivalent existed'],
        '1201' => ['1510', ''],
        '1202' => ['1520', ''],
        '1203' => ['1540', ''],
        '1204' => ['1530', ''],
        '1209' => ['1550', ''],

        '2101' => ['2100', 'payableAccountCode setting'],
        '2102' => ['2120', 'salariesPayableCode setting'],
        '2103' => ['2140', ''],
        '2104' => ['2130', 'taxAccountCode setting'],
        '2105' => ['2150', ''],

        '3' => ['3000', ''],
        '3101' => ['3100', ''],
        '3102' => ['3200', 'retainedEarningsCode setting'],

        '4' => ['4000', ''],
        '4101' => ['4110', ''],
        '4102' => ['4100', ''],
        '4103' => ['4120', ''],
        '4104' => ['4140', ''],
        '4105' => ['4150', ''],
        '4109' => ['4180', 'new account: the old catch-all "other income"'],

        '5' => ['5000', ''],
        '5101' => ['5100', ''],
        '5102' => ['5105', 'new account: staff allowances were folded into 5110'],
        '5103' => ['5115', 'new account: 5120 covers utilities, not rent'],
        '5104' => ['5120', ''],
        '5105' => ['5130', ''],
        '5106' => ['5140', ''],
        '5107' => ['5150', ''],
        '5108' => ['5180', ''],
        '5109' => ['5240', 'discountAccountCode setting'],
        '5199' => ['5290', ''],
    ],

    // The retired chart exactly as it stood before the merge -- name and type
    // for each of its 39 accounts. Held here rather than read from the database
    // so the mapping above stays checkable now that the table is gone.
    'retired' => [
        '1' => ['الأصول', 'Asset'],
        '2' => ['الخصوم', 'Liability'],
        '3' => ['حقوق الملكية', 'Equity'],
        '4' => ['الإيرادات', 'Revenue'],
        '5' => ['المصروفات', 'Expense'],
        '11' => ['الأصول المتداولة', 'Asset'],
        '12' => ['الأصول الثابتة', 'Asset'],
        '1101' => ['الصندوق الرئيسي', 'Asset'],
        '1102' => ['البنك', 'Asset'],
        '1103' => ['ذمم الطلاب (مدينون)', 'Asset'],
        '1104' => ['سلف الموظفين', 'Asset'],
        '1201' => ['المباني', 'Asset'],
        '1202' => ['الأثاث والتجهيزات', 'Asset'],
        '1203' => ['الحاسبات', 'Asset'],
        '1204' => ['الحافلات', 'Asset'],
        '1209' => ['مجمع الإهلاك', 'Asset'],
        '2101' => ['الموردون (دائنون)', 'Liability'],
        '2102' => ['رواتب مستحقة', 'Liability'],
        '2103' => ['رسوم مقبوضة مقدماً', 'Liability'],
        '2104' => ['ضرائب مستحقة', 'Liability'],
        '2105' => ['تأمينات اجتماعية مستحقة', 'Liability'],
        '3101' => ['رأس المال', 'Equity'],
        '3102' => ['الأرباح المحتجزة', 'Equity'],
        '4101' => ['رسوم التسجيل', 'Revenue'],
        '4102' => ['الرسوم الدراسية', 'Revenue'],
        '4103' => ['رسوم النقل', 'Revenue'],
        '4104' => ['إيرادات الزي والكتب', 'Revenue'],
        '4105' => ['رسوم الأنشطة', 'Revenue'],
        '4109' => ['إيرادات أخرى', 'Revenue'],
        '5101' => ['الرواتب والأجور', 'Expense'],
        '5102' => ['البدلات', 'Expense'],
        '5103' => ['الإيجارات', 'Expense'],
        '5104' => ['الكهرباء والمياه', 'Expense'],
        '5105' => ['الصيانة', 'Expense'],
        '5106' => ['القرطاسية والمستلزمات', 'Expense'],
        '5107' => ['وقود ونقل', 'Expense'],
        '5108' => ['مصروف الإهلاك', 'Expense'],
        '5109' => ['خصومات ومنح الطلاب', 'Expense'],
        '5199' => ['مصروفات عمومية', 'Expense'],
    ],

    // The six module settings that pointed at a SchoolAccounting account code.
    // Each is rewritten to the TawasulFinance code its account resolved to.
    'settings' => [
        'receivableAccountCode' => '1200',
        'payableAccountCode' => '2100',
        'salariesPayableCode' => '2120',
        'taxAccountCode' => '2130',
        'retainedEarningsCode' => '3200',
        'discountAccountCode' => '5240',
    ],
];
