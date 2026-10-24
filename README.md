# sw-newspaper2

sw-newspaper2 veröffentlicht PDF-Zeitungen über die WordPress-Mediathek. Das Plugin erzeugt ein Titelbild aus der ersten PDF-Seite und stellt die aktuelle Ausgabe, einen Download-Link und ein Ausgabenarchiv über Shortcodes bereit. Optional lässt sich die aktuelle Ausgabe als dFlip-Blätterbuch öffnen.

Diese Dokumentation beschreibt die Implementierung von Version 2.0.0.


## Schnellstart: Was muss ich im Frontend einsetzen?

1. sw-newspaper2 unter **Plugins** aktivieren; die Voraussetzungen weiter unten beachten.
2. Im WordPress-Menü **Zeitung / Newspaper → Upload** eine PDF mit Titel und Ausgabedatum hochladen.
3. Eine Seite oder einen Beitrag bearbeiten und einen **Shortcode-Block** hinzufügen. Im klassischen Editor den Shortcode direkt in den Inhalt einsetzen. Bei Page-Buildern das jeweilige Shortcode-Element verwenden, nicht einen Code-Block.
4. Je nach gewünschter Ausgabe eines oder mehrere der folgenden Beispiele einsetzen und die Seite veröffentlichen.

| Gewünschte Ausgabe | Einzusetzender Shortcode |
| --- | --- |
| Titelbild der aktuellen Ausgabe, 300 Pixel breit | [`[swnewspaper2_current width="300"]`](src/Shortcodes.php:55) |
| Button zur aktuellen PDF | [`[swnewspaper2_download label="Aktuelle Ausgabe herunterladen"]`](src/Shortcodes.php:36) |
| Die zwölf neuesten Ausgaben, bis zu vier Spalten | [`[swnewspaper2_archive columns="4" limit="12"]`](src/Shortcodes.php:78) |
| Alle Ausgaben mit Standardeinstellungen | [`[swnewspaper2_archive]`](src/Shortcodes.php:78) |

Die Shortcodes benötigen keine schließenden Tags. Alle Parameter sind optional. Es muss weder eine PDF-Adresse noch eine Medien-ID im Shortcode angegeben werden. Die drei Shortcodes sind unabhängig voneinander einsetzbar, auch gemeinsam auf derselben Seite.

**Ohne mindestens eine über das Plugin veröffentlichte oder aus dem Vorgänger importierte Ausgabe liefern alle drei Shortcodes eine leere Ausgabe, ohne Hinweistext.**

## Voraussetzungen und Installation

- WordPress ab **6.4** und PHP ab **8.2**, siehe [Plugin-Kopf](sw-newspaper2.php:3).
- Ein vollständiges Plugin-Paket einschließlich des Composer-Autoloaders unter [`vendor/autoload.php`](vendor/autoload.php). Ohne diesen startet das Plugin nicht und zeigt einen Hinweis im Backend.
- Für automatische Titelbilder: die PHP-Erweiterung Imagick mit funktionierender PDF-Leseunterstützung. Je nach Server benötigt ImageMagick dafür Ghostscript und passende Sicherheitsrichtlinien. Imagick allein garantiert noch nicht, dass PDFs verarbeitet werden können.
- Schreibbare WordPress-Upload- und temporäre Verzeichnisse sowie ausreichende Upload- und Speicherlimits für die verwendeten PDFs.
- Optional **dFlip** für die Blätterbuchansicht und **WP Media Folder** zur Zuordnung neuer Dateien zu einem Medienordner. Beide sind für die normalen PDF-Links und das Archiv nicht erforderlich.

Bei Installation aus dem Quellcode ohne mitgelieferte Abhängigkeiten im Plugin-Verzeichnis Composer ausführen. Für eine produktive Installation:

```sh
composer install --no-dev --optimize-autoloader
```

Anschließend das Plugin in WordPress aktivieren. Die Prüfung des Autoloaders steht in der [Plugin-Startdatei](sw-newspaper2.php:47).

## Automatischer Import aus sw-newspaper

Bei der **Aktivierung** von sw-newspaper2 werden vorhandene Zeitungen aus dem Vorgänger automatisch übernommen, wenn beide Bedingungen erfüllt sind:

- Das alte Plugin ist unter [`sw-newspaper/sw-newspaper.php`](../sw-newspaper/sw-newspaper.php) installiert; es muss nicht aktiviert sein.
- Es existiert noch kein als sw-newspaper2-Zeitung gekennzeichneter Medienanhang.

Bereits aktivierte Installationen können den Import durch Deaktivieren und erneutes Aktivieren von sw-newspaper2 auslösen. Bei einer Netzwerkaktivierung werden die Bedingungen für jede bestehende Website separat geprüft.

Der [Importer](src/Legacy_Importer.php) übernimmt alle vom Vorgänger gekennzeichneten PDF-Anhänge einschließlich vorhandener gültiger Titelbild-Anhänge. Er ergänzt nur die neuen Plugin-Metadaten: Es werden keine Dateien kopiert, keine Titelbilder neu erzeugt und keine alten Metadaten gelöscht. Titel, Medien-URLs und vorhandene Ordnerzuordnungen bleiben unverändert.

Das alte Ausgabedatum wird übernommen, sofern es gültig ist. Fehlt es oder ist es ungültig, dient das lokale Anhangsdatum als Ersatz; ist auch dieses ungültig, wird das aktuelle Datum der Website verwendet. Auch importierte Ausgaben werden im Frontend nach diesem Ausgabedatum sortiert. Die im Vorgänger manuell beziehungsweise per letztem Upload festgelegte aktuelle Ausgabe wird nicht gesondert übernommen.

Die alten dFlip- und Medienordner-Einstellungen werden nicht kopiert und dFlip-Buchquellen nicht verändert. Diese Integrationen bei Bedarf in sw-newspaper2 neu auswählen. Vorhandene Seiteninhalte werden ebenfalls nicht umgeschrieben: Alte Shortcodes müssen durch die oben dokumentierten sw-newspaper2-Shortcodes ersetzt werden.

Sobald sw-newspaper2 bereits eine Zeitung enthält, wird bei erneuter Aktivierung nichts importiert oder überschrieben. Normale, nicht vom Vorgänger gekennzeichnete Mediathek-PDFs werden weiterhin nicht automatisch übernommen. Beide Plugins verweisen nach dem Import auf dieselben Dateien; ein endgültiges Löschen in sw-newspaper2 betrifft deshalb auch die vom alten Plugin verlinkten Medien.

## Ausgaben im Backend veröffentlichen und verwalten

### Upload

Unter **Zeitung / Newspaper → Upload** einen Titel, ein gültiges Ausgabedatum und eine PDF-Datei angeben. Das Plugin:

1. speichert die PDF als Anhang in der Mediathek;
2. versucht, die erste Seite als separates PNG-Titelbild zu speichern;
3. kennzeichnet die PDF als Zeitung und speichert das Ausgabedatum;
4. ordnet PDF und Titelbild gegebenenfalls dem eingestellten Medienordner zu;
5. aktualisiert gegebenenfalls das konfigurierte dFlip-Buch.

Schlägt nur die Titelbilderzeugung fehl, wird die Ausgabe trotzdem veröffentlicht. Im Backend erscheint eine Warnung; die normale Cover-Ausgabe zeigt dann einen anklickbaren **PDF**-Platzhalter.

**Ein gewöhnlicher PDF-Upload unter „Medien“ genügt nicht.** Das Frontend berücksichtigt nur Anhänge mit der Plugin-Kennzeichnung und dem gespeicherten Ausgabedatum. Abgesehen vom automatischen Import aus dem Vorgänger bei Aktivierung gibt es keine Oberfläche zum nachträglichen Importieren vorhandener Mediathek-PDFs.

### Welche Ausgabe gilt als aktuell?

Die Ausgaben werden nach dem **Ausgabedatum absteigend** sortiert, nicht nach Upload-Zeitpunkt oder Dateiname. Die erste Ausgabe dieser Sortierung gilt als aktuell. Bei gleichem Datum definiert das Plugin keine zusätzliche Sortierung.

Ein Datum in der Zukunft ist keine geplante Veröffentlichung: Die Ausgabe wird sofort berücksichtigt und kann damit unmittelbar zur aktuellen Ausgabe werden. Siehe [Abfrage und Auswahl der aktuellen Ausgabe](src/Newspaper_Repository.php:25).

### Archiv und Löschen

Der Backend-Reiter **Archiv / Archive** zeigt die gespeicherten Ausgaben. Beim Löschen einer Ausgabe werden **PDF und zugehöriges Titelbild endgültig** aus der Mediathek entfernt, nicht nur aus der Frontend-Liste.

Für den Backend-Zugang und Upload ist die WordPress-Berechtigung [`upload_files`](src/Capabilities.php:15) erforderlich. Die Einstellungen benötigen zusätzlich [`manage_options`](src/Capabilities.php:18). Das Löschen wird über die WordPress-Berechtigung für den jeweiligen Anhang geprüft.

## Frontend-Shortcodes und Parameter

### 1. Download-Button zur aktuellen Ausgabe

Beispiel: [`[swnewspaper2_download label="Zeitung als PDF öffnen"]`](src/Shortcodes.php:36)

| Parameter | Standard | Bedeutung |
| --- | --- | --- |
| [`label`](src/Shortcodes.php:37) | `Download` | Sichtbarer Button-Text; wird als Text ausgegeben, nicht als HTML. |

Ohne Parameter: [`[swnewspaper2_download]`](src/Shortcodes.php:36)

Der Button verlinkt direkt auf die PDF der nach Ausgabedatum neuesten Ausgabe. Er öffnet standardmäßig im selben Tab. Trotz der Bezeichnung „Download“ erzwingt er keinen Dateidownload: Ob die PDF angezeigt oder heruntergeladen wird, entscheiden Browser und Server.

### 2. Titelbild oder dFlip-Vorschau der aktuellen Ausgabe

Beispiel: [`[swnewspaper2_current width="300"]`](src/Shortcodes.php:55)

| Parameter | Standard | Werte und Bedeutung |
| --- | --- | --- |
| [`width`](src/Shortcodes.php:56) | `200` | Breite des äußeren Containers in Pixeln, als ganze Zahl ohne Einheit. Wird auf mindestens 50 und höchstens 1200 begrenzt. |

Ohne Parameter: [`[swnewspaper2_current]`](src/Shortcodes.php:55)

- Ohne verfügbare dFlip-Integration erscheint das Titelbild als Link zur aktuellen PDF. Der Link öffnet in einem neuen Tab.
- Ohne Titelbild erscheint stattdessen der anklickbare Text **PDF**.
- Wenn ein dFlip-Buch in den Plugin-Einstellungen ausgewählt wurde und der dFlip-Shortcode registriert ist, wird die dFlip-Vorschau statt des normalen PDF-Covers ausgegeben.
- Der Container ist maximal so breit wie der verfügbare Platz. Die tatsächliche Bildgröße kann durch das Bildformat und die maximale Bildhöhe von 400 Pixeln kleiner ausfallen.
- Dieser Shortcode gibt selbst weder den Ausgabentitel noch das Ausgabedatum aus.

Die Auswahl eines bestimmten Buchs oder einer bestimmten Zeitung ist kein Shortcode-Parameter; die dFlip-Zuordnung erfolgt global in den Plugin-Einstellungen.

### 3. Ausgabenarchiv als Raster

Beispiel: [`[swnewspaper2_archive columns="3" limit="9"]`](src/Shortcodes.php:78)

| Parameter | Standard | Werte und Bedeutung |
| --- | --- | --- |
| [`columns`](src/Shortcodes.php:81) | `4` | Gewünschte Spaltenzahl auf großen Bildschirmen; ganze Zahl von 1 bis 6. Kleinere oder größere Werte werden auf diese Grenzen begrenzt. |
| [`limit`](src/Shortcodes.php:82) | `-1` | Maximale Zahl der Ausgaben. Eine positive ganze Zahl begrenzt die Liste; `-1` zeigt alle Ausgaben. Für eine verlässliche Begrenzung nicht `0` verwenden. |

Ohne Parameter: [`[swnewspaper2_archive]`](src/Shortcodes.php:78)

Jede Karte enthält ein verlinktes Titelbild beziehungsweise den PDF-Platzhalter, den beim Upload vergebenen Titel und das Ausgabedatum. Das Datumsformat stammt aus **Einstellungen → Allgemein → Datumsformat** in WordPress.

- Sortierung: neuestes Ausgabedatum zuerst.
- Die aktuelle Ausgabe ist ebenfalls im Archiv enthalten.
- Cover-Links öffnen die jeweilige PDF in einem neuen Tab, auch wenn dFlip eingerichtet ist.
- Bis einschließlich 900 Pixel Viewport-Breite zeigt das Raster höchstens zwei Spalten, bis einschließlich 480 Pixel nur eine.
- Es gibt keine Seitennavigation, Jahresfilter, Suchfunktion oder Auswahl einzelner Ausgaben per ID. Auch ein Ausschluss der aktuellen Ausgabe ist nicht als Parameter implementiert.

Nur die oben genannten Attribute werden von den jeweiligen Plugin-Shortcodes ausgewertet; zusätzliche Attribute ergänzen keine Funktionen.

## Optionale Integrationen

### dFlip-Blätterbuch

1. dFlip installieren und aktivieren.
2. In dFlip ein Buch anlegen.
3. Unter **Zeitung / Newspaper → Einstellungen / Settings → dFlip-Buch** dieses Buch auswählen und speichern.
4. Anschließend eine Ausgabe über sw-newspaper2 hochladen, damit PDF-Quelle und Vorschaubild des Buchs gesetzt werden.
5. Im Frontend [`[swnewspaper2_current width="300"]`](src/Shortcodes.php:55) verwenden.

Intern wird die dFlip-Ausgabe [`[dflip id="123" type="thumb"][/dflip]`](src/Shortcodes.php:67) mit der gespeicherten Buch-ID aufgerufen. Ein zusätzlicher dFlip-Shortcode im Seiteninhalt ist für diese Integration nicht nötig. Wie sich die Blätterbuchansicht im Detail verhält, bestimmt dFlip.

**Wichtiger Unterschied:** Das ausgewählte dFlip-Buch wird bei **jedem neuen Upload** auf die hochgeladene PDF umgestellt, unabhängig von deren Ausgabedatum. Download-Button und normale Cover-Ausgabe wählen dagegen nach Ausgabedatum. Beim nachträglichen Hochladen einer älteren Ausgabe können dFlip und Download deshalb unterschiedliche PDFs zeigen.

Das bloße Speichern einer neuen Buchauswahl synchronisiert keine bereits vorhandene Ausgabe. Auch das Löschen einer Zeitung setzt das dFlip-Buch nicht automatisch auf die nächste Ausgabe zurück. In solchen Fällen die Buchquelle in dFlip prüfen und gegebenenfalls dort korrigieren. Siehe [Aktualisierung beim Upload](src/Upload_Service.php:151) und [Speichern der Einstellungen](src/Settings.php:32).

### WP Media Folder

Unter **Einstellungen / Settings → Medienordner / Media folder** kann ein vorhandener WP-Media-Folder-Ordner gewählt werden. Zukünftige Uploads von PDFs und Titelbildern werden diesem Ordner zugeordnet. Bestehende Ausgaben werden beim Speichern der Einstellung nicht nachträglich verschoben.

Die Ordnerzuordnung organisiert die Mediathek; sie ist kein Filter für das Frontend-Archiv.

## Gestaltung

Das Plugin lädt seine [Frontend-Styles](assets/frontend.css) bei einer nicht leeren Shortcode-Ausgabe. Anpassungen können im Theme beziehungsweise über dessen zusätzliche CSS-Einstellungen erfolgen. Zentrale Selektoren sind:

| Selektor | Element |
| --- | --- |
| [`.swnp-button`](assets/frontend.css:65) | Download-Button |
| [`.swnp-current`](src/Shortcodes.php:72) | Container der aktuellen Ausgabe |
| [`.swnp-archive`](assets/frontend.css:1) | Archiv-Raster |
| [`.swnp-card`](assets/frontend.css:10) | Einzelne Archivkarte |
| [`.swnp-cover`](assets/frontend.css:29) | Verlinktes Titelbild |
| [`.swnp-card__title`](assets/frontend.css:53) | Ausgabentitel |
| [`.swnp-card__date`](assets/frontend.css:60) | Ausgabedatum |

## Häufige Probleme

| Symptom | Prüfung / Lösung |
| --- | --- |
| Der Shortcode erscheint als Text. | Plugin-Aktivierung, PHP-Version und vollständiges Paket mit Composer-Autoloader prüfen. Einen Shortcode-Block beziehungsweise ein Shortcode-Element verwenden. |
| Es erscheint gar nichts. | Mindestens eine Ausgabe über den Plugin-Upload veröffentlichen. Ein PDF nur in der Mediathek reicht nicht. |
| Statt eines Covers erscheint „PDF“. | Die Titelbilderzeugung war nicht erfolgreich. Imagick, PDF-Unterstützung, Serverrichtlinien und Schreibrechte prüfen. Die PDF bleibt trotzdem erreichbar. |
| Die falsche Ausgabe ist aktuell. | Das Ausgabedatum prüfen; nicht der letzte Upload, sondern das höchste Ausgabedatum entscheidet. Sonderfall dFlip beachten. |
| dFlip zeigt eine ältere oder andere Ausgabe. | Buchauswahl und Quelle in dFlip kontrollieren. Die Quelle wird nur beim Plugin-Upload geändert; auch ein Archiv-Nachupload überschreibt sie. |
| Ein Klick lädt nicht direkt herunter. | Der Download-Button ist ein normaler PDF-Link ohne erzwungenen Download. Das Anzeigeverhalten hängt von Browser und Server ab. |
| Der Upload schlägt fehl. | Nur PDF verwenden, Titel und Datum prüfen sowie WordPress-/PHP-Uploadlimits und Verzeichnisrechte kontrollieren. |
| Das Archiv hat auf dem Smartphone weniger Spalten. | Das ist die beabsichtigte responsive Darstellung. |

Die Frontend-Shortcodes enthalten keine Anmeldung oder Zugriffsbeschränkung. Verlinkte PDFs werden direkt über ihre Medien-URL aufgerufen; dieses Plugin ist kein Schutz für vertrauliche Dokumente.

## Deinstallation und Daten

Bei der Deinstallation entfernt das Plugin seine gespeicherten Einstellungen. Hochgeladene Zeitungen und Titelbilder bleiben in der Mediathek erhalten, siehe [Deinstallationsroutine](uninstall.php:10). Davon zu unterscheiden ist das endgültige Löschen einzelner Ausgaben im Plugin-Archiv, das PDF und Cover entfernt.
