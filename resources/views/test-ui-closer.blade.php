@antlers
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Knowledge Base</title>
  <style>
    :root{--bg:#f5f4f0;--card:#fff;--border:#e5e5e5;--text:#111;--muted:#6b6b6b}
    *{box-sizing:border-box}
    html,body{height:100%;margin:0;font-family:Inter, system-ui, -apple-system, Arial, sans-serif;background:var(--bg);color:var(--text)}
    .layout{display:grid;grid-template-columns:64px 320px 1fr;min-height:100vh}
    .nav{background:#fff;border-right:1px solid var(--border);display:flex;flex-direction:column;gap:16px;align-items:center;padding:12px 0}
    .nav .icon{width:28px;height:28px;border-radius:6px;background:#eee}
    .panel-left{background:#fff;border-right:1px solid var(--border);padding:14px 18px;color:#333}
    .kb-header{font-weight:700; font-size:16px; margin-bottom:12px}
    .search{display:flex;gap:8px;background:#f0eee6;border:1px solid var(--border);border-radius:12px;padding:10px 12px}
    .search input{border:0;background:transparent;outline:none;width:100%}
    .tabs{display:flex;gap:8px;margin:10px 0}
    .tab{border:1px solid var(--border);background:#f7f5f0;border-radius:999px;padding:6px 12px;font-size:12px}
    .tab.active{background:#fff}
    .tree{list-style:none;padding:0;margin:6px 0}
    .tree > li{padding:6px 0;border-bottom:1px solid #f0f0f0}
    .tree .folder{font-weight:600;margin-right:6px}
    .panel-right{padding:16px 28px;background:#fff}
    .content-header{font-size:22px;font-weight:700;margin-bottom:8px}
    .cards{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin:8px 0 14px}
    .card{display:flex;flex-direction:column;align-items:flex-start;background:#fff;border:1px solid var(--border);border-radius:12px;padding:12px;height:120px;justify-content:center}
    .card-title{font-weight:700;margin-top:6px}
    .card-sub{font-size:12px;color:var(--muted)}
    .card-thumb{width:60px;height:40px;background:#eee;border-radius:6px;margin-bottom:6px}
    .files{margin-top:12px}
    .files table{width:100%;border-collapse:collapse}
    .files th,.files td{padding:10px;border-bottom:1px solid var(--border);text-align:left}
    .tag{display:inline-block;padding:4px 8px;border-radius:6px;font-size:11px;margin-right:6px;background:#f3f3f9;border:1px solid #ddd}
    .pdf{background:#ffe0e0;border-color:#e29}
    .doc{background:#e0e8ff;border-color:#89b}
  </style>
</head>
<body>
  <div class="layout">
    <aside class="nav" aria-label="Navigation">
      <div class="icon"></div>
      <div class="icon"></div>
      <div class="icon"></div>
      <div class="icon" style="margin-top:auto"></div>
    </aside>

    <section class="panel-left" aria-label="Knowledge base panels">
      <div class="kb-header">Knowledge Base</div>
      <div class="search">
        <span>🔎</span>
        <input placeholder="Search..." />
      </div>
      <div class="tabs">
        <span class="tab active">Folders</span>
        <span class="tab">Tags</span>
      </div>
      <ul class="tree" aria-label="Folders list">
        <li><span class="folder">General Knowledge</span><span class="count">10</span>
          <ul>
            <li>Onboarding</li>
            <li>Subfolder 1</li>
            <li>Subfolder 2</li>
            <li>Integrations</li>
            <li>Documents</li>
            <li>Onboarding Design</li>
            <li>Team Interviews</li>
          </ul>
        </li>
      </ul>
    </section>

    <main class="panel-right" aria-label="Knowledge content">
      <div class="content-header">General Knowledge ▾</div>
      <div class="cards" aria-label="Folders">
        <div class="card">
          <div class="card-thumb"></div>
          <div class="card-title">Onboarding</div>
          <div class="card-sub">15 Files</div>
        </div>
        <div class="card">
          <div class="card-thumb"></div>
          <div class="card-title">Integrations</div>
          <div class="card-sub">5 Files</div>
        </div>
        <div class="card">
          <div class="card-thumb"></div>
          <div class="card-title">Documents</div>
          <div class="card-sub">10 Files</div>
        </div>
      </div>
      <div class="files">
        <h3 style="font-weight:700;margin:8px 0 6px">Files</h3>
        <table>
          <thead><tr><th>Name</th><th>Added By</th></tr></thead>
          <tbody>
            <tr>
              <td><span class="tag pdf">PDF</span> Onboarding-Guide.pdf</td>
              <td>kevin@mail.com</td>
            </tr>
            <tr>
              <td><span class="tag doc">DOC</span> Product-Roadmap.docx</td>
              <td>antonwe@gmail.com</td>
            </tr>
          </tbody>
        </table>
      </div>
    </main>
  </div>
</body>
</html>
@endphp
