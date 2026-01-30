# InfoDisplay flygklubb
Ett projekt byggt med Lovable. Detta är den kompilerade versionen som inte kräver att man bygger projektet själv.

## Beskrivning
Detta är en enkel informationspanel för en flygklubb som hämtar bokningar från myweblog.se och METAR/TAF för närmaste flygplats från <a href="https://checkwxapi.com">checkwx.com</a>. Båda kräver att man har API-tillgång. För närvarande används myweblog api 4.

<img width="1445" height="1093" alt="infodisplay_exempel" src="https://github.com/user-attachments/assets/ee5d647f-e6b3-497f-b426-4b327065b3be" />

## Installation

- Lägg till API-nycklar för checkwx och myweblog i config.php
- Ange ICAO för närmaste flygplats samt koordinater (ex. 55.92) i config.php
- ÄNDRA TEXT FÖR KRFK i koden
- Ladda upp i ditt webbhotells public_html-mapp. PHP och JS krävs.
