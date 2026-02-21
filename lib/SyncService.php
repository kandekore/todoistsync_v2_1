<?php

use WHMCS\Database\Capsule;

require_once __DIR__ . '/TodoistClient.php';

class TodoistSyncService
{
    private $client;

    public function __construct()
    {
        $token = Capsule::table('tbladdonmodules')
            ->where('module', 'todoistsync')
            ->where('setting', 'api_token')
            ->value('value');

        $this->client = new TodoistClient($token);
    }

    public function syncFromWhmcs($todoId)
    {
        if (defined('TODOIST_SYNC_ORIGIN')) return;

        $task = Capsule::table('tbltodolist')->where('id', $todoId)->first();
        if (!$task) return;

        $hash = hash('sha256', $task->title.$task->description.$task->duedate.$task->status.$task->admin);

        $mapping = Capsule::table('mod_todoistsync_map')
            ->where('whmcs_todo_id', $todoId)
            ->first();

        if ($mapping && $mapping->last_hash === $hash) return;

        $adminId = Capsule::table('tbladmins')->where('username', $task->admin)->value('id');
        $todoistUserId = Capsule::table('mod_todoistsync_admin_map')
            ->where('whmcs_admin_id', $adminId)
            ->value('todoist_user_id');

        $payload = [
            "content" => $task->title,
            "description" => $task->description,
            "due_date" => $task->duedate ?: null,
            "assignee_id" => $todoistUserId ?: null
        ];

        if (!$mapping) {
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
            $this->client->updateTask($mapping->todoist_task_id, $payload);

            if ($task->status === 'Completed') {
                $this->client->closeTask($mapping->todoist_task_id);
            }

            Capsule::table('mod_todoistsync_map')
                ->where('whmcs_todo_id', $todoId)
                ->update([
                    'last_hash' => $hash,
                    'last_sync' => date('Y-m-d H:i:s')
                ]);
        }
    }

    public function closeTodoistTask($todoistId)
    {
        $this->client->closeTask($todoistId);
    }

    public function completeFromTodoist($todoistId)
    {
        define('TODOIST_SYNC_ORIGIN', true);

        $mapping = Capsule::table('mod_todoistsync_map')
            ->where('todoist_task_id', $todoistId)
            ->first();

        if (!$mapping) return;

        Capsule::table('tbltodolist')
            ->where('id', $mapping->whmcs_todo_id)
            ->update(['status' => 'Completed']);
    }
}
