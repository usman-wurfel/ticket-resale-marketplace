<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; margin-left: 24px; }
    h3 { margin: 0 0 12px 0; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
    th { background: #f5f5f5; }
  </style>
</head>
<body>
  <h3>All Tickets</h3>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Title</th>
        <th>Ticket Type</th>
        <th>Category</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      @foreach($events as $event)
        <tr>
          <td>{{ $event->id }}</td>
          <td>{{ $event->title }}</td>
          <td>{{ ucfirst((string) ($event->event_ticket_transfer_type ?? 'external')) }}</td>
          <td>{{ $event->category }}</td>
          <td>{{ (int) $event->status === 1 ? 'Active' : 'Deactive' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</body>
</html>

