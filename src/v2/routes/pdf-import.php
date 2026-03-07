<?php
/**
 * ChurchCRM PDF Import — Slim 4 Routes
 *
 * File location in container:
 *   /var/www/html/src/external/routes/pdf-import.php
 *
 * This file is auto-discovered by ChurchCRM's route loader.
 * It registers two endpoints:
 *
 *   GET  /v2/pdf-import          — renders the import page (Twig)
 *   POST /v2/pdf-import/submit   — AJAX endpoint, returns JSON
 */

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\PDFImport\PdfImportHelper;
use Slim\Http\Request;
use Slim\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// ── GET  /v2/pdf-import ──────────────────────────────────────────────────────
$app->get('/v2/pdf-import', function (ServerRequestInterface $request, ResponseInterface $response) {

    // Auth check — redirect to login if no session
    $currentUser = AuthenticationManager::GetCurrentUser();
    if (!$currentUser) {
        return $response->withHeader('Location', '/session/begin')->withStatus(302);
    }

    // Load live dropdown options from DB (falls back to defaults on failure)
    $roleOptions    = PdfImportHelper::getRoleOptions();
    $clsOptions     = PdfImportHelper::getClassificationOptions();
    $maritalOptions = PdfImportHelper::getMaritalOptions();

    // Generate CSRF token
    if (empty($_SESSION['pdf_import_csrf'])) {
        $_SESSION['pdf_import_csrf'] = bin2hex(random_bytes(32));
    }

    return $this->get('view')->render($response, 'pdf-import.html.twig', [
        'pageTitle'       => gettext('PDF Member Import'),
        'roleOptions'     => $roleOptions,
        'clsOptions'      => $clsOptions,
        'maritalOptions'  => $maritalOptions,
        'csrfToken'       => $_SESSION['pdf_import_csrf'],
        'currentUser'     => $currentUser,
    ]);

})->setName('pdf-import');


// ── POST /v2/pdf-import/submit ────────────────────────────────────────────────
$app->post('/v2/pdf-import/submit', function (ServerRequestInterface $request, ResponseInterface $response) {

    // Always respond JSON
    $response = $response->withHeader('Content-Type', 'application/json');

    $fail = function (string $msg, int $code = 400) use ($response): ResponseInterface {
        $response->getBody()->write(json_encode(['success' => false, 'message' => $msg]));
        return $response->withStatus($code);
    };

    // Auth
    $currentUser = AuthenticationManager::GetCurrentUser();
    if (!$currentUser) {
        return $fail('Not authenticated', 401);
    }

    // Parse JSON body
    $post = $request->getParsedBody();
    if (!is_array($post)) {
        $raw  = (string) $request->getBody();
        $post = json_decode($raw, true) ?? [];
    }

    // CSRF
    $token = $post['csrf_token'] ?? '';
    if (empty($_SESSION['pdf_import_csrf']) || !hash_equals($_SESSION['pdf_import_csrf'], $token)) {
        return $fail('Invalid security token — please reload the page.');
    }

    // Required fields
    $famName  = trim($post['fam_Name'] ?? '');
    $perFirst = trim($post['per_FirstName'] ?? '');
    $perLast  = trim($post['per_LastName'] ?? '');

    if (!$famName) {
        return $fail('Family Name is required.');
    }
    if (!$perFirst && !$perLast) {
        return $fail('First Name or Last Name is required.');
    }

    // Build records
    $famRecord = PdfImportHelper::buildFamilyRecord($post, $currentUser->getID());
    $perRecord = PdfImportHelper::buildPersonRecord($post, 0, $currentUser->getID());
    $notes     = trim($post['notes_nte'] ?? '');

    // Dry run — return SQL without writing
    if (!empty($post['dry_run'])) {
        $sql = PdfImportHelper::generateSql($famRecord, $perRecord, $notes);
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Dry run complete — no data was written.',
            'sql'     => $sql,
            'famId'   => 0,
            'perId'   => 0,
        ]));
        return $response;
    }

    // Live import
    try {
        $pdo = Propel\Runtime\Propel::getConnection()->getWrappedConnection();

        $pdo->beginTransaction();

        // Family — dedup check
        $famId      = 0;
        $famCreated = false;
        if (empty($post['skip_dup_check'])) {
            $famId = PdfImportHelper::findExistingFamily($pdo, $famName, $famRecord['fam_Address1'] ?? '');
        }
        if (!$famId) {
            $famId      = PdfImportHelper::insertRecord($pdo, 'family_fam', $famRecord);
            $famCreated = true;
        }

        // Person — dedup check
        $perRecord['per_fam_ID'] = $famId;
        $perId      = 0;
        $perCreated = false;
        if (empty($post['skip_dup_check'])) {
            $perId = PdfImportHelper::findExistingPerson($pdo, $famId, $perFirst, $perLast);
        }
        if (!$perId) {
            $perId      = PdfImportHelper::insertRecord($pdo, 'person_per', $perRecord);
            $perCreated = true;
        }

        // Note
        if ($notes) {
            PdfImportHelper::insertNote($pdo, $perId, $famId, $notes, $currentUser->getID());
        }

        $pdo->commit();

        $statusParts = [
            $famCreated ? "Family created (ID #{$famId})" : "Existing family matched (ID #{$famId})",
            $perCreated ? "Person created (ID #{$perId})" : "Existing person matched (ID #{$perId})",
        ];

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => implode(' · ', $statusParts),
            'famId'   => $famId,
            'perId'   => $perId,
            'sql'     => PdfImportHelper::generateSql($famRecord, $perRecord, $notes),
        ]));
        return $response;

    } catch (\Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[ChurchCRM PDF Import] ' . $e->getMessage());
        return $fail('Database error: ' . $e->getMessage(), 500);
    }

})->setName('pdf-import-submit');

