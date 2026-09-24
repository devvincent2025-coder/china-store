@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
@if (trim($slot) === 'Laravel' || trim($slot) === config('app.name'))
<img src="{{ asset('images/branding/icon.png') }}" width="32" height="32" alt="" style="vertical-align: middle; border-radius: 8px; margin-right: 8px;">
<span style="font-family: 'Urbanist', 'Manrope', Helvetica, Arial, sans-serif; font-size: 24px; font-weight: 800; letter-spacing: -0.5px; vertical-align: middle;">
<span style="color: #ff5000;">China</span><span style="color: #FF6A00;">In</span><span style="color: #1FA854;">Nigeria</span>
</span>
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
