# Todoist Sync — WHMCS Addon Module

**Version:** 2.1.1
**Author:** Host Dada
**Requires:** WHMCS, PHP with cURL, Todoist account

Mirrors your WHMCS To-Do list into Todoist in real time. Tasks are created, updated, and assigned in Todoist automatically whenever your WHMCS To-Do list changes. When a task is completed or deleted in Todoist, the corresponding WHMCS To-Do item is marked Completed via webhook.

---

## Features

- **Real-time sync** — WHMCS hooks fire on every todo add, edit, or status change and push the update to Todoist immediately
- **Scheduled sync** — Piggybacks on the WHMCS system cron to catch any items that may have been missed
- **Hash-based change detection** — Compares a SHA-256 hash of task fields before every update; skips the API call if nothing changed
- **Bidirectional completion** — Completing or deleting a task in Todoist marks the WHMCS To-Do as Completed via a signed webhook
- **Admin-to-assignee mapping** — Maps each WHMCS admin to a Todoist user ID so tasks are automatically assigned to the right person
- **Webhook signature verification** — Validates `X-Todoist-HMAC-SHA256` on every inbound webhook to prevent spoofing
- **Loop prevention** — A `TODOIST_SYNC_ORIGIN` constant guard stops webhook-triggered completions from re-triggering outbound syncs
- **Bulk sync** — Admin UI button to push all currently open To-Do items to Todoist in one go
- **WHMCS Module Log integration** — All API calls are logged via `logModuleCall` for easy debugging in the WHMCS admin panel

---

## File Structure

```
todoistsync/
├── todoistsync.php       # Addon entry point — config, activation, admin UI
├── hooks.php             # WHMCS event hooks + cron hook
├── webhook.php           # Todoist webhook receiver
└── lib/
    ├── TodoistClient.php  # HTTP client for the Todoist REST API v1
    └── SyncService.php    # Core sync logic (WHMCS ↔ Todoist)
```

---

## Database Tables

Created automatically on module activation:

| Table | Purpose |
|---|---|
| `mod_todoistsync_map` | Maps each `whmcs_todo_id` to a `todoist_task_id`; stores a `last_hash` for change detection and a `last_sync` timestamp |
| `mod_todoistsync_admin_map` | Maps each `whmcs_admin_id` to a `todoist_user_id` for task assignment |
| `mod_todoistsync_log` | General sync event log with `type`, `message`, and `created_at` |

---

## Installation

1. **Upload the module** — Copy the `todoistsync` folder into your WHMCS `modules/addons/` directory so the path becomes:
   ```
   /path/to/whmcs/modules/addons/todoistsync/
   ```

2. **Activate the addon** — In the WHMCS admin panel go to **Setup → Addon Modules**, find *Todoist Sync*, and click **Activate**. The three database tables are created automatically.

3. **Configure the addon** — Click **Configure** and fill in:
   - **Todoist API Token** — Your personal or service account API token from [todoist.com/app/settings/integrations/developer](https://todoist.com/app/settings/integrations/developer)
   - **Webhook Secret** — A strong random string you choose; this is used to verify inbound webhook signatures
   - **Enable Logging** — Check to write sync events to the WHMCS Module Log

4. **Register the webhook in Todoist**
   In your Todoist developer settings create a new webhook pointing to:
   ```
   https://yourdomain.com/modules/addons/todoistsync/webhook.php
   ```
   Subscribe to at minimum the `item:completed` and `item:deleted` events. Set the secret to match the value you entered in step 3.

5. **Map admins to Todoist users** — Go to **Addons → Todoist Sync** in the WHMCS admin panel. For each WHMCS admin enter their Todoist user ID and click **Save Mappings**.

6. **Run an initial bulk sync** (optional) — Click **Run Initial Bulk Sync** to push all currently open To-Do items to Todoist.

---

## How Sync Works

### WHMCS → Todoist

```
WHMCS To-Do event (add / edit / status change)
        ↓
hooks.php  →  todoistsync_safe_sync()
        ↓
SyncService::syncFromWhmcs($todoId)
        ↓
Compute SHA-256 hash of (title + description + duedate + status + admin)
        ↓
Hash unchanged?  →  skip (no API call)
        ↓
Task completed?  →  TodoistClient::closeTask()
Task not mapped yet?  →  TodoistClient::createTask()  →  store mapping row
Task already mapped?  →  TodoistClient::updateTask()  →  update hash + timestamp
```

**Fields synced to Todoist:**

| WHMCS field | Todoist field |
|---|---|
| `title` | `content` |
| `description` | `description` |
| `duedate` | `due_date` (formatted `Y-m-d`) |
| assigned admin (via admin map) | `assignee_id` |

### Todoist → WHMCS

```
Todoist fires webhook  (item:completed or item:deleted)
        ↓
webhook.php verifies HMAC-SHA256 signature
        ↓
SyncService::completeFromTodoist($todoistId)
        ↓
Looks up mod_todoistsync_map → gets whmcs_todo_id
        ↓
Updates tbltodolist.status = 'Completed'
```

### Scheduled / Cron Sync

The `AfterCronJob` hook fires every time the WHMCS system cron runs. It fetches all non-completed To-Do items and calls `syncFromWhmcs()` for each — the hash check prevents unnecessary API calls for items that haven't changed.

---

## Admin Panel

Navigate to **Addons → Todoist Sync** in the WHMCS admin panel to:

- **Map admins** — Enter the Todoist user ID for each WHMCS admin account so tasks can be auto-assigned
- **Bulk sync** — Push all currently open To-Do items to Todoist at any time

---

## Debugging

All outbound Todoist API calls are written to the WHMCS Module Log (`logModuleCall`) with the HTTP method, endpoint, request payload, and raw response. The API token is automatically redacted from log output.

To view logs: **Utilities → Logs → Module Log** → filter by module name `todoistsync`.

Webhook completions are also logged:
```
todoistsync | webhook_complete | <todoist_task_id> | Marked WHMCS ID <n> as Completed
```

---

## API Reference

### `TodoistClient`

| Method | Todoist endpoint | Description |
|---|---|---|
| `createTask($data)` | `POST /tasks` | Create a new task |
| `updateTask($id, $data)` | `POST /tasks/{id}` | Update task content, description, due date, or assignee |
| `closeTask($id)` | `POST /tasks/{id}/close` | Mark a task complete |
| `reopenTask($id)` | `POST /tasks/{id}/reopen` | Reopen a previously closed task |
| `getTask($id)` | `GET /tasks/{id}` | Fetch a single task |

All requests target **Todoist REST API v1** (`https://api.todoist.com/api/v1/`) and authenticate with a Bearer token.

---

## Security Notes

- The webhook endpoint verifies the `X-Todoist-HMAC-SHA256` header against an HMAC-SHA256 of the raw request body using your configured secret. Requests with an invalid or missing signature receive a `401 Unauthorized` response.
- The API token is passed only in memory and is redacted in all WHMCS Module Log entries.
- The `TODOIST_SYNC_ORIGIN` constant prevents webhook-triggered WHMCS updates from firing outbound sync hooks, avoiding infinite loops.

---

## Requirements

- WHMCS (any version with addon module support and the `AfterCronJob` hook)
- PHP 7.4+ with the `curl` extension enabled
- A publicly accessible HTTPS URL for the webhook endpoint
- A Todoist account with API access (any plan that exposes the REST API)

---

## License

Proprietary — Host Dada. All rights reserved.
