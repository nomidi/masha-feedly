<%t KW\MashaFeedly\Translations.EMAIL_DUE_DATE_TITLE "Heute fällig: {title}" title=$BugTitle %>
<% if $NotificationIntroPlain %>$NotificationIntroPlain<% else %><%t KW\MashaFeedly\Translations.EMAIL_DUE_DATE_INTRO "Der Masha-Feedly-Meldung ist am {date} fällig." date=$DueDate %><% end_if %>

<%t KW\MashaFeedly\Translations.EMAIL_PREVIEW_ENTRY_DETAILS "Meldungsdetails" %>
<%t KW\MashaFeedly\Translations.EMAIL_NEW_DUE_DATE "Fällig am" %>: $DueDate
<% if $ReporterName %><%t KW\MashaFeedly\Translations.EMAIL_NEW_REPORTER "Gemeldet von" %>: $ReporterName
<% end_if %><% if $CategoryTitle %><%t KW\MashaFeedly\Translations.EMAIL_NEW_CATEGORY "Kategorie" %>: $CategoryTitle
<% end_if %><% if $PriorityTitle %><%t KW\MashaFeedly\Translations.EMAIL_NEW_PRIORITY "Priorität" %>: $PriorityTitle
<% end_if %><%t KW\MashaFeedly\Translations.EMAIL_NEW_ASSIGNEES "Verantwortlich" %>: <% if $AssignedNames %>$AssignedNames<% else %><%t KW\MashaFeedly\Translations.EMAIL_NEW_NO_ASSIGNEES "Noch niemand zugeordnet" %><% end_if %>
<% if $BugDescription %>
<%t KW\MashaFeedly\Translations.EMAIL_NEW_DESCRIPTION_LABEL "Beschreibung" %>:
$BugDescription
<% end_if %>

<%t KW\MashaFeedly\Translations.EMAIL_OPEN_DUE_DATE_ENTRY "Meldung öffnen" %>: $EntryURL

<% include KW/MashaFeedly/Email/EmailFooterPlain %>
