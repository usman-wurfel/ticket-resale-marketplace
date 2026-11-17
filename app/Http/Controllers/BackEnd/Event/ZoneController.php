<?php

namespace App\Http\Controllers\BackEnd\Event;

use App\Http\Controllers\Controller;
use App\Models\Event\EventLocation;
use App\Models\Event\EventZone;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ZoneController extends Controller
{
    public function index(Request $request)
    {
        $lang = Language::where('code', $request->language)->first();
        if (!$lang) {
            $lang = Language::where('is_default', 1)->firstOrFail();
        }
        $data['langs'] = Language::all();
        $data['selectedLanguageId'] = (int) $lang->id;
        $data['selectedLangCode'] = $lang->code;

        $data['zones'] = EventZone::with(['location' => function ($q) use ($lang) {
            $q->where('language_id', $lang->id);
        }])
            ->orderBy('created_at', 'desc')
            ->where('language_id', $lang->id)
            ->paginate(10);

        $data['locations'] = DB::table('event_locations')
            ->where('language_id', $lang->id)
            ->where('status', 1)
            ->orderBy('serial_number', 'asc')
            ->get();

        return view('backend.event.specification.zone.index', $data);
    }

    public function edit($id)
    {
        $data['zone'] = EventZone::findOrFail($id);
        $langId = (int) ($data['zone']->language_id ?? 0);
        $data['locations'] = EventLocation::where('language_id', $langId)
            ->orderBy('name', 'asc')
            ->get(['id', 'name']);

        return view('backend.event.specification.zone.edit', $data);
    }

    public function store(Request $request)
    {
        $rules = [
            'language_id' => 'required',
            'location_id' => 'required',
            'name' => 'required',
            'status' => 'required',
            'serial_number' => 'required|numeric|min:0',
        ];

        $messages = [];
        $messages['language_id.required'] = __('The language field is required.');
        $messages['location_id.required'] = __('The location field is required.');
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->getMessageBag()
            ], 400);
        }

        $location = EventLocation::where('id', $request->location_id)
            ->where('language_id', $request->language_id)
            ->first();
        if (!$location) {
            return response()->json([
                'errors' => [
                    'location_id' => [__('Please choose a location that belongs to the selected language.')],
                ],
            ], 400);
        }

        $zone = new EventZone();
        $zone->language_id = $request->language_id;
        $zone->location_id = $request->location_id;
        $zone->name = $request->name;
        $zone->slug = createSlug($request->name);
        $zone->status = $request->status;
        $zone->serial_number = $request->serial_number;
        $zone->save();

        session()->flash('success', 'Added Successfully');
        return response()->json(['status' => 'success'], 200);
    }

    public function update(Request $request)
    {
        $rules = [
            'location_id' => 'required',
            'name' => 'required',
            'status' => 'required',
            'serial_number' => 'required|numeric|min:0',
        ];
        $messages = [];
        $messages['location_id.required'] = __('The location field is required.');
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->getMessageBag()
            ], 400);
        }

        $zone = EventZone::findOrFail($request->id);
        $zone->location_id = $request->location_id;
        $zone->name = $request->name;
        $zone->slug = createSlug($request->name);
        $zone->status = $request->status;
        $zone->serial_number = $request->serial_number;
        $zone->save();

        session()->flash('success', 'Updated Successfully');
        return response()->json(['status' => 'success'], 200);
    }

    public function destroy($id)
    {
        $zone = EventZone::where('id', $id)->first();
        if ($zone) {
            $zone->delete();
        }

        return redirect()->back()->with('success', 'Deleted Successfully');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->ids;

        foreach ($ids as $id) {
            $zone = EventZone::where('id', $id)->first();
            if ($zone) {
                $zone->delete();
            }
        }
        session()->flash('success', 'Deleted Successfully');
        return response()->json(['status' => 'success'], 200);
    }
}
