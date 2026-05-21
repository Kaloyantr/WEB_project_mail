document.addEventListener("DOMContentLoaded", () => {
  const table = document.getElementById("reviews-table");
  const form = document.getElementById("review-form");

  if (!table || !form) {
    return;
  }

  async function loadAssignments() {
    const response = await apiGet("assignments.php?mine=1");
    const items = response.items || [];

    if (items.length === 0) {
      table.innerHTML = `
        <tr>
          <td colspan="4" class="muted">No review assignments are available for your account.</td>
        </tr>
      `;
      return;
    }

    table.innerHTML = items.map((item) => `
      <tr>
        <td>${escapeHtml(item.topic_title)}</td>
        <td>${escapeHtml(item.anonymous_box_name)}</td>
        <td>${escapeHtml(item.deadline)}</td>
        <td><button class="button" data-topic="${escapeHtml(item.topic_id)}" data-box="${escapeHtml(item.anonymous_box_id)}" data-title="${escapeHtml(item.topic_title)}" type="button">Send Review</button></td>
      </tr>
    `).join("");
  }

  table.addEventListener("click", (event) => {
    const topicId = event.target.dataset.topic;

    if (!topicId) {
      return;
    }

    form.topic_id.value = topicId;
    form.sender_box_id.value = event.target.dataset.box;
    form.subject.value = `Review: ${event.target.dataset.title}`;
    clearNotice("#reviews-notice");
    form.body.focus();
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const data = formDataToObject(form);

    try {
      await apiPost("messages.php", {
        topic_id: Number(data.topic_id),
        sender_box_id: Number(data.sender_box_id),
        subject: data.subject,
        body: data.body,
        message_type: "review"
      });
      showNotice("#reviews-notice", "Review sent for moderation.");
      form.reset();
      form.topic_id.value = "";
      form.sender_box_id.value = "";
      await loadAssignments();
    } catch (error) {
      showNotice("#reviews-notice", error.message, "error");
    }
  });

  requireAuth().then(loadAssignments).catch((error) => showNotice("#reviews-notice", error.message, "error"));
});
