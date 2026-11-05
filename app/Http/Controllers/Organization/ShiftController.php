<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\ShiftRequest;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;

class ShiftController extends Controller
{
    public function index()
    {
        return view('organization.shifts.index', [
            'shifts' => Shift::withCount('courses')->ordered()->get(),
        ]);
    }

    public function store(ShiftRequest $request): RedirectResponse
    {
        $shift = Shift::create($request->safe()->only(['name', 'slug']) + [
            'active' => $request->boolean('active', true),
        ]);

        return back()->with('status', "Turno «{$shift->name}» creado.");
    }

    public function update(ShiftRequest $request, Shift $shift): RedirectResponse
    {
        $oldSlug = $shift->slug;
        $shift->update($request->safe()->only(['name', 'slug']) + [
            'active' => $request->boolean('active', true),
        ]);

        if ($oldSlug !== $shift->slug) {
            $shift->courses()->update(['shift' => $shift->slug]);
        }

        return back()->with('status', "Turno «{$shift->name}» actualizado.");
    }

    public function toggleActive(Shift $shift): RedirectResponse
    {
        $shift->update(['active' => ! $shift->active]);

        return back()->with('status', $shift->active ? "Turno «{$shift->name}» activado." : "Turno «{$shift->name}» desactivado lógicamente.");
    }
}
