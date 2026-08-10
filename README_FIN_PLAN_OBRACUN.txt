Finansijski plan - dopuna logike obračuna

Urađeno:
- Učestalost stavke je odvojena od načina obračuna.
- Učestalost: jednokratno, mesečno, godišnje.
- Način obračuna: fiksno za zgradu, po posebnom delu, po garažnom mestu, po m² posebnih delova, po m² garažnog prostora.
- Investiciono održavanje je razdvojeno na m² posebnih delova i m² garažnog prostora.
- Zadržana je kompatibilnost sa postojećim podacima.

SQL:
- Ako ensure_finansijski_plan_schema() radi pri otvaranju stranice, kolone će biti dodate automatski.
- Može se ručno pokrenuti i sql/finansijski_plan_stavke_obracun_patch.sql.
