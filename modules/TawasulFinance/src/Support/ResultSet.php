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

TawasulOS — Result set wrapper for the Connection adapter.
*/

namespace Tos\Module\TawasulFinance\Support;

use PDO;
use PDOStatement;

/**
 * Wraps a PDOStatement with the row helpers the gateways call.
 *
 * Extending PDOStatement directly is not possible here because it cannot be
 * instantiated without being returned from a prepared statement, so this holds
 * one and adds the intent-revealing methods the rest of the codebase expects.
 */
class ResultSet
{
    private $statement;
    private $rows;
    private $pdo;

    public function __construct(PDOStatement $statement, PDO $pdo = null)
    {
        $this->statement = $statement;
        $this->rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $this->pdo = $pdo;
    }

    public function isNotEmpty() : bool
    {
        return count($this->rows) > 0;
    }

    public function isEmpty() : bool
    {
        return count($this->rows) === 0;
    }

    public function fetch() : array
    {
        return $this->rows[0] ?? [];
    }

    public function fetchAll() : array
    {
        return $this->rows;
    }

    /**
     * Convert to the DataSet the paginated table renderer consumes.
     *
     * Mirrors TawasulOS\Database\Result::toDataSet(); QueryableGateway::runQuery
     * calls this on whatever select() returns.
     */
    public function toDataSet()
    {
        return new \TawasulOS\Domain\DataSet($this->rows);
    }

    public function count() : int
    {
        return count($this->rows);
    }

    public function getStatement()
    {
        return $this->statement;
    }
}