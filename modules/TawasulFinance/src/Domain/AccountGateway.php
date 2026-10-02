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

TawasulOS — Chart of accounts gateway.
*/

namespace Tos\Module\TawasulFinance\Domain;

use TawasulOS\Domain\QueryableGateway;
use TawasulOS\Domain\QueryCriteria;
use TawasulOS\Domain\Traits\TableAware;

/**
 * Account Gateway
 *
 * Provides the chart of accounts: the parent/child tree of ledger accounts that
 * journal lines post against. Accounts with isPosting = 'N' are headings and
 * cannot receive postings themselves.
 */
class AccountGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tawasulFinanceAccount';
    private static $primaryKey = 'tawasulFinanceAccountID';

    private static $searchableColumns = ['code', 'name'];

    /**
     * The account types the ledger understands, mapped to whether they carry a
     * debit or credit balance in normal operation. Used by the reports to work
     * out a running balance from a debit/credit pair.
     */
    public const TYPES = ['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'];

    /**
     * Whether an account type increases on the debit side.
     */
    public const DEBIT_TYPES = ['Asset', 'Expense'];

    public const ARABIC_TYPES = [
        'Asset'     => 'أصول',
        'Liability' => 'التزامات',
        'Equity'    => 'حقوق ملكية',
        'Revenue'   => 'إيرادات',
        'Expense'   => 'مصروفات',
    ];

    /**
     * Paginated account listing, ordered by code so the chart reads in statement
     * order rather than insertion order.
     */
    public function queryAccounts(QueryCriteria $criteria) : \TawasulOS\Domain\DataSet
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tawasulFinanceAccountID',
                'code',
                'name',
                'type',
                'parentAccountID',
                'isPosting',
                'active',
            ])
            ->orderBy(['code ASC']);

        $criteria->addFilterRules([
            'type' => function ($query, $type) {
                return $query
                    ->where('type = :type')
                    ->bindValue('type', $type);
            },
            'active' => function ($query, $active) {
                return $query
                    ->where('active = :active')
                    ->bindValue('active', $active);
            },
            'isPosting' => function ($query, $isPosting) {
                return $query
                    ->where('isPosting = :isPosting')
                    ->bindValue('isPosting', $isPosting);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Every account as a flat array, for select boxes. Inactive accounts are
     * included but flagged so callers can choose whether to offer them.
     */
    public function selectAllAccounts() : array
    {
        return $this->db()->select(
            'SELECT tawasulFinanceAccountID, code, name, type, isPosting, active
               FROM tawasulFinanceAccount
              ORDER BY code'
        )->fetchAll();
    }

    /**
     * Accounts that may legally receive a posting, ordered for a select box.
     */
    public function selectPostingAccounts() : array
    {
        return $this->db()->select(
            "SELECT tawasulFinanceAccountID, code, name, type
               FROM tawasulFinanceAccount
              WHERE isPosting = 'Y' AND active = 'Y'
              ORDER BY code"
        )->fetchAll();
    }

    /**
     * Load an account joined to its parent, for edit screens and validation.
     */
    public function selectAccountWithParent($tawasulFinanceAccountID) : array
    {
        return $this->db()->selectOne(
            'SELECT a.tawasulFinanceAccountID, a.code, a.name, a.type, a.parentAccountID,
                    a.isPosting, a.active,
                    p.name as parentName, p.code as parentCode
               FROM tawasulFinanceAccount a
          LEFT JOIN tawasulFinanceAccount p
                 ON p.tawasulFinanceAccountID = a.parentAccountID
              WHERE a.tawasulFinanceAccountID = :id',
            ['id' => $tawasulFinanceAccountID]
        ) ?: [];
    }

    /**
     * Children of an account, used to render the tree and to block deleting a
     * parent that still has postings or children.
     */
    public function selectChildren($parentAccountID) : array
    {
        return $this->db()->select(
            'SELECT tawasulFinanceAccountID, code, name, type, isPosting, active
               FROM tawasulFinanceAccount
              WHERE parentAccountID = :parent
              ORDER BY code',
            ['parent' => $parentAccountID]
        )->fetchAll();
    }

    /**
     * Whether an account can be deleted: no journal lines, no children.
     */
    public function isAccountInUse($tawasulFinanceAccountID) : array
    {
        return $this->db()->selectOne(
            'SELECT (SELECT COUNT(*) FROM tawasulFinanceJournalLine WHERE tawasulFinanceAccountID = :id) AS lineCount,
                    (SELECT COUNT(*) FROM tawasulFinanceAccount WHERE parentAccountID = :id) AS childCount',
            ['id' => $tawasulFinanceAccountID]
        ) ?: ['lineCount' => 0, 'childCount' => 0];
    }

    /**
     * Insert or update an account.
     *
     * Accounts have an integer surrogate key, so this cannot use the
     * insertAndUpdate() shortcut: that relies on ON DUPLICATE KEY UPDATE, and
     * here the natural key is the code, not the ID. Callers pass the ID when
     * editing and omit it when creating.
     */
    public function saveAccount(array $data) : int
    {
        $id = $data['tawasulFinanceAccountID'] ?? null;

        if (empty($id)) {
            unset($data['tawasulFinanceAccountID']);

            $query = $this
                ->newInsert()
                ->into($this->getTableName())
                ->cols($data);
            $this->runInsert($query);

            return (int) $this->db()->getConnection()->lastInsertId();
        }

        $this->update($id, $data);

        return (int) $id;
    }
}