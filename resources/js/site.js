// resources/js/site.js

// ── Neural Network Animator ──────────────────────────────────────────────────
class NeuralNetworkViz {
  constructor(canvas) {
    this.canvas = canvas;
    this.ctx = canvas.getContext('2d');
    this.dpr = window.devicePixelRatio || 1;
    this.W = 120;
    this.H = 52;
    canvas.width = this.W * this.dpr;
    canvas.height = this.H * this.dpr;
    this.ctx.scale(this.dpr, this.dpr);

    // Layers: [input, hidden1, hidden2, output]
    this.layers = [3, 5, 4, 2];
    this.nodes = this._buildNodes();
    this.connections = this._buildConnections();
    this.signals = [];   // active pulses
    this.animId = null;
    this.phase = 0;
    this.active = false;
  }

  _buildNodes() {
    const nodes = [];
    const xPositions = [14, 44, 80, 110];
    this.layers.forEach((count, li) => {
      const x = xPositions[li];
      for (let i = 0; i < count; i++) {
        const y = (this.H / (count + 1)) * (i + 1);
        nodes.push({ x, y, layer: li, idx: i, brightness: 0 });
      }
    });
    return nodes;
  }

  _buildConnections() {
    const conns = [];
    let layerStart = 0;
    for (let li = 0; li < this.layers.length - 1; li++) {
      const nextStart = layerStart + this.layers[li];
      for (let a = layerStart; a < nextStart; a++) {
        for (let b = nextStart; b < nextStart + this.layers[li + 1]; b++) {
          conns.push({ from: a, to: b, strength: Math.random() });
        }
      }
      layerStart = nextStart;
    }
    return conns;
  }

  start() {
    this.active = true;
    this._spawnSignals();
    this._loop();
  }

  stop() {
    this.active = false;
    if (this.animId) cancelAnimationFrame(this.animId);
    this.signals = [];
    this._drawIdle();
  }

  _spawnSignals() {
    if (!this.active) return;
    // Pick a random input node and send a wave
    const inputNodes = this.nodes.filter(n => n.layer === 0);
    const start = inputNodes[Math.floor(Math.random() * inputNodes.length)];
    this._propagate(start.layer, start.idx);
    setTimeout(() => this._spawnSignals(), 400 + Math.random() * 300);
  }

  _propagate(layer, idx) {
    if (!this.active) return;
    const fromNode = this.nodes.find(n => n.layer === layer && n.idx === idx);
    if (!fromNode) return;
    fromNode.brightness = 1;

    if (layer < this.layers.length - 1) {
      const connsOut = this.connections.filter(c => c.from === this.nodes.indexOf(fromNode));
      connsOut.forEach(conn => {
        this.signals.push({ conn, progress: 0, speed: 0.04 + Math.random() * 0.03 });
      });
    }
  }

  _loop() {
    if (!this.active) return;
    this.phase++;
    this._update();
    this._draw();
    this.animId = requestAnimationFrame(() => this._loop());
  }

  _update() {
    // Advance signals
    this.signals = this.signals.filter(sig => {
      sig.progress += sig.speed;
      if (sig.progress >= 1) {
        const toNode = this.nodes[sig.conn.to];
        if (toNode) {
          toNode.brightness = 1;
          // Cascade to next layer
          setTimeout(() => this._propagate(toNode.layer, toNode.idx), 60);
        }
        return false;
      }
      return true;
    });

    // Decay node brightness
    this.nodes.forEach(n => {
      n.brightness = Math.max(0, n.brightness - 0.04);
    });
  }

  _draw() {
    const ctx = this.ctx;
    ctx.clearRect(0, 0, this.W, this.H);

    // Connections
    this.connections.forEach(conn => {
      const a = this.nodes[conn.from];
      const b = this.nodes[conn.to];
      const bright = (a.brightness + b.brightness) * 0.5;
      ctx.beginPath();
      ctx.moveTo(a.x, a.y);
      ctx.lineTo(b.x, b.y);
      ctx.strokeStyle = `rgba(217,119,87,${0.06 + bright * 0.35})`;
      ctx.lineWidth = 0.8 + bright * 0.6;
      ctx.stroke();
    });

    // Signal pulses
    this.signals.forEach(sig => {
      const a = this.nodes[sig.conn.from];
      const b = this.nodes[sig.conn.to];
      const px = a.x + (b.x - a.x) * sig.progress;
      const py = a.y + (b.y - a.y) * sig.progress;
      ctx.beginPath();
      ctx.arc(px, py, 2.5, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(217,119,87,${0.5 + sig.progress * 0.5})`;
      ctx.fill();
    });

    // Nodes
    this.nodes.forEach(n => {
      const base = n.layer === 0 ? 0.18 : (n.layer === this.layers.length - 1 ? 0.22 : 0.14);
      const alpha = base + n.brightness * 0.75;
      const r = n.layer === 0 || n.layer === this.layers.length - 1 ? 3.5 : 3;

      // Glow
      if (n.brightness > 0.1) {
        const grd = ctx.createRadialGradient(n.x, n.y, 0, n.x, n.y, 8);
        grd.addColorStop(0, `rgba(217,119,87,${n.brightness * 0.3})`);
        grd.addColorStop(1, 'rgba(217,119,87,0)');
        ctx.beginPath();
        ctx.arc(n.x, n.y, 8, 0, Math.PI * 2);
        ctx.fillStyle = grd;
        ctx.fill();
      }

      ctx.beginPath();
      ctx.arc(n.x, n.y, r, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(100,80,70,${alpha})`;
      ctx.fill();
      if (n.brightness > 0.2) {
        ctx.strokeStyle = `rgba(217,119,87,${n.brightness * 0.8})`;
        ctx.lineWidth = 1;
        ctx.stroke();
      }
    });
  }

  _drawIdle() {
    const ctx = this.ctx;
    ctx.clearRect(0, 0, this.W, this.H);

    this.connections.forEach(conn => {
      const a = this.nodes[conn.from];
      const b = this.nodes[conn.to];
      ctx.beginPath();
      ctx.moveTo(a.x, a.y);
      ctx.lineTo(b.x, b.y);
      ctx.strokeStyle = 'rgba(160,140,135,0.1)';
      ctx.lineWidth = 0.6;
      ctx.stroke();
    });

    this.nodes.forEach(n => {
      ctx.beginPath();
      ctx.arc(n.x, n.y, 2.5, 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(100,80,70,0.18)';
      ctx.fill();
    });
  }
}

// ── Chat Init ────────────────────────────────────────────────────────────────
function initAiChat(root) {
  const form = root.querySelector('[data-ai-form]');
  const messages = root.querySelector('[data-ai-messages]');
  const input = form?.querySelector('[name="message"]');
  const collectionInput = form?.querySelector('input[name="collection"]');
  const includeRecentInput = form?.querySelector('[data-include-recent-input]');
  const suggestions = root.querySelector('[data-ai-suggestions]');

  if (!form || !messages || !input) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  let isSending = false;
  let activeNN = null;

  // Textarea auto-resize
  if (input instanceof HTMLTextAreaElement) {
    const resize = () => {
      input.style.height = 'auto';
      input.style.height = `${Math.min(input.scrollHeight, 160)}px`;
    };
    input.addEventListener('input', resize);
    resize();
  }

  // Enter to send (shift+enter = newline)
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      if (!isSending) form.dispatchEvent(new Event('submit'));
    }
  });

  function scrollToBottom() {
    messages.scrollTo({ top: messages.scrollHeight, behavior: 'smooth' });
  }

  function addUserMessage(text) {
    const wrap = document.createElement('div');
    wrap.className = 'ai-msg ai-msg--user';
    const bubble = document.createElement('div');
    bubble.className = 'ai-bubble';
    bubble.textContent = text;
    wrap.appendChild(bubble);
    messages.appendChild(wrap);
    scrollToBottom();
  }

  function addLoadingBubble() {
    const wrap = document.createElement('div');
    wrap.className = 'ai-msg ai-msg--bot';

    const bubble = document.createElement('div');
    bubble.className = 'ai-bubble ai-bubble--loading';

    // Neural network canvas
    const nnContainer = document.createElement('div');
    nnContainer.className = 'nn-container';

    const canvasWrap = document.createElement('div');
    canvasWrap.className = 'nn-canvas-wrap';

    const canvas = document.createElement('canvas');
    canvas.className = 'nn-canvas';
    canvasWrap.appendChild(canvas);

    const label = document.createElement('span');
    label.className = 'nn-label';
    label.textContent = 'Processing…';

    nnContainer.appendChild(canvasWrap);
    nnContainer.appendChild(label);

    // Typing dots
    const dots = document.createElement('div');
    dots.className = 'typing-dots';
    dots.innerHTML = '<span></span><span></span><span></span>';

    bubble.appendChild(nnContainer);
    bubble.appendChild(dots);
    wrap.appendChild(bubble);
    messages.appendChild(wrap);
    scrollToBottom();

    // Start neural net animation
    const nn = new NeuralNetworkViz(canvas);
    nn._drawIdle();
    nn.start();
    activeNN = nn;

    return { wrap, nn };
  }

  function addBotAnswer(answerText, primarySource) {
    const wrap = document.createElement('div');
    wrap.className = 'ai-msg ai-msg--bot';

    const bubble = document.createElement('div');
    bubble.className = 'ai-bubble';

    // Simple markdown: newlines → paragraphs
    const lines = answerText.trim().split('\n\n');
    lines.forEach(line => {
      const p = document.createElement('p');
      p.style.margin = '0 0 8px';
      p.textContent = line.trim();
      bubble.appendChild(p);
    });
    // Remove last margin
    const last = bubble.querySelector('p:last-child');
    if (last) last.style.margin = '0';

    wrap.appendChild(bubble);

    if (primarySource?.url) {
      const card = document.createElement('div');
      card.className = 'ai-source-card';
      card.innerHTML = `
        <svg class="ai-source-card__icon" width="13" height="13" viewBox="0 0 16 16" fill="currentColor">
          <path d="M8 1a7 7 0 100 14A7 7 0 008 1zm0 2a5 5 0 110 10A5 5 0 018 3zm-.5 2v4.5l3.5 2-.5.85L7 10.5V5h.5z"/>
        </svg>
        <a class="ai-source-card__title" href="${primarySource.url}" target="_blank">${primarySource.title || 'Vis notat'}</a>
      `;
      wrap.appendChild(card);
    }

    messages.appendChild(wrap);
    scrollToBottom();
  }

  async function readJsonSafe(res) {
    const raw = await res.text();
    try { return { data: JSON.parse(raw), raw }; }
    catch { return { data: { error: raw?.slice?.(0, 300) || 'Ukjent feil' }, raw }; }
  }

  async function sendQuestion(question) {
    isSending = true;
    form.querySelector('.send-btn')?.setAttribute('disabled', '');

    const { wrap: loadingWrap, nn } = addLoadingBubble();

    try {
      const res = await fetch('/ai/ask', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
        },
        body: JSON.stringify({
          q: question,
          k: 5,
          collection: collectionInput?.value || null,
          include_recent: includeRecentInput?.value === '1',
        }),
      });

      const { data } = await readJsonSafe(res);
      nn.stop();
      loadingWrap.remove();

      if (!res.ok) {
        const errWrap = document.createElement('div');
        errWrap.className = 'ai-msg ai-msg--bot';
        const errBubble = document.createElement('div');
        errBubble.className = 'ai-bubble';
        errBubble.textContent = data?.error || `Feil (${res.status})`;
        errWrap.appendChild(errBubble);
        messages.appendChild(errWrap);
        return;
      }

      addBotAnswer(data?.answer || '(tomt svar)', data?.primary_source || null);
    } catch (err) {
      nn.stop();
      loadingWrap.remove();
      const errWrap = document.createElement('div');
      errWrap.className = 'ai-msg ai-msg--bot';
      const errBubble = document.createElement('div');
      errBubble.className = 'ai-bubble';
      errBubble.textContent = 'Noe gikk galt. Sjekk konsollen.';
      errWrap.appendChild(errBubble);
      messages.appendChild(errWrap);
      console.error(err);
    } finally {
      isSending = false;
      form.querySelector('.send-btn')?.removeAttribute('disabled');
      input.disabled = false;
      input.focus();
      if (includeRecentInput) includeRecentInput.value = '0';
    }
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (isSending) return;
    const question = input.value.trim();
    if (!question) return;

    messages.querySelector('.empty-state')?.remove();
    addUserMessage(question);
    input.value = '';
    if (input instanceof HTMLTextAreaElement) input.style.height = 'auto';

    await sendQuestion(question);
  });

  suggestions?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-suggestion]');
    if (!btn) return;
    const text = btn.dataset.suggestion?.trim();
    if (!text) return;
    input.value = text;
    if (includeRecentInput && btn.dataset.includeRecent === '1') {
      includeRecentInput.value = '1';
      form.dispatchEvent(new Event('submit'));
      return;
    }
    input.focus();
  });
}

// ── Sidebar ──────────────────────────────────────────────────────────────────
function initSidebar(root) {
  root.querySelectorAll('[data-section-toggle]').forEach(btn => {
    btn.addEventListener('click', () => {
      btn.closest('.section')?.classList.toggle('open');
    });
  });

  const toggle = root.querySelector('[data-sidebar-toggle]');
  const backdrop = root.querySelector('[data-sidebar-backdrop]');
  const closeSidebar = () => root.classList.remove('sidebar-open');

  toggle?.addEventListener('click', () => root.classList.toggle('sidebar-open'));
  backdrop?.addEventListener('click', closeSidebar);
  root.querySelectorAll('.note-item').forEach(link => link.addEventListener('click', closeSidebar));
  window.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });
}

// ── Boot ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-ai-chat]').forEach(root => {
    initAiChat(root);
    initSidebar(root);
  });
});