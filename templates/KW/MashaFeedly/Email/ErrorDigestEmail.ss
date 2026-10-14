<h1><%t KW\MashaFeedly\Translations.ERRORS_DIGEST "Tägliche Fehlerzusammenfassung" %></h1>
<% loop $Errors %>
<p><strong>$Title.XML</strong><br>$Summary.XML<br><a href="$URL.XML"><%t KW\MashaFeedly\Translations.EMAIL_OPEN_UPDATED_ENTRY "Geänderte Meldung in Masha:Feedly öffnen" %></a></p>
<% end_loop %>
<% include KW/MashaFeedly/Email/EmailFooter %>
