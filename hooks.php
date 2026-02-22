<?php
if (!defined("WHMCS")) die("Access Denied");

function todoistsync_safe_sync($vars) {
    // Determine the ID from WHMCS variables
    $todoId = $vars['todoid'] ?? $vars['id'];

    if ($todoId) {
        $libPath = __DIR__ . '/lib/SyncService.php';
        if (file_exists($libPath)) {
            require_once $libPath;
            if (class_exists('TodoistSyncService')) {
                $sync = new TodoistSyncService();
                $sync->syncFromWhmcs($todoId);
            }
        }
    }
}

// Re-attach to the standard WHMCS hook points
add_hook('AdminToDoAdd', 1, 'todoistsync_safe_sync');
add_hook('AdminToDoEdit', 1, 'todoistsync_safe_sync');
add_hook('AdminToDoStatusUpdate', 1, 'todoistsync_safe_sync');