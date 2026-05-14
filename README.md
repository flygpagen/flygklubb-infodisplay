# InfoDisplay flygklubb version 2
Ett projekt byggt med Lovable. Detta är den kompilerade versionen som inte kräver att man bygger projektet själv.

<img width="1507" height="849" alt="infodisplay_m_notam" src="https://github.com/user-attachments/assets/65960a91-5b25-4389-b9fa-163ddaa04133" />

## Version 2.1 nyheter ##
- Möjlighet att ange antal uppdateringar av METAR/TAF. Då CheckWX inför begränsat antal anrop per dygn till max 200 kan man nu ange hur ofta data hämtas. Standardvärdena är TAF var 60 minut och METAR var 15 minut
- Konfigurerbart att hämta väder från en flygplats och NOTAM från en annan. Lämpligt för mindre flygplatser utan "eget väder"
- Allmänt småfix

## Version 2 nyheter ##
Denna version är ordentligt uppdaterad med flera efterfrågade funktioner:
- Valbart tema för dag/natt eller auto (växlar vid solnedgång)
- Välj mellan format 4:3, 16:9 eller stående 9:16
- I format 16:9 tillkommer en 3e panel "radar" med konfigurerbar visning av exempelvis flygradar eller windy
- Möjlighet att växla vy mellan flera olika sidor (karusell)
- Möjlighet att växla radarvy på huvudsidan (panelkarusell)
- Banriktning konfigurerbar visas i vindcirkeln
- Diverse förbättringar i avkodning av METAR, från CheckWX API v2
- NOTAM från AutoRouter, experimentell funktion

## Beskrivning
Detta är en webbaserad informationspanel för en flygklubb som hämtar bokningar från <a href="https://myweblog.se">klubbens myWebLog</a>, METAR/TAF för närmaste flygplats från <a href="https://checkwxapi.com">checkwx.com</a> och NOTAM från [Autorouter](https://www.autorouter.aero/). Alla tjänster kräver att man har API-tillgång. **Nycklar från myWebLog måste vara myweblog api v4**. 

Informationspanelen är en webbsida med möjlighet till konfiguration. Den går att sätta upp på ett webhotell (testat) och säkert direkt på en lokal server såsom en Rbpi (ej testat). Lämpligt att sätta upp i kiosk-läge i klubbstugan. Data hämtas regelbundet från båda källor, METAR avkodas och visas grafiskt. Baserat på positionsangivelse visas soltider nederst på skärmen.

## Installation
- Ladda upp i ditt webbhotells public_html-mapp. Filerna måste ligga i roten (.../public_html/index.html respektive /api /assets). De flesta webhotell gör det enkelt att skapa en underdomän där man kan lägga filerna (exempelvis *infodisplay.flygklubb.nu*)
- Lägg till API-nycklar för myweblog (api v4), checkwx och autorouter (valbart) i config.php
- Ange ICAO för närmaste flygplats samt koordinater (ex. 55.92) i config.php
- Konfigurera övriga parametrar enligt önskemål i config.php

### Parametrar i config.php
Se även kommentarer i filen.
- **Display title**: skriv in vad som ska stå överst till vänster, till exempel flygklubbens namn.
- **Runway settings**: Ange banriktning i grader, till exempel 10 för bana 01. Detta ritar upp en bana i önskad riktning för att illustrera vindinfallsvinkeln. 
- **Display settings**: Ange om skärmen är 4:3 ("tjocktv"), 16:9 ("widescreen") eller stående 9:16. Vid formaten 16:9 och 9:16 läggs en tredje del av skärmen till som kan konfigureras att visa väderradar eller flygradar eller något annat. Ange i parametern RADAR_URL vad som ska visas här. Hemma kör vi den lokala FR24-mottagarens radarbild eller snarare tar1090.
- **Panel carousel**: Vill du visa växelvis flera tjänster på informationssidan lägger du till ytterligare URL:er här. [Windy har en embed-funktion](https://embed.windy.com/config/map) där du kan ta fram en lokal väderradar att lägga in. Flera adresser separeras med kommatecken. Det är bara tjänster utöver den du angav i RADAR_URL som du lägger till här.
- **Carousel settings**: Om du vill att det ska visas fler sidor på skärmen än bara flyginformationssidan kan du lägga till dem här. Carousel enabled behöver sättas till *true*. Ställ in intervall mellan byten i sekunder. Lägg till vilka sidor som ska hämtas (url:er kommaseparerade) i den ordning som de ska visas.
- **NOTAM Panel**: Aktivera (true) eller avaktivera (false) visning av NOTAM. Data hämtas för värdet i ICAO_CODE under Airport/Location settings.

## Begränsningar
- Koden filtrerar flygplan med SE-registrering, andra objekt visas inte i listan.
- Vid skolning med elev visas den som har gjort bokningen, inte elevens namn.
- Lite osäkert hur visningen blir om man har en bredare skärm men bara vill visa bokningar/väder och väljer format 4:3. Sidan ska nu vara responsiv men detta har inte testats.

Boka & flyg! :-)
