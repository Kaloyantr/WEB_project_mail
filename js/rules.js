document.addEventListener("DOMContentLoaded", () => {
  const adminTable = document.getElementById("rules-table");
  const publicTable = document.getElementById("public-rules-table");
  const form = document.getElementById("rule-form");

  if (!adminTable && !publicTable) {
    return;
  }

  let rules = [];

  async function loadRules() {
    const response = await apiGet("rules.php");
    rules = response.items || [];

    if (publicTable) {
      publicTable.innerHTML = rules.map((rule) => `
        <tr>
          <td>${escapeHtml(rule.title)}</td>
          <td>${escapeHtml(rule.rule_type)}</td>
          <td>${escapeHtml(rule.description)}</td>
          <td>${escapeHtml(rule.start_date || "")} - ${escapeHtml(rule.end_date || "")}</td>
        </tr>
      `).join("");
    }

    if (adminTable) {
      adminTable.innerHTML = rules.map((rule) => `
        <tr>
          <td>${escapeHtml(rule.id)}</td>
          <td>${escapeHtml(rule.title)}</td>
          <td>${escapeHtml(rule.rule_type)}</td>
          <td>${Number(rule.is_active) === 1 ? "Yes" : "No"}</td>
          <td>${escapeHtml(rule.start_date || "")} - ${escapeHtml(rule.end_date || "")}</td>
          <td>
            <button class="button" data-edit="${escapeHtml(rule.id)}" type="button">Edit</button>
            <button class="button" data-delete="${escapeHtml(rule.id)}" type="button">Delete</button>
          </td>
        </tr>
      `).join("");
    }
  }

  function resetForm() {
    if (!form) {
      return;
    }

    form.reset();
    form.id.value = "";
    form.is_active.checked = true;
    document.getElementById("rule-form-title").textContent = "Create Rule";
  }

  if (form) {
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const data = formDataToObject(form);
      const payload = {
        title: data.title,
        description: data.description,
        rule_type: data.rule_type,
        start_date: data.start_date,
        end_date: data.end_date,
        is_active: form.is_active.checked ? 1 : 0
      };

      try {
        if (data.id) {
          payload.id = Number(data.id);
          await apiPut("rules.php", payload);
          showNotice("#rules-notice", "Rule updated.");
        } else {
          await apiPost("rules.php", payload);
          showNotice("#rules-notice", "Rule created.");
        }

        resetForm();
        await loadRules();
      } catch (error) {
        showNotice("#rules-notice", error.message, "error");
      }
    });

    document.getElementById("rule-form-reset").addEventListener("click", resetForm);
  }

  if (adminTable) {
    adminTable.addEventListener("click", async (event) => {
      const editId = event.target.dataset.edit;
      const deleteId = event.target.dataset.delete;

      if (editId) {
        const rule = rules.find((item) => Number(item.id) === Number(editId));
        form.id.value = rule.id;
        form.title.value = rule.title;
        form.description.value = rule.description || "";
        form.rule_type.value = rule.rule_type;
        form.start_date.value = rule.start_date || "";
        form.end_date.value = rule.end_date || "";
        form.is_active.checked = Number(rule.is_active) === 1;
        document.getElementById("rule-form-title").textContent = "Edit Rule";
      }

      if (deleteId && confirm("Delete this rule?")) {
        try {
          await apiDelete(`rules.php?id=${encodeURIComponent(deleteId)}`);
          showNotice("#rules-notice", "Rule deleted.");
          await loadRules();
        } catch (error) {
          showNotice("#rules-notice", error.message, "error");
        }
      }
    });
  }

  requireAuth().then(loadRules).catch((error) => showNotice("#rules-notice", error.message, "error"));
});
