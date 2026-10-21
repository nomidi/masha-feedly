<h1><%t KW\MashaFeedly\Translations.ERRORS_DIGEST "Tägliche Fehlerzusammenfassung" %></h1>
<% loop $Errors %>
<p><strong>$Title.XML</strong><br>$Summary.XML</p><p style="margin:22px 0"><a href="$URL.XML" style="display:inline-block;padding:12px 18px;border-radius:9px;background:#e6007e;color:#fff;font-family:Arial,sans-serif;font-size:15px;font-weight:700;line-height:1.2;text-decoration:none"><%t KW\MashaFeedly\Translations.EMAIL_OPEN_UPDATED_ENTRY "Geänderte Meldung in Masha:Feedly öffnen" %></a></p>
<% end_loop %>
<% include KW/MashaFeedly/Email/EmailFooter %>
