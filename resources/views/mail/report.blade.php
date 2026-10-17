<x-mail::message>
# {{ $title }}

{{ $body }}

@if ($url)
<x-mail::button :url="$url">
{{ __('saved-filter::saved-filter.mail.action') }}
</x-mail::button>
@endif

@if ($schedule)
{{ $schedule }} {{ __('saved-filter::saved-filter.mail.stop') }}
@endif

{{ __('Regards,') }}<br>
{{ config('app.name') }}
</x-mail::message>
