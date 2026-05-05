---
name: todo
description: "Manage personal to-do lists: create, list, complete, and delete tasks"
version: "1.0.0"
tools_required: ["todo_create", "todo_list", "todo_complete", "todo_delete"]
---

# Todo Skill

## Overview
Allows the agent to manage personal to-do lists stored in the FlamingDragon database. Supports multiple named lists, priorities, and due dates.

## Instructions

### Creating a Task
Use `todo_create` with:
- `title` — task description (required)
- `list_name` — which list (default: `default`)
- `priority` — `low`, `normal`, or `high` (default: `normal`)
- `due_at` — optional ISO 8601 date, e.g. `2026-04-15`
- `notes` — optional extra notes

### Listing Tasks
Use `todo_list` with:
- `list_name` — which list (default: `default`; use `all` for all lists)
- `show_done` — `true` to include completed tasks (default: `false`)

### Completing a Task
Use `todo_complete` with:
- `id` — numeric task ID (from `todo_list`)

### Deleting a Task
Use `todo_delete` with:
- `id` — numeric task ID

## Examples

User: "Add buy milk to my shopping list"
→ `todo_create` title="Buy milk" list_name="shopping"

User: "What do I need to do today?"
→ `todo_list` list_name="default"

User: "Mark task 3 as done"
→ `todo_complete` id=3

User: "Show me all my lists"
→ `todo_list` list_name="all"
