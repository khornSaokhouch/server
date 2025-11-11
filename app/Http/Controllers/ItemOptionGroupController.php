<?php

namespace App\Http\Controllers;

use App\Models\ItemOptionGroup;
use Illuminate\Http\Request;

class ItemOptionGroupController extends Controller
{
    public function index()
    {
        $groups = ItemOptionGroup::with(['options' => function ($query) {
            $query->where('is_active', 1);
        }])->get();
    
        // Optionally, convert icon paths to full URLs
        $groups->transform(function ($group) {
            $group->options->transform(function ($option) {
                if ($option->icon) {
                    $option->icon = asset('storage/' . $option->icon);
                }
                return $option;
            });
            return $group;
        });
    
        return response()->json($groups);
    }
    

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',//e.g. "Sugar Level"
            'type' => 'required|in:select,multiple', 
            'is_required' => 'boolean',
        ]);

        $group = ItemOptionGroup::create($validated);
        return response()->json($group, 201);
    }

    public function show($id)
    {
        $group = ItemOptionGroup::with('options')->findOrFail($id);
        return response()->json($group);
    }

    public function update(Request $request, $id)
    {
        $group = ItemOptionGroup::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'type' => 'sometimes|required|in:select,multiple',
            'is_required' => 'sometimes|boolean',
        ]);

        $group->update($validated);
        return response()->json($group);
    }

    public function destroy($id)
    {
        $group = ItemOptionGroup::findOrFail($id);
        $group->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
