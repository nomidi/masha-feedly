------
<% loop $EmailFooterLines %>$Text.XML
<% end_loop %>
<%t KW\MashaFeedly\Translations.EMAIL_FOOTER_IMPRINT "Impressum" %>: $EmailImprintURL.XML

$EmailOptOutText.XML
<%t KW\MashaFeedly\Translations.EMAIL_FOOTER_PROFILE_LINK "E-Mail-Einstellungen öffnen" %>: $EmailProfileURL.XML
