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
  <h3>Registered Sellers</h3>
  <table>
    <thead>
      <tr>
        <th>Name</th>
        <th>Username</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Sold</th>
        <th>On hold</th>
        <th>In hand</th>
        <th>Earnings</th>
      </tr>
    </thead>
    <tbody>
      @foreach($rows as $row)
        <tr>
          <td>{{ $row['name'] }}</td>
          <td>{{ $row['username'] }}</td>
          <td>{{ $row['email'] }}</td>
          <td>{{ $row['phone'] }}</td>
          <td>{{ $row['sold'] }}</td>
          <td>{{ $row['on_hold'] }}</td>
          <td>{{ $row['in_hand'] }}</td>
          <td>{{ $row['earnings'] }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</body>
</html>

