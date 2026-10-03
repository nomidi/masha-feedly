# Masha:Feedly Browsertests

Der opt-in Playwright-Test prüft mit zwei voneinander getrennten Benutzersitzungen den Ablauf „Eintrag erstellen → von anderem Benutzer kommentieren → neue Aktivität beim Ersteller anzeigen → beim Öffnen als gelesen markieren“.

Voraussetzungen:

- eine lokal erreichbare Silverstripe-Testinstanz mit Masha:Feedly
- Playwright mit Chromium
- zwei Testkonten mit Masha:Feedly-Zugriff; beide müssen die Einführung bereits abgeschlossen haben
- Die lokale Datei `.env.e2e` im selben Ordner mit `MASHA_FEEDLY_E2E_BASE_URL`, `MASHA_FEEDLY_E2E_CREATOR_EMAIL`, `MASHA_FEEDLY_E2E_CREATOR_PASSWORD`, `MASHA_FEEDLY_E2E_COMMENTER_EMAIL` und `MASHA_FEEDLY_E2E_COMMENTER_PASSWORD`

Die Datei `.env.e2e` wird nicht versioniert. Zugangsdaten können alternativ als Umgebungsvariablen gesetzt werden; diese haben Vorrang vor Dateiwerten.

```sh
node --test masha-feedly/tests/e2e/comment-activity.test.cjs
```

Ohne vollständige Konfiguration wird der Test übersprungen. Zugangsdaten gehören in die ignorierte lokale Datei oder einen Secret Store, nicht in die Versionsverwaltung.
