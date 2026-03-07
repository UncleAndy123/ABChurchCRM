<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\PDFImport\PdfImportHelper;
use ChurchCRM\dto\SystemURLs;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// ── GET /pdf-import ───────────────────────────────────────────────────────────
$app->get('/pdf-import', function (Request $request, Response $response): Response {
    return pdfImportView($request, $response, []);
});

// ── POST /pdf-import/submit ───────────────────────────────────────────────────
$app->post('/pdf-import/submit', function (Request $request, Response $response): Response {
    return pdfImportSubmit($request, $response, []);
});

function pdfImportView(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/pdf-import/');

    if (empty($_SESSION['pdf_import_csrf'])) {
        $_SESSION['pdf_import_csrf'] = bin2hex(random_bytes(32));
    }

    $pageArgs = [
        'sRootPath'      => SystemURLs::getRootPath(),
        'sPageTitle'     => gettext('PDF Member Import'),
        'roleOptions'    => PdfImportHelper::getRoleOptions(),
        'clsOptions'     => PdfImportHelper::getClassificationOptions(),
        'maritalOptions' => PdfImportHelper::getMaritalOptions(),
        'csrfToken'      => $_SESSION['pdf_import_csrf'],
    ];

    return $renderer->render($response, 'pdf-import-view.php', $pageArgs);
}

function pdfImportSubmit(Request $request, Response $response, array $args): Response
{
    $response = $response->withHeader('Content-Type', 'application/json');

    $fail = function (string $msg, int $code = 400) use ($response): Response {
        $response->getBody()->write(json_encode(['success' => false, 'message' => $msg]));
        return $response->withStatus($code);
    };

    $currentUser = AuthenticationManager::GetCurrentUser();
    if (!$currentUser) {
        return $fail('Not authenticated', 401);
    }

    $post = $request->getParsedBody();
    if (!is_array($post)) {
        $post = json_decode((string) $request->getBody(), true) ?? [];
    }

    $token = $post['csrf_token'] ?? '';
    if (empty($_SESSION['pdf_import_csrf']) || !hash_equals($_SESSION['pdf_import_csrf'], $token)) {
        return $fail('Invalid security token — please reload the page.');
    }

    $famName  = trim($post['fam_Name'] ?? '');
    $perFirst = trim($post['per_FirstName'] ?? '');
    $perLast  = trim($post['per_LastName'] ?? '');

    if (!$famName) {
        return $fail('Family Name is required.');
    }
    if (!$perFirst && !$perLast) {
        return $fail('First Name or Last Name is required.');
    }

    $famRecord = PdfImportHelper::buildFamilyRecord($post, $currentUser->getID());
    $perRecord = PdfImportHelper::buildPersonRecord($post, 0, $currentUser->getID());
    $notes     = trim($post['notes_nte'] ?? '');

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

    try {
        $pdo = \Propel\Runtime\Propel::getConnection()->getWrappedConnection();
        $pdo->beginTransaction();

        $famId = 0;
        if (empty($post['skip_dup_check'])) {
            $famId = PdfImportHelper::findExistingFamily($pdo, $famName, $famRecord['fam_Address1'] ?? '');
        }
        $famCreated = false;
        if (!$famId) {
            $famId      = PdfImportHelper::insertRecord($pdo, 'family_fam', $famRecord);
            $famCreated = true;
        }

        $perRecord['per_fam_ID'] = $famId;
        $perId = 0;
        if (empty($post['skip_dup_check'])) {
            $perId = PdfImportHelper::findExistingPerson($pdo, $famId, $perFirst, $perLast);
        }
        $perCreated = false;
        if (!$perId) {
            $perId      = PdfImportHelper::insertRecord($pdo, 'person_per', $perRecord);
            $perCreated = true;
        }

        if ($notes) {
            PdfImportHelper::insertNote($pdo, $perId, $famId, $notes, $currentUser->getID());
        }

        $pdo->commit();

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => implode(' · ', [
                $famCreated ? "Family created (ID #{$famId})" : "Existing family matched (ID #{$famId})",
                $perCreated ? "Person created (ID #{$perId})" : "Existing person matched (ID #{$perId})",
            ]),
            'famId' => $famId,
            'perId' => $perId,
            'sql'   => PdfImportHelper::generateSql($famRecord, $perRecord, $notes),
        ]));
        return $response;

    } catch (\Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[ChurchCRM PDF Import] ' . $e->getMessage());
        return $fail('Database error: ' . $e->getMessage(), 500);
    }
}