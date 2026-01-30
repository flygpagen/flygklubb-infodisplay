# InfoDisplay flygklubb
Ett projekt byggt med Lovable. Detta är den kompilerade versionen som inte kräver att man bygger projektet själv.

## Beskrivning
Detta är en enkel informationspanel för en flygklubb som hämtar bokningar från <a href="https://myweblog.se">klubbens myWebLog</a> och METAR/TAF för närmaste flygplats från <a href="https://checkwxapi.com">checkwx.com</a>. Båda kräver att man har API-tillgång. **Det måste vara myweblog api 4**! Lämpligt att sätta upp i kiosk-läge i klubbstugan. Data hämtas regelbundet från båda källor, METAR avkodas och visas grafiskt. Baserat på positionsangivelse visas soltider nederst på skärmen.

<img width="1190" height="897" alt="Infodisp_exempel" src="https://github.com/user-attachments/assets/3aa9c6c8-d14e-4572-8fe4-775d0b0839ec" />

## Installation

- Lägg till API-nycklar för checkwx och myweblog (api v4) i config.php
- Ange ICAO för närmaste flygplats samt koordinater (ex. 55.92) i config.php
- Ladda upp i ditt webbhotells public_html-mapp. Filerna måste ligga i roten.

## Begränsningar
- Displayen är gjord för skärm i 4:3-format och är inte testad på andra.
- Koden filtrerar flygplan med SE-registrering, andra objekt visas inte i listan.
- Bokningar som började för mer än 365 dagar sedan visas inte, det blir för mycket data att hämta och filtrera.
- Vid skolning med elev visas den som har gjort bokningen, inte elevens namn.
