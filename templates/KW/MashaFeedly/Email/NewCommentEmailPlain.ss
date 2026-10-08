<%t KW\MashaFeedly\Translations.EMAIL_COMMENT_TITLE "Neuer Kommentar zu einer Masha:Feedly-Meldung" %>

<%t KW\MashaFeedly\Translations.EMAIL_COMMENT_INTRO "{author} hat einen Kommentar zu einer dir zugewiesenen Meldung auf {siteTitle} geschrieben." author=$CommentAuthor siteTitle=$SiteTitle %>

<%t KW\MashaFeedly\Translations.EMAIL_ENTRY_LABEL "Meldung:" %> $BugTitle

$CommentText

<%t KW\MashaFeedly\Translations.EMAIL_OPEN_COMMENT_ENTRY "Meldung und Kommentar in Masha:Feedly öffnen" %>:
$EntryURL

<% include KW/MashaFeedly/Email/EmailFooterPlain %>
