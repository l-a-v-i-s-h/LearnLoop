(function () {
  "use strict";

  // Empty by default so empty state is shown first.
  let questions = [];
  const expanded = new Set();

  // ===== DOM =====
  const wrapper = document.getElementById("questionsWrapper");
  const list = document.getElementById("questionsList");
  const emptyState = document.getElementById("forumEmpty");

  const askBtn = document.getElementById("askBtn");
  const askPanel = document.getElementById("askPanel");
  const askForm = document.getElementById("askForm");
  const questionTitle = document.getElementById("questionTitle");
  const questionDescription = document.getElementById("questionDescription");
  const askCancel = document.getElementById("askCancel");

  // ===== Helpers =====
  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function formatDate(d) {
    const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun",
                    "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    return months[d.getMonth()] + " " + d.getDate();
  }

  // ===== Render =====
  function render() {
    if (questions.length === 0) {
      wrapper.hidden = true;
      emptyState.hidden = false;
      list.innerHTML = "";
      return;
    }
    wrapper.hidden = false;
    emptyState.hidden = true;

    list.innerHTML = questions.map(q => {
      const isOpen = expanded.has(q.id);
      const replyCount = q.replies.length;
      const toggleLabel = isOpen ? "Hide Replies" : "View & Reply";
      const toggleIcon = isOpen ? "fa-chevron-up" : "fa-chevron-down";

      const repliesHtml = replyCount === 0
        ? `<p class="no-replies">No replies yet. Be the first to respond.</p>`
        : `<div class="replies-list">
            ${q.replies.map(r => `
              <div class="reply-item">${escapeHtml(r.text)}</div>
            `).join("")}
          </div>`;

      return `
        <div class="question-card" data-id="${q.id}">
          <div class="q-head">
            <div class="q-main">
              <h3 class="q-title">${escapeHtml(q.title)}</h3>
              <p class="q-description">${escapeHtml(q.description)}</p>
              <div class="q-meta">
                <span class="reply-count">${replyCount} ${replyCount === 1 ? "Reply" : "Replies"}</span>
                <span>${escapeHtml(q.date)}</span>
              </div>
            </div>
            <button class="q-toggle" data-action="toggle" data-id="${q.id}">
              ${toggleLabel} <i class="fa-solid ${toggleIcon}"></i>
            </button>
          </div>
          <div class="q-body" ${isOpen ? "" : "hidden"}>
            <div class="replies-label">ANSWERS / REPLIES</div>
            ${repliesHtml}
            <form class="reply-form" data-action="reply-form" data-id="${q.id}">
              <textarea
                class="reply-input"
                name="replyText"
                placeholder="Write a reply..."
                rows="1"
                required
              ></textarea>
              <button type="submit" class="btn-reply">Reply</button>
            </form>
          </div>
        </div>
      `;
    }).join("");
  }

  // ===== Ask panel toggle =====
  function resetAskForm() {
    questionTitle.value = "";
    questionDescription.value = "";
  }

  function openAskPanel() {
    askPanel.hidden = false;
    questionTitle.focus();
  }

  function closeAskPanel() {
    resetAskForm();
    askPanel.hidden = true;
  }

  askBtn.addEventListener("click", () => {
    if (askPanel.hidden) openAskPanel();
    else closeAskPanel();
  });

  askCancel.addEventListener("click", closeAskPanel);

  // ===== Post question =====
  askForm.addEventListener("submit", (e) => {
    e.preventDefault();
    const title = questionTitle.value.trim();
    const description = questionDescription.value.trim();
    if (!title || !description) return;

    const newQuestion = {
      id: Date.now(),
      title,
      description,
      date: formatDate(new Date()),
      replies: [],
    };
    questions.unshift(newQuestion);
    render();
    closeAskPanel();
  });

  // ===== Expand/collapse + reply (event delegation) =====
  list.addEventListener("click", (e) => {
    const toggleBtn = e.target.closest('button[data-action="toggle"]');
    if (toggleBtn) {
      const id = Number(toggleBtn.dataset.id);
      if (expanded.has(id)) expanded.delete(id);
      else expanded.add(id);
      render();
    }
  });

  list.addEventListener("submit", (e) => {
    const form = e.target.closest('form[data-action="reply-form"]');
    if (!form) return;
    e.preventDefault();
    const id = Number(form.dataset.id);
    const input = form.querySelector(".reply-input");
    const text = input.value.trim();
    if (!text) return;

    const question = questions.find(q => q.id === id);
    if (!question) return;

    question.replies.push({ id: Date.now(), text });
    expanded.add(id);
    render();
  });

  // Init
  render();
})();
