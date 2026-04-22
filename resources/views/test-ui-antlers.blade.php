@antlers
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>General Knowledge</title>
  <style>
    :root{--bg:#f6f0e5;--card:#fff;--muted:#6b6b6b;--border:#e6e2d9;--chip:#e8eefc}
    *{box-sizing:border-box}
    html,body{height:100%;font-family:Inter, system-ui, -apple-system, Arial, sans-serif;margin:0;background:var(--bg);color:#111}
    .layout{display:grid;grid-template-columns:60px 320px 1fr;min-height:100vh}
    .nav-rail{background:#fff;border-right:1px solid var(--border);display:flex;flex-direction:column;gap:16px;align-items:center;padding-top:12px}
    .nav-rail .dot{width:28px;height:28px;border-radius:6px;background:#eee}
    .nav-rail .dot.bottom{margin-top:auto;margin-bottom:12px}
    .panel-left{background:#fff;border-right:1px solid var(--border);padding:14px 16px}
    .kb-header{font-weight:700;font-size:18px;margin-bottom:12px}
    .search-wrap{display:flex;align-items:center;background:#f0ede3;border:1px solid var(--border);border-radius:12px;padding:8px 12px;gap:8px}
    .search-wrap input{border:0;outline:none;background:transparent;flex:1;font-size:14px}
    .tabs{display:flex;gap:8px;margin:12px 0}
    .tabs .tab{border:1px solid var(--border);background:#f7f5f0;border-radius:999px;padding:8px 14px;font-size:13px}
    .tabs .tab.active{background:#fff}
    .tree{list-style:none;padding-left:0;margin:0}
    .tree > li{padding:6px 0;border-bottom:1px solid #f0f0f0}
    .tree .folder{font-weight:600;margin-right:8px}
    .tree ul{list-style: none; padding-left:18px; color:#555}
    .panel-right{padding:14px 28px;background:#fff}
    .content-header{font-size:22px;font-weight:700;margin-bottom:12px}
    .cards-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin:8px 0 20px}
    .card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:14px;display:flex;gap:12px;align-items:center;min-height:88px}
    .card-icon{width:34px;height:34px;border-radius:9px;background:#eef2ff;display:flex;align-items:center;justify-content:center;font-weight:700}
    .card-title{font-weight:700}
    .card-subtitle{color:var(--muted);font-size:12px}
    .files-section{background:#fff;border:1px solid var(--border);border-radius:12px;padding:12px;margin-top:8px}
    .files-section h3{margin:6px 0 12px}
    .files-table{width:100%;border-collapse:collapse}
    .files-table th{text-align:left;padding:8px;background:#f7f6f3;border-bottom:1px solid var(--border)}
    .files-table td{padding:12px 8px;border-bottom:1px solid var(--border)}
    .tag{display:inline-block;padding:4px 8px;border-radius:6px;font-size:12px;margin-right:8px;background:#f3f3f9;border:1px solid #ddd}
    .pdf{background:#ffeae6;border-color:#f2a1a0}
    .doc{background:#e6f0ff;border-color:#7fb3ff}
    .added-by{color:var(--muted);font-size:13px}
    @media (max-width: 1000px){
      .layout{grid-template-columns:60px 1fr}
      .panel-left{display:none}
    }
  </style>
</head>
<body>
  <div class="layout">
    <aside class="nav-rail" aria-label="Navigation">
      <div class="dot" title="dashboard"></div>
      <div class="dot" title="notes"></div>
      <div class="dot" title="folders"></div>
      <div class="dot bottom" title="profile"></div>
    </aside>

    <section class="panel-left" aria-label="Knowledge base panels">
      <div class="kb-header">Knowledge Base</div>
      <div class="search-wrap">
        <span class="search-icon">🔍</span>
        <input type="text" placeholder="Search..." />
      </div>
      <div class="tabs">
        <button class="tab active">Folders</button>
        <button class="tab">Tags</button>
      </div>
      <ul class="tree">
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
      <div class="content-header">
        General Knowledge
        <span class="dropdown">▾</span>
      </div>
      <h3 style="font-weight:600;margin:8px 0 6px">Folders</h3>
      <div class="cards-grid" aria-label="Folders">
        <div class="card" title="Onboarding">
          <div class="card-icon">N</div>
          <div class="card-title">Onboarding</div>
          <div class="card-subtitle">15 Files</div>
        </div>
        <div class="card" title="Integrations">
          <div class="card-icon">G</div>
          <div class="card-title">Integrations</div>
          <div class="card-subtitle">5 Files</div>
        </div>
        <div class="card" title="Documents">
          <div class="card-icon">W</div>
          <div class="card-title">Documents</div>
          <div class="card-subtitle">10 Files</div>
        </div>
      </div>
      <section class="files-section" aria-label="Files">
        <h3>Files</h3>
        <table class="files-table">
          <thead>
            <tr><th>Name</th><th>Added By</th></tr>
          </thead>
          <tbody>
            <tr>
              <td><span class="tag pdf">PDF</span> Onboarding-Guide.pdf</td>
              <td class="added-by">kevin@mail.com</td>
            </tr>
            <tr>
              <td><span class="tag doc">DOC</span> Product-Roadmap.docx</td>
              <td class="added-by">antonwe@gmail.com</td>
            </tr>
          </tbody>
        </table>
      </section>
    </main>
  </div>
</body>
</html>
@endants
