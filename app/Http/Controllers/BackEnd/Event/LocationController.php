<?php

namespace App\Http\Controllers\BackEnd\Event;

use App\Http\Controllers\Controller;
use App\Models\Event\EventLocation;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LocationController extends Controller
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
        $data['locations'] = DB::table('event_locations')
            ->where('language_id', $lang->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('backend.event.specification.location.index', $data);
    }

    public function store(Request $request)
    {
        $rules = [
            'language_id' => 'required',
            'name' => 'required',
            'status' => 'required',
            'serial_number' => 'required|numeric|min:0',
        ];
        $messages = [];
        $messages['language_id.required'] = __('The language field is required.');
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->getMessageBag()
            ], 400);
        }

        $location = new EventLocation();
        $location->language_id = $request->language_id;
        $location->name = $request->name;
        $location->slug = createSlug($request->name);
        $location->status = $request->status;
        $location->serial_number = $request->serial_number;
        $location->save();

        session()->flash('success', 'Added Successfully');
        return response()->json(['status' => 'success'], 200);
    }

    public function update(Request $request)
    {
        $rules = [
            'name' => 'required',
            'status' => 'required',
            'serial_number' => 'required|numeric|min:0',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->getMessageBag()
            ], 400);
        }

        $location = EventLocation::findOrFail($request->id);
        $location->name = $request->name;
        $location->slug = createSlug($request->name);
        $location->status = $request->status;
        $location->serial_number = $request->serial_number;
        $location->save();

        session()->flash('success', 'Updated Successfully');
        return response()->json(['status' => 'success'], 200);
    }

    public function destroy($id)
    {
        $location = EventLocation::where('id', $id)->first();
        if ($location) {
            $location->zones()->delete();
            $location->delete();
        }

        return redirect()->back()->with('success', 'Deleted Successfully');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->ids;

        foreach ($ids as $id) {
            $location = EventLocation::where('id', $id)->first();
            if ($location) {
                $location->zones()->delete();
                $location->delete();
            }
        }
        session()->flash('success', 'Deleted Successfully');
        return response()->json(['status' => 'success'], 200);
    }
}
