<?php
  require_once 'config.php';
  require_once __DIR__ . '/payroll_attendance_rules.php';
  if (file_exists(__DIR__ . '/vendor/autoload.php')) {
      require_once __DIR__ . '/vendor/autoload.php';
  }
  class Database extends Config {
    private static $offerLettersTableReady = false;
    private static $offerLettersSynced = false;
    private static $userPayslipsTableReady = false;

    public function __construct() {
      parent::__construct();
      try {
          $check = $this->conn->query("SHOW COLUMNS FROM leave_types LIKE 'is_paid'")->fetch();
          if (!$check) {
              $this->conn->exec("ALTER TABLE leave_types ADD COLUMN is_paid TINYINT(1) DEFAULT 1");
          }
      } catch (Exception $e) {
          // ignore or log
      }
      $this->ensurePayrollPresentDaysDecimal();
      $this->createCompanyHolidaysTable();
    }

    /** Support fractional paid days from payroll rules (half-day %, late %, LWP %). */
    private function ensurePayrollPresentDaysDecimal() {
      try {
          $col = $this->conn->query("SHOW COLUMNS FROM payroll WHERE Field = 'present_days'")->fetch();
          if ($col && stripos($col['Type'], 'int') !== false) {
              $this->conn->exec("ALTER TABLE payroll MODIFY present_days DECIMAL(6,2) NOT NULL");
          }
      } catch (Exception $e) {
          // table may not exist yet
      }
    }

    public function createCompanyHolidaysTable() {
      $sql = "CREATE TABLE IF NOT EXISTS company_holidays (
          id INT AUTO_INCREMENT PRIMARY KEY,
          holiday_date DATE NOT NULL,
          reason VARCHAR(255) NOT NULL,
          created_by INT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY uniq_holiday_date (holiday_date)
      )";
      try {
          $this->conn->exec($sql);
      } catch (Exception $e) {
          // ignore or log
      }
    }

    /** @return array<int,array{holiday_date:string,reason:string,created_at?:string}> */
    public function listCompanyHolidays(string $startDate, string $endDate) {
      $sql = "SELECT holiday_date, reason, created_at
              FROM company_holidays
              WHERE holiday_date BETWEEN :start AND :end
              ORDER BY holiday_date ASC";
      $stmt = $this->conn->prepare($sql);
      $stmt->execute(['start' => $startDate, 'end' => $endDate]);
      return $stmt->fetchAll();
    }

    public function upsertCompanyHoliday(string $date, string $reason, ?int $createdBy = null) {
      $sql = "INSERT INTO company_holidays (holiday_date, reason, created_by)
              VALUES (:d, :r, :cb)
              ON DUPLICATE KEY UPDATE reason = VALUES(reason), created_by = VALUES(created_by)";
      $stmt = $this->conn->prepare($sql);
      return $stmt->execute(['d' => $date, 'r' => $reason, 'cb' => $createdBy]);
    }

    public function deleteCompanyHoliday(string $date) {
      $sql = "DELETE FROM company_holidays WHERE holiday_date = :d";
      $stmt = $this->conn->prepare($sql);
      return $stmt->execute(['d' => $date]);
    }
    // Ensure Offer Letters table exists (once per request).
    public function createOfferLettersTable() {
      if (self::$offerLettersTableReady) {
          return;
      }
      self::$offerLettersTableReady = true;

      $sql = "CREATE TABLE IF NOT EXISTS offer_letters (
          id INT AUTO_INCREMENT PRIMARY KEY,
          user_id INT NULL,
          candidate_name VARCHAR(100) NOT NULL,
          email VARCHAR(100) NOT NULL,
          phone VARCHAR(20) NOT NULL,
          position VARCHAR(100) NOT NULL,
          department VARCHAR(100),
          monthly_salary DECIMAL(10, 2),
          joining_date DATE,
          reporting_manager VARCHAR(100),
          offer_status VARCHAR(20) DEFAULT 'Draft',
          emailed_at DATETIME NULL,
          emailed_by VARCHAR(100) NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )";
      $this->conn->exec($sql);
      
      try {
          $this->conn->exec("ALTER TABLE offer_letters ADD COLUMN user_id INT NULL AFTER id");
      } catch (Exception $e) {
          // column may already exist
      }
      try {
          $this->conn->exec("ALTER TABLE offer_letters ADD COLUMN emailed_at DATETIME NULL AFTER offer_status");
      } catch (Exception $e) {
          // column may already exist
      }
      try {
          $this->conn->exec("ALTER TABLE offer_letters ADD COLUMN emailed_by VARCHAR(100) NULL AFTER emailed_at");
      } catch (Exception $e) {
          // column may already exist
      }
    }
    public function tablenameExists($tablename, $excludeId = null) {
      $tablename = trim((string)$tablename);
      if ($tablename === '') {
          return false;
      }
      $sql = 'SELECT COUNT(*) FROM accounts WHERE tablename = :tablename';
      $params = ['tablename' => $tablename];
      if ($excludeId !== null && (int)$excludeId > 0) {
          $sql .= ' AND id != :id';
          $params['id'] = (int)$excludeId;
      }
      $stmt = $this->conn->prepare($sql);
      $stmt->execute($params);
      return (int)$stmt->fetchColumn() > 0;
    }

    public function emailExists($email, $excludeId = null) {
      $email = strtolower(trim((string)$email));
      if ($email === '') {
          return false;
      }
      $sql = 'SELECT COUNT(*) FROM accounts WHERE LOWER(TRIM(useremail)) = :email';
      $params = ['email' => $email];
      if ($excludeId !== null && (int)$excludeId > 0) {
          $sql .= ' AND id != :id';
          $params['id'] = (int)$excludeId;
      }
      $stmt = $this->conn->prepare($sql);
      $stmt->execute($params);
      return (int)$stmt->fetchColumn() > 0;
    }

    public function generateUniqueTablename($preferred = '') {
      $base = trim((string)$preferred);
      if ($base === '') {
          $base = 'USR';
      }
      $candidate = $base;
      $suffix = 1;
      while ($this->tablenameExists($candidate)) {
          $candidate = $base . '_' . $suffix;
          $suffix++;
          if ($suffix > 9999) {
              $candidate = $base . '_' . time() . '_' . random_int(100, 999);
              break;
          }
      }
      return $candidate;
    }

    // Insert User Into Database 
    public function insert($doj, $dob, $ename, $eemail, $enumber, $epass, $esalary, $etable, $emid, $ecode, $amountO, $amountT, $amountTh, $amountF, $amountFf, $amountS, $project_name, $D_project, $user_type = '', $assign_user = '', $is_active = 1, $city = '') {
      $etable = trim((string)$etable);
      if ($etable === '' && trim((string)$emid) !== '') {
          $etable = trim((string)$emid);
      }
      if ($etable === '') {
          $etable = $this->generateUniqueTablename('USR');
      } elseif ($this->tablenameExists($etable)) {
          return false;
      }
      if ($this->emailExists($eemail)) {
          return false;
      }

      $sql = 'INSERT INTO accounts (doj, dob, username, useremail, phonenumber, epassword, salary, tablename, employee_id, one_amt, two_amt, thrid_amt, forth_amt, fifth_amt, sixth_amt, project_name, project_type, user_type, assign_user, city, is_active)
       VALUES (:doj, :dob, :ename, :eemail, :enumber, :epass, :esalary, :etable, :emid, :amountO, :amountT, :amountTh, :amountF, :amountFf, :amountS, :project_name, :D_project, :user_type, :assign_user, :city, :is_active)';
      try {
          $stmt = $this->conn->prepare($sql);
          $stmt->execute([
            'doj' => $doj,
            'dob' => $dob,
            'ename' => $ename,
            'eemail' => $eemail,
            'enumber' => $enumber,
            'epass' => $epass,
            'esalary' => $esalary,
            'etable' => $etable,
            'emid' => $emid,
            'amountO' => $amountO,
            'amountT' => $amountT,
            'amountTh' => $amountTh,
            'amountF' => $amountF,
            'amountFf' => $amountFf,
            'amountS' => $amountS,
            'project_name' => $project_name,
            'D_project' => $D_project,
            'user_type' => $user_type,
            'assign_user' => $assign_user,
            'city' => $city,
            'is_active' => $is_active
          ]);
          return true;
      } catch (PDOException $e) {
          if ($e->getCode() === '23000') {
              return false;
          }
          throw $e;
      }
    }
    // Fetch All accounts From Database
    public function read() {
      $sql = 'SELECT * FROM accounts ORDER BY id DESC';
      $stmt = $this->conn->prepare($sql);
      $stmt->execute();
      $result = $stmt->fetchAll();
      return $result;
    }

    /** Active employees with fields required for bulk payroll (no password / unused columns). */
    public function readActiveForPayroll() {
      $sql = 'SELECT id, username, salary, is_active,
                     one_amt, two_amt, thrid_amt, forth_amt, fifth_amt, sixth_amt
              FROM accounts
              WHERE is_active = 1
              ORDER BY id DESC';
      $stmt = $this->conn->prepare($sql);
      $stmt->execute();
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // Fetch Single User From Database
    public function readOne($id) {
      $sql = 'SELECT * FROM accounts WHERE id = :id';
      $stmt = $this->conn->prepare($sql);
      $stmt->execute(['id' => $id]);
      $result = $stmt->fetch();
      return $result;
    }
    // Update Single User 
    public function update($id, 
      $doj, $dob, 
      $ename, $eemail, 
      $enumber, $epass, 
      $esalary, $etable, 
      $emid, $amountO, 
      $amountT, $amountTh, 
      $amountF, $amountFf, 
      $amountS, $project_name, 
      $D_project, $user_type,
      $assign_user, $is_active, $city = ''
    ) {
    
    // Get the current salary from the account table
    $sql = 'SELECT is_active, salary, assign_user FROM accounts WHERE id = :id';
    $stmt = $this->conn->prepare($sql);
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $oldIsActive = $row['is_active'];
    $oldSalary = $row['salary'];
    $oldAssignUser = $row['assign_user'];
    if ($this->emailExists($eemail, $id)) {
        return false;
    }
    // Check if the assign_user has changed
    if ($assign_user !== $oldAssignUser) {
      // Step 1: Update the end date for the old manager's record in assign_user_history
      $sqlUpdateOldManager = 'UPDATE assign_user_history 
                              SET end_date = NOW() 
                              WHERE user_id = :user_id 
                              AND assign_user = :old_assign_user 
                              AND end_date IS NULL';  // Only update if end_date is NULL (still active)
      $stmtUpdateOldManager = $this->conn->prepare($sqlUpdateOldManager);
      $stmtUpdateOldManager->execute([
          'user_id' => $id,
          'old_assign_user' => $oldAssignUser
      ]);
      // Step 2: Insert a new record for the new manager in assign_user_history
      $sqlInsertNewManager = 'INSERT INTO assign_user_history (user_id, assign_user, effective_date) 
                              VALUES (:user_id, :assign_user, NOW())';
      $stmtInsertNewManager = $this->conn->prepare($sqlInsertNewManager);
      $stmtInsertNewManager->execute([
          'user_id' => $id,
          'assign_user' => $assign_user
      ]);
  }
    
    // Update the account table with the new values
    $sql = 'UPDATE accounts SET
        doj = :doj, 
        dob = :dob, 
        username = :ename, 
        useremail = :eemail, 
        phonenumber = :enumber, 
        epassword = :epass, 
        salary = :esalary, 
        tablename = :etable, 
        employee_id = :emid, 
        user_type = :user_type,
        old_salary = :oldsalary,
        one_amt = :amountO,
        two_amt = :amountT,
        thrid_amt = :amountTh,
        forth_amt = :amountF,
        fifth_amt = :amountFf,
        sixth_amt = :amountS,
        project_name = :project_name,
        project_type = :D_project,
        assign_user = :assign_user,
        city = :city,
        is_active = :is_active,
        flag_user_login = CURRENT_TIMESTAMP,
        deactivated_at = CASE 
            WHEN :is_active = 0 AND :oldIsActive = 1 THEN NOW()  -- Set deactivated_at when deactivating
            WHEN :is_active = 1 THEN NULL  -- Reset deactivated_at when reactivating
            ELSE deactivated_at  -- Keep the current value if no status change
        END
        WHERE id = :id';
    try {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            'doj' => $doj,
            'dob' => $dob,
            'ename' => $ename,
            'eemail' => $eemail,
            'enumber' => $enumber,
            'epass' => $epass,
            'esalary' => $esalary,
            'etable' => $etable,
            'emid' => $emid,
            'user_type' => $user_type,
            'oldsalary' => $oldSalary,
            'amountO' => $amountO,
            'amountT' => $amountT,
            'amountTh' => $amountTh,
            'amountF' => $amountF,
            'amountFf' => $amountFf,
            'amountS' => $amountS,
            'project_name' => $project_name,
            'D_project' => $D_project,
            'assign_user' => $assign_user,
            'city' => $city,
            'is_active' => $is_active,
            'oldIsActive' => $oldIsActive,
            'id' => $id
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return false;
        }
        throw $e;
    }
    // Send a WebSocket message to notify clients of the update
    $this->sendWebSocketMessage(json_encode(['action' => 'update', 'userId' => $id]));
    return true;
  }
  // this is to call the websocket
  private function sendWebSocketMessage($message) {
    if (!class_exists('WebSocket\Client')) {
        return;
    }
    $client = new WebSocket\Client("ws://searchhomesindia.in:65003/");
    try {
        $client->send($message);
        $client->close();
    } catch (Exception $e) {
        error_log("Failed to send WebSocket message: " . $e->getMessage());
    }
  }
    // Delete User From Database
    public function delete($id) {
      $id = (int)$id;
      if ($id <= 0) return false;

      // Fetch the employee unique-id key used by user_alerts.
      $sqlFetchTableName = 'SELECT tablename FROM accounts WHERE id = :id';
      $stmtFetchTableName = $this->conn->prepare($sqlFetchTableName);
      $stmtFetchTableName->execute(['id' => $id]);
      $result = $stmtFetchTableName->fetch(PDO::FETCH_ASSOC);
      if (!$result) return false;

      $tablename = (string)($result['tablename'] ?? '');

      // Helpers to keep cleanup compatible with optional tables.
      $tableExists = function ($tableName) {
          $stmt = $this->conn->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
          $stmt->execute(['t' => $tableName]);
          return (int)$stmt->fetchColumn() > 0;
      };
      $columnExists = function ($tableName, $columnName) {
          $stmt = $this->conn->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c");
          $stmt->execute(['t' => $tableName, 'c' => $columnName]);
          return (int)$stmt->fetchColumn() > 0;
      };

      try {
          $this->conn->beginTransaction();

          // Remove alerts keyed by employee unique-id.
          if ($tablename !== '' && $tableExists('user_alerts') && $columnExists('user_alerts', 'user_id')) {
              $stmtAlerts = $this->conn->prepare('DELETE FROM user_alerts WHERE user_id = :tablename');
              $stmtAlerts->execute(['tablename' => $tablename]);
          }

          // Remove dependent records from all major HR modules.
          $relations = [
              ['table' => 'attendance_logs',   'column' => 'user_id'],
              ['table' => 'leave_requests',    'column' => 'user_id'],
              ['table' => 'location_history',  'column' => 'user_id'],
              ['table' => 'assign_user_history','column' => 'user_id'],
              ['table' => 'user_documents',    'column' => 'user_id'],
              ['table' => 'offer_letters',     'column' => 'user_id'],
              ['table' => 'fnf_settlements',   'column' => 'user_id'],
              ['table' => 'payroll',           'column' => 'employee_id'],
              ['table' => 'asset_assignments', 'column' => 'employee_id'],
          ];

          foreach ($relations as $relation) {
              $table = $relation['table'];
              $column = $relation['column'];
              if (!$tableExists($table) || !$columnExists($table, $column)) continue;
              $stmt = $this->conn->prepare("DELETE FROM {$table} WHERE {$column} = :id");
              $stmt->execute(['id' => $id]);
          }

          // Finally delete employee account.
          $stmtAccount = $this->conn->prepare('DELETE FROM accounts WHERE id = :id');
          $stmtAccount->execute(['id' => $id]);
          $deleted = $stmtAccount->rowCount() > 0;

          if (!$deleted) {
              $this->conn->rollBack();
              return false;
          }

          $this->conn->commit();
          return true;
      } catch (Throwable $e) {
          if ($this->conn->inTransaction()) {
              $this->conn->rollBack();
          }
          error_log('Employee cascade delete failed: ' . $e->getMessage());
          return false;
      }
    }

    // --- Offer Letter Methods ---
    public function getDefaultOfferLetterHtml($user) {
        $today = date('d-m-Y');
        $joining_date = date('d-m-Y', strtotime($user['joining_date'] ?? ($user['doj'] ?? date('Y-m-d'))));
        $monthly_salary = (float)($user['monthly_salary'] ?? ($user['salary'] ?? 0));
        $annual_salary = $monthly_salary * 12;

        $basic = round($monthly_salary * 0.5);
        $hra = round($monthly_salary * 0.2);
        $conveyance = round($monthly_salary * 0.07);
        $pf_employer = min(1800, round(($basic) * 0.12));
        $monthly_gross = $monthly_salary - $pf_employer;
        $special = $monthly_gross - ($basic + $hra + $conveyance);
        $pf_employee = $pf_employer;
        $pt = 200;
        $medical = 817;
        $deductions = $pf_employee + $pt + $medical;
        $net_pay = $monthly_gross - $deductions;
        
        $candidate_name = htmlspecialchars($user['candidate_name'] ?? ($user['username'] ?? 'Candidate Name'));
        $position = htmlspecialchars($user['position'] ?? ($user['user_type'] ?? 'Position'));
        
        return '
        <div class="offer-letter-doc">
            <table class="letter-layout-table">
                <thead><tr><td><div class="header-space">&nbsp;</div></td></tr></thead>
                <tfoot><tr><td><div class="footer-space">&nbsp;</div></td></tr></tfoot>
                <tbody>
                    <tr>
                        <td>
                            <div class="page-content">
                                <h2 class="letter-title" style="text-align:center; font-size:18px; font-weight:800; text-decoration:underline; margin:20px 0 30px 0; color:#115b82;">Offer Letter</h2>
                                <p><strong>Date:</strong> ' . $today . '</p>
                                <p><strong>To,</strong><br><strong>' . $candidate_name . '</strong></p>
                                <div class="content-body" style="font-size:13.5px; text-align:justify; color:#1a1a1a;">
                                    <p>We are pleased to offer you employment at <strong>Search Homes India Pvt Ltd</strong>. We believe your skills and background will be valuable assets to our team and contribute significantly to our success.</p>
                                    <p>As per our discussion, your position will be <strong>' . $position . '</strong> with a fixed Annual Cost to Company (CTC) of <strong>INR ' . number_format($annual_salary) . '/- LPA</strong>. Enclosed with this letter, you\'ll find our employee handbook, which outlines additional benefits, including Provident Fund (PF) and Insurance.</p>
                                    <p><strong>Probation Period</strong></p>
                                    <p>You will be on a 90 days probationary period, during which the company reserves the right to terminate employment without notice or remuneration if your performance is not deemed satisfactory or you abscond / or as part of your employment you are expected to meet specific benchmark of Minimum 2 confirmed bookings within 60 days. Additionally, please note that no leave will be granted during the probationary period, and any absence will be considered as Loss of Pay (LOP).</p>
                                    <p><strong>Dress Code Guidelines</strong></p>
                                    <ol>
                                        <li><strong>Business Casual:</strong> Acceptable for most office days. This includes collared shirts, blouses, trousers, skirts, and dresses.</li>
                                        <li><strong>Formal Attire:</strong> On days when you have client meetings or special events, formal business attire is required. This includes suits, ties, blazers, formal skirts, and dresses.</li>
                                        <li><strong>Inappropriate Attire:</strong> Please avoid casual wear like T-shirts, shorts, flip-flops, and any clothing with logos, slogans, or Graphics not aligned with our company\'s image.</li>
                                    </ol>
                                    <p><strong>Notice Period</strong></p>
                                    <p>During your employment, a 15-day notice period is required by either party to terminate this contract. The notice period starts from the date your resignation letter is received by your manager. However, in case of a breach of company policy, the company may terminate the contract with immediate effect.</p>
                                    <p><strong>Full &amp; Final Settlement</strong></p>
                                    <p>Any employee wishing to resign must communicate his intent in writing for acceptance by management. On acceptance of resignation and after serving notice period by employee, FNF and deductions settlements process is initiated after post last working day and final amount (includes the employees unpaid salary only), shall be credited to the respective Employees Bank Account within 30 to 45 after relieving.</p>
                                    <p>If you choose to accept this offer, please sign and return the enclosed copy of this letter in the provided self-addressed, stamped envelope. We are excited to welcome you to the Search Homes India family.</p>
                                </div>
                                <div class="closing" style="margin-top:40px;">
                                    <p style="font-weight:bold; margin-bottom:40px;">Warm regards,</p>
                                    <div style="display:flex; justify-content:space-between; align-items:flex-end;">
                                        <div>
                                            <p style="font-weight:bold; margin-bottom:0;">Shivali V Rai</p>
                                            <p style="margin:0; font-size:13px; font-weight:600;">HR Manager</p>
                                            <p style="margin:0; font-size:13px; font-weight:600;">Search Homes India Pvt Ltd</p>
                                        </div>
                                    </div>
                                    <div style="margin-top:60px; display:flex; justify-content:space-between;">
                                        <div>
                                            <div style="border-top:1.5px solid #000; width:220px; padding-top:5px; font-weight:bold; font-size:13px;">Employee Signature</div>
                                            <div style="font-size:13px; margin-top:10px; font-weight:bold;">Date:</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr style="page-break-before: always;">
                        <td>
                            <div class="page-content" style="padding-top:40px;">
                                <h3 style="text-align:center; text-decoration:underline; font-weight:800; color:#115b82; margin-bottom:10px;">ANNEXURE - A</h3>
                                <p style="text-align:center; font-weight:bold; font-size:16px; margin-bottom:25px;">' . $candidate_name . '</p>
                                <table class="salary-table" style="width:100%; border-collapse:collapse; margin:20px 0; font-size:12px;">
                                    <thead>
                                        <tr style="background:#115b82; color:white; font-weight:700; text-transform:uppercase;">
                                            <th style="background:white; color:#115b82; font-size:16px;">CTC</th>
                                            <th>Monthly CTC</th>
                                            <th>Yearly CTC</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr class="total-row" style="background:#115b82; color:white; font-weight:700;">
                                            <td style="text-align:left; font-weight:600;">Earning</td>
                                            <td>' . round($monthly_salary) . '</td>
                                            <td>' . round($annual_salary) . '</td>
                                        </tr>
                                        <tr><td style="text-align:left; font-weight:600;">Basic</td><td>' . round($basic) . '</td><td>' . round($basic * 12) . '</td></tr>
                                        <tr><td style="text-align:left; font-weight:600;">HRA</td><td>' . round($hra) . '</td><td>' . round($hra * 12) . '</td></tr>
                                        <tr><td style="text-align:left; font-weight:600;">Conveyance Allowance</td><td>' . round($conveyance) . '</td><td>' . round($conveyance * 12) . '</td></tr>
                                        <tr><td style="text-align:left; font-weight:600;">Special Allowance</td><td>' . round($special) . '</td><td>' . round($special * 12) . '</td></tr>
                                        <tr class="category-row" style="background:#115b82; color:white; font-weight:700; text-align:left;"><td colspan="3">Statutory Benefit</td></tr>
                                        <tr><td style="text-align:left; font-weight:600;">PF (Employer Part)</td><td>' . round($pf_employer) . '</td><td>' . round($pf_employer * 12) . '</td></tr>
                                        <tr class="total-row" style="background:#115b82; color:white; font-weight:700;"><td style="text-align:left; font-weight:600;">Monthly Gross</td><td>' . round($monthly_gross) . '</td><td>' . round($monthly_gross * 12) . '</td></tr>
                                        <tr><td style="text-align:left; font-weight:600;">PF (Employee Part)</td><td>' . round($pf_employee) . '</td><td>' . round($pf_employee * 12) . '</td></tr>
                                        <tr><td style="text-align:left; font-weight:600;">PT</td><td>' . round($pt) . '</td><td>' . round($pt * 12) . '</td></tr>
                                        <tr><td style="text-align:left; font-weight:600;">Medical Benefit</td><td>' . round($medical) . '</td><td>' . round($medical * 12) . '</td></tr>
                                        <tr class="total-row" style="background:#115b82; color:white; font-weight:700;"><td style="text-align:left; font-weight:600;">Net Pay</td><td>' . round($net_pay) . '</td><td>' . round($net_pay * 12) . '</td></tr>
                                    </tbody>
                                </table>
                                <p style="font-size:11px; font-weight:800; color:#444; margin-top:15px;">Note: 1) Income Tax will be deducted as per the provision of Income Tax act 1961</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>';
    }

    /** One-time account/document → offer_letters sync (page load or explicit call). */
    public function ensureOfferLettersSynced() {
      $this->createOfferLettersTable();
      if (self::$offerLettersSynced) {
          return;
      }
      self::$offerLettersSynced = true;
      $this->syncAllAccountsToOfferLetters();
      $this->syncOfferLettersFromDocuments();
    }

    private function syncAllAccountsToOfferLetters() {
      try {
          $sql = "INSERT INTO offer_letters (
                      user_id, candidate_name, email, phone, position, department,
                      monthly_salary, joining_date, reporting_manager, offer_status
                  )
                  SELECT
                      a.id,
                      COALESCE(a.username, ''),
                      COALESCE(a.useremail, ''),
                      COALESCE(a.phonenumber, ''),
                      COALESCE(a.user_type, ''),
                      COALESCE(a.project_name, ''),
                      COALESCE(a.salary, 0),
                      a.doj,
                      COALESCE(a.assign_user, ''),
                      'Draft'
                  FROM accounts a
                  LEFT JOIN offer_letters ol ON ol.user_id = a.id
                  WHERE ol.id IS NULL";
          $this->conn->exec($sql);
      } catch (Exception $e) {
          // ignore or log sync errors
      }
    }

    private function syncOfferLettersFromDocuments() {
      try {
          $sql = "INSERT INTO offer_letters (
                      user_id, candidate_name, email, phone, position, department,
                      monthly_salary, joining_date, reporting_manager, offer_status, created_at
                  )
                  SELECT
                      ud.user_id,
                      COALESCE(a.username, ''),
                      COALESCE(a.useremail, ''),
                      COALESCE(a.phonenumber, ''),
                      COALESCE(a.user_type, ''),
                      COALESCE(a.project_name, ''),
                      COALESCE(a.salary, 0),
                      a.doj,
                      COALESCE(a.assign_user, ''),
                      'Draft',
                      ud.created_at
                  FROM user_documents ud
                  INNER JOIN accounts a ON a.id = ud.user_id
                  LEFT JOIN offer_letters ol ON ol.user_id = ud.user_id
                  WHERE ud.document_type = 'offer_letter'
                    AND ol.id IS NULL";
          $this->conn->exec($sql);
      } catch (Exception $e) {
          // ignore or log sync errors
      }
    }

    public function getOfferLetterStats() {
      $sql = "SELECT
          COUNT(*) AS total,
          SUM(CASE WHEN offer_status = 'Draft' THEN 1 ELSE 0 END) AS draft,
          SUM(CASE WHEN offer_status = 'Sent' AND emailed_at IS NOT NULL THEN 1 ELSE 0 END) AS sent,
          SUM(CASE WHEN offer_status = 'Accepted' THEN 1 ELSE 0 END) AS accepted,
          SUM(CASE WHEN offer_status = 'Rejected' THEN 1 ELSE 0 END) AS rejected
          FROM offer_letters";
      $stmt = $this->conn->prepare($sql);
      $stmt->execute();
      $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

      $total = (int)($row['total'] ?? 0);
      $sent = (int)($row['sent'] ?? 0);

      return [
          'total' => $total,
          'draft' => (int)($row['draft'] ?? 0),
          'sent' => $sent,
          'accepted' => (int)($row['accepted'] ?? 0),
          'rejected' => (int)($row['rejected'] ?? 0),
          'sent_percent' => $total > 0 ? (int)round(($sent / $total) * 100) : 0
      ];
    }

    public function getOfferLetters() {
      $sql = "SELECT * FROM offer_letters ORDER BY id DESC";
      $stmt = $this->conn->prepare($sql);
      $stmt->execute();
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOfferLettersPaged($filters = []) {
      $listColumns = 'id, user_id, candidate_name, email, phone, position, department, monthly_salary,
          joining_date, reporting_manager, offer_status, emailed_at, emailed_by, created_at';

      $where = ["1=1"];
      $params = [];

      if (!empty($filters['search'])) {
          $where[] = "(candidate_name LIKE :search OR email LIKE :search OR position LIKE :search OR department LIKE :search OR offer_status LIKE :search)";
          $params['search'] = '%' . $filters['search'] . '%';
      }
      if (!empty($filters['status'])) {
          $where[] = "offer_status = :status";
          $params['status'] = $filters['status'];
      }
      if (!empty($filters['from'])) {
          $where[] = "created_at >= :from_date";
          $params['from_date'] = $filters['from'] . ' 00:00:00';
      }
      if (!empty($filters['to'])) {
          $where[] = "created_at < :to_date_exclusive";
          $params['to_date_exclusive'] = date('Y-m-d', strtotime($filters['to'] . ' +1 day')) . ' 00:00:00';
      }

      $whereSql = implode(' AND ', $where);
      $limit = isset($filters['limit']) ? max(1, min(2000, (int)$filters['limit'])) : 10;
      $page = isset($filters['page']) ? max(1, (int)$filters['page']) : 1;
      $offset = ($page - 1) * $limit;

      $countStmt = $this->conn->prepare("SELECT COUNT(*) FROM offer_letters WHERE $whereSql");
      $countStmt->execute($params);
      $total = (int)$countStmt->fetchColumn();

      $stmt = $this->conn->prepare("SELECT {$listColumns} FROM offer_letters WHERE $whereSql ORDER BY candidate_name ASC, id DESC LIMIT :limit OFFSET :offset");
      foreach ($params as $key => $value) {
          $stmt->bindValue(':' . $key, $value);
      }
      $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
      $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
      $stmt->execute();

      return [
          'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
          'total' => $total,
          'page' => $page,
          'limit' => $limit,
          'total_pages' => max(1, (int)ceil($total / $limit))
      ];
    }

    public function getOfferLetter($id) {
      $sql = "SELECT * FROM offer_letters WHERE id = :id";
      $stmt = $this->conn->prepare($sql);
      $stmt->execute(['id' => $id]);
      return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function upsertOfferLetter($data) {
      if (isset($data['id']) && $data['id'] > 0) {
        $sql = "UPDATE offer_letters SET 
                user_id = :user_id,
                candidate_name = :candidate_name, 
                email = :email, 
                phone = :phone, 
                position = :position, 
                department = :department, 
                monthly_salary = :monthly_salary, 
                joining_date = :joining_date, 
                reporting_manager = :reporting_manager,
                offer_status = :offer_status
                WHERE id = :id";
      } else {
        $sql = "INSERT INTO offer_letters (user_id, candidate_name, email, phone, position, department, monthly_salary, joining_date, reporting_manager, offer_status) 
                VALUES (:user_id, :candidate_name, :email, :phone, :position, :department, :monthly_salary, :joining_date, :reporting_manager, :offer_status)";
      }
      $stmt = $this->conn->prepare($sql);
      $res = $stmt->execute($data);
      
      // Auto-populate user_documents with default layout if missing
      if ($res && !empty($data['user_id'])) {
          try {
              $checkSql = "SELECT COUNT(*) FROM user_documents WHERE user_id = :uid AND document_type = 'offer_letter'";
              $checkStmt = $this->conn->prepare($checkSql);
              $checkStmt->execute(['uid' => $data['user_id']]);
              if ($checkStmt->fetchColumn() == 0) {
                  $defaultHtml = $this->getDefaultOfferLetterHtml($data);
                  $insertDocSql = "INSERT INTO user_documents (user_id, document_type, content) VALUES (:uid, 'offer_letter', :content)";
                  $insertDocStmt = $this->conn->prepare($insertDocSql);
                  $insertDocStmt->execute(['uid' => $data['user_id'], 'content' => $defaultHtml]);
              }
          } catch (Exception $e) {
              // ignore
          }
      }
      return $res;
    }

    public function updateOfferLetterStatus($id, $status) {
      $allowed = ['Draft', 'Accepted', 'Rejected'];
      if (!in_array($status, $allowed, true)) {
          return false;
      }
      $stmt = $this->conn->prepare("UPDATE offer_letters SET offer_status = :status WHERE id = :id");
      return $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public function markOfferLetterEmailed($id, $emailedBy = '') {
      $stmt = $this->conn->prepare("UPDATE offer_letters SET offer_status = 'Sent', emailed_at = NOW(), emailed_by = :emailed_by WHERE id = :id");
      return $stmt->execute(['emailed_by' => $emailedBy, 'id' => $id]);
    }

    public function deleteOfferLetter($id) {
      try {
          $sqlGet = "SELECT user_id FROM offer_letters WHERE id = :id";
          $stmtGet = $this->conn->prepare($sqlGet);
          $stmtGet->execute(['id' => $id]);
          $row = $stmtGet->fetch(PDO::FETCH_ASSOC);
          if ($row && $row['user_id']) {
              $sqlDoc = "DELETE FROM user_documents WHERE user_id = :user_id AND document_type = 'offer_letter'";
              $stmtDoc = $this->conn->prepare($sqlDoc);
              $stmtDoc->execute(['user_id' => $row['user_id']]);
          }
      } catch (Exception $e) {
          // ignore
      }

      $sql = "DELETE FROM offer_letters WHERE id = :id";
      $stmt = $this->conn->prepare($sql);
      return $stmt->execute(['id' => $id]);
    }
    public function updateUserStatus($id, $status) {
      $sql = 'UPDATE accounts SET is_active = :status, deactivated_at = CASE WHEN :status = 0 THEN NOW() ELSE NULL END WHERE id = :id';
      $stmt = $this->conn->prepare($sql);
      return $stmt->execute(['id' => $id, 'status' => $status]);
    }
    public function active_users() {
      // Query to count Active (1) and Inactive (0) users
      $sql = 'SELECT is_active, COUNT(*) AS user_count 
              FROM accounts 
              GROUP BY is_active';
      
      $stmt = $this->conn->prepare($sql);
      $stmt->execute();
      $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
      
      return $result; // Return the result as an array
    }
    public function updateAdvancePay($id, $newAdvancePay) {
      $sql = 'UPDATE payment_table SET advance_pay = :newAdvancePay WHERE id = :id';
      $stmt = $this->conn->prepare($sql);
      $stmt->bindParam(':newAdvancePay', $newAdvancePay);
      $stmt->bindParam(':id', $id);
      $stmt->execute();
    }
    // --- Live Location Tracking ---
    public function createLocationHistoryTable() {
        $sql = "CREATE TABLE IF NOT EXISTS location_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            latitude DECIMAL(10, 8) NOT NULL,
            longitude DECIMAL(11, 8) NOT NULL,
            captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (user_id, captured_at)
        )";
        $this->conn->exec($sql);
    }
    public function recordLocation($user_id, $lat, $lng) {
        $sql = "INSERT INTO location_history (user_id, latitude, longitude) VALUES (:uid, :lat, :lng)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute(['uid' => $user_id, 'lat' => $lat, 'lng' => $lng]);
    }
    // --- Payroll & Payslip Logic ---
    // Create Payroll Table if not exists
    public function createPayrollTable() {
      $sql = "CREATE TABLE IF NOT EXISTS payroll (
          id INT AUTO_INCREMENT PRIMARY KEY,
          employee_id INT NOT NULL,
          employee_name VARCHAR(255) NOT NULL,
          month_year VARCHAR(20) NOT NULL,
          base_salary DECIMAL(10,2) NOT NULL,
          present_days DECIMAL(6,2) NOT NULL,
          total_days INT NOT NULL,
          deductions DECIMAL(10,2) DEFAULT 0.00,
          net_salary DECIMAL(10,2) NOT NULL,
          status VARCHAR(20) DEFAULT 'Processed',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY unique_payroll (employee_id, month_year)
      )";
      $this->conn->exec($sql);
    }
    // Create Deductions Table
    public function createDeductionsTable() {
      $sql = "CREATE TABLE IF NOT EXISTS deductions (
          id INT AUTO_INCREMENT PRIMARY KEY,
          company_id INT DEFAULT 0,
          deduction_name VARCHAR(255) NOT NULL,
          type ENUM('percentage', 'fixed') NOT NULL,
          value DECIMAL(10,2) NOT NULL,
          status TINYINT(1) DEFAULT 1,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )";
      $this->conn->exec($sql);
    }
    // Fetch Active Deductions
    public function getActiveDeductions($company_id = 0) {
      $sql = "SELECT * FROM deductions WHERE status = 1 AND company_id = :cid";
      $stmt = $this->conn->prepare($sql);
      $stmt->execute(['cid' => $company_id]);
      return $stmt->fetchAll();
    }
    // Fetch All Deductions
    public function getAllDeductions($company_id = 0) {
      $sql = "SELECT * FROM deductions WHERE company_id = :cid ORDER BY created_at DESC";
      $stmt = $this->conn->prepare($sql);
      $stmt->execute(['cid' => $company_id]);
      return $stmt->fetchAll();
    }
    // Add Deduction
    public function addDeduction($name, $type, $value, $cid = 0) {
      $sql = "INSERT INTO deductions (deduction_name, type, value, company_id) VALUES (:name, :type, :value, :cid)";
      $stmt = $this->conn->prepare($sql);
      return $stmt->execute(['name' => $name, 'type' => $type, 'value' => $value, 'cid' => $cid]);
    }
    // Delete Deduction
    public function deleteDeduction($id) {
      $sql = "DELETE FROM deductions WHERE id = :id";
      $stmt = $this->conn->prepare($sql);
      return $stmt->execute(['id' => $id]);
    }
    // Toggle Deduction Status
    public function toggleDeduction($id, $status) {
      $sql = "UPDATE deductions SET status = :status WHERE id = :id";
      $stmt = $this->conn->prepare($sql);
      return $stmt->execute(['id' => $id, 'status' => $status]);
    }
    // Insert or Update Payroll Record
    public function upsertPayroll($data) {
      $sql = "INSERT INTO payroll (employee_id, employee_name, month_year, base_salary, present_days, total_days, deductions, net_salary, status)
              VALUES (:eid, :ename, :month, :base, :present, :total, :deductions, :net, :status)
              ON DUPLICATE KEY UPDATE 
              employee_name = VALUES(employee_name),
              base_salary = VALUES(base_salary),
              present_days = VALUES(present_days),
              total_days = VALUES(total_days),
              deductions = VALUES(deductions),
              net_salary = VALUES(net_salary),
              status = VALUES(status)";
      $stmt = $this->conn->prepare($sql);
      return $stmt->execute($data);
    }
    private function ensureUserPayslipsTable() {
      if (self::$userPayslipsTableReady) {
          return;
      }
      self::$userPayslipsTableReady = true;

      try {
          $this->conn->exec("CREATE TABLE IF NOT EXISTS user_payslips (
              id INT AUTO_INCREMENT PRIMARY KEY,
              user_id INT NOT NULL,
              month INT NOT NULL,
              year INT NOT NULL,
              net_pay DECIMAL(10,2) NOT NULL,
              payslip_data LONGTEXT NOT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              UNIQUE KEY user_month_year (user_id, month, year)
          )");
      } catch (Exception $e) {
          // table may already exist with different engine/options
      }
    }
    public function initializeUserPayslipsTable() {
      $this->ensureUserPayslipsTable();
    }

    public function upsertUserPayslip($userId, $month, $year, $netPay, $payslipData, bool $ensureTable = true) {
      if ($ensureTable) {
        $this->ensureUserPayslipsTable();
      }
      $json = is_string($payslipData) ? $payslipData : json_encode($payslipData);
      $sql = "INSERT INTO user_payslips (user_id, month, year, net_pay, payslip_data)
              VALUES (:uid, :month, :year, :net, :data)
              ON DUPLICATE KEY UPDATE
              net_pay = VALUES(net_pay),
              payslip_data = VALUES(payslip_data),
              updated_at = CURRENT_TIMESTAMP";
      $stmt = $this->conn->prepare($sql);
      return $stmt->execute([
          'uid' => $userId,
          'month' => $month,
          'year' => $year,
          'net' => $netPay,
          'data' => $json,
      ]);
    }
    // Get Payroll List by Month
    public function getPayrollByMonth($month) {
      $sql = "SELECT p.*, a.useremail, a.phonenumber, a.employee_id as emp_code, a.user_type as designation 
              FROM payroll p
              LEFT JOIN accounts a ON p.employee_id = a.id
              WHERE p.month_year = :month 
              ORDER BY p.employee_name ASC";
      $stmt = $this->conn->prepare($sql);
      $stmt->execute(['month' => $month]);
      return $stmt->fetchAll();
    }
    public function getUserPayslipJson($userId, $month, $year) {
        $this->ensureUserPayslipsTable();
        $stmt = $this->conn->prepare("SELECT payslip_data FROM user_payslips WHERE user_id = ? AND month = ? AND year = ?");
        $stmt->execute([(int)$userId, (int)$month, (int)$year]);
        $row = $stmt->fetch();
        if (!$row || empty($row['payslip_data'])) {
            return null;
        }
        $decoded = json_decode($row['payslip_data'], true);
        return is_array($decoded) ? $decoded : null;
    }

    private function attachPayslipView(array &$record) {
        require_once __DIR__ . '/payroll_payslip_builder.php';
        [$month, $year] = payroll_parse_month_year((string)($record['month_year'] ?? ''));
        $json = null;
        if ($month > 0 && $year > 0 && !empty($record['employee_id'])) {
            $json = $this->getUserPayslipJson((int)$record['employee_id'], $month, $year);
        }
        $record['payslip_view'] = payroll_resolve_payslip_display($record, $json);
    }

    // Get Single Payroll Record for Payslip
    public function getPayrollRecord($id) {
       $sql = "SELECT p.*, a.useremail, a.phonenumber, a.employee_id as emp_code, a.user_type as designation, a.one_amt, a.two_amt, a.thrid_amt, a.forth_amt, a.fifth_amt, a.sixth_amt 
               FROM payroll p
               LEFT JOIN accounts a ON p.employee_id = a.id
               WHERE p.id = :id";
       $stmt = $this->conn->prepare($sql);
       $stmt->execute(['id' => $id]);
       $record = $stmt->fetch();
       if ($record) {
           $this->attachPayslipView($record);
       }
       return $record;
    }
    // Get count of 'Present' days for an employee in a specific month
    public function getPresentDaysCount($employee_id, $month_year) {
        // Parse month_year (e.g., "Apr 2024")
        $dateObj = DateTime::createFromFormat('M Y', $month_year);
        if (!$dateObj) return 0;
        
        $month = $dateObj->format('m');
        $year = $dateObj->format('Y');
        $sql = "SELECT COUNT(*) as present_count 
                FROM attendance_logs 
                WHERE user_id = :eid 
                AND MONTH(punch_date) = :month 
                AND YEAR(punch_date) = :year 
                AND status IN ('Present', 'Late')";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            'eid' => $employee_id,
            'month' => $month,
            'year' => $year
        ]);
        $result = $stmt->fetch();
        return (int)($result['present_count'] ?? 0);
    }
    private function getPayrollHrSettings(): array
    {
        $settingsKeys = [
            'sunday_is_paid_day',
            'late_payment_percent',
            'saturday_rule',
            'half_day_payment_percent',
            'lwp_payment_percent',
            'holidays_are_paid',
            'late_grace_count',
        ];
        $settingsStmt = $this->conn->prepare(
            'SELECT setting_key, setting_value FROM hr_settings WHERE setting_key IN ('
            . implode(',', array_fill(0, count($settingsKeys), '?')) . ')'
        );
        $settingsStmt->execute($settingsKeys);
        $hrSettings = [];
        while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
            $hrSettings[$row['setting_key']] = $row['setting_value'];
        }
        return $hrSettings;
    }

    private function getPayrollMonthHolidays(string $start_date, string $end_date): array
    {
        $hStmt = $this->conn->prepare(
            'SELECT holiday_date, reason FROM company_holidays
             WHERE holiday_date BETWEEN :start AND :end ORDER BY holiday_date ASC'
        );
        $hStmt->execute(['start' => $start_date, 'end' => $end_date]);
        $holidays = [];
        while ($h = $hStmt->fetch(PDO::FETCH_ASSOC)) {
            $holidays[(string)$h['holiday_date']] = (string)$h['reason'];
        }
        return $holidays;
    }

    /**
     * Shared month context for bulk payroll (settings + holidays loaded once per run).
     *
     * @return array{month:int,year:int,start_date:string,end_date:string,hr_settings:array,holidays:array}|null
     */
    public function buildPayrollRunContext(string $month_year): ?array
    {
        $dateObj = DateTime::createFromFormat('M Y', $month_year);
        if (!$dateObj) {
            return null;
        }

        $month = (int) $dateObj->format('m');
        $year = (int) $dateObj->format('Y');
        $start_date = $year . '-' . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . '-01';
        $end_date = date('Y-m-t', strtotime($start_date));

        return [
            'month' => $month,
            'year' => $year,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'hr_settings' => $this->getPayrollHrSettings(),
            'holidays' => $this->getPayrollMonthHolidays($start_date, $end_date),
        ];
    }

    /**
     * Batch-load attendance + leave maps for bulk payroll (2 queries instead of 2×N).
     *
     * @return array{records_by_user: array<int, array<string, string>>, leaves_by_user: array<int, array<string, int>>}
     */
    public function prefetchPayrollAttendanceMaps(array $employeeIds, array $payrollContext): array
    {
        $recordsByUser = [];
        $leavesByUser = [];
        foreach ($employeeIds as $id) {
            $recordsByUser[(int) $id] = [];
            $leavesByUser[(int) $id] = [];
        }
        if ($employeeIds === []) {
            return ['records_by_user' => $recordsByUser, 'leaves_by_user' => $leavesByUser];
        }

        $start_date = $payrollContext['start_date'];
        $end_date = $payrollContext['end_date'];
        $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));

        $sql = "SELECT user_id, status, punch_date FROM attendance_logs
                WHERE user_id IN ($placeholders) AND punch_date BETWEEN ? AND ?";
        $stmt = $this->conn->prepare($sql);
        $params = array_merge(array_map('intval', $employeeIds), [$start_date, $end_date]);
        $stmt->execute($params);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $uid = (int) $row['user_id'];
            $key = payroll_normalize_punch_date((string) $row['punch_date']);
            $recordsByUser[$uid][$key] = strtolower((string) $row['status']);
        }

        $leaveSql = "
            SELECT lr.user_id, lr.start_date, lr.end_date, COALESCE(t.is_paid, 1) AS is_paid
            FROM leave_requests lr
            JOIN leave_types t ON lr.leave_type_id = t.id
            WHERE lr.user_id IN ($placeholders) AND lr.status = 'Approved'
            AND (lr.start_date <= ? AND lr.end_date >= ?)
        ";
        $leaveStmt = $this->conn->prepare($leaveSql);
        $leaveParams = array_merge(array_map('intval', $employeeIds), [$end_date, $start_date]);
        $leaveStmt->execute($leaveParams);
        while ($row = $leaveStmt->fetch(PDO::FETCH_ASSOC)) {
            $uid = (int) $row['user_id'];
            $s_date = new DateTime(max($start_date, $row['start_date']));
            $e_date = new DateTime(min($end_date, $row['end_date']));
            while ($s_date <= $e_date) {
                $leavesByUser[$uid][$s_date->format('Y-m-d')] = (int) $row['is_paid'];
                $s_date->modify('+1 day');
            }
        }

        return ['records_by_user' => $recordsByUser, 'leaves_by_user' => $leavesByUser];
    }

    public function getPayrollAttendanceMetrics($employee_id, $month_year, ?array $payrollContext = null, ?array $prefetchMaps = null) {
        if ($payrollContext) {
            $month = (int) $payrollContext['month'];
            $year = (int) $payrollContext['year'];
            $start_date = $payrollContext['start_date'];
            $end_date = $payrollContext['end_date'];
            $hrSettings = $payrollContext['hr_settings'];
            $holidays = $payrollContext['holidays'];
        } else {
            $dateObj = DateTime::createFromFormat('M Y', $month_year);
            if (!$dateObj) {
                return [
                    'days_in_month' => 0, 'sunday_count' => 0, 'working_days' => 0,
                    'sundays_are_paid' => true, 'pay_denominator' => 0,
                    'paid_days' => 0, 'lops' => 0,
                    'present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0,
                ];
            }

            $month = (int) $dateObj->format('m');
            $year = (int) $dateObj->format('Y');
            $start_date = $year . '-' . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . '-01';
            $end_date = date('Y-m-t', strtotime($start_date));
            $hrSettings = $this->getPayrollHrSettings();
            $holidays = $this->getPayrollMonthHolidays($start_date, $end_date);
        }

        $employee_id = (int) $employee_id;
        if ($prefetchMaps) {
            $records = $prefetchMaps['records_by_user'][$employee_id] ?? [];
            $approved_leaves = $prefetchMaps['leaves_by_user'][$employee_id] ?? [];
        } else {
            $sql = 'SELECT status, punch_date FROM attendance_logs WHERE user_id = :eid AND (punch_date BETWEEN :start AND :end)';
            $stmt = $this->conn->prepare($sql);
            $stmt->execute(['eid' => $employee_id, 'start' => $start_date, 'end' => $end_date]);

            $records = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $key = payroll_normalize_punch_date((string) $row['punch_date']);
                $records[$key] = strtolower($row['status']);
            }

            $leaveStmt = $this->conn->prepare('
                SELECT lr.start_date, lr.end_date, COALESCE(t.is_paid, 1) as is_paid
                FROM leave_requests lr
                JOIN leave_types t ON lr.leave_type_id = t.id
                WHERE lr.user_id = :eid AND lr.status = \'Approved\'
                AND (lr.start_date <= :end_date AND lr.end_date >= :start_date)
            ');
            $leaveStmt->execute([
                'eid' => $employee_id,
                'start_date' => $start_date,
                'end_date' => $end_date,
            ]);

            $approved_leaves = [];
            while ($row = $leaveStmt->fetch(PDO::FETCH_ASSOC)) {
                $s_date = new DateTime(max($start_date, $row['start_date']));
                $e_date = new DateTime(min($end_date, $row['end_date']));
                while ($s_date <= $e_date) {
                    $approved_leaves[$s_date->format('Y-m-d')] = (int) $row['is_paid'];
                    $s_date->modify('+1 day');
                }
            }
        }

        return payroll_calculate_attendance_metrics($month, $year, $records, $approved_leaves, $holidays, $hrSettings);
    }

    public function getPaidDaysCount($employee_id, $month_year) {
        $metrics = $this->getPayrollAttendanceMetrics($employee_id, $month_year);
        return $metrics['paid_days'];
    }
    /** Slim active accounts for asset assignment dropdowns (no passwords). */
    public function getActiveAccountsForAssignment(): array
    {
        $sql = 'SELECT id, username, employee_id, tablename, user_type
                FROM accounts WHERE is_active = 1 ORDER BY username ASC';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Asset registry counts by status (no full-table load). */
    public function getAssetSummaryCounts(): array
    {
        $sql = "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN LOWER(status) = 'assigned' THEN 1 ELSE 0 END) AS assigned,
                    SUM(CASE WHEN LOWER(status) = 'available' THEN 1 ELSE 0 END) AS available
                FROM assets";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'assigned' => (int) ($row['assigned'] ?? 0),
            'available' => (int) ($row['available'] ?? 0),
        ];
    }

    // --- Asset Management Logic (Group D) ---
    // Fetch all assets from registry
    public function getAllAssets(?int $limit = null, ?int $offset = null, ?string $search = null, ?string $status = null, ?string $type = null) {
        $sql = "SELECT * FROM assets WHERE 1=1";
        $params = [];
        if ($search) {
            $sql .= " AND (asset_name LIKE :q OR asset_type LIKE :q OR serial_number LIKE :q OR CAST(id AS CHAR) LIKE :q)";
            $params['q'] = '%' . $search . '%';
        }
        if ($status) {
            $sql .= ' AND LOWER(status) = LOWER(:status)';
            $params['status'] = $status;
        }
        if ($type) {
            $sql .= ' AND LOWER(asset_type) = LOWER(:atype)';
            $params['atype'] = $type;
        }
        $sql .= ' ORDER BY id DESC';
        if ($limit !== null) {
            $sql .= ' LIMIT :lim OFFSET :off';
        }
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        if ($limit !== null) {
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset ?? 0, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getAvailableAssetsForDropdown(): array
    {
        $sql = "SELECT id, asset_name, asset_type, serial_number
                FROM assets WHERE LOWER(status) = 'available'
                ORDER BY asset_name ASC LIMIT 500";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countAssets(?string $search = null, ?string $status = null, ?string $type = null): int
    {
        $sql = 'SELECT COUNT(*) FROM assets WHERE 1=1';
        $params = [];
        if ($search) {
            $sql .= " AND (asset_name LIKE :q OR asset_type LIKE :q OR serial_number LIKE :q OR CAST(id AS CHAR) LIKE :q)";
            $params['q'] = '%' . $search . '%';
        }
        if ($status) {
            $sql .= ' AND LOWER(status) = LOWER(:status)';
            $params['status'] = $status;
        }
        if ($type) {
            $sql .= ' AND LOWER(asset_type) = LOWER(:atype)';
            $params['atype'] = $type;
        }
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
    // Fetch active assignments (Who has what)
    public function getActiveAssignments() {
        $sql = "SELECT aa.*, a.asset_name, a.asset_type, a.serial_number,
                       acc.id AS employee_user_id,
                       acc.username as employee_name, acc.employee_id as emp_code
                FROM asset_assignments aa
                JOIN assets a ON aa.asset_id = a.id
                JOIN accounts acc ON aa.employee_id = acc.id
                WHERE aa.returned_date IS NULL";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // Fetch active assignments for a specific user
    public function getActiveAssignmentsByUser($user_id) {
        $sql = "SELECT aa.*, a.asset_name, a.asset_type, a.serial_number 
                FROM asset_assignments aa
                JOIN assets a ON aa.asset_id = a.id
                WHERE aa.employee_id = :uid AND aa.returned_date IS NULL";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['uid' => $user_id]);
        return $stmt->fetchAll();
    }
    // Add asset to registry
    public function addAsset($data) {
        $sql = "INSERT INTO assets (asset_name, asset_type, serial_number, status) 
                VALUES (:name, :type, :sn, 'Available')";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($data);
    }
    // Assign an asset
    public function assignAsset($asset_id, $emp_id, $date, $notes) {
        $this->conn->beginTransaction();
        try {
            // 1. Create assignment record
            $sql = "INSERT INTO asset_assignments (asset_id, employee_id, assigned_date, notes) 
                    VALUES (:aid, :eid, :adate, :notes)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                'aid' => $asset_id,
                'eid' => $emp_id,
                'adate' => $date,
                'notes' => $notes
            ]);
            // 2. Mark asset as Assigned
            $sqlUpd = "UPDATE assets SET status = 'Assigned' WHERE id = :aid";
            $stmtUpd = $this->conn->prepare($sqlUpd);
            $stmtUpd->execute(['aid' => $asset_id]);
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }
    // Return an asset
    public function returnAsset($assignment_id, $date) {
        $this->conn->beginTransaction();
        try {
            // 1. Get the asset ID first
            $sqlId = "SELECT asset_id FROM asset_assignments WHERE id = :id";
            $stmtId = $this->conn->prepare($sqlId);
            $stmtId->execute(['id' => $assignment_id]);
            $res = $stmtId->fetch();
            $asset_id = $res['asset_id'];
            // 2. Mark assignment as closed
            $sql = "UPDATE asset_assignments SET returned_date = :rdate WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                'id' => $assignment_id,
                'rdate' => $date
            ]);
            // 3. Mark asset as Available
            $sqlUpd = "UPDATE assets SET status = 'Available' WHERE id = :aid";
            $stmtUpd = $this->conn->prepare($sqlUpd);
            $stmtUpd->execute(['aid' => $asset_id]);
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }
    /** Active employees for payslip filter dropdown (slim, no passwords). */
    public function getPayslipEmployeesDropdown() {
        $sql = "SELECT id, username, employee_id
                FROM accounts
                WHERE is_active = 1
                ORDER BY username ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'id' => (int)$row['id'],
                'username' => $row['username'],
                'emp_code' => $row['employee_id'],
            ];
        }
        return $data;
    }

    // Search Payroll Records with filters (list columns only; detail on fetch_payslip_data).
    public function searchPayslips($month = null, $eid = null, ?int $limit = null, ?int $offset = null) {
        $sql = "SELECT p.id, p.employee_id, p.employee_name, p.month_year, p.net_salary, p.status
                FROM payroll p
                WHERE 1=1";
        $params = [];
        if ($month) { $sql .= " AND p.month_year = :month"; $params['month'] = $month; }
        if ($eid) { $sql .= " AND p.employee_id = :eid"; $params['eid'] = $eid; }
        $sql .= " ORDER BY p.id DESC";
        if ($limit !== null) {
            $sql .= ' LIMIT :lim OFFSET :off';
        }
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        if ($limit !== null) {
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset ?? 0, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countPayslips($month = null, $eid = null): int
    {
        $sql = 'SELECT COUNT(*) FROM payroll p WHERE 1=1';
        $params = [];
        if ($month) { $sql .= ' AND p.month_year = :month'; $params['month'] = $month; }
        if ($eid) { $sql .= ' AND p.employee_id = :eid'; $params['eid'] = $eid; }
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
    // Fetch Salary Summary statistics
    public function getSalarySummary($month = null, $eid = null) {
        $sql = "SELECT SUM(net_salary) as total_payout, AVG(net_salary) as avg_salary, COUNT(*) as total_emps 
                FROM payroll WHERE 1=1";
        $params = [];
        if ($month) { $sql .= " AND month_year = :month"; $params['month'] = $month; }
        if ($eid) { $sql .= " AND employee_id = :eid"; $params['eid'] = $eid; }
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    // --- FNF (Full and Final) Settlement Logic ---
    public function createFnfTable() {
        $sql = "CREATE TABLE IF NOT EXISTS fnf_settlements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            last_working_day DATE NOT NULL,
            unpaid_salary DECIMAL(10,2) DEFAULT 0.00,
            leave_encashment DECIMAL(10,2) DEFAULT 0.00,
            bonus_incentives DECIMAL(10,2) DEFAULT 0.00,
            deductions DECIMAL(10,2) DEFAULT 0.00,
            net_settlement DECIMAL(10,2) NOT NULL,
            status ENUM('Pending', 'Settled') DEFAULT 'Pending',
            assets_returned TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_fnf (user_id)
        )";
        $this->conn->exec($sql);
    }

    public function getFnfSettlement($user_id) {
        $sql = "SELECT * FROM fnf_settlements WHERE user_id = :uid";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['uid' => $user_id]);
        return $stmt->fetch();
    }

    public function upsertFnfSettlement($data) {
        $sql = "INSERT INTO fnf_settlements (user_id, last_working_day, unpaid_salary, leave_encashment, bonus_incentives, deductions, net_settlement, status, assets_returned)
                VALUES (:uid, :lwd, :salary, :leaves, :bonus, :deductions, :net, :status, :assets)
                ON DUPLICATE KEY UPDATE 
                last_working_day = VALUES(last_working_day),
                unpaid_salary = VALUES(unpaid_salary),
                leave_encashment = VALUES(leave_encashment),
                bonus_incentives = VALUES(bonus_incentives),
                deductions = VALUES(deductions),
                net_settlement = VALUES(net_settlement),
                status = VALUES(status),
                assets_returned = VALUES(assets_returned)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($data);
    }

    public function checkPendingAssets($user_id) {
        $sql = "SELECT COUNT(*) as pending_count FROM asset_assignments WHERE employee_id = :uid AND returned_date IS NULL";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['uid' => $user_id]);
        $res = $stmt->fetch();
        return (int)($res['pending_count'] ?? 0);
    }
  }
?>