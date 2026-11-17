@extends('backend.layout')

@section('content')
    <div class="page-header">
        <h4 class="page-title">{{ __('Zones') }}</h4>
        <ul class="breadcrumbs">
            <li class="nav-home">
                <a href="{{ route('admin.dashboard') }}">
                    <i class="flaticon-home"></i>
                </a>
            </li>
            <li class="separator">
                <i class="flaticon-right-arrow"></i>
            </li>
            <li class="nav-item">
                <a href="#">{{ __('Event management') }}</a>
            </li>
            <li class="separator">
                <i class="flaticon-right-arrow"></i>
            </li>
            <li class="nav-item">
                <a href="{{ route('admin.event_management.zones', ['language' => $defaultLang->code]) }}">{{ __('Zones') }}</a>
            </li>
            <li class="separator">
                <i class="flaticon-right-arrow"></i>
            </li>
            <li class="nav-item">
                <a href="#">{{ __('Edit Zone') }}</a>
            </li>
        </ul>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-lg-4">
                            <div class="card-title d-inline-block">{{ __('Edit Zone') }}</div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="col-lg-6 mx-auto">
                        <form id="modalForm" class="modal-form create"
                            action="{{ route('admin.event_management.update_zone') }}" method="post">
                            @csrf
                            <input type="hidden" name="id" value="{{ $zone->id }}">
                            <input type="hidden" name="language_id" value="{{ $zone->language_id }}">

                            <div class="form-group">
                                <label for="">{{ __('Location') . '*' }}</label>
                                <select name="location_id" class="form-control select2">
                                    <option disabled>{{ __('Select Location') }}</option>
                                    @foreach ($locations as $location)
                                        <option value="{{ $location->id }}"
                                            {{ (int) $zone->location_id === (int) $location->id ? 'selected' : '' }}>
                                            {{ $location->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <p id="err_location_id" class="mt-1 mb-0 text-danger em"></p>
                            </div>

                            <div class="form-group">
                                <label for="">{{ __('Name') . ' *' }}</label>
                                <input type="text" value="{{ $zone->name }}" class="form-control" name="name"
                                    placeholder="Enter name">
                                <p id="err_name" class="mt-2 mb-0 text-danger em"></p>
                            </div>

                            <div class="form-group">
                                <label for="">{{ __('Status') . '*' }}</label>
                                <select name="status" class="form-control">
                                    <option value="1" {{ $zone->status == 1 ? 'selected' : '' }}>{{ __('Active') }}</option>
                                    <option value="0" {{ $zone->status == 0 ? 'selected' : '' }}>{{ __('Deactive') }}</option>
                                </select>
                                <p id="err_status" class="mt-1 mb-0 text-danger em"></p>
                            </div>
                            <div class="form-group">
                                <label for="">{{ __('Serial Number') . ' *' }}</label>
                                <input type="text" value="{{ $zone->serial_number }}" class="form-control" name="serial_number"
                                    placeholder="Enter serial number">
                                <p id="err_serial_number" class="mt-2 mb-0 text-danger em"></p>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card-footer d-flex justify-content-center">
                    <button id="modalSubmit" type="button" class="btn btn-primary">
                        {{ __('Update') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection
