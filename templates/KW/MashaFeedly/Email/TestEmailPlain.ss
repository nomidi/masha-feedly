<%t KW\MashaFeedly\Translations.EMAIL_TEST_BODY "Der E-Mail-Versand von {siteTitle} funktioniert." siteTitle=$SiteTitle.XML %>

<% loop $EmailFooterLines %>$Text.XML
<% end_loop %>
<%t KW\MashaFeedly\Translations.EMAIL_FOOTER_IMPRINT "Impressum" %>: $EmailImprintURL.XML

$EmailFooterOptOutText.XML
<%t KW\MashaFeedly\Translations.EMAIL_FOOTER_PROFILE_LINK "E-Mail-Einstellungen öffnen" %>: $EmailProfileURL.XML
