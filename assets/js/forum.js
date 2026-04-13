(function () {
  "use strict";

  const API_URL = "../api/post.php";

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

  let editingPostId = "";
  let pendingDeleteId = "";
  let editingReplyPostId = "";
  let editingReplyId = "";

  // ===== Helpers =====
  function formatDate(d) {
    const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun",
                    "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    return months[d.getMonth()] + " " + d.getDate();
  }

  function toQuestion(post) {
    const createdAt = post && post.created_at ? new Date(post.created_at.replace(" ", "T")) : new Date();
    const safeDate = Number.isNaN(createdAt.getTime()) ? new Date() : createdAt;
    const dbReplies = Array.isArray(post && post.replies) ? post.replies : [];

    return {
      id: String(post.post_id || post.id || ""),
      title: String(post.title || ""),
      description: String(post.description || post.content || ""),
      date: formatDate(safeDate),
      replies: dbReplies.map((reply) => ({
        id: String(reply.comment_id || reply.id || ""),
        text: String(reply.text || reply.content || "")
      }))
    };
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

  async function createQuestion(title, description) {
    const response = await fetch(API_URL, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json"
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
        "Accept": "application/json"
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
        "Accept": "application/json"
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
        "Accept": "application/json"
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
        "Accept": "application/json"
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
        "Accept": "application/json"
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

      const repliesHtml = replyCount === 0
        ? `<p class="no-replies">No replies yet. Be the first to respond.</p>`
        : `<div class="replies-list">
            ${q.replies.map(r => `
                <div class="reply-item">
                  <p class="reply-text">${r.text}</p>
                  <div class="reply-actions">
                    <button type="button" class="q-link-action" data-action="edit-reply" data-id="${q.id}" data-reply-id="${r.id}" title="Edit reply" aria-label="Edit reply">
                      <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button type="button" class="q-link-action danger" data-action="delete-reply" data-id="${q.id}" data-reply-id="${r.id}" title="Delete reply" aria-label="Delete reply">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </div>
                </div>
            `).join("")}
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

      return `
        <div class="question-card" data-id="${q.id}">
          <div class="q-head">
            <div class="q-main">
              <h3 class="q-title">${q.title}</h3>
              <p class="q-description">${q.description}</p>
              <div class="q-meta">
                <span class="reply-count">${replyCount} ${replyCount === 1 ? "Reply" : "Replies"}</span>
                <span>${q.date}</span>
              </div>
            </div>
            <div class="q-side-actions">
              <button class="q-toggle" data-action="toggle" data-id="${q.id}">
                ${toggleLabel} <i class="fa-solid ${toggleIcon}"></i>
              </button>
              <div class="q-inline-actions">
                <button type="button" class="q-link-action" data-action="edit" data-id="${q.id}" title="Edit question" aria-label="Edit question">
                  <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <button type="button" class="q-link-action danger" data-action="delete" data-id="${q.id}" title="Delete question" aria-label="Delete question">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </div>
            </div>
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
              >${replyTextValue}</textarea>
              <button type="submit" class="btn-reply">${replySubmitLabel}</button>
              ${replyCancelHtml}
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

  if (deleteModal) {
    deleteModal.addEventListener("click", (e) => {
      if (e.target === deleteModal) {
        closeDeleteModal();
      }
    });
  }

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && deleteModal && !deleteModal.hidden) {
      closeDeleteModal();
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

      const confirmed = window.confirm("Delete this reply? This action cannot be undone.");
      if (!confirmed) return;

      deleteReplyBtn.disabled = true;
      try {
        await deleteReply(postId, replyId);
        question.replies = question.replies.filter(r => r.id !== replyId);
        if (editingReplyPostId === postId && editingReplyId === replyId) {
          editingReplyPostId = "";
          editingReplyId = "";
        }
        expanded.add(postId);
        render();
      } catch (error) {
        console.error(error);
        alert(error.message || "Failed to delete reply.");
      } finally {
        deleteReplyBtn.disabled = false;
      }
      return;
    }

    const editBtn = e.target.closest('button[data-action="edit"]');
    if (editBtn) {
      const id = String(editBtn.dataset.id || "");
      if (!id) return;

      const question = questions.find(q => q.id === id);
      if (!question) return;

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
      const id = String(deleteBtn.dataset.id || "");
      if (!id) return;

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
})();
