<?php

namespace App\Http\Controllers\Director;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /** Visor institucional de la tabla `audit_logs` (quién hizo qué, cuándo y desde qué IP). */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'action' => ['nullable', 'string', 'in:created,updated,deleted,login,logout'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $logs = AuditLog::query()
            ->with('user')
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['action'] ?? null, fn ($q, $v) => $q->where('action', $v))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('description', 'like', "%{$term}%"))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('director.audit-logs.index', [
            'logs' => $logs,
            'users' => User::orderBy('name')->get(['id', 'name', 'username']),
            'filters' => $filters,
        ]);
    }
}
