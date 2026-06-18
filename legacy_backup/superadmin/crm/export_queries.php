<?php
// export_queries.php
function build_export_queries(array $params, string $useruniqueId = '', bool $applyLimit = false) {
    $page = isset($params['page']) ? (int)$params['page'] : 1;
    $rowsPerPage = isset($params['rowsPerPage']) ? (int)$params['rowsPerPage'] : 10;
    $searchQuery = isset($params['searchQuery']) ? trim($params['searchQuery']) : '';
    $multiFilters = isset($params['multiFilters']) ? $params['multiFilters'] : [];
    if (!is_array($multiFilters)) {
        // if multiFilters stored as string for some reason, try decode
        $decoded = json_decode($multiFilters, true);
        $multiFilters = is_array($decoded) ? $decoded : [];
    }
    $startDate = isset($params['startDate']) ? $params['startDate'] : '';
    $endDate = isset($params['endDate']) ? $params['endDate'] : '';
    $startRow = ($page - 1) * $rowsPerPage;
    $showDeletedOnly = isset($params['showDeletedOnly']) && ($params['showDeletedOnly'] === '1' || $params['showDeletedOnly'] === 1 || $params['showDeletedOnly'] === true);
    $currentFilter = isset($params['currentFilter']) ? $params['currentFilter'] : '';

    $tableName = $showDeletedOnly ? 'deleted_item' : 'shi_upload_data';

    $baseQuery = "SELECT * FROM `$tableName` WHERE 1";
    $countQuery = "SELECT COUNT(*) as total FROM `$tableName` WHERE 1";
    $queryParams = [];

    // Special restriction for NoUser323 (if you rely on $useruniqueId)
    if ($useruniqueId === "NoUser323" && $tableName === "shi_upload_data") {
        $allowedProjects = [
            "Godrej Rajendranagar",
            "Ramky Rajendranagar",
            "Godrej Shettigere Rd",
            "Godrej Thanisandra"
        ];
        $projectList = "'" . implode("','", array_map('addslashes', $allowedProjects)) . "'";
        $baseQuery .= " AND project IN ($projectList)";
        $countQuery .= " AND project IN ($projectList)";
    }

    // Handle selected IDs - if user selected specific leads via checkboxes, ONLY export those
    $selectedIds = isset($params['selectedIds']) ? $params['selectedIds'] : [];
    if (!empty($selectedIds) && is_array($selectedIds)) {
        // User has selected specific leads - filter to only those IDs
        $idPlaceholders = [];
        foreach ($selectedIds as $i => $id) {
            $p = ":selectedId_$i";
            $idPlaceholders[] = $p;
            $queryParams[$p] = $id;
        }
        if (!empty($idPlaceholders)) {
            $phStr = implode(', ', $idPlaceholders);
            $baseQuery .= " AND id IN ($phStr)";
            $countQuery .= " AND id IN ($phStr)";
        }
        
        // When IDs are selected, skip all other filters (status, search, etc.)
        // Jump directly to the end
        $baseQuery .= " ORDER BY created_at DESC";
        if ($applyLimit) {
            $baseQuery .= " LIMIT :startRow, :rowsPerPage";
            $queryParams[':startRow'] = $startRow;
            $queryParams[':rowsPerPage'] = $rowsPerPage;
        }
        
        return [
            'baseQuery' => $baseQuery,
            'countQuery' => $countQuery,
            'queryParams' => $queryParams,
            'tableName' => $tableName
        ];
    }

    $validStatuses = [
        'Active', 'New', 'Pending', 'Dropped', 'Fake', 'RNR', 'Call Back', 'Already Booked',
        'Not Interested', 'Interested', 'Follow Up', 'Fix Site Visit', 'Site Visit Done',
        'Converted', 'Not Connected'
    ];

    if (in_array($currentFilter, $validStatuses)) {
        $baseQuery .= " AND status = :statusFilter";
        $countQuery .= " AND status = :statusFilter";
        $queryParams[':statusFilter'] = $currentFilter;
    } elseif ($currentFilter === 'my') {
        $baseQuery .= " AND assign_to_user = :currentUser";
        $countQuery .= " AND assign_to_user = :currentUser";
        $queryParams[':currentUser'] = $useruniqueId;
    } elseif ($currentFilter === 'unassigned') {
        $baseQuery .= " AND (assign_to_user IS NULL OR assign_to_user = '')";
        $countQuery .= " AND (assign_to_user IS NULL OR assign_to_user = '')";
    } elseif ($currentFilter === 'dropped') {
        $baseQuery .= " AND id IN (
            SELECT upload_data_id FROM user_remarks
            GROUP BY upload_data_id
            HAVING SUM(CASE WHEN status = 'Not Interested' THEN 1 ELSE 0 END) = COUNT(*)
        )";
        $countQuery .= " AND id IN (
            SELECT upload_data_id FROM user_remarks
            GROUP BY upload_data_id
            HAVING SUM(CASE WHEN status = 'Not Interested' THEN 1 ELSE 0 END) = COUNT(*)
        )";
    } elseif ($currentFilter === 'active') {
        $baseQuery .= " AND id NOT IN (
            SELECT upload_data_id FROM user_remarks
            GROUP BY upload_data_id
            HAVING SUM(CASE WHEN status IN ('Not Interested', 'Fake', 'Already Booked') THEN 1 ELSE 0 END) = COUNT(*)
        )";
        $countQuery .= " AND id IN (
            SELECT upload_data_id FROM user_remarks
            GROUP BY upload_data_id
            HAVING SUM(CASE WHEN status IN ('Not Interested', 'Fake', 'Already Booked') THEN 1 ELSE 0 END) < COUNT(*)
        )";
    } elseif ($currentFilter === 'pending') {
        $baseQuery .= " AND id IN (SELECT DISTINCT upload_data_id FROM user_remarks WHERE status = 'Pending')";
        $countQuery .= " AND id IN (SELECT DISTINCT upload_data_id FROM user_remarks WHERE status = 'Pending')";
    } elseif ($currentFilter === 'fresh') {
        $baseQuery .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 5 DAY)";
        $countQuery .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 5 DAY)";
    }

    // Multi-filters: iterate keys and build conditions
    foreach ($multiFilters as $column => $values) {
        if ($column === null) continue;
        if (!empty($values) && is_array($values)) {
            if ($column === 'status') {
                // Build a list of named params for statuses
                $statusPlaceholders = [];
                foreach ($values as $i => $v) {
                    $p = ":mf_status_$i";
                    $statusPlaceholders[] = $p;
                    $queryParams[$p] = $v;
                }
                if (!empty($statusPlaceholders)) {
                    $phStr = implode(', ', $statusPlaceholders);
                    $baseQuery .= " AND id IN (SELECT upload_data_id FROM user_remarks WHERE status IN ($phStr))";
                    $countQuery .= " AND id IN (SELECT upload_data_id FROM user_remarks WHERE status IN ($phStr))";
                }
            } else {
                // For normal columns we use LIKE with OR between values
                $conds = [];
                foreach ($values as $i => $val) {
                    $p = ":mf_{$column}_$i";
                    $conds[] = "`$column` LIKE $p";
                    $queryParams[$p] = "%$val%";
                }
                if (!empty($conds)) {
                    $baseQuery .= " AND (" . implode(' OR ', $conds) . ")";
                    $countQuery .= " AND (" . implode(' OR ', $conds) . ")";
                }
            }
        }
    }

    // Search across columns (single search box)
    if (!empty($searchQuery)) {
        $searchCols = ['name', 'email', 'number', 'location', 'source_of_lead', 'project', 'assign_to_user'];
        $sc = [];
        foreach ($searchCols as $col) {
            $p = ":search_{$col}";
            $sc[] = "`$col` LIKE $p";
            $queryParams[$p] = "%$searchQuery%";
        }
        if (!empty($sc)) {
            $baseQuery .= " AND (" . implode(' OR ', $sc) . ")";
            $countQuery .= " AND (" . implode(' OR ', $sc) . ")";
        }
    }

    // Date range
    if (!empty($startDate) && !empty($endDate)) {
        $baseQuery .= " AND DATE(`created_at`) BETWEEN :startDate AND :endDate";
        $countQuery .= " AND DATE(`created_at`) BETWEEN :startDate AND :endDate";
        $queryParams[':startDate'] = $startDate;
        $queryParams[':endDate'] = $endDate;
    }

    // Order
    $baseQuery .= " ORDER BY created_at DESC";

    // Limit for UI only
    if ($applyLimit) {
        $baseQuery .= " LIMIT :startRow, :rowsPerPage";
        $queryParams[':startRow'] = $startRow;
        $queryParams[':rowsPerPage'] = $rowsPerPage;
    }

    return [
        'baseQuery' => $baseQuery,
        'countQuery' => $countQuery,
        'queryParams' => $queryParams,
        'tableName' => $tableName
    ];
}
