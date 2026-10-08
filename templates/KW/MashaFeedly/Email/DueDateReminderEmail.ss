<h1><%t KW\MashaFeedly\Translations.EMAIL_DUE_DATE_TITLE "Heute fällig: {title}" title=$BugTitle.XML %></h1>
<p><%t KW\MashaFeedly\Translations.EMAIL_DUE_DATE_INTRO "Der Masha-Feedly-Meldung ist am {date} fällig." date=$DueDate.XML %></p>
<p><a href="$EntryURL.XML"><%t KW\MashaFeedly\Translations.EMAIL_OPEN_DUE_DATE_ENTRY "Meldung öffnen" %></a></p>
<% include KW/MashaFeedly/Email/EmailFooter %>
