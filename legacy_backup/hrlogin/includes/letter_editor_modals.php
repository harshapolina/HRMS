<!-- Appointment Letter Modal -->
<div class="modal fade" id="appointmentLetterModal" aria-hidden="true" data-bs-focus="false">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background: linear-gradient(135deg, #1e6063 0%, #2a8c90 100%); color: white; border-radius: 20px 20px 0 0;">
        <h5 class="modal-title" id="letterModalTitle"><i class="bi bi-envelope-paper"></i> Document Editor</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body letter-editor-body" style="padding: 0;">
        <input type="hidden" id="editing_document_type">
        <div class="letter-mobile-format-bar" id="letterMobileFormatBar" aria-label="Editing options">
          <div class="dropdown">
            <button type="button" class="btn btn-sm letter-format-toggle dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false">
              <i class="bi bi-sliders"></i> Editing options
            </button>
            <ul class="dropdown-menu letter-format-menu shadow">
              <li><h6 class="dropdown-header">Style</h6></li>
              <li><button type="button" class="dropdown-item" data-sn-cmd="formatBlock" data-sn-arg="p">Normal text</button></li>
              <li><button type="button" class="dropdown-item" data-sn-cmd="formatBlock" data-sn-arg="h3">Heading</button></li>
              <li><hr class="dropdown-divider"></li>
              <li><h6 class="dropdown-header">Text</h6></li>
              <li><button type="button" class="dropdown-item" data-sn-cmd="bold"><i class="bi bi-type-bold me-2"></i>Bold</button></li>
              <li><button type="button" class="dropdown-item" data-sn-cmd="underline"><i class="bi bi-type-underline me-2"></i>Underline</button></li>
              <li><button type="button" class="dropdown-item" data-sn-cmd="removeFormat"><i class="bi bi-eraser me-2"></i>Clear formatting</button></li>
              <li><hr class="dropdown-divider"></li>
              <li><h6 class="dropdown-header">Paragraph</h6></li>
              <li><button type="button" class="dropdown-item" data-sn-cmd="insertUnorderedList"><i class="bi bi-list-ul me-2"></i>Bullet list</button></li>
              <li><button type="button" class="dropdown-item" data-sn-cmd="insertOrderedList"><i class="bi bi-list-ol me-2"></i>Numbered list</button></li>
              <li><hr class="dropdown-divider"></li>
              <li><h6 class="dropdown-header">Insert</h6></li>
              <li><button type="button" class="dropdown-item" data-sn-cmd="insertTable"><i class="bi bi-table me-2"></i>Table</button></li>
              <li><button type="button" class="dropdown-item" data-sn-cmd="createLink"><i class="bi bi-link-45deg me-2"></i>Link</button></li>
              <li><button type="button" class="dropdown-item" data-sn-cmd="insertHorizontalRule"><i class="bi bi-dash-lg me-2"></i>Horizontal line</button></li>
              <li><hr class="dropdown-divider"></li>
              <li><h6 class="dropdown-header">View</h6></li>
              <li><button type="button" class="dropdown-item" data-sn-cmd="fullscreen"><i class="bi bi-arrows-fullscreen me-2"></i>Full screen</button></li>
              <li><button type="button" class="dropdown-item" data-sn-cmd="codeview"><i class="bi bi-code-slash me-2"></i>HTML source</button></li>
            </ul>
          </div>
        </div>
        <textarea id="appointmentLetterEditor" style="width: 100%; height: 600px;"></textarea>
      </div>
      <div class="modal-footer letter-editor-footer">
        <button type="button" class="btn btn-outline-info letter-footer-btn letter-footer-signature" onmousedown="saveLetterEditorRange(event)" onclick="openSignatureModal()"><i class="bi bi-pen"></i> Add Signature</button>
        <button type="button" class="btn btn-outline-primary letter-footer-btn letter-footer-preview" onclick="previewLetterFromEditor()" title="Preview how the letter will look when printed or mailed"><i class="bi bi-eye"></i> Preview</button>
        <button type="button" class="btn btn-secondary letter-footer-btn letter-footer-close" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-warning letter-footer-btn letter-footer-reset" id="resetLetterBtn" style="color: #000; border: none;"><i class="bi bi-arrow-counterclockwise"></i> Reset to Template</button>
        <button type="button" class="btn btn-primary letter-footer-btn letter-footer-save" id="saveLetterBtn" style="background: #2a8c90; border: none;"><i class="bi bi-save"></i> Save Letter</button>
      </div>
    </div>
  </div>
</div>

<!-- Letter final output preview -->
<div class="modal fade" id="letterPreviewModal" tabindex="-1" aria-hidden="true" data-bs-focus="false">
  <div class="modal-dialog modal-dialog-centered letter-preview-dialog">
    <div class="modal-content" style="border-radius: 16px; overflow: hidden;">
      <div class="modal-header" style="background: linear-gradient(135deg, #1e6063 0%, #2a8c90 100%); color: white; position: relative; z-index: 10;">
        <h5 class="modal-title" id="letterPreviewTitle"><i class="bi bi-eye me-2"></i> Letter Preview</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="bootstrap.Modal.getInstance(document.getElementById('letterPreviewModal'))?.hide();" aria-label="Close" style="position: relative; z-index: 20; pointer-events: auto !important;"></button>
      </div>
      <div class="modal-body p-0 letter-preview-body position-relative">
        <div id="letterPreviewLoading" class="letter-preview-loading">
          <div class="text-center text-muted">
            <div class="spinner-border text-info mb-2" role="status"></div>
            <div class="small fw-semibold">Building preview...</div>
          </div>
        </div>
        <iframe id="letterPreviewFrame" title="Letter preview" class="letter-preview-frame"></iframe>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary ms-auto" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-outline-primary" onclick="downloadLetterPreview()" style="border-color: #2a8c90; color: #2a8c90;"><i class="bi bi-download"></i> Download PDF</button>
        <button type="button" class="btn btn-primary" onclick="printLetterPreview()" style="background: #2a8c90; border: none;"><i class="bi bi-printer"></i> Print</button>
      </div>
    </div>
  </div>
</div>

<!-- Signature Creator Modal -->
<div class="modal fade" id="signatureModal" data-bs-focus="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0" style="border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4); background: #ffffff;">
      <div class="modal-header px-4 pt-4 pb-2 border-0">
        <h5 class="modal-title fw-bold" style="color: #0f172a; font-size: 1.25rem;"><i class="bi bi-pen-fill me-2 text-primary"></i> Create Your Signature</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body px-4 py-3">
        <!-- Placement/Slot Selector -->
        <div class="mb-4">
          <label class="form-label small fw-bold text-muted text-uppercase tracking-wider">Placement / Slot</label>
          <div class="input-group">
            <span class="input-group-text bg-light border-end-0" style="border-radius: 12px 0 0 12px;"><i class="bi bi-person-badge"></i></span>
            <select class="form-select bg-light border-start-0" id="signature_role_select" style="border-radius: 0 12px 12px 0; font-weight: 600; color: #334155; height: 46px;">
              <option value="cursor">Active Cursor Point</option>
              <option value="hr">HR Signature (Shivali V Rai)</option>
              <option value="manager">Manager/Supervisor Signature</option>
              <option value="candidate">Employee/Candidate Signature</option>
            </select>
          </div>
        </div>

        <!-- Mode Tabs -->
        <ul class="nav nav-pills nav-justified mb-4 p-1 bg-light" id="signatureTab" role="tablist" style="border-radius: 14px;">
          <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold py-2" id="tab-draw-btn" data-bs-toggle="pill" data-bs-target="#sign-draw" type="button" role="tab" style="border-radius: 10px;"><i class="bi bi-brush me-1"></i> Draw</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold py-2" id="tab-type-btn" data-bs-toggle="pill" data-bs-target="#sign-type" type="button" role="tab" style="border-radius: 10px;"><i class="bi bi-keyboard me-1"></i> Type</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold py-2" id="tab-upload-btn" data-bs-toggle="pill" data-bs-target="#sign-upload" type="button" role="tab" style="border-radius: 10px;"><i class="bi bi-cloud-upload me-1"></i> Upload</button>
          </li>
        </ul>

        <div class="tab-content" id="signatureTabContent">
          <!-- Draw Mode -->
          <div class="tab-pane fade show active" id="sign-draw" role="tabpanel">
            <div class="canvas-container mb-3 position-relative" style="background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 16px; height: 160px; overflow: hidden;">
              <canvas id="signature-pad" width="450" height="156" style="cursor: crosshair; display: block; width: 100%; height: 100%; touch-action: none;"></canvas>
              <div class="canvas-hint" style="position: absolute; bottom: 8px; left: 0; right: 0; text-align: center; font-size: 11px; color: #94a3b8; pointer-events: none;">Sign on the line above</div>
              <div style="position: absolute; bottom: 24px; left: 10%; right: 10%; border-top: 1px solid #cbd5e1; pointer-events: none; opacity: 0.7;"></div>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div class="d-flex align-items-center gap-2">
                <span class="small fw-bold text-muted">Ink Color:</span>
                <div class="d-flex gap-2">
                  <button type="button" class="btn-color-swatch active" data-color="#000000" style="background-color: #000000; width: 24px; height: 24px; border-radius: 50%; border: 2px solid transparent; cursor: pointer; padding: 0; transition: all 0.2s; box-shadow: 0 0 0 1px #cbd5e1;"></button>
                  <button type="button" class="btn-color-swatch" data-color="#0033aa" style="background-color: #0033aa; width: 24px; height: 24px; border-radius: 50%; border: 2px solid transparent; cursor: pointer; padding: 0; transition: all 0.2s; box-shadow: 0 0 0 1px #cbd5e1;"></button>
                </div>
              </div>
              <button type="button" class="btn btn-sm btn-outline-danger px-3" id="clear-draw-btn" style="border-radius: 8px;"><i class="bi bi-trash3 me-1"></i> Clear</button>
            </div>
          </div>

          <!-- Type Mode -->
          <div class="tab-pane fade" id="sign-type" role="tabpanel">
            <div class="mb-3">
              <label class="form-label small fw-bold text-muted">Enter Your Full Name</label>
              <input type="text" class="form-control" id="sign_name_input" placeholder="e.g. Shivali V Rai" style="border-radius: 12px; height: 44px; padding: 10px 14px;">
            </div>
            <div class="signature-preview-box mb-3 position-relative" style="background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 16px; height: 160px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
              <canvas id="type-signature-canvas" width="450" height="156" style="background: transparent; display: block; max-width: 100%; height: auto;"></canvas>
            </div>
            <div class="mb-1"><span class="small fw-bold text-muted">Select Signature Style:</span></div>
            <div class="d-flex gap-2 overflow-auto pb-2" id="type-font-selectors">
              <button type="button" class="btn btn-sm btn-outline-secondary active flex-grow-1 py-2 font-pill" data-font="Mrs Saint Delafield" style="font-family: 'Mrs Saint Delafield', cursive; font-size: 18px; border-radius: 10px;">Mrs Saint Delafield</button>
              <button type="button" class="btn btn-sm btn-outline-secondary flex-grow-1 py-2 font-pill" data-font="Dancing Script" style="font-family: 'Dancing Script', cursive; font-size: 16px; border-radius: 10px;">Dancing Script</button>
              <button type="button" class="btn btn-sm btn-outline-secondary flex-grow-1 py-2 font-pill" data-font="Pinyon Script" style="font-family: 'Pinyon Script', cursive; font-size: 18px; border-radius: 10px;">Pinyon Script</button>
            </div>
          </div>

          <!-- Upload Mode -->
          <div class="tab-pane fade" id="sign-upload" role="tabpanel">
            <div class="upload-area p-4 text-center" id="sign-upload-zone" style="border: 2px dashed #cbd5e1; border-radius: 16px; cursor: pointer; background: #f8fafc; transition: all 0.2s;">
              <i class="bi bi-cloud-arrow-up text-primary" style="font-size: 2.5rem;"></i>
              <p class="mb-1 mt-2 fw-semibold text-dark">Drag & drop your signature, or <span class="text-primary">browse</span></p>
              <p class="text-muted mb-0" style="font-size: 11px;">Supports PNG, JPG (transparent PNG recommended)</p>
              <input type="file" id="sign_file_input" hidden accept="image/*">
            </div>
            <div id="uploadPreviewContainer" class="mt-3 p-3 text-center" style="display: none; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0;">
              <img id="uploadPreviewImg" src="" style="max-height: 100px; max-width: 100%; border: 1px dashed #ccc; padding: 4px; border-radius: 6px;">
              <button type="button" class="btn btn-sm btn-link text-danger d-block mx-auto mt-2 text-decoration-none fw-semibold" id="remove-upload-btn"><i class="bi bi-trash3 me-1"></i> Remove Image</button>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer px-4 pb-4 pt-2 border-0" style="background: #ffffff; border-radius: 0 0 24px 24px;">
        <button type="button" class="btn btn-light fw-bold px-4 py-2 me-2" data-bs-dismiss="modal" style="border-radius: 12px; color: #64748b;">Cancel</button>
        <button type="button" class="btn btn-primary fw-bold px-4 py-2" id="useSignatureBtn" style="background: #4f46e5; border: none; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2), 0 2px 4px -1px rgba(79, 70, 229, 0.1);">
          <i class="bi bi-check2-circle me-1"></i> Use Signature
        </button>
      </div>
    </div>
  </div>
</div>

<style>
/* Color Swatches active state */
.btn-color-swatch.active {
  transform: scale(1.15);
  box-shadow: 0 0 0 2px #ffffff, 0 0 0 4px #4f46e5 !important;
}
/* Font pills customization */
#type-font-selectors .btn.active {
  background-color: #eff6ff !important;
  color: #1d4ed8 !important;
  border-color: #3b82f6 !important;
}
#type-font-selectors .btn {
  border-color: #cbd5e1;
  background-color: #ffffff;
  color: #475569;
  transition: all 0.2s ease;
}
/* Upload Drag Over Style */
.upload-area.drag-over {
  background-color: #eff6ff !important;
  border-color: #3b82f6 !important;
}
/* High fidelity Modal Tab active styling */
#signatureTab .nav-link {
  color: #64748b;
  border: none;
  background: transparent;
  transition: all 0.2s ease;
}
#signatureTab .nav-link.active {
  background: #ffffff !important;
  color: #0f172a !important;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03) !important;
}
body.dark-mode #signatureModal .modal-content,
body.dark-mode #signatureModal .modal-footer {
  background: #1e293b !important;
  color: #f8fafc !important;
}
body.dark-mode #signatureModal .modal-header h5 {
  color: #f8fafc !important;
}
body.dark-mode #signatureModal .bg-light,
body.dark-mode #signatureModal .upload-area,
body.dark-mode #signatureModal .canvas-container,
body.dark-mode #signatureModal .signature-preview-box,
body.dark-mode #signatureModal #uploadPreviewContainer {
  background-color: #0f172a !important;
  border-color: #334155 !important;
}
body.dark-mode #signatureModal .form-select,
body.dark-mode #signatureModal .form-control {
  background-color: #0f172a !important;
  border-color: #334155 !important;
  color: #f8fafc !important;
}
body.dark-mode #signatureModal .form-select option {
  background-color: #1e293b !important;
  color: #f8fafc !important;
}
body.dark-mode #type-font-selectors .btn {
  background-color: #1e293b;
  color: #cbd5e1;
  border-color: #334155;
}
body.dark-mode #type-font-selectors .btn.active {
  background-color: #1e3a8a !important;
  color: #60a5fa !important;
  border-color: #3b82f6 !important;
}
body.dark-mode #signatureTab {
  background-color: #0f172a !important;
}
body.dark-mode #signatureTab .nav-link.active {
  background: #1e293b !important;
  color: #f8fafc !important;
}

/* Stack letter editor below signature modal when both are open */
#appointmentLetterModal.modal {
  z-index: 100100 !important;
}
#signatureModal.modal {
  z-index: 100110 !important;
}
.note-editor .note-modal,
.note-modal {
  z-index: 100105 !important;
}

/* SweetAlert confirm dialogs must sit above letter/signature modals */
.swal2-container {
  z-index: 100200 !important;
}

/* Mobile: compact editing dropdown instead of multi-row Summernote toolbar */
.letter-mobile-format-bar {
  display: none;
}
@media (max-width: 768px) {
  .letter-mobile-format-bar {
    display: flex;
    align-items: center;
    padding: 6px 10px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    flex-shrink: 0;
    z-index: 5;
  }
  .letter-format-toggle {
    width: 100%;
    justify-content: space-between;
    font-weight: 600;
    border-radius: 10px !important;
    min-height: 36px;
    background: #fff !important;
    border: 1px solid #cbd5e1 !important;
    color: #334155 !important;
  }
  .letter-format-menu {
    max-height: min(60vh, 320px);
    overflow-y: auto;
    width: min(280px, calc(100vw - 32px));
  }
  .letter-format-menu .dropdown-item {
    min-height: 40px;
    display: flex;
    align-items: center;
  }
  #appointmentLetterModal .note-toolbar {
    display: none !important;
  }
  body.dark-mode .letter-mobile-format-bar {
    background: #242424;
    border-bottom-color: #3d3d3d;
  }
  body.dark-mode .letter-format-toggle {
    background: #1a1a1a !important;
    border-color: #525252 !important;
    color: #e8e8e8 !important;
  }
  body.dark-mode .letter-format-menu {
    background: #242424;
    border-color: #3d3d3d;
  }
  body.dark-mode .letter-format-menu .dropdown-item {
    color: #e8e8e8;
  }
  body.dark-mode .letter-format-menu .dropdown-item:hover {
    background: #333;
    color: #fff;
  }
  body.dark-mode .letter-format-menu .dropdown-header {
    color: #9a9a9a;
  }
}

/* Letter editor — near full-screen on mobile for maximum letter preview */
@media (max-width: 768px) {
  #appointmentLetterModal.modal {
    padding: 0 !important;
  }
  #appointmentLetterModal .modal-dialog {
    margin: 0 !important;
    max-width: 100vw !important;
    width: 100vw !important;
    height: 100dvh !important;
    height: 100vh !important;
    max-height: 100dvh !important;
    max-height: 100vh !important;
  }
  #appointmentLetterModal .modal-dialog.modal-dialog-centered {
    align-items: stretch !important;
    min-height: 100% !important;
  }
  #appointmentLetterModal .modal-content {
    height: 100% !important;
    max-height: 100% !important;
    display: flex !important;
    flex-direction: column !important;
    border-radius: 0 !important;
  }
  #appointmentLetterModal .modal-header {
    padding: 10px 14px !important;
    border-radius: 0 !important;
    flex-shrink: 0 !important;
  }
  #appointmentLetterModal .modal-header .modal-title {
    font-size: 1rem !important;
  }
  #appointmentLetterModal .letter-editor-body {
    flex: 1 1 auto !important;
    min-height: 0 !important;
    display: flex !important;
    flex-direction: column !important;
  }
  #appointmentLetterModal .modal-body {
    flex: 1 1 auto !important;
    min-height: 0 !important;
    overflow: hidden !important;
    display: flex !important;
    flex-direction: column !important;
  }
  #appointmentLetterModal #appointmentLetterEditor {
    flex: 1 1 auto !important;
    min-height: 0 !important;
    height: auto !important;
  }
  #appointmentLetterModal .note-editor.note-frame {
    height: 100% !important;
    display: flex !important;
    flex-direction: column !important;
  }
  #appointmentLetterModal .note-editing-area,
  #appointmentLetterModal .note-editable {
    flex: 1 1 auto !important;
    min-height: 0 !important;
  }
  #appointmentLetterModal .note-statusbar {
    display: none !important;
  }

  #appointmentLetterModal .modal-footer.letter-editor-footer {
    display: grid !important;
    grid-template-columns: 1fr 1fr;
    grid-template-rows: auto auto auto;
    gap: 6px !important;
    padding: 8px 10px calc(8px + env(safe-area-inset-bottom, 0px)) !important;
    flex-shrink: 0 !important;
    flex-wrap: nowrap !important;
    justify-content: stretch !important;
  }
  #appointmentLetterModal .letter-editor-footer .letter-footer-btn {
    width: 100% !important;
    min-height: 38px !important;
    margin: 0 !important;
    font-size: 0.75rem !important;
    padding: 6px 8px !important;
    white-space: nowrap;
  }
  body.letter-editor-open .usr-mobile-bottom-nav,
  body.letter-editor-open .offer-mobile-bottom-nav {
    display: none !important;
  }
  #appointmentLetterModal .letter-footer-signature { grid-column: 1; grid-row: 1; }
  #appointmentLetterModal .letter-footer-preview { grid-column: 2; grid-row: 1; }
  #appointmentLetterModal .letter-footer-close { grid-column: 1; grid-row: 2; }
  #appointmentLetterModal .letter-footer-reset { grid-column: 2; grid-row: 2; }
  #appointmentLetterModal .letter-footer-save {
    grid-column: 1 / -1;
    grid-row: 3;
  }

  /* Replace fat teal horizontal scrollbar inside the letter table */
  #appointmentLetterModal .note-editable,
  #appointmentLetterModal .note-editing-area {
    scrollbar-width: thin;
    scrollbar-color: #94a3b8 #e8ecef;
  }
  #appointmentLetterModal .note-editable::-webkit-scrollbar,
  #appointmentLetterModal .note-editing-area::-webkit-scrollbar {
    height: 5px !important;
    width: 5px !important;
  }
  #appointmentLetterModal .note-editable::-webkit-scrollbar-track,
  #appointmentLetterModal .note-editing-area::-webkit-scrollbar-track {
    background: #e8ecef !important;
    border-radius: 4px !important;
  }
  #appointmentLetterModal .note-editable::-webkit-scrollbar-thumb,
  #appointmentLetterModal .note-editing-area::-webkit-scrollbar-thumb {
    background: #94a3b8 !important;
    border-radius: 4px !important;
    min-height: 0 !important;
  }
  #appointmentLetterModal .note-editable::-webkit-scrollbar-thumb:hover,
  #appointmentLetterModal .note-editing-area::-webkit-scrollbar-thumb:hover {
    background: #64748b !important;
  }
  body.dark-mode #appointmentLetterModal .note-editable,
  body.dark-mode #appointmentLetterModal .note-editing-area {
    scrollbar-color: #525252 #2a2a2a;
  }
  body.dark-mode #appointmentLetterModal .note-editable::-webkit-scrollbar-track,
  body.dark-mode #appointmentLetterModal .note-editing-area::-webkit-scrollbar-track {
    background: #2a2a2a !important;
  }
  body.dark-mode #appointmentLetterModal .note-editable::-webkit-scrollbar-thumb,
  body.dark-mode #appointmentLetterModal .note-editing-area::-webkit-scrollbar-thumb {
    background: #525252 !important;
  }
}
</style>
