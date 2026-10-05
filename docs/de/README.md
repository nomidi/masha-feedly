# Masha:Feedly verwenden

Masha:Feedly ist ein gemeinsamer Fehler- und Feedback-Tracker direkt auf der Website. Berechtigte Personen können Meldungen anlegen, diskutieren und ihren Bearbeitungsstand nachverfolgen.

## Zugang und Einrichtung

Administratoren öffnen im CMS **Masha:Feedly → Konfiguration** und wählen die Personen aus, die das Modul verwenden dürfen. Auch Administratoren sehen das Widget nur, wenn sie ausdrücklich ausgewählt wurden. Im selben Bereich lassen sich Ansprache, Schriftgröße, Erscheinungsbild, Kategorien und Prioritäten einstellen. Die Kategorien für Startstatus, Erledigt und Freigabe sind erforderlich; ihre Namen können angepasst werden.

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

Im Masha:Feedly-Bereich deines Profils kannst du Avatarbild und -farbe festlegen, einzelne Arten von E-Mail-Benachrichtigungen verwalten und die Einführung erneut aktivieren. Neue und geänderte Einträge, Kommentare zu Einträgen, für die du zuständig bist oder die du erstellt hast, Fälligkeitstermine und Kostenschätzungen lassen sich getrennt einstellen. E-Mails zu eigenen Meldungen und Änderungen sind optional und standardmäßig ausgeschaltet. Neuigkeiten im Widget zeigen Aktivitäten unabhängig von E-Mail-Benachrichtigungen.

CMS-Administratoren können unter **Masha:Feedly → Konfiguration** mit **Test-E-Mail senden** den Mailversand an die E-Mail-Adresse ihres Kontos prüfen. Schlägt eine Benachrichtigung fehl, wird der Eintrag trotzdem gespeichert; der Fehler steht im PHP-Fehlerprotokoll.

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
MASHA_FEEDLY_MITE_ACCOUNT="kooperative-web"
```

Der API-Schlüssel gehört zu deinem Mite-Benutzer und wird weder im CMS noch im Browser ausgegeben. Nach `dev/build?flush=1` aktivierst du unter **Masha:Feedly → Mite** die Integration und wählst das Standardprojekt sowie eine oder mehrere **Startkategorien** aus. Die **Leistung** wählst du beim Start jedes Timers aus. Mite ist standardmäßig deaktiviert. Der Hauptreiter, der allgemeine Mite-Timerknopf im Widget und die Timerfunktionen sind nur für das CMS-Admin-Konto mit der unter `MASHA_FEEDLY_REPORTER_MANAGER_EMAIL` konfigurierten E-Mail verfügbar. Andere Personen können das Board weiterhin benutzen, sehen den Mite-Knopf aber nicht und erhalten keinen Mite-Dialog.

Verschiebst du selbst einen Fehler im CMS-Board oder änderst im Eintragsdialog den Status in eine ausgewählte **Startkategorie**, erscheint der Dialog **Mite-Timer starten?**. Er lädt verfügbare Projekte und Leistungen und zeigt einen bereits laufenden Timer an. **Timer starten** legt einen Mite-Zeiteintrag mit der vollständigen Fehlerbeschreibung als Bemerkung, dem gewählten Mite-Projekt und der gewählten Leistung an und startet dessen Stoppuhr. Das Standardprojekt ist vorausgewählt; Projekt und Leistung lassen sich für jeden Start auswählen. Über **Mite-Timer** im ersten Bereich des Seitenwidgets kannst du unabhängig davon eine allgemeine Zeiterfassung ohne Feedly-Eintrag starten oder einen laufenden Timer stoppen. Allgemeine Zeiteinträge erhalten in Mite den Hinweis „Masha:Feedly – allgemeine Zeiterfassung“.

Mite erlaubt nur einen laufenden Timer pro Benutzer. Ändert sich der laufende Timer nach Öffnen des Dialogs, musst du ihn über **Neu laden** erneut prüfen. Bei einem API-Fehler bleibt der Fehler in der gewählten Kategorie; der Dialog zeigt die Ursache. Feedly speichert keine Mite-Zeiteinträge oder Zuordnungen dazu. Zeitdaten bleiben in Mite. Im Widget-Dialog kannst du einen laufenden Timer mit **Timer stoppen** beenden. Bloßes Umsortieren innerhalb derselben Kategorie öffnet keinen Dialog. Bei deaktiviertem Mite erscheinen keine Timerdialoge und bereits geöffnete Dialoge können keinen Timer mehr starten oder stoppen. Beim Zurücksetzen von Feedly werden die Startkategorien entfernt und Mite deaktiviert.

Im CMS findest du die Übersicht unter **Masha:Feedly → Einträge → Meldepersonen ändern**. Dort kannst du Einträge nach ID oder Titel suchen und die angezeigte Person direkt in der Zeile speichern.

## Installation und Tests

Installation und Befehle für PHP- und JavaScript-Tests stehen in der [Projekt-README](../../../README.md). Browserbasierte Ende-zu-Ende-Tests sind in [`../../tests/e2e/README.md`](../../tests/e2e/README.md) beschrieben.
