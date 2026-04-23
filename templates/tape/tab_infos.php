<div id="tab-infos" class="tab-panel">

    <div class="info-section" style="border-left-color:var(--accent3)">
        <h3>FORMAT CDT / TZX</h3>
        <p>Les fichiers <strong>.cdt</strong> (Amstrad CPC) et <strong>.tzx</strong> (ZX Spectrum) partagent le même conteneur binaire
        développé par Tomaz Kac. L'en-tête global fait 10 octets :</p>
        <table class="fdc-table" style="margin-top:8px">
            <tr><td>0–6</td><td><code>ZXTape!</code></td><td>Signature (7 octets ASCII)</td></tr>
            <tr><td>7</td><td><code>0x1A</code></td><td>Marqueur fin-de-texte MS-DOS (^Z)</td></tr>
            <tr><td>8</td><td>Major Version</td><td>Version majeure (généralement 01)</td></tr>
            <tr><td>9</td><td>Minor Version</td><td>Version mineure (14 = v1.14)</td></tr>
        </table>
        <p style="margin-top:8px">Chaque bloc qui suit commence par un octet d'identifiant de type, puis sa structure propre.</p>
        <p style="margin-top:6px">
            <strong>Horloge :</strong>
            Les durées en T-states (cycles machine) s'interprètent à <strong>4 MHz</strong> pour les CDT Amstrad CPC,
            et à <strong>3,5 MHz</strong> pour les TZX ZX Spectrum.
        </p>
    </div>

    <div class="info-section" style="border-left-color:var(--accent)">
        <h3>TYPES DE BLOCS</h3>
        <div class="fdc-grid">
            <div class="fdc-col">
                <div class="fdc-title">Blocs de données</div>
                <table class="fdc-table">
                    <tr><td><code>0x10</code></td><td>Standard Loading Data</td><td>Timings ZX standard</td></tr>
                    <tr><td><code>0x11</code></td><td>Turbo Loading Data</td><td>Timings personnalisés</td></tr>
                    <tr><td><code>0x12</code></td><td>Pure Tone</td><td>Tonalité pure (sync)</td></tr>
                    <tr><td><code>0x13</code></td><td>Sequence of Pulses</td><td>Suite de pulses</td></tr>
                    <tr><td><code>0x14</code></td><td>Pure Data</td><td>Données sans pilote</td></tr>
                    <tr><td><code>0x15</code></td><td>Direct Recording</td><td>Enregistrement direct</td></tr>
                    <tr><td><code>0x18</code></td><td>CSW Recording</td><td>Format CSW compressé</td></tr>
                    <tr><td><code>0x19</code></td><td>Generalized Data</td><td>Encodage libre</td></tr>
                </table>
            </div>
            <div class="fdc-col">
                <div class="fdc-title">Blocs de contrôle et métadonnées</div>
                <table class="fdc-table">
                    <tr><td><code>0x20</code></td><td>Pause / Stop</td><td>Pause entre blocs (ms)</td></tr>
                    <tr><td><code>0x21</code></td><td>Group Start</td><td>Début de groupe</td></tr>
                    <tr><td><code>0x22</code></td><td>Group End</td><td>Fin de groupe</td></tr>
                    <tr><td><code>0x30</code></td><td>Text Description</td><td>Texte libre</td></tr>
                    <tr><td><code>0x31</code></td><td>Message Block</td><td>Message temporisé</td></tr>
                    <tr><td><code>0x32</code></td><td>Archive Info</td><td>Infos éditeur/auteur</td></tr>
                    <tr><td><code>0x33</code></td><td>Hardware Type</td><td>Machine cible</td></tr>
                    <tr><td><code>0x35</code></td><td>Custom Info</td><td>Données personnalisées</td></tr>
                    <tr><td><code>0x5A</code></td><td>Glue Block</td><td>Marqueur de concaténation</td></tr>
                </table>
            </div>
        </div>
    </div>

    <div class="info-section" style="border-left-color:var(--green)">
        <h3>EN-TÊTE CASSETTE AMSTRAD CPC (0x2C)</h3>
        <p>Quand le premier octet d'un bloc de données vaut <code>0x2C</code>, c'est un bloc
        <strong>HEADER</strong> Amstrad CPC. Structure des 29 premiers octets :</p>
        <table class="fdc-table" style="margin-top:8px">
            <tr><td>0</td><td><code>0x2C</code></td><td>Marqueur de synchronisation</td></tr>
            <tr><td>1–16</td><td>Filename</td><td>Nom du fichier (16 octets, padding <code>0x00</code>)</td></tr>
            <tr><td>17</td><td>Block Number</td><td>Numéro de bloc (1 = premier)</td></tr>
            <tr><td>18</td><td>Last Block</td><td><code>0xFF</code> = dernier, <code>0x00</code> = pas dernier</td></tr>
            <tr><td>19</td><td>File Type</td><td>0=BASIC, 2=Binaire, 4=Screen, 8=ASM</td></tr>
            <tr><td>20–21</td><td>Data Length</td><td>Longueur des données (16 bits LE)</td></tr>
            <tr><td>22–23</td><td>Load Address</td><td>Adresse de chargement (16 bits LE)</td></tr>
            <tr><td>24</td><td>First Block</td><td><code>0xFF</code> = premier, <code>0x00</code> = pas premier</td></tr>
            <tr><td>25–26</td><td>Logical Length</td><td>Longueur logique du fichier (16 bits LE)</td></tr>
            <tr><td>27–28</td><td>Exec Address</td><td>Adresse d'exécution (16 bits LE)</td></tr>
        </table>
        <p style="margin-top:8px">Le bloc HEADER est suivi d'un bloc DATA contenant les données brutes du fichier.</p>
    </div>

    <div class="info-section" style="border-left-color:#5AF7CE">
        <h3>EN-TÊTE CASSETTE ZX SPECTRUM (0x00)</h3>
        <p>Pour le ZX Spectrum, le premier octet du bloc header est <code>0x00</code> (flag header).
        Structure des 19 octets :</p>
        <table class="fdc-table" style="margin-top:8px">
            <tr><td>0</td><td><code>0x00</code></td><td>Flag header</td></tr>
            <tr><td>1</td><td>Type</td><td>0=Program, 1=Number Array, 2=Char Array, 3=Byte Block</td></tr>
            <tr><td>2–11</td><td>Filename</td><td>Nom du fichier (10 octets)</td></tr>
            <tr><td>12–13</td><td>Length</td><td>Longueur des données (16 bits LE)</td></tr>
            <tr><td>14–15</td><td>Param 1</td><td>Adresse de chargement ou numéro de ligne BASIC</td></tr>
            <tr><td>16–17</td><td>Param 2</td><td>Déplacement dans le tableau / offset</td></tr>
            <tr><td>18</td><td>Checksum</td><td>XOR de tous les octets précédents</td></tr>
        </table>
        <p style="margin-top:8px">Le bloc DATA suivant commence par <code>0xFF</code> (flag data).</p>
    </div>

    <div class="info-section" style="border-left-color:var(--accent2)">
        <h3>BLOC TURBO (0x11) — STRUCTURE DÉTAILLÉE</h3>
        <table class="fdc-table">
            <tr><td>0–1</td><td>PILOT pulse</td><td>Longueur de chaque pulse du pilote (T-states)</td></tr>
            <tr><td>2–3</td><td>SYNC First</td><td>Première pulse de synchronisation</td></tr>
            <tr><td>4–5</td><td>SYNC Second</td><td>Deuxième pulse de synchronisation</td></tr>
            <tr><td>6–7</td><td>ZERO bit</td><td>Longueur de pulse pour un bit 0</td></tr>
            <tr><td>8–9</td><td>ONE bit</td><td>Longueur de pulse pour un bit 1</td></tr>
            <tr><td>10–11</td><td>PILOT tone</td><td>Nombre de pulses du pilote</td></tr>
            <tr><td>12</td><td>Used bits</td><td>Bits utilisés dans le dernier octet (1–8)</td></tr>
            <tr><td>13–14</td><td>Pause after</td><td>Pause après ce bloc (ms)</td></tr>
            <tr><td>15–17</td><td>Data length</td><td>Longueur des données (24 bits LE)</td></tr>
            <tr><td>18+</td><td>Data</td><td>Données du bloc</td></tr>
        </table>
        <p style="margin-top:8px">
            <strong>Calcul de durée :</strong> Durée ≈ (PILOT × PILOT_COUNT + SYNC1 + SYNC2 + NbBits × (ZERO+ONE)) × 1000 / fréquence.
            La fréquence est 4 000 000 Hz pour CDT, 3 500 000 Hz pour TZX.
        </p>
    </div>

    <div class="info-section" style="border-left-color:var(--text-dim)">
        <h3>SUM DATA</h3>
        <p>Somme arithmétique de tous les octets du champ DATA de chaque bloc.
        Permet de comparer rapidement deux dumps pour détecter des différences de contenu,
        sans avoir à comparer les binaires octets par octets.</p>
        <p style="margin-top:6px">
            <strong>Attention :</strong> Le Sum DATA est calculé sur les octets stockés dans le fichier CDT/TZX.
            Pour les blocs TURBO avec usedBits &lt; 8, les bits non utilisés du dernier octet sont inclus
            dans la somme mais ne représentent pas de données réelles.
        </p>
    </div>

</div>