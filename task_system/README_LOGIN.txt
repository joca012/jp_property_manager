LOGIN MODUL - Task System

Urađeno u ovoj verziji:
- Login zaštita je centralno ugrađena u config.php.
- Ne moraš ručno da dodaješ include "auth.php" u svaki fajl.
- Svi fajlovi koji već imaju include "config.php" automatski su zaštićeni.
- login.php, logout.php i set_admin_password.php su izuzeti iz zaštite.
- Ovo je samo zaštitni login sloj. Još se NE filtriraju taskovi po user_id i još se NE koriste korisničke kategorije.

FAJLOVI:
- config.php               centralna zaštita + konekcija na bazu
- login.php                forma za prijavu
- logout.php               odjava
- auth.php                 ostavljen zbog kompatibilnosti, nije obavezan
- set_admin_password.php   privremeni fajl za podešavanje admin lozinke
- .htaccess                osnovna zaštita Apache foldera

VAŽNO ZA SERVER:
U config.php moraš podesiti podatke za bazu:

$host = "localhost";
$user = "SERVER_DB_USER";
$pass = "SERVER_DB_PASSWORD";
$db   = "SERVER_DB_NAME";

PODEŠAVANJE ADMIN LOZINKE:
1. Uploaduj projekat na server.
2. Otvori:
   https://tvoj-domen/set_admin_password.php
3. Unesi admin lozinku.
4. Kada dobiješ poruku da je lozinka podešena, ODMAH obriši:
   set_admin_password.php

LOGIN:
Korisničko ime:
admin

Lozinka:
ona koju postaviš preko set_admin_password.php

NAPOMENE:
- Baza već treba da ima tabelu users i admin korisnika.
- Ako password_hash u users tabeli stoji TEMP, login neće raditi dok ne pokreneš set_admin_password.php.
- Pošto je zaštita u config.php, svaki novi fajl koji bude uključivao config.php biće automatski zaštićen.
- Za sada ne menjamo SQL upite i ne dodajemo WHERE user_id = ... u postojeće fajlove.
