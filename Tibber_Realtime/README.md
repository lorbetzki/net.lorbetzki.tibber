# Tibber Realtime
Mit diesem Modul können die Informationen abgerufen werden welche von der "Tibber Realtime API" bereitgestellt werden.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [PHP-Befehlsreferenz](#6-php-befehlsreferenz)

### 1. Funktionsumfang

diese optionale Instanz erlaubt es, sofern ein Tibber Pulse im Account vorhanden ist, diese in Echtzeit abzufragen. Folgende Variablen werden dabei erstellt, sofern der Zähler das ausgibt:

Aktuelle Leistung Bezug
Aktuelle Leistung Einspeisung
Zählerstand Bezug
Zählerstand Einspeisung
Verbrauch des aktuellen Tages
Produktion des aktuellen Tages
Verbrauch der aktuellen Stunde
Produktion der aktuellen Stunde
Kosten des aktuellen Tages
minimale Bezugs-Leistung des Tages
maximale Bezugs-Leistung des Tages
minimale Leistung des Tages
maximale Leistung des Tages
durchschnittliche Leistung des Tages
minimale Produktions-Leistung des Tages
maximale Produktions-Leistung des Tages
Blindleistung
Produktions-Blindleistung 
Spannung Phase 1
Spannung Phase 2
Spannung Phase 3
Stromstärke Phase 1
Stromstärke Phase 2
Stromstärke Phase 3
Signalstärke Zähler
Währung

### 2. Voraussetzungen

- Symcon ab Version 6.3
- Tibber Account
- Tibber Api Token -> [Tibber Developer](https://developer.tibber.com/) -> dort auf Sign-in, meldet euch mit eurem Tibber Account an und erstellt dort den Access-Token.
- Tibber Pulse für Realtime Daten

### 3. Software-Installation

* Über den Module Store das 'Tibber'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen https://github.com/lorbetzki/net.lorbetzki.tibber.git

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann die 'Tibber_Realtime'-Instanz mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name          				     | Beschreibung
-------------------------------- | -------------------------------------------------------
Realtime Stream aktiveren | de/aktivieren der Abfragen.
Benutzer Token | Access-Token aus der Tibber API eintragen
Heim auswählen | Nachdem der Token eingetragen und die Änderung übernommen wurde, werden hier die im Account gespeicherten Heime aufgeführt. Wählt das, welches Ihr abfragen möchtet



### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Name                          							| Typ     | Beschreibung
----------------------------- 							| ------- | ------------
Aktuelle Leistung Bezug | Float | Aktuelle Leistung Bezug
Aktuelle Leistung Einspeisung | Float | Aktuelle Leistung Einspeisung
Zählerstand Bezug | Float | Zählerstand Bezug
Zählerstand Einspeisung | Float | Zählerstand Einspeisung
Verbrauch des aktuellen Tages | Float | Verbrauch des aktuellen Tages
Produktion des aktuellen Tages | Float | Produktion des aktuellen Tages
Verbrauch der aktuellen Stunde | Float | Verbrauch der aktuellen Stunde
Produktion der aktuellen Stunde | Float | Produktion der aktuellen Stunde
Kosten des aktuellen Tages | Float | Kosten des aktuellen Tages
minimale Bezugs-Leistung des Tages | Float | minimale Bezugs-Leistung des Tages
maximale Bezugs-Leistung des Tages | Float | maximale Bezugs-Leistung des Tages
durchschnittliche Leistung des Tages | Float | durchschnittliche Leistung des Tages
minimale Produktions-Leistung des Tages | Float | minimale Produktions-Leistung des Tages
maximale Produktions-Leistung des Tages | Float | maximale Produktions-Leistung des Tages
minimale Leistung des Tages | Float | minimale Leistung des Tages
maximale Leistung des Tages | Float | maximale Leistung des Tages
Blindleistung | Float | Blindleistung
Produktions-Blindleistung | Float | Produktions-Blindleistung
Spannung Phase 1 | Float | Spannung Phase 1
Spannung Phase 2 | Float | Spannung Phase 2
Spannung Phase 3 | Float | Spannung Phase 3
Stromstärke Phase 1 | Float | Stromstärke Phase 1
Stromstärke Phase 2 | Float | Stromstärke Phase 2
Stromstärke Phase 3 | Float | Stromstärke Phase 3
Signalstärke Zähler | Integer | Signalstärke Zähler
Währung | String | Währung

#### Profile

Name                    | Typ
------------------------| -------
Tibber.price.cent | Integer | Eurocent zweistellig
Tibber.price.euro | Integer | Euro zweistellig

### .6 PHP-Befehlsreferenz
`TIBBERRT_ReloginSequence(integer $InstanzID);`
INTERNE FUNKTION: startet eine Neuanmeldungssequenz. Dafür wird die vorherige Verbindung geschlossen, zufällig zwischen 60-120 sek gewartet und neu angemeldet.

Beispiel:
`TIBBERRT_ReloginSequence(12345);`


`TIBBERRT_StartWatchdog(integer $InstanzID);`
INTERNE FUNKTION: sobald Daten empfangen werden, wird der Watschdog auf 30 sek. gesetzt, kommen innerhalb dieser Zeit keine Daten an, wird die Funktion TIBBERRT_ReloginSequence() ausgeführt.

Beispiel:
`TIBBERRT_StartWatchdog(12345);`
