<?php
declare(strict_types=1);

/**
 * Partidos integrantes de los gobiernos nacionales de la UE desde 2015.
 * Los apoyos parlamentarios externos no se incluyen. Los periodos sin un
 * ejecutivo partidista se identifican como provisionales o técnicos.
 */
return [
    'alemania' => ['fuente' => 'https://www.bundesregierung.de/breg-en/federal-government/federal-chancellors-since-1949', 'periodos' => [
        ['desde'=>'2015-01-01','hasta'=>'2021-12-08','partidos'=>'CDU/CSU + SPD','color'=>'#6d28d9'],
        ['desde'=>'2021-12-08','hasta'=>'2024-11-07','partidos'=>'SPD + Verdes + FDP','color'=>'#7c3aed'],
        ['desde'=>'2024-11-07','hasta'=>'2025-05-06','partidos'=>'SPD + Verdes','color'=>'#dc2626'],
        ['desde'=>'2025-05-06','hasta'=>null,'partidos'=>'CDU/CSU + SPD','color'=>'#6d28d9'],
    ]],
    'austria' => ['fuente' => 'https://www.bundeskanzleramt.gv.at/en/federal-chancellery/history.html', 'periodos' => [
        ['desde'=>'2015-01-01','hasta'=>'2017-12-18','partidos'=>'SPÖ + ÖVP','color'=>'#7c3aed'],
        ['desde'=>'2017-12-18','hasta'=>'2019-05-28','partidos'=>'ÖVP + FPÖ','color'=>'#1d4ed8'],
        ['desde'=>'2019-05-28','hasta'=>'2020-01-07','partidos'=>'Gobierno provisional','color'=>'#64748b'],
        ['desde'=>'2020-01-07','hasta'=>'2025-03-03','partidos'=>'ÖVP + Verdes','color'=>'#059669'],
        ['desde'=>'2025-03-03','hasta'=>null,'partidos'=>'ÖVP + SPÖ + NEOS','color'=>'#7c3aed'],
    ]],
    'belgica' => ['fuente' => 'https://www.belgium.be/en/about_belgium/government/federal_authorities/federal_government', 'periodos' => [
        ['desde'=>'2015-01-01','hasta'=>'2018-12-09','partidos'=>'N-VA + MR + CD&V + Open Vld','color'=>'#6d28d9'],
        ['desde'=>'2018-12-09','hasta'=>'2020-10-01','partidos'=>'MR + CD&V + Open Vld','color'=>'#2563eb'],
        ['desde'=>'2020-10-01','hasta'=>'2025-02-03','partidos'=>'PS + MR + CD&V + Open Vld + Vooruit + Ecolo + Groen','color'=>'#7c3aed'],
        ['desde'=>'2025-02-03','hasta'=>null,'partidos'=>'N-VA + MR + CD&V + Vooruit + Les Engagés','color'=>'#6d28d9'],
    ]],
    'bulgaria' => ['fuente' => 'https://www.government.bg/en/Cabinet', 'periodos' => [
        ['desde'=>'2015-01-01','hasta'=>'2017-01-27','partidos'=>'GERB + Bloque Reformista + ABV','color'=>'#2563eb'],
        ['desde'=>'2017-01-27','hasta'=>'2017-05-04','partidos'=>'Gobierno provisional','color'=>'#64748b'],
        ['desde'=>'2017-05-04','hasta'=>'2021-05-12','partidos'=>'GERB + Patriotas Unidos','color'=>'#1d4ed8'],
        ['desde'=>'2021-05-12','hasta'=>'2021-12-13','partidos'=>'Gobiernos provisionales','color'=>'#64748b'],
        ['desde'=>'2021-12-13','hasta'=>'2022-08-02','partidos'=>'PP + BSP + ITN + DB','color'=>'#7c3aed'],
        ['desde'=>'2022-08-02','hasta'=>'2023-06-06','partidos'=>'Gobierno provisional','color'=>'#64748b'],
        ['desde'=>'2023-06-06','hasta'=>'2024-04-09','partidos'=>'PP-DB + GERB-SDS','color'=>'#7c3aed'],
        ['desde'=>'2024-04-09','hasta'=>'2025-01-16','partidos'=>'Gobiernos provisionales','color'=>'#64748b'],
        ['desde'=>'2025-01-16','hasta'=>'2026-05-08','partidos'=>'GERB-SDS + BSP + ITN','color'=>'#6d28d9'],
        ['desde'=>'2026-05-08','hasta'=>null,'partidos'=>'Gobierno Radev','color'=>'#64748b'],
    ]],
    'chipre' => ['fuente' => 'https://www.presidency.gov.cy/cypresidency/cypresidency.nsf/government_en/government_en', 'periodos' => [
        ['desde'=>'2015-01-01','hasta'=>'2023-02-28','partidos'=>'Gobierno Anastasiades (DISY)','color'=>'#2563eb'],
        ['desde'=>'2023-02-28','hasta'=>null,'partidos'=>'Gobierno Christodoulides (ind. + DIKO/DIPA/EDEK)','color'=>'#7c3aed'],
    ]],
    'croacia' => ['fuente' => 'https://vlada.gov.hr/about-the-government/previous-governments/14968', 'periodos' => [
        ['desde'=>'2015-01-01','hasta'=>'2016-01-22','partidos'=>'SDP + HNS + IDS + HSU','color'=>'#dc2626'],
        ['desde'=>'2016-01-22','hasta'=>'2017-06-09','partidos'=>'HDZ + MOST','color'=>'#2563eb'],
        ['desde'=>'2017-06-09','hasta'=>'2024-05-17','partidos'=>'HDZ + HNS','color'=>'#1d4ed8'],
        ['desde'=>'2024-05-17','hasta'=>null,'partidos'=>'HDZ + DP','color'=>'#1d4ed8'],
    ]],
    'dinamarca' => ['fuente' => 'https://english.stm.dk/the-prime-minister/prime-ministers-since-1848/', 'periodos' => [
        ['desde'=>'2015-01-01','hasta'=>'2015-06-28','partidos'=>'Socialdemokratiet + RV + SF','color'=>'#dc2626'],
        ['desde'=>'2015-06-28','hasta'=>'2016-11-28','partidos'=>'Venstre','color'=>'#2563eb'],
        ['desde'=>'2016-11-28','hasta'=>'2019-06-27','partidos'=>'Venstre + LA + KF','color'=>'#1d4ed8'],
        ['desde'=>'2019-06-27','hasta'=>'2022-12-15','partidos'=>'Socialdemokratiet','color'=>'#dc2626'],
        ['desde'=>'2022-12-15','hasta'=>null,'partidos'=>'Socialdemokratiet + Venstre + Moderaterne','color'=>'#7c3aed'],
    ]],
    'eslovaquia' => ['fuente' => 'https://www.vlada.gov.sk/government-of-the-slovak-republic/', 'periodos' => [
        ['desde'=>'2015-01-01','hasta'=>'2016-03-23','partidos'=>'Smer-SD','color'=>'#dc2626'],
        ['desde'=>'2016-03-23','hasta'=>'2020-03-21','partidos'=>'Smer-SD + SNS + Most-Híd','color'=>'#7c3aed'],
        ['desde'=>'2020-03-21','hasta'=>'2023-05-15','partidos'=>'OĽaNO + Sme Rodina + SaS + Za ľudí','color'=>'#6d28d9'],
        ['desde'=>'2023-05-15','hasta'=>'2023-10-25','partidos'=>'Gobierno técnico','color'=>'#64748b'],
        ['desde'=>'2023-10-25','hasta'=>null,'partidos'=>'Smer-SD + Hlas-SD + SNS','color'=>'#7c3aed'],
    ]],
    'eslovenia' => ['fuente' => 'https://www.gov.si/en/state-authorities/government/about-the-government/previous-governments/', 'periodos' => [
        ['desde'=>'2015-01-01','hasta'=>'2018-09-13','partidos'=>'SMC + SD + DeSUS','color'=>'#7c3aed'],
        ['desde'=>'2018-09-13','hasta'=>'2020-03-13','partidos'=>'LMŠ + SD + SMC + SAB + DeSUS','color'=>'#7c3aed'],
        ['desde'=>'2020-03-13','hasta'=>'2022-06-01','partidos'=>'SDS + SMC + NSi + DeSUS','color'=>'#1d4ed8'],
        ['desde'=>'2022-06-01','hasta'=>'2026-06-04','partidos'=>'Gibanje Svoboda + SD + Levica','color'=>'#dc2626'],
        ['desde'=>'2026-06-04','hasta'=>null,'partidos'=>'SDS + NSi + Demokrati + Fokus + SLS','color'=>'#1d4ed8'],
    ]],
    'espana' => ['fuente' => 'https://www.lamoncloa.gob.es/gobierno/gobiernosporlegislaturas/Paginas/index.aspx', 'periodos' => [
        ['desde'=>'2015-01-01','hasta'=>'2018-06-02','partidos'=>'PP','color'=>'#2563eb'],
        ['desde'=>'2018-06-02','hasta'=>'2020-01-13','partidos'=>'PSOE','color'=>'#ef4444'],
        ['desde'=>'2020-01-13','hasta'=>'2023-11-21','partidos'=>'PSOE + Unidas Podemos','color'=>'#9333ea'],
        ['desde'=>'2023-11-21','hasta'=>null,'partidos'=>'PSOE + Sumar','color'=>'#be123c'],
    ]],
    'estonia' => ['fuente' => 'https://valitsus.ee/en/prime-ministers', 'periodos' => [
        ['desde'=>'2015-01-01','hasta'=>'2016-11-23','partidos'=>'Reform + SDE + IRL','color'=>'#7c3aed'],
        ['desde'=>'2016-11-23','hasta'=>'2019-04-29','partidos'=>'Centre + SDE + Isamaa','color'=>'#7c3aed'],
        ['desde'=>'2019-04-29','hasta'=>'2021-01-26','partidos'=>'Centre + EKRE + Isamaa','color'=>'#1d4ed8'],
        ['desde'=>'2021-01-26','hasta'=>'2022-07-18','partidos'=>'Reform + Centre','color'=>'#6d28d9'],
        ['desde'=>'2022-07-18','hasta'=>'2023-04-17','partidos'=>'Reform + Isamaa + SDE','color'=>'#7c3aed'],
        ['desde'=>'2023-04-17','hasta'=>null,'partidos'=>'Reform + Eesti 200 + SDE','color'=>'#7c3aed'],
    ]],
    'finlandia' => ['fuente' => 'https://valtioneuvosto.fi/en/governments-and-ministers', 'periodos' => [
        ['desde'=>'2015-01-01','hasta'=>'2015-05-29','partidos'=>'Kokoomus + SDP + RKP + KD','color'=>'#7c3aed'],
        ['desde'=>'2015-05-29','hasta'=>'2017-06-13','partidos'=>'Keskusta + Kokoomus + PS','color'=>'#1d4ed8'],
        ['desde'=>'2017-06-13','hasta'=>'2019-06-06','partidos'=>'Keskusta + Kokoomus + Sininen','color'=>'#2563eb'],
        ['desde'=>'2019-06-06','hasta'=>'2023-06-20','partidos'=>'SDP + Keskusta + Verdes + Vasemmisto + RKP','color'=>'#7c3aed'],
        ['desde'=>'2023-06-20','hasta'=>null,'partidos'=>'Kokoomus + PS + RKP + KD','color'=>'#1d4ed8'],
    ]],
    'francia' => ['fuente' => 'https://www.gouvernement.fr/les-anciens-premiers-et-premieres-ministres-de-la-ve-republique', 'periodos' => [
        ['desde'=>'2015-01-01','hasta'=>'2017-05-15','partidos'=>'PS + PRG','color'=>'#dc2626'],
        ['desde'=>'2017-05-15','hasta'=>'2024-09-05','partidos'=>'Renaissance + MoDem + Horizons','color'=>'#7c3aed'],
        ['desde'=>'2024-09-05','hasta'=>null,'partidos'=>'Renaissance + MoDem + Horizons + LR','color'=>'#6d28d9'],
    ]],
    'grecia' => ['fuente' => 'https://www.primeminister.gr/en/the-prime-minister/previous-prime-ministers', 'periodos' => [
        ['desde'=>'2015-01-01','hasta'=>'2015-01-26','partidos'=>'Nueva Democracia + PASOK','color'=>'#2563eb'],
        ['desde'=>'2015-01-26','hasta'=>'2019-07-08','partidos'=>'Syriza + ANEL','color'=>'#7c3aed'],
        ['desde'=>'2019-07-08','hasta'=>null,'partidos'=>'Nueva Democracia','color'=>'#2563eb'],
    ]],
    'hungria' => ['fuente' => 'https://kormany.hu/en/government', 'periodos' => [
        ['desde'=>'2015-01-01','hasta'=>'2026-05-12','partidos'=>'Fidesz + KDNP','color'=>'#f97316'],
        ['desde'=>'2026-05-12','hasta'=>null,'partidos'=>'TISZA','color'=>'#0f766e'],
    ]],
    'irlanda' => ['fuente' => 'https://www.gov.ie/en/department-of-the-taoiseach/collections/former-taoisigh/', 'periodos' => [
        ['desde'=>'2015-01-01','hasta'=>'2016-05-06','partidos'=>'Fine Gael + Labour','color'=>'#6d28d9'],
        ['desde'=>'2016-05-06','hasta'=>'2020-06-27','partidos'=>'Fine Gael + independientes','color'=>'#2563eb'],
        ['desde'=>'2020-06-27','hasta'=>'2025-01-23','partidos'=>'Fianna Fáil + Fine Gael + Greens','color'=>'#7c3aed'],
        ['desde'=>'2025-01-23','hasta'=>null,'partidos'=>'Fianna Fáil + Fine Gael + independientes','color'=>'#6d28d9'],
    ]],
    'italia' => ['fuente' => 'https://www.governo.it/en/government/previous-governments', 'periodos' => [
        ['desde'=>'2015-01-01','hasta'=>'2018-06-01','partidos'=>'PD + NCD/AP','color'=>'#dc2626'],
        ['desde'=>'2018-06-01','hasta'=>'2019-09-05','partidos'=>'M5S + Lega','color'=>'#7c3aed'],
        ['desde'=>'2019-09-05','hasta'=>'2021-02-13','partidos'=>'M5S + PD + LeU + IV','color'=>'#7c3aed'],
        ['desde'=>'2021-02-13','hasta'=>'2022-10-22','partidos'=>'Gobierno de unidad nacional','color'=>'#64748b'],
        ['desde'=>'2022-10-22','hasta'=>null,'partidos'=>'FdI + Lega + FI','color'=>'#1d4ed8'],
    ]],
    'letonia' => ['fuente' => 'https://www.mk.gov.lv/en/prime-ministers-latvia', 'periodos' => [
        ['desde'=>'2015-01-01','hasta'=>'2019-01-23','partidos'=>'Unity + ZZS + NA','color'=>'#6d28d9'],
        ['desde'=>'2019-01-23','hasta'=>'2022-12-14','partidos'=>'New Unity + JKP + AP + NA + KPV','color'=>'#7c3aed'],
        ['desde'=>'2022-12-14','hasta'=>'2023-09-15','partidos'=>'New Unity + United List + NA','color'=>'#6d28d9'],
        ['desde'=>'2023-09-15','hasta'=>'2026-05-28','partidos'=>'New Unity + ZZS + Progressives','color'=>'#7c3aed'],
        ['desde'=>'2026-05-28','hasta'=>null,'partidos'=>'United List + NA + ZZS + New Unity','color'=>'#6d28d9'],
    ]],
    'lituania' => ['fuente' => 'https://lrv.lt/en/about-government/previous-governments/', 'periodos' => [
        ['desde'=>'2015-01-01','hasta'=>'2016-12-13','partidos'=>'LSDP + Labour + Order and Justice','color'=>'#dc2626'],
        ['desde'=>'2016-12-13','hasta'=>'2020-12-11','partidos'=>'LVŽS + LSDP/LSDDP','color'=>'#7c3aed'],
        ['desde'=>'2020-12-11','hasta'=>'2024-12-12','partidos'=>'TS-LKD + Liberal Movement + Freedom','color'=>'#2563eb'],
        ['desde'=>'2024-12-12','hasta'=>null,'partidos'=>'LSDP + DSVL + Dawn of Nemunas','color'=>'#7c3aed'],
    ]],
    'luxemburgo' => ['fuente' => 'https://gouvernement.lu/en/systeme-politique/gouvernements-precedents.html', 'periodos' => [
        ['desde'=>'2015-01-01','hasta'=>'2023-11-17','partidos'=>'DP + LSAP + Verdes','color'=>'#7c3aed'],
        ['desde'=>'2023-11-17','hasta'=>null,'partidos'=>'CSV + DP','color'=>'#2563eb'],
    ]],
    'malta' => ['fuente' => 'https://gov.mt/en/Government/Government%20of%20Malta/Prime%20Ministers%20of%20Malta/Pages/Prime-Ministers-of-Malta.aspx', 'periodos' => [
        ['desde'=>'2015-01-01','hasta'=>null,'partidos'=>'Labour Party','color'=>'#dc2626'],
    ]],
    'paises-bajos' => ['fuente' => 'https://www.government.nl/government/history', 'periodos' => [
        ['desde'=>'2015-01-01','hasta'=>'2017-10-26','partidos'=>'VVD + PvdA','color'=>'#7c3aed'],
        ['desde'=>'2017-10-26','hasta'=>'2024-07-02','partidos'=>'VVD + CDA + D66 + CU','color'=>'#6d28d9'],
        ['desde'=>'2024-07-02','hasta'=>'2025-06-03','partidos'=>'PVV + VVD + NSC + BBB','color'=>'#1d4ed8'],
        ['desde'=>'2025-06-03','hasta'=>'2026-02-23','partidos'=>'VVD + NSC + BBB (provisional)','color'=>'#64748b'],
        ['desde'=>'2026-02-23','hasta'=>null,'partidos'=>'D66 + VVD + CDA','color'=>'#6d28d9'],
    ]],
    'polonia' => ['fuente' => 'https://www.gov.pl/web/primeminister/previous-governments', 'periodos' => [
        ['desde'=>'2015-01-01','hasta'=>'2015-11-16','partidos'=>'PO + PSL','color'=>'#6d28d9'],
        ['desde'=>'2015-11-16','hasta'=>'2023-12-13','partidos'=>'PiS + Solidarna Polska/United Poland','color'=>'#1d4ed8'],
        ['desde'=>'2023-12-13','hasta'=>null,'partidos'=>'KO + Third Way + New Left','color'=>'#7c3aed'],
    ]],
    'portugal' => ['fuente' => 'https://www.portugal.gov.pt/en/gc24/prime-minister/former-prime-ministers', 'periodos' => [
        ['desde'=>'2015-01-01','hasta'=>'2015-11-26','partidos'=>'PSD + CDS-PP','color'=>'#2563eb'],
        ['desde'=>'2015-11-26','hasta'=>'2024-04-02','partidos'=>'PS','color'=>'#dc2626'],
        ['desde'=>'2024-04-02','hasta'=>null,'partidos'=>'PSD + CDS-PP','color'=>'#2563eb'],
    ]],
    'republica-checa' => ['fuente' => 'https://vlada.gov.cz/en/vlada/', 'periodos' => [
        ['desde'=>'2015-01-01','hasta'=>'2017-12-13','partidos'=>'ČSSD + ANO + KDU-ČSL','color'=>'#7c3aed'],
        ['desde'=>'2017-12-13','hasta'=>'2018-06-27','partidos'=>'ANO (minoría)','color'=>'#2563eb'],
        ['desde'=>'2018-06-27','hasta'=>'2021-12-17','partidos'=>'ANO + ČSSD','color'=>'#7c3aed'],
        ['desde'=>'2021-12-17','hasta'=>'2024-10-01','partidos'=>'SPOLU + STAN + Piratas','color'=>'#6d28d9'],
        ['desde'=>'2024-10-01','hasta'=>'2025-12-15','partidos'=>'SPOLU + STAN','color'=>'#2563eb'],
        ['desde'=>'2025-12-15','hasta'=>null,'partidos'=>'ANO + SPD + Motoristé','color'=>'#1d4ed8'],
    ]],
    'rumania' => ['fuente' => 'https://gov.ro/en/government/previous-governments', 'periodos' => [
        ['desde'=>'2015-01-01','hasta'=>'2015-11-17','partidos'=>'PSD + UNPR + ALDE','color'=>'#dc2626'],
        ['desde'=>'2015-11-17','hasta'=>'2017-01-04','partidos'=>'Gobierno técnico','color'=>'#64748b'],
        ['desde'=>'2017-01-04','hasta'=>'2019-11-04','partidos'=>'PSD + ALDE','color'=>'#dc2626'],
        ['desde'=>'2019-11-04','hasta'=>'2020-12-23','partidos'=>'PNL','color'=>'#2563eb'],
        ['desde'=>'2020-12-23','hasta'=>'2021-11-25','partidos'=>'PNL + USR PLUS + UDMR','color'=>'#6d28d9'],
        ['desde'=>'2021-11-25','hasta'=>'2025-06-23','partidos'=>'PNL + PSD + UDMR','color'=>'#7c3aed'],
        ['desde'=>'2025-06-23','hasta'=>null,'partidos'=>'PNL + PSD + USR + UDMR','color'=>'#7c3aed'],
    ]],
    'suecia' => ['fuente' => 'https://www.government.se/government-of-sweden/previous-governments/', 'periodos' => [
        ['desde'=>'2015-01-01','hasta'=>'2021-11-30','partidos'=>'Socialdemokraterna + Verdes','color'=>'#dc2626'],
        ['desde'=>'2021-11-30','hasta'=>'2022-10-18','partidos'=>'Socialdemokraterna','color'=>'#dc2626'],
        ['desde'=>'2022-10-18','hasta'=>null,'partidos'=>'Moderaterna + KD + Liberalerna','color'=>'#2563eb'],
    ]],
];
