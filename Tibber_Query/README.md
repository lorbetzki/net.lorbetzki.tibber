# Tibber
Mit diesem Modul können die Informationen abgerufen werden welche von der Tibber Query API bereitgestellt werden.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Auslesen aktueller Preis
* Auslesen aktueller Preis Level (sehr günstig, günstig, normal, teuer, sehr teuer)
* Preisvorschau als Chart
* Variablen pro Stunden anlegen für den heutigen und morgigen Tag
* Array zur Verwendung in eigene Anwendungen und Scripten.
 
### 2. Voraussetzungen

- Symcon ab Version 6.3
- Tibber Account
- Tibber Api Token -> [Tibber Developer](https://developer.tibber.com/) -> dort auf Sign-in, meldet euch mit eurem Tibber Account an und erstellt dort den Access-Token.

### 3. Software-Installation

* Über den Module Store das 'Tibber'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen https://github.com/lorbetzki/net.lorbetzki.tibber.git

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann die 'Tibber'-Instanz mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name          				     | Beschreibung
-------------------------------- | -------------------------------------------------------
Benutzer Token | Access-Token aus der Tibber API eintragen
Heim auswählen | Nachdem der Token eingetragen und die Änderung übernommen wurde, werden hier die im Account gespeicherten Heime aufgeführt. Wählt das, welches Ihr abfragen möchtet
Preisdatenvariablen loggen | Diese Checkbox muss aktiviert werden, wenn die Day Ahead Preise im Archiv gespeichert sowie der Multi-Chart erzeugt werden sollen. [1] 
Preisvariablen pro Stunde anlegen |Wird diese Checkbox aktivert, werden 48 Variablen ( 24 für den aktuellen Tag und 24 für den Folgetag) für jede Stunde angelegt, welche beim Abruf der Day Ahead Preise aktualisiert werden.
Preis Array anlegen | Auswahl diverser Variablen
aktiviere Instanz | aktivieren der Instanz

[1] Es werden Day Ahead Preise für den aktuellen und (wenn schon publiziert) für den Folgetag abgerufen und gespeichert. Dies passiert rückwirkend, da Symcon keine zukünftigen Werte im Archiv erlaubt, in der "Day Ahead Preis Hilfsvariable" Variablen. Dabei wird der aktuelle Tag mit T -2 und der morgige Tag mit T -1 ins Archiv gespeichert.
Zusätzlich wird automatisch ein Multi-Chart angelegt, welcher diese beiden Tage im stündlichen Vergleich über die beiden Tagen darstellt.


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


