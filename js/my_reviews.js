document.addEventListener("DOMContentLoaded", () => {
  const table = document.getElementById("reviews-table");
  const form = document.getElementById("review-form");

  if (!table || !form) {
    return;
  }

  async function loadAssignments() {
    const response = await apiGet("assignments.php");
    const items = response.items || [];
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
    } catch (error) {
      showNotice("#reviews-notice", error.message, "error");
    }
  });

  requireAuth().then(loadAssignments).catch((error) => showNotice("#reviews-notice", error.message, "error"));
});
