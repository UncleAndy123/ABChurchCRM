<?php
/**
 * ChurchCRM PDF Import — page template
 * Uses the same PhpRenderer pattern as all other ChurchCRM v2 pages.
 */
require_once $sRootPath . '/Include/Header.php';
?>

<div class="content-wrapper">
  <section class="content-header">
    <h1>
      <i class="fa fa-file-pdf-o text-red"></i>
      <?= gettext('PDF Member Import') ?>
      <small><?= gettext('Upload a completed registration form to add a family & person') ?></small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="<?= $sRootPath ?>"><i class="fa fa-home"></i> <?= gettext('Home') ?></a></li>
      <li><a href="<?= $sRootPath ?>/v2/people"><?= gettext('People') ?></a></li>
      <li class="active"><?= gettext('PDF Import') ?></li>
    </ol>
  </section>

  <section class="content">
    <div class="row">

      <!-- ── Left: Upload ─────────────────────────────────────────────── -->
      <div class="col-md-5">
        <div class="box box-primary">
          <div class="box-header with-border">
            <h3 class="box-title">
              <i class="fa fa-upload"></i> <?= gettext('Upload Registration PDF') ?>
            </h3>
          </div>
          <div class="box-body">
            <div id="drop-zone" class="pdf-drop-zone">
              <div class="drop-icon"><i class="fa fa-file-pdf-o"></i></div>
              <p class="drop-text">
                <?= gettext('Drag & drop PDF here') ?><br>
                <small class="text-muted"><?= gettext('or click to browse') ?></small>
              </p>
              <input type="file" id="pdf-file-input" accept=".pdf" style="display:none">
            </div>

            <div id="file-info" class="hidden" style="margin-top:10px">
              <div class="alert alert-info" style="padding:8px 12px; margin:0">
                <i class="fa fa-file-pdf-o"></i>
                <span id="file-name-display"></span>
                <button type="button" class="close" id="clear-file"><span>&times;</span></button>
              </div>
            </div>

            <button id="parse-btn" class="btn btn-primary btn-block" style="margin-top:12px" disabled>
              <i class="fa fa-cog fa-spin hidden" id="parse-spin"></i>
              <i class="fa fa-search" id="parse-icon"></i>
              <?= gettext('Extract Fields from PDF') ?>
            </button>

            <div id="parse-status" class="hidden" style="margin-top:10px"></div>
          </div>
        </div>

        <div class="box box-default">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-info-circle"></i> <?= gettext('How It Works') ?></h3>
          </div>
          <div class="box-body" style="font-size:13px">
            <ol style="padding-left:18px; line-height:2">
              <li><?= gettext('Upload a completed ChurchCRM Registration PDF') ?></li>
              <li><?= gettext('Fields are read in your browser — the PDF never leaves your network') ?></li>
              <li><?= gettext('Review and edit any field before saving') ?></li>
              <li><?= gettext('Click') ?> <strong><?= gettext('Import to ChurchCRM') ?></strong></li>
            </ol>
          </div>
        </div>
      </div>

      <!-- ── Right: Review form ────────────────────────────────────────── -->
      <div class="col-md-7">
        <div class="box box-success">
          <div class="box-header with-border">
            <h3 class="box-title">
              <i class="fa fa-edit"></i> <?= gettext('Review & Edit Extracted Fields') ?>
            </h3>
            <div class="box-tools">
              <span id="field-count-badge" class="badge bg-green hidden"></span>
            </div>
          </div>
          <div class="box-body">

            <div id="review-placeholder" class="text-center text-muted" style="padding:40px 0">
              <i class="fa fa-arrow-left fa-2x"></i>
              <p style="margin-top:10px"><?= gettext('Upload and parse a PDF to see fields here') ?></p>
            </div>

            <form id="import-form" class="hidden">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

              <!-- Family -->
              <div class="import-section">
                <div class="import-section-header bg-primary">
                  <i class="fa fa-home"></i> <?= gettext('Family Information') ?>
                  <span class="label label-default pull-right">family_fam</span>
                </div>
                <div class="import-section-body">
                  <div class="row">
                    <div class="col-xs-12"><?php pdfField('Family Name *', 'fam_Name', 'text', '', true) ?></div>
                  </div>
                  <div class="row">
                    <div class="col-sm-8"><?php pdfField('Address Line 1', 'fam_Address1') ?></div>
                    <div class="col-sm-4"><?php pdfField('Address Line 2', 'fam_Address2') ?></div>
                  </div>
                  <div class="row">
                    <div class="col-sm-5"><?php pdfField('City', 'fam_City') ?></div>
                    <div class="col-sm-3"><?php pdfField('State', 'fam_State') ?></div>
                    <div class="col-sm-4"><?php pdfField('ZIP', 'fam_Zip') ?></div>
                  </div>
                  <div class="row">
                    <div class="col-sm-4"><?php pdfField('Home Phone', 'fam_HomePhone', 'tel') ?></div>
                    <div class="col-sm-4"><?php pdfField('Email', 'fam_Email', 'email') ?></div>
                    <div class="col-sm-4"><?php pdfField('Anniversary', 'fam_WeddingDate', 'text', 'MM/DD/YYYY') ?></div>
                  </div>
                </div>
              </div>

              <!-- Individual -->
              <div class="import-section">
                <div class="import-section-header bg-blue">
                  <i class="fa fa-user"></i> <?= gettext('Individual Information') ?>
                  <span class="label label-default pull-right">person_per</span>
                </div>
                <div class="import-section-body">
                  <div class="row">
                    <div class="col-sm-2"><?php pdfField('Title', 'per_Title') ?></div>
                    <div class="col-sm-3"><?php pdfField('First *', 'per_FirstName', 'text', '', true) ?></div>
                    <div class="col-sm-2"><?php pdfField('Middle', 'per_MiddleName') ?></div>
                    <div class="col-sm-3"><?php pdfField('Last *', 'per_LastName', 'text', '', true) ?></div>
                    <div class="col-sm-2"><?php pdfField('Suffix', 'per_Suffix') ?></div>
                  </div>
                  <div class="row">
                    <div class="col-sm-4"><?php pdfField('Birth Date', 'per_BirthDate', 'text', 'MM/DD/YYYY') ?></div>
                    <div class="col-sm-4"><?php pdfSelect('Gender', 'per_Gender', ['' => '-- Select --', '1' => 'Male', '2' => 'Female']) ?></div>
                    <div class="col-sm-4"><?php pdfSelect('Marital Status', 'per_mrs_ID', $maritalOptions) ?></div>
                  </div>
                  <div class="row">
                    <div class="col-sm-4"><?php pdfSelect('Family Role', 'per_fmr_ID', $roleOptions) ?></div>
                    <div class="col-sm-4"><?php pdfSelect('Classification', 'per_cls_ID', $clsOptions) ?></div>
                    <div class="col-sm-4"><?php pdfField('Membership Date', 'per_MembershipDate', 'text', 'MM/DD/YYYY') ?></div>
                  </div>
                  <div class="row">
                    <div class="col-sm-4"><?php pdfField('Cell', 'per_CellPhone', 'tel') ?></div>
                    <div class="col-sm-4"><?php pdfField('Home Phone', 'per_HomePhone', 'tel') ?></div>
                    <div class="col-sm-4"><?php pdfField('Work Phone', 'per_WorkPhone', 'tel') ?></div>
                  </div>
                  <div class="row">
                    <div class="col-sm-6"><?php pdfField('Personal Email', 'per_Email', 'email') ?></div>
                    <div class="col-sm-6"><?php pdfField('Work Email', 'per_WorkEmail', 'email') ?></div>
                  </div>
                </div>
              </div>

              <!-- Individual Address -->
              <div class="import-section">
                <div class="import-section-header bg-navy">
                  <i class="fa fa-map-marker"></i> <?= gettext('Individual Address') ?>
                  <small style="font-weight:normal; font-size:11px"> — <?= gettext('leave blank if same as family') ?></small>
                </div>
                <div class="import-section-body">
                  <div class="row">
                    <div class="col-sm-8"><?php pdfField('Address Line 1', 'per_Address1') ?></div>
                    <div class="col-sm-4"><?php pdfField('Line 2', 'per_Address2') ?></div>
                  </div>
                  <div class="row">
                    <div class="col-sm-5"><?php pdfField('City', 'per_City') ?></div>
                    <div class="col-sm-3"><?php pdfField('State', 'per_State') ?></div>
                    <div class="col-sm-4"><?php pdfField('ZIP', 'per_Zip') ?></div>
                  </div>
                </div>
              </div>

              <!-- Notes -->
              <div class="import-section">
                <div class="import-section-header" style="background:#6c757d">
                  <i class="fa fa-sticky-note"></i> <?= gettext('Notes') ?>
                  <span class="label label-default pull-right">note_nte</span>
                </div>
                <div class="import-section-body">
                  <div class="form-group">
                    <textarea name="notes_nte" id="field-notes_nte" class="form-control" rows="3"
                              placeholder="<?= gettext('Additional notes…') ?>"></textarea>
                  </div>
                </div>
              </div>

              <!-- Actions -->
              <div class="import-actions">
                <div class="row">
                  <div class="col-sm-5">
                    <div class="checkbox" style="margin:0">
                      <label><input type="checkbox" id="dry-run-check"> <?= gettext('Dry run (preview SQL only)') ?></label>
                    </div>
                    <div class="checkbox">
                      <label><input type="checkbox" name="skip_dup_check"> <?= gettext('Skip duplicate check') ?></label>
                    </div>
                  </div>
                  <div class="col-sm-7 text-right" style="padding-top:4px">
                    <button type="button" id="preview-sql-btn" class="btn btn-default">
                      <i class="fa fa-code"></i> <?= gettext('Preview SQL') ?>
                    </button>
                    <button type="submit" id="import-submit-btn" class="btn btn-success btn-lg">
                      <i class="fa fa-database"></i> <?= gettext('Import to ChurchCRM') ?>
                    </button>
                  </div>
                </div>
              </div>
            </form>
          </div>
        </div>

        <div id="result-box" class="hidden">
          <div class="alert" id="result-alert">
            <div id="result-message"></div>
            <div id="result-links" class="hidden" style="margin-top:10px">
              <a id="link-family" href="#" class="btn btn-sm btn-default">
                <i class="fa fa-home"></i> <?= gettext('View Family') ?>
              </a>
              <a id="link-person" href="#" class="btn btn-sm btn-primary">
                <i class="fa fa-user"></i> <?= gettext('View Person') ?>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<!-- SQL Modal -->
<div class="modal fade" id="sql-modal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        <h4 class="modal-title"><i class="fa fa-code"></i> <?= gettext('Generated SQL') ?></h4>
      </div>
      <div class="modal-body" style="padding:0">
        <pre id="sql-preview-content" class="sql-preview"></pre>
      </div>
      <div class="modal-footer">
        <button type="button" id="copy-sql-btn" class="btn btn-default">
          <i class="fa fa-copy"></i> <?= gettext('Copy SQL') ?>
        </button>
        <button type="button" class="btn btn-primary" data-dismiss="modal"><?= gettext('Close') ?></button>
      </div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="<?= $sRootPath ?>/skin/external/pdf-import/pdf-import.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="<?= $sRootPath ?>/skin/external/pdf-import/pdf-import.js"></script>

<?php require_once $sRootPath . '/Include/Footer.php'; ?>

<?php
function pdfField(string $label, string $name, string $type = 'text',
                  string $placeholder = '', bool $required = false): void
{
    $req = $required ? ' <span class="text-danger">*</span>' : '';
    $r   = $required ? ' required' : '';
    echo <<<HTML
<div class="form-group">
  <label class="field-label">{$label}{$req}</label>
  <input type="{$type}" name="{$name}" id="field-{$name}"
         class="form-control input-sm" placeholder="{$placeholder}"{$r}>
</div>
HTML;
}

function pdfSelect(string $label, string $name, array $options): void
{
    $opts = '';
    foreach ($options as $val => $txt) {
        $opts .= '<option value="' . htmlspecialchars((string)$val) . '">'
               . htmlspecialchars($txt) . '</option>';
    }
    echo <<<HTML
<div class="form-group">
  <label class="field-label">{$label}</label>
  <select name="{$name}" id="field-{$name}" class="form-control input-sm">
    {$opts}
  </select>
</div>
HTML;
}