<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function todoistsync_config()
{
    return [
        'name' => 'Todoist Sync',
        'description' => 'WHMCS To-Do → Todoist controlled mirror sync.',
        'version' => '2.1.1',
        'author' => 'Host Dada',
        'fields' => [
            'api_token' => [
                'FriendlyName' => 'Todoist API Token',
                'Type' => 'password',
                'Size' => '60',
            ],
            'webhook_secret' => [
                'FriendlyName' => 'Webhook Secret',
                'Type' => 'text',
                'Size' => '60',
            ],
            'enable_logging' => [
                'FriendlyName' => 'Enable Logging',
                'Type' => 'yesno',
                'Description' => 'Enable sync logs'
            ],
        ]
    ];
}

function todoistsync_activate()
{
    $schema = \WHMCS\Database\Capsule::schema();

    if (!$schema->hasTable('mod_todoistsync_map')) {
        $schema->create('mod_todoistsync_map', function ($table) {
            $table->increments('id');
            $table->integer('whmcs_todo_id')->unique();
            $table->string('todoist_task_id');
            $table->string('last_hash')->nullable();
            $table->timestamp('last_sync')->nullable();
        });
    }

    if (!$schema->hasTable('mod_todoistsync_admin_map')) {
        $schema->create('mod_todoistsync_admin_map', function ($table) {
            $table->increments('id');
            $table->integer('whmcs_admin_id')->unique();
            $table->string('todoist_user_id');
        });
    }

    if (!$schema->hasTable('mod_todoistsync_log')) {
        $schema->create('mod_todoistsync_log', function ($table) {
            $table->increments('id');
            $table->string('type');
            $table->text('message');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    return ['status' => 'success', 'description' => 'Todoist Sync activated'];
}

function todoistsync_deactivate()
{
    return ['status' => 'success'];
}

function todoistsync_output($vars)
{
    require_once __DIR__ . '/lib/SyncService.php';

    echo '<h2>Todoist Admin Mapping</h2>';

    if (isset($_POST['save_mapping'])) {
        foreach ($_POST['mapping'] as $adminId => $todoistId) {
            \WHMCS\Database\Capsule::table('mod_todoistsync_admin_map')
                ->updateOrInsert(
                    ['whmcs_admin_id' => $adminId],
                    ['todoist_user_id' => trim($todoistId)]
                );
        }
        echo '<div class="successbox">Mappings Saved</div>';
    }

    if (isset($_POST['bulk_sync'])) {
        $tasks = \WHMCS\Database\Capsule::table('tbltodolist')
            ->where('status', '!=', 'Completed')
            ->get();

        $sync = new TodoistSyncService();
        foreach ($tasks as $task) {
            $sync->syncFromWhmcs($task->id);
        }

        echo '<div class="successbox">Bulk Sync Complete</div>';
    }

    $admins = \WHMCS\Database\Capsule::table('tbladmins')->get();
    $existing = \WHMCS\Database\Capsule::table('mod_todoistsync_admin_map')
        ->pluck('todoist_user_id', 'whmcs_admin_id');

    echo '<form method="post"><table class="form" width="100%">';

    foreach ($admins as $admin) {
        $value = $existing[$admin->id] ?? '';
        echo "<tr>
            <td>{$admin->firstname} {$admin->lastname}</td>
            <td><input type='text' name='mapping[{$admin->id}]' value='{$value}' placeholder='Todoist User ID' style='width:300px'></td>
        </tr>";
    }

    echo '</table>';
    echo '<p><input type="submit" name="save_mapping" value="Save Mappings" class="btn btn-primary"></p>';
    echo '</form><hr>';

    echo '<form method="post">';
    echo '<input type="submit" name="bulk_sync" value="Run Initial Bulk Sync" class="btn btn-success">';
    echo '</form>';
}
