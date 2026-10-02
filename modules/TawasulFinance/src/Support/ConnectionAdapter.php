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

TawasulOS — Connection adapter for CLI and tests.

The gateways depend on TawasulOS\Contracts\Database\Connection, which the
container normally supplies. Scripts and tests that build their own PDO handle
use this to bridge the two.
*/

namespace Tos\Module\TawasulFinance\Support;

use PDO;
use PDOStatement;
use TawasulOS\Contracts\Database\Connection;

/**
 * Adapts a plain PDO handle to the Connection contract.
 */
class ConnectionAdapter implements Connection
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getConnection()
    {
        return $this->pdo;
    }

    /**
     * Runs a SELECT and returns the rows as plain arrays, with keys passed
     * through exactly as the driver reports them.
     *
     * No case normalisation happens here on purpose: the production Connection
     * does not normalise either, and code that relies on camelCase keys such as
     * tawasulFinanceAccountID would break if this adapter disagreed with it.
     */
    public function fetchRows($query, $bindings = []) : array
    {
        $s = $this->pdo->prepare($query);
        $s->execute($bindings);

        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function selectOne($query, $bindings = [])
    {
        $s = $this->pdo->prepare($query);
        $s->execute($bindings);
        $row = $s->fetch(PDO::FETCH_ASSOC);

        return $row === false ? [] : $row;
    }

    /**
     * Returns a Result-shaped statement. TableAware calls isNotEmpty() and
     * fetch() on the result, so a plain array would not satisfy it.
     */
    public function select($query, $bindings = [])
    {
        $s = $this->pdo->prepare($query);
        $s->execute($bindings);

        return new ResultSet($s, $this->pdo);
    }

    public function insert($query, $bindings = [])
    {
        $this->pdo->prepare($query)->execute($bindings);

        return (int) $this->pdo->lastInsertId();
    }

    public function update($query, $bindings = [])
    {
        $this->pdo->prepare($query)->execute($bindings);

        return true;
    }

    public function delete($query, $bindings = [])
    {
        $s = $this->pdo->prepare($query);
        $s->execute($bindings);

        return $s->rowCount();
    }

    public function statement($query, $bindings = [])
    {
        $this->pdo->prepare($query)->execute($bindings);

        return true;
    }

    public function affectingStatement($query, $bindings = [])
    {
        $s = $this->pdo->prepare($query);
        $s->execute($bindings);

        return $s->rowCount();
    }

    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }

    public function commit()
    {
        return $this->pdo->commit();
    }

    public function rollBack()
    {
        return $this->pdo->rollBack();
    }

    public function executeQuery($bindings = [], $query = "")
    {
        return $this->pdo->prepare($query)->execute($bindings);
    }
}