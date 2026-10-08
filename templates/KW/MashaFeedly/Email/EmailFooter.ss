<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:24px;border-collapse:collapse;border-top:1px dashed #d8d1dc;color:#6d6672;font-family:Arial,sans-serif;font-size:11px;line-height:1.45">
    <tr><td style="padding:12px 14px;border-left:3px solid #e6007e;background:#faf8fc">
        <p style="margin:0 0 8px;font-weight:600;color:#51485a"><% loop $EmailFooterLines %>$Text.XML<br><% end_loop %></p>
        <p style="margin:0 0 6px"><a href="$EmailImprintURL.XML" style="color:#7150b5;text-decoration:underline"><%t KW\MashaFeedly\Translations.EMAIL_FOOTER_IMPRINT "Impressum" %></a></p>
        <p style="margin:0">$EmailFooterOptOutText.XML<br><a href="$EmailProfileURL.XML" style="color:#7150b5;text-decoration:underline"><%t KW\MashaFeedly\Translations.EMAIL_FOOTER_PROFILE_LINK "E-Mail-Einstellungen ändern" %></a></p>
    </td></tr>
</table>
