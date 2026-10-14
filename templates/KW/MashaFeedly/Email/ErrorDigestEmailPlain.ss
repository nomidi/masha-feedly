<%t KW\MashaFeedly\Translations.ERRORS_DIGEST "Tägliche Fehlerzusammenfassung" %>

<% loop $Errors %>
$Title
$Summary
$URL

<% end_loop %>
<% include KW/MashaFeedly/Email/EmailFooterPlain %>
