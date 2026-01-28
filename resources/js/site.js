// resources/js/site.js
// (Behold evt. andre imports du har fra før over her.)
// import "./bootstrap";

function initAiChat(root) {
  const form = root.querySelector("[data-ai-form]");
  const messages = root.querySelector("[data-ai-messages]");
  const input = form?.querySelector('input[name="message"]');
  const tagInput = form?.querySelector('input[name="tag"]');

  if (!form || !messages || !input) return;

  const csrf =
    document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";

  let isSending = false;

  function scrollToBottom() {
    messages.scrollTop = messages.scrollHeight;
  }

  function addMessage(role, text) {
    const wrap = document.createElement("div");
    wrap.className = `ai-msg ai-msg--${role}`;

    const bubble = document.createElement("div");
    bubble.className = "ai-bubble";
    bubble.textContent = text;

    wrap.appendChild(bubble);
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

    const thinkingEl = addMessage("bot", "Tenker…");

    try {
      const body = {
        q: question,          // ✅ matcher web.php (eller fallbacken din)
        k: 5,
        tag: tagInput?.value || null,
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
      addMessage("bot", data?.answer || "(tomt svar)");

      // Valgfritt: print kilder under svaret
      if (Array.isArray(data?.sources) && data.sources.length) {
        const lines = data.sources
          .map((s, i) => {
            const title = s?.title || `Kilde ${i + 1}`;
            const url = s?.url || s?.permalink || "";
            return url ? `• ${title} — ${url}` : `• ${title}`;
          })
          .join("\n");
        addMessage("bot", `Kilder:\n${lines}`);
      }
    } catch (err) {
      thinkingEl.remove();
      addMessage("bot", "Noe gikk galt. Sjekk console + /ai/ask route.");
      console.error(err);
    } finally {
      isSending = false;
      input.disabled = false;
      input.focus();
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
}

document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("[data-ai-chat]").forEach(initAiChat);
});
