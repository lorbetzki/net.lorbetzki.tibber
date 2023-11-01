# Tibber Realtime
Mit diesem Modul können die Informationen abgerufen werden welche von der "Tibber Realtime API" bereitgestellt werden.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

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


#### Profile

Name                    | Typ
------------------------| -------

### 6. WebFront

Name                          							| Typ     | Beschreibung
--------------------------------------------------------| ------- | ------------

### 7. PHP-Befehlsreferenz


