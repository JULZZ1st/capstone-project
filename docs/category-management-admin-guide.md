# Admin Guide: Managing Item Categories & Units

**Module:** In-Kind Inventory (Gina)
**Related Requirement:** A6 (Configure Item Categories)
**Access:** Admin-only for changes; Admin and Staff can view categories and units.

## What this replaces

Inventory categories and units used to live as hardcoded arrays in `config/inventory.php`. They are now database-backed reference tables (`inventory_categories`, `inventory_units`) so an admin can add, rename, or retire them without a code deploy. `config/inventory.php` still exists but only backs the legacy `donations` config item lists — the live inventory item form now reads from these two tables.

## Where to find it

1. Go to **In-Kind Inventory** in the sidebar.
2. Click **Manage Categories** (top of the inventory index page — visible to any role, but the underlying form only submits successfully for admins).
3. This opens `/inventory/categories`, showing three tabbed panels: **Categories**, **Subcategories**, and **Units**.

## Adding a category, subcategory, or unit

1. On the relevant panel, open the add form.
2. Fill in:
   - **Key** — a short, unique machine identifier (e.g. `medical_supplies`). Must be unique among categories (or units, or subcategories within the same parent category) and non-empty.
   - **Label** — the human-readable name shown throughout the app (e.g. `Medical Supplies`).
   - **Parent Category** (subcategories only) — which category the subcategory belongs to.
   - **Active** — whether it should appear as a selectable option on the inventory item form. Defaults to active. Only shown on the Add form — see "Deactivating vs. deleting" below for how active status is changed afterwards.
3. Submit. On success you'll see a confirmation message naming the entity ("Category created successfully.", "Subcategory created successfully.", or "Unit created successfully.").

## Editing a category, subcategory, or unit

1. Click **Edit** on the row you want to change. This opens a centered modal with the edit form.
2. Update the key and/or label (and, for subcategories, the parent category).
3. Submit — the change applies immediately everywhere the category/subcategory/unit is referenced (item creation form, filter tabs, table display).

Note: changing a `key` does **not** retroactively update existing records that already used the old key value — they store the key at the time they were created/updated. The Edit form no longer has an Active checkbox — see below.

## Deactivating vs. deleting

Each row shows how many records depend on it (subcategories and inventory items for a category; inventory items for a unit; inventory items and donation records for a subcategory) so you can see the impact before acting.

- **Deactivate** — a dedicated action, available whenever a record is active and has at least one dependent. Clicking it opens a confirmation dialog naming the record and its dependency counts; confirming removes it from the selectable list on new entries while keeping it visible on existing records, intact. This is the only way to retire a record that's still in use — Delete is not offered for it.
- **Reactivate** — available whenever a record is currently inactive ("Retired"). Clicking it opens a confirmation dialog; confirming makes the record selectable again for new entries.
- **Delete** — only ever offered for records with zero dependents (confirmed via the same dependency counts). Confirming permanently removes the row. Attempting to delete a record with dependents (including, for a category, having any subcategories at all — even unused ones, since removing the category would otherwise cascade-delete them) is blocked server-side with an error explaining to deactivate instead.

Active status can **only** be changed via Deactivate/Reactivate — the Edit form's Active checkbox was removed to keep this a single, confirmed path (it remains on the Add form, since a brand-new record has no history to protect).

## Authorization summary

| Action | admin | staff |
|---|---|---|---|
| View the Manage Categories page | ✅ | ❌ |
| Create category/subcategory/unit | ✅ | ❌ |
| Edit category/subcategory/unit | ✅ | ❌ |
| Deactivate/Reactivate category/subcategory/unit | ✅ | ❌ |
| Delete category/subcategory/unit | ✅ | ❌ |

Enforced server-side by `InventoryCategoryPolicy` and `InventoryUnitPolicy` (subcategories reuse their parent category's policy) — Staff cannot open the management page or hit any mutation route directly; these requests receive a `403`, not just a hidden button. Staff can still select active categories, subcategories, and units in operational forms.

## Data model

- `inventory_categories`, `inventory_subcategories`, and `inventory_units`: `id`, `key` (unique within scope), `label`, `is_active`, timestamps. Subcategories additionally have `inventory_category_id` (cascades on category delete).
- `ManageInventoryReferenceItem` service handles create/update/delete/deactivate/reactivate for categories and units generically (they share the same shape); `InventorySubcategoryController` has its own equivalent logic.
- Deletion safety checks: a category blocks on any `inventory_items` row referencing its key (`inventoryItemColumn()`) **or** on having any subcategory at all; a unit blocks the same way on its key; a subcategory blocks on `inventory_items.inventory_subcategory_id` **or** on a matching `contribution_items` (donation) row.
