<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; margin-left: 24px; }
    h3 { margin: 0 0 12px 0; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #ccc; padding: 5px; text-align: left; }
    th { background: #f5f5f5; }
  </style>
</head>
<body>
  <h3>Bookings List</h3>
  <table>
    <thead>
      <tr>
        <th>Type</th>
        <th>Booking ID</th>
        <th>Ticket/Event</th>
        <th>Seller</th>
        <th>Customer</th>
        <th>Amount</th>
        <th>Paid Via</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      @foreach($rows as $row)
        <tr>
          <td>{{ $row['type'] }}</td>
          <td>{{ $row['booking_id'] }}</td>
          <td>{{ $row['ticket'] }}</td>
          <td>{{ $row['seller'] }}</td>
          <td>{{ $row['customer'] }}</td>
          <td>{{ number_format((float) $row['amount'], 2) }}</td>
          <td>{{ $row['paid_via'] }}</td>
          <td>{{ $row['status'] }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</body>
</html>

