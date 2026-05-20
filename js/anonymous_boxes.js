document.addEventListener("DOMContentLoaded", () => {
  const table = document.getElementById("boxes-table");
  const form = document.getElementById("box-form");

  if (!table || !form) {
    return;
  }

  let boxes = [];

  async function loadOptions() {
    const [users, topics] = await Promise.all([apiGet("users.php"), apiGet("topics.php")]);
    document.getElementById("box-user-select").innerHTML = optionList(users.items || []);
    document.getElementById("box-topic-select").innerHTML = '<option value="">None</option>' + optionList(topics.items || [], "title");
  }

  async function loadBoxes() {
    const response = await apiGet("anonymous_boxes.php");
    boxes = response.items || [];
    table.innerHTML = boxes.map((box) => `
      <tr>
        <td>${escapeHtml(box.id)}</td>
        <td>${escapeHtml(box.display_name)}</td>
        <td>${escapeHtml(box.topic_title || "")}</td>
        <td>${escapeHtml(box.real_user_name || "")} <span class="muted">${escapeHtml(box.real_user_email || "")}</span></td>
        <td>
          <button class="button" data-edit="${escapeHtml(box.id)}" type="button">Edit</button>
          <button class="button" data-delete="${escapeHtml(box.id)}" type="button">Delete</button>
        </td>
      </tr>
    `).join("");
  }

  function resetForm() {
    form.reset();
    form.id.value = "";
    document.getElementById("box-form-title").textContent = "Create Box";
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const data = formDataToObject(form);
    const payload = {
      display_name: data.display_name,
      real_user_id: Number(data.real_user_id),
      topic_id: data.topic_id ? Number(data.topic_id) : null
    };

    try {
      if (data.id) {
        payload.id = Number(data.id);
        await apiPut("anonymous_boxes.php", payload);
        showNotice("#boxes-notice", "Box updated.");
      } else {
        await apiPost("anonymous_boxes.php", payload);
        showNotice("#boxes-notice", "Box created.");
      }

      resetForm();
      await loadBoxes();
    } catch (error) {
      showNotice("#boxes-notice", error.message, "error");
    }
  });

  table.addEventListener("click", async (event) => {
    const editId = event.target.dataset.edit;
    const deleteId = event.target.dataset.delete;

    if (editId) {
      const box = boxes.find((item) => Number(item.id) === Number(editId));
      form.id.value = box.id;
      form.display_name.value = box.display_name;
      form.real_user_id.value = box.real_user_id;
      form.topic_id.value = box.topic_id || "";
      document.getElementById("box-form-title").textContent = "Edit Box";
    }

    if (deleteId && confirm("Delete this anonymous box?")) {
      try {
        await apiDelete(`anonymous_boxes.php?id=${encodeURIComponent(deleteId)}`);
        showNotice("#boxes-notice", "Box deleted.");
        await loadBoxes();
      } catch (error) {
        showNotice("#boxes-notice", error.message, "error");
      }
    }
  });

  document.getElementById("box-form-reset").addEventListener("click", resetForm);
  requireAuth().then(async () => {
    await loadOptions();
    await loadBoxes();
  }).catch((error) => showNotice("#boxes-notice", error.message, "error"));
});
