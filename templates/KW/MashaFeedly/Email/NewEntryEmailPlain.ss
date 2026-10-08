<%t KW\MashaFeedly\Translations.EMAIL_NEW_TITLE "Neue Meldung" %>
<%t KW\MashaFeedly\Translations.EMAIL_NEW_INTRO "Auf {siteTitle} wurde eine neue Meldung erstellt." siteTitle=$SiteTitle %>

<% if $ReporterName %><%t KW\MashaFeedly\Translations.EMAIL_NEW_REPORTER "Gemeldet von" %>: $ReporterName
<% end_if %><% if $CategoryTitle %><%t KW\MashaFeedly\Translations.EMAIL_NEW_CATEGORY "Kategorie" %>: $CategoryTitle
<% end_if %><% if $PriorityTitle %><%t KW\MashaFeedly\Translations.EMAIL_NEW_PRIORITY "Priorität" %>: $PriorityTitle
<% end_if %><% if $DueDate %><%t KW\MashaFeedly\Translations.EMAIL_NEW_DUE_DATE "Fällig am" %>: $DueDate
<% end_if %><%t KW\MashaFeedly\Translations.EMAIL_NEW_ASSIGNEES "Verantwortlich" %>: <% if $AssignedNames %>$AssignedNames<% else %><%t KW\MashaFeedly\Translations.EMAIL_NEW_NO_ASSIGNEES "Noch niemand zugeordnet" %><% end_if %>

<%t KW\MashaFeedly\Translations.EMAIL_NEW_DESCRIPTION_LABEL "Vollständige Beschreibung" %>:
$BugDescription

<%t KW\MashaFeedly\Translations.EMAIL_OPEN_ENTRY "Meldung ansehen" %>:
$EntryURL

<% include KW/MashaFeedly/Email/EmailFooterPlain %>
