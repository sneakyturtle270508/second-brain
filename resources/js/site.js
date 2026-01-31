// resources/js/site.js
// (Behold evt. andre imports du har fra før over her.)
// import "./bootstrap";

function initAiChat(root) {
  const form = root.querySelector("[data-ai-form]");
  const messages = root.querySelector("[data-ai-messages]");
  const input = form?.querySelector('[name="message"]');
  const tagInput = form?.querySelector('input[name="tag"]');
  const collectionInput = form?.querySelector('input[name="collection"]');
  const includeRecentInput = form?.querySelector("[data-include-recent-input]");
  const suggestions = root.querySelector("[data-ai-suggestions]");

  if (!form || !messages || !input) return;

  const csrf =
    document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";

  let isSending = false;

  if (input instanceof HTMLTextAreaElement) {
    const resize = () => {
      input.style.height = "auto";
      input.style.height = `${input.scrollHeight}px`;
    };
    input.addEventListener("input", resize);
    resize();
  }

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

    const thinkingEl = addMessage("bot", "Tenker", { isLoading: true });

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

    messages.querySelector(".empty-state")?.remove();
    addMessage("user", question);
    input.value = "";
    if (input instanceof HTMLTextAreaElement) {
      input.style.height = "auto";
    }

    await sendQuestion(question);
  });

  suggestions?.addEventListener("click", (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;

    const suggestionEl = target.closest("[data-suggestion]");
    if (!(suggestionEl instanceof HTMLElement)) return;

    const suggestionText = suggestionEl.dataset.suggestion?.trim();
    if (!suggestionText) return;

    input.value = suggestionText;

    if (includeRecentInput) {
      const includeRecent = suggestionEl.dataset.includeRecent === "1";
      includeRecentInput.value = includeRecent ? "1" : "0";

      if (includeRecent) {
        form.requestSubmit();
        return;
      }
    }

    input.focus();
  });
}

function initSidebar(root) {
  root.querySelectorAll("[data-section-toggle]").forEach((button) => {
    button.addEventListener("click", () => {
      const section = button.closest(".section");
      section?.classList.toggle("open");
    });
  });

  const toggle = root.querySelector("[data-sidebar-toggle]");
  const backdrop = root.querySelector("[data-sidebar-backdrop]");

  const closeSidebar = () => {
    root.classList.remove("sidebar-open");
  };

  toggle?.addEventListener("click", () => {
    root.classList.toggle("sidebar-open");
  });

  backdrop?.addEventListener("click", closeSidebar);

  root.querySelectorAll(".note-item").forEach((link) => {
    link.addEventListener("click", closeSidebar);
  });

  window.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeSidebar();
    }
  });
}

document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("[data-ai-chat]").forEach((root) => {
    initAiChat(root);
    initSidebar(root);
  });
});
