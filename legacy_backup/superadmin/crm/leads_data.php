<?php include('./htmlopen.php'); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<!-- Select2 CSS -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
  @media (max-width: 450px) {
    .container, .container-fluid, .container-lg, .container-md, .container-sm, .container-xl, .container-xxl {
        padding-left: 0.2rem !important;
        padding-right: 0.2rem !important;
    }
}
  /* this is for dropdown CSS */
  .dropdown-container {
    position: relative;
    width: 100%
  }

  .search-box {
    margin-bottom: 10px;
    padding: 8px;
    border: 1px solid #ced4da;
    border-radius: 5px;
    width: 100%
  }

  #users {
    width: 100%;
    height: 150px;
    padding: 10px;
    border-radius: 5px;
    border: 1px solid #ced4da;
    background-color: #fff;
    font-size: 16px
  }

  #users option {
    padding: 8px;
    border-bottom: 1px solid #ddd
  }

  #users option:hover {
    background-color: #f0f0f0
  }

  .modal-body {
    padding: 20px;
    background-color: white;
    border-radius: 10px
  }

  .modal-footer {
    border-top: 1px solid #e9ecef;
    padding: 15px
  }

  /* this is for dropdown CSS End */
  /* this is for Filter CSS */
  .custom-modal-content {
    overflow: visible !important
  }

  .dropdown-container {
    position: relative
  }

  .dropdown-content {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    width: 100%;
    background-color: #fff;
    box-shadow: 0 4px 8px rgba(0, 0, 0, .1);
    z-index: 1000
  }

  .dropdown-menu {
    width: 100%;
    overflow: auto
  }

  .dropdown-content ul {
    list-style-type: none;
    padding: 0;
    margin: 0;
    max-height: 200px;
    overflow-y: auto
  }

  .dropdown-content li {
    padding: 8px;
    cursor: pointer
  }

  .dropdown-content li:hover {
    background-color: #f1f1f1
  }

  .dropdown-search {
    width: 100%;
    padding: 8px;
    box-sizing: border-box
  }

  .dropdown-container input[readonly] {
    cursor: pointer
  }

  .assignedusertd {
    width: 100px;
    overflow: auto
  }

  .assignedusertd::-webkit-scrollbar {
    height: 5px
  }

  .assignedusertd::-webkit-scrollbar-track {
    -webkit-box-shadow: inset 0 0 6px rgba(0, 0, 0, .3);
    border-radius: 10px
  }

  .assignedusertd::-webkit-scrollbar-thumb {
    border-radius: 10px;
    -webkit-box-shadow: inset 0 0 6px rgba(0, 0, 0, .5)
  }

  /* this is for Filter CSS End */
  /* styling for Assigned Users */
  .assigned-users-list {
    display: flex;
    flex-wrap: wrap;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    max-height: 150px;
    overflow-y: auto
  }

  .assigned-user {
    display: flex;
    align-items: center;
    margin: 5px;
    padding: 5px 10px;
    background-color: #f8f9fa;
    border-radius: 15px;
    font-size: 14px;
    gap: 5px;
  }

  .assigned-user span {
    margin-right: 10px
  }

  .remove-btn {
    background: #dc3545;
    border: none;
    color: #fff;
    font-size: 12px;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer
  }

  .remarks_project {
    border-radius: 3px;
    background-color: #cdffd9;
    font-size: 9px;
    font-weight: 700;
    color: #00008b
  }

  /* styling for Assigned Users End */
  /* styling for dashobard buttons */
  .side-menu li.sideactive8 {
    background: var(--shicol);
    position: relative
  }

  .side-menu li.sideactive8 a {
    color: white
  }

  .addNewUserModal {
    display: none;
  }

  /* styling for dashobard buttons End */
  /* style for filter dropdown  */
  .select2-container {
    width: 100% !important;
    z-index: 1050 !important;
  }

  .select2-selection__choice {
    display: flex;
    align-items: center;
    background-color: #007bff;
    color: white;
    padding: 5px 10px;
    border-radius: 5px;
    margin: 2px;
  }

  .select2-selection__choice__remove {
    margin-left: 10px;
    color: white;
    cursor: pointer;
    font-weight: bold;
  }

  .modal {
    overflow: hidden !important;
  }

  .modal-dialog {
    overflow-y: initial !important;
    pointer-events: auto !important;
  }

  .modal-content {
    pointer-events: auto !important;
    overflow: hidden !important;
  }

  .modal-body {
    position: relative !important;
  }

  .select2-dropdown {
    z-index: 1055 !important;
    max-width: 100% !important;
  }

  .select2-container--open {
    z-index: 1055 !important;
  }

  .select2-search {
    z-index: 1055 !important;
  }

  .select2-container--open .select2-dropdown {
    position: absolute !important;
  }

  .modal-header {
    position: relative;
    z-index: 1060 !important;
    pointer-events: auto !important;
  }

  .modal-header .btn-close {
    z-index: 1065 !important;
    position: relative;
    cursor: pointer !important;
    pointer-events: auto !important;
    user-select: none !important;
  }

  .modal-header * {
    pointer-events: auto !important;
  }

  .modal-footer {
    pointer-events: auto !important;
  }

  .modal-footer * {
    pointer-events: auto !important;
  }

  .remove-user {
    background: transparent;
    border: none;
  }

  /* Floating create cron button */
  .create-cron-fab {
    position: fixed;
    right: 24px;
    bottom: 24px;
    z-index: 1200;
    display: none;
    border: none;
    border-radius: 999px;
    padding: 10px 16px;
    font-weight: 600;
    background: #0f766e;
    color: #fff;
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.2);
  }

  .create-cron-fab:hover {
    background: #0d5f59;
  }

  .create-cron-fab-count {
    margin-left: 8px;
    padding: 2px 8px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.2);
    font-size: 12px;
  }

  @media (max-width: 768px) {
    .create-cron-fab {
      right: 14px;
      bottom: 14px;
      padding: 9px 12px;
      font-size: 12px;
    }
  }

  /* Form-item styling for fieldset-style labels */
  .form-item {
    margin-bottom: 0.3rem;
    position: relative !important;
  }

  .form-item input,
  .form-item select {
    display: block !important;
    background: transparent !important;
    transition: .3s !important;
    padding: 0 15px !important;
    border-radius: 5px !important;
    width: 100% !important;
    font-size: 14px !important;
    font-weight: 500 !important;
    color: #0000009e !important;
  }

  .form-item label {
    position: absolute !important;
    cursor: text !important;
    z-index: 2000 !important;
    left: 10px !important;
    font-weight: 600 !important;
    background: #fff !important;
    padding: 0 10px !important;
    transition: .3s !important;
    font-size: 11px !important;
    top: -8px !important;
    color: #000000 !important;
  }

  .form-item input:focus,
  .form-item select:focus {
    border-color: #1b6c9f !important;
    outline: none !important;
    box-shadow: none !important;
  }

  .form-item input::placeholder,
  .form-item select::placeholder {
    color: #999 !important;
    font-weight: normal !important;
  }

  #createCronModal .cron-modal-row {
    margin-left: -6px;
    margin-right: -6px;
  }

  #createCronModal .cron-modal-col {
    padding-left: 6px !important;
    padding-right: 6px !important;
  }

  #createCronModal .cron-input-field {
    height: 45px !important;
  }

  #createCronModal input.cron-input-field {
    border: 1px solid #aaa !important;
  }

  #createCronModal .form-item {
    margin-bottom: 0.5rem;
  }

  /* Fix for modal backdrop and z-index issues */
  #isolatedFilterModal {
    z-index: 1050 !important;
  }

  #isolatedFilterModal .modal-dialog {
    z-index: 1051 !important;
  }

  #isolatedFilterModal .modal-content {
    z-index: 1052 !important;
    background-color: #fff !important;
  }

  .modal-backdrop {
    z-index: 1040 !important;
  }

  .modal-backdrop.show {
    opacity: 0.5 !important;
  }

  /* Responsive buttons for mobile */
  @media (max-width: 768px) {

    #isolatedFilterModal .modal-footer .btn {
      padding: 0.5rem 1rem !important;
      font-size: 0.875rem !important;
      border-radius: 6px !important;
    }

    #isolatedFilterModal .modal-dialog {
      max-width: 95% !important;
      margin: 1rem !important;
    }

    #isolatedFilterModal .modal-header h5 {
      font-size: 1rem !important;
    }

    .modal-footer {
      padding: 6px !important;
    }
  }

  @media (max-width: 425px) {
    .modal-footer {
      padding: 6px !important;
      gap: 0.1rem !important;
    }

    #isolatedFilterModal .modal-footer .btn {
      padding: 0.3rem 0.5rem !important;
      font-size: 0.775rem !important;
      border-radius: 8px !important;
    }
  }

  /* Add Lead Form - spacing between fields */
  #addLeadForm .form-item {
    margin-bottom: 1rem !important;
  }

  /* Fix horizontal spacing in filter modal */
  #isolatedFilterModal .row [class*="col-"] {
    padding-right: 1rem !important;
    padding-left: 1rem !important;
  }

  #isolatedFilterModal .row {
    margin-right: -1rem !important;
    margin-left: -1rem !important;
  }

  /* style for filter dropdown End */
  /* this is history Side bar CSS START */
  .unique-sidebar-title {
    font-size: 11px;
    color: #333
  }

  .unique-status-sidebar {
    height: 100vh;
    position: fixed;
    top: 0;
    background-color: #f4f4f4;
    transition: right .3s;
    padding: 0 20px;
    display: none;
    opacity: 0
  }

  .unique-top-sect {
    height: 5vh;
    display: flex;
    align-items: center;
    justify-content: center;
    border-bottom: 1px solid #ccc
  }

  .unique-mid-sect {
    height: 85vh;
    overflow-y: auto
  }

  .unique-btm-sect {
    height: 10vh
  }

  .unique-status-sidebar.active {
    right: 0;
    display: block;
    opacity: 1
  }

  .unique-close-btn {
    border: none;
    font-size: 1.5em;
    cursor: pointer;
    position: absolute;
    top: 12px;
    color: #000;
    width: 30px;
    right: 6px;
    height: 30px;
    border-radius: 50%;
    z-index: 999;
    background: #eae9e9
  }

  .unique-close-btn:hover,
  .unique-uparrow {
    color: red !important
  }

  .unique-lead-history {
    list-style-type: none;
    padding: 0;
    position: relative
  }

  .unique-lead-history::before {
    content: '';
    position: absolute;
    left: 14px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #ccc
  }

  .unique-lead-history li {
    position: relative;
    padding: 15px 0 15px 20px;
    cursor: pointer;
    border-radius: 20px
  }

  .unique-lead-history .unique-step:hover {
    background-color: #dfdfdf;
    transition: .2s
  }

  .unique-dot {
    width: 10px;
    height: 10px;
    background-color: #555;
    border-radius: 50%;
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%)
  }

  .unique-content {
    margin-left: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between
  }

  .unique-date-time {
    display: block;
    font-size: 10px;
    color: #555
  }

  .unique-dropdown {
    display: none;
    font-size: .8em;
    color: #555;
    background: #f9f9f9;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, .1);
    transition: max-height .3s, opacity .3s;
    max-height: 0;
    overflow: hidden;
    opacity: 0;
    margin-left: 22px !important;
    margin-right: 20px !important
  }

  .unique-dropdown-insides span {
    display: block
  }

  .unique-dropdown.show {
    display: block;
    max-height: 200px;
    opacity: 1;
    margin-top: 10px;
    max-width: 245px;
    overflow-y: auto;
    text-align: justify;
    word-wrap: break-word
  }

  .unique-arrow {
    cursor: pointer;
    font-size: 12px
  }

  .unique-arrow.unique-uparrow,
  .unique-dropdown.show+.unique-arrow.unique-downarrow {
    display: none
  }

  .unique-dropdown.show+.unique-arrow.unique-uparrow {
    display: inline
  }

  .unique-active-timeline .unique-dot {
    background-color: green
  }

  .unique-status-info {
    font-size: 12px;
    font-weight: 600;
    padding: 2px 6px;
  }

  .unique-status-view a {
    font-size: 11px;
    font-weight: 600
  }

  .unique-bottom-boxes,
  .unique-bottom-static {
    width: 100%;
    display: flex;
    justify-content: space-between;
    padding: 10px 0
  }

  .unique-bottom-boxes {
    border-bottom: 1px solid #ccc
  }

  .unique-bottom-static {
    border-top: 1px solid #ccc
  }

  .unique-left-box,
  .unique-left-static,
  .unique-right-box,
  .unique-right-static {
    width: 50%;
    text-align: center;
    font-weight: 500;
    font-size: 14px
  }

  .unique-left-box h4,
  .unique-left-box h6,
  .unique-left-static h4,
  .unique-left-static h6,
  .unique-right-box h4,
  .unique-right-box h6,
  .unique-right-static h4,
  .unique-right-static h6 {
    margin: 0;
    padding: 0
  }

  .unique-left-box h4,
  .unique-left-static h4,
  .unique-right-box h4,
  .unique-right-static h4 {
    font-size: 14px;
    color: teal;
    font-weight: 300
  }

  .unique-left-box h6,
  .unique-left-static h6,
  .unique-right-box h6,
  .unique-right-static h6 {
    font-size: 12px;
    color: #000;
    margin-top: 5px;
    font-weight: 700
  }

  .unique-status-view a {
    text-decoration: none;
    color: red
  }

  .unique-downarrow {
    color: green !important
  }

  .unique-dropdown.show::-webkit-scrollbar-track,
  .unique-mid-sect::-webkit-scrollbar-track {
    background-color: #f5f5f5
  }

  .unique-dropdown.show::-webkit-scrollbar,
  .unique-mid-sect::-webkit-scrollbar {
    width: 2px;
    background-color: #f5f5f5
  }

  .unique-dropdown.show::-webkit-scrollbar-thumb,
  .unique-mid-sect::-webkit-scrollbar-thumb {
    background-color: #ccc
  }

  .unique-dropdown-insides {
    padding: 10px
  }

  .date-time {
    margin-top: 5px
  }

  /* this is history Side bar CSS End */
  .different-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
  }

  .history-button {
    background: linear-gradient(135deg, #4CAF50, #2E7D32);
    /* Green gradient */
    border: none;
    border-radius: 50%;
    /* Circle button */
    width: 20px;
    height: 20px;
    cursor: pointer;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
    display: flex;
    justify-content: center;
    align-items: center;
    color: white;
    font-size: 18px;
  }

  .history-button:hover {
    background: linear-gradient(135deg, #66BB6A, #388E3C);
    transform: scale(1.1);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
  }

  .history-button i {
    font-size: 20px;
  }

  /* this is for status model table CSS Start */
  .custom-status-table {
    width: 100%;
    border-collapse: collapse;
    font-family: Poppins, sans-serif;
    background-color: #fff;
    box-shadow: 0 4px 12px rgba(0, 0, 0, .1);
    border-radius: 12px;
    overflow: hidden
  }

  .custom-status-table td,
  .custom-status-table th {
    padding: 14px 20px;
    text-align: left;
    border-bottom: 1px solid #eee
  }

  .custom-status-table thead {
    background-color: #f5f7fa
  }

  .custom-status-table th {
    font-size: 16px;
    color: #333;
    font-weight: 600
  }

  .custom-status-table td {
    font-size: 15px;
    color: #555
  }

  .custom-status-table tbody tr:hover {
    background-color: #f0f4f8
  }

  .custom-status-table .status {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500
  }

  .custom-status-table .status-rnr {
    color: #f1c40f
  }

  .custom-status-table .status-pending {
    color: #f39c12
  }

  .custom-status-table .history-icon {
    display: flex;
    justify-content: center;
    align-items: center
  }

  .custom-status-table .history-icon img {
    width: 30px;
    height: 30px;
    border-radius: 50%
  }

  @media (max-width:768px) {

    .custom-status-table td,
    .custom-status-table th {
      padding: 10px 12px;
      font-size: 14px
    }
  }

  @media (max-width:480px) {
    .custom-status-table thead {
      display: none
    }

    .custom-status-table tr {
      display: block;
      margin-bottom: 15px;
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 10px
    }

    .custom-status-table td {
      display: flex;
      justify-content: space-between;
      padding: 8px 10px
    }

    .custom-status-table td::before {
      content: attr(data-label);
      font-weight: 700;
      color: #777
    }
  }

  /* this is for status model table CSS End */
  /* counter CSS */
  .user-info {
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: 'Segoe UI', sans-serif
  }

  .avatar-wrapper {
    position: relative;
    width: 32px;
    height: 32px
  }

  .avatar-wrapper img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover
  }

  .lead-counter {
    position: absolute;
    top: -4px;
    right: -4px;
    background-color: #ff4d4f;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    padding: 0 5px;
    border-radius: 50%;
    height: 16px;
    min-width: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    box-shadow: 0 0 2px rgba(0, 0, 0, .2)
  }

  .user-name {
    font-size: 14px;
    font-weight: 500;
    color: #333;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 140px
  }

  /* counter CSS End */
  span.select2-selection.select2-selection--multiple {
    overflow-y: auto !important;
    height: 44px !important;
    padding: 4px 8px !important;
  }

  .select2-container--default .select2-selection--multiple .select2-selection__choice {
    margin: 3px 3px 3px 0 !important;
  }

  /*Assign User model*/
  #assignModal .modal-dialog {
    max-width: 500px;
    margin: 1.75rem auto
  }

  #assignModal .modal-content {
    max-height: 85vh;
    display: flex;
    flex-direction: column
  }

  #assignModal .modal-body {
    overflow: auto;
    padding: 1rem;
    max-height: calc(85vh - 120px)
  }

  #assignModal #assigned-users-container {
    max-height: 44px;
    scrollbar-width: none;
    overflow-y: auto;
    border: 1px solid black;
    border-radius: 4px;
    padding: 8px;
    margin-bottom: 12px;
    background: #fff
  }

  #assignModal .assigned-badge {
    display: inline-block;
    margin: 4px 6px 4px 0;
    padding: 6px 10px;
    border-radius: 16px;
    background: #f1f3f5;
    font-size: .9rem
  }

  #assignModal #users {
    min-height: 120px;
    max-height: 220px;
    overflow-y: auto
  }

  #assignModal .modal-footer {
    margin: 0;
    padding: 12px 16px;
    border-top: 1px solid #e9ecef;
    position: sticky;
    bottom: 0;
    z-index: 10
  }

  @media (max-width:576px) {
    #assignModal .modal-dialog {
      max-width: 95%
    }

    #assignModal #assigned-users-container {
      max-height: 140px
    }

    #assignModal #users {
      min-height: 100px
    }
  }

  /*Assign users model close*/
  /* Ensure Select2 dropdown stays within modal boundaries */
  #assignModal {
    overflow: hidden !important;
  }

  #assignModal .modal-content {
    overflow: hidden !important;
  }

  #assignModal .modal-body {
    position: relative !important;
  }

  #assignModal .select2-container {
    z-index: 1060 !important;
  }

  #assignModal .select2-dropdown {
    max-height: 200px !important;
    overflow-y: auto !important;
  }

  /*Assign Modal fixes end*/
  /* Fix horizontal scrolling for table */
  .scrollable-table {
    width: 100% !important;
    max-width: 100% !important;
    overflow-x: auto !important;
    overflow-y: auto !important;
    -webkit-overflow-scrolling: touch;
    display: block !important;
  }

  .scrollable-table table {
    min-width: max-content !important;
    width: auto !important;
  }

  .dt-layout-row-mid {
    overflow: visible !important;
    width: 100% !important;
  }

  /* ========================================= */
  /* MODERN BUTTON STYLING - Userlogin 6 Style */
  /* ========================================= */

  /* Status/Filter Buttons - Top Header Row */
  button.activebutton.accessbtn,
  button.deactivebutton.accessbtn,
  button.salarybutton.accessbtn,
  button.filterbutton.accessbtn {
    background-color: #f8f9fa;
    border: 1px solid #e0e2e5;
    border-radius: 20px;
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 600;
    color: #3c4043;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    position: relative;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    white-space: nowrap;
  }

  /* Total (Green) */
  button.activebutton.accessbtn {
    background-color: rgba(16, 185, 129, 0.1) !important;
    color: #10b981 !important;
    border-color: rgba(16, 185, 129, 0.2) !important;
  }

  button.activebutton.accessbtn:hover {
    background: linear-gradient(135deg, #10b981 0%, #0d9b6c 100%) !important;
    border-color: #10b981 !important;
    color: #fff !important;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3) !important;
    transform: translateY(-1px);
  }

  button.activebutton.accessbtn i {
    color: #10b981;
  }

  button.activebutton.accessbtn:hover i {
    color: #fff !important;
  }

  /* My Leads (Pink/Coral) */
  button.deactivebutton.accessbtn {
    background-color: rgba(236, 72, 153, 0.1) !important;
    color: #ec4899 !important;
    border-color: rgba(236, 72, 153, 0.2) !important;
  }

  button.deactivebutton.accessbtn:hover {
    background: linear-gradient(135deg, #ec4899 0%, #db2777 100%) !important;
    border-color: #ec4899 !important;
    color: #fff !important;
    box-shadow: 0 4px 12px rgba(236, 72, 153, 0.3) !important;
    transform: translateY(-1px);
  }

  button.deactivebutton.accessbtn i {
    color: #ec4899;
  }

  button.deactivebutton.accessbtn:hover i {
    color: #fff !important;
  }

  /* EOI & Salary (Purple/Blue) */
  button.salarybutton.accessbtn {
    background-color: rgba(139, 92, 246, 0.1) !important;
    color: #8b5cf6 !important;
    border-color: rgba(139, 92, 246, 0.2) !important;
  }

  button.salarybutton.accessbtn:hover {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%) !important;
    border-color: #8b5cf6 !important;
    color: #fff !important;
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3) !important;
    transform: translateY(-1px);
  }

  button.salarybutton.accessbtn i {
    color: #8b5cf6;
  }

  button.salarybutton.accessbtn:hover i {
    color: #fff !important;
  }

  /* Filter & Deleted (Red/Orange) */
  button.filterbutton.accessbtn {
    background-color: rgba(239, 68, 68, 0.1) !important;
    color: #ef4444 !important;
    border-color: rgba(239, 68, 68, 0.2) !important;
  }

  button.filterbutton.accessbtn:hover {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
    border-color: #ef4444 !important;
    color: #fff !important;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3) !important;
    transform: translateY(-1px);
  }

  button.filterbutton.accessbtn i {
    color: #ef4444;
  }

  button.filterbutton.accessbtn:hover i {
    color: #fff !important;
  }

  /* Action Buttons - Second Row (more specific selectors) */
  button.acitvebutton.accessbtn,
  button.assignbutton.accessbtn,
  button.assignmodalbutton.accessbtn,
  button.deletebutton.accessbtn,
  button.uploadbutton.accessbtn,
  button.downloadbutton.accessbtn,
  button.excelbutton.accessbtn,
  a.excelbutton.accessbtn,
  a.salarybutton.accessbtn {
    border: none;
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 12px;
    background-color: #eaeaf8 !important;
    text-align: center;
    white-space: nowrap;
    font-weight: 500;
    transition: all 0.2s ease;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    display: inline-flex;
    align-items: center;
    gap: 4px;
  }

  a.excelbutton.accessbtn {
    margin-top: 15px;
  }

  .upload-card {
    padding: 1rem !important;
  }

  /* Position SweetAlert2 messages lower on the page */
  .swal2-container {
    padding-top: 180px !important;
  }

  .swal2-popup {
    margin-top: 0 !important;
  }

  /* Customize success message appearance */
  .swal2-popup.swal2-toast {
    padding: 10px 20px !important;
  }

  /* Custom toast styling for assignment success */
  .custom-toast-popup {
    border-radius: 12px !important;
    padding: 16px 20px !important;
    font-size: 15px !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
  }

  /* Position toast at bottom with margin */
  .swal2-container.swal2-bottom {
    padding-bottom: 2rem !important;
  }

  button.acitvebutton.accessbtn:hover,
  button.assignbutton.accessbtn:hover,
  button.assignmodalbutton.accessbtn:hover,
  button.deletebutton.accessbtn:hover,
  button.uploadbutton.accessbtn:hover,
  button.downloadbutton.accessbtn:hover,
  button.excelbutton.accessbtn:hover,
  a.excelbutton.accessbtn:hover,
  a.salarybutton.accessbtn:hover {
    background-color: #d8d9eb !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  }

  /* Specific button colors for actions */
  button.acitvebutton.accessbtn {
    background-color: rgba(34, 197, 94, 0.15) !important;
    color: #16a34a !important;
  }

  button.acitvebutton.accessbtn:hover {
    background-color: #16a34a !important;
    color: #fff !important;
  }

  button.acitvebutton.accessbtn i {
    color: #16a34a;
  }

  button.acitvebutton.accessbtn:hover i {
    color: #fff !important;
  }

  button.assignbutton.accessbtn {
    background-color: rgba(59, 130, 246, 0.15) !important;
    color: #2563eb !important;
  }

  button.assignbutton.accessbtn:hover {
    background-color: #2563eb !important;
    color: #fff !important;
  }

  button.assignbutton.accessbtn i {
    color: #2563eb;
  }

  button.assignbutton.accessbtn:hover i {
    color: #fff !important;
  }

  button.assignmodalbutton.accessbtn {
    background-color: rgba(139, 92, 246, 0.15) !important;
    color: #7c3aed !important;
  }

  button.assignmodalbutton.accessbtn:hover {
    background-color: #7c3aed !important;
    color: #fff !important;
  }

  button.assignmodalbutton.accessbtn i {
    color: #7c3aed;
  }

  button.assignmodalbutton.accessbtn:hover i {
    color: #fff !important;
  }

  button.deletebutton.accessbtn {
    background-color: rgba(239, 68, 68, 0.15) !important;
    color: #dc2626 !important;
  }

  button.deletebutton.accessbtn:hover {
    background-color: #dc2626 !important;
    color: #fff !important;
  }

  button.deletebutton.accessbtn i {
    color: #dc2626;
  }

  button.deletebutton.accessbtn:hover i {
    color: #fff !important;
  }

  button.uploadbutton.accessbtn {
    background-color: rgba(16, 185, 129, 0.15) !important;
    color: #059669 !important;
  }

  button.uploadbutton.accessbtn:hover {
    background-color: #059669 !important;
    color: #fff !important;
  }

  button.uploadbutton.accessbtn i {
    color: #059669;
  }

  button.uploadbutton.accessbtn:hover i {
    color: #fff !important;
  }

  button.downloadbutton.accessbtn {
    background-color: rgba(6, 182, 212, 0.15) !important;
    color: #0284c7 !important;
  }

  button.downloadbutton.accessbtn:hover {
    background-color: #0284c7 !important;
    color: #fff !important;
  }

  button.downloadbutton.accessbtn i {
    color: #0284c7;
  }

  button.downloadbutton.accessbtn:hover i {
    color: #fff !important;
  }

  /* Table container modern styling */
  .dt-layout-row-wrapper {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
    border-radius: 16px !important;
    overflow: hidden;
    background: #f8f9fc;
  }

  .scrollable-table {
    border-radius: 12px;
    box-shadow: none !important;
    background: transparent !important;
  }

  /* Card-based table styling */
  .scrollable-table table {
    border-collapse: separate !important;
    border-spacing: 0 12px !important;
    background: transparent !important;
  }

  .scrollable-table table thead {
    background-color: transparent !important;
  }

  .scrollable-table table thead tr {
    background: transparent !important;
  }

  .scrollable-table table thead th {
    background-color: #fff !important;
    padding: 12px 16px !important;
    font-weight: 600 !important;
    font-size: 12px !important;
    color: #64748b !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    border: none !important;
    border-bottom: 2px solid #e2e8f0 !important;
  }

  .scrollable-table table thead th:first-child {
    border-top-left-radius: 12px !important;
    border-bottom-left-radius: 0 !important;
  }

  .scrollable-table table thead th:last-child {
    border-top-right-radius: 12px !important;
    border-bottom-right-radius: 0 !important;
  }

  /* Table body rows as cards */
  .scrollable-table table tbody {
    background: transparent !important;
  }

  .scrollable-table table tbody tr {
    background: #fff !important;
    border-radius: 12px !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06) !important;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1) !important;
    border: 1px solid #e2e8f0 !important;
    overflow: hidden !important;
  }

  .scrollable-table table tbody tr:hover {
    background-color: #fff !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12) !important;
    transform: translateY(-2px) !important;
    border-color: #cbd5e1 !important;
  }

  .scrollable-table table tbody td {
    padding: 16px !important;
    border: none !important;
    font-size: 13px !important;
    color: #334155 !important;
    vertical-align: middle !important;
    background-color: #fff !important;
  }

  .scrollable-table table tbody td:first-child {
    border-top-left-radius: 12px !important;
    border-bottom-left-radius: 12px !important;
    padding-left: 20px !important;
    background-color: #fff !important;
  }

  .scrollable-table table tbody td:last-child {
    border-top-right-radius: 0 !important;
    border-bottom-right-radius: 0 !important;
  }

  /* Status column is the visual last column - apply border-radius here */
  .scrollable-table table tbody td:nth-child(11) {
    border-top-right-radius: 12px !important;
    border-bottom-right-radius: 12px !important;
    padding-right: 20px !important;
    background-color: #fff !important;
  }

  /* Remove default table borders */
  .scrollable-table table,
  .scrollable-table table tr,
  .scrollable-table table td,
  .scrollable-table table th {
    border-collapse: separate !important;
  }

  /* Avatar and user info styling */
  .scrollable-table table tbody td .user-info {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .scrollable-table table tbody td .avatar-wrapper {
    position: relative;
    flex-shrink: 0;
  }

  .scrollable-table table tbody td .avatar-wrapper img {
    width: 36px !important;
    height: 36px !important;
    border-radius: 50% !important;
    border: 2px solid #f1f5f9 !important;
  }

  .scrollable-table table tbody td .user-name {
    font-weight: 600 !important;
    color: #0f172a !important;
    font-size: 14px !important;
  }

  /* Email styling */
  .scrollable-table table tbody td:nth-child(3) {
    color: #64748b !important;
    font-size: 13px !important;
  }

  /* Project name styling - Status column gets border-radius */
  .scrollable-table table tbody td:nth-child(11) {
    font-weight: 600 !important;
    color: #475569 !important;
    border-top-right-radius: 12px !important;
    border-bottom-right-radius: 12px !important;
    padding-right: 20px !important;
    background-color: #fff !important;
    text-align: center !important;
  }

  /* Footer styling */
  .dt-layout-foot {
    background: transparent !important;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 5px;
  }

  /* Pagination styling */
  .pagination {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .pagination button {
    border-radius: 6px !important;
    border: 1px solid #dee2e6 !important;
    background: #fff !important;
    padding: 6px 14px !important;
    transition: all 0.2s ease !important;
    font-size: 14px !important;
    font-weight: 500 !important;
    color: #495057 !important;
    min-width: auto !important;
  }

  .pagination button:hover:not(:disabled) {
    background: #e9ecef !important;
    border-color: #adb5bd !important;
  }

  .pagination button.active {
    background: #007bff !important;
    color: #fff !important;
    border-color: #007bff !important;
  }

  .pagination button:disabled {
    opacity: 0.5 !important;
    cursor: not-allowed !important;
  }

  .modal-dialog-centered {
    justify-content: center;
    display: flex;
    align-items: center;
    min-height: calc(100% - var(--bs-modal-margin) * 2);
  }

  .pagination span {
    display: flex;
    align-items: center;
    gap: 5px;
  }

  #rowInfo {
    font-size: 14px;
    font-weight: 500;
  }

  /* Mobile responsive adjustments */
  @media (max-width: 768px) {

    button.activebutton.accessbtn,
    button.deactivebutton.accessbtn,
    button.salarybutton.accessbtn,
    button.filterbutton.accessbtn {
      padding: 6px 12px;
      font-size: 11px;
    }

    button.acitvebutton.accessbtn,
    button.assignbutton.accessbtn,
    button.assignmodalbutton.accessbtn,
    button.deletebutton.accessbtn,
    button.uploadbutton.accessbtn,
    button.downloadbutton.accessbtn {
      padding: 5px 10px;
      font-size: 11px;
    }

    .scrollable-table table {
      border-spacing: 0 8px !important;
    }

    .scrollable-table table tbody td {
      padding: 12px !important;
      font-size: 12px !important;
    }

    .scrollable-table table thead th {
      padding: 10px 12px !important;
      font-size: 11px !important;
    }

    /* On mobile, Status column (11) loses right border-radius */
    .scrollable-table table tbody td:nth-child(11) {
      border-top-right-radius: 0 !important;
      border-bottom-right-radius: 0 !important;
    }
    

    /* On mobile, expand button column (12) becomes the last visible and gets border-radius */
    .scrollable-table table tbody td:nth-child(12) {
      border-top-right-radius: 12px !important;
      border-bottom-right-radius: 12px !important;
      padding-right: 20px !important;
      background-color: #fff !important;
    }
    /* Assigned Users Badges Styling */
    .assigned-users-wrapper {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        align-items: center;
    }

    .assigned-user-badge {
        display: inline-block;
        padding: 3px 8px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 4px;
        font-size: 0.65rem;
        font-weight: 500;
        white-space: nowrap;
        transition: all 0.2s ease;
    }

    .assigned-users-more {
        display: inline-block;
        padding: 3px 8px;
        background: #e5e7eb;
        color: #374151;
        border-radius: 4px;
        font-size: 0.65rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .assigned-users-more:hover {
        background: #d1d5db;
        transform: translateY(-1px);
    }

    .assigned-users-more:active {
        transform: translateY(0);
    }

    /* Dark Mode Support for Assigned Users */
    body.dark-mode .assigned-user-badge,
    [data-theme="dark"] .assigned-user-badge {
        background: linear-gradient(135deg, #818cf8 0%, #a78bfa 100%);
    }

    body.dark-mode .assigned-users-more,
    [data-theme="dark"] .assigned-users-more {
        background: #374151;
        color: #d1d5db;
    }

    body.dark-mode .assigned-users-more:hover,
    [data-theme="dark"] .assigned-users-more:hover {
        background: #4b5563;
    }
    
  }
</style>
<link rel="stylesheet" href="./assets/css/leads_modern.css" />
<link rel="stylesheet" href="../assets/css/unified_table_styles.css" />
<?php include('../header.php'); ?>
<div class="content">
  <!-- Isolated Filter Modal -->
  <div class="modal fade" tabindex="-1" id="isolatedFilterModal">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 600px;">
      <div class="modal-content">
        <div class="modal-header" style="border-bottom: 1px solid #e5e7eb; padding: 1.5rem;">
          <h5 class="modal-title"
            style="color: #000; font-weight: 700; font-size: 1.125rem; margin: 0; text-transform: uppercase; letter-spacing: 0.05em;">
            FILTER DATA</h5>
          <button type="button" class="btn-close" aria-label="Close" id="isolatedCloseFilter"
            style="font-size: 1.0rem;"></button>
        </div>
        <div class="modal-body" style="padding: 1.5rem; max-height: 60vh; overflow-y: auto;">
          <form id="filterForm">
            <div class="container">
              <div class="row gx-5">
                <!-- Date Range -->
                <div class="col-lg-6 mb-3">
                  <div class="form-item">
                    <label for="isolatedFilterStartDate">From Date</label>
                    <input type="date" class="form-control form-control-lg" id="isolatedFilterStartDate"
                      style="height: 44px;">
                  </div>
                </div>
                <div class="col-lg-6 mb-3">
                  <div class="form-item">
                    <label for="isolatedFilterEndDate">To Date</label>
                    <input type="date" class="form-control form-control-lg" id="isolatedFilterEndDate"
                      style="height: 44px;">
                  </div>
                </div>

                <!-- Customer Name -->
                <div class="col-lg-6 mb-3">
                  <div class="form-item">
                    <label for="isolatedFilterCustumername">Customer Name</label>
                    <select id="isolatedFilterCustumername" class="form-select filter-select" multiple></select>
                  </div>
                </div>

                <!-- Contact Number -->
                <div class="col-lg-6 mb-3">
                  <div class="form-item">
                    <label for="isolatedFilterContactnumber">Contact No.</label>
                    <select id="isolatedFilterContactnumber" class="form-select filter-select" multiple></select>
                  </div>
                </div>

                <!-- Email -->
                <div class="col-lg-6 mb-3">
                  <div class="form-item">
                    <label for="isolatedFilterEmail">Email Id</label>
                    <select id="isolatedFilterEmail" class="form-select filter-select" multiple></select>
                  </div>
                </div>

                <!-- Location -->
                <div class="col-lg-6 mb-3">
                  <div class="form-item">
                    <label for="isolatedFilterLocation">Location</label>
                    <select id="isolatedFilterLocation" class="form-select filter-select" multiple></select>
                  </div>
                </div>

                <!-- Source of Lead -->
                <div class="col-lg-6 mb-3">
                  <div class="form-item">
                    <label for="isolatedFilterSourceOfLead">Source of Lead</label>
                    <select id="isolatedFilterSourceOfLead" class="form-select filter-select" multiple></select>
                  </div>
                </div>

                <!-- Status -->
                <div class="col-lg-6 mb-3">
                  <div class="form-item">
                    <label for="isolatedFilterStatus">Status</label>
                    <select id="isolatedFilterStatus" class="form-select filter-select" multiple></select>
                  </div>
                </div>

                <!-- Assigned Project Name -->
                <div class="col-lg-6 mb-3">
                  <div class="form-item">
                    <label for="isolatedFilterAssignedProjectName">Assigned Project Name</label>
                    <select id="isolatedFilterAssignedProjectName" class="form-select filter-select" multiple></select>
                  </div>
                </div>

                <!-- Assigned User Name -->
                <div class="col-lg-6 mb-3">
                  <div class="form-item">
                    <label for="isolatedFilterAssignedUserName">Assigned User Name</label>
                    <select id="isolatedFilterAssignedUserName" class="form-select filter-select" multiple></select>
                  </div>
                </div>
              </div>
            </div>
          </form>
        </div>
        <div class="modal-footer"
          style="border-top: 1px solid #e5e7eb; justify-content: center;">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
            style="border-radius: 8px; padding: 0.45rem 0.7rem; font-weight: 500;">Close</button>
          <button type="button" class="btn btn-danger" id="isolatedCancleFilter"
            style="border-radius: 8px; padding: 0.45rem 0.7rem; font-weight: 500;">Clear Filters</button>
          <button type="button" class="btn btn-primary" id="isolatedApplyFiltersBtn"
            style="border-radius: 8px; padding: 0.50rem 0.7rem; font-weight: 500; background-color: #0d6efd; border: none;">Apply
            Filters</button>
        </div>
      </div>
    </div>
  </div>
  <!-- Isolated Filter Modal End -->

  <!-- upload excel popup -->
  <div class="modal fade" id="uploadExcelPopup" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title">Upload Files</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="close"></button>
        </div>
        <form id="uploadForm" enctype="multipart/form-data">
          <div class="modal-body">
            <div class="container">
              <div class="card upload-card p-5">
                <div class="drop_box">
                  <h6 style="text-transform:uppercase">Files Supported: xlsx, xls, csv</h6>
                  <div class="upload-wrap">
                    <input type="file" name="file" accept=".xlsx, .xls, .csv" required class="upload-input">
                  </div>
                </div>
              </div>
              <a class="excelbutton accessbtn" id="download-excel-ex" style="cursor:pointer;"><i
                  class="bi bi-file-earmark-spreadsheet"></i>Download Sample Example</a>
            </div>
          </div>
          <div class="modal-footer">
            <div class="col-lg-12 text-center">
              <button type="submit" name="submit" class="btn btn-sm btn-primary">Upload</button>
              <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
  <!-- THis upload Form Ends -->
  <!-- Status Modal -->
  <div class="modal fade" id="viewStatusModal" tabindex="-1" role="dialog" aria-labelledby="viewStatusModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title" id="viewStatusModalLabel"><i class="bi bi-eye"></i> View Status</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <table class="table-bordered">
            <thead>
              <tr>
                <th>User Unique ID</th>
                <th>Project name</th>
                <th>Status</th>
                <th>History</th>
                <th>Recording</th>
              </tr>
            </thead>
            <tbody id="statusModalData">

            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <!-- Status Modal End-->
  <!-- Remarks Modal -->
  <div class="modal fade" id="viewRemarksModal" tabindex="-1" role="dialog" aria-labelledby="viewRemarksModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title" id="viewRemarksModalLabel">View Remarks</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <table class="table-bordered">
            <thead>
              <tr>
                <th>User Unique ID</th>
                <th>Project name</th>
                <th>Remarks</th>
              </tr>
            </thead>
            <tbody id="remarksModalData">

            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <!-- Remarks Modal End -->
  <!-- Assign User Popup -->
  <div class="modal fade" id="assignModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title">Assign Users</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
          </button>
        </div>
        <form id="assign-form">
          <div class="modal-body" style="padding: 1.5rem;">
            <p style="font-weight: 600; margin-bottom: 1rem;"><b>Total Leads</b>: <span id="selected-count"><b>
                  0</b></span></p>
            <input type="hidden" id="selected-ids" name="selected_ids">
            <!-- Hidden input to store selected users -->
            <input type="hidden" name="users" id="hidden-users">

            <!-- Show currently assigned users for selected leads -->
            <div id="current-assigned-users"
              style="display:none; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 14px;">
            </div>

            <div class="mb-3">
              <div class="form-item">
                <label for="assignprojectname">Project Name</label>
                <input type="text" id="assignprojectname" name="assignprojectname" class="form-control"
                  placeholder="Enter Project Name..." style="height: 44px;">
              </div>
            </div>

            <!-- Unified User Selection -->
            <div class="mb-3">
              <div class="form-item">
                <label for="user-select">Select User(s)</label>
                <select id="user-select" name="users[]" class="form-select filter-select" multiple style="width: 100%;">
                  <!-- Options will be populated dynamically -->
                </select>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <div class="col-lg-12 text-center">
              <button type="button" id="modal-assign-button" class="btn btn-sm btn-primary">Assign</button>
              <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
  <!-- Assign User PopUp model End -->

  <!-- Create Cron Job Popup -->
  <div class="modal fade" id="createCronModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title">Create Cron Job</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="create-cron-form">
          <div class="modal-body" style="padding: 1.5rem;">
            <p style="font-weight: 600; margin-bottom: 1rem;">Selected Leads: <span id="cron-selected-count">0</span></p>

            <div class="row cron-modal-row">
              <div class="col-md-6 cron-modal-col">
                <div class="mb-3">
                  <div class="form-item">
                    <label for="cron-project-name">Project Name</label>
                    <input type="text" id="cron-project-name" name="project_name" class="form-control cron-input-field" required>
                  </div>
                </div>
              </div>

              <div class="col-md-6 cron-modal-col">
                <div class="mb-3">
                  <div class="form-item">
                    <label for="cron-location">Location</label>
                    <input type="text" id="cron-location" name="location" class="form-control cron-input-field" required>
                  </div>
                </div>
              </div>
            </div>

            <div class="row cron-modal-row">
              <div class="col-md-6 cron-modal-col">
                <div class="mb-3">
                  <div class="form-item">
                    <label for="cron-interval">Interval (minutes)</label>
                    <input type="number" min="1" id="cron-interval" name="interval_time" class="form-control cron-input-field" required>
                  </div>
                </div>
              </div>

              <div class="col-md-6 cron-modal-col">
                <div class="mb-3">
                  <div class="form-item">
                    <label for="cron-assigned-user">Assigned Users</label>
                    <select id="cron-assigned-user" name="assigned_user[]" class="form-select cron-input-field" multiple required>
                    </select>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-sm btn-primary">Create Cron Job</button>
            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <!-- Create Cron Job Popup End -->

  <!-- Modal Structure for Assigned Users Start -->
  <div class="modal fade" id="assignUserModal" tabindex="-1" role="dialog" aria-labelledby="assignUserModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title" id="assignUserModalLabel">Assigned Users</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p id="assignUserList"></p>
        </div>
      </div>
    </div>
  </div>
  <!-- Modal Structure for Assigned Users End -->
  <!-- History Model for leads Start -->
  <div class="unique-status-sidebar" id="uniqueLeadHistorySidebar" style="z-index: 99999999;">
    <div class="unique-top-sect">
      <h1 class="unique-sidebar-title"><b>Lead History</b></h1>
      <button class="unique-close-btn" id="uniqueCloseSidebar">&times;</button>
    </div>
    <div class="unique-mid-sect">
      <div class="unique-bottom-boxes">
        <div class="unique-left-box" style="border-right:1px solid #ccc">
          <h4>Cus. Name</h4>
          <h6 id="lead_user_name"></h6>
        </div>
        <div class="unique-right-box">
          <h4>Cus. Number</span>
            <h6 id="lead_user_number"></h6>
        </div>
      </div>
      <ul class="unique-lead-history" id="followUpHistory">

      </ul>
    </div>
    <div class="unique-btm-sect">
      <div class="unique-bottom-static d-flex">
        <div class="unique-left-static" style="border-right:1px solid #ccc">
          <h4>Lead assigned on</h4>
          <h6 id="assigned_date_leads"></h6>
        </div>
        <div class="unique-right-static">
          <h4>Lead assigned by </span>
            <h6 id="assigned_by_user"></h6>
        </div>
      </div>
    </div>
  </div>
  <!-- History Model for leads End -->
  <!-- History Call Model for leads Start -->
  <div class="unique-status-sidebar" id="uniqueCallHistorySidebar" style="z-index: 9999999;">
    <div class="unique-top-sect">
      <h1 class="unique-sidebar-title"><b>Call History</b></h1>
      <button class="unique-close-btn" id="uniqueCloseCallSidebar">&times;</button>
    </div>
    <div class="unique-mid-sect">
      <div class="unique-bottom-boxes">
        <div class="unique-left-box" style="border-right:1px solid #ccc">
          <h4>Cus. Name</h4>
          <h6 id="lead_user_callname"></h6>
        </div>
        <div class="unique-right-box">
          <h4>Cus. Number</span>
            <h6 id="lead_user_callnumber"></h6>
        </div>
      </div>
      <ul class="unique-lead-history" id="followUpCallHistory">

      </ul>
    </div>
    <div class="unique-btm-sect">
      <div class="unique-bottom-static d-flex">
        <div class="unique-left-static" style="border-right:1px solid #ccc">
          <h4>Lead assigned on</h4>
          <h6 id="assigned_date_callleads"></h6>
        </div>
        <div class="unique-right-static">
          <h4>Lead assigned by </span>
            <h6 id="assigned_by_calluserr"></h6>
        </div>
      </div>
    </div>
  </div>
  <!-- History CAll Model for leads End -->
  <!-- Add Lead Modal -->
  <div class="modal fade" id="addLeadModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 500px; margin: 1.75rem auto;">
      <form id="addLeadForm" method="POST">
        <div class="modal-content">
          <div class="modal-header" style="border-bottom: 1px solid #e5e7eb; padding: 1.5rem;">
            <h5 class="modal-title"
              style="color: #000; font-weight: 700; font-size: 1.125rem; margin: 0; text-transform: uppercase; letter-spacing: 0.05em;">
              ADD NEW LEAD</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
              style="font-size: 1.0rem;"></button>
          </div>
          <div class="modal-body" style="padding: 1.5rem; padding-bottom: 0.1rem;">
            <div class="form-item">
              <label for="leadName">Name</label>
              <input type="text" class="form-control" id="leadName" name="name" required>
            </div>
            <div class="form-item">
              <label for="leadNumber">Number</label>
              <input type="text" class="form-control" id="leadNumber" name="number" required>
            </div>
            <div class="form-item">
              <label for="leadEmail">Email</label>
              <input type="email" class="form-control" id="leadEmail" name="email" required>
            </div>
            <div class="form-item">
              <label for="leadProject">Project</label>
              <input type="text" class="form-control" id="leadProject" name="project" required>
            </div>
          </div>
          <div class="modal-footer"
            style="border-top: 1px solid #e5e7eb; padding: 0.5rem 0.1rem; justify-content: center; gap: 1rem;">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
              style="border-radius: 8px; padding: 0.45rem 0.5rem; font-weight: 400;">Close</button>
            <button type="button" class="btn btn-primary" id="submitLead"
              style="border-radius: 8px; padding: 0.45rem 0.5rem; font-weight: 400; background-color: #0d6efd; border: none;">Add
              Lead</button>
          </div>
        </div>
      </form>
    </div>
  </div>
  <!-- Add Lead Modal End -->
  <!-- this is main table structure start -->
  <div class="container">
    <div class="row">
      <div id="filterToast" class="filter-toast" role="status" aria-live="polite">
        <span class="toast-count">0</span>
        <span class="toast-text">Leads Filtered</span>
        <button type="button" class="toast-close" aria-label="Close">&times;</button>
      </div>
      <div id="uploadMessage" class="alert" style="display: none;"></div>
      <div class="dt-layout-row-wrapper" style="padding: 0px 5px !important;">
        <div class="col-lg-12">
          <!-- Top Row: Main Badges -->
          <div class="badge-scroll-wrap">
            <button class="badge-arrow left" data-target="#topBadgeTrack" aria-label="Scroll left">&#8249;</button>
            <div class="leads-top-badges badge-scroll" id="topBadgeTrack">
              <button class="filter-btn server-true" id="totalLeads" data-lead-type="total"
                style="background-color: #fff; color: #16a34a; padding: 8px 12px"><i class="fa-solid fa-list-ul"></i>
                Total <span class="count">0</span></button>
              <button class="filter-btn server-true" id="myLeads" data-lead-type="my"><i class="fa-solid fa-user"></i>
                My Leads <span class="count">0</span></button>
              <button class="filter-btn server-true booked" id="bookedLeads" data-lead-type="booked"><i
                  class="fa-solid fa-book"></i> Booked <span class="count">0</span></button>
              <button class="filter-btn server-true" id="todayFollowUps" data-lead-type="today"><i
                  class="fa-solid fa-calendar-check"></i> Today FollowUp's <span class="count">0</span></button>
              <button class="filter-btn server-true dropped" id="droppedLeads" data-lead-type="dropped"><i
                  class="fa-solid fa-ban"></i> Dropped <span class="count">0</span></button>
              <button class="filter-btn server-true ads" id="paidAds" data-lead-type="ads"><i
                  class="fa-solid fa-bullhorn"></i> Ads <span class="count">0</span></button>
              <button class="filter-btn server-true" id="shi_d" data-lead-type="shi_d"><i
                  class="fa-solid fa-database"></i> SHI-D <span class="count">0</span></button>
              <?php if ($tablename !== 'subham323'): ?>
                <a href="/superadmin/crm/user-eoi" style="text-decoration: none;">
                  <button class="filter-btn server-true" id="eoicounterdata"
                    style="background-color: #fff; color: #16a34a;"><i class="fa-solid fa-clipboard-check"></i> EOI <span
                      class="count">0</span></button>
                </a>
                <button class="filter-btn server-true" id="deletedLeads" data-lead-type="deleted"
                  style="background-color: #fff; color: #dc2626;"><i class="fa-solid fa-trash"></i> Deleted Leads <span
                    class="count">0</span></button>
              <?php endif; ?>
              <button class="filter-btn server-true" id="totalUnassigned" data-lead-type="unassigned"
                style="background-color: #fff; color: #16a34a;"><i class="fa-solid fa-user-slash"></i> Unassigned <span
                  class="count">0</span></button>
            </div>
            <button class="badge-arrow right" data-target="#topBadgeTrack" aria-label="Scroll right">&#8250;</button>
          </div>

          <!-- Second Row: Filter Badges -->
          <div class="badge-scroll-wrap">
            <button class="badge-arrow left" data-target="#filterBadgeTrack" aria-label="Scroll left">&#8249;</button>
            <div class="leads-filter-badges badge-scroll" id="filterBadgeTrack">
              <?php if ($tablename !== 'subham323'): ?>
                <button type="button" class="filter-btn small-btn" data-bs-toggle="modal" data-bs-target="#addLeadModal"
                  style="background-color: #fff; color: #16a34a;">
                  <i class="bi bi-person-add"></i> Add Lead
                </button>
              <?php endif; ?>
              <button class="filter-btn server-true small-btn" id="activeLeads" data-lead-type="active"><i
                  class="fa-solid fa-circle-check"></i> Active <span class="count">0</span></button>
              <button class="filter-btn server-true small-btn" id="freshLeads" data-lead-type="fresh"><i
                  class="fa-solid fa-star"></i> New <span class="count">0</span></button>
              <button class="filter-btn server-true untouch-small" id="pendingLeads" data-lead-type="pending"><i
                  class="fa-solid fa-clock"></i> Untouched <span class="count">0</span></button>
              <button class="filter-btn server-true small-btn" id="followLeads" data-lead-type="follow"><i
                  class="fa-solid fa-arrow-up"></i> Follow Up <span class="count">0</span></button>
              <button class="filter-btn server-true untouch-small" id="overdueLeads" data-lead-type="overdue"><i
                  class="fa-solid fa-triangle-exclamation"></i> Overdue <span class="count">0</span></button>

              <!-- Status Filter Dropdown -->
              <div class="status-filter-container">
                <button class="filter-btn server-false small-btn status-filter-btn" id="filterStatus">
                  <i class="fa-solid fa-filter"></i> Status <i class="fa-solid fa-caret-down"></i>
                </button>
                <div class="status-filter-dropdown">
                  <div class="status-filter-search">
                    <input type="text" placeholder="Search status..." class="status-search-input">
                  </div>
                  <div class="status-options">
                    <button class="status-option" data-status="All"><span class="status-badge all">All</span></button>
                    <button class="status-option" data-status="Pending"><span
                        class="status-badge pending">Pending</span></button>
                    <button class="status-option" data-status="Fake"><span
                        class="status-badge fake">Fake</span></button>
                    <button class="status-option" data-status="RNR"><span class="status-badge rnr">RNR</span></button>
                    <button class="status-option" data-status="Call Back"><span class="status-badge call-back">Call
                        Back</span></button>
                    <button class="status-option" data-status="Already Booked"><span
                        class="status-badge already-booked">Already Booked</span></button>
                    <button class="status-option" data-status="Not Interested"><span
                        class="status-badge not-interested">Not Interested</span></button>
                    <button class="status-option" data-status="Interested"><span
                        class="status-badge interested">Interested</span></button>
                    <button class="status-option" data-status="Fix Site Visit"><span
                        class="status-badge fix-site-visit">Fix Site Visit</span></button>
                    <button class="status-option" data-status="Site Visit Done"><span
                        class="status-badge site-visit-done">Site Visit Done</span></button>
                    <button class="status-option" data-status="Converted"><span
                        class="status-badge converted">Converted</span></button>
                    <button class="status-option" data-status="Re site visit"><span
                        class="status-badge re-site-visit">Re site visit</span></button>
                    <button class="status-option" data-status="Not Connected"><span
                        class="status-badge not-connected">Not Connected</span></button>
                  </div>
                </div>
              </div>
            </div>
            <button class="badge-arrow right" data-target="#filterBadgeTrack" aria-label="Scroll right">&#8250;</button>
          </div>

          <!-- Third Row: Controls -->
          <div class="leads-controls-row">
            <div class="leads-search-box">
              <i class="bi bi-search"></i>
              <input type="text" id="searchInput" placeholder="Search leads...">
            </div>

            <div class="leads-toolbar-controls">
              <?php if ($tablename !== 'subham323' && $tablename !== 'NoUser323'): ?>
                <button class="toolbar-icon-btn" id="assign-button" data-bs-toggle="modal" data-bs-target="#assignModal"
                  disabled title="Assign Users">
                  <i class="bi bi-people"></i>
                </button>
                <button class="toolbar-icon-btn" id="delete-selected-btn" title="Delete Selected">
                  <i class="bi bi-trash"></i>
                </button>
                <button class="toolbar-icon-btn" id="downloadCsv" title="Download Leads">
                  <i class="bi bi-download"></i>
                </button>
                <button class="toolbar-icon-btn uploadExcelPopup" data-bs-toggle="modal"
                  data-bs-target="#uploadExcelPopup" title="Upload Excel">
                  <i class="bi bi-cloud-arrow-up"></i>
                </button>
              <?php endif; ?>
              <button class="toolbar-icon-btn" id="multicolumFilter" data-bs-toggle="modal"
                data-bs-target="#isolatedFilterModal" title="Filter">
                <i class="bi bi-filter"></i>
              </button>
              <button class="toolbar-icon-btn Visibility_dropdown" title="Column Visibility">
                <i class="bi bi-layout-three-columns"></i>
                <div class="Visibility_dropdown-content" id="columnSelector"></div>
              </button>
              <div class="rowSelector_wrap">
                <select id="rowSelector">
                  <option value="10">10</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
                  <option value="200">200</option>
                  <option value="300">300</option>
                </select>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-12">
          <div class="dt-layout-row-mid">
            <div class="scrollable-table leads-modern-wrapper">
              <div id="responseMessage" style="display:none;"></div>
              <form id="bulkDeleteForm" method="POST">
                <table id="myTable" class="enhanced-table">
                  <thead>
                    <tr>
                      <th><input type="checkbox" id="select-all"></th>
                      <th>Lead</th>
                      <th>Project</th>
                      <th class="default-hide">Budget</th>
                      <th>Assigned Lead</th>
                      <th class="default-hide">Email</th>
                      <th class="default-hide">Location</th>
                      <th class="default-hide">Created At</th>
                      <th class="default-hide">ID</th>
                      <th style="text-align: center;">Lead Source</th>
                      <th>Status</th>
                      <th class="expand-btn-cell" style="display: none;"></th>
                      <th class="always-hide visibility-skip" <?php if ($tablename === 'subham323')
                        echo 'style="display:none;"'; ?>>Number</th>
                      <th class="always-hide visibility-skip">Status Text</th>
                    </tr>
                  </thead>
                  <tbody id="uploaddata">

                  </tbody>
                </table>
              </form>
            </div>
          </div>
        </div>
        <div class="col-lg-12 mt-2">
          <div class="dt-layout-foot">
            <!-- showing page nos  -->
            <div id="rowInfo"></div>
            <!-- pagination div  -->
            <div class="pagination" id="pagination">
              <button id="prevButton" disabled>←</button>
              <span id="pageNumbers"></span>
              <button id="nextButton" disabled>→</button>
            </div>
            <!-- jump on page no div  -->
            <div id="jumpToPage" class="search">
              <input type="number" id="jumpToPageInput" class="searchTerm" placeholder="Page No." min="1" />
              <button id="jumpButton" class="searchButton">Jump</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- this is main table structure start End -->
</div>

<button id="create-cron-fab" class="create-cron-fab" type="button" data-bs-toggle="modal" data-bs-target="#createCronModal">
  Create Cron Job <span id="create-cron-fab-count" class="create-cron-fab-count">0</span>
</button>

<!-- incentive main close -->
<script>
  const tablename = "<?php echo $_SESSION['tablename']; ?>";
</script>
<!-- this is my scripts for working project  -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.min.js"></script>
<!-- Select2 JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script src="https://www.searchhomesindia.in/assets/js/pullToRefresh.js"></script>
<script type="text/javascript" src="./assets/js/get_data_api.js"></script>
<script type="text/javascript" src="./assets/js/get_table_js.js"></script>
<script type="text/javascript" src="./assets/js/get_downloads.js"></script>
<script type="text/javascript" src="../assets/js/script.js"></script>
<script>
  (function () {
    const cronTickUrl = new URL('../myapicontainer/v2/api/leads_cron.php', window.location.href).href;

    function tickCron() {
      fetch(cronTickUrl, {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-store'
      }).catch(function () {
        // Silent by design: failed tick should not affect UI usage.
      });
    }

    tickCron();
    setInterval(tickCron, 30000);
  })();
</script>
<!-- javascript for get the search in dropdown search  -->
<script>
  $(document).ready(function () {
    // Function to populate dropdowns based on the table data
    function populateDropdowns() {
      var locations = new Set();
      var projects = new Set();
      var source_of_lead = new Set();
      var assignedUsers = new Set();
      var statuses = new Set();
      var createdAtDates = new Set();

      $("#uploaddata tr").each(function () {
        var location = $(this).find("td:eq(8)").text().trim();
        var project = $(this).find("td:eq(2)").text().trim();
        var source_of_lead = $(this).find("td:eq(4)").text().trim();
        var assignedUser = $(this).find("td:eq(3)").text().trim();
        var status = $(this).find("td:eq(10)").text().trim();
        var createdAt = $(this).find("td:eq(9)").text().trim();

        if (location) locations.add(location);
        if (project) projects.add(project);
        if (source_of_lead) source_of_lead.add(source_of_lead);
        if (assignedUser) assignedUsers.add(assignedUser);
        if (status) statuses.add(status);
        if (createdAt) createdAtDates.add(createdAt);
      });

      // Populate Location dropdown
      $("#LocationDropdown").empty().append('<a class="dropdown-item" data-value="">Select Location</a>');
      locations.forEach(function (location) {
        $("#LocationDropdown").append('<a class="dropdown-item" data-value="' + location + '">' + location + '</a>');

      });

      // Populate Project dropdown
      $("#ProjectDropdown").empty().append('<a class="dropdown-item" data-value="">Select Project</a>');
      projects.forEach(function (project) {
        $("#ProjectDropdown").append('<a class="dropdown-item" data-value="' + project + '">' + project + '</a>');

      });

      // Populate Assigneduser dropdown
      $("#AssigneduserDropdown").empty().append('<a class="dropdown-item" data-value="">Select Assigned User</a>');
      assignedUsers.forEach(function (user) {
        $("#AssigneduserDropdown").append('<a class="dropdown-item" data-value="' + user + '">' + user + '</a>');
      });

      // Populate Status dropdown
      $("#StatusDropdown").empty().append('<a class="dropdown-item" data-value="">Select Status</a>');
      statuses.forEach(function (status) {
        $("#StatusDropdown").append('<a class="dropdown-item" data-value="' + status + '">' + status + '</a>');
      });

      // Populate Created At dropdown
      $("#CreatedatDropdown").empty().append('<a class="dropdown-item" data-value="">Select Created At</a>');
      createdAtDates.forEach(function (date) {
        $("#CreatedatDropdown").append('<a class="dropdown-item" data-value="' + date + '">' + date + '</a>');
      });
    }

    // Call the function to populate the dropdowns when the page loads
    populateDropdowns();

    // Searchable input field
    function setUpSearchableDropdown(inputId, dropdownId) {
      var $input = $("#" + inputId);
      var $dropdown = $("#" + dropdownId);

      $input.on("focus", function () {
        $dropdown.show();
      });

      $input.on("input", function () {
        var query = $(this).val().toLowerCase();
        $dropdown.find(".dropdown-item").each(function () {
          var text = $(this).text().toLowerCase();
          $(this).toggle(text.indexOf(query) !== -1);
        });
      });

      $(document).on("click", function (event) {
        if (!$(event.target).closest("#" + inputId + ", #" + dropdownId).length) {
          $dropdown.hide();
        }
      });

      $dropdown.on("click", ".dropdown-item", function () {
        var value = $(this).data("value");
        $input.val($(this).text());
        $dropdown.hide();
      });
    }

    // Set up searchable dropdowns
    setUpSearchableDropdown("LocationInput", "LocationDropdown");
    setUpSearchableDropdown("ProjectInput", "ProjectDropdown");
    setUpSearchableDropdown("AssigneduserInput", "AssigneduserDropdown");
    setUpSearchableDropdown("StatusInput", "StatusDropdown");
    setUpSearchableDropdown("CreatedatInput", "CreatedatDropdown");
  });
</script>
<!-- javascript for get the search in dropdown search End  -->
<!-- this is my scripts for working project  End -->
<!-- Audio Popup Player Styles -->
<style>
    #audioPopupPlayer {
        position: fixed;
        bottom: -100px;
        left: 50%;
        transform: translateX(-50%);
        background: #111827;
        color: white;
        padding: 12px 24px;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        gap: 15px;
        z-index: 100000;
        transition: bottom 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        min-width: 300px;
    }
    #audioPopupPlayer.active {
        bottom: 30px;
    }
    #audioPopupPlayer .controls {
        display: flex;
        align-items: center;
        gap: 12px;
        width: 100%;
    }
    #audioPopupPlayer .player-btn {
        background: #3b82f6;
        border: none;
        color: white;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 14px;
    }
    #audioPopupPlayer .time-display {
        font-size: 12px;
        font-variant-numeric: tabular-nums;
        color: #9ca3af;
        min-width: 35px;
    }
    #audioPopupPlayer input[type="range"] {
        flex: 1;
        accent-color: #3b82f6;
        height: 4px;
    }
    #popupClose {
        cursor: pointer;
        padding: 4px;
        margin-left: auto;
        color: #9ca3af;
        font-size: 18px;
        line-height: 1;
    }
    #popupClose:hover {
        color: white;
    }
</style>

<!-- Audio Popup Player HTML -->
<div id="audioPopupPlayer">
    <audio id="popupAudio"></audio>
    <div class="controls">
        <button class="player-btn" id="popupPlayPause">▶</button>
        <span class="time-display" id="popupCurrent">0:00</span>
        <input type="range" id="popupProgress" value="0" min="0" step="1">
        <span class="time-display" id="popupDuration">0:00</span>
        <span id="popupClose">&times;</span>
    </div>
</div>

<!-- Audio Popup Player Script -->
<script>
function initAudioPlayer() {
    const popup = document.getElementById('audioPopupPlayer');
    const audio = document.getElementById('popupAudio');
    const playPauseBtn = document.getElementById('popupPlayPause');
    const progressBar = document.getElementById('popupProgress');
    const currentTimeEl = document.getElementById('popupCurrent');
    const durationEl = document.getElementById('popupDuration');
    const closeBtn = document.getElementById('popupClose');
    
    if(!popup || !audio || !playPauseBtn || !progressBar || !currentTimeEl || !durationEl || !closeBtn) return;
    
    // Show popup and play audio
    $(document).on('click', '.play-recording-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const url = $(this).data('url');
        if (!url) return;
        
        audio.src = url;
        audio.load();
        popup.classList.add('active');
        
        audio.play()
            .then(() => {
                playPauseBtn.textContent = '⏸';
            })
            .catch(e => console.log('Auto-play prevented'));
    });

    // Play/Pause toggle
    playPauseBtn.addEventListener('click', () => {
        if (audio.paused) {
            audio.play();
            playPauseBtn.textContent = '⏸';
        } else {
            audio.pause();
            playPauseBtn.textContent = '▶';
        }
    });

    // Update progress
    audio.addEventListener('timeupdate', () => {
        const current = audio.currentTime;
        const duration = audio.duration;
        
        if (duration) {
            const progressPercent = (current / duration) * 100;
            progressBar.value = progressPercent;
            
            // Format time
            currentTimeEl.textContent = formatTime(current);
            if (!isNaN(duration)) {
                durationEl.textContent = formatTime(duration);
            }
        }
    });

    // Seek
    progressBar.addEventListener('input', (e) => {
        const seekTime = (audio.duration / 100) * e.target.value;
        audio.currentTime = seekTime;
    });

    // Close
    closeBtn.addEventListener('click', () => {
        audio.pause();
        popup.classList.remove('active');
    });

    function formatTime(seconds) {
        if (isNaN(seconds)) return "0:00";
        const min = Math.floor(seconds / 60);
        const sec = Math.floor(seconds % 60);
        return `${min}:${sec.toString().padStart(2, '0')}`;
    }
}

// Execute initialization
document.addEventListener('DOMContentLoaded', initAudioPlayer);
if(document.readyState === 'complete' || document.readyState === 'interactive') {
    initAudioPlayer();
}
</script>
</body>

</html>