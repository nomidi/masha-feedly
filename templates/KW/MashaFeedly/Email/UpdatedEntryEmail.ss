<h1><%t KW\MashaFeedly\Translations.EMAIL_UPDATED_TITLE "Ein Masha-Feedly-Meldung wurde geändert" %></h1>
<% if $NotificationIntroHTML %>$NotificationIntroHTML.RAW<% else %><p><%t KW\MashaFeedly\Translations.EMAIL_UPDATED_INTRO "Die Meldung „{title}“ auf {siteTitle} wurde aktualisiert." title=$BugTitle.XML siteTitle=$SiteTitle.XML %></p><% end_if %>
<p><%t KW\MashaFeedly\Translations.EMAIL_CURRENT_CATEGORY "Aktuelle Kategorie:" %> $CategoryTitle.XML</p>
<p style="margin:22px 0"><a href="$EntryURL.XML" style="display:inline-block;padding:12px 18px;border-radius:9px;background:#e6007e;color:#fff;font-family:Arial,sans-serif;font-size:15px;font-weight:700;line-height:1.2;text-decoration:none"><%t KW\MashaFeedly\Translations.EMAIL_OPEN_UPDATED_ENTRY "Geänderte Meldung in Masha:Feedly öffnen" %></a></p>
<% include KW/MashaFeedly/Email/EmailFooter %>
