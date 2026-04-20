# InfoDisplay flygklubb version 2
Ett projekt byggt med Lovable. Detta är den kompilerade versionen som inte kräver att man bygger projektet själv.

# Version 2 nyheter
Denna version är ordentligt uppdaterad med flera efterfrågade funktioner:
- Valbart tema för dag/natt eller auto (växlar vid solnedgång)
- Välj mellan format 4:3, 16:9 eller stående 9:16
- I format 16:9 tillkommer en 3e panel "radar" med konfigurerbar visning av exempelvis flygradar eller windy
- Möjlighet att växla vy mellan flera olika sidor (karusell)
- Möjlighet att växla radarvy på huvudsidan (panelkarusell)
- Banriktning konfigurerbar visas i vindcirkeln
- Diverse förbättringar i avkodning av METAR, från CheckWX API v2

![flightinformation_v2](https://github.com/user-attachments/assets/39f4e75e-ce40-46c8-88b5-f66841c406fe)

## Beskrivning
Detta är en enkel informationspanel för en flygklubb som hämtar bokningar från <a href="https://myweblog.se">klubbens myWebLog</a> och METAR/TAF för närmaste flygplats från <a href="https://checkwxapi.com">checkwx.com</a>. Båda kräver att man har API-tillgång. **Det måste vara myweblog api 4**! Lämpligt att sätta upp i kiosk-läge i klubbstugan. Data hämtas regelbundet från båda källor, METAR avkodas och visas grafiskt. Baserat på positionsangivelse visas soltider nederst på skärmen.

## Installation
- Lägg till API-nycklar för checkwx och myweblog (api v4) i config.php
- Ange ICAO för närmaste flygplats samt koordinater (ex. 55.92) i config.php
- Konfigurera övriga parametrar enligt önskemål i config.php
- Ladda upp i ditt webbhotells public_html-mapp. Filerna måste ligga i roten.

### Parametrar i config.php
Se även kommentarer i filen.
- **Display title**: skriv in vad som ska stå överst till vänster, till exempel flygklubbens namn.
- **Runway settings**: Ange banriktning i grader, till exempel 10 för bana 01. Detta ritar upp en bana i önskad riktning för att illustrera vindinfallsvinkeln. 
- **Display settings**: Ange om skärmen är 4:3 ("tjocktv"), 16:9 ("widescreen") eller stående 9:16. Vid formaten 16:9 och 9:16 läggs en tredje del av skärmen till som kan konfigureras att visa väderradar eller flygradar eller något annat. Ange i parametern RADAR_URL vad som ska visas här. Hemma kör vi den lokala FR24-mottagarens radarbild eller snarare tar1090.
- **Panel carousel**: Vill du visa växelvis flera tjänster på informationssidan lägger du till ytterligare URL:er här. [Windy har en embed-funktion](https://embed.windy.com/config/map) där du kan ta fram en lokal väderradar att lägga in. Flera adresser separeras med kommatecken. Det är bara tjänster utöver den du angav i RADAR_URL som du lägger till här.
- **Carousel settings**: Om du vill att det ska visas fler sidor på skärmen än bara flyginformationssidan kan du lägga till dem här. Carousel enabled behöver sättas till *true*. Ställ in intervall mellan byten i sekunder. Lägg till vilka sidor som ska hämtas (url:er kommaseparerade) i den ordning som de ska visas.

## Begränsningar
- Koden filtrerar flygplan med SE-registrering, andra objekt visas inte i listan.
- Vid skolning med elev visas den som har gjort bokningen, inte elevens namn.
- Bokningar hämtas 3 dagar bakåt i tiden (och 3 dagar framåt) men detta ska verifieras, behövs eventuellt inte längre än innevarande dag.
- Lite osäkert hur visningen blir om man har en bredare skärm men bara vill visa bokningar/väder och väljer format 4:3. Sidan ska nu vara responsiv men detta har inte testats.

Boka & flyg! :-)
