<?php
/**
 * GiftAura - build docs/diagrams.html from the Mermaid blocks in docs/*.md
 *
 * Usage (from the project folder):
 *   C:\xampp\php\php.exe scripts\build_diagrams.php
 *
 * Run it again whenever a diagram in the docs changes, so the HTML view
 * always matches the documents. Command line only.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Command line only.');
}

$docsDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'docs';
$files   = glob($docsDir . DIRECTORY_SEPARATOR . '*.md') ?: [];
sort($files);

$diagrams = [];
foreach ($files as $file) {
    $lines   = file($file, FILE_IGNORE_NEW_LINES) ?: [];
    $doc     = basename($file);
    $heading = $doc;
    $inBlock = false;
    $buffer  = [];

    foreach ($lines as $line) {
        if (!$inBlock && preg_match('/^#{1,4}\s+(.+)$/', $line, $m)) {
            $heading = preg_replace('/^\d+(\.\d+)*\.?\s+/', '', trim($m[1]));
        }
        if (!$inBlock && trim($line) === '```mermaid') {
            $inBlock = true;
            $buffer  = [];
            continue;
        }
        if ($inBlock && trim($line) === '```') {
            $diagrams[] = ['doc' => $doc, 'title' => $heading, 'code' => implode("\n", $buffer)];
            $inBlock = false;
            continue;
        }
        if ($inBlock) {
            $buffer[] = $line;
        }
    }
}

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$sections = '';
foreach ($diagrams as $i => $d) {
    $n = $i + 1;
    $sections .= <<<HTML
    <section class="diagram">
      <header>
        <h2>{$n}. {$e($d['title'])}</h2>
        <span class="src">from docs/{$e($d['doc'])}</span>
        <button type="button" data-download="d{$n}">Download SVG</button>
      </header>
      <pre class="mermaid" id="d{$n}">{$e($d['code'])}</pre>
    </section>

HTML;
}

$generated = date('Y-m-d H:i');
$count     = count($diagrams);

$html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>GiftAura Diagrams</title>
<style>
  :root { --purple:#5b2a86; --ink:#1f1630; --muted:#6b6280; --line:#e6def0; --bg:#faf8fc; }
  * { box-sizing:border-box; }
  body { margin:0; font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; color:var(--ink); background:var(--bg); }
  .top { background:var(--purple); color:#fff; padding:20px 24px; }
  .top h1 { margin:0 0 4px; font-size:1.4rem; }
  .top p { margin:0; opacity:.85; font-size:.9rem; }
  main { max-width:1200px; margin:0 auto; padding:24px 16px 48px; }
  .diagram { background:#fff; border:1px solid var(--line); border-radius:10px; margin:0 0 24px; overflow:hidden; }
  .diagram header { display:flex; flex-wrap:wrap; gap:8px 16px; align-items:center; padding:12px 16px; border-bottom:1px solid var(--line); }
  .diagram h2 { margin:0; font-size:1.05rem; color:var(--purple); }
  .src { color:var(--muted); font-size:.85rem; flex:1; }
  button { border:1px solid var(--purple); background:#fff; color:var(--purple); border-radius:6px; padding:6px 12px; cursor:pointer; font:inherit; font-size:.85rem; }
  button:hover { background:var(--purple); color:#fff; }
  .mermaid { margin:0; padding:16px; overflow-x:auto; text-align:center; background:#fff; }
  .notice { background:#fff4e5; border:1px solid #f0c27a; padding:12px 16px; border-radius:8px; margin-bottom:24px; display:none; }
</style>
</head>
<body>
<div class="top">
  <h1>GiftAura — System Diagrams</h1>
  <p>{$count} diagrams generated from docs/*.md on {$generated}. Regenerate with: php scripts/build_diagrams.php</p>
</div>
<main>
  <div class="notice" id="offline">Diagrams could not be drawn because the Mermaid library did not load. Connect to the internet and refresh, or paste the code shown below into https://mermaid.live.</div>
{$sections}</main>
<script type="module">
  try {
    const { default: mermaid } = await import('https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.esm.min.mjs');
    mermaid.initialize({ startOnLoad: false, theme: 'base', securityLevel: 'strict',
      themeVariables: { primaryColor: '#efe6f8', primaryBorderColor: '#5b2a86', primaryTextColor: '#1f1630', lineColor: '#5b2a86', fontFamily: 'system-ui, Segoe UI, sans-serif' } });
    await mermaid.run({ querySelector: '.mermaid' });
  } catch (err) {
    document.getElementById('offline').style.display = 'block';
    console.error(err);
  }
  document.querySelectorAll('[data-download]').forEach(btn => {
    btn.addEventListener('click', () => {
      const svg = document.querySelector('#' + btn.dataset.download + ' svg');
      if (!svg) { alert('Diagram not drawn yet.'); return; }
      const blob = new Blob([new XMLSerializer().serializeToString(svg)], { type: 'image/svg+xml' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'giftaura-' + btn.dataset.download + '.svg';
      a.click();
      URL.revokeObjectURL(a.href);
    });
  });
</script>
</body>
</html>

HTML;

file_put_contents($docsDir . DIRECTORY_SEPARATOR . 'diagrams.html', $html);
echo "Wrote docs/diagrams.html with {$count} diagram(s)." . PHP_EOL;
