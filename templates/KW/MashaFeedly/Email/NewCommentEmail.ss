<h1><%t KW\MashaFeedly\Translations.EMAIL_COMMENT_TITLE "Neuer Kommentar zu einer Masha:Feedly-Meldung" %></h1>
<p><%t KW\MashaFeedly\Translations.EMAIL_COMMENT_INTRO "{author} hat einen Kommentar zu einer dir zugewiesenen Meldung auf {siteTitle} geschrieben." author=$CommentAuthor siteTitle=$SiteTitle %></p>
<p><%t KW\MashaFeedly\Translations.EMAIL_ENTRY_LABEL "Meldung:" %> $BugTitle.XML</p>
<blockquote>$CommentText.XML</blockquote>
<p><a href="$EntryURL.XML"><%t KW\MashaFeedly\Translations.EMAIL_OPEN_COMMENT_ENTRY "Meldung und Kommentar in Masha:Feedly öffnen" %></a></p>
<% include KW/MashaFeedly/Email/EmailFooter %>
