# Admin Guide: Managing Item Categories & Units

**Module:** In-Kind Inventory (Gina)
**Related Requirement:** A6 (Configure Item Categories)
**Access:** Admin-only for changes; Admin and Staff can view categories and units.

## What this replaces

Inventory categories and units used to live as hardcoded arrays in `config/inventory.php`. They are now database-backed reference tables (`inventory_categories`, `inventory_units`) so an admin can add, rename, or retire them without a code deploy. `config/inventory.php` still exists but only backs the legacy `donations` config item lists — the live inventory item form now reads from these two tables.

## Where to find it

1. Go to **In-Kind Inventory** in the sidebar.
2. Click **Manage Categories** (top of the inventory index page — visible to any role, but the underlying form only submits successfully for admins).
3. This opens `/inventory/categories`, showing two side-by-side panels: **Categories** and **Units**.

## Adding a category or unit

1. On the relevant panel (Categories or Units), open the add form.
2. Fill in:
   - **Key** — a short, unique machine identifier (e.g. `medical_supplies`). Must be unique among categories (or units) and non-empty.
   - **Label** — the human-readable name shown throughout the app (e.g. `Medical Supplies`).
   - **Active** — whether it should appear as a selectable option on the inventory item form. Defaults to active.
3. Submit. On success you'll see "Category created successfully." (or "Unit created successfully.").

## Editing a category or unit

1. Click **Edit** on the row you want to change.
2. Update the key, label, and/or active flag.
3. Submit — the change applies immediately everywhere the category/unit is referenced (item creation form, filter tabs, table display).

Note: changing a `key` does **not** retroactively update existing inventory items that already used the old key value — items store the key at the time they were created/updated.

## Deactivating vs. deleting

- **Deactivate** (edit → uncheck Active) removes it from the selectable list on the inventory item form but keeps it visible in the admin table and keeps existing items referencing it intact. This is the recommended way to retire a category/unit that's still in use.
- **Delete** permanently removes the row. The system blocks deletion if any `inventory_items` row currently references that category/unit's key — you'll see: *"This category/unit is used by existing inventory items. Deactivate it instead of deleting."* Only delete categories/units that were never used, or that have had every referencing item reassigned first.

## Authorization summary

| Action | admin | staff |
|---|---|---|---|
| View the Manage Categories page | ✅ | ❌ |
| Create category/unit | ✅ | ❌ |
| Edit category/unit | ✅ | ❌ |
| Delete category/unit | ✅ | ❌ |

Enforced server-side by `InventoryCategoryPolicy` and `InventoryUnitPolicy` — Staff cannot open the management page or hit create/update/destroy routes directly; these requests receive a `403`, not just a hidden button. Staff can still select active categories and units in operational forms.

## Data model

- `inventory_categories` and `inventory_units`: `id`, `key` (unique), `label`, `is_active`, timestamps.
- `ManageInventoryReferenceItem` service handles create/update/delete for both models generically (they share the same shape).
- Deletion safety check queries `inventory_items` on the model's configured column (`category` for `InventoryCategory`, `unit` for `InventoryUnit`) via `inventoryItemColumn()`.
