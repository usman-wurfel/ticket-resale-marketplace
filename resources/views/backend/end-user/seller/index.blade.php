@extends('backend.layout')

@section('content')
  <div class="page-header">
    <h4 class="page-title">Registered sellers</h4>
    <ul class="breadcrumbs">
      <li class="nav-home">
        <a href="{{ route('admin.dashboard') }}"><i class="flaticon-home"></i></a>
      </li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="#">Seller management</a></li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="#">Registered sellers</a></li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="card">
        <div class="card-header">
          <div class="row">
            <div class="col-lg-4">
              <div class="card-title">All sellers</div>
            </div>
            <div class="col-lg-8">
              <form action="{{ route('admin.seller_management.registered_seller.export') }}" method="GET" class="float-right ml-2">
                <input type="hidden" name="info" value="{{ request()->input('info') }}">
                <button type="submit" class="btn btn-success btn-sm mt-1" title="PDF Format">{{ __('Export') }}</button>
              </form>
              <form class="float-right" method="GET">
                <input name="info" type="text" class="form-control min-230"
                  placeholder="Search seller by name, username or email"
                  value="{{ request()->input('info') ?? '' }}">
              </form>
            </div>
          </div>
        </div>
        <div class="card-body">
          @if (count($sellers) === 0)
            <h3 class="text-center">NO SELLER FOUND!</h3>
          @else
            <div class="table-responsive">
              <table class="table table-striped mt-2">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Sold</th>
                    <th>On hold</th>
                    <th>In hand</th>
                    <th>Earnings</th>
                    <th>PDF files</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach ($sellers as $seller)
                    @php $st = $stats[$seller->id] ?? ['sold_qty' => 0, 'on_hold_qty' => 0, 'in_hand_qty' => 0, 'earnings' => 0, 'pdf_files' => 0]; @endphp
                    <tr>
                      <td>{{ $seller->fname }} {{ $seller->lname }}</td>
                      <td>{{ $seller->email }}</td>
                      <td>{{ $seller->phone ?: '-' }}</td>
                      <td>{{ (int) $st['sold_qty'] }}</td>
                      <td>{{ (int) $st['on_hold_qty'] }}</td>
                      <td>{{ (int) $st['in_hand_qty'] }}</td>
                      <td>{{ number_format((float) $st['earnings'], 2) }}</td>
                      <td>{{ (int) $st['pdf_files'] }}</td>
                      <td>
                        <a class="btn btn-sm btn-info" href="{{ route('admin.seller_management.seller_details', ['id' => $seller->id, 'language' => request()->input('language')]) }}">
                          <i class="fas fa-eye"></i>
                        </a>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>
        <div class="card-footer text-center">
          <div class="d-inline-block mt-2">
            {{ $sellers->appends(['info' => request()->input('info')])->links() }}
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

