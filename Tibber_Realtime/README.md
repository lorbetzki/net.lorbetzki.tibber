# IP-Symcon Modul: Tibber Realtime
 
Die Nutzung des Moduls geschieht auf eigene Gefahr ohne Gewähr.

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang) 
2. [Systemanforderungen](#2-systemanforderungen)
3. [Installation](#3-installation)
4. [Einrichten der Instanz](#4-einrichten-der-instanz)
5. [ChangeLog](#5-changelog)

## 1. Funktionsumfang

Es handelt sich hierbei um einen frühen Entwicklungsstand.

Mit diesem Modul können die Informationen abgerufen werden welche von der Tibber Realtime API (WSS Stream) bereit gestellt werden.

### 1.1 Aktuelle Zählerwerte
Es können folgende Informastionen in Echtzeit abgerufen werden, sofern vom Zähler auch unterstützt:

Name     | Beschreibung
-------- | ------------------
Aktuelle Leistung Bezug |
Aktuelle Leistung Einspeisung |
Zählerstand Bezug |
Zählerstand Einspeisung |
Verbrauch des aktuellen Tages |
Produktion des aktuellen Tages |
Verbrauch der aktuellen Stunde |
Produktion der aktuellen Stunde |
Kosten des aktuellen Tages |
minimale Bezugs-Leistung des Tages |
maximale Bezugs-Leistung des Tages |
minimale Leistung des Tages |
maximale Leistung des Tages |
durchschnittliche Leistung des Tages |
minimale Produktions-Leistung des Tages |
maximale Produktions-Leistung des Tages |
Blindleistung |
Produktions-Blindleistung  |
Spannung Phase 1 |
Spannung Phase 2 |
Spannung Phase 3 |
Stromstärke Phase 1 |
Stromstärke Phase 2 |
Stromstärke Phase 3 |
Signalstärke Zähler |
Währung |

## 2. Systemanforderungen
- IP-Symcon ab Version 6.0
- Tibber Account
- Tokern aus dem eigenen Tibber-Account
- Tibber Pulse Zähler der mit dem Tibber Account verknüpft ist


## 3. Installation

Das Modul ist Bestandteil der Tibber Library und wird somit bei der Installation im Module Store mit installiert.

## 4. Einrichten der Instanz

Mit der Tibber_Realtime Instanz wird automatisch eine neue IO Instanz (WS Client) angelegt und mit der Tibber_Realtime Instanz als Parent verbunden.
Diese IO Instanz wird vom Tibber Modul automatisch konfiguriert. Das Pop-Up von dem IO Modul braucht nur gespeichert zu werden.

Nach dem anlegen der Tibber_Realtime Instanz muss die Instanz konfiguriert werden.
Dazu muss der Token (aus dem Tibber Account) eingetragen werden. Danach muss die Änderung der Instanz gespeichert werden.

Ist dies geschehen, kann über den Button "Zuhauser abrufen" die Liste der im Tibber Account vorhanden Häuser abgerufen werden, welche im "Home ID" Feld dann zur Auswahl bereit gestellt werden.
Hier dann das gewünschte Haus auswählen.

### 4.1 Variablen Auswahl
In der Liste können die Variablen ausgewählt werden, welche vom Realtime Stream abgerufen werden sollen.


## 5. ChangeLog
Änderungshistorie

### Version 0.1 Test
* Initialer Commit
  