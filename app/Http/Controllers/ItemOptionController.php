<?php

namespace App\Http\Controllers;

use App\Models\ItemOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ItemOptionController extends Controller
{
    public function index()
    {
        // Get all active item options with their group
        $options = ItemOption::with('group')
          
            ->get();
    
        // Optionally, convert icon paths to full URLs
        $options->transform(function ($option) {
            if ($option->icon) {
                $option->icon = asset('storage/' . $option->icon);
            }
            return $option;
        });
    
        return response()->json($options);
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_option_group_id' => 'required|exists:item_option_groups,id',
            'name' => 'required|string|max:100',
            'price_adjust_cents' => 'numeric|min:0',
            'icon' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        if ($request->hasFile('icon')) {
            $path = $this->uploadImage($request->file('icon'), 'item_option_icons');
            $validated['icon'] = $path;
        }
        

        $option = ItemOption::create($validated);
        return response()->json($option, 201);
    }

    public function show($id)
    {
        $option = ItemOption::with('group')->findOrFail($id);
         // Convert icon to full URL if it exists
        if ($option->icon) {
            $option->icon = asset('storage/' . $option->icon);
        }
        return response()->json($option);
    }

    public function getByGroup($groupId)
    {
        $options = ItemOption::with('group')
            ->where('item_option_group_id', $groupId)
            ->get();

        // Add full URLs for icons
        $options->transform(function ($option) {
            if ($option->icon) {
                $option->icon = asset('storage/' . $option->icon);
            }
            return $option;
        });

        return response()->json($options);
    }



    public function update(Request $request, $id)
    {
        $option = ItemOption::findOrFail($id);
    
        $validated = $request->validate([
            'item_option_group_id' => 'sometimes|exists:item_option_groups,id',
            'name' => 'sometimes|string|max:100',
            'price_adjust_cents' => 'sometimes|numeric|min:0',
            'icon' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
            'is_active' => 'sometimes|in:0,1',
        ]);
    
       // Handle image upload
        if ($request->hasFile('icon') && $request->file('icon')->isValid()) {

            // Delete old icon if it exists
            if (!empty($option->icon) && Storage::disk('public')->exists($option->icon)) {
                Storage::disk('public')->delete($option->icon);
            }

            // Upload new icon to item_option_icons folder
            $validated['icon'] = $this->uploadImage($request->file('icon'), 'item_option_icons');
        }

        // Update model
        $option->update($validated);
    
        return response()->json($option);
    }
    
    public function destroy($id)
    {
        $option = ItemOption::findOrFail($id);
        $option->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }


    private function uploadImage($file, $directory = 'item_option_icons')
    {
        $fileName = 'ItemOptionicon_' . Str::random(20) . '.' . $file->getClientOriginalExtension();
    
        return $file->storeAs($directory, $fileName, 'public');
    }
    

}
