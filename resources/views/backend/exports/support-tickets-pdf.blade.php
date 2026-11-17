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
  <h3>Incident Tickets</h3>
  <table>
    <thead>
      <tr>
        <th>Ticket ID</th>
        <th>User Type</th>
        <th>Username</th>
        <th>Email</th>
        <th>Subject</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      @foreach($rows as $row)
        <tr>
          <td>{{ $row['id'] }}</td>
          <td>{{ $row['user_type'] }}</td>
          <td>{{ $row['username'] }}</td>
          <td>{{ $row['email'] }}</td>
          <td>{{ $row['subject'] }}</td>
          <td>{{ $row['status'] }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</body>
</html>

