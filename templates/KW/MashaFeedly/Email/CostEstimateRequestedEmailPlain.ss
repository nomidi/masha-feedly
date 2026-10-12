<%t KW\MashaFeedly\Translations.EMAIL_ESTIMATE_TITLE "Kostenschätzung zur Freigabe" %>
<% if $NotificationIntroPlain %>$NotificationIntroPlain<% else %><%t KW\MashaFeedly\Translations.EMAIL_ESTIMATE_REQUEST_INTRO "Für die Meldung {title} liegt eine Kostenschätzung zur Freigabe vor." title=$EntryTitle %><% end_if %>

<%t KW\MashaFeedly\Translations.EMAIL_PREVIEW_ENTRY_DETAILS "Meldungsdetails" %>
<%t KW\MashaFeedly\Translations.EMAIL_ENTRY_LABEL "Meldung" %>: $EntryTitle
<% if $ReporterName %><%t KW\MashaFeedly\Translations.EMAIL_NEW_REPORTER "Gemeldet von" %>: $ReporterName
<% end_if %><% if $CategoryTitle %><%t KW\MashaFeedly\Translations.EMAIL_NEW_CATEGORY "Kategorie" %>: $CategoryTitle
<% end_if %><% if $PriorityTitle %><%t KW\MashaFeedly\Translations.EMAIL_NEW_PRIORITY "Priorität" %>: $PriorityTitle
<% end_if %><% if $DueDate %><%t KW\MashaFeedly\Translations.EMAIL_NEW_DUE_DATE "Fällig am" %>: $DueDate
<% end_if %><%t KW\MashaFeedly\Translations.EMAIL_NEW_ASSIGNEES "Verantwortlich" %>: <% if $AssignedNames %>$AssignedNames<% else %><%t KW\MashaFeedly\Translations.EMAIL_NEW_NO_ASSIGNEES "Noch niemand zugeordnet" %><% end_if %>
<% if $EstimateDuration %>
<%t KW\MashaFeedly\Translations.EMAIL_ESTIMATE_DETAILS "Kostenschätzung" %>: $EstimateDuration
<% if $EstimatePrice %><%t KW\MashaFeedly\Translations.EMAIL_ESTIMATE_PRICE "Voraussichtliche Kosten" %>: $EstimatePrice
<% end_if %><% if $EstimateNote %>$EstimateNote
<% end_if %><% end_if %><% if $BugDescription %>
<%t KW\MashaFeedly\Translations.EMAIL_NEW_DESCRIPTION_LABEL "Beschreibung" %>:
$BugDescription
<% end_if %>

<%t KW\MashaFeedly\Translations.EMAIL_ESTIMATE_REQUEST_LINK "Kostenschätzung prüfen und freigeben" %>: $EntryURL

<% include KW/MashaFeedly/Email/EmailFooterPlain %>
