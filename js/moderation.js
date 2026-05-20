document.addEventListener("DOMContentLoaded", () => {
  const table = document.getElementById("moderation-table");

  if (!table) {
    return;
  }

  async function loadModeration() {
    const response = await apiGet("moderation.php");
    const items = response.items || [];
    table.innerHTML = items.map((item) => `
      <tr>
        <td><input type="checkbox" value="${escapeHtml(item.message_id)}" class="moderation-select"></td>
        <td>
          <strong>${escapeHtml(item.subject)}</strong>
          <div class="muted">${escapeHtml(item.message_type)} | ${escapeHtml(formatDate(item.message_created_at))}</div>
        </td>
        <td>${escapeHtml(item.sender_display_name)}</td>
        <td><span class="badge">${escapeHtml(item.status)}</span></td>
        <td>${Number(item.anonymity_violation) === 1 ? "Yes" : "No"}</td>
        <td><input data-note="${escapeHtml(item.message_id)}" value="${escapeHtml(item.moderator_note || "")}"></td>
        <td>
          <button class="button" data-approve="${escapeHtml(item.message_id)}" type="button">Approve</button>
          <button class="button" data-reject="${escapeHtml(item.message_id)}" type="button">Reject</button>
        </td>
      </tr>
    `).join("");
  }

  table.addEventListener("click", async (event) => {
    const approveId = event.target.dataset.approve;
    const rejectId = event.target.dataset.reject;

    try {
      if (approveId) {
        await apiPost("moderation.php", { action: "approve", message_id: Number(approveId) });
        showNotice("#moderation-notice", "Message approved.");
        await loadModeration();
      }

      if (rejectId) {
        const noteInput = document.querySelector(`[data-note="${CSS.escape(rejectId)}"]`);
        await apiPost("moderation.php", {
          action: "reject",
          message_id: Number(rejectId),
          moderator_note: noteInput ? noteInput.value : ""
        });
        showNotice("#moderation-notice", "Message rejected.");
        await loadModeration();
      }
    } catch (error) {
      showNotice("#moderation-notice", error.message, "error");
    }
  });

  document.getElementById("bulk-approve-button").addEventListener("click", async () => {
    const ids = Array.from(document.querySelectorAll(".moderation-select:checked")).map((item) => Number(item.value));

    try {
      await apiPost("moderation.php", { action: "bulk_approve", message_ids: ids });
      showNotice("#moderation-notice", "Selected messages approved.");
      await loadModeration();
    } catch (error) {
      showNotice("#moderation-notice", error.message, "error");
    }
  });

  requireAuth().then(loadModeration).catch((error) => showNotice("#moderation-notice", error.message, "error"));
});
