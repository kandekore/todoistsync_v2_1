<?php
if (!defined("WHMCS")) die("Access Denied");

/**
 * Real-time Hooks
 */
add_hook('AdminToDoAdd', 1, 'todoistsync_safe_sync');
add_hook('AdminToDoEdit', 1, 'todoistsync_safe_sync');
add_hook('AdminToDoStatusUpdate', 1, 'todoistsync_safe_sync');
add_hook('ToDoItemAdd', 1, 'todoistsync_safe_sync');
add_hook('ToDoItemEdit', 1, 'todoistsync_safe_sync');

function todoistsync_safe_sync($vars) {
    $todoId = $vars['todoid'] ?? $vars['id'];
    if (!$todoId) return;

    require_once __DIR__ . '/lib/SyncService.php';
    if (class_exists('TodoistSyncService')) {
        $sync = new TodoistSyncService();
        $sync->syncFromWhmcs($todoId);
    }
}

/**
 * Scheduled Sync (Cron)
 * Runs every time the WHMCS System Cron runs
 */
add_hook('AfterCronJob', 1, function($vars) {
    require_once __DIR__ . '/lib/SyncService.php';
    
    if (class_exists('TodoistSyncService')) {
        $sync = new TodoistSyncService();
        
        // Fetch all tasks that are NOT completed
        $tasks = \WHMCS\Database\Capsule::table('tbltodolist')
            ->where('status', '!=', 'Completed')
            ->get();

        if ($tasks->count() > 0) {
            foreach ($tasks as $task) {
                $sync->syncFromWhmcs($task->id);
            }
            logModuleCall('todoistsync', 'cron_sync', 'Scheduled Sync Success', 'Processed ' . $tasks->count() . ' tasks');
        }
    }
});