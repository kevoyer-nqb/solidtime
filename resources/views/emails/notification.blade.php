@component('mail::message')
{{ $body }}

@if($actionUrl)
@component('mail::button', ['url' => $actionUrl])
{{ $title }}
@endcomponent
@endif

@endcomponent
