document.addEventListener("DOMContentLoaded", () => {
  const table = document.getElementById("topics-table");
  const form = document.getElementById("topic-form");

  if (!table || !form) {
    return;
  }

  let currentUser = null;
  let topics = [];

  async function loadAuthors() {
    if (!["moderator", "admin"].includes(currentUser.role)) {
      document.getElementById("topic-author-field").hidden = true;
      return;
    }

    const response = await apiGet("users.php");
    document.getElementById("topic-author-select").innerHTML = optionList(response.items || []);
  }

  async function loadTopics() {
    const response = await apiGet("topics.php");
    topics = response.items || [];
    table.innerHTML = topics.map((topic) => `
      <tr>
        <td>${escapeHtml(topic.id)}</td>
        <td>${escapeHtml(topic.title)}</td>
        <td>${escapeHtml(topic.author_name)} <span class="muted">${escapeHtml(topic.author_email)}</span></td>
        <td>${escapeHtml(formatDate(topic.created_at))}</td>
        <td>
          <button class="button" data-edit="${escapeHtml(topic.id)}" type="button">Edit</button>
          <button class="button" data-delete="${escapeHtml(topic.id)}" type="button">Delete</button>
        </td>
      </tr>
    `).join("");
  }

  function resetForm() {
    form.reset();
    form.id.value = "";
    document.getElementById("topic-form-title").textContent = "Create Topic";
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const data = formDataToObject(form);
    const payload = {
      title: data.title,
      description: data.description
    };

    if (["moderator", "admin"].includes(currentUser.role)) {
      payload.author_id = Number(data.author_id);
    }

    try {
      if (data.id) {
        payload.id = Number(data.id);
        await apiPut("topics.php", payload);
        showNotice("#topics-notice", "Topic updated.");
      } else {
        await apiPost("topics.php", payload);
        showNotice("#topics-notice", "Topic created.");
      }

      resetForm();
      await loadTopics();
    } catch (error) {
      showNotice("#topics-notice", error.message, "error");
    }
  });

  table.addEventListener("click", async (event) => {
    const editId = event.target.dataset.edit;
    const deleteId = event.target.dataset.delete;

    if (editId) {
      const topic = topics.find((item) => Number(item.id) === Number(editId));
      form.id.value = topic.id;
      form.title.value = topic.title;
      form.description.value = topic.description || "";

      if (form.author_id) {
        form.author_id.value = topic.author_id;
      }

      document.getElementById("topic-form-title").textContent = "Edit Topic";
    }

    if (deleteId && confirm("Delete this topic?")) {
      try {
        await apiDelete(`topics.php?id=${encodeURIComponent(deleteId)}`);
        showNotice("#topics-notice", "Topic deleted.");
        await loadTopics();
      } catch (error) {
        showNotice("#topics-notice", error.message, "error");
      }
    }
  });

  document.getElementById("topic-form-reset").addEventListener("click", resetForm);
  requireAuth().then(async (user) => {
    currentUser = user;
    await loadAuthors();
    await loadTopics();
  }).catch((error) => showNotice("#topics-notice", error.message, "error"));
});
