<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\CreditTransaction;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::withCount('voiceoverLogs')
            ->withSum('creditTransactions as total_spent', 'amount');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        $users = $query->latest()->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    public function show(User $user)
    {
        $user->load(['voiceoverLogs' => fn($q) => $q->latest()->take(20)]);
        $transactions = $user->creditTransactions()->latest()->paginate(15);

        return view('admin.users.show', compact('user', 'transactions'));
    }

    public function grantCredits(Request $request, User $user)
    {
        $validated = $request->validate([
            'npr_amount'  => 'required|numeric|min:1|max:999999',
            'description' => 'nullable|string|max:255',
        ]);

        $nprAmount = $validated['npr_amount'];
        $credits = \App\Models\ApiSetting::nprToCredits($nprAmount);

        $user->addCredits($credits);

        CreditTransaction::create([
            'user_id'     => $user->id,
            'amount'      => $credits,
            'type'        => 'admin_grant',
            'description' => $validated['description'] ?? "Admin granted Rs. {$nprAmount} ({$credits} credits)",
            'admin_id'    => auth()->id(),
        ]);

        return back()->with('success', "Granted Rs. {$nprAmount} ({$credits} credits) to {$user->name}");
    }

    public function toggleActive(User $user)
    {
        $user->update(['is_active' => !$user->is_active]);
        $status = $user->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "User {$user->name} has been {$status}");
    }

    public function updateRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => 'required|in:user,admin',
        ]);

        $user->update(['role' => $validated['role']]);

        return back()->with('success', "User {$user->name} role changed to {$validated['role']}");
    }
}
