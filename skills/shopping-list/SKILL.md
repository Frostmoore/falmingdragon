---
name: shopping-list
description: "Manage grocery and shopping lists with categories and bought-status tracking"
version: "1.0.0"
tools_required: ["shopping_add", "shopping_items", "shopping_bought", "shopping_clear"]
---

# Shopping List Skill

## Overview
Manages one or more shopping lists stored in the FlamingDragon database. Items can be categorized (produce, dairy, etc.), have quantities and units, and be marked as bought.

## Instructions

### Adding an Item
Use `shopping_add` with:
- `name` — item name (required)
- `list_name` — which list (default: `default`)
- `category` — e.g. `produce`, `dairy`, `bakery`, `household` (optional)
- `quantity` — numeric amount (default: 1)
- `unit` — e.g. `kg`, `L`, `pz`, `confezione` (optional)

### Listing Items
Use `shopping_items` with:
- `list_name` — which list (default: `default`)
- `show_bought` — `true` to include bought items (default: `false`)

### Marking as Bought
Use `shopping_bought` with:
- `id` — item ID from `shopping_items`

### Clearing Bought Items
Use `shopping_clear` with:
- `list_name` — removes all bought items from this list (default: `default`)

## Examples

User: "Add 2kg of tomatoes and 1L of milk to the shopping list"
→ `shopping_add` name="Tomatoes" quantity=2 unit="kg"
→ `shopping_add` name="Milk" quantity=1 unit="L"

User: "What's on my grocery list?"
→ `shopping_items` list_name="default"

User: "I bought the milk (item 3)"
→ `shopping_bought` id=3

User: "Clear the bought items"
→ `shopping_clear` list_name="default"
