document.addEventListener("DOMContentLoaded", () => {
  const table = document.getElementById("users-table");
  const form = document.getElementById("user-form");

  if (!table || !form) {
    return;
  }

  let users = [];

  async function loadUsers() {
    try {
      const response = await apiGet("users.php");
      users = response.items || [];
      table.innerHTML = users.map((user) => `
        <tr>
          <td>${escapeHtml(user.id)}</td>
          <td>${escapeHtml(user.name)}</td>
          <td>${escapeHtml(user.email)}</td>
          <td><span class="badge">${escapeHtml(user.role)}</span></td>
          <td>
            <button class="button" data-edit="${escapeHtml(user.id)}" type="button">Edit</button>
            <button class="button" data-delete="${escapeHtml(user.id)}" type="button">Delete</button>
          </td>
        </tr>
      `).join("");
    } catch (error) {
      showNotice("#users-notice", error.message, "error");
    }
  }

  function resetForm() {
    form.reset();
    form.id.value = "";
    document.getElementById("user-form-title").textContent = "Create User";
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    clearNotice("#users-notice");
    const data = formDataToObject(form);
    const payload = {
      name: data.name,
      email: data.email,
      role: data.role,
      password: data.password
    };

    try {
      if (data.id) {
        payload.id = Number(data.id);
        await apiPut("users.php", payload);
        showNotice("#users-notice", "User updated.");
      } else {
        await apiPost("users.php", payload);
        showNotice("#users-notice", "User created.");
      }

      resetForm();
      await loadUsers();
    } catch (error) {
      showNotice("#users-notice", error.message, "error");
    }
  });

  table.addEventListener("click", async (event) => {
    const editId = event.target.dataset.edit;
    const deleteId = event.target.dataset.delete;

    if (editId) {
      const user = users.find((item) => Number(item.id) === Number(editId));

      if (!user) {
        return;
      }

      form.id.value = user.id;
      form.name.value = user.name;
      form.email.value = user.email;
      form.password.value = "";
      form.role.value = user.role;
      document.getElementById("user-form-title").textContent = "Edit User";
    }

    if (deleteId && confirm("Delete this user?")) {
      try {
        await apiDelete(`users.php?id=${encodeURIComponent(deleteId)}`);
        showNotice("#users-notice", "User deleted.");
        await loadUsers();
      } catch (error) {
        showNotice("#users-notice", error.message, "error");
      }
    }
  });

  document.getElementById("user-form-reset").addEventListener("click", resetForm);
  requireAuth().then(loadUsers);
});
