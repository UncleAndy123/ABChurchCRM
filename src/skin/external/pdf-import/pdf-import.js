/**
 * ChurchCRM PDF Import — client-side logic  (v7 / Slim 4 edition)
 *
 * File location: /var/www/html/skin/external/pdf-import/pdf-import.js
 *
 * Depends on:
 *   - PDF.js  (loaded in the Twig template before this script)
 *   - jQuery  (already loaded by ChurchCRM's AdminLTE layout)
 *   - Bootstrap (already loaded)
 */

(function ($) {
    'use strict';

    /* ── PDF.js worker ─────────────────────────────────────────────────────
       For fully offline / air-gapped use, download PDF.js and change this
       path to:  'skin/external/pdfjs/pdf.worker.min.js'
    ── */
    if (typeof pdfjsLib !== 'undefined') {
        pdfjsLib.GlobalWorkerOptions.workerSrc =
            'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    }

    /* ── PDF field name → HTML input id mapping ────────────────────────────
       Key   = AcroForm field name inside the PDF (set in create_church_form.py)
       Value = the `id` attribute of the matching <input> in the Twig template
               (the template uses  id="field-{name}"  for every field)
    ── */
    const FIELD_MAP = {
        // Family
        fam_Name:           'fam_Name',
        fam_Address1:       'fam_Address1',
        fam_Address2:       'fam_Address2',
        fam_City:           'fam_City',
        fam_State:          'fam_State',
        fam_Zip:            'fam_Zip',
        fam_Country:        'fam_Country',
        fam_HomePhone:      'fam_HomePhone',
        fam_Email:          'fam_Email',
        fam_WeddingDate:    'fam_WeddingDate',
        // Individual
        per_Title:          'per_Title',
        per_FirstName:      'per_FirstName',
        per_MiddleName:     'per_MiddleName',
        per_LastName:       'per_LastName',
        per_Suffix:         'per_Suffix',
        per_BirthDate:      'per_BirthDate',
        per_CellPhone:      'per_CellPhone',
        per_HomePhone:      'per_HomePhone',
        per_WorkPhone:      'per_WorkPhone',
        per_Email:          'per_Email',
        per_WorkEmail:      'per_WorkEmail',
        per_MembershipDate: 'per_MembershipDate',
        // Individual address
        per_Address1:       'per_Address1',
        per_Address2:       'per_Address2',
        per_City:           'per_City',
        per_State:          'per_State',
        per_Zip:            'per_Zip',
        // Notes
        notes_nte:          'notes_nte',
    };

    /* ── Dropdown label → select value maps ────────────────────────────────
       These map the PDF's dropdown *display labels* to the integer IDs that
       ChurchCRM stores in the DB.  If your install uses different labels,
       adjust here — or leave it; the PHP side does a live DB lookup anyway.
    ── */
    const ROLE_MAP = {
        'Head of Household': '1',
        'Spouse':            '2',
        'Child':             '3',
        'Other / Extended':  '4',
        'Other':             '4',
    };
    const CLS_MAP = {
        'Member':            '1',
        'Regular Attender':  '2',
        'Guest':             '3',
        'Non-Attender':      '4',
        'Staff':             '5',
    };
    const MARITAL_MAP = {
        'Single':    '1',
        'Married':   '2',
        'Separated': '3',
        'Divorced':  '4',
        'Widowed':   '5',
    };

    // ── State ────────────────────────────────────────────────────────────────
    let currentFile  = null;

    // ── Element refs ─────────────────────────────────────────────────────────
    const $drop         = $('#drop-zone');
    const $fileInput    = $('#pdf-file-input');
    const $fileInfo     = $('#file-info');
    const $fileName     = $('#file-name-display');
    const $clearFile    = $('#clear-file');
    const $parseBtn     = $('#parse-btn');
    const $parseSpin    = $('#parse-spin');
    const $parseIcon    = $('#parse-icon');
    const $parseStatus  = $('#parse-status');
    const $placeholder  = $('#review-placeholder');
    const $form         = $('#import-form');
    const $countBadge   = $('#field-count-badge');
    const $resultBox    = $('#result-box');
    const $resultAlert  = $('#result-alert');
    const $resultMsg    = $('#result-message');
    const $resultLinks  = $('#result-links');
    const $linkFamily   = $('#link-family');
    const $linkPerson   = $('#link-person');
    const $submitBtn    = $('#import-submit-btn');
    const $previewBtn   = $('#preview-sql-btn');
    const $sqlContent   = $('#sql-preview-content');
    const $copySql      = $('#copy-sql-btn');
    const $dryRun       = $('#dry-run-check');

    // ── Drag & Drop ──────────────────────────────────────────────────────────
    $drop.on('dragover dragenter', function (e) {
        e.preventDefault();
        $(this).addClass('drop-zone-active');
    });
    $drop.on('dragleave dragend', function () {
        $(this).removeClass('drop-zone-active');
    });
    $drop.on('drop', function (e) {
        e.preventDefault();
        $(this).removeClass('drop-zone-active');
        const file = e.originalEvent.dataTransfer.files[0];
        if (file) loadFile(file);
    });
    $drop.on('click', () => $fileInput.trigger('click'));
    $fileInput.on('change', function () {
        if (this.files[0]) loadFile(this.files[0]);
    });
    $clearFile.on('click', resetAll);

    // ── Load file ────────────────────────────────────────────────────────────
    function loadFile(file) {
        if (!file.name.toLowerCase().endsWith('.pdf')) {
            setStatus('danger', '<i class="fa fa-warning"></i> Please select a PDF file.');
            return;
        }
        currentFile = file;
        $fileName.text(file.name + '  (' + fmtBytes(file.size) + ')');
        $fileInfo.removeClass('hidden');
        $parseBtn.prop('disabled', false);
        setStatus('info', '<i class="fa fa-info-circle"></i> Ready — click "Extract Fields from PDF".');
    }

    function resetAll() {
        currentFile = null;
        $fileInput.val('');
        $fileInfo.addClass('hidden');
        $fileName.text('');
        $parseBtn.prop('disabled', true);
        $parseStatus.addClass('hidden');
        $placeholder.removeClass('hidden');
        $form.addClass('hidden');
        $countBadge.addClass('hidden');
        $resultBox.addClass('hidden');
        $form[0].reset();
    }

    // ── Parse PDF ────────────────────────────────────────────────────────────
    $parseBtn.on('click', function () {
        if (!currentFile) return;
        if (typeof pdfjsLib === 'undefined') {
            setStatus('danger',
                '<i class="fa fa-warning"></i> PDF.js failed to load. ' +
                'For offline use, install a local copy (see INSTALL.md).');
            return;
        }
        parsePDF(currentFile);
    });

    async function parsePDF(file) {
        setParsing(true);
        setStatus('info', '<i class="fa fa-cog fa-spin"></i> Reading PDF fields…');

        try {
            const buf  = await readAsArrayBuffer(file);
            const pdf  = await pdfjsLib.getDocument({ data: buf }).promise;
            const raw  = await extractFields(pdf);
            const n    = populateForm(raw);

            $countBadge.text(n + ' fields extracted').removeClass('hidden');
            $placeholder.addClass('hidden');
            $form.removeClass('hidden');
            $resultBox.addClass('hidden');
            setStatus('success',
                '<i class="fa fa-check"></i> Extracted <strong>' + n +
                '</strong> non-empty field(s) from <em>' + file.name + '</em>');

        } catch (err) {
            console.error('PDF parse error:', err);
            setStatus('danger',
                '<i class="fa fa-times"></i> Could not read PDF: ' + err.message +
                '<br><small>Ensure this is a fillable PDF form, not a scanned image.</small>');
        } finally {
            setParsing(false);
        }
    }

    // ── PDF.js AcroForm extraction ────────────────────────────────────────────
    async function extractFields(pdf) {
        const out = {};
        for (let p = 1; p <= pdf.numPages; p++) {
            const page   = await pdf.getPage(p);
            const annots = await page.getAnnotations();
            for (const a of annots) {
                if (!a.fieldName) continue;
                let val = '';
                if (a.fieldType === 'Tx') {
                    val = a.fieldValue || '';
                } else if (a.fieldType === 'Btn') {
                    // Radio buttons — use exportValue when selected
                    if (a.exportValueString && a.buttonValue !== 'Off') {
                        val = a.exportValueString;
                    } else if (a.fieldValue && a.fieldValue !== 'Off') {
                        val = a.fieldValue;
                    }
                } else if (a.fieldType === 'Ch') {
                    val = Array.isArray(a.fieldValue)
                        ? (a.fieldValue[0] || '')
                        : (a.fieldValue || '');
                }
                const name = a.fieldName;
                if (String(val).trim() !== '' || !(name in out)) {
                    out[name] = String(val).trim();
                }
            }
        }
        return out;
    }

    // ── Populate review form ──────────────────────────────────────────────────
    function populateForm(fields) {
        let count = 0;
        // Clear previous success highlights
        $form.find('.form-group').removeClass('has-success');

        for (const [pdfKey, htmlKey] of Object.entries(FIELD_MAP)) {
            const raw = fields[pdfKey] || '';
            if (!raw) continue;

            const $el = $('#field-' + htmlKey);
            if (!$el.length) continue;

            const tag = $el.prop('tagName').toLowerCase();

            if (tag === 'select') {
                let mapped = raw;
                if (htmlKey === 'per_Gender') {
                    mapped = raw === '1' ? '1' : raw === '2' ? '2' : '';
                } else if (htmlKey === 'per_fmr_ID') {
                    mapped = ROLE_MAP[raw] || raw;
                } else if (htmlKey === 'per_cls_ID') {
                    mapped = CLS_MAP[raw] || raw;
                } else if (htmlKey === 'per_mrs_ID') {
                    mapped = MARITAL_MAP[raw] || raw;
                }
                $el.val(mapped);
                if ($el.val() === null) $el.val('');
            } else if (tag === 'textarea') {
                $el.val(raw);
            } else {
                $el.val(raw);
            }

            $el.closest('.form-group').addClass('has-success');
            count++;
        }
        return count;
    }

    // ── Preview SQL ───────────────────────────────────────────────────────────
    $previewBtn.on('click', function () {
        const data = collectFormData();
        data.dry_run = true;
        post(data, function (resp) {
            if (resp.sql) {
                $sqlContent.text(resp.sql);
                $('#sql-modal').modal('show');
            } else {
                alert('Could not generate SQL:\n' + (resp.message || 'Unknown error'));
            }
        });
    });

    $copySql.on('click', function () {
        navigator.clipboard.writeText($sqlContent.text()).then(() => {
            $copySql.html('<i class="fa fa-check"></i> Copied!');
            setTimeout(() => $copySql.html('<i class="fa fa-copy"></i> Copy SQL'), 2000);
        });
    });

    // ── Form submit ───────────────────────────────────────────────────────────
    $form.on('submit', function (e) {
        e.preventDefault();
        if (!validateForm()) return;

        const data    = collectFormData();
        data.dry_run  = $dryRun.is(':checked');

        $submitBtn.prop('disabled', true)
                  .html('<i class="fa fa-spinner fa-spin"></i> Importing…');

        post(data, function (resp) {
            showResult(resp, data.dry_run);
            $submitBtn.prop('disabled', false)
                      .html('<i class="fa fa-database"></i> Import to ChurchCRM');
        }, function (xhr) {
            showResult({ success: false,
                message: 'HTTP ' + xhr.status + ' — check server error log.' }, false);
            $submitBtn.prop('disabled', false)
                      .html('<i class="fa fa-database"></i> Import to ChurchCRM');
        });
    });

    function showResult(resp, isDry) {
        $resultBox.removeClass('hidden');
        $resultAlert.removeClass('alert-success alert-danger alert-info alert-warning');
        $resultLinks.addClass('hidden');

        if (resp.success) {
            $resultAlert.addClass(isDry ? 'alert-info' : 'alert-success');
            $resultMsg.html(
                '<i class="fa fa-' + (isDry ? 'info-circle' : 'check-circle') + '"></i> ' +
                '<strong>' + (isDry ? 'Dry run complete.' : 'Import successful!') + '</strong> ' +
                escHtml(resp.message || '')
            );
            if (!isDry && resp.famId && resp.perId) {
                $resultLinks.removeClass('hidden');
                $linkFamily.attr('href', 'v2/family/' + resp.famId + '/view');
                $linkPerson.attr('href', 'PersonView.php?PersonID=' + resp.perId);
            }
            if (isDry && resp.sql) {
                $sqlContent.text(resp.sql);
                $('#sql-modal').modal('show');
            }
        } else {
            $resultAlert.addClass('alert-danger');
            $resultMsg.html(
                '<i class="fa fa-times-circle"></i> <strong>Import failed.</strong> ' +
                escHtml(resp.message || 'Unknown error')
            );
        }
        $resultBox[0].scrollIntoView({ behavior: 'smooth' });
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    function collectFormData() {
        const out = {};
        $form.find('[name]').each(function () {
            const $el = $(this);
            const nm  = $el.attr('name');
            if ($el.prop('tagName').toLowerCase() === 'textarea') {
                out[nm] = $el.val();
            } else if ($el.attr('type') === 'checkbox') {
                out[nm] = $el.is(':checked') ? '1' : '0';
            } else {
                out[nm] = $el.val() || '';
            }
        });
        return out;
    }

    function validateForm() {
        let ok = true;
        $form.find('[required]').each(function () {
            const $g = $(this).closest('.form-group');
            if (!$(this).val().trim()) {
                $g.addClass('has-error').removeClass('has-success');
                ok = false;
            } else {
                $g.removeClass('has-error');
            }
        });
        if (!ok) setStatus('danger',
            '<i class="fa fa-warning"></i> Fill in all required fields (marked *).');
        return ok;
    }

    function post(data, onSuccess, onError) {
        $.ajax({
            url:         'v2/pdf-import/submit',
            method:      'POST',
            contentType: 'application/json',
            data:        JSON.stringify(data),
            success:     onSuccess,
            error:       onError || function (xhr) {
                alert('Request failed: HTTP ' + xhr.status);
            },
        });
    }

    function setParsing(on) {
        $parseBtn.prop('disabled', on);
        $parseSpin.toggleClass('hidden', !on);
        $parseIcon.toggleClass('hidden', on);
    }

    function setStatus(type, html) {
        const cls = { success: 'alert-success', danger: 'alert-danger', info: 'alert-info' };
        $parseStatus
            .removeClass('hidden alert-success alert-danger alert-info')
            .addClass('alert ' + (cls[type] || 'alert-info'))
            .html(html);
    }

    function readAsArrayBuffer(file) {
        return new Promise((res, rej) => {
            const r = new FileReader();
            r.onload  = e => res(e.target.result);
            r.onerror = () => rej(new Error('Could not read file'));
            r.readAsArrayBuffer(file);
        });
    }

    function fmtBytes(b) {
        return b < 1048576
            ? (b / 1024).toFixed(1) + ' KB'
            : (b / 1048576).toFixed(1) + ' MB';
    }

    function escHtml(s) {
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

})(jQuery);
