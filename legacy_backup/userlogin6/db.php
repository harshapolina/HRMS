<?php
session_start();
require_once 'config.php';

// acessing the variables from accounts table according to the user login
$tablename = $_SESSION['tablename'] ?? '';
$salary = $_SESSION['salary'] ?? '';
$frist = $_SESSION['one_amt'] ?? '';
$secound = $_SESSION['two_amt'] ?? '';
$third = $_SESSION['thrid_amt'] ?? '';
$forth = $_SESSION['forth_amt'] ?? '';
$fifth = $_SESSION['fifth_amt'] ?? '';
$sixth = $_SESSION['sixth_amt'] ?? '';
$user_type = $_SESSION['user_type'] ?? '';
$Project_type = $_SESSION['project_type'] ?? '';
// $assign_person = $_SESSION['assign_user'];

class Database extends Config
{
    // Insert User Into Database'

    public function buildAssignPersonListUp()
    {
        global $tablename;

        $assign_person = '';

        if (empty($tablename)) {
            return '';
        }

        $chain = [];
        $visited = [];

        $sql = "SELECT assign_user FROM accounts WHERE tablename = :tablename LIMIT 1";
        $stmt = $this->conn->prepare($sql);

        $current = $tablename;

        while (true) {
            $stmt->execute([':tablename' => $current]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || empty($row['assign_user'])) {
                break;
            }

            $parent = $row['assign_user'];

            if ($parent === $current || isset($visited[$parent])) {
                break;
            }

            $chain[] = $parent;
            $visited[$parent] = true;
            $current = $parent;
        }

        $assign_person = implode(',', $chain);
        return $assign_person;
    }

    public function insert($bdate, $bmonth, $developer, $bproject, $cname, $cnumber,
        $cemail, $tproject, $unitno, $psize, $cagreement, $ccashback, $crevenue, $cccashback,
        $ccrevenue, $cstatus, $brecived, $msalary, $filePathStored, $leadsource, $bremarks, $deduct_agreement_value, $city)
    {
        global $tablename;

        // Build the upward manager chain for assign_user
        $assign_person = $this->buildAssignPersonListUp();
        $assign_person = $assign_person === '' ? '' : $assign_person;

        // Check for duplicates based on unit_no in BOTH admintable and pending approvals
        $check_sql = "SELECT COUNT(*) FROM admintable WHERE unit_no = :unitno";
        $check_stmt = $this->conn->prepare($check_sql);
        $check_stmt->execute(['unitno' => $unitno]);
        if ($check_stmt->fetchColumn() > 0) {
            return 'duplicate';
        }
        $check_sql2 = "SELECT COUNT(*) FROM booking_approvals WHERE unit_no = :unitno AND approval_status NOT IN ('rejected')";
        $check_stmt2 = $this->conn->prepare($check_sql2);
        $check_stmt2->execute(['unitno' => $unitno]);
        if ($check_stmt2->fetchColumn() > 0) {
            return 'duplicate_pending';
        }

        // ==============================================================
        // INSERT INTO booking_approvals (staging/pending table)
        // Booking will only move to admintable after approval.
        // ==============================================================
        $sql = "INSERT INTO booking_approvals (
                  booking_date, booking_month, builder, project, customer_name, contact_number, email_id,
                  project_type, unit_no, size, agreement_value, cashback, revenue, ccashback, crevenue,
                  astatus, recived_amt, msalary, source_table, assign_user,
                  document_path, source_lead, remarks, deduct_agreement, city,
                  approval_status, submitted_at
              ) VALUES (
                  :bdate, :bmonth, :developer, :bproject, :cname, :cnumber, :cemail, :tproject, :unitno,
                  :psize, :cagreement, :ccashback, :crevenue, :cccashback, :ccrevenue, :cstatus,
                  :brecived, :msalary, :tablename, :assignuser,
                  :document_path, :leadsource, :bremarks, :deduct_agreement_value, :city,
                  'pending', NOW()
              )";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            'bdate'                 => $bdate,
            'bmonth'                => $bmonth,
            'developer'             => $developer,
            'bproject'              => $bproject,
            'cname'                 => $cname,
            'cnumber'               => $cnumber,
            'cemail'                => $cemail,
            'tproject'              => $tproject,
            'unitno'                => $unitno,
            'psize'                 => $psize,
            'cagreement'            => $cagreement,
            'ccashback'             => $ccashback,
            'crevenue'              => $crevenue,
            'cccashback'            => $cccashback,
            'ccrevenue'             => $ccrevenue,
            'cstatus'               => $cstatus,
            'brecived'              => $brecived,
            'msalary'               => $msalary,
            'tablename'             => $tablename,
            'assignuser'            => $assign_person,
            'document_path'         => $filePathStored,
            'leadsource'            => $leadsource,
            'bremarks'              => $bremarks,
            'deduct_agreement_value'=> $deduct_agreement_value,
            'city'                  => $city
        ]);

        return 'pending_approval';
    }

    // ============================================================
    // APPROVAL SYSTEM METHODS
    // ============================================================

    /**
     * Promote a booking from booking_approvals into admintable + backuptable.
     * Called internally after approval conditions are met.
     */
    public function promoteBookingToAdmintable($approvalId)
    {
        // Fetch the pending booking row
        $fetchSql = "SELECT * FROM booking_approvals WHERE id = :id LIMIT 1";
        $fetchStmt = $this->conn->prepare($fetchSql);
        $fetchStmt->execute(['id' => $approvalId]);
        $row = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        // Insert into admintable (no perm columns — access controlled by APPROVAL_MANAGER)
        $sql = "INSERT INTO admintable (
                    booking_date, booking_month, builder, project, customer_name, contact_number, email_id,
                    project_type, unit_no, size, agreement_value, cashback, revenue, ccashback, crevenue,
                    astatus, recived_amt, msalary, update_date_column, source_table, assign_user,
                    document_path, source_lead, remarks, deduct_agreement, city
                ) VALUES (
                    :bdate, :bmonth, :builder, :project, :cname, :cnumber, :cemail, :tproject, :unitno,
                    :psize, :cagreement, :ccashback, :crevenue, :cccashback, :ccrevenue, :cstatus,
                    :brecived, :msalary, NOW(), :source_table, :assign_user,
                    :document_path, :source_lead, :remarks, :deduct_agreement, :city
                )";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            'bdate'           => $row['booking_date']     ?? null,
            'bmonth'          => $row['booking_month']    ?? null,
            'builder'         => $row['builder']          ?? null,
            'project'         => $row['project']          ?? null,
            'cname'           => $row['customer_name']    ?? null,
            'cnumber'         => $row['contact_number']   ?? null,
            'cemail'          => $row['email_id']         ?? null,
            'tproject'        => $row['project_type']     ?? null,
            'unitno'          => $row['unit_no']          ?? null,
            'psize'           => $row['size']             ?? null,
            'cagreement'      => $row['agreement_value']  ?? null,
            'ccashback'       => $row['cashback']         ?? null,
            'crevenue'        => $row['revenue']          ?? null,
            'cccashback'      => $row['ccashback']        ?? null,
            'ccrevenue'       => $row['crevenue']         ?? null,
            'cstatus'         => $row['astatus']          ?? null,
            'brecived'        => $row['recived_amt']      ?? 0,
            'msalary'         => $row['msalary']          ?? null,
            'source_table'    => $row['source_table']     ?? null,
            'assign_user'     => $row['assign_user']      ?? null,
            'document_path'   => $row['document_path']   ?? null,
            'source_lead'     => $row['source_lead']     ?? null,
            'remarks'         => $row['remarks']          ?? null,
            'deduct_agreement'=> $row['deduct_agreement'] ?? null,
            'city'            => $row['city']             ?? null,
        ]);

        // Insert into backuptable
        $sqlBackup = "INSERT INTO backuptable (
                        booking_date, booking_month, builder, project, customer_name, contact_number, email_id,
                        project_type, unit_no, size, agreement_value, cashback, revenue, ccashback, crevenue,
                        astatus, recived_amt, msalary, update_date_column, source_table, assign_user
                    ) VALUES (
                        :bdate, :bmonth, :builder, :project, :cname, :cnumber, :cemail, :tproject, :unitno,
                        :psize, :cagreement, :ccashback, :crevenue, :cccashback, :ccrevenue, :cstatus,
                        :brecived, :msalary, NOW(), :source_table, :assign_user
                    )";
        $stmtBackup = $this->conn->prepare($sqlBackup);
        $stmtBackup->execute([
            'bdate'        => $row['booking_date'],
            'bmonth'       => $row['booking_month'],
            'builder'      => $row['builder'],
            'project'      => $row['project'],
            'cname'        => $row['customer_name'],
            'cnumber'      => $row['contact_number'],
            'cemail'       => $row['email_id'],
            'tproject'     => $row['project_type'],
            'unitno'       => $row['unit_no'],
            'psize'        => $row['size'],
            'cagreement'   => $row['agreement_value'],
            'ccashback'    => $row['cashback'],
            'crevenue'     => $row['revenue'],
            'cccashback'   => $row['ccashback'],
            'ccrevenue'    => $row['crevenue'],
            'cstatus'      => $row['astatus'],
            'brecived'     => $row['recived_amt'],
            'msalary'      => $row['msalary'],
            'source_table' => $row['source_table'],
            'assign_user'  => $row['assign_user'],
        ]);

        // DELETE approved row from booking_approvals (on approve = remove from queue)
        $delSql = "DELETE FROM booking_approvals WHERE id = :id";
        $delStmt = $this->conn->prepare($delSql);
        $delStmt->execute(['id' => $approvalId]);

        return true;
    }

    /**
     * Approve a pending booking.
     * approverType: 'user' (manager) or 'superadmin'
     * Option B: whichever acts first promotes the booking immediately.
     */
    public function approveBooking($approvalId, $approverTablename)
    {
        // Fetch current status
        $fetchSql = "SELECT approval_status FROM booking_approvals WHERE id = :id LIMIT 1";
        $fetchStmt = $this->conn->prepare($fetchSql);
        $fetchStmt->execute(['id' => $approvalId]);
        $row = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || $row['approval_status'] === 'approved' || $row['approval_status'] === 'rejected') {
            return ['success' => false, 'message' => 'Booking already processed or not found.'];
        }

        // Promote: INSERT into admintable + backuptable, then DELETE from booking_approvals
        $promoted = $this->promoteBookingToAdmintable($approvalId);

        return ['success' => true, 'promoted' => $promoted, 'message' => 'Booking approved and moved to admintable.'];
    }

    /**
     * Reject a pending booking.
     */
    public function rejectBooking($approvalId, $rejectorTablename, $reason = '')
    {
        $fetchSql = "SELECT approval_status FROM booking_approvals WHERE id = :id LIMIT 1";
        $fetchStmt = $this->conn->prepare($fetchSql);
        $fetchStmt->execute(['id' => $approvalId]);
        $row = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || $row['approval_status'] === 'approved' || $row['approval_status'] === 'rejected') {
            return ['success' => false, 'message' => 'Booking already processed or not found.'];
        }

        $sql = "UPDATE booking_approvals
                SET approval_status = 'rejected',
                    rejected_by = :rejector,
                    rejected_at = NOW(),
                    rejection_reason = :reason
                WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['rejector' => $rejectorTablename, 'id' => $approvalId, 'reason' => $reason]);

        return ['success' => true, 'message' => 'Booking has been rejected.'];
    }


    /**
     * Get bookings submitted BY a specific user (for the user's own read-only view).
     */
    public function getUserSubmittedApprovals($userTablename)
    {
        $sql = "SELECT ba.*
                FROM booking_approvals ba
                WHERE ba.source_table = :src
                ORDER BY ba.submitted_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['src' => $userTablename]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get ALL pending bookings for superadmin (all statuses except approved).
     */
    public function getPendingApprovalsForSuperadmin()
    {
        $sql = "SELECT ba.*,
                       COALESCE(a.username, ba.source_table) AS submitter_name
                FROM booking_approvals ba
                LEFT JOIN accounts a ON a.tablename = ba.source_table
                WHERE ba.approval_status IN ('pending', 'user_approved', 'rejected')
                ORDER BY FIELD(ba.approval_status, 'pending', 'user_approved', 'rejected'),
                         ba.submitted_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }



    // Fetch All Data From Database
    // public function read() {
    //   global $tablename, $user_type;
    //   if ($user_type === 'manager') {
    //       // If the user is a manager, fetch data based on the assign_user column
    //       $sql = "SELECT * FROM admintable WHERE assign_user = :tablename ORDER BY id DESC";
    //       $stmt = $this->conn->prepare($sql);
    //       $stmt->execute(['tablename' => $tablename]);
    //   } else {
    //       // If the user is a normal user, fetch data based on the source_table column
    //       $sql = "SELECT * FROM admintable WHERE source_table = :tablename ORDER BY id DESC";
    //       $stmt = $this->conn->prepare($sql);
    //       $stmt->execute(['tablename' => $tablename]);
    //   }
    //   $result = $stmt->fetchAll();
    //   return $result;
    // }
    public function read() {
    global $tablename, $user_type;

    // Toggle debug output (false for production)
    $DEBUG = false;

    // normalize inputs
    $tablename = isset($tablename) ? trim((string)$tablename) : '';
    $user_type = isset($user_type) ? strtolower(trim((string)$user_type)) : '';

    if ($DEBUG) {
        echo "<b>[DEBUG]</b> read() called with user_type='{$user_type}', tablename='{$tablename}'<br>";
        error_log("read() start - user_type={$user_type}, tablename={$tablename}");
    }

    // guard
    if ($tablename === '') {
        if ($DEBUG)
            echo "<b>[DEBUG]</b> Empty tablename - returning []<br>";
        return [];
    }

    // Normalize tablename
    $normalized = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', trim($tablename));
    $ltab = strtolower($normalized);

    if ($DEBUG) {
        echo "<b>[DEBUG]</b> Normalized tablename: '{$normalized}'<br>";
    }

    try {

        $dateCondition = "1=1";

        if ($user_type === 'user') {

            $sql = "
                SELECT *
                FROM admintable
                WHERE {$dateCondition}
                  AND source_table = :tablename
                ORDER BY id DESC
            ";

            $params = ['tablename' => $normalized];

        } else {

            $sql = "
                SELECT *
                FROM admintable
                WHERE {$dateCondition}
                  AND (
                        source_table = :tablename
                        OR FIND_IN_SET(
                              :ltab,
                              LOWER(
                                  REPLACE(
                                      REPLACE(
                                          REPLACE(
                                              REPLACE(
                                                  REPLACE(assign_user, CHAR(160), ''),
                                              CHAR(9), ''),
                                          ' ', ''),
                                      ';', ','),
                                  '|', ',')
                              )
                           ) > 0
                      )
                ORDER BY id DESC
            ";

            $params = [
                'tablename' => $normalized,
                'ltab' => $ltab
            ];
        }

        if ($DEBUG) {
            echo "<pre>{$sql}</pre>";
        }

        $stmt = $this->conn->prepare($sql);

        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
        }

        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($DEBUG) {
            echo "Rows: " . count($rows);
        }

        return is_array($rows) ? $rows : [];

    }
    catch (PDOException $e) {

        error_log("read() error: " . $e->getMessage());

        if ($DEBUG) {
            echo $e->getMessage();
        }

        return [];
    }
}


    // Fetch the Most Recent Updated Row from the Database
    public function recent($month)
    {
        global $tablename;
        $sql = "SELECT id, recived_amt
      FROM admintable
      WHERE recived_amt != 0 AND booking_month = :month AND source_table = :tablename
      ORDER BY update_date_column DESC
      LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':month', $month);
        $stmt->bindParam(':tablename', $tablename);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result;
    }

    // Fetch the Most Recent Updated Row from the Database for mandate project
    public function recentCashback($month)
    {
        global $tablename;
        $sql = "SELECT id, ccashback
      FROM admintable
      WHERE booking_month = :month AND source_table = :tablename
      ORDER BY update_date_column DESC
      LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':month', $month);
        $stmt->bindParam(':tablename', $tablename);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result;
    }


    // Store all send amount in recent amount send_amt column 
    public function updateAmt($co2, $id)
    {
        global $tablename;
        $sql = "UPDATE admintable SET send_amt = :co2 WHERE id = :id AND source_table = :tablename";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':co2', $co2, PDO::PARAM_INT); // Assuming the send_amt column is of type int
        $stmt->bindValue(':id', $id, PDO::PARAM_INT); // Assuming you have an 'id' column
        $stmt->bindValue(':tablename', $tablename);
        $stmt->execute();
        $result = $stmt->rowCount(); // Check the number of affected rows
        return $result;
    }

    // Here we are storing the value of all the amount which has to be pay for the sales team
    public function insert_pay_amount($temp, $sourceTableName, $bookingMonth)
    {
        $sql = "UPDATE admintable
              SET getamount = :temp
              WHERE booking_month = :bookingMonth
              AND source_table = :sourceTableName LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':temp', $temp, PDO::PARAM_INT);
        $stmt->bindValue(':sourceTableName', $sourceTableName, PDO::PARAM_STR);
        $stmt->bindValue(':bookingMonth', $bookingMonth, PDO::PARAM_STR);
        $stmt->execute();
    }

    // Get the sum of releaseamount values from column send_amt and getting the total of group row
    public function getTotalSendAmt($month)
    {
        global $tablename;
        $sql = "SELECT SUM(send_amt) AS total FROM admintable WHERE booking_month = :month AND source_table = :tablename";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':month', $month);
        $stmt->bindValue(':tablename', $tablename);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }

    // geeting current salary for the sales team member
    // $sql = "SELECT msalary FROM $tablename WHERE booking_month = :month ORDER BY update_date_column DESC LIMIT 1";
    public function currentSalary($month)
    {
        global $tablename;
        $sql = "SELECT msalary FROM admintable WHERE booking_month = :month AND source_table = :tablename ORDER BY id DESC LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':month', $month);
        $stmt->bindValue(':tablename', $tablename);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['msalary'];
    }

    // Fetch Single User From Database
    // Mirrors superadmin: if row exists in updaterowtable, return that override; else return admintable row
    public function readOne($id){
        // Check if the row has an override in updaterowtable
        $sqlCheck = 'SELECT COUNT(*) FROM updaterowtable WHERE id = :id';
        $stmtCheck = $this->conn->prepare($sqlCheck);
        $stmtCheck->execute(['id' => $id]);
        $rowExistsInOverride = $stmtCheck->fetchColumn() > 0;

        if ($rowExistsInOverride) {
            $sql = 'SELECT * FROM updaterowtable WHERE id = :id';
        }
        else {
            $sql = 'SELECT * FROM admintable WHERE id = :id';
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Update Single User
    public function update(
        $id,
        $bdate,
        $bmonth,
        $developer,
        $bproject,
        $cname,
        $cnumber,
        $cemail,
        $tproject,
        $unitno,
        $psize,
        $cagreement,
        $ccashback,
        $crevenue,
        $cccashback,
        $ccrevenue,
        $cstatus,
        $brecived,
        $invoice,
        $updateInUserTable,
        $updateInvoice,
        $tablename,
        $cashbackverify,
        $filePathStored = null
    ) {
        // global $tablename; // passed as parameter now, or we can use global $tablename inside, but signature takes it

        error_log('DB->update() called with: ID=' . $id . ', Status=' . $cstatus . ', updateInUserTable=' . $updateInUserTable . ', Invoice=' . $invoice);

        $params = [
            'bdate' => $bdate,
            'bmonth' => $bmonth,
            'developer' => $developer,
            'bproject' => $bproject,
            'cname' => $cname,
            'cnumber' => $cnumber,
            'cemail' => $cemail,
            'tproject' => $tproject,
            'unitno' => $unitno,
            'psize' => $psize,
            'cagreement' => $cagreement,
            'ccashback' => $ccashback,
            'crevenue' => $crevenue,
            'cccashback' => $cccashback,
            'ccrevenue' => $ccrevenue,
            'cstatus' => $cstatus,
            'brecived' => $brecived,
            'invoice' => $invoice,
            'updateInvoice' => $updateInvoice,
            'cashbackverify' => $cashbackverify,
            'source_table' => $tablename,
            'id' => $id,
        ];


        if ($updateInUserTable) {
            // ── "Update User" is CHECKED: update admintable directly ──────────
            $sql = "UPDATE admintable SET
                booking_date    = :bdate,
                booking_month   = :bmonth,
                builder         = :developer,
                project         = :bproject,
                customer_name   = :cname,
                contact_number  = :cnumber,
                email_id        = :cemail,
                project_type    = :tproject,
                unit_no         = :unitno,
                size            = :psize,
                agreement_value = :cagreement,
                cashback        = :ccashback,
                revenue         = :crevenue,
                ccashback       = :cccashback,
                crevenue        = :ccrevenue,
                astatus         = :cstatus,
                recived_amt     = :brecived,
                invoice_raise   = :invoice,
                source_table    = :source_table,
                update_in_invoice_table = :updateInvoice,
                cashbackverify  = :cashbackverify,
                update_in_user_table = 1
            WHERE id = :id";

            $stmt = $this->conn->prepare($sql);
            $executeResult = $stmt->execute($params);
            $rowCount = $stmt->rowCount();

            // Remove any override row so superadmin sees the updated admintable version
            $delSql = 'DELETE FROM updaterowtable WHERE id = :id';
            $delStmt = $this->conn->prepare($delSql);
            $delStmt->execute(['id' => $id]);

            error_log('DB->update() admintable execute: ' . ($executeResult ? 'TRUE' : 'FALSE') . ', rows affected: ' . $rowCount);
            return $executeResult; // success as long as SQL ran without error

        }
        else {
            // ── "Update User" is UNCHECKED: save to updaterowtable (override) ─
            $sqlInsert = 'INSERT INTO updaterowtable (
                id, booking_date, booking_month, builder, project,
                customer_name, contact_number, email_id, project_type,
                unit_no, size, agreement_value, cashback, revenue,
                ccashback, crevenue, astatus, recived_amt, invoice_raise,
                source_table, update_in_invoice_table, cashbackverify
            ) VALUES (
                :id, :bdate, :bmonth, :developer, :bproject,
                :cname, :cnumber, :cemail, :tproject,
                :unitno, :psize, :cagreement, :ccashback, :crevenue,
                :cccashback, :ccrevenue, :cstatus, :brecived, :invoice,
                :source_table, :updateInvoice, :cashbackverify
            ) ON DUPLICATE KEY UPDATE
                booking_date    = VALUES(booking_date),
                booking_month   = VALUES(booking_month),
                builder         = VALUES(builder),
                project         = VALUES(project),
                customer_name   = VALUES(customer_name),
                contact_number  = VALUES(contact_number),
                email_id        = VALUES(email_id),
                project_type    = VALUES(project_type),
                unit_no         = VALUES(unit_no),
                size            = VALUES(size),
                agreement_value = VALUES(agreement_value),
                cashback        = VALUES(cashback),
                revenue         = VALUES(revenue),
                ccashback       = VALUES(ccashback),
                crevenue        = VALUES(crevenue),
                astatus         = VALUES(astatus),
                recived_amt     = VALUES(recived_amt),
                invoice_raise   = VALUES(invoice_raise),
                source_table    = VALUES(source_table),
                update_in_invoice_table = VALUES(update_in_invoice_table),
                cashbackverify  = VALUES(cashbackverify)';

            $stmt = $this->conn->prepare($sqlInsert);
            $executeResult = $stmt->execute($params);

            error_log('DB->update() updaterowtable insert/update result: ' . ($executeResult ? 'TRUE' : 'FALSE'));
            return $executeResult;
        }
    }

    // Delete User From Database
    public function delete($id)
    {
        global $tablename;
        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare('DELETE FROM updaterowtable WHERE id = :id');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $this->conn->prepare('DELETE FROM admintable WHERE id = :id AND source_table = :tablename');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':tablename', $tablename, PDO::PARAM_STR);
            $stmt->execute();

            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('DB->delete() error: ' . $e->getMessage());
            return false;
        }
    }

    // this is for tracking for all the user
    public function insertOrUpdateTrackingData(
        $month,
        $gen_revenue,
        $recent_pay,
        $remaning_amt,
        $user_name,
        $bookin_number,
        $send_amt,
        $user_type
        )
    {
        // Ensure $send_amt is never NULL
        $send_amt = isset($send_amt) && $send_amt !== '' ? floatval($send_amt) : 0.00;

        // Check if row exists
        $sql = "SELECT * FROM tracking_table WHERE month = :month AND user_name = :user_name";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':month', $month);
        $stmt->bindParam(':user_name', $user_name);
        $stmt->execute();
        $existingRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingRow) {
            // Update existing row
            $sql = "UPDATE tracking_table 
                    SET gen_revenue = :gen_revenue, 
                        recent_pay = :recent_pay, 
                        remaning_amt = :remaning_amt, 
                        bookin_number = :bookin_number, 
                        send_amt = :send_amt
                    WHERE month = :month AND user_name = :user_name AND user_type = :user_type";
        }
        else {
            // Insert new row
            $sql = "INSERT INTO tracking_table 
                    (month, gen_revenue, recent_pay, remaning_amt, user_name, bookin_number, send_amt, user_type)
                    VALUES (:month, :gen_revenue, :recent_pay, :remaning_amt, :user_name, :bookin_number, :send_amt, :user_type)";
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':month', $month);
        $stmt->bindParam(':gen_revenue', $gen_revenue);
        $stmt->bindParam(':recent_pay', $recent_pay);
        $stmt->bindParam(':remaning_amt', $remaning_amt);
        $stmt->bindParam(':user_name', $user_name);
        $stmt->bindParam(':bookin_number', $bookin_number);
        $stmt->bindParam(':send_amt', $send_amt);
        $stmt->bindParam(':user_type', $user_type);

        return $stmt->execute();
    }
    // This is for tarking for all the user end 
    // Insert Payment Data Into the payment table 
    public function insertOrUpdatePayment($overall_earn, $overall_paid, $remaning_payment, $user_name, $bookin_number)
    {
        $sql = "SELECT * FROM payment_table WHERE user_name = :user_name";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_name', $user_name);
        $stmt->execute();
        $existingRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingRow) {
            // If a row exists, update it
            $sql = "UPDATE payment_table 
                  SET overall_earn = :overall_earn, overall_paid = :overall_paid, remaning_payment = :remaning_payment, bookin_number = :bookin_number
                  WHERE user_name = :user_name";
        }
        else {
            // If no row exists, insert a new row
            $sql = "INSERT INTO payment_table (overall_earn, overall_paid, remaning_payment, user_name, bookin_number)
                  VALUES (:overall_earn, :overall_paid, :remaning_payment, :user_name, :bookin_number)";
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':overall_earn', $overall_earn);
        $stmt->bindParam(':overall_paid', $overall_paid);
        $stmt->bindParam(':remaning_payment', $remaning_payment); // Fix the column name here
        $stmt->bindParam(':user_name', $user_name);
        $stmt->bindParam(':bookin_number', $bookin_number);

        return $stmt->execute();
    }

    // Insert Payment Data Into the payment table End Here
    // Get the User Advance Pay Amount
    public function getAdvancePayByUser($user_name)
    {
        $sql = "SELECT advance_pay FROM payment_table WHERE user_name = :user_name";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_name', $user_name);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if the user exists in the payment_table
        if ($result) {
            return $result['advance_pay'];
        }
        else {
            // Return a default value or handle the case where the user is not found
            return null; // You can customize this as needed
        }
    }

    // Get the User Advance Pay Amount End 
    public function sendMessage($sender_id, $receiver_id, $message_content)
    {
        $sql = "INSERT INTO messages (sender_id, receiver_id, message_content, timestamp) VALUES (:sender_id, :receiver_id, :message_content, NOW())";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':sender_id', $sender_id, PDO::PARAM_STR);
        $stmt->bindParam(':receiver_id', $receiver_id, PDO::PARAM_STR);
        $stmt->bindParam(':message_content', $message_content, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $message_data = [
                'action' => 'send_message',
                'sender_id' => $sender_id,
                'receiver_id' => $receiver_id,
                'message_content' => $message_content,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            $this->sendWebSocketMessage($message_data);
            return true;
        }
        return false;
    }

    private function sendWebSocketMessage($data)
    {
        $url = 'ws://searchhomesindia.in:65003/';
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode($data)
            ]
        ]);
        file_get_contents($url, false, $context);
    }

    public function fetchMessages($user_id)
    {
        $sql = "SELECT * FROM messages WHERE sender_id = :user_id OR receiver_id = :user_id ORDER BY timestamp ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
        $stmt->execute();
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $messages;
    }
    // Get total from msalary column
    public function getTotalAccountSalary($month)
    {
        global $tablename;

        // Define the start and end of the month
        $startOfMonth = $month . '-01'; // e.g., '2024-07-01'
        $endOfMonth = (new DateTime($startOfMonth))->modify('last day of this month')->format('Y-m-d'); // Last day of the month

        // SQL to fetch the salary, active dates, deactivation dates, and assignment history
        $sql = "SELECT a.id, a.salary, a.created_at, a.deactivated_at, a.is_active,
                   h.assign_user, h.effective_date, h.end_date
            FROM accounts a
            LEFT JOIN assign_user_history h ON a.id = h.user_id
            WHERE (a.created_at <= :end_of_month)  -- User created on or before the end of the month
            AND (a.deactivated_at IS NULL OR a.deactivated_at >= :start_of_month)  -- User active at least part of the month
            AND (COALESCE(h.assign_user, a.assign_user) = :tablename)  -- Assigned to the current manager
            AND (h.effective_date <= :end_of_month OR h.effective_date IS NULL)  -- Manager assignment within the month
            ORDER BY a.id, h.effective_date";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':end_of_month', $endOfMonth);
        $stmt->bindParam(':start_of_month', $startOfMonth);
        $stmt->bindParam(':tablename', $tablename);

        // Execute the query
        $stmt->execute();
        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalSalary = 0;

        // Process each account and calculate the salary
        foreach ($accounts as $account) {
            $salary = $account['salary'];
            $created_at = new DateTime($account['created_at']);
            $deactivated_at = !is_null($account['deactivated_at']) ? new DateTime($account['deactivated_at']) : null;
            $is_active = $account['is_active'];
            $effective_date = new DateTime($account['effective_date']); // Manager's start date
            $end_date = !is_null($account['end_date']) ? new DateTime($account['end_date']) : null; // Manager's end date

            // Define the range for the current month
            $startOfMonthDate = new DateTime($startOfMonth);
            $endOfMonthDate = new DateTime($endOfMonth);

            // Determine the actual start and end dates for calculating salary
            $userStartDate = max($created_at, $startOfMonthDate); // Start either from account creation or month start
            $userEndDate = $deactivated_at ? min($deactivated_at, $endOfMonthDate) : $endOfMonthDate; // Stop at deactivation or month end

            // Adjust for the manager's assignment period
            $managerStartDate = max($effective_date, $userStartDate);
            if ($end_date !== null) {
                $managerEndDate = min($end_date, $userEndDate);
            }
            else {
                $managerEndDate = $userEndDate; // If no end date, use the user's active range end
            }

            // Check if there's any active overlap between manager assignment and user activity
            if ($managerStartDate <= $managerEndDate) {
                // Calculate days active for the manager in the current month
                $daysInMonth = $endOfMonthDate->diff($startOfMonthDate)->days + 1; // Total days in the month
                $daysActiveForManager = $managerEndDate->diff($managerStartDate)->days + 1; // Days the user was assigned to this manager

                // Prorate the salary based on active days for the manager
                $proportion = $daysActiveForManager / $daysInMonth;
                $totalSalary += $salary * $proportion;
            }
        }

        return (int)$totalSalary; // Return the total salary for the current manager in the specified month
    }

    // This function is for get the total expenses
    public function getTotalExpenses($month)
    {
        global $tablename;
        // Prepare the SQL query
        $sql = "SELECT SUM(expense_amount) AS total_expenses FROM company_expenses WHERE expenses_month = :month AND assign_user = :tablename";
        $stmt = $this->conn->prepare($sql);
        // Bind parameters and execute query
        $stmt->bindParam(':month', $month);
        $stmt->bindParam(':tablename', $tablename);
        $stmt->execute();
        // Fetch the total msalary
        $result = $stmt->fetch();
        // Check if there are results
        if ($result) {
            // Return the total msalary
            return $result['total_expenses'];
        }
        else {
            // Return 0 if no results found
            return 0;
        }
    }
    //This function is for get the total of incentive as per user based
    public function getTotalIncentive($month)
    {
        global $tablename;
        // Prepare the SQL query to sum both send_amt and getamount
        $sql = "SELECT SUM(getamount) AS total_incentive FROM admintable WHERE booking_month = :month AND assign_user = :tablename";
        $stmt = $this->conn->prepare($sql);
        // Bind parameters and execute query
        $stmt->bindParam(':month', $month);
        $stmt->bindParam(':tablename', $tablename);
        $stmt->execute();
        // Fetch the result
        $result = $stmt->fetch();
        // Check if there are results
        if ($result) {
            // Return the total expenses
            return $result['total_incentive'];
        }
        else {
            // Return 0 if no results found
            return 0;
        }
    }
}
?>