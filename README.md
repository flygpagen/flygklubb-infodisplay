# InfoDisplay flygklubb
Ett projekt byggt med Lovable. Detta är den kompilerade versionen som inte kräver att man bygger projektet själv.

## Beskrivning
Detta är en enkel informationspanel för en flygklubb som hämtar bokningar från myweblog.se och METAR/TAF för närmaste flygplats från <a href="https://checkwxapi.com">checkwx.com</a>. Båda kräver att man har API-tillgång. För närvarande används myweblog api 4. Displayen är gjord för skärm i 4:3-format och är inte testad på andra.

<img width="1190" height="897" alt="Infodisp_exempel" src="https://github.com/user-attachments/assets/3aa9c6c8-d14e-4572-8fe4-775d0b0839ec" />

## Installation

- Lägg till API-nycklar för checkwx och myweblog i config.php
- Ange ICAO för närmaste flygplats samt koordinater (ex. 55.92) i config.php
- Ladda upp i ditt webbhotells public_html-mapp. Filerna måste ligga i roten.
