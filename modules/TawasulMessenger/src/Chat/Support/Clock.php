<?php
namespace Tos\Module\TawasulChat\Support;

/**
 * The database's clock.
 *
 * TawasulOS sets PHP's timezone from the installation settings, and MySQL keeps
 * its own from the server. Those two are not reliably the same: on a stock
 * European host PHP ends up on Europe/Helsinki while MySQL's SYSTEM zone is an
 * hour behind for part of the year. Any chat logic that compares a PHP timestamp
 * against a column MySQL wrote with NOW() is therefore comparing two different
 * clocks, and the error is silently an hour — enough to delete messages an hour
 * early and to make the poll cursor skip or repeat messages.
 *
 * So every time comparison in this module is done by the database, and this
 * class is the single place the module asks it for the time. Nothing here should
 * call date() or time() and then compare the result with a datetime column.
 */
class Clock
{
    /** @var \PDO */
    private $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /** The database's current time, in the same format as a datetime column. */
    public function now(): string
    {
        return (string) $this->db->query('SELECT NOW()')->fetchColumn();
    }

    /**
     * The database's current time, that many seconds ago.
     *
     * Used for cutoffs so the interval is applied by the same clock that wrote
     * the rows being compared against.
     */
    public function ago(int $seconds): string
    {
        $seconds = max(0, $seconds);

        return (string) $this->db->query('SELECT DATE_SUB(NOW(), INTERVAL '.$seconds.' SECOND)')->fetchColumn();
    }

    /**
     * How old a datetime column value is, in seconds, as the database sees it.
     *
     * Returns null for a null value rather than a large number, so a caller can
     * tell "never" from "long ago".
     */
    public function ageInSeconds(?string $timestamp): ?int
    {
        if ($timestamp === null || trim($timestamp) === '') {
            return null;
        }

        $stmt = $this->db->prepare('SELECT TIMESTAMPDIFF(SECOND, :when, NOW())');
        $stmt->execute(['when' => $timestamp]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (int) $value;
    }

    /**
     * Whether a datetime column value is within $seconds of now.
     *
     * The comparison happens in SQL, which is the only way to be sure both sides
     * are on the same clock.
     */
    public function isWithin(?string $timestamp, int $seconds): bool
    {
        if ($timestamp === null || trim($timestamp) === '') {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT :when >= DATE_SUB(NOW(), INTERVAL '.(int) max(0, $seconds).' SECOND)'
        );
        $stmt->execute(['when' => $timestamp]);

        return (bool) $stmt->fetchColumn();
    }
}
