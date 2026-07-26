<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Location;
use Illuminate\Support\Facades\Validator;
use App\Services\CacheHelper;

class LocationController extends Controller
{
    /**
     * Display a listing of locations.
     */
    public function index()
    {
        $locations = CacheHelper::remember(['locations'], 'locations_all', 3600, function () {
            return Location::all();
        });
        return response()->json($locations, 200);
    }

    /**
     * Store a newly created location.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:locations,name|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $location = Location::create($request->only(['name', 'description']));

        CacheHelper::flush(['locations']);

        return response()->json([
            'message' => 'Location created successfully',
            'location' => $location
        ], 201);
    }

    /**
     * Display the specified location.
     */
    public function show($id)
    {
        $location = CacheHelper::remember(['locations'], "location_show_{$id}", 3600, function () use ($id) {
            return Location::find($id);
        });

        if (!$location) {
            return response()->json(['message' => 'Location not found'], 404);
        }

        return response()->json($location, 200);
    }

    /**
     * Update the specified location.
     */
    public function update(Request $request, $id)
    {
        $location = Location::find($id);

        if (!$location) {
            return response()->json(['message' => 'Location not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|unique:locations,name,' . $id . '|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $location->update($request->only(['name', 'description']));

        CacheHelper::flush(['locations', 'items']);

        return response()->json([
            'message' => 'Location updated successfully',
            'location' => $location
        ], 200);
    }

    /**
     * Remove the specified location.
     */
    public function destroy($id)
    {
        $location = Location::find($id);

        if (!$location) {
            return response()->json(['message' => 'Location not found'], 404);
        }

        if ($location->items()->exists()) {
            return response()->json([
                'message' => 'Cannot delete location as it is currently linked to reports.'
            ], 400);
        }

        $location->delete();

        CacheHelper::flush(['locations', 'items']);

        return response()->json([
            'message' => 'Location deleted successfully'
        ], 200);
    }
}
