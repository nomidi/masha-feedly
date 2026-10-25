# Masha:Feedly verwenden

## Scrollen in Meldungen

Lange Beschreibungen und das Bearbeitungsfenster lassen sich mit Mausrad, Trackpad und auf Touchscreens scrollen. Feedly nimmt seinen gesamten Widget-Bereich automatisch von Lenis-Scrollanimationen der Website aus. Das gilt auch beim Öffnen einer Meldung über ihren Direktlink; zusätzliche Lenis-Einstellungen sind dafür nicht nötig.

## Eigenständig ohne Connect

Feedly kann vollständig ohne Masha:Connect installiert und betrieben werden. `composer require kooperativeweb/masha-feedly` zieht Connect nicht als Pflichtabhängigkeit nach. Meldungen, Kommentare, geschützte Anhänge, lokale E-Mails und die lokale Häkchen-Animation benötigen keinen Anbieter. Connect ist optional und kann separat per Git installiert werden; es wird nur für signierte zentrale Effekte, Profil-Icons und E-Mail-Standards verwendet. Ohne Anbieterzugang startet der Animationsloader keine Anbieterabfrage. Ist ein gemeinsamer Schlüssel eingerichtet, aber Connect fehlt, bleiben Anbieterinhalte gesperrt; lokale Funktionen bleiben nutzbar. Kein Rückfall auf alte Anbieterzugänge.


## Gemeinsamer Website-Zugang

Aktuelle Kundenmodule verwenden einmal `MASHA_PROVIDER_URL` und `MASHA_API_KEY`; Prism zusätzlich einen lokalen `MASHA_ENCRYPTION_KEY`. Die Anbieteradresse wird über `MASHA_PROVIDER_URL` festgelegt. Im Anbieter unter **Website-Zugänge** die benötigten Dienste einzeln freigeben und für Promptly das KI-Konto ausdrücklich zuordnen. Alte modulspezifische Variablen sind nur noch ein Kompatibilitätsweg. Neu erzeugte Schlüssel benötigen das signierte Masha:Connect-Protokoll.

Masha:Feedly ist ein gemeinsamer Fehler- und Feedback-Tracker direkt auf der Website. Berechtigte Personen können Meldungen anlegen, diskutieren und ihren Bearbeitungsstand nachverfolgen.

Die Oberfläche verwendet „Meldung“ für Fehler und Änderungswünsche. Unter **Profileinstellungen öffnen** findest du **Profilfarbe**, **Danke-Animation** und die persönliche Anrede. In der Einführung und in Fehlermeldungen wird der nächste Handlungsschritt genannt. Die Vorschau zeigt Änderungen sofort; dauerhaft übernommen werden sie erst beim Speichern.

Plus und Speichern bleiben in beiden Erscheinungsbildern pink. Der Neuigkeiten-Button hat einen hellen Hintergrund ohne dunkle Fläche hinter dem NEW-Symbol.

## Zugang und Einrichtung

Administratoren öffnen im CMS **Masha:Feedly → Konfiguration**. Die Einstellungen stehen auf einer Seite in den Abschnitten **Allgemein**, **Zugriff & Darstellung**, **Erinnerungen & Schätzungen** und **Daten zurücksetzen**. E-Mail-Vorlagen und Footer liegen im eigenen Tab **E-Mail-Texte**. Diesen Tab und seine Aktionen sieht nur das ausdrücklich über `MASHA_OPERATOR_EMAIL` konfigurierte CMS-Admin-Konto. Unter **Zugriff & Darstellung** wählst du die Personen aus, die das Modul verwenden dürfen. Auch Administratoren sehen das Widget nur, wenn sie ausdrücklich ausgewählt wurden. Unter **Allgemein** stellst du Ansprache, Schriftgröße und Website-Vorgabe für die Effekt-Kategorie ein. Die Vorgabe ist bei neuen Installationen **Verspielt**. Jede berechtigte Person kann im eigenen Profil eine andere Effekt-Kategorie wählen. Die Kategorien für Startstatus, Erledigt und Freigabe sind erforderlich; ihre Namen können angepasst werden.

Im Tab **E-Mail-Texte** bearbeitet das Betreiberkonto die Einleitungen für neue Meldungen, Änderungen, Kommentare, Fälligkeitserinnerungen und Kostenschätzungen – jeweils in Du- und Sie-Ansprache. Oben stehen die beiden Willkommenstexte. Unter jeder Vorlage kann eine Test-E-Mail an die E-Mail-Adresse des Betreiberkontos gesendet werden. Sie verwendet den aktuellen Editorinhalt auch dann, wenn er noch nicht gespeichert ist.

Die Testmails zeigen neben der Einleitung Beispielinhalte im tatsächlichen E-Mail-Layout: Meldungen enthalten Meldeperson, Kategorie, Priorität, Fälligkeit, Zuständigkeit und Beschreibung; Kommentare zeigen Meldung, Kommentar und Person; Fälligkeitserinnerungen ergänzen den Termin und Meldungsdetails; Kostenschätzungen zeigen Aufwand, Beispielkosten und Erläuterung. Die Änderungs-Testmail veranschaulicht Status-, Prioritäts-, Termin- und Zuständigkeitswechsel. Diese Beispielwerte sind keine echten Meldungsdaten.

Bei normalen Benachrichtigungen entscheidet die persönliche Anrede im Profil der empfangenden Person über Du oder Sie. Für die Willkommens-E-Mail gilt dagegen die Website-Vorgabe unter **Masha:Feedly → Konfiguration**. Meldungsdaten und Link ergänzt Masha:Feedly automatisch. Der gemeinsame Footer wird in diesem Tab gepflegt.

Ist `MASHA_PROVIDER_URL` samt API-Schlüssel eingerichtet, übernimmt `dev/build` Texte aus Masha:Suite Admin nur in noch leere Felder. **Texte aus Masha:Suite Admin übernehmen** lädt sie nach ausdrücklichem Klick erneut und ersetzt die gespeicherten Willkommen- und Benachrichtigungstexte. Der Footer wird nicht mit diesem Knopf importiert. Masha:Suite Admin stellt die geschützten Standards unter `GET /__masha-effects/email-defaults` bereit; Feedly ruft den Endpunkt serverseitig mit `Authorization: Bearer …` ab. Die Antwort enthält `version`, `footer`, `welcome.du`/`welcome.sie` und die Varianten unter `notifications`. Die Einrichtung des Website-Zugangs und des API-Schlüssels ist in der Masha:Suite Admin-Anleitung beschrieben.

Die CMS-Ansicht und ihr Menüpunkt sind ebenfalls nur für ausdrücklich ausgewählte Personen verfügbar. Die einzige Ausnahme ist das CMS-Admin-Konto mit der unter `MASHA_OPERATOR_EMAIL` konfigurierten E-Mail-Adresse; es kann die Modulkonfiguration auch ohne Zuordnung öffnen.

Nicht freigegebene Konten sehen auch im eigenen CMS-Profil keinen Masha:Feedly-Reiter.

Neue berechtigte Personen erhalten eine Willkommens-E-Mail. Beim ersten Besuch führt sie das Onboarding durch die wichtigsten Schritte. Die Einführung lässt sich jederzeit abbrechen und im Profil oder im Hilfefenster erneut starten. Im Abschlussfenster kannst du zuerst die Profilfarbe und – bei verfügbarem Anbieter – ein Symbol auswählen. Eine Vorschau zeigt, wie dein Profil neben Meldungen und Kommentaren erscheint. Farb- und Symboländerungen erscheinen sofort in der Vorschau, auch vor dem Speichern. Danach legst du die Danke-Animation für erledigte Meldungen und, sofern der Mailversand getestet wurde, deine E-Mail-Benachrichtigungen fest. Die Farbpalette lässt sich über „Verfügbare Farben“ ausklappen; der aktuelle Farbton bleibt eingeklappt sichtbar. Das Willkommensfenster erklärt das Melden direkt an der betroffenen Stelle sowie Zuständigkeiten, Kommentare und Fortschritt als gemeinsame Alternative zu E-Mail-Abstimmungen. In der Begrüßung, während aller Schritte und im Abschlussfenster lässt sich die Einführung mit „Einführung beenden“ schließen; während der Tour funktioniert auch Esc. „Später“ lässt „Einführung erneut anzeigen“ auf Ja und „Einführung abgeschlossen“ auf Nein. „Einführung beenden“ setzt die Werte auf Nein beziehungsweise Ja. Die Einführung übernimmt die aktuell eingestellte Anrede: die persönliche Auswahl oder, wenn sie leer ist, die Website-Vorgabe. Begrüßung, Schritte, Hinweise und Profiltexte bleiben dadurch in derselben Anrede.

Bei der Auswahl der Verantwortlichen erscheint der Name als kleiner Hinweis über dem Avatar, sobald du ihn mit der Maus berührst oder per Tastatur fokussierst.

## Einträge auf der Website

Beim Erstellen einer Meldung kannst du unter **Schritte zum Nachstellen (optional)** freiwillig beschreiben, welche Schritte zum Fehler führen und was du erwartet hast beziehungsweise was tatsächlich passiert ist. Der Bereich ist zunächst eingeklappt und öffnet sich automatisch, wenn die Beschreibung typische Fehlerbegriffe wie „Fehler“, „Bug“ oder „funktioniert nicht“ enthält. Du kannst ihn jederzeit selbst öffnen. Gespeicherte Angaben bleiben in der Meldung getrennt sichtbar und können beim Bearbeiten geändert werden.

Auf dem Smartphone öffnest du Masha:Feedly mit demselben Button unten rechts wie am Computer. Das seitliche Startpanel zeigt nur Plus und Hilfe. Die mobile Hilfe erklärt das Erstellen einer Meldung und weist darauf hin, dass alle Werkzeuge nur am Computer mit größerem Bildschirm verfügbar sind. Der Hilfetext lässt sich auch in kleinen Fenstern scrollen; der Kopf mit dem Schließen-Knopf bleibt sichtbar. Die Einführung benötigt einen größeren Bildschirm: **OK** verschiebt sie, **Einführung abbrechen** beendet sie dauerhaft. Seitenmarker bleiben im geöffneten Widget sichtbar und anklickbar. Der Zähler für die aktuelle Seite berücksichtigt nur offene Meldungen mit gespeichertem Seitenbereich, der sich auf der aktuellen Seite finden lässt. Meldungen ohne Seitenbereich oder mit nicht mehr auffindbarem Bereich erscheinen weder als Seitenmarker noch im Seitenzähler. Offene Meldungen ohne auffindbares Ziel werden dabei nicht als erledigt ausgegeben.

Die ausführlichen Antworten zu Masha:Feedly liegen in Masha:Helply. Helply stellt den eigenen Hilfe-Einstieg und den durchsuchbaren Dialog bereit. In der Kurzhilfe und am Ende der Einführung kann Helply direkt geöffnet werden. Wenn die Suche keine passende Antwort findet, kann die Frage mit einem Klick an Feedly übergeben und dort als Meldung weiterbearbeitet werden. Feedly prüft beim Erstellen der Meldung weiterhin die Zugriffsberechtigung.

## Screenshot hinzufügen

Im Formular kannst du freiwillig **Screenshot hinzufügen** wählen. Der Browser fragt, welchen Tab, welches Fenster oder welchen Bildschirm du freigeben möchtest. Wähle die betroffene Website. Während der Aufnahme wird die Masha:Feedly-Oberfläche ausgeblendet; die Vorschau zeigt die aufgenommene Seite.

Wähle anschließend den gewünschten Ausschnitt: Klicke oder tippe auf eine Ecke und danach auf die gegenüberliegende Ecke. Mit der Maus folgt der Rahmen zwischen beiden Klicks dem Zeiger. Wähle **Ausschnitt übernehmen**, damit dieser Bereich als PNG-Anhang mit der Meldung gespeichert wird. **Screenshot entfernen** verwirft die Aufnahme. Vor dem Übernehmen wird kein Screenshot angehängt.

Die aktuelle Funktion schneidet das Bild zu; einzelne Stellen innerhalb des Ausschnitts lassen sich nicht schwärzen. Lasse sensible Inhalte außerhalb des Rahmens oder entferne die Aufnahme. Unterstützt dein Browser keine Bildschirmaufnahme, kannst du einen selbst erstellten Screenshot als normalen Anhang hinzufügen.

## Einträge finden und bearbeiten

Im Feedly-Panel öffnet **Board** unter dem FAQ-Button das Meldungsboard im CMS. Dort siehst du alle für dich freigegebenen Meldungen nach Status. Beim Darüberfahren oder Tastaturfokus erklärt ein Hinweis das Ziel. Der Board-Link ist unabhängig von Helply verfügbar.

Das Globus-Symbol öffnet offene Einträge der ganzen Website. Das Seitensymbol zeigt offene Einträge der aktuellen Seite. Die Schaltflächen für Neuigkeiten, Feedback und abgeschlossene Einträge öffnen jeweils die passende Liste. Neuigkeiten heben Einträge und Kommentare seit dem letzten Besuch hervor.

In der Liste kannst du nach Kategorie, Priorität, Seite und Zuständigkeit filtern. Über **Filter** lassen sich persönliche Filterkombinationen speichern und später wieder auswählen. Öffne einen Eintrag, um Beschreibung, Browser- und Seitendetails, Kommentare, Anhänge, Verlauf und Zusammenhänge zu sehen. Status, Priorität und Zuständigkeiten werden nach **Änderungen speichern** übernommen.

Der Verlauf hält Status-, Prioritäts- und Zuständigkeitsänderungen, Kommentare, Anhänge und Verknüpfungen mit Person und Zeitpunkt fest. Wenn eine andere Person deinen Eintrag abschließt, kann er auf die erforderliche Freigabe-Kategorie wechseln. Du kannst das Ergebnis anschließend freigeben.

Die Meldungsliste lädt nur die Daten für Karten, Filter, Sortierung und Seitenmarker. Beschreibung, Kommentare, Verlauf, Anhänge und Verknüpfungen werden erst beim Öffnen einer Meldung aktuell nachgeladen. Bei einem Ladefehler bleibt die Liste verfügbar; öffne die Meldung erneut, um es noch einmal zu versuchen.
Kategorien, Prioritäten und Zuständigkeiten werden erst beim ersten Öffnen des Widgets nachgeladen; der Seitenaufruf rendert dafür nur leere Auswahlfelder. Das gilt auch beim Öffnen über die Einführung oder einen direkten Meldungslink. Die Bearbeitung wartet auf die Auswahlwerte, damit vorhandene Zuständigkeiten richtig angezeigt werden. Bei einem Ladefehler öffne die Meldung erneut. Der geschützte Endpunkt liefert diese Werte nur an freigeschaltete Mitglieder.

## Zusammenhänge und Duplikate

Im aufklappbaren Bereich **Zusammenhänge** kannst du Einträge als Duplikat, thematisch verwandt oder blockiert durch verknüpfen. Der Zusammenhang wird an beiden Einträgen angezeigt und im Verlauf festgehalten. Wird ein Haupteintrag abgeschlossen, werden seine Duplikate ebenfalls abgeschlossen. Andere Statusänderungen werden nicht automatisch übertragen.

## Profil und Benachrichtigungen

Wenn eine Person zu Masha:Feedly hinzugefügt wird, auch erneut, werden ihre E-Mail-Einstellungen auf „Meine Meldungen“ und alle Ereignisarten eingeschaltet gesetzt. Die Willkommensmail wird unabhängig von den Benachrichtigungseinstellungen versendet. Am Ende der Einführung kannst du die Kurzhilfe oder Masha:Helply öffnen. Von dort aus kannst du auch die Einführung erneut starten.

„Alle Meldungen“ bedeutet: E-Mails zu allen freigegebenen Meldungen der Website, einschließlich Kommentaren und Fälligkeiten. „Meine Meldungen“ bedeutet: nur selbst erstellte oder aktuell zugewiesene Meldungen. Für beide Bereiche gelten die ausgewählten Ereignis-Häkchen. Neue Zuweisungen zählen zu „Neue Meldungen und neue Zuweisungen“. Nach dem Entfernen einer Zuweisung gehört die Meldung nur noch dann zu „Meine Meldungen“, wenn sie selbst erstellt wurde. Eigene Meldungen, Änderungen und Kommentare lösen keine E-Mail an die handelnde Person aus. Kostenschätzungen zur Freigabe gehen ausschließlich an Zuständige mit aktivierter Benachrichtigung. Die Hauptoption schaltet alle Benachrichtigungen aus und blendet die Auswahl aus; beim Einschalten erscheint die gespeicherte Auswahl wieder. Willkommens- und ausdrücklich angeforderte Test-E-Mails bleiben unabhängig vom Bereich.

Deine Avatarvorschau im Profil zeigt dasselbe Icon und dieselbe Farbe wie Einträge und Zuständigkeiten. Bei leerer Farbe wird beim Speichern einmal eine zufällige Palettenfarbe vergeben; sie bleibt gespeichert und kann von dir oder dem zuständigen Administrator geändert werden. Auf dunklen Farben sowie auf Smaragd und Waldgrün erscheint das helle Icon, auf den übrigen hellen Farben das dunkle. Nur das ausgewählte Icon wird mit beiden Varianten lokal im Projekt gespeichert, sodass seine Anzeige keinen wiederholten Abruf beim Anbieter benötigt.

Im Masha:Feedly-Bereich deines Profils kannst du Avatarbild und -farbe festlegen, dein persönliches Erscheinungsbild wählen, einzelne Arten von E-Mail-Benachrichtigungen verwalten und die Einführung erneut aktivieren. Die auswählbaren Effekt-Kategorien kommen vom konfigurierten Masha:Suite Admin-Anbieter. Die Website-Vorgabe wird unter **Masha:Feedly → Konfiguration** festgelegt; ohne Anbieter oder bei einem Ausfall bleibt der lokale Häkchen-Effekt verfügbar. Neue Installationen verwenden als Vorgabe **Verspielt**. Neue und geänderte Einträge, Kommentare im ausgewählten Bereich, Fälligkeitstermine und Kostenschätzungen lassen sich getrennt einstellen. Eigene Aktionen erzeugen keine Benachrichtigungsmail. Neuigkeiten im Widget zeigen Aktivitäten unabhängig von E-Mail-Benachrichtigungen. Die persönlichen E-Mail-Einstellungen bleiben gesperrt, bis der konfigurierte Masha:Feedly-Administrator in der Konfiguration mindestens eine Test-E-Mail erfolgreich versendet hat; im Profil erscheint dazu ein Hinweis. Jede Masha:Feedly-E-Mail enthält außerdem den Footertext aus **Masha:Feedly → Konfiguration → E-Mail-Footer**, einen Impressumslink und einen direkten Link zu deinen E-Mail-Einstellungen. Ist das Textfeld leer, wird die geprüfte gemeinsame Footer-Vorlage über die konfigurierte Anbieterschnittstelle verwendet. Eine ausdrücklich gesetzte lokale Vorgabe `MASHA_FEEDLY_EMAIL_FOOTER` aus der Server-`.env` hat Vorrang. Dort stehen die Betreiberangaben getrennt vom Git-Repository; Zeilenumbrüche werden als `\n` notiert. `dev/build` übernimmt den Wert, wenn noch kein Footer gespeichert ist.

Der Footer erklärt außerdem, dass die Nachricht automatisch aufgrund deiner Benachrichtigungseinstellungen versendet wurde. Über den Link darin kannst du diese Einstellungen jederzeit ändern.

Bei einer neuen Meldung nennt die E-Mail die Website und zeigt – sofern vorhanden – Meldeperson, Kategorie, Priorität, Fälligkeit und Zuständigkeit. Darunter steht der vollständige Beschreibungstext; der automatisch daraus gebildete Kurztitel wird nicht zusätzlich wiederholt.

„Meldung ansehen“ öffnet die gespeicherte Ursprungsseite und die betreffende Meldung im Feedly-Widget. So gelangst du zur markierten Stelle. Das gilt auch für Kommentare, Änderungen, Erinnerungen und Kostenschätzungen. Die Anmeldung und die Feedly-Zugriffsrechte bleiben erforderlich. Bei fehlenden, ungültigen oder fremden Seitenadressen führt der Link zur geschützten CMS-Detailansicht. Bereits versendete E-Mails behalten ihren bisherigen Link.

Die formatierte Einleitung für neue Meldungen, Änderungen, Kommentare, Fälligkeitserinnerungen und Kostenschätzungen kommt – sofern konfiguriert – vom Masha:Suite Admin-Anbieter. Dort gibt es pro Typ eine Du- und Sie-Fassung. Platzhalter wie `{title}` oder `{date}` werden sicher ersetzt. Angaben wie Meldungsdetails, Link und Footer bleiben feste Bestandteile der E-Mail. Für Willkommens- und Benachrichtigungs-E-Mails verwendet Masha:Feedly seine eingebauten Texte, wenn kein Anbieter erreichbar ist oder das jeweilige Textfeld leer ist. Der Footer kommt separat aus der Feedly-Konfiguration. Leere Felder nutzen die Anbietervorlage; eine ausdrückliche lokale `MASHA_FEEDLY_EMAIL_FOOTER`-Vorgabe hat Vorrang. Eigene Texte werden nicht überschrieben.

Wenn `MASHA_PROVIDER_URL` und `MASHA_API_KEY` auf dem Server gesetzt sind und Masha:Suite Admin einen gültigen Icon-Katalog liefert, kannst du zusätzlich eines der dort bereitgestellten Profil-Icons auswählen. Der Icon-Katalog wird über den geschützten Feedly-Proxy vom Anbieter geladen. Das ausgewählte SVG-Icon wird mit seiner hellen und dunklen Variante lokal gespeichert. Sie sind nach Menschen, Tieren, Natur, Alltag, Hobbys & Technik, Obst & Gemüse, Essen & Trinken, Aliens & UFOs, Weltraum, Grusel und Dinosauriern sortiert. Die Icon-Farbe wechselt passend zu deiner Avatarfarbe zwischen Schwarz und Weiß. Ohne Anbieter-Konfiguration erscheint keine Icon-Auswahl. Bei einem eingerichteten, aber nicht erreichbaren Anbieter erscheint ein Hinweis; dein gespeicherter Avatar und der Profilbild-Upload bleiben verfügbar.

Der über `MASHA_OPERATOR_EMAIL` konfigurierte CMS-Administrator kann unter **Masha:Feedly → Konfiguration** mit **Test-E-Mail senden** den Mailversand an die E-Mail-Adresse seines Kontos prüfen. Bei einem Fehler zeigt die Konfiguration zusätzlich die konkrete Ursache an; Zugangsdaten in Verbindungs-URLs werden dabei ausgeblendet. Die vollständigen technischen Details stehen im PHP-Fehlerprotokoll. Schlägt eine Benachrichtigung fehl, wird der Eintrag trotzdem gespeichert.

Einträge können ein optionales Fälligkeitsdatum erhalten. Mit **Erinnerung zurückstellen bis** pausierst du nur die automatische Fälligkeitserinnerung. Kommentare und Änderungs-E-Mails laufen weiter. Am gewählten Datum wird eine weiterhin offene und fällige Meldung wieder berücksichtigt. Wähle in den Masha:Feedly-Einstellungen unter **Fälligkeitserinnerungen ausführen**, ob die Prüfung per Cronjob oder beim ersten Websitebesuch des Tages läuft. Beim Besuchsmodus werden keine Erinnerungen versendet, solange niemand die Website aufruft. Die Prüfung sendet am Fälligkeitstag (oder beim nächsten Lauf danach) einmalig eine E-Mail an freigegebene Personen mit aktivierter Fälligkeitserinnerung und passendem E-Mail-Bereich. Änderungen am Fälligkeitstermin setzen die Erinnerung zurück.

Für den Cronjob-Modus richte im Silverstripe-Projekt einen täglichen Aufruf ein:

```sh
vendor/bin/sake tasks:MashaFeedlyDueDateReminderTask
```

Für ALL-INKL mit HTTP-Cronjob gibt es im Projektordner `masha-cronjob/` eine Anleitung samt Beispielskript und `.htaccess`-Vorlage. Ohne Shell-Cronjob kannst du stattdessen **Bei Websitebesuchen** auswählen.

## Im CMS verwalten

Das Masha:Feedly-Board im CMS gruppiert Einträge nach Kategorie. Berechtigte Personen können Einträge dort bearbeiten und per Drag-and-drop sortieren. Kategorien, Prioritäten und allgemeine Widget-Einstellungen werden ebenfalls im CMS verwaltet.

Die Meldungsübersicht nutzt die verfügbare Breite des CMS-Arbeitsbereichs. Auf großen Monitoren passen dadurch mehr Kategorien nebeneinander. Reicht der Platz nicht aus, bleiben die Kategorien horizontal scrollbar.

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

Der API-Schlüssel gehört zu deinem Mite-Benutzer und wird weder im CMS noch im Browser ausgegeben. Nach `dev/build?flush=1` aktivierst du unter **Masha:Feedly → Mite** die Integration und wählst das Standardprojekt sowie eine oder mehrere **Startkategorien** aus. Die **Leistung** wählst du beim Start jedes Timers aus. Mite ist standardmäßig deaktiviert. Der Hauptreiter, der allgemeine Mite-Timerknopf im Widget und die Timerfunktionen sind nur für das CMS-Admin-Konto mit der unter `MASHA_OPERATOR_EMAIL` konfigurierten E-Mail verfügbar. Andere Personen können das Board weiterhin benutzen, sehen den Mite-Knopf aber nicht und erhalten keinen Mite-Dialog.

Verschiebst du selbst einen Fehler im CMS-Board oder änderst im Eintragsdialog den Status in eine ausgewählte **Startkategorie**, erscheint der Dialog **Mite-Timer starten?**. Er lädt verfügbare Projekte und Leistungen und zeigt einen bereits laufenden Timer an. **Timer starten** legt einen Mite-Zeiteintrag mit der vollständigen Fehlerbeschreibung als Bemerkung, dem gewählten Mite-Projekt und der gewählten Leistung an und startet dessen Stoppuhr. Das Standardprojekt ist vorausgewählt; Projekt und Leistung lassen sich für jeden Start auswählen. Über **Mite-Timer** im ersten Bereich des Seitenwidgets kannst du unabhängig davon eine allgemeine Zeiterfassung ohne Feedly-Eintrag starten oder einen laufenden Timer stoppen. Allgemeine Zeiteinträge erhalten in Mite den Hinweis „Masha:Feedly – allgemeine Zeiterfassung“.

Mite erlaubt nur einen laufenden Timer pro Benutzer. Ändert sich der laufende Timer nach Öffnen des Dialogs, musst du ihn über **Neu laden** erneut prüfen. Bei einem API-Fehler bleibt der Fehler in der gewählten Kategorie; der Dialog zeigt die Ursache. Feedly speichert keine Mite-Zeiteinträge oder Zuordnungen dazu. Zeitdaten bleiben in Mite. Im Widget-Dialog kannst du einen laufenden Timer mit **Timer stoppen** beenden. Bloßes Umsortieren innerhalb derselben Kategorie öffnet keinen Dialog. Bei deaktiviertem Mite erscheinen keine Timerdialoge und bereits geöffnete Dialoge können keinen Timer mehr starten oder stoppen. Beim Zurücksetzen von Feedly werden die Startkategorien entfernt und Mite deaktiviert.

Im CMS findest du die Übersicht unter **Masha:Feedly → Einträge → Meldepersonen ändern**. Dort kannst du Einträge nach ID oder Titel suchen und die angezeigte Person direkt in der Zeile speichern.

## Installation und Tests

Installation und Befehle für PHP- und JavaScript-Tests stehen in der [Projekt-README](../../../README.md). Browserbasierte Ende-zu-Ende-Tests sind in [`../../tests/e2e/README.md`](../../tests/e2e/README.md) beschrieben.

## Abschluss-Effekte

Die Effektversorgung ist optional. Ohne erreichbaren Anbieter spielt Feedly nach einem bestätigten Abschluss ein lokales, dezentes Häkchen ab. Das gilt auch bei leerem Katalog oder einem Fehler beim Laden eines Effekts.

Für zentral verwaltete Animationen kann das eigenständige Silverstripe-Modul **Masha:Suite Admin** auf derselben oder einer anderen Website installiert werden. Dort werden Kategorien, Effekte, Dateien und saisonale Regeln verwaltet. Dateien liegen privat auf dem Anbieter-Server. Feedly enthält nur den Loader und lädt passende Effekte über einen geschützten Server-Proxy. Ein Anbieterausfall verhindert das Speichern nicht.

`MASHA_PROVIDER_URL` in der Server-`.env` der Feedly-Website enthält die Basis-Adresse des Anbieters, zum Beispiel `https://effects.example.org`; Feedly ergänzt `/__masha-effects/manifest`. Ohne vollständig konfigurierten Anbieter ruft Feedly keinen Effekt-Katalog ab und verwendet den lokalen Häkchen-Effekt. Das gilt auch für die Vorschauen im CMS. Auch bei einem nicht erreichbaren Anbieter oder einem leeren Katalog verhindert der Ersatzeffekt nicht das Speichern.

Für einen externen Anbieter wird `MASHA_API_KEY` in der Server-`.env` der Feedly-Website hinterlegt. Der Schlüssel wird im CMS des Anbieters erzeugt und nur serverseitig verwendet. Browser erhalten ausschließlich lokale Proxy-URLs; der Proxy prüft Anmeldung und Feedly-Freigabe.

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
      "text": "Geschafft!",
      "detail": "Die Meldung ist erledigt.",
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

Im eigenen Profil und im letzten Schritt der Einführung kannst du **Animationen ohne Ton abspielen** aktivieren. Alle Effekte bleiben verfügbar und werden ohne Musik oder andere Töne wiedergegeben, auch in den Vorschauen der Masha:Feedly-Konfiguration. Die Auswahl wird beim Speichern übernommen. Bereits geöffnete Konfigurationsfenster danach neu laden.

Der Effekt-Anbieter muss ebenfalls die aktuelle Masha:Suite Admin-Version verwenden: Seine JavaScript-Dateien müssen die Option `muted` berücksichtigen. Ältere Anbieterdateien können trotz korrekter Profileinstellung Ton abspielen.

Beim Wechsel zwischen mobiler und Desktop-Ansicht schließen sich die Feedly-Fenster. Eine laufende Einführung zeigt wieder die Begrüßung in der passenden Größe. Formulareingaben bleiben erhalten. Größenänderungen innerhalb derselben Ansicht schließen keine Fenster.

### Abschluss bestätigen

Wenn eine andere Person „Erledigt“ auswählt, bleibt die Meldung offen im Status „Feedback“. Ein hervorgehobener Hinweis erklärt die nächste Aktion. Er bleibt nach dem Speichern in der geöffneten Meldung sichtbar, auch wenn diese über einen Direktlink geöffnet wurde. Der ursprüngliche Ersteller oder die unter „Angezeigte Meldeperson“ eingetragene Person prüft das Ergebnis und bestätigt den Abschluss mit „Erledigt“ und „Änderungen speichern“. Eine Zuständigkeitszuweisung allein berechtigt nicht zur endgültigen Bestätigung. Ein Kommentar ist freiwillig. Die Regel gilt auch beim Verschieben und Speichern im CMS.

Helply-Verweise werden nur angezeigt, wenn Helply die berechtigte Feedly-Hilfe auf der aktuellen Seite bereitstellt. Ohne Helply bleiben Buttons und Hinweise ausgeblendet.

## Automatisch erfasste technische Fehler

Automatisch erfasste Systemmeldungen darf das konfigurierte Betreiberkonto direkt auf „Erledigt“ setzen, auch im CMS. Eine zusätzliche technische Lesefreigabe oder Zuständigkeit allein reicht dafür nicht. Tritt derselbe Fehler erneut auf, wird die Meldung wieder geöffnet. Änderungs-E-Mails folgen weiterhin den persönlichen Einstellungen; die handelnde Person erhält keine eigene Mail.

Für die Einrichtung bei ALL-INKL stehen im Hostprojekt unter `masha-cronjob/README.md` KAS-Schritte und unter `masha-cronjob/run-feedly-errors.php` ein geschütztes HTTP-Cronjob-Beispiel bereit. Die Anleitung erklärt außerdem die gemeinsame private Fehlerablage für Website und CLI, den E-Mail-Filter und das Zusammenspiel von KAS- und Feedly-Intervall.

Unter **Masha:Feedly → Konfiguration → Technische Fehler** kann das konfigurierte Betreiberkonto die Erfassung aktivieren. Das Konto aus `MASHA_OPERATOR_EMAIL` muss vorhanden sein und Administratorrechte besitzen. Jede technische Meldung wird immer diesem Konto zugewiesen. Nur dieses Konto und zusätzlich ausgewählte, allgemein für Feedly freigegebene Personen dürfen technische Meldungen sehen.

Gleiche Fehler werden anhand einer bereinigten Meldung und ihrer Herkunft zusammengefasst. Variable Zahlen und UUIDs werden für den Vergleich vereinheitlicht. Die Detailansicht zeigt die Gesamtzahl sowie erstes und letztes Auftreten in UTC. Neue Fehler verwenden die bestehende Benachrichtigung für neue Meldungen; erneut aufgetretene erledigte Fehler werden wieder geöffnet und verwenden die Änderungsbenachrichtigung. Die persönlichen E-Mail-Einstellungen und der Bereich „Meine Meldungen“ gelten weiterhin. Wiederholungen offener Fehler erhöhen nur den Zähler und erzeugen weder Kommentare noch Änderungsmails.

**Verarbeitung:** Wähle Cronjob oder Websitebesuche und ein Intervall von 1 bis 1440 Minuten. Der Cronjob sollte jede Minute den folgenden Befehl aus dem Projektverzeichnis ausführen; Feedly prüft selbst das eingestellte Intervall:

```sh
php vendor/bin/sake tasks:MashaFeedlyErrorTask
```

Im Besuchsmodus startet ein Aufruf einer Silverstripe-Inhaltsseite die fällige Prüfung. Ohne solche Seitenaufrufe oder bei vollständig zwischengespeicherten Seiten verzögert sie sich. Parallele Durchläufe werden verhindert. Pro Durchlauf werden höchstens 100 Fehlergruppen übernommen; weitere folgen im nächsten Intervall.

**Tägliche Zusammenfassung:** Optional aktivieren und eine Uhrzeit im Format HH:MM einstellen (Zeitzone Europe/Berlin). Die Mail listet im Zeitraum aufgetretene Fehler mit ihren Gesamtzahlen und Links auf. Persönliche E-Mail-Hauptoption, Meldungsbereich und technischer Zugriff gelten auch hier; die einzelnen Ereignis-Häkchen steuern weiterhin die Sofortmails. Ohne passende Fehler wird keine Mail geschickt. Ohne Cronjob oder Websitebesuch kommt die Zusammenfassung später. Nach Aktivierung umfasst die erste Zusammenfassung höchstens die vergangenen 24 Stunden. Bei einem abgebrochenen oder fehlgeschlagenen Versand wird die Zusammenfassung an diesem Tag nicht automatisch wiederholt.

**Servereinrichtung:** Der Modul-LogHandler wird automatisch registriert und arbeitet erst nach Aktivierung. Bestehende `NativeMailerHandler` werden dadurch nicht abgeschaltet: Entferne deren Registrierung, wenn Fehler nicht zusätzlich weiterhin einzeln per Mail versendet werden sollen. Behalte ein unabhängiges PHP-/Server-Fehlerprotokoll bei. Nach Installation oder Aktualisierung einmal `dev/build flush=1` ausführen und die Konfiguration speichern.

Die lokale Ablage liegt standardmäßig in einem projektspezifischen Unterordner von `TEMP_PATH`. Webserver und Cronjob benötigen denselben Temp-Pfad und Zugriff auf dieselben Dateien. Bei unterschiedlichen Serverbenutzern oder mehreren Servern muss eine geeignete gemeinsame private Ablage eingerichtet werden. Ein absoluter Pfad kann über YAML konfiguriert werden:

```yaml
KW\MashaFeedly\Logging\MashaFeedlyErrorHandler:
  spool_directory: '/srv/private/feedly-errors'
```

Der Ordner muss außerhalb des Webroots liegen. Ordner und Dateien werden mit 0700 beziehungsweise 0600 geschützt. Erfasst werden Fehler ab dem gewählten Mindestschweregrad (Error, Critical, Alert oder Emergency), die Silverstripe an diesen Logger übergibt. Fehler vor dem Frameworkstart können nur im unabhängigen Serverprotokoll stehen. Die Erfassung benötigt keine Datenbank; bei einem fehlgeschlagenen Import bleiben die Chargen für einen späteren Versuch erhalten. Nicht übernommene Fehler bleiben auch bei ausgeschalteter Erfassung gespeichert.

Die Ablage übernimmt keine vollständigen Request-Daten, Kontextwerte oder Trace-Argumente. Häufige Zugangsdatenmuster und URL-Querystrings werden entfernt. Freitext in einer Fehlermeldung kann dennoch sensible Informationen enthalten; technische Freigaben sollten entsprechend eng bleiben. Fehler während Import oder Mailversand werden nicht erneut in Feedly eingespeist, um Rückkopplungen zu vermeiden. Der Modul-Reset löscht die bereits importierten Meldungen; ausstehende Chargen können sie beim nächsten Lauf erneut anlegen.

Die Feedly-Konfiguration gliedert sich in aufklappbare Bereiche. Allgemein und technische Fehler stehen zuerst; Zugriff, Profilfarben, Effektvorschauen, Erinnerungen und Schätzungen sowie Zurücksetzen folgen separat. Der Kopf zeigt den gespeicherten Mail- und Fehlererfassungsstand. Änderungen werden erst mit **Konfiguration speichern** übernommen.

**Ort technischer Fehler:** Die Erfassung übernimmt bei einem Fehler auf einer Inhaltsseite deren lokalen Pfad, ohne Querystring oder Fragment. Ohne Inhaltsseite wird `/admin` der Website gespeichert. Sobald für einen bislang dem Adminbereich zugeordneten Fehler eine Inhaltsseite bekannt wird, übernimmt Feedly diese. Gleiche Fehler auf weiteren Seiten bleiben eine gemeinsame Meldung mit dem zuerst bekannten Inhaltsort. Der Meldungstitel im CMS-Board ist auch bei lokalen Pfaden und älteren technischen Meldungen ohne Ort anklickbar. Die Eintragskennung wird für Admin-Ziele zusätzlich im URL-Fragment übergeben, damit sie bei der CMS-Weiterleitung erhalten bleibt. Neue Einträge verwenden standardmäßig `/admin`; ausdrücklich leere oder unsichere Seitenadressen verhindern das Speichern. `dev/build` ergänzt den Admin-Ort bei vorhandenen Meldungen mit leerer Adresse, ohne Änderungsmails auszulösen.

Der ursprüngliche FAQ-Button mit Buchsymbol steht direkt unter dem Fragezeichen und öffnet die Feedly-Sammlung in Helply. Er erscheint erst, wenn die Sammlung „feedly“ für das aktuelle Konto verfügbar ist. Bei installiertem aktuellem Masha:Helply stellt Feedly die Sammlung auch in CMS-Bereichen wie Profil, Meldungsboard und Konfiguration bereit. Helply prüft weiterhin CMS-Zugang sowie globale und sammlungsspezifische Gruppenrechte; zusätzlich ist die Feedly-Freigabe erforderlich. Bei fehlendem Zugriff oder ohne Helply bleiben Button und Hinweis verborgen.

### Markierungen in CMS-Reitern und Bilddialogen

Feedly löst gespeicherte Stellen nach dem Laden und bei späteren Änderungen des CMS-Formulars erneut auf. Vorhandene Reiter um eine geöffnete Meldung werden eingeblendet. Sichtbare Ziele bekommen eine Bubble; verborgene oder entfernte Ziele werden nicht an einer anderen Stelle markiert. Offene Meldungen mit fehlendem Ziel gelten nicht als erledigt.

Wenn eine alte Meldung im Bilddialog angelegt wurde, speichert sie nur das Feld und nicht die Identität des geöffneten Bildes. Feedly kann deshalb keinen bestimmten Bilddialog sicher wiederherstellen. Die Meldung erklärt dies: schließe sie und öffne das betreffende Bild. Sobald das Feld sichtbar ist, erscheint die Markierung automatisch. Bei dauerhaft entfernten Feldern bleibt die Meldung über die Liste erreichbar.

Mit **Markierte Stelle anzeigen** schließt du die Meldungsfenster und springst zur sichtbaren Bubble. Die Markierung der geöffneten Meldung bleibt hervorgehoben.

Die Meldungsbearbeitung schließt auch im Erfolgszustand ohne zusätzliche Lücke an die Liste an. Alle drei Panels verwenden dieselben responsiven Breiten; auf dem Smartphone bleibt die Bearbeitung bildschirmfüllend.

## Zentrale Anbieterinhalte

Feedly und Helply laufen mit ihren lokalen Benutzerrechten unabhängig von einer zentralen Modulfreigabe. In **Masha:Suite Admin → Kunden** steuert **Feedly-Anbieterinhalte erlauben** ausschließlich zentrale Effekte, Profil-Icons und E-Mail-Standards. Bei gemeinsamem Zugang wird diese Inhaltsfreigabe vor dem Zugriff auf Anbieter-Caches geprüft. Gesperrte oder nicht prüfbare Anbieterinhalte werden nicht ausgeliefert; Meldungen, Konfiguration, lokaler Mailversand, Erinnerungen und Fehlerübernahme bleiben nutzbar. Ohne gemeinsamen Zugang gibt es keine zentrale Statusabfrage für den lokalen Betrieb. Bereits lokal übernommene Texte bleiben erhalten.

## Gemeinsames Betreiberkonto

In diesem Hostprojekt ordnet `app/_config.php` die E-Mail aus `MASHA_OPERATOR_EMAIL` der Feedly-Konfiguration `reporter_manager_emails` zu. Ist die gemeinsame Variable leer oder nicht gesetzt, bleibt `MASHA_FEEDLY_REPORTER_MANAGER_EMAIL` als Rückfall gültig. Sind beide belegt, hat `MASHA_OPERATOR_EMAIL` Vorrang. Eine separat gesetzte `MASHA_FEEDLY_ESTIMATE_MANAGER_EMAIL` bleibt erhalten.

Der Name ist für andere Masha-Module wiederverwendbar; er erteilt nicht automatisch Rechte in anderen Modulen. Das Konto muss bereits existieren und weiterhin CMS-Administrator sein. SMTP, Benutzerzugriff auf das Widget und die zentrale Feedly-Freigabe sind getrennte Einstellungen. Eigenständige Feedly-Installationen können die unten beschriebene Projektzuordnung nachbilden; das Modul setzt keinen Masha:Suite-Host voraus.

```php
$operatorEmail = trim((string) Environment::getEnv('MASHA_OPERATOR_EMAIL'));
Config::modify()->set(
    MashaFeedlyEntry::class,
    'reporter_manager_emails',
    $operatorEmail === '' ? [] : [$operatorEmail]
);
```

Im Projektcode dafür `SilverStripe\Core\Environment`, `SilverStripe\Core\Config\Config` und `KW\MashaFeedly\Model\MashaFeedlyEntry` importieren. Nur eine tatsächlich konfigurierte Adresse zuordnen; bei leerem Wert eine leere Liste verwenden.

Unter **Konfiguration** bleibt die Aktionsleiste beim Scrollen sichtbar. **Test-E-Mail senden** zeigt Versandfortschritt, Erfolg oder Fehler direkt in dieser Leiste und wechselt keinen Reiter. Ungespeicherte Formulareingaben bleiben erhalten und werden durch den Testversand nicht gespeichert. Ohne JavaScript erscheint die Rückmeldung im Konfigurationsformular.

Zentrale Cron-Einstiege im Hostroot: `masha-cronjob/run-feedly-errors.php` und `masha-cronjob/run-feedly-reminders.php`. Anleitung: `masha-cronjob/README.md`. Der gemeinsame Erfolgsmarker für den KAS-Mailfilter ist `MashaCronOK`; alte eigene Skripte und Cron-Einträge nach Umstellung deaktivieren.

Benachrichtigungen zu neuen, geänderten und kommentierten Meldungen sowie Erinnerungen und Schätzungen zeigen den Meldungslink unten als einheitlichen Button. Der Link öffnet die gespeicherte Originalseite mit Meldungskennung; Feedly verwendet deren gespeicherten Seitenbereich. Reine Textmails enthalten stattdessen die vollständige Adresse.

Im CMS kann Masha:Admin Plus Jakarta Sans lokal bereitstellen. Das Widget übernimmt ausschließlich eine bereits vorhandene CSS-Schriftvariable; Feedly lädt selbst keine Schriftdateien oder Schrift-Stylesheets. Ohne gemeinsame Schrift bleibt die bisherige Ersatzschrift aktiv. Der lokale Versionsstand steht in `masha-release.json`.

## Cron-Status und Hilfe

Administratoren finden in der CMS-Konfiguration die Laufmeldungen für technische Fehler und Erinnerungen, wenn der aktuelle optionale Masha:Connect-Statusleser vorhanden ist. Der aktuelle zentrale runner.php schreibt sie privat in dieser Installation. Grün bedeutet erfolgreichen Aufgabenabschluss, nicht E-Mail-Zustellung. Fehlende, fehlerhafte und überfällige Läufe sind getrennt erkennbar. Ohne Connect bleibt Feedly unabhängig nutzbar. Die lokale Feedly-Helply-Sammlung erklärt diese Anzeige zusätzlich zu den bestehenden Funktionen.


## Moduleinstieg und Hilfe

Der CMS-Einstieg zeigt zuerst Start mit einer kurzen Einführung und der Feedly-Hilfe. Meldungen, Kategorien und weitere Funktionen bleiben in ihren eigenen Reitern; bestehende Meldungslinks bleiben erreichbar.


Die Modulbereiche haben eine gemeinsame Kopfzeile: Modulname oben, eine flache Reiterleiste darunter. Hilfe wird gezielt auf Start geöffnet und schwebt nicht über der Navigation. Speichern steht rechts und verwendet die Modulfarbe. Auf Start und reinen Bericht-/Kontoansichten wird keine unnötige Speicheraktion angezeigt.

Die Verwaltungslisten verwenden kühle weiße Flächen, dezente Zeilentrenner und einheitliche Werkzeuge. Sortieren, Filtern, Seitennavigation, Datensatzrechte und Formularaktionen bleiben native GridField-Funktionen.

Systemmeldungen lassen sich über ihren Direktlink auch ohne markierten Seitenbereich öffnen. In der Gesamtliste öffnet sich die Meldung direkt; die Fehleradresse wird nicht automatisch aufgerufen. Die vorhandenen Feedly- und Datensatzrechte gelten weiterhin.


## Optionaler masha.tools-Wochenrückblick

Mit Masha:Admin kann dieses Modul einen ausschließlich lesenden Beitrag zur persönlichen Wochenmail liefern. Modulrechte bleiben Voraussetzung; die zentrale Abwahl liegt unter Mein Profil → Wochenrückblick und ist standardmäßig eingeschaltet. Bei Feedly ist die Auswahl zusätzlich in dessen Profileinstellungen sichtbar. Für Einrichtung, Zeiträume, Versandreservierungen und Betreiberbuttons siehe Masha:Admin → README, Abschnitt „Persönlicher Website-Wochenrückblick“. Die Wochenmail ersetzt keine bestehenden Ereignisbenachrichtigungen oder Erinnerungen.

In der Wochenmail stehen abgeschlossene Aufgaben vor neuen und offenen Aufgaben. Der Link „Aufgaben und Meldungen ansehen“ öffnet direkt die Meldungsübersicht; technische Hinweise verweisen ebenfalls auf diesen Arbeitsbereich statt auf die Moduleinführung. Bestehende Sichtbarkeitsrechte gelten weiterhin.
