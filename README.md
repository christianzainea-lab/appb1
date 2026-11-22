# Echipa de voluntari – aplicație pentru biserici

Aplicație web simplă, în limba română, pentru echipa de voluntari a unei biserici:

- **Programul** de joi și duminică: cine slujește, pe ce poziție, confirmări și „Nu pot ajunge”
- **Biblioteca de cântări**: tonuri, cărți de cântări, etichete, înregistrări YouTube/MP3, acorduri, versuri
- **Import de cântări** din Excel (.xlsx) sau CSV, cu model de descărcat
- **Ordinea serviciului**, notițe pentru echipă, fișiere (predică, anunțuri), mesaj gata de trimis pe WhatsApp
- **Zile indisponibile**, calendar personal (Google / iPhone), ghid cu imagini pentru voluntari

PHP 8.1+ și MySQL, fără biblioteci externe. Se instalează pe orice hosting cu cPanel și pornește goală (fără date).

## Descărcare

Ia arhiva **`echipa-voluntari-v1.5.0.zip`** din [Releases](../../releases/latest).

Merge și butonul verde **Code → Download ZIP** de pe această pagină. Atunci arhiva are un dosar în plus
(`appb1-main`): încarcă pe server **conținutul** acelui dosar, nu dosarul în sine.

## Instalare

Pașii completi sunt în [`INSTALARE.txt`](INSTALARE.txt). Pe scurt:

1. În cPanel: un subdomeniu cu HTTPS, PHP 8.2, o bază de date MySQL goală.
2. Încarcă arhiva în dosarul subdomeniului și dezarhiveaz-o (File Manager → Extract).
3. Deschide `https://subdomeniul-tău/install.php` și completează formularul.
4. Intră ca administrator: logo, poziții, șabloane, cărți de cântări, apoi invită echipa.

Aplicația nu primește actualizări din acest depozit.
