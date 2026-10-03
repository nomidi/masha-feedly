# Masha:Feedly Browsertests

Die opt-in Playwright-Tests prüfen den Kommentar-Aktivitätsablauf mit zwei getrennten Benutzersitzungen sowie das vollständige Onboarding vom Neustart in der Hilfe bis zum Danke-Dialog.

Voraussetzungen:

- eine lokal erreichbare Silverstripe-Testinstanz mit Masha:Feedly
- Playwright mit Chromium
- zwei Testkonten mit Masha:Feedly-Zugriff für den Kommentar-Aktivitätstest; beide müssen die Einführung bereits abgeschlossen haben
- ein eigenes Onboarding-Testkonto, das in den Masha:Feedly-Zugriffseinstellungen ausdrücklich freigeschaltet ist
- Die lokale Datei `.env.e2e` im selben Ordner mit `MASHA_FEEDLY_E2E_BASE_URL`, `MASHA_FEEDLY_E2E_CREATOR_EMAIL`, `MASHA_FEEDLY_E2E_CREATOR_PASSWORD`, `MASHA_FEEDLY_E2E_COMMENTER_EMAIL` und `MASHA_FEEDLY_E2E_COMMENTER_PASSWORD`
- Zusätzlich für den Onboarding-Test: `MASHA_FEEDLY_E2E_ONBOARDING_EMAIL` und `MASHA_FEEDLY_E2E_ONBOARDING_PASSWORD`. Dieses Konto muss zum erneuten Start der Einführung berechtigt sein; der Test verändert keine Zugriffsfreigaben.

Die Datei `.env.e2e` wird nicht versioniert. Zugangsdaten können alternativ als Umgebungsvariablen gesetzt werden; diese haben Vorrang vor Dateiwerten.

```sh
node --test masha-feedly/tests/e2e/comment-activity.test.cjs
node --test masha-feedly/tests/e2e/onboarding.test.cjs
```

Ohne vollständige Konfiguration wird der Test übersprungen. Zugangsdaten gehören in die ignorierte lokale Datei oder einen Secret Store, nicht in die Versionsverwaltung.
