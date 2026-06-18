<?php
// export_worker.php
// Run manually: php export_worker.php
// --- Autoload PhpSpreadsheet ---
$possible = [
  __DIR__ . '/../../vendor/autoload.php',
  __DIR__ . '/../vendor/autoload.php',
  __DIR__ . '/vendor/autoload.php'
];
foreach ($possible as $p) {
  if (file_exists($p)) { require $p; break; }
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

require __DIR__ . '/../config.php';
require __DIR__ . '/export_queries.php';
require_once __DIR__ . '/export_table_helper.php';

// Allow long-running export
@set_time_limit(0);
@ini_set('memory_limit', '1024M');

$config = new Config();
$conn = $config->getConnection();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
ensureExportJobsTable($conn);

// Ensure logs directory exists for error logging
$logsDir = __DIR__ . '/../logs';
if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0755, true);
}

// We will validate PhpSpreadsheet after we claim a job so we can mark that job as failed if missing.

$exportsDir = __DIR__ . '/../exports';
if (!is_dir($exportsDir)) mkdir($exportsDir, 0755, true);

// Fallback: CSV export when PhpSpreadsheet is not installed
function exportCsvFallback(PDO $conn, array $params, string $userId, int $jobId, string $exportsDir, string $logsDir): void {
    $q = build_export_queries($params, $userId, false);
    $baseQuery   = $q['baseQuery'];
    $queryParams = $q['queryParams'];
    $tableName   = $q['tableName'];

    // Collect ids
    $idQuery = preg_replace('/^SELECT\s+\*/i', 'SELECT id', $baseQuery);
    $idQuery = preg_replace('/ORDER\s+BY[\s\S]*$/i', '', $idQuery);
    $idStmt = $conn->prepare($idQuery);
    unset($queryParams[':startRow'], $queryParams[':rowsPerPage']);
    foreach ($queryParams as $k => $v) {
        if (strpos($k, ':') === 0) {
            $idStmt->bindValue($k, $v, PDO::PARAM_STR);
        }
    }
    $idStmt->execute();
    $ids = [];
    while ($r = $idStmt->fetch(PDO::FETCH_ASSOC)) {
        $ids[] = $r['id'];
    }

    $fileName = sprintf("leads_job_%d_%s.csv", $jobId, date('Ymd_His'));
    $filePath = $exportsDir . '/' . $fileName;
    $tmpPath  = $filePath . '.tmp';
    $fh = fopen($tmpPath, 'w');
    if (!$fh) {
        throw new RuntimeException('Cannot open temp CSV for writing');
    }

    // empty data
    if (empty($ids)) {
        fputcsv($fh, ['No data available']);
        fclose($fh);
        rename($tmpPath, $filePath);
        $conn->prepare("UPDATE export_jobs SET status='done', file_path=?, file_name=?, updated_at=NOW() WHERE id=?")
             ->execute([$filePath, $fileName, $jobId]);
        return;
    }

    $batchSize = 1000;
    $fetchRowsForIds = function(array $chunkIds) use ($conn, $tableName) {
        if (empty($chunkIds)) return [];
        $placeholders = implode(',', array_fill(0, count($chunkIds), '?'));
        $q = "SELECT * FROM `$tableName` WHERE id IN ($placeholders)";
        $stmt = $conn->prepare($q);
        $i = 1;
        foreach ($chunkIds as $cid) {
            $stmt->bindValue($i++, $cid, PDO::PARAM_INT);
        }
        $stmt->execute();
        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $map[$row['id']] = $row;
        }
        return $map;
    };

    $fetchRemarksForIds = function(array $chunkIds) use ($conn) {
        if (empty($chunkIds)) return [];
        $placeholders = implode(',', array_fill(0, count($chunkIds), '?'));
        $q = "SELECT upload_data_id, history, status FROM user_remarks WHERE upload_data_id IN ($placeholders) ORDER BY created_at DESC";
        $stmt = $conn->prepare($q);
        $i = 1;
        foreach ($chunkIds as $cid) {
            $stmt->bindValue($i++, $cid, PDO::PARAM_INT);
        }
        $stmt->execute();
        $map = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($map[$r['upload_data_id']])) {
                $map[$r['upload_data_id']] = [
                    'history' => $r['history'],
                    'status'  => $r['status'],
                ];
            }
        }
        return $map;
    };

    // headers
    $firstChunk = array_slice($ids, 0, min($batchSize, count($ids)));
    $rowsMap = $fetchRowsForIds($firstChunk);
    $firstRow = reset($rowsMap) ?: [];
    $excludeColumns = [ 'status','type','page_id','fb_created_time','lead_count','updated_at','subsource_of_lead' ];
    $headers = array_keys($firstRow);
    $headers = array_filter($headers, function($h) use ($excludeColumns) { return !in_array(strtolower($h), $excludeColumns); });
    $headers = array_values($headers);
    if (!in_array('status', array_map('strtolower', $headers))) {
        $headers[] = 'status';
    }
    $headers[] = 'History';
    fputcsv($fh, $headers);

    $total = count($ids);
    for ($i = 0; $i < $total; $i += $batchSize) {
        $chunk = array_slice($ids, $i, $batchSize);
        $rowsMap = $fetchRowsForIds($chunk);
        $remarksMap = $fetchRemarksForIds($chunk);

        foreach ($chunk as $id) {
            $row = $rowsMap[$id] ?? [];
            $out = [];
            foreach ($headers as $h) {
                if ($h === 'History') {
                    $historyJson = isset($remarksMap[$id]['history']) ? $remarksMap[$id]['history'] : '';
                    $decoded = json_decode($historyJson, true);
                    if (is_array($decoded)) {
                        $parts = [];
                        foreach ($decoded as $entry) {
                            if (is_array($entry)) {
                                $kv = [];
                                foreach ($entry as $k => $v) {
                                    if ($v === '' || $v === null) continue;
                                    $kv[] = ucfirst($k) . ': ' . $v;
                                }
                                if ($kv) $parts[] = implode('; ', $kv);
                            } else {
                                $parts[] = (string)$entry;
                            }
                        }
                        $out[] = implode(' | ', $parts);
                    } else {
                        $out[] = (string)$historyJson;
                    }
                } elseif (strtolower($h) === 'status') {
                    $out[] = isset($remarksMap[$id]['status']) ? $remarksMap[$id]['status'] : '';
                } else {
                    $out[] = isset($row[$h]) ? $row[$h] : '';
                }
            }
            fputcsv($fh, $out);
        }
    }

    fclose($fh);
    if (!rename($tmpPath, $filePath)) {
        throw new RuntimeException("Failed to rename temp file to final path: $filePath");
    }
    $conn->prepare("UPDATE export_jobs SET status='done', file_path=?, file_name=?, updated_at=NOW() WHERE id=?")
         ->execute([$filePath, $fileName, $jobId]);
}

try {
    // Optional: process a specific job when called via HTTP with ?jobId=...&token=...
    $requestedJobId = isset($_GET['jobId']) ? (int)$_GET['jobId'] : 0;
    $requestedToken = isset($_GET['token']) ? $_GET['token'] : '';

    // --- 1) Claim one pending job ---
    $conn->beginTransaction();
    if ($requestedJobId) {
        $stmt = $conn->prepare("SELECT * FROM export_jobs WHERE id = ? AND status = 'pending' " . ($requestedToken ? "AND token = ?" : "") . " FOR UPDATE");
        $stmt->execute($requestedToken ? [$requestedJobId, $requestedToken] : [$requestedJobId]);
        $jobRow = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $jobRow = $conn->query("SELECT * FROM export_jobs WHERE status = 'pending' ORDER BY created_at LIMIT 1 FOR UPDATE")
                       ->fetch(PDO::FETCH_ASSOC);
    }

    if (!$jobRow) {
        $conn->rollBack();
        exit(0);
    }

    $update = $conn->prepare("UPDATE export_jobs SET status = 'processing', updated_at = NOW() 
                              WHERE id = ? AND status = 'pending'");
    $update->execute([$jobRow['id']]);
    if ($update->rowCount() === 0) {
        $conn->rollBack();
        exit(0);
    }
    $conn->commit();

    $jobId = $jobRow['id'];
    $params = json_decode($jobRow['params'], true) ?: [];
    $useruniqueId = $jobRow['user_id'] ?? '';

    // If PhpSpreadsheet is missing, fall back to CSV export and finish the job.
    if (!class_exists(Spreadsheet::class)) {
        try {
            exportCsvFallback($conn, $params, $useruniqueId, $jobId, $exportsDir, $logsDir);
            exit(0);
        } catch (Throwable $e) {
            $msg = 'CSV fallback failed: ' . $e->getMessage();
            $conn->prepare("UPDATE export_jobs SET status='failed', error=?, updated_at=NOW() WHERE id=?")
                 ->execute([$msg, $jobId]);
            file_put_contents($logsDir . '/export_worker_error.log', date('c') . " job {$jobId}: {$msg}" . PHP_EOL, FILE_APPEND);
            http_response_code(500);
            echo $msg;
            exit(1);
        }
    }

    // Ensure multiFilters is array
    if (isset($params['multiFilters']) && is_string($params['multiFilters'])) {
        $mf = json_decode($params['multiFilters'], true);
        $params['multiFilters'] = ($mf !== null) ? $mf : [];
    } elseif (!isset($params['multiFilters']) || !is_array($params['multiFilters'])) {
        $params['multiFilters'] = [];
    }

    // Ensure selectedIds is array
    if (isset($params['selectedIds']) && is_string($params['selectedIds'])) {
        $si = json_decode($params['selectedIds'], true);
        $params['selectedIds'] = (is_array($si)) ? $si : [];
    } elseif (!isset($params['selectedIds']) || !is_array($params['selectedIds'])) {
        $params['selectedIds'] = [];
    }

    // --- 2) Build queries (no LIMIT) ---
    $q = build_export_queries($params, $useruniqueId, false);
    $baseQuery   = $q['baseQuery'];
    $queryParams = $q['queryParams'];
    $tableName   = $q['tableName'];

    // --- 3) Collect ids only ---
    $idQuery = preg_replace('/^SELECT\s+\*/i', 'SELECT id', $baseQuery);
    $idQuery = preg_replace('/ORDER\s+BY[\s\S]*$/i', '', $idQuery);

    $idStmt = $conn->prepare($idQuery);
    if (isset($queryParams[':startRow'])) unset($queryParams[':startRow']);
    if (isset($queryParams[':rowsPerPage'])) unset($queryParams[':rowsPerPage']);

    foreach ($queryParams as $k => $v) {
        if (strpos($k, ':') === 0) {
            $idStmt->bindValue($k, $v, PDO::PARAM_STR);
        }
    }
    $idStmt->execute();

    $ids = [];
    while ($r = $idStmt->fetch(PDO::FETCH_ASSOC)) {
        $ids[] = $r['id'];
    }

    // --- 4) Prepare spreadsheet ---
    $fileName = sprintf("leads_job_%d_%s.xlsx", $jobId, date('Ymd_His'));
    $filePath = $exportsDir . '/' . $fileName;
    $tmpPath  = $filePath . '.tmp';

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Leads Export');

    if (empty($ids)) {
        $sheet->setCellValue('A1', 'No data available');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpPath);
        rename($tmpPath, $filePath);
        $conn->prepare("UPDATE export_jobs SET status='done', file_path=?, file_name=?, updated_at=NOW() WHERE id=?")
             ->execute([$filePath, $fileName, $jobId]);
        exit(0);
    }

    $batchSize = 1000;

    // Helpers
    $fetchRowsForIds = function(array $chunkIds) use ($conn, $tableName) {
        if (empty($chunkIds)) return [];
        $placeholders = implode(',', array_fill(0, count($chunkIds), '?'));
        $q = "SELECT * FROM `$tableName` WHERE id IN ($placeholders)";
        $stmt = $conn->prepare($q);
        $i = 1;
        foreach ($chunkIds as $cid) {
            $stmt->bindValue($i++, $cid, PDO::PARAM_INT);
        }
        $stmt->execute();
        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $map[$row['id']] = $row;
        }
        return $map;
    };

    // NEW: fetch both history (JSON) and status (separate column) from user_remarks (latest row per id)
    $fetchRemarksForIds = function(array $chunkIds) use ($conn) {
        if (empty($chunkIds)) return [];
        $placeholders = implode(',', array_fill(0, count($chunkIds), '?'));
        // fetch latest remark row per upload_data_id (ordered DESC, keep first)
        $q = "SELECT upload_data_id, history, status
              FROM user_remarks
              WHERE upload_data_id IN ($placeholders)
              ORDER BY created_at DESC";
        $stmt = $conn->prepare($q);
        $i = 1;
        foreach ($chunkIds as $cid) {
            $stmt->bindValue($i++, $cid, PDO::PARAM_INT);
        }
        $stmt->execute();
        $map = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($map[$r['upload_data_id']])) {
                $map[$r['upload_data_id']] = [
                    'history' => $r['history'],
                    'status'  => $r['status'],
                ];
            }
        }
        return $map;
    };

    // --- 5) Headers ---
    $firstChunk = array_slice($ids, 0, min($batchSize, count($ids)));
    $rowsMap = $fetchRowsForIds($firstChunk);
    $firstRow = reset($rowsMap) ?: [];

    $excludeColumns = [
        'status','type','page_id','fb_created_time',
        'lead_count','updated_at','subsource_of_lead'
    ];

    $headers = array_keys($firstRow);
    $headers = array_filter($headers, function($h) use ($excludeColumns) {
        return !in_array(strtolower($h), $excludeColumns);
    });
    $headers = array_values($headers);

    // Add status (from user_remarks) — keep it named 'status' per your request
    if (!in_array('status', array_map('strtolower', $headers))) {
        $headers[] = 'status';
    }

    // Always keep History last
    $headers[] = 'History';

    // Write headers
    $colIndex = 1;
    foreach ($headers as $h) {
        $cell = Coordinate::stringFromColumnIndex($colIndex) . '1';
        $sheet->setCellValue($cell, ucfirst($h));
        $sheet->getStyle($cell)->getFont()->setBold(true);
        $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $colIndex++;
    }
    $sheet->freezePane('A2');
    $sheet->setAutoFilter($sheet->calculateWorksheetDimension());

    // --- 6) Write data rows ---
    $rowIndex = 2;
    $total = count($ids);

    for ($i = 0; $i < $total; $i += $batchSize) {
        $chunk = array_slice($ids, $i, $batchSize);
        $rowsMap = $fetchRowsForIds($chunk);
        $remarksMap = $fetchRemarksForIds($chunk);

        foreach ($chunk as $id) {
            $row = $rowsMap[$id] ?? [];
            $colIndex = 1;

            foreach ($headers as $h) {
                $cell = Coordinate::stringFromColumnIndex($colIndex) . $rowIndex;

                // History column: use remarksMap[$id]['history'] (JSON) and format with RichText
                if ($h === 'History') {
                    $historyJson = '';
                    if (isset($remarksMap[$id]) && is_array($remarksMap[$id]) && isset($remarksMap[$id]['history'])) {
                        $historyJson = $remarksMap[$id]['history'];
                    } elseif (isset($remarksMap[$id]) && is_string($remarksMap[$id])) {
                        // backwards-compat: if map contains string
                        $historyJson = $remarksMap[$id];
                    }

                    $decoded = json_decode($historyJson, true);

                    if (is_array($decoded) && count($decoded) > 0) {
                        $rich = new RichText();
                        $firstEntry = true;
                        foreach ($decoded as $entry) {
                            if (!$firstEntry) $rich->createText("\n");
                            $firstEntry = false;

                            if (is_array($entry)) {
                                $order = ['status','notes','followUpDate','followUpTime','leadIdentity','timestamp','update_by'];
                                foreach ($order as $key) {
                                    if (isset($entry[$key]) && $entry[$key] !== '') {
                                        $labelRun = $rich->createTextRun(ucfirst($key) . ': ');
                                        $labelRun->getFont()->setBold(true);
                                        $rich->createText($entry[$key] . "; ");
                                    }
                                }
                            } else {
                                $rich->createText((string)$entry);
                            }
                        }
                        $sheet->getCell($cell)->setValue($rich);
                        $sheet->getStyle($cell)->getAlignment()->setWrapText(true);
                    } else {
                        $sheet->setCellValueExplicit($cell, (string)$historyJson, DataType::TYPE_STRING);
                        $sheet->getStyle($cell)->getAlignment()->setWrapText(true);
                    }

                // status column: write the separate status from user_remarks (latest)
                } elseif (strtolower($h) === 'status') {
                    $statusVal = '';
                    if (isset($remarksMap[$id]) && is_array($remarksMap[$id]) && isset($remarksMap[$id]['status'])) {
                        $statusVal = $remarksMap[$id]['status'];
                    }
                    // explicit string to preserve formatting
                    $sheet->setCellValueExplicit($cell, (string)$statusVal, DataType::TYPE_STRING);

                } else {
                    $value = isset($row[$h]) ? $row[$h] : '';
                    $lowerH = strtolower($h);
                    if (strpos($lowerH, 'number') !== false || strpos($lowerH, 'phone') !== false || $lowerH === 'number') {
                        $sheet->setCellValueExplicit($cell, (string)$value, DataType::TYPE_STRING);
                    } else {
                        $sheet->setCellValue($cell, $value);
                    }
                }
                $colIndex++;
            }
            $rowIndex++;
        }
        gc_collect_cycles();
    }

    // --- 7) Column sizing ---
    $colCount = count($headers);
    for ($c = 1; $c <= $colCount; $c++) {
        $col = Coordinate::stringFromColumnIndex($c);
        if ($c === $colCount) {
            $sheet->getColumnDimension($col)->setWidth(60); // History column wide
        } else {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    // --- 8) Save file ---
    $writer = new Xlsx($spreadsheet);
    $writer->save($tmpPath);
    if (!rename($tmpPath, $filePath)) {
        throw new RuntimeException("Failed to rename temp file to final path: $filePath");
    }

    $stmt = $conn->prepare("UPDATE export_jobs 
        SET status='done', file_path=?, file_name=?, updated_at=NOW() WHERE id=?");
    $stmt->execute([$filePath, $fileName, $jobId]);

    echo "Job $jobId processed. File: $filePath\n";

} catch (Throwable $e) {
    if (!empty($jobId)) {
        $stmt = $conn->prepare("UPDATE export_jobs SET status='failed', error=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$e->getMessage(), $jobId]);
    }
    file_put_contents(__DIR__ . '/../logs/export_worker_error.log',
        date('c') . " " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}