<?php

require_once __DIR__ . '/lib/SyncService.php';

add_hook('AdminToDoAdd', 1, function($vars) {
    (new TodoistSyncService())->syncFromWhmcs($vars['todoid']);
});

add_hook('AdminToDoEdit', 1, function($vars) {
    (new TodoistSyncService())->syncFromWhmcs($vars['todoid']);
});

add_hook('AdminToDoStatusUpdate', 1, function($vars) {
    (new TodoistSyncService())->syncFromWhmcs($vars['todoid']);
});

add_hook('AdminToDoDelete', 1, function($vars) {
    $mapping = \WHMCS\Database\Capsule::table('mod_todoistsync_map')
        ->where('whmcs_todo_id', $vars['todoid'])
        ->first();

    if ($mapping) {
        $sync = new TodoistSyncService();
        $sync->closeTodoistTask($mapping->todoist_task_id);
    }
});
