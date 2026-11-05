<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\ActivityRequest;
use App\Models\Activity;
use App\Models\Scope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $scopeId = $request->integer('scope_id') ?: null;

        return view('organization.activities.index', [
            'activities' => Activity::with('scopeRef')
                ->when($scopeId, fn ($q) => $q->where('scope_id', $scopeId))
                ->orderBy('scope_id')
                ->ordered()
                ->get(),
            'scopes' => Scope::ordered()->get(),
            'scopeId' => $scopeId,
        ]);
    }

    public function store(ActivityRequest $request): RedirectResponse
    {
        $activity = Activity::create($request->safe()->only(['scope_id', 'name']) + [
            'active' => $request->boolean('active', true),
        ]);

        return back()->with('status', "Actividad «{$activity->name}» creada.");
    }

    public function update(ActivityRequest $request, Activity $activity): RedirectResponse
    {
        $activity->update($request->safe()->only(['scope_id', 'name']) + [
            'active' => $request->boolean('active', true),
        ]);

        return back()->with('status', "Actividad «{$activity->name}» actualizada.");
    }

    public function toggleActive(Activity $activity): RedirectResponse
    {
        $activity->update(['active' => ! $activity->active]);

        return back()->with('status', $activity->active ? "Actividad «{$activity->name}» activada." : "Actividad «{$activity->name}» desactivada lógicamente.");
    }
}
