VuProject Certificate Management
================================

{{ $notification->title }}

Priority: {{ strtoupper($notification->priority) }}

{{ $notification->message }}

@if($notification->data)
Details:
--------
@foreach(json_decode(json_encode($notification->data), true) as $key => $value)
{{ ucwords(str_replace('_', ' ', $key)) }}: {{ is_array($value) ? implode(', ', $value) : $value }}
@endforeach
@endif

View your dashboard: {{ config('app.url') }}/dashboard

---
This is an automated notification from VuProject Certificate Management System.
Sent on {{ now()->format('F j, Y \a\t g:i A') }}

© {{ date('Y') }} VuProject. All rights reserved.

