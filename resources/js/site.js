// resources/js/site.js
// (Behold evt. andre imports du har fra før over her.)
// import "./bootstrap";

function initAiChat(root) {
  const form = root.querySelector("[data-ai-form]");
  const messages = root.querySelector("[data-ai-messages]");
  const input = form?.querySelector('input[name="message"]');
  const tagInput = form?.querySelector('input[name="tag"]');
  const collectionInput = form?.querySelector('input[name="collection"]');
  const includeRecentInput = form?.querySelector("[data-include-recent-input]");
  const suggestions = root.querySelector("[data-ai-suggestions]");
  const weeklyNotes = document.querySelector("[data-weekly-notes]");
  const weeklyCount = document.querySelector("[data-weekly-count]");
  const noteItems = Array.from(document.querySelectorAll("[data-note-item]"));

  if (!form || !messages || !input) return;

  const csrf =
    document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";

  let isSending = false;

  function scrollToBottom() {
    messages.scrollTop = messages.scrollHeight;
  }

  function addMessage(role, text, { isLoading = false } = {}) {
    const wrap = document.createElement("div");
    wrap.className = `ai-msg ai-msg--${role}`;

    const bubble = document.createElement("div");
    bubble.className = `ai-bubble${isLoading ? " ai-bubble--loading" : ""}`;
    bubble.textContent = text;

    wrap.appendChild(bubble);
    messages.appendChild(wrap);
    scrollToBottom();
    return wrap;
  }

  function addBotAnswer(answerText, primarySource) {
    const wrap = document.createElement("div");
    wrap.className = "ai-msg ai-msg--bot";

    const bubble = document.createElement("div");
    bubble.className = "ai-bubble";
    bubble.textContent = answerText;
    wrap.appendChild(bubble);

    if (primarySource) {
      const card = document.createElement("div");
      card.className = "ai-source-card";

      const titleLink = document.createElement("a");
      titleLink.className = "ai-source-card__title";
      titleLink.textContent = primarySource.title || "Kilde";
      titleLink.href = primarySource.url || "#";
      card.appendChild(titleLink);

      wrap.appendChild(card);
    }

    messages.appendChild(wrap);
    scrollToBottom();
    return wrap;
  }

  // Leser JSON trygt, selv om backend returnerer HTML/redirect/404
  async function readJsonSafe(res) {
    const raw = await res.text();
    try {
      return { data: JSON.parse(raw), raw };
    } catch {
      return { data: { error: raw?.slice?.(0, 500) || "Ukjent respons" }, raw };
    }
  }

  async function sendQuestion(question) {
    isSending = true;
    input.disabled = true;

    const thinkingEl = addMessage("bot", "Tenker…", { isLoading: true });

    try {
      const body = {
        q: question,          // ✅ matcher web.php (eller fallbacken din)
        k: 5,
        tag: tagInput?.value || null,
        collection: collectionInput?.value || null,
        include_recent: includeRecentInput?.value === "1",
      };

      const res = await fetch("/ai/ask", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          ...(csrf ? { "X-CSRF-TOKEN": csrf } : {}),
        },
        body: JSON.stringify(body),
      });

      const { data } = await readJsonSafe(res);
      thinkingEl.remove();

      if (!res.ok) {
        addMessage("bot", data?.error || `Server error (${res.status})`);
        return;
      }

      // Hvis du vil vise kilder pent i chatten:
      // (valgfritt – funker bare hvis backend returnerer sources med url/permalink)
      addBotAnswer(data?.answer || "(tomt svar)", data?.primary_source || null);
    } catch (err) {
      thinkingEl.remove();
      addMessage("bot", "Noe gikk galt. Sjekk console + /ai/ask route.");
      console.error(err);
    } finally {
      isSending = false;
      input.disabled = false;
      input.focus();
      if (includeRecentInput) {
        includeRecentInput.value = "0";
      }
    }
  }

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    if (isSending) return;

    const question = input.value.trim();
    if (!question) return;

    addMessage("user", question);
    input.value = "";

    await sendQuestion(question);
  });

  suggestions?.addEventListener("click", (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    const card = target.closest(".ai-card");
    if (!card) return;

    const suggestionText = card.dataset.suggestion?.trim() || card.textContent?.trim();
    if (!suggestionText) return;

    input.value = suggestionText;
    if (includeRecentInput) {
      includeRecentInput.value = card.dataset.includeRecent === "1" ? "1" : "0";
    }
    input.focus();
  });

  if (weeklyNotes && noteItems.length > 0) {
    const now = Date.now();
    const weekMs = 7 * 24 * 60 * 60 * 1000;
    const recent = noteItems.filter((item) => {
      const updatedAt = Number(item.dataset.updatedAt) * 1000;
      return Number.isFinite(updatedAt) && now - updatedAt <= weekMs;
    });

    weeklyNotes.innerHTML = "";
    recent.forEach((item) => {
      const clone = item.cloneNode(true);
      clone.removeAttribute("data-note-item");
      weeklyNotes.appendChild(clone);
    });

    if (weeklyCount) {
      weeklyCount.textContent = String(recent.length);
    }

    if (recent.length === 0) {
      const empty = document.createElement("div");
      empty.className = "note-card";
      empty.innerHTML =
        "<strong>Ingen oppdateringer denne uken.</strong><span class=\"note-card__meta\">Lag et notat eller oppdater eksisterende for å se det her.</span>";
      weeklyNotes.appendChild(empty);
    }
  }
}

document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("[data-ai-chat]").forEach(initAiChat);
});
