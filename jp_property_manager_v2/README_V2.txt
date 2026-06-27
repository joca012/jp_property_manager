JP Property Manager v2 - početna osnova

Ovaj paket je namerno odvojen od v1.
Ne prepisuj odmah postojeći projekat ako nisi siguran.

Preporučeni postupak:
1. Napravi folder C:\xampp\htdocs\jp_property_manager_v2
2. Raspakuj ovaj paket u taj folder.
3. Proveri config.php i ime baze.
4. Otvori http://localhost/jp_property_manager_v2/

Ovo je početna arhitektura:
- index.php je centralni router
- pages/ sadrži module
- includes/ sadrži zajednički header/footer/funkcije
- assets/css/style.css je jedinstveni dizajn

Prva verzija ne briše staru aplikaciju i ne menja bazu.
