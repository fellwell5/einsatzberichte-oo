=== Einsatzberichte OOE ===
Contributors: fellwell5
Tags: einsatzberichte, lfk, oberoesterreich, feuerwehr, freiwillig, einsatz, presse, lfv, ooelfv, afk, bfk
Requires at least: 3.0.1
Tested up to: 5.9
Requires PHP: 5.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
 
Einsatzberichte OOE wurde für eine einfache Verknüpfung und Datenabfrage vom LFK-Intranet erstellt.
 
== Description ==
Einsatzberichte OOE wurde für eine einfache Verknüpfung und Datenabfrage vom LFK-Intranet erstellt.

Verknüpfe deine Berichte/Beiträge mit Einsätzen vom LFK (Klicke auf Beitrag bearbeiten oder erstelle einen neuen).
Füge das Widget zu deiner Seitenleiste hinzu. Hier kannst du auch viele Einstellungen treffen.
Füge den Shortcode [eib_jahresuebersicht] als Text in einem Beitrag oder einer Seite ein. An dieser Stelle wird automatisch eine Jahresübersicht deiner Einsätze erstellt.

Einsätze werden ab dem 20. Jänner 2016 angezeigt. (Ab hier speichert unsere umfangreiche Datenbank)
 
== Installation ==
1. Lade die .zip-Datei in das Verzeichnis /wp-content/plugins
2. Aktiviere das Plugin über die Pluginseite von Wordpress
3. Navigiere im Adminpanel auf die Seite "EIB-Konfig" (unten in der Seitenleiste) und wähle dort deine Feuerwehr aus und klicke auf Speichern.

== Localization ==
* German (default) - always included

== Screenshots ==
1. Die Ansicht der Jahresübersicht. Diese Ansicht wird mit dem Shortcode [eib_jahresuebersicht] erstellt. Einfach den Code (mit den Klammern) in einen Beitrag oder eine Seite kopieren und die Ansicht generiert sich automatisch.
2. Dieses Auswahl-Feld sehen Sie am Ende der Beitrag erstellen-Seite, hier wird ein Einsatz mit einem Bericht verknüft.
3. Diese zwei Widgets stehen zur Verfügung und können in die Seitenleisten des Blogs eingebunden und konfiguriert werden.
4. Die einfache Einstellungs-Seite des Plugins: Feuerwehrname auswählen und wenn der Haken bei Statistiken anzeigen gesetzt ist, wird der runde Chart mit Einsatzarten angezeigt.
5. Auch ein Einsatzkarten-Widget steht zur Verfügung. Der Hintergrund kann auf weiß oder eine Unwetterkarte geändert werden.

== Disclaimer ==
Die Einsatzdaten werden von einer externen Seite geladen!
An die Adresse https://einsatzinfo.cloud wird die von dir ausgewählte Feuerwehr übertragen und die Einsatzdaten empfangen.
Beispiel der übertragenen Daten: "FF Gallneukirchen"

== Changelog ==

= 0.2.1 =
* Einsatzart SELBST für selbstständige Einsätze hinzugefügt

= 0.2.0 =
* Anpassungen für neues ELS des OÖLFV durchgeführt

= 0.1.4 =
* Fehler bei der Darstellung von Umlauten behoben

= 0.1.1 =
* Cache zurücksetzen wenn die Auswahl der Feuerwehr geändert wird

= 0.1.0 =
* Widget Laufende Einsätze in OÖ hinzugefügt
* Um Bandbreite zu sparen werden die Daten nun von Wordpress zwischengespeichert
* Ressourcen von Jahresübersicht werden nun nicht mehr immer geladen

= 0.0.9 =
* Einsatzdaten werden nun von einsatzinfo.cloud geladen

= 0.0.8 =
* Widget "Einsatzkarte" wurde hinzugefügt

= 0.0.7 =
* Link zur Erstellung einer Einsatzkurzmeldung für den Einsatz hinzugefügt.
* Fehlerbehebungen (Wordpress-DB Prefix wird nun für Datenbankabfragen verwendet. (Danke an Herbert!))

= 0.0.6 =
* Es wird versucht die Ortsnamen richtig von der Groß-/Kleinschreibung auszugeben. (Standard ist alles in Großbuchstaben)

= 0.0.5 =
* Fehlerbehebungen (Fehlermeldung wegen curl, Zeitzone von Wordpress wird erzwungen (Unter Einstellungen > Allgemein > Zeitzone > "Wien" auswählen))

= 0.0.4 =
* Fehlerbehebungen (Fehlermeldung bei der Jahresübersicht)

= 0.0.3 =
* Einsatzkarte-Widget hinzugefügt
* Fehlerbehebungen

= 0.0.2 =
* Updates und änderungen im Code entsprechend den Wordpress Plugin Developer Richtlinien
 
= 0.0.1 =
* Der Grundstein für das Plugin wurde gelegt.
* Beitragsverknüpfung hinzugefügt.
* Widget hinzugefügt.
* Jahresübersicht hinzugefügt.
