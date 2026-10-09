<%t KW\MashaFeedly\Translations.EMAIL_COMMENT_TITLE "Neuer Kommentar zu einer Masha:Feedly-Meldung" %>

<% if $NotificationIntroPlain %>$NotificationIntroPlain<% else %><%t KW\MashaFeedly\Translations.EMAIL_COMMENT_INTRO "{author} hat einen Kommentar zu einer dir zugewiesenen Meldung auf {siteTitle} geschrieben." author=$CommentAuthor siteTitle=$SiteTitle %><% end_if %>

<%t KW\MashaFeedly\Translations.EMAIL_PREVIEW_ENTRY_DETAILS "Meldungsdetails" %>
<%t KW\MashaFeedly\Translations.EMAIL_ENTRY_LABEL "Meldung" %>: $BugTitle
<% if $ReporterName %><%t KW\MashaFeedly\Translations.EMAIL_NEW_REPORTER "Gemeldet von" %>: $ReporterName
<% end_if %><% if $CategoryTitle %><%t KW\MashaFeedly\Translations.EMAIL_NEW_CATEGORY "Kategorie" %>: $CategoryTitle
<% end_if %><% if $PriorityTitle %><%t KW\MashaFeedly\Translations.EMAIL_NEW_PRIORITY "Priorität" %>: $PriorityTitle
<% end_if %><% if $DueDate %><%t KW\MashaFeedly\Translations.EMAIL_NEW_DUE_DATE "Fällig am" %>: $DueDate
<% end_if %><%t KW\MashaFeedly\Translations.EMAIL_NEW_ASSIGNEES "Verantwortlich" %>: <% if $AssignedNames %>$AssignedNames<% else %><%t KW\MashaFeedly\Translations.EMAIL_NEW_NO_ASSIGNEES "Noch niemand zugeordnet" %><% end_if %>

<%t KW\MashaFeedly\Translations.EMAIL_PREVIEW_COMMENT_TITLE "Neuer Kommentar von {author}" author=$CommentAuthor %>
$CommentText
<% if $CommentDate %>$CommentDate
<% end_if %>

<%t KW\MashaFeedly\Translations.EMAIL_OPEN_COMMENT_ENTRY "Meldung und Kommentar in Masha:Feedly öffnen" %>: $EntryURL

<% include KW/MashaFeedly/Email/EmailFooterPlain %>
