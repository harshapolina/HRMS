<?php
$pageTitle = "EOI";
include 'htmlopen.php';
include 'header.php';
?>

<div class="container">
    <!-- EOI Table Section -->
    <div class="container">
        <!-- Filter Buttons -->
        <!-- <div class="filter-row">
            <button class="filter-btn small-btn"><i class="fas fa-check-circle"></i> New <span class="count">15</span></button>
            <button class="filter-btn small-btn"><i class="fas fa-star"></i> Active <span class="count">36</span></button>
        </div> -->

        <!-- EOI Table -->
        <div class="dashboard-card-eoi">
          <div class="card-header-eoi">
          </div>
            <!-- Fixed Controls Container -->
            <div class="table-controls-fixed">
                <div id="eoiTableControlsContainer"></div>
            </div>
            
            <!-- Scrollable Table Container -->
            <div class="table-container">
                <table class="enhanced-table eoi-table" id="eoiTable">
                        <tr>
                            <th class="filter-header id-column-eoi" style="z-index: 9; ">
                                <button class="filter-header-btn">ID <i class="fas fa-caret-down"></i></button>
                                <div class="filter-dropdown">
                                  <button class="filter-close-btn">&times;</button>
                                    <div class="filter-search">
                                      <input type="text" placeholder="Search IDs..." class="filter-search-input" data-column="0">
                                    </div>
                                        <label><input type="checkbox" checked class="filter-option" data-column="0" value=""> All IDs</label>
                                        <label><input type="checkbox" class="filter-option" data-column="0" value="EOI001"> EOI001</label>
                                        <label><input type="checkbox" class="filter-option" data-column="0" value="EOI002"> EOI002</label>
                                        <label><input type="checkbox" class="filter-option" data-column="0" value="EOI003"> EOI003</label>
                                    </div>
                            </th>
                            <th class="filter-header ">
                                <button class="filter-header-btn">CUSTOMER NAME <i class="fas fa-caret-down"></i></button>
                                <div class="filter-dropdown">
                                  <button class="filter-close-btn">&times;</button>
                                    <div class="filter-search">
                                        <input type="text" placeholder="Search Customers..." class="filter-search-input" data-column="1">
                                    </div>
                                    <div class="filter-options">
                                        <label><input type="checkbox" checked class="filter-option" data-column="1" value=""> All Customers</label>
                                        
                                    </div>
                                </div>
                            </th>
                            
                            
                            <th class="filter-header   tab-hidde">
                                <button class="filter-header-btn">BUILDER NAME <i class="fas fa-caret-down"></i></button>
                                <div class="filter-dropdown">
                                    <div class="filter-search">
                                        <input type="text" placeholder="Search builders..." class="filter-search-input" data-column="2">
                                    </div>
                                    <div class="filter-options">
                                        <label><input type="checkbox" checked class="filter-option" data-column="2" value=""> All Builders</label>
                                        <label><input type="checkbox" class="filter-option" data-column="2" value="Prestige Group"> Prestige Group</label>
                                        <label><input type="checkbox" class="filter-option" data-column="2" value="Sobha Limited"> Sobha Limited</label>
                                        <label><input type="checkbox" class="filter-option" data-column="2" value="Brigade Group"> Brigade Group</label>
                                    </div>
                                </div>
                            </th>
                            <th class="filter-header   mobile-hidde">
                                <button class="filter-header-btn">PROJECT NAME <i class="fas fa-caret-down"></i></button>
                                <div class="filter-dropdown">
                                  <button class="filter-close-btn">&times;</button>
                                    <div class="filter-search">
                                        <input type="text" placeholder="Search projects..." class="filter-search-input" data-column="3">
                                    </div>
                                    <div class="filter-options">
                                        <label><input type="checkbox" checked class="filter-option" data-column="3" value=""> All Projects</label>
                                        <label><input type="checkbox" class="filter-option" data-column="3" value="Prestige Raintree Park"> Prestige Raintree Park</label>
                                        <label><input type="checkbox" class="filter-option" data-column="3" value="Sobha Dream Acres"> Sobha Dream Acres</label>
                                        <label><input type="checkbox" class="filter-option" data-column="3" value="Brigade Metropolis"> Brigade Metropolis</label>
                                    </div>
                                </div>
                            </th>
                            
                            <th class="filter-header   mobile-hidde">
                                <button class="filter-header-btn">CONTACT NUMBER <i class="fas fa-caret-down"></i></button>
                                <div class="filter-dropdown">
                                  <button class="filter-close-btn">&times;</button>
                                    <div class="filter-search">
                                        <input type="text" placeholder="Search Contacts..." class="filter-search-input" data-column="4">
                                    </div>
                                    <div class="filter-options">
                                        <label><input type="checkbox" checked class="filter-option" data-column="4" value=""> All Contacts</label>
                                        
                                    </div>
                                </div>
                            </th>
                            <th class="filter-header   mobile-hidde">
                                <button class="filter-header-btn">EMAIL <i class="fas fa-caret-down"></i></button>
                                <div class="filter-dropdown">
                                  <button class="filter-close-btn">&times;</button>
                                    <div class="filter-search">
                                        <input type="text" placeholder="Search Emails..." class="filter-search-input" data-column="5">
                                    </div>
                                    <div class="filter-options">
                                        <label><input type="checkbox" checked class="filter-option" data-column="5" value=""> All Emails</label>
                                        
                                    </div>
                                </div>
                            </th>
                            <th class="filter-header   tab-hidde">
                                <button class="filter-header-btn">PROJECT TYPE <i class="fas fa-caret-down"></i></button>
                                <div class="filter-dropdown">
                                  <button class="filter-close-btn">&times;</button>
                                    <div class="filter-search">
                                        <input type="text" placeholder="Search Projects..." class="filter-search-input" data-column="6">
                                    </div>
                                    <div class="filter-options">
                                        <label><input type="checkbox" checked class="filter-option" data-column="6" value=""> All Type</label>
                                        
                                    </div>
                                </div>
                            </th>
                            <th class="filter-header   mobile-hidde">
                                <button class="filter-header-btn">BOOKING DATE <i class="fas fa-caret-down"></i></button>
                                <div class="filter-dropdown">
                                  <button class="filter-close-btn">&times;</button>
                                    <div class="filter-search">
                                        <input type="text" placeholder="Search dates..." class="filter-search-input" data-column="7">
                                    </div>
                                    <div class="filter-options">
                                        <label><input type="checkbox" checked class="filter-option" data-column="7" value=""> All Dates</label>
                                        
                                    </div>
                                </div>
                            </th>
                            
                             <th class="filter-header ">
                                <button class="filter-header-btn">BOOKING MONTH <i class="fas fa-caret-down"></i></button>
                                <div class="filter-dropdown">
                                  <button class="filter-close-btn">&times;</button>
                                    <div class="filter-search">
                                        <input type="text" placeholder="Search Months..." class="filter-search-input" data-column="8">
                                    </div>
                                    <div class="filter-options">
                                        <label><input type="checkbox" checked class="filter-option" data-column="8" value=""> All Months</label>
                                        
                                    </div>
                                </div>
                            </th>
                            <th class="expand-btn-cell"></th>
                            <th class="filter-header   mobile-hidde">
                                <button class="filter-header-btn">ACTIONS </i></button>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Table rows will be populated by DataTables -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="loader-container">
  <div class="loader">
    <div class="loader-circle"></div>
    <div class="loader-circle"></div>
    <div class="loader-circle"></div>
    <div class="loader-logo">
      <!-- You can put your logo initials or icon here -->
      <img src="assets/dataimage/mecntec-icon.png" alt="logo">
    </div>
  </div>
</div>
    
    <!-- Floating Action Button -->
    <div class="floating-add-btn">
        <div class="selection-actions" style="display: none;">
            <button class="action-button assign-users-btn"><i class="fas fa-user-friends"></i> Assign</button>
            <button class="action-button delete-selected-btn"><i class="fas fa-trash"></i></button>
        </div>
        <button class="action-button"><i class="fas fa-plus"></i></button>
    </div>
    
    <!-- Mobile Bottom Navigation -->
    <div class="mobile-bottom-nav">
        <button class="mobile-nav-btn filter-btn-mobile filter-eoi" id="openFilterModal1">
            <i class="fas fa-filter bottom-icon"></i>
            <span>Filter</span>
        </button>
         

        <button class="mobile-nav-btn add-btn-mobile ">

            <i class="fas fa-user-plus bottom-icon"></i>
            <span class="add-lead-btn">Add EOI</span>
        </button>
        
        <button class="mobile-nav-btn columns-btn-mobile">
            <i class="fas fa-columns bottom-icon"></i>
            <span>Columns</span>
        </button>
    </div>
</div> 


<div class="modal-overlay" id="addNewEOIModal" style="display: none;">
  <div class="modal-container-eoi add-booking-modal">
    <div class="modal-header">
      <h3>Add New EOI</h3>
      <button type="button" class="modal-close-btn" id="closeEOIModal">
        &times;
      </button>
    </div>

    <div class="modal-body">
      <form id="add-eoi-form" name="myform1" novalidate method="POST">
        <input type="hidden" name="cstatus" value="Processing">

        <!-- Booking Info Section -->
        <div class="section">
          
          <div class="grid">
            <div class="field">
              <fieldset class="fieldset-label">
                <legend class="field-legend">Booking Date</legend>
                <input 
                  type="date"
                  id="bdateo"
                  name="bdate"
                  required
                  onclick="this.showPicker()"
                  min="<?php echo date('Y-m-d'); ?>"
                  value="<?php echo date('Y-m-d'); ?>"
                  oninput="updateBookingMonth()"
                >
              </fieldset>
            </div>
            <div class="field">
              <fieldset class="fieldset-label">
                <legend class="field-legend">Booking Month</legend>
                <input 
                  type="text"
                  id="bmontho"
                  name="bmonth"
                  readonly
                  required
                  value="<?php echo date('Y-m'); ?>"
                >
              </fieldset>
            </div>
          </div>
        </div>

        <!-- Project Info Section -->
        <div class="section">
          
          <div class="grid">
            <div class="field">
              <button class="add-btn" data-add="builder" type="button">+ Add</button>
              <fieldset class="fieldset-label">
                <legend class="field-legend">Builder Name</legend>
                <input list="developer-list" name="developer" id="addDeveloper" placeholder="Start typing..." required>
                <datalist id="developer-list">
                  <!-- Options -->
                </datalist>
              </fieldset>
            </div>
            <div class="field">
              <button class="add-btn" data-add="project" type="button">+ Add</button>
              <fieldset class="fieldset-label">
                <legend class="field-legend">Project Name</legend>
                <input list="project-list" name="bproject" id="addProject" placeholder="Start typing..." required>
                <datalist id="project-list">
                  <!-- Options -->
                </datalist>
              </fieldset>
            </div>
            <div class="field">
              <button class="add-btn" data-add="ptype" type="button">+ Add</button>
              <fieldset class="fieldset-label">
                <legend class="field-legend">Project Type</legend>
                <input type="text" name="tproject" id="addProjectType" placeholder="Type project type" required>
              </fieldset>
            </div>
             <div class="field">
              <button class="add-btn" data-add="customer" type="button">+ Add</button>
              <fieldset class="fieldset-label">
                <legend class="field-legend">Customer Name</legend>
                <input type="text" name="cname" id="addCustomerName" placeholder="Full name" required>
              </fieldset>
            </div>
            <div class="field">
              <button class="add-btn" data-add="contact" type="button">+ Add</button>
              <fieldset class="fieldset-label">
                <legend class="field-legend">Contact No.</legend>
                <input 
                  type="tel"
                  name="cnumber"
                  id="addContactNo"
                  placeholder="+91 XXXXX XXXXX"
                  required
                  maxlength="10"
                  pattern="\d{10}"
                  oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,10);"
                >
              </fieldset>
            </div>
            <div class="field">
              <button class="add-btn" data-add="email" type="button">+ Add</button>
              <fieldset class="fieldset-label">
                <legend class="field-legend">E-mail</legend>
                <input type="email" name="cemail" id="addEmail" placeholder="name@example.com" required>
              </fieldset>
            </div>
          </div>
        </div>

      
        <!-- Actions -->
        <div class="form-actions">
          <button type="button" class="cancel-btn btn" id="closeEOIModalBtn">
            Cancel
          </button>
          <button type="submit" class="submit-btn btn " id="add-eoi-btn">
            Add EOI
          </button>
        </div>
      </form>
    </div>
  </div>
</div>



<!-- Edit EOI Modal -->
<div class="modal-overlay" id="editEOIModal" style="display: none;">
  <div class="modal-container-eoi add-booking-modal">
    <div class="modal-header">
      <h3>Edit EOI</h3>
      <button type="button" class="modal-close-btn" id="closeEditEOIModal">
        &times;
      </button>
    </div>

    <div class="modal-body">
      <form id="edit-eoi-form" name="myform2" method="POST">
        <input type="hidden" name="id">
        <input type="hidden" name="cstatus" value="Processing">

        <!-- Booking Info Section -->
        <div class="section">
         
          <div class="grid">
            <div class="field">
              <fieldset class="fieldset-label">
                <legend class="field-legend">Booking Date</legend>
                <input type="date" name="bdate" id="bdate" required>
              </fieldset>
            </div>
            <div class="field">
              <fieldset class="fieldset-label">
                <legend class="field-legend">Booking Month</legend>
                <input type="text" name="bmonth" id="bmonth" required readonly>
              </fieldset>
            </div>
          </div>
        </div>

        <!-- Project Info Section -->
        <div class="section">
          
          <div class="grid">
            <div class="field">
              <button class="add-btn" data-add="builder" type="button">+ Add</button>
              <fieldset class="fieldset-label">
                <legend class="field-legend">Builder Name</legend>
                <input type="text" name="developer" id="developer" placeholder="Builder name" required>
              </fieldset>
            </div>
            <div class="field">
              <button class="add-btn" data-add="project" type="button">+ Add</button>
              <fieldset class="fieldset-label">
                <legend class="field-legend">Project Name</legend>
                <input type="text" name="bproject" id="bproject" placeholder="Project name" required>
              </fieldset>
            </div>
            <div class="field">
              <button class="add-btn" data-add="ptype" type="button">+ Add</button>
              <fieldset class="fieldset-label">
                <legend class="field-legend">Project Type</legend>
                <input type="text" name="tproject" id="tproject" placeholder="Type" required>
              </fieldset>
            </div>
            <div class="field">
              <button class="add-btn" data-add="customer" type="button">+ Add</button>
              <fieldset class="fieldset-label">
                <legend class="field-legend">Customer Name</legend>
                <input type="text" name="cname" id="cname" placeholder="Full name" required>
              </fieldset>
            </div>
            <div class="field">
              <button class="add-btn" data-add="contact" type="button">+ Add</button>
              <fieldset class="fieldset-label">
                <legend class="field-legend">Contact Number</legend>
                <input type="tel" name="cnumber" id="cnumber" placeholder="+91 XXXXX XXXXX" maxlength="10" pattern="\d{10}" required>
              </fieldset>
            </div>
            <div class="field">
              <button class="add-btn" data-add="email" type="button">+ Add</button>
              <fieldset class="fieldset-label">
                <legend class="field-legend">E-mail</legend>
                <input type="email" name="cemail" id="cemail" placeholder="name@example.com" required>
              </fieldset>
            </div>
          </div>
        </div>

        <!-- Status Controls -->
        <div class="section">
          <h3>Status</h3>
          <div class="grid">
            <div class="field checboxes">
              <label for="converted"><input type="checkbox" id="toggleFields1" name="converted"> Converted</label>
            </div>
            <div class="field checboxes">
              <label for="cancel"><input type="checkbox" id="canceleoi" name="canceleoi"> Cancel</label>
            </div>
          </div>
        </div>

        <!-- Additional Fields -->
        <div id="additional-fields1" style="display: none;">
          <div class="section">
            <h3>Additional Info</h3>
            <div class="grid">
              <div class="field">
                <fieldset class="fieldset-label">
                  <legend class="field-legend">Unit No.</legend>
                  <input type="text" name="unitno" id="unitno" required>
                </fieldset>
              </div>
              <div class="field">
                <fieldset class="fieldset-label">
                  <legend class="field-legend">City</legend>
                  <div class="custom-select">
                    <select id="cityNameEoi" name="city" required>
                      <option value="">Agent City</option>
                      <option value="Bangalore">Bangalore</option>
                      <option value="Hyderabad">Hyderabad</option>
                      <option value="Pune">Pune</option>
                      <option value="Chennai">Chennai</option>
                      <option value="Mumbai">Mumbai</option>
                      <option value="Delhi">Delhi</option>
                      <option value="Gujarat">Gujarat</option>
                    </select>
                  </div>
                </fieldset>
              </div>
              <div class="field">
                <fieldset class="fieldset-label">
                  <legend class="field-legend">Project Size</legend>
                  <div class="input-wrap">
                    <input type="number" name="psize" id="psize" class="width" required>
                    <span class="suffix">sq.ft</span>
                  </div>
                </fieldset>
              </div>
              <div class="field">
                <fieldset class="fieldset-label">
                  <legend class="field-legend">Agreement Value</legend>
                  <div class="input-wrap">
                    <input type="number" class="width-1" name="cagreement" id="cagreement-2" required>
                    <span class="suffix">₹</span>
                  </div>
                </fieldset>
              </div>
              <div class="field">
                <fieldset class="fieldset-label">
                  <legend class="field-legend">Confirm Agreement Value</legend>
                  <div class="input-wrap">
                    <input type="number" class="width-1" name="cagreement_confirm" id="cagreement-confirm-2" required>
                    <span class="suffix">₹</span>
                  </div>
                </fieldset>
              </div>
              <div class="field">
                <fieldset class="fieldset-label">
                  <legend class="field-legend">Commission %</legend>
                  <div class="input-wrap">
                    <input type="number" class="width-1" name="ccashback" id="ccashback-2" min="0" max="100" step="0.01" onkeyup="addCalculate(2)" required>
                    <span class="suffix">%</span>
                  </div>
                </fieldset>
              </div>
              <div class="field">
                <fieldset class="fieldset-label">
                  <legend class="field-legend">Revenue Amount</legend>
                  <div class="input-wrap">
                    <input type="number" class="width-1" name="crevenue" id="crevenue-2" readonly>
                    <span class="suffix">₹</span>
                  </div>
                </fieldset>
              </div>
              <div class="field">
                <fieldset class="fieldset-label">
                  <legend class="field-legend">Cashback %</legend>
                  <div class="input-wrap">
                    <input type="number" class="width-1" name="cccashback" id="cccashback-2" min="0" max="100" step="0.01" onkeyup="addCalculate(2)" required>
                    <span class="suffix">%</span>
                  </div>
                </fieldset>
              </div>
              <div class="field">
                <fieldset class="fieldset-label">
                  <legend class="field-legend">Cashback Revenue Amt</legend>
                  <div class="input-wrap">
                    <input type="number" class="width-1" name="ccrevenue" id="ccrevenue-2" readonly>
                    <span class="suffix">₹</span>
                  </div>
                </fieldset>
              </div>
              <div class="field">
                <fieldset class="fieldset-label">
                  <legend class="field-legend">Lead Source</legend>
                  <div class="custom-select">
                    <select id="leadSourceEoi" name="leadsource" required>
                      <option value="">Select Source</option>
                      <option value="Google">Google</option>
                      <option value="Facebook">Facebook</option>
                      <option value="Direct">Direct</option>
                      <option value="Referral">Referral</option>
                      <option value="Portal">Portal</option>
                      <option value="WhatsApp">WhatsApp</option>
                    </select>
                  </div>
                </fieldset>
              </div>
              <div class="field full-row eoi-row-break" aria-hidden="true"></div>
              <div class="field">
                <fieldset class="fieldset-label">
                  <legend class="field-legend">Remarks</legend>
                  <textarea id="remarksEoi" name="bremarks" placeholder="Additional notes..."></textarea>
                </fieldset>
              </div>
              <div class="field">
                <fieldset class="fieldset-label">
                  <legend class="field-legend">Attachments</legend>
                  <div class="uploader" id="eoiUploader" style="width: 100%; box-sizing: border-box; overflow: hidden;">
                    <input type="file" id="eoiFileInput" name="document" accept=".pdf,application/pdf">
                    <div id="eoiUploadPrompt" style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                      <div><strong>Drag &amp; drop files here</strong> or</div>
                      <div class="actions">
                        <button class="browse" id="eoiBrowseBtn" type="button">Browse files</button>
                      </div>
                      <div class="hint">PDF. Upto 1 File</div>
                    </div>
                    <div class="file-list" id="eoiFileList" aria-live="polite"></div>
                  </div>
                </fieldset>
              </div>
            </div>
          </div>
        </div>

        <!-- Submit -->
        <div class="form-actions">
          <button type="button" class="cancel-btn btn" id="closeEditEOIModalBtn">
            Cancel
          </button>
          <button type="submit" class="submit-btn btn" id="edit-eoi-btn">
            Update EOI
          </button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- multifilter starts-->
<div class="modal-overlay" id="filterModal1" style="display: none;">
  <div class="modal-container-eoi">
    <div class="modal-header">
      <h3>Filter EOI</h3>
      <button class="modal-close-btn" id="closeFilterModal1">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <div class="modal-body">
      <form id="filterForm1">
        
        <!-- Date Range -->
        <div class="section">
         
          <div class="grid">
            <div class="field">
              <fieldset class="fieldset-label">
                <legend class="field-legend">From Date</legend>
                <div class="date-overlay-wrapper" style="position: relative; display: flex; align-items: center; padding: 10px 12px; width: 100%; min-height: 42px; border: none; overflow: hidden; box-sizing: border-box; background-color: transparent;"><span id="displayIsolatedFilterStartDate1" style="flex: 1; color: #6b7280; font-family: inherit; font-size: 14px; pointer-events: none; z-index: 1; text-transform: lowercase !important; white-space: nowrap;">dd-mm-yyyy</span><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="color: #6b7280; pointer-events: none; z-index: 1;"><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM2 2a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1H2z"/><path d="M2.5 4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5H3a.5.5 0 0 1-.5-.5V4z"/></svg><input type="date" id="isolatedFilterStartDate1" name="fromDate" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; margin: 0; padding: 0; z-index: 2; border: none; appearance: none; -webkit-appearance: none;"></div>
              </fieldset>
            </div>
            <div class="field">
              <fieldset class="fieldset-label">
                <legend class="field-legend">To Date</legend>
                <div class="date-overlay-wrapper" style="position: relative; display: flex; align-items: center; padding: 10px 12px; width: 100%; min-height: 42px; border: none; overflow: hidden; box-sizing: border-box; background-color: transparent;"><span id="displayIsolatedFilterEndDate1" style="flex: 1; color: #6b7280; font-family: inherit; font-size: 14px; pointer-events: none; z-index: 1; text-transform: lowercase !important; white-space: nowrap;">dd-mm-yyyy</span><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="color: #6b7280; pointer-events: none; z-index: 1;"><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM2 2a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1H2z"/><path d="M2.5 4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5H3a.5.5 0 0 1-.5-.5V4z"/></svg><input type="date" id="isolatedFilterEndDate1" name="toDate" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; margin: 0; padding: 0; z-index: 2; border: none; appearance: none; -webkit-appearance: none;"></div>
              </fieldset>
            </div>
          </div>
        </div>

        <!-- Customer Info -->
        <div class="section">
          
          <div class="grid">
            <div class="field">
              <fieldset class="fieldset-label">
                <legend class="field-legend">Customer Name</legend>
                <input type="text" id="isolatedFilterCustumername1" placeholder="Customer Name">
              </fieldset>
            </div>
            <div class="field">
              <fieldset class="fieldset-label">
                <legend class="field-legend">Email</legend>
                <input type="text" id="isolatedFilterEmail1" placeholder="Email">
              </fieldset>
            </div>
          </div>
        </div>

        <!-- Contact & Builder -->
        <div class="section">
         
          <div class="grid">
            <div class="field">
              <fieldset class="fieldset-label">
                <legend class="field-legend">Contact No.</legend>
                <input type="text" id="isolatedFilterContactnumber1" placeholder="Contact No.">
              </fieldset>
            </div>
            <div class="field">
              <fieldset class="fieldset-label">
                <legend class="field-legend">Builder Name</legend>
                <input type="text" id="isolatedFilterBuilderName" placeholder="Builder Name">
              </fieldset>
            </div>
          </div>
        </div>

        <!-- Project Info -->
        <div class="section">
        
          <div class="grid">
            <div class="field">
              <fieldset class="fieldset-label">
                <legend class="field-legend">Project Type</legend>
                <input type="text" id="isolatedFilterProjectType" placeholder="Project Type">
              </fieldset>
            </div>
            <div class="field">
              <fieldset class="fieldset-label">
                <legend class="field-legend">Project Name</legend>
                <input type="text" id="isolatedFilterProjectName" placeholder="Project Name">
              </fieldset>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="form-actions">
          <button type="button" class="cancel-btn btn" id="isolatedClearFiltersBtn1">Reset All</button>
          <button type="submit" class="submit-btn btn" id="isolatedApplyFiltersBtn1">Apply Filters</button><script>setInterval(function(){ let ids=[["isolatedFilterStartDate1", "displayIsolatedFilterStartDate1", "dd-mm-yyyy"], ["isolatedFilterEndDate1", "displayIsolatedFilterEndDate1", "dd-mm-yyyy"]]; ids.forEach(p=>{let inp=document.getElementById(p[0]); let sp=document.getElementById(p[1]); if(inp && sp){ let v=inp.value; sp.innerText = v ? v.split("-").reverse().join("-") : p[2]; let isDark=document.body.getAttribute("data-theme")==="dark"; sp.style.color = v ? (isDark ? "#fff" : "#000") : "#6b7280"; }}); }, 100);</script>
        </div>

      </form>
    </div>
  </div>
</div>


  <!-- <script type="text/javascript" src="../assets/js/bootstrap_alpha2.js"></script> -->
  <script src="calc.js?v=20250930"></script>
<?php include 'footer.php'; ?>


<?php
$customJs = [
    "assets/js/eoi_script.js?v=20250930"
];
include "htmlclose.php";
?>






