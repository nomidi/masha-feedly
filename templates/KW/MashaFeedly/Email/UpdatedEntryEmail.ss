<h1><%t KW\MashaFeedly\Translations.EMAIL_UPDATED_TITLE "Ein Masha-Feedly-Meldung wurde geändert" %></h1>
<p><%t KW\MashaFeedly\Translations.EMAIL_UPDATED_INTRO "Die Meldung „{title}“ auf {siteTitle} wurde aktualisiert." title=$BugTitle.XML siteTitle=$SiteTitle.XML %></p>
<p><%t KW\MashaFeedly\Translations.EMAIL_CURRENT_CATEGORY "Aktuelle Kategorie:" %> $CategoryTitle.XML</p>
<p><a href="$EntryURL.XML"><%t KW\MashaFeedly\Translations.EMAIL_OPEN_UPDATED_ENTRY "Geänderte Meldung in Masha:Feedly öffnen" %></a></p>
<% include KW/MashaFeedly/Email/EmailFooter %>
