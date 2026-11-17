@extends('backend.layout')

@section('content')
  <div class="page-header">
    <h4 class="page-title">Ticket PDFs</h4>
    <ul class="breadcrumbs">
      <li class="nav-home">
        <a href="{{ route('admin.dashboard') }}">
          <i class="flaticon-home"></i>
        </a>
      </li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="#">Resellers</a></li>
      <li class="separator"><i class="flaticon-right-arrow"></i></li>
      <li class="nav-item"><a href="#">Ticket PDFs</a></li>
    </ul>
  </div>

  <div class="row">
    <div class="col-lg-12">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="mb-0">Reseller Ticket #{{ $ticket->id }}</h5>
            <small class="text-muted">
              Event ID: {{ $event->id }} | Reseller: {{ optional($ticket->seller)->username ?? '-' }}
            </small>
          </div>
          <a href="{{ url()->previous() }}" class="btn btn-sm btn-secondary">Back</a>
        </div>
        <div class="card-body">
          @if (count($pdfFiles) > 0)
            <div class="mb-3">
              <button type="button" class="btn btn-sm btn-primary"
                onclick="event.preventDefault(); @foreach ($pdfFiles as $file) window.open('{{ asset('assets/admin/file/resale-tickets/' . $file) }}', '_blank'); @endforeach">
                Open All PDFs
              </button>
            </div>

            <div class="table-responsive">
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>File</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach ($pdfFiles as $index => $file)
                    <tr>
                      <td>{{ $index + 1 }}</td>
                      <td>{{ $file }}</td>
                      <td>
                        <a href="{{ asset('assets/admin/file/resale-tickets/' . $file) }}" target="_blank"
                          class="btn btn-sm btn-info">
                          Open PDF
                        </a>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @else
            <p class="mb-0">No PDF files found for this reseller ticket.</p>
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection

