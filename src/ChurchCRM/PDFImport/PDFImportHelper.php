<?php
/**
 * ChurchCRM PDF Import Helper
 *
 * File location in container:
 *   /var/www/html/src/ChurchCRM/PDFImport/PdfImportHelper.php
 *
 * Namespace matches ChurchCRM's PSR-4 autoloader:
 *   "ChurchCRM\\" => "src/ChurchCRM/"
 * So this class is autoloaded with no require_once needed.
 */

namespace ChurchCRM\PDFImport;

use PDO;
use DateTime;

class PdfImportHelper
{
    // ── Date / phone normalisation ────────────────────────────────────────

    public static function normaliseDate(?string $val): ?string
    {
        if (!$val || trim($val) === '') {
            return null;
        }
        $val = trim($val);
        foreach (['m/d/Y', 'm-d-Y', 'Y-m-d', 'd/m/Y'] as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $val);
            if ($dt && $dt->format($fmt) === $val) {
                return $dt->format('Y-m-d');
            }
        }
        return null;
    }

    public static function normalisePhone(?string $val): string
    {
        if (!$val || trim($val) === '') {
            return '';
        }
        $digits = preg_replace('/\D/', '', $val);
        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s',
                substr($digits, 0, 3),
                substr($digits, 3, 3),
                substr($digits, 6, 4)
            );
        }
        return trim($val);
    }

    // ── Build DB records from POST data ───────────────────────────────────

    public static function buildFamilyRecord(array $post, int $userId = 1): array
    {
        $today = date('Y-m-d');
        return array_filter([
            'fam_Name'           => self::s($post, 'fam_Name'),
            'fam_Address1'       => self::s($post, 'fam_Address1'),
            'fam_Address2'       => self::s($post, 'fam_Address2') ?: null,
            'fam_City'           => self::s($post, 'fam_City'),
            'fam_State'          => self::s($post, 'fam_State'),
            'fam_Zip'            => self::s($post, 'fam_Zip'),
            'fam_Country'        => self::s($post, 'fam_Country') ?: 'USA',
            'fam_HomePhone'      => self::normalisePhone(self::s($post, 'fam_HomePhone')) ?: null,
            'fam_Email'          => self::s($post, 'fam_Email') ?: null,
            'fam_WeddingDate'    => self::normaliseDate(self::s($post, 'fam_WeddingDate')),
            'fam_DateEntered'    => $today,
            'fam_DateLastEdited' => $today,
            'fam_EnteredBy'      => $userId,
            'fam_Active'         => 1,
        ], fn($v) => $v !== null);
    }

    public static function buildPersonRecord(array $post, int $famId = 0, int $userId = 1): array
    {
        $today = date('Y-m-d');
        return array_filter([
            'per_fam_ID'          => $famId,
            'per_Title'           => self::s($post, 'per_Title') ?: null,
            'per_FirstName'       => self::s($post, 'per_FirstName'),
            'per_MiddleName'      => self::s($post, 'per_MiddleName') ?: null,
            'per_LastName'        => self::s($post, 'per_LastName'),
            'per_Suffix'          => self::s($post, 'per_Suffix') ?: null,
            'per_Address1'        => self::s($post, 'per_Address1') ?: null,
            'per_Address2'        => self::s($post, 'per_Address2') ?: null,
            'per_City'            => self::s($post, 'per_City') ?: null,
            'per_State'           => self::s($post, 'per_State') ?: null,
            'per_Zip'             => self::s($post, 'per_Zip') ?: null,
            'per_HomePhone'       => self::normalisePhone(self::s($post, 'per_HomePhone')) ?: null,
            'per_WorkPhone'       => self::normalisePhone(self::s($post, 'per_WorkPhone')) ?: null,
            'per_CellPhone'       => self::normalisePhone(self::s($post, 'per_CellPhone')) ?: null,
            'per_Email'           => self::s($post, 'per_Email') ?: null,
            'per_WorkEmail'       => self::s($post, 'per_WorkEmail') ?: null,
            'per_BirthDate'       => self::normaliseDate(self::s($post, 'per_BirthDate')),
            'per_Gender'          => (int) self::s($post, 'per_Gender', '0') ?: null,
            'per_fmr_ID'          => (int) self::s($post, 'per_fmr_ID', '0') ?: null,
            'per_cls_ID'          => (int) self::s($post, 'per_cls_ID', '0') ?: null,
            'per_mrs_ID'          => (int) self::s($post, 'per_mrs_ID', '0') ?: null,
            'per_MembershipDate'  => self::normaliseDate(self::s($post, 'per_MembershipDate')),
            'per_DateEntered'     => $today,
            'per_DateLastEdited'  => $today,
            'per_EnteredBy'       => $userId,
            'per_Active'          => 1,
        ], fn($v) => $v !== null);
    }

    // ── Duplicate detection ───────────────────────────────────────────────

    public static function findExistingFamily(PDO $pdo, string $name, string $addr1): int
    {
        $st = $pdo->prepare(
            'SELECT fam_ID FROM family_fam
             WHERE fam_Name = :n AND fam_Address1 = :a LIMIT 1'
        );
        $st->execute([':n' => $name, ':a' => $addr1]);
        return (int) ($st->fetchColumn() ?: 0);
    }

    public static function findExistingPerson(PDO $pdo, int $famId, string $first, string $last): int
    {
        $st = $pdo->prepare(
            'SELECT per_ID FROM person_per
             WHERE per_fam_ID = :f AND per_FirstName = :fn AND per_LastName = :ln LIMIT 1'
        );
        $st->execute([':f' => $famId, ':fn' => $first, ':ln' => $last]);
        return (int) ($st->fetchColumn() ?: 0);
    }

    // ── Insert helpers ────────────────────────────────────────────────────

    public static function insertRecord(PDO $pdo, string $table, array $data): int
    {
        $cols = implode(', ', array_keys($data));
        $ph   = implode(', ', array_map(fn($k) => ':' . $k, array_keys($data)));
        $st   = $pdo->prepare("INSERT INTO {$table} ({$cols}) VALUES ({$ph})");
        foreach ($data as $k => $v) {
            $st->bindValue(':' . $k, $v);
        }
        $st->execute();
        return (int) $pdo->lastInsertId();
    }

    public static function insertNote(PDO $pdo, int $perId, int $famId,
                                      string $text, int $userId = 1): void
    {
        if (!trim($text)) {
            return;
        }
        $st = $pdo->prepare(
            'INSERT INTO note_nte
             (nte_per_ID, nte_fam_ID, nte_text, nte_DateEntered, nte_Private, nte_EnteredBy)
             VALUES (:p, :f, :t, :d, 0, :u)'
        );
        $st->execute([
            ':p' => $perId,
            ':f' => $famId,
            ':t' => $text,
            ':d' => date('Y-m-d'),
            ':u' => $userId,
        ]);
    }

    // ── SQL preview generator ─────────────────────────────────────────────

    public static function generateSql(array $fam, array $per, string $notes): string
    {
        $today = date('Y-m-d');
        $q = static fn($v) => $v === null ? 'NULL'
            : "'" . str_replace("'", "''", (string)$v) . "'";

        $famCols = implode(', ', array_keys($fam));
        $famVals = implode(', ', array_map($q, array_values($fam)));

        $perData = $per;
        $perData['per_fam_ID'] = '__FAM_ID__'; // placeholder for readability
        $perCols = implode(', ', array_keys($perData));
        $perVals = implode(', ', array_map(
            fn($k, $v) => $k === 'per_fam_ID' ? '@fam_id' : $q($v),
            array_keys($perData),
            array_values($perData)
        ));

        $out  = "-- ① family_fam\n";
        $out .= "INSERT INTO family_fam ({$famCols})\nVALUES ({$famVals});\n";
        $out .= "SET @fam_id = LAST_INSERT_ID();\n\n";
        $out .= "-- ② person_per\n";
        $out .= "INSERT INTO person_per ({$perCols})\nVALUES ({$perVals});\n";

        if (trim($notes)) {
            $out .= "\n-- ③ note_nte\n";
            $out .= "INSERT INTO note_nte\n";
            $out .= "  (nte_per_ID, nte_fam_ID, nte_text, nte_DateEntered, nte_Private)\n";
            $out .= "VALUES (LAST_INSERT_ID(), @fam_id, {$q($notes)}, '{$today}', 0);\n";
        }

        return $out;
    }

    // ── Live DB lookups for dropdowns ─────────────────────────────────────
    // These query your actual ChurchCRM DB so IDs always match your install.

    public static function getRoleOptions(): array
    {
        $defaults = [
            0 => gettext('-- Select --'),
            1 => gettext('Head of Household'),
            2 => gettext('Spouse'),
            3 => gettext('Child'),
            4 => gettext('Other'),
        ];
        try {
            $pdo  = \Propel\Runtime\Propel::getConnection()->getWrappedConnection();
            $rows = $pdo->query(
                'SELECT fmr_ID, fmr_Name FROM famroles_fmr ORDER BY fmr_ID'
            )->fetchAll(PDO::FETCH_KEY_PAIR);
            return $rows ? [0 => gettext('-- Select --')] + $rows : $defaults;
        } catch (\Exception $e) {
            return $defaults;
        }
    }

    public static function getClassificationOptions(): array
    {
        $defaults = [
            0 => gettext('-- Select --'),
            1 => gettext('Member'),
            2 => gettext('Regular Attender'),
            3 => gettext('Guest'),
            4 => gettext('Non-Attender'),
            5 => gettext('Staff'),
        ];
        try {
            $pdo  = \Propel\Runtime\Propel::getConnection()->getWrappedConnection();
            $rows = $pdo->query(
                'SELECT lsr_ID, lsr_OptionName FROM listrole_lsr ORDER BY lsr_ID'
            )->fetchAll(PDO::FETCH_KEY_PAIR);
            return $rows ? [0 => gettext('-- Select --')] + $rows : $defaults;
        } catch (\Exception $e) {
            return $defaults;
        }
    }

    public static function getMaritalOptions(): array
    {
        $defaults = [
            0 => gettext('-- Select --'),
            1 => gettext('Single'),
            2 => gettext('Married'),
            3 => gettext('Separated'),
            4 => gettext('Divorced'),
            5 => gettext('Widowed'),
        ];
        try {
            $pdo  = \Propel\Runtime\Propel::getConnection()->getWrappedConnection();
            $rows = $pdo->query(
                'SELECT msr_ID, msr_OptionName FROM listmaritalstatus_msr ORDER BY msr_ID'
            )->fetchAll(PDO::FETCH_KEY_PAIR);
            return $rows ? [0 => gettext('-- Select --')] + $rows : $defaults;
        } catch (\Exception $e) {
            return $defaults;
        }
    }

    // ── Internal helper ───────────────────────────────────────────────────

    private static function s(array $arr, string $key, string $default = ''): string
    {
        return trim($arr[$key] ?? $default);
    }
}
