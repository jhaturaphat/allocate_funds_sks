<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserApprovalController extends Controller
{
    public function pending(): View
    {
        $users = User::query()
            ->where('is_approved', false)
            ->where('is_admin', false)
            ->orderBy('created_at')
            ->paginate(20);

        return view('admin.users.pending', compact('users'));
    }

    public function approve(User $user): RedirectResponse
    {
        abort_if($user->is_admin, 403, 'ไม่สามารถอนุมัติบัญชีผู้ดูแลระบบผ่านหน้านี้');

        DB::transaction(function () use ($user) {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $lockedUser->forceFill([
                'is_approved' => true,
                'approved_at' => now(),
                'approved_by' => Auth::id(),
            ])->save();
        });

        return redirect()
            ->route('admin.users.pending')
            ->with('success', "อนุมัติบัญชี {$user->username} เรียบร้อยแล้ว");
    }
}
