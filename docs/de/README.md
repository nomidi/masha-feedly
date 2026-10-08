# Masha:Feedly verwenden

Masha:Feedly ist ein gemeinsamer Fehler- und Feedback-Tracker direkt auf der Website. Berechtigte Personen können Meldungen anlegen, diskutieren und ihren Bearbeitungsstand nachverfolgen.

## Zugang und Einrichtung

Administratoren öffnen im CMS **Masha:Feedly → Konfiguration**. Die Einstellungen stehen auf einer Seite in den Abschnitten **Allgemein**, **Zugriff & Darstellung**, **Erinnerungen & Schätzungen** und **Daten zurücksetzen**. Die letzten beiden Abschnitte sind standardmäßig eingeklappt und nur für das ausdrücklich konfigurierte Betreiberkonto sichtbar. Auch Test-E-Mail und Zurücksetzen sieht nur dieses Konto; normale CMS-Administratoren sehen diese Funktionen nicht. Unter **Zugriff & Darstellung** wählst du die Personen aus, die das Modul verwenden dürfen. Auch Administratoren sehen das Widget nur, wenn sie ausdrücklich ausgewählt wurden. Unter **Allgemein** stellst du Ansprache, Schriftgröße und Website-Vorgabe für die Effekt-Kategorie ein. Die Vorgabe ist bei neuen Installationen **Verspielt**. Jede berechtigte Person kann im eigenen Profil eine andere Effekt-Kategorie wählen. Die Kategorien für Startstatus, Erledigt und Freigabe sind erforderlich; ihre Namen können angepasst werden.

Neue berechtigte Personen erhalten eine Willkommens-E-Mail. Beim ersten Besuch führt sie das Onboarding durch die wichtigsten Schritte. Die Einführung lässt sich jederzeit abbrechen und im Profil oder im Hilfefenster erneut starten.

## Einträge auf der Website

1. Öffne Masha:Feedly über das Symbol unten rechts.
2. Wähle **+** und klicke auf den betroffenen Bereich der Seite.
3. Gib einen Titel und eine Beschreibung ein. Ergänze bei Bedarf Status, Priorität, zuständige Personen und Anhänge.
4. Speichere den Eintrag. Ein nummerierter Marker zeigt die angeklickte Stelle am ausgewählten Element. Die Stelle bleibt auch bei anderer Fenstergröße relativ zum Element erhalten. Während das Widget geöffnet ist, folgt der Marker dem Element auch bei Animationen und nachträglichen Layoutänderungen. Neue Einträge speichern einen vollständigen Auswahlpfad, damit gleich aufgebaute Seitenbereiche unterscheidbar bleiben. Bei älteren mehrdeutigen Auswahlpfaden wird der gespeicherte Elementtext zur Zuordnung verwendet; bleibt die Stelle mehrdeutig, erscheint kein Marker.

Kommentare unterstützen Links. Bilder werden im Eintrag angezeigt; PDF- und ZIP-Dateien erscheinen als geschützte Downloadlinks. Nur Personen mit Zugriff auf Masha:Feedly können Einträge und Anhänge aufrufen.

## Einträge finden und bearbeiten

Das Globus-Symbol öffnet offene Einträge der ganzen Website. Das Seitensymbol zeigt offene Einträge der aktuellen Seite. Die Schaltflächen für Neuigkeiten, Feedback und abgeschlossene Einträge öffnen jeweils die passende Liste. Neuigkeiten heben Einträge und Kommentare seit dem letzten Besuch hervor.

In der Liste kannst du nach Kategorie, Priorität, Seite und Zuständigkeit filtern. Über **Filter** lassen sich persönliche Filterkombinationen speichern und später wieder auswählen. Öffne einen Eintrag, um Beschreibung, Browser- und Seitendetails, Kommentare, Anhänge, Verlauf und Zusammenhänge zu sehen. Status, Priorität und Zuständigkeiten werden nach **Änderungen speichern** übernommen.

Der Verlauf hält Status-, Prioritäts- und Zuständigkeitsänderungen, Kommentare, Anhänge und Verknüpfungen mit Person und Zeitpunkt fest. Wenn eine andere Person deinen Eintrag abschließt, kann er auf die erforderliche Freigabe-Kategorie wechseln. Du kannst das Ergebnis anschließend freigeben.

## Zusammenhänge und Duplikate

Im aufklappbaren Bereich **Zusammenhänge** kannst du Einträge als Duplikat, thematisch verwandt oder blockiert durch verknüpfen. Der Zusammenhang wird an beiden Einträgen angezeigt und im Verlauf festgehalten. Wird ein Haupteintrag abgeschlossen, werden seine Duplikate ebenfalls abgeschlossen. Andere Statusänderungen werden nicht automatisch übertragen.

## Profil und Benachrichtigungen

Im Masha:Feedly-Bereich deines Profils kannst du Avatarbild und -farbe festlegen, dein persönliches Erscheinungsbild wählen, einzelne Arten von E-Mail-Benachrichtigungen verwalten und die Einführung erneut aktivieren. Die auswählbaren Effekt-Kategorien kommen vom konfigurierten Masha:Effects-Anbieter. Die Website-Vorgabe wird unter **Masha:Feedly → Konfiguration** festgelegt; ohne Anbieter oder bei einem Ausfall bleibt der lokale Häkchen-Effekt verfügbar. Neue Installationen verwenden als Vorgabe **Verspielt**. Neue und geänderte Einträge, Kommentare zu Einträgen, für die du zuständig bist oder die du erstellt hast, Fälligkeitstermine und Kostenschätzungen lassen sich getrennt einstellen. E-Mails zu eigenen Meldungen und Änderungen sind optional und standardmäßig ausgeschaltet. Neuigkeiten im Widget zeigen Aktivitäten unabhängig von E-Mail-Benachrichtigungen. Die persönlichen E-Mail-Einstellungen bleiben gesperrt, bis der konfigurierte Masha:Feedly-Administrator in der Konfiguration mindestens eine Test-E-Mail erfolgreich versendet hat; im Profil erscheint dazu ein Hinweis.

Wenn `MASHA_FEEDLY_EFFECTS_BASE_URL` und `MASHA_FEEDLY_EFFECTS_API_KEY` auf dem Server gesetzt sind und Masha:Effects einen gültigen Icon-Katalog liefert, kannst du zusätzlich eines der dort bereitgestellten Profil-Icons auswählen. Die 116 SVG-Icons liegen ausschließlich im privaten Ressourcenordner des Anbieters und werden über den geschützten Feedly-Proxy ausgeliefert. Sie sind nach Menschen, Tieren, Natur, Alltag, Hobbys & Technik, Obst & Gemüse, Essen & Trinken, Aliens & UFOs, Weltraum, Grusel und Dinosauriern sortiert. Die Icon-Farbe wechselt passend zu deiner Avatarfarbe zwischen Schwarz und Weiß. Ohne vollständige Anbieter-Konfiguration oder bei einem ungültigen bzw. nicht erreichbaren Katalog erscheint die Auswahl nicht; dein Profilbild-Upload bleibt verfügbar.

Der über `MASHA_FEEDLY_REPORTER_MANAGER_EMAIL` konfigurierte CMS-Administrator kann unter **Masha:Feedly → Konfiguration** mit **Test-E-Mail senden** den Mailversand an die E-Mail-Adresse seines Kontos prüfen. Bei einem Fehler zeigt die Konfiguration zusätzlich die konkrete Ursache an; Zugangsdaten in Verbindungs-URLs werden dabei ausgeblendet. Die vollständigen technischen Details stehen im PHP-Fehlerprotokoll. Schlägt eine Benachrichtigung fehl, wird der Eintrag trotzdem gespeichert.

Einträge können ein optionales Fälligkeitsdatum erhalten. Wähle in den Masha:Feedly-Einstellungen unter **Fälligkeitserinnerungen ausführen**, ob die Prüfung per Cronjob oder beim ersten Websitebesuch des Tages läuft. Beim Besuchsmodus werden keine Erinnerungen versendet, solange niemand die Website aufruft. Die Prüfung sendet am Fälligkeitstag (oder beim nächsten Lauf danach) einmalig eine E-Mail an freigegebene zuständige Personen und die erstellende Person. Änderungen am Termin setzen die Erinnerung zurück.

Für den Cronjob-Modus richte im Silverstripe-Projekt einen täglichen Aufruf ein:

```sh
vendor/bin/sake dev/tasks/MashaFeedlyDueDateReminderTask
```

## Im CMS verwalten

Das Masha:Feedly-Board im CMS gruppiert Einträge nach Kategorie. Berechtigte Personen können Einträge dort bearbeiten und per Drag-and-drop sortieren. Kategorien, Prioritäten und allgemeine Widget-Einstellungen werden ebenfalls im CMS verwaltet.

Das speziell konfigurierte Superadmin-Konto sieht unter **Masha:Feedly → Konfiguration** zusätzlich die Funktion **Alle Masha:Feedly-Daten löschen**. Sie entfernt Einträge und zugehörige Kommentare, Reaktionen, Anhänge, Verknüpfungen, Lesezeichen und Verlauf. Anschließend werden die Standardkategorien einschließlich der optionalen Kostenschätzungskategorien neu angelegt. Benutzer, Profile, Zugriffsrechte und Konfiguration bleiben bestehen. Zur Bestätigung muss `RESET` eingegeben werden.

Wenn ältere Einträge manuell übertragen werden, kann ein Betreiberkonto die angezeigte Meldeperson nachträglich ändern. Trage dafür ausschließlich die E-Mail-Adresse dieses Kontos in der Projektdatei `app/_config.php` ein:

```php
use KW\MashaFeedly\Model\MashaFeedlyEntry;
use SilverStripe\Core\Config\Config;

Config::modify()->set(MashaFeedlyEntry::class, 'reporter_manager_emails', [
    'dein-konto@example.org',
]);
```

Das Konto muss zusätzlich SilverStripe-CMS-Administrator sein. Kundenadmins ohne diese konfigurierte E-Mail erhalten keinen Zugriff auf die Auswahl. Der tatsächliche Ersteller bleibt unverändert und jede Änderung der Meldeperson erscheint im Verlauf.

Für die persönliche Mite-Zeiterfassung hinterlegst du die Verbindung ausschließlich in der `.env`:

```dotenv
MASHA_FEEDLY_MITE_API_KEY="dein-persoenlicher-api-schluessel"
MASHA_FEEDLY_MITE_ACCOUNT="dein-mite-konto"
```

Der API-Schlüssel gehört zu deinem Mite-Benutzer und wird weder im CMS noch im Browser ausgegeben. Nach `dev/build?flush=1` aktivierst du unter **Masha:Feedly → Mite** die Integration und wählst das Standardprojekt sowie eine oder mehrere **Startkategorien** aus. Die **Leistung** wählst du beim Start jedes Timers aus. Mite ist standardmäßig deaktiviert. Der Hauptreiter, der allgemeine Mite-Timerknopf im Widget und die Timerfunktionen sind nur für das CMS-Admin-Konto mit der unter `MASHA_FEEDLY_REPORTER_MANAGER_EMAIL` konfigurierten E-Mail verfügbar. Andere Personen können das Board weiterhin benutzen, sehen den Mite-Knopf aber nicht und erhalten keinen Mite-Dialog.

Verschiebst du selbst einen Fehler im CMS-Board oder änderst im Eintragsdialog den Status in eine ausgewählte **Startkategorie**, erscheint der Dialog **Mite-Timer starten?**. Er lädt verfügbare Projekte und Leistungen und zeigt einen bereits laufenden Timer an. **Timer starten** legt einen Mite-Zeiteintrag mit der vollständigen Fehlerbeschreibung als Bemerkung, dem gewählten Mite-Projekt und der gewählten Leistung an und startet dessen Stoppuhr. Das Standardprojekt ist vorausgewählt; Projekt und Leistung lassen sich für jeden Start auswählen. Über **Mite-Timer** im ersten Bereich des Seitenwidgets kannst du unabhängig davon eine allgemeine Zeiterfassung ohne Feedly-Eintrag starten oder einen laufenden Timer stoppen. Allgemeine Zeiteinträge erhalten in Mite den Hinweis „Masha:Feedly – allgemeine Zeiterfassung“.

Mite erlaubt nur einen laufenden Timer pro Benutzer. Ändert sich der laufende Timer nach Öffnen des Dialogs, musst du ihn über **Neu laden** erneut prüfen. Bei einem API-Fehler bleibt der Fehler in der gewählten Kategorie; der Dialog zeigt die Ursache. Feedly speichert keine Mite-Zeiteinträge oder Zuordnungen dazu. Zeitdaten bleiben in Mite. Im Widget-Dialog kannst du einen laufenden Timer mit **Timer stoppen** beenden. Bloßes Umsortieren innerhalb derselben Kategorie öffnet keinen Dialog. Bei deaktiviertem Mite erscheinen keine Timerdialoge und bereits geöffnete Dialoge können keinen Timer mehr starten oder stoppen. Beim Zurücksetzen von Feedly werden die Startkategorien entfernt und Mite deaktiviert.

Im CMS findest du die Übersicht unter **Masha:Feedly → Einträge → Meldepersonen ändern**. Dort kannst du Einträge nach ID oder Titel suchen und die angezeigte Person direkt in der Zeile speichern.

## Installation und Tests

Installation und Befehle für PHP- und JavaScript-Tests stehen in der [Projekt-README](../../../README.md). Browserbasierte Ende-zu-Ende-Tests sind in [`../../tests/e2e/README.md`](../../tests/e2e/README.md) beschrieben.

## Abschluss-Effekte

Die Effektversorgung ist optional. Ohne erreichbaren Anbieter spielt Feedly nach einem bestätigten Abschluss ein lokales, dezentes Häkchen ab. Das gilt auch bei leerem Katalog oder einem Fehler beim Laden eines Effekts.

Für zentral verwaltete Animationen kann das eigenständige Silverstripe-Modul **Masha:Effects** auf derselben oder einer anderen Website installiert werden. Dort werden Kategorien, Effekte, Dateien und saisonale Regeln verwaltet. Dateien liegen privat auf dem Anbieter-Server. Feedly enthält nur den Loader und lädt passende Effekte über einen geschützten Server-Proxy. Ein Anbieterausfall verhindert das Speichern nicht.

`MASHA_FEEDLY_EFFECTS_BASE_URL` in der Server-`.env` der Feedly-Website enthält die Basis-Adresse des Anbieters, zum Beispiel `https://effects.example.org`; Feedly ergänzt `/__masha-effects/manifest`. Ohne Einstellung sucht Feedly auf derselben Website. Dort muss Masha:Effects installiert und eingerichtet sein; andernfalls greift der lokale Ersatzeffekt. Auch bei einem nicht erreichbaren Anbieter oder einem leeren Katalog verhindert der Ersatzeffekt nicht das Speichern.

Für einen externen Anbieter wird `MASHA_FEEDLY_EFFECTS_API_KEY` in der Server-`.env` der Feedly-Website hinterlegt. Der Schlüssel wird im CMS des Anbieters erzeugt und nur serverseitig verwendet. Browser erhalten ausschließlich lokale Proxy-URLs; der Proxy prüft Anmeldung und Feedly-Freigabe.

### Schnittstelle eines externen Effekt-Anbieters

Der Anbieter muss HTTPS unterstützen und diese beiden GET-Endpunkte bereitstellen. Beide erwarten `Authorization: Bearer <Schlüssel>`:

- `GET /__masha-effects/manifest` liefert JSON mit `Content-Type: application/json`.
- `GET /__masha-effects/file/{ID}/{SHA256}/{Typ}` liefert eine versionierte JS-, CSS- oder Bilddatei.

Beispiel für ein Manifest:

```json
{
  "version": 2,
  "maxAge": 300,
  "categories": [{"id": "serious", "name": "Sachlich"}],
  "effects": [
    {
      "id": "myEffect",
      "name": "Mein Effekt",
      "categories": ["serious"],
      "weight": 1,
      "files": {
        "js": "https://effects.example.org/__masha-effects/file/42/0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef/js",
        "css": "https://effects.example.org/__masha-effects/file/42/abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789/css",
        "image": "https://effects.example.org/__masha-effects/file/42/abcdefabcdef0123456789abcdef0123456789abcdef0123456789abcdef01/image"
      }
    }
  ]
}
```

`maxAge` gibt die Katalog-Lebensdauer in Sekunden an; Feedly begrenzt sie auf höchstens 300 Sekunden. `categories` enthält aktive Kategorien mit stabiler `id` und Anzeigename `name`; IDs entsprechen `/^[a-z][a-z0-9_-]{0,79}$/`. Effekte benötigen mindestens eine Kategorie und können mehreren zugeordnet sein. `effects` darf leer sein und höchstens 1000 Einträge enthalten. Jeder Eintrag braucht `id`, `name`, `categories`, `weight` und `files.js`. `id` muss `/^[A-Za-z][A-Za-z0-9_-]{0,79}$/` entsprechen; `weight` ist eine Ganzzahl von 1 bis 100. `files.css` und `files.image` sind optional.

Jede Datei-URL muss absolut sein, denselben HTTPS-Host und Basispfad wie der Anbieter verwenden und dem Muster `/__masha-effects/file/{positive ID}/{64 kleingeschriebene Hex-Zeichen}/{js|css|image}` entsprechen. `{SHA256}` ist der SHA-256-Hash der unveränderten Dateibytes. Feedly folgt keinen Weiterleitungen und prüft URL, Dateihash und MIME-Typ. Erlaubte MIME-Typen sind `text/javascript` oder `application/javascript` für JavaScript, `text/css` für Stylesheets sowie `image/svg+xml`, `image/png`, `image/jpeg`, `image/webp` oder `image/gif` für Bilder. Ohne gültigen Schlüssel soll der Anbieter `403` liefern. Für geschützte Antworten werden `Cache-Control: private, no-store`, `Vary: Authorization` und `X-Content-Type-Options: nosniff` empfohlen.
