# Masha:Feedly Browsertests

Die opt-in Playwright-Tests prüfen den Kommentar-Aktivitätsablauf mit zwei getrennten Benutzersitzungen, das vollständige Onboarding vom Neustart in der Hilfe bis zum Danke-Dialog, den Kostenschätzungsablauf mit getrenntem Superadmin und Freigabeperson sowie die Zugriffsmatrix für Gäste und nicht freigegebene Mitglieder.

Voraussetzungen:

- eine lokal erreichbare Silverstripe-Testinstanz mit Masha:Feedly
- Playwright mit Chromium
- zwei Testkonten mit Masha:Feedly-Zugriff für den Kommentar-Aktivitätstest; beide müssen die Einführung bereits abgeschlossen haben
- ein eigenes Onboarding-Testkonto, das in den Masha:Feedly-Zugriffseinstellungen ausdrücklich freigeschaltet ist
- Die lokale Datei `.env.e2e` im selben Ordner mit `MASHA_FEEDLY_E2E_BASE_URL`, `MASHA_FEEDLY_E2E_CREATOR_EMAIL`, `MASHA_FEEDLY_E2E_CREATOR_PASSWORD`, `MASHA_FEEDLY_E2E_COMMENTER_EMAIL` und `MASHA_FEEDLY_E2E_COMMENTER_PASSWORD`
- Zusätzlich für den Onboarding-Test: `MASHA_FEEDLY_E2E_ONBOARDING_EMAIL` und `MASHA_FEEDLY_E2E_ONBOARDING_PASSWORD`. Dieses Konto muss zum erneuten Start der Einführung berechtigt sein; der Test verändert keine Zugriffsfreigaben.
- Zusätzlich für den Kostenschätzungstest: `MASHA_FEEDLY_E2E_ESTIMATE_MANAGER_EMAIL` und `MASHA_FEEDLY_E2E_ESTIMATE_MANAGER_PASSWORD` für die Freigabeperson sowie `MASHA_FEEDLY_E2E_SUPERADMIN_EMAIL` und `MASHA_FEEDLY_E2E_SUPERADMIN_PASSWORD` für den konfigurierten Masha:Feedly-Superadmin. Nur der Superadmin darf Dauer und Erläuterung eintragen und den Stundensatz sehen. Die Freigabeperson sieht Dauer, Erläuterung und geschätzten Preis nur lesend und kann die Freigabe erteilen. Ein positiver Stundensatz muss in den Masha:Feedly-Einstellungen gesetzt sein.
- Für den Zugriffsmatrix-Browsertest wird zusätzlich `MASHA_FEEDLY_E2E_DENIED_EMAIL` und `MASHA_FEEDLY_E2E_DENIED_PASSWORD` verwendet. Dieses angemeldete Testkonto darf **nicht** in den Masha:Feedly-Zugriffseinstellungen freigegeben sein. Der Gasttest läuft mit den Zugangsdaten des freigegebenen Kontos und einer separaten anonymen Browsersitzung.

Die Datei `.env.e2e` wird nicht versioniert. Zugangsdaten können alternativ als Umgebungsvariablen gesetzt werden; diese haben Vorrang vor Dateiwerten.

```sh
node --test masha-feedly/tests/e2e/comment-activity.test.cjs
node --test masha-feedly/tests/e2e/onboarding.test.cjs
node --test masha-feedly/tests/e2e/estimate-workflow.test.cjs
node --test masha-feedly/tests/e2e/access-matrix.test.cjs
node --test --test-concurrency=1 masha-feedly/tests/e2e/*.test.cjs
```

Die gemeinsame E2E-Suite seriell ausführen, weil alle Browserläufe dieselbe lokale Website und Datenbank verwenden.

Ohne vollständige Konfiguration wird der Test übersprungen. Zugangsdaten gehören in die ignorierte lokale Datei oder einen Secret Store, nicht in die Versionsverwaltung.
