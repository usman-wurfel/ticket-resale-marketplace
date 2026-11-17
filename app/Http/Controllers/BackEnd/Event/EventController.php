<?php

namespace App\Http\Controllers\BackEnd\Event;

use Carbon\Carbon;
use App\Models\City;
use App\Models\Event;
use App\Models\State;
use App\Models\Country;
use App\Models\Language;
use App\Models\Organizer;
use App\Models\Event\Ticket;
use App\Models\Event\ResaleTicket;
use Illuminate\Http\Request;
use App\Models\Event\EventCity;
use App\Models\Event\EventDates;
use App\Models\Event\EventImage;
use App\Models\Event\EventState;
use App\Models\Event\EventContent;
use App\Models\Event\EventCountry;
use App\Models\Event\EventLocation;
use Illuminate\Support\Facades\DB;
use Mews\Purifier\Facades\Purifier;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use App\Http\Requests\Event\StoreRequest;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\Event\UpdateRequest;
use App\Http\Requests\TicketSettingRequest;
use App\Support\EventGalleryUploader;

class EventController extends Controller
{
  //index
  public function index(Request $request)
  {
    $information['langs'] = Language::all();

    $language = Language::where('code', $request->language)->firstOrFail();
    $information['language'] = $language;

    $event_type = null;
    if (filled($request->event_type)) {
      $event_type = $request->event_type;
    }
    $title = null;
    if (request()->filled('title')) {
      $title = request()->input('title');
    }
    $fromDate = $request->filled('from_date') ? $request->from_date : null;
    $toDate = $request->filled('to_date') ? $request->to_date : null;

    $events = Event::join('event_contents', 'event_contents.event_id', '=', 'events.id')
      ->join('event_categories', 'event_categories.id', '=', 'event_contents.event_category_id')
      ->where('event_contents.language_id', '=', $language->id)
      ->when($title, function ($query) use ($title) {
        return $query->where('event_contents.title', 'like', '%' . $title . '%');
      })
      ->when($event_type, function ($query) use ($event_type) {
        return $query->where('events.event_type', $event_type);
      })
      ->when($fromDate, function ($query) use ($fromDate) {
        return $query->whereDate('events.start_date', '>=', $fromDate);
      })
      ->when($toDate, function ($query) use ($toDate) {
        return $query->whereDate('events.start_date', '<=', $toDate);
      })
      ->select(
        'events.*',
        'event_contents.id as eventInfoId',
        'event_contents.title',
        'event_contents.slug',
        'event_categories.name as category'
      )
      ->selectSub(function ($query) {
        $query->from('resale_tickets')
          ->selectRaw('COUNT(*)')
          ->whereColumn('resale_tickets.event_id', 'events.id')
          ->whereNull('resale_tickets.parent_id')
          ->whereNotNull('resale_tickets.seller_customer_id');
      }, 'reseller_count')
      ->selectSub(function ($query) {
        $query->from('resale_tickets')
          ->selectRaw('COALESCE(SUM(quantity), 0)')
          ->whereColumn('resale_tickets.event_id', 'events.id')
          ->where('resale_tickets.status', 'sold');
      }, 'resale_sold_qty')
      ->selectSub(function ($query) {
        $query->from('resale_tickets')
          ->selectRaw('COALESCE(SUM(quantity), 0)')
          ->whereColumn('resale_tickets.event_id', 'events.id')
          ->where('resale_tickets.status', 'reserved');
      }, 'resale_pending_qty')
      ->orderByDesc('events.id')
      ->paginate(10);

    $information['events'] = $events;
    return view('backend.event.index', $information);
  }
  //choose_event_type
  public function choose_event_type()
  {
    return view('backend.event.event_type');
  }
  //online_event
  public function add_event()
  {
    $information = [];
    $languages = Language::get();
    $information['languages'] = $languages;
    // $countries = Country::get();
    // $information['countries'] = $countries;
    $organizers = Organizer::get();
    $information['organizers'] = $organizers;
    $defaultLang = Language::where('is_default', 1)->first();
    $information['locations'] = EventLocation::where('status', 1)
      ->when($defaultLang, fn ($q) => $q->where('language_id', $defaultLang->id))
      ->orderBy('serial_number', 'asc')
      ->orderBy('name', 'asc')
      ->get();

    $information['getCurrencyInfo']  = $this->getCurrencyInfo();
    return view('backend.event.create', $information);
  }

  public function gallerystore(Request $request)
  {
    $rules = [
      'file' => 'required|image|mimes:jpg,jpeg,png'
    ];
    $messages = [
      'file.required' => 'Please upload an image file.',
      'file.image'    => 'The uploaded file must be an image.',
      'file.mimes'    => 'Only jpg, jpeg, and png files are allowed.'
    ];

    $validator = Validator::make($request->all(), $rules, $messages);
    if ($validator->fails()) {
      return response()->json([
        'status' => 'error',
        'msg' => $validator->errors()->first() ?: 'Invalid image upload.',
        'file' => $validator->errors()->get('file'),
      ], 422);
    }

    $img = $request->file('file');
    if (!$img) {
      return response()->json([
        'status' => 'error',
        'msg' => 'Please upload an image file.',
      ], 422);
    }

    return EventGalleryUploader::store($img, $request->event_id ?? null);
  }


  public function imagermv(Request $request)
  {
    $pi = EventImage::where('id', $request->fileid)->first();
    if (!$pi) {
      return response()->json(['message' => 'Image not found.'], 404);
    }
    if (!empty($pi->image)) {
      @unlink(public_path('assets/admin/img/event-gallery/') . $pi->image);
    }
    $id = $pi->id;
    $pi->delete();

    return response($id, 200)->header('Content-Type', 'text/plain');
  }

  public function store(StoreRequest $request)
  {
    $request->merge([
      'date_type' => 'single',
      'countdown_status' => 1,
      'organizer_id' => null
    ]);

    //calculate duration
    if ($request->date_type == 'single') {
      $start = Carbon::parse($request->start_date . $request->start_time);
      $end =  Carbon::parse($request->end_date . $request->end_time);
      $diffent = DurationCalulate($start, $end);
    }
    //calculate duration end

    $in = $request->all();
    $in['duration'] = $request->date_type == 'single' ? $diffent : '';

    $img = $request->file('thumbnail');

    $in['organizer_id'] = null;
    if ($request->hasFile('thumbnail')) {
      $filename = time() . '.' . $img->getClientOriginalExtension();
      $directory = public_path('assets/admin/img/event/thumbnail/');
      @mkdir($directory, 0775, true);
      $request->file('thumbnail')->move($directory, $filename);
      $in['thumbnail'] = $filename;
    }
    // Venue events: price/quantity fields UI se remove ho sakte hain,
    // is liye Ticket create ke liye safe defaults use kiye ja rahe hain.
    $ticketPrice = $request->filled('price') ? $request->price : 0;
    $ticketAvailable = $request->filled('ticket_available') ? $request->ticket_available : 1;

    $in['f_price'] = $ticketPrice;
    $in['end_date_time'] = Carbon::parse($request->end_date . ' ' . $request->end_time);
    $in['start_date_time'] = Carbon::parse($request->start_date . ' ' . $request->start_time);
    if ($request->filled('ticket_availability_datetime')) {
      $in['ticket_availability_datetime'] = Carbon::parse($request->ticket_availability_datetime);
    } else {
      $in['ticket_availability_datetime'] = null;
    }
    $in['location_id'] = $request->filled('location_id') ? (int) $request->location_id : null;
    $in['allow_custom_zone'] = $request->boolean('allow_custom_zone') ? 1 : 0;
    if (($request->event_ticket_transfer_type ?? '') === 'external') {
      $in['external_platform_name'] = trim((string) $request->external_platform_name) ?: null;
    } else {
      $in['external_platform_name'] = null;
    }
    $event = Event::create($in);

    if ($request->date_type == 'multiple') {
      $i = 1;
      foreach ($request->m_start_date as $key => $date) {
        $start = Carbon::parse($date . $request->m_start_time[$key]);
        $end =  Carbon::parse($request->m_end_date[$key] . $request->m_end_time[$key]);
        $diffent = DurationCalulate($start, $end);

        EventDates::create([
          'event_id' => $event->id,
          'start_date' => $date,
          'start_time' => $request->m_start_time[$key],
          'end_date' => $request->m_end_date[$key],
          'end_time' => $request->m_end_time[$key],
          'duration' => $diffent,
          'start_date_time' => $start,
          'end_date_time' => $end,
        ]);
        if ($i == 1) {
          $event->update([
            'duration' => $diffent
          ]);
        }
        $i++;
      }
      //update event date time
      $event_date = EventDates::where('event_id', $event->id)->orderBy('end_date_time', 'desc')->first();

      $event->end_date_time = $event_date->end_date_time;
      $event->save();
    }


    $in['event_id'] = $event->id;
    if ($request->event_type == 'online') {
      if (!$request->pricing_type) {
        $in['pricing_type'] = 'normal';
      }
      $in['early_bird_discount'] = $request->early_bird_discount_type;
      $in['early_bird_discount_type'] = $request->discount_type;
      Ticket::create($in);
    } elseif ($request->event_type == 'venue') {
      Ticket::create([
        'event_id' => $event->id,
        'event_type' => 'venue',
        'pricing_type' => 'normal',
        'price' => $ticketPrice,
        'f_price' => $ticketPrice,
        'ticket_available_type' => 'limited',
        'ticket_available' => $ticketAvailable,
        'max_ticket_buy_type' => 'unlimited',
        'max_buy_ticket' => null,
        'early_bird_discount' => 'disable',
        'early_bird_discount_type' => null,
        'normal_ticket_slot_enable' => 0,
        'normal_ticket_slot_unique_id' => rand(100000, 999999),
        'slot_seat_min_price' => 0
      ]);
    }

    //event slider-images
    $slders = $request->input('slider_images', []);
    if (!is_array($slders)) {
      $slders = $slders !== null && $slders !== '' ? [$slders] : [];
    }
    foreach ($slders as $key => $id) {
      $event_image = EventImage::where('id', $id)->first();
      if ($event_image) {
        $event_image->event_id = $event->id;
        $event_image->save();
      }
    }

    //event content
    $languages = Language::all();
    foreach ($languages as $language) {
      $event_content = new EventContent();
      $event_content->language_id = $language->id;
      $event_content->event_category_id = $request[$language->code . '_category_id'];
      $event_content->event_id = $event->id;
      $event_content->title = $request[$language->code . '_title'];
      if ($request->event_type == 'venue') {
        $event_content->address = $request[$language->code . '_address'];
        $event_content->country_id = $request[$language->code . '_country'];
        $event_content->city_id = $request[$language->code . '_city'];
        $event_content->state_id = $request[$language->code . '_state'];
        $event_content->zip_code = $request[$language->code . '_zip_code'];
      }
      $event_content->slug = createSlug($request[$language->code . '_title']);
      $event_content->description = Purifier::clean($request[$language->code . '_description'], 'youtube');
      $event_content->refund_policy = $request[$language->code . '_refund_policy'];
      $event_content->meta_keywords = $request[$language->code . '_meta_keywords'];
      $event_content->meta_description = $request[$language->code . '_meta_description'];
      $event_content->save();
    }

    Session::flash('success', 'Added Successfully');
    return response()->json(['status' => 'success'], 200);
  }

  /**
   * delete events dates
   */
  public function deleteDate($id)
  {
    $date = EventDates::where('id', $id)->first();
    $date->delete();
    return 'success';
  }
  /**
   * Update status (active/DeActive) of a specified resource.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function updateStatus(Request $request, $id)
  {
    $event = Event::find($id);

    $event->update([
      'status' => $request['status']
    ]);
    Session::flash('success', 'Deleted Successfully');

    return redirect()->back();
  }
  /**
   * Update featured status of a specified resource.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function updateFeatured(Request $request, $id)
  {
    $event = Event::find($id);

    if ($request['is_featured'] == 'yes') {
      $event->is_featured = 'yes';
      $event->save();

      Session::flash('success', 'Updated Successfully');
    } else {
      $event->is_featured = 'no';
      $event->save();

      Session::flash('success', 'Updated Successfully');
    }

    return redirect()->back();
  }

  public function edit($id)
  {
    $event = Event::with('ticket')->findOrFail($id);
    $information['event'] = $event;

    $mapStatus = DB::table('basic_settings')->pluck('google_map_status')->first();
    $defaultLang = Language::where('is_default', 1)->first();
    if ($mapStatus == 1) {
      $information['event_address'] = EventContent::select('address')
        ->where(['event_id' => $id, 'language_id' => $defaultLang->id])
        ->first();
    }

    $information['languages'] = Language::all();
    $organizers = Organizer::get();
    $information['organizers'] = $organizers;
    $information['locations'] = EventLocation::where('status', 1)
      ->when($defaultLang, fn ($q) => $q->where('language_id', $defaultLang->id))
      ->orderBy('serial_number', 'asc')
      ->orderBy('name', 'asc')
      ->get();

    $information['getCurrencyInfo']  = $this->getCurrencyInfo();

    return view('backend.event.edit', $information);
  }
  public function imagedbrmv(Request $request)
  {
    $pi = EventImage::where('id', $request->fileid)->first();
    $event_id = $pi->event_id;
    $image_count = EventImage::where('event_id', $event_id)->get()->count();
    if ($image_count > 1) {
      @unlink(public_path('assets/admin/img/event-gallery/') . $pi->image);
      $pi->delete();
      return $pi->id;
    } else {
      return 'false';
    }
  }
  public function images($portid)
  {
    $images = EventImage::where('event_id', $portid)->get();
    return $images;
  }

  public function update(UpdateRequest $request)
  {
    $request->merge([
      'date_type' => 'single',
      'countdown_status' => 1,
      'organizer_id' => null
    ]);
    //calculate duration
    if ($request->date_type == 'single') {
      $start = Carbon::parse($request->start_date . $request->start_time);
      $end =  Carbon::parse($request->end_date . $request->end_time);
      $diffent = DurationCalulate($start, $end);
    }
    //calculate duration end
    $img = $request->file('thumbnail');

    $in = $request->all();
    $in['organizer_id'] = null;

    $event = Event::where('id', $request->event_id)->first();
    if ($request->hasFile('thumbnail')) {
      @unlink(public_path('assets/admin/img/event/thumbnail/') . $event->thumbnail);
      $filename = time() . '.' . $img->getClientOriginalExtension();
      @mkdir(public_path('assets/admin/img/event/thumbnail/'), 0775, true);
      $request->file('thumbnail')->move(public_path('assets/admin/img/event/thumbnail/'), $filename);
      $in['thumbnail'] = $filename;
    }

    $languages = Language::all();

    $i = 1;
    foreach ($languages as $language) {
      $event_content = EventContent::where('event_id', $event->id)->where('language_id', $language->id)->first();
      if (!$event_content) {
        $event_content = new EventContent();
      }
      $event_content->language_id = $language->id;
      $event_content->event_category_id = $request[$language->code . '_category_id'];
      $event_content->event_id = $event->id;
      $event_content->title = $request[$language->code . '_title'];
      if ($request->event_type == 'venue') {
        $event_content->address = $request[$language->code . '_address'];
        $event_content->country_id = $request[$language->code . '_country'];
        $event_content->state_id = $request[$language->code . '_state'];
        $event_content->city_id = $request[$language->code . '_city'];
        $event_content->zip_code = $request[$language->code . '_zip_code'];
      }
      $event_content->slug = createSlug($request[$language->code . '_title']);
      $event_content->description = Purifier::clean($request[$language->code . '_description'], 'youtube');
      $event_content->refund_policy = $request[$language->code . '_refund_policy'];
      $event_content->meta_keywords = $request[$language->code . '_meta_keywords'];
      $event_content->meta_description = $request[$language->code . '_meta_description'];
      $event_content->save();
    }
    if ($request->event_type == 'online') {
      if (!$request->pricing_type) {
        $pricing_type = 'normal';
      } else {
        $pricing_type = $request->pricing_type;
      }
      Ticket::where('event_id', $request->event_id)->update([
        'price' => $request->price,
        'f_price' => $request->price,
        'pricing_type' => $pricing_type,
        'ticket_available_type' => $request->ticket_available_type,
        'ticket_available' => $request->ticket_available_type == 'limited' ? $request->ticket_available : null,
        'max_ticket_buy_type' => $request->max_ticket_buy_type,
        'max_buy_ticket' => $request->max_ticket_buy_type == 'limited' ? $request->max_buy_ticket : null,
        'early_bird_discount' => $request->early_bird_discount_type,
        'early_bird_discount_type' => $request->discount_type,
        'early_bird_discount_amount' => $request->early_bird_discount_amount,
        'early_bird_discount_date' => $request->early_bird_discount_date,
        'early_bird_discount_time' => $request->early_bird_discount_time,
      ]);
    } elseif ($request->event_type == 'venue') {
      $existingTicket = Ticket::where('event_id', $request->event_id)->first();
      $venuePrice = $request->filled('price')
        ? $request->price
        : (($existingTicket->price ?? $existingTicket->f_price ?? 0));

      $venueTicketAvailable = $request->filled('ticket_available')
        ? $request->ticket_available
        : ($existingTicket->ticket_available ?? 1);

      $venueTicketAvailable = ((int) $venueTicketAvailable >= 1) ? (int) $venueTicketAvailable : 1;

      Ticket::updateOrCreate(
        ['event_id' => $request->event_id],
        [
          'event_type' => 'venue',
          'pricing_type' => 'normal',
          'price' => $venuePrice,
          'f_price' => $venuePrice,
          'ticket_available_type' => 'limited',
          'ticket_available' => $venueTicketAvailable,
          'max_ticket_buy_type' => 'unlimited',
          'max_buy_ticket' => null,
          'early_bird_discount' => 'disable',
          'early_bird_discount_type' => null
        ]
      );
    }

    $event = Event::where('id', $event->id)->first();

    if ($request->date_type == 'multiple') {
      $i = 1;
      foreach ($request->m_start_date as $key => $date) {
        $start = Carbon::parse($date . $request->m_start_time[$key]);
        $end =  Carbon::parse($request->m_end_date[$key] . $request->m_end_time[$key]);
        $diffent = DurationCalulate($start, $end);

        if (!empty($request->date_ids[$key])) {
          $event_date = EventDates::where('id', $request->date_ids[$key])->first();
          $event_date->start_date = $date;
          $event_date->start_time = $request->m_start_time[$key];
          $event_date->end_date = $request->m_end_date[$key];
          $event_date->end_time = $request->m_end_time[$key];
          $event_date->duration = $diffent;
          $event_date->start_date_time = $start;
          $event_date->end_date_time = $end;
          $event_date->save();
        } else {
          EventDates::create([
            'event_id' => $event->id,
            'start_date' => $date,
            'start_time' => $request->m_start_time[$key],
            'end_date' => $request->m_end_date[$key],
            'end_time' => $request->m_end_time[$key],
            'duration' => $diffent,
            'start_date_time' => $start,
            'end_date_time' => $end,
          ]);
        }
        if ($i == 1) {
          $event->update([
            'duration' => $diffent
          ]);
        }
        $i++;
      }
    }

    if ($request->date_type == 'single') {
      $in['end_date_time'] = Carbon::parse($request->end_date . ' ' . $request->end_time);
      $in['start_date_time'] = Carbon::parse($request->start_date . ' ' . $request->start_time);
      $in['duration'] = $diffent;
    } else {
      //update event date time
      $event_date = EventDates::where('event_id', $event->id)->orderBy('end_date_time', 'desc')->first();

      $in['end_date_time'] = $event_date->end_date_time;
      $in['start_date_time'] = $event_date->start_date_time ?? $in['end_date_time'];
    }

    $in['ticket_availability_datetime'] = $request->filled('ticket_availability_datetime')
      ? Carbon::parse($request->ticket_availability_datetime)
      : null;

    $in['location_id'] = $request->filled('location_id') ? (int) $request->location_id : null;
    $in['allow_custom_zone'] = $request->boolean('allow_custom_zone') ? 1 : 0;
    if (($request->event_ticket_transfer_type ?? '') === 'external') {
      $in['external_platform_name'] = trim((string) $request->external_platform_name) ?: null;
    } else {
      $in['external_platform_name'] = null;
    }

    $event->update($in);
    Session::flash('success', 'Updated Successfully');

    return response()->json(['status' => 'success'], 200);
  }
  /**
   * Remove the specified resource from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function destroy($id)
  {
    $event = Event::find($id);

    @unlink(public_path('assets/admin/img/event/thumbnail/') . $event->thumbnail);

    $event_contents = EventContent::where('event_id', $event->id)->get();
    foreach ($event_contents as $event_content) {
      $event_content->delete();
    }
    $event_images = EventImage::where('event_id', $event->id)->get();
    foreach ($event_images as $event_image) {
      @unlink(public_path('assets/admin/img/event-gallery/') . $event_image->image);
      $event_image->delete();
    }

    //bookings
    $bookings = $event->booking()->get();
    foreach ($bookings as $booking) {
      // first, delete the attachment
      @unlink(public_path('assets/admin/file/attachments/') . $booking->attachment);

      // second, delete the invoice
      @unlink(public_path('assets/admin/file/invoices/') . $booking->invoice);

      $booking->delete();
    }

    //tickets
    $tickets = $event->tickets()->get();
    foreach ($tickets as $ticket) {
      $ticket->delete();
    }
    //wishlists
    $wishlists = $event->wishlists()->get();
    foreach ($wishlists as $wishlist) {
      $wishlist->delete();
    }

    //dates
    $dates = $event->dates()->get();
    foreach ($dates as $date) {
      $date->delete();
    }

    // finally delete the event
    $event->delete();

    return redirect()->back()->with('success', 'Deleted Successfully');
  }
  //bulk_delete
  public function bulk_delete(Request $request)
  {
    foreach ($request->ids as $id) {
      $event = Event::find($id);

      @unlink(public_path('assets/admin/img/event/thumbnail/') . $event->thumbnail);

      $event_contents = EventContent::where('event_id', $event->id)->get();
      foreach ($event_contents as $event_content) {
        $event_content->delete();
      }
      $event_images = EventImage::where('event_id', $event->id)->get();
      foreach ($event_images as $event_image) {
        @unlink(public_path('assets/admin/img/event-gallery/') . $event_image->image);
        $event_image->delete();
      }

      //bookings
      $bookings = $event->booking()->get();
      foreach ($bookings as $booking) {
        // first, delete the attachment
        @unlink(public_path('assets/admin/file/attachments/') . $booking->attachment);

        // second, delete the invoice
        @unlink(public_path('assets/admin/file/invoices/') . $booking->invoice);

        $booking->delete();
      }

      //tickets
      $tickets = $event->tickets()->get();
      foreach ($tickets as $ticket) {
        $ticket->delete();
      }

      //wishlists
      $wishlists = $event->wishlists()->get();
      foreach ($wishlists as $wishlist) {
        $wishlist->delete();
      }

      //dates
      $dates = $event->dates()->get();
      foreach ($dates as $date) {
        $date->delete();
      }
      // finally delete the event
      $event->delete();
    }
    Session::flash('success', 'Deleted Successfully');
    return response()->json(['status' => 'success'], 200);
  }
  public function editTicketSetting($id)
  {
    $event = Event::with('ticket')->findOrFail($id);
    $information['event'] = $event;
    return view('backend.event.ticket-settings', $information);
  }
  public function updateTicketSetting(TicketSettingRequest $request)
  {
    $ticket_image = $request->file('ticket_image');
    $ticket_slot_image = $request->file('ticket_slot_image');
    $ticket_logo = $request->file('ticket_logo');
    $in = $request->all();
    $instructions = Purifier::clean($request->instructions);
    $event = Event::where('id', $request->event_id)->first();
    if ($request->hasFile('ticket_image')) {
      @unlink(public_path('assets/admin/img/event_ticket/') . $event->ticket_image);
      $filename = time() . rand(111, 999) . '.' . $ticket_image->getClientOriginalExtension();
      @mkdir(public_path('assets/admin/img/event_ticket/'), 0775, true);
      $request->file('ticket_image')->move(public_path('assets/admin/img/event_ticket/'), $filename);
      $in['ticket_image'] = $filename;
    }
    if ($request->hasFile('ticket_slot_image')) {
      @unlink(public_path('assets/admin/img/event_ticket/') . $event->ticket_slot_image);
      $filename = time() . rand(111, 999) . '.' . $ticket_slot_image->getClientOriginalExtension();
      @mkdir(public_path('assets/admin/img/event_ticket/'), 0775, true);
      $request->file('ticket_slot_image')->move(public_path('assets/admin/img/event_ticket/'), $filename);
      $in['ticket_slot_image'] = $filename;
    }
    if ($request->hasFile('ticket_logo')) {
      @unlink(public_path('assets/admin/img/event_ticket_logo/') . $event->ticket_logo);
      $filename = time() . rand(111, 999) . '.' . $ticket_logo->getClientOriginalExtension();
      @mkdir(public_path('assets/admin/img/event_ticket_logo/'), 0775, true);
      $request->file('ticket_logo')->move(public_path('assets/admin/img/event_ticket_logo/'), $filename);
      $in['ticket_logo'] = $filename;
    }
    $in['instructions'] = $instructions;

    $event->update($in);
    Session::flash('success', 'Updated Successfully');

    return response()->json(['status' => 'success'], 200);
  }

  public function resellers($id, Request $request)
  {
    $event = Event::findOrFail($id);
    $defaultLang = Language::where('is_default', 1)->first();
    $selectedLanguage = Language::where('code', $request->language)->first();
    $language = $selectedLanguage ?: $defaultLang;

    $eventTitle = EventContent::where('event_id', $event->id)
      ->where('language_id', optional($language)->id)
      ->value('title');

    if (empty($eventTitle)) {
      $eventTitle = EventContent::where('event_id', $event->id)->value('title');
    }

    $usernameFilter = trim((string) $request->input('username', ''));
    $statusFilter = trim((string) $request->input('status', ''));

    $resellerTickets = ResaleTicket::where('event_id', $event->id)
      ->whereNotNull('seller_customer_id')
      ->whereNull('parent_id')
      ->with(['seller', 'buyer', 'children.buyer'])
      ->when($usernameFilter !== '', function ($q) use ($usernameFilter) {
        $q->whereHas('seller', function ($sellerQuery) use ($usernameFilter) {
          $sellerQuery->where('username', 'like', '%' . $usernameFilter . '%');
        });
      })
      ->when($statusFilter !== '', function ($q) use ($statusFilter) {
        $q->where('status', $statusFilter);
      })
      ->latest()
      ->get();

    $resellerSummary = [];
    foreach ($resellerTickets as $ticket) {
      $sellerId = (int) $ticket->seller_customer_id;
      if (!isset($resellerSummary[$sellerId])) {
        $resellerSummary[$sellerId] = [
          'seller' => $ticket->seller,
          'sold_qty' => 0,
          'pending_qty' => 0,
          'listings' => 0,
        ];
      }

      $soldQty = (int) $ticket->children->where('status', 'sold')->sum('quantity');
      $reservedQty = (int) $ticket->children->where('status', 'reserved')->sum('quantity');
      $listedQty = strtolower((string) $ticket->status) === 'listed' ? (int) $ticket->quantity : 0;

      $resellerSummary[$sellerId]['sold_qty'] += $soldQty;
      $resellerSummary[$sellerId]['pending_qty'] += ($listedQty + $reservedQty);
      $resellerSummary[$sellerId]['listings'] += 1;
    }

    $statusOptions = [
      ResaleTicket::STATUS_LISTED,
      ResaleTicket::STATUS_RESERVED,
      ResaleTicket::STATUS_PAID,
      ResaleTicket::STATUS_TRANSFERRED,
      ResaleTicket::STATUS_SOLD,
      ResaleTicket::STATUS_CANCELLED,
    ];

    return view('backend.event.resellers', [
      'event' => $event,
      'eventTitle' => $eventTitle ?: ('Event #' . $event->id),
      'language' => $language,
      'defaultLang' => $defaultLang,
      'resellerTickets' => $resellerTickets,
      'resellerSummary' => collect($resellerSummary)->values(),
      'usernameFilter' => $usernameFilter,
      'statusFilter' => $statusFilter,
      'statusOptions' => $statusOptions,
    ]);
  }

  public function resellerTicketPdfs($id, $ticketId)
  {
    $event = Event::findOrFail($id);
    $ticket = ResaleTicket::with('seller')
      ->where('event_id', $event->id)
      ->where('id', $ticketId)
      ->whereNull('parent_id')
      ->firstOrFail();

    $pdfFiles = $this->getResaleTicketPdfFiles($ticket);

    return view('backend.event.reseller-ticket-pdfs', compact('event', 'ticket', 'pdfFiles'));
  }

  public function destroyResellerTicket($id, $ticketId)
  {
    $event = Event::findOrFail($id);

    DB::transaction(function () use ($event, $ticketId) {
      $ticket = ResaleTicket::where('event_id', $event->id)
        ->where('id', $ticketId)
        ->whereNull('parent_id')
        ->lockForUpdate()
        ->firstOrFail();

      $children = ResaleTicket::where('parent_id', $ticket->id)->lockForUpdate()->get();

      foreach ($children as $child) {
        foreach ($this->getResaleTicketPdfFiles($child) as $pdfFile) {
          @unlink(public_path('assets/admin/file/resale-tickets/') . $pdfFile);
        }
      }

      foreach ($this->getResaleTicketPdfFiles($ticket) as $pdfFile) {
        @unlink(public_path('assets/admin/file/resale-tickets/') . $pdfFile);
      }

      ResaleTicket::where('parent_id', $ticket->id)->delete();
      $ticket->delete();
    });

    Session::flash('success', 'Reseller ticket removed successfully.');
    return redirect()->back();
  }

  private function getResaleTicketPdfFiles(ResaleTicket $ticket): array
  {
    $files = [];

    if (!empty($ticket->ticket_pdfs)) {
      $decoded = json_decode((string) $ticket->ticket_pdfs, true);
      if (is_array($decoded)) {
        foreach ($decoded as $file) {
          if (!empty($file)) {
            $files[] = (string) $file;
          }
        }
      }
    }

    if (!empty($ticket->ticket_pdf)) {
      $files[] = (string) $ticket->ticket_pdf;
    }

    return array_values(array_unique($files));
  }

  public function export(Request $request)
  {
    $language = Language::where('code', $request->language)->first();
    if (!$language) {
      $language = Language::where('is_default', 1)->first();
    }

    $eventType = $request->filled('event_type') ? $request->event_type : null;
    $title = $request->filled('title') ? $request->title : null;
    $fromDate = $request->filled('from_date') ? $request->from_date : null;
    $toDate = $request->filled('to_date') ? $request->to_date : null;

    $events = Event::join('event_contents', 'event_contents.event_id', '=', 'events.id')
      ->join('event_categories', 'event_categories.id', '=', 'event_contents.event_category_id')
      ->where('event_contents.language_id', '=', optional($language)->id)
      ->when($title, function ($query) use ($title) {
        return $query->where('event_contents.title', 'like', '%' . $title . '%');
      })
      ->when($eventType, function ($query) use ($eventType) {
        return $query->where('events.event_type', $eventType);
      })
      ->when($fromDate, function ($query) use ($fromDate) {
        return $query->whereDate('events.start_date', '>=', $fromDate);
      })
      ->when($toDate, function ($query) use ($toDate) {
        return $query->whereDate('events.start_date', '<=', $toDate);
      })
      ->select(
        'events.id',
        'event_contents.title',
        'events.event_ticket_transfer_type',
        'event_categories.name as category',
        'events.status',
        'events.start_date',
        'events.start_time',
        'events.end_date',
        'events.end_time',
        'events.end_date_time'
      )
      ->orderByDesc('events.id')
      ->get();

    $fileName = 'all-events.csv';
    $headers = [
      'Content-Type' => 'text/csv; charset=UTF-8',
      'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
    ];

    return response()->streamDownload(function () use ($events) {
      $out = fopen('php://output', 'w');
      fputcsv($out, ['ID', 'Title', 'Event start', 'Event end', 'Transfer Type', 'Category', 'Status']);

      foreach ($events as $event) {
        $startStr = '';
        $endStr = '';
        if (!empty($event->start_date)) {
          try {
            $startStr = Carbon::parse(trim($event->start_date . ' ' . ($event->start_time ?? '')))->format('Y-m-d H:i');
          } catch (\Throwable $e) {
            $startStr = trim($event->start_date . ' ' . ($event->start_time ?? ''));
          }
        }
        if (!empty($event->end_date_time)) {
          try {
            $endStr = Carbon::parse($event->end_date_time)->format('Y-m-d H:i');
          } catch (\Throwable $e) {
            $endStr = (string) $event->end_date_time;
          }
        } elseif (!empty($event->end_date)) {
          try {
            $endStr = Carbon::parse(trim($event->end_date . ' ' . ($event->end_time ?? '')))->format('Y-m-d H:i');
          } catch (\Throwable $e) {
            $endStr = trim($event->end_date . ' ' . ($event->end_time ?? ''));
          }
        }

        fputcsv($out, [
          (int) $event->id,
          (string) $event->title,
          $startStr,
          $endStr,
          ucfirst((string) ($event->event_ticket_transfer_type ?? 'external')),
          (string) ($event->category ?? '-'),
          (int) $event->status === 1 ? 'Active' : 'Deactive',
        ]);
      }

      fclose($out);
    }, $fileName, $headers);
  }

  //search country
  public function getCountry(Request $request)
  {
    $search = $request->input('search');
    $page = $request->input('page', 1);
    $pageSize = 10;

    $query = EventCountry::where('language_id', $request->lang);

    if ($search) {
      $query->where('name', 'like', "%{$search}%");
    }

    // Add pagination
    $countries = $query->skip(($page - 1) * $pageSize)
      ->take($pageSize + 1)
      ->get(['id', 'slug', 'name']);


    // Check if there's more data
    $hasMore = count($countries) > $pageSize;
    $results = $hasMore ? $countries->slice(0, $pageSize) : $countries;

    return response()->json([
      'results' => $results,
      'more' => $hasMore
    ]);
  }


  public function searchSate(Request $request)
  {
    $search = $request->input('search');
    $page = $request->input('page', 1);
    $pageSize = 10;

    $country_id = $request->country;

    $query = EventState::where('language_id', $request->lang)
      ->when($request->country, function ($q) use ($country_id) {
        return $q->where('country_id', $country_id);
      });

    if ($search) {
      $query->where('name', 'like', "%{$search}%");
    }

    // Add pagination
    $cities = $query->skip(($page - 1) * $pageSize)
      ->take($pageSize + 1)
      ->get(['id', 'slug', 'name']);

    // Check if there's more data
    $hasMore = count($cities) > $pageSize;
    $results = $hasMore ? $cities->slice(0, $pageSize) : $cities;

    return response()->json([
      'results' => $results,
      'more' => $hasMore
    ]);
  }


  public function getSearchCity(Request $request)
  {
    $search = $request->input('search');
    $page = $request->input('page', 1);
    $pageSize = 10;

    $state_id = $request->state;

    $query = EventCity::where('language_id', $request->lang)
      ->when($request->state, function ($q) use ($state_id) {
        return $q->where('state_id', $state_id);
      });

    if ($search) {
      $query->where('name', 'like', "%{$search}%");
    }

    // Add pagination
    $cities = $query->skip(($page - 1) * $pageSize)
      ->take($pageSize + 1)
      ->get(['id', 'slug', 'name']);

    // Check if there's more data
    $hasMore = count($cities) > $pageSize;
    $results = $hasMore ? $cities->slice(0, $pageSize) : $cities;

    return response()->json([
      'results' => $results,
      'more' => $hasMore
    ]);
  }
}
