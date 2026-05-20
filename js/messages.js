document.addEventListener("DOMContentLoaded", () => {
  const listTable = document.getElementById("messages-table");
  const thread = document.getElementById("conversation-thread");

  if (listTable) {
    setupMessageList();
  }

  if (thread) {
    setupMessageView();
  }
});

function getQueryParam(name) {
  return new URLSearchParams(window.location.search).get(name);
}

async function setupMessageList() {
  let folder = "inbox";

  async function load(folderName) {
    folder = folderName;
    const response = await apiGet(`messages.php?folder=${encodeURIComponent(folder)}`);
    const items = response.items || [];
    const head = document.getElementById("messages-head");
    const table = document.getElementById("messages-table");

    if (folder === "sent") {
      head.innerHTML = "<tr><th>Subject</th><th>Recipients</th><th>Type</th><th>Status</th><th>Date</th></tr>";
      table.innerHTML = items.map((message) => `
        <tr>
          <td><a href="message-view.html?id=${escapeHtml(message.id)}">${escapeHtml(message.subject)}</a></td>
          <td>${escapeHtml(message.recipients_display)}</td>
          <td>${escapeHtml(message.message_type)}</td>
          <td><span class="badge">${escapeHtml(message.status)}</span></td>
          <td>${escapeHtml(formatDate(message.created_at))}</td>
        </tr>
      `).join("");
    } else {
      head.innerHTML = "<tr><th>Subject</th><th>Sender</th><th>Type</th><th>Status</th><th>Read</th><th>Date</th></tr>";
      table.innerHTML = items.map((message) => `
        <tr>
          <td><a href="message-view.html?id=${escapeHtml(message.id)}">${escapeHtml(message.subject)}</a></td>
          <td>${escapeHtml(message.sender_display_name)}</td>
          <td>${escapeHtml(message.message_type)}</td>
          <td><span class="badge">${escapeHtml(message.status)}</span></td>
          <td>${Number(message.is_read) === 1 ? "Yes" : "No"}</td>
          <td>${escapeHtml(formatDate(message.created_at))}</td>
        </tr>
      `).join("");
    }
  }

  document.querySelectorAll("[data-folder]").forEach((button) => {
    button.addEventListener("click", async () => {
      document.querySelectorAll("[data-folder]").forEach((item) => item.classList.remove("primary"));
      button.classList.add("primary");
      await load(button.dataset.folder);
    });
  });

  requireAuth().then(() => load(folder)).catch((error) => showNotice("#messages-notice", error.message, "error"));
}

async function setupMessageView() {
  const messageId = Number(getQueryParam("id"));

  if (!messageId) {
    showNotice("#message-notice", "Missing message id.", "error");
    return;
  }

  let currentMessage = null;

  async function loadView() {
    const messageResponse = await apiGet(`messages.php?id=${encodeURIComponent(messageId)}`);
    currentMessage = messageResponse.message;
    setText("#message-subject", currentMessage.subject);
    setText("#message-meta", `${currentMessage.sender_display_name} | ${formatDate(currentMessage.created_at)} | ${currentMessage.status}`);

    const conversationResponse = await apiGet(`conversations.php?id=${encodeURIComponent(currentMessage.conversation_id)}`);
    document.getElementById("conversation-thread").innerHTML = (conversationResponse.items || []).map((message) => `
      <article class="thread-item">
        <div class="thread-meta">
          <strong>${escapeHtml(message.sender_display_name)}</strong>
          <span>${escapeHtml(formatDate(message.created_at))}</span>
          <span class="badge">${escapeHtml(message.message_type)}</span>
          <span class="badge">${escapeHtml(message.status)}</span>
          <span>${message.read_status ? `Read ${message.read_status.read}/${message.read_status.total}` : ""}</span>
        </div>
        <p>${escapeHtml(message.body)}</p>
      </article>
    `).join("");

    const flagButton = document.getElementById("flag-violation-button");
    flagButton.hidden = !(currentMessage.is_anonymous && currentMessage.current_user_is_recipient);
  }

  async function loadMyBoxes() {
    const response = await apiGet("anonymous_boxes.php?mine=1");
    document.getElementById("reply-box-select").innerHTML = '<option value="">My user account</option>' + optionList(response.items || [], "display_name");
  }

  document.getElementById("reply-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    const data = formDataToObject(event.currentTarget);

    try {
      await apiPut("messages.php", {
        conversation_id: Number(currentMessage.conversation_id),
        parent_message_id: Number(currentMessage.id),
        body: data.body,
        sender_box_id: data.sender_box_id ? Number(data.sender_box_id) : null
      });
      event.currentTarget.reset();
      showNotice("#message-notice", "Reply sent.");
      await loadView();
    } catch (error) {
      showNotice("#message-notice", error.message, "error");
    }
  });

  document.getElementById("flag-violation-button").addEventListener("click", async () => {
    const note = prompt("Optional note") || "";

    try {
      await apiPost("moderation.php", {
        action: "flag_violation",
        message_id: Number(currentMessage.id),
        note
      });
      showNotice("#message-notice", "Anonymity violation reported.");
    } catch (error) {
      showNotice("#message-notice", error.message, "error");
    }
  });

  requireAuth().then(async () => {
    await loadMyBoxes();
    await loadView();
  }).catch((error) => showNotice("#message-notice", error.message, "error"));
}
