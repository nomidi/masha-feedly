<% if $CategoryNotice == 'done' %>
<%t KW\MashaFeedly\Translations.EMAIL_COMPLETED_TITLE "Geschafft!" %>
<%t KW\MashaFeedly\Translations.EMAIL_COMPLETED_INTRO "Die Meldung „{title}“ auf {siteTitle} wurde abgeschlossen." title=$BugTitle siteTitle=$SiteTitle %>
<%t KW\MashaFeedly\Translations.EMAIL_VIEW_COMPLETED_ENTRY "Abgeschlossene Meldung ansehen" %>:
$EntryURL
<% else_if $CategoryNotice == 'archive' %>
<%t KW\MashaFeedly\Translations.EMAIL_ARCHIVED_TITLE "Meldung archiviert" %>
<%t KW\MashaFeedly\Translations.EMAIL_ARCHIVED_INTRO "Die Meldung „{title}“ auf {siteTitle} wurde archiviert." title=$BugTitle siteTitle=$SiteTitle %>
<%t KW\MashaFeedly\Translations.EMAIL_VIEW_ARCHIVED_ENTRY "Archivierte Meldung ansehen" %>:
$EntryURL
<% else %>
<%t KW\MashaFeedly\Translations.EMAIL_UPDATED_TITLE "Masha:Feedly-Meldung aktualisiert" %>

<% if $NotificationIntroPlain %>$NotificationIntroPlain<% end_if %>

<%t KW\MashaFeedly\Translations.EMAIL_ENTRY_LABEL "Meldung:" %> $BugTitle
<%t KW\MashaFeedly\Translations.EMAIL_WEBSITE_LABEL "Website:" %> $SiteTitle
<%t KW\MashaFeedly\Translations.EMAIL_CURRENT_CATEGORY "Aktuelle Kategorie:" %> $CategoryTitle

<%t KW\MashaFeedly\Translations.EMAIL_OPEN_UPDATED_ENTRY "Geänderte Meldung in Masha:Feedly öffnen" %>:
$EntryURL
<% end_if %>

<% include KW/MashaFeedly/Email/EmailFooterPlain %>
