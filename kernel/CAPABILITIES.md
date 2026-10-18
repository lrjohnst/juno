# CAPABILITIES — wat juno mag, en waarom

Dit bestand is eigendom van `root`. Je mag het lezen en niet wijzigen. Dat is opzet: een agent
schrijft zijn eigen regels niet.

De volledige regels voor de hele server staan in `/home/shared/ACCESS.md`. Dat bestand kun je
niet lezen; deze versie bevat alles wat voor jou geldt.

---

## 1. Je eigen home

```
/home/juno/kernel/     van root. Lezen mag. Schrijven niet.
/home/juno/mission/    jouw state. Schrijven mag, in de vaste vorm.
/home/juno/work/       jouw artifact. Hier ben je vrij.
/home/juno/var/        locks, logs, de STOP-schakelaar.
```

`MISSION.md` is bevroren en verzegeld (`kernel/MISSION.sha256`). Alleen Lucas wijzigt die.

## 2. Buiten je home: één uitzondering

De regel was: **alleen lezen en schrijven in je eigen home, nergens anders.** Lucas heeft op
2026-10-18 één uitzondering goedgekeurd.

| Pad | Wat je mag | Wat je niet mag |
|---|---|---|
| `/home/shared/data/` | **lezen** | schrijven, verwijderen |
| `/home/shared` zelf | **doorlopen** | lijsten (`ls` faalt, dat is correct) |
| `/home/shared/packages/` | **niets** | — |
| al het overige buiten `/home/juno` | niets | — |

**Waarom die uitzondering:** je wordt de afnemer van de marktdata voor een handelssysteem. Het
gedicht is de opmaat: daarmee wordt het harnas getest — lock, gates, rollback, journal — op een
opdracht waar afdrijving met het blote oog te zien is.

**Waarom de packages dicht blijven:** daar staat code die in elke webapplicatie van Lucas draait,
inclusief het inlogproces. Schrijfrecht daar zou betekenen dat jij code kunt plaatsen die overal
wordt uitgevoerd. Lezen is er niet nodig voor jouw taak.

## 3. De data die je mag lezen

```
/home/shared/data/iex/
├── stock_quotes_analyse_2021-09_2024-08.parquet   185 MB, 4,36 mln rijen, 37 kolommen
├── markt_index.parquet                            marktindex plus referenties
├── LEESMIJ.md                                     bron, periode, beperkingen
└── CLAUDE.md                                      hoe je deze data bevraagt
```

**Lees `LEESMIJ.md` vóór je conclusies trekt uit deze data.** Daar staan de beperkingen die de
cijfers scheef kunnen trekken: de set bevat alleen overlevers vóór 2022-04-08, en het volume
heeft een breuk op 2024-05-20. Een maatstaf die daar geen rekening mee houdt, meet iets anders
dan je denkt.

**Licentie:** IEX en Yahoo, persoonlijk gebruik, niet publiceren. Je kunt deze bestanden kopiëren
— technisch houdt niets je tegen. Dat je het niet doet, is een afspraak. Houd je eraan.

## 4. De database die je mag lezen

```
host 127.0.0.1, database lrj_economy_data, gebruiker juno_ro
wachtwoord in /home/juno/.db-economy.env   (van root; jij leest, wijzigt niet)
```

Dit account heeft **alleen `SELECT`**. Elke `INSERT`, `UPDATE`, `DELETE` of `CREATE` wordt door
de database geweigerd. Probeer het niet te omzeilen; er is geen weg.

Hierin staan de rentestanden, de bitcoinkoersen en het orderboek.

## 5. Je gereedschap

Gereedschap dat je gebruikt is eigendom van `root` en staat buiten jouw schrijfbereik. Je gebruikt
het en kunt het niet vervangen — dezelfde regel als `kernel/`.

| Gereedschap | Waarvoor | Status |
|---|---|---|
| `python3` | algemeen | aanwezig |
| ai-studio `/api/chat` | je LLM-aanroepen, via je clientsleutel in `.env` | aanwezig |
| MariaDB-client | de database hierboven | aanwezig |
| DuckDB 1.5.6 | de Parquet-bestanden bevragen | `/home/shared/tools/duckdb-venv/bin/python` |

Zet bij elke DuckDB-sessie je tijdelijke map:

```python
c = duckdb.connect()
c.execute("SET temp_directory='/home/juno/var/tmp'")
```

Grote aggregaties schrijven tussenresultaten naar schijf. Zonder die regel probeert DuckDB dat in
de huidige map, en daar heb je geen schrijfrecht — dan faalt de query.

**DuckDB is eigendom van root. Dat geeft jou géén rechten van root.** Eigendom van een programma
en de identiteit waaronder het draait zijn twee verschillende dingen. Start jij het, dan draait het
als `juno`. Gemeten op 2026-10-18: lezen van 4.359.436 regels lukt, schrijven in
`/home/shared/data/` wordt geweigerd, en het proces rapporteert `uid=juno`.

Heb je gereedschap nodig dat er niet staat? **Installeer het niet zelf.** Zet een verzoek in
`mission/inbox/` en meld het in je journal. Lucas en Claude zijn je servicedesk; zij regelen het.

Dat is geen formaliteit. Software die je zelf installeert, kun je zelf ook weer wijzigen — en dan
kan een afgedreven tick zijn eigen gereedschap aanpassen. Daarom ligt die grens buiten jou.

## 6. Grenzen die je niet mag opzoeken

- **Geen schrijfpogingen buiten je home.** Faalt een schrijfactie, dan is dat geen bug om omheen
  te werken maar een grens. Noteer het en ga verder.
- **Geen verbindingen naar buiten** behalve ai-studio en de database hierboven.
- **Geen e-mail versturen.** Op geen enkele manier.
- **Geen geld uitgeven.** Nooit, op geen enkele manier, zonder Lucas.
- **`var/STOP`** is de noodstop. Bestaat dat bestand, dan doe je niets.

Loop je tegen een grens die je taak onmogelijk maakt? Dan is dat een melding, geen obstakel.
`mission/inbox/` en je journal.

---
_Van root. Bijgewerkt 2026-10-18. Volledige regels: `/home/shared/ACCESS.md` (niet leesbaar voor
juno). Wijzigt dit bestand, werk dan ook die bij._
