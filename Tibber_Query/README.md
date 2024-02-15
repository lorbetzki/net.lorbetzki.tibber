# Tibber
Mit diesem Modul können die Informationen abgerufen werden welche von der Tibber Query API bereitgestellt werden.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [PHP-Befehlsreferenz](#6-php-befehlsreferenz)

### 1. Funktionsumfang

* Auslesen aktueller Preis
* Auslesen aktueller Preis Level (sehr günstig, günstig, normal, teuer, sehr teuer)
* Preisvorschau als Chart
* Variablen pro Stunden anlegen für den heutigen und morgigen Tag
* Array zur Verwendung in eigene Anwendungen und Scripten.
 
### 2. Voraussetzungen

- Symcon ab Version 6.3
- Tibber Account UND Vertrag zum Heim. Ohne Vertrag bekommen wir keine Preisdaten!
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
Preisvariablen pro Stunde anlegen | Wird diese Checkbox aktivert, werden 48 Variablen ( 24 für den aktuellen Tag und 24 für den Folgetag) für jede Stunde angelegt, welche beim Abruf der Day Ahead Preise aktualisiert werden.
einige Statistiken | erstellt Variablen mit ein paar statistischen werten.

aktiviere Instanz | aktivieren der Instanz

[1] Es werden Day Ahead Preise für den aktuellen und (wenn schon publiziert) für den Folgetag abgerufen und gespeichert. Dies passiert rückwirkend, da Symcon keine zukünftigen Werte im Archiv erlaubt, in der "Day Ahead Preis Hilfsvariable" Variablen. Dabei wird der aktuelle Tag mit T -2 und der morgige Tag mit T -1 ins Archiv gespeichert.
Zusätzlich wird automatisch ein Multi-Chart angelegt, welcher diese beiden Tage im stündlichen Vergleich über die beiden Tagen darstellt.


### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Name						 | Typ     | Beschreibung
-----------------------------| ------- | ------------
Aktueller Preis | FLOAT | Gibt den aktuellen Strompreis von Tibber wieder
Aktueller Preis Level | INT | Preisniveau auf der Grundlage des nachlaufenden Preisdurchschnitts 
Day Ahead Preis Hilfsvariable | FLOAT | Wird nur gebraucht um einen Chart über zukünftige Preise erstellen zu können
Preis Array | STRING | Tibber Abfrage wird in ein Array geschrieben um eigene Anwendungen zu ermöglichen. WIRD ERSETZT DURCH DIE FUNKTION TIBBER_PriceArray();
Realtime Verfügbar | BOOL | Gibt an, ob ein Pulse oder anderer Smartmeter in Tibber erkannt wurde und Echtzeitabfragen des Verbauchs zu ermöglichen. Dazu das Modul Tibber_Realtime benutzen
Day Ahead Chart | CHART | Multichart mit Anzeige vergangener und zukünftigen Preise
Heute 0 bis 1 Uhr | FLOAT | Preisvariable pro Stunde 
Heute 1 bis 2 Uhr | FLOAT | Preisvariable pro Stunde 
Heute 2 bis 3 Uhr | FLOAT | Preisvariable pro Stunde 
Heute 3 bis 4 Uhr | FLOAT | Preisvariable pro Stunde 
Heute 4 bis 5 Uhr | FLOAT | Preisvariable pro Stunde 
Heute 5 bis 6 Uhr | FLOAT | Preisvariable pro Stunde 
Heute 6 bis 7 Uhr | FLOAT | Preisvariable pro Stunde 
Heute 7 bis 8 Uhr | FLOAT | Preisvariable pro Stunde 
Heute 8 bis 9 Uhr | FLOAT | Preisvariable pro Stunde 
Heute 9 bis 10 Uhr | FLOAT | Preisvariable pro Stunde 
Heute 10 bis 11 Uhr | FLOAT | Preisvariable pro Stunde 
Heute 11 bis 12 Uhr | FLOAT | Preisvariable pro Stunde 
Heute 12 bis 13 Uhr | FLOAT | Preisvariable pro Stunde 
Heute 13 bis 14 Uhr | FLOAT | Preisvariable pro Stunde 
Heute 14 bis 15 Uhr | FLOAT | Preisvariable pro Stunde 
Heute 15 bis 16 Uhr | FLOAT | Preisvariable pro Stunde 
Heute 16 bis 17 Uhr | FLOAT | Preisvariable pro Stunde 
Heute 17 bis 18 Uhr | FLOAT | Preisvariable pro Stunde 
Heute 18 bis 19 Uhr | FLOAT | Preisvariable pro Stunde 
Heute 19 bis 20 Uhr | FLOAT | Preisvariable pro Stunde 
Heute 20 bis 21 Uhr | FLOAT | Preisvariable pro Stunde 
Heute 21 bis 22 Uhr | FLOAT | Preisvariable pro Stunde 
Heute 22 bis 23 Uhr | FLOAT | Preisvariable pro Stunde 
Heute 23 bis 24 Uhr | FLOAT | Preisvariable pro Stunde 
Morgen 0 bis 1 Uhr | FLOAT | Preisvariable pro Stunde 
Morgen 1 bis 2 Uhr | FLOAT | Preisvariable pro Stunde 
Morgen 2 bis 3 Uhr | FLOAT | Preisvariable pro Stunde 
Morgen 3 bis 4 Uhr | FLOAT | Preisvariable pro Stunde 
Morgen 4 bis 5 Uhr | FLOAT | Preisvariable pro Stunde 
Morgen 5 bis 6 Uhr | FLOAT | Preisvariable pro Stunde 
Morgen 6 bis 7 Uhr | FLOAT | Preisvariable pro Stunde 
Morgen 7 bis 8 Uhr | FLOAT | Preisvariable pro Stunde 
Morgen 8 bis 9 Uhr | FLOAT | Preisvariable pro Stunde 
Morgen 9 bis 10 Uhr | FLOAT | Preisvariable pro Stunde 
Morgen 10 bis 11 Uhr | FLOAT | Preisvariable pro Stunde 
Morgen 11 bis 12 Uhr | FLOAT | Preisvariable pro Stunde 
Morgen 12 bis 13 Uhr | FLOAT | Preisvariable pro Stunde 
Morgen 13 bis 14 Uhr | FLOAT | Preisvariable pro Stunde 
Morgen 14 bis 15 Uhr | FLOAT | Preisvariable pro Stunde 
Morgen 15 bis 16 Uhr | FLOAT | Preisvariable pro Stunde 
Morgen 16 bis 17 Uhr | FLOAT | Preisvariable pro Stunde 
Morgen 17 bis 18 Uhr | FLOAT | Preisvariable pro Stunde 
Morgen 18 bis 19 Uhr | FLOAT | Preisvariable pro Stunde 
Morgen 19 bis 20 Uhr | FLOAT | Preisvariable pro Stunde 
Morgen 20 bis 21 Uhr | FLOAT | Preisvariable pro Stunde 
Morgen 21 bis 22 Uhr | FLOAT | Preisvariable pro Stunde 
Morgen 22 bis 23 Uhr | FLOAT | Preisvariable pro Stunde 
Morgen 23 bis 24 Uhr | FLOAT | Preisvariable pro Stunde 
Morgen 23 bis 24 Uhr | FLOAT | Preisvariable pro Stunde 
min/max Preisspanne für heute | FLOAT | Preisspanne, also die differenz zwischen min und max Wert
max. Preis für heute  | FLOAT | der höchste Tagespreis 
min. Preis für heute | FLOAT | der niedriegste Tagespreis
niedrigster Preis am diesem Zeitpunkt  für heute | INT | der niedriegste war zu diesem Zeitpunkt
höchster Preis am diesem Zeitpunkt für heute | INT | der höchste Preis war zu diesem Zeitpunkt
min/max Preisspanne für morgen | FLOAT | Preisspanne, also die differenz zwischen min und max Wert
max. Preis für morgen | FLOAT | der höchste Tagespreis 
min. Preis für morgen | FLOAT | der niedriegste Tagespreis
niedrigster Preis am diesem Zeitpunkt für morgen | INT | der niedriegste war zu diesem Zeitpunkt
höchster Preis am diesem Zeitpunkt für morgen | INT | der höchste Preis war zu diesem Zeitpunkt
Anzahl sehr günstiger Preis | INT | Anzahl des Preises
Anzahl günstiger Preis | INT | Anzahl des Preises
Anzahl normaler Preis | INT | Anzahl des Preises
Anzahl teurer Preis | INT | Anzahl des Preises
Anzahl sehr teurer Preis | INT | Anzahl des Preises

#### Profile

Name                    | Typ
------------------------| -------
Tibber.price.cent | INT | Eurocent zweistellig
Tibber.price.level | INT | ermittelter Preislevel von Tibber 1 = sehr günstig, 2 = günstig, 3 = normal, 4 = teuer, 5 = sehr teuer, 0 = -
Tibber.price.hour | INT | zeigt nur den Suffix "Uhr" an. 

### .6 PHP-Befehlsreferenz
`TIBBER_GetPriceData(integer $InstanzID);`

Holt sich neue Preisdaten von Tibber ab und schreibt es in eine interne Variable. Bei aktiviertem "Preis - Array anlegen -> Zur Nutzung in Scripten und anderen Modulen" aktiviert ist, wird auch diese Variable geschrieben.

Beispiel:
`TIBBER_GetPriceData(12345);`


`TIBBER_SetActualPrice(integer $InstanzID);`

Holt sich neue Preisdaten von Tibber ab und schreibt diese in alle aktivierten Variablen.

Beispiel:
`TIBBER_SetActualPrice(12345);`	



`TIBBER_GetConsumptionHourlyLast(integer $InstanzID, integer $count);`,

`TIBBER_GetConsumptionDailyLast(integer $InstanzID, integer $count);`, 

`TIBBER_GetConsumptionWeeklyLast(integer $InstanzID, integer $count);`, 

`TIBBER_GetConsumptionMonthlyLast(integer $InstanzID, integer $count);`, 

`TIBBER_GetConsumptionYearlyLast(integer $InstanzID, integer $count);`

Holt sich die letzen Verbrauchsdaten für den angegebenen ($count) Zeitraum ab. $count gibt die Anzahl der Datensätze an. Als Ergebnis kommt ein JSON Codierter Datensatz mit dem man weiterarbeiten kann.

Beispiel starte ich um 21:00 diese Abfrage werden die letzten 24 Stunden ab 21:00 des vortages abgeholt:

`TIBBER_GetConsumptionHourlyLast(12345,24);`

Beispiel holt den letzten Monat ab:

`TIBBER_GetConsumptionMonthlyLast(12345,1);`



`TIBBER_GetConsumptionHourlyFirst(integer $InstanzID, integer $count);`, 

`TIBBER_GetConsumptionDailyFirst(integer $InstanzID, integer $count);`, 

`TIBBER_GetConsumptionWeeklyFirst(integer $InstanzID, integer $count);`, 

`TIBBER_GetConsumptionMonthlyFirst(integer $InstanzID, integer $count);`, 

`TIBBER_GetConsumptionYearlyFirst(integer $InstanzID, integer $count);`

Holt sich die Verbrauchsdaten für den angegebenen ($count) Zeitraum ab. $count gibt die Anzahl der Datensätze an. Als Ergebnis kommt ein JSON Codierter Datensatz mit dem man weiterarbeiten kann.

Beispiel holt die letzten 24 Stunden ab Anfang des Monats ab:

`TIBBER_GetConsumptionHourlyFirst(12345,24);`

Beispiel holt die Daten des aktuellen Monats ab:

`TIBBER_GetConsumptionMonthlyFirst(12345,1);`


`TIBBER_PriceArray(integer $InstanzID);`
kann für externe Scripte eingesetzt werden. Gibt das abgeholte Preis Array raus

Beispiel:
`TIBBER_PriceArray(12345);`
