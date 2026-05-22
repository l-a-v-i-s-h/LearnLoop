(function () {
  "use strict";

  const API_URL = "../api/post.php";
  const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
  const csrfToken = csrfTokenMeta ? csrfTokenMeta.getAttribute("content") || "" : "";
  const PUSHER_KEY = "14db4509a104fa2c4d52";
  const PUSHER_CLUSTER = "ap2";
  const currentUserId = String(document.body?.getAttribute("data-user-id") || "");
  const isAdminForum = document.body.classList.contains("admin-dashboard-page");

  // Questions are loaded from backend.
  let questions = [];
  const expanded = new Set();

  // ===== DOM =====
  const wrapper = document.getElementById("questionsWrapper");
  const list = document.getElementById("questionsList");
  const emptyState = document.getElementById("forumEmpty");

  const askBtn = document.getElementById("askBtn");
  const askPanel = document.getElementById("askPanel");
  const askPanelLabel = document.querySelector(".ask-panel-label");
  const askForm = document.getElementById("askForm");
  const questionTitle = document.getElementById("questionTitle");
  const questionDescription = document.getElementById("questionDescription");
  const askCancel = document.getElementById("askCancel");
  const askSubmitButton = askForm ? askForm.querySelector(".btn-post") : null;

  const deleteModal = document.getElementById("forumDeleteModal");
  const deleteModalClose = document.getElementById("forumDeleteModalClose");
  const deleteCancel = document.getElementById("forumDeleteCancel");
  const deleteConfirm = document.getElementById("forumDeleteConfirm");
  const deleteQuestionName = document.getElementById("forumDeleteQuestionName");

  const replyDeleteModal = document.getElementById("forumReplyDeleteModal");
  const replyDeleteCancel = document.getElementById("forumReplyDeleteCancel");
  const replyDeleteConfirm = document.getElementById("forumReplyDeleteConfirm");
  const replyDeleteText = document.getElementById("forumDeleteReplyText");

  let editingPostId = "";
  let pendingDeleteId = "";
  let pendingReplyDeleteId = "";
  let pendingReplyDeletePostId = "";
  let editingReplyPostId = "";
  let editingReplyId = "";

  // ===== Helpers =====
  function formatDate(d) {
    const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun",
                    "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    return months[d.getMonth()] + " " + d.getDate();
  }

  function esc(value) {
    return String(value || "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#39;");
  }

  function toQuestion(post) {
    const createdAtRaw = post && post.created_at ? String(post.created_at) : "";
    const updatedAtRaw = post && post.updated_at ? String(post.updated_at) : "";
    const createdAt = createdAtRaw ? new Date(createdAtRaw.replace(" ", "T")) : new Date();
    const updatedAt = updatedAtRaw ? new Date(updatedAtRaw.replace(" ", "T")) : null;
    const safeDate = Number.isNaN(createdAt.getTime()) ? new Date() : createdAt;
    const updatedTime = updatedAt && !Number.isNaN(updatedAt.getTime()) ? updatedAt.getTime() : null;
    const createdTime = safeDate.getTime();
    const isEdited = updatedTime !== null && updatedTime > createdTime;
    const dbReplies = Array.isArray(post && post.replies) ? post.replies : [];

    return {
      id: String(post.post_id || post.id || ""),
      ownerId: String(post.user_id || ""),
      userName: String(post.user_name || post.userName || ""),
      title: String(post.title || ""),
      description: String(post.description || post.content || ""),
      date: formatDate(safeDate),
      edited: isEdited,
      replies: dbReplies.map((reply) => ({
        id: String(reply.comment_id || reply.id || ""),
        text: String(reply.text || reply.content || ""),
        ownerId: String(reply.user_id || ""),
        userName: String(reply.user_name || reply.userName || "")
      }))
    };
  }

  function canEditReply(reply) {
    if (!reply) return false;
    if (!reply.ownerId || !currentUserId) return false;
    return reply.ownerId === currentUserId;
  }

  function canDeleteReply(reply) {
    if (isAdminForum) return true;
    return canEditReply(reply);
  }

  function canEditQuestion(question) {
    if (!question) return false;
    if (!question.ownerId || !currentUserId) return false;
    return question.ownerId === currentUserId;
  }

  function canDeleteQuestion(question) {
    return canEditQuestion(question);
  }

  async function loadQuestions() {
    try {
      const response = await fetch(API_URL, {
        method: "GET",
        headers: {
          "Accept": "application/json"
        }
      });

      const payload = await response.json();
      if (!response.ok || !payload.success) {
        throw new Error(payload.message || "Failed to fetch posts.");
      }

      const items = Array.isArray(payload.data) ? payload.data : [];
      questions = items.map(toQuestion).filter(q => q.id !== "");
      render();
    } catch (error) {
      console.error(error);
      alert("Could not load forum posts from database.");
    }
  }

  function refreshQuestionsSilently() {
    const xhr = new XMLHttpRequest();
    xhr.open("GET", API_URL, true);
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) return;

      if (xhr.status >= 200 && xhr.status < 300) {
        try {
          const payload = JSON.parse(xhr.responseText || "{}");
          if (payload && payload.success && Array.isArray(payload.data)) {
            questions = payload.data.map(toQuestion).filter(q => q.id !== "");
            render();
          }
        } catch (error) {
          // Ignore bad JSON response.
        }
      }
    };
    xhr.send();
  }

  function initForumRealtime() {
    if (!window.Pusher || !PUSHER_KEY) return;

    const pusher = new window.Pusher(PUSHER_KEY, {
      cluster: PUSHER_CLUSTER
    });

    const channel = pusher.subscribe("forum-channel");
    const refresh = function () {
      refreshQuestionsSilently();
    };

    channel.bind("post-created", refresh);
    channel.bind("post-updated", refresh);
    channel.bind("post-deleted", refresh);
    channel.bind("reply-created", refresh);
    channel.bind("reply-updated", refresh);
    channel.bind("reply-deleted", refresh);
  }

  async function createQuestion(title, description) {
    const response = await fetch(API_URL, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json",
        "X-CSRF-Token": csrfToken
      },
      body: JSON.stringify({
        title: title,
        description: description
      })
    });

    const payload = await response.json();
    if (!response.ok || !payload.success) {
      throw new Error(payload.message || "Failed to create post.");
    }

    return toQuestion(payload.data || {});
  }

  async function deleteQuestion(postId) {
    const response = await fetch(API_URL, {
      method: "DELETE",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json",
        "X-CSRF-Token": csrfToken
      },
      body: JSON.stringify({
        post_id: postId
      })
    });

    let payload = {};
    try {
      payload = await response.json();
    } catch (error) {
      payload = {};
    }

    if (!response.ok || !payload.success) {
      throw new Error(payload.message || "Failed to delete post.");
    }
  }

  async function updateQuestion(postId, title, description) {
    const response = await fetch(API_URL, {
      method: "PUT",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json",
        "X-CSRF-Token": csrfToken
      },
      body: JSON.stringify({
        post_id: postId,
        title: title,
        description: description
      })
    });

    let payload = {};
    try {
      payload = await response.json();
    } catch (error) {
      payload = {};
    }

    if (!response.ok || !payload.success) {
      throw new Error(payload.message || "Failed to update post.");
    }
  }

  async function createReply(postId, text) {
    const response = await fetch(API_URL + "?action=comment", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json",
        "X-CSRF-Token": csrfToken
      },
      body: JSON.stringify({
        post_id: postId,
        content: text
      })
    });

    let payload = {};
    try {
      payload = await response.json();
    } catch (error) {
      payload = {};
    }

    if (!response.ok || !payload.success) {
      throw new Error(payload.message || "Failed to add reply.");
    }

    const reply = payload.data || {};
    return {
      id: String(reply.comment_id || reply.id || ""),
      text: String(reply.text || reply.content || text)
    };
  }

  async function updateReply(postId, replyId, text) {
    const response = await fetch(API_URL + "?action=comment", {
      method: "PUT",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json",
        "X-CSRF-Token": csrfToken
      },
      body: JSON.stringify({
        post_id: postId,
        comment_id: replyId,
        content: text
      })
    });

    let payload = {};
    try {
      payload = await response.json();
    } catch (error) {
      payload = {};
    }

    if (!response.ok || !payload.success) {
      throw new Error(payload.message || "Failed to update reply.");
    }
  }

  async function deleteReply(postId, replyId) {
    const response = await fetch(API_URL + "?action=comment", {
      method: "DELETE",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json",
        "X-CSRF-Token": csrfToken
      },
      body: JSON.stringify({
        post_id: postId,
        comment_id: replyId
      })
    });

    let payload = {};
    try {
      payload = await response.json();
    } catch (error) {
      payload = {};
    }

    if (!response.ok || !payload.success) {
      throw new Error(payload.message || "Failed to delete reply.");
    }
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
      const authorLabel = q.userName ? `<span class="q-author">Asked by ${esc(q.userName)}</span>` : "";

      const repliesHtml = replyCount === 0
        ? `<p class="no-replies">No replies yet. Be the first to respond.</p>`
        : `<div class="replies-list">
            ${q.replies.map(r => {
              const canDelete = canDeleteReply(r);
              const replyActions = canDelete
                ? `
                    <div class="reply-actions">
                      <button type="button" class="q-link-action danger" data-action="delete-reply" data-id="${q.id}" data-reply-id="${r.id}" title="Delete reply" aria-label="Delete reply">
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </div>
                  `
                : "";

              const authorLabel = r.userName ? `<div class="reply-name">${esc(r.userName)}</div>` : "";

              return `
                <div class="reply-item">
                  <div class="reply-content">
                    ${authorLabel}
                    <p class="reply-text">${esc(r.text)}</p>
                  </div>
                  ${replyActions}
                </div>
              `;
            }).join("")}
          </div>`;

      const isEditingReplyForPost = editingReplyPostId === q.id && editingReplyId !== "";
      const editingReply = isEditingReplyForPost
        ? q.replies.find(r => r.id === editingReplyId)
        : null;
      const replySubmitLabel = isEditingReplyForPost ? "Update" : "Reply";
      const replyTextValue = editingReply ? editingReply.text : "";
      const replyCancelHtml = isEditingReplyForPost
        ? `<button type="button" class="btn-cancel-reply-edit" data-action="cancel-reply-edit" data-id="${q.id}">Cancel</button>`
        : "";

      const canEdit = canEditQuestion(q);
      const canDelete = canDeleteQuestion(q);
      const actionsHtml = canEdit || canDelete
        ? `
              <div class="q-inline-actions">
                ${canEdit ? `<button type="button" class="q-link-action" data-action="edit" data-id="${q.id}" title="Edit question" aria-label="Edit question">
                  <i class="fa-solid fa-pen-to-square"></i>
                </button>` : ""}
                ${canDelete ? `<button type="button" class="q-link-action danger" data-action="delete" data-id="${q.id}" title="Delete question" aria-label="Delete question">
                  <i class="fa-solid fa-trash"></i>
                </button>` : ""}
              </div>
            `
        : "";

      return `
        <div class="question-card" data-id="${q.id}">
          <div class="q-head">
            <div class="q-main">
              <h3 class="q-title">${esc(q.title)}</h3>
              <p class="q-description">${esc(q.description)}</p>
              <div class="q-meta">
                <span class="reply-count">${replyCount} ${replyCount === 1 ? "Reply" : "Replies"}</span>
                ${q.edited ? `<span class="edited-badge">Edited</span>` : ""}
                ${authorLabel}
                <span>${esc(q.date)}</span>
              </div>
            </div>
            <div class="q-side-actions">
              <button class="q-toggle" data-action="toggle" data-id="${q.id}">
                ${toggleLabel} <i class="fa-solid ${toggleIcon}"></i>
              </button>
              ${actionsHtml}
            </div>
          </div>
          <div class="q-body" ${isOpen ? "" : "hidden"}>
            <div class="replies-label">ANSWERS / REPLIES</div>
            ${repliesHtml}
            ${isAdminForum ? "" : `
              <form class="reply-form" data-action="reply-form" data-id="${q.id}">
                <textarea
                  class="reply-input"
                  name="replyText"
                  placeholder="Write a reply..."
                  rows="1"
                  required
                >${esc(replyTextValue)}</textarea>
                <button type="submit" class="btn-reply">${replySubmitLabel}</button>
                ${replyCancelHtml}
              </form>
            `}
          </div>
        </div>
      `;
    }).join("");
  }

  // ===== Ask panel toggle =====
  function resetAskForm() {
    questionTitle.value = "";
    questionDescription.value = "";
    editingPostId = "";
    if (askPanelLabel) {
      askPanelLabel.textContent = "ASK QUESTION HERE";
    }
    if (askSubmitButton) {
      askSubmitButton.textContent = "Post Question";
    }
  }

  function openAskPanel() {
    askPanel.hidden = false;
    questionTitle.focus();
  }

  function closeAskPanel() {
    resetAskForm();
    askPanel.hidden = true;
  }

  function openDeleteModal(postId) {
    const question = questions.find(q => q.id === postId);
    if (!question || !deleteModal) return;

    pendingDeleteId = postId;
    if (deleteQuestionName) {
      deleteQuestionName.textContent = question.title;
    }
    deleteModal.hidden = false;
  }

  function closeDeleteModal() {
    pendingDeleteId = "";
    if (!deleteModal) return;
    deleteModal.hidden = true;
  }

  function openReplyDeleteModal(postId, replyId) {
    if (!replyDeleteModal) return;

    const question = questions.find(q => q.id === postId);
    if (!question) return;

    const reply = question.replies.find(r => r.id === replyId);
    if (!reply) return;

    pendingReplyDeletePostId = postId;
    pendingReplyDeleteId = replyId;
    if (replyDeleteText) {
      const preview = String(reply.text || "").trim();
      replyDeleteText.textContent = preview === "" ? "this reply" : preview.slice(0, 80);
    }
    replyDeleteModal.hidden = false;
  }

  function closeReplyDeleteModal() {
    pendingReplyDeletePostId = "";
    pendingReplyDeleteId = "";
    if (replyDeleteText) {
      replyDeleteText.textContent = "this reply";
    }
    if (!replyDeleteModal) return;
    replyDeleteModal.hidden = true;
  }

  askBtn.addEventListener("click", () => {
    if (askPanel.hidden) openAskPanel();
    else closeAskPanel();
  });

  askCancel.addEventListener("click", closeAskPanel);

  if (deleteCancel) {
    deleteCancel.addEventListener("click", closeDeleteModal);
  }

  if (deleteModalClose) {
    deleteModalClose.addEventListener("click", closeDeleteModal);
  }

  if (deleteConfirm) {
    deleteConfirm.addEventListener("click", async () => {
      if (!pendingDeleteId) return;

      deleteConfirm.disabled = true;
      deleteConfirm.textContent = "Deleting...";

      try {
        await deleteQuestion(pendingDeleteId);
        questions = questions.filter(q => q.id !== pendingDeleteId);
        if (editingReplyPostId === pendingDeleteId) {
          editingReplyPostId = "";
          editingReplyId = "";
        }
        expanded.delete(pendingDeleteId);
        closeDeleteModal();
        render();
      } catch (error) {
        console.error(error);
        alert(error.message || "Failed to delete question.");
      } finally {
        deleteConfirm.disabled = false;
        deleteConfirm.textContent = "Delete";
      }
    });
  }

  if (replyDeleteCancel) {
    replyDeleteCancel.addEventListener("click", closeReplyDeleteModal);
  }

  if (replyDeleteConfirm) {
    replyDeleteConfirm.addEventListener("click", async () => {
      if (!pendingReplyDeleteId || !pendingReplyDeletePostId) return;

      replyDeleteConfirm.disabled = true;
      replyDeleteConfirm.textContent = "Deleting...";

      try {
        await deleteReply(pendingReplyDeletePostId, pendingReplyDeleteId);
        const question = questions.find(q => q.id === pendingReplyDeletePostId);
        if (question) {
          question.replies = question.replies.filter(r => r.id !== pendingReplyDeleteId);
        }
        if (editingReplyPostId === pendingReplyDeletePostId && editingReplyId === pendingReplyDeleteId) {
          editingReplyPostId = "";
          editingReplyId = "";
        }
        closeReplyDeleteModal();
        render();
      } catch (error) {
        console.error(error);
        alert(error.message || "Failed to delete reply.");
      } finally {
        replyDeleteConfirm.disabled = false;
        replyDeleteConfirm.textContent = "Delete";
      }
    });
  }

  if (deleteModal) {
    deleteModal.addEventListener("click", (e) => {
      if (e.target === deleteModal) {
        closeDeleteModal();
      }
    });
  }

  if (replyDeleteModal) {
    replyDeleteModal.addEventListener("click", (e) => {
      if (e.target === replyDeleteModal) {
        closeReplyDeleteModal();
      }
    });
  }

  document.addEventListener("keydown", (e) => {
    if (e.key !== "Escape") return;
    if (deleteModal && !deleteModal.hidden) {
      closeDeleteModal();
    }
    if (replyDeleteModal && !replyDeleteModal.hidden) {
      closeReplyDeleteModal();
    }
  });

  // ===== Post question =====
  askForm.addEventListener("submit", async (e) => {
    const title = questionTitle.value.trim();
    const description = questionDescription.value.trim();
    if (!title || !description) return;

    // For new posts, use normal form submit to post.php.
    if (!editingPostId) {
      if (askSubmitButton) {
        askSubmitButton.disabled = true;
        askSubmitButton.textContent = "Posting...";
      }
      return;
    }

    e.preventDefault();

    const submitButton = askSubmitButton;
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = editingPostId ? "Updating..." : "Posting...";
    }

    try {
      await updateQuestion(editingPostId, title, description);
      const target = questions.find(q => q.id === editingPostId);
      if (target) {
        target.title = title;
        target.description = description;
        target.edited = true;
        expanded.add(target.id);
      }
      render();
      closeAskPanel();
    } catch (error) {
      console.error(error);
      alert(error.message || "Failed to save question in database.");
    } finally {
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = editingPostId ? "Update Question" : "Post Question";
      }
    }
  });

  // ===== Expand/collapse + reply (event delegation) =====
  list.addEventListener("click", async (e) => {
    const editReplyBtn = e.target.closest('button[data-action="edit-reply"]');
    if (editReplyBtn) {
      const postId = String(editReplyBtn.dataset.id || "");
      const replyId = String(editReplyBtn.dataset.replyId || "");
      if (!postId || !replyId) return;

      const question = questions.find(q => q.id === postId);
      if (!question) return;

      const reply = question.replies.find(r => r.id === replyId);
      if (!reply) return;
      if (!canDeleteReply(reply)) return;

      editingReplyPostId = postId;
      editingReplyId = replyId;
      expanded.add(postId);
      render();
      const replyFormInput = list.querySelector(`form.reply-form[data-id="${postId}"] .reply-input`);
      if (replyFormInput) {
        replyFormInput.focus();
        const valueLength = replyFormInput.value.length;
        replyFormInput.setSelectionRange(valueLength, valueLength);
      }
      return;
    }

    const cancelReplyEditBtn = e.target.closest('button[data-action="cancel-reply-edit"]');
    if (cancelReplyEditBtn) {
      const postId = String(cancelReplyEditBtn.dataset.id || "");
      if (!postId || postId === editingReplyPostId) {
        editingReplyPostId = "";
        editingReplyId = "";
        render();
      }
      return;
    }

    const deleteReplyBtn = e.target.closest('button[data-action="delete-reply"]');
    if (deleteReplyBtn) {
      const postId = String(deleteReplyBtn.dataset.id || "");
      const replyId = String(deleteReplyBtn.dataset.replyId || "");
      if (!postId || !replyId) return;

      const question = questions.find(q => q.id === postId);
      if (!question) return;

      const reply = question.replies.find(r => r.id === replyId);
      if (!reply) return;
      if (!canEditReply(reply)) return;

      openReplyDeleteModal(postId, replyId);
      return;
    }

    const editBtn = e.target.closest('button[data-action="edit"]');
    if (editBtn) {
      if (isAdminForum) return;
      const id = String(editBtn.dataset.id || "");
      if (!id) return;

      const question = questions.find(q => q.id === id);
      if (!question) return;
      if (!canDeleteQuestion(question)) return;

      editingPostId = id;
      questionTitle.value = question.title;
      questionDescription.value = question.description;
      if (askPanelLabel) {
        askPanelLabel.textContent = "EDIT QUESTION HERE";
      }
      if (askSubmitButton) {
        askSubmitButton.textContent = "Update Question";
      }

      openAskPanel();
      questionTitle.focus();
      askPanel.scrollIntoView({ behavior: "smooth", block: "start" });
      return;
    }

    const deleteBtn = e.target.closest('button[data-action="delete"]');
    if (deleteBtn) {
      if (isAdminForum) return;
      const id = String(deleteBtn.dataset.id || "");
      if (!id) return;

      const question = questions.find(q => q.id === id);
      if (!question) return;
      if (!canEditQuestion(question)) return;

      openDeleteModal(id);
      return;
    }

    const toggleBtn = e.target.closest('button[data-action="toggle"]');
    if (toggleBtn) {
      const id = String(toggleBtn.dataset.id || "");
      if (!id) return;
      if (expanded.has(id)) expanded.delete(id);
      else expanded.add(id);
      render();
    }
  });

  list.addEventListener("submit", async (e) => {
    const form = e.target.closest('form[data-action="reply-form"]');
    if (!form) return;
    e.preventDefault();
    const id = String(form.dataset.id || "");
    if (!id) return;
    const input = form.querySelector(".reply-input");
    const submitBtn = form.querySelector(".btn-reply");
    const text = input.value.trim();
    if (!text) return;

    const question = questions.find(q => q.id === id);
    if (!question) return;

    const isReplyEdit = editingReplyPostId === id && editingReplyId !== "";
    const replyToEdit = isReplyEdit
      ? question.replies.find(r => r.id === editingReplyId)
      : null;

    if (isReplyEdit && replyToEdit && !canEditReply(replyToEdit)) {
      editingReplyPostId = "";
      editingReplyId = "";
      render();
      return;
    }

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = isReplyEdit ? "Updating..." : "Replying...";
    }

    try {
      if (isReplyEdit) {
        if (!replyToEdit) {
          editingReplyPostId = "";
          editingReplyId = "";
          render();
          return;
        }

        if (text === replyToEdit.text) {
          editingReplyPostId = "";
          editingReplyId = "";
          render();
          return;
        }

        await updateReply(id, editingReplyId, text);
        replyToEdit.text = text;
        editingReplyPostId = "";
        editingReplyId = "";
      } else {
        const createdReply = await createReply(id, text);
        question.replies.push(createdReply);
      }
      expanded.add(id);
      render();
    } catch (error) {
      console.error(error);
      alert(error.message || (isReplyEdit ? "Failed to update reply." : "Failed to add reply."));
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = isReplyEdit ? "Update" : "Reply";
      }
    }
  });

  // Init
  loadQuestions();
  initForumRealtime();
})();
