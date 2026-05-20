document.addEventListener("DOMContentLoaded", () => {
  const table = document.getElementById("groups-table");
  const form = document.getElementById("group-form");

  if (!table || !form) {
    return;
  }

  let groups = [];
  let users = [];

  async function loadUsers() {
    const response = await apiGet("users.php");
    users = response.items || [];
    document.getElementById("group-members-select").innerHTML = optionList(users);
  }

  async function loadGroups() {
    const response = await apiGet("groups.php");
    groups = response.items || [];
    table.innerHTML = groups.map((group) => `
      <tr>
        <td>${escapeHtml(group.id)}</td>
        <td>${escapeHtml(group.name)}</td>
        <td>${escapeHtml(group.description)}</td>
        <td>${escapeHtml(group.member_count)}</td>
        <td>
          <button class="button" data-edit="${escapeHtml(group.id)}" type="button">Edit</button>
          <button class="button" data-delete="${escapeHtml(group.id)}" type="button">Delete</button>
        </td>
      </tr>
    `).join("");
  }

  function selectedMemberIds() {
    return Array.from(form.member_user_ids.selectedOptions).map((option) => Number(option.value));
  }

  function resetForm() {
    form.reset();
    form.id.value = "";
    Array.from(form.member_user_ids.options).forEach((option) => option.selected = false);
    document.getElementById("group-form-title").textContent = "Create Group";
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const data = formDataToObject(form);
    const payload = {
      name: data.name,
      description: data.description,
      member_user_ids: selectedMemberIds()
    };

    try {
      if (data.id) {
        payload.id = Number(data.id);
        await apiPut("groups.php", payload);
        showNotice("#groups-notice", "Group updated.");
      } else {
        await apiPost("groups.php", payload);
        showNotice("#groups-notice", "Group created.");
      }

      resetForm();
      await loadGroups();
    } catch (error) {
      showNotice("#groups-notice", error.message, "error");
    }
  });

  table.addEventListener("click", async (event) => {
    const editId = event.target.dataset.edit;
    const deleteId = event.target.dataset.delete;

    if (editId) {
      const response = await apiGet(`groups.php?id=${encodeURIComponent(editId)}`);
      const group = response.group;
      form.id.value = group.id;
      form.name.value = group.name;
      form.description.value = group.description || "";
      const memberIds = new Set((group.members || []).map((member) => Number(member.id)));
      Array.from(form.member_user_ids.options).forEach((option) => option.selected = memberIds.has(Number(option.value)));
      document.getElementById("group-form-title").textContent = "Edit Group";
    }

    if (deleteId && confirm("Delete this group?")) {
      try {
        await apiDelete(`groups.php?id=${encodeURIComponent(deleteId)}`);
        showNotice("#groups-notice", "Group deleted.");
        await loadGroups();
      } catch (error) {
        showNotice("#groups-notice", error.message, "error");
      }
    }
  });

  document.getElementById("group-form-reset").addEventListener("click", resetForm);

  requireAuth().then(async () => {
    await loadUsers();
    await loadGroups();
  }).catch((error) => showNotice("#groups-notice", error.message, "error"));
});
