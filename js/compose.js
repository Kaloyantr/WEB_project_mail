document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("compose-form");

  if (!form) {
    return;
  }

  let reviewAssignments = [];

  async function loadOptions() {
    const [users, groups, boxes, myBoxes, topics, assignments] = await Promise.all([
      apiGet("users.php?directory=1"),
      apiGet("groups.php"),
      apiGet("anonymous_boxes.php"),
      apiGet("anonymous_boxes.php?mine=1"),
      apiGet("topics.php"),
      apiGet("assignments.php?mine=1")
    ]);

    document.getElementById("compose-users").innerHTML = optionList(users.items || []);
    document.getElementById("compose-groups").innerHTML = optionList(groups.items || []);
    document.getElementById("compose-boxes").innerHTML = optionList(boxes.items || [], "display_name");
    document.getElementById("compose-sender-box").innerHTML = '<option value="">My user account</option>' + optionList(myBoxes.items || [], "display_name");
    document.getElementById("compose-topic").innerHTML = '<option value="">No topic</option>' + optionList(topics.items || [], "title");

    reviewAssignments = assignments.items || [];
    document.getElementById("compose-assignment").innerHTML = [
      '<option value="">Select assignment</option>',
      ...reviewAssignments.map((assignment) => `
        <option value="${escapeHtml(assignment.id)}">
          ${escapeHtml(assignment.topic_title)} | ${escapeHtml(assignment.anonymous_box_name)} | ${escapeHtml(assignment.deadline)}
        </option>
      `)
    ].join("");
  }

  function selectedValues(select) {
    return Array.from(select.selectedOptions).map((option) => Number(option.value));
  }

  function toggleField(fieldId, hidden) {
    const element = document.getElementById(fieldId);

    if (element) {
      element.hidden = hidden;
    }
  }

  function syncAssignmentSelection() {
    const assignmentId = Number(form.review_assignment_id.value);
    const assignment = reviewAssignments.find((item) => Number(item.id) === assignmentId);

    if (!assignment) {
      form.sender_box_id.value = "";
      form.topic_id.value = "";
      return;
    }

    form.sender_box_id.value = String(assignment.anonymous_box_id);
    form.topic_id.value = String(assignment.topic_id);

    if (!form.subject.value.trim() || form.subject.value.startsWith("Review: ")) {
      form.subject.value = `Review: ${assignment.topic_title}`;
    }
  }

  function updateFormMode() {
    const isReview = form.message_type.value === "review";
    const hasAssignments = reviewAssignments.length > 0;

    toggleField("compose-users-field", isReview);
    toggleField("compose-groups-field", isReview);
    toggleField("compose-boxes-field", isReview);
    toggleField("compose-assignment-field", !isReview);
    toggleField("compose-review-help", !isReview);

    form.review_assignment_id.disabled = !isReview;

    if (isReview) {
      if (!hasAssignments) {
        showNotice("#compose-notice", "You do not have a review assignment yet.", "error");
      } else {
        clearNotice("#compose-notice");
      }

      syncAssignmentSelection();
      return;
    }

    clearNotice("#compose-notice");
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const data = formDataToObject(form);
    const isReview = data.message_type === "review";

    try {
      if (isReview && !data.review_assignment_id) {
        showNotice("#compose-notice", "Select one of your review assignments first.", "error");
        return;
      }

      const payload = {
        subject: data.subject,
        body: data.body,
        message_type: data.message_type
      };

      if (isReview) {
        payload.sender_box_id = data.sender_box_id ? Number(data.sender_box_id) : null;
        payload.topic_id = data.topic_id ? Number(data.topic_id) : null;
      } else {
        payload.recipient_user_ids = selectedValues(form.recipient_user_ids);
        payload.recipient_group_ids = selectedValues(form.recipient_group_ids);
        payload.recipient_box_ids = selectedValues(form.recipient_box_ids);
        payload.sender_box_id = data.sender_box_id ? Number(data.sender_box_id) : null;
        payload.topic_id = data.topic_id ? Number(data.topic_id) : null;
      }

      await apiPost("messages.php", payload);
      showNotice("#compose-notice", isReview ? "Review sent for moderation." : "Message sent.");
      form.reset();
      updateFormMode();
    } catch (error) {
      showNotice("#compose-notice", error.message, "error");
    }
  });

  form.message_type.addEventListener("change", updateFormMode);
  form.review_assignment_id.addEventListener("change", syncAssignmentSelection);

  requireAuth().then(async () => {
    await loadOptions();
    updateFormMode();
  }).catch((error) => showNotice("#compose-notice", error.message, "error"));
});
