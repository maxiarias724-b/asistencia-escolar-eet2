<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\SpecialtyRequest;
use App\Models\Specialty;
use Illuminate\Http\RedirectResponse;

class SpecialtyController extends Controller
{
    public function index()
    {
        return view('organization.specialties.index', [
            'specialties' => Specialty::withCount('courses')->ordered()->get(),
        ]);
    }

    public function store(SpecialtyRequest $request): RedirectResponse
    {
        $specialty = Specialty::create($request->safe()->only(['name', 'code']) + [
            'active' => $request->boolean('active', true),
        ]);

        return back()->with('status', "Especialidad «{$specialty->name}» creada.");
    }

    public function update(SpecialtyRequest $request, Specialty $specialty): RedirectResponse
    {
        $specialty->update($request->safe()->only(['name', 'code']) + [
            'active' => $request->boolean('active', true),
        ]);

        return back()->with('status', "Especialidad «{$specialty->name}» actualizada.");
    }

    public function toggleActive(Specialty $specialty): RedirectResponse
    {
        $specialty->update(['active' => ! $specialty->active]);

        return back()->with('status', $specialty->active ? "Especialidad «{$specialty->name}» activada." : "Especialidad «{$specialty->name}» desactivada lógicamente.");
    }
}
