// resources/js/site.js

// Hvis du har andre imports fra før (AOS, Alpine, osv), behold dem over/under her.
// import "./bootstrap";

function initAiChat(root) {
  const form = root.querySelector("[data-ai-form]");
  const messages = root.querySelector("[data-ai-messages]");
  const input = form?.querySelector('input[name="message"]');
  const tagInput = form?.querySelector('input[name="tag"]');

  if (!form || !messages || !input) return;

  const csrf =
    document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";

  const state = {
    isSending: false,
  };

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

  async function safeReadJsonResponse(res) {
    // Hvis backend sender HTML/redirect/404, json() knekker -> vi leser text først.
    const raw = await res.text();

    try {
      return { ok: true, data: JSON.parse(raw) };
    } catch {
      // Fallback: vis litt av raw for debugging i UI
      return { ok: false, data: { error: raw?.slice?.(0, 300) || "Ukjent respons" } };
    }
  }

  async function sendQuestion(question) {
    state.isSending = true;
    input.disabled = true;

    const thinkingEl = addMessage("bot", "Tenker…");

    try {
      const payload = {
        q: question, // ✅ matcher web.php
        k: 5,
        tag: tagInput?.value || "",
      };

      const res = await fetch("/ai/ask", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          // Du har disabled CSRF på routen, men dette skader ikke om meta finnes.
          "X-CSRF-TOKEN": csrf,
        },
        body: JSON.stringify({
  q: question,
  k: 5,
  tag: tagInput?.value || null,
}),

      });

      const { data } = await safeReadJsonResponse(res);

      thinkingEl.remove();

      if (!res.ok) {
        addMessage("bot", data?.error || `Server error (${res.status})`);
        return;
      }

      addMessage("bot", data?.answer || "(tomt svar)");
    } catch (err) {
      thinkingEl.remove();
      addMessage("bot", "Noe gikk galt. Sjekk console og route.");
      console.error(err);
    } finally {
      state.isSending = false;
      input.disabled = false;
      input.focus();
    }
  }

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    if (state.isSending) return;

    const question = input.value.trim();
    if (!question) return;

    addMessage("user", question);
    input.value = "";

    await sendQuestion(question);
  });

  // Optional: Enter = send, Shift+Enter = ikke relevant for input (kun textarea),
  // men lar stå om du bytter til textarea senere.
  input.addEventListener("keydown", (e) => {
    if (e.key === "Enter" && !e.shiftKey) {
      // input trigges allerede av form submit, så dette er bare “safety”.
    }
  });
}

document.addEventListener("DOMContentLoaded", () => {
  // Init alle chat-komponenter på siden
  document.querySelectorAll("[data-ai-chat]").forEach(initAiChat);
});
