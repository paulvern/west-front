# Implementation Plan: Rename Copertina and Create Cronologia Isonzo

## Task Overview
1. Rename `sections/copertina.html` to `sections/00-copertina.html`
2. Create `sections/22-cronologia-isonzo.html` with historical context for Isonzo battles

## File Status Analysis

### sections.json
- Already correctly references `sections/00-copertina.html` (line 7)
- Already correctly references `sections/22-cronologia-isonzo.html` (lines 115-118)
- **No changes needed to sections.json**

### Current File Issue
- Physical file exists as `sections/copertina.html`
- Should be renamed to `sections/00-copertina.html` to match sections.json reference

## Implementation Steps

### Step 1: Rename Copertina File
**Action**: Read `sections/copertina.html` and write to `sections/00-copertina.html`

**Content**: Keep existing content exactly as is (25 lines)

**Verification**: Check that sections.json reference at line 7 now resolves correctly

**Cleanup**: The old `sections/copertina.html` file can be deleted after successful rename

---

### Step 2: Create 22-cronologia-isonzo.html

**Template Reference**: Follow structure of `sections/17-cronologia-offensive-locali.html` (277 lines)

#### Content Structure

**Opening Section** (similar to 17-cronologia lines 1-17)
```html
<section id="cronologia-isonzo" class="page-break">
  <div class="chapter-title">
    <h1>Trafili storici — Le battaglie dell'Isonzo</h1>
    <p>Note storiche e operative per contestualizzare i settori dello scenario italiano.</p>
  </div>
  
  <div class="box warning">
    <strong>Nota di accuratezza storica:</strong>
    [Explain that scenario focuses on 6th Battle (August 1916) but includes elements from the broader Isonzo campaign]
  </div>
```

**Main Sections** (numbered consistently with project style):

1. **24.0 Il contesto delle battaglie dell'Isonzo**
   - Overview of the 12 Isonzo battles (1915-1917)
   - Strategic objectives: Trieste, Ljubljana gap
   - Geographic challenges: Isonzo river, Karst plateau, mountains
   - Italian offensive doctrine vs. Austro-Hungarian defense

2. **25.0 Cronologia sintetica dell'estate 1916**
   - Table format (similar to line 44-111 of 17-cronologia)
   - Key dates and events of 6th Battle of Isonzo (August 1916)
   
   | Data storica | Evento storico sintetico | Riflesso nello scenario |
   |--------------|--------------------------|-------------------------|
   | 4-6 agosto 1916 | Preparazione artiglieria italiana | Turno 1 bonus PL |
   | 6 agosto | Assalto Monte Sabotino | I2 settore chiave |
   | 8 agosto | Conquista Sabotino | Apertura verso Gorizia |
   | 9 agosto | Entrata italiana a Gorizia | A3 obiettivo principale |
   | 9-10 agosto | Combattimenti sul Carso | A4, A5 settori di pressione |
   | [etc] | | |

3. **26.0 Offensive locali e settori dello scenario**

   **26.1 I1 Plava — Testa di ponte**
   - Historical context about Plava bridgehead
   - Operational importance for Italian logistics
   - Box elements: history, example (in-game reference)

   **26.2 I2 Monte Sabotino — L'altura decisiva**
   - Historical: Key to Gorizia campaign, captured August 6, 1916
   - Elevation advantage, observation posts
   - How Italian Alpini assault succeeded
   - In-game: Critical first objective

   **26.3 I3 Oslavia-Podgora — Il fianco settentrionale**
   - Village complex north of Gorizia
   - Austro-Hungarian defensive positions
   - Multiple assault waves needed

   **26.4 I4 Lucinico — Passaggio intermedio**
   - Southern approach to Gorizia
   - Less fortified but still contested

   **26.5 I5 Gradisca — Settore meridionale**
   - Southern sector, secondary pressure point
   - Bridge crossings and logistics

   **26.6 A1 Monte Santo — L'altura inespugnata**
   - Historical: Never captured in 1916, symbol of Isonzo stalemate
   - Repeated Italian assaults, heavy casualties
   - Austro-Hungarian fortress position

   **26.7 A2 San Gabriele — La fortezza del Carso**
   - Another key height never captured in 6th Battle
   - Connected defensive system with Monte Santo
   - Artillery observation advantage

   **26.8 A3 Gorizia — L'obiettivo simbolico**
   - Historical: Captured August 9, 1916 - major Italian success
   - Political and morale significance
   - Urban combat challenges
   - In-game: Primary victory objective

   **26.9 A4 Monte San Michele — Il Carso sanguinoso**
   - Historical: Bloodiest sector of Isonzo battles
   - Changed hands multiple times in various battles
   - Karst terrain challenges
   - In-game: High attrition sector

   **26.10 A5 Doberdò — Le caverne del Carso**
   - Austro-Hungarian cave systems
   - Nearly impregnable defenses in limestone
   - Italian mining and assault tactics

   **26.11 A6 Vallone del Carso — Retrovia e profondità**
   - Rear area and reserve positions
   - Supply lines through karst valleys
   - Strategic depth for Austro-Hungarian defense

#### Content Guidelines

**Box Types to Use** (matching project style):
- `<div class="box history">` - Historical context
- `<div class="box rule">` - Game rule connections
- `<div class="box warning">` - Important clarifications
- `<div class="box example">` - In-game examples

**Table Structure**:
- Use standard HTML tables with `<th>` headers
- Keep columns aligned with content purpose
- Include "Riflesso nello scenario" column to tie history to game

**Section Numbering**:
- Continue from section 17 (which ends at 23.x)
- Use 24.0, 25.0, 26.0 for main sections
- Use 26.1, 26.2, etc. for subsections

**Historical Accuracy**:
- Focus on 6th Battle of Isonzo (August 1916) as primary reference
- Mention broader campaign context where relevant
- Explain any compression or abstraction for game purposes

**Language Style**:
- Match existing Italian terminology and tone
- Professional military history style
- Clear connections between history and game mechanics

#### Estimated Length
Approximately 250-300 lines (similar to 17-cronologia-offensive-locali.html at 277 lines)

## Content Research Notes

### Historical Context to Include
1. **6th Battle of Isonzo (August 1916)**:
   - Italian Commander: Luigi Cadorna
   - Austro-Hungarian defense under Boroević
   - Italian forces: ~250,000 troops
   - Main success: Capture of Gorizia (first major Italian victory)
   - Secondary success: Monte Sabotino captured
   - Failures: Monte Santo, San Gabriele remained Austro-Hungarian

2. **Geographic Features**:
   - Isonzo River: Natural defensive barrier
   - Karst Plateau: Limestone terrain, caves, difficult assault terrain
   - Mountain positions: Sabotino, Santo, San Gabriele (observation/artillery)

3. **Tactical Patterns**:
   - Italian: Mass artillery preparation, frontal assaults, Alpini specialists
   - Austro-Hungarian: Defense in depth, cave positions, machine gun nests

### Game-History Links to Emphasize
- Why Sabotino must fall before Gorizia can be threatened
- Why Monte Santo and San Gabriele are so difficult (never captured)
- Why Carso sectors are high-attrition (terrain, fortifications)
- How Italian logistics across Isonzo affects operational tempo
- Why Alpini units are specialized for mountain assault

## Verification Checklist
- [ ] File naming matches sections.json references
- [ ] HTML structure is valid and consistent with other sections
- [ ] Section numbering continues logically (24, 25, 26...)
- [ ] All 11 scenario sectors have historical context subsections
- [ ] Historical dates and events are accurate
- [ ] Box styling classes match existing pattern
- [ ] Tables are properly formatted
- [ ] Language is consistent (Italian terminology)
- [ ] In-game connections are clear and useful
- [ ] File can be loaded by index.html navigation system

## Implementation Order
1. First: Rename copertina.html → 00-copertina.html
2. Second: Create 22-cronologia-isonzo.html with full content
3. Third: Visual verification by loading in browser
4. Fourth: Confirm sections.json resolves both files correctly
