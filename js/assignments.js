document.addEventListener("DOMContentLoaded", () => {
  const table = document.getElementById("assignments-table");
  const form = document.getElementById("assignment-form");

  if (!table || !form) {
    return;
  }

  async function loadOptions() {
    const [topics, users] = await Promise.all([apiGet("topics.php"), apiGet("users.php")]);
    document.getElementById("assignment-topic-select").innerHTML = optionList(topics.items || [], "title");
    document.getElementById("assignment-user-select").innerHTML = optionList(users.items || []);
  }

  async function loadAssignments() {
    const response = await apiGet("assignments.php");
    const items = response.items || [];
    table.innerHTML = items.map((item) => `
      <tr>
        <td>${escapeHtml(item.id)}</td>
        <td>${escapeHtml(item.topic_title)}</td>
        <td>${escapeHtml(item.reviewer_name)} <span class="muted">${escapeHtml(item.reviewer_email)}</span></td>
        <td>${escapeHtml(item.anonymous_box_name)}</td>
        <td>${escapeHtml(item.deadline)}</td>
        <td><button class="button" data-delete="${escapeHtml(item.id)}" type="button">Delete</button></td>
      </tr>
    `).join("");
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const data = formDataToObject(form);

    try {
      await apiPost("assignments.php", {
        topic_id: Number(data.topic_id),
        reviewer_user_id: Number(data.reviewer_user_id),
        deadline: data.deadline.replace("T", " ")
      });
      form.reset();
      showNotice("#assignments-notice", "Reviewer assigned.");
      await loadAssignments();
    } catch (error) {
      showNotice("#assignments-notice", error.message, "error");
    }
  });

  table.addEventListener("click", async (event) => {
    const id = event.target.dataset.delete;

    if (id && confirm("Delete this assignment?")) {
      try {
        await apiDelete(`assignments.php?id=${encodeURIComponent(id)}`);
        showNotice("#assignments-notice", "Assignment deleted.");
        await loadAssignments();
      } catch (error) {
        showNotice("#assignments-notice", error.message, "error");
      }
    }
  });

  requireAuth().then(async () => {
    await loadOptions();
    await loadAssignments();
  }).catch((error) => showNotice("#assignments-notice", error.message, "error"));
});
