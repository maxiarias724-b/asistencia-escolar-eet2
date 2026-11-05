<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\ScopeRequest;
use App\Models\Scope;
use Illuminate\Http\RedirectResponse;

class ScopeController extends Controller
{
    public function index()
    {
        return view('organization.scopes.index', [
            'scopes' => Scope::withCount(['activities', 'courseAssignments'])->ordered()->get(),
        ]);
    }

    public function store(ScopeRequest $request): RedirectResponse
    {
        $scope = Scope::create($request->safe()->only(['name', 'slug']) + [
            'active' => $request->boolean('active', true),
        ]);

        return back()->with('status', "Ámbito «{$scope->name}» creado y disponible para asignaciones y asistencia.");
    }

    public function update(ScopeRequest $request, Scope $scope): RedirectResponse
    {
        $oldSlug = $scope->slug;
        $scope->update($request->safe()->only(['name', 'slug']) + [
            'active' => $request->boolean('active', true),
        ]);

        if ($oldSlug !== $scope->slug) {
            $scope->courseAssignments()->update(['activity_type' => $scope->slug]);
            $scope->studentAttendances()->update(['activity_type' => $scope->slug]);
        }

        return back()->with('status', "Ámbito «{$scope->name}» actualizado.");
    }

    public function toggleActive(Scope $scope): RedirectResponse
    {
        $scope->update(['active' => ! $scope->active]);

        return back()->with('status', $scope->active ? "Ámbito «{$scope->name}» activado." : "Ámbito «{$scope->name}» desactivado lógicamente.");
    }
}
