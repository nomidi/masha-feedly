# Masha:Feedly Browsertests

Die opt-in Playwright-Tests prüfen Kommentaraktivität, Onboarding, Kostenschätzungen, Zugriffsschutz, Screenshot-Zuschnitt und den Effekt-Anbieter. Die Suite muss seriell laufen, weil alle Browserläufe dieselbe Website und Datenbank verwenden.

## Konfiguration

Lege die nicht versionierte Datei `.env.e2e` in diesem Verzeichnis an: `masha-feedly/tests/e2e/.env.e2e`. Alternativ können dieselben Variablen in der Shell gesetzt werden; gesetzte Umgebungsvariablen haben Vorrang.

```dotenv
MASHA_FEEDLY_E2E_BASE_URL="https://feedly:8890"
MASHA_FEEDLY_E2E_CREATOR_EMAIL="creator@example.test"
MASHA_FEEDLY_E2E_CREATOR_PASSWORD="..."
MASHA_FEEDLY_E2E_COMMENTER_EMAIL="commenter@example.test"
MASHA_FEEDLY_E2E_COMMENTER_PASSWORD="..."
MASHA_FEEDLY_E2E_SUPERADMIN_EMAIL="superadmin@example.test"
MASHA_FEEDLY_E2E_SUPERADMIN_PASSWORD="..."
MASHA_FEEDLY_E2E_DENIED_EMAIL="denied@example.test"
MASHA_FEEDLY_E2E_DENIED_PASSWORD="..."
```

Damit reichen vier Konten: Creator, Commenter, Superadmin und ein nicht freigegebenes Mitglied. Creator wird auch für den Onboarding-Test verwendet; Commenter übernimmt zusätzlich die Rolle der Kostenschätzungs-Freigabeperson. Es gibt keine zusätzlichen `ONBOARDING_*`- oder `ESTIMATE_MANAGER_*`-Variablen. Alle Konten müssen aktive Silverstripe-Mitglieder mit funktionierendem Login sein. E-Mail-Adressen müssen für die E2E-Browserläufe nicht zustellbar sein.

## Testkonten und erforderliche Rechte

| Konto | Erforderliche Einrichtung | Verwendet von |
| --- | --- | --- |
| **CREATOR** | In **Masha:Feedly → Konfiguration → Benutzer mit Zugriff** freischalten. Normales Mitglied ohne Kostenschätzungsrechte; Einführung bereits abgeschlossen. | Kommentaraktivität, Zugriffsmatrix, Kostenschätzung; Onboarding als Ersatzkonto |
| **COMMENTER / Freigabeperson** | Freischalten; Einführung abgeschlossen. Zusätzlich muss ein Betreiber/Superadmin im Mitgliederprofil **Darf Kostenschätzungen freigeben** aktivieren. Kein CMS-ADMIN nötig; dieses Konto darf nicht als Reporter-Superadmin konfiguriert sein. | Kommentaraktivität und Kostenschätzung: kommentiert/reagiert sowie sieht die Schätzung lesend und gibt sie frei |
| **SUPERADMIN** | Mitglied muss in Silverstripe CMS die Berechtigung **ADMIN** haben, in **Benutzer mit Zugriff** freigeschaltet sein und seine E-Mail-Adresse muss in der Projektkonfiguration `MashaFeedlyEntry.reporter_manager_emails` bzw. `MASHA_FEEDLY_REPORTER_MANAGER_EMAIL` stehen. | Kostenschätzung: trägt Dauer/Erläuterung ein und sieht den Stundensatz |
| **DENIED** | Aktiver Silverstripe-Login, aber **nicht** in **Benutzer mit Zugriff** freischalten. Keine Masha:Feedly-Rolle erforderlich. | Zugriffsmatrix: muss Widget, Einträge, Kommentare und private Anhänge nicht abrufen können |

Zusätzlich für den Kostenschätzungstest muss in der Masha:Feedly-Konfiguration ein **positiver Stundensatz** hinterlegt sein. Commenter/Freigabeperson und Superadmin müssen unterschiedliche Konten sein: Der Superadmin erfasst die Schätzung; Commenter bestätigt sie. Creator muss die Einführung bereits abgeschlossen haben, bevor die Suite läuft; der Onboarding-Test startet sie gezielt neu. CMS-ADMIN-Rechte allein geben keinen Masha:Feedly-Zugriff und machen ein Mitglied nicht zum konfigurierten Superadmin.

Der Gastfall des Zugriffsmatrix-Tests verwendet CREATOR zum Anlegen geschützter Inhalte und eine separate anonyme Browsersitzung. Der DENIED-Fall benötigt zusätzlich die DENIED-Zugangsdaten. Erscheint bei diesem Test das Widget, ist das DENIED-Konto wahrscheinlich versehentlich in der Zugriffsfreigabe eingetragen.

## Tests ausführen

Vom Projekt-Hauptverzeichnis aus:

```sh
node --test masha-feedly/tests/e2e/comment-activity.test.cjs
node --test masha-feedly/tests/e2e/onboarding.test.cjs
node --test masha-feedly/tests/e2e/estimate-workflow.test.cjs
node --test masha-feedly/tests/e2e/access-matrix.test.cjs
node --test masha-feedly/tests/e2e/screenshot-crop.test.cjs
node --test --test-concurrency=1 masha-feedly/tests/e2e/*.test.cjs
```

Ohne vollständige Konfiguration überspringt Node die jeweils betroffenen Tests. Zugangsdaten gehören nur in die ignorierte lokale Datei oder einen Secret Store, niemals in Git. Die Suite legt Testeinträge und Kommentare in der konfigurierten Datenbank an; nur gegen eine lokale oder dafür vorgesehene Testinstanz ausführen.

Der separate **Test-E-Mail senden**-Knopf ist kein Teil der E2E-Tests. Er sendet an die Adresse des angemeldeten Masha:Feedly-Superadmins und setzt einen passend konfigurierten und erreichbaren SMTP-Dienst voraus.

## Externer Effekt-Anbieter

Masha:Effects kann auf einem getrennten Server laufen. Feedly verbindet sich mit der serverseitig konfigurierten Anbieter-URL und gibt die Effekte über seinen eigenen, zugriffsgeschützten Proxy an den Browser weiter. Adresse und Zugangsschlüssel bleiben auf dem Feedly-Server und gehören weder in `.env.e2e` noch in den Browser.

`effects-provider.test.cjs` prüft ausschließlich Feedlys Proxy, dessen Zugriffsschutz, den Icon-Katalog und die Wiedergabe im Widget. Der Test ruft keine Masha:Effects-Routen oder CMS-Seiten auf dem Feedly-Host auf. CMS-, Schlüssel- und Dateizugriffstests des Anbieters gehören in dessen eigenes Repository und müssen gegen dessen Testinstanz laufen.

Vom Projekt-Root:

```sh
node --test masha-feedly/tests/e2e/effects-provider.test.cjs
```

Der mobile Ablauf in `onboarding.test.cjs` prüft die Feedly-Lasche, das Seitenpanel, den mobilen Hilfetext und beide Onboarding-Auswege in Chromium, Firefox und WebKit (Safari-Engine). In Fenstern mit 320 × 400 und 390 × 280 Pixeln wird geprüft, dass der Hilfetext tatsächlich scrollt und der Schließen-Knopf erreichbar bleibt. Dafür müssen die drei Playwright-Browser installiert sein.

## Größenwechsel ohne Neuladen

`node --test masha-feedly/tests/e2e/viewport.test.cjs` prüft Chromium, Firefox und WebKit: geöffnete Hilfe, laufende Einführung, Bereichsauswahl und ein ausgefülltes Formular beim Wechsel Desktop → mobil → Desktop. Der Test prüft geschlossene Fenster am Breakpoint, die neu gestartete Begrüßung, erhaltene Formulareingaben und freigegebene Bedienung nach „OK“ oder Abbrechen. Innerhalb derselben Variante bleibt die Hilfe geöffnet. Einführung-Neustart und -Abschluss werden nur im Browser simuliert; es wird keine Meldung gespeichert.

## Simulierte Touch-Bedienung

Vom Projekt-Root: `node --test masha-feedly/tests/e2e/touch.test.cjs`. Chromium emuliert ein Touchgerät in kleinen Hoch- und Querformatfenstern. Fingertipps öffnen und schließen Seitenpanel und Hilfe sowie den mobilen Einführungshinweis. Eine Wischgeste über das Browser-Eingabeprotokoll scrollt den Hilfetext; der Test setzt `scrollTop` nicht selbst. Hilfekopf und Schließen bleiben erreichbar. Der Test speichert keine Meldungen und verändert den Tourstatus nicht. Die Simulation ersetzt keine Prüfung auf einem echten Smartphone oder Tablet.

## Mobiler Hinweis in Du, Sie und Englisch

Vom Projekt-Root: `node --test masha-feedly/tests/e2e/mobile-language.test.cjs`. Die drei Sprachvarianten laufen jeweils in Chromium, Firefox und WebKit. Die Browserantwort erhält dafür Texte aus den tatsächlichen Sprachdateien und die passende Anrede; Website-Sprache und Benutzerprofile bleiben unverändert. Geprüft werden der sichtbare mobile Hinweis, genau zwei korrekt beschriftete Knöpfe sowie ihre Bedeutung: „OK“ verschiebt die Einführung, „Einführung abbrechen“ beziehungsweise „Cancel tour“ beendet sie. Die Abschlussanfragen werden abgefangen, sodass der gespeicherte Tourstatus unverändert bleibt. Dieser Test prüft die Browserdarstellung, nicht die serverseitige Auswahl der Website-Sprache.

## Farben der Aktionsbuttons

`node --test --test-concurrency=1 masha-feedly/tests/e2e/news-button.test.cjs` prüft Chromium, Firefox und WebKit jeweils mit heller und dunkler Systemdarstellung und beiden Themes. Der Neuigkeiten-Button behält einen hellen Hintergrund mit Verlauf; hinter dem NEW-Symbol liegt keine dunkle Fläche. Plus und Speichern verwenden die pinke Aktionsfarbe auch im sachlichen Theme. Neuigkeiten werden nur in der Browserantwort simuliert; der Test legt keine Meldungen an.
