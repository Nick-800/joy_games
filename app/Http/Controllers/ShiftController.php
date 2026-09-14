<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\User;
use App\Services\Shifts\ShiftLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShiftController extends Controller
{
    public function __construct(
        protected ShiftLedger $shiftLedger
    ) {}

    public function open(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'opening_float_millimes' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $this->shiftLedger->openShift($user, $validated['opening_float_millimes'], $validated['notes'] ?? null);

        return back()->with('success', 'Shift opened successfully');
    }

    public function close(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'closing_cash_counted_millimes' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $shift = Shift::active();
        if (! $shift) {
            return back()->withErrors(['shift' => 'No active shift found to close.']);
        }

        $this->shiftLedger->closeShift($shift, $validated['closing_cash_counted_millimes'], $validated['notes'] ?? null);

        return back()->with('success', 'Shift closed successfully. Blind drop report generated.');
    }

    public function switchCashier(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pin_code' => ['required', 'string', 'size:4'],
        ]);

        $user = User::where('pin_code', $validated['pin_code'])->where('is_active', true)->first();

        if (! $user) {
            return back()->withErrors(['pin_code' => 'Invalid PIN code entered.']);
        }

        Auth::login($user);

        return back()->with('success', "Switched active staff to {$user->name}");
    }
}
