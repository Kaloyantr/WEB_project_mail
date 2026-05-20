document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("compose-form");

  if (!form) {
    return;
  }

  async function loadOptions() {
    const [users, groups, boxes, myBoxes, topics] = await Promise.all([
      apiGet("users.php?directory=1"),
      apiGet("groups.php"),
      apiGet("anonymous_boxes.php"),
      apiGet("anonymous_boxes.php?mine=1"),
      apiGet("topics.php")
    ]);

    document.getElementById("compose-users").innerHTML = optionList(users.items || []);
    document.getElementById("compose-groups").innerHTML = optionList(groups.items || []);
    document.getElementById("compose-boxes").innerHTML = optionList(boxes.items || [], "display_name");
    document.getElementById("compose-sender-box").innerHTML = '<option value="">My user account</option>' + optionList(myBoxes.items || [], "display_name");
    document.getElementById("compose-topic").innerHTML = '<option value="">No topic</option>' + optionList(topics.items || [], "title");
  }

  function selectedValues(select) {
    return Array.from(select.selectedOptions).map((option) => Number(option.value));
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const data = formDataToObject(form);

    try {
      await apiPost("messages.php", {
        recipient_user_ids: selectedValues(form.recipient_user_ids),
        recipient_group_ids: selectedValues(form.recipient_group_ids),
        recipient_box_ids: selectedValues(form.recipient_box_ids),
        sender_box_id: data.sender_box_id ? Number(data.sender_box_id) : null,
        topic_id: data.topic_id ? Number(data.topic_id) : null,
        subject: data.subject,
        body: data.body,
        message_type: data.message_type
      });
      showNotice("#compose-notice", "Message sent.");
      form.reset();
    } catch (error) {
      showNotice("#compose-notice", error.message, "error");
    }
  });

  requireAuth().then(loadOptions).catch((error) => showNotice("#compose-notice", error.message, "error"));
});
