<?php
use WHMCS\Database\Capsule;

if (!class_exists('TodoistSyncService')) {
    require_once __DIR__ . '/TodoistClient_v1.php';

    class TodoistSyncService {
        private $client;

        public function __construct() {
            $token = Capsule::table('tbladdonmodules')
                ->where('module', 'todoistsync')
                ->where('setting', 'api_token')
                ->value('value');
            $this->client = new TodoistClient($token);
        }

        public function syncFromWhmcs($todoId) {
            if (defined('TODOIST_SYNC_ORIGIN')) return;

            $task = Capsule::table('tbltodolist')->where('id', $todoId)->first();
            if (!$task) return;

            $mapping = Capsule::table('mod_todoistsync_map')->where('whmcs_todo_id', $todoId)->first();

            // Handle completion BEFORE checking the hash
            if ($task->status === 'Completed' && $mapping) {
                $this->client->closeTask($mapping->todoist_task_id);
            }

            // Generate hash to see if an update is needed
            $hash = hash('sha256', $task->title.$task->description.$task->duedate.$task->status.$task->admin);
            if ($mapping && $mapping->last_hash === $hash) return;

            $admin = Capsule::table('tbladmins')->where('username', $task->admin)->orWhere('id', $task->admin)->first();
            $todoistUserId = $admin ? Capsule::table('mod_todoistsync_admin_map')->where('whmcs_admin_id', $admin->id)->value('todoist_user_id') : null;

            $payload = [
                "content" => $task->title,
                "description" => $task->description,
                "due_date" => $task->duedate ? date('Y-m-d', strtotime($task->duedate)) : null,
                "assignee_id" => $todoistUserId ?: null
            ];

            if (!$mapping) {
                if ($task->status === 'Completed') return; 
                $created = $this->client->createTask($payload);
                if (!empty($created['id'])) {
                    Capsule::table('mod_todoistsync_map')->insert([
                        'whmcs_todo_id' => $todoId,
                        'todoist_task_id' => $created['id'],
                        'last_hash' => $hash,
                        'last_sync' => date('Y-m-d H:i:s')
                    ]);
                }
            } else {
                if ($task->status !== 'Completed') {
                    $this->client->updateTask($mapping->todoist_task_id, $payload);
                }
                
                Capsule::table('mod_todoistsync_map')->where('whmcs_todo_id', $todoId)->update([
                    'last_hash' => $hash,
                    'last_sync' => date('Y-m-d H:i:s')
                ]);
            }
        }

    
        public function completeFromTodoist($todoistId) {
            // Prevent recursive loops
            if (!defined('TODOIST_SYNC_ORIGIN')) define('TODOIST_SYNC_ORIGIN', true);

            $mapping = Capsule::table('mod_todoistsync_map')->where('todoist_task_id', $todoistId)->first();
            if (!$mapping) return;

            Capsule::table('tbltodolist')
                ->where('id', $mapping->whmcs_todo_id)
                ->update(['status' => 'Completed']);
                
            logModuleCall('todoistsync', 'webhook_complete', $todoistId, "Marked WHMCS ID {$mapping->whmcs_todo_id} as Completed");
        }
    }
}