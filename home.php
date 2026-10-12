<?php
require 'db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$me = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$me->execute([$_SESSION['user_id']]);
$me = $me->fetch();
if (!$me) { session_destroy(); header('Location: login.php'); exit; }

$isEmployee = $me['user_type'] === 'employee';
$otherType  = $isEmployee ? 'employer' : 'employee';

/* ---- Swipe endpoint (called by JS on this same page) ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $in  = json_decode(file_get_contents('php://input'), true) ?: [];
    $tid = (int)($in['target_id'] ?? 0);
    $dir = ($in['direction'] ?? '') === 'right' ? 'right' : 'left';

    $t = $pdo->prepare('SELECT id,name,mobile FROM users WHERE id = ? AND user_type = ?');
    $t->execute([$tid, $otherType]);
    $target = $t->fetch();
    if (!$target) { echo json_encode(['ok'=>false]); exit; }

    $pdo->prepare('INSERT INTO swipes (swiper_id,target_id,direction) VALUES (?,?,?)
                   ON DUPLICATE KEY UPDATE direction = VALUES(direction)')
        ->execute([$me['id'], $tid, $dir]);

    $match = false;
    if ($dir === 'right') {
        $c = $pdo->prepare("SELECT 1 FROM swipes WHERE swiper_id = ? AND target_id = ? AND direction = 'right'");
        $c->execute([$tid, $me['id']]);
        $match = (bool)$c->fetch();
    }
    echo json_encode(['ok'=>true,'match'=>$match,'user'=>$match ? $target : null]);
    exit;
}

/* ---- Deck: people on the other side that I haven't swiped yet ---- */
$st = $pdo->prepare('SELECT id,name,country,description FROM users
                     WHERE user_type = ? AND id NOT IN (SELECT target_id FROM swipes WHERE swiper_id = ?)
                     ORDER BY created_at DESC LIMIT 30');
$st->execute([$otherType, $me['id']]);
$deck = $st->fetchAll();

/* ---- Matches: both swiped right ---- */
$st = $pdo->prepare("SELECT u.id,u.name,u.mobile,u.country FROM swipes a
                     JOIN swipes b ON b.swiper_id = a.target_id AND b.target_id = a.swiper_id AND b.direction = 'right'
                     JOIN users u ON u.id = a.target_id
                     WHERE a.swiper_id = ? AND a.direction = 'right' ORDER BY a.created_at DESC");
$st->execute([$me['id']]);
$matches = $st->fetchAll();

function tint(int $id): string {
    $c = ['#0E9F8E','#3B5BDB','#C2410C','#7C3AED','#0F766E','#BE185D','#1D4ED8'];
    return $c[$id % count($c)];
}
function initial(string $n): string { return mb_strtoupper(mb_substr($n, 0, 1)); }

$title = $isEmployee ? 'Find your next job' : 'Find your next hire';
$sub   = $isEmployee ? 'Swipe right on companies you want to work for.' : 'Swipe right on people you want to hire.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Home · JobSwipe</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="topbar">
  <div class="brand"><i></i>JobSwipe</div>
  <div class="who">
    <span class="chip"><?= $isEmployee ? 'Employee' : 'Employer' ?></span>
    <span><?= h($me['name']) ?></span>
    <a href="logout.php">Log out</a>
  </div>
</header>

<div class="app">
  <main class="stage">
    <h1><?= h($title) ?></h1>
    <p class="sub"><?= h($sub) ?> Drag a card, or use the buttons.</p>

    <div class="deck" id="deck">
      <div class="empty" id="empty">
        <h3>You're all caught up</h3>
        <p>No more <?= $isEmployee ? 'employers' : 'employees' ?> for now. Check back soon.</p>
      </div>
      <?php foreach ($deck as $d): ?>
        <article class="swipe-card" data-id="<?= (int)$d['id'] ?>" data-name="<?= h($d['name']) ?>">
          <span class="stamp yes">Interested</span><span class="stamp no">Pass</span>
          <div class="avatar" style="background:<?= tint((int)$d['id']) ?>"><?= h(initial($d['name'])) ?></div>
          <h3><?= h($d['name']) ?></h3>
          <div class="meta"><?= $isEmployee ? 'Employer' : 'Job seeker' ?> · <?= h($d['country']) ?></div>
          <p><?= nl2br(h($d['description'])) ?></p>
        </article>
      <?php endforeach; ?>
    </div>

    <div class="actions">
      <button class="round no"  id="btnNo"  aria-label="Pass">✕</button>
      <button class="round yes" id="btnYes" aria-label="Interested">♥</button>
    </div>
  </main>

  <aside class="side">
    <h2>Your matches</h2>
    <div id="matches">
      <?php if (!$matches): ?><p class="none" id="noMatch">No matches yet. When both sides swipe right, the contact number shows up here.</p><?php endif; ?>
      <?php foreach ($matches as $m): ?>
        <div class="match">
          <div class="avatar" style="background:<?= tint((int)$m['id']) ?>"><?= h(initial($m['name'])) ?></div>
          <div><b><?= h($m['name']) ?></b><small><?= h($m['mobile']) ?> · <?= h($m['country']) ?></small></div>
        </div>
      <?php endforeach; ?>
    </div>
  </aside>
</div>

<div class="toast" id="toast" role="status"></div>

<script>
const deck = document.getElementById('deck');
const cards = [...deck.querySelectorAll('.swipe-card')];
let i = 0, busy = false;

function layout(){
  cards.forEach((c,k)=>{
    const d = k - i; if (d < 0) return;
    c.style.zIndex = 100 - d;
    c.style.opacity = d < 3 ? 1 : 0;
    c.style.transform = `translateY(${d*10}px) scale(${1 - d*0.04})`;
    c.style.pointerEvents = d === 0 ? 'auto' : 'none';
  });
  document.getElementById('empty').style.display = i >= cards.length ? 'flex' : 'none';
}

async function decide(dir){
  if (busy || i >= cards.length) return;
  busy = true;
  const c = cards[i];
  c.classList.remove('drag');
  c.style.transform = `translate(${dir==='right'?520:-520}px,-30px) rotate(${dir==='right'?22:-22}deg)`;
  c.style.opacity = 0;
  i++; layout();
  try{
    const r = await fetch('home.php', {method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({target_id: c.dataset.id, direction: dir})});
    const j = await r.json();
    if (j.match) showMatch(j.user, c);
  }catch(e){}
  setTimeout(()=>busy=false, 250);
}

function showMatch(u, card){
  const t = document.getElementById('toast');
  t.innerHTML = `<b>It's a match with ${esc(u.name)}</b><br>Call or message: ${esc(u.mobile)}`;
  t.classList.add('show'); setTimeout(()=>t.classList.remove('show'), 5000);
  const none = document.getElementById('noMatch'); if (none) none.remove();
  const av = card.querySelector('.avatar');
  const el = document.createElement('div'); el.className = 'match';
  el.innerHTML = `<div class="avatar" style="background:${av.style.background}">${esc(u.name.charAt(0).toUpperCase())}</div>
                  <div><b>${esc(u.name)}</b><small>${esc(u.mobile)}</small></div>`;
  document.getElementById('matches').prepend(el);
}
function esc(s){ return String(s).replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

// Drag to swipe
let sx = 0, sy = 0, dragging = null;
deck.addEventListener('pointerdown', e=>{
  const c = e.target.closest('.swipe-card'); if (!c || c !== cards[i] || busy) return;
  dragging = c; sx = e.clientX; sy = e.clientY; c.classList.add('drag'); c.setPointerCapture(e.pointerId);
});
deck.addEventListener('pointermove', e=>{
  if (!dragging) return;
  const dx = e.clientX - sx, dy = (e.clientY - sy) * .3;
  dragging.style.transform = `translate(${dx}px,${dy}px) rotate(${dx/18}deg)`;
  dragging.querySelector('.stamp.yes').style.opacity = Math.min(Math.max(dx/110,0),1);
  dragging.querySelector('.stamp.no').style.opacity  = Math.min(Math.max(-dx/110,0),1);
});
function endDrag(e){
  if (!dragging) return;
  const dx = e.clientX - sx, c = dragging; dragging = null;
  c.classList.remove('drag');
  if (Math.abs(dx) > 110) { decide(dx > 0 ? 'right' : 'left'); }
  else { c.querySelectorAll('.stamp').forEach(s=>s.style.opacity=0); layout(); }
}
deck.addEventListener('pointerup', endDrag);
deck.addEventListener('pointercancel', endDrag);

document.getElementById('btnYes').onclick = ()=>decide('right');
document.getElementById('btnNo').onclick  = ()=>decide('left');
addEventListener('keydown', e=>{ if(e.key==='ArrowRight') decide('right'); if(e.key==='ArrowLeft') decide('left'); });
layout();
</script>
</body>
</html>
