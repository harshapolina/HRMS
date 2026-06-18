<?php
/**
 * wa_auto_send.php
 * ------------------------------------------------------------------
 * Shared helper — call triggerAutoWhatsApp() right after every
 * fresh INSERT INTO user_remarks to automatically send an Esha AI
 * WhatsApp opening message to the lead's number.
 *
 * Usage:
 *   require_once __DIR__ . '/wa_auto_send.php';
 *   triggerAutoWhatsApp($conn, $uploadDataId);
 *
 * This function NEVER throws and NEVER blocks the caller.
 * Results are logged to userlogin6/wa_auto.log.
 * ------------------------------------------------------------------
 */

if (!function_exists('triggerAutoWhatsApp')) {

    // ── Esha API constants ────────────────────────────────────────
    define('WA_ESHA_BASE',   'https://omegaappbuilder.com');   // ← new domain (no agent. prefix)
    define('WA_ESHA_KEY', 'whsec_008283d646aba0038a769475bb7b67a17676d42ee4111002b87d31f7fed18d98');
    define('WA_TENANT_ID',   'tenant_omega_ba8790e7364b');     // kept for reference
    define('WA_LOG_FILE',    __DIR__ . '/wa_auto.log');

    /**
     * triggerAutoWhatsApp
     *
     * @param PDO $conn          Active DB connection (userlogin6 schema)
     * @param int $remarkId      The user_remarks.id of the new lead row
     */
    function triggerAutoWhatsApp(PDO $conn, $remarkId)
    {
        $remarkId = (int)$remarkId;
        if ($remarkId <= 0) return;

        try {
            // ── 1. Fetch lead details ─────────────────────────────
            $stmt = $conn->prepare("
                SELECT sud.id AS uploadDataId, sud.name, sud.number, sud.project,
                       sud.assign_to_user, a.username AS assign_to_name
                FROM   user_remarks ur
                JOIN   shi_upload_data sud ON ur.upload_data_id = sud.id
                LEFT JOIN accounts a ON sud.assign_to_user = a.tablename
                WHERE  ur.id = :id
                LIMIT  1
            ");
            $stmt->execute([':id' => $remarkId]);
            $lead = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$lead || empty($lead['number'])) {
                _waLog("SKIP remark_id=$remarkId — no phone number");
                return;
            }
            
            $uploadDataId = $lead['uploadDataId'];

            // Clean phone → E.164
            $rawPhone = preg_replace('/\D/', '', (string)$lead['number']);
            if (strlen($rawPhone) === 10) {
                $e164Phone = '+91' . $rawPhone;
            } elseif (strlen($rawPhone) === 12 && str_starts_with($rawPhone, '91')) {
                $e164Phone = '+' . $rawPhone;
            } elseif (strlen($rawPhone) >= 10) {
                $e164Phone = '+' . $rawPhone;
            } else {
                _waLog("SKIP lead_id=$uploadDataId — phone '$rawPhone' too short");
                return;
            }

            $leadName    = trim($lead['name']    ?? 'Lead');
            $projectRaw  = trim($lead['project'] ?? 'Not Specified');
            $assignTo    = trim($lead['assign_to_user'] ?? 'agent');
            $assignName  = trim($lead['assign_to_name'] ?? 'Agent');

            // Sanitise project for projectId
            $projectSafe = strtolower(preg_replace('/[^a-z0-9_]/i', '_', $projectRaw));

            // ── 2. Build new /api/initiate payload ────────────────
            $payload = [
                'salesperson_id'      => 'sp_' . $assignTo . '(' . $assignName . ')',
                'salesperson_phone'   => '+919632056699',   // default sender phone
                'project_ids'         => ['proj_' . $projectSafe . '(' . $projectRaw . ')'],
                'row_ids'             => ['row_' . $remarkId],
                'lead_numbers'        => [$e164Phone],
                'sendInitialOutreach' => true,              // send first AI message
            ];

            // ── 3. POST to new /api/initiate endpoint ─────────────
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => WA_ESHA_BASE . '/api/initiate',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                ],
            ]);

            $body    = curl_exec($ch);
            $err     = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($err) {
                _waLog("ERROR lead_id=$uploadDataId curl_error=$err");
                return;
            }

            // ── 4. Parse response ─────────────────────────────────
            $resp = json_decode($body, true);
            $ok   = !empty($resp['ok']);

            _waLog("lead_id=$uploadDataId phone=$e164Phone http=$httpCode ok=" . ($ok ? '1' : '0')
                . " resp=" . substr($body, 0, 200));

            // ── 5. Save outbound message to whatsapp_history ──────
            if ($ok) {
                // Strict Mirroring enforcement: Removed eager local auto-save.
                // Outbound messages will now strictly wait for the Esha CHAT_UPDATE_API webhook, ensuring accuracy.
            }

        } catch (Throwable $e) {
            // Never propagate — just log
            _waLog("EXCEPTION lead_id=$uploadDataId " . $e->getMessage());
        }
    }

    /**
     * Internal log helper — appends a single line to wa_auto.log.
     */
    function _waLog($msg)
    {
        $line = '[' . date('Y-m-d H:i:s') . '] [AUTO-WA] ' . $msg . PHP_EOL;
        @file_put_contents(WA_LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    }
}
