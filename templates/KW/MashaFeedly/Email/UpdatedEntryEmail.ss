<% if $CategoryNotice == 'done' %>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:0 0 18px;background:#f1f8f3;border-left:4px solid #43815c">
    <tr><td style="padding:18px 20px;font-family:Arial,sans-serif;color:#284d35">
        <h1 style="margin:0 0 8px;font-size:24px;line-height:1.25"><%t KW\MashaFeedly\Translations.EMAIL_COMPLETED_TITLE "Geschafft!" %></h1>
        <p style="margin:0;font-size:15px;line-height:1.5"><%t KW\MashaFeedly\Translations.EMAIL_COMPLETED_INTRO "Die Meldung „{title}“ auf {siteTitle} wurde abgeschlossen." title=$BugTitle.XML siteTitle=$SiteTitle.XML %></p>
    </td></tr>
</table>
<p style="margin:16px 0;font-family:Arial,sans-serif;font-size:14px"><a href="$EntryURL.XML" style="color:#386b49;text-decoration:underline"><%t KW\MashaFeedly\Translations.EMAIL_VIEW_COMPLETED_ENTRY "Abgeschlossene Meldung ansehen" %></a></p>
<% else_if $CategoryNotice == 'archive' %>
<h1><%t KW\MashaFeedly\Translations.EMAIL_ARCHIVED_TITLE "Meldung archiviert" %></h1>
<p><%t KW\MashaFeedly\Translations.EMAIL_ARCHIVED_INTRO "Die Meldung „{title}“ auf {siteTitle} wurde archiviert." title=$BugTitle.XML siteTitle=$SiteTitle.XML %></p>
<p style="margin:16px 0;font-family:Arial,sans-serif;font-size:14px"><a href="$EntryURL.XML" style="color:#7150b5;text-decoration:underline"><%t KW\MashaFeedly\Translations.EMAIL_VIEW_ARCHIVED_ENTRY "Archivierte Meldung ansehen" %></a></p>
<% else %>
<h1><%t KW\MashaFeedly\Translations.EMAIL_UPDATED_TITLE "Masha:Feedly-Meldung aktualisiert" %></h1>
<% if $NotificationIntroHTML %>$NotificationIntroHTML.RAW<% else %><p><%t KW\MashaFeedly\Translations.EMAIL_UPDATED_INTRO "Die Meldung „{title}“ auf {siteTitle} wurde aktualisiert." title=$BugTitle.XML siteTitle=$SiteTitle.XML %></p><% end_if %>
<p><%t KW\MashaFeedly\Translations.EMAIL_CURRENT_CATEGORY "Aktuelle Kategorie:" %> $CategoryTitle.XML</p>
<p style="margin:22px 0"><a href="$EntryURL.XML" style="display:inline-block;padding:12px 18px;border-radius:9px;background:#e6007e;color:#fff;font-family:Arial,sans-serif;font-size:15px;font-weight:700;line-height:1.2;text-decoration:none"><%t KW\MashaFeedly\Translations.EMAIL_OPEN_UPDATED_ENTRY "Geänderte Meldung in Masha:Feedly öffnen" %></a></p>
<% end_if %>
<% include KW/MashaFeedly/Email/EmailFooter %>
