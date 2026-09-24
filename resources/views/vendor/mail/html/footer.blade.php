<tr>
<td>
<table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell" align="center">
{{ Illuminate\Mail\Markdown::parse($slot) }}
<p style="margin-top: 16px; color: #a1a1aa; font-size: 12px;">
&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.<br>
<a href="{{ config('app.url') }}/account/email-preferences" style="color: #a1a1aa; text-decoration: underline;">Manage email preferences / Unsubscribe</a>
</p>
</td>
</tr>
</table>
</td>
</tr>
