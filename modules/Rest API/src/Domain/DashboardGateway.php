<?php
namespace Gibbon\Module\RestAPI\Domain;

use PDO;

/**
 * Read-only aggregates behind the API control dashboard.
 *
 * Everything here is a plain COUNT/MAX against core Gibbon tables plus the
 * module's own log and webhook tables, so the dashboard can render without
 * booting the API or touching a gateway that might write.
 */
class DashboardGateway
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    protected function value(string $sql, array $bindings = [], $default = 0)
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($bindings);
            $result = $stmt->fetchColumn();

            return $result === false ? $default : $result;
        } catch (\PDOException $e) {
            return $default;
        }
    }

    protected function rows(string $sql, array $bindings = []): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($bindings);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            return [];
        }
    }

    /** The school year the school is currently operating in. */
    public function currentSchoolYear(): ?array
    {
        $rows = $this->rows("SELECT tawasulSchoolYearID, name, status, firstDay, lastDay
            FROM tawasulSchoolYear WHERE status='Current' ORDER BY sequenceNumber LIMIT 1");

        return $rows[0] ?? null;
    }

    /**
     * "Active schools" in Gibbon terms: the year groups, form groups and
     * campuses that make up the live school structure for the current year.
     */
    public function schools(?string $schoolYearID): array
    {
        return [
            'schoolYears' => (int) $this->value("SELECT COUNT(*) FROM tawasulSchoolYear"),
            'schoolYearsUpcoming' => (int) $this->value("SELECT COUNT(*) FROM tawasulSchoolYear WHERE status='Upcoming'"),
            'yearGroups' => (int) $this->value("SELECT COUNT(*) FROM tawasulYearGroup"),
            'formGroups' => (int) $this->value(
                "SELECT COUNT(*) FROM tawasulFormGroup WHERE tawasulSchoolYearID=:year",
                ['year' => $schoolYearID]
            ),
            'departments' => (int) $this->value("SELECT COUNT(*) FROM tawasulDepartment"),
            'facilities' => (int) $this->value("SELECT COUNT(*) FROM tawasulSpace"),
            'houses' => (int) $this->value("SELECT COUNT(*) FROM tawasulHouse"),
        ];
    }

    /** Enrolled students for the given school year, plus the roll breakdown. */
    public function students(?string $schoolYearID): array
    {
        $enrolled = (int) $this->value(
            "SELECT COUNT(DISTINCT tawasulStudentEnrolment.tawasulPersonID)
             FROM tawasulStudentEnrolment
             JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID)
             WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:year AND tawasulPerson.status='Full'",
            ['year' => $schoolYearID]
        );

        return [
            'enrolled' => $enrolled,
            'expected' => (int) $this->value(
                "SELECT COUNT(*) FROM tawasulPerson WHERE status='Expected' AND tawasulPersonID IN
                 (SELECT tawasulPersonID FROM tawasulStudentEnrolment)"
            ),
            'left' => (int) $this->value("SELECT COUNT(*) FROM tawasulPerson WHERE status='Left' AND tawasulPersonID IN
                 (SELECT tawasulPersonID FROM tawasulStudentEnrolment)"),
            'applications' => (int) $this->value("SELECT COUNT(*) FROM tawasulAdmissionsApplication"),
            'byYearGroup' => $this->rows(
                "SELECT tawasulYearGroup.name AS label, COUNT(DISTINCT tawasulStudentEnrolment.tawasulPersonID) AS total
                 FROM tawasulStudentEnrolment
                 JOIN tawasulYearGroup ON (tawasulYearGroup.tawasulYearGroupID=tawasulStudentEnrolment.tawasulYearGroupID)
                 JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulStudentEnrolment.tawasulPersonID)
                 WHERE tawasulStudentEnrolment.tawasulSchoolYearID=:year AND tawasulPerson.status='Full'
                 GROUP BY tawasulYearGroup.tawasulYearGroupID
                 ORDER BY tawasulYearGroup.sequenceNumber",
                ['year' => $schoolYearID]
            ),
        ];
    }

    /** Staff on the books, split by type. */
    public function staff(): array
    {
        return [
            'total' => (int) $this->value(
                "SELECT COUNT(*) FROM tawasulStaff
                 JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulStaff.tawasulPersonID)
                 WHERE tawasulPerson.status='Full'"
            ),
            'byType' => $this->rows(
                "SELECT tawasulStaff.type AS label, COUNT(*) AS total
                 FROM tawasulStaff
                 JOIN tawasulPerson ON (tawasulPerson.tawasulPersonID=tawasulStaff.tawasulPersonID)
                 WHERE tawasulPerson.status='Full'
                 GROUP BY tawasulStaff.type ORDER BY total DESC"
            ),
            'parents' => (int) $this->value(
                "SELECT COUNT(DISTINCT tawasulPersonID) FROM tawasulFamilyAdult WHERE childDataAccess='Y'"
            ),
            'users' => (int) $this->value("SELECT COUNT(*) FROM tawasulPerson WHERE status='Full'"),
        ];
    }

    /**
     * Live sync status: is the API reachable, how busy has it been, when did
     * each key last call in, and are webhook deliveries landing.
     */
    public function sync(): array
    {
        $keys = $this->rows(
            "SELECT name, active, lastAccess, lastIPAddress, dateExpiry
             FROM restApiKey ORDER BY (lastAccess IS NULL), lastAccess DESC LIMIT 10"
        );

        $lastRequest = $this->value("SELECT MAX(timestamp) FROM restApiLog", [], null);

        return [
            'keysTotal' => (int) $this->value("SELECT COUNT(*) FROM restApiKey"),
            'keysActive' => (int) $this->value("SELECT COUNT(*) FROM restApiKey WHERE active='Y'
                AND (dateExpiry IS NULL OR dateExpiry >= CURDATE())"),
            'tokensLive' => (int) $this->value("SELECT COUNT(*) FROM restApiToken
                WHERE timestampExpiry IS NULL OR timestampExpiry > NOW()"),
            'lastRequest' => $lastRequest,
            'requests24h' => (int) $this->value("SELECT COUNT(*) FROM restApiLog
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"),
            'errors24h' => (int) $this->value("SELECT COUNT(*) FROM restApiLog
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND statusCode >= 400"),
            'writes24h' => (int) $this->value("SELECT COUNT(*) FROM restApiLog
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND method IN ('POST','PATCH','PUT','DELETE')"),
            'averageDuration' => (int) $this->value("SELECT AVG(durationMS) FROM restApiLog
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"),
            'webhooksActive' => (int) $this->value("SELECT COUNT(*) FROM restApiWebhook WHERE active='Y'"),
            'deliveries24h' => (int) $this->value("SELECT COUNT(*) FROM restApiWebhookDelivery
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"),
            'deliveryFailures24h' => (int) $this->value("SELECT COUNT(*) FROM restApiWebhookDelivery
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND success='N'"),
            'keys' => $keys,
            'busiestResources' => $this->rows(
                "SELECT resource AS label, COUNT(*) AS total FROM restApiLog
                 WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND resource <> ''
                 GROUP BY resource ORDER BY total DESC LIMIT 8"
            ),
        ];
    }

    /**
     * A single traffic-light for the header: ok, idle, degraded or off.
     */
    public function status(array $sync, bool $apiEnabled): array
    {
        if (!$apiEnabled) {
            return ['state' => 'off', 'message' => 'The API master switch is off; every request returns 503.'];
        }
        if ($sync['keysActive'] === 0) {
            return ['state' => 'degraded', 'message' => 'No usable API key, so nothing can sync yet.'];
        }
        if (empty($sync['lastRequest'])) {
            return ['state' => 'idle', 'message' => 'Ready, but no client has called the API yet.'];
        }

        $ageMinutes = (int) round((time() - strtotime($sync['lastRequest'])) / 60);
        $errorRate = $sync['requests24h'] > 0 ? ($sync['errors24h'] / $sync['requests24h']) : 0;

        if ($errorRate >= 0.25) {
            return ['state' => 'degraded', 'message' => round($errorRate * 100).'% of calls in the last 24 hours failed.'];
        }
        if ($ageMinutes > 1440) {
            return ['state' => 'idle', 'message' => 'No API traffic for over a day.'];
        }

        return ['state' => 'ok', 'message' => 'Last call '.($ageMinutes < 1 ? 'moments' : $ageMinutes.' minutes').' ago.'];
    }

    /** Everything the dashboard and the /v2/dashboard endpoint need. */
    public function snapshot(bool $apiEnabled): array
    {
        $year = $this->currentSchoolYear();
        $yearID = $year['tawasulSchoolYearID'] ?? null;
        $sync = $this->sync();

        return [
            'schoolYear' => $year,
            'schools' => $this->schools($yearID),
            'students' => $this->students($yearID),
            'staff' => $this->staff(),
            'sync' => $sync,
            'status' => $this->status($sync, $apiEnabled),
            'generated' => date('c'),
        ];
    }
}
