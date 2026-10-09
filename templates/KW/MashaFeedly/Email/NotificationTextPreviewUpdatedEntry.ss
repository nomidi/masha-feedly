<h1 style="margin:0 0 10px;color:#272331;font-family:Arial,sans-serif;font-size:24px;line-height:1.3"><%t KW\MashaFeedly\Translations.EMAIL_UPDATED_TITLE "Meldung geändert" %></h1>
$NotificationIntroHTML.RAW
<div style="margin:18px 0;padding:18px 20px;border:1px solid #eee7ef;border-radius:12px;background:#fbf8fb;font-family:Arial,sans-serif;color:#272331">
    <p style="margin:0 0 14px;color:#514b59;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.04em"><%t KW\MashaFeedly\Translations.EMAIL_PREVIEW_CHANGES_TITLE "Was wurde geändert?" %></p>
    <table role="presentation" style="width:100%;border-collapse:collapse;font-size:14px;line-height:1.5">
        <tr><td style="padding:8px 12px 8px 0;color:#6d6672;border-bottom:1px solid #eee7ef"><%t KW\MashaFeedly\Translations.EMAIL_PREVIEW_STATUS "Status" %></td><td style="padding:8px 0;border-bottom:1px solid #eee7ef"><span style="color:#8a8490;text-decoration:line-through">Backlog</span> <span style="color:#e6007e;font-weight:700">→ In Bearbeitung</span></td></tr>
        <tr><td style="padding:8px 12px 8px 0;color:#6d6672;border-bottom:1px solid #eee7ef"><%t KW\MashaFeedly\Translations.EMAIL_PREVIEW_PRIORITY "Priorität" %></td><td style="padding:8px 0;border-bottom:1px solid #eee7ef"><span style="color:#8a8490;text-decoration:line-through">Normal</span> <span style="color:#e6007e;font-weight:700">→ Hoch</span></td></tr>
        <tr><td style="padding:8px 12px 8px 0;color:#6d6672;border-bottom:1px solid #eee7ef"><%t KW\MashaFeedly\Translations.EMAIL_PREVIEW_DUE_DATE "Fälligkeit" %></td><td style="padding:8px 0;border-bottom:1px solid #eee7ef"><span style="color:#8a8490;text-decoration:line-through">10.10.2026</span> <span style="color:#e6007e;font-weight:700">→ 12.10.2026</span></td></tr>
        <tr><td style="padding:8px 12px 0 0;color:#6d6672"><%t KW\MashaFeedly\Translations.EMAIL_PREVIEW_ASSIGNEES "Zuständig" %></td><td style="padding:8px 0"><span style="color:#8a8490;text-decoration:line-through">Alex Beispiel</span> <span style="color:#e6007e;font-weight:700">→ Sam Beispiel</span></td></tr>
    </table>
</div>
<p style="margin:22px 0"><a href="$EntryURL.XML" style="display:inline-block;padding:12px 18px;border-radius:9px;background:#e6007e;color:#fff;font-family:Arial,sans-serif;font-size:15px;font-weight:700;line-height:1.2;text-decoration:none"><%t KW\MashaFeedly\Translations.EMAIL_OPEN_UPDATED_ENTRY "Geänderte Meldung in Masha:Feedly öffnen" %></a></p>
<% include KW/MashaFeedly/Email/EmailFooter %>
