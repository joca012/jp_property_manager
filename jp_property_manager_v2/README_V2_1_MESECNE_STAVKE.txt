JP Property Manager v2.1 - finansijski plan po mesecima

U ovom paketu je uvedena nova logika stavki finansijskog plana:
- stavka može biti priliv ili odliv;
- može biti mesečna, jednokratna ili godišnja;
- može se obračunavati fiksno, po posebnom delu, po garažnom mestu ili po m2;
- ima mesec početka i mesec prestanka;
- finansijski plan prikazuje vremensku osu po mesecima.

SQL fajl je dodat kao ručna migracija, ali PHP funkcija ensure_finansijski_plan_schema() pokušava automatski da doda kolone ako ne postoje.
