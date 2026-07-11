<?php

namespace App\Http\Controllers;

use App\Models\CashTransaction;
use App\Models\InventoryItem;
use Illuminate\Http\Request;

class InventoryCashController extends Controller
{
    public function index()
    {
        $inventoryItems = InventoryItem::latest('received_at')->get();
        $cashTransactions = CashTransaction::latest('received_at')->get();

        return view('role-workflows', compact('inventoryItems', 'cashTransactions'));
    }

    public function storeInventory(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
            'description' => 'nullable|string',
            'received_at' => 'nullable|date',
            'status' => 'nullable|string|max:255',
        ]);

        InventoryItem::create($data);

        return redirect()->route('workflows.index')->with('success', 'Inventory item recorded.');
    }

    public function storeCash(Request $request)
    {
        $data = $request->validate([
            'donor_name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'purpose' => 'required|string|max:255',
            'received_at' => 'nullable|date',
            'status' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        CashTransaction::create($data);

        return redirect()->route('workflows.index')->with('success', 'Cash transaction recorded.');
    }
}
