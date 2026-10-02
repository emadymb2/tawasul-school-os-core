<?php
namespace Tos\Module\TawasulChat\Support;

/**
 * Reads the module's own tawasulSetting rows.
 *
 * The scope is the module name, which is also what the uninstaller deletes by,
 * so every setting here disappears with the module. Values are read once per
 * request: a chat page can ask for the poll timeout, the presence timeout and
 * the attachment limit in the space of a single poll, and there is no reason to
 * pay for three queries to do it.
 */
class Settings
{
    /** Fallbacks for a setting that has not been created yet. */
    const DEFAULTS = [
        'pollTimeoutSeconds' => '25',
        'presenceTimeoutSeconds' => '60',
        'attachmentMaxSizeMB' => '25',
        'voiceNotesEnabled' => 'Y',
        'typingIndicatorEnabled' => 'Y',
        'readReceiptsEnabled' => 'Y',
        'editingEnabled' => 'Y',
        'messageRetentionDays' => '0',
        'maxGroupSize' => '100',
        'unreadBadgeEnabled' => 'Y',
    ];

    /** @var array<string,string>|null */
    private static $cache = [];

    /** @var \PDO */
    private $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function get(string $name, string $default = null): string
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach ($this->db->query("SELECT name, value FROM `tawasulSetting` WHERE scope = 'TawasulMessenger'") as $row) {
                self::$cache[$row['name']] = (string) $row['value'];
            }
        }

        if (isset(self::$cache[$name])) {
            return self::$cache[$name];
        }

        return $default ?? (self::DEFAULTS[$name] ?? '');
    }

    /** Cast to int, clamped into a sane range so a bad setting cannot hang a request. */
    public function getInt(string $name, int $min, int $max): int
    {
        $value = (int) $this->get($name);

        return max($min, min($max, $value));
    }

    public function isOn(string $name): bool
    {
        return $this->get($name) === 'Y';
    }

    /**
     * How long a poll may be held open.
     *
     * Never longer than the interpreter's own limit: a poll that outlives
     * max_execution_time would be killed with a partial body, which the client
     * would see as a broken response rather than as a timeout. Leaving a couple
     * of seconds of headroom is what stops that.
     */
    public function pollTimeout(): int
    {
        $configured = $this->getInt('pollTimeoutSeconds', 5, 120);
        $phpLimit = (int) ini_get('max_execution_time');

        if ($phpLimit > 0) {
            $configured = min($configured, max(5, $phpLimit - 2));
        }

        return $configured;
    }

    /** Drop the cache. Used by the settings page after a write, and by tests. */
    public static function flush(): void
    {
        self::$cache = null;
    }
}
