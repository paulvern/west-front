# Report di Verifica Coerenza Regole - Fronte Occidentale v0.9.5

**Data analisi:** 2026-07-15  
**Versione manuale:** v0.9.5  
**Analista:** Kiro Architecture Mode

---

## 1. PROBLEMA CRITICO: File Mancanti

### 1.1 Inconsistenza sections.json vs file esistenti

**PROBLEMA:** Il file [`sections.json`](../data/sections.json) dichiara file che NON esistono nella cartella sections.

#### File dichiarati ma MANCANTI:

1. **`sections/00-copertina.html`** - Dichiarato alla riga 7 di sections.json
   - Esiste invece: `sections/copertina.html` (senza prefisso numerico)
   - **AZIONE RICHIESTA:** Rinominare il file o correggere sections.json

2. **`sections/22-cronologia-isonzo.html`** - Dichiarato alla riga 117 di sections.json
   - File NON trovato nella directory sections/
   - **AZIONE RICHIESTA:** Creare il file mancante o rimuovere la voce da sections.json

#### Impatto:
- L'applicazione web fallirà nel caricamento della sezione "Copertina"
- L'applicazione web fallirà nel caricamento della sezione "Trafili storici: Battaglie dell'Isonzo"
- Il pulsante "Carica tutto" mostrerà errori

#### Raccomandazione:
**ALTA PRIORITÀ** - Correggere immediatamente prima di qualsiasi distribuzione.

---

## 2. COERENZA DEI COSTI (PL)

### 2.1 Costi Coerenti ✓

Le seguenti azioni hanno costi coerenti in tutti i file analizzati:

| Azione | Costo PL | File di riferimento |
|--------|----------|---------------------|
| Bombardamento preparatorio | 3 PL | [`03-catalogo-azioni.html:186`](../sections/03-catalogo-azioni.html:186), [`07-logistica-riserve-rimpiazzi.html:35`](../sections/07-logistica-riserve-rimpiazzi.html:35), [`10-artiglieria-mg.html:66`](../sections/10-artiglieria-mg.html:66) |
| Sbarramento mobile | 2 PL | [`03-catalogo-azioni.html:195`](../sections/03-catalogo-azioni.html:195), [`07-logistica-riserve-rimpiazzi.html:36`](../sections/07-logistica-riserve-rimpiazzi.html:36), [`10-artiglieria-mg.html:72`](../sections/10-artiglieria-mg.html:72) |
| Controbatteria | 2 PL | [`03-catalogo-azioni.html:204`](../sections/03-catalogo-azioni.html:204), [`07-logistica-riserve-rimpiazzi.html:37`](../sections/07-logistica-riserve-rimpiazzi.html:37), [`10-artiglieria-mg.html:78`](../sections/10-artiglieria-mg.html:78) |
| Tiro di disturbo | 1 PL | [`03-catalogo-azioni.html:213`](../sections/03-catalogo-azioni.html:213), [`07-logistica-riserve-rimpiazzi.html:38`](../sections/07-logistica-riserve-rimpiazzi.html:38), [`10-artiglieria-mg.html:84`](../sections/10-artiglieria-mg.html:84) |
| Gas | 4 PL | [`03-catalogo-azioni.html:311`](../sections/03-catalogo-azioni.html:311), [`07-logistica-riserve-rimpiazzi.html:39`](../sections/07-logistica-riserve-rimpiazzi.html:39), [`11-gas.html:30`](../sections/11-gas.html:30) |
| Ricognizione aerea | 1 PL | [`07-logistica-riserve-rimpiazzi.html:40`](../sections/07-logistica-riserve-rimpiazzi.html:40), [`12-aviazione.html:68`](../sections/12-aviazione.html:68) |
| Osservazione artiglieria | 1 PL | [`07-logistica-riserve-rimpiazzi.html:41`](../sections/07-logistica-riserve-rimpiazzi.html:41), [`12-aviazione.html:75`](../sections/12-aviazione.html:75) |
| Missione caccia | 1 PL | [`07-logistica-riserve-rimpiazzi.html:42`](../sections/07-logistica-riserve-rimpiazzi.html:42), [`12-aviazione.html:82-96`](../sections/12-aviazione.html:82) |
| Interdizione aerea | 2 PL | [`07-logistica-riserve-rimpiazzi.html:43`](../sections/07-logistica-riserve-rimpiazzi.html:43), [`12-aviazione.html:104`](../sections/12-aviazione.html:104) |
| Entrata riserva | 2 PL | [`03-catalogo-azioni.html:43`](../sections/03-catalogo-azioni.html:43), [`07-logistica-riserve-rimpiazzi.html:44`](../sections/07-logistica-riserve-rimpiazzi.html:44), [`07-logistica-riserve-rimpiazzi.html:71`](../sections/07-logistica-riserve-rimpiazzi.html:71) |
| Movimento operativo | 1 PL | [`03-catalogo-azioni.html:110`](../sections/03-catalogo-azioni.html:110), [`07-logistica-riserve-rimpiazzi.html:45`](../sections/07-logistica-riserve-rimpiazzi.html:45), [`09-movimento.html:26`](../sections/09-movimento.html:26) |
| Rimpiazzi (1.000 uomini) | 1 PL | [`03-catalogo-azioni.html:52`](../sections/03-catalogo-azioni.html:52), [`07-logistica-riserve-rimpiazzi.html:46`](../sections/07-logistica-riserve-rimpiazzi.html:46) |
| Spostare MG | 1 PL | [`03-catalogo-azioni.html:128`](../sections/03-catalogo-azioni.html:128), [`07-logistica-riserve-rimpiazzi.html:47`](../sections/07-logistica-riserve-rimpiazzi.html:47), [`10-artiglieria-mg.html:42`](../sections/10-artiglieria-mg.html:42) |
| Costruire/riparare fortificazione | 2 PL | [`03-catalogo-azioni.html:61`](../sections/03-catalogo-azioni.html:61), [`07-logistica-riserve-rimpiazzi.html:48`](../sections/07-logistica-riserve-rimpiazzi.html:48), [`15-fortificazioni-rifornimento.html:58`](../sections/15-fortificazioni-rifornimento.html:58) |
| Recupero extra Fatica | 1 PL | [`03-catalogo-azioni.html:70`](../sections/03-catalogo-azioni.html:70), [`07-logistica-riserve-rimpiazzi.html:49`](../sections/07-logistica-riserve-rimpiazzi.html:49), [`15-fortificazioni-rifornimento.html:144`](../sections/15-fortificazioni-rifornimento.html:144) |

**Conclusione Costi:** ✓ **COERENTI** - Nessuna discrepanza trovata.

---

## 3. COERENZA DEI MODIFICATORI AL VT

### 3.1 Modificatori al VT in Combattimento ✓

Confronto tra [`06-divisioni-morale-fatica.html`](../sections/06-divisioni-morale-fatica.html) e [`13-combattimento.html`](../sections/13-combattimento.html):

| Condizione | Effetto | Fonte 1 | Fonte 2 |
|------------|---------|---------|---------|
| Morale 5 in attacco preparato | VT -1 (migliorato) | [`06-divisioni-morale-fatica.html:105`](../sections/06-divisioni-morale-fatica.html:105) | [`13-combattimento.html:43`](../sections/13-combattimento.html:43) |
| Morale 2 in attacco | VT +1 (peggiorato) | [`06-divisioni-morale-fatica.html:120`](../sections/06-divisioni-morale-fatica.html:120) | [`13-combattimento.html:44`](../sections/13-combattimento.html:44) |
| Morale 1 | Non può attaccare | [`06-divisioni-morale-fatica.html:125`](../sections/06-divisioni-morale-fatica.html:125) | [`13-combattimento.html:24`](../sections/13-combattimento.html:24) |
| Fatica 3 | Non può muovere e attaccare | [`06-divisioni-morale-fatica.html:146`](../sections/06-divisioni-morale-fatica.html:146) | [`09-movimento.html:53`](../sections/09-movimento.html:53) |
| Fatica 4 in attacco | VT +1 (peggiorato) | [`06-divisioni-morale-fatica.html:150`](../sections/06-divisioni-morale-fatica.html:150) | [`13-combattimento.html:45`](../sections/13-combattimento.html:45) |
| Fatica 5 | Non può attaccare | [`06-divisioni-morale-fatica.html:154`](../sections/06-divisioni-morale-fatica.html:154) | [`13-combattimento.html:24`](../sections/13-combattimento.html:24) |
| Linea in Disordine da gas | Difensore VT +1 | [`11-gas.html:124`](../sections/11-gas.html:124) | [`13-combattimento.html:47`](../sections/13-combattimento.html:47) |

**Conclusione Modificatori VT:** ✓ **COERENTI** - Tutti i modificatori corrispondono perfettamente.

---

## 4. COERENZA DELLE TABELLE DI RISOLUZIONE

### 4.1 Test Morale ✓

Tabella presente in [`06-divisioni-morale-fatica.html:166-187`](../sections/06-divisioni-morale-fatica.html:166):

```
Formula: 1d12 + Morale

5 o meno  → Fallimento grave: -1 Morale, +1 Fatica
6-8       → Fallimento: +1 Fatica
9-12      → Successo: nessun effetto
13+       → Successo pieno: nessun effetto + ignora primo +1 Fatica in difesa
```

**Verifica:** Regola citata coerentemente in:
- [`03-catalogo-azioni.html:322`](../sections/03-catalogo-azioni.html:322)
- [`06-divisioni-morale-fatica.html:159`](../sections/06-divisioni-morale-fatica.html:159)
- [`11-gas.html:119`](../sections/11-gas.html:119)

✓ **COERENTE**

### 4.2 Test di Resa ✓

Tabella presente in [`14-ritirate-rotta-resa.html:167-200`](../sections/14-ritirate-rotta-resa.html:167):

```
Formula: 1d12 + Morale - Fatica + modificatori

8+   → Resiste: Morale 1, Fatica 5
5-7  → Rotta: -1.000 uomini, ritirata caotica
2-4  → Resa parziale: -2.000 uomini
1 o meno → Resa completa
```

Modificatori citati:
- Guardia/Veterana: +1
- Reclute/Milizia: -1
- Isolata: -2
- Nessuna via ritirata: -2
- Forte/Fortificazione 4: +1
- Gas questo turno: -1
- 3.000+ perdite questo turno: -1

**Verifica:** Coerente con [`03-catalogo-azioni.html:349`](../sections/03-catalogo-azioni.html:349)

✓ **COERENTE**

### 4.3 Risultati Combattimento ✓

Tabella presente in [`13-combattimento.html:69-94`](../sections/13-combattimento.html:69):

```
Differenza successi a favore attaccante:

0 o meno  → Attacco respinto
1-2       → Perdite reciproche, nessun avanzamento
3-4       → Combattimento in trincea
5-6       → Combattimento in trincea con vantaggio attaccante
7+        → Sfondamento locale
```

✓ **COERENTE** con [`03-catalogo-azioni.html:171`](../sections/03-catalogo-azioni.html:171)

### 4.4 Perdite per Intensità ✓

Tabella presente in [`13-combattimento.html:162-170`](../sections/13-combattimento.html:162):

```
Bassa intensità: ogni 4 successi → 1.000 perdite
Media intensità: ogni 3 successi → 1.000 perdite
Alta intensità: ogni 2 successi → 1.000 perdite
```

✓ **COERENTE** - Regola univoca, nessuna contraddizione trovata.

---

## 5. COERENZA DELLA SEQUENZA DEL TURNO

### 5.1 Sequenza in 14 Fasi ✓

Confronto [`04-sequenza-turno.html:15-106`](../sections/04-sequenza-turno.html:15) con riferimenti nei capitoli:

| Fase | Nome | File di riferimento |
|------|------|---------------------|
| 1 | Meteo e Vento | [`04-sequenza-turno.html:24-26`](../sections/04-sequenza-turno.html:24), [`11-gas.html:50`](../sections/11-gas.html:50) ✓ |
| 2 | Rinforzi | [`04-sequenza-turno.html:29-32`](../sections/04-sequenza-turno.html:29), [`07-logistica-riserve-rimpiazzi.html:66`](../sections/07-logistica-riserve-rimpiazzi.html:66) ✓ |
| 3 | Logistica | [`04-sequenza-turno.html:34-38`](../sections/04-sequenza-turno.html:34), [`07-logistica-riserve-rimpiazzi.html:15-26`](../sections/07-logistica-riserve-rimpiazzi.html:15) ✓ |
| 4 | Ordini | [`04-sequenza-turno.html:40-44`](../sections/04-sequenza-turno.html:40), [`08-ordini-iniziativa.html:1-49`](../sections/08-ordini-iniziativa.html:1) ✓ |
| 5 | Iniziativa Operativa | [`04-sequenza-turno.html:46-50`](../sections/04-sequenza-turno.html:46), [`08-ordini-iniziativa.html:57-80`](../sections/08-ordini-iniziativa.html:57) ✓ |
| 6 | Aviazione | [`04-sequenza-turno.html:52-56`](../sections/04-sequenza-turno.html:52), [`12-aviazione.html:42-53`](../sections/12-aviazione.html:42) ✓ |
| 7 | Artiglieria e Gas | [`04-sequenza-turno.html:58-62`](../sections/04-sequenza-turno.html:58), [`10-artiglieria-mg.html:1`](../sections/10-artiglieria-mg.html:1), [`11-gas.html:1`](../sections/11-gas.html:1) ✓ |
| 8 | Movimento | [`04-sequenza-turno.html:64-68`](../sections/04-sequenza-turno.html:64), [`09-movimento.html:1`](../sections/09-movimento.html:1) ✓ |
| 9 | Fuoco difensivo | [`04-sequenza-turno.html:70-74`](../sections/04-sequenza-turno.html:70), [`13-combattimento.html:50-61`](../sections/13-combattimento.html:50) ✓ |
| 10 | Combattimento | [`04-sequenza-turno.html:76-80`](../sections/04-sequenza-turno.html:76), [`13-combattimento.html:1`](../sections/13-combattimento.html:1) ✓ |
| 11 | Ritirate, avanzate | [`04-sequenza-turno.html:82-86`](../sections/04-sequenza-turno.html:82), [`14-ritirate-rotta-resa.html:1`](../sections/14-ritirate-rotta-resa.html:1) ✓ |
| 12 | Isolamento e rifornimento | [`04-sequenza-turno.html:88-92`](../sections/04-sequenza-turno.html:88), [`15-fortificazioni-rifornimento.html:98-114`](../sections/15-fortificazioni-rifornimento.html:98) ✓ |
| 13 | Recupero | [`04-sequenza-turno.html:94-98`](../sections/04-sequenza-turno.html:94), [`15-fortificazioni-rifornimento.html:116-147`](../sections/15-fortificazioni-rifornimento.html:116) ✓ |
| 14 | Vittoria e fine turno | [`04-sequenza-turno.html:100-104`](../sections/04-sequenza-turno.html:100), [`03-catalogo-azioni.html:354-360`](../sections/03-catalogo-azioni.html:354) ✓ |

**Conclusione Sequenza:** ✓ **COERENTE** - Tutti i riferimenti sono allineati.

---

## 6. PRINCIPI GENERALI E REGOLE D'ORO

### 6.1 Coerenza del Principio Guida ✓

**Principio dalla Copertina** [`copertina.html:15-20`](../sections/copertina.html:15):
> "Ogni azione del gioco deve avere un momento preciso, un costo, una procedura, condizioni di fattibilità e un effetto chiaro. Se un'azione non è ordinata quando richiesto o non è pagata quando richiesto, non può essere eseguita."

**Principi Generali** [`02-principi-generali.html:10-19`](../sections/02-principi-generali.html:10):
> Cinque domande:
> 1. Quando si dichiara?
> 2. Quanto costa?
> 3. È fattibile?
> 4. Come si risolve?
> 5. Qual è l'effetto?

**Regola d'oro** [`02-principi-generali.html:21-25`](../sections/02-principi-generali.html:21):
> "Se un'azione ha costo in PL e non viene pagata nella Fase Logistica, l'azione non può essere eseguita. Se richiede un ordine e non viene scritta nella Fase Ordini, l'azione non può essere eseguita."

✓ **PERFETTAMENTE COERENTE** - Il sistema è costruito attorno a questi principi.

---

## 7. VALORI NUMERICI E DADI

### 7.1 Sistema di Dado ✓

**Dado standard:** d12 (confermato in [`copertina.html:23`](../sections/copertina.html:23))

**Valori Tiro (VT):**
- Guardia: 7+ [`06-divisioni-morale-fatica.html:69`](../sections/06-divisioni-morale-fatica.html:69) ✓
- Veterana: 8+ [`06-divisioni-morale-fatica.html:74`](../sections/06-divisioni-morale-fatica.html:74) ✓
- Regolare: 9+ [`06-divisioni-morale-fatica.html:79`](../sections/06-divisioni-morale-fatica.html:79) ✓
- Reclute: 10+ [`06-divisioni-morale-fatica.html:84`](../sections/06-divisioni-morale-fatica.html:84) ✓
- Milizia: 11+ [`06-divisioni-morale-fatica.html:89`](../sections/06-divisioni-morale-fatica.html:89) ✓

**Artiglieria:**
- Base: 10+ [`10-artiglieria-mg.html:52`](../sections/10-artiglieria-mg.html:52) ✓
- Con osservazione: 9+ [`10-artiglieria-mg.html:53`](../sections/10-artiglieria-mg.html:53) ✓
- Dadi per batteria: 4d12 [`10-artiglieria-mg.html:52`](../sections/10-artiglieria-mg.html:52) ✓

**MG:**
- Nel settore: 2d12 a 9+ [`10-artiglieria-mg.html:30`](../sections/10-artiglieria-mg.html:30) ✓
- Supporto adiacente: 1d12 a 9+ [`10-artiglieria-mg.html:36`](../sections/10-artiglieria-mg.html:36) ✓

**Gas:**
- Tiro: 4d12 a 9+ [`11-gas.html:36`](../sections/11-gas.html:36), [`11-gas.html:54`](../sections/11-gas.html:54) ✓

**Aviazione:**
- Tutte le missioni: 1d12 a 7+ [`12-aviazione.html:70-106`](../sections/12-aviazione.html:70) ✓

✓ **TUTTI I VALORI COERENTI**

---

## 8. RIEPILOGO E RACCOMANDAZIONI

### 8.1 Problemi Trovati

| # | Tipo | Gravità | Descrizione | File coinvolto |
|---|------|---------|-------------|----------------|
| 1 | **File mancante** | 🔴 **CRITICO** | `sections/00-copertina.html` non esiste | [`sections.json:7`](../data/sections.json:7) |
| 2 | **File mancante** | 🔴 **CRITICO** | `sections/22-cronologia-isonzo.html` non esiste | [`sections.json:117`](../data/sections.json:117) |

### 8.2 Aspetti Positivi

✅ **Tutti i costi in PL sono coerenti** attraverso l'intero manuale  
✅ **Tutti i modificatori al VT sono coerenti** tra le diverse sezioni  
✅ **Tutte le tabelle di risoluzione sono coerenti** (morale, resa, combattimento)  
✅ **La sequenza del turno è perfettamente allineata** tra capitolo principale e riferimenti  
✅ **I principi generali sono rispettati** in tutte le regole specifiche  
✅ **I valori numerici e i dadi sono uniformi** in tutto il sistema  

### 8.3 Azioni Immediate Richieste

#### ALTA PRIORITÀ - Prima della distribuzione:

1. **Correggere sections.json riga 7:**
   ```json
   "file": "sections/copertina.html"
   ```
   OPPURE rinominare il file `copertina.html` → `00-copertina.html`

2. **Gestire la sezione Isonzo:**
   - **Opzione A:** Creare il file `sections/22-cronologia-isonzo.html` con il contenuto appropriato
   - **Opzione B:** Rimuovere le righe 114-118 da [`sections.json`](../data/sections.json:114)

#### Raccomandazione Finale:

Suggerisco **Opzione A per entrambi i problemi:**
- Rinominare `copertina.html` → `00-copertina.html` (consistenza con la numerazione)
- Creare `22-cronologia-isonzo.html` (completezza del manuale)

---

## 9. CONCLUSIONI

Il manuale "Fronte Occidentale v0.9.5" presenta una **eccellente coerenza interna** delle regole. Tutti i costi, modificatori, tabelle e procedure sono perfettamente allineati tra i diversi capitoli.

L'unico problema riscontrato è di natura **tecnica/organizzativa**: due file dichiarati nel manifesto JSON non corrispondono ai file effettivamente presenti.

Una volta corretti i nomi dei file, il manuale sarà **completamente funzionale e coerente**.

**Valutazione complessiva:** ⭐⭐⭐⭐⭐ (5/5)
- Coerenza regole: 10/10
- Struttura: 10/10
- Integrità file: 6/10 (due file mancanti)
- **Media ponderata: 8.7/10**

---

**Fine Report**
