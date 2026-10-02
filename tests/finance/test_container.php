<?php
// Verify the accounting gateways resolve through the real League container,
// exactly as the pages request them.
require __DIR__ . '/../../vendor/autoload.php';

$password = getenv('DB_PASSWORD');
if ($password === false || $password === '') {
    fwrite(STDERR, "Set DB_PASSWORD first (see tools/merge/check_identifiers.php).\n");
    exit(2);
}
$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST') ?: 'localhost', getenv('DB_NAME') ?: 'tos1');
$pdo = new PDO($dsn, getenv('DB_USER') ?: 'tos', $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$conn = new class($pdo) implements \TawasulOS\Contracts\Database\Connection {
    private $pdo;
    public function __construct(PDO $p){$this->pdo=$p;}
    public function getConnection(){return $this->pdo;}
    public function selectOne($q,$b=[]){$s=$this->pdo->prepare($q);$s->execute($b);$r=$s->fetch(\PDO::FETCH_ASSOC);return $r===false?[]:$r;}
    public function select($q,$b=[]){$s=$this->pdo->prepare($q);$s->execute($b);return new \Tos\Module\TawasulFinance\Support\ResultSet($s,$this->pdo);}
    public function insert($q,$b=[]){$this->pdo->prepare($q)->execute($b);return (int)$this->pdo->lastInsertId();}
    public function update($q,$b=[]){$this->pdo->prepare($q)->execute($b);return true;}
    public function delete($q,$b=[]){$s=$this->pdo->prepare($q);$s->execute($b);return $s->rowCount();}
    public function statement($q,$b=[]){$this->pdo->prepare($q)->execute($b);return true;}
    public function affectingStatement($q,$b=[]){$s=$this->pdo->prepare($q);$s->execute($b);return $s->rowCount();}
    public function beginTransaction(){return $this->pdo->beginTransaction();}
    public function commit(){return $this->pdo->commit();}
    public function rollBack(){return $this->pdo->rollBack();}
    public function executeQuery($b=[],$q=""){return $this->pdo->prepare($q)->execute($b);}
};

$container = new League\Container\Container();
$container->add(\TawasulOS\Contracts\Database\Connection::class, $conn);
$container->delegate(new League\Container\ReflectionContainer);
require __DIR__ . '/../../modules/TawasulFinance/src/bootstrap.php';

$pass=0;$fail=0;
function check($l,$ok,$d=''){global $pass,$fail;$ok?$pass++:$fail++;echo ($ok?"  PASS  ":"  FAIL  ").$l.($d?"  -- $d":"")."\n";}

$ns='Tos\\Module\\TawasulFinance\\Domain\\';
foreach (['AccountGateway','FiscalYearGateway','JournalGateway','LedgerReportGateway','ChartOfAccounts'] as $g) {
    try {
        $o = $container->get($ns.$g);
        check("container resolves $g", $o instanceof ($ns.$g), get_class($o));
    } catch (\Throwable $e) {
        check("container resolves $g", false, get_class($e).': '.$e->getMessage());
    }
}

// Exercise the real query path a page would call.
$acct = $container->get($ns.'AccountGateway');
$criteria = $acct->newQueryCriteria(false);
$criteria->page(1)->pageSize(5);
$ds = $acct->queryAccounts($criteria);
check('queryAccounts returns a DataSet', $ds instanceof \TawasulOS\Domain\DataSet, get_class($ds));
check('queryAccounts returns rows', $ds->count() > 0, 'count='.$ds->count());

$posting = $acct->selectPostingAccounts();
check('selectPostingAccounts returns rows', count($posting) > 0, 'count='.count($posting));

$rep = $container->get($ns.'LedgerReportGateway');
$tb = $rep->trialBalance();
check('trialBalance on live returns rows', is_array($tb['rows']), 'rows='.count($tb['rows']));
check('live ledger balances (empty)', $tb['isBalanced']);

echo "\n$pass passed, $fail failed\n";
exit($fail>0?1:0);
