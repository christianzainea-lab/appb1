<?php
$title = 'Cum funcționează';
// [imagini, titlu, text]. În text, ① ② ③ corespund cercurilor portocalii din imagini.
$roles = [
    'voluntar' => ['Voluntar', 'Ce poate face oricine din echipă', [
        [['v-program'], 'Programul tău', 'Pe prima pagină vezi unde ești programat. ① Confirmă cu „Accept”.'],
        [['v-confirma', 'v-nupot', 'v-trimis'], 'Nu poți ajunge?', 'Apasă ② „Nu pot ajunge”, scrie motivul dacă vrei și trimite. Liderul serviciului este anunțat pe loc.'],
        [['v-ordinea'], 'Ordinea serviciului', '① Ascultă cântarea direct aici. ② Apasă pe titlu ca să vezi tonul, versurile și acordurile.'],
        [['v-cantare'], 'De la o cântare la alta', '① Treci la cântarea următoare din același serviciu (sau glisează pe ecran). ② Te întorci la serviciu.'],
        [['v-cantari'], 'Toate cântările', '① Caută după titlu, număr (ex. „cr 4”) sau un vers. Alege una sau mai multe cărți și etichete. ② Ascultă înregistrarea.'],
        [['v-indisponibil'], 'Zilele în care nu poți', 'Apasă pe prima și pe ultima zi, apoi ① „Marchează”. În acele zile nu vei fi programat.'],
        [['v-calendar'], 'Programul în telefon', '① Adaugă-l o singură dată în Google Calendar sau pe iPhone. Se actualizează singur.'],
        [['v-pozitii'], 'Unde slujești', 'În Profil alegi ① pozițiile tale: voce, instrument, sunet… Poți fi programat doar pe ele.'],
    ]],
    'pastor' => ['Pastor', 'Pentru pregătirea serviciului', [
        [['p-lista'], 'Toate serviciile', 'În Planificare ① deschizi orice serviciu care urmează, chiar și cele încă nepublicate.'],
        [['p-ordinea'], 'Ordinea serviciului', '① Muți cântările mai sus sau mai jos. ② Adaugi predica, o rugăciune, o mărturie, cu notă. ③ Salvezi.'],
        [['p-fisier'], 'Prezentarea predicii', 'La predică (sau la orice alt element) ② atașezi PowerPoint-ul sau PDF-ul. ① Echipa îl descarcă de aici, iar mesajul de WhatsApp primește linkul direct.'],
        [['p-notite'], 'Notițe pentru echipă', 'Scrii ce trebuie să știe toți cei programați și ① salvezi.'],
        [['p-whatsapp'], 'Mesaj pentru grup', '① Copiezi programul gata scris și îl lipești în grupul de WhatsApp.'],
        [['p-vede'], 'Cum vede echipa', '① Deschizi pagina exact cum o vede un voluntar.'],
    ]],
    'editor' => ['Editor', 'Planificare, cântări și oameni', [
        [['e-roster'], 'Programează oamenii', 'Apasă pe rândul unei poziții sau pe ① „Alege” / „Schimbă”.'],
        [['e-alege'], 'Alege omul potrivit', 'Cei mai potriviți sunt primii. Vezi câte duminici are fiecare luna aceasta. ① Alege.'],
        [['e-luna'], 'Toată luna dintr-o privire', 'Pe calculator sau tabletă: ① alegi doar duminicile sau joile, ② apeși pe o căsuță ca să alegi omul.'],
        [['e-cantari'], 'Alege cântările', 'Scrii titlul sau numărul și ① alegi din listă. Apoi pui tonul și cine conduce.'],
        [['e-publica'], 'Publică pentru echipă', 'Echipa vede serviciul doar după ① „Publică”. ② Bifează dacă vrei să-i anunți și pe email.'],
        [['e-cantare'], 'Biblioteca de cântări', 'Adaugi cântări noi, înregistrări YouTube sau MP3, acorduri și ① versurile.'],
        [['e-sablon'], 'Șabloane', 'Joile și duminicile apar singure. În șablon alegi pozițiile, ordinea și ① liderul implicit.'],
        [['e-persoane'], 'Oamenii din echipă', 'Le dai voluntarilor poziții și ① eticheta „Lider”.'],
        [['e-istoric'], 'Istoric', 'La fiecare serviciu și cântare vezi cine a schimbat ce și când.'],
    ]],
];
$img = fn(string $n) => asset('ghid/' . $n . '.webp');
$num = fn(string $t) => preg_replace_callback('/[①②③]/u', fn($m) => '<span class="gnum">' . (['①' => 1, '②' => 2, '③' => 3][$m[0]]) . '</span>', e($t));
?>
<main class="page guide">
  <div class="page-head">
    <div class="grow">
      <h1>Cum funcționează</h1>
      <span class="small muted">Câteva imagini, un minut. Cercurile <span class="gnum">1</span> din text arată butonul din imagine.</span>
    </div>
  </div>
  <div class="seg-links guide-tabs" role="tablist" aria-label="Rol">
    <?php foreach ($roles as $k => [$label]): ?>
      <a href="#<?= $k ?>" role="tab" data-gtab="<?= $k ?>"<?= $k === $start ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
  <?php foreach ($roles as $k => [$label, $sub, $cards]): ?>
    <section class="guide-role" id="<?= $k ?>" data-gpanel="<?= $k ?>"<?= $k === $start ? '' : ' hidden' ?>>
      <p class="small muted" style="margin:0"><?= e($sub) ?><?= $k === 'pastor' ? ' · plus tot ce poate un voluntar' : ($k === 'editor' ? ' · plus tot ce pot voluntarul și pastorul' : '') ?></p>
      <div class="guide-grid">
        <?php foreach ($cards as $i => [$pics, $t, $txt]): ?>
          <article class="gcard<?= count($pics) > 1 ? ' wide' : '' ?>">
            <div class="gpics">
              <?php foreach ($pics as $j => $p): ?>
                <button type="button" class="gpic" data-zoom="<?= e($img($p)) ?>" aria-label="Mărește imaginea">
                  <?php if (count($pics) > 1): ?><span class="gstep"><?= $j + 1 ?></span><?php endif; ?>
                  <img src="<?= e($img($p)) ?>" alt="<?= e($t) ?>" loading="lazy" decoding="async">
                </button>
              <?php endforeach; ?>
            </div>
            <div class="gtext">
              <h2><span class="gcount"><?= $i + 1 ?></span><?= e($t) ?></h2>
              <p><?= $num($txt) ?></p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
  <p class="xs muted" style="text-align:center">Imaginile folosesc nume inventate. Întrebări? Scrie liderului sau unui editor.</p>
</main>
<dialog id="gzoom" class="gzoom" aria-label="Imagine mărită"><img alt=""><button class="icon-btn" type="button" data-close aria-label="Închide"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></dialog>
