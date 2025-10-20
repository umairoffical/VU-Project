<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $notification->title }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
            color: white;
            padding: 30px 20px;
            border-radius: 10px 10px 0 0;
            text-align: center;
        }
        .content {
            background: #ffffff;
            padding: 30px;
            border: 1px solid #e0e0e0;
            border-top: none;
        }
        .footer {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 0 0 10px 10px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        .priority-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-top: 10px;
        }
        .priority-high {
            background: #f44336;
            color: white;
        }
        .priority-medium {
            background: #ff9800;
            color: white;
        }
        .priority-low {
            background: #4caf50;
            color: white;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background: #1976d2;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .data-table td {
            padding: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        .data-table td:first-child {
            font-weight: bold;
            width: 150px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔐 VuProject Certificate Management</h1>
        <h2>{{ $notification->title }}</h2>
        <span class="priority-badge priority-{{ $notification->priority }}">
            {{ strtoupper($notification->priority) }} PRIORITY
        </span>
    </div>
    
    <div class="content">
        <p>{{ $notification->message }}</p>
        
        @if($notification->data)
            <table class="data-table">
                @foreach(json_decode(json_encode($notification->data), true) as $key => $value)
                    <tr>
                        <td>{{ ucwords(str_replace('_', ' ', $key)) }}</td>
                        <td>{{ is_array($value) ? implode(', ', $value) : $value }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
        
        <p>
            <a href="{{ config('app.url') }}/dashboard" class="button">
                View Dashboard
            </a>
        </p>
        
        <p style="margin-top: 30px; font-size: 14px; color: #666;">
            This is an automated notification from VuProject Certificate Management System.
            For any questions or concerns, please contact your system administrator.
        </p>
    </div>
    
    <div class="footer">
        <p>&copy; {{ date('Y') }} VuProject. All rights reserved.</p>
        <p>Sent on {{ now()->format('F j, Y \a\t g:i A') }}</p>
    </div>
</body>
</html>

