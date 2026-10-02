<?php
namespace Gibbon\Module\RestAPI\Support;

use PDO;

/**
 * Reads the module's settings once per request, straight from tawasulSetting.
 */
class Settings
{
    protected $values = [];

    public function __construct(PDO $pdo)
    {
        $stmt = $pdo->prepare("SELECT name, value FROM tawasulSetting WHERE scope='Rest API'");
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $this->values[$row['name']] = $row['value'];
        }
    }

    public function get(string $name, $default = null)
    {
        $value = $this->values[$name] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    public function getInt(string $name, int $default): int
    {
        $value = $this->get($name);

        return $value === null ? $default : (int) $value;
    }

    public function isOn(string $name, bool $default = false): bool
    {
        $value = $this->get($name);

        return $value === null ? $default : ($value === 'Y');
    }

    public function all(): array
    {
        return $this->values;
    }
}
