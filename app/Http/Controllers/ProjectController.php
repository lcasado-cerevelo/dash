
public function store(Request $request)
{
    $data = $request->validate([
        // ...tus otras reglas
        'customer_id' => ['nullable', 'exists:customers,id'],
    ]);

    $project = new \App\Models\Project();
    // ...asignar otros campos
    $project->customer_id = $data['customer_id'] ?? null;
    $project->save();

    return redirect()->route('projects.index')->with('success', 'Proyecto creado.');
}

public function update(Request $request, $id)
{
    $data = $request->validate([
        // ...tus otras reglas
        'customer_id' => ['nullable', 'exists:customers,id'],
    ]);

    $project = \App\Models\Project::findOrFail($id);
    // ...asignar otros campos
    $project->customer_id = $data['customer_id'] ?? null;
    $project->save();

    return redirect()->route('projects.index')->with('success', 'Proyecto actualizado.');
}
