<?php

namespace App\Http\Controllers;

use App\Models\Donation;
use Illuminate\Http\Request;

class DonationController extends Controller
{
    public function storeDonation(Request $request)
    {
        $data = $request->validate([
            'donor_id' => 'required|exists:donors,id',
            'donation_type' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'description' => 'nullable|string',
            'received_at' => 'nullable|date',
        ]);

        Donation::create($data);

        return redirect()->route('donors.index')->with('success', 'Donation recorded successfully.');
    }
}
