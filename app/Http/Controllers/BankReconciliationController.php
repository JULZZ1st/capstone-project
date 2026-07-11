<?php

namespace App\Http\Controllers;

use App\Models\BankReconciliation;
use Illuminate\Http\Request;

class BankReconciliationController extends Controller
{
    public function index()
    {
        $reconciliations = BankReconciliation::latest('date')->get();

        return view('bank-reconciliation', compact('reconciliations'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'opening_balance' => 'required|numeric',
            'cash_in' => 'required|numeric',
            'cash_out' => 'required|numeric',
            'notes' => 'nullable|string',
        ]);

        $data['closing_balance'] = $data['opening_balance'] + $data['cash_in'] - $data['cash_out'];

        BankReconciliation::create($data);

        return redirect()->route('bank-reconciliation.index')->with('success', 'Bank reconciliation saved.');
    }
}
